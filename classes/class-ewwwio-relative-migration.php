<?php
/**
 * Class file for EWWWIO_Relative_Migration
 *
 * Performs the migration from storing absolute paths to using relative paths in the ewwwio_images table.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 * @since 4.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates absolute paths to relative paths (once).
 */
class EWWWIO_Relative_Migration {

	/**
	 * The offset/position in the database.
	 *
	 * @var int $id
	 */
	private $offset = 0;

	/**
	 * The time when we started processing.
	 *
	 * @var int $started
	 */
	private $started;

	/**
	 * Sets up the the migration.
	 */
	function __construct() {
		if ( 'done' === get_option( 'ewww_image_optimizer_relative_migration_status' ) ) {
			return;
		}
		if ( ! $this->table_exists() ) {
			$this->unschedule();
			update_option( 'ewww_image_optimizer_relative_migration_status', 'done' );
			delete_option( 'ewww_image_optimizer_relative_migration_offset' );
		}
		if ( ! get_option( 'ewww_image_optimizer_relative_migration_status' ) ) {
			update_option( 'ewww_image_optimizer_relative_migration_status', 'started' );
		}
		$this->maybe_schedule();
	}

	/**
	 * Check to see if the ewwwio_images table actually exists.
	 *
	 * @return bool True if does, false if it don't.
	 */
	private function table_exists() {
		global $wpdb;
		return $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->ewwwio_images'" ) === $wpdb->ewwwio_images;
	}

	/**
	 * Retrieves a batch of records based on the current offset.
	 */
	private function get_records() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$query   = "SELECT id,path,updated FROM $ewwwdb->ewwwio_images WHERE pending=0 AND image_size > 0 ORDER BY id DESC LIMIT $this->offset,500";
		$records = $ewwwdb->get_results( $query, ARRAY_A );

		$this->offset += 500;
		if ( is_array( $records ) ) {
			return $records;
		}
		return array();
	}

	/**
	 * Called via wp_cron to initiate the migration effort.
	 */
	public function migrate() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->started = time();
		$this->offset  = (int) get_option( 'ewww_image_optimizer_relative_migration_offset' );
		$records       = $this->get_records();
		ewwwio_debug_message( 'starting at ' . gmdate( 'Y-m-d H:i:s', $this->started ) . " with offset $this->offset" );
		while ( ! empty( $records ) ) {
			foreach ( $records as $record ) {
				if ( $this->already_migrated( $record['path'] ) ) {
					ewwwio_debug_message( 'already migrated' );
					continue;
				}
				// Relativize the path, and store it back in the db.
				$relative_path = ewww_image_optimizer_relativize_path( $record['path'] );
				if ( $record['path'] !== $relative_path ) {
					$record['path'] = $relative_path;
					$this->update_relative_record( $record );
				}
			}
			if ( time() - $this->started > 20 ) {
				update_option( 'ewww_image_optimizer_relative_migration_offset', $this->offset, false );
				return;
			}
			$records = $this->get_records();
		}
		$this->unschedule();
		update_option( 'ewww_image_optimizer_relative_migration_status', 'done' );
		delete_option( 'ewww_image_optimizer_relative_migration_offset' );
	}

	/**
	 * Checks to see if the record already has been migrated.
	 *
	 * @param string $path Path of file as retrieved from database.
	 */
	private function already_migrated( $path ) {
		if ( strpos( $path, 'EWWW_IMAGE_OPTIMIZER_RELATIVE_FOLDER' ) === 0 ) {
			return true;
		}
		if ( strpos( $path, 'ABSPATH' ) === 0 ) {
			return true;
		}
		if ( strpos( $path, 'WP_CONTENT_DIR' ) === 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Updates the db record with the relativized path.
	 *
	 * @param array $record Includes a relative path, the ID, and the updated timestamp.
	 */
	private function update_relative_record( $record ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'path'    => $record['path'],
				'updated' => $record['updated'],
			),
			array(
				'id' => $record['id'],
			)
		);
	}

	/**
	 * Schedule the migration.
	 */
	private function maybe_schedule() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Create 5 minute wp_cron schedule.
		add_filter( 'cron_schedules', array( $this, 'add_migration_schedule' ) );
		add_action( 'ewww_image_optimizer_relative_migration', array( $this, 'migrate' ) );
		// Schedule migration function.
		if ( ! wp_next_scheduled( 'ewww_image_optimizer_relative_migration' ) ) {
			ewwwio_debug_message( 'scheduling migration' );
			wp_schedule_event( time(), 'ewwwio_relative_migration_interval', 'ewww_image_optimizer_relative_migration' );
		}
	}
	/**
	 * Clean up the scheduled event.
	 */
	private function unschedule() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$timestamp = wp_next_scheduled( 'ewww_image_optimizer_relative_migration' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ewww_image_optimizer_relative_migration' );
		}
	}


	/**
	 * Adds a custom cron schedule: every 5 minutes.
	 *
	 * @param array $schedules An array of custom cron schedules.
	 */
	public function add_migration_schedule( $schedules ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$schedules['ewwwio_relative_migration_interval'] = array(
			'interval' => MINUTE_IN_SECONDS * 5,
			'display'  => 'Every 5 Minutes until complete',
		);
		return $schedules;
	}
}
new EWWWIO_Relative_Migration();
