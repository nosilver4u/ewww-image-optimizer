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
 * Implements wp-cli extension for bulk optimizing.
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
	 * : flagallery: Grand FlaGallery
	 *
	 * <delay>
	 * : optional, number of seconds to pause between images
	 *
	 * <force>
	 * : optional, should the plugin re-optimize images that have already been processed.
	 * * <reset>
	 * : optional, start the optimizer back at the beginning instead of resuming from last position
	 *
	 * <noprompt>
	 * : do not prompt, just start optimizing
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli ewwwio optimize media 5 --force --reset --noprompt
	 *
	 * @synopsis <library> [<delay>] [--force] [--reset] [--noprompt]
	 *
	 * @global bool $ewww_defer Gets set to false to make sure optimization happens inline.
	 * @global object $ngg
	 *
	 * @param array $args A numeric array of required arguments.
	 * @param array $assoc_args An associative array of optional arguments.
	 */
	function optimize( $args, $assoc_args ) {
		global $ewww_defer;
		$ewww_defer = false;
		// because NextGEN hasn't flushed it's buffers...
		while ( @ob_end_flush() ) {
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
		}
		if ( ! empty( $assoc_args['force'] ) ) {
			WP_CLI::line( __( 'Forcing re-optimization of previously processed images.', 'ewww-image-optimizer' ) );
			$_REQUEST['ewww_force'] = true;
		}
		/* translators: 1: type of images, like media, or nextgen 2: number of seconds */
		WP_CLI::line( sprintf( _x( 'Optimizing %1$s with a %2$d second pause between images.', 'string will be something like "media" or "nextgen"', 'ewww-image-optimizer' ), $library, $delay ) );
		// Let's get started, shall we?
		ewww_image_optimizer_admin_init();
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
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				if ( class_exists( 'EwwwNgg' ) ) {
					global $ngg;
					if ( preg_match( '/^2/', $ngg->version ) ) {
						list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'ngg' );
						/* translators: 1-4: number of images */
						WP_CLI::line( 'Nextgen: ' . sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', 'ewww-image-optimizer' ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
					} else {
						$attachments = ewww_image_optimizer_scan_next();
						/* translators: %d: number of images */
						WP_CLI::line( 'Nextgen: ' . sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ), count( $attachments ) ) );
					}
				}
				if ( class_exists( 'ewwwflag' ) ) {
					list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
					/* translators: 1-4: number of images */
					WP_CLI::line( 'Flagallery: ' . sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', 'ewww-image-optimizer' ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
				}
				if ( empty( $assoc_args['noprompt'] ) ) {
					/* translators: %d: number of images */
					WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', $pending_count, 'ewww-image-optimizer' ), $pending_count ) );
				}
				/* ewww_image_optimizer_bulk_media( $delay ); */
				$_REQUEST['ewww_batch_limit'] = 1;
				while ( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
					$something = 1;
				}
				ewww_image_optimizer_bulk_media_cleanup();
				if ( class_exists( 'Ewwwngg' ) ) {
					global $ngg;
					if ( preg_match( '/^2/', $ngg->version ) ) {
						ewww_image_optimizer_bulk_ngg( $delay );
					} else {
						$attachments = ewww_image_optimizer_scan_next();
						ewww_image_optimizer_bulk_next( $delay, $attachments );
					}
				}
				if ( class_exists( 'ewwwflag' ) ) {
					ewww_image_optimizer_bulk_flag( $delay );
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
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				if ( empty( $assoc_args['noprompt'] ) ) {
					/* translators: %d: number of images */
					WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', $pending_count, 'ewww-image-optimizer' ), $pending_count ) );
				}
				$_REQUEST['ewww_batch_limit'] = 1;
				while ( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
					$something = 1;
				}
				ewww_image_optimizer_bulk_media_cleanup();
				break;
			case 'nextgen':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_ngg_resume', '' );
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				if ( class_exists( 'EwwwNgg' ) ) {
					global $ngg;
					if ( preg_match( '/^2/', $ngg->version ) ) {
						list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'ngg' );
						if ( empty( $assoc_args['noprompt'] ) ) {
							/* translators: 1-4: number of images */
							WP_CLI::confirm( sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', 'ewww-image-optimizer' ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
						}
						ewww_image_optimizer_bulk_ngg( $delay );
					} else {
						$attachments = ewww_image_optimizer_scan_next();
						if ( empty( $assoc_args['noprompt'] ) ) {
							/* translators: %d: number of images */
							WP_CLI::confirm( sprintf( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', count( $attachments ), 'ewww-image-optimizer' ), count( $attachments ) ) );
						}
						ewww_image_optimizer_bulk_next( $delay, $attachments );
					}
				} else {
					WP_CLI::error( __( 'NextGEN/Nextcellent not installed.', 'ewww-image-optimizer' ) );
				}
				break;
			case 'flagallery':
				if ( $ewww_reset ) {
					update_option( 'ewww_image_optimizer_bulk_flag_resume', '' );
					WP_CLI::line( __( 'Bulk status has been reset, starting from the beginning.', 'ewww-image-optimizer' ) );
				}
				if ( class_exists( 'ewwwflag' ) ) {
					list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'flag' );
					if ( empty( $assoc_args['noprompt'] ) ) {
						/* translators: 1-4: number of images */
						WP_CLI::confirm( sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', 'ewww-image-optimizer' ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
					}
					ewww_image_optimizer_bulk_flag( $delay );
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
}

WP_CLI::add_command( 'ewwwio', 'EWWWIO_CLI' );

/**
 * Cleanup after ourselves after a bulk operation.
 */
function ewww_image_optimizer_bulk_media_cleanup() {
	// All done, so we can update the bulk options with empty values...
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
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
function ewww_image_optimizer_bulk_flag( $delay = 0 ) {
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
	// Set the resume flag to indicate the bulk operation is in progress.
	update_option( 'ewww_image_optimizer_bulk_flag_resume', 'true' );
	// Need this file to work with flag meta.
	require_once( WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php' );
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
		$meta      = new flagMeta( $id );
		$file_path = $meta->image->imagePath;
		// Optimize the full-size version.
		$fres = ewww_image_optimizer( $file_path, 3, false, false, true );
		WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . ' ' . esc_html( $meta->image->filename ) );
		/* translators: %s: compression results */
		WP_CLI::line( sprintf( __( 'Full size – %s', 'ewww-image-optimizer' ), html_entity_decode( $fres[1] ) ) );
		if ( ! empty( $meta->image->meta_data['webview'] ) ) {
			// Determine path of the webview.
			$web_path = $meta->image->webimagePath;
			$wres     = ewww_image_optimizer( $web_path, 3, false, true );
			/* translators: %s: compression results */
			WP_CLI::line( sprintf( __( 'Optimized size – %s', 'ewww-image-optimizer' ), html_entity_decode( $wres[1] ) ) );
		}
		$thumb_path = $meta->image->thumbPath;
		// Optimize the thumbnail.
		$tres = ewww_image_optimizer( $thumb_path, 3, false, true );
		/* translators: %s: compression results */
		WP_CLI::line( sprintf( __( 'Thumbnail – %s', 'ewww-image-optimizer' ), html_entity_decode( $tres[1] ) ) );
		// Determine how much time the image took to process...
		$elapsed = microtime( true ) - $started;
		// and output it to the user.
		/* translators: %s: localized number of seconds */
		WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed ) ) );
		// Retrieve the list of attachments left to work on.
		$attachments = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
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
 * Search for all NextGEN uploads using WP-CLI command.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_scan_ngg() {
	$images = null;
	if ( get_option( 'ewww_image_optimizer_bulk_ngg_resume' ) ) {
		// Get the list of attachment IDs from the queue.
		$images = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
	} else {
		// Otherwise, get all the images in the db.
		global $wpdb;
		$images = $wpdb->get_col( "SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC" );
	}
	// Store the image IDs to process in the db.
	update_option( 'ewww_image_optimizer_bulk_ngg_attachments', $images, false );
	return $images;
}

/**
 * Bulk Optimize all NextGEN uploads from WP-CLI.
 *
 * @global object $wpdb
 * @global object $ewwwngg
 *
 * @param int $delay Number of seconds to pause between images.
 */
function ewww_image_optimizer_bulk_ngg( $delay = 0 ) {
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
	global $ewwwngg;
	foreach ( $images as $id ) {
		if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
		// Find out what time we started, in microseconds.
		$started = microtime( true );
		// Creating the 'registry' object for working with nextgen.
		$registry = C_Component_Registry::get_instance();
		// Creating a database storage object from the 'registry' object.
		$storage = $registry->get_utility( 'I_Gallery_Storage' );
		// Get an image object.
		$image = $storage->object->_image_mapper->find( $id );
		$image = $ewwwngg->ewww_added_new_image( $image, $storage );
		// Output the results of the optimization.
		WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . ' ' . basename( $storage->object->get_image_abspath( $image, 'full' ) ) );
		// Get an array of sizes available for the $image.
		$sizes = $storage->get_image_sizes();
		if ( ewww_image_optimizer_iterable( $sizes ) ) {
			// Output the results for each $size.
			foreach ( $sizes as $size ) {
				if ( 'full' === $size ) {
					/* translators: %s: compression results */
					WP_CLI::line( sprintf( __( 'Full size - %s', 'ewww-image-optimizer' ), html_entity_decode( $image->meta_data['ewww_image_optimizer'] ) ) );
				} elseif ( 'thumbnail' === $size ) {
					// Output the results of the thumb optimization.
					/* translators: %s: compression results */
					WP_CLI::line( sprintf( __( 'Thumbnail - %s', 'ewww-image-optimizer' ), html_entity_decode( $image->meta_data[ $size ]['ewww_image_optimizer'] ) ) );
				} else {
					// Output savings for any other sizes, if they ever exist...
					WP_CLI::line( ucfirst( $size ) . ' - ' . html_entity_decode( $image->meta_data[ $size ]['ewww_image_optimizer'] ) );
				}
			}
		}
		// Output how much time we spent.
		$elapsed = microtime( true ) - $started;
		/* translators: %s: number of seconds */
		WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed ) ) );
		// Get the list of attachments remaining from the db.
		$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
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
function ewww_image_optimizer_scan_next() {
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
 * Bulk Optimize all NextGEN uploads from WP-CLI.
 *
 * @param int   $delay Number of seconds to pause between images.
 * @param array $attachments A list of image IDs to optimize.
 */
function ewww_image_optimizer_bulk_next( $delay, $attachments ) {
	// Toggle the resume flag to indicate an operation is in progress.
	update_option( 'ewww_image_optimizer_bulk_ngg_resume', 'true' );
	// Need this file to work with metadata.
	require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
	foreach ( $attachments as $id ) {
		if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
		// Find out what time we started, in microseconds.
		$started = microtime( true );
		// Get the metadata.
		$meta = new nggMeta( $id );
		// Retrieve the filepath.
		$file_path = $meta->image->imagePath;
		// Run the optimizer on the current image.
		$fres = ewww_image_optimizer( $file_path, 2, false, false, true );
		// Update the metadata of the optimized image.
		nggdb::update_image_meta( $id, array(
			'ewww_image_optimizer' => $fres[1],
		) );
		// Output the results of the optimization.
		WP_CLI::line( __( 'Optimized image:', 'ewww-image-optimizer' ) . $meta->image->filename );
		/* translators: %s: compression results */
		WP_CLI::line( sprintf( __( 'Full size - %s', 'ewww-image-optimizer' ), html_entity_decode( $fres[1] ) ) );
		// Get the filepath of the thumbnail image.
		$thumb_path = $meta->image->thumbPath;
		// Run the optimization on the thumbnail.
		$tres = ewww_image_optimizer( $thumb_path, 2, false, true );
		// Output the results of the thumb optimization.
		/* translators: %s: compression results */
		WP_CLI::line( sprintf( __( 'Thumbnail - %s', 'ewww-image-optimizer' ), html_entity_decode( $tres[1] ) ) );
		// Output how much time we spent.
		$elapsed = microtime( true ) - $started;
		/* translators: %s: number of seconds */
		WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed ) ) );
		// Get the list of attachments remaining from the db.
		$attachments = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
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

/**
 * Increases the EWWW IO timeout for scanning images.
 *
 * @param int $time_limit The number of seconds before a timeout happens.
 * @return int The number of seconds to wait before a timeout from the CLI.
 */
function ewww_image_optimizer_cli_timeout( $time_limit ) {
	return 9999;
}
