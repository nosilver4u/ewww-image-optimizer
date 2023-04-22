<?php
/**
 * Implements backup/restore functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backup & Restore images from both local and cloud locations.
 */
class Backup extends Base {

	/**
	 * An error from a restore operation.
	 *
	 * @access protected
	 * @var string $error_message
	 */
	protected $error_message = '';

	/**
	 * A list of exclusions.
	 *
	 * @access protected
	 * @var array $exclusions
	 */
	protected $exclusions = array();

	/**
	 * Backup mode (local/cloud).
	 *
	 * @var string $backup_mode
	 */
	protected $backup_mode = '';

	/**
	 * Backup location.
	 *
	 * @var string $backup_dir
	 */
	protected $backup_dir = '';

	/**
	 * Backup location for media uploads.
	 *
	 * @var string $backup_uploads_dir
	 */
	protected $backup_uploads_dir = '';

	/**
	 * Backup location for images outside the wp-content directory.
	 *
	 * @var string $backup_root_dir
	 */
	protected $backup_root_dir = '';

	/**
	 * Register (once) actions and filters for Backup and Restore.
	 */
	function __construct() {
		global $eio_backup;
		if ( \is_object( $eio_backup ) ) {
			return $eio_backup;
		}
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( 'local' === $this->get_option( 'ewww_image_optimizer_backup_files' ) ) {
			$this->backup_mode = 'local';
			// Sub-folders of the content directory will be stored directly in the image-backup/ folder.
			$this->backup_dir = \trailingslashit( $this->content_dir ) . \trailingslashit( 'image-backup' );
			// Sub-folders of the content directory will be stored directly in the image-backup/ folder.
			$this->backup_uploads_dir = \trailingslashit( $this->backup_dir ) . \trailingslashit( 'uploads' );
			// Folders outside the content dir, and relative to ABSPATH will be stored in the root/ directory.
			$this->backup_root_dir = \trailingslashit( $this->backup_dir ) . \trailingslashit( 'root' );
		} elseif ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) && $this->get_option( 'ewww_image_optimizer_backup_files' ) ) {
			$this->backup_mode = 'cloud';
		}

		// AJAX action hook for manually restoring a single image from cloud/local backups.
		\add_action( 'wp_ajax_ewww_manual_image_restore_single', array( $this, 'restore_single_image_handler' ) );
		\add_action( 'ewww_image_optimizer_pre_optimization', array( $this, 'store_local_backup' ) );

		$this->exclusions = array(
			$this->content_dir,
			'/wp-admin/',
			'/wp-includes/',
			'/cache/',
			'/dynamic/', // Nextgen dynamic images.
		);
		$this->exclusions = \apply_filters( 'ewww_image_optimizer_backup_exclusions', $this->exclusions );
	}

	/**
	 * Gets the error message from the most recent restore operation, if any.
	 *
	 * @return string An error message.
	 */
	public function get_error() {
		return (string) $this->error_message;
	}

	/**
	 * Sets the error message for restore operations.
	 *
	 * @param string $error An error message.
	 */
	public function throw_error( $error ) {
		if ( \is_string( $error ) ) {
			$this->error_message = \sanitize_text_field( $error );
		}
	}

	/**
	 * Checks whether a file is in the uploads dir, content dir, or within the ABSPATH/root.
	 *
	 * This helps to deal with cases where folks have upload and/or content dirs outside ABSPATH.
	 *
	 * @param string $file The filename to backup.
	 * @return string The backup location for the file.
	 */
	public function get_backup_location( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( \ewww_image_optimizer_stream_wrapped( $file ) || 'local' !== $this->backup_mode ) {
			return '';
		}
		$upload_dir = \wp_get_upload_dir();
		$upload_dir = \trailingslashit( \realpath( $upload_dir['basedir'] ) );
		if ( $upload_dir && \strpos( $file, $upload_dir ) === 0 ) {
			$this->debug_message( 'using ' . $this->backup_uploads_dir );
			return \str_replace( $upload_dir, $this->backup_uploads_dir, $file );
		}
		$content_dir = \trailingslashit( \realpath( \WP_CONTENT_DIR ) );
		if ( $content_dir && \strpos( $file, $content_dir ) === 0 ) {
			$this->debug_message( 'using ' . $this->backup_dir );
			return \str_replace( $content_dir, $this->backup_dir, $file );
		}
		$wp_dir = \trailingslashit( \realpath( \ABSPATH ) );
		if ( $wp_dir && \strpos( $file, $wp_dir ) === 0 ) {
			$this->debug_message( 'using ' . $this->backup_root_dir );
			return \str_replace( $wp_dir, $this->backup_root_dir, $file );
		}
		return '';
	}

	/**
	 * Checks to see if a backup is available for a given file.
	 *
	 * @param string $file The image file to search for a backup.
	 * @param array  $record The database record for the file. Optional.
	 * @return bool True if a backup is available, false otherwise.
	 */
	public function is_backup_available( $file, $record = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$file = \ewww_image_optimizer_absolutize_path( $file );
		if ( 'local' === $this->backup_mode ) {
			\clearstatcache();
			$backup_file = $this->get_backup_location( $file );
			return $this->is_file( $backup_file );
		} elseif ( 'cloud' === $this->backup_mode ) {
			if ( ! $record || ! isset( $record['backup'] ) || ! isset( $record['updated'] ) ) {
				$record = \ewww_image_optimizer_find_already_optimized( $file );
			}
			if ( $record && $this->is_iterable( $record ) && ! empty( $record['backup'] ) && ! empty( $record['updated'] ) ) {
				$updated_time = \strtotime( $record['updated'] );
				if ( \DAY_IN_SECONDS * 30 + $updated_time > \time() ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Backup a file to local or cloud storage.
	 *
	 * @param string $file Name of the file to backup.
	 */
	public function backup_file( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "file: $file " );
		foreach ( $this->exclusions as $exclusion ) {
			if ( false !== \strpos( $file, $exclusion ) ) {
				return;
			}
		}
		if ( 'local' === $this->backup_mode ) {
			$this->store_local_backup( $file );
		} elseif ( 'cloud' === $this->backup_mode ) {
			$this->store_cloud_backup( $file );
		}
	}

	/**
	 * Copy a file to the backup location.
	 *
	 * @param string $file Name of the file to backup.
	 */
	public function store_local_backup( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( 'local' !== $this->backup_mode ) {
			return;
		}
		if ( ! $this->is_file( $file ) || ! $this->is_readable( $file ) ) {
			return;
		}
		$backup_file = $this->get_backup_location( $file );
		if ( ! $backup_file || $backup_file === $file ) {
			return;
		}
		\clearstatcache();
		if ( $this->is_file( $backup_file ) ) {
			return;
		}
		\wp_mkdir_p( \dirname( $backup_file ) );
		\clearstatcache();
		if ( ! \is_writable( \dirname( $backup_file ) ) ) {
			return;
		}
		$this->debug_message( "backing up $file to $backup_file" );
		\copy( $file, $backup_file );
		if ( $this->filesize( $file ) !== $this->filesize( $backup_file ) ) {
			// In order to not store bogus files.
			$this->delete_file( $backup_file );
		}
	}

	/**
	 * Send a file to the API for backup.
	 *
	 * @param string $file Name of the file to backup.
	 */
	protected function store_cloud_backup( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\ewww_image_optimizer_cloud_backup( $file );
	}

	/**
	 * Restore an image from local or cloud storage.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
	 *
	 * @param int|array $image The db record/ID of the image to restore.
	 * @return bool True if the image was restored successfully.
	 */
	public function restore_file( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( \strpos( $wpdb->charset, 'utf8' ) === false ) {
			\ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$this->error_message = '';
		if ( ! \is_array( $image ) && ! empty( $image ) && \is_numeric( $image ) ) {
			$image = $ewwwdb->get_row( "SELECT id,path,backup FROM $ewwwdb->ewwwio_images WHERE id = $image", \ARRAY_A );
		}
		if ( ! empty( $image['path'] ) ) {
			$image['path'] = \ewww_image_optimizer_absolutize_path( $image['path'] );
		}
		if ( empty( $image['path'] ) ) {
			return false;
		}
		if ( 'local' === $this->backup_mode ) {
			return $this->restore_from_local( $image );
		} elseif ( 'cloud' === $this->backup_mode ) {
			return $this->restore_from_cloud( $image );
		}
		return false;
	}

	/**
	 * Restore a file from a local backup location.
	 *
	 * @param array $image The db record of the image to restore.
	 */
	protected function restore_from_local( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( 'local' !== $this->backup_mode ) {
			return false;
		}
		$file = $image['path'];
		if ( ! \is_writable( \dirname( $file ) ) ) {
			$this->debug_message( "$file (or the parent dir) is not writable" );
			/* translators: %s: An image filename */
			$this->error_message = \sprintf( \__( '%s is not writable.', 'ewww-image-optimizer' ), $file );
			return false;
		}
		$backup_file = $this->get_backup_location( $file );
		if ( ! $backup_file || $backup_file === $file ) {
			$this->debug_message( "$backup_file is not a valid backup location for $file" );
			/* translators: %s: An image filename */
			$this->error_message = \sprintf( \__( 'Could not determine backup location for %s.', 'ewww-image-optimizer' ), $file );
			return false;
		}
		\clearstatcache();
		if ( ! $this->is_file( $backup_file ) ) {
			$this->debug_message( "$backup_file does not exist" );
			/* translators: %s: An image filename */
			$this->error_message = \sprintf( \__( 'No backup available for %s.', 'ewww-image-optimizer' ), $file );
			return false;
		}
		if ( \ewww_image_optimizer_mimetype( $file, 'i' ) !== \ewww_image_optimizer_mimetype( $backup_file, 'i' ) ) {
			$this->debug_message( "$backup_file is different type than $file " . \ewww_image_optimizer_mimetype( $backup_file, 'i' ) . ' vs. ' . \ewww_image_optimizer_mimetype( $file, 'i' ) );
			/* translators: %s: An image filename */
			$this->error_message = \sprintf( \__( 'Backup file for %s has the wrong mime type.', 'ewww-image-optimizer' ), $file );
			return false;
		}
		$filesize = $this->filesize( $file );
		$backsize = $this->filesize( $backup_file );
		if ( $filesize && $filesize === $backsize ) {
			// $this->delete_file( $backup_file );
			// return true; // Because restore not needed, already done!
		}
		$this->debug_message( "restoring $file from $backup_file" );
		copy( $backup_file, $file );
		if ( $this->filesize( $file ) === $this->filesize( $backup_file ) ) {
			if ( $this->is_file( $file . '.webp' ) && \is_writable( $file . '.webp' ) ) {
				$this->delete_file( $file . '.webp' );
			}
			/* $this->delete_file( $backup_file ); */
			global $wpdb;
			// Reset the image record.
			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->ewwwio_images SET results = '', image_size = 0, updates = 0, updated=updated, level = 0 WHERE id = %d", $image['id'] ) );
			return true;
		}
		/* translators: %s: An image filename */
		$this->error_message = \sprintf( \__( 'Restore attempted for %s, but could not be confirmed.', 'ewww-image-optimizer' ), $file );
		return false;
	}

	/**
	 * Send a file to the API for backup.
	 *
	 * @param int|array $image The db record/ID of the image to restore.
	 * @return bool True if the image was restored successfully.
	 */
	protected function restore_from_cloud( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		return \ewww_image_optimizer_cloud_restore_single_image( $image );
	}

	/**
	 * Delete the local backup file. Used when deleting an attachment.
	 *
	 * @param array $file The filename of the image for which we should remove the backup.
	 */
	public function delete_local_backup( $file ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $file ) {
			return;
		}
		if ( 'local' !== $this->backup_mode ) {
			return;
		}
		$backup_file = $this->get_backup_location( $file );
		if ( ! $backup_file || $backup_file === $file ) {
			return;
		}
		\clearstatcache();
		if ( ! $this->is_file( $backup_file ) || ! \is_writable( $backup_file ) ) {
			return;
		}
		$this->delete_file( $backup_file );
	}

	/**
	 * Restore an attachment from the API or local backups.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A clone of $wpdb unless it is lacking utf8 connectivity.
	 *
	 * @param int    $id The attachment id number.
	 * @param string $gallery Optional. The gallery from whence we came. Default 'media'.
	 * @param array  $meta Optional. The image metadata from the postmeta table.
	 * @return array The altered meta (if size differs), or the original value passed along.
	 */
	public function restore_backup_from_meta_data( $id, $gallery = 'media', $meta = array() ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( \strpos( $wpdb->charset, 'utf8' ) === false ) {
			\ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$images = $ewwwdb->get_results( "SELECT id,path,resize,backup FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery'", \ARRAY_A );
		foreach ( $images as $image ) {
			if ( ! empty( $image['path'] ) ) {
				$image['path'] = \ewww_image_optimizer_absolutize_path( $image['path'] );
			}
			$this->restore_file( $image );
			if ( 'media' === $gallery && 'full' === $image['resize'] && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				list( $width, $height ) = \wp_getimagesize( $image['path'] );
				if ( (int) $width !== (int) $meta['width'] || (int) $height !== (int) $meta['height'] ) {
					$meta['height'] = $height;
					$meta['width']  = $width;
				}
			}
		}
		if ( 'media' === $gallery ) {
			\remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9 );
			$meta = \ewww_image_optimizer_update_filesize_metadata( $meta, $id );
		}
		if ( $this->s3_uploads_enabled() ) {
			\ewww_image_optimizer_remote_push( $meta, $id );
			$this->debug_message( 're-uploading to S3(_Uploads)' );
		}
		return $meta;
	}

	/**
	 * Handle the AJAX call for a single image restore.
	 */
	public function restore_single_image_handler() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Check permissions of current user.
		$permissions = \apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
		if ( ! \current_user_can( $permissions ) ) {
			// Display error message if insufficient permissions.
			$this->ob_clean();
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'You do not have permission to optimize images.', 'ewww-image-optimizer' ) ) ) );
		}
		// Make sure we didn't accidentally get to this page without an attachment to work on.
		if ( empty( $_REQUEST['ewww_image_id'] ) ) {
			// Display an error message since we don't have anything to work on.
			$this->ob_clean();
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'No image ID was provided.', 'ewww-image-optimizer' ) ) ) );
		}
		if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-tools' ) ) {
			$this->ob_clean();
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
		}
		\session_write_close();
		$image = (int) $_REQUEST['ewww_image_id'];
		$this->debug_message( "attempting restore for $image" );
		if ( $this->restore_file( $image ) ) {
			$this->ob_clean();
			\wp_die( \wp_json_encode( array( 'success' => 1 ) ) );
		}
		$this->ob_clean();
		\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Unable to restore image.', 'ewww-image-optimizer' ) ) ) );
	}
}

global $eio_backup;
$eio_backup = new Backup();
