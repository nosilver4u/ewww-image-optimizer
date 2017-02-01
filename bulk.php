<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// presents the bulk optimize form with the number of images, and runs it once they submit the button
function ewww_image_optimizer_bulk_preview() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// retrieve the attachment IDs that were pre-loaded in the database
?>
	<div class="wrap"> 
	<h1>
<?php 		esc_html_e( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			echo '<span><a id="ewww-bulk-credits-available" target="_blank" class="page-title-action" style="float:right;" href="https://ewww.io/my-account/">' . esc_html__( 'Image credits available:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . ewww_image_optimizer_cloud_quota() . '</a></span>';
		}
	echo '</h1>';
	// Retrieve the value of the 'bulk resume' option and set the button text for the form to use
	$resume = get_option( 'ewww_image_optimizer_bulk_resume' );
	if ( empty( $resume ) ) {
		$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );
		$button_text = esc_attr__( 'Start optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} elseif ( $resume == 'scanning' ) {
		$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );
		$button_text = esc_attr__( 'Start optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$fullsize_count = ewww_image_optimizer_aux_images_table_count_pending();
		$button_text = esc_attr__( 'Resume previous optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	}
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// create the html for the bulk optimize form and status divs
?>
		<div id="ewww-bulk-loading">
			<p id="ewww-loading" class="ewww-bulk-info" style="display:none"><?php esc_html_e( 'Importing', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>&nbsp;<img src='<?php echo $loading_image; ?>' /></p>
		</div>
		<div id="ewww-bulk-progressbar"></div>
		<div id="ewww-bulk-timer" style="float:right;"></div>
		<div id="ewww-bulk-counter"></div>
		<form id="ewww-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>" />
		</form>
		<div id="ewww-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="ewww-bulk-last" class="postbox">
					<button type="button" class="handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="hndle"><span><?php esc_html_e( 'Last Batch Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
			<div class="meta-box-sortables">
				<div id="ewww-bulk-status" class="postbox">
					<button type="button" class="handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></span>
						<span class="toggle-indicator" aria-hidden="true"></span>
					</button>
					<h2 class="hndle"><span><?php esc_html_e( 'Optimization Log', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
		<form class="ewww-bulk-form">
			<p><?php esc_html_e( 'Previously optimized images will be skipped by default.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></label>&emsp;<input type="checkbox" id="ewww-force" name="ewww-force"></p>
			<p><label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php if ( $delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ) { echo (int) $delay; } else { echo 0; } ?>"></p>
			<div id="ewww-delay-slider" style="width:50%"></div>
		</form>
<!--		<h2 class="ewww-bulk-media"><?php esc_html_e( 'Optimize Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></h2>-->
<?php		if ( $fullsize_count < 1 ) {
			echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>';
		} else { ?>
			<div id="ewww-bulk-forms">
<?php			if ( $resume == 'true' ) { 
				//if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				//	$credits_needed = $fullsize_count * ( count( get_intermediate_image_sizes() ) + 1 );
				//} ?>
				<p class="ewww-media-info ewww-bulk-info"><?php printf( esc_html__( 'There are %d images ready to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ); ?> <?php //if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && $credits_needed > 0 ) { printf( esc_html__( 'This could require approximately %d image credits to complete.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $credits_needed ); } ?></p>
<?php			} else { 
				//if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				//	$credits_needed = $unoptimized_count + $unoptimized_resize_count;
				//} ?>
				<p class="ewww-media-info ewww-bulk-info"><?php printf( esc_html__( '%1$d images in the Media Library have been selected.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ); ?><br />
				<?php esc_html_e( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
				<!--<p class="ewww-media-info ewww-bulk-info"><?php // printf( esc_html__( '%1$d images in the Media Library have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ); ?>  <?php // if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && $credits_needed > 0 ) { printf( esc_html__( 'This could require approximately %d image credits to complete.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $credits_needed ); } ?><br />-->
<?php			} ?>
			<?php //esc_html_e( 'Previously optimized images will be skipped by default.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>
			<p id="ewww-nothing" class="ewww-bulk-info" style="display:none"><?php esc_html_e( 'There are no images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<p id="ewww-scanning" class="ewww-bulk-info" style="display:none"><?php printf( esc_html__( 'Stage 1, %d images left to scan.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ); ?>&nbsp;<img src='<?php echo $loading_image; ?>' alt='loading'/></p>
			<form id="ewww-aux-start" class="ewww-bulk-form" method="post" action="">
<?php				if ( $resume != 'true' ) { ?>
				<input id="ewww-aux-first" type="submit" class="button-primary action" value="<?php esc_attr_e( 'Scan for unoptimized images', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>" />
				<?php } ?>
				<input id="ewww-aux-again" type="submit" class="button-secondary action" style="display:none" value="<?php esc_attr_e( 'Scan Again', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>" />
			</form>
			<form id="ewww-bulk-start" class="ewww-bulk-form" <?php if ( $resume != 'true' ) { ?>style="display:none" <?php } ?>method="post" action="">
				<input id="ewww-aux-first" type="submit" class="button-primary action" value="<?php echo $button_text; ?>" />
			</form>
<?php		}
		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if ( $resume == 'true' ) { 
?>
			<p class="ewww-media-info ewww-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<form class="ewww-bulk-form" method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk-reset', 'ewww_wpnonce' ); ?>
				<input type="hidden" name="ewww_reset" value="1">
				<button id="ewww-bulk-reset" type="submit" class="button-secondary action"><?php esc_html_e( 'Reset Status', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
			</form>
<?php		}
	echo '</div>';
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_aux_images();
}

// detect if memory limits are a bit low and reduce the number of records we query at a time
function ewww_image_optimizer_reduce_query_count( $max_query ) {
	$memory_limit = ewwwio_memory_limit();
	if ( $memory_limit <= 33560000 ) {
		return 500;
	} elseif ( $memory_limit <= 67120000 ) {
		return 1000;
	} elseif ( $memory_limit <= 134300000 ) {
		return 1500;
	} elseif ( $memory_limit <= 268500000 ) {
		return 3000;
	}
	return $max_query;
}

// retrieve image counts for the bulk process
function ewww_image_optimizer_count_optimized( $gallery, $return_ids = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$full_count = 0;
	$unoptimized_full = 0;
	$unoptimized_re = 0;
	$resize_count = 0;
	$attachment_query = '';
	ewwwio_debug_message( "scanning for $gallery" );
	// retrieve the time when the optimizer starts
	$started = microtime(true);
	$max_query = apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );
	$max_query = (int) $max_query;
	$attachment_query_count = 0;
	switch ( $gallery ) {
		case 'media':
			$ids = array();
			$resume = get_option( 'ewww_image_optimizer_bulk_resume' );
			// see if we were given attachment IDs to work with via GET/POST
		        if ( ! empty( $_REQUEST['ids'] ) || $resume ) {
				ewwwio_debug_message( 'we have received attachment ids via $_REQUEST' );
				// retrieve the attachment IDs that were pre-loaded in the database
				if ( 'scanning' == $resume ) {
					$finished = (array) get_option( 'ewww_image_optimizer_bulk_attachments' );
					$remaining = (array) get_option( 'ewww_image_optimizer_scanning_attachments' );
					$attachment_ids = array_merge( $finished, $remaining );
				} elseif ( $resume ) {
					// this shouldn't ever happen, but doesn't hurt to account for the use case, just in case something changes in the future
					$attachment_ids = get_option( 'ewww_image_optimizer_bulk_attachments' );
				} else {
					$attachment_ids = get_option( 'ewww_image_optimizer_scanning_attachments' );
				}
				if ( ! empty( $attachment_ids ) ) {
					$full_count = count( $attachment_ids );
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'AND metas.post_id IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			} else {
				$full_count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%')" );
			}
			return $full_count;
			break;
		case 'ngg':
			// see if we were given attachment IDs to work with via GET/POST
		        if ( ! empty( $_REQUEST['ewww_inline'] ) || get_option( 'ewww_image_optimizer_bulk_ngg_resume') ) {
				// retrieve the attachment IDs that were pre-loaded in the database
				$attachment_ids = get_option('ewww_image_optimizer_bulk_ngg_attachments');
				while ( $attachment_ids && $attachment_query_count < $max_query ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					$attachment_query_count++;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
			}
			// creating the 'registry' object for working with nextgen
			$registry = C_Component_Registry::get_instance();
			// creating a database storage object from the 'registry' object
			$storage  = $registry->get_utility('I_Gallery_Storage');
			// get an array of sizes available for the $image
			$sizes = $storage->get_image_sizes();
			$offset = 0;
			while ( $attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->nggpictures $attachment_query LIMIT $offset, $max_query" ) ) {
				foreach ( $attachments as $attachment ) {
					if ( class_exists( 'Ngg_Serializable' ) ) {
				        	$serializer = new Ngg_Serializable();
				        	$meta = $serializer->unserialize( $attachment );
					} else {
						$meta = unserialize( $attachment );
					}
					if ( ! is_array( $meta ) ) {
						continue;
					}
					if ( empty( $meta['ewww_image_optimizer'] ) ) {
							$unoptimized_full++;
					}
					if ( ewww_image_optimizer_iterable( $sizes ) ) {
						foreach ( $sizes as $size ) {
							if ( $size !== 'full' ) {
								$resize_count++;
								if ( empty( $meta[ $size ]['ewww_image_optimizer'] ) ) {
									$unoptimized_re++;
								}
							}
						}
					}
				}
				$full_count += count($attachments);
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
		case 'flag':
			if ( ! empty( $_REQUEST['doaction'] ) || get_option( 'ewww_image_optimizer_bulk_flag_resume' ) ) {
				// retrieve the attachment IDs that were pre-loaded in the database
				$attachment_ids = get_option('ewww_image_optimizer_bulk_flag_attachments');
				while ( $attachment_ids && $attachment_query_count < $max_query ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					$attachment_query_count++;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
			}
			$offset = 0;
			while ( $attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->flagpictures $attachment_query LIMIT $offset, $max_query" ) ) {
				foreach ($attachments as $attachment) {
					$meta = unserialize( $attachment );
					if ( ! is_array( $meta ) ) {
						continue;
					}
					if (empty($meta['ewww_image_optimizer'])) {
						$unoptimized_full++;
					}
					if (!empty($meta['webview'])) {
						$resize_count++;
						if(empty($meta['webview']['ewww_image_optimizer'])) {
							$unoptimized_re++;
						}
					}
					if (!empty($meta['thumbnail'])) {
						$resize_count++;
						if(empty($meta['thumbnail']['ewww_image_optimizer'])) {
							$unoptimized_re++;
						}
					}
				}
				$full_count += count($attachments);
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
	}
	if ( empty( $full_count ) && ! empty( $attachment_ids ) ) {
		ewwwio_debug_message( 'query appears to have failed, just counting total images instead' );
		$full_count = count( $attachment_ids );
	}
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "counting images took $elapsed seconds" );
	ewwwio_debug_message( "found $full_count fullsize ($unoptimized_full unoptimized), and $resize_count resizes ($unoptimized_re unoptimized)" );
	ewwwio_memory( __FUNCTION__ );
	if ( $return_ids ) {
		return $ids;
	} else {
		return array( $full_count, $unoptimized_full, $resize_count, $unoptimized_re );
	}
}

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_bulk_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// make sure we are being called from the bulk optimization page
	if ( 'media_page_ewww-image-optimizer-bulk' != $hook ) {
		return;
	}
        // initialize the $attachments variable
        $attachments = array();
        // check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if ( ! empty( $_REQUEST['ewww_reset'] ) && wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk-reset' ) ) {
		// set the 'bulk resume' option to an empty string to reset the bulk operation
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		update_option( 'ewww_image_optimizer_aux_resume', '' );
		update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
		update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
		ewww_image_optimizer_delete_pending();
	}
	global $wpdb;
        // check to see if we are supposed to reset the bulk operation and verify we are authorized to do so
	if ( ! empty( $_REQUEST['ewww_reset_aux'] ) && wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-aux-images-reset' ) ) {
		// set the 'bulk resume' option to an empty string to reset the bulk operation
		update_option( 'ewww_image_optimizer_aux_resume', '' );
		$wpdb->query( "DELETE from $wpdb->ewwwio_images WHERE image_size IS NULL" );
	}
        // check to see if we are supposed to convert the auxiliary images table and verify we are authorized to do so
	if ( ! empty( $_REQUEST['ewww_convert'] ) && wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-aux-images-convert' ) ) {
		ewww_image_optimizer_aux_images_convert();
	}
	// check the 'bulk resume' option
	$resume = get_option('ewww_image_optimizer_bulk_resume');
	$scanning = get_option('ewww_image_optimizer_aux_resume');
	if ( ! $resume && ! $scanning ) {
		update_option( 'ewww_image_optimizer_scanning_attachments', '', false );
		update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
		ewww_image_optimizer_delete_pending();
	}
//	$attachments = get_option( 'ewww_image_optimizer_scanning_attachments' );
	// see if we were given attachment IDs to work with via GET/POST
	$ids = array();
        if ( ! empty( $_REQUEST['ids'] ) && ( preg_match( '/^[\d,]+$/', $_REQUEST['ids'], $request_ids ) || is_numeric( $_REQUEST['ids'] ) ) ) {
		ewww_image_optimizer_delete_pending();
		set_transient( 'ewww_image_optimizer_skip_aux', true, 3 * MINUTE_IN_SECONDS );
		if ( is_numeric( $_REQUEST['ids'] ) ) {
			$ids[] = (int) $_REQUEST['ids'];
		} else {
			$ids = explode( ',', $request_ids[0] );
		}
		$sample_post_type = get_post_type( $ids[0] );
		//ewwwio_debug_message( "ids: " . $request_ids[0] );
		ewwwio_debug_message( "post type (checking for ims_gallery): $sample_post_type" );
		if ( 'ims_gallery' == $sample_post_type ) {
			$attachments = array();
			foreach ( $ids as $gid ) {
				ewwwio_debug_message( "gallery id: $gid" );
				$ims_images = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'ims_image' AND post_mime_type LIKE '%%image%%' AND post_parent = $gid ORDER BY ID DESC" );
				$attachments = array_merge( $attachments, $ims_images );
			}
		} else {
			ewwwio_debug_message( "validating requested ids: {$request_ids[0]}" );
	                // retrieve post IDs correlating to the IDs submitted to make sure they are all valid
			$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') AND ID IN ({$request_ids[0]}) ORDER BY ID DESC" );
		}
		// unset the 'bulk resume' option since we were given specific IDs to optimize
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
        // check if there is a previous bulk operation to resume
        } elseif ( $resume == 'scanning' ) {
		// retrieve the attachment IDs that have not been finished from the 'scanning attachments' option
		$attachments = get_option( 'ewww_image_optimizer_scanning_attachments' );
        } elseif ( $scanning || $resume ) {
		// do nothing
		$attachments = array();
	// since we aren't resuming, and weren't given a list of IDs, we will optimize everything
        } elseif ( empty( $attachments ) ) {
		delete_transient( 'ewww_image_optimizer_scan_aux' );
                // load up all the image attachments we can find
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') ORDER BY ID DESC" );
        }
	// store the attachment IDs we retrieved in the 'bulk_attachments' option so we can keep track of our progress in the database
	update_option( 'ewww_image_optimizer_scanning_attachments', $attachments, false );
	wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	// number of images in the ewwwio_table (previously optimized images)
	$image_count = ewww_image_optimizer_aux_images_table_count();
	// number of image attachments to be optimized
	$attachment_count = count( $attachments );
	// submit a couple variables to the javascript to work with
	wp_localize_script('ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
			'attachments' => ewww_image_optimizer_aux_images_table_count_pending(),
			'image_count' => $image_count,
			'count_string' => sprintf( esc_html__( '%d images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $image_count ),
			'scan_fail' => esc_html__( 'Operation timed out, you may need to increase the max_execution_time for PHP', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'scan_incomplete' => esc_html__( 'Scan did not complete, will try again', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'operation_stopped' => esc_html__( 'Optimization stopped, reload page to resume.', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'operation_interrupted' => esc_html__( 'Operation Interrupted', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'temporary_failure' => esc_html__( 'Temporary failure, seconds left to retry:', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'invalid_response' => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'remove_failed' => esc_html__( 'Could not remove image from table.', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			/* translators: used for Bulk Optimize progress bar, like so: Optimized 32/346 */
			'optimized' => esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'last_image_header' => esc_html( 'Last Image Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'time_remaining' => esc_html( 'remaining', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ) );
	ewwwio_memory( __FUNCTION__ );
}

function ewww_image_optimizer_optimized_list() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $optimized_list;
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$offset = 0;
	$max_query = (int) apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );
	$optimized_list = array();
	if ( get_transient( 'ewww_image_optimizer_low_memory_mode' ) ) {
		$optimized_list = 'low_memory';
		return;
	}
	$starting_memory_usage = memory_get_usage( true );
	while( $already_optimized = $ewwwdb->get_results( "SELECT id,path,image_size,pending,attachment_id,updated FROM $ewwwdb->ewwwio_images LIMIT $offset,$max_query", ARRAY_A ) ) {
		$ewwwdb->flush();
		//ewwwio_memory( 'queried already opt' );
		//ewwwio_memory( 'flushed already opt' );
		foreach ( $already_optimized as $optimized ) {
			$optimized_path = $optimized['path'];
			$optimized_list[ $optimized_path ]['image_size'] = $optimized['image_size'];
			$optimized_list[ $optimized_path ]['id'] = $optimized['id'];
			$optimized_list[ $optimized_path ]['pending'] = $optimized['pending'];
			$optimized_list[ $optimized_path ]['attachment_id'] = $optimized['attachment_id'];
			$optimized_list[ $optimized_path ]['updated'] = $optimized['updated'];
		}
		//ewwwio_memory( 'swapped records' );
		ewwwio_memory( 'removed original records' );
		$offset += $max_query;
		if ( empty( $estimated_batch_memory ) ) {
			$estimated_batch_memory = memory_get_usage( true ) - $starting_memory_usage;
			if ( ! $estimated_batch_memory ) { // if the memory did not appear to increase, set it to a safe default
				$estimated_batch_memory = 3146000;
			}
			ewwwio_debug_message( "estimated batch memory is $estimated_batch_memory" );
		}
		if ( ! ewwwio_check_memory_available( 3146000 + $estimated_batch_memory ) ) { // initial batch storage used + 3MB
			$optimized_list = 'low_memory';
			set_transient( 'ewww_image_optimizer_low_memory_mode', 1, 600 ); // keep us in low memory mode for at least 10 minutes so we don't keep abusing the db server with superfluous requests
			return;
		}
	}
}

function ewww_image_optimizer_fetch_metadata_batch( $attachments_in ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// retrieve image attachment metadata from the database (in batches)
	$attachments = $wpdb->get_results( "SELECT metas.post_id,metas.meta_key,metas.meta_value,posts.post_mime_type FROM $wpdb->postmeta metas INNER JOIN $wpdb->posts posts ON posts.ID = metas.post_id WHERE (posts.post_mime_type LIKE '%%image%%' OR posts.post_mime_type LIKE '%%pdf%%') AND metas.post_id IN ($attachments_in)", ARRAY_A );
	ewwwio_debug_message( "fetched " . count( $attachments ) . " attachment meta items" );
	//ewwwio_memory( 'fetched selected attachments' );
	$wpdb->flush();
	$attachment_meta = array();
	//ewwwio_memory( 'flushed selected attachments' );
	foreach ( $attachments as $attachment ) {
		if ( '_wp_attached_file' == $attachment['meta_key'] ) {
			$attachment_meta[ $attachment['post_id'] ]['_wp_attached_file'] = $attachment['meta_value'];
			if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachment_meta[ $attachment['post_id'] ]['type'] ) ) {
				$attachment_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
			}
			continue;
		} elseif ( '_wp_attachment_metadata' == $attachment['meta_key'] ) {
			$attachment_meta[ $attachment['post_id'] ]['meta'] = $attachment['meta_value'];
			if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachment_meta[ $attachment['post_id'] ]['type'] ) ) {
				$attachment_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
			}
			continue;
		}
		if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachment_meta[ $attachment['post_id'] ]['type'] ) ) {
			$attachment_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
		}
		//ewwwio_debug_message( print_r( $attachment, true ) );
	}
	//ewwwio_memory( 'swapped attachment meta' );
	unset( $attachments );
	return $attachment_meta;
}

// retrieve image counts for the bulk process
function ewww_image_optimizer_media_scan( $hook = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( 'ewww-image-optimizer-cli' !== $hook && empty( $_REQUEST['ewww_scan'] ) ) {
		die( json_encode( array( 'error' => esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	if ( ! empty( $_REQUEST['ewww_scan'] ) && ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		die( json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	//ewwwio_memory( __FUNCTION__ );

	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $optimized_list;
	//ewwwio_memory( 'post wpdb' );
	$image_count = 0;
	$reset_count = 0;
	$attachment_query = '';
	$images = array();
	$attachment_images = array();
	$reset_images = array();
	$queued_ids = array();
	$field_formats = array(
		'%s', // path
		'%s', // gallery
		'%d', // orig_size
		'%d', // attachment_id
		'%s', // resize
		'%d', // pending
	);
	ewwwio_debug_message( "scanning for media attachments" );
	update_option( 'ewww_image_optimizer_bulk_resume', 'scanning' );
	set_transient( 'ewww_image_optimizer_no_scheduled_optimization', true, 30  * MINUTE_IN_SECONDS );

	// retrieve the time when the scan starts
	$started = microtime( true );

	ewww_image_optimizer_optimized_list();

	$max_query = apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );
	$max_query = (int) $max_query;

	$attachment_ids = get_option( 'ewww_image_optimizer_scanning_attachments' );
	//ewwwio_memory( 'grabbed our ids' );
	if ( empty( $attachment_ids ) ) {
		// run aux script to scan for additional images
		ewww_image_optimizer_aux_images_script();
	}

	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );

	$enabled_types = array();
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ) ) {
		$enabled_types[] = 'image/jpeg';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ) ) {
		$enabled_types[] = 'image/png';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_level' ) ) {
		$enabled_types[] = 'image/gif';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level' ) ) {
		$enabled_types[] = 'application/pdf';
	}

	$starting_memory_usage = memory_get_usage( true );
	while ( microtime( true ) - $started < apply_filters( 'ewww_image_optimizer_timeout', 15 ) && count( $attachment_ids ) ) {
		if ( ! empty( $estimated_batch_memory ) && ! ewwwio_check_memory_available( 3146000 + $estimated_batch_memory ) ) { // initial batch storage used + 3MB
			break;
		}
		if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
			$selected_ids = null;
			ewwwio_debug_message( 'remaining items: ' . count( $attachment_ids ) );
			// retrieve the attachment IDs that were pre-loaded in the database
			$selected_ids = array_splice( $attachment_ids, 0, $max_query );
			ewwwio_debug_message( 'selected items: ' . count( $selected_ids ) );
			$attachments_in = "'" . implode( "','", $selected_ids ) . "'";
		} else {
			ewwwio_debug_message( 'no array found' );
			die( json_encode( array( 'error' => esc_html__( 'List of attachment IDs not found.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
		}

		$failsafe_selected_ids = $selected_ids;

		$attachment_meta = ewww_image_optimizer_fetch_metadata_batch( $attachments_in );
		$attachments_in = null;	

		// if we just completed the first batch, check how much the memory usage increased
		if ( empty( $estimated_batch_memory ) ) {
			$estimated_batch_memory = memory_get_usage( true ) - $starting_memory_usage;
			if ( ! $estimated_batch_memory ) { // if the memory did not appear to increase, set it to a safe default
				$estimated_batch_memory = 3146000;
			}
			ewwwio_debug_message( "estimated batch memory is $estimated_batch_memory" );
		}

		ewwwio_debug_message( "validated " . count( $attachment_meta ) . " attachment meta items" );
		ewwwio_debug_message( 'remaining items after selection: ' . count( $attachment_ids ) );
		foreach ( $selected_ids as $selected_id ) {
			array_shift( $failsafe_selected_ids );
//	ewwwio_memory( 'scanning an attachment for images' );
			clearstatcache();
			$pending = false;
			$remote_file = false;
			if ( empty( $attachment_meta[ $selected_id ]['meta'] ) ) {
				ewwwio_debug_message( "empty meta for $selected_id" );
				$meta = array();
			} else {
				$meta = maybe_unserialize( $attachment_meta[ $selected_id ]['meta'] );
			}
			if ( ! empty( $attachment_meta[ $selected_id ]['type'] ) ) {
				$mime = $attachment_meta[ $selected_id ]['type'];
				ewwwio_debug_message( "got mime via db query: $mime" );
			} elseif ( ! empty( $meta['file'] ) ) {
				$mime = ewww_image_optimizer_quick_mimetype( $meta['file'] );
				ewwwio_debug_message( "got quick mime via filename: $mime" );
			} elseif ( ! empty( $selected_id ) ) {
				$mime = get_post_mime_type( $selected_id );
				ewwwio_debug_message( "checking mime via get_post_mime_type: $mime" );
			}
			if ( empty( $mime ) ) {
				ewwwio_debug_message( "missing mime for $selected_id" );
			}

			if ( 'application/pdf' != $mime // NOT a pdf
				&& ( // AND
					empty( $meta ) // meta is empty
					|| ( is_string( $meta ) && 'processing' == $meta ) // OR the string 'processing'
					|| ( is_array( $meta ) && ! empty( $meta[0] ) && 'processing' == $meta[0] ) // OR array( 'processing )
				)
			) {
				// rebuild meta
				ewwwio_debug_message( "attempting to rebuild attachment meta for $selected_id" );
				$new_meta = ewww_image_optimizer_rebuild_meta( $selected_id );
				if ( is_array( $new_meta ) ) {
					$meta = $new_meta;
				} else {
					$meta = array();
				}
			}

			if ( ! in_array( $mime, $enabled_types ) ) {
				continue;
			}
			//ewwwio_debug_message( print_r( $meta, true ) );
			ewwwio_debug_message( "id: $selected_id and type: $mime" );
			$attached_file = ( ! empty( $attachment_meta[ $selected_id ]['_wp_attached_file'] ) ? $attachment_meta[ $selected_id ]['_wp_attached_file'] : '' );
			list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $selected_id, $attached_file, false );
			// run a quick fix for as3cf files
			if ( class_exists( 'Amazon_S3_And_CloudFront' ) && strpos( $file_path, 's3' ) === 0 ) {
				ewww_image_optimizer_check_table_as3cf( $meta, $selected_id, $file_path );
			}
			if ( ( strpos( $file_path, 's3' ) === 0 || ! is_file( $file_path ) ) && ( class_exists( 'WindowsAzureStorageUtil' ) || class_exists( 'Amazon_S3_And_CloudFront' ) ) ) {
				// construct a $file_path and proceed IF a supported CDN plugin is installed
				ewwwio_debug_message( 'Azure or S3 detected and no local file found' );
				$file_path = get_attached_file( $selected_id );
				if ( strpos( $file_path, 's3' ) === 0 ) {
					$file_path = get_attached_file( $selected_id, true );
				}
				ewwwio_debug_message( "remote file possible: $file_path" );
				if ( ! $file_path ) {
					ewwwio_debug_message( 'no file found on remote storage, bailing' );
					continue;
				}
				$remote_file = true;
			} elseif ( ! $file_path ) {
				ewwwio_debug_message( "no file path for $selected_id" );
				continue;
			}
			$attachment_images['full'] = $file_path;
			$retina_path = ewww_image_optimizer_hidpi_optimize( $file_path, true );
			if ( $retina_path ) {
				$attachment_images['full-retina'] = $retina_path;
			}
			// resized versions, so we can continue
			if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
				// meta sizes don't contain a path, so we calculate one
				$base_ims_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
				$base_dir = trailingslashit( dirname( $file_path ) );
				// process each resized version
				$processed = array();
				foreach ( $meta['sizes'] as $size => $data ) {
					ewwwio_debug_message( "checking for size: $size" );
					if ( strpos( $size, 'webp') === 0 ) {
						continue;
					}
					if ( ! empty( $disabled_sizes[ $size ] ) ) {
						continue;
					}
					if ( ! empty( $disabled_sizes[ 'pdf-full' ] ) && $size == 'full' ) {
						continue;
					}
					if ( empty( $data['file'] ) ) {
						continue;
					}

					// check to see if an IMS record exist from before a resize was moved to the IMS _resized folder
					$ims_path = $base_ims_dir . $data['file'];
					if ( file_exists( $ims_path ) ) {
						// we reset base_dir, because base_dir potentially gets overwritten with base_ims_dir
						$base_dir = trailingslashit( dirname( $file_path ) );
						$ims_temp_path = $base_dir . $data['file']; // formerly $image_path
						ewwwio_debug_message( "ims path: $ims_path" );
						if ( $file_path != $ims_temp_path && is_array( $optimized_list ) && isset( $optimized_list[ $ims_temp_path ] ) ) {
							$optimized_list[ $ims_path ] = $optimized_list[ $ims_temp_path ];
							ewwwio_debug_message( "updating record {$optimized_list[ $ims_temp_path ]['id']} with $ims_path" );
							// store info on the current image for future reference
							$ewwwdb->update( $ewwwdb->ewwwio_images,
								array(
									'path' => $ims_path,
									'updated' => $optimized_list[ $ims_temp_path ]['updated'],
								),
								array(
									'id' => $optimized_list[ $ims_temp_path ]['id'],
								));
						}
						$base_dir = $base_ims_dir;
					}

					// check through all the sizes we've processed so far
					foreach ( $processed as $proc => $scan ) {
						// if a previous resize had identical dimensions
						if ( $scan['height'] == $data['height'] && $scan['width'] == $data['width'] ) {
							// found a duplicate resize
							continue( 2 );
						}
					}
					$resize_path = $base_dir . $data['file'];
					if ( ( $remote_file || is_file( $resize_path ) ) && 'application/pdf' == $mime && $size == 'full' ) {
						$attachment_images[ 'pdf-' . $size ] = $resize_path;
					} elseif ( $remote_file || is_file( $resize_path ) ) {
						$attachment_images[ $size ] = $resize_path;
					}
					// optimize retina images, if they exist
					if ( function_exists( 'wr2x_get_retina' ) ) {
						$retina_path = wr2x_get_retina( $resize_path );
					} else {
						$retina_path = false;
					}
					if ( $retina_path && is_file( $retina_path ) ) {
						ewwwio_debug_message( "found retina via wr2x_get_retina $retina_path" );
						$attachment_images[ $size . '-retina' ] = $retina_path;
					} else {
						$retina_path = ewww_image_optimizer_hidpi_optimize( $resize_path, true );
						if ( $retina_path ) {
							ewwwio_debug_message( "found retina via hidpi_opt $retina_path" );
							$attachment_images[ $size . '-retina' ] = $retina_path;
						}
					}
					// store info on the sizes we've processed, so we can check the list for duplicate sizes
					$processed[ $size ]['width'] = $data['width'];
					$processed[ $size ]['height'] = $data['height'];
				}
			}

			// queue sizes from a custom theme
			if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
				$imagemeta_resize_pathinfo = pathinfo( $file_path );
				$imagemeta_resize_path = '';
				foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
					$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
					if ( is_file( $imagemeta_resize_path ) ) {
						$attachment_images[ 'resized-images-' . $index ] = $imagemeta_resize_path;
					}
				}		
			}

			// and another custom theme
			if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
				$custom_sizes_pathinfo = pathinfo( $file_path );
				$custom_size_path = '';
				foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
					$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
					if ( is_file( $custom_size_path ) ) {
						$attachment_images[ 'custom-size-' . $dimensions ] = $custom_size_path;
					}
				}
			}

			// check if the files are 'prev opt', pending, or brand new, and then queue the file
			foreach ( $attachment_images as $size => $file_path ) {
//	ewwwio_memory( 'checking an image we found' );
				ewwwio_debug_message( "here is a path $file_path" );
				if ( ! $remote_file && strpos( $file_path, 's3' ) !== 0 ) {
					$file_path = realpath( $file_path );
				}
				if ( empty( $file_path ) ) {
					continue;
				}
				if ( apply_filters( 'ewww_image_optimizer_bypass', false, $file_path ) === true ) {
					ewwwio_debug_message( "skipping $file_path as instructed" );
					continue;
				}
				ewwwio_debug_message( "here is a path $file_path" );
				$already_optimized = false;
				if ( ! is_array( $optimized_list ) && $optimized_list === 'low_memory' ) {
					$already_optimized = ewww_image_optimizer_find_already_optimized( $file_path );
				}
				if ( ( $already_optimized || isset( $optimized_list[ $file_path ] ) ) && ( ! $remote_file || ! empty( $_REQUEST['ewww_force'] ) ) ) {
					if ( ! $already_optimized ) {
						$already_optimized = $optimized_list[ $file_path ];
					}
					if ( ! empty( $already_optimized['pending'] ) ) {
						$pending = true;
						ewwwio_debug_message( "pending record for $file_path" );
						continue;
					}
					if ( $remote_file ) {
						$image_size = $already_optimized['image_size'];
					} else {
						$image_size = filesize( $file_path );
					}
					if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
						ewwwio_debug_message( "file skipped due to filesize: $file_path" );
						continue;
					}
					if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
						ewwwio_debug_message( "file skipped due to PNG filesize: $file_path" );
						continue;
					}
					if ( $already_optimized['image_size'] == $image_size && empty( $_REQUEST['ewww_force'] ) ) {
						ewwwio_debug_message( "match found for $file_path" );
						continue;
					} else {
						$pending = true;
						if ( empty( $already_optimized['attachment_id'] ) ) {
							$ewwwdb->update(
								$ewwwdb->ewwwio_images,
								array(
									'pending' => 1,
									'attachment_id' => $selected_id,
									'gallery' => 'media',
									'resize' => $size,
									'updated' => $already_optimized['updated'],
								),
								array( 'id' => $already_optimized['id'] )
							);
						} else {
							$reset_images[] = (int) $already_optimized['id'];
						}
						ewwwio_debug_message( "mismatch found for $file_path, db says " . $already_optimized['image_size'] . " vs. current $image_size" );
					}
				} else {
					if ( ! empty( $images[ $file_path ] ) ) {
						continue;
					}
					$pending = true;
					ewwwio_debug_message( "queuing $file_path" );
					if ( $remote_file ) {
						$image_size = 0;
					} else {
						$image_size = filesize( $file_path );
						if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
							ewwwio_debug_message( "file skipped due to filesize: $file_path" );
							continue;
						}
						if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
							ewwwio_debug_message( "file skipped due to PNG filesize: $file_path" );
							continue;
						}
					}
					if ( seems_utf8( $file_path ) ) {
						ewwwio_debug_message( 'file seems utf8' );
						$utf8_file_path = $file_path;
					} else {
						ewwwio_debug_message( 'file will become utf8' );
						$utf8_file_path = utf8_encode( $file_path );
					}
					//$images[] = "('" . esc_sql( $utf8_file_path ) . "','media',$image_size,$selected_id,'$size',1)";
					$images[ $file_path ] = array(
						'path' => $utf8_file_path,
						'gallery' => 'media',
						'orig_size' => $image_size,
						'attachment_id' => $selected_id,
						'resize' => $size,
						'pending' => 1,
					);
					$image_count++;
				}
				if ( $image_count > 1000 || count( $reset_images ) > 1000 ) {
					ewwwio_debug_message( 'making a dump run' );
					ewww_image_optimizer_debug_log();
	//ewwwio_memory( 'dumping images to db' );
					// let's dump what we have so far to the db
					$image_count = 0;
					if ( ! empty( $images ) ) {
						ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, $field_formats );
//						$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,gallery,orig_size,attachment_id,resize,pending) VALUES " . implode( ',', $images );
//						$wpdb->query( $insert_query );
					}
					$images = array();
					if ( ! empty( $reset_images ) ) {
						$ewwwdb->query( "UPDATE $ewwwdb->ewwwio_images SET pending = 1, updated = updated WHERE id IN (" . implode( ',', $reset_images ) . ')' );
					}
					$reset_images = array();
	//ewwwio_memory( 'dumped images to db' );
				}
			} // end of foreach loop checking all the attachment_images for selected_id to see if they are optimized already or pending already
			if ( $pending ) {
				$queued_ids[] = $selected_id;
			}
			$attachment_images = array();
			if ( $image_count % 100 == 0 && ( microtime( true ) - $started > apply_filters( 'ewww_image_optimizer_timeout', 15 ) || ! ewwwio_check_memory_available( 2097000 ) ) ) {
				$attachment_ids = array_merge( $failsafe_selected_ids, $attachment_ids );
				break;
			}
		} // end foreach loop for the selected_id
		update_option( 'ewww_image_optimizer_scanning_attachments', $attachment_ids, false );
		$attachments_queued = get_option( 'ewww_image_optimizer_bulk_attachments' );
		if ( empty( $attachments_queued ) || ! is_array( $attachments_queued ) ) {
			update_option( 'ewww_image_optimizer_bulk_attachments', $queued_ids, false );
		} else {
			update_option( 'ewww_image_optimizer_bulk_attachments', array_merge( $attachments_queued, $queued_ids ), false );
		}
		$queued_ids = array();
	//ewwwio_memory( 'finished a while loop (selected_ids)' );
	} // endwhile
	ewww_image_optimizer_debug_log();
	if ( ! empty( $images ) ) {
		ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, $field_formats );
		//$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,gallery,orig_size,attachment_id,resize,pending) VALUES " . implode( ',', $images );
		//$wpdb->query( $insert_query );
	//ewwwio_memory( 'inserted ids' );
	}
	if ( ! empty( $reset_images ) ) {
		$ewwwdb->query( "UPDATE $ewwwdb->ewwwio_images SET pending = 1, updated = updated WHERE id IN (" . implode( ',', $reset_images ) . ')' );
	//ewwwio_memory( 'updated ids' );
	}
	update_option( 'ewww_image_optimizer_scanning_attachments', $attachment_ids, false );
	if ( ! empty( $queued_ids ) ) {
		$attachments_queued = get_option( 'ewww_image_optimizer_bulk_attachments' );
		if ( empty( $attachments_queued ) || ! is_array( $attachments_queued ) ) {
			update_option( 'ewww_image_optimizer_bulk_attachments', $queued_ids, false );
		} else {
			update_option( 'ewww_image_optimizer_bulk_attachments', array_merge( $attachments_queued, $queued_ids ), false );
		}
	}
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "counting images took $elapsed seconds" );
	//ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
	if ( 'ewww-image-optimizer-cli' === $hook ) {
		return;
	}
	$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
	$notice = ( get_transient( 'ewww_image_optimizer_low_memory_mode' ) ? esc_html__( "Increasing PHP's memory_limit setting will allow for faster scanning with fewer database queries. Please allow up to 10 minutes for changes to memory limit to be detected.", EWWW_IMAGE_OPTIMIZER_DOMAIN ) : '' );
	if ( count( $attachment_ids ) ) {
		die( json_encode( array(
			'remaining' => sprintf( esc_html__( 'Stage 1, %d images left to scan.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), count( $attachment_ids ) ) . "&nbsp;<img src='$loading_image' />",
			'notice' => $notice,
		) ) );
	} else {
		die( json_encode( array(
			'remaining' => esc_html__( 'Stage 2, please wait.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' />",
			'notice' => $notice,
		 ) ) );
	}
}

function ewww_image_optimizer_bulk_quota_update() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
	//	ewww_image_optimizer_cloud_verify(); 
		echo esc_html__( 'Image credits available:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . ewww_image_optimizer_cloud_quota();
	}
	ewwwio_memory( __FUNCTION__ );
	die();
}

// called by javascript to initialize some output
function ewww_image_optimizer_bulk_initialize() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		die( json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	session_write_close();
	$output = array();
	// update the 'bulk resume' option to show that an operation is in progress
	update_option('ewww_image_optimizer_bulk_resume', 'true');
	$attachments = get_option( 'ewww_image_optimizer_bulk_attachments' );
	if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
		$attachments = unserialize( $attachments );
	}
	if ( ! is_array( $attachments ) ) {
		die( json_encode( array( 'error' => esc_html__( 'Error retrieving list of images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'data' => print_r( $attachments, true ) ) ) );
	}
	$attachment = (int) array_shift( $attachments );
	ewwwio_debug_message( "first image: $attachment" );
	$first_image = new EWWW_Image( $attachment, 'media' );
	$file = $first_image->file;
	// generate the WP spinner image for display
	$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	if ( $file ) {
		$output['results'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <b>$file</b>&nbsp;<img src='$loading_image' /></p>";
	} else {
		$output['results'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>";
	}
	$output['start_time'] = time();
	ewwwio_memory( __FUNCTION__ );
	die( json_encode( $output ) );
}

// called by javascript to process each image in the loop
function ewww_image_optimizer_bulk_loop( $hook, $delay = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	global $image;
	$ewww_defer = false;
	$output = array();
	$time_adjustment = 0;
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( 'ewww-image-optimizer-cli' !== $hook && ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		die( json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	session_write_close();
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	// prevent the scheduled optimizer from firing during a bulk optimization
	set_transient( 'ewww_image_optimizer_no_scheduled_optimization', true, 5  * MINUTE_IN_SECONDS );
	// find out if our nonce is on it's last leg/tick
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
		if ( $tick === 2 ) {
			$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
		} else {
			$output['new_nonce'] = '';
		}
	}
	$batch_image_limit = ( empty( $_REQUEST['ewww_batch_limit'] ) ? 999 : 1 );
	// get the 'bulk attachments' with a list of IDs remaining
	$attachments = get_option( 'ewww_image_optimizer_bulk_attachments' );
	if ( ! empty( $attachments ) && is_array( $attachments ) ) {
		$attachment = (int) $attachments[0];
	} else {
		$attachment = 0;
	}
	$image = new EWWW_Image( $attachment, 'media' );
	if ( ! $image->file ) {
		die( json_encode( array( 'done' => 1, 'completed' => 0 ) ) );
	}
	$output['results'] = '';
	$output['completed'] = 0;
	while ( $output['completed'] < $batch_image_limit && $image->file && microtime( true ) - $started + $time_adjustment < apply_filters( 'ewww_image_optimizer_timeout', 15 ) ) {
		$output['completed']++;
		$meta = false;
		// see if the image needs fetching from a CDN
		if ( ! is_file( $image->file ) ) {
			$meta = wp_get_attachment_metadata( $image->attachment_id );
			$file_path = ewww_image_optimizer_remote_fetch( $image->attachment_id, $meta );
			unset( $meta );
		/*	if ( $image->resize === 'full' && $file_path && $image->file != $file_path ) {
				$image->file = $file_path;
			} else*/if ( ! $file_path ) {
				ewwwio_debug_message( 'could not retrieve path' );
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::line( __( 'Could not find image', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . $image->file );
				} else {
					$output['results'] .= sprintf( '<p>' . esc_html__( 'Could not find image', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong></p>", esc_html( $image->file ) );
				}
			}
		}
		// if a resize is missing, see if it should (and can) be regenerated
		if ( $image->resize && $image->resize != 'full' && ! is_file( $image->file ) ) {
			// TODO: make sure this is optional, because of CDN offloading: resized image does not exist, regenerate it
		}
		if ( $image->resize === 'full' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
			if ( ! $meta || ! is_array( $meta ) ) {
				$meta = wp_get_attachment_metadata( $image->attachment_id );
			}
			$new_dimensions = ewww_image_optimizer_resize_upload( $image->file );
			if ( is_array( $new_dimensions ) ) {
				$meta['width'] = $new_dimensions[0];
				$meta['height'] = $new_dimensions[1];
			}
		}
		list( $file, $msg, $converted, $original ) = ewww_image_optimizer( $image->file, 1, false, false, $image->resize == 'full' );
		// gotta make sure we don't delete a pending record if the license is exceeded, so the license check goes first
		$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
		if ( ! empty( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
			$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			die( json_encode( $output ) );
		}
		// delete a pending record if the optimization failed for whatever reason
		if ( ! $file && $image->id ) {
			global $wpdb;
			$wpdb->delete( $wpdb->ewwwio_images, array( 'id' => $image->id ), array( '%d' ) );
		}
		// if this is a full size image and it was converted
		if ( $image->resize == 'full' && ( $image->increment !== false || $converted !== false ) ) {
			if ( ! $meta || ! is_array( $meta ) ) {
				$meta = wp_get_attachment_metadata( $image->attachment_id );
			}
			if ( $converted ) {
				$image->increment = $converted;
			}
			$image->file = $file;
			$image->converted = $original;
			$meta['file'] = trailingslashit( dirname( $meta['file'] ) ) . basename( $file );
			$image->update_converted_attachment( $meta );
			$meta = $image->convert_sizes( $meta );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( __( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' ' . $image->file );
			WP_CLI::line( str_replace( '&nbsp;', '', $msg ) );
		}
		$output['results'] .= sprintf( "<p>" . esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong><br>", esc_html( $image->file ) );
		$output['results'] .= "$msg</p>";

		// do metadata update after full-size is processed, usually because of conversion or resizing
		if ( $image->resize == 'full' && $image->attachment_id ) {
			if ( $meta && is_array( $meta ) ) {
				$meta_saved = wp_update_attachment_metadata( $image->attachment_id, $meta );
				if ( ! $meta_saved ) {
					ewwwio_debug_message( 'failed to save meta' );
				}
			}
		}

		// pull the next image
		$next_image = new EWWW_Image( $attachment, 'media' );

		// when we finish all the sizes, we just want to fire off any filters for plugins that might need to take action when an image is updated
		if ( $attachment && $attachment != $next_image->attachment_id ) {
			$meta = apply_filters( 'wp_update_attachment_metadata', wp_get_attachment_metadata( $image->attachment_id ), $image->attachment_id );
		}
		// when an image (attachment) is done, pull the next attachment ID off the stack
		if ( ( $next_image->resize == 'full' || empty( $next_image->resize ) ) && ! empty( $attachment ) && $attachment != $next_image->attachment_id ) {
			$attachment = (int) array_shift( $attachments ); // pull the last image off the stack first
			if ( ! empty( $attachments ) && is_array( $attachments ) ) {
				$attachment = (int) $attachments[0]; // and then grab the next one (if any are left)
			} else {
				$attachment = 0;
			}
			$next_image = new EWWW_Image( $attachment, 'media' );
		}
		$image = $next_image;
		$time_adjustment = $image->time_estimate();
	} // endwhile

	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::line( sprintf( __( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN), $elapsed ) );
		sleep( $delay );
	}
	$output['results'] .= sprintf( '<p>' . esc_html__( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</p>', $elapsed );
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option( 'ewww_image_optimizer_bulk_attachments', $attachments, false );
	if ( ewww_image_optimizer_get_option ( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_debug;
		$output['results'] .= '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
	}
	if ( ! empty( $next_image->file ) ) {
		//$next_attachment = array_shift( $attachments );
		$next_file = esc_html( $next_image->file );
		// generate the WP spinner image for display
		$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
		if ( $next_file ) {
			$output['next_file'] = "<p>" . esc_html__('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <b>$next_file</b>&nbsp;<img src='$loading_image' /></p>";
		} else {
			$output['next_file'] = "<p>" . esc_html__('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' /></p>";
		}
	} else {
		$output['done'] = 1;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
	}
	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}
	$output['current_time'] = time();
	die( json_encode( $output ) );
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_bulk_cleanup() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		die( '<p><b>' . esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</b></p>' );
	}
	// all done, so we can update the bulk options with empty values
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
	delete_transient( 'ewww_image_optimizer_skip_aux' );
	// and let the user know we are done
	ewwwio_memory( __FUNCTION__ );
	die( '<p><b>' . esc_html__( 'Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</b> - <a href="upload.php">' . esc_html__( 'Return to Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a></p>' );
}

add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script' );
add_action( 'wp_ajax_bulk_scan', 'ewww_image_optimizer_media_scan' );
add_action( 'wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize' );
add_action( 'wp_ajax_bulk_filename', 'ewww_image_optimizer_bulk_filename' );
add_action( 'wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop' );
add_action( 'wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup' );
add_action( 'wp_ajax_bulk_quota_update', 'ewww_image_optimizer_bulk_quota_update' );
add_filter( 'ewww_image_optimizer_count_optimized_queries', 'ewww_image_optimizer_reduce_query_count' );
?>
