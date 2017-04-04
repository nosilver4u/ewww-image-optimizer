<?php
/**
 * Functions to migrate WebP files from the extension replacement to extension appending.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Displays the bulk migration form.
 */
function ewww_image_optimizer_webp_migrate_preview() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
?>	<div class="wrap">
	<h1><?php esc_html_e( 'Migrate WebP Images', 'ewww-image-optimizer' ); ?></h1>
<?php		esc_html_e( 'The migration is split into two parts. First, the plugin needs to scan all folders for webp images. Once it has obtained the list of images to rename, it will proceed with the renaming' );
	$button_text = esc_attr__( 'Start Migration', 'ewww-image-optimizer' );
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// Create the html for the migration form and status divs.
?>
		<div id="webp-loading">
		</div>
		<div id="webp-progressbar"></div>
		<div id="webp-counter"></div>
		<div id="webp-status"></div>
			<div id="bulk-forms">
			<form id="webp-start" class="webp-form" method="post" action="">
				<input id="webp-first" type="submit" class="button-secondary action" value="<?php echo $button_text; ?>" />
			</form>
	</div>
<?php
}

/**
 * Scan a folder for webp images using the old naming scheme and return them as an array.
 *
 * @return array A list of images with the old naming scheme.
 */
function ewww_image_optimizer_webp_scan() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$list = array();
	$dir = get_home_path();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST );
	$start = microtime( true );
	$file_counter = 0;
	foreach ( $iterator as $path ) {
		if ( ewww_image_optimizer_stl_check() ) {
			set_time_limit( 0 );
		}
		$skip_optimized = false;
		if ( $path->isDir() ) {
			continue;
		} else {
			$file_counter++;
			$path = $path->getPathname();
			$newwebpformat = preg_replace( '/\.webp/', '', $path );
			if ( file_exists( $newwebpformat ) ) {
				continue;
			}
			if ( preg_match( '/\.webp$/', $path ) ) {
				ewwwio_debug_message( "queued $path" );
				$list[] = $path;
			}
		}
	}
	$end = microtime( true ) - $start;
	ewwwio_debug_message( "query time for $file_counter files (seconds): $end" );
	return $list;
}

/**
 * Prepares the migration and includes the javascript functions.
 *
 * @param string $hook The hook identifier for the current page.
 */
function ewww_image_optimizer_webp_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the migration page.
	if ( 'admin_page_ewww-image-optimizer-webp-migrate' != $hook ) {
		return;
	}
	$images = ewww_image_optimizer_webp_scan();
	// Remove the images array from the db if it currently exists, and then store the new list in the database.
	if ( get_option( 'ewww_image_optimizer_webp_images' ) ) {
		delete_option( 'ewww_image_optimizer_webp_images' );
	}
	add_option( 'ewww_image_optimizer_webp_images', '', '', 'no' );
	update_option( 'ewww_image_optimizer_webp_images', $images );
	wp_enqueue_script( 'ewwwwebpscript', plugins_url( '/includes/webp.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	$image_count = count( $images );
	// Submit a couple variables to the javascript to work with.
	wp_localize_script( 'ewwwwebpscript', 'ewww_vars', array(
			'ewww_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-webp' ),
		)
	);
}

/**
 * Called by javascript to initialize some output.
 */
function ewww_image_optimizer_webp_initialize() {
	// Verify that an authorized user has started the migration.
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	if ( get_option( 'ewww_image_optimizer_webp_skipped' ) ) {
		delete_option( 'ewww_image_optimizer_webp_skipped' );
	}
	add_option( 'ewww_image_optimizer_webp_skipped', '', '', 'no' );
	// Generate the WP spinner image for display.
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// Let the user know that we are beginning.
	echo '<p>' . esc_html__( 'Scanning', 'ewww-image-optimizer' ) . "&nbsp;<img src='$loading_image' /></p>";
	die();
}

/**
 * Called by javascript to process each image in the queue.
 */
function ewww_image_optimizer_webp_loop() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	// Retrieve the time when the migration starts.
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	$images = array();
	ewwwio_debug_message( 'renaming images now' );
	$images_processed = 0;
	$images_skipped = '';
	$images = get_option( 'ewww_image_optimizer_webp_images' );
	if ( $images ) {
		/* translators: %d: number of images */
		printf( esc_html__( '%d Webp images left to rename.', 'ewww-image-optimizer' ), count( $images ) );
		echo '<br>';
	}
	while ( $images ) {
		$images_processed++;
		ewwwio_debug_message( "processed $images_processed images so far" );
		if ( $images_processed > 1000 ) {
			ewwwio_debug_message( 'hit 1000, breaking loop' );
			break;
		}
		$image = array_pop( $images );
		$replace_base = '';
		$skip = true;
		$pngfile = preg_replace( '/webp$/', 'png', $image );
		$upngfile = preg_replace( '/webp$/', 'PNG', $image );
		$jpgfile = preg_replace( '/webp$/', 'jpg', $image );
		$jpegfile = preg_replace( '/webp$/', 'jpeg', $image );
		$ujpgfile = preg_replace( '/webp$/', 'JPG', $image );
		if ( file_exists( $pngfile ) ) {
			$replace_base = $pngfile;
			$skip = false;
		} if ( file_exists( $upngfile ) ) {
			if ( empty( $replace_base ) ) {
				$replace_base = $upngfile;
				$skip = false;
			} else {
				$skip = true;
			}
		} if ( file_exists( $jpgfile ) ) {
			if ( empty( $replace_base ) ) {
				$replace_base = $jpgfile;
				$skip = false;
			} else {
				$skip = true;
			}
		} if ( file_exists( $jpegfile ) ) {
			if ( empty( $replace_base ) ) {
				$replace_base = $jpegfile;
				$skip = false;
			} else {
				$skip = true;
			}
		} if ( file_exists( $ujpgfile ) ) {
			if ( empty( $replace_base ) ) {
				$replace_base = $ujpgfile;
				$skip = false;
			} else {
				$skip = true;
			}
		}
		if ( $skip ) {
			if ( $replace_base ) {
				ewwwio_debug_message( "multiple replacement options for $image, not renaming" );
			} else {
				ewwwio_debug_message( "no match found for $image, strange..." );
			}
			$images_skipped .= "$image<br>";
		} else {
			ewwwio_debug_message( "renaming $image with match of $replace_base" );
			rename( $image, $replace_base . '.webp' );
		}
	} // End while().
	if ( $images_skipped ) {
		update_option( 'ewww_image_optimizer_webp_skipped', get_option( 'ewww_image_optimizer_webp_skipped' ) . $images_skipped );
	}
	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "took $elapsed seconds this time around" );
	// Store the updated list of images back in the database.
	update_option( 'ewww_image_optimizer_webp_images', $images );
	ewww_image_optimizer_debug_log();
	die();
}

/**
 * Called by javascript to cleanup after ourselves.
 */
function ewww_image_optimizer_webp_cleanup() {
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	$skipped = get_option( 'ewww_image_optimizer_webp_skipped' );
	// All done, so we can remove the webp options...
	delete_option( 'ewww_image_optimizer_webp_images' );
	delete_option( 'ewww_image_optimizer_webp_skipped', '' );
	if ( $skipped ) {
		echo '<p><b>' . esc_html__( 'Skipped:', 'ewww-image-optimizer' ) . '</b></p>';
		echo "<p>$skipped</p>";
	}
	// and let the user know we are done.
	echo '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>';
	die();
}
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_webp_script' );
add_action( 'wp_ajax_webp_init', 'ewww_image_optimizer_webp_initialize' );
add_action( 'wp_ajax_webp_loop', 'ewww_image_optimizer_webp_loop' );
add_action( 'wp_ajax_webp_cleanup', 'ewww_image_optimizer_webp_cleanup' );
?>
