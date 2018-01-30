<?php
/**
 * Functions for dealing with auxiliary images
 *
 * This file contains functions for bulk optimizing images outside the Media
 * Library, and AJAX hooks for handling the image status table on the bulk
 * optimize page.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays the lower portion of the Bulk Optimize page.
 *
 * Includes the table migration notice, and the framework for displaying the image status table.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$output = '';
	// Find out if the auxiliary image table has anything in it.
	$already_optimized = ewww_image_optimizer_aux_images_table_count();
	// See if the auxiliary image table needs converting from md5sums to image sizes.
	$column = $wpdb->get_row( "SHOW COLUMNS FROM $wpdb->ewwwio_images LIKE 'image_md5'", ARRAY_N );
	if ( ! empty( $column ) ) {
		ewwwio_debug_message( 'image_md5 column exists, checking for image_md5 values' );
		$db_convert = $wpdb->get_results( "SELECT image_md5 FROM $wpdb->ewwwio_images WHERE image_md5 <> ''", ARRAY_N );
	}
	$output .= '<div id="ewww-aux-forms">';
	if ( ! empty( $db_convert ) ) {
		$output .= '<p class="ewww-bulk-info">' . esc_html__( 'The database schema has changed, you need to convert to the new format.', 'ewww-image-optimizer' ) . '</p>';
		$output .= '<form method="post" id="ewww-aux-convert" class="ewww-bulk-form" action="">';
		$output .= wp_nonce_field( 'ewww-image-optimizer-aux-images-convert', 'ewww_wpnonce', true, false );
		$output .= '<input type="hidden" name="ewww_convert" value="1">';
		$output .= '<button id="ewww-table-convert" type="submit" class="button-secondary action">' . esc_html__( 'Convert Table', 'ewww-image-optimizer' ) . '</button>';
		$output .= '</form>';
	}
	if ( empty( $already_optimized ) ) {
		$display = ' style="display:none"';
	} else {
		$display = '';
	}
	/* translators: %d: number of images */
	$output .= "<p id='ewww-table-info' class='ewww-bulk-info' $display>" . sprintf( esc_html__( 'The plugin keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', 'ewww-image-optimizer' ), $already_optimized ) . '</p>';
	$output .= "<form id='ewww-show-table' class='ewww-bulk-form' method='post' action='' $display>";
	$output .= '<button type="submit" class="button-secondary action">' . esc_html__( 'Show Optimized Images', 'ewww-image-optimizer' ) . '</button>';
	$output .= '</form>';
	$output .= '<div class="tablenav ewww-aux-table" style="display:none">' .
		'<div class="tablenav-pages ewww-aux-table">' .
		'<span class="displaying-num ewww-aux-table"></span>' . "\n" .
		'<span id="paginator" class="pagination-links ewww-aux-table">' . "\n" .
		'<a id="first-images" class="first-page" style="display:none">&laquo;</a>' . "\n" .
		'<a id="prev-images" class="prev-page" style="display:none">&lsaquo;</a>' . "\n";
	$output .= esc_html__( 'page', 'ewww-image-optimizer' ) . ' <span class="current-page"></span> ' . esc_html__( 'of', 'ewww-image-optimizer' ) . "\n";
	$output .= '<span class="total-pages"></span>' . "\n" .
		'<a id="next-images" class="next-page" style="display:none">&rsaquo;</a>' . "\n" .
		'<a id="last-images" class="last-page" style="display:none">&raquo;</a>' .
		'</span>' .
		'</div>' .
		'</div>' .
		'<div id="ewww-bulk-table" class="ewww-aux-table"></div>' .
		'<span id="ewww-pointer" style="display:none">0</span>' .
		'</div>' .
		'</div>';

	$help_instructions = esc_html__( 'Enable the Debugging option and refresh this page to include debugging information with your question.', 'ewww-image-optimizer' ) . ' ' .
		esc_html__( 'This will allow us to assist you more quickly.', 'ewww-image-optimizer' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		ewww_image_optimizer_options( 'debug-silent' );
		?>
<script type="text/javascript">
	function selectText(containerid) {
		var debug_node = document.getElementById(containerid);
		if (document.selection) {
			var range = document.body.createTextRange();
			range.moveToElementText(debug_node);
			range.select();
		} else if (window.getSelection) {
			window.getSelection().selectAllChildren(debug_node);
		}
	}
</script>
		<?php
		global $ewww_debug;
		$output .= '<p style="clear:both"><b>' . esc_html__( 'Debugging Information', 'ewww-image-optimizer' ) . ':</b> <button onclick="selectText(' . "'ewww-debug-info'" . ')">' . esc_html__( 'Select All', 'ewww-image-optimizer' ) . '</button>';
		if ( is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
			$debug_log_url = plugins_url( '/debug.log', __FILE__ );
			$output       .= "&emsp;<a href='$debug_log_url'>" . esc_html( 'View Debug Log', 'ewww-image-optimizer' ) . "</a> - <a href='admin.php?action=ewww_image_optimizer_delete_debug_log'>" . esc_html( 'Remove Debug Log', 'ewww-image-optimizer' ) . '</a>';
		}
		$output           .= '</p>';
		$output           .= '<div id="ewww-debug-info" style="background:#ffff99;margin-left:-20px;padding:10px" contenteditable="true">' . $ewww_debug . '</div>';
		$help_instructions = esc_html__( 'Debugging information will be included with your message automatically.', 'ewww-image-optimizer' ) . ' ' .
			esc_html__( 'This will allow us to assist you more quickly.', 'ewww-image-optimizer' );
	}
	echo $output;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		/* $help_instructions = esc_html__( 'Please turn on the Debugging option. Then copy and paste the Debug Information from the bottom of the settings page. This will allow us to assist you more quickly.', 'ewww-image-optimizer' ); */
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_config    = array(
			'color'             => '#3eadc9',
			'icon'              => 'buoy',
			'instructions'      => $help_instructions,
			'poweredBy'         => false,
			'showContactFields' => true,
			'showSubject'       => true,
			'topArticles'       => true,
			'zIndex'            => 100000,
		);
		$hs_identify  = array(
			'email' => $help_email,
		);
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
			$ewww_debug_array = explode( '<br>', $ewww_debug );
			$ewww_debug_i     = 0;
			foreach ( $ewww_debug_array as $ewww_debug_line ) {
				$hs_identify[ 'debug_info_' . $ewww_debug_i ] = $ewww_debug_line;
				$ewww_debug_i++;
			}
		}
		?>
<script type='text/javascript'>
	!function(e,o,n){window.HSCW=o,window.HS=n,n.beacon=n.beacon||{};var t=n.beacon;t.userConfig={},t.readyQueue=[],t.config=function(e){this.userConfig=e},t.ready=function(e){this.readyQueue.push(e)},o.config={docs:{enabled:!0,baseUrl:"//ewwwio.helpscoutdocs.com/"},contact:{enabled:!0,formId:"af75cf17-310a-11e7-9841-0ab63ef01522"}};var r=e.getElementsByTagName("script")[0],c=e.createElement("script");c.type="text/javascript",c.async=!0,c.src="https://djtflbt20bdde.cloudfront.net/",r.parentNode.insertBefore(c,r)}(document,window.HSCW||{},window.HS||{});
	HS.beacon.config(<?php echo json_encode( $hs_config ); ?>);
	HS.beacon.ready(function() {
		HS.beacon.identify(
			<?php echo json_encode( $hs_identify ); ?>
		);
	});
</script>
		<?php
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Displays 50 records from the images table.
 *
 * Called via AJAX to find 50 records from the images table and display them
 * with alternating row style.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images_table() {
	// Verify that an authorized user has called function.
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$offset            = 50 * (int) $_POST['ewww_offset'];
	$query             = "SELECT path,orig_size,image_size,id,backup,updated FROM $ewwwdb->ewwwio_images WHERE pending=0 AND image_size > 0 ORDER BY id DESC LIMIT $offset,50";
	$already_optimized = $ewwwdb->get_results( $query, ARRAY_A );
	$upload_info       = wp_upload_dir();
	$upload_path       = $upload_info['basedir'];
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>&nbsp;</th><th>' . esc_html__( 'Filename', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Type', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 'ewww-image-optimizer' ) . '</th></tr></thead>';
	$alternate = true;
	foreach ( $already_optimized as $optimized_image ) {
		$image_name   = str_replace( ABSPATH, '', ewww_image_optimizer_relative_path_replace( $optimized_image['path'] ) );
		$image_url    = esc_url( trailingslashit( get_site_url() ) . $image_name );
		$savings      = esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) );
		$updated_time = strtotime( $optimized_image['updated'] );
		if ( DAY_IN_SECONDS * 30 + $updated_time < time() ) {
			$optimized_image['backup'] = '';
		}
		if ( strpos( $optimized_image['path'], 's3' ) === 0 ) {
			// Retrieve the mimetype of the attachment.
			$type      = esc_html__( 'Amazon S3 image', 'ewww-image-optimizer' );
			$file_size = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			/* translators: %s: human-readable filesize */
			$size_string = sprintf( esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ), $file_size );
			?>
			<tr
			<?php
			if ( $alternate ) {
				echo " class='alternate' ";
			}
			?>
			id="ewww-image-<?php echo $optimized_image['id']; ?>">
				<td style='width:80px' class='column-icon'>&nbsp;</td>
				<td class='title'><?php echo $image_name; ?></td>
				<td><?php echo $type; ?></td>
				<td>
					<?php echo "$savings <br>$size_string"; ?><br>
					<a class="removeimage" onclick="ewwwRemoveImage( <?php echo $optimized_image['id']; ?> )"><?php esc_html_e( 'Remove from table', 'ewww-image-optimizer' ); ?></a>
				<?php	if ( $optimized_image['backup'] ) { ?>
					<br><a class="restoreimage" onclick="ewwwRestoreImage( <?php echo $optimized_image['id']; ?> )"><?php esc_html_e( 'Restore original', 'ewww-image-optimizer' ); ?></a>
				<?php	} ?>
				</td>
			</tr>
			<?php
			$alternate = ! $alternate;
		} elseif ( file_exists( $optimized_image['path'] ) ) {
			// Retrieve the mimetype of the attachment.
			$type = ewww_image_optimizer_mimetype( $optimized_image['path'], 'i' );
			// Get a human readable filesize.
			$file_size = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			/* translators: %s: human-readable filesize */
			$size_string = sprintf( esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ), $file_size );
			?>
			<tr
			<?php
			if ( $alternate ) {
				echo " class='alternate' ";
			}
			?>
			id="ewww-image-<?php echo $optimized_image['id']; ?>">
				<td style='width:80px' class='column-icon'><img width='50' height='50' src="<?php echo $image_url; ?>" /></td>
				<td class='title'>...<?php echo $image_name; ?></td>
				<td><?php echo $type; ?></td>
				<td>
					<?php echo "$savings <br>$size_string"; ?><br>
					<a class="removeimage" onclick="ewwwRemoveImage( <?php echo $optimized_image['id']; ?> )"><?php esc_html_e( 'Remove from table', 'ewww-image-optimizer' ); ?></a>
				<?php	if ( $optimized_image['backup'] ) { ?>
					<br><a class="restoreimage" onclick="ewwwRestoreImage( <?php echo $optimized_image['id']; ?> )"><?php esc_html_e( 'Restore original', 'ewww-image-optimizer' ); ?></a>
				<?php	} ?>
				</td>
			</tr>
			<?php
			$alternate = ! $alternate;
		} // End if().
	} // End foreach().
	echo '</table>';
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
	die();
}

/**
 * Removes an image from the auxiliary images table.
 *
 * Called via AJAX, this function will remove the record in provided by the
 * POST variable 'ewww_image_id' and return a '1' if successful.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images_remove() {
	// Verify that an authorized user has called function.
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	ewwwio_ob_clean();
	global $wpdb;
	if ( $wpdb->delete(
		$wpdb->ewwwio_images,
		array(
			'id' => $_POST['ewww_image_id'],
		)
	) ) {
		echo '1';
	}
	ewwwio_memory( __FUNCTION__ );
	die();
}

/**
 * Find the number of optimized images in the ewwwio_images table.
 *
 * @global object $wpdb
 * @return int The total number of records in the images table that are not pending and have a
 *             valid file-size.
 */
function ewww_image_optimizer_aux_images_table_count() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE pending=0 AND image_size > 0" );
	if ( ! empty( $_REQUEST['ewww_inline'] ) ) {
		ewwwio_ob_clean();
		echo $count;
		ewwwio_memory( __FUNCTION__ );
		die();
	}
	ewwwio_memory( __FUNCTION__ );
	return $count;
}

/**
 * Find the number of un-optimized images in the ewwwio_images table.
 *
 * @global object $wpdb
 * @return int Number of pending images in queue.
 */
function ewww_image_optimizer_aux_images_table_count_pending() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE pending=1" );
	return $count;
}

/**
 * Find the number of un-optimized (media) images in the ewwwio_images table.
 *
 * This is useful to know if we need to alert the user when the bulk attachments array is empty.
 *
 * @global object $wpdb
 * @return int Number of pending media images in queue.
 */
function ewww_image_optimizer_aux_images_table_count_pending_media() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE pending=1 AND gallery='media'" );
	return $count;
}

/**
 * Remove all un-optimized images from the ewwwio_images table.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_delete_pending() {
	global $wpdb;
	$wpdb->query( "DELETE from $wpdb->ewwwio_images WHERE pending=1 AND (image_size IS NULL OR image_size = 0)" );
	$wpdb->update( $wpdb->ewwwio_images,
		array(
			'pending' => 0,
		),
		array(
			'pending' => 1,
		)
	);
}

/**
 * Searches for images to optimize in a specific folder.
 *
 * Scan a folder for images and mark unoptimized images in the database
 * (inserts new records as necessary).
 *
 * @global object $wpdb
 * @global array|string $optimized_list An associative array containing information from the images
 *                                      table, or 'low_memory', 'large_list', 'small_scan'.
 *
 * @param string $dir The absolute path of the folder to be scanned for unoptimized images.
 * @param int    $started Optional. The number of seconds since the overall scanning process started. Default 0.
 */
function ewww_image_optimizer_image_scan( $dir, $started = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$folders_completed = get_option( 'ewww_image_optimizer_aux_folders_completed' );
	if ( ! is_array( $folders_completed ) ) {
		$folders_completed = array();
	}
	if ( in_array( $dir, $folders_completed ) ) {
		return;
	}
	global $wpdb;
	global $optimized_list;
	$images       = array();
	$reset_images = array();
	if ( ! is_dir( $dir ) ) {
		return;
	}
	ewwwio_debug_message( "scanning folder for images: $dir" );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
	$start    = microtime( true );
	if ( empty( $optimized_list ) || ! is_array( $optimized_list ) ) {
		ewww_image_optimizer_optimized_list();
	}
	$file_counter = 0; // Used to track total files overall.
	$image_count  = 0; // Used to track number of files since last queue update.
	if ( ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	$enabled_types = array();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
		$enabled_types[] = 'image/jpeg';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
		$enabled_types[] = 'image/png';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
		$enabled_types[] = 'image/gif';
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
		$enabled_types[] = 'application/pdf';
	}
	foreach ( $iterator as $file ) {
		if ( get_transient( 'ewww_image_optimizer_aux_iterator' ) && get_transient( 'ewww_image_optimizer_aux_iterator' ) > $file_counter ) {
			continue;
		}
		if ( $started && ! empty( $_REQUEST['ewww_scan'] ) && 0 === $file_counter % 100 && microtime( true ) - $started > apply_filters( 'ewww_image_optimizer_timeout', 15 ) ) {
			if ( ! empty( $reset_images ) ) {
				array_walk( $reset_images, 'intval' );
				$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' ); // WPCS: unprepared SQL ok.
			}
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // Keep track of where we left off, minus 20 to be safe.
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			ewwwio_ob_clean();
			die( json_encode( array(
				'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'notice'    => '',
			) ) );
		}
		// TODO: can we tailor this for scheduled opt also?
		if ( ! empty( $_REQUEST['ewww_scan'] ) && 0 === $file_counter % 100 && ! ewwwio_check_memory_available( 2097000 ) ) {
			if ( $file_counter < 100 ) {
				ewwwio_ob_clean();
				die( json_encode( array(
					'error' => esc_html__( 'Stage 2 unable to complete due to memory restrictions. Please increase the memory_limit setting for PHP and try again.', 'ewww-image-optimizer' ),
				) ) );
			}
			if ( ! empty( $reset_images ) ) {
				array_walk( $reset_images, 'intval' );
				$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' ); // WPCS: unprepared SQL ok.
			}
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // Keep track of where we left off, minus 20 to be safe.
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			ewwwio_ob_clean();
			die( json_encode( array(
				'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
				'notice'    => '',
			) ) );
		}
		$file_counter++;
		if ( $file->isFile() ) {
			$path = $file->getPathname();
			if ( preg_match( '/(\/|\\\\)\./', $path ) && apply_filters( 'ewww_image_optimizer_ignore_hidden_files', true ) ) {
				continue;
			}
			$mime = ewww_image_optimizer_quick_mimetype( $path );
			if ( ! in_array( $mime, $enabled_types ) ) {
				continue;
			}
			if ( apply_filters( 'ewww_image_optimizer_bypass', false, $path ) === true ) {
				ewwwio_debug_message( "skipping $path as instructed" );
				continue;
			}

			$already_optimized = false;
			if ( ! is_array( $optimized_list ) && is_string( $optimized_list ) ) {
				$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
			} elseif ( is_array( $optimized_list ) && isset( $optimized_list[ $path ] ) && ! empty( $optimized_list[ $path ] ) ) {
				$already_optimized = $optimized_list[ $path ];
			}

			if ( is_array( $already_optimized ) && ! empty( $already_optimized ) ) {
				if ( ! empty( $already_optimized['pending'] ) ) {
					ewwwio_debug_message( "pending record for $path" );
					continue;
				}
				$image_size = $file->getSize();
				if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
					ewwwio_debug_message( "file skipped due to filesize: $path" );
					continue;
				}
				if ( 'image/png' == $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				if ( $already_optimized['image_size'] == $image_size && empty( $_REQUEST['ewww_force'] ) ) {
					ewwwio_debug_message( "match found for $path" );
					continue;
				} else {
					$reset_images[] = (int) $already_optimized['id'];
					ewwwio_debug_message( "mismatch found for $path, db says " . $already_optimized['image_size'] . " vs. current $image_size" );
				}
			} else {
				$image_size = $file->getSize();
				if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
					ewwwio_debug_message( "file skipped due to filesize: $path" );
					continue;
				}
				if ( 'image/png' == $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				ewwwio_debug_message( "queuing $path" );
				$path = ewww_image_optimizer_relative_path_remove( $path );
				if ( seems_utf8( $path ) ) {
					$utf8_file_path = $path;
				} else {
					$utf8_file_path = utf8_encode( $path );
				}
				$images[] = array(
					'path'      => $utf8_file_path,
					'orig_size' => $image_size,
					'pending'   => 1,
				);
				$image_count++;
			} // End if().
			if ( $image_count > 1000 ) {
				// Let's dump what we have so far to the db.
				$image_count = 0;
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
				$images = array();
			}
		} // End if().
	} // End foreach().
	if ( ! empty( $images ) ) {
		ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
	}
	if ( ! empty( $reset_images ) ) {
		array_walk( $reset_images, 'intval' );
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' ); // WPCS: unprepared SQL ok.
	}
	delete_transient( 'ewww_image_optimizer_aux_iterator' );
	$end = microtime( true ) - $start;
	ewwwio_debug_message( "query time for $file_counter files (seconds): $end" );
	clearstatcache();
	ewwwio_memory( __FUNCTION__ );
	$folders_completed[] = $dir;
	update_option( 'ewww_image_optimizer_aux_folders_completed', $folders_completed, false );
}

/**
 * Convert all records in table to use filesize rather than md5sum.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images_convert() {
	global $wpdb;
	$old_records = $wpdb->get_results( "SELECT id,path,image_md5 FROM $wpdb->ewwwio_images", ARRAY_A );
	foreach ( $old_records as $record ) {
		if ( empty( $record['image_md5'] ) ) {
			continue;
		}
		$record['path'] = ewww_image_optimizer_relative_path_replace( $record['path'] );
		$image_md5      = md5_file( $record['path'] );
		if ( $image_md5 === $record['image_md5'] ) {
			$filesize = filesize( $record['path'] );
			$wpdb->update( $wpdb->ewwwio_images,
				array(
					'image_md5'  => null,
					'image_size' => $filesize,
				),
				array(
					'id' => $record['id'],
				)
			);
		} else {
			$wpdb->delete( $wpdb->ewwwio_images,
				array(
					'id' => $record['id'],
				)
			);
		}
	}
}

/**
 * Searches for images to optimize.
 *
 * Scans all auxiliary folders, including some predefined ones, and those configured by the user.
 * Used for the main bulk tool, and the scheduled optimization.
 *
 * @param string $hook Optional. Indicates if scheduled optimization is running.
 * @global object $wpdb
 * @return int Number of images ready to optimize.
 */
function ewww_image_optimizer_aux_images_script( $hook = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the proper page.
	if ( wp_doing_ajax() && empty( $_REQUEST['ewww_scan'] ) ) {
		return;
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_force'] ) ) {
		ewwwio_debug_message( 'forcing re-optimize: true' );
	}
	// Retrieve the time when the scan starts.
	$started = microtime( true );
	if ( ! get_transient( 'ewww_image_optimizer_skip_aux' ) ) {
		update_option( 'ewww_image_optimizer_aux_resume', 'scanning' );
		ewwwio_debug_message( 'getting fresh list of files to optimize' );
		// Collect a list of images from the current theme (and parent theme if applicable).
		$child_path  = get_stylesheet_directory();
		$parent_path = get_template_directory();
		ewww_image_optimizer_image_scan( $child_path, $started );
		if ( $child_path !== $parent_path ) {
			ewww_image_optimizer_image_scan( $parent_path, $started );
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_plugin_active( 'buddypress/bp-loader.php' ) || is_plugin_active_for_network( 'buddypress/bp-loader.php' ) ) {
			$upload_dir = wp_upload_dir();
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/avatars', $started );
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/group-avatars', $started );
		}
		if ( is_plugin_active( 'buddypress-activity-plus/bpfb.php' ) || is_plugin_active_for_network( 'buddypress-activity-plus/bpfb.php' ) ) {
			$upload_dir = wp_upload_dir();
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/bpfb', $started );
		}
		if ( is_plugin_active( 'grand-media/grand-media.php' ) || is_plugin_active_for_network( 'grand-media/grand-media.php' ) ) {
			// Scan the grand media folder for images.
			ewww_image_optimizer_image_scan( WP_CONTENT_DIR . '/grand-media', $started );
		}
		if ( is_plugin_active( 'wp-symposium/wp-symposium.php' ) || is_plugin_active_for_network( 'wp-symposium/wp-symposium.php' ) ) {
			ewww_image_optimizer_image_scan( get_option( 'symposium_img_path' ), $started );
		}
		if ( defined( 'WPS_CORE_PLUGINS' ) ) {
			ewww_image_optimizer_image_scan( WP_CONTENT_DIR . '/wps-pro-content', $started );
		}
		if ( is_plugin_active( 'ml-slider/ml-slider.php' ) || is_plugin_active_for_network( 'ml-slider/ml-slider.php' ) ) {
			global $wpdb;
			$slide_paths = array();
			$slides      = $wpdb->get_col(
				"
				SELECT wpposts.ID
				FROM $wpdb->posts wpposts
				INNER JOIN $wpdb->term_relationships term_relationships
						ON wpposts.ID = term_relationships.object_id
				INNER JOIN $wpdb->terms wpterms
						ON term_relationships.term_taxonomy_id = wpterms.term_id
				INNER JOIN $wpdb->term_taxonomy term_taxonomy
						ON wpterms.term_id = term_taxonomy.term_id
				WHERE 	term_taxonomy.taxonomy = 'ml-slider'
					AND wpposts.post_type = 'attachment'
				"
			);
			if ( ewww_image_optimizer_iterable( $slides ) ) {
				foreach ( $slides as $slide ) {
					$type = get_post_meta( $slide, 'ml-slider_type', true );
					$type = $type ? $type : 'image'; // For backwards compatibility, fall back to 'image'.
					if ( 'image' != $type ) {
						continue;
					}
					$backup_sizes = get_post_meta( $slide, '_wp_attachment_backup_sizes', true );
					if ( ewww_image_optimizer_iterable( $backup_sizes ) ) {
						foreach ( $backup_sizes as $backup_size => $meta ) {
							if ( preg_match( '/resized-/', $backup_size ) ) {
								$path       = $meta['path'];
								$image_size = ewww_image_optimizer_filesize( $path );
								if ( ! $image_size ) {
									continue;
								}
								$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
								// A pending record already present.
								if ( ! empty( $already_optimized ) && empty( $already_optimized['image_size'] ) ) {
									continue;
								}
								$mimetype = ewww_image_optimizer_mimetype( $path, 'i' );
								// This is a brand new image.
								if ( preg_match( '/^image\/(jpeg|png|gif)/', $mimetype ) && empty( $already_optimized ) ) {
									$slide_paths[] = array(
										'path'      => ewww_image_optimizer_relative_path_remove( $path ),
										'orig_size' => $image_size,
									);
									// This is a changed image.
								} elseif ( preg_match( '/^image\/(jpeg|png|gif)/', $mimetype ) && ! empty( $already_optimized ) && $already_optimized['image_size'] != $image_size ) {
									$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id = %d", $already_optimized['id'] ) );
								}
							}
						}
					}
				}
			} // End if().
			if ( ! empty( $slide_paths ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $slide_paths, array( '%s', '%d' ) );
			}
		} // End if().
		// Collect a list of images in auxiliary folders provided by user.
		$aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' );
		if ( $aux_paths ) {
			if ( ewww_image_optimizer_iterable( $aux_paths ) ) {
				foreach ( $aux_paths as $aux_path ) {
					ewww_image_optimizer_image_scan( $aux_path, $started );
				}
			}
		}
		// Scan images in two most recent media library folders if the option is enabled, and this is a scheduled optimization.
		if ( 'ewww-image-optimizer-auto' == $hook && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) ) {
			// Retrieve the location of the WordPress upload folder.
			$upload_dir = wp_upload_dir();
			// Retrieve the path of the upload folder.
			$upload_path = $upload_dir['basedir'];
			$this_month  = date( 'm' );
			$this_year   = date( 'Y' );
			ewww_image_optimizer_image_scan( "$upload_path/$this_year/$this_month/", $started );
			if ( class_exists( 'DateTime' ) ) {
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1M' ) );
				$last_year  = $date->format( 'Y' );
				$last_month = $date->format( 'm' );
				ewww_image_optimizer_image_scan( "$upload_path/$last_year/$last_month/", $started );
			}
		}
	} // End if().
	$image_count = ewww_image_optimizer_aux_images_table_count_pending();
	ewwwio_debug_message( "found $image_count images to optimize while scanning" );
	update_option( 'ewww_image_optimizer_aux_folders_completed', array(), false );
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	ewww_image_optimizer_debug_log();
	if ( wp_doing_ajax() ) {
		ewwwio_memory( __FUNCTION__ );
		/* translators: %d: number of images */
		$ready_msg = sprintf( esc_html( _n( 'There is %d image ready to optimize.', 'There are %d images ready to optimize.', $image_count, 'ewww-image-optimizer' ) ), $image_count )
			. ' <a href="https://docs.ewww.io/article/20-why-do-i-have-so-many-images-on-my-site" target="_blank" data-beacon-article="58598744c697912ffd6c3eb4">' . esc_html__( 'Why are there so many images?', 'ewww-image-optimizer' ) . '</a>';
		ewwwio_ob_clean();
		die( json_encode( array(
			'ready'   => $image_count,
			'message' => $ready_msg,
		) ) );
	}
	ewwwio_memory( __FUNCTION__ );
	return $image_count;
}

/**
 * Called by scheduled optimization to cleanup after ourselves.
 *
 * @param bool $auto Indicates whether or not the function is called from scheduled (auto) optimization mode.
 */
function ewww_image_optimizer_aux_images_cleanup( $auto = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	$stored_last = get_option( 'ewww_image_optimizer_aux_last' );
	update_option( 'ewww_image_optimizer_aux_last', array( time(), $stored_last[1] ) );
	// All done, so we can update the bulk options with empty values.
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	if ( ! $auto ) {
		ewwwio_ob_clean();
		// And let the user know we are done.
		echo '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>';
		ewwwio_memory( __FUNCTION__ );
		die();
	}
}

add_action( 'wp_ajax_bulk_aux_images_table', 'ewww_image_optimizer_aux_images_table' );
add_action( 'wp_ajax_bulk_aux_images_table_count', 'ewww_image_optimizer_aux_images_table_count' );
add_action( 'wp_ajax_bulk_aux_images_remove', 'ewww_image_optimizer_aux_images_remove' );
?>
