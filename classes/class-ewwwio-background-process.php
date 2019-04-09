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
		 * Either an 'a' or a 'b', depending on which one is currently running.
		 *
		 * @var string
		 * @access protected
		 */
		protected $active_queue;

		/**
		 * Either an 'a' or a 'b', depending on which one is NOT currently running.
		 *
		 * @var string
		 * @access protected
		 */
		protected $second_queue;

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
		 *
		 * @return $this
		 */
		public function push_to_queue( $data ) {
			$this->data[] = $data;

			return $this;
		}

		/**
		 * Save queue
		 *
		 * @return $this
		 */
		public function save() {
			$key = $this->generate_key();
			ewwwio_debug_message( "queue $key will be saved to" );
			if ( ! empty( $this->data ) ) {
				$existing_data = get_option( $key );
				if ( ! empty( $existing_data ) ) {
					$this->data = array_merge( $existing_data, $this->data );
				}
				update_option( $key, $this->data, false );
			}
			$this->data = array();
			return $this;
		}

		/**
		 * Update queue
		 *
		 * @param string $key Key.
		 * @param array  $data Data.
		 *
		 * @return $this
		 */
		public function update( $key, $data ) {
			if ( ! empty( $data ) ) {
				$existing_data = get_option( $key );
				if ( ! empty( $existing_data ) ) {
					update_option( $key, $data, false );
				}
			}

			return $this;
		}

		/**
		 * Delete queue
		 *
		 * @param string $key Key.
		 *
		 * @return $this
		 */
		public function delete( $key ) {
			update_option( $key, '' );

			return $this;
		}

		/**
		 * Generate key
		 *
		 * Generates a unique key based on microtime. Queue items are
		 * given a unique key so that they can be merged upon save.
		 *
		 * @param int $length Length.
		 *
		 * @return string
		 */
		protected function generate_key( $length = 64 ) {
			$unique = 'a';
			if ( $this->is_queue_active( $unique ) ) {
				$unique = 'b';
			}
			$this->second_queue = $unique;
			$prepend            = $this->identifier . '_batch_';

			return substr( $prepend . $unique, 0, $length );
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
				wp_die();
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				wp_die();
			}

			check_ajax_referer( $this->identifier, 'nonce' );

			$this->handle();

			wp_die();
		}

		/**
		 * Count items in queue.
		 *
		 * @return bool
		 */
		public function count_queue() {
			global $wpdb;

			$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

			$queues = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_value
					FROM $wpdb->options
					WHERE option_name LIKE %s AND option_value != ''",
					$key
				),
				ARRAY_A
			);
			if ( empty( $queues ) ) {
				return 0;
			}
			$queued = array();
			foreach ( $queues as $queue ) {
				$queue  = maybe_unserialize( $queue['option_value'] );
				$queued = array_merge( $queued, $queue );
			}
			return count( $queued );
		}

		/**
		 * Is queue empty
		 *
		 * @return bool
		 */
		protected function is_queue_empty() {
			global $wpdb;

			$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM $wpdb->options
					WHERE option_name LIKE %s AND option_value != ''",
					$key
				)
			);

			return ( $count > 0 ) ? false : true;
		}

		/**
		 * Is process running
		 *
		 * Check whether the current process is already running
		 * in a background process.
		 *
		 * @return bool
		 */
		protected function is_process_running() {
			if ( get_transient( $this->identifier . '_process_lock' ) ) {
				// Process already running.
				return true;
			}

			return false;
		}

		/**
		 * Is a particular queue active and running.
		 *
		 * @param string $queue_id The identifier for a background queue.
		 * @return bool
		 */
		protected function is_queue_active( $queue_id ) {
			global $wpdb;
			$process_lock_transient = '_transient_' . $this->identifier . '_process_lock';
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name LIKE %s", $process_lock_transient ) ) === $queue_id ) {
				ewwwio_debug_message( "queue $queue_id is running" );
				return true;
			}
			ewwwio_debug_message( "queue $queue_id is not running, checked with: " . $this->identifier . '_process_lock' );
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
			if ( empty( $this->active_queue ) ) {
				$this->active_queue = 'a';
			}
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
		 * @return stdClass Return the first batch from the queue
		 */
		protected function get_batch() {
			global $wpdb;

			$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

			$query = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->options WHERE option_name LIKE %s AND option_value != '' ORDER BY option_id ASC LIMIT 1",
					$key
				)
			);

			$batch              = new stdClass();
			$batch->key         = $query->option_name;
			$batch->data        = maybe_unserialize( $query->option_value );
			$this->active_queue = substr( $batch->key, -1 );
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

				foreach ( $batch->data as $key => $value ) {
					$task = $this->task( $value );

					if ( false !== $task ) {
						$batch->data[ $key ] = $task;
					} else {
						unset( $batch->data[ $key ] );
					}

					if ( $this->time_exceeded() || $this->memory_exceeded() ) {
						// Batch limits reached.
						break;
					}
				}

				// Update or delete current batch.
				if ( ! empty( $batch->data ) ) {
					$this->update( $batch->key, $batch->data );
				} else {
					$this->delete( $batch->key );
				}
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}

			wp_die();
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
			if ( ! $this->is_queue_empty() ) {
				$batch = $this->get_batch();

				$this->delete( $batch->key );

				wp_clear_scheduled_hook( $this->cron_hook_identifier );
			}

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

	}
}
