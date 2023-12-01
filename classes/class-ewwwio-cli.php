<?php
/**
 * Class file for EWWWIO_CLI
 *
 * EWWWIO_CLI contains an extension to the WP_CLI_Command class to enable bulk optimizing from the
 * command line using WP-CLI.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run bulk EWWW IO tools via WP CLI.
 */
class EWWWIO_CLI extends WP_CLI_Command {
	/**
	 * Optimizes images from the selected 'gallery'.
	 *
	 * ## OPTIONS
	 *
	 * <library>
	 * : valid values are 'all' (default), 'media', 'nextgen', and 'flagallery'
	 * : media: Media Library, theme, and configured folders
	 * : nextgen: Nextcellent and NextGEN 2.x
	 * : flagallery: Grand FlAGallery
	 *
	 * <delay>
	 * : optional, number of seconds to pause between images
	 *
	 * <force>
	 * : optional, should the plugin re-optimize images that have already been processed.
	 *
	 * <reset>
	 * : optional, start the optimizer back at the beginning instead of resuming from last position
	 *
	 * <webp-only>
	 * : optional, only do WebP Conversion, skip all other operations
	 *
	 * <noprompt>
	 * : do not prompt, just start optimizing
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli ewwwio optimize media 5 --force --reset --webp-only --noprompt
	 *
	 * @synopsis <library> [<delay>] [--force] [--reset] [--webp-only] [--noprompt]
	 *
	 * @global bool $ewww_defer Gets set to false to make sure optimization happens inline.
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	public function optimize( $args, $assoc_args ) {
		global $ewww_defer;
		$ewww_defer = false;
		global $ewww_webp_only;
		$ewww_webp_only = false;
		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$library = $args[0];
		if ( empty( $args[1] ) ) {
			$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		} else {
			$delay = $args[1];
		}
		$ewww_reset = false;
		if ( ! empty( $assoc_args['reset'] ) ) {
			$ewww_reset = true;
		} else {
			$media_resume = get_option( 'ewww_image_optimizer_bulk_resume' );
			if (
				( ! empty( $media_resume ) && 'scanning' !== $media_resume ) ||
				get_option( 'ewww_image_optimizer_bulk_ngg_resume', '' ) ||
				get_option( 'ewww_image_optimizer_bulk_flag_resume', '' )
			) {
				WP_CLI::line( __( 'Resuming previous operation.', 'ewww-image-optimizer' ) );
			}
		}

		if ( ! empty( $assoc_args['force'] ) ) {
			WP_CLI::line( __( 'Forcing re-optimization of previously processed images.', 'ewww-image-optimizer' ) );
			$_REQUEST['ewww_force'] = true;
			global $ewww_force;
			$ewww_force = 1;
		}
		if ( ! empty( $assoc_args['webp-only'] ) ) {
			if ( empty( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) ) {
				WP_CLI::error( __( 'WebP Conversion is not enabled.', 'ewww-image-optimizer' ) );
			}
			WP_CLI::line( __( 'Running WebP conversion only.', 'ewww-image-optimizer' ) );
			$ewww_webp_only = true;
		}
		/* translators: 1: type of images, like media, or nextgen 2: number of seconds */
		WP_CLI::line( sprintf( _x( 'Optimizing %1$s with a %2$d second pause between images.', 'string will be something like "media" or "nextgen"', 'ewww-image-optimizer' ), $library, $delay ) );
		// Let's get started, shall we?
		ewwwio()->admin_init();

		// And what shall we do?
		switch ( $library ) {
			case 'all':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_resume', '' );
					update_option( 'ewww_image_optimizer_aux_resume', '' );
					update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
					update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					ewww_image_optimizer_delete_pending();
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				ewww_image_optimizer_bulk_script( 'media_page_ewww-image-optimizer-bulk' );
				$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );

				/* translators: %d: number of images */
				WP_CLI::line( sprintf( _n( '%1$d image in the Media Library has been selected.', '%1$d images in the Media Library have been selected.', $fullsize_count, 'ewww-image-optimizer' ), $fullsize_count ) );
				WP_CLI::line( __( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', 'ewww-image-optimizer' ) );
				WP_CLI::line( __( 'Scanning, this could take a while', 'ewww-image-optimizer' ) );
				// Do a filter to increase the timeout to 999 or something crazy.
				add_filter( 'ewww_image_optimizer_timeout', 'ewww_image_optimizer_cli_timeout', 200 );
				ewww_image_optimizer_media_scan( 'ewww-image-optimizer-cli' );
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-cli' );
				if ( class_exists( 'EWWW_Nextgen' ) ) {
					list( $fullsize_count, $resize_count ) = ewww_image_optimizer_count_optimized( 'ngg' );
					/* translators: 1-2: number of images */
					WP_CLI::line( 'Nextgen: ' . sprintf( __( '%1$d images have been selected, with %2$d resized versions.', 'ewww-image-optimizer' ), $fullsize_count, $resize_count ) );
				} elseif ( class_exists( 'EWWW_Nextcellent' ) ) {
					$attachments = $this->scan_nextcellent();
					/* translators: %d: number of images */
					WP_CLI::line( 'Nextgen: ' . sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ), count( $attachments ) ) );
				}
				if ( class_exists( 'EWWW_Flag' ) ) {
					list( $fullsize_count, $resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
					/* translators: 1-2: number of images */
					WP_CLI::line( 'FlAGallery: ' . sprintf( __( '%1$d images have been selected, with %2$d resized versions.', 'ewww-image-optimizer' ), $fullsize_count, $resize_count ) );
				}
				if ( empty( $assoc_args['noprompt'] ) && $pending_count ) {
					/* translators: %d: number of images */
					WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', $pending_count, 'ewww-image-optimizer' ), $pending_count ) );
				}
				if ( $pending_count ) {
					// Update the 'bulk resume' option to show that an operation is in progress.
					update_option( 'ewww_image_optimizer_bulk_resume', 'true' );
					$_REQUEST['ewww_batch_limit'] = 1;
					$clicount                     = 1;
					/* translators: 1: current image being proccessed 2: total number of images*/
					WP_CLI::line( sprintf( __( 'Processing image %1$d of %2$d', 'ewww-image-optimizer' ), $clicount, $pending_count ) );
					while ( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
						++$clicount;
						if ( $clicount <= $pending_count ) {
							/* translators: 1: current image being proccessed 2: total number of images*/
							WP_CLI::line( sprintf( __( 'Processing image %1$d of %2$d', 'ewww-image-optimizer' ), $clicount, $pending_count ) );
						}
					}
				} else {
					WP_CLI::line( __( 'No images to optimize', 'ewww-image-optimizer' ) );
				}
				$this->bulk_media_cleanup();
				if ( class_exists( 'EWWW_Nextgen' ) ) {
					$this->bulk_ngg( $delay );
				} elseif ( class_exists( 'EWWW_Nextcellent' ) ) {
					$attachments = $this->scan_nextcellent();
					$this->bulk_nextcellent( $delay, $attachments );
				}
				if ( class_exists( 'EWWW_Flag' ) ) {
					$this->bulk_flag( $delay );
				}
				break;
			case 'media':
			case 'other':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_resume', '' );
					update_option( 'ewww_image_optimizer_aux_resume', '' );
					update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
					update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
					ewww_image_optimizer_delete_pending();
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				ewww_image_optimizer_bulk_script( 'media_page_ewww-image-optimizer-bulk' );
				$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );
				/* translators: %d: number of images */
				WP_CLI::line( sprintf( __( '%1$d images in the Media Library have been selected.', 'ewww-image-optimizer' ), $fullsize_count ) );
				WP_CLI::line( __( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', 'ewww-image-optimizer' ) );
				WP_CLI::line( __( 'Scanning, this could take a while', 'ewww-image-optimizer' ) );
				// Do a filter to increase the timeout to 999 or something crazy.
				add_filter( 'ewww_image_optimizer_timeout', 'ewww_image_optimizer_cli_timeout', 200 );
				ewww_image_optimizer_media_scan( 'ewww-image-optimizer-cli' );
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-cli' );
				if ( empty( $assoc_args['noprompt'] ) && $pending_count ) {
					/* translators: %d: number of images */
					WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', $pending_count, 'ewww-image-optimizer' ), $pending_count ) );
				}
				$_REQUEST['ewww_batch_limit'] = 1;
				if ( $pending_count ) {
					// Update the 'bulk resume' option to show that an operation is in progress.
					update_option( 'ewww_image_optimizer_bulk_resume', 'true' );
					$clicount = 1;
					/* translators: 1: current image being proccessed 2: total number of images*/
					WP_CLI::line( sprintf( __( 'Processing image %1$d of %2$d', 'ewww-image-optimizer' ), $clicount, $pending_count ) );
					while ( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
						++$clicount;
						if ( $clicount <= $pending_count ) {
							/* translators: 1: current image being proccessed 2: total number of images*/
							WP_CLI::line( sprintf( __( 'Processing image %1$d of %2$d', 'ewww-image-optimizer' ), $clicount, $pending_count ) );
						}
					}
				} else {
					WP_CLI::line( __( 'No images to optimize', 'ewww-image-optimizer' ) );
				}
				$this->bulk_media_cleanup();
				break;
			case 'nextgen':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				if ( class_exists( 'EWWW_Nextgen' ) ) {
					list( $fullsize_count, $resize_count ) = ewww_image_optimizer_count_optimized( 'ngg' );
					if ( empty( $assoc_args['noprompt'] ) ) {
						/* translators: 1-2: number of images */
						WP_CLI::confirm( sprintf( __( '%1$d images have been selected, with %2$d resized versions.', 'ewww-image-optimizer' ), $fullsize_count, $resize_count ) );
					}
					$this->bulk_ngg( $delay );
				} elseif ( class_exists( 'EWWW_Nextcellent' ) ) {
					$attachments = $this->scan_nextcellent();
					if ( empty( $assoc_args['noprompt'] ) ) {
						/* translators: %d: number of images */
						WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ), count( $attachments ) ) );
					}
					$this->bulk_nextcellent( $delay, $attachments );
				} else {
					WP_CLI::error( __( 'NextGEN/Nextcellent not installed.', 'ewww-image-optimizer' ) );
				}
				break;
			case 'flagallery':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				if ( class_exists( 'EWWW_Flag' ) ) {
					list( $fullsize_count, $resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
					if ( empty( $assoc_args['noprompt'] ) ) {
						/* translators: 1-2: number of images */
						WP_CLI::confirm( 'FlAGallery: ' . sprintf( __( '%1$d images have been selected, with %2$d resized versions.', 'ewww-image-optimizer' ), $fullsize_count, $resize_count ) );
					}
					$this->bulk_flag( $delay );
				} else {
					WP_CLI::error( __( 'Grand Flagallery not installed.', 'ewww-image-optimizer' ) );
				}
				break;
			default:
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_resume', '' );
					update_option( 'ewww_image_optimizer_aux_resume', '' );
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					WP_CLI::success( __( 'Bulk status has been reset, the next bulk operation will start from the beginning.', 'ewww-image-optimizer' ) );
				} else {
					WP_CLI::line( __( 'Please specify a valid library option, see "wp-cli help ewwwio optimize" for more information.', 'ewww-image-optimizer' ) );
				}
		} // End switch().
	}

	/**
	 * Restore images from cloud/local backups.
	 *
	 * ## OPTIONS
	 *
	 * <reset>
	 * : optional, start the process over instead of resuming from last position
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli ewwwio restore --reset
	 *
	 * @synopsis [--reset]
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	public function restore( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['reset'] ) ) {
			delete_option( 'ewww_image_optimizer_bulk_restore_position' );
		}
		global $eio_backup;
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}

		$completed = 0;
		$position  = (int) get_option( 'ewww_image_optimizer_bulk_restore_position' );
		$per_page  = 200;

		ewwwio_debug_message( "searching for $per_page records starting at $position" );
		$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0 ORDER BY id LIMIT %d", $position, $per_page ), ARRAY_A );

		$restorable_images = (int) $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0", $position ) );

		/* translators: %d: number of images */
		WP_CLI::line( sprintf( __( 'There are %d images that may be restored.', 'ewww-image-optimizer' ), $restorable_images ) );
		WP_CLI::confirm( __( 'You should take a site backup before performing a bulk action on your images. Do you wish to continue?', 'ewww-image-optimizer' ) );

		// Because some plugins might have loose filters (looking at you WPML).
		remove_all_filters( 'wp_delete_file' );

		while ( ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $optimized_image ) {
				++$completed;
				ewwwio_debug_message( "submitting {$optimized_image['id']} to be restored" );
				$optimized_image['path'] = \ewww_image_optimizer_absolutize_path( $optimized_image['path'] );
				$eio_backup->restore_file( $optimized_image );
				$error_message = $eio_backup->get_error();
				if ( $error_message ) {
					WP_CLI::warning( "$completed/$restorable_images: $error_message" );
				} else {
					WP_CLI::success( "$completed/$restorable_images: {$optimized_image['path']}" );
				}
				update_option( 'ewww_image_optimizer_bulk_restore_position', $optimized_image['id'], false );
			} // End foreach().
			$optimized_images = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0 ORDER BY id LIMIT %d", $optimized_image['id'], $per_page ), ARRAY_A );
		}

		delete_option( 'ewww_image_optimizer_bulk_restore_position' );
	}

	/**
	 * Remove pre-scaled original size versions of image uploads.
	 *
	 * ## OPTIONS
	 *
	 * <reset>
	 * : optional, start the process back at the beginning instead of resuming from last position
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli ewwwio remove_originals --reset
	 *
	 * @synopsis [--reset]
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	public function remove_originals( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['reset'] ) ) {
			delete_option( 'ewww_image_optimizer_delete_originals_resume' );
		}
		global $wpdb;

		$per_page = 200;
		$position = (int) get_option( 'ewww_image_optimizer_delete_originals_resume' );

		$cleanable_uploads = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(ID) FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND post_mime_type LIKE %s",
				(int) $position,
				'%image%'
			)
		);

		/* translators: %d: number of image uploads */
		WP_CLI::line( sprintf( __( 'This process removes the originals that WordPress preserves for thumbnail generation. %d media uploads will checked for originals to remove.', 'ewww-image-optimizer' ), $cleanable_uploads ) );
		WP_CLI::confirm( __( 'You should take a site backup before performing a bulk action on your images. Do you wish to continue?', 'ewww-image-optimizer' ) );

		/**
		 * Require the files that contain functions for the images table and bulk processing images outside the library.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php';

		\ewwwio_debug_message( "searching for $per_page records starting at $position" );

		$attachments = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND post_mime_type LIKE %s ORDER BY ID LIMIT %d",
				(int) $position,
				'%image%',
				(int) $per_page
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Deleting originals', 'ewww-image-optimizer' ), $cleanable_uploads );

		// Because some plugins might have loose filters (looking at you WPML).
		\remove_all_filters( 'wp_delete_file' );

		while ( \ewww_image_optimizer_iterable( $attachments ) ) {
			foreach ( $attachments as $id ) {
				$new_meta = \ewwwio_remove_original_image( $id );
				if ( \ewww_image_optimizer_iterable( $new_meta ) ) {
					\wp_update_attachment_metadata( $id, $new_meta );
				}
				\update_option( 'ewww_image_optimizer_delete_originals_resume', $id, false );
				$progress->tick();
			}
			$attachments = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND post_mime_type LIKE %s ORDER BY ID LIMIT %d",
					(int) $id,
					'%image%',
					(int) $per_page
				)
			);
		}
		$progress->finish();

		WP_CLI::success( __( 'Finished', 'ewww-image-optimizer' ) );

		\delete_option( 'ewww_image_optimizer_delete_originals_resume' );
	}

	/**
	 * Remove the original version of converted images.
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	public function remove_converted_originals( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['reset'] ) ) {
			delete_option( 'ewww_image_optimizer_delete_originals_resume' );
		}
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}

		$per_page = 200;

		$converted_count = (int) $wpdb->get_var( "SELECT count(id) FROM $wpdb->ewwwio_images WHERE converted != ''" );

		/* translators: %d: number of converted images */
		WP_CLI::line( sprintf( __( 'This process will remove the originals after you have converted images (PNG to JPG and friends). %d images will checked for originals to remove.', 'ewww-image-optimizer' ), $converted_count ) );
		WP_CLI::confirm( __( 'You should take a site backup before performing a bulk action on your images. Do you wish to continue?', 'ewww-image-optimizer' ) );

		$converted_images = $wpdb->get_results( $wpdb->prepare( "SELECT path,converted,id FROM $wpdb->ewwwio_images WHERE converted != '' ORDER BY id DESC LIMIT %d", $per_page ), ARRAY_A );

		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Deleting converted images', 'ewww-image-optimizer' ), $converted_count );

		// Because some plugins might have loose filters (looking at you WPML).
		\remove_all_filters( 'wp_delete_file' );

		while ( \ewww_image_optimizer_iterable( $converted_images ) ) {
			foreach ( $converted_images as $optimized_image ) {
				$file = \ewww_image_optimizer_absolutize_path( $optimized_image['converted'] );
				\ewwwio_debug_message( "$file was converted, checking if it still exists" );
				if ( ! \ewww_image_optimizer_stream_wrapped( $file ) && \ewwwio_is_file( $file ) ) {
					\ewwwio_debug_message( "removing original: $file" );
					if ( \ewwwio_delete_file( $file ) ) {
						\ewwwio_debug_message( "removed $file" );
					} else {
						/* translators: %s: file name */
						WP_CLI::warning( sprintf( __( 'Could not delete %s, please remove manually or fix permissions and try again.', 'ewww-image-optimizer' ), $file ) );
					}
				}
				$wpdb->update(
					$wpdb->ewwwio_images,
					array(
						'converted' => '',
					),
					array(
						'id' => $optimized_image['id'],
					)
				);
				$progress->tick();
			} // End foreach().
			$converted_images = $wpdb->get_results( $wpdb->prepare( "SELECT path,converted,id FROM $wpdb->ewwwio_images WHERE converted != '' ORDER BY id DESC LIMIT %d", $per_page ), ARRAY_A );
		}
		$progress->finish();

		WP_CLI::success( __( 'Finished', 'ewww-image-optimizer' ) );

		\delete_option( 'ewww_image_optimizer_delete_originals_resume' );
	}

	/**
	 * Remove all WebP images.
	 *
	 * ## OPTIONS
	 *
	 * <reset>
	 * : optional, start the process back at the beginning instead of resuming from last position
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli ewwwio remove_webp --reset
	 *
	 * @synopsis [--reset]
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	public function remove_webp( $args, $assoc_args ) {
		if ( ! empty( $assoc_args['reset'] ) ) {
			delete_option( 'ewww_image_optimizer_webp_clean_position' );
		}
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}

		$completed = 0;
		$per_page  = 200;

		$resume    = get_option( 'ewww_image_optimizer_webp_clean_position' );
		$position1 = is_array( $resume ) && ! empty( $resume['stage1'] ) ? (int) $resume['stage1'] : 0;
		$position2 = is_array( $resume ) && ! empty( $resume['stage2'] ) ? (int) $resume['stage2'] : 0;

		$cleanable_uploads = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(ID) FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE %s OR post_mime_type LIKE %s)",
				(int) $position1,
				'%image%',
				'%pdf%'
			)
		);
		$cleanable_records = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(id) FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0",
				$position2
			)
		);

		/* translators: 1: number of image uploads, 2: number of database records */
		WP_CLI::line( sprintf( __( 'WebP copies of %1$d media uploads will be removed first, then %2$d records in the optimization history will be checked to remove any remaining WebP images.', 'ewww-image-optimizer' ), $cleanable_uploads, $cleanable_records ) );
		WP_CLI::confirm( __( 'You should take a site backup before performing a bulk action on your images. Do you wish to continue?', 'ewww-image-optimizer' ) );

		/**
		 * Require the files that contain functions for the images table and bulk processing images outside the library.
		 */
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php';

		ewwwio_debug_message( "searching for $per_page records starting at $position1" );

		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE %s OR post_mime_type LIKE %s) ORDER BY ID LIMIT %d",
				(int) $position1,
				'%image%',
				'%pdf%',
				(int) $per_page
			)
		);

		$progress1 = \WP_CLI\Utils\make_progress_bar( __( 'Stage 1:', 'ewww-image-optimizer' ), $cleanable_uploads );

		// Because some plugins might have loose filters (looking at you WPML).
		\remove_all_filters( 'wp_delete_file' );

		while ( \ewww_image_optimizer_iterable( $attachment_ids ) ) {
			foreach ( $attachment_ids as $id ) {
				\ewww_image_optimizer_delete_webp( $id );
				$resume['stage1'] = (int) $id;
				\update_option( 'ewww_image_optimizer_webp_clean_position', $resume, false );
				$progress1->tick();
			}
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE ID > %d AND (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE %s OR post_mime_type LIKE %s) ORDER BY ID LIMIT %d",
					(int) $id,
					'%image%',
					'%pdf%',
					(int) $per_page
				)
			);
		}
		$progress1->finish();

		\ewwwio_debug_message( "searching for $per_page records starting at $position2" );

		$optimized_images = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0 ORDER BY id LIMIT %d",
				(int) $position2,
				(int) $per_page
			),
			ARRAY_A
		);

		$progress2 = \WP_CLI\Utils\make_progress_bar( __( 'Stage 2:', 'ewww-image-optimizer' ), $cleanable_records );

		while ( \ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $optimized_image ) {
				\ewww_image_optimizer_aux_images_webp_clean( $optimized_image );
				$resume['stage2'] = $optimized_image['id'];
				\update_option( 'ewww_image_optimizer_webp_clean_position', $resume, false );
				$progress2->tick();
			}
			$optimized_images = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0 ORDER BY id LIMIT %d",
					(int) $optimized_image['id'],
					(int) $per_page
				),
				ARRAY_A
			);
		}
		$progress2->finish();
		WP_CLI::success( __( 'Finished', 'ewww-image-optimizer' ) );

		\delete_option( 'ewww_image_optimizer_webp_clean_position' );
	}

	/**
	 * Cleanup after ourselves after a bulk operation.
	 */
	private function bulk_media_cleanup() {
		// All done, so we can update the bulk options with empty values...
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		update_option( 'ewww_image_optimizer_aux_resume', '' );
		// and let the user know we are done.
		WP_CLI::success( __( 'Finished Optimization!', 'ewww-image-optimizer' ) );
	}

	/**
	 * Bulk Optimize all GRAND FlaGallery uploads from WP-CLI.
	 *
	 * @global object $wpdb
	 *
	 * @param int $delay Number of seconds to pause between images.
	 */
	private function bulk_flag( $delay = 0 ) {
		$ids = null;
		if ( get_option( 'ewww_image_optimizer_bulk_flag_resume' ) ) {
			// If there is an operation to resume, get those IDs from the db.
			$ids = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
		} else {
			// Otherwise, if we are on the main bulk optimize page, just get all the IDs available.
			global $wpdb;
			$ids = $wpdb->get_col( "SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC" );
			// Store the IDs to optimize in the options table of the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $ids, false );
		}
		$attachments = $ids; // Use this separately to keep track of progress in the db.
		// Set the resume flag to indicate the bulk operation is in progress.
		update_option( 'ewww_image_optimizer_bulk_flag_resume', 'true' );
		// Need this file to work with flag meta.
		require_once WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php';
		if ( ! ewww_image_optimizer_iterable( $ids ) ) {
			WP_CLI::line( __( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) );
			return;
		}
		foreach ( $ids as $id ) {
			if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
				sleep( $delay );
			}
			// Record the starting time for the current image (in microseconds).
			$started = microtime( true );
			// Retrieve the meta for the current ID.
			$meta               = new flagMeta( $id );
			$file_path          = $meta->image->imagePath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $file_path );
			$ewww_image->resize = 'full';
			// Optimize the full-size version.
			$fres = ewww_image_optimizer( $file_path, 3, false, false, true );
			WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . ' ' . esc_html( $meta->image->filename ) );
			/* translators: %s: compression results */
			WP_CLI::line( sprintf( __( 'Full size – %s', 'ewww-image-optimizer' ), html_entity_decode( $fres[1] ) ) );
			if ( ! empty( $meta->image->meta_data['webview'] ) ) {
				// Determine path of the webview.
				$web_path           = $meta->image->webimagePath;
				$ewww_image         = new EWWW_Image( $id, 'flag', $web_path );
				$ewww_image->resize = 'webview';
				$wres               = ewww_image_optimizer( $web_path, 3, false, true );
				/* translators: %s: compression results */
				WP_CLI::line( sprintf( __( 'Optimized size – %s', 'ewww-image-optimizer' ), html_entity_decode( $wres[1] ) ) );
			}
			$thumb_path         = $meta->image->thumbPath;
			$ewww_image         = new EWWW_Image( $id, 'flag', $thumb_path );
			$ewww_image->resize = 'thumbnail';
			// Optimize the thumbnail.
			$tres = ewww_image_optimizer( $thumb_path, 3, false, true );
			/* translators: %s: compression results */
			WP_CLI::line( sprintf( __( 'Thumbnail – %s', 'ewww-image-optimizer' ), html_entity_decode( $tres[1] ) ) );
			// Determine how much time the image took to process...
			$elapsed = microtime( true ) - $started;
			// and output it to the user.
			/* translators: %s: localized number of seconds */
			WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed, 2 ) ) );
			// Take the first image off the list.
			if ( ! empty( $attachments ) ) {
				array_shift( $attachments );
			}
			// And send the list back to the db.
			update_option( 'ewww_image_optimizer_bulk_flag_attachments', $attachments, false );
		} // End foreach().
		// Reset the bulk flags in the db...
		update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
		update_option( 'ewww_image_optimizer_bulk_flag_attachments', '', false );
		// and let the user know we are done.
		WP_CLI::success( __( 'Finished Optimization!', 'ewww-image-optimizer' ) );
	}

	/**
	 * Bulk Optimize all NextGEN uploads from WP-CLI.
	 *
	 * @global object $wpdb
	 * @global object $ewwwngg
	 *
	 * @param int $delay Number of seconds to pause between images.
	 */
	private function bulk_ngg( $delay = 0 ) {
		if ( get_option( 'ewww_image_optimizer_bulk_ngg_resume' ) ) {
			// Get the list of attachment IDs from the db.
			$images = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
		} else {
			// Otherwise, get all the images in the db.
			global $wpdb;
			$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC" );
			// Store the image IDs to process in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $images, false );
			// Toggle the resume flag to indicate an operation is in progress.
			update_option( 'ewww_image_optimizer_bulk_ngg_resume', 'true' );
		}
		if ( ! ewww_image_optimizer_iterable( $images ) ) {
			WP_CLI::line( __( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) );
			return;
		}
		$attachments = $images; // Kept separate to update status in db.
		global $ewwwngg;
		global $ewww_defer;
		$ewww_defer    = false;
		$clicount      = 0;
		$pending_count = count( $images );
		foreach ( $images as $id ) {
			if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
				sleep( $delay );
			}
			// Output which image in the queue is being worked on.
			++$clicount;
			/* translators: 1: current image being proccessed 2: total number of images*/
			WP_CLI::line( sprintf( __( 'Processing image %1$d of %2$d', 'ewww-image-optimizer' ), $clicount, $pending_count ) );
			// Find out what time we started, in microseconds.
			$started = microtime( true );
			// Get an image object.
			$image = $ewwwngg->get_ngg_image( $id );
			$image = $ewwwngg->ewww_added_new_image( $image );
			// Output the results of the optimization.
			WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . ' ' . basename( $ewwwngg->get_image_abspath( $image, 'full' ) ) );
			if ( ewww_image_optimizer_iterable( $ewwwngg->bulk_sizes ) ) {
				// Output the results for each $size.
				foreach ( $ewwwngg->bulk_sizes as $size => $results_msg ) {
					if ( 'backup' === $size ) {
						continue;
					} elseif ( 'full' === $size ) {
						/* translators: %s: compression results */
						WP_CLI::line( sprintf( __( 'Full size - %s', 'ewww-image-optimizer' ), html_entity_decode( $results_msg ) ) );
					} elseif ( 'thumbnail' === $size ) {
						// Output the results of the thumb optimization.
						/* translators: %s: compression results */
						WP_CLI::line( sprintf( __( 'Thumbnail - %s', 'ewww-image-optimizer' ), html_entity_decode( $results_msg ) ) );
					} else {
						// Output savings for any other sizes, if they ever exist...
						WP_CLI::line( ucfirst( $size ) . ' - ' . html_entity_decode( $results_msg ) );
					}
				}
				$ewwwngg->bulk_sizes = array();
			}
			// Output how much time we spent.
			$elapsed = microtime( true ) - $started;
			/* translators: %s: number of seconds */
			WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed, 2 ) ) );
			// Remove the first item.
			if ( ! empty( $attachments ) ) {
				array_shift( $attachments );
			}
			// And store the list back in the db.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $attachments, false );
		} // End foreach().

		// Reset all the bulk options in the db.
		update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
		update_option( 'ewww_image_optimizer_bulk_ngg_attachments', '', false );
		WP_CLI::success( __( 'Finished Optimization!', 'ewww-image-optimizer' ) );
	}

	/**
	 * Search for all Nextcellent uploads using WP-CLI command.
	 *
	 * @global object $wpdb
	 */
	private function scan_nextcellent() {
		$images = null;
		if ( get_option( 'ewww_image_optimizer_bulk_ngg_resume' ) ) {
			// If we have an operation to resume...
			// get the list of attachment IDs from the queue.
			$images = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
		} else {
			// Otherwise, get all the images in the db.
			global $wpdb;
			$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC" );
		}

		// Store the image IDs to process in the queue.
		update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $images, false );
		return $images;
	}

	/**
	 * Bulk Optimize all Nextcellent uploads from WP-CLI.
	 *
	 * @param int   $delay Number of seconds to pause between images.
	 * @param array $attachments A list of image IDs to optimize.
	 */
	private function bulk_nextcellent( $delay, $attachments ) {
		global $ewwwngg;
		global $ewww_defer;
		$ewww_defer = false;
		// Toggle the resume flag to indicate an operation is in progress.
		update_option( 'ewww_image_optimizer_bulk_ngg_resume', 'true' );
		// Need this file to work with metadata.
		require_once WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php';
		foreach ( $attachments as $id ) {
			if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
				sleep( $delay );
			}
			// Find out what time we started, in microseconds.
			$started = microtime( true );
			// Optimize by ID.
			list( $fres, $tres ) = $ewwwngg->ewww_ngg_optimize( $id );
			if ( $fres[0] ) {
				// Output the results of the optimization.
				WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . $fres[0] );
			}
			/* translators: %s: compression results */
			WP_CLI::line( sprintf( __( 'Full size - %s', 'ewww-image-optimizer' ), html_entity_decode( $fres[1] ) ) );
			// Output the results of the thumb optimization.
			/* translators: %s: compression results */
			WP_CLI::line( sprintf( __( 'Thumbnail - %s', 'ewww-image-optimizer' ), html_entity_decode( $tres[1] ) ) );
			// Output how much time we spent.
			$elapsed = microtime( true ) - $started;
			/* translators: %s: number of seconds */
			WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed, 2 ) ) );
			// Remove the first item.
			if ( ! empty( $attachments ) ) {
				array_shift( $attachments );
			}
			// and store the list back in the db queue.
			update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $attachments, false );
		} // End foreach().
		// Reset all the bulk options in the db.
		update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
		update_option( 'ewww_image_optimizer_bulk_ngg_attachments', '', false );
		WP_CLI::success( __( 'Finished Optimization!', 'ewww-image-optimizer' ) );
	}
}

WP_CLI::add_command( 'ewwwio', 'EWWWIO_CLI' );

/**
 * Increases the EWWW IO timeout for scanning images.
 *
 * @param int $time_limit The number of seconds before a timeout happens.
 * @return int The number of seconds to wait before a timeout from the CLI.
 */
function ewww_image_optimizer_cli_timeout( $time_limit ) {
	return 9999;
}
