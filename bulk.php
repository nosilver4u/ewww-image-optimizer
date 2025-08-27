<?php
/**
 * Functions for performing Bulk Optimizations
 * This file contains functions for the main bulk optimize page.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presents the tools page and optimization results table.
 */
function ewww_image_optimizer_display_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div class='wrap'>\n";
	echo "<h1 id='ewwwio-tools-header'>EWWW Image Optimizer</h1>\n";

	// Find out if the auxiliary image table has anything in it.
	$already_optimized = ewww_image_optimizer_aux_images_table_count();
	if ( ! $already_optimized ) {
		esc_html_e( 'Nothing has been optimized yet!', 'ewww-image-optimizer' );
		echo "</div>\n";
		return;
	}

	echo "<div id='ewww-aux-forms'>\n";
	echo "<p id='ewww-table-info' class='ewww-tool-info'>" .
		/* translators: %s: number of images */
		sprintf( esc_html__( 'The plugin keeps track of already optimized images to prevent re-optimization. There are %s images that have been optimized so far.', 'ewww-image-optimizer' ), esc_html( number_format_i18n( $already_optimized ) ) ) .
		"</p>\n";
	echo "<form id='ewww-show-table' class='ewww-tool-form' method='post' action=''>\n" .
		'<button type="submit" class="button-primary action">' . esc_html__( 'Show Optimized Images', 'ewww-image-optimizer' ) . "</button>\n" .
		"</form>\n";

	ewwwio_table_nav_controls( 'top' );
	echo '<div id="ewww-bulk-table" class="ewww-aux-table"></div>';
	ewwwio_table_nav_controls( 'bottom' );
	echo "\n</div>\n";

	$queue_status = __( 'idle', 'ewww-image-optimizer' );
	if ( ewwwio()->background_media->is_process_running() ) {
		$queue_status = __( 'running', 'ewww-image-optimizer' );
	}
	if ( ewwwio()->background_image->is_process_running() ) {
		$queue_status = __( 'running', 'ewww-image-optimizer' );
	}

	echo '<hr class="ewww-tool-divider">';
	$queue_count  = ewwwio()->background_media->count_queue();
	$queue_count += ewwwio()->background_image->count_queue();
	/* translators: %s: idle/running */
	echo "<p id='ewww-queue-info' class='ewww-tool-info'>" . sprintf( esc_html__( 'Current queue status: %s', 'ewww-image-optimizer' ), esc_html( $queue_status ) ) . "<br>\n";
	if ( $queue_count ) {
		/* translators: %d: number of images */
		echo sprintf( esc_html__( 'There are %d images in the queue currently.', 'ewww-image-optimizer' ), (int) $queue_count ) . "</p>\n";
		$nonce = wp_create_nonce( 'ewww_image_optimizer_clear_queue' );
		echo "<form id='ewww-clear-queue' class='ewww-tool-form' method='post' action=''>\n" .
			"<input type='hidden' id='ewww_nonce' name='ewww_nonce' value='" . esc_attr( $nonce ) . "'>" .
			"<input type='hidden' name='action' value='ewww_image_optimizer_clear_queue'>" .
			'<button type="submit" class="button-secondary action">' . esc_html__( 'Clear Queue', 'ewww-image-optimizer' ) . "</button>\n" .
			"</form>\n";
	} else {
		echo esc_html__( 'There are no images in the queue currently.', 'ewww-image-optimizer' ) . "</p>\n";
	}

	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-clear-table-info' class='ewww-tool-info'>" .
		esc_html__( 'The optimization history prevents the plugin from re-optimizing images, but you may erase the history to reduce database size or to force the plugin to re-optimize all images.', 'ewww-image-optimizer' );
	echo "</p>\n";
	echo "<form id='ewww-clear-table' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Erase Optimization History', 'ewww-image-optimizer' ) . "' />\n" .
		"</form>\n</div>\n";

	$backup_mode = '';
	if ( 'local' === ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		$backup_mode = __( 'local', 'ewww-image-optimizer' );
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		$backup_mode = __( 'cloud', 'ewww-image-optimizer' );
	}
	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-restore-originals-info' class='ewww-tool-info'>";
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		/* translators: %s: 'cloud' or 'local', translated separately */
		printf( esc_html__( 'Restore all your images from %s backups in case of image corruption or degraded quality.', 'ewww-image-optimizer' ), esc_html( $backup_mode ) );
		if ( ! get_option( 'ewww_image_optimizer_bulk_restore_position' ) ) {
			echo '<br>';
			esc_html_e( '*As such things are quite rare, it is highly recommended to contact support first, as this may be due to a plugin conflict.', 'ewww-image-optimizer' );
		}
	} else {
		esc_html_e( 'Backups are currently disabled in the Local settings.', 'ewww-image-optimizer' );
	}
	echo "</p>\n";
	echo "<form id='ewww-restore-originals' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Restore Images', 'ewww-image-optimizer' ) . "' " . disabled( (bool) ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ), false, false ) . " />\n" .
		"</form>\n";
	if ( get_option( 'ewww_image_optimizer_bulk_restore_position' ) ) {
		?>
		<p class="description ewww-tool-info">
			<i><?php esc_html_e( 'Will resume from previous position.', 'ewww-image-optimizer' ); ?></i> -
			<a  href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_reset_bulk_restore' ), 'ewww-image-optimizer-tools' ) ); ?>'>
				<?php esc_html_e( 'Reset position', 'ewww-image-optimizer' ); ?>
			</a>
		</p>
		<?php
	}
	echo "</div>\n";
	echo "<div id='ewww-restore-originals-progressbar' style='display:none;'></div>";
	echo "<div id='ewww-restore-originals-progress' style='display:none;'></div>";
	echo "<div id='ewww-restore-originals-messages' style='display:none;'></div>";

	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-clean-originals-info' class='ewww-tool-info'>" .
		esc_html__( 'When WordPress scales down large images, it keeps the original on disk for thumbnail generation. You may delete them to save disk space.', 'ewww-image-optimizer' ) . "</p>\n";
	echo "<form id='ewww-clean-originals' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Delete Originals', 'ewww-image-optimizer' ) . "' />\n" .
		"</form>\n</div>\n";
	echo "<div id='ewww-clean-originals-action' style='display:none;'><p>" . esc_html__( 'Searching for originals to remove...', 'ewww-image-optimizer' ) . '</p></div>';
	echo "<div id='ewww-clean-originals-progressbar' style='display:none;'></div>";
	echo "<div id='ewww-clean-originals-progress' style='display:none;'></div>";
	echo "<div id='ewww-clean-originals-messages' style='display:none;'><p></p></div>";

	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-clean-converted-info' class='ewww-tool-info'>" .
		esc_html__( 'If you have converted images (PNG to JPG and friends) without deleting the originals, you may remove them when ready.', 'ewww-image-optimizer' ) . "<br>\n" .
		'<i>' . esc_html__( 'Please perform a site backup before proceeding.', 'ewww-image-optimizer' ) . "</i></p>\n";
	echo "<form id='ewww-clean-converted' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Remove Converted Originals', 'ewww-image-optimizer' ) . "' />\n" .
		"</form>\n</div>\n";
	echo "<div id='ewww-clean-converted-progressbar' style='display:none;'></div>";
	echo "<div id='ewww-clean-converted-progress' style='display:none;'></div>";
	echo "<div id='ewww-clean-converted-messages' style='display:none;'></div>";

	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-clean-webp-info' class='ewww-tool-info'>" .
		esc_html__( 'You may remove all the WebP images from your site if you no longer need them. For example, sites that use Easy IO do not need local WebP images.', 'ewww-image-optimizer' ) . "</p>\n";
	echo "<form id='ewww-clean-webp' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Remove WebP Images', 'ewww-image-optimizer' ) . "' />\n" .
		"</form>\n";
	if ( get_option( 'ewww_image_optimizer_webp_clean_position' ) ) {
		?>
		<p class="description ewww-tool-info">
			<i><?php esc_html_e( 'Will resume from previous position.', 'ewww-image-optimizer' ); ?></i> -
			<a  href='<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=ewww_image_optimizer_reset_webp_clean' ), 'ewww-image-optimizer-tools' ) ); ?>'>
				<?php esc_html_e( 'Reset position', 'ewww-image-optimizer' ); ?>
			</a>
		</p>
		<?php
	}
	?>
	</div>
	<div id='ewww-clean-webp-progressbar' style='display:none;'></div>
	<div id='ewww-clean-webp-details'>
		<div id='ewww-clean-webp-progress' style='display:none;'></div>
		<div id='ewww-clean-webp-removed' style='display:none;'>
			<?php
			printf(
				/* translators: %s: Number of images (wrapped in a span, to be updated via JS) */
				esc_html__( 'Removed %s WebP images', 'ewww-image-optimizer' ),
				"<span id='ewww-clean-webp-removed-total'>0</span>"
			);
			?>
		</div>
	</div>

	<?php
	$as3cf_remove = false;
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		if ( $as3cf->get_setting( 'serve-from-s3' ) && $as3cf->get_setting( 'remove-local-file' ) ) {
			$as3cf_remove = true;
		}
	}
	if ( ! ewww_image_optimizer_s3_uploads_enabled() && ! function_exists( 'ud_get_stateless_media' ) && ! $as3cf_remove ) {
		echo '<hr class="ewww-tool-divider">';
		echo "<div>\n<p id='ewww-clean-table-info' class='ewww-tool-info'>" .
			esc_html__( 'Older sites may have duplicate records or references to deleted files. Use the cleanup tool to remove such records.', 'ewww-image-optimizer' ) . '<br>' .
			'<i>' . esc_html__( 'If you offload your media to external storage like Amazon S3, and remove the local files, do not run this tool.', 'ewww-image-optimizer' ) . "</i></p>\n";
		echo "<form id='ewww-clean-table' class='ewww-tool-form' method='post' action=''>\n" .
			"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Clean Optimization Records', 'ewww-image-optimizer' ) . "' />\n" .
			"</form>\n</div>\n";
		echo "<div id='ewww-clean-table-progressbar' style='display:none;'></div>";
		echo "<div id='ewww-clean-table-progress' style='display:none;'></div>";
	}

	global $wpdb;
	$years_since_meta_migration = (int) gmdate( 'Y' ) - 2017;
	echo '<hr class="ewww-tool-divider">';
	echo "<div>\n<p id='ewww-clean-meta-info' class='ewww-tool-info'>" .
		/* translators: 1: number of years 2: postmeta table name 3: ewwwio_images table name */
		esc_html( sprintf( __( 'Sites using EWWW IO for more than %1$d years may have optimization data that still needs to be migrated between the %2$s and %3$s tables.', 'ewww-image-optimizer' ), $years_since_meta_migration, $wpdb->postmeta, $wpdb->ewwwio_images ) ) . "</p>\n";
	echo "<form id='ewww-clean-meta' class='ewww-tool-form' method='post' action=''>\n" .
		"<input type='submit' class='button-secondary action' value='" . esc_attr__( 'Migrate Optimization Records', 'ewww-image-optimizer' ) . "' />\n" .
		"</form>\n</div>\n";
	echo "<div id='ewww-clean-meta-progressbar' style='display:none;'></div>";
	echo "<div id='ewww-clean-meta-progress' style='display:none;'></div>";

	echo '<hr class="ewww-tool-divider">';
	echo "<p id='ewww-debug-table-info' class='ewww-tool-info'>" . esc_html__( 'Some plugins have bugs that cause them to re-create thumbnails and trigger re-optimization when the images are modified. Turn on the Debugging option to record trace logs for further investigation.', 'ewww-image-optimizer' ) . "</p>\n";
	echo "<form id='ewww-show-debug-table' class='ewww-tool-form' method='post' action=''>\n" .
		'<button type="submit" class="button-secondary action">' . esc_html__( 'Show Re-optimized Images', 'ewww-image-optimizer' ) . "</button>\n" .
		"</form>\n";

	echo "</div>\n";
}

/**
 * Outputs the navigation controls for the optimized images table.
 *
 * @param string $location The placement of the controls relative to the table, either 'top' or 'bottom'.
 */
function ewwwio_table_nav_controls( $location = 'top' ) {
	?>
	<div class='ewww-tablenav ewww-aux-table ewwwio-flex-space-between' style='display:none'>
		<form class="ewww-search-form">
			<label for="ewww-search-input-<?php echo esc_attr( $location ); ?>" class="screen-reader-text"><?php esc_html_e( 'Search', 'ewww-image-optimizer' ); ?></label>
			<input id="ewww-search-input-<?php echo esc_attr( $location ); ?>" type="search" class="ewww-search-input search" name="ewww-search-input" value="">
			<input type="submit" class="ewww-search-submit button" value="<?php esc_attr_e( 'Search', 'ewww-image-optimizer' ); ?>">
			&emsp;<span class="ewww-search-count"></span>
		</form>
	<?php if ( 'top' === $location ) : ?>
		<div class="ewww-search-controls">
			<a id="ewww-search-pending" class="button button-secondary" style="display:none;"><?php esc_html_e( 'View Queued Images', 'ewww-image-optimizer' ); ?></a>
			<a id="ewww-search-optimized" class="button button-secondary" style="display: none;"><?php esc_html_e( 'View Optimized Images', 'ewww-image-optimizer' ); ?></a>
		</div>
	<?php endif; ?>
		<div class="ewww-tablenav-pages ewww-aux-table">
			<div class="displaying-num ewww-aux-table"></div>
			<div class="pagination-links ewww-aux-table">
				<a class="tablenav-pages-navspan button first-page disabled">&laquo;</a>
				<a class="tablenav-pages-navspan button prev-page disabled">&lsaquo;</a>
				<?php /* translators: 1: current page in list of images 2: total pages for list of images */ ?>
				<div class="current-page"><?php printf( esc_html__( 'page %1$d of %2$d', 'ewww-image-optimizer' ), 1, 0 ); ?></div>
				<a class="tablenav-pages-navspan button next-page disabled">&rsaquo;</a>
				<a class="tablenav-pages-navspan button last-page disabled">&raquo;</a>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Prepares the javascript functions for the tools page.
 *
 * @param string $hook The Hook suffix of the calling page.
 */
function ewww_image_optimizer_tool_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the bulk optimization page.
	if ( 'tools_page_ewww-image-optimizer-tools' !== $hook ) {
		return;
	}
	add_filter( 'admin_footer_text', 'ewww_image_optimizer_footer_review_text' );
	wp_enqueue_script( 'ewww-tool-script', plugins_url( '/includes/eio-tools.js', __FILE__ ), array( 'jquery', 'jquery-ui-progressbar' ), EWWW_IMAGE_OPTIMIZER_VERSION, true );
	// Number of images in the ewwwio_table (previously optimized images).
	$image_count = ewww_image_optimizer_aux_images_table_count();
	// Submit a couple variables for our javascript to work with.
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	$erase_warning = esc_html__( 'Warning: this cannot be undone and will cause a bulk optimize to re-optimize all images.', 'ewww-image-optimizer' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$erase_warning = esc_html__( 'Warning: this cannot be undone. Re-optimizing images will use additional API credits.', 'ewww-image-optimizer' );
	}
	global $wpdb;
	$attachment_count  = (int) $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_type = 'attachment' AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') ORDER BY ID DESC" );
	$restore_position  = (int) get_option( 'ewww_image_optimizer_bulk_restore_position' );
	$restorable_images = (int) $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0", $restore_position ) );
	$webp_clean_resume = get_option( 'ewww_image_optimizer_webp_clean_position' );
	$webp_position     = is_array( $webp_clean_resume ) && ! empty( $webp_clean_resume['stage2'] ) ? (int) $webp_clean_resume['stage2'] : 0;
	$webp_cleanable    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM $wpdb->ewwwio_images WHERE id > %d AND pending = 0 AND image_size > 0 AND updates > 0", $webp_position ) );

	wp_localize_script(
		'ewww-tool-script',
		'ewww_vars',
		array(
			'_wpnonce'          => wp_create_nonce( 'ewww-image-optimizer-tools' ),
			'image_count'       => $image_count,
			'attachment_count'  => $attachment_count,
			/* translators: %d: number of attachments from Media Library */
			'attachment_string' => sprintf( esc_html__( '%d attachments', 'ewww-image-optimizer' ), $attachment_count ),
			/* translators: %d: number of images */
			'count_string'      => sprintf( esc_html__( '%d total images', 'ewww-image-optimizer' ), $image_count ),
			'remove_failed'     => esc_html__( 'Could not remove image from table.', 'ewww-image-optimizer' ),
			'invalid_response'  => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ),
			'original_restored' => esc_html__( 'Original Restored', 'ewww-image-optimizer' ),
			'restoring'         => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
			'finished'          => '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>',
			'stage1'            => esc_html__( 'Stage 1:', 'ewww-image-optimizer' ),
			'stage2'            => esc_html__( 'Stage 2:', 'ewww-image-optimizer' ),
			/* translators: used for Table Cleanup progress bar, like so: batch 32/346 */
			'batch'             => esc_html__( 'batch', 'ewww-image-optimizer' ),
			'erase_warning'     => $erase_warning,
			'tool_warning'      => esc_html__( 'Please be sure to backup your site before proceeding. Do you wish to continue?', 'ewww-image-optimizer' ),
			'too_far'           => esc_html__( 'More images have been processed than expected. Unless you have added new images, you should refresh the page to stop the process and contact support.', 'ewww-image-optimizer' ),
			'network_error'     => esc_html__( 'A network or server error has occurred, retrying automatically in 15 seconds.', 'ewww-image-optimizer' ),
			'restorable_images' => $restorable_images,
			'webp_cleanable'    => $webp_cleanable,
		)
	);
	// Load the stylesheet for the jquery progressbar.
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ) );
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Presents the bulk optimize form.
 */
function ewww_image_optimizer_bulk_preview() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_version_info();
	// Retrieve the attachment IDs that were pre-loaded in the database.
	echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Bulk Optimize', 'ewww-image-optimizer' ) . '</h1>';
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ) {
		echo '<div class="error"><p>';
		esc_html_e( 'Please disable Scheduled optimization before continuing.', 'ewww-image-optimizer' );
		echo '</p></div></div>';
		return;
	}

	$async_preempt = false;
	if ( 'scanning' === get_option( 'ewww_image_optimizer_bulk_resume' ) || 'scanning' === get_option( 'ewww_image_optimizer_aux_resume' ) ) {
		$async_preempt = true;
	}
	if ( get_option( 'ewww_image_optimizer_pause_queues' ) || get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
		if ( ewwwio()->background_media->count_queue() || ewwwio()->background_image->count_queue() ) {
			$async_preempt = true;
		}
	}
	if ( $async_preempt ) {
		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: %s: settings page */
			esc_html__( 'Images are already queued for optimization, please check the %s for more details.', 'ewww-image-optimizer' ),
			'<a href="' . esc_url( ewww_image_optimizer_get_settings_link() ) . '">' . esc_html__( 'settings page', 'ewww-image-optimizer' ) . '</a>'
		);
		echo '</p></div></div>';
		return;
	}

	if (
		! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		ewww_image_optimizer_easy_active()
	) {
		?>
		<div id="ewww-bulk-warning" class="ewww-bulk-info notice notice-warning">
			<p>
				<?php esc_html_e( 'Easy IO is automatically optimizing your site! Bulk Optimization of local images is not necessary unless you wish to save storage space. Please be sure you have a backup of your images before proceeding.', 'ewww-image-optimizer' ); ?>
				<?php ewww_image_optimizer_bulk_resize_warning_message(); ?>
			</p>
		</div>
		<?php
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		?>
		<div id="ewww-bulk-warning" class="ewww-bulk-info notice notice-warning">
			<p>
				<?php esc_html_e( 'Bulk Optimization will alter your original images and cannot be undone. Please be sure you have a backup of your images before proceeding.', 'ewww-image-optimizer' ); ?>
				<?php ewww_image_optimizer_bulk_resize_warning_message(); ?>
			</p>
		</div>
		<?php
	}
	// Retrieve the value of the 'bulk resume' option and set the button text for the form to use.
	$resume = get_option( 'ewww_image_optimizer_bulk_resume' );
	if ( empty( $resume ) ) {
		$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );
		$button_text    = esc_attr__( 'Start optimizing', 'ewww-image-optimizer' );
	} elseif ( 'scanning' === $resume ) {
		$fullsize_count = ewww_image_optimizer_count_optimized( 'media' );
		$button_text    = esc_attr__( 'Start optimizing', 'ewww-image-optimizer' );
	} else {
		$fullsize_count = ewww_image_optimizer_aux_images_table_count_pending();
		$button_text    = esc_attr__( 'Resume previous optimization', 'ewww-image-optimizer' );
	}
	// Check that quota is reset after purchasing more credits.
	ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
	// Create the html for the bulk optimize form and status divs.
	ewww_image_optimizer_bulk_head_output();
	echo '<div id="ewww-bulk-forms">';
	if ( $fullsize_count < 1 ) {
		echo '<p>' . esc_html__( 'You do not appear to have uploaded any images yet.', 'ewww-image-optimizer' ) . '</p>';
	} else {
		if ( 'true' === $resume ) {
			echo '<p class="ewww-media-info ewww-bulk-info">' . esc_html__( 'Resume where you left off:', 'ewww-image-optimizer' ) . '</p>';
		} else {
			$resizes = ewww_image_optimizer_get_image_sizes();
			if ( is_array( $resizes ) ) {
				$resize_count = count( $resizes );
			}
			$resize_count = ( ! empty( $resize_count ) && $resize_count > 1 ? $resize_count : 6 );
			if ( ! empty( $_REQUEST['ids'] ) && ( preg_match( '/^[\d,]+$/', sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) ) || is_numeric( sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				echo '<p class="ewww-media-info ewww-bulk-info">' .
					/* translators: 1: number of images 2: number of registered image sizes */
					esc_html( sprintf( _n( '%1$s uploaded item in the Media Library has been selected with up to %2$d image files per upload.', '%1$s uploaded items in the Media Library have been selected with up to %2$d image files per upload.', $fullsize_count, 'ewww-image-optimizer' ), number_format_i18n( $fullsize_count ), $resize_count ) ) .
					' ' . esc_html__( 'The total number of images found will be displayed before optimization begins.', 'ewww-image-optimizer' ) .
					'</p>';
			} else {
				echo '<p class="ewww-media-info ewww-bulk-info">' .
					/* translators: 1: number of images 2: number of registered image sizes */
					esc_html( sprintf( _n( '%1$s uploaded item in the Media Library has been selected with up to %2$d image files per upload.', '%1$s uploaded items in the Media Library have been selected with up to %2$d image files per upload.', $fullsize_count, 'ewww-image-optimizer' ), number_format_i18n( $fullsize_count ), $resize_count ) ) .
					' ' . esc_html__( 'The total number of images found will be displayed before optimization begins.', 'ewww-image-optimizer' ) .
					'<br />' .
					esc_html__( 'The active theme, BuddyPress, WP Symposium, and folders that you have configured will also be scanned for unoptimized images.', 'ewww-image-optimizer' ) .
					'</p>';
			}
		}
		ewww_image_optimizer_bulk_action_output( $button_text, $fullsize_count, $resume );
	}
	// If the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning.
	if ( 'true' === $resume ) {
		ewww_image_optimizer_bulk_reset_form_output();
	}
	echo '</div>';
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_aux_images();
}

/**
 * Make sure folks know their images will be resized during bulk optimization.
 */
function ewww_image_optimizer_bulk_resize_warning_message() {
	if (
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) )
	) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_other_existing' ) ) {
			printf(
				/* translators: 1: width in pixels, 2: height in pixels */
				esc_html__( 'All images will be scaled to %1$d x %2$d.', 'ewww-image-optimizer' ),
				(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ),
				(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' )
			);
		} else {
			printf(
				/* translators: 1: width in pixels, 2: height in pixels */
				esc_html__( 'All images in the Media Library will be scaled to %1$d x %2$d.', 'ewww-image-optimizer' ),
				(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ),
				(int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' )
			);
		}
	}
}

/**
 * Outputs the status area and delay/force controls for the Bulk optimize page.
 */
function ewww_image_optimizer_bulk_head_output() {
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	$delay         = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) ? (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) : 0;
	?>
		<div id="ewww-bulk-loading">
			<p id="ewww-loading" class="ewww-bulk-info" style="display:none"><?php esc_html_e( 'Importing', 'ewww-image-optimizer' ); ?>&nbsp;<img src='<?php echo esc_url( $loading_image ); ?>' /></p>
		</div>
		<div id="ewww-bulk-progressbar"></div>
		<div id="ewww-bulk-timer" style="float:right;"></div>
		<div id="ewww-bulk-counter"></div>
		<form id="ewww-bulk-stop" style="display:none;" method="post" action="">
			<br /><input type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Stop Optimizing', 'ewww-image-optimizer' ); ?>" />
		</form>
		<div id="ewww-bulk-widgets" class="metabox-holder" style="display:none">
			<div class="meta-box-sortables">
				<div id="ewww-bulk-last" class="postbox">
					<button type="button" class="ewww-handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="false"></span>
					</button>
					<h2 class="ewww-hndle"><span><?php esc_html_e( 'Last Batch Optimized', 'ewww-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
			<div class="meta-box-sortables">
				<div id="ewww-bulk-status" class="postbox">
					<button type="button" class="ewww-handlediv button-link" aria-expanded="true">
						<span class="screen-reader-text"><?php esc_html_e( 'Click to toggle', 'ewww-image-optimizer' ); ?></span>
						<span class="toggle-indicator" aria-hidden="false"></span>
					</button>
					<h2 class="ewww-hndle"><span><?php esc_html_e( 'Optimization Log', 'ewww-image-optimizer' ); ?></span></h2>
					<div class="inside"></div>
				</div>
			</div>
		</div>
		<form id="ewww-bulk-controls" class="ewww-bulk-form">
			<p><label for="ewww-force" style="font-weight: bold"><?php esc_html_e( 'Force re-optimize', 'ewww-image-optimizer' ); ?></label><?php ewwwio_help_link( 'https://docs.ewww.io/article/65-force-re-optimization', '5bb640a7042863158cc711cd' ); ?>
				&emsp;<input type="checkbox" id="ewww-force" name="ewww-force"<?php echo ( get_transient( 'ewww_image_optimizer_force_reopt' ) || ! empty( ewwwio()->force ) ) ? ' checked' : ''; ?>>
				&nbsp;<?php esc_html_e( 'Previously optimized images will be skipped by default, check this box before scanning to override.', 'ewww-image-optimizer' ); ?>
				&nbsp;<a href="tools.php?page=ewww-image-optimizer-tools"><?php esc_html_e( 'View optimization history.', 'ewww-image-optimizer' ); ?></a>
			</p>
			<?php ewww_image_optimizer_bulk_variant_option(); ?>
			<?php ewww_image_optimizer_bulk_webp_only(); ?>
			<p>
				<label for="ewww-delay" style="font-weight: bold"><?php esc_html_e( 'Pause between images', 'ewww-image-optimizer' ); ?></label>&emsp;<input type="text" id="ewww-delay" name="ewww-delay" value="<?php echo (int) $delay; ?>"> <?php esc_html_e( 'in seconds', 'ewww-image-optimizer' ); ?>
			</p>
			<div id="ewww-delay-slider"></div>
		</form>
	<?php
}

/**
 * Output the control to compress images with varied compression levels.
 */
function ewww_image_optimizer_bulk_variant_option() {
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ) {
		return;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		return;
	}
	?>
			<p><label for="ewww-force-smart" style="font-weight: bold"><?php esc_html_e( 'Smart Re-optimize', 'ewww-image-optimizer' ); ?></label>
				&emsp;<input type="checkbox" id="ewww-force-smart" name="ewww-force-smart"<?php echo ( get_transient( 'ewww_image_optimizer_smart_reopt' ) || ! empty( ewwwio()->force_smart ) ) ? ' checked' : ''; ?>>
				&nbsp;<?php esc_html_e( 'If compression settings have changed, re-optimize images that were compressed on the old settings. If possible, images compressed in Premium mode will be restored to originals beforehand.', 'ewww-image-optimizer' ); ?>
			</p>
	<?php
}

/**
 * Output the control for WebP Only on the Bulk page.
 */
function ewww_image_optimizer_bulk_scan_only() {
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_ludicrous_mode' ) ) {
		return;
	}
	?>
			<p><label for="ewww-scan-only" style="font-weight: bold"><?php esc_html_e( 'Scan Only', 'ewww-image-optimizer' ); ?></label>
				&emsp;<input type="checkbox" id="ewww-scan-only" name="ewww-scan-only">
				&nbsp;<?php esc_html_e( 'Search for images to optimize and add them to the queue, but do not optimize any images yet.', 'ewww-image-optimizer' ); ?>
			</p>
	<?php
}

/**
 * Output the control for WebP Only on the Bulk page.
 */
function ewww_image_optimizer_bulk_webp_only() {
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		return;
	}
	?>
			<p><label for="ewww-webp-only" style="font-weight: bold"><?php esc_html_e( 'WebP Only', 'ewww-image-optimizer' ); ?></label>
				&emsp;<input type="checkbox" id="ewww-webp-only" name="ewww-webp-only"<?php echo ( ! empty( ewwwio()->webp_only ) ) ? ' checked' : ''; ?>>
				&nbsp;<?php esc_html_e( 'Skip compression and only attempt WebP conversion.', 'ewww-image-optimizer' ); ?>
			</p>
	<?php
}

/**
 * Outputs the buttons and scanner status html for the Bulk optimize page.
 *
 * @param string $button_text Value for the button that starts the optimization (after scanning).
 * @param int    $fullsize_count The total number of images that need to be scanned.
 * @param string $resume Optional. If a bulk operation was interrupted, indicates in which phase it
 *                                 was operating. Accepts 'true', 'scanning', or ''.
 */
function ewww_image_optimizer_bulk_action_output( $button_text, $fullsize_count, $resume = '' ) {
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	/* translators: %s: number of images */
	$scanning_starter_message = sprintf( __( 'Stage 1, %s items left to scan.', 'ewww-image-optimizer' ), number_format_i18n( $fullsize_count ) );
	if ( 'true' === $resume ) {
		/* translators: %s: number of images, formatted for locale */
		$button_text = sprintf( __( 'Optimize %s images', 'ewww-image-optimizer' ), number_format_i18n( $fullsize_count ) );
		$scan_hide   = 'style="display:none"';
		$start_hide  = '';
	} else {
		$start_hide = 'style="display:none"';
		$scan_hide  = '';

	}
	?>
	<p id="ewww-nothing" class="ewww-bulk-info" style="display:none"><?php echo esc_html_e( 'There are no images to optimize.', 'ewww-image-optimizer' ); ?></p>
	<p id="ewww-scanning" class="ewww-bulk-info" style="display:none"><?php echo esc_html( $scanning_starter_message ); ?>&nbsp;<img src='<?php echo esc_url( $loading_image ); ?>' alt='loading'/></p>
	<form id="ewww-aux-start" class="ewww-bulk-form" <?php echo ( 'true' === $resume ? 'style="display:none"' : '' ); ?> method="post" action="">
		<input id="ewww-aux-first" type="submit" class="button-primary action" value="<?php esc_attr_e( 'Scan for unoptimized images', 'ewww-image-optimizer' ); ?>" />
	</form>
	<form id="ewww-bulk-start" class="ewww-bulk-form" <?php echo ( 'true' === $resume ? '' : 'style="display:none"' ); ?> method="post" action="">
		<input id="ewww-bulk-first" type="submit" class="button-primary action" value="<?php echo esc_attr( $button_text ); ?>" />
	</form>
	<?php
}

/**
 * Outputs the Reset form on the Bulk optimize page.
 */
function ewww_image_optimizer_bulk_reset_form_output() {
	?>
		<p class="ewww-media-info ewww-bulk-info" style="margin-top:3em;"><?php esc_html_e( 'Would you like to clear the queue and rescan for images?', 'ewww-image-optimizer' ); ?></p>
		<form class="ewww-bulk-form" method="post" action="">
			<?php wp_nonce_field( 'ewww-image-optimizer-bulk-reset', 'ewww_wpnonce' ); ?>
			<input type="hidden" name="ewww_reset" value="1">
			<button id="ewww-bulk-reset" type="submit" class="button-secondary action"><?php esc_html_e( 'Clear Queue', 'ewww-image-optimizer' ); ?></button>
		</form>
	<?php
}

/**
 * Detect the current memory limit and reduce the query limit appropriately.
 *
 * @param int $max_query The default number of records to query in large batches.
 * @return int The adjusted level based on the memory limit
 */
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

/**
 * Clear the image queues and shutdown background processing.
 */
function ewww_image_optimizer_clear_queue() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	check_admin_referer( 'ewww_image_optimizer_clear_queue', 'ewww_nonce' );

	update_option( 'ewww_image_optimizer_pause_queues', false, false );
	update_option( 'ewww_image_optimizer_pause_image_queue', false, false );
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	update_option( 'ewww_image_optimizer_scan_args', '' );

	ewwwio()->background_media->cancel_process();
	ewwwio()->background_image->cancel_process();
	ewww_image_optimizer_delete_pending();
	update_option( 'ewwwio_stop_scheduled_scan', true, false );
	sleep( 5 ); // Give the queues a little time to complete in-process items.
	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Pause the image queues until further notice.
 */
function ewww_image_optimizer_pause_queue() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	check_admin_referer( 'ewww_image_optimizer_clear_queue', 'ewww_nonce' );

	update_option( 'ewww_image_optimizer_pause_queues', true, false );
	update_option( 'ewwwio_stop_scheduled_scan', true, false );

	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Resumes async queues.
 */
function ewww_image_optimizer_resume_queue() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	check_admin_referer( 'ewww_image_optimizer_clear_queue', 'ewww_nonce' );

	update_option( 'ewww_image_optimizer_pause_queues', false, false );
	update_option( 'ewww_image_optimizer_pause_image_queue', false, false );
	delete_option( 'ewwwio_stop_scheduled_scan' );

	if ( ! ewwwio()->background_media->is_process_running() ) {
		ewwwio_debug_message( 'media process idle, dispatching post-haste' );
		ewwwio()->background_media->dispatch();
	}

	if ( ! ewwwio()->background_image->is_process_running() ) {
		ewwwio_debug_message( 'media process idle, dispatching post-haste' );
		ewwwio()->background_image->dispatch();
	}

	wp_safe_redirect( wp_get_referer() );
	exit;
}

/**
 * Retrieve image counts for the bulk process.
 *
 * For the media library, returns a simple count of the number of attachments. For other galleries,
 * counts the number of thumbnails/resizes along with how many of each need to be optimized. Uses
 * attachment "metadata" to calculate the counts, which will not be accurate for long.
 *
 * @param string $gallery Bulk page that is calling the function. Accepts 'media', 'ngg', and 'flag'.
 * @return int|array {
 *     The image count(s) found during the search.
 *
 *     @type int $full_count The number of original uploads found.
 *     @type int $resize_count The number of thumbnails/resizes found.
 * }
 */
function ewww_image_optimizer_count_optimized( $gallery ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "scanning for $gallery" );
	global $wpdb;
	$full_count       = 0;
	$resize_count     = 0;
	$attachment_query = '';
	$started          = microtime( true ); // Retrieve the time when the counting starts.
	$max_query        = (int) apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );
	/**
	 * Set a maximum for a query, 1k less than WPE's 16k limit, just to be safe.
	 *
	 * @param int 15000 The maximum query length.
	 */
	$max_query_length       = apply_filters( 'ewww_image_optimizer_max_query_length', 15000 );
	$attachment_query_count = 0;
	switch ( $gallery ) {
		case 'media':
			return ewww_image_optimizer_count_attachments();
			break;
		case 'ngg':
			// See if we were given attachment IDs to work with via GET/POST.
			if ( ! empty( $_REQUEST['doaction'] ) || get_option( 'ewww_image_optimizer_bulk_ngg_resume' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				// Retrieve the attachment IDs that were pre-loaded in the database.
				$attachment_ids = get_option( 'ewww_image_optimizer_bulk_ngg_attachments' );
				array_walk( $attachment_ids, 'intval' );
				while ( $attachment_ids && strlen( $attachment_query ) < $max_query_length ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					++$attachment_query_count;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				$max_query        = $attachment_query_count;
			}
			// Get an array of sizes available for the $image.
			global $ewwwngg;
			$sizes       = $ewwwngg->get_image_sizes();
			$offset      = 0;
			$attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->nggpictures $attachment_query LIMIT $offset, $max_query" ); // phpcs:ignore WordPress.DB.PreparedSQL
			while ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					if ( class_exists( 'Ngg_Serializable' ) ) {
						$serializer = new Ngg_Serializable();
						$meta       = $serializer->unserialize( $attachment );
					} elseif ( class_exists( 'C_NextGen_Serializable' ) ) {
						$meta = C_NextGen_Serializable::unserialize( $attachment );
					} else {
						$meta = unserialize( $attachment );
					}
					if ( ! is_array( $meta ) ) {
						continue;
					}
					$ngg_sizes = $ewwwngg->maybe_get_more_sizes( $sizes, $meta );
					if ( ewww_image_optimizer_iterable( $ngg_sizes ) ) {
						foreach ( $ngg_sizes as $size ) {
							if ( 'full' !== $size ) {
								++$resize_count;
							}
						}
					}
				}
				$full_count += count( $attachments );
				$offset     += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query       = '';
					$attachment_query_count = 0;
					$offset                 = 0;
					while ( $attachment_ids && strlen( $attachment_query ) < $max_query_length ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						++$attachment_query_count;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
					$max_query        = $attachment_query_count;
				}
				$attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->nggpictures $attachment_query LIMIT $offset, $max_query" ); // phpcs:ignore WordPress.DB.PreparedSQL
			} // End while().
			break;
		case 'flag':
			if ( ! empty( $_REQUEST['doaction'] ) || get_option( 'ewww_image_optimizer_bulk_flag_resume' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				// Retrieve the attachment IDs that were pre-loaded in the database.
				$attachment_ids = get_option( 'ewww_image_optimizer_bulk_flag_attachments' );
				array_walk( $attachment_ids, 'intval' );
				while ( $attachment_ids && strlen( $attachment_query ) < $max_query_length ) {
					$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
					++$attachment_query_count;
				}
				$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
				$max_query        = $attachment_query_count;
			}
			$offset      = 0;
			$attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->flagpictures $attachment_query LIMIT $offset, $max_query" ); // phpcs:ignore WordPress.DB.PreparedSQL
			while ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					$meta = unserialize( $attachment );
					if ( ! is_array( $meta ) ) {
						continue;
					}
					if ( ! empty( $meta['webview'] ) ) {
						++$resize_count;
					}
					if ( ! empty( $meta['thumbnail'] ) ) {
						++$resize_count;
					}
				}
				$full_count += count( $attachments );
				$offset     += $max_query;
				if ( ! empty( $attachment_ids ) ) {
					$attachment_query       = '';
					$attachment_query_count = 0;
					$offset                 = 0;
					while ( $attachment_ids && strlen( $attachment_query ) < $max_query_length ) {
						$attachment_query .= "'" . array_pop( $attachment_ids ) . "',";
						++$attachment_query_count;
					}
					$attachment_query = 'WHERE pid IN (' . substr( $attachment_query, 0, -1 ) . ')';
					$max_query        = $attachment_query_count;
				}
				$attachments = $wpdb->get_col( "SELECT meta_data FROM $wpdb->flagpictures $attachment_query LIMIT $offset, $max_query" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
			break;
	} // End switch().
	if ( empty( $full_count ) && ! empty( $attachment_ids ) ) {
		ewwwio_debug_message( 'query appears to have failed, just counting total images instead' );
		$full_count = count( $attachment_ids );
	}
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "counting images took $elapsed seconds" );
	ewwwio_debug_message( "found $full_count fullsize and $resize_count resizes" );
	ewwwio_memory( __FUNCTION__ );
	return array( $full_count, $resize_count );
}

/**
 * Prepares the bulk operation and includes the javascript functions.
 *
 * Checks to see if a scan was in progress, or if attachment IDs were POSTed, and loads the
 * appropriate attachments into the list to be scanned. Also sets up the js includes, and
 * defines a few js variables needed for the bulk operation.
 *
 * @global object $wpdb
 *
 * @param string $hook An indicator if this was not called from AJAX, like WP-CLI.
 */
function ewww_image_optimizer_bulk_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the bulk optimization page.
	if ( 'media_page_ewww-image-optimizer-bulk' !== $hook ) {
		return;
	}
	add_filter( 'admin_footer_text', 'ewww_image_optimizer_footer_review_text' );
	global $wpdb;
	// Initialize the $attachments variable.
	$attachments = array();
	// Check to see if we are supposed to reset the bulk operation and verify we are authorized to do so.
	if ( ! empty( $_REQUEST['ewww_reset'] ) && ! empty( $_REQUEST['ewww_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk-reset' ) ) {
		ewwwio_debug_message( 'resetting resume flags' );
		// Set the 'bulk resume' option to an empty string to reset the bulk operation.
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		update_option( 'ewww_image_optimizer_aux_resume', '' );

		ewww_image_optimizer_delete_queue_images();
		ewww_image_optimizer_delete_pending();
	}
	// Check to see if we are supposed to convert the auxiliary images table and verify we are authorized to do so.
	if ( ! empty( $_REQUEST['ewww_convert'] ) && ! empty( $_REQUEST['ewww_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-aux-images-convert' ) ) {
		ewww_image_optimizer_aux_images_convert();
	}
	if ( ! empty( $_GET['ewww_webp_only'] ) ) {
		ewwwio()->webp_only = true;
	}
	if ( ! empty( $_GET['ewww_force'] ) ) {
		ewwwio()->force = true;
	}
	// Check the 'bulk resume' option.
	$resume   = get_option( 'ewww_image_optimizer_bulk_resume' );
	$scanning = get_option( 'ewww_image_optimizer_aux_resume' );
	if ( 'scanning' !== $scanning && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) ) {
		$scanning = false;
	}
	if ( get_option( 'ewww_image_optimizer_pause_queues' ) || get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
		if ( ewwwio()->background_media->count_queue() || ewwwio()->background_image->count_queue() ) {
			$resume = true;
		}
	}
	if ( ! $resume && ! $scanning ) {
		ewwwio_debug_message( 'not resuming/scanning, so clearing any pending images in both tables' );
		ewww_image_optimizer_delete_queue_images();
		ewww_image_optimizer_delete_pending();
	}
	// See if we were given attachment IDs to work with via GET/POST.
	$ids = array();
	if ( ! empty( $_REQUEST['ids'] ) && ( preg_match( '/^[\d,]+$/', sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ), $request_ids ) || is_numeric( sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) ) ) ) {
		ewww_image_optimizer_delete_pending();
		set_transient( 'ewww_image_optimizer_skip_aux', true, 3 * MINUTE_IN_SECONDS );
		if ( is_numeric( sanitize_text_field( wp_unslash( $_REQUEST['ids'] ) ) ) ) {
			$ids[] = (int) $_REQUEST['ids'];
		} else {
			$ids = explode( ',', $request_ids[0] );
			array_walk( $ids, 'intval' );
		}
		ewwwio_debug_message( "validating requested ids: {$request_ids[0]}" );
		// Retrieve post IDs correlating to the IDs submitted to make sure they are all valid.
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') AND ID IN ({$request_ids[0]}) ORDER BY ID DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		// Unset the 'bulk resume' option since we were given specific IDs to optimize.
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		// Check if there is a previous bulk operation to resume.
	} elseif ( $scanning || $resume ) {
		ewwwio_debug_message( 'scanning/resuming, nothing doing' );
	} elseif ( empty( $attachments ) ) {
		ewwwio_debug_message( 'load em all up' );
		// Since we aren't resuming, and weren't given a list of IDs, we will optimize everything.
		delete_transient( 'ewww_image_optimizer_scan_aux' );
		// Load up all the image attachments we can find.
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') ORDER BY ID DESC" );
	} // End if().
	if ( ! empty( $attachments ) ) {
		// Store the attachment IDs we retrieved in the queue table so we can keep track of our progress in the database.
		ewwwio_debug_message( 'loading attachments into queue table' );
		ewww_image_optimizer_insert_unscanned( $attachments );
		$attachment_count = count( $attachments );
	} else {
		$attachment_count = ewww_image_optimizer_count_unscanned_attachments();
	}
	if ( empty( $attachment_count ) && ! ewww_image_optimizer_count_attachments() && ! ewww_image_optimizer_aux_images_table_count_pending() ) {
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
		update_option( 'ewww_image_optimizer_aux_resume', '' );
	}
	wp_enqueue_script( 'ewww-beacon-script', plugins_url( '/includes/eio-beacon.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_script( 'ewww-bulk-script', plugins_url( '/includes/eio-bulk.js', __FILE__ ), array( 'jquery', 'jquery-ui-slider', 'jquery-ui-progressbar', 'postbox', 'dashboard' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	// Number of images in the ewwwio_table (previously optimized images).
	$image_count = ewww_image_optimizer_aux_images_table_count();
	// Submit a couple variables for our javascript to work with.
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	wp_localize_script(
		'ewww-bulk-script',
		'ewww_vars',
		array(
			'_wpnonce'              => wp_create_nonce( 'ewww-image-optimizer-bulk' ),
			'attachments'           => ewww_image_optimizer_aux_images_table_count_pending(),
			'image_count'           => $image_count,
			/* translators: %d: number of images */
			'count_string'          => sprintf( esc_html__( '%d images', 'ewww-image-optimizer' ), $image_count ),
			'scan_fail'             => esc_html__( 'Operation timed out, you may need to increase the max_execution_time or memory_limit for PHP', 'ewww-image-optimizer' ),
			'scan_incomplete'       => esc_html__( 'Scan did not complete, will try again', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' />",
			'operation_stopped'     => esc_html__( 'Optimization stopped, reload page to resume.', 'ewww-image-optimizer' ),
			'operation_interrupted' => esc_html__( 'Operation Interrupted', 'ewww-image-optimizer' ),
			'temporary_failure'     => esc_html__( 'Temporary failure, attempts remaining:', 'ewww-image-optimizer' ),
			'invalid_response'      => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ),
			'bad_attachment'        => esc_html__( 'Previous failure due to broken/missing metadata, skipped resizes for attachment:', 'ewww-image-optimizer' ),
			'remove_failed'         => esc_html__( 'Could not remove image from table.', 'ewww-image-optimizer' ),
			/* translators: used for Bulk Optimize progress bar, like so: Optimized 32/346 */
			'optimized'             => esc_html__( 'Optimized', 'ewww-image-optimizer' ),
			'last_image_header'     => esc_html__( 'Last Image Optimized', 'ewww-image-optimizer' ),
			'time_remaining'        => esc_html__( 'remaining', 'ewww-image-optimizer' ),
			'original_restored'     => esc_html__( 'Original Restored', 'ewww-image-optimizer' ),
			'restoring'             => '<p>' . esc_html__( 'Restoring', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>",
			'bulk_fail_more'        => '<a href="https://docs.ewww.io/article/39-bulk-optimizer-failure" target="_blank" data-beacon-article="596f84f72c7d3a73488b3ca7">' . esc_html__( 'more...', 'ewww-image-optimizer' ) . '</a>',
		)
	);
	// Load the stylesheet for the jquery progressbar.
	wp_enqueue_style( 'jquery-ui-progressbar', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Check bulk options and set corresponding globals/transients.
 *
 * @param array $request The POST/GET parameters that are currently set.
 */
function ewww_image_optimizer_check_bulk_options( $request ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make the Force Re-optimize option persistent.
	if ( ! empty( $request['ewww_force'] ) ) {
		ewwwio_debug_message( 'forcing re-optimize: true' );
		ewwwio()->force = true;
		set_transient( 'ewww_image_optimizer_force_reopt', true, HOUR_IN_SECONDS );
	} else {
		ewwwio()->force = false;
		delete_transient( 'ewww_image_optimizer_force_reopt' );
	}
	// Make the Smart Re-optimize option persistent.
	if ( ! empty( $request['ewww_force_smart'] ) ) {
		ewwwio_debug_message( 'forcing (smart) re-optimize: true' );
		ewwwio()->force_smart = true;
		set_transient( 'ewww_image_optimizer_smart_reopt', true, HOUR_IN_SECONDS );
	} else {
		ewwwio()->force_smart = false;
		delete_transient( 'ewww_image_optimizer_smart_reopt' );
	}
	ewwwio()->webp_only = false;
	if ( ! empty( $request['ewww_webp_only'] ) ) {
		ewwwio()->webp_only = true;
	}
	if ( ! empty( $request['ewww_scan_only'] ) ) {
		update_option( 'ewww_image_optimizer_pause_image_queue', true, false );
	} else {
		update_option( 'ewww_image_optimizer_pause_image_queue', false, false );
	}
	if ( isset( $request['ewww_delay'] ) && $request['ewww_delay'] <= 60 ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_delay', (int) $request['ewww_delay'] );
	}
}

/**
 * Called via AJAX to start the (asynchronous) bulk operation.
 *
 * @global object $wpdb
 */
function ewww_image_optimizer_bulk_async_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has made the request.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	$output = array();

	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$verify_cloud = ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
		if ( 'exceeded' === $verify_cloud ) {
			$output['error'] = ewww_image_optimizer_credits_exceeded();
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
		if ( 'exceeded quota' === $verify_cloud ) {
			$output['error'] = ewww_image_optimizer_soft_quota_exceeded();
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
		if ( 'exceeded subkey' === $verify_cloud ) {
			$output['error'] = esc_html__( 'Out of credits', 'ewww-image-optimizer' );
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
	}

	// Update the 'bulk resume' option to show that an operation is in progress.
	update_option( 'ewww_image_optimizer_bulk_resume', 'scanning' );
	delete_option( 'ewwwio_stop_scheduled_scan' );

	ewww_image_optimizer_check_bulk_options( $_REQUEST );

	$scan_args = array(
		'force_reopt' => ewwwio()->force,
		'force_smart' => ewwwio()->force_smart,
		'webp_only'   => ewwwio()->webp_only,
	);
	update_option( 'ewww_image_optimizer_scan_args', $scan_args );

	global $wpdb;
	$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND (post_mime_type LIKE '%%image%%' OR post_mime_type LIKE '%%pdf%%') ORDER BY ID DESC" );
	if ( ! empty( $attachments ) ) {
		ewwwio_debug_message( 'loading attachments into queue table' );
		ewww_image_optimizer_insert_unscanned( $attachments, 'media-async' );
		$attachment_count = count( $attachments );
		ewwwio()->background_media->dispatch();
		/* translators: %s: number of images */
		$output['media_remaining'] = sprintf( esc_html__( '%s media uploads left to scan', 'ewww-image-optimizer' ), number_format_i18n( $attachment_count ) );
	} else {
		$output['media_remaining'] = esc_html__( 'Searching for images to optimize...', 'ewww-image-optimizer' );
		ewwwio_debug_message( 'starting async scan' );
		ewwwio()->async_scan->data(
			array(
				'ewww_scan' => 'scheduled',
			)
		)->dispatch();
		update_option( 'ewww_image_optimizer_bulk_resume', '' );
	}

	ewwwio_ob_clean();
	die( wp_json_encode( $output ) );
}

/**
 * Display the status of the bulk async process.
 */
function ewww_image_optimizer_bulk_async_get_status() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has made the request.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-settings' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	$output = array();

	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
			$output['complete'] = ewww_image_optimizer_credits_exceeded();
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
		if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
			$output['complete'] = ewww_image_optimizer_soft_quota_exceeded();
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
		if ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
			$output['complete'] = esc_html__( 'Out of credits', 'ewww-image-optimizer' );
			ewwwio_ob_clean();
			die( wp_json_encode( $output ) );
		}
	}

	$media_queue_running = false;
	if ( ewwwio()->background_media->is_process_running() ) {
		$media_queue_running = true;
	}
	$image_queue_running = false;
	if ( ewwwio()->background_image->is_process_running() ) {
		$image_queue_running = true;
	}

	$media_queue_count = ewwwio()->background_media->count_queue();
	$image_queue_count = ewwwio()->background_image->count_queue();

	if ( $media_queue_count && ! $media_queue_running ) {
		ewwwio_debug_message( 'rebooting media queue' );
		ewwwio()->background_media->dispatch();
	} elseif ( $image_queue_count && ! $image_queue_running && ! get_option( 'ewww_image_optimizer_pause_image_queue' ) ) {
		ewwwio_debug_message( 'rebooting image queue' );
		ewwwio()->background_image->dispatch();
	} elseif ( 'scanning' === get_option( 'ewww_image_optimizer_aux_resume' ) && ! $media_queue_count ) {
		ewwwio_debug_message( 'rebooting async image scanner' );
		if ( ! get_transient( 'ewww_image_optimizer_aux_lock' ) ) {
			ewwwio_debug_message( 'running scheduled optimization' );
			ewwwio()->async_scan->data(
				array(
					'ewww_scan' => 'scheduled',
				)
			)->dispatch();
		}
	}

	if ( $media_queue_count ) {
		/* translators: %s: number of images/uploads */
		$output['media_remaining'] = sprintf( esc_html__( '%s media uploads left to scan', 'ewww-image-optimizer' ), number_format_i18n( $media_queue_count ) );
	} elseif ( $image_queue_count ) {
		/* translators: %s: number of images */
		$output['images_remaining'] = sprintf( esc_html__( '%s images left to optimize', 'ewww-image-optimizer' ), number_format_i18n( $image_queue_count ) );
	} elseif ( 'scanning' === get_option( 'ewww_image_optimizer_aux_resume' ) ) {
		// We output this as 'media_remaining' because the async scan hasn't run yet, and we don't want the autopoll to quit just yet.
		$output['media_remaining'] = esc_html__( 'Searching for images to optimize...', 'ewww-image-optimizer' );
	} elseif ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
		$output['complete'] = '<div><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b> - ' .
		( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ?
		'<a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post">' .
		esc_html__( 'Write a Review', 'ewww-image-optimizer' ) :
		esc_html__( 'Want more compression?', 'ewww-image-optimizer' ) . ' ' .
		'<a target="_blank" href="https://ewww.io/trial/">' .
		esc_html__( 'Get 5x more with a free trial', 'ewww-image-optimizer' )
		) .
		'</a></div>';
	} else {
		$output['complete'] = '<div><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</div></b>';
	}
	ewwwio_ob_clean();
	die( wp_json_encode( $output ) );
}

/**
 * Loads the list of optimized images into memory.
 *
 * Pulls a list of all optimized images from the database, and stores it globally unless there is
 * a memory constraint, or the list of images is too large to be efficient.
 *
 * @global string|array $optimized_list A list of all images that have been optimized, or a string
 *                                      indicating why that is not a good idea.
 * @global object $wpdb
 */
function ewww_image_optimizer_optimized_list() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Retrieve the time when the list building starts.
	$started = microtime( true );
	global $optimized_list;
	global $wpdb;
	$offset         = 0;
	$max_query      = (int) apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );
	$optimized_list = array();
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_SCAN_MODE_B' ) && EWWW_IMAGE_OPTIMIZER_SCAN_MODE_B ) { // User manually enabled Plan B.
		ewwwio_debug_message( 'user chose low memory mode' );
		$optimized_list = 'user_configured';
		set_transient( 'ewww_image_optimizer_low_memory_mode', 'user_configured', 90 ); // Put it in low memory mode for at least 10 minutes.
		return;
	}
	if ( get_transient( 'ewww_image_optimizer_low_memory_mode' ) ) {
		$optimized_list = get_transient( 'ewww_image_optimizer_low_memory_mode' );
		ewwwio_debug_message( "staying in low memory mode: $optimized_list" );
		return;
	}
	$starting_memory_usage = memory_get_usage( true );
	$already_optimized     = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id,path,image_size,pending,attachment_id,level,updated,resized_width,resized_height,resize_error FROM $wpdb->ewwwio_images LIMIT %d,%d",
			$offset,
			$max_query
		),
		ARRAY_A
	);
	while ( $already_optimized ) {
		$wpdb->flush();
		foreach ( $already_optimized as $optimized ) {
			$optimized_path = ewww_image_optimizer_absolutize_path( $optimized['path'] );
			// Check for duplicate records.
			if ( ! empty( $optimized_list[ $optimized_path ] ) && ! empty( $optimized_list[ $optimized_path ]['id'] ) ) {
				$optimized = ewww_image_optimizer_remove_duplicate_records( array( $optimized_list[ $optimized_path ]['id'], $optimized['id'] ) );
			}
			if ( $optimized ) {
				unset( $optimized['path'] );
				$optimized_list[ $optimized_path ] = $optimized;
			}
		}
		ewwwio_memory( 'removed original records' );
		$offset += $max_query;
		if ( empty( $estimated_batch_memory ) ) {
			$estimated_batch_memory = memory_get_usage( true ) - $starting_memory_usage;
			if ( ! $estimated_batch_memory ) { // If the memory did not appear to increase, set it to a safe default.
				$estimated_batch_memory = 3146000;
			}
			ewwwio_debug_message( "estimated batch memory is $estimated_batch_memory" );
		}
		if ( ! ewwwio_check_memory_available( 3146000 + $estimated_batch_memory ) ) { // Initial batch storage used + 3MB.
			ewwwio_debug_message( 'loading optimized list took too much memory' );
			$optimized_list = 'low_memory';
			set_transient( 'ewww_image_optimizer_low_memory_mode', 'low_memory', 600 ); // Put it in low memory mode for at least 10 minutes so we don't abuse the db server with extra requests.
			return;
		}
		$elapsed = microtime( true ) - $started;
		ewwwio_debug_message( "loading optimized list took $elapsed seconds so far" );
		if ( $elapsed > 5 ) {
			ewwwio_debug_message( 'loading optimized list took too long' );
			$optimized_list = 'large_list';
			set_transient( 'ewww_image_optimizer_low_memory_mode', 'large_list', 600 ); // Use low memory mode so that we don't waste lots of time pulling a huge list of images repeatedly.
			return;
		}
		$already_optimized = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,path,image_size,pending,attachment_id,level,updated,resized_width,resized_height,resize_error FROM $wpdb->ewwwio_images LIMIT %d,%d",
				$offset,
				$max_query
			),
			ARRAY_A
		);
	} // End while().
}

/**
 * Retrieves a selected set of attachment metadata from the postmeta table.
 *
 * @global object $wpdb
 *
 * @param string $attachments_in A comma-imploded array containing a list of attachment IDs.
 * @return array An associative array with the results of the query.
 */
function ewww_image_optimizer_query_metadata_batch( $attachments_in ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! preg_match( '/^[\d,]+$/', $attachments_in ) ) {
		ewwwio_debug_message( 'invalid attachments string' );
		return array();
	}
	$attachments_in = rtrim( $attachments_in, ',' );
	global $wpdb;
	$attachments = $wpdb->get_results( "SELECT metas.post_id,metas.meta_key,metas.meta_value,posts.post_mime_type FROM $wpdb->postmeta metas INNER JOIN $wpdb->posts posts ON posts.ID = metas.post_id WHERE (posts.post_mime_type LIKE '%%image%%' OR posts.post_mime_type LIKE '%%pdf%%') AND metas.post_id IN ($attachments_in)", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$wpdb->flush();
	return $attachments;
}

/**
 * Retrieves a selected set of attachment metadata from the postmeta table.
 *
 * @param array $attachment_ids An array of attachment IDs.
 * @return array Multi-dimensional array containing all the postmeta and mime-types for the IDs provided.
 */
function ewww_image_optimizer_fetch_metadata_batch( $attachment_ids ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $attachment_ids ) ) {
		ewwwio_debug_message( 'invalid attachments provided' );
		return array();
	}
	ewwwio_debug_message( 'fetching meta for ' . count( $attachment_ids ) . ' attachments' );
	$attachments_meta = array();
	$attachments_in   = '';
	$attachments      = array();
	/**
	 * Set a maximum for a query, 1k less than WPE's 16k limit, just to be safe.
	 *
	 * @param int 15000 The maximum query length.
	 */
	$max_query_length = apply_filters( 'ewww_image_optimizer_max_query_length', 15000 );
	foreach ( $attachment_ids as $attachment_id ) {
		$attachments_in .= (int) $attachment_id . ',';
		if ( strlen( $attachments_in ) > $max_query_length - 20 ) {
			ewwwio_debug_message( 'fetching partial metadata batch with query length: ' . strlen( $attachments_in ) );
			$more_attachments = ewww_image_optimizer_query_metadata_batch( $attachments_in );
			if ( $more_attachments ) {
				$attachments = array_merge( $attachments, $more_attachments );
			}
			$attachments_in = '';
		}
	}
	// Retrieve image attachment metadata from the database (in batches).
	$more_attachments = ewww_image_optimizer_query_metadata_batch( $attachments_in );
	if ( $more_attachments ) {
		$attachments = array_merge( $attachments, $more_attachments );
	}
	ewwwio_debug_message( 'fetched ' . count( $attachments ) . ' attachment meta items (final)' );
	foreach ( $attachments as $attachment ) {
		if ( '_wp_attached_file' === $attachment['meta_key'] ) {
			$attachments_meta[ $attachment['post_id'] ]['_wp_attached_file'] = $attachment['meta_value'];
			if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachments_meta[ $attachment['post_id'] ]['type'] ) ) {
				$attachments_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
			}
			continue;
		} elseif ( '_wp_attachment_metadata' === $attachment['meta_key'] ) {
			$attachments_meta[ $attachment['post_id'] ]['meta'] = $attachment['meta_value'];
			if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachments_meta[ $attachment['post_id'] ]['type'] ) ) {
				$attachments_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
			}
			continue;
		} elseif ( 'tiny_compress_images' === $attachment['meta_key'] ) {
			$attachments_meta[ $attachment['post_id'] ]['tinypng'] = true;
		} elseif ( 'wpml_media_processed' === $attachment['meta_key'] ) {
			$attachments_meta[ $attachment['post_id'] ]['wpml_media_processed'] = (bool) $attachment['meta_value'];
		}
		if ( ! empty( $attachment['post_mime_type'] ) && empty( $attachments_meta[ $attachment['post_id'] ]['type'] ) ) {
			$attachments_meta[ $attachment['post_id'] ]['type'] = $attachment['post_mime_type'];
		}
	}
	unset( $attachments );
	return $attachments_meta;
}

/**
 * Checks an image to see if it ought to  be resized based on current configuration.
 *
 * @param string $file The file that needs to be checked.
 * @param bool   $media Whether the image is from the Media Library.
 * @return bool True to resize, false if it isn't needed.
 */
function ewww_image_optimizer_should_resize( $file, $media = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if (
		! ewwwio_is_file( $file ) ||
		( $media && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) ||
		function_exists( 'imsanity_get_max_width_height' )
	) {
		return false;
	}
	if ( ! $media && ! ewww_image_optimizer_should_resize_other_image( $file ) ) {
		return false;
	}
	global $ewww_image;
	if ( $media && ! empty( $ewww_image->resize ) && 'full' !== $ewww_image->resize ) {
		return false;
	}
	global $optimized_list;
	$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' );
	$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' );
	if ( ! $maxwidth && ! $maxheight ) {
		$maxwidth  = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
	}
	list( $oldwidth, $oldheight ) = wp_getimagesize( $file );
	if ( ( $maxwidth && $oldwidth > $maxwidth ) || ( $maxheight && $oldheight > $maxheight ) ) {
		$already_optimized = false;
		if ( empty( $optimized_list ) || ! is_array( $optimized_list ) ) {
			$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
		} elseif ( is_array( $optimized_list ) && isset( $optimized_list[ $file ] ) ) {
			$already_optimized = $optimized_list[ $file ];
		}
		if ( is_array( $already_optimized ) && isset( $already_optimized['resized_width'] ) ) {
			if ( (int) $maxwidth >= (int) $already_optimized['resized_width'] && (int) $maxheight >= $already_optimized['resized_height'] && 2 === (int) $already_optimized['resize_error'] ) {
				ewwwio_debug_message( "$file ($oldwidth x $oldheight) already attempted resize to $maxwidth x $maxheight, and the resulting filesize was too large" );
				return false;
			}
		}
		ewwwio_debug_message( "$file ($oldwidth x $oldheight) larger than $maxwidth x $maxheight" );
		return true;
	}
	return false;
}

/**
 * Scans the Media Library for images that need optimizing.
 *
 * Searches for images using the attachment metadata and stores them in the ewwwio_images table.
 * Optionally restricted to specific attachments selected by the user. If Force Re-optimize is
 * checked, marks existing records as pending also.
 *
 * @global object $wpdb
 * @global string|array $optimized_list A list of all images that have been optimized, or a string
 *                                      indicating why that is not a good idea.
 *
 * @param string $hook An indicator if this was not called from AJAX, like WP-CLI.
 */
function ewww_image_optimizer_media_scan( $hook = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( 'ewww-image-optimizer-cli' !== $hook && empty( $_REQUEST['ewww_scan'] ) ) {
		ewwwio_debug_message( 'bailing no cli' );
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access denied.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( ! empty( $_REQUEST['ewww_scan'] ) && ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		ewwwio_debug_message( 'bailing no nonce' );
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	global $wpdb;
	global $ewww_scan;
	$ewww_scan = empty( $_REQUEST['ewww_scan'] ) ? '' : sanitize_key( $_REQUEST['ewww_scan'] );

	// Check options like Force Re-opt, Smart Re-opt, or WebP Only.
	ewww_image_optimizer_check_bulk_options( $_REQUEST );

	global $optimized_list;
	$queued_ids            = array();
	$skipped_ids           = array();
	$tiny_notice           = '';
	$image_count           = 0;
	$attachments_processed = 0;
	$attachment_query      = '';
	$images                = array();
	$attachment_images     = array();
	$reset_images          = array();

	ewwwio_debug_message( 'scanning for media attachments' );
	update_option( 'ewww_image_optimizer_bulk_resume', 'scanning' );
	set_transient( 'ewww_image_optimizer_no_scheduled_optimization', true, 60 * MINUTE_IN_SECONDS );

	// Retrieve the time when the scan starts.
	$started = microtime( true );

	$max_query = (int) apply_filters( 'ewww_image_optimizer_count_optimized_queries', 4000 );

	$attachment_ids = ewww_image_optimizer_get_unscanned_attachments( 'media', $max_query );

	if ( ! empty( $attachment_ids ) && count( $attachment_ids ) > 300 ) {
		ewww_image_optimizer_debug_log();
		ewww_image_optimizer_optimized_list();
	} elseif ( ! empty( $attachment_ids ) ) {
		$optimized_list = 'small_scan';
	}
	ewww_image_optimizer_debug_log();

	list( $bad_attachments, $bad_attachment ) = ewww_image_optimizer_get_bad_attachments();

	if ( empty( $attachment_ids ) && $ewww_scan ) {
		// When the media library is finished, run the aux script function to scan for additional images.
		ewww_image_optimizer_aux_images_script();
	}

	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt', false, true );

	$supported_types = ewwwio()->get_supported_types();
	$webp_types      = ewwwio()->get_webp_types();

	ewww_image_optimizer_debug_log();
	$starting_memory_usage = memory_get_usage( true );
	while ( microtime( true ) - $started < apply_filters( 'ewww_image_optimizer_timeout', 22 ) && count( $attachment_ids ) ) {
		ewww_image_optimizer_debug_log();
		if ( ! empty( $estimated_batch_memory ) && ! ewwwio_check_memory_available( 3146000 + $estimated_batch_memory ) ) { // Initial batch storage used + 3MB.
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				if ( is_array( $optimized_list ) ) {
					set_transient( 'ewww_image_optimizer_low_memory_mode', 'low_memory', 600 ); // Keep us in low memory mode for up to 10 minutes.
					$optimized_list = 'low_memory';
				}
			} else {
				break;
			}
		}
		if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
			ewwwio_debug_message( 'selected items: ' . count( $attachment_ids ) );
		} else {
			ewwwio_debug_message( 'no array found' );
			ewwwio_ob_clean();
			die( wp_json_encode( array( 'error' => esc_html__( 'List of attachment IDs not found.', 'ewww-image-optimizer' ) ) ) );
		}

		$attachments_meta = ewww_image_optimizer_fetch_metadata_batch( $attachment_ids );

		// If we just completed the first batch, check how much the memory usage increased.
		if ( empty( $estimated_batch_memory ) ) {
			$estimated_batch_memory = memory_get_usage( true ) - $starting_memory_usage;
			if ( ! $estimated_batch_memory ) { // If the memory did not appear to increase, set it to a safe default.
				$estimated_batch_memory = 3146000;
			}
			ewwwio_debug_message( "estimated batch memory is $estimated_batch_memory" );
		}

		ewwwio_debug_message( 'validated ' . count( $attachments_meta ) . ' attachment meta items' );
		ewwwio_debug_message( 'remaining items after selection: ' . count( $attachment_ids ) );
		foreach ( $attachment_ids as $selected_id ) {
			++$attachments_processed;
			if ( 0 === $attachments_processed % 5 && ( microtime( true ) - $started > apply_filters( 'ewww_image_optimizer_timeout', 22 ) || ! ewwwio_check_memory_available( 2194304 ) ) ) {
				ewwwio_debug_message( 'time exceeded, or memory exceeded' );
				ewww_image_optimizer_debug_log();
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					if ( is_array( $optimized_list ) ) {
						set_transient( 'ewww_image_optimizer_low_memory_mode', 'low_memory', 600 ); // Keep us in low memory mode for up to 10 minutes.
						$optimized_list = 'low_memory';
					}
					break;
				} else {
					break 2;
				}
			}
			ewww_image_optimizer_debug_log();
			clearstatcache();
			$pending     = false;
			$remote_file = false;
			if ( ! empty( $attachments_meta[ $selected_id ]['wpml_media_processed'] ) ) {
				$wpml_id = ewww_image_optimizer_get_primary_translated_media_id( $selected_id );
				if ( (int) $wpml_id !== (int) $selected_id ) {
					ewwwio_debug_message( "skipping WPML replica image $selected_id" );
					$skipped_ids[] = $selected_id;
					continue;
				}
			}
			if ( in_array( $selected_id, $bad_attachments, true ) ) { // a known broken attachment, which would mean we already tried this once before...
				ewwwio_debug_message( "skipping bad attachment $selected_id" );
				$skipped_ids[] = $selected_id;
				continue;
			}
			if ( ! empty( $attachments_meta[ $selected_id ]['_wp_attached_file'] ) && false !== strpos( $attachments_meta[ $selected_id ]['_wp_attached_file'], 'https://images-na.ssl-images-amazon.com' ) ) {
				ewwwio_debug_message( "Cannot compress externally-hosted Amazon image $selected_id" );
				$skipped_ids[] = $selected_id;
				continue;
			}
			if ( empty( $attachments_meta[ $selected_id ]['meta'] ) ) {
				ewwwio_debug_message( "empty meta for $selected_id" );
				$meta = array();
			} else {
				$meta = maybe_unserialize( $attachments_meta[ $selected_id ]['meta'] );
			}
			$mime = '';
			if ( ! empty( $attachments_meta[ $selected_id ]['type'] ) ) {
				$mime = $attachments_meta[ $selected_id ]['type'];
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

			if ( ! in_array( $mime, $supported_types, true ) && empty( ewwwio()->webp_only ) ) {
				$skipped_ids[] = $selected_id;
				continue;
			}
			ewwwio_debug_message( "id: $selected_id and type: $mime" );
			$attached_file = ( ! empty( $attachments_meta[ $selected_id ]['_wp_attached_file'] ) ? $attachments_meta[ $selected_id ]['_wp_attached_file'] : '' );

			list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $selected_id, $attached_file, false );

			if ( ! empty( $file_path ) && false !== strpos( $file_path, 'https://images-na.ssl-images-amazon.com' ) ) {
				ewwwio_debug_message( "Cannot compress externally-hosted Amazon image $selected_id" );
				$skipped_ids[] = $selected_id;
				continue;
			}

			// Run a quick fix for as3cf files.
			if ( class_exists( 'Amazon_S3_And_CloudFront' ) && ewww_image_optimizer_stream_wrapped( $file_path ) ) {
				ewww_image_optimizer_check_table_as3cf( $meta, $selected_id, $file_path );
			}
			if (
				( ewww_image_optimizer_stream_wrapped( $file_path ) || ! ewwwio_is_file( $file_path ) ) &&
				(
					class_exists( 'WindowsAzureStorageUtil' ) ||
					class_exists( 'Amazon_S3_And_CloudFront' ) ||
					ewww_image_optimizer_s3_uploads_enabled() ||
					class_exists( 'wpCloud\StatelessMedia\EWWW' ) ||
					apply_filters( 'ewww_image_optimizer_is_remote_file', false, $file_path, $selected_id )
				)
			) {
				// Construct a $file_path and proceed IF a supported CDN plugin is installed.
				ewwwio_debug_message( 'Azure or S3 detected and no local file found' );
				$file_path = get_attached_file( $selected_id );
				if ( class_exists( 'S3_Uploads', false ) && method_exists( 'S3_Uploads', 'filter_upload_dir' ) ) {
					$s3_uploads = S3_Uploads::get_instance();
					remove_filter( 'upload_dir', array( $s3_uploads, 'filter_upload_dir' ) );
				}
				if ( class_exists( 'S3_Uploads\Plugin', false ) && method_exists( 'S3_Uploads\Plugin', 'filter_upload_dir' ) ) {
					$s3_uploads = \S3_Uploads\Plugin::get_instance();
					remove_filter( 'upload_dir', array( $s3_uploads, 'filter_upload_dir' ) );
				}
				if ( ewww_image_optimizer_stream_wrapped( $file_path ) || 0 === strpos( $file_path, 'http' ) ) {
					$file_path = get_attached_file( $selected_id, true );
				}
				if ( class_exists( 'S3_Uploads', false ) && method_exists( 'S3_Uploads', 'filter_upload_dir' ) ) {
					add_filter( 'upload_dir', array( $s3_uploads, 'filter_upload_dir' ) );
				}
				if ( class_exists( 'S3_Uploads\Plugin', false ) && method_exists( 'S3_Uploads\Plugin', 'filter_upload_dir' ) ) {
					add_filter( 'upload_dir', array( $s3_uploads, 'filter_upload_dir' ) );
				}
				ewwwio_debug_message( "remote file possible: $file_path" );
				if ( ! $file_path ) {
					ewwwio_debug_message( 'no file found on remote storage, bailing' );
					$skipped_ids[] = $selected_id;
					continue;
				}
				$remote_file = true;
			} elseif ( ! $file_path ) {
				ewwwio_debug_message( "no file path for $selected_id" );
				$skipped_ids[] = $selected_id;
				continue;
			}

			// Early check for bypass based on full-size path.
			if ( apply_filters( 'ewww_image_optimizer_bypass', false, $file_path ) === true ) {
				ewwwio_debug_message( "skipping $file_path as instructed" );
				$skipped_ids[] = $selected_id;
				ewww_image_optimizer_debug_log();
				continue;
			}

			$should_resize = ewww_image_optimizer_should_resize( $file_path, true );
			if (
				! empty( $attachments_meta[ $selected_id ]['tinypng'] ) &&
				empty( ewwwio()->force ) &&
				empty( ewwwio()->webp_only ) &&
				! $should_resize
			) {
				ewwwio_debug_message( "TinyPNG already compressed $selected_id" );
				if ( ! $tiny_notice ) {
					$tiny_notice = esc_html__( 'Images compressed by TinyJPG and TinyPNG have been skipped, refresh and use the Force Re-optimize option to override.', 'ewww-image-optimizer' );
				}
				$skipped_ids[] = $selected_id;
				continue;
			}

			// NOTE: this logic could possibly be enhanced to match Background_Process_Media::should_optimize_size().
			// While previous checks short-circuit an entire attachment (and all thumbs), some checks are size specific.
			// For example, we can't convert a PDF to WebP, but PDF uploads may have JPG thumbs that can be converted.
			if ( ( ewwwio()->webp_only && in_array( $mime, $webp_types, true ) ) || empty( ewwwio()->webp_only ) ) {
				$attachment_images['full'] = $file_path;

				$retina_path = ewww_image_optimizer_get_hidpi_path( $file_path );
				if ( $retina_path ) {
					$attachment_images['full-retina'] = $retina_path;
				}
			}

			// Resized versions available, see what we can find.
			if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
				// Meta sizes don't contain a full path, so we calculate one.
				$base_ims_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
				$base_dir     = trailingslashit( dirname( $file_path ) );
				// To keep track of the ones we have already processed.
				$processed = array();
				foreach ( $meta['sizes'] as $size => $data ) {
					ewwwio_debug_message( "checking for size: $size" );
					ewww_image_optimizer_debug_log();
					if ( strpos( $size, 'webp' ) === 0 ) {
						continue;
					}
					if ( ! empty( $disabled_sizes[ $size ] ) ) {
						continue;
					}
					if ( ! empty( $disabled_sizes['pdf-full'] ) && 'full' === $size ) {
						continue;
					}
					if ( empty( $data['file'] ) ) {
						continue;
					}

					// Check to see if an IMS record exist from before a resize was moved to the IMS _resized folder.
					$ims_path = $base_ims_dir . $data['file'];
					if ( file_exists( $ims_path ) ) {
						// We reset base_dir, because base_dir potentially gets overwritten with base_ims_dir.
						$base_dir      = trailingslashit( dirname( $file_path ) );
						$ims_temp_path = $base_dir . $data['file'];
						ewwwio_debug_message( "ims path: $ims_path" );
						if ( $file_path !== $ims_temp_path && is_array( $optimized_list ) && isset( $optimized_list[ $ims_temp_path ] ) ) {
							$optimized_list[ $ims_path ] = $optimized_list[ $ims_temp_path ];
							ewwwio_debug_message( "updating record {$optimized_list[ $ims_temp_path ]['id']} with $ims_path" );
							// Update our records so that we have the correct path going forward.
							$wpdb->update(
								$wpdb->ewwwio_images,
								array(
									'path'    => ewww_image_optimizer_relativize_path( $ims_path ),
									'updated' => $optimized_list[ $ims_temp_path ]['updated'],
								),
								array(
									'id' => $optimized_list[ $ims_temp_path ]['id'],
								)
							);
						}
						$base_dir = $base_ims_dir;
					}

					if ( empty( $data['mime-type'] ) ) {
						$data['mime-type'] = ewww_image_optimizer_quick_mimetype( $data['file'] );
					}
					if ( ewwwio()->webp_only && ! in_array( $data['mime-type'], $webp_types, true ) ) {
						continue;
					}

					// Check through all the sizes we've processed so far.
					foreach ( $processed as $proc => $scan ) {
						// If a previous resize had identical dimensions...
						if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
							// Found a duplicate size, get outta here!
							continue( 2 );
						}
					}
					$resize_path = $base_dir . $data['file'];
					if ( ( $remote_file || ewwwio_is_file( $resize_path ) ) && 'application/pdf' === $mime && 'full' === $size ) {
						$attachment_images[ 'pdf-' . $size ] = $resize_path;
					} elseif ( $remote_file || ewwwio_is_file( $resize_path ) ) {
						$attachment_images[ $size ] = $resize_path;
					}
					// Optimize retina image, if it exists.
					if ( function_exists( 'wr2x_get_retina' ) ) {
						$retina_path = wr2x_get_retina( $resize_path );
					} else {
						$retina_path = false;
					}
					if ( $retina_path && ( $remote_file || ewwwio_is_file( $retina_path ) ) ) {
						ewwwio_debug_message( "found retina via wr2x_get_retina $retina_path" );
						$attachment_images[ $size . '-retina' ] = $retina_path;
					} else {
						$retina_path = ewww_image_optimizer_get_hidpi_path( $resize_path );
						if ( $retina_path ) {
							ewwwio_debug_message( "found retina via hidpi_opt $retina_path" );
							$attachment_images[ $size . '-retina' ] = $retina_path;
						}
					}
					// Store info on the sizes we've processed, so we can check the list for duplicate sizes.
					$processed[ $size ]['width']  = $data['width'];
					$processed[ $size ]['height'] = $data['height'];
				} // End foreach().
			} // End if().

			// Original image detected.
			if ( isset( $meta['original_image'] ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
				ewwwio_debug_message( 'checking for original_image' );
				// Meta sizes don't contain a path, so we calculate one.
				$resize_path = trailingslashit( dirname( $file_path ) ) . $meta['original_image'];
				$thumb_mime  = ewww_image_optimizer_quick_mimetype( $resize_path );
				if ( ( ewwwio()->webp_only && in_array( $thumb_mime, $webp_types, true ) ) || empty( ewwwio()->webp_only ) ) {
					if ( $remote_file || ewwwio_is_file( $resize_path ) ) {
						$attachment_images['original_image'] = $resize_path;
					}
				}
			}

			// Queue sizes from a custom theme.
			if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
				$imagemeta_resize_pathinfo = pathinfo( $file_path );
				$imagemeta_resize_path     = '';
				foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
					$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
					$thumb_mime            = ewww_image_optimizer_quick_mimetype( $imagemeta_resize_path );
					if ( ewwwio()->webp_only && ! in_array( $thumb_mime, $webp_types, true ) ) {
						continue;
					}
					if ( $remote_file || ewwwio_is_file( $imagemeta_resize_path ) ) {
						$attachment_images[ 'resized-images-' . $index ] = $imagemeta_resize_path;
					}
				}
			}

			// Queue size from another custom theme.
			if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
				$custom_sizes_pathinfo = pathinfo( $file_path );
				$custom_size_path      = '';
				foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
					$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
					$thumb_mime       = ewww_image_optimizer_quick_mimetype( $custom_size_path );
					if ( ewwwio()->webp_only && ! in_array( $thumb_mime, $webp_types, true ) ) {
						continue;
					}
					if ( $remote_file || ewwwio_is_file( $custom_size_path ) ) {
						$attachment_images[ 'custom-size-' . $dimensions ] = $custom_size_path;
					}
				}
			}

			// Check if the files are 'prev opt', pending, or brand new, and then queue the file as needed.
			foreach ( $attachment_images as $size => $file_path ) {
				ewwwio_debug_message( "here is a path $file_path" );
				if ( ! $remote_file && ! ewww_image_optimizer_stream_wrapped( $file_path ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) ) {
					$file_path = realpath( $file_path );
				}
				if ( empty( $file_path ) ) {
					continue;
				}
				if ( apply_filters( 'ewww_image_optimizer_bypass', false, $file_path ) === true ) {
					ewwwio_debug_message( "skipping $file_path as instructed" );
					continue;
				}
				ewwwio_debug_message( "here is the real path $file_path" );
				ewwwio_debug_message( 'memory used: ' . memory_get_usage( true ) );
				$already_optimized = false;
				if ( ! is_array( $optimized_list ) && is_string( $optimized_list ) ) {
					$already_optimized = ewww_image_optimizer_find_already_optimized( $file_path );
				} elseif ( is_array( $optimized_list ) && isset( $optimized_list[ $file_path ] ) ) {
					$already_optimized = $optimized_list[ $file_path ];
				}
				if ( is_array( $already_optimized ) && ! empty( $already_optimized ) ) {
					ewwwio_debug_message( 'potential match found' );
					if ( ! empty( $already_optimized['pending'] ) ) {
						$pending = true;
						ewwwio_debug_message( "pending record for $file_path" );
						continue;
					}
					if ( $remote_file ) {
						$image_size = $already_optimized['image_size'];
						ewwwio_debug_message( "image size for remote file is $image_size" );
					} else {
						$image_size = filesize( $file_path );
						ewwwio_debug_message( "image size is $image_size" );
						if ( ! $image_size ) {
							continue;
						}
					}
					if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
						ewwwio_debug_message( "file skipped due to filesize: $file_path" );
						continue;
					}
					if ( 'image/png' === $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
						ewwwio_debug_message( "file skipped due to PNG filesize: $file_path" );
						continue;
					}
					$compression_level = ewww_image_optimizer_get_level( $mime );
					$smart_reopt       = false;
					if ( ! empty( ewwwio()->force_smart ) && ewww_image_optimizer_level_mismatch( $already_optimized['level'], $compression_level ) ) {
						$smart_reopt = true;
					}
					if ( 'full' === $size && $should_resize ) {
						$smart_reopt = true;
					}
					if ( (int) $already_optimized['image_size'] === (int) $image_size && empty( ewwwio()->force ) && empty( ewwwio()->webp_only ) && ! $smart_reopt ) {
						ewwwio_debug_message( "match found for $file_path" );
						ewww_image_optimizer_debug_log();
						continue;
					} else {
						if ( $smart_reopt ) {
							ewwwio_debug_message( "smart re-opt found level mismatch (or needs resizing) for $file_path, db says " . $already_optimized['level'] . " vs. current $compression_level" );
						} else {
							ewwwio_debug_message( "mismatch found for $file_path, db says " . $already_optimized['image_size'] . " vs. current $image_size" );
						}
						$pending = true;
						if ( empty( $already_optimized['attachment_id'] ) ) {
							ewwwio_debug_message( "updating record for $file_path, with id $selected_id and resize $size" );
							$wpdb->update(
								$wpdb->ewwwio_images,
								array(
									'pending'       => 1,
									'attachment_id' => $selected_id,
									'gallery'       => 'media',
									'resize'        => $size,
									'updated'       => $already_optimized['updated'],
								),
								array(
									'id' => $already_optimized['id'],
								)
							);
							ewwwio_debug_message( 'updated record' );
						} else {
							ewwwio_debug_message( "adding $selected_id to reset queue" );
							$reset_images[] = (int) $already_optimized['id'];
						}
					}
				} else { // Looks like a new image.
					if ( ! empty( $images[ $file_path ] ) ) {
						continue;
					}
					$pending = true;
					ewwwio_debug_message( "queuing $file_path" );
					if ( $remote_file ) {
						$image_size = 0;
						ewwwio_debug_message( 'image size set to 0' );
					} else {
						$image_size = filesize( $file_path );
						ewwwio_debug_message( "image size is $image_size" );
						if ( ! $image_size ) {
							continue;
						}
						if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
							ewwwio_debug_message( "file skipped due to filesize: $file_path" );
							continue;
						}
						if ( 'image/png' === $mime && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
							ewwwio_debug_message( "file skipped due to PNG filesize: $file_path" );
							continue;
						}
					}
					if ( seems_utf8( $file_path ) ) {
						ewwwio_debug_message( 'file seems utf8' );
						$utf8_file_path = $file_path;
					} else {
						ewwwio_debug_message( 'file will become utf8' );
						$utf8_file_path = mb_convert_encoding( $file_path, 'UTF-8' );
					}
					ewww_image_optimizer_debug_log();
					$images[ $file_path ] = array(
						'path'          => ewww_image_optimizer_relativize_path( $utf8_file_path ),
						'gallery'       => 'media',
						'orig_size'     => $image_size,
						'attachment_id' => $selected_id,
						'resize'        => $size,
						'pending'       => 1,
					);
					++$image_count;
					ewwwio_debug_message( 'image added to $images queue' );
				} // End if().
				if ( false ) { // $image_count > 1000 || count( $reset_images ) > 1000 ) { // Disabled, should not be needed anymore.
					ewwwio_debug_message( 'making a dump run' );
					// Let's dump what we have so far to the db.
					$image_count = 0;
					if ( ! empty( $images ) ) {
						ewwwio_debug_message( 'doing mass insert' );
						ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images );
					}
					$images = array();
					if ( ! empty( $reset_images ) ) {
						ewwwio_debug_message( 'marking reset_images as pending' );
						ewww_image_optimizer_reset_images( $reset_images );
					}
					$reset_images = array();
				}
			} // End foreach().
			// End of loop checking all the attachment_images for selected_id to see if they are optimized already or pending already.
			if ( $pending ) {
				ewwwio_debug_message( "$selected_id added to queue" );
				ewww_image_optimizer_debug_log();
				$queued_ids[] = $selected_id;
			} else {
				$skipped_ids[] = $selected_id;
			}
			$attachment_images = array();
			ewwwio_debug_message( 'checking for bad attachment' );
			ewww_image_optimizer_debug_log();
			if ( $selected_id === $bad_attachment ) {
				ewwwio_debug_message( 'found bad attachment, bailing to reset the counter' );
				ewww_image_optimizer_debug_log();
				if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
					break 2;
				}
			}
		} // End foreach().
		// End of loop for the selected_id.
		ewwwio_debug_message( 'finished foreach of attachment_ids' );
		ewww_image_optimizer_debug_log();

		ewww_image_optimizer_update_scanned_images( $queued_ids );
		ewww_image_optimizer_delete_queued_images( $skipped_ids );
		$queued_ids  = array();
		$skipped_ids = array();

		ewwwio_debug_message( 'finished a loop in the while, going back for more possibly' );
		$attachment_ids = ewww_image_optimizer_get_unscanned_attachments( 'media', $max_query );
		ewww_image_optimizer_debug_log();
	} // End while().
	ewwwio_debug_message( 'done for this request, wrapping up' );
	ewww_image_optimizer_debug_log();

	if ( ! empty( $images ) ) {
		ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images );
	}
	ewww_image_optimizer_reset_images( $reset_images );
	ewww_image_optimizer_update_scanned_images( $queued_ids );
	ewww_image_optimizer_delete_queued_images( $skipped_ids );

	if ( 250 > $attachments_processed ) { // in-memory table is too slow.
		ewwwio_debug_message( 'using in-memory table is too slow, switching to plan b' );
		set_transient( 'ewww_image_optimizer_low_memory_mode', 'slow_list', 600 ); // Put it in low memory mode for at least 10 minutes.
	}
	ewww_image_optimizer_debug_log();

	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "counting images took $elapsed seconds" );
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
	if ( 'ewww-image-optimizer-cli' === $hook ) {
		return;
	}
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	$notice        = ( 'low_memory' === get_transient( 'ewww_image_optimizer_low_memory_mode' ) ? esc_html__( "Increasing PHP's memory_limit setting will allow for faster scanning with fewer database queries. Please allow up to 10 minutes for changes to memory limit to be detected.", 'ewww-image-optimizer' ) : '' );
	$remaining     = ewww_image_optimizer_count_unscanned_attachments();
	if ( $remaining ) {
		ewwwio_ob_clean();
		die(
			wp_json_encode(
				array(
					/* translators: %s: number of images */
					'remaining'      => sprintf( esc_html__( 'Stage 1, %s items left to scan.', 'ewww-image-optimizer' ), number_format_i18n( $remaining ) ) . "&nbsp;<img src='$loading_image' />",
					'notice'         => $notice,
					'bad_attachment' => $bad_attachment,
					'tiny_skip'      => $tiny_notice,
				)
			)
		);
	} else {
		ewwwio_ob_clean();
		die(
			wp_json_encode(
				array(
					'remaining'      => esc_html__( 'Stage 2, please wait.', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' />",
					'notice'         => $notice,
					'bad_attachment' => $bad_attachment,
					'tiny_skip'      => $tiny_notice,
				)
			)
		);
	}
}

/**
 * Called via AJAX to get an update on the API quota usage.
 */
function ewww_image_optimizer_bulk_quota_update() {
	// Verify that an authorized user has made the request.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	ewwwio_ob_clean();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		echo esc_html__( 'Image credits available:', 'ewww-image-optimizer' ) . ' ' . wp_kses_post( ewww_image_optimizer_cloud_quota() );
	}
	ewwwio_memory( __FUNCTION__ );
	die();
}

/**
 * Called via AJAX to start the bulk operation and get the name of the first image in the queue.
 */
function ewww_image_optimizer_bulk_initialize() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has made the request.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	$output = array();

	// Update the 'bulk resume' option to show that an operation is in progress.
	update_option( 'ewww_image_optimizer_bulk_resume', 'true' );
	list( $attachment ) = ewww_image_optimizer_get_queued_attachments( 'media', 1 );
	ewwwio_debug_message( "first image: $attachment" );
	$first_image = new EWWW_Image( $attachment, 'media' );
	$file        = $first_image->file;
	// Generate the WP spinner image for display.
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// Let the user know that we are beginning.
	if ( $file ) {
		$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <b>$file</b>&nbsp;<img src='$loading_image' /></p>";
	} else {
		$output['results'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>";
	}
	$output['start_time'] = time();
	ewwwio_memory( __FUNCTION__ );
	ewwwio_ob_clean();
	die( wp_json_encode( $output ) );
}

/**
 * Skips an un-optimizable image after all counter-measures have been attempted.
 *
 * @param object $image The EWWW_Image object representing the image to skip.
 */
function ewww_image_optimizer_bulk_skip_image( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_update_table( $image->file, filesize( $image->file ), filesize( $image->file ) );
}

/**
 * Checks if any optimization failures have been detected and attempts to react accordingly.
 *
 * @param object $image The EWWW_Image object representing the currently queued image.
 * @param int    $error_counter The number of times an error has been encountered so far.
 */
function ewww_image_optimizer_bulk_counter_measures( $image, $error_counter = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( 30 >= $error_counter ) {
		$failed_file              = get_transient( 'ewww_image_optimizer_failed_file' );
		$previous_incomplete_file = get_transient( 'ewww_image_optimizer_bulk_current_image' );
		if ( is_array( get_transient( 'ewww_image_optimizer_bulk_counter_measures' ) ) ) {
			$previous_countermeasures = get_transient( 'ewww_image_optimizer_bulk_counter_measures' );
		} else {
			$previous_countermeasures = array(
				'resize_existing' => false,
				'png50'           => false,
				'png40'           => false,
				'png2jpg'         => false,
				'pngdefaults'     => false,
				'jpg2png'         => false,
				'jpg40'           => false,
				'gif2png'         => false,
				'pdf20'           => false,
			);
		}
		if ( $failed_file && $failed_file === $image->file || $previous_incomplete_file === $image->file ) {
			ewwwio_debug_message( "failed file detected, taking evasive action: $failed_file" );
			// Use the constants for temporary overrides, while keeping track of which ones we've used.
			if ( 'image/png' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				if ( empty( $previous_countermeasures['png50'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL' ) && 50 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
					ewwwio_debug_message( 'png50' );
					// If the file is a PNG and compression is 50, try 40.
					define( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL', 40 );
					$previous_countermeasures['png50'] = true;
				} elseif ( empty( $previous_countermeasures['png40'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL' ) && 40 <= ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
					ewwwio_debug_message( 'png40' );
					// If the file is a PNG and compression is 40 (or higher), try 20.
					define( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL', 20 );
					$previous_countermeasures['png40'] = true;
				} elseif ( empty( $previous_countermeasures['png2jpg'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_PNG_TO_JPG' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
					ewwwio_debug_message( 'png2jpg' );
					// If the file is a PNG and PNG2JPG is enabled.
					// also set png level to 20 if needed...
					define( 'EWWW_IMAGE_OPTIMIZER_PNG_TO_JPG', false );
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL' ) && 40 <= ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL', 20 );
					}
					$previous_countermeasures['png2jpg'] = true;
				} elseif ( empty( $previous_countermeasures['pngdefaults'] )
					&& 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' )
					&& ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' ) > 2
					|| ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) )
				) {
					ewwwio_debug_message( 'pngdefaults' );
					// If PNG compression is 10 with pngout or optipng set higher than 2 or pngout enabled.
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG_LEVEL' ) && 2 < ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG_LEVEL', 2 );
					}
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_PNGOUT' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_DISABLE_PNGOUT', true );
					}
					$previous_countermeasures['pngdefaults'] = true;
				} elseif ( empty( $previous_countermeasures['resize_existing'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_RESIZE_EXISTING' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) {
					ewwwio_debug_message( 'resize_existing' );
					// If resizing is enabled, try to disable it.
					define( 'EWWW_IMAGE_OPTIMIZER_RESIZE_EXISTING', false );
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL' ) && 40 <= ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_PNG_LEVEL', 20 );
					}
					if ( 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG_LEVEL' ) && 2 < ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' ) ) {
							define( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG_LEVEL', 2 );
						}
						if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_PNGOUT' ) ) {
							define( 'EWWW_IMAGE_OPTIMIZER_DISABLE_PNGOUT', true );
						}
					}
					$previous_countermeasures['resize_existing'] = true;
				} else {
					// If the file is a PNG and nothing else worked, skip it.
					ewww_image_optimizer_bulk_skip_image( $image );
				} // End if().
			} // End if().
			if ( 'image/jpeg' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				if ( empty( $previous_countermeasures['jpg2png'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
					ewwwio_debug_message( 'jpg2png' );
					// If the file is a JPG and JPG2PNG is enabled.
					define( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG', false );
					$previous_countermeasures['jpg2png'] = true;
				} elseif ( empty( $previous_countermeasures['jpg40'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_JPG_LEVEL' ) && 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					ewwwio_debug_message( 'jpg40' );
					// If the file is a JPG and level 40 is enabled, drop it to 30 (and nuke jpg2png).
					define( 'EWWW_IMAGE_OPTIMIZER_JPG_LEVEL', 30 );
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG', false );
					}
					$previous_countermeasures['jpg40'] = true;
				} elseif ( empty( $previous_countermeasures['resize_existing'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_RESIZE_EXISTING' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) {
					ewwwio_debug_message( 'resize_existing' );
					// If resizing is enabled, try to disable it.
					define( 'EWWW_IMAGE_OPTIMIZER_RESIZE_EXISTING', false );
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPG_LEVEL' ) && 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_JPG_LEVEL', 30 );
					}
					if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG' ) ) {
						define( 'EWWW_IMAGE_OPTIMIZER_JPG_TO_PNG', false );
					}
					$previous_countermeasures['resize_existing'] = true;
				} else {
					// If all else fails, skip it.
					ewww_image_optimizer_bulk_skip_image( $image );
				}
			}
			if ( 'image/gif' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				if ( empty( $previous_countermeasures['gif2png'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_GIF_TO_PNG' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
					ewwwio_debug_message( 'gif2png' );
					// If the file is a GIF and GIF2PNG is enabled.
					define( 'EWWW_IMAGE_OPTIMIZER_GIF_TO_PNG', false );
					$previous_countermeasures['gif2png'] = true;
				} else {
					// If all else fails, skip it.
					ewww_image_optimizer_bulk_skip_image( $image );
				}
			}
			if ( 'image/bmp' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				if ( empty( $previous_countermeasures['bmp2png'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_BMP_TO_PNG' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_bmp_convert' ) ) {
					ewwwio_debug_message( 'bmp2png' );
					// If the file is a BMP and GIF2PNG is enabled.
					define( 'EWWW_IMAGE_OPTIMIZER_BMP_TO_PNG', false );
					$previous_countermeasures['bmp2png'] = true;
				} else {
					// If all else fails, skip it.
					ewww_image_optimizer_bulk_skip_image( $image );
				}
			}
			if ( 'image/webp' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				// There is nothing "less" that we can do with WebP, so just skip it.
				ewww_image_optimizer_bulk_skip_image( $image );
			}
			if ( 'application/pdf' === ewww_image_optimizer_quick_mimetype( $image->file ) ) {
				if ( empty( $previous_countermeasures['pdf20'] ) && ! defined( 'EWWW_IMAGE_OPTIMIZER_PDF_LEVEL' ) && 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
					ewwwio_debug_message( 'pdf20' );
					// If lossy PDF is enabled, drop it down a notch.
					define( 'EWWW_IMAGE_OPTIMIZER_PDF_LEVEL', 10 );
					$previous_countermeasures['pdf20'] = true;
				} else {
					// If all else fails, skip it.
					ewww_image_optimizer_bulk_skip_image( $image );
				}
			}
			set_transient( 'ewww_image_optimizer_bulk_counter_measures', $previous_countermeasures, 600 );
		} // End if().
		set_transient( 'ewww_image_optimizer_failed_file', $image->file, 600 );
		return $previous_countermeasures;
	} else {
		delete_transient( 'ewww_image_optimizer_failed_file' );
		delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
	} // End if().
	return false;
}

/**
 * Called by AJAX to process each image in the queue.
 *
 * @global object $wpdb
 *
 * @param string $hook Optional. Lets us know if WP-CLI is running. Default empty.
 * @param int    $delay Optional. Number of seconds to pause between images. Default 0.
 * @return bool When using WP-CLI, true keeps the process running, false indicates completion.
 */
function ewww_image_optimizer_bulk_loop( $hook = '', $delay = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwio_resize_status;
	ewwwio()->defer  = false;
	$output          = array();
	$time_adjustment = 0;
	$add_to_total    = 0;
	add_filter( 'ewww_image_optimizer_allowed_reopt', '__return_true' );
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if (
		'ewww-image-optimizer-cli' !== $hook &&
		(
			empty( $_REQUEST['ewww_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) ||
			! current_user_can( $permissions )
		)
	) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	session_write_close();
	// Retrieve the time when the optimizer starts.
	$started = microtime( true );
	// Prevent the scheduled optimizer from firing during a bulk optimization.
	set_transient( 'ewww_image_optimizer_no_scheduled_optimization', true, 10 * MINUTE_IN_SECONDS );
	// Make the Force Re-optimize option persistent.
	if ( ! empty( $_REQUEST['ewww_force'] ) ) {
		ewwwio()->force = true;
		set_transient( 'ewww_image_optimizer_force_reopt', true, HOUR_IN_SECONDS );
	} else {
		ewwwio()->force = false;
		delete_transient( 'ewww_image_optimizer_force_reopt' );
	}
	// Make the Smart Re-optimize option persistent.
	if ( ! empty( $_REQUEST['ewww_force_smart'] ) ) {
		ewwwio()->force_smart = true;
		set_transient( 'ewww_image_optimizer_smart_reopt', true, HOUR_IN_SECONDS );
	} else {
		ewwwio()->force_smart = false;
		delete_transient( 'ewww_image_optimizer_smart_reopt' );
	}
	if ( ! empty( $_REQUEST['ewww_webp_only'] ) ) {
		ewwwio()->webp_only = true;
	}
	// Find out if our nonce is on it's last leg/tick.
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		$output['new_nonce'] = ewwwio_maybe_get_new_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' );
	}
	$batch_image_limit = ( empty( $_REQUEST['ewww_batch_limit'] ) && ! ewww_image_optimizer_s3_uploads_enabled() ? 999 : 1 );
	// Get the 'bulk attachments' with a list of IDs remaining.
	$attachments = ewww_image_optimizer_get_queued_attachments( 'media', $batch_image_limit );
	if ( ! empty( $attachments ) && is_array( $attachments ) ) {
		$attachment = (int) $attachments[0];
	} else {
		$attachment = 0;
	}
	$image = new EWWW_Image( $attachment, 'media' );
	if ( ! $image->file ) {
		ewwwio_ob_clean();
		die(
			wp_json_encode(
				array(
					'done'      => 1,
					'completed' => 0,
				)
			)
		);
	}

	$output['results']   = '';
	$output['completed'] = 0;
	while ( $output['completed'] < $batch_image_limit && $image->file && microtime( true ) - $started + $time_adjustment < apply_filters( 'ewww_image_optimizer_timeout', 15 ) ) {
		++$output['completed'];
		$meta = false;
		ewwwio_debug_message( "processing {$image->id}: {$image->file}" );
		// See if the image needs fetching from a CDN.
		if ( ! ewwwio_is_file( $image->file ) ) {
			$meta      = wp_get_attachment_metadata( $image->attachment_id );
			$file_path = ewww_image_optimizer_remote_fetch( $image->attachment_id, $meta );
			// Nuke the meta, otherwise this will trigger unnecessary metadata updates,
			// which should be reserved for conversion/resize operations on the full-size image only.
			unset( $meta );
			if ( ! $file_path ) {
				ewwwio_debug_message( 'could not retrieve path' );
				if ( defined( 'WP_CLI' ) && WP_CLI ) {
					WP_CLI::line( __( 'Could not find image', 'ewww-image-optimizer' ) . ' ' . $image->file );
				} else {
					$output['results'] .= sprintf( '<p>' . esc_html__( 'Could not find image', 'ewww-image-optimizer' ) . ' <strong>%s</strong></p>', esc_html( $image->file ) );
				}
			}
		}
		if ( ! empty( $_REQUEST['ewww_error_counter'] ) ) {
			$error_counter   = (int) $_REQUEST['ewww_error_counter'];
			$countermeasures = ewww_image_optimizer_bulk_counter_measures( $image, $error_counter );
			if ( $countermeasures ) {
				$batch_image_limit = 1;
			}
		}
		set_transient( 'ewww_image_optimizer_bulk_current_image', $image->file, 600 );
		global $ewww_image;
		$ewww_image = $image;
		if ( 'full' === $image->resize && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
			if ( empty( $meta ) || ! is_array( $meta ) ) {
				$meta = wp_get_attachment_metadata( $image->attachment_id );
			}
			$new_dimensions = ewww_image_optimizer_resize_upload( $image->file );
			if ( ! empty( $new_dimensions ) && is_array( $new_dimensions ) ) {
				$meta['width']  = $new_dimensions[0];
				$meta['height'] = $new_dimensions[1];
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_preserve_originals' ) ) {
					$meta        = ewww_image_optimizer_update_scaled_metadata( $meta, $image->attachment_id );
					$scaled_file = ewww_image_optimizer_scaled_filename( $image->file );
					if ( ewwwio_is_file( $scaled_file ) ) {
						if ( ! empty( $ewww_image->id ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_originals' ) ) {
							ewww_image_optimizer_delete_pending_image( $ewww_image->id );
						} elseif ( ! empty( $ewww_image->id ) ) {
							$add_to_total = 1;
							global $wpdb;
							$wpdb->update(
								$wpdb->ewwwio_images,
								array(
									'resize' => 'original_image',
								),
								array(
									'id' => $ewww_image->id,
								)
							);
						}
						set_transient( 'ewww_image_optimizer_bulk_current_image', $scaled_file, 600 );
						delete_transient( 'ewww_image_optimizer_failed_file' );
						$ewww_image                = new EWWW_Image( 0, 'media', $scaled_file );
						$ewww_image->resize        = 'full';
						$ewww_image->attachment_id = $image->attachment_id;
						$ewww_image->gallery       = 'media';
						$wpdb->update(
							$wpdb->ewwwio_images,
							array(
								'pending'       => 1,
								'attachment_id' => $image->attachment_id,
								'gallery'       => 'media',
								'resize'        => 'full',
							),
							array(
								'id' => $ewww_image->id,
							)
						);
						$image = $ewww_image;
					}
				}
			}
		} elseif ( empty( $image->resize ) && ewww_image_optimizer_should_resize_other_image( $image->file ) ) {
			$new_dimensions = ewww_image_optimizer_resize_upload( $image->file );
		}

		list( $file, $msg, $converted, $original ) = ewww_image_optimizer( $image->file, 1, false, false, 'full' === $image->resize );

		// Gotta make sure we don't delete a pending record if the license is exceeded, so the license check goes first.
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			if ( 'exceeded' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = ewww_image_optimizer_credits_exceeded();
				delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
				delete_transient( 'ewww_image_optimizer_bulk_current_image' );
				ewwwio_ob_clean();
				die( wp_json_encode( $output ) );
			}
			if ( 'exceeded quota' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = ewww_image_optimizer_soft_quota_exceeded();
				delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
				delete_transient( 'ewww_image_optimizer_bulk_current_image' );
				ewwwio_ob_clean();
				die( wp_json_encode( $output ) );
			}
			if ( 'exceeded subkey' === get_transient( 'ewww_image_optimizer_cloud_status' ) ) {
				$output['error'] = esc_html__( 'Out of credits', 'ewww-image-optimizer' );
				delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
				delete_transient( 'ewww_image_optimizer_bulk_current_image' );
				ewwwio_ob_clean();
				die( wp_json_encode( $output ) );
			}
		}

		// Delete a pending record if the optimization failed for whatever reason.
		if ( ! $file && $image->id ) {
			ewww_image_optimizer_delete_pending_image( $image->id );
		}
		// Toggle a pending record if the optimization was webp-only.
		if ( true === $file && $image->id ) {
			ewww_image_optimizer_toggle_pending_image( $image->id );
		}

		// If this is a full size image and it was converted.
		if ( 'full' === $image->resize && false !== $converted ) {
			if ( empty( $meta ) || ! is_array( $meta ) ) {
				$meta = wp_get_attachment_metadata( $image->attachment_id );
			}
			$image->file      = $file;
			$image->converted = $original;
			$meta['file']     = _wp_relative_upload_path( $file );
			$image->update_converted_attachment( $meta );
			$meta = $image->convert_sizes( $meta );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::line( __( 'Optimized', 'ewww-image-optimizer' ) . ' ' . $image->file );
			WP_CLI::line( str_replace( array( '&nbsp;', '<br>' ), array( '', "\n" ), $msg ) );
		}
		$output['results'] .= sprintf( '<p>' . esc_html__( 'Optimized', 'ewww-image-optimizer' ) . ' <strong>%s</strong><br>', esc_html( $image->file ) );
		if ( ! empty( $ewwwio_resize_status ) ) {
			$output['results'] .= esc_html( $ewwwio_resize_status ) . '<br>';
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::line( $ewwwio_resize_status );
			}
		}
		$output['results'] .= "$msg</p>";

		// Do metadata update after full-size is processed, usually because of conversion or resizing.
		if ( 'full' === $image->resize && $image->attachment_id ) {
			ewwwio_debug_message( 'saving meta for ' . $image->attachment_id );
			if ( ! empty( $meta ) && is_array( $meta ) ) {
				clearstatcache();
				if ( ! empty( $image->file ) && is_file( $image->file ) ) {
					$meta['filesize'] = filesize( $image->file );
				}
				add_filter( 'as3cf_pre_update_attachment_metadata', '__return_true' );
				$meta_saved = wp_update_attachment_metadata( $image->attachment_id, $meta );
				if ( ! $meta_saved ) {
					ewwwio_debug_message( 'failed to save meta' );
				}
			}
		}

		// Pull the next image.
		$next_image = new EWWW_Image( $attachment, 'media' );

		// When we finish all the sizes, we stop the loop so we can fire off any filters for plugins that might need to take action when an image is updated.
		// The call to wp_get_attachment_metadata() will be done in a separate AJAX request for better reliability, giving it the full request time to complete.
		if ( $attachment && (int) $attachment !== (int) $next_image->attachment_id ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				remove_all_filters( 'as3cf_pre_update_attachment_metadata' );
				ewwwio_debug_message( 'saving attachment meta' );
				$meta = wp_get_attachment_metadata( $image->attachment_id );
				if ( ewww_image_optimizer_s3_uploads_enabled() ) {
					ewwwio_debug_message( 're-uploading to S3(_Uploads)' );
					ewww_image_optimizer_remote_push( $meta, $image->attachment_id );
				}
				if ( class_exists( 'Windows_Azure_Helper' ) && function_exists( 'windows_azure_storage_wp_generate_attachment_metadata' ) ) {
					$meta = windows_azure_storage_wp_generate_attachment_metadata( $meta, $image->attachment_id );
					if ( Windows_Azure_Helper::delete_local_file() && function_exists( 'windows_azure_storage_delete_local_files' ) ) {
						windows_azure_storage_delete_local_files( $meta, $image->attachment_id );
					}
				}
				wp_update_attachment_metadata( $image->attachment_id, $meta );
				do_action( 'ewww_image_optimizer_after_optimize_attachment', $image->attachment_id, $meta );
			} else {
				$batch_image_limit     = 1;
				$output['update_meta'] = (int) $attachment;
			}
		}

		// When an image (attachment) is done, pull the next attachment ID off the stack.
		if ( ( 'full' === $next_image->resize || empty( $next_image->resize ) ) && ! empty( $attachment ) && (int) $attachment !== (int) $next_image->attachment_id ) {
			ewwwio_debug_message( 'grabbing next attachment id' );
			ewww_image_optimizer_delete_queued_images( array( $attachment ) );
			if ( 1 === count( $attachments ) && 1 === (int) $batch_image_limit ) {
				$attachments = ewww_image_optimizer_get_queued_attachments( 'media', $batch_image_limit );
			} else {
				$attachment = (int) array_shift( $attachments ); // Pull the first image off the stack.
			}
			if ( ! empty( $attachments ) && is_array( $attachments ) ) {
				$attachment = (int) $attachments[0]; // Then grab the next one (if any are left).
			} else {
				$attachment = 0;
			}
			ewwwio_debug_message( "next id is $attachment" );
			$next_image = new EWWW_Image( $attachment, 'media' );
		}
		$image           = $next_image;
		$time_adjustment = $image->time_estimate();
	} // End while().

	ewwwio_debug_message( 'ending bulk loop for now' );
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	// Output how much time has elapsed since we started.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		/* translators: %s: number of seconds */
		WP_CLI::line( sprintf( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ), number_format_i18n( $elapsed, 2 ) ) );
		if ( ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
	}
	/* translators: %s: number of seconds */
	$output['results'] .= sprintf( '<p>' . esc_html( _n( 'Elapsed: %s second', 'Elapsed: %s seconds', $elapsed, 'ewww-image-optimizer' ) ) . '</p>', number_format_i18n( $elapsed, 1 ) );

	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		$debug_button       = esc_html__( 'Show Debug Output', 'ewww-image-optimizer' );
		$debug_id           = uniqid();
		$output['results'] .= "<button type='button' class='ewww-show-debug-meta button button-secondary' data-id='$debug_id'>$debug_button</button><div class='ewww-debug-meta-$debug_id' style='background-color:#f1f1f1;display:none;'>" . EWWW\Base::$debug_data . '</div>';
	}

	$output['add_to_total'] = (int) $add_to_total;

	if ( ! empty( $next_image->file ) ) {
		$next_file = esc_html( $next_image->file );
		// Generate the WP spinner image for display.
		$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
		if ( $next_file ) {
			$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . " <b>$next_file</b>&nbsp;<img src='$loading_image' /></p>";
		} else {
			$output['next_file'] = '<p>' . esc_html__( 'Optimizing', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>";
		}
	} else {
		$output['done'] = 1;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
			delete_transient( 'ewww_image_optimizer_bulk_current_image' );
			return false;
		}
	}

	ewww_image_optimizer_debug_log();
	delete_transient( 'ewww_image_optimizer_bulk_counter_measures' );
	delete_transient( 'ewww_image_optimizer_bulk_current_image' );
	ewwwio_memory( __FUNCTION__ );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}
	$output['current_time'] = time();
	ewwwio_ob_clean();
	die( wp_json_encode( $output ) );
}

/**
 * Called via AJAX to trigger any actions by other plugins.
 */
function ewww_image_optimizer_bulk_update_meta() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['attachment_id'] ) ) {
		die( wp_json_encode( array( 'success' => 0 ) ) );
	}
	$attachment_id = (int) $_REQUEST['attachment_id'];
	ewww_image_optimizer_post_optimize_attachment( $attachment_id );
	die( wp_json_encode( array( 'success' => 1 ) ) );
}

/**
 * Run metadata updates and other actions after an attachment is done processing.
 *
 * @param int $attachment_id The attachment ID number.
 */
function ewww_image_optimizer_post_optimize_attachment( $attachment_id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "running post opt for attachment $attachment_id" );
	$meta = wp_get_attachment_metadata( $attachment_id );
	$meta = ewww_image_optimizer_update_filesize_metadata( $meta, $attachment_id );
	remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_filesize_metadata', 9 );
	if ( ewww_image_optimizer_s3_uploads_enabled() ) {
		ewwwio_debug_message( 're-uploading to S3(_Uploads)' );
		ewww_image_optimizer_remote_push( $meta, $attachment_id );
	}
	if ( class_exists( 'Windows_Azure_Helper' ) && function_exists( 'windows_azure_storage_wp_generate_attachment_metadata' ) ) {
		$meta = windows_azure_storage_wp_generate_attachment_metadata( $meta, $attachment_id );
		if ( Windows_Azure_Helper::delete_local_file() && function_exists( 'windows_azure_storage_delete_local_files' ) ) {
			windows_azure_storage_delete_local_files( $meta, $attachment_id );
		}
	}
	global $ewww_attachment;
	$ewww_attachment['id']   = $attachment_id;
	$ewww_attachment['meta'] = $meta;
	add_filter( 'w3tc_cdn_update_attachment_metadata', 'ewww_image_optimizer_w3tc_update_files' );

	wp_update_attachment_metadata( $attachment_id, $meta );
	do_action( 'ewww_image_optimizer_after_optimize_attachment', $attachment_id, $meta );
}

/**
 * Called by javascript to cleanup after ourselves after a bulk operation.
 */
function ewww_image_optimizer_bulk_cleanup() {
	// Verify that an authorized user has started the optimizer.
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( '<p><b>' . esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) . '</b></p>' );
	}
	// All done, so we can update the bulk options with empty values.
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	// update_option( 'ewww_image_optimizer_bulk_attachments', '', false );.
	delete_transient( 'ewww_image_optimizer_skip_aux' );
	delete_transient( 'ewww_image_optimizer_force_reopt' );
	// Let the user know we are done.
	ewwwio_memory( __FUNCTION__ );
	ewwwio_ob_clean();
	if ( ! apply_filters( 'ewwwio_whitelabel', false ) ) {
		die(
			'<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b> - ' .
			( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ?
			'<a target="_blank" href="https://wordpress.org/support/plugin/ewww-image-optimizer/reviews/#new-post">' .
			esc_html__( 'Write a Review', 'ewww-image-optimizer' ) :
			esc_html__( 'Want more compression?', 'ewww-image-optimizer' ) . ' ' .
			'<a target="_blank" href="https://ewww.io/trial/">' .
			esc_html__( 'Get 5x more with a free trial', 'ewww-image-optimizer' )
			) .
			'</a></p>'
		);
	} else {
		die( '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>' );
	}
}

add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_bulk_script' );
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_tool_script' );
add_action( 'wp_ajax_ewww_bulk_async_init', 'ewww_image_optimizer_bulk_async_init' );
add_action( 'wp_ajax_ewww_bulk_async_get_status', 'ewww_image_optimizer_bulk_async_get_status' );
add_action( 'wp_ajax_bulk_scan', 'ewww_image_optimizer_media_scan' );
add_action( 'wp_ajax_bulk_init', 'ewww_image_optimizer_bulk_initialize' );
add_action( 'wp_ajax_bulk_loop', 'ewww_image_optimizer_bulk_loop' );
add_action( 'wp_ajax_ewww_bulk_update_meta', 'ewww_image_optimizer_bulk_update_meta' );
add_action( 'wp_ajax_bulk_cleanup', 'ewww_image_optimizer_bulk_cleanup' );
add_action( 'wp_ajax_bulk_quota_update', 'ewww_image_optimizer_bulk_quota_update' );
// Non-AJAX handler to clear all async queues.
add_action( 'admin_action_ewww_image_optimizer_clear_queue', 'ewww_image_optimizer_clear_queue' );
// Non-AJAX handler to pause all async queues.
add_action( 'admin_action_ewww_image_optimizer_pause_queue', 'ewww_image_optimizer_pause_queue' );
// Non-AJAX handler to resume all async queues.
add_action( 'admin_action_ewww_image_optimizer_resume_queue', 'ewww_image_optimizer_resume_queue' );
add_filter( 'ewww_image_optimizer_count_optimized_queries', 'ewww_image_optimizer_reduce_query_count' );
