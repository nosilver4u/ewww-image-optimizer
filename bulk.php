<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// presents the bulk optimize form with the number of images, and runs it once they submit the button
function ewww_image_optimizer_bulk_preview() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// retrieve the attachment IDs that were pre-loaded in the database
	list( $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ) = ewww_image_optimizer_count_optimized( 'media' );
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
		$button_text = esc_attr__( 'Scan and optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$button_text = esc_attr__( 'Resume previous optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	}
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// create the html for the bulk optimize form and status divs
?>
		<div id="ewww-bulk-loading">
			<p id="ewww-loading" class="ewww-bulk-info" style="display:none"><?php esc_html_e( 'Importing', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>&nbsp;<img src='<?php echo $loading_image; ?>' /></p>
		</div>
		<div id="ewww-bulk-progressbar"></div>
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
					<h2 class="hndle"><span><?php esc_html_e( 'Last Image Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></span></h2>
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
			<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></label>&emsp;<input type="checkbox" id="ewww-force" name="ewww-force"></p>
			<p><label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Choose how long to pause between images (in seconds, 0 = disabled)', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php if ( $delay = ewww_image_optimizer_get_option ( 'ewww_image_optimizer_delay' ) ) { echo $delay; } else { echo 0; } ?>"></p>
			<div id="ewww-delay-slider" style="width:50%"></div>
		</form>
		<h2 class="ewww-bulk-media"><?php esc_html_e( 'Optimize Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></h2>
<?php		if ( $fullsize_count < 1 ) {
			echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>';
		} else { ?>
			<div id="ewww-bulk-forms">
<?php			if ( ! $resize_count && ! $unoptimized_count && ! $unoptimized_resize_count ) { 
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$credits_needed = $fullsize_count * ( count( get_intermediate_image_sizes() ) + 1 );
				} ?>
				<p class="ewww-media-info ewww-bulk-info"><?php printf( esc_html__( '%1$d images in the Media Library have been selected, unable to determine how many resizes and how many are unoptimized.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count ); ?> <?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && $credits_needed > 0 ) { printf( esc_html__( 'This could require approximately %d image credits to complete.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $credits_needed ); } ?><br />
<?php			} else { 
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$credits_needed = $unoptimized_count + $unoptimized_resize_count;
				} ?>
				<p class="ewww-media-info ewww-bulk-info"><?php printf( esc_html__( '%1$d images in the Media Library have been selected (%2$d unoptimized), with %3$d resizes (%4$d unoptimized).', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $fullsize_count, $unoptimized_count, $resize_count, $unoptimized_resize_count ); ?>  <?php if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && $credits_needed > 0 ) { printf( esc_html__( 'This could require approximately %d image credits to complete.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $credits_needed ); } ?><br />
<?php			} ?>
			<?php esc_html_e( 'Previously optimized images will be skipped by default.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<form id="ewww-bulk-start" class="ewww-bulk-form" method="post" action="">
				<input id="ewww-bulk-first" type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
				<input id="ewww-bulk-again" type="submit" class="button-secondary action" style="display:none" value="<?php esc_attr_e( 'Optimize Again', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?>" />
			</form>
<?php		}
		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if ( ! empty( $resume ) ): 
?>
			<p class="ewww-media-info ewww-bulk-info"><?php esc_html_e( 'If you would like to start over again, press the Reset Status button to reset the bulk operation status.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<form class="ewww-bulk-form" method="post" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-bulk-reset', 'ewww_wpnonce' ); ?>
				<input type="hidden" name="ewww_reset" value="1">
				<button id="ewww-bulk-reset" type="submit" class="button-secondary action"><?php esc_html_e( 'Reset Status', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
			</form>
<?php		endif;
	echo '</div>';
	ewww_image_optimizer_media_scan();
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_aux_images();
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
	if ( ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	$max_query = apply_filters( 'ewww_image_optimizer_count_optimized_queries', 3000 );
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
					$attachment_ids = array_merge( get_option( 'ewww_image_optimizer_scanning_attachments' ), get_option( 'ewww_image_optimizer_bulk_attachments' ) );
				} else {
					$attachment_ids = get_option( 'ewww_image_optimizer_bulk_attachments' );
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
			$offset = 0;
			// retrieve all the image attachment metadata from the database
			while ( $attachments = $wpdb->get_results( "SELECT metas.meta_value,post_id FROM $wpdb->postmeta metas INNER JOIN $wpdb->posts posts ON posts.ID = metas.post_id WHERE (posts.post_mime_type LIKE '%%image%%' OR posts.post_mime_type LIKE '%%pdf%%') AND metas.meta_key = '_wp_attachment_metadata' $attachment_query LIMIT $offset,$max_query", ARRAY_N ) ) {
				ewwwio_debug_message( "fetched " . count( $attachments ) . " attachments starting at $offset" );
				$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
				foreach ( $attachments as $attachment ) {
					$meta = maybe_unserialize( $attachment[0] );
					if ( empty( $meta ) ) {
						ewwwio_debug_message( 'empty meta' );
						continue;
					}
					$mime = '';
					if ( ! empty( $meta['file'] ) ) {
						$mime = ewww_image_optimizer_quick_mimetype( $meta['file'] );
					} elseif ( ! empty( $attachment[1] ) ) {
						$mime = get_post_mime_type( $attachment[1] );
						ewwwio_debug_message( 'checking mime via get_post...' );
					}
					if ( $mime == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 0 ) {
						continue;
					}
					if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 0 ) {
						continue;
					}
					if ( $mime == 'image/gif' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) == 0 ) {
						continue;
					}
					if ( $mime == 'application/pdf' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 0 ) {
						continue;
					}
					if ( empty( $meta['ewww_image_optimizer'] ) ) {
						$unoptimized_full++;
						$ids[] = $attachment[1];
					}
					if ( ! empty( $meta['ewww_image_optimizer'] ) && preg_match( '/' . __('License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '/', $meta['ewww_image_optimizer'] ) ) {
						$unoptimized_full++;
						$ids[] = $attachment[1];
					}
					// resized versions, so we can continue
					if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
						foreach( $meta['sizes'] as $size => $data ) {
							if ( ! empty( $disabled_sizes[ $size ] ) ) {
								continue;
							}
							if ( strpos( $size, 'webp') === 0 ) {
								continue;
							}
							$resize_count++;
							if ( empty( $meta['sizes'][ $size ]['ewww_image_optimizer'] ) ) {
								$unoptimized_re++;
							}
						}
					}
				}
				$offset += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query = '';
					$attachment_query_count = 0;
					$offset = 0;
					while ( $attachment_ids && $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'AND metas.post_id IN (' . substr( $attachment_query, 0, -1 ) . ')';
				}
			}
			break;
		case 'ngg':
			// see if we were given attachment IDs to work with via GET/POST
		        if ( ! empty($_REQUEST['ewww_inline']) || get_option('ewww_image_optimizer_bulk_ngg_resume')) {
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
	$attachments = get_option( 'ewww_image_optimizer_scanning_attachments' );
	// see if we were given attachment IDs to work with via GET/POST
	$ids = array();
        if ( ! empty( $_REQUEST['ids'] ) && ( preg_match( '/^[\d,]+$/', $_REQUEST['ids'], $request_ids ) || is_numeric( $_REQUEST['ids'] ) ) ) {
		ewww_image_optimizer_delete_pending();
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
	                // retrieve post IDs correlating to the IDs submitted to make sure they are all valid
			$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') AND ID IN ({$request_ids[0]}) ORDER BY ID DESC" );
		}
		// unset the 'bulk resume' option since we were given specific IDs to optimize
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
        // check if there is a previous bulk operation to resume
//        } elseif ( $scanning == 'scanning' ) {
		// retrieve the attachment IDs that have not been finished from the 'scanning attachments' option
//		$attachments = get_option( 'ewww_image_optimizer_scanning_attachments' );
        } elseif ( $scanning ) {
		// do nothing
		$attachments = array();
	// since we aren't resuming, and weren't given a list of IDs, we will optimize everything
        } elseif ( empty( $attachments ) ) {
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
			'attachments' => $attachment_count,
			'image_count' => $image_count,
			'count_string' => sprintf( esc_html__( '%d images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $image_count ),
			'scan_fail' => esc_html__( 'Operation timed out, you may need to increase the max_execution_time for PHP', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'scan_incomplete' => esc_html__( 'Scan did not complete, will try again', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'operation_stopped' => esc_html__( 'Optimization stopped, reload page to resume.', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'operation_interrupted' => esc_html__( 'Operation Interrupted', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'temporary_failure' => esc_html__( 'Temporary failure, seconds left to retry:', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			'remove_failed' => esc_html__( 'Could not remove image from table.', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			/* translators: used for Bulk Optimize progress bar, like so: Optimized 32/346 */
			'optimized' => esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
		)
	);
	// load the stylesheet for the jquery progressbar
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ) );
	ewwwio_memory( __FUNCTION__ );
}

// retrieve image counts for the bulk process
function ewww_image_optimizer_media_scan() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$image_count = 0;
	$reset_count = 0;
	$attachment_query = '';
	$images = array();
	$attachment_images = array();
	$reset_images = array();
	ewwwio_debug_message( "scanning for media attachments" );
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	ewwwio_debug_message( 'building optimized list' );
	$query = "SELECT id,path,image_size,pending,attachment_id FROM $wpdb->ewwwio_images";
	$already_optimized = $wpdb->get_results( $query, ARRAY_A );
	$optimized_list = array();
	foreach ( $already_optimized as $optimized ) {
		$optimized_path = $optimized['path'];
		$optimized_list[ $optimized_path ]['image_size'] = $optimized['image_size'];
		$optimized_list[ $optimized_path ]['id'] = $optimized['id'];
		$optimized_list[ $optimized_path ]['pending'] = $optimized['pending'];
		$optimized_list[ $optimized_path ]['attachment_id'] = $optimized['attachment_id'];
	}

	$max_query = apply_filters( 'ewww_image_optimizer_count_optimized_queries', 3000 );
	$max_query = (int) $max_query;
	$attachment_query_count = 0;
	// We WILL have IDs to work with, we then need to grab 'max_query' (3000) of the IDs to query metadata
	// the while() loop might need to change to something like while( there are attachment IDs left to scan )
	//$resume = get_option( 'ewww_image_optimizer_bulk_resume' );
	$attachment_ids = get_option( 'ewww_image_optimizer_scanning_attachments' );
	if ( empty( $attachment_ids ) ) {
		// run aux script to scan for additional images
		ewww_image_optimizer_aux_images_script();
	}
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
	while ( microtime( true ) - $started < 20 && count( $attachment_ids ) ) {
		if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
			ewwwio_debug_message( 'remaining items: ' . count( $attachment_ids ) );
			// retrieve the attachment IDs that were pre-loaded in the database
			$selected_ids = array_splice( $attachment_ids, 0, $max_query );
			ewwwio_debug_message( 'selected items: ' . count( $selected_ids ) );
			$attachments_in = "'" . implode( "','", $selected_ids ) . "'";
					/*while ( $selected_ids ) { //&& $attachment_query_count < $max_query ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						$attachment_query_count++;
					}
					$attachment_query = 'AND metas.post_id IN (' . substr( $attachment_query, 0, -1 ) . ')';*/
		} else {
//				$full_count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%')" );
			ewwwio_debug_message( 'no array found' );
			// TODO: make sure to throw appropriate errors in json (if we can't overcome or ignore them)
			die( json_encode( array( 'error' => 'no array of attachment IDs found' ) ) );
		}
//			if ( ! empty( $_REQUEST['ewww_offset'] ) ) {
//				$offset = (int) $_REQUEST['ewww_offset'];
//			} else {
//				$offset = 0;
//			}

		// retrieve image attachment metadata from the database (in batches)
		$attachments = $wpdb->get_results( "SELECT metas.post_id,metas.meta_key,metas.meta_value,posts.post_mime_type FROM $wpdb->postmeta metas INNER JOIN $wpdb->posts posts ON posts.ID = metas.post_id WHERE (posts.post_mime_type LIKE '%%image%%' OR posts.post_mime_type LIKE '%%pdf%%') AND metas.post_id IN ($attachments_in)", ARRAY_A );
		ewwwio_debug_message( "fetched " . count( $attachments ) . " attachment meta items" );
		foreach ( $attachments as $attachment ) {
			if ( '_wp_attached_file' == $attachment['meta_key'] ) {
				$attachment_meta[ $attachment['post_id'] ]['_wp_attached_file'] = $attachment['meta_value'];
			} elseif ( '_wp_attachment_metadata' != $attachment['meta_key'] ) {
				if ( ! empty( $attachment['post_mime_type'] ) ) {
					$attachment_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
				}
				//ewwwio_debug_message( print_r( $attachment, true ) );
				continue;
			}
			$attachment_meta[ $attachment['post_id'] ]['meta'] = $attachment['meta_value'];
			$attachment_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
		}

		ewwwio_debug_message( "validated " . count( $attachment_meta ) . " attachment meta items" );
		ewwwio_debug_message( 'remaining items after selection: ' . count( $attachment_ids ) );
		foreach ( $selected_ids as $selected_id ) {
			if ( empty( $attachment_meta[ $selected_id ]['meta'] ) ) {
				ewwwio_debug_message( "empty meta for $selected_id" );
				$meta = array();
				//continue;
			} else {
				$meta = maybe_unserialize( $attachment_meta[ $selected_id ]['meta'] );
			}
			if ( ! empty( $attachment_meta[ $selected_id ]['type'] ) ) {
				$mime = $attachment_meta[ $selected_id ]['type'];
			} elseif ( ! empty( $meta['file'] ) ) {
				$mime = ewww_image_optimizer_quick_mimetype( $meta['file'] );
				ewwwio_debug_message( 'got quick mime via filename' );
			} elseif ( ! empty( $selected_id ) ) {
				$mime = get_post_mime_type( $selected_id );
				ewwwio_debug_message( 'checking mime via get_post...' );
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

			if ( $mime == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 0 ) {
				continue;
			}
			if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 0 ) {
				continue;
			}
			if ( $mime == 'image/gif' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) == 0 ) {
				continue;
			}
			if ( $mime == 'application/pdf' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 0 ) {
				continue;
			}
			//ewwwio_debug_message( print_r( $meta, true ) );
			//ewwwio_debug_message( "type: $mime" );
			$attached_file = ( ! empty( $attachment_meta[ $selected_id ]['_wp_attached_file'] ) ? $attachment_meta[ $selected_id ]['_wp_attached_file'] : '' );
			list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $selected_id, $attached_file, false );
			// run a quick fix for as3cf files
			if ( class_exists( 'Amazon_S3_And_CloudFront' ) && strpos( $file_path, 's3' ) === 0 ) {
				ewww_image_optimizer_check_table_as3cf( $meta, $selected_id, $file_path );
			}
			if ( ! is_file( $file_path ) && ( class_exists( 'WindowsAzureStorageUtil' ) || class_exists( 'Amazon_S3_And_CloudFront' ) ) ) {
				// construct a $file_path and proceed IF a supported CDN plugin is installed
				$file_path = get_attached_file( $selected_id );
				if ( ! $file_path ) {
					continue;
				}
			} elseif ( ! $file_path ) {
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
					if ( empty( $data['file'] ) ) {
						continue;
					}

					$ims_path = $base_ims_dir . $data['file'];
					if ( file_exists( $ims_path ) ) {
						// we reset base_dir, because base_dir potentially gets overwritten with base_ims_dir
						$base_dir = trailingslashit( dirname( $file_path ) );
						$ims_temp_path = $base_dir . $data['file']; // formerly $image_path
						ewwwio_debug_message( "ims path: $ims_path" );
						if ( isset( $optimized_list[ $ims_temp_path ] ) ) {
						/*	ewwwio_debug_message( "possibly need to replace $ims_temp_path" );
							if ( isset( $optimized_list[ $ims_path ] ) ) {
								ewwwio_debug_message( "we got a dup: {$optimized_list[ $ims_path ]['id']}" );
							}*/
							$optimized_list[ $ims_path ] = $optimized_list[ $ims_temp_path ];
							ewwwio_debug_message( "updating record {$optimized_list[ $ims_temp_path ]['id']} with $ims_path" );
							// store info on the current image for future reference
							$wpdb->update( $wpdb->ewwwio_images,
								array(
									'path' => $ims_path,
								),
								array(
									'id' => $optimized_list[ $ims_temp_path ]['id'],
								));
							unset( $optimized_list[ $ims_temp_path ] );
						}
						$base_dir = $base_ims_dir;
					}

					// check through all the sizes we've processed so far
					foreach ( $processed as $proc => $scan ) {
						// if a previous resize had identical dimensions
						if ( $scan['height'] == $data['height'] && $scan['width'] == $data['width'] ) {
							// found a duplicate resize
							continue( 2 );
							//$dup_size = true;
							// point this resize at the same image as the previous one
							//$meta['sizes'][ $size ]['file'] = $meta['sizes'][ $proc ]['file'];
							// and tell the user we didn't do any further optimization
							//$meta['sizes'][ $size ]['ewww_image_optimizer'] = __( 'No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN );
						}
					}
					$resize_path = $base_dir . $data['file'];
					if ( is_file( $resize_path ) ) {
						$attachment_images[ $size ] = $resize_path;
					}
					// optimize retina images, if they exist
					if ( function_exists( 'wr2x_get_retina' ) && $retina_path = wr2x_get_retina( $resize_path ) && is_file( $retina_path ) ) {
						$attachment_images[ $size . '-retina' ] = $retina_path;
					} elseif ( $retina_path = ewww_image_optimizer_hidpi_optimize( $resize_path, true ) ) {
						$attachment_images[ $size . '-retina' ] = $retina_path;
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
				if ( isset( $optimized_list[ $file_path ] ) ) {
					if ( ! empty( $optimized_list[ $file_path ]['pending'] ) ) {
						ewwwio_debug_message( "pending record for $file_path" );
						continue;
					}
					$image_size = filesize( $file_path );
					if ( $optimized_list[ $file_path ]['image_size'] == $image_size && empty( $_REQUEST['ewww_force'] ) ) {
						ewwwio_debug_message( "match found for $file_path" );
						continue;
					} else {
				// TODO: need to queue with filename, PLUS media/gallery type and size name: 'full', medium, etc.
				// hmm, this could get expensive time-wise, see if there is another place we can trigger a lookup to populate records
				// perhaps the custom column, as that is where people will expect to see results, and where we plan to query the db anyway
						$reset_images[] = (int) $optimized_list[ $file_path ]['id'];
						ewwwio_debug_message( "mismatch found for $file_path, db says " . $optimized_list[ $file_path ]['image_size'] . " vs. current $image_size" );
					}
				} else {
					ewwwio_debug_message( "queuing $file_path" );
					$image_size = filesize( $file_path );
					$images[] = "('" . esc_sql( utf8_encode( $file_path ) ) . "','media',$image_size,'$size',1)";
					$image_count++;
				}
				if ( $image_count > 3000 ) {
					ewwwio_debug_message( 'making a dump run' );
					// let's dump what we have so far to the db
					$image_count = 0;
					$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,gallery,orig_size,resize,pending) VALUES " . implode( ',', $images );
					$wpdb->query( $insert_query );
					$images = array();
				}
			}
			$attachment_images = array();
		}
		update_option( 'ewww_image_optimizer_scanning_attachments', $attachment_ids );
		update_option( 'ewww_image_optimizer_bulk_attachments', array_merge( get_option( 'ewww_image_optimizer_bulk_attachments' ), $selected_ids ), false );
	} // endwhile
	if ( ! empty( $images ) ) {
		$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,gallery,orig_size,resize,pending) VALUES " . implode( ',', $images );
		$wpdb->query( $insert_query );
	}
	if ( ! empty( $reset_images ) ) {
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' );
	}
	update_option( 'ewww_image_optimizer_scanning_attachments', $attachment_ids );
	update_option( 'ewww_image_optimizer_bulk_attachments', array_merge( get_option( 'ewww_image_optimizer_bulk_attachments' ), $selected_ids ), false );
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "counting images took $elapsed seconds" );
	ewwwio_memory( __FUNCTION__ );
	return;
	die( json_encode( array( 'remaining' => count( $attachment_ids ) ) ) );
}

function ewww_image_optimizer_bulk_quota_update() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
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
		wp_die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
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
		$output['error'] = esc_html__( 'Error retrieving list of images', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		echo json_encode( $output );
		die();
	}
	$attachment = array_shift( $attachments );
	$file = ewww_image_optimizer_bulk_filename( $attachment );
	// generate the WP spinner image for display
	$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
	// let the user know that we are beginning
	if ( $file ) {
		$output['results'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <b>$file</b>&nbsp;<img src='$loading_image' /></p>";
	} else {
		$output['results'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>";
	}
	echo json_encode( $output );
	ewwwio_memory( __FUNCTION__ );
	die();
}

// called by javascript to output filename of attachment in progress
function ewww_image_optimizer_bulk_filename( $attachment_ID = null ) {
	$meta = wp_get_attachment_metadata( $attachment_ID );
	ewwwio_memory( __FUNCTION__ );
	if ( ! empty( $meta['file'] ) ) {
		return $meta['file'];
	} else {
		return false;
	}
}
 
// called by javascript to process each image in the loop
function ewww_image_optimizer_bulk_loop() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	$ewww_defer = false;
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		echo json_encode( $output );
		die();
	}
	session_write_close();
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) ) {
		set_time_limit( 0 );
	}
	// find out if our nonce is on it's last leg/tick
	$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
	if ( $tick === 2 ) {
		$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
	} else {
		$output['new_nonce'] = '';
	}
	// get the 'bulk attachments' with a list of IDs remaining
	$attachments = get_option( 'ewww_image_optimizer_bulk_attachments' );
	$attachment = (int) array_shift( $attachments );
	$meta = wp_get_attachment_metadata( $attachment, true );
	// do the optimization for the current attachment (including resizes)
	$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $attachment, false );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		die( json_encode( $output ) );
	}
	if ( ! empty ( $meta['file'] ) ) {
		// output the filename (and path relative to 'uploads' folder)
		$output['results'] = sprintf( "<p>" . esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong><br>", esc_html( $meta['file']) );
	} else {
		$output['results'] = sprintf( "<p>" . esc_html__( 'Skipped image, ID:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong><br>", esc_html( $attachment ) );
	}
	if ( ! empty( $meta['ewww_image_optimizer'] ) ) {
		// tell the user what the results were for the original image
		$output['results'] .= sprintf( esc_html__( 'Full size – %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "<br>", esc_html( $meta['ewww_image_optimizer'] ) );
	}
	// check to see if there are resized version of the image
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// cycle through each resize
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['ewww_image_optimizer'] ) ) {
				// output the results for the current resized version
				$output['results'] .= sprintf( "%s – %s<br>", esc_html( $size['file'] ), esc_html( $size['ewww_image_optimizer'] ) );
			}
		}
	}
	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>", $elapsed );
	// update the metadata for the current attachment
	$meta_saved = wp_update_attachment_metadata( $attachment, $meta );
	if ( ! $meta_saved ) {
		ewwwio_debug_message( 'failed to save meta' );
	}
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
	update_option( 'ewww_image_optimizer_bulk_attachments', $attachments, false );
	if ( ewww_image_optimizer_get_option ( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_debug;
		$output['results'] .= '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
	}
	if ( ! empty( $attachments ) ) {
		$next_attachment = array_shift( $attachments );
		$next_file = ewww_image_optimizer_bulk_filename( $next_attachment );
		// generate the WP spinner image for display
		$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
		if ( $next_file ) {
			$output['next_file'] = "<p>" . esc_html__('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <b>$next_file</b>&nbsp;<img src='$loading_image' /></p>";
		} else {
			$output['next_file'] = "<p>" . esc_html__('Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&nbsp;<img src='$loading_image' /></p>";
		}
	}
	echo json_encode( $output );
	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	die();
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_bulk_cleanup() {
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	// all done, so we can update the bulk options with empty values
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_attachments', '', false );
	// and let the user know we are done
	echo '<p><b>' . esc_html__( 'Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</b> - <a href="upload.php">' . esc_html__( 'Return to Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a></p>';
	ewwwio_memory( __FUNCTION__ );
	die();
}
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script' );
add_action( 'wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize' );
add_action( 'wp_ajax_bulk_filename', 'ewww_image_optimizer_bulk_filename' );
add_action( 'wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop' );
add_action( 'wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup' );
add_action( 'wp_ajax_bulk_quota_update', 'ewww_image_optimizer_bulk_quota_update' );
?>
