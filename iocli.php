<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * implements wp-cli extension for bulk optimizing
 */
class EWWWIO_CLI extends WP_CLI_Command {
	/**
	 * Bulk Optimize Images
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
	 */
	function optimize( $args, $assoc_args ) {
		global $ewww_defer;
		$ewww_defer = false;
		// because NextGEN hasn't flushed it's buffers...
		while( @ob_end_flush() );
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
			WP_CLI::line( __('Forcing re-optimization of previously processed images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
			$_REQUEST['ewww_force'] = true;
		}
		WP_CLI::line( sprintf( _x('Optimizing %1$s with a %2$d second pause between images.', 'string will be something like "media" or "nextgen"', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $library, $delay ) );
		// let's get started, shall we?
		ewww_image_optimizer_admin_init();
		// and what shall we do?
		switch( $library ) {
			case 'all':
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_bulk_resume', '');
					update_option('ewww_image_optimizer_aux_resume', '');
					update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
					update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
					update_option('ewww_image_optimizer_bulk_ngg_resume', '');
					update_option('ewww_image_optimizer_bulk_flag_resume', '');
					ewww_image_optimizer_delete_pending();
					WP_CLI::line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				WP_CLI::line( __( 'Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				ewww_image_optimizer_bulk_script( 'media_page_ewww-image-optimizer-bulk' );
				$fullsize_count = ewww_image_optimizer_count_optimized ('media');
				ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				WP_CLI::line( sprintf( __( '%1$d images in the Media Library have been selected.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ) );
				WP_CLI::line( __( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				// do a filter to increase the timeout to 999 or something crazy
				add_filter( 'ewww_image_optimizer_timeout', 'ewww_image_optimizer_cli_timeout', 200 );
				ewww_image_optimizer_media_scan( 'ewww-image-optimizer-cli' );
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				if ( class_exists( 'EwwwNgg' ) ) {
					global $ngg;
					if ( preg_match( '/^2/', $ngg->version ) ) {
						list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized ('ngg');
						WP_CLI::line( 'Nextgen: ' . sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
					} else {
						$attachments = ewww_image_optimizer_scan_next();
						WP_CLI::line( 'Nextgen: ' . sprintf( __( 'We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count( $attachments ) ) );
					}
				}
				if ( class_exists( 'ewwwflag' ) ) {
					list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized ('flag');
					WP_CLI::line( 'Flagallery: ' . sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
				}
				if ( empty( $assoc_args['noprompt'] ) ) {
					WP_CLI::confirm( sprintf( __( '%d images in other folders need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $pending_count ) );
				}
				//ewww_image_optimizer_bulk_media( $delay );
				$_REQUEST['ewww_batch_limit'] = 1;
				while( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
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
				//ewww_image_optimizer_bulk_other( $delay );
				break;
			case 'media':
			case 'other':
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_bulk_resume', '');
					update_option('ewww_image_optimizer_aux_resume', '');
					update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
					update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
					ewww_image_optimizer_delete_pending();
					WP_CLI::line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				ewww_image_optimizer_bulk_script( 'media_page_ewww-image-optimizer-bulk' );
				$fullsize_count = ewww_image_optimizer_count_optimized ('media');
				WP_CLI::line( sprintf( __( '%1$d images in the Media Library have been selected.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ) );
				WP_CLI::line( __( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				WP_CLI::line( __( 'Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				// do a filter to increase the timeout to 999 or something crazy
				add_filter( 'ewww_image_optimizer_timeout', 'ewww_image_optimizer_cli_timeout', 200 );
				ewww_image_optimizer_media_scan( 'ewww-image-optimizer-cli' );
				$pending_count = ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				if ( empty( $assoc_args['noprompt'] ) ) {
					WP_CLI::confirm( sprintf( __( 'There are %d images ready to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $pending_count ) );
				}
				$_REQUEST['ewww_batch_limit'] = 1;
				while( ewww_image_optimizer_bulk_loop( 'ewww-image-optimizer-cli', $delay ) ) {
					$something = 1;
				}
				ewww_image_optimizer_bulk_media_cleanup();
				break;
			case 'nextgen':
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_bulk_ngg_resume', '');
					WP_CLI::line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				if ( class_exists( 'EwwwNgg' ) ) {
					global $ngg;
					if ( preg_match( '/^2/', $ngg->version ) ) {
						list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized ('ngg');
						if ( empty( $assoc_args['noprompt'] ) ) {
							WP_CLI::confirm( sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
						}
						ewww_image_optimizer_bulk_ngg( $delay );
					} else {
						$attachments = ewww_image_optimizer_scan_next();
						if ( empty( $assoc_args['noprompt'] ) ) {
							WP_CLI::confirm( sprintf( __( 'We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count( $attachments ) ) );
						}
						ewww_image_optimizer_bulk_next( $delay, $attachments );
					}
				} else {
					WP_CLI::error( __( 'NextGEN/Nextcellent not installed.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				break;
			case 'flagallery':
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_bulk_flag_resume', '');
					WP_CLI::line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				if ( class_exists( 'ewwwflag' ) ) {
					list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized ('flag');
					if ( empty( $assoc_args['noprompt'] ) ) {
						WP_CLI::confirm( sprintf( __( '%1$d images have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) );
					}
					ewww_image_optimizer_bulk_flag( $delay );
				} else {
					WP_CLI::error( __( 'Grand Flagallery not installed.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				break;
/*			case 'other':
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_aux_resume', '');
					WP_CLI::line( __('Bulk status has been reset, starting from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
				WP_CLI::line( __( 'Scanning, this could take a while', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
				if ( empty( $assoc_args['noprompt'] ) ) {
					WP_CLI::confirm( sprintf( __( '%1$d images in other folders need optimizing.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), ewww_image_optimizer_aux_images_table_count_pending() ) );
				}
				ewww_image_optimizer_bulk_other( $delay );
				break;*/
			default:
				if ( $ewww_reset ) {
					update_option('ewww_image_optimizer_bulk_resume', '');
					update_option('ewww_image_optimizer_aux_resume', '');
					update_option('ewww_image_optimizer_bulk_ngg_resume', '');
					update_option('ewww_image_optimizer_bulk_flag_resume', '');
					WP_CLI::success( __('Bulk status has been reset, the next bulk operation will start from the beginning.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				} else {
					WP_CLI::line( __('Please specify a valid library option, see "wp-cli help ewwwio optimize" for more information.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				}
		}
	}
}

WP_CLI::add_command( 'ewwwio', 'EWWWIO_CLI' );

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_bulk_media_cleanup( $delay = 0 ) {
	// all done, so we can update the bulk options with empty values
	update_option('ewww_image_optimizer_bulk_resume', '');
	update_option('ewww_image_optimizer_aux_resume', '');
	update_option('ewww_image_optimizer_bulk_attachments', '', false);
	// and let the user know we are done
	WP_CLI::success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
}

function ewww_image_optimizer_bulk_flag( $delay = 0 ) {
	$ids = null;
	// if there is an operation to resume, get those IDs from the db
	if ( get_option('ewww_image_optimizer_bulk_flag_resume') ) {
		$ids = get_option('ewww_image_optimizer_bulk_flag_attachments');
	// otherwise, if we are on the main bulk optimize page, just get all the IDs available
	} else {
		global $wpdb;
		$ids = $wpdb->get_col("SELECT pid FROM $wpdb->flagpictures ORDER BY sortorder ASC");
		// store the IDs to optimize in the options table of the db
		update_option('ewww_image_optimizer_bulk_flag_attachments', $ids, false);
	}
	// set the resume flag to indicate the bulk operation is in progress
	update_option('ewww_image_optimizer_bulk_flag_resume', 'true');
	// need this file to work with flag meta
	require_once( WP_CONTENT_DIR . '/plugins/flash-album-gallery/lib/meta.php' );
	if ( ! ewww_image_optimizer_iterable( $ids ) ) {
		WP_CLI::line( __('You do not appear to have uploaded any images yet.', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
		return;
	}
	foreach ( $ids as $id ) {
		sleep( $delay );
		// record the starting time for the current image (in microseconds)
		$started = microtime(true);
		// retrieve the meta for the current ID
		$meta = new flagMeta($id);
		$file_path = $meta->image->imagePath;
		// optimize the full-size version
		$fres = ewww_image_optimizer($file_path, 3, false, false, true);
		$meta->image->meta_data['ewww_image_optimizer'] = $fres[1];
		// let the user know what happened
		WP_CLI::line( __( 'Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " " . esc_html($meta->image->filename) );
		WP_CLI::line( sprintf( __( 'Full size – %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $fres[1] ) ) );
		if ( ! empty( $meta->image->meta_data['webview'] ) ) {
			// determine path of the webview
			$web_path = $meta->image->webimagePath;
			$wres = ewww_image_optimizer($web_path, 3, false, true);
			$meta->image->meta_data['webview']['ewww_image_optimizer'] = $wres[1];
			WP_CLI::line( sprintf( __( 'Optimized size – %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $wres[1] ) ) );
		}
		$thumb_path = $meta->image->thumbPath;
		// optimize the thumbnail
		$tres = ewww_image_optimizer($thumb_path, 3, false, true);
		$meta->image->meta_data['thumbnail']['ewww_image_optimizer'] = $tres[1];
		// and let the user know the results
		WP_CLI::line( sprintf( __( 'Thumbnail – %s', EWWW_IMAGE_OPTIMIZER_DOMAIN), html_entity_decode( $tres[1] ) ) );
		flagdb::update_image_meta($id, $meta->image->meta_data);
		// determine how much time the image took to process
		$elapsed = microtime(true) - $started;
		// and output it to the user
		WP_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed ) );
		// retrieve the list of attachments left to work on
		$attachments = get_option('ewww_image_optimizer_bulk_flag_attachments');
		// take the first image off the list
		if (!empty($attachments))
			array_shift($attachments);
		// and send the list back to the db
		update_option('ewww_image_optimizer_bulk_flag_attachments', $attachments, false);
	}
	// reset the bulk flags in the db
	update_option('ewww_image_optimizer_bulk_flag_resume', '');
	update_option('ewww_image_optimizer_bulk_flag_attachments', '', false);
	// and let the user know we are done
	WP_CLI::success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
}

function ewww_image_optimizer_scan_ngg() {
	$images = null;
	if ( get_option('ewww_image_optimizer_bulk_ngg_resume') ) {
		// get the list of attachment IDs from the db
		$images = get_option('ewww_image_optimizer_bulk_ngg_attachments');
	// otherwise, get all the images in the db
	} else {
		global $wpdb;
		$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
	}
	// store the image IDs to process in the db
	update_option('ewww_image_optimizer_bulk_ngg_attachments', $images, false);
	return $images;
}

function ewww_image_optimizer_bulk_ngg( $delay = 0 ) {
	if ( get_option('ewww_image_optimizer_bulk_ngg_resume') ) {
		// get the list of attachment IDs from the db
		$images = get_option('ewww_image_optimizer_bulk_ngg_attachments');
	// otherwise, get all the images in the db
	} else {
		global $wpdb;
		$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
		// store the image IDs to process in the db
		update_option('ewww_image_optimizer_bulk_ngg_attachments', $images, false);
		// toggle the resume flag to indicate an operation is in progress
		update_option('ewww_image_optimizer_bulk_ngg_resume', 'true');
	}
	if ( ! ewww_image_optimizer_iterable( $images ) ) {
		WP_CLI::line( __('You do not appear to have uploaded any images yet.', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
		return;
	}
	global $ewwwngg;
	foreach ( $images as $id ) {
		sleep( $delay );
		// find out what time we started, in microseconds
		$started = microtime(true);
		// creating the 'registry' object for working with nextgen
		$registry = C_Component_Registry::get_instance();
		// creating a database storage object from the 'registry' object
		$storage  = $registry->get_utility('I_Gallery_Storage');
		// get an image object
		$image = $storage->object->_image_mapper->find($id);
		$image = $ewwwngg->ewww_added_new_image ($image, $storage);
		// output the results of the optimization
		WP_CLI::line( __('Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " " . basename($storage->object->get_image_abspath($image, 'full')));
		// get an array of sizes available for the $image
		$sizes = $storage->get_image_sizes();
		if ( ewww_image_optimizer_iterable( $sizes ) ) {
			// output the results for each $size
			foreach ( $sizes as $size ) {
				if ($size === 'full') {
					WP_CLI::line( sprintf( __( 'Full size - %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $image->meta_data['ewww_image_optimizer'] ) ) );
				} elseif ($size === 'thumbnail') {
					// output the results of the thumb optimization
					WP_CLI::line( sprintf( __( 'Thumbnail - %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $image->meta_data[$size]['ewww_image_optimizer'] ) ) );
				} else {
					// output savings for any other sizes, if they ever exist...
					WP_CLI::line( ucfirst($size) . " - " . html_entity_decode( $image->meta_data[$size]['ewww_image_optimizer'] ) );
				}
			}
		}
		// outupt how much time we spent
		$elapsed = microtime(true) - $started;
		WP_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed ) );
		// get the list of attachments remaining from the db
		$attachments = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		// remove the first item
		if (!empty($attachments))
			array_shift($attachments);
		// and store the list back in the db
		update_option('ewww_image_optimizer_bulk_ngg_attachments', $attachments, false);
	}

	// reset all the bulk options in the db
	update_option('ewww_image_optimizer_bulk_ngg_resume', '');
	update_option('ewww_image_optimizer_bulk_ngg_attachments', '', false);
	// and let the user know we are done
	WP_CLI::success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
}

function ewww_image_optimizer_scan_next() {
		$images = null;
		// see if there is a previous operation to resume
//		$resume = get_option('ewww_image_optimizer_bulk_ngg_resume');
		// otherwise, if we have an operation to resume
		if ( get_option('ewww_image_optimizer_bulk_ngg_resume') ) {
			// get the list of attachment IDs from the db
			$images = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		// otherwise, if we are on the standard bulk page, get all the images in the db
		} else {
			//$ewww_debug .= "starting from scratch, grabbing all the images<br />";
			global $wpdb;
			$images = $wpdb->get_col("SELECT pid FROM $wpdb->nggpictures ORDER BY sortorder ASC");
		}
		
		// store the image IDs to process in the db
		update_option('ewww_image_optimizer_bulk_ngg_attachments', $images, false);
	return $images;
}

function ewww_image_optimizer_bulk_next( $delay, $attachments ) {
	// toggle the resume flag to indicate an operation is in progress
	update_option('ewww_image_optimizer_bulk_ngg_resume', 'true');
	// need this file to work with metadata
	require_once( WP_CONTENT_DIR . '/plugins/nextcellent-gallery-nextgen-legacy/lib/meta.php' );
	foreach ( $attachments as $id ) {
		sleep( $delay );
		// find out what time we started, in microseconds
		$started = microtime(true);
		// get the metadata
		$meta = new nggMeta($id);
		// retrieve the filepath
		$file_path = $meta->image->imagePath;
		// run the optimizer on the current image
		$fres = ewww_image_optimizer($file_path, 2, false, false, true);
		// update the metadata of the optimized image
		nggdb::update_image_meta($id, array('ewww_image_optimizer' => $fres[1]));
		// output the results of the optimization
		WP_CLI::line( __( 'Optimized image:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . $meta->image->filename );
		WP_CLI::line( sprintf( __( 'Full size - %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $fres[1] ) ) );
		// get the filepath of the thumbnail image
		$thumb_path = $meta->image->thumbPath;
		// run the optimization on the thumbnail
		$tres = ewww_image_optimizer($thumb_path, 2, false, true);
		// output the results of the thumb optimization
		WP_CLI::line( sprintf( __( 'Thumbnail - %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), html_entity_decode( $tres[1] ) ) );
		// outupt how much time we spent
		$elapsed = microtime(true) - $started;
		WP_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $elapsed ) );
		// get the list of attachments remaining from the db
		$attachments = get_option('ewww_image_optimizer_bulk_ngg_attachments');
		// remove the first item
		if (!empty($attachments))
			array_shift($attachments);
		// and store the list back in the db
		update_option('ewww_image_optimizer_bulk_ngg_attachments', $attachments, false);
	}
	// reset all the bulk options in the db
	update_option('ewww_image_optimizer_bulk_ngg_resume', '');
	update_option('ewww_image_optimizer_bulk_ngg_attachments', '', false);
	// and let the user know we are done
	WP_CLI::success( __('Finished Optimization!', EWWW_IMAGE_OPTIMIZER_DOMAIN) );
}

function ewww_image_optimizer_cli_timeout( $time_limit ) {
	return 9999;
}
