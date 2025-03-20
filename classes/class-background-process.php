<?php
/**
 * EWWWIO Background Process
 *
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Background_Process class.
 *
 * @abstract
 * @extends EWWW\Async_Request
 */
abstract class Background_Process extends Async_Request {

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
	 * Time limit.
	 *
	 * @var int
	 * @access protected
	 */
	protected $time_limit = 20;

	/**
	 * Cron_hook_identifier
	 *
	 * @var mixed
	 * @access protected
	 */
	protected $cron_hook_identifier;

	/**
	 * Cron health check interval.
	 *
	 * @var int
	 * @access protected
	 */
	protected $cron_interval = 5;

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
	 * A unique key for each background process. Used to prevent duplication.
	 *
	 * @var string
	 * @access protected
	 */
	protected $lock_key;

	/**
	 * Amount of time to set the "process lock" transient.
	 *
	 * @var int
	 * @access protected
	 */
	protected $queue_lock_time = 90; // in seconds.

	/**
	 * Directory in which to store process locks, if writable.
	 *
	 * @var string
	 * @access protected
	 */
	protected $lock_dir;

	/**
	 * Initiate new background process
	 */
	public function __construct() {
		parent::__construct();

		$this->lock_dir = $this->get_lock_dir();

		$this->cron_hook_identifier     = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->identifier . '_cron_interval';

		add_action( $this->cron_hook_identifier, array( $this, 'handle_cron_healthcheck' ) );
		add_filter( 'cron_schedules', array( $this, 'add_healthcheck_cron_schedule' ) );
	}

	/**
	 * Dispatch
	 *
	 * @access public
	 * @return array The wp_remote_post response.
	 */
	public function dispatch() {
		if ( did_action( 'init' ) ) {
			// Schedule the cron healthcheck.
			$this->schedule_event();
		} else {
			add_action( 'init', array( $this, 'schedule_event' ) );
		}

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

		$id           = (int) $data['id'];
		$new          = ! empty( $data['new'] ) ? 1 : 0;
		$convert_once = ! empty( $data['convert_once'] ) ? 1 : 0;
		$force_reopt  = ! empty( $data['force_reopt'] ) ? 1 : 0;
		$force_smart  = ! empty( $data['force_smart'] ) ? 1 : 0;
		$webp_only    = ! empty( $data['webp_only'] ) ? 1 : 0;
		if ( ! $id ) {
			return;
		}

		$exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_queue WHERE attachment_id = %d AND gallery = %s LIMIT 1", $id, $this->active_queue ) );
		if ( empty( $exists ) ) {
			$to_insert = array(
				'attachment_id' => $id,
				'gallery'       => $this->active_queue,
				'new'           => $new,
				'convert_once'  => $convert_once,
				'force_reopt'   => $force_reopt,
				'force_smart'   => $force_smart,
				'webp_only'     => $webp_only,
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
			$wpdb->get_row( $wpdb->prepare( "UPDATE $wpdb->ewwwio_queue SET scanned=scanned+1 WHERE id = %d LIMIT 1", $id ) );
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
				'id' => $key,
			),
			array( '%d' )
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

		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		\ewwwio_debug_message( "$this->identifier checking for valid nonce" );
		\check_ajax_referer( $this->identifier, 'nonce' );

		if ( ! empty( $_REQUEST['lock_key'] ) ) {
			$this->lock_key = \sanitize_text_field( \wp_unslash( $_REQUEST['lock_key'] ) );
		}
		\ewwwio_debug_message( "nonce was valid, lock key is $this->lock_key" );

		if ( $this->is_process_running() && ! $this->is_key_valid() ) {
			// Background process already running.
			\ewwwio_debug_message( 'background process already running and the submitted lock key is not the active/valid key' );
			die;
		}

		\ewwwio_debug_message( 'not already running, checking queue' );

		if ( $this->is_queue_empty() ) {
			// No data to process.
			\ewwwio_debug_message( 'nothing in the queue, bye!' );
			die;
		}

		\ewwwio_debug_message( 'queue has items, lets handle them...' );

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
	 * Is process running?
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 *
	 * @return bool
	 */
	public function is_process_running() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->get_process_lock() ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Get the process lock directory, if allowed.
	 *
	 * @return string The lock directory to use, or an empty string.
	 */
	protected function get_lock_dir() {
		if (
			\is_writable( EWWWIO_CONTENT_DIR ) &&
			\function_exists( '\filemtime' ) &&
			empty( $_ENV['PANTHEON_ENVIRONMENT'] ) &&
			\apply_filters( 'ewww_image_optimizer_async_disk_locking', true )
		) {
			return EWWWIO_CONTENT_DIR;
		}
	}

	/**
	 * Is disk-based lock valid?
	 *
	 * @param string $lock_file Location of the process lock file.
	 * @return bool True if it is valid, false if it is expired.
	 */
	protected function is_disk_lock_valid( $lock_file ) {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$lock_duration = \apply_filters( $this->identifier . '_queue_lock_time', $this->queue_lock_time );
		\clearstatcache();
		if ( \ewwwio_is_file( $lock_file ) && \time() - \filemtime( $lock_file ) < $lock_duration ) {
			\ewwwio_debug_message( 'process lock file in place' );
			return true;
		}
		\ewwwio_debug_message( 'process lock file gone or expired' );
		return false;
	}

	/**
	 * Get the process lock/key from disk or transient.
	 *
	 * @return bool|string The key in the lock, if one exists, false otherwise.
	 */
	protected function get_process_lock() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$db_key = false;
		if ( $this->lock_dir ) {
			\ewwwio_debug_message( "lock dir is $this->lock_dir" );
			$lock_file = $this->process_lock_file();
			\ewwwio_debug_message( "checking $lock_file" );
			if ( $this->is_disk_lock_valid( $lock_file ) ) {
				$db_key = \trim( \file_get_contents( $lock_file ) );
				\ewwwio_debug_message( "retrieved lock key: $db_key" );
			}
		} else {
			$db_key = \get_transient( $this->identifier . '_process_lock' );
		}
		return $db_key;
	}

	/**
	 * Build the filename to the disk-based process lock file.
	 *
	 * @return string The filename to use for the lock file.
	 */
	protected function process_lock_file() {
		return $this->lock_dir . '.' . $this->identifier . '_process_lock';
	}

	/**
	 * Is process unique?
	 *
	 * Check the lock value/transient against the key for this process to ensure they match.
	 *
	 * @return bool
	 */
	protected function is_key_valid() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$stored_key = $this->get_process_lock();
		\ewwwio_debug_message( "stored key is $stored_key" );
		\ewwwio_debug_message( "process key is $this->lock_key" );
		if ( ! empty( $this->lock_key ) && $stored_key === $this->lock_key ) {
			// Process is unique because db key still matches the key for this process.
			return true;
		}

		return false;
	}

	/**
	 * Update (or initialize) process lock
	 *
	 * Update the process lock so that other instances do not spawn.
	 */
	protected function update_lock() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! empty( $this->active_queue ) ) {
			if ( empty( $this->lock_key ) ) {
				$this->lock_key = \uniqid( $this->active_queue, true ) . $this->generate_key_suffix();
				\ewwwio_debug_message( "no key, generated: $this->lock_key" );
			} else {
				\ewwwio_debug_message( "using existing key: $this->lock_key" );
			}
			if ( $this->lock_dir ) {
				$written = \file_put_contents( $this->process_lock_file(), $this->lock_key );
				if ( $written ) {
					\ewwwio_debug_message( 'saved key to lock file' );
				} else {
					\ewwwio_debug_message( 'zero bytes written' );
				}
			} else {
				$lock_duration = \apply_filters( $this->identifier . '_queue_lock_time', $this->queue_lock_time );
				\set_transient( $this->identifier . '_process_lock', $this->lock_key, $lock_duration );
				\ewwwio_debug_message( "transient locking, stored $this->lock_key in " . $this->identifier . "_process_lock for $lock_duration" );
			}
		}
	}

	/**
	 * Generate a random alpha-numeric suffix for the lock key.
	 *
	 * @return string A random alpha-numeric string with 5-10 characters.
	 */
	protected function generate_key_suffix() {
		$suffix = '';
		$chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		$length = random_int( 5, 10 );
		while ( strlen( $suffix ) < $length ) {
			$suffix .= substr( $chars, random_int( 0, 61 ), 1 );
		}
		return $suffix;
	}

	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @return $this
	 */
	protected function unlock_process() {
		if ( $this->lock_dir ) {
			\ewwwio_delete_file( $this->process_lock_file() );
		}
		\delete_transient( $this->identifier . '_process_lock' );

		return $this;
	}

	/**
	 * Get batch
	 *
	 * @return array Return the first batch from the queue
	 */
	protected function get_batch() {
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		$batch = $wpdb->get_results( $wpdb->prepare( "SELECT id, attachment_id, scanned AS attempts, new, convert_once, force_reopt, force_smart, webp_only FROM $wpdb->ewwwio_queue WHERE gallery = %s ORDER BY id LIMIT %d", $this->active_queue, $this->limit ), ARRAY_A );
		if ( empty( $batch ) ) {
			return array();
		}
		\ewwwio_debug_message( "selected items for {$this->active_queue}: " . count( $batch ) );

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
		\ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->start_time = \time(); // Set start time of current process.

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

		// For most queues, it is sufficient to only check once per batch. Individual queues may check on each item if needed.
		if ( ! $this->is_key_valid() ) {
			// There is another process running.
			die;
		}

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

		return \apply_filters( $this->identifier . '_memory_exceeded', $return );
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

		return \wp_convert_hr_to_bytes( $memory_limit );
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
		$finish = $this->start_time + \apply_filters( $this->identifier . '_default_time_limit', $this->time_limit ); // 20 seconds
		$return = false;

		if ( time() >= $finish ) {
			$return = true;
		}

		return \apply_filters( $this->identifier . '_time_exceeded', $return );
	}

	/**
	 * Complete.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		$this->unlock_process();

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
	public function add_healthcheck_cron_schedule( $schedules ) {
		$interval    = \apply_filters( $this->identifier . '_cron_interval', $this->cron_interval );
		$description = \sprintf( 'Every %d Minutes', $interval );
		if ( \did_action( 'init' ) || doing_action( 'init' ) ) {
			/* translators: %d: number of minutes */
			$description = \sprintf( __( 'Every %d Minutes', 'ewww-image-optimizer' ), $interval );
		}

		// Adds every X (default=5) minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = array(
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display'  => $description,
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
	public function schedule_event() {
		if ( ! \wp_next_scheduled( $this->cron_hook_identifier ) ) {
			\wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		$timestamp = \wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			\wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
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
		\wp_clear_scheduled_hook( $this->cron_hook_identifier );
		$this->unlock_process();
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
