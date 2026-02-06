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
	$naming_mode = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
	?>
	<div class="wrap">
	<h1><?php esc_html_e( 'Rename WebP Images', 'ewww-image-optimizer' ); ?></h1>
	<p>
		<?php if ( 'replace' === $naming_mode ) : ?>
			<?php esc_html_e( 'This tool will search your entire WordPress folder for images with a .webp extension appended and convert them to replacement naming.', 'ewww-image-optimizer' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'This tool will search your entire WordPress folder for images with a .webp extension in place of the original, and append the extension instead.', 'ewww-image-optimizer' ); ?>
		<?php endif; ?>
	</p>
	<div id="webp-loading"></div>
	<div id="webp-progressbar"></div>
	<div id="webp-counter"></div>
	<div id="webp-status"></div>
		<div id="bulk-forms">
		<form id="webp-start" class="webp-form" method="post" action="">
			<input id="webp-first" type="submit" class="button-secondary action" value="<?php esc_attr_e( 'Start Migration', 'ewww-image-optimizer' ); ?>" />
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
	$list                = array();
	$dir                 = get_home_path();
	$iterator            = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST );
	$start               = microtime( true );
	$file_counter        = 0;
	$naming_mode         = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
	$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );
	if ( ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	foreach ( $iterator as $path ) {
		if ( $path->isDir() ) {
			continue;
		} else {
			++$file_counter;
			$path = $path->getPathname();
			if ( ! str_ends_with( $path, '.webp' ) ) {
				continue;
			}
			$original_path = ewwwio()->remove_from_end( $path, '.webp' );
			$info          = pathinfo( $original_path );
			$ext           = strtolower( $info['extension'] ?? '' );
			$is_real_ext   = in_array( $ext, $original_extensions, true );
			$plugins_dir   = plugin_dir_path( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH );
			if ( str_contains( $path, $plugins_dir ) ) {
				continue;
			}
			if ( 'append' === $naming_mode ) {
				if ( $is_real_ext ) {
					continue;
				}
				foreach ( $original_extensions as $ext ) {
					if ( ewwwio_is_file( $original_path . '.' . $ext ) || ewwwio_is_file( $original_path . '.' . strtoupper( $ext ) ) ) {
						ewwwio_debug_message( "queued $path" );
						$list[] = $path;
						break;
					}
				}
			} elseif ( 'replace' === $naming_mode ) {
				if ( ! $is_real_ext ) {
					continue;
				}
				if ( ewwwio_is_file( $original_path ) ) {
					ewwwio_debug_message( "queued $path" );
					$list[] = $path;
				}
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
	if ( 'admin_page_ewww-image-optimizer-webp-migrate' !== $hook ) {
		return;
	}
	$images = ewww_image_optimizer_webp_scan();
	// Remove the images array from the db if it currently exists, and then store the new list in the database.
	if ( get_option( 'ewww_image_optimizer_webp_images' ) ) {
		delete_option( 'ewww_image_optimizer_webp_images' );
	}
	if ( get_option( 'ewww_image_optimizer_webp_skipped' ) ) {
		delete_option( 'ewww_image_optimizer_webp_skipped' );
	}
	wp_enqueue_script( 'ewwwwebpscript', plugins_url( '/includes/webp.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	$image_count = count( $images );
	// Submit a couple variables to the javascript to work with.
	wp_localize_script(
		'ewwwwebpscript',
		'ewww_vars',
		array(
			'ewww_wpnonce'     => wp_create_nonce( 'ewww-image-optimizer-webp' ),
			'interrupted'      => esc_html__( 'Operation Interrupted', 'ewww-image-optimizer' ),
			'retrying'         => esc_html__( 'Temporary failure, attempts remaining:', 'ewww-image-optimizer' ),
			'invalid_response' => esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ),
			'image_count'      => $image_count,
			'webp_images'      => $images,
		)
	);
}

/**
 * Called by javascript to initialize some output.
 */
function ewww_image_optimizer_webp_initialize() {
	// Verify that an authorized user has started the migration.
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	// Generate the WP spinner image for display.
	$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
	// Let the user know that we are beginning.
	ewwwio_ob_clean();
	die( '<p>' . esc_html__( 'Scanning', 'ewww-image-optimizer' ) . '&nbsp;<img src="' . esc_url( $loading_image ) . '" /></p>' );
}

/**
 * Called by javascript to process each image in the queue.
 */
function ewww_image_optimizer_webp_loop() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) ) ) );
	}
	if ( empty( $_REQUEST['webp_images'] ) || ! is_array( $_REQUEST['webp_images'] ) ) {
		ewwwio_ob_clean();
		die( wp_json_encode( array( 'error' => esc_html__( 'No images to migrate.', 'ewww-image-optimizer' ) ) ) );
	}
	// Retrieve the time when the migration starts.
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	ewwwio_debug_message( 'renaming images now' );
	$images_processed = 0;
	$images_renamed   = 0;
	$output           = '';
	$images           = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['webp_images'] ) );
	while ( $images ) {
		++$images_processed;
		ewwwio_debug_message( "processed $images_processed images so far" );
		$image = array_pop( $images );
		if ( ! ewwwio_is_file( $image ) ) {
			ewwwio_debug_message( "skipping $image because it is not a file, or not in a permitted folder" );
			continue;
		}
		$replace_base        = '';
		$skip                = true;
		$naming_mode         = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_naming_mode', 'append' );
		$extensionless       = ewwwio()->remove_from_end( $image, '.webp' );
		$info                = pathinfo( $extensionless );
		$ext                 = strtolower( $info['extension'] ?? '' );
		$original_extensions = array( 'png', 'jpg', 'jpeg', 'gif' );
		$is_real_ext         = in_array( $ext, $original_extensions, true );
		if ( 'replace' === $naming_mode ) {
			if ( $is_real_ext && ewwwio_is_file( $extensionless ) ) {
				$replace_base = $extensionless;
				$skip         = false;
			}
		} elseif ( 'append' === $naming_mode ) {
			if ( $is_real_ext ) {
				continue;
			}
			foreach ( $original_extensions as $img_ext ) {
				if ( ewwwio_is_file( $extensionless . '.' . $img_ext ) || ewwwio_is_file( $extensionless . '.' . strtoupper( $img_ext ) ) ) {
					if ( ! empty( $replace_base ) ) {
						$skip = true;
						break;
					}
					$replace_base = $extensionless . '.' . $img_ext;
					$skip         = false;
				}
			}
		}
		if ( $skip ) {
			if ( $replace_base ) {
				ewwwio_debug_message( "multiple replacement options for $image, not renaming" );
			} else {
				ewwwio_debug_message( "no match found for $image, strange..." );
			}
			/* translators: %s: a webp file */
			$output .= sprintf( esc_html__( 'Skipped %s, could not determine original image path', 'ewww-image-optimizer' ), esc_html( $image ) ) . '<br>';
		} else {
			$new_webp_path = ewww_image_optimizer_get_webp_path( $replace_base );
			if ( is_file( $new_webp_path ) ) {
				ewwwio_debug_message( "$new_webp_path already exists, deleting $image" );
				ewwwio_delete_file( $image );
				/* translators: 1: a webp file 2: another webp file */
				$output .= sprintf( esc_html__( '%1$s already exists, removed %2$s', 'ewww-image-optimizer' ), esc_html( $new_webp_path ), esc_html( $image ) ) . '<br>';
				continue;
			}
			++$images_renamed;
			ewwwio_debug_message( "renaming $image with match of $replace_base to $new_webp_path" );
			rename( $image, $new_webp_path );
		}
	} // End while().
	if ( $images_renamed ) {
		/* translators: %d: number of images */
		$output .= sprintf( esc_html__( 'Renamed %d WebP images', 'ewww-image-optimizer' ), (int) $images_renamed ) . '<br>';
	}

	// Calculate how much time has elapsed since we started.
	$elapsed = microtime( true ) - $started;
	ewwwio_debug_message( "took $elapsed seconds this time around" );
	// Store the updated list of images back in the database.
	echo wp_json_encode(
		array(
			'output' => $output,
		)
	);
	die();
}

/**
 * Called by javascript to cleanup after ourselves.
 */
function ewww_image_optimizer_webp_cleanup() {
	$permissions = apply_filters( 'ewww_image_optimizer_admin_permissions', '' );
	if ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['ewww_wpnonce'] ), 'ewww-image-optimizer-webp' ) || ! current_user_can( $permissions ) ) {
		ewwwio_ob_clean();
		die( esc_html__( 'Access token has expired, please reload the page.', 'ewww-image-optimizer' ) );
	}
	ewwwio_ob_clean();
	// and let the user know we are done.
	die( '<p><b>' . esc_html__( 'Finished', 'ewww-image-optimizer' ) . '</b></p>' );
}
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_webp_script' );
add_action( 'wp_ajax_webp_init', 'ewww_image_optimizer_webp_initialize' );
add_action( 'wp_ajax_webp_loop', 'ewww_image_optimizer_webp_loop' );
add_action( 'wp_ajax_webp_cleanup', 'ewww_image_optimizer_webp_cleanup' );
