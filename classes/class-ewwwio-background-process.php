<?php
/**
 * EWWWIO Background Process
 *
 * @package EWWW_Image_Optimizer
 */

if ( ! class_exists( 'EWWWIO_Background_Process' ) ) {

	/**
	 * Abstract EWWWIO_Background_Process class.
	 *
	 * @abstract
	 * @extends WP_Async_Request
	 */
	abstract class EWWWIO_Background_Process extends WP_Async_Request {

		/**
		 * Action
		 *
		 * (default value: 'background_process')
		 *
		 * @var string
		 * @access protected
		 */
		protected $action = 'background_process';

		/**
		 * Start time of current process.
		 *
		 * (default value: 0)
		 *
		 * @var int
		 * @access protected
		 */
		protected $start_time = 0;

		/**
		 * Batch size limit.
		 *
		 * @var int
		 * @access protected
		 */
		protected $limit = 50;

		/**
		 * Attempts limit.
		 *
		 * @var int
		 * @access protected
		 */
		protected $max_attempts = 15;

		/**
		 * Cron_hook_identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $cron_hook_identifier;

		/**
		 * Cron_interval_identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $cron_interval_identifier;

		/**
		 * A unique identifier for each background class extension.
		 *
		 * @var string
		 * @access protected
		 */
		protected $active_queue;

		/**
		 * Initiate new background process
		 */
		public function __construct() {
			parent::__construct();

			$this->cron_hook_identifier     = $this->identifier . '_cron';
			$this->cron_interval_identifier = $this->identifier . '_cron_interval';

			add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
			add_filter( 'cron_schedules', array( $this, 'schedule_cron_healthcheck' ) );
		}

		/**
		 * Dispatch
		 *
		 * @access public
		 * @return array The wp_remote_post response.
		 */
		public function dispatch() {
			// Schedule the cron healthcheck.
			$this->schedule_event();

			// Perform remote post.
			return parent::dispatch();
		}

		/**
		 * Push to queue
		 *
		 * @param mixed $data Data.
		 */
		public function push_to_queue( $data ) {
			global $wpdb;

			$id  = (int) $data['id'];
			$new = ! empty( $data['new'] ) ? 1 : 0;
			if ( ! $id ) {
				return;
			}

			$exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_queue WHERE attachment_id = %d AND gallery = %s LIMIT 1", $id, $this->active_queue ) );
			if ( empty( $exists ) ) {
				$to_insert = array(
					'attachment_id' => $id,
					'gallery'       => $this->active_queue,
					'new'           => $new,
				);
				$wpdb->insert( $wpdb->ewwwio_queue, $to_insert );
			}
		}

		/**
		 * Update queue item
		 *
		 * @param int   $id ID of queue item.
		 * @param array $data Data related to queue item.
		 */
		public function update( $id, $data = array() ) {
			if ( ! empty( $id ) ) {
				global $wpdb;
				$wpdb->get_row( $wpdb->prepare( "UPDATE $wpdb->ewwwio_queue SET scanned=scanned+1 WHERE attachment_id = %d AND gallery = %s LIMIT 1", $id, $this->active_queue ) );
			}
		}

		/**
		 * Delete queue item
		 *
		 * @param string $key Key.
		 */
		public function delete( $key ) {
			if ( ! $key ) {
				return;
			}
			$key = (int) $key;
			global $wpdb;
			$wpdb->delete(
				$wpdb->ewwwio_queue,
				array(
					'attachment_id' => $key,
					'gallery'       => $this->active_queue,
				),
				array( '%d', '%s' )
			);
		}

		/**
		 * Maybe process queue
		 *
		 * Checks whether data exists within the queue and that
		 * the process is not already running.
		 */
		public function maybe_handle() {
			session_write_close();

			if ( $this->is_process_running() ) {
				// Background process already running.
				die;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				die;
			}

			check_ajax_referer( $this->identifier, 'nonce' );

			$this->handle();

			die;
		}

		/**
		 * Count items in queue.
		 *
		 * @return bool
		 */
		public function count_queue() {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->ewwwio_queue WHERE gallery = %s", $this->active_queue ) );
		}

		/**
		 * Is queue empty
		 *
		 * @return bool
		 */
		protected function is_queue_empty() {
			return ! $this->count_queue();
		}

		/**
		 * Is process running
		 *
		 * Check whether the current process is already running
		 * in a background process.
		 *
		 * @return bool
		 */
		public function is_process_running() {
			if ( get_transient( $this->identifier . '_process_lock' ) ) {
				// Process already running.
				return true;
			}

			return false;
		}

		/**
		 * Lock process
		 *
		 * Lock the process so that multiple instances can't run simultaneously.
		 * Override if applicable, but the duration should be greater than that
		 * defined in the time_exceeded() method.
		 */
		protected function lock_process() {
			$this->start_time = time(); // Set start time of current process.

			$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
			$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );
		}

		/**
		 * Update process lock
		 *
		 * Update the process lock so that other instances do not spawn.
		 *
		 * @return $this
		 */
		protected function update_lock() {
			if ( empty( $this->active_queue ) ) {
				return;
			}
			$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
			$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );
			set_transient( $this->identifier . '_process_lock', $this->active_queue, $lock_duration );
		}

		/**
		 * Unlock process
		 *
		 * Unlock the process so that other instances can spawn.
		 *
		 * @return $this
		 */
		protected function unlock_process() {
			delete_transient( $this->identifier . '_process_lock' );

			return $this;
		}

		/**
		 * Get batch
		 *
		 * @return array Return the first batch from the queue
		 */
		protected function get_batch() {
			global $wpdb;
			$batch = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id AS id, scanned AS attempts, new FROM $wpdb->ewwwio_queue WHERE gallery = %s LIMIT %d", $this->active_queue, $this->limit ), ARRAY_A );
			if ( empty( $batch ) ) {
				return array();
			}
			ewwwio_debug_message( 'selected items: ' . count( $batch ) );

			$this->update_lock();
			return $batch;
		}

		/**
		 * Handle
		 *
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 */
		protected function handle() {
			$this->lock_process();

			do {
				$batch = $this->get_batch();

				foreach ( $batch as $key => $value ) {
					if ( $value['attempts'] > $this->max_attempts ) {
						$this->failure( $value );
						$this->delete( $value['id'] );
						continue;
					}
					$this->update( $value['id'], $value );
					$task = $this->task( $value );

					if ( false !== $task ) {
						$batch[ $key ] = $task;
					} else {
						$this->delete( $value['id'] );
					}

					if ( $this->time_exceeded() || $this->memory_exceeded() ) {
						// Batch limits reached.
						break;
					}
				}
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}

			die;
		}

		/**
		 * Memory exceeded
		 *
		 * Ensures the batch process never exceeds 90%
		 * of the maximum WordPress memory.
		 *
		 * @return bool
		 */
		protected function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_memory_exceeded', $return );
		}

		/**
		 * Get memory limit
		 *
		 * @return int
		 */
		protected function get_memory_limit() {
			if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
				return 128 * MB_IN_BYTES;
			}
			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				// Sensible default.
				$memory_limit = '128M';
			}
			if ( ! $memory_limit || -1 === (int) $memory_limit ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32G';
			}

			return wp_convert_hr_to_bytes( $memory_limit );
		}

		/**
		 * Time exceeded.
		 *
		 * Ensures the batch never exceeds a sensible time limit.
		 * A timeout limit of 30s is common on shared hosting.
		 *
		 * @return bool
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + apply_filters( $this->identifier . '_default_time_limit', 20 ); // 20 seconds
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return apply_filters( $this->identifier . '_time_exceeded', $return );
		}

		/**
		 * Complete.
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			// Unschedule the cron healthcheck.
			$this->clear_scheduled_event();
		}

		/**
		 * Schedule cron healthcheck
		 *
		 * @access public
		 * @param mixed $schedules Schedules.
		 * @return mixed
		 */
		public function schedule_cron_healthcheck( $schedules ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', 5 );

			if ( property_exists( $this, 'cron_interval' ) ) {
				$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval );
			}

			// Adds every 5 minutes to the existing schedules.
			$schedules[ $this->identifier . '_cron_interval' ] = array(
				'interval' => MINUTE_IN_SECONDS * $interval,
				/* translators: %d: number of minutes */
				'display'  => sprintf( __( 'Every %d Minutes' ), $interval ),
			);

			return $schedules;
		}

		/**
		 * Handle cron healthcheck
		 *
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 */
		public function handle_cron_healthcheck() {
			if ( $this->is_process_running() ) {
				// Background process already running.
				exit;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				$this->clear_scheduled_event();
				exit;
			}

			$this->handle();

			exit;
		}

		/**
		 * Schedule event
		 */
		protected function schedule_event() {
			if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
				wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
			}
		}

		/**
		 * Clear scheduled event
		 */
		protected function clear_scheduled_event() {
			$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
			}
		}

		/**
		 * Cancel Process
		 *
		 * Stop processing queue items, clear cronjob and delete batch.
		 */
		public function cancel_process() {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", $this->active_queue ) );
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param mixed $item Queue item to iterate over.
		 *
		 * @return mixed
		 */
		abstract protected function task( $item );

		/**
		 * Failure
		 *
		 * Override this method to perform any actions required when a
		 * queue item reaches the maximum retries. Will be removed
		 * from the queue after this fires.
		 *
		 * @param mixed $item Queue item entering failure condition.
		 */
		abstract protected function failure( $item );
	}
}
