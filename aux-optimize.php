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
	$output = '';

	global $eio_debug;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		ewww_image_optimizer_options( 'debug-silent' );
		$output .= '<div style="clear:both;"></div>';
		$output .= '<p><b>' . esc_html__( 'Debugging Information', 'ewww-image-optimizer' ) . ':</b> <button id="ewww-copy-debug" class="button button-secondary" type="button">' . esc_html__( 'Copy', 'ewww-image-optimizer' ) . '</button>';
		if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
			$debug_log_url = plugins_url( '/debug.log', __FILE__ );
			$output       .= "&emsp;<a href='$debug_log_url'>" . esc_html( 'View Debug Log', 'ewww-image-optimizer' ) . "</a> - <a href='admin.php?action=ewww_image_optimizer_delete_debug_log'>" . esc_html( 'Remove Debug Log', 'ewww-image-optimizer' ) . '</a>';
		}
		$output .= '</p>';
		$output .= '<div id="ewww-debug-info" contenteditable="true">' . $eio_debug . '</div>';
	} else {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_debug', true );
		ewww_image_optimizer_options( 'debug-silent' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_debug', false );
	}
	echo $output;
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_debug     = '';
		if ( ! empty( $eio_debug ) ) {
			$hs_debug = str_replace( array( "'", '<br>', '<b>', '</b>' ), array( "\'", '\n', '<', '>' ), $eio_debug );
		}
		?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo utf8_encode( $help_email ); ?>',
		text: '\n\n----------------------------------------\n<?php echo $hs_debug; ?>',
	});
</script>
		<?php
	}
	$eio_debug = '';
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
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-tools' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( ewwwio_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$per_page = 50;
	$offset   = $per_page * (int) $_POST['ewww_offset'];
	$search   = sanitize_text_field( $_POST['ewww_search'] );
	$total    = (int) $_POST['ewww_total_pages'];
	$output   = array();
	if ( ! empty( $search ) ) {
		$already_optimized = $ewwwdb->get_results( $ewwwdb->prepare( "SELECT path,orig_size,image_size,id,backup,updated FROM $ewwwdb->ewwwio_images WHERE pending=0 AND image_size > 0 AND path LIKE %s ORDER BY id DESC LIMIT %d,%d", '%' . $ewwwdb->esc_like( $search ) . '%', $offset, $per_page ), ARRAY_A );
		$search_count      = $ewwwdb->get_var( $ewwwdb->prepare( "SELECT COUNT(*) FROM $ewwwdb->ewwwio_images WHERE pending=0 AND image_size > 0 AND path LIKE %s", '%' . $ewwwdb->esc_like( $search ) . '%' ) );
		if ( $search_count < $per_page ) {
			/* translators: %d: number of image records found */
			$output['search_result'] = sprintf( esc_html__( '%d items found', 'ewww-image-optimizer' ), count( $already_optimized ) );
		} else {
			/* translators: 1: number of image records displayed, 2: number of total records found */
			$output['search_result'] = sprintf( esc_html__( '%1$d items displayed of %2$d records found', 'ewww-image-optimizer' ), count( $already_optimized ), $search_count );
		}
		$total = ceil( $search_count / $per_page );
	} else {
		$already_optimized = $ewwwdb->get_results( $ewwwdb->prepare( "SELECT path,orig_size,image_size,id,backup,updated FROM $ewwwdb->ewwwio_images WHERE pending=0 AND image_size > 0 ORDER BY id DESC LIMIT %d,%d", $offset, $per_page ), ARRAY_A );
		/* translators: %d: number of image records found */
		$output['search_result'] = sprintf( esc_html__( '%d items displayed', 'ewww-image-optimizer' ), count( $already_optimized ) );
	}
	/* translators: 1: current page in list of images 2: total pages for list of images */
	$output['pagination']   = sprintf( esc_html__( 'page %1$d of %2$d', 'ewww-image-optimizer' ), (int) $_POST['ewww_offset'] + 1, $total );
	$output['search_count'] = count( $already_optimized );
	$output['search_total'] = $total;

	$upload_info     = wp_upload_dir();
	$upload_path     = $upload_info['basedir'];
	$output['table'] = '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>&nbsp;</th><th>' . esc_html__( 'Filename', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Type', 'ewww-image-optimizer' ) . '</th><th>' . esc_html__( 'Image Optimizer', 'ewww-image-optimizer' ) . '</th></tr></thead>';
	$alternate       = true;
	foreach ( $already_optimized as $optimized_image ) {
		$file       = ewww_image_optimizer_absolutize_path( $optimized_image['path'] );
		$image_name = str_replace( ABSPATH, '', $file );
		$image_url  = esc_url( site_url( 'wp-includes/images/media/default.png' ) );
		ewwwio_debug_message( "name is $image_name after replacing ABSPATH" );
		if ( $file !== $image_name ) {
			$image_url = esc_url( site_url( $image_name ) );
		} else {
			$image_name = str_replace( WP_CONTENT_DIR, '', $file );
			ewwwio_debug_message( "name is $image_name after replacing WP_CONTENT_DIR" );
			if ( $file !== $image_name ) {
				$image_url = esc_url( content_url( $image_name ) );
			}
		}
		$savings      = esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) );
		$updated_time = strtotime( $optimized_image['updated'] );
		if ( DAY_IN_SECONDS * 30 + $updated_time < time() ) {
			$optimized_image['backup'] = '';
		}
		if ( ewww_image_optimizer_stream_wrapped( $file ) ) {
			// Retrieve the mimetype of the attachment.
			$type = esc_html__( 'Amazon S3 image', 'ewww-image-optimizer' );
			// Get a human readable filesize.
			$file_size = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			/* translators: %s: human-readable filesize */
			$size_string = sprintf( esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ), $file_size );

			$output['table'] .= '<tr ' . ( $alternate ? "class='alternate' " : '' ) . 'id="ewww-image-' . $optimized_image['id'] . '">';
			$output['table'] .= '<td style="width:50px;" class="column-icon">&nbsp;</td>';
			$output['table'] .= "<td class='title'>$image_name</td>";
			$output['table'] .= "<td>$type</td>";
			$output['table'] .= "<td>$savings<br>$size_string<br>" .
				'<a class="removeimage" onclick="ewwwRemoveImage(' . $optimized_image['id'] . ')">' . esc_html__( 'Remove from history', 'ewww-image-optimizer' ) . '</a>' .
				( $optimized_image['backup'] ? '<br><a class="restoreimage" onclick="ewwwRestoreImage(' . $optimized_image['id'] . ')">' . esc_html__( 'Restore original', 'ewww-image-optimizer' ) . '</a>' : '' ) .
				'</td>';
			$output['table'] .= '</tr>';
			$alternate        = ! $alternate;
		} elseif ( ewwwio_is_file( $file ) ) {
			// Retrieve the mimetype of the attachment.
			$type = ewww_image_optimizer_quick_mimetype( $file, 'i' );
			// Get a human readable filesize.
			$file_size = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			/* translators: %s: human-readable filesize */
			$size_string = sprintf( esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ), $file_size );

			$output['table'] .= '<tr ' . ( $alternate ? "class='alternate' " : '' ) . 'id="ewww-image-' . $optimized_image['id'] . '">';
			$output['table'] .= "<td style='width:50px;' class='column-icon'><img style='width:50px;height:50px;object-fit:contain;' loading='lazy' src='$image_url' /></td>";
			$output['table'] .= "<td class='title'>...$image_name</td>";
			$output['table'] .= "<td>$type</td>";
			$output['table'] .= "<td>$savings<br>$size_string<br>" .
				'<a class="removeimage" onclick="ewwwRemoveImage(' . $optimized_image['id'] . ')">' . esc_html__( 'Remove from history', 'ewww-image-optimizer' ) . '</a>' .
				( $optimized_image['backup'] ? '<br><a class="restoreimage" onclick="ewwwRestoreImage(' . $optimized_image['id'] . ')">' . esc_html__( 'Restore original', 'ewww-image-optimizer' ) . '</a>' : '' ) .
				'</td>';
			$output['table'] .= '</tr>';
			$alternate        = ! $alternate;
		} else {
			// Retrieve the mimetype of the attachment.
			$type = ewww_image_optimizer_quick_mimetype( $file, 'i' );
			// Get a human readable filesize.
			$file_size = ewww_image_optimizer_size_format( $optimized_image['image_size'] );
			/* translators: %s: human-readable filesize */
			$size_string = sprintf( esc_html__( 'Image Size: %s', 'ewww-image-optimizer' ), $file_size );

			$output['table'] .= '<tr ' . ( $alternate ? "class='alternate' " : '' ) . 'id="ewww-image-' . $optimized_image['id'] . '">';
			$output['table'] .= "<td style='width:50px;' class='column-icon'>" . esc_html__( 'file not found', 'ewww-image-optimizer' ) . '</td>';
			$output['table'] .= "<td class='title'>...$image_name</td>";
			$output['table'] .= "<td>$type</td>";
			$output['table'] .= "<td>$savings<br>$size_string<br>" .
				'<a class="removeimage" onclick="ewwwRemoveImage(' . $optimized_image['id'] . ')">' . esc_html__( 'Remove from history', 'ewww-image-optimizer' ) . '</a>' .
				'</td>';
			$output['table'] .= '</tr>';
			$alternate        = ! $alternate;
			ewwwio_debug_message( "could not find $file" );
		} // End if().
	} // End foreach().
	$output['table'] .= '</table>';
	die( ewwwio_json_encode( $output ) );
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
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-tools' ) || ! current_user_can( $permissions ) ) {
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
 * Removes all images from the auxiliary images table.
 *
 * Called via AJAX, this function will return a '1' if successful.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images_clear_all() {
	// Verify that an authorized user has called function.
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-tools' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	ewwwio_ob_clean();
	global $wpdb;
	if ( $wpdb->query( "TRUNCATE $wpdb->ewwwio_images" ) ) {
		die( esc_html__( 'All records have been removed from the optimization history.', 'ewww-image-optimizer' ) );
	}
	ewwwio_memory( __FUNCTION__ );
	die();
}

/**
 * Cleanup duplicate and unreferenced records from the images table.
 *
 * Called via AJAX to find records from the images table and checks them for duplicates and
 * references to non-existent files.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_aux_images_clean() {
	// Verify that an authorized user has called function.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-tools' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( ewwwio_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$per_page = 500;
	$offset   = $per_page * (int) $_POST['ewww_offset'];

	$already_optimized = $wpdb->get_results( $wpdb->prepare( "SELECT path,orig_size,image_size,id,backup,updated FROM $wpdb->ewwwio_images WHERE pending=0 AND image_size > 0 ORDER BY id DESC LIMIT %d,%d", $offset, $per_page ), ARRAY_A );

	$upload_info = wp_upload_dir();
	$upload_path = $upload_info['basedir'];
	foreach ( $already_optimized as $optimized_image ) {
		$file = ewww_image_optimizer_absolutize_path( $optimized_image['path'] );
		ewwwio_debug_message( "checking $file for duplicates and dereferences" );
		// Will remove duplicates.
		ewww_image_optimizer_find_already_optimized( $file );
		if ( ! ewww_image_optimizer_stream_wrapped( $file ) && ! ewwwio_is_file( $file ) ) {
			ewwwio_debug_message( "removing defunct record for $file" );
			$wpdb->delete(
				$wpdb->ewwwio_images,
				array(
					'id' => $optimized_image['id'],
				),
				array( '%d' )
			);
		}
	} // End foreach().
	die( ewwwio_json_encode( array( 'success' => 1 ) ) );
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
 * Set a batch of images to pending.
 *
 * @global object $wpdb
 *
 * @param array $reset_images A list of images to reset in the ewwwio_images table.
 */
function ewww_image_optimizer_reset_images( $reset_images ) {
	if ( ! ewww_image_optimizer_iterable( $reset_images ) ) {
		return;
	}
	array_walk( $reset_images, 'intval' );
	global $wpdb;
	$reset_images_sql = '(' . implode( ',', $reset_images ) . ')';
	$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1, updated = updated WHERE id IN $reset_images_sql" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Remove all un-optimized images from the ewwwio_images table.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_delete_pending() {
	global $wpdb;
	$wpdb->query( "DELETE from $wpdb->ewwwio_images WHERE pending=1 AND (image_size IS NULL OR image_size = 0)" );
	$wpdb->update(
		$wpdb->ewwwio_images,
		array(
			'pending' => 0,
		),
		array(
			'pending' => 1,
		)
	);
}

/**
 * Retrieve the number images from the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param string $gallery The type of attachments to count from the queue. Default is media library.
 * @global object $wpdb
 */
function ewww_image_optimizer_count_attachments( $gallery = 'media' ) {
	global $wpdb;
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->ewwwio_queue WHERE gallery = %s", $gallery ) );
	return $count;
}

/**
 * Retrieve the number of un-scanned images from the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param string $gallery The type of attachments to count from the queue.
 * @global object $wpdb
 */
function ewww_image_optimizer_count_unscanned_attachments( $gallery = 'media' ) {
	global $wpdb;
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->ewwwio_queue WHERE gallery = %s AND scanned = 0", $gallery ) );
	return $count;
}

/**
 * Retrieve unscanned images from the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param string $gallery The type of attachments for which to search.
 * @param int    $limit The maximum number of unscanned attachments to retrieve.
 * @return array A list of unscanned attachments. Will always be an array of integers.
 * @global object $wpdb
 */
function ewww_image_optimizer_get_unscanned_attachments( $gallery, $limit = 1000 ) {
	global $wpdb;
	// Retrieve the attachment IDs that were pre-loaded in the database.
	$selected_ids = $wpdb->get_col( $wpdb->prepare( "SELECT attachment_id FROM $wpdb->ewwwio_queue WHERE gallery = %s AND scanned = 0 LIMIT %d", $gallery, $limit ) );
	if ( empty( $selected_ids ) ) {
		ewwwio_debug_message( 'no attachments found for scanning' );
		return array();
	}
	array_walk( $selected_ids, 'intval' );
	ewwwio_debug_message( 'selected items: ' . count( $selected_ids ) );
	return $selected_ids;
}

/**
 * Retrieve an image ID from the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param string $gallery The type of attachment to find.
 * @param int    $limit The maximum number of unscanned attachments to retrieve.
 * @return array The ID list for queued/scanned attachments.
 * @global object $wpdb
 */
function ewww_image_optimizer_get_queued_attachments( $gallery, $limit = 100 ) {
	global $wpdb;
	// Retrieve the attachment IDs that were pre-loaded in the database.
	$selected_ids = $wpdb->get_col( $wpdb->prepare( "SELECT attachment_id FROM $wpdb->ewwwio_queue WHERE gallery = %s AND scanned = 1 LIMIT %d", $gallery, $limit ) );
	if ( empty( $selected_ids ) ) {
		ewwwio_debug_message( 'no attachments found in queue' );
		return array( 0 );
	}
	array_walk( $selected_ids, 'intval' );
	ewwwio_debug_message( 'selected items: ' . count( $selected_ids ) );
	return $selected_ids;
}

/**
 * Insert a batch of attachment IDs into the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param array  $ids The list of attachment IDs to insert.
 * @param string $gallery The type of attachments to insert. Defaults to media library.
 * @global object $wpdb
 */
function ewww_image_optimizer_insert_unscanned( $ids, $gallery = 'media' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$images = array();
	$id     = array_shift( $ids );
	while ( ! empty( $id ) ) {
		$images[] = array(
			'attachment_id' => (int) $id,
			'gallery'       => $gallery,
		);
		if ( count( $images ) > 999 ) {
			ewww_image_optimizer_mass_insert( $wpdb->ewwwio_queue, $images, array( '%d', '%s' ) );
			$images = array();
		}
		$id = array_shift( $ids );
	}
	if ( $images ) {
		ewww_image_optimizer_mass_insert( $wpdb->ewwwio_queue, $images, array( '%d', '%s' ) );
	}
}

/**
 * Update an image in the queue after it has been scanned.
 *
 * @since 4.6.0
 *
 * @param int    $ids The attachment IDs to update.
 * @param string $gallery The type of attachment to update. Defaults to media library.
 * @global object $wpdb
 */
function ewww_image_optimizer_update_scanned_images( $ids, $gallery = 'media' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_iterable( $ids ) ) {
		return;
	}
	global $wpdb;

	array_walk( $ids, 'intval' );
	$ids_sql = '(' . implode( ',', $ids ) . ')';

	$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->ewwwio_queue SET scanned = 1 WHERE gallery = %s AND attachment_id IN $ids_sql", $gallery ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Remove an image from the ewwwio_queue table (usually when we are done with it).
 *
 * @since 4.6.0
 *
 * @param int    $ids The attachment IDs to remove.
 * @param string $gallery The type of attachment to remove. Defaults to media library.
 * @global object $wpdb
 */
function ewww_image_optimizer_delete_queued_images( $ids, $gallery = 'media' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_iterable( $ids ) ) {
		return;
	}
	global $wpdb;

	array_walk( $ids, 'intval' );
	$ids_sql = '(' . implode( ',', $ids ) . ')';

	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s AND attachment_id IN $ids_sql", $gallery ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Remove images from the ewwwio_queue table.
 *
 * @since 4.6.0
 *
 * @param string $gallery The type of attachments to clear from the queue. Default media library.
 * @global object $wpdb
 */
function ewww_image_optimizer_delete_queue_images( $gallery = 'media' ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_queue WHERE gallery = %s", $gallery ) );
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
	if ( in_array( $dir, $folders_completed, true ) ) {
		ewwwio_debug_message( "$dir already completed" );
		return;
	}
	global $wpdb;
	global $optimized_list;
	$images       = array();
	$reset_images = array();
	if ( ! is_dir( $dir ) ) {
		ewwwio_debug_message( "$dir is not a directory, or unreadable" );
		return;
	}
	ewwwio_debug_message( "scanning folder for images: $dir" );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
	$start    = microtime( true );
	// Make the Force Re-optimize option persistent.
	if ( ! empty( $_REQUEST['ewww_force'] ) ) {
		set_transient( 'ewww_image_optimizer_force_reopt', true, HOUR_IN_SECONDS );
	} else {
		delete_transient( 'ewww_image_optimizer_force_reopt' );
	}
	// Make the Smart Re-optimize option persistent.
	if ( ! empty( $_REQUEST['ewww_force_smart'] ) ) {
		set_transient( 'ewww_image_optimizer_smart_reopt', true, HOUR_IN_SECONDS );
	} else {
		delete_transient( 'ewww_image_optimizer_smart_reopt' );
	}
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
			ewww_image_optimizer_reset_images( $reset_images );
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // Keep track of where we left off, minus 20 to be safe.
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			ewwwio_ob_clean();
			die(
				ewwwio_json_encode(
					array(
						'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
						'notice'    => '',
					)
				)
			);
		}
		// TODO: can we tailor this for scheduled opt also?
		if ( ! empty( $_REQUEST['ewww_scan'] ) && 0 === $file_counter % 100 && ! ewwwio_check_memory_available( 2097000 ) ) {
			if ( $file_counter < 100 ) {
				ewwwio_ob_clean();
				die(
					ewwwio_json_encode(
						array(
							'error' => esc_html__( 'Stage 2 unable to complete due to memory restrictions. Please increase the memory_limit setting for PHP and try again.', 'ewww-image-optimizer' ),
						)
					)
				);
			}
			ewww_image_optimizer_reset_images( $reset_images );
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // Keep track of where we left off, minus 20 to be safe.
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			ewwwio_ob_clean();
			die(
				ewwwio_json_encode(
					array(
						'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
						'notice'    => '',
					)
				)
			);
		}
		$file_counter++;
		if ( $file->isFile() ) {
			$path = $file->getPathname();
			if ( preg_match( '/(\/|\\\\)\./', $path ) && apply_filters( 'ewww_image_optimizer_ignore_hidden_files', true ) ) {
				continue;
			}
			if ( defined( 'EWWW_IMAGE_OPTIMIZER_REAL_MIME' ) && EWWW_IMAGE_OPTIMIZER_REAL_MIME ) {
				$mime = ewww_image_optimizer_mimetype( $path, 'i' );
			} else {
				$mime = ewww_image_optimizer_quick_mimetype( $path );
			}
			if ( ! in_array( $mime, $enabled_types, true ) ) {
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
				if ( 'image/png' === $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				$compression_level = ewww_image_optimizer_get_level( $mime );
				if ( ! empty( $_REQUEST['ewww_force_smart'] ) && ewww_image_optimizer_level_mismatch( $already_optimized['level'], $compression_level ) ) {
					$reset_images[] = (int) $already_optimized['id'];
					ewwwio_debug_message( "smart re-opt found level mismatch for $path, db says " . $already_optimized['level'] . " vs. current $compression_level" );
				} elseif ( (int) $already_optimized['image_size'] === $image_size && empty( $_REQUEST['ewww_force'] ) ) {
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
				if ( 'image/png' === $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				ewwwio_debug_message( "queuing $path" );
				$path = ewww_image_optimizer_relativize_path( $path );
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
	ewww_image_optimizer_reset_images( $reset_images );
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
		$record['path'] = ewww_image_optimizer_absolutize_path( $record['path'] );
		$image_md5      = md5_file( $record['path'] );
		if ( $image_md5 === $record['image_md5'] ) {
			$filesize = filesize( $record['path'] );
			$wpdb->update(
				$wpdb->ewwwio_images,
				array(
					'image_md5'  => null,
					'image_size' => $filesize,
				),
				array(
					'id' => $record['id'],
				)
			);
		} else {
			$wpdb->delete(
				$wpdb->ewwwio_images,
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
	if ( ! empty( $_REQUEST['ewww_force_smart'] ) ) {
		ewwwio_debug_message( 'forcing (smart) re-optimize: true' );
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
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
			ewww_image_optimizer_image_scan( WP_CONTENT_DIR . '/ewww/lazy/', $started );
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
					if ( 'image' !== $type ) {
						continue;
					}
					$backup_sizes = get_post_meta( $slide, '_wp_attachment_backup_sizes', true );
					if ( ewww_image_optimizer_iterable( $backup_sizes ) ) {
						foreach ( $backup_sizes as $backup_size => $meta ) {
							if ( preg_match( '/resized-/', $backup_size ) ) {
								$path = $meta['path'];
								if ( ! ewwwio_is_file( $path ) ) {
									continue;
								}
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
										'path'      => ewww_image_optimizer_relativize_path( $path ),
										'orig_size' => $image_size,
									);
									// This is a changed image.
								} elseif ( preg_match( '/^image\/(jpeg|png|gif)/', $mimetype ) && ! empty( $already_optimized ) && (int) $already_optimized['image_size'] !== $image_size ) {
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
		if ( 'ewww-image-optimizer-auto' === $hook && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) ) {
			// Retrieve the location of the WordPress upload folder.
			$upload_dir = wp_upload_dir();
			// Retrieve the path of the upload folder.
			$upload_path = $upload_dir['basedir'];
			$this_month  = gmdate( 'm' );
			$this_year   = gmdate( 'Y' );
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
		$verify_cloud = ewww_image_optimizer_cloud_verify( false );
		$usage        = false;
		if ( preg_match( '/great/', $verify_cloud ) ) {
			$usage = ewww_image_optimizer_cloud_quota( true );
		}
		ewwwio_memory( __FUNCTION__ );
		/* translators: %s: number of images */
		$ready_msg = sprintf( esc_html( _n( 'There is %s image ready to optimize.', 'There are %s images ready to optimize.', $image_count, 'ewww-image-optimizer' ) ), '<strong>' . number_format_i18n( $image_count ) . '</strong>' );
		if ( is_array( $usage ) && ! $usage['metered'] ) {
			$credits_available = $usage['licensed'] - $usage['consumed'];
			if ( $credits_available < $image_count ) {
				$ready_msg .= ' ' . esc_html__( 'You do not appear to have enough image credits to complete this operation.', 'ewww-image-optimizer' );
			}
		}
		if ( $image_count > 1000 ) {
			$ready_msg .= ' <a href="https://docs.ewww.io/article/20-why-do-i-have-so-many-images-on-my-site" target="_blank" data-beacon-article="58598744c697912ffd6c3eb4">' . esc_html__( 'Why are there so many images?', 'ewww-image-optimizer' ) . '</a>';
		}
		ewwwio_ob_clean();
		die(
			ewwwio_json_encode(
				array(
					'ready'        => $image_count,
					'message'      => $ready_msg,
					/* translators: %s: number of images */
					'start_button' => sprintf( esc_attr__( 'Optimize %s images', 'ewww-image-optimizer' ), number_format_i18n( $image_count ) ),
				)
			)
		);
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
add_action( 'wp_ajax_bulk_aux_images_table_clear', 'ewww_image_optimizer_aux_images_clear_all' );
add_action( 'wp_ajax_bulk_aux_images_table_clean', 'ewww_image_optimizer_aux_images_clean' );
add_action( 'wp_ajax_bulk_aux_images_remove', 'ewww_image_optimizer_aux_images_remove' );
?>
