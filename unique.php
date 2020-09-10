<?php
/**
 * Unique functions for Standard EWWW I.O. plugins.
 *
 * This file contains functions that are unique to the regular EWWW IO plugin.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Installation routine for PNGOUT.
add_action( 'admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout_wrapper' );
// AJAX action hook to dismiss the exec notice May be extended to other notices in the future.
add_action( 'wp_ajax_ewww_dismiss_exec_notice', 'ewww_image_optimizer_dismiss_exec_notice' );
// Removes the binaries when the plugin is deactivated.
register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_remove_binaries' );

/**
 * Make sure the cloud constant is defined.
 *
 * Check to see if the cloud constant is defined (which would mean we've already run init) and set it properly if not.
 */
function ewww_image_optimizer_cloud_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if (
		! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 &&
		ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10
	) {
		define( 'EWWW_IMAGE_OPTIMIZER_CLOUD', true );
	} elseif ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
		define( 'EWWW_IMAGE_OPTIMIZER_CLOUD', false );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Initializes settings for the local tools, and runs the checks for tools on select pages.
 */
function ewww_image_optimizer_exec_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $exactdn;
	// If cloud is fully enabled, we're going to skip all the checks related to the bundled tools.
	if ( EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		ewwwio_debug_message( 'cloud options enabled, shutting off binaries' );
		ewww_image_optimizer_disable_tools();
	} elseif (
		defined( 'WPCOMSH_VERSION' ) ||
		! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ||
		defined( 'WPE_PLUGIN_VERSION' ) ||
		defined( 'FLYWHEEL_CONFIG_DIR' ) ||
		defined( 'KINSTAMU_VERSION' ) ||
		defined( 'WPNET_INIT_PLUGIN_VERSION' )
	) {
		if (
			! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
			( ! is_object( $exactdn ) || ! $exactdn->get_exactdn_domain() )
		) {
			add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_hosting_requires_api' );
			add_action( 'admin_notices', 'ewww_image_optimizer_notice_hosting_requires_api' );
		}
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
			define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', true );
		}
		ewwwio_debug_message( 'WPE/wp.com/pantheon/flywheel site, disabling tools' );
		ewww_image_optimizer_disable_tools();
		// Check if this is an unsupported OS (not Linux or Mac OSX or FreeBSD or Windows or SunOS).
	} elseif ( 'Linux' !== PHP_OS && 'Darwin' !== PHP_OS && 'FreeBSD' !== PHP_OS && 'WINNT' !== PHP_OS && 'SunOS' !== PHP_OS ) {
		// Call the function to display a notice.
		add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_os' );
		add_action( 'admin_notices', 'ewww_image_optimizer_notice_os' );
		// Turn off all the tools.
		ewwwio_debug_message( 'unsupported OS, disabling tools: ' . PHP_OS );
		ewww_image_optimizer_disable_tools();
	} else {
		add_action( 'load-upload.php', 'ewww_image_optimizer_tool_init', 9 );
		add_action( 'load-media-new.php', 'ewww_image_optimizer_tool_init' );
		add_action( 'load-media_page_ewww-image-optimizer-bulk', 'ewww_image_optimizer_tool_init' );
		add_action( 'load-settings_page_ewww-image-optimizer-options', 'ewww_image_optimizer_tool_init' );
		add_action( 'load-plugins.php', 'ewww_image_optimizer_tool_init' );
		add_action( 'load-ims_gallery_page_ewww-ims-optimize', 'ewww_image_optimizer_tool_init' );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Check for binary installation and availability.
 */
function ewww_image_optimizer_tool_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure the bundled tools are installed.
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' ) ) {
		ewww_image_optimizer_install_tools();
	}
	// Check for optimization utilities and register a notice if something is missing.
	add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_utils' );
	add_action( 'admin_notices', 'ewww_image_optimizer_notice_utils' );
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) && EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		ewwwio_debug_message( 'cloud options enabled, shutting off binaries' );
		ewww_image_optimizer_disable_tools();
	}
}

/**
 * Set some default option values.
 */
function ewww_image_optimizer_set_defaults() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Set defaults for all options that need to be autoloaded.
	add_option( 'ewww_image_optimizer_noauto', false );
	add_option( 'ewww_image_optimizer_disable_editor', false );
	add_option( 'ewww_image_optimizer_debug', false );
	add_option( 'ewww_image_optimizer_metadata_remove', true );
	add_option( 'ewww_image_optimizer_jpg_level', '10' );
	add_option( 'ewww_image_optimizer_png_level', '10' );
	add_option( 'ewww_image_optimizer_gif_level', '10' );
	add_option( 'ewww_image_optimizer_pdf_level', '0' );
	add_option( 'ewww_image_optimizer_exactdn', false );
	add_option( 'ewww_image_optimizer_exactdn_plan_id', 0 );
	add_option( 'exactdn_all_the_things', true );
	add_option( 'exactdn_lossy', true );
	add_option( 'exactdn_exclude', '' );
	add_option( 'ewww_image_optimizer_lazy_load', false );
	add_option( 'ewww_image_optimizer_ll_exclude', '' );
	add_option( 'ewww_image_optimizer_disable_pngout', true );
	add_option( 'ewww_image_optimizer_optipng_level', 2 );
	add_option( 'ewww_image_optimizer_pngout_level', 2 );
	add_option( 'ewww_image_optimizer_webp_for_cdn', false );
	add_option( 'ewww_image_optimizer_force_gif2webp', false );
	add_option( 'ewww_image_optimizer_picture_webp', false );
	add_option( 'ewww_image_optimizer_webp_rewrite_exclude', '' );

	// Set network defaults.
	add_site_option( 'ewww_image_optimizer_metadata_remove', true );
	add_site_option( 'ewww_image_optimizer_jpg_level', '10' );
	add_site_option( 'ewww_image_optimizer_png_level', '10' );
	add_site_option( 'ewww_image_optimizer_gif_level', '10' );
	add_site_option( 'ewww_image_optimizer_pdf_level', '0' );
	add_site_option( 'ewww_image_optimizer_disable_pngout', true );
	add_site_option( 'ewww_image_optimizer_optipng_level', 2 );
	add_site_option( 'ewww_image_optimizer_pngout_level', 2 );
	add_site_option( 'exactdn_all_the_things', true );
	add_site_option( 'exactdn_lossy', true );
}

/**
 * Let the user know the plugin requires API/ExactDN to operate at their webhost.
 */
function ewww_image_optimizer_notice_hosting_requires_api() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$settings_url = network_admin_url( 'settings.php?page=ewww-image-optimizer-options' );
	} else {
		$settings_url = admin_url( 'options-general.php?page=ewww-image-optimizer-options' );
	}
	if ( defined( 'WPCOMSH_VERSION' ) ) {
		$webhost = 'WordPress.com';
	} elseif ( ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
		$webhost = 'Pantheon';
	} elseif ( defined( 'WPE_PLUGIN_VERSION' ) ) {
		$webhost = 'WP Engine';
	} elseif ( defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
		$webhost = 'Flywheel';
	} elseif ( defined( 'KINSTAMU_VERSION' ) ) {
		$webhost = 'Kinsta';
	} elseif ( defined( 'WPNET_INIT_PLUGIN_VERSION' ) ) {
		$webhost = 'WPNET';
	} else {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
		return;
	}
	echo "<div id='ewww-image-optimizer-warning-exec' class='notice notice-warning is-dismissible'><p>" .
		/* translators: %s: Name of a web host, like WordPress.com or Pantheon. */
		sprintf( esc_html__( 'Normally, %s sites require cloud-based optimization, because server-based optimization is disallowed. However, we are trying something new, and offering free cloud-based JPG compression. Those who upgrade to our premium service receive much higher compression, PNG/GIF/PDF compression, WebP conversion, and image backups.', 'ewww-image-optimizer' ), esc_html( $webhost ) ) .
		'<br><strong>' .
		/* translators: %s: link to 'start your free trial' */
		sprintf( esc_html__( 'Dismiss this notice to continue with free cloud-based JPG compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' );
	ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
	echo '</strong></p></div>';
	?>
<script>
	jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {
		var ewww_dismiss_exec_data = {
			action: 'ewww_dismiss_exec_notice',
		};
		jQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {
			if (response) {
				console.log(response);
			}
		});
	});
</script>
	<?php
}

/**
 * Tells the user they are on an unsupported operating system.
 */
function ewww_image_optimizer_notice_os() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div id='ewww-image-optimizer-warning-os' class='notice notice-error'><p><strong>" .
		esc_html__( 'The free mode of EWWW Image Optimizer is only supported on Linux, FreeBSD, Mac OSX, Solaris, and Windows.', 'ewww-image-optimizer' ) .
		'</strong><br>' .
		"<a href='https://ewww.io/plans/'>" . esc_html__( 'Visit our site to start your free trial with cloud-based optimization.', 'ewww-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Generates the source and destination paths for the executables that we bundle with the plugin.
 *
 * Paths are determined based on the operating system and architecture.
 */
function ewww_image_optimizer_install_paths() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$tool_path = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( PHP_OS === 'WINNT' ) {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'gifsicle.exe';
		$optipng_src  = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'optipng.exe';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'jpegtran.exe';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngquant.exe';
		$webp_src     = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'cwebp.exe';
		$gifsicle_dst = $tool_path . 'gifsicle.exe';
		$optipng_dst  = $tool_path . 'optipng.exe';
		$jpegtran_dst = $tool_path . 'jpegtran.exe';
		$pngquant_dst = $tool_path . 'pngquant.exe';
		$webp_dst     = $tool_path . 'cwebp.exe';
	}
	if ( PHP_OS === 'Darwin' ) {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'gifsicle-mac';
		$optipng_src  = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'optipng-mac';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'jpegtran-mac';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngquant-mac';
		$webp_src     = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'cwebp-mac14';
		$gifsicle_dst = $tool_path . 'gifsicle';
		$optipng_dst  = $tool_path . 'optipng';
		$jpegtran_dst = $tool_path . 'jpegtran';
		$pngquant_dst = $tool_path . 'pngquant';
		$webp_dst     = $tool_path . 'cwebp';
	}
	if ( PHP_OS === 'SunOS' ) {
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'gifsicle-sol';
		$optipng_src  = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'optipng-sol';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'jpegtran-sol';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngquant-sol';
		$webp_src     = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'cwebp-sol';
		$gifsicle_dst = $tool_path . 'gifsicle';
		$optipng_dst  = $tool_path . 'optipng';
		$jpegtran_dst = $tool_path . 'jpegtran';
		$pngquant_dst = $tool_path . 'pngquant';
		$webp_dst     = $tool_path . 'cwebp';
	}
	if ( PHP_OS === 'FreeBSD' ) {
		if ( ewww_image_optimizer_function_exists( 'php_uname' ) ) {
			$arch_type = php_uname( 'm' );
			ewwwio_debug_message( "CPU architecture: $arch_type" );
		} else {
			ewwwio_debug_message( 'CPU architecture unknown, php_uname disabled' );
		}
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'gifsicle-fbsd';
		$optipng_src  = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'optipng-fbsd';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'jpegtran-fbsd';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngquant-fbsd';
		$webp_src     = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'cwebp-fbsd';
		$gifsicle_dst = $tool_path . 'gifsicle';
		$optipng_dst  = $tool_path . 'optipng';
		$jpegtran_dst = $tool_path . 'jpegtran';
		$pngquant_dst = $tool_path . 'pngquant';
		$webp_dst     = $tool_path . 'cwebp';
	}
	if ( PHP_OS === 'Linux' ) {
		if ( ewww_image_optimizer_function_exists( 'php_uname' ) ) {
			$arch_type = php_uname( 'm' );
			ewwwio_debug_message( "CPU architecture: $arch_type" );
		} else {
			ewwwio_debug_message( 'CPU architecture unknown, php_uname disabled' );
		}
		$gifsicle_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'gifsicle-linux';
		$optipng_src  = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'optipng-linux';
		$jpegtran_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'jpegtran-linux';
		$pngquant_src = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngquant-linux';
		$webp_src     = EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'cwebp-linux';
		$gifsicle_dst = $tool_path . 'gifsicle';
		$optipng_dst  = $tool_path . 'optipng';
		$jpegtran_dst = $tool_path . 'jpegtran';
		$pngquant_dst = $tool_path . 'pngquant';
		$webp_dst     = $tool_path . 'cwebp';
	}
	ewwwio_debug_message( "generated paths:<br>$jpegtran_src<br>$optipng_src<br>$gifsicle_src<br>$pngquant_src<br>$webp_src<br>$jpegtran_dst<br>$optipng_dst<br>$gifsicle_dst<br>$pngquant_dst<br>$webp_dst" );
	ewwwio_memory( __FUNCTION__ );
	return array( $jpegtran_src, $optipng_src, $gifsicle_src, $pngquant_src, $webp_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst, $pngquant_dst, $webp_dst );
}

/**
 * Makes sure permissions on a file/folder are adequate.
 *
 * @param string $file The file or folder to test.
 * @param string $minimum The minimum file permissions needed.
 * @return bool True if permissions are equal to or better than what is required.
 */
function ewww_image_optimizer_check_permissions( $file, $minimum ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$perms = fileperms( $file );
	ewwwio_debug_message( "permissions for $file: " . substr( sprintf( '%o', $perms ), -4 ) );
	if ( ! ewwwio_is_file( $file ) ) {
		ewwwio_debug_message( 'permissions check failed, file not found' );
		return false;
	}
	if ( is_readable( $file ) && is_executable( $file ) ) {
		ewwwio_debug_message( 'permissions ok' );
		return true;
	}
	ewwwio_debug_message( 'permissions insufficient' );
	return false;
}

/**
 * Alert the user when the tool folder could not be created.
 */
function ewww_image_optimizer_tool_folder_notice() {
	echo "<div id='ewww-image-optimizer-warning-tool-folder-create' class='notice notice-error'><p><strong>" . esc_html__( 'EWWW Image Optimizer could not create the tool folder', 'ewww-image-optimizer' ) . ': ' . esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) . '.</strong> ' . esc_html__( 'Please adjust permissions or create the folder', 'ewww-image-optimizer' ) . '.</p></div>';
}

/**
 * Alert the user when permissions on the tool folder are insufficient.
 */
function ewww_image_optimizer_tool_folder_permissions_notice() {
	echo "<div id='ewww-image-optimizer-warning-tool-folder-permissions' class='notice notice-error'><p><strong>" .
		/* translators: %s: Folder location where executables should be installed */
		sprintf( esc_html__( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) . '.</strong> ' .
		esc_html__( 'Please adjust permissions on the folder. If you have installed the tools elsewhere, use the override to skip the bundled tools.', 'ewww-image-optimizer' ) . ' ' .
		/* translators: s: Installation Instructions (link) */
		sprintf( esc_html__( 'For more details, see the %s.', 'ewww-image-optimizer' ), "<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'>" . esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
}

/**
 * Alert the user when tool installation fails.
 */
function ewww_image_optimizer_tool_installation_failed_notice() {
	echo "<div id='ewww-image-optimizer-warning-tool-install' class='notice notice-error'><p><strong>" .
		/* translators: %s: Folder location where executables should be installed */
		sprintf( esc_html__( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), esc_html( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) . '.</strong> ' .
		/* translators: %s: Installation Instructions */
		sprintf( esc_html__( 'For more details, see the %s.', 'ewww-image-optimizer' ), "<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'>" . esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
}

/**
 * Installs the executables that are bundled with the plugin.
 */
function ewww_image_optimizer_install_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( 'Checking/Installing tools in ' . EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	$toolfail = false;
	if ( ! is_dir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) && is_writable( dirname( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) ) {
		ewwwio_debug_message( 'folder does not exist, creating...' );
		if ( ! wp_mkdir_p( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
			ewwwio_debug_message( 'could not create folder' );
			return;
		}
	} elseif ( is_dir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
		if ( ! is_writable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
			ewwwio_debug_message( 'wp-content/ewww is not writable, not installing anything' );
			return;
		} elseif ( ! is_executable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) && PHP_OS !== 'WINNT' ) {
			ewwwio_debug_message( 'wp-content/ewww is not executable (non-Windows), not installing anything' );
			return;
		} elseif ( ! is_readable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
			ewwwio_debug_message( 'wp-content/ewww is not readable, not installing anything' );
			return;
		}
		$ewww_perms = substr( sprintf( '%o', fileperms( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ), -4 );
		ewwwio_debug_message( "wp-content/ewww permissions: $ewww_perms" );
	} else {
		ewwwio_debug_message( 'wp-content is not writable, and the ewww folder does not exist' );
		return;
	}
	list( $jpegtran_src, $optipng_src, $gifsicle_src, $pngquant_src, $webp_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst, $pngquant_dst, $webp_dst ) = ewww_image_optimizer_install_paths();
	$skip = ewww_image_optimizer_skip_tools();
	if ( ! $skip['jpegtran'] && ( ! file_exists( $jpegtran_dst ) || filesize( $jpegtran_dst ) !== filesize( $jpegtran_src ) ) ) {
		ewwwio_debug_message( 'jpegtran not found or different size, installing' );
		if ( ! copy( $jpegtran_src, $jpegtran_dst ) ) {
			$toolfail = true;
			ewwwio_debug_message( 'could not copy jpegtran' );
		}
	}
	if ( ! $skip['gifsicle'] && ( ! file_exists( $gifsicle_dst ) || filesize( $gifsicle_dst ) !== filesize( $gifsicle_src ) ) ) {
		ewwwio_debug_message( 'gifsicle not found or different size, installing' );
		if ( ! copy( $gifsicle_src, $gifsicle_dst ) ) {
			$toolfail = true;
			ewwwio_debug_message( 'could not copy gifsicle' );
		}
	}
	if ( ! $skip['optipng'] && ( ! file_exists( $optipng_dst ) || filesize( $optipng_dst ) !== filesize( $optipng_src ) ) ) {
		ewwwio_debug_message( 'optipng not found or different size, installing' );
		if ( ! copy( $optipng_src, $optipng_dst ) ) {
			$toolfail = true;
			ewwwio_debug_message( 'could not copy optipng' );
		}
	}
	if ( ! $skip['pngquant'] && ( ! file_exists( $pngquant_dst ) || filesize( $pngquant_dst ) !== filesize( $pngquant_src ) ) ) {
		ewwwio_debug_message( 'pngquant not found or different size, installing' );
		if ( ! copy( $pngquant_src, $pngquant_dst ) ) {
			$toolfail = true;
			ewwwio_debug_message( 'could not copy pngquant' );
		}
	}
	if ( ! $skip['webp'] && ( ! file_exists( $webp_dst ) || filesize( $webp_dst ) !== filesize( $webp_src ) ) ) {
		ewwwio_debug_message( 'webp not found or different size, installing' );
		if ( ! copy( $webp_src, $webp_dst ) ) {
			$toolfail = true;
			ewwwio_debug_message( 'could not copy webp' );
		}
	}

	if ( PHP_OS !== 'WINNT' && ! $toolfail ) {
		ewwwio_debug_message( 'Linux/UNIX style OS, checking permissions' );
		if ( ! $skip['jpegtran'] && ! ewww_image_optimizer_check_permissions( $jpegtran_dst, 'rwxr-xr-x' ) ) {
			if ( ! is_writable( $jpegtran_dst ) || ! chmod( $jpegtran_dst, 0755 ) ) {
				$toolfail = true;
				ewwwio_debug_message( 'could not set jpegtran permissions' );
			}
		}
		if ( ! $skip['gifsicle'] && ! ewww_image_optimizer_check_permissions( $gifsicle_dst, 'rwxr-xr-x' ) ) {
			if ( ! is_writable( $gifsicle_dst ) || ! chmod( $gifsicle_dst, 0755 ) ) {
				$toolfail = true;
				ewwwio_debug_message( 'could not set gifsicle permissions' );
			}
		}
		if ( ! $skip['optipng'] && ! ewww_image_optimizer_check_permissions( $optipng_dst, 'rwxr-xr-x' ) ) {
			if ( ! is_writable( $optipng_dst ) || ! chmod( $optipng_dst, 0755 ) ) {
				$toolfail = true;
				ewwwio_debug_message( 'could not set optipng permissions' );
			}
		}
		if ( ! $skip['pngquant'] && ! ewww_image_optimizer_check_permissions( $pngquant_dst, 'rwxr-xr-x' ) ) {
			if ( ! is_writable( $pngquant_dst ) || ! chmod( $pngquant_dst, 0755 ) ) {
				$toolfail = true;
				ewwwio_debug_message( 'could not set pngquant permissions' );
			}
		}
		if ( ! $skip['webp'] && ! ewww_image_optimizer_check_permissions( $webp_dst, 'rwxr-xr-x' ) ) {
			if ( ! is_writable( $webp_dst ) || ! chmod( $webp_dst, 0755 ) ) {
				$toolfail = true;
				ewwwio_debug_message( 'could not set webp permissions' );
			}
		}
	}
	if ( $toolfail ) {
		add_action( 'network_admin_notices', 'ewww_image_optimizer_tool_installation_failed_notice' );
		add_action( 'admin_notices', 'ewww_image_optimizer_tool_installation_failed_notice' );
	}
	ewwwio_memory( __FUNCTION__ );
}

/**
 * Checks which tools should be skipped.
 *
 * @return array {
 *     A list of tools to skip.
 *
 *     @type bool $jpegtran
 *     @type bool $optipng
 *     @type bool $gifsicle
 *     @type bool $pngout
 *     @type bool $pngquant
 *     @type bool $webp
 * }
 */
function ewww_image_optimizer_skip_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$skip['jpegtran'] = false;
	$skip['optipng']  = false;
	$skip['gifsicle'] = false;
	// Except these which are off by default.
	$skip['pngout']   = true;
	$skip['pngquant'] = true;
	$skip['webp']     = true;
	// If the user has disabled a tool, we aren't going to bother checking to see if it is there.
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 || ( defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) && EWWW_IMAGE_OPTIMIZER_NOEXEC ) ) {
		$skip['jpegtran'] = true;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) || ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) ) {
		$skip['optipng'] = true;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$skip['gifsicle'] = true;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$skip['pngout'] = false;
	}
	if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		$skip['pngquant'] = false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ! ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) ) {
		$skip['webp'] = false;
	}
	foreach ( $skip as $tool => $disabled ) {
		if ( ! $disabled ) {
			ewwwio_debug_message( "enabled: $tool" );
		}
	}
	return $skip;
}

/**
 * See if the plugin should avoid the exec() function.
 */
function ewww_image_optimizer_define_noexec() {
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
		return;
	}
	// Check if exec is disabled.
	if ( ewww_image_optimizer_exec_check() ) {
		define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', true );
		ewwwio_debug_message( 'exec seems to be disabled' );
		ewww_image_optimizer_disable_tools();
	} else {
		define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', false );
	}
}

/**
 * Disables local compression when exec notice is dismissed by ExactDN user.
 */
function ewww_image_optimizer_dismiss_exec_notice() {
	ewwwio_ob_clean();
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Verify that the user is properly authorized.
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'Access denied.', 'ewww-image-optimizer' ) );
	}
	update_option( 'ewww_image_optimizer_jpg_level', 10 );
	update_option( 'ewww_image_optimizer_png_level', 0 );
	update_option( 'ewww_image_optimizer_gif_level', 0 );
	update_option( 'ewww_image_optimizer_pdf_level', 0 );
	update_option( 'ewww_image_optimizer_dismiss_exec_notice', 1 );
	update_site_option( 'ewww_image_optimizer_dismiss_exec_notice', 1 );
	wp_die();
}

/**
 * Checks for safe mode and exec, then displays an error if needed.
 *
 * @param string $quiet Optional. Use 'quiet' to suppress warning messages.
 */
function ewww_image_optimizer_notice_utils( $quiet = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Check if exec is disabled.
	if ( ewww_image_optimizer_exec_check() ) {
		// Need to be a little particular with the quiet parameter.
		if ( 'quiet' !== $quiet && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_dismiss_exec_notice' ) ) {
			$exactdn_dismiss = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ? true : false;
			ob_start();
			$notice_class  = 'notice notice-warning is-dismissible';
			$notice_action = __( 'An API key or Easy IO subscription will allow you to offload the compression to our dedicated servers instead.', 'ewww-image-optimizer' );
			/* translators: %s: link to 'start your free trial' */
			$notice_action = sprintf( __( 'You may ask your system administrator to enable exec(), dismiss this notice to continue with free cloud-based compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . __( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
				$notice_action = __( 'Sites that use Easy IO already have built-in image optimization and may dismiss this notice to disable local compression.', 'ewww-image-optimizer' );
			}
			// Display a warning if exec() is disabled, can't run local tools without it.
			echo "<div id='ewww-image-optimizer-warning-exec' class='" . esc_attr( $notice_class ) . "'><p>" .
				esc_html__( ' Normally, sites where the exec() function is banned require cloud-based optimization, because free server-based optimization will not work. However, we are trying something new, and offering free cloud-based JPG compression. Those who upgrade to our premium service receive much higher compression, PNG/GIF/PDF compression, WebP conversion, and image backups.', 'ewww-image-optimizer' ) . '<br>' .
				'<strong>' .
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) || get_option( 'easyio_exactdn' ) ?
				esc_html__( 'Sites that use Easy IO already have built-in image optimization and may dismiss this notice to disable local compression.', 'ewww-image-optimizer' )
				:
				/* translators: %s: link to 'start your free trial' */
				sprintf( esc_html__( 'You may ask your system administrator to enable exec(), dismiss this notice to continue with free cloud-based compression or %s.', 'ewww-image-optimizer' ), "<a href='https://ewww.io/plans/'>" . esc_html__( 'start your premium trial', 'ewww-image-optimizer' ) . '</a>' )
			);
			ewwwio_help_link( 'https://docs.ewww.io/article/29-what-is-exec-and-why-do-i-need-it', '592dd12d0428634b4a338c39' );
			echo '</strong></p></div>';
			echo
				"<script>\n" .
				"jQuery(document).on('click', '#ewww-image-optimizer-warning-exec .notice-dismiss', function() {\n" .
					"\tvar ewww_dismiss_exec_data = {\n" .
						"\t\taction: 'ewww_dismiss_exec_notice',\n" .
					"\t};\n" .
					"\tjQuery.post(ajaxurl, ewww_dismiss_exec_data, function(response) {\n" .
						"\t\tif (response) {\n" .
							"\t\t\tconsole.log(response);\n" .
						"\t\t}\n" .
					"\t});\n" .
				"});\n" .
				"</script>\n";
			if (
				ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) &&
				! ewww_image_optimizer_get_option( 'ewww_image_optimizer_local_mode' )
			) {
				ob_end_clean();
			} else {
				ob_end_flush();
			}
		}
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
			define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', true );
		}
		ewwwio_debug_message( 'exec seems to be disabled' );
		ewww_image_optimizer_disable_tools();
		return;
	} else {
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_NOEXEC' ) ) {
			define( 'EWWW_IMAGE_OPTIMIZER_NOEXEC', false );
		}
	}

	$skip = ewww_image_optimizer_skip_tools();
	// Attempt to retrieve values for utility paths, and store them in the appropriate variables.
	$required = ewww_image_optimizer_path_check( ! $skip['jpegtran'], ! $skip['optipng'], ! $skip['gifsicle'], ! $skip['pngout'], ! $skip['pngquant'], ! $skip['webp'] );
	$missing  = array();
	// Go through each of the required tools.
	foreach ( $required as $key => $req ) {
		// if the tool wasn't found, add it to the $missing array if we are supposed to check the tool in question.
		switch ( $key ) {
			case 'JPEGTRAN':
				if ( ! $skip['jpegtran'] && empty( $req ) ) {
					$missing[] = 'jpegtran';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
			case 'OPTIPNG':
				if ( ! $skip['optipng'] && empty( $req ) ) {
					$missing[] = 'optipng';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
			case 'GIFSICLE':
				if ( ! $skip['gifsicle'] && empty( $req ) ) {
					$missing[] = 'gifsicle';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
			case 'PNGOUT':
				if ( ! $skip['pngout'] && empty( $req ) ) {
					$missing[] = 'pngout';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
			case 'PNGQUANT':
				if ( ! $skip['pngquant'] && empty( $req ) ) {
					$missing[] = 'pngquant';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
			case 'CWEBP':
				if ( ! $skip['webp'] && empty( $req ) ) {
					$missing[] = 'webp';
					$req       = false;
				}
				if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_' . $key ) ) {
					ewwwio_debug_message( "defining EWWW_IMAGE_OPTIMIZER_$key" );
					define( 'EWWW_IMAGE_OPTIMIZER_' . $key, $req );
				}
				break;
		} // End switch().
	} // End foreach().
	// Expand the missing utilities list for use in the error message.
	$msg = implode( ', ', $missing );
	// If there is a message, display the warning.
	if ( ! empty( $msg ) && 'quiet' !== $quiet ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
			$settings_page = network_admin_url( 'settings.php?page=ewww-image-optimizer-options' );
		} else {
			$settings_page = admin_url( 'options-general.php?page=ewww-image-optimizer-options' );
		}
		if ( ! is_dir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
			ewww_image_optimizer_tool_folder_notice();
		} elseif ( ! is_writable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) || ! is_readable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
			ewww_image_optimizer_tool_folder_permissions_notice();
		} elseif ( ! is_executable( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) && PHP_OS !== 'WINNT' ) {
			ewww_image_optimizer_tool_folder_permissions_notice();
		}
		if ( 'pngout' === $msg ) {
			$pngout_install_url = admin_url( 'admin.php?action=ewww_image_optimizer_install_pngout' );
			echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-error'><p>" .
			sprintf(
				/* translators: 1: automatically (link) 2: manually (link) */
				esc_html__( 'You are missing pngout. Install %1$s or %2$s.', 'ewww-image-optimizer' ),
				"<a href='" . esc_url( $pngout_install_url ) . "'>" . esc_html__( 'automatically', 'ewww-image-optimizer' ) . '</a>',
				'<a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . esc_html__( 'manually', 'ewww-image-optimizer' ) . '</a>'
			) .
			'</p></div>';
		} else {
			echo "<div id='ewww-image-optimizer-warning-opt-missing' class='notice notice-error'><p>" .
			sprintf(
				/* translators: 1-6: jpegtran, optipng, pngout, pngquant, gifsicle, and cwebp (links) 7: Settings Page (link) 8: Installation Instructions (link) */
				esc_html__( 'EWWW Image Optimizer uses %1$s, %2$s, %3$s, %4$s, %5$s, and %6$s. You are missing: %7$s. Please install via the %8$s or the %9$s.', 'ewww-image-optimizer' ),
				"<a href='http://jpegclub.org/jpegtran/'>jpegtran</a>",
				"<a href='http://optipng.sourceforge.net/'>optipng</a>",
				"<a href='http://advsys.net/ken/utils.htm'>pngout</a>",
				"<a href='http://pngquant.org/'>pngquant</a>",
				"<a href='http://www.lcdf.org/gifsicle/'>gifsicle</a>",
				"<a href='https://developers.google.com/speed/webp/'>cwebp</a>",
				esc_html( $msg ),
				"<a href='" . esc_url( $settings_page ) . "'>" . esc_html__( 'Settings Page', 'ewww-image-optimizer' ) . '</a>',
				"<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something' data-beacon-article='585371e3c697912ffd6c0ba1' target='_blank'>" . esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>'
			) .
			'</p></div>';
		}
		ewwwio_memory( __FUNCTION__ );
	}
}

/**
 * Checks if exec() is disabled.
 *
 * @return bool True if exec() is disabled.
 */
function ewww_image_optimizer_exec_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_function_exists( 'exec' ) ) {
		return true;
	}
	return false;
}

/**
 * Sends each tool to the binary checker appropriate for the operating system.
 *
 * @param bool $j True to check jpegtran.
 * @param bool $o True to check optipng.
 * @param bool $g True to check gifsicle.
 * @param bool $p True to check pngout.
 * @param bool $q True to check pngquant.
 * @param bool $w True to check cwebp.
 * @return array Path for each tool (indexes JPEGTRAN, OPTIPNG, GIFSICLE, PNGOUT, PNGQUANT, CWEBP),
 *               or false for disabled/missing tools.
 */
function ewww_image_optimizer_path_check( $j = true, $o = true, $g = true, $p = true, $q = true, $w = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$jpegtran = false;
	$optipng  = false;
	$gifsicle = false;
	$pngout   = false;
	$pngquant = false;
	$webp     = false;
	ewww_image_optimizer_define_noexec();
	if ( EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
		return array(
			'JPEGTRAN' => false,
			'OPTIPNG'  => false,
			'GIFSICLE' => false,
			'PNGOUT'   => false,
			'PNGQUANT' => false,
			'CWEBP'    => false,
		);
	}
	if ( 'WINNT' === PHP_OS ) {
		if ( $j ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
				$jpegtran = ewww_image_optimizer_find_win_binary( 'jpegtran', 'j' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_JPEGTRAN' );
				define( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN', $jpegtran );
			} else {
				$jpegtran = EWWW_IMAGE_OPTIMIZER_JPEGTRAN;
			}
		}
		if ( $o ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
				$optipng = ewww_image_optimizer_find_win_binary( 'optipng', 'o' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_OPTIPNG' );
				define( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG', $optipng );
			} else {
				$optipng = EWWW_IMAGE_OPTIMIZER_OPTIPNG;
			}
		}
		if ( $g ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
				$gifsicle = ewww_image_optimizer_find_win_binary( 'gifsicle', 'g' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_GIFSICLE' );
				define( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE', $gifsicle );
			} else {
				$gifsicle = EWWW_IMAGE_OPTIMIZER_GIFSICLE;
			}
		}
		if ( $p ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGOUT' ) ) {
				$pngout = ewww_image_optimizer_find_win_binary( 'pngout', 'p' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_PNGOUT' );
				define( 'EWWW_IMAGE_OPTIMIZER_PNGOUT', $pngout );
			} else {
				$pngout = EWWW_IMAGE_OPTIMIZER_PNGOUT;
			}
		}
		if ( $q ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT' ) ) {
				$pngquant = ewww_image_optimizer_find_win_binary( 'pngquant', 'q' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_PNGQUANT' );
				define( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT', $pngquant );
			} else {
				$pngquant = EWWW_IMAGE_OPTIMIZER_PNGQUANT;
			}
		}
		if ( $w ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CWEBP' ) ) {
				$webp = ewww_image_optimizer_find_win_binary( 'cwebp', 'w' );
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_CWEBP' );
				define( 'EWWW_IMAGE_OPTIMIZER_CWEBP', $webp );
			} else {
				$webp = EWWW_IMAGE_OPTIMIZER_CWEBP;
			}
		}
	} else {
		if ( $j ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
				$jpegtran = ewww_image_optimizer_find_nix_binary( 'jpegtran', 'j' );
				if ( ! $jpegtran ) {
					$jpegtran = ewww_image_optimizer_find_nix_binary( 'jpegtran', 'jb' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_JPEGTRAN' );
				define( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN', $jpegtran );
			} else {
				$jpegtran = EWWW_IMAGE_OPTIMIZER_JPEGTRAN;
			}
		}
		if ( $o ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
				$optipng = ewww_image_optimizer_find_nix_binary( 'optipng', 'o' );
				if ( ! $optipng ) {
					$optipng = ewww_image_optimizer_find_nix_binary( 'optipng', 'ob' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_OPTIPNG' );
				define( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG', $optipng );
			} else {
				$optipng = EWWW_IMAGE_OPTIMIZER_OPTIPNG;
			}
		}
		if ( $g ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
				$gifsicle = ewww_image_optimizer_find_nix_binary( 'gifsicle', 'g' );
				if ( ! $gifsicle ) {
					$gifsicle = ewww_image_optimizer_find_nix_binary( 'gifsicle', 'gb' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_GIFSICLE' );
				define( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE', $gifsicle );
			} else {
				$gifsicle = EWWW_IMAGE_OPTIMIZER_GIFSICLE;
			}
		}
		if ( $p ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGOUT' ) ) {
				// Pngout is special and has a dynamic and static binary to check.
				$pngout = ewww_image_optimizer_find_nix_binary( 'pngout-static', 'p' );
				if ( ! $pngout ) {
					$pngout = ewww_image_optimizer_find_nix_binary( 'pngout', 'p' );
				}
				if ( ! $pngout ) {
					$pngout = ewww_image_optimizer_find_nix_binary( 'pngout-static', 'pb' );
				}
				if ( ! $pngout ) {
					$pngout = ewww_image_optimizer_find_nix_binary( 'pngout', 'pb' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_PNGOUT' );
				define( 'EWWW_IMAGE_OPTIMIZER_PNGOUT', $pngout );
			} else {
				$pngout = EWWW_IMAGE_OPTIMIZER_PNGOUT;
			}
		}
		if ( $q ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT' ) ) {
				$pngquant = ewww_image_optimizer_find_nix_binary( 'pngquant', 'q' );
				if ( ! $pngquant ) {
					$pngquant = ewww_image_optimizer_find_nix_binary( 'pngquant', 'qb' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_PNGQUANT' );
				define( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT', $pngquant );
			} else {
				$pngquant = EWWW_IMAGE_OPTIMIZER_PNGQUANT;
			}
		}
		if ( $w ) {
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CWEBP' ) ) {
				$webp = ewww_image_optimizer_find_nix_binary( 'cwebp', 'w' );
				if ( ! $webp ) {
					$webp = ewww_image_optimizer_find_nix_binary( 'cwebp', 'wb' );
				}
				ewwwio_debug_message( 'defining EWWW_IMAGE_OPTIMIZER_CWEBP' );
				define( 'EWWW_IMAGE_OPTIMIZER_CWEBP', $webp );
			} else {
				$webp = EWWW_IMAGE_OPTIMIZER_CWEBP;
			}
		}
	} // End if().
	if ( $jpegtran ) {
		ewwwio_debug_message( "using: $jpegtran" );
	}
	if ( $optipng ) {
		ewwwio_debug_message( "using: $optipng" );
	}
	if ( $gifsicle ) {
		ewwwio_debug_message( "using: $gifsicle" );
	}
	if ( $pngout ) {
		ewwwio_debug_message( "using: $pngout" );
	}
	if ( $pngquant ) {
		ewwwio_debug_message( "using: $pngquant" );
	}
	if ( $webp ) {
		ewwwio_debug_message( "using: $webp" );
	}
	ewwwio_memory( __FUNCTION__ );
	return array(
		'JPEGTRAN' => $jpegtran,
		'OPTIPNG'  => $optipng,
		'GIFSICLE' => $gifsicle,
		'PNGOUT'   => $pngout,
		'PNGQUANT' => $pngquant,
		'CWEBP'    => $webp,
	);
}

/**
 * Checks the binary against a list of valid sha256 checksums (formerly md5sums).
 *
 * @param string $path The filename of a binary to check for a match.
 * @return bool True if the sha256sum is validated.
 */
function ewww_image_optimizer_md5check( $path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$binary_sum = hash_file( 'sha256', $path );
	ewwwio_debug_message( "$path: $binary_sum" );
	$valid_sums = array(
		'463de9ba684d54d27185cb6487a0b22b7571a87419abde4dee72c9b107f23315', // jpegtran-mac     9, EWWW 1.3.0.
		'0b94f82e3d740d1853281e9aaee5cc7122c27fd63da9d6d62ed3398997cbed1e', // jpegtran-linux   9, EWWW 1.4.0.
		'f5f079bfe6f3f48c17738679292f35cdee44afe8f8413cdbc4f555cee7de4173', // jpegtran-linux64 9, EWWW 1.4.0.
		'ec71f638d2101f08fab66f4d139746d4042352bc75d55bd093aa446081892e0c', // jpegtran-fbsd    9, EWWW 1.4.0.
		'356532227fce51fcb9df29f143ab9d202fbd40f18e2b8234aee95937c93bd67e', // jpegtran-fbsd64  9, EWWW 1.4.0.
		'7be857837764dff4f0d7d2c5d546bf4d2573af7f326ced908ac229d60fd054c6', // jpegtran.exe     9, EWWW 1.4.0.
		'bce5205bb240532c01273b5442a44244a8a27a74fb47e2ce467c18b91fabea6b', // jpegtran-sol     9, EWWW 1.7.4.
		'cadc7be4688632bf2860562a1596f1b2b54b9a9c8b27df7ecabca49b1dcd8a5f', // jpegtran-fbsd    9a, EWWW 2.0.0.
		'bab4aa853c143534503464eeb35893d16799cf859ff22f9a4e62aa383f4fc99c', // jpegtran-fbsd64  9a, EWWW 2.0.0.
		'deb7e0f579fac767196611aa110052864e3093017970ff74de709b41e265e8b1', // jpegtran-linux   9a, EWWW 2.0.0.
		'b991fde396ebcc0e4f805df44b1797fe369f7f19e9392684dd4052e3f23c441e', // jpegtran-linux64 9a, EWWW 2.0.0.
		'436835bd42b27d2f05440bc5dc5174f2a896d38f8a550d96704d39969951d9ac', // jpegtran-mac     9a, EWWW 2.0.0.
		'bdf3c6b6cb16287a3f62e7cde8f69f8bda5d310abca28e00068c526f9f37cc89', // jpegtran-sol     9a, EWWW 2.0.0.
		'3c2746d0b1ae150b13b767715af45ff601e394c01ada929cbe16e6dcd18fb199', // jpegtran.exe     9a, EWWW 2.0.0.
		'8e11f7df5735b36d3ecc95c84b0e355355a766d3ccafbf751bcf343a8952432c', // jpegtran-fbsd  9b, EWWW 2.6.0.
		'21d8046e07cb298dfd2f3b1e321c67c378a4d35fa8adc3521acc42b5b8088d64', // jpegtran-linux 9b, EWWW 2.6.0.
		'4d1a1c601d291f96dc03ea7e42ab9137a17f93ebc391353db65b4e32c1e9fbdb', // jpegtran-mac   9b, EWWW 2.6.0.
		'7e8719703d31e1ab9bf2b2ad7ab633649012ab6aae46ea40462365b9c00876d5', // jpegtran-sol   9b, EWWW 2.6.0.
		'9767f05ae1b59d4fea25a73b276dcd1245f5281b53386dc03784539265bffbea', // jpegtran.exe   9b, EWWW 2.6.0.
		// end jpegtran.
		'6deddb5562ac13ffc3e46a0af79b592e92fb4553c5df294b6e0052bc890fd0e3', // optipng-linux 0.7.4, EWWW 1.2.0.
		'51df81fa8c765efbe0aa4c1cf5293e25e7e2e7f6962f5161615239c54aec4c01', // optipng-linux 0.7.4, EWWW 1.3.0.
		'7a56cca66471ce2b6cdff4460db0d75258ef05de8da1eda0448e4d4ad9ae252f', // optipng-mac   0.7.4, EWWW 1.3.0.
		'2f9140cdc3ef1f7687baa710f0bba84c5f7f11e3f62c3ce43124e23b849ac5ff', // optipng-linux 0.7.4, EWWW 1.3.7.
		'5d59467363c457bf743f4df121c365dd43365357f1cdea5f3752a7ca1b3e315a', // optipng-fbsd  0.7.4, EWWW 1.4.0.
		'1af8077958a88a3064a71903841f901179e27fe137774085565619fb199c653a', // optipng.exe   0.7.4, EWWW 1.4.0.
		'f692fef395b8689de033b9f2ce80c867c8a229c52e948df733377e20b62773a9', // optipng-sol   0.7.4, EWWW 1.7.4.
		'e17d327cd89ab34eff7f994806fe9f2c124d6cc6cd309fa4c3911d5ce90312c9', // optipng-fbsd  0.7.5, EWWW 2.0.0.
		'd263ecfb5b29ed08920e26cf604a86d3484daee5b80605e445cf97aa14d8aebc', // optipng-linux 0.7.5, EWWW 2.0.0.
		'6f15cb2e8d25e51037efa7bcec7499c96eb11e576536a478edfee500207655ae', // optipng-mac   0.7.5, EWWW 2.0.0.
		'1d2de40b009f16e9c709f9b0c15a47abb8da57668a918ac9a0723ddc6de6c30a', // optipng-sol   0.7.5, EWWW 2.0.0.
		'fad3a0fd95706d53576f72593bf13d3e31d1c896c852bfd5b9ba602eca0bd2b6', // optipng.exe   0.7.5, EWWW 2.0.0.
		'9d60eaeb9dc5167a57a5f3af236d56b4149d1043b543f2faa38a0936fa6b54b2', // optipng-fbsd  0.7.6, EWWW 2.8.0.
		'853ca5936a2dd92a17b3518fd55db6be35e1b2bebfabca3949c34700072e08b8', // optipng-linux 0.7.6, EWWW 2.8.0.
		'd4f11e96733aed64a72e744843dcd0929e144a7fc97f40d405a034a72eb9bbc6', // optipng-mac   0.7.6, EWWW 2.8.0.
		'1ed9343194cfca0a1c32677c974192746adfd48cb4cea6a2df668452df0e68f7', // optipng-sol   0.7.6, EWWW 2.8.0.
		'03b86ce2c08e2cc78d76d3d3dd173986b498b055c3c19e13a97a7c3c674772c6', // optipng.exe   0.7.6, EWWW 2.8.0.
		'f01cba0ab658e08738315843ee635be273726bf102ae448416b3d8956843d864', // optipng-fbsd  0.7.7 EWWW 4.1.0.
		'4404076a4f9119d4dfbb7acb00eb65345e804186a019c7136d8f8e87fb0cb997', // optipng-linux 0.7.7 EWWW 4.1.0.
		'36535c1b262e0c457bbb0ed2bc71e812a49e26a6cada63b6acbd8d809c68a5a1', // optipng-mac   0.7.7 EWWW 4.1.0.
		'41a4c78e6c97ea26836f4b021157b34f1812a9e5c2341502aad8cde942b18576', // optipng-sol   0.7.7 EWWW 4.1.0.
		'6a321e07eca8e28fa8a969b5db3c1d3cc008a2064d636cf74762bbe4364b7b14', // optipng.exe   0.7.7 EWWW 4.1.0.
		// end optipng.
		'a2292c0085863a65c99cb41ff8418ce63033e162906df72e8fdde52f0633579b', // gifsicle linux 1.67, EWWW 1.2.0.
		'd7f9609b6fd0000b2eaad2bd0c3cb85476988b18705762e915bda3f2e6007801', // gifsicle-linux 1.68, EWWW 1.3.0.
		'204a839a50367adb8cd23fae5d1913a5ca8b41307f054156ed152748d3e7934d', // gifsicle-linux 1.68, EWWW 1.3.7.
		'23e208099fa7ce75a3f98144190d6362d69b90c6f0a534ffa45dbbf789f7d99c', // gifsicle-mac   1.68, EWWW 1.3.0.
		'8b08243a7cc655512a03403f6c3814176e28bbd140df7c059bd321a9a0151c18', // gifsicle-fbsd  1.70, EWWW 1.4.0.
		'fd074673967ee9d387208f047c081a6331663b4076f4a6a608d6f646622af718', // gifsicle-linux 1.70, EWWW 1.4.0 - 1.7.4.
		'bc32a390e86d2d8f40e970b2dc059015b51afe26794d92a936c1fe7216db805d', // gifsicle-mac   1.70, EWWW 1.4.0.
		'41e67a35cd178f781b5224d196185e4243e6c2b3bece43277130fe07cdda402f', // gifsicle-sol   1.70, EWWW 1.7.4.
		'3c6d9fabd1ea1014b8f58063dd00a653980c06bc1b45e96a47d866247263a1e1', // gifsicle.exe   1.70, EWWW 1.4.0.
		'decba7a95b637bee53847af680fd37bde8bd568528412c514b7bd794056fd4ff', // gifsicle-fbsd  1.78, EWWW 1.7.5.
		'c28e5e4b5344f77f415973d013e4cb393fc550e8de44117b090d534e98b30d1c', // gifsicle-linux 1.78, EWWW 1.7.5 - 1.9.3.
		'fc2de863e8579b0d540003300e918cee450bc8e026018c631dffc0ed851a8c1c', // gifsicle-mac   1.78, EWWW 1.7.5.
		'74d011ee1b6d9fe6d5d8bdb4cd17db0c5987fa6e3d495b42439cd70b0763c07a', // gifsicle-sol   1.78, EWWW 1.7.5.
		'7c10da38f4afb28373779d40a30710aa9fb369e82f7f29363554bea965d132df', // gifsicle.exe   1.78, EWWW 1.7.5.
		'e75acedd0725fba64ee72855b796cdfa8dac9959d63e89a9e0e5ba059ae013c2', // gifsicle-fbsd  1.84, EWWW 2.0.0.
		'a4f0f21bc4bea51f5d304fe944262c12f671d70a3e5f688061da7bb036e84ff8', // gifsicle-linux 1.84, EWWW 2.0.0 - 2.4.3.
		'5f4176b3fe69f975563d2ce7e76615ab558f5f1839b9bfa6f6de1b3c3fa11c02', // gifsicle-mac   1.84, EWWW 2.0.0.
		'9f0027bed22d4be60012488ab726c3a131d9f3e1e276e9400c578173347a9a48', // gifsicle-sol   1.84, EWWW 2.0.0.
		'72f0077e8591292d09efee09a181458b34fb3c0e9a6ac7e8e11cec574bf619ac', // gifsicle.exe   1.84, EWWW 2.0.0.
		'c64936b429e46b6a75339df00eb8daa39d335844c906fa16d4d0af481851e91e', // gifsicle-fbsd  1.87, EWWW 2.4.4.
		'deea065a91c8429edecf42ccef78636065f7ae0dad867df7696128c6711e4735', // gifsicle-linux 1.87, EWWW 2.4.4.
		'2e0d8b7413173555bbec6e019c3cd7c55f7d582a017a0af7b14cfd24a6921f51', // gifsicle-mac   1.87, EWWW 2.4.4.
		'3966e01474601059c6a13aefbe4f313c6cb6d49c799f7850966950892a9ab45a', // gifsicle-sol   1.87, EWWW 2.4.4.
		'40b86b2ea6642f4c921152923af1e631922b624f7d23189f53c659506c7179b5', // gifsicle.exe   1.87, EWWW 2.4.4.
		'3da9e1a764a459d78dc1468ba60d882ff042050a86f82d895777b172b50f2f19', // gifsicle.exe   1.87, EWWW 2.4.5.
		'327c21635ea8c789e3e9533210e6baf372db27c7bbed3791881d74a7dd41cef9', // gifsicle-fbsd  1.91, EWWW 4.1.0.
		'566f058b2043c4f3c8c049b0507bfa78dcb33dac52b132cade5f67bbb62d91e4', // gifsicle-linux 1.91, EWWW 4.1.0.
		'03602b141432af2211882fc079ba15a773a7ec782c92755cb31279eb6d8b99d4', // gifsicle-mac   1.91, EWWW 4.1.0.
		'5fcdd102146984e41b01a160d072dd36852d7be14ab569a323c47e7e56916d0d', // gifsicle-sol   1.91, EWWW 4.x.
		'7156bfe16dc5e33af7facdc6847d268154ffeb75c0217517e4e188b58b293c6a', // gifsicle.exe   1.91, EWWW 4.1.0.
		// end gifsicle.
		'bdea95497d6e60aae8938cae8e999ef74a255ad603531bf523dcdb531f61fc8f', // 20110722-bsd/i686/pngout.
		'57c09b3ebd7d4623d16f6056efd7951e8f98e2362a27993a7d865af677875c00', // 20110722-bsd-static/i686/pngout-static.
		'17960599ca28a61aeb883a68b2eb52c513b730a410a0db75a7c2c22e0a3f925a', // 20110722-linux/i686/pngout.
		'689f68bcbf39e68cdf0f0a350d59c0acafdbcf7ff122e25b5a8b58ed3a8f18ef', // 20110722-linux/x86_64/pngout.
		'2028eea62f04b074b7693e5ce625c848ff6521206782616c893ca93637644a51', // 20110722-linux-static/i686/pngout-static.
		'7d071c3a6ac9c4e8077f029dbba1cde49008d38adf897401e951f9c2e7ce8bb1', // 20110722-linux-static/x86_64/pngout-static.
		'89c510b551718d263433bb37e67364cab582a71bf7f5558213a121bb86cb5f98', // 20110722-mac/pngout.
		'e383a5293e3b1934c87367799f6eaefbd6714cfa004262f273fb7f2f4d15930b', // 20130221-bsd/i686/pngout.
		'd2b70c882be527543818d84552cc4e6faf40da3cec45286e5c36ed73e9611b7b', // 20130221-bsd/amd64/pngout.
		'bc08e1f883ba92a04e44fe4e756e1afc3b77fc1d072519adff6ce2f7787109bb', // 20130221-bsd-static/i686/pngout-static.
		'860779de32c1fe34f211da036471d6e4ecc0d35527727d476f29623785cf6f82', // 20130221-bsd-static/amd64/pngout-static.
		'edd8e6173bf3b862c6c40c4b5aad6514169a58ee9b0b34d8c37e475005889592', // 20130221-linux/i686/pngout.
		'f6a053d1c03b69e2ac4435aaa5b5e13ea5169d9a262286595f9f455d8da5caf1', // 20130221-linux/x86_64/pngout.
		'9591669b3984a19f0aab3a8e8fad98c5274b3c30daecf46b35d22df934546618', // 20130221-linux-static/i686/pngout-static.
		'25d2aab99796c26f1e9cf1f2a9713920be40ce1b99e02c2c50b67fa6e3da06be', // 20130221-linux-static/x86_64/pngout-static.
		'57fd225f3ae921309ee4570f1970629d31cb02946983405d1b1f648aeaab30a1', // 20130221-mac/pngout.
		'3dfeb927e96853d2470350b0a916dc263cd4ebe878b402773dba105a6644e796', // 20150319-bsd/i686/pngout.
		'60a2848c79551a3e79ffcea7f54964767e25bb05c2255b0ea6a1eb03605661d2', // 20150319-bsd/amd64/pngout.
		'52dd45f15221f2ff30739151f30aedb5e3377dd6bccd350d4bce9429d7fa5e8b', // 20150319-bsd-static/i686/pngout-static.
		'12ffa454936e1d35dc96749208d740695fea26d07267b6a17b9890db0f156026', // 20150319-bsd-static/amd64/pngout-static.
		'5b97595c2b4e5f47ba797b105b3b56dbb769437bdc9092f07f6c57bc457ee667', // 20150319-linux/i686/pngout.
		'a496985d02c785c05f21f653fc4d61a5a171a68f691119448bc3c3152246f0d7', // 20150319-linux/x86_64/pngout.
		'b6641cb01b684c42e40076b91f98485dd115f6200d3f0baf989f1a4ae43c603a', // 20150319-linux-static/i686/pngout-static.
		'2b8245fe21a648101b8e7399a9dfcc4cf42a39dafa7aab673a7c47901bf82e4a', // 20150319-linux-static/x86_64/pngout-static.
		'12afd90e04387d4c3be985042c1eada89e0c4504f84c0b4739c459c7b3831774', // 20150319-mac/pngout.
		'843f0be42e86680c1663c4ef58eb0677ace15fc29ab23897c83f4b7e5af3ef36', // 20150319-windows/pngout.exe 20150319.
		'aa3993937455094c0f66ac77d60bf53be441fdf8f14618520c2af68f2253085d', // 20150920-mac/pngout.
		// end pngout.
		'8417d5d60bc66442ecc666e31ec7b9e1b7c55f48291e74b4b81f35703e2aef2e', // pngquant-fbsd  2.0.2, EWWW 1.8.3.
		'78668c38d0be70764b18f3f4e0ea2b647df2ae87cedb2216d0ef69c8c55b688a', // pngquant-linux 2.0.2, EWWW 1.8.3.
		'7df1b7f6ed73a189083dd931fb3380d236d34790318f00233b59c8f26f90665f', // pngquant-mac   2.0.2, EWWW 1.8.3.
		'56d2c6212eb595f5eab8a7469e56fa8d3d0e6ffc231aef27742134fba4a39298', // pngquant-sol   2.0.2, EWWW 1.8.3.
		'd3851c962cd59d74a35174bf3ce71d876dfcd8bdf76f81cd428b2ab7e53c0515', // pngquant.exe   2.0.2, EWWW 1.8.3.
		'0ee6f1dbf4fa168b11ce60860e5700ca0e5125323a43540a78c76644835abc84', // pngquant-fbsd  2.3.0, EWWW 2.0.0.
		'85d8a70930a554f50181a1d061577cf67ef2e76e2cbd5bcb1b7f006064ff1444', // pngquant-linux 2.3.0, EWWW 2.0.0.
		'a807f769922fdad0ba07307c548df8cf8eeced649d04237d13dfc95757161459', // pngquant-mac   2.3.0, EWWW 2.0.0.
		'cf2cc40274c438b35e93bd0346c2a6d871bd7a7bdd90c52f4e79f369cb8ded74', // pngquant-sol   2.3.0, EWWW 2.0.0.
		'7454aba77b1a2b63a42d8a5870d3c2d733c7efb2d828643d5e64784af1f65f2a', // pngquant.exe   2.3.0, EWWW 2.0.0.
		'6287f1bb7179c7b6d71a41112222347ed97b6eae4e79b180d7e1e332a4bde3e2', // pngquant-fbsd  2.5.2, EWWW 2.5.4.
		'fcfe4d3a602e7b491f4126a2707144f5f9cc9359d13f443575d7ea6a74e85ddb', // pngquant-linux 2.5.2, EWWW 2.5.4.
		'35794819a35e949dc0c0d6f90d0bb675791fa9bc3f405eb19f48ea31bb6456a8', // pngquant-mac   2.5.2, EWWW 2.5.4.
		'c242586c70d83af544334f1846b838ef68c6ab4fc247b2cff9ad4b714f825866', // pngquant-sol   2.5.2, EWWW 2.5.4.
		'ad79d9b3395d41404b28362972bd68db3c58d5be5f063884df3a595fc38c6a98', // pngquant.exe   2.5.2, EWWW 2.5.4.
		'54d632fc4446d88ad4d1beeaf73420d68d87786f02adc9d3363766cb93ec95a4', // pngquant-fbsd  2.9.1, EWWW 3.4.0.
		'91f704f02468f86766007e46973a1ef9e282d6ccadc54caf339dc537c9b2b61d', // pngquant-linux 2.9.1, EWWW 3.4.0.
		'65dc20f05af588d948fc6f4df37c294f4a3a1c1ad207a8b56d13e6829773165a', // pngquant-mac   2.9.1, EWWW 3.4.0.
		'dbc9e12d5bb3e806aaf5e2c3d30d122d569069027a633485761cbf072cf2236d', // pngquant-sol   2.9.1, EWWW 3.4.0.
		'84e63e6f9f9630a1a0c3e782609349c12b8df9ea9d02c5a29230819379e56b3c', // pngquant.exe   2.8.1, EWWW 3.4.0.
		'd6433dc6ecf6a0fdedf754782e6d5c9e494ddec762426a6d0b1896a220bd6d3f', // pngquant-fbsd  2.11.7 EWWW 4.1.0.
		'40b0860abba39342fb64612a612e0f24571d259b6b83d7483af9a1d586950d79', // pngquant-linux 2.11.7 EWWW 4.1.0.
		'c924e11d9a3166afd5ed19165193c1351ff4a2cc993498f1f28c7daee829ca76', // pngquant-mac   2.11.7 EWWW 4.1.0.
		'34534e69929e7fe267f77c55f487e419f76cc1d24e41fdb642f9671383012c56', // pngquant-sol   2.11.7 EWWW 4.1.0.
		'af7598aa09ba519ad15305a56011949db19c5b2176187662640bc0ebc4ddd19a', // pngquant.exe   2.11.7 EWWW 4.1.0.
		'6b1d4e685a4f5b3cbed9b9c7b71c7f75ae860684783e4a8274cdc66247d11fae', // pngquant.exe   2.12.5 EWWW 5.1.0.
		'1ab09e21dd0c8aafe482227c2b53f13faf00fa9ba2b9046c1e9f8c4d4d851b9d', // pngquant-fbsd  2.12.5 EWWW 5.1.0.
		'b580c7d68c3ec7cd7685fb388cdbb2635aae92c7d520e54e8f67c57fc6215db0', // pngquant-linux 2.12.5 EWWW 5.1.0.
		'ddec62d4074d54d76dde9313302b6a95025286ad82006a0f83eb0452cc86da6c', // pngquant-mac   2.12.5 EWWW 5.1.0.
		'7c10d643f936114aaa307c5fa2024c5bd5f9a25fa90a07e7f2b100c161f15898', // pngquant-sol   2.12.5 EWWW 5.1.0.
		// end pngquant.
		'bf0e12f996802dc114a864e5150647ce41089a5a2b5e36c3a270ac848b655c26', // cwebp-fbsd 0.4.1, EWWW 2.0.0.
		'5349646072c3ef5f8b4588bbee8635e882c245439e2d86b863f04b7e27f4fafe', // cwebp-fbsd64 0.4.1, EWWW 2.0.0.
		'3044c02cfef53f4361f7b2db49c5679f894ed346f665d4c8d91c6675d84dbf67', // cwebp-linux6 0.4.1, EWWW 2.0.0.
		'c9899718a5e272a082fd7c9d93d7c23d8a50f49d1b739a9aa1ef404f78cd7baf', // cwebp-linux664 0.4.1, EWWW 2.0.0.
		'2a0dff5c80fd5fa170babd0c0571f4499606f8d09bf820938da41a311d6dec6f', // cwebp-linux8 0.4.1, EWWW 2.0.0.
		'c1dfbbad935e31bde2e517dff43911c0651a8e5f78c022a252a864278065ae11', // cwebp-linux864 0.4.1, EWWW 2.0.0.
		'bae23f1614d391b136e8618a21590e4a9f0614c8716b86a6a7067527e9950d87', // cwebp-mac7 0.4.1, EWWW 2.0.0.
		'bc72654fead42c6d4fd841cecdee6ccbf21b2407292593ec982f31d39b566955', // cwebp-mac8 0.4.1, EWWW 2.0.0.
		'7fa005dc6a18563e4f6574bec83c92cabf786d8ee845503d80fa52e370dc4903', // cwebp-sol 0.4.1, EWWW 2.0.0.
		'6486779c8e1e9cc7c63ae03c416fc6d5dc7598c58a6cddbe9a41e70d804410f9', // cwebp.exe 0.4.1, EWWW 2.0.0.
		'814a168f190c4712df231b1f7d1910185ef823953b54c9fb8b354f415172a371', // cwebp-fbsd64 0.4.3, EWWW 2.4.4.
		'0f867ea2db0db895612bd15916ad31bc71c89ef2ad74552b7e878df09b843da5', // cwebp-linux6 0.4.3, EWWW 2.4.4.
		'179c7b9a2fbc1af542b3653bff58ca4dcb35bebf346687c12bb667ab49e9e21b', // cwebp-linux664 0.4.3, EWWW 2.4.4.
		'212e59654bbb6147ee8a554bf8eb7b5c11f75b9ef14ac3e6ee92ad726a47339a', // cwebp-linux8 0.4.3, EWWW 2.4.4.
		'b491509221f7c97e8dcc3bdd6f7fc201f40bc93062618bfba06f84aac7704558', // cwebp-linux864 0.4.3, EWWW 2.4.4.
		'2e8c5f53f44656ec80f11cca3c985200f502c88ea47bb34063e09eb6313e04a6', // cwebp-mac8 0.4.2, EWWW 2.4.4.
		'963a09a2c45ba036291b32ecb665541e40c232bb0f2474810ac2a9ddf8837fe4', // cwebp-mac9 0.4.3, EWWW 2.4.4.
		'2642d98bb75bc2fd2d969ba1d27b8628fd7fa73a7a204ed8f71a65e124abcac0', // cwebp-sol 0.4.3, EWWW 2.4.4.
		'64cd62e33201b0d14ec4823b64d93f92825f2e8f5239726f5b00ed9ff944a581', // cwebp.exe 0.4.3, EWWW 2.4.4.
		'7d7329671d445924dafcaacee7f2db6f4ce33567ffca41aa5b5818ebff806bc5', // cwebp-fbsd64 0.4.4, EWWW 2.5.4.
		'f1a48031d0ab602080f5646695ce8a3e84d5470f1be99d1b8fc20aded9c7839b', // cwebp-linux6 0.4.4, EWWW 2.5.4.
		'b2bef90b62d80b35d4c5a41f793454e95e5159bf0aec2e4bd8c19fc3de3556bd', // cwebp-linux664 0.4.4, EWWW 2.5.4.
		'd3c358524efd50f6e078362733870229ca1e1db8885580b6814c2535b4d20612', // cwebp-linux8 0.4.4, EWWW 2.5.4.
		'271deeec579c252e364495addad03d9c1f3248c2177a01638002b25eee787ded', // cwebp-linux864 0.4.4, EWWW 2.5.4.
		'379e2b95e20dd33f4667c134099df358e178f6a6bf32f3a5b6b78bbb6950b4b5', // cwebp-mac9  0.4.4, EWWW 2.5.4.
		'118ea3f0bcdcce6128d64e34159c93c3324cb038c9e5a51efaf530ea52af7070', // cwebp-sol   0.4.4, EWWW 2.5.4.
		'43941c1d7169e66fb1fd62a1950286b230d3e5bec3bbb14fdb4ac091ca7a0f9f', // cwebp.exe   0.4.4, EWWW 2.5.4.
		'26d5d88dee2993d1d0e16f5e60318cd8adec485614facd6c7f9c22c71eb7b2e5', // cwebp-fbsd  0.5.0, EWWW 2.6.0.
		'60b1738d6502691227a46658cd7656b4a52702680f169e8e04d72077e967aeed', // cwebp-linux 0.5.0, EWWW 2.6.0.
		'276a0221a4c978825903572c2b68b3010399375d6b9dc7429286caf625cae95a', // cwebp-mac9  0.5.0, EWWW 2.6.0.
		'be3e81ec7267e7878ddd4ee01df1553966952f74bbfd30a5523d12d53f019ecb', // cwebp-sol   0.5.0, EWWW 2.6.0.
		'b41123ec06f21765f50ec1b017839f99ab4f28497d87da722817a6023e4a3b32', // cwebp.exe   0.5.0, EWWW 2.6.0.
		'f0547a6219c5c05d0af29c5e411e054b9d795567f4ae2e27893815af9383c60f', // cwebp-fbsd  0.5.1, EWWW 2.9.9.
		'9eaf670bb2d567421c7e2918112dc00406c60f008b120f648cf0bdba73ee9b6b', // cwebp-linux 0.5.1, EWWW 2.9.9.
		'1202ea932b315913d3736460dd3d50bc5b251b7a0a8f0468c63144ba427679c2', // cwebp-mac9  0.5.1, EWWW 2.9.9.
		'27ba0abce52e74744f6235fcde9b153b5052b9c15cd78e74feffaea9dafcc178', // cwebp-sol   0.5.1, EWWW 2.9.9.
		'b02864989f0a1a263caa796c5b8caf18c1f774ed0ba08a9350e8820459875f51', // cwebp.exe   0.5.1, EWWW 2.9.9.
		'e5cbea11c97fadffe221fdf57c093c19af2737e4bbd2cb3cd5e908de64286573', // cwebp-fbsd  0.6.0, EWWW 3.4.0.
		'43ca351e8f5d457b898c587151ebe3d8f6cce8dcfb7de44f6cb70148a31a68bc', // cwebp-linux 0.6.0, EWWW 3.4.0.
		'a06a3ee436e375c89dbc1b0b2e8bd7729a55139ae072ed3f7bd2e07de0ebb379', // cwebp-mac12 0.6.0, EWWW 3.4.0.
		'1febaffbb18e52dc2c524cda9eefd00c6db95bc388732868999c0f48deb73b4f', // cwebp-sol   0.6.0, EWWW 3.4.0.
		'49e9cb98db30bfa27936933e6fd94d407e0386802cb192800d9fd824f6476873', // cwebp.exe   0.6.0, EWWW 3.4.0.
		'dde516a971fed2960442e3354df9e8043328acecfd1d68dae8712183a0a06f48', // cwebp-fbsd  1.0.3, EWWW 5.1.0.
		'90a506eea038e89ef53b41bfcae2cf2d67db6a3eae57fa43ca02da407420e0b6', // cwebp-linux 1.0.3, EWWW 5.1.0.
		'7332ed5f0d4091e2379b1eaa32a764f8c0d51b7926996a1dc8b4ef4e3c441a12', // cwebp-mac14 1.0.3, EWWW 5.1.0.
		'66568f3b31f8f22deef38aa6ba3d2be19516514e94b7d623cd2ce2a290ccdd69', // cwebp-sol   1.0.3, EWWW 5.1.0.
		'e1041c5486fb4e57e31155c45d66117f8fc270e5a56a1049408a05f54bd52969', // cwebp.exe   1.0.3, EWWW 5.1.0.
		// end cwebp.
	);
	foreach ( $valid_sums as $checksum ) {
		if ( $checksum === $binary_sum ) {
			ewwwio_debug_message( 'checksum verified, binary is intact' );
			ewwwio_memory( __FUNCTION__ );
			return true;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Check the mimetype of the given file with various methods.
 *
 * Checks WebP files using a direct pattern match, then prefers fileinfo, getimagesize (for images
 * only), mime_content_type, and lastly the 'file' utility (only for binaries).
 *
 * @param string $path The absolute path to the file.
 * @param string $case The type of file we are checking. Accepts 'i' for
 *                     images/pdfs or 'b' for binary.
 * @return bool|string A valid mime-type or false.
 */
function ewww_image_optimizer_mimetype( $path, $case ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "testing mimetype: $path" );
	$type = false;
	// For S3 images/files, don't attempt to read the file, just use the quick (filename) mime check.
	if ( 'i' === $case && ewww_image_optimizer_stream_wrapped( $path ) ) {
		return ewww_image_optimizer_quick_mimetype( $path );
	}
	$path = realpath( $path );
	if ( ! ewwwio_is_file( $path ) ) {
		ewwwio_debug_message( "$path is not a file, or out of bounds" );
		return $type;
	}
	global $eio_filesystem;
	ewwwio_get_filesystem();
	if ( 'i' === $case ) {
		$file_contents = $eio_filesystem->get_contents( $path );
		if ( $file_contents ) {
			// Read first 12 bytes, which equates to 24 hex characters.
			$magic = bin2hex( substr( $file_contents, 0, 12 ) );
			ewwwio_debug_message( $magic );
			if ( 0 === strpos( $magic, '52494646' ) && 16 === strpos( $magic, '57454250' ) ) {
				$type = 'image/webp';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( 'ffd8ff' === substr( $magic, 0, 6 ) ) {
				$type = 'image/jpeg';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '89504e470d0a1a0a' === substr( $magic, 0, 16 ) ) {
				$type = 'image/png';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '474946383761' === substr( $magic, 0, 12 ) || '474946383961' === substr( $magic, 0, 12 ) ) {
				$type = 'image/gif';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			if ( '25504446' === substr( $magic, 0, 8 ) ) {
				$type = 'application/pdf';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			ewwwio_debug_message( "match not found for image: $magic" );
		} else {
			ewwwio_debug_message( 'could not open for reading' );
		}
	}
	if ( 'b' === $case ) {
		$file_contents = $eio_filesystem->get_contents( $path );
		if ( $file_contents ) {
			// Read first 4 bytes, which equates to 8 hex characters.
			$magic = bin2hex( substr( $file_contents, 0, 4 ) );
			ewwwio_debug_message( $magic );
			// Mac (Mach-O) binary.
			if ( 'cffaedfe' === $magic || 'feedface' === $magic || 'feedfacf' === $magic || 'cefaedfe' === $magic || 'cafebabe' === $magic ) {
				$type = 'application/x-executable';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			// ELF (Linux or BSD) binary.
			if ( '7f454c46' === $magic ) {
				$type = 'application/x-executable';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			// MS (DOS) binary.
			if ( '4d5a9000' === $magic ) {
				$type = 'application/x-executable';
				ewwwio_debug_message( "ewwwio type: $type" );
				return $type;
			}
			ewwwio_debug_message( "match not found for binary: $magic" );
		} else {
			ewwwio_debug_message( 'could not open for reading' );
		}
	}
	return false;
}

/**
 * Escape any spaces in the filename.
 *
 * @param string $path The path to a binary file.
 * @return string The path with spaces escaped.
 */
function ewww_image_optimizer_escapeshellcmd( $path ) {
	return ( preg_replace( '/ /', '\ ', $path ) );
}

/**
 * Replacement for escapeshellarg() that won't kill non-ASCII characters.
 *
 * @param string $arg A value to sanitize/escape for commmand-line usage.
 * @return string The value after being escaped.
 */
function ewww_image_optimizer_escapeshellarg( $arg ) {
	if ( PHP_OS === 'WINNT' ) {
		$safe_arg = str_replace( '%', ' ', $arg );
		$safe_arg = str_replace( '!', ' ', $safe_arg );
		$safe_arg = str_replace( '"', ' ', $safe_arg );
		return '"' . $safe_arg . '"';
	}
	$safe_arg = "'" . str_replace( "'", "'\\''", $arg ) . "'";
	return $safe_arg;
}

/**
 * Test the given binary to see if it returns a valid version string.
 *
 * @param string $path The absolute path to a binary file.
 * @param string $tool The specific tool to test. Accepts j, o, g, p, q, w or append a b for blind
 *                     test like jb.
 * @return bool True (or truthy) if found.
 */
function ewww_image_optimizer_tool_found( $path, $tool ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "testing case: $tool at $path" );

	// '*b' cases are 'blind' testing in case we can't get at the version string, but the binaries are actually working, we run a test compression, and compare the results with what they should be.
	switch ( $tool ) {
		case 'j': // jpegtran.
			// In case you forget, it is not any slower to run jpegtran this way (with a sample file to operate on) than the other tools.
			exec( $path . ' -v ' . EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'sample.jpg 2>&1', $jpegtran_version );
			if ( ewww_image_optimizer_iterable( $jpegtran_version ) ) {
				ewwwio_debug_message( "$path: {$jpegtran_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			foreach ( $jpegtran_version as $jout ) {
				if ( preg_match( '/Independent JPEG Group/', $jout ) ) {
					ewwwio_debug_message( 'optimizer found' );
					return $jout;
				}
			}
			break;
		case 'jb':
			$upload_dir = wp_upload_dir();
			$testjpg    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.jpg';
			exec( $path . ' -copy none -optimize -outfile ' . ewww_image_optimizer_escapeshellarg( $testjpg ) . ' ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.jpg' ) );
			$testjpgsize = ewww_image_optimizer_filesize( $testjpg );
			ewwwio_debug_message( "blind testing jpegtran, is $testjpgsize smaller than 5700?" );
			if ( $testjpgsize ) {
				unlink( $testjpg );
			}
			if ( 0 < $testjpgsize && $testjpgsize < 5700 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
		case 'o': // optipng.
			exec( $path . ' -v 2>&1', $optipng_version );
			if ( ewww_image_optimizer_iterable( $optipng_version ) ) {
				ewwwio_debug_message( "$path: {$optipng_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( ! empty( $optipng_version ) && strpos( $optipng_version[0], 'OptiPNG' ) === 0 ) {
				ewwwio_debug_message( 'optimizer found' );
				return $optipng_version[0];
			}
			break;
		case 'ob':
			$upload_dir = wp_upload_dir();
			$testpng    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
			exec( $path . ' -out ' . ewww_image_optimizer_escapeshellarg( $testpng ) . ' -o1 -quiet -strip all ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) );
			$testpngsize = ewww_image_optimizer_filesize( $testpng );
			ewwwio_debug_message( "blind testing optipng, is $testpngsize smaller than 110?" );
			if ( $testpngsize ) {
				unlink( $testpng );
			}
			if ( 0 < $testpngsize && $testpngsize < 110 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
		case 'g': // gifsicle.
			exec( $path . ' --version 2>&1', $gifsicle_version );
			if ( ewww_image_optimizer_iterable( $gifsicle_version ) ) {
				ewwwio_debug_message( "$path: {$gifsicle_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( ! empty( $gifsicle_version ) && strpos( $gifsicle_version[0], 'LCDF Gifsicle' ) === 0 ) {
				ewwwio_debug_message( 'optimizer found' );
				return $gifsicle_version[0];
			}
			break;
		case 'gb':
			$upload_dir = wp_upload_dir();
			$testgif    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.gif';
			exec( $path . ' -O3 -o ' . ewww_image_optimizer_escapeshellarg( $testgif ) . ' ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.gif' ) );
			$testgifsize = ewww_image_optimizer_filesize( $testgif );
			ewwwio_debug_message( "blind testing gifsicle, is $testgifsize smaller than 12000?" );
			if ( $testgifsize ) {
				unlink( $testgif );
			}
			if ( 0 < $testgifsize && $testgifsize < 12000 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
		case 'p': // pngout.
			exec( "$path 2>&1", $pngout_version );
			if ( ewww_image_optimizer_iterable( $pngout_version ) ) {
				ewwwio_debug_message( "$path: {$pngout_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( ! empty( $pngout_version ) && strpos( $pngout_version[0], 'PNGOUT' ) === 0 ) {
				ewwwio_debug_message( 'optimizer found' );
				return $pngout_version[0];
			}
			break;
		case 'pb':
			$upload_dir = wp_upload_dir();
			$testpng    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
			exec( $path . ' -s3 -q ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) . ' ' . ewww_image_optimizer_escapeshellarg( $testpng ) );
			$testpngsize = ewww_image_optimizer_filesize( $testpng );
			ewwwio_debug_message( "blind testing pngout, is $testpngsize smaller than 110?" );
			if ( $testpngsize ) {
				unlink( $testpng );
			}
			if ( 0 < $testpngsize && $testpngsize < 110 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
		case 'q': // pngquant.
			exec( $path . ' -V 2>&1', $pngquant_version );
			if ( ewww_image_optimizer_iterable( $pngquant_version ) ) {
				ewwwio_debug_message( "$path: {$pngquant_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( ! empty( $pngquant_version ) && substr( $pngquant_version[0], 0, 3 ) >= 2.0 ) {
				ewwwio_debug_message( 'optimizer found' );
				return $pngquant_version[0];
			}
			break;
		case 'qb':
			$upload_dir = wp_upload_dir();
			$testpng    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
			exec( $path . ' -o ' . ewww_image_optimizer_escapeshellarg( $testpng ) . ' ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) );
			$testpngsize = ewww_image_optimizer_filesize( $testpng );
			ewwwio_debug_message( "blind testing pngquant, is $testpngsize smaller than 114?" );
			if ( $testpngsize ) {
				unlink( $testpng );
			}
			if ( 0 < $testpngsize && $testpngsize < 114 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
		case 'n': // nice.
			if ( PHP_OS === 'WINNT' ) {
				return false;
			}
			exec( "$path 2>&1", $nice_output );
			if ( ewww_image_optimizer_iterable( $nice_output ) && isset( $nice_output[0] ) ) {
				ewwwio_debug_message( "$path: {$nice_output[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( is_array( $nice_output ) && isset( $nice_output[0] ) && preg_match( '/usage/', $nice_output[0] ) ) {
				ewwwio_debug_message( 'nice found' );
				return true;
			} elseif ( is_array( $nice_output ) && isset( $nice_output[0] ) && preg_match( '/^\d+$/', $nice_output[0] ) ) {
				ewwwio_debug_message( 'nice found' );
				return true;
			}
			break;
		case 'w': // cwebp.
			exec( "$path -version 2>&1", $webp_version );
			if ( ewww_image_optimizer_iterable( $webp_version ) ) {
				ewwwio_debug_message( "$path: {$webp_version[0]}" );
			} else {
				ewwwio_debug_message( "$path: invalid output" );
				break;
			}
			if ( ! empty( $webp_version ) && preg_match( '/\d\.\d\.\d/', $webp_version[0] ) ) {
				ewwwio_debug_message( 'optimizer found' );
				return $webp_version[0];
			}
			break;
		case 'wb':
			$upload_dir = wp_upload_dir();
			$testpng    = trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
			exec( $path . ' -lossless -quiet ' . ewww_image_optimizer_escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $testpng ) );
			$testpngsize = ewww_image_optimizer_filesize( $testpng );
			ewwwio_debug_message( "blind testing cwebp, is $testpngsize smaller than 114?" );
			if ( $testpngsize ) {
				unlink( $testpng );
			}
			if ( 0 < $testpngsize && $testpngsize < 114 ) {
				ewwwio_debug_message( 'optimizer found' );
				return esc_html__( 'unknown', 'ewww-image-optimizer' );
			}
			break;
	} // End switch().
	ewwwio_debug_message( 'tool not found' );
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Searches for the given $binary on a Windows system.
 *
 * Checks the bundled tool, as well as -custom and -alt suffixes, and looks for a system-installed
 * executable if all else fails.
 *
 * @param string $binary Path to the executable.
 * @param string $switch Passed along to ewww_image_optimizer_tool_found().
 * @return string A validated executable path.
 */
function ewww_image_optimizer_find_win_binary( $binary, $switch ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $binary ) || empty( $switch ) ) {
		return '';
	}
	$use_system = ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' );
	$tool_path  = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( file_exists( $tool_path . $binary . '.exe' ) && ! $use_system ) {
		$binary_path = $tool_path . $binary . '.exe';
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( ewww_image_optimizer_md5check( $binary_path ) && ewww_image_optimizer_tool_found( '"' . $binary_path . '"', $switch ) ) {
			return '"' . $binary_path . '"';
		}
	}
	if ( file_exists( $tool_path . $binary . '-custom.exe' ) && ! $use_system ) {
		$binary_path = $tool_path . $binary . '-custom.exe';
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( ewww_image_optimizer_tool_found( '"' . $binary_path . '"', $switch ) ) {
			return '"' . $binary_path . '"';
		}
	}
	if ( file_exists( $tool_path . $binary . '-alt.exe' ) && ! $use_system ) {
		$binary_path = $tool_path . $binary . '-alt.exe';
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( ewww_image_optimizer_tool_found( '"' . $binary_path . '"', $switch ) ) {
			return '"' . $binary_path . '"';
		}
	}
	// If we still haven't found a usable binary, try a system-installed version.
	if ( ewww_image_optimizer_tool_found( $binary . '.exe', $switch ) ) {
		return $binary . '.exe';
	} else {
		return '';
	}
}

/**
 * Searches for the given $binary on a *nix system.
 *
 * Checks the bundled tool, as well as -custom and -alt suffixes, searches several system folders,
 * and looks for a system-installed binary if all else fails.
 *
 * @param string $binary Path to the binary.
 * @param string $switch Passed along to ewww_image_optimizer_tool_found().
 * @return string A validated binary path.
 */
function ewww_image_optimizer_find_nix_binary( $binary, $switch ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $binary ) || empty( $switch ) ) {
		return '';
	}
	$use_system = ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' );
	$tool_path  = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	// First check for the binary in the ewww tool folder.
	if ( file_exists( $tool_path . $binary ) && ! $use_system ) {
		$binary_path = $tool_path . $binary;
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( ewww_image_optimizer_md5check( $binary_path ) && ewww_image_optimizer_mimetype( $binary_path, 'b' ) ) {
			$binary_path = ewww_image_optimizer_escapeshellcmd( $binary_path );
			if ( ewww_image_optimizer_tool_found( $binary_path, $switch ) ) {
				return $binary_path;
			}
		}
	}
	// If the standard binary didn't work, see if the user custom compiled one and check that.
	if ( file_exists( $tool_path . $binary . '-custom' ) && ! $use_system ) {
		$binary_path = $tool_path . $binary . '-custom';
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( filesize( $binary_path ) > 15000 && ewww_image_optimizer_mimetype( $binary_path, 'b' ) ) {
			$binary_path = ewww_image_optimizer_escapeshellcmd( $binary_path );
			if ( ewww_image_optimizer_tool_found( $binary_path, $switch ) ) {
				return $binary_path;
			}
		}
	}
	// See if the alternative binary works.
	if ( file_exists( $tool_path . $binary . '-alt' ) && ! $use_system ) {
		$binary_path = $tool_path . $binary . '-alt';
		ewwwio_debug_message( "found $binary_path, testing..." );
		if ( filesize( $binary_path ) > 15000 && ewww_image_optimizer_mimetype( $binary_path, 'b' ) ) {
			$binary_path = ewww_image_optimizer_escapeshellcmd( $binary_path );
			if ( ewww_image_optimizer_tool_found( $binary_path, $switch ) ) {
				return $binary_path;
			}
		}
	}
	// If we still haven't found a usable binary, try a system-installed version.
	if ( ewww_image_optimizer_tool_found( $binary, $switch ) ) {
		return $binary;
	} elseif ( ewww_image_optimizer_tool_found( '/usr/bin/' . $binary, $switch ) ) {
		return '/usr/bin/' . $binary;
	} elseif ( ewww_image_optimizer_tool_found( '/usr/local/bin/' . $binary, $switch ) ) {
		return '/usr/local/bin/' . $binary;
	} elseif ( ewww_image_optimizer_tool_found( '/usr/gnu/bin/' . $binary, $switch ) ) {
		return '/usr/gnu/bin/' . $binary;
	} elseif ( ewww_image_optimizer_tool_found( '/usr/syno/bin/' . $binary, $switch ) ) { // For synology diskstation OS.
		return '/usr/syno/bin/' . $binary;
	} else {
		return '';
	}
}

/**
 * Resizes an image with gifsicle to preserve animations.
 *
 * @param string $file The file to resize.
 * @param int    $dst_x X-coordinate of destination image (usually 0).
 * @param int    $dst_y Y-coordinate of destination image (usually 0).
 * @param int    $src_x X-coordinate of source image (usually 0 unless cropping).
 * @param int    $src_y Y-coordinate of source image (usually 0 unless cropping).
 * @param int    $dst_w Desired image width.
 * @param int    $dst_h Desired image height.
 * @param int    $src_w Source width.
 * @param int    $src_h Source height.
 * @return string|WP_Error The image contents or the error message.
 */
function ewww_image_optimizer_gifsicle_resize( $file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$tools = ewww_image_optimizer_path_check(
		false,
		false,
		true,
		false,
		false,
		false
	);
	if ( empty( $tools['GIFSICLE'] ) ) {
		return new WP_Error(
			'image_resize_error',
			/* translators: %s: name of a tool like jpegtran */
			sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' )
		);
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "width: $dst_w" );
	ewwwio_debug_message( "height: $dst_h" );

	list( $orig_w, $orig_h ) = getimagesize( $file );

	$outfile = "$file.tmp";
	// Run gifsicle.
	if ( (int) $orig_w !== (int) $src_w || (int) $orig_h !== (int) $src_h ) {
		$dim_string  = $dst_w . 'x' . $dst_h;
		$crop_string = $src_x . ',' . $src_y . '+' . $src_w . 'x' . $src_h;
		ewwwio_debug_message( "resize to $dim_string" );
		ewwwio_debug_message( "crop to $crop_string" );
		exec( "{$tools['GIFSICLE']} --crop $crop_string  -o " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
		exec( "{$tools['GIFSICLE']} --resize-fit $dim_string  -b " . ewww_image_optimizer_escapeshellarg( $outfile ) );
	} else {
		$dim_string = $dst_w . 'x' . $dst_h;
		exec( "{$tools['GIFSICLE']} --resize-fit $dim_string  -o " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
	}
	ewwwio_debug_message( "$file resized to $outfile" );

	if ( file_exists( $outfile ) ) {
		$new_type = ewww_image_optimizer_mimetype( $outfile, 'i' );
		// Check the filesize of the new JPG.
		$new_size = filesize( $outfile );
		ewwwio_debug_message( "$outfile exists, testing type and size" );
	} else {
		return new WP_Error( 'image_resize_error', 'file does not exist' );
	}

	if ( ! $new_size || 'image/gif' !== $new_type ) {
		unlink( $outfile );
		return new WP_Error( 'image_resize_error', 'wrong type or zero bytes' );
	}
	ewwwio_debug_message( 'resize success' );
	$image = file_get_contents( $outfile );
	unlink( $outfile );
	return $image;
}

/**
 * Automatically corrects JPG rotation using local jpegtran tool.
 *
 * @param string $file Name of the file to fix.
 * @param string $type File type of the file.
 * @param int    $orientation The EXIF orientation value.
 *
 * @return bool True if the rotation was successful.
 */
function ewww_image_optimizer_jpegtran_autorotate( $file, $type, $orientation ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( 'image/jpeg' !== $type ) {
		ewwwio_debug_message( 'not a JPG, go away!' );
		return false;
	}
	if ( ! $orientation || 1 === (int) $orientation ) {
		return false;
	}
	$nice = '';
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) || ! EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		ewww_image_optimizer_define_noexec();
		if ( ! EWWW_IMAGE_OPTIMIZER_NOEXEC && PHP_OS !== 'WINNT' ) {
			// Check to see if 'nice' exists.
			$nice = ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
		}
	}
	$tools = ewww_image_optimizer_path_check(
		true,
		false,
		false,
		false,
		false,
		false
	);
	if ( empty( $tools['JPEGTRAN'] ) ) {
		return false;
	}
	switch ( $orientation ) {
		case 1:
			return false;
		case 2:
			$transform = '-flip horizontal';
			break;
		case 3:
			$transform = '-rotate 180';
			break;
		case 4:
			$transform = '-flip vertical';
			break;
		case 5:
			$transform = '-transpose';
			break;
		case 6:
			$transform = '-rotate 90';
			break;
		case 7:
			$transform = '-transverse';
			break;
		case 8:
			$transform = '-rotate 270';
			break;
		default:
			return false;
	}
	$outfile = "$file.rotate";
	// Run jpegtran.
	exec( "$nice {$tools['JPEGTRAN']} -trim -copy all $transform -outfile " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
	ewwwio_debug_message( "$file rotated to $outfile" );

	if ( file_exists( $outfile ) ) {
		$new_type = ewww_image_optimizer_mimetype( $outfile, 'i' );
		// Check the filesize of the new JPG.
		$new_size = filesize( $outfile );
		ewwwio_debug_message( "$outfile exists, testing type and size" );
	} else {
		return false;
	}

	if ( ! $new_size || 'image/jpeg' !== $new_type ) {
		unlink( $outfile );
		return false;
	}
	ewwwio_debug_message( 'rotation success' );
	rename( $outfile, $file );
	return true;
}

/**
 * Process an image.
 *
 * @param string $file Full absolute path to the image file.
 * @param int    $gallery_type 1=WordPress, 2=nextgen, 3=flagallery, 4=aux_images, 5=image editor,
 *                             6=imagestore.
 * @param bool   $converted True if this is a resize and the full image was converted to a
 *                          new format.
 * @param bool   $new True if this is a new image, so it should attempt conversion regardless of
 *                    previous results.
 * @param bool   $fullsize True if this is a full size (original) image.
 * @return array {
 *     Status of the optimization attempt.
 *
 *     @type string $file The filename or false on error.
 *     @type string $results The results of the optimization.
 *     @type bool $converted True if an image changes formats.
 *     @type string The original filename if converted.
 * }
 */
function ewww_image_optimizer( $file, $gallery_type = 4, $converted = false, $new = false, $fullsize = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	session_write_close();
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	// If the plugin gets here without initializing, we need to run through some things first.
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
		ewww_image_optimizer_cloud_init();
	}
	ewww_image_optimizer_define_noexec();
	if ( apply_filters( 'ewww_image_optimizer_bypass', false, $file ) ) {
		ewwwio_debug_message( "optimization bypassed: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	global $ewww_image;
	global $ewww_force;
	global $ewww_force_smart;
	global $ewww_convert;
	global $ewww_webp_only;
	// Initialize the original filename.
	$original = $file;
	$result   = '';
	if ( false !== strpos( $file, '../' ) || false !== strpos( $file, '..\\' ) ) {
		$msg = __( 'Path traversal in filename not allowed.', 'ewww-image-optimizer' );
		ewwwio_debug_message( "file is using ../ potential path traversal blocked: $file" );
		return array( false, $msg, $converted, $original );
	}
	if ( ! ewwwio_is_file( $file ) ) {
		/* translators: %s: Image filename */
		$msg = sprintf( __( 'Could not find %s', 'ewww-image-optimizer' ), $file );
		ewwwio_debug_message( "file doesn't appear to exist: $file" );
		return array( false, $msg, $converted, $original );
	}
	if ( ! is_writable( $file ) ) {
		/* translators: %s: Image filename */
		$msg = sprintf( __( '%s is not writable', 'ewww-image-optimizer' ), $file );
		ewwwio_debug_message( "couldn't write to the file $file" );
		return array( false, $msg, $converted, $original );
	}
	$file_perms = 'unknown';
	if ( ewww_image_optimizer_function_exists( 'fileperms' ) ) {
		$file_perms = substr( sprintf( '%o', fileperms( $file ) ), -4 );
	}
	$file_owner = 'unknown';
	$file_group = 'unknown';
	if ( function_exists( 'posix_getpwuid' ) ) {
		$file_owner = posix_getpwuid( fileowner( $file ) );
		$file_owner = 'xxxxxxxx' . substr( $file_owner['name'], -4 );
	}
	if ( function_exists( 'posix_getgrgid' ) ) {
		$file_group = posix_getgrgid( filegroup( $file ) );
		$file_group = 'xxxxx' . substr( $file_group['name'], -5 );
	}
	ewwwio_debug_message( "permissions: $file_perms, owner: $file_owner, group: $file_group" );
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( ! $type ) {
		ewwwio_debug_message( 'could not find any functions for mimetype detection' );
		// Otherwise we store an error message since we couldn't get the mime-type.
		return array( false, __( 'Unknown file type', 'ewww-image-optimizer' ), $converted, $original );
	}
	// Not an image or pdf.
	if ( strpos( $type, 'image' ) === false && strpos( $type, 'pdf' ) === false ) {
		ewwwio_debug_message( "unsupported mimetype: $type" );
		return array( false, __( 'Unsupported file type', 'ewww-image-optimizer' ) . ": $type", $converted, $original );
	}
	if ( ! is_object( $ewww_image ) || ! $ewww_image instanceof EWWW_Image || $ewww_image->file !== $file ) {
		$ewww_image = new EWWW_Image( 0, '', $file );
	}
	$nice = '';
	if ( ! EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		if ( ! EWWW_IMAGE_OPTIMIZER_NOEXEC && PHP_OS !== 'WINNT' ) {
			// Check to see if 'nice' exists.
			$nice = ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
		}
	}
	$skip = ewww_image_optimizer_skip_tools();
	if ( EWWW_IMAGE_OPTIMIZER_CLOUD ) {
		$skip['jpegtran'] = true;
		$skip['optipng']  = true;
		$skip['gifsicle'] = true;
		$skip['pngout']   = true;
		$skip['pngquant'] = true;
		$skip['webp']     = true;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) && $fullsize ) {
		$keep_metadata = true;
	} else {
		$keep_metadata = false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' ) && $fullsize ) {
		$skip_lossy = true;
	} else {
		$skip_lossy = false;
	}
	if ( ini_get( 'max_execution_time' ) < 90 && ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	// Get the original image size.
	$orig_size = ewww_image_optimizer_filesize( $file );
	ewwwio_debug_message( "original filesize: $orig_size" );
	if ( $orig_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
		ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $orig_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
		ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	$backup_hash = '';
	$new_size    = 0;
	// Set the optimization process to OFF.
	$optimize = false;
	// Toggle the convert process to ON.
	$convert = true;
	// Allow other plugins to mangle the image however they like prior to optimization.
	do_action( 'ewww_image_optimizer_pre_optimization', $file, $type, $fullsize );
	// Run the appropriate optimization/conversion for the mime-type.
	switch ( $type ) {
		case 'image/jpeg':
			$png_size = 0;
			// If jpg2png conversion is enabled, and this image is in the WordPress media library.
			if (
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) || ! empty( $ewww_convert ) ) &&
				empty( $ewww_webp_only )
			) {
				// Generate the filename for a PNG:
				// If this is a resize version.
				if ( $converted ) {
					// just change the file extension.
					$pngfile = preg_replace( '/\.\w+$/', '.png', $file );
				} else {
					// If this is a full size image.
					// Get a unique filename for the png image.
					list( $pngfile, $filenum ) = ewww_image_optimizer_unique_filename( $file, '.png' );
				}
			} else {
				// Otherwise, turn conversion OFF.
				$convert = false;
				$pngfile = '';
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' );
			// Check for previous optimization, so long as the force flag is not on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( $compression_level > 10 && empty( $ewww_webp_only ) ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type, $convert, $pngfile, 'image/png', $skip_lossy );
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $original );
					}
					$converted   = $filenum;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/png', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			if ( 10 === (int) $compression_level && EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
				break;
			}
			// If we get this far, we are using local (jpegtran) optimization, so do an autorotate on the image.
			ewww_image_optimizer_autorotate( $file );
			// Get the (possibly new) original image size.
			$orig_size = ewww_image_optimizer_filesize( $file );
			if ( $convert ) {
				$tools = ewww_image_optimizer_path_check(
					! $skip['jpegtran'],
					! $skip['optipng'],
					false,
					! $skip['pngout'],
					! $skip['pngquant'],
					! $skip['webp']
				);
			} else {
				$tools = ewww_image_optimizer_path_check(
					! $skip['jpegtran'],
					false,
					false,
					false,
					false,
					! $skip['webp']
				);
			}
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
				// Store an appropriate message in $result.
				$result = __( 'JPG optimization is disabled', 'ewww-image-optimizer' );
				// Otherwise, if we aren't skipping the utility verification and jpegtran doesn't exist.
			} elseif ( ! $skip['jpegtran'] && ! $tools['JPEGTRAN'] ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' );
				// Otherwise, things should be good, so...
			} else {
				// Set the optimization process to ON.
				$optimize = true;
			}
			// If optimization is turned ON.
			if ( $optimize ) {
				ewwwio_debug_message( 'attempting to optimize JPG...' );
				// Generate temporary file-name.
				$progfile = $file . '.prog';
				// Check to see if we are supposed to strip metadata.
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && ! $keep_metadata ) {
					// Don't copy metadata.
					$copy_opt = 'none';
				} else {
					// Copy all the metadata.
					$copy_opt = 'all';
				}
				if ( $orig_size > 10240 ) {
					$progressive = '-progressive';
				} else {
					$progressive = '';
				}
				// Run jpegtran.
				exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize $progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
				// Check the filesize of the new JPG.
				$new_size = ewww_image_optimizer_filesize( $progfile );
				ewwwio_debug_message( "optimized JPG size: $new_size" );
				// If the best-optimized is smaller than the original JPG, and we didn't create an empty JPG.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $progfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $progfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller JPG.
				} else {
					if ( ewwwio_is_file( $progfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $progfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				ewwwio_debug_message( 'calling webp, but neither convert or optimize' );
				// If conversion and optimization are both turned OFF, finish the JPG processing.
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tools['CWEBP'] );
				break;
			} // End if().
			// If the conversion process is turned ON, or if this is a resize and the full-size was converted.
			if ( $convert ) {
				ewwwio_debug_message( "attempting to convert JPG to PNG: $pngfile" );
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				// Convert the JPG to PNG.
				if ( ewww_image_optimizer_gmagick_support() ) {
					try {
						$gmagick = new Gmagick( $file );
						$gmagick->stripimage();
						$gmagick->setimageformat( 'PNG' );
						$gmagick->writeimage( $pngfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				if ( ! $png_size && ewww_image_optimizer_imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						$imagick->stripImage();
						$imagick->setImageFormat( 'PNG' );
						$imagick->writeImage( $pngfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				if ( ! $png_size && ewww_image_optimizer_gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromjpeg( $file ), $pngfile );
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				// If lossy optimization is ON and full-size exclusion is not active.
				if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && $tools['PNGQUANT'] && ! $skip_lossy ) {
					ewwwio_debug_message( 'attempting lossy reduction' );
					exec( "$nice " . $tools['PNGQUANT'] . ' ' . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $pngfile );
					if ( ewwwio_is_file( $quantfile ) && filesize( $pngfile ) > filesize( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is better: original - ' . filesize( $pngfile ) . ' vs. lossy - ' . filesize( $quantfile ) );
						rename( $quantfile, $pngfile );
					} elseif ( ewwwio_is_file( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is worse: original - ' . filesize( $pngfile ) . ' vs. lossy - ' . filesize( $quantfile ) );
						ewwwio_delete_file( $quantfile );
					} else {
						ewwwio_debug_message( 'pngquant did not produce any output' );
					}
				}
				// If optipng isn't disabled.
				if ( $tools['OPTIPNG'] ) {
					// Retrieve the optipng optimization level.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && preg_match( '/0.7/', ewww_image_optimizer_tool_found( $tools['OPTIPNG'], 'o' ) ) && ! $keep_metadata ) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// If the PNG file was created.
					if ( file_exists( $pngfile ) ) {
						ewwwio_debug_message( 'optimizing converted PNG with optipng' );
						// Run optipng on the new PNG.
						exec( "$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				// If pngout isn't disabled.
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ) {
					// Retrieve the pngout optimization level.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					// If the PNG file was created.
					if ( file_exists( $pngfile ) ) {
						ewwwio_debug_message( 'optimizing converted PNG with pngout' );
						// Run pngout on the new PNG.
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				$png_size = ewww_image_optimizer_filesize( $pngfile );
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// If the PNG is smaller than the original JPG, and we didn't end up with an empty file.
				if ( $png_size && $new_size > $png_size && ewww_image_optimizer_mimetype( $pngfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( "converted PNG is better: $png_size vs. $new_size" );
					// Store the size of the converted PNG.
					$new_size = $png_size;
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
					// Store the location of the PNG file.
					$file = $pngfile;
					// Let webp know what we're dealing with now.
					$type = 'image/png';
					// Successful conversion and we store the increment.
					$converted = $filenum;
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					// Otherwise delete the PNG.
					$converted = false;
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_delete_file( $pngfile );
					}
				}
			} // End if().
			$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['CWEBP'], $orig_size !== $new_size );
			break;
		case 'image/png':
			$jpg_size = 0;
			// Png2jpg conversion is turned on, and the image is in the WordPress media library.
			// We check for transparency later, after optimization, because optipng might fix an empty alpha channel.
			if (
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) || ! empty( $ewww_convert ) ) &&
				! $skip_lossy &&
				empty( $ewww_webp_only )
			) {
				ewwwio_debug_message( 'PNG to JPG conversion turned on' );
				$cloud_background = '';
				$r                = '';
				$g                = '';
				$b                = '';
				// If the user set a fill background for transparency.
				$background = ewww_image_optimizer_jpg_background();
				if ( $background ) {
					$cloud_background = "#$background";
					// Set background color for GD.
					$r = hexdec( '0x' . strtoupper( substr( $background, 0, 2 ) ) );
					$g = hexdec( '0x' . strtoupper( substr( $background, 2, 2 ) ) );
					$b = hexdec( '0x' . strtoupper( substr( $background, 4, 2 ) ) );
					// Set the background flag for 'convert'.
					$background = '-background ' . '"' . "#$background" . '"';
				}
				$gquality = ewww_image_optimizer_jpg_quality();
				$gquality = $gquality ? $gquality : '82';
				// If this is a resize version.
				if ( $converted ) {
					// Just replace the file extension with a .jpg.
					$jpgfile = preg_replace( '/\.\w+$/', '.jpg', $file );
					// If this is a full version.
				} else {
					// Construct the filename for the new JPG.
					list( $jpgfile, $filenum ) = ewww_image_optimizer_unique_filename( $file, '.jpg' );
				}
			} else {
				ewwwio_debug_message( 'PNG to JPG conversion turned off' );
				// Turn the conversion process OFF.
				$convert          = false;
				$jpgfile          = '';
				$r                = null;
				$g                = null;
				$b                = null;
				$cloud_background = '';
				$gquality         = null;
			} // End if().
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' );
			// Check for previous optimization, so long as the force flag is not on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if (
				$compression_level >= 20 &&
				ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
				empty( $ewww_webp_only )
			) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer(
					$file,
					$type,
					$convert,
					$jpgfile,
					'image/jpeg',
					$skip_lossy,
					$cloud_background,
					$gquality
				);
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $original );
					}
					$converted   = $filenum;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/jpeg', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			if ( $convert ) {
				$tools = ewww_image_optimizer_path_check(
					! $skip['jpegtran'],
					! $skip['optipng'],
					false,
					! $skip['pngout'],
					! $skip['pngquant'],
					! $skip['webp']
				);
			} else {
				$tools = ewww_image_optimizer_path_check(
					false,
					! $skip['optipng'],
					false,
					! $skip['pngout'],
					! $skip['pngquant'],
					! $skip['webp']
				);
			}
			// If png optimization is disabled.
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
				// Tell the user all PNG tools are disabled.
				$result = __( 'PNG optimization is disabled', 'ewww-image-optimizer' );
				// If the utility checking is on, optipng is enabled, but optipng cannot be found.
			} elseif ( ! $skip['optipng'] && ! $tools['OPTIPNG'] ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng</em>' );
				// If the utility checking is on, pngout is enabled, but pngout cannot be found.
			} elseif ( ! $skip['pngout'] && ! $tools['PNGOUT'] ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>pngout</em>' );
			} else {
				// Turn optimization on if we made it through all the checks.
				$optimize = true;
			}
			// If optimization is turned on.
			if ( $optimize ) {
				// If lossy optimization is ON and full-size exclusion is not active.
				if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && $tools['PNGQUANT'] && ! $skip_lossy ) {
					ewwwio_debug_message( 'attempting lossy reduction' );
					exec( "$nice " . $tools['PNGQUANT'] . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
					$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $file );
					if ( ewwwio_is_file( $quantfile ) && filesize( $file ) > filesize( $quantfile ) && ewww_image_optimizer_mimetype( $quantfile, 'i' ) === $type ) {
						ewwwio_debug_message( 'lossy reduction is better: original - ' . filesize( $file ) . ' vs. lossy - ' . filesize( $quantfile ) );
						rename( $quantfile, $file );
					} elseif ( ewwwio_is_file( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is worse: original - ' . filesize( $file ) . ' vs. lossy - ' . filesize( $quantfile ) );
						ewwwio_delete_file( $quantfile );
					} else {
						ewwwio_debug_message( 'pngquant did not produce any output' );
					}
				}
				$tempfile = $file . '.tmp.png';
				copy( $file, $tempfile );
				// If optipng is enabled.
				if ( $tools['OPTIPNG'] ) {
					// Retrieve the optimization level for optipng.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && preg_match( '/0.7/', ewww_image_optimizer_tool_found( $tools['OPTIPNG'], 'o' ) ) && ! $keep_metadata ) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// Run optipng on the PNG file.
					exec( "$nice " . $tools['OPTIPNG'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $tempfile ) );
				}
				// If pngout is enabled.
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ) {
					// Retrieve the optimization level for pngout.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					// Run pngout on the PNG file.
					exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $tempfile ) );
				}
				// Retrieve the filesize of the temporary PNG.
				$new_size = ewww_image_optimizer_filesize( $tempfile );
				// If the new PNG is smaller.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $tempfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller PNG.
				} else {
					if ( ewwwio_is_file( $tempfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $tempfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				// If conversion and optimization are both disabled we are done here.
				ewwwio_debug_message( 'calling webp, but neither convert or optimize' );
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tools['CWEBP'] );
				break;
			} // End if().
			// Retrieve the new filesize of the PNG.
			$new_size = ewww_image_optimizer_filesize( $file );
			// Double check for png2jpg conversion to see if we have an alpha image.
			if ( $convert && ewww_image_optimizer_png_alpha( $file ) && ! ewww_image_optimizer_jpg_background() ) {
				ewwwio_debug_message( 'PNG to JPG conversion turned off due to alpha' );
				$convert = false;
			}
			// If conversion is on and the PNG doesn't have transparency or the user set a background color to replace transparency.
			if ( $convert ) {
				ewwwio_debug_message( "attempting to convert PNG to JPG: $jpgfile" );
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				$magick_background = ewww_image_optimizer_jpg_background();
				if ( empty( $magick_background ) ) {
					$magick_background = '000000';
				}
				// Convert the PNG to a JPG with all the proper options.
				if ( ewww_image_optimizer_gmagick_support() ) {
					try {
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$gmagick_overlay = new Gmagick( $file );
							$gmagick         = new Gmagick();
							$gmagick->newimage( $gmagick_overlay->getimagewidth(), $gmagick_overlay->getimageheight(), '#' . $magick_background );
							$gmagick->compositeimage( $gmagick_overlay, 1, 0, 0 );
						} else {
							$gmagick = new Gmagick( $file );
						}
						$gmagick->setimageformat( 'JPG' );
						$gmagick->setcompressionquality( $gquality );
						$gmagick->writeimage( $jpgfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				}
				if ( ! $jpg_size && ewww_image_optimizer_imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$imagick->setImageBackgroundColor( new ImagickPixel( '#' . $magick_background ) );
							$imagick->setImageAlphaChannel( 11 );
						}
						$imagick->setImageFormat( 'JPG' );
						$imagick->setCompressionQuality( $gquality );
						$imagick->writeImage( $jpgfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				}
				if ( ! $jpg_size && ewww_image_optimizer_gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					// Retrieve the data from the PNG.
					$input = imagecreatefrompng( $file );
					// Retrieve the dimensions of the PNG.
					list($width, $height) = getimagesize( $file );
					// Create a new image with those dimensions.
					$output = imagecreatetruecolor( $width, $height );
					if ( '' === $r ) {
						$r = 255;
						$g = 255;
						$b = 255;
					}
					// Allocate the background color.
					$rgb = imagecolorallocate( $output, $r, $g, $b );
					// Fill the new image with the background color.
					imagefilledrectangle( $output, 0, 0, $width, $height, $rgb );
					// Copy the original image to the new image.
					imagecopy( $output, $input, 0, 0, 0, 0, $width, $height );
					// Output the JPG with the quality setting.
					imagejpeg( $output, $jpgfile, $gquality );
				}
				$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				if ( $jpg_size ) {
					ewwwio_debug_message( "converted JPG filesize: $jpg_size" );
				} else {
					ewwwio_debug_message( 'unable to convert to JPG' );
				}
				// Next we need to optimize that JPG if jpegtran is enabled.
				if ( 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) && file_exists( $jpgfile ) ) {
					// Generate temporary file-name.
					$progfile = $jpgfile . '.prog';
					// Check to see if we are supposed to strip metadata.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && ! $keep_metadata ) {
						// Don't copy metadata.
						$copy_opt = 'none';
					} else {
						// Copy all the metadata.
						$copy_opt = 'all';
					}
					if ( $jpg_size > 10240 ) {
						$progressive = '-progressive';
					} else {
						$progressive = '';
					}
					// Run jpegtran.
					exec( "$nice " . $tools['JPEGTRAN'] . " -copy $copy_opt -optimize $progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $jpgfile ) );
					// Check the filesize of the new JPG.
					$opt_jpg_size = ewww_image_optimizer_filesize( $progfile );
					// If the best-optimized is smaller than the original JPG, and we didn't create an empty JPG.
					if ( $opt_jpg_size && $jpg_size > $opt_jpg_size ) {
						// Replace the original with the optimized file.
						rename( $progfile, $jpgfile );
						// Store the size of the optimized JPG.
						$jpg_size = $opt_jpg_size;
						ewwwio_debug_message( 'optimized JPG was smaller than un-optimized version' );
						// If the optimization didn't produce a smaller JPG.
					} elseif ( ewwwio_is_file( $progfile ) ) {
						ewwwio_delete_file( $progfile );
					}
				}
				ewwwio_debug_message( "converted JPG size: $jpg_size" );
				// If the new JPG is smaller than the original PNG.
				if ( $jpg_size && $new_size > $jpg_size && ewww_image_optimizer_mimetype( $jpgfile, 'i' ) === 'image/jpeg' ) {
					// Store the size of the JPG as the new filesize.
					$new_size = $jpg_size;
					// If the user wants originals delted after a conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original PNG.
						ewwwio_delete_file( $file );
					}
					// Update the $file location to the new JPG.
					$file = $jpgfile;
					// Let webp know what we're dealing with now.
					$type = 'image/jpeg';
					// Successful conversion, so we store the increment.
					$converted = $filenum;
				} else {
					$converted = false;
					if ( ewwwio_is_file( $jpgfile ) ) {
						// Otherwise delete the new JPG.
						ewwwio_delete_file( $jpgfile );
					}
				}
			} // End if().
			$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['CWEBP'], $orig_size !== $new_size );
			break;
		case 'image/gif':
			// If gif2png is turned on, and the image is in the WordPress media library.
			if (
				empty( $ewww_webp_only ) &&
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) || ! empty( $ewww_convert ) ) &&
				! ewww_image_optimizer_is_animated( $file )
			) {
				// Generate the filename for a PNG:
				// if this is a resize version...
				if ( $converted ) {
					// just change the file extension.
					$pngfile = preg_replace( '/\.\w+$/', '.png', $file );
				} else {
					// If this is the full version...
					// construct the filename for the new PNG.
					list( $pngfile, $filenum ) = ewww_image_optimizer_unique_filename( $file, '.png' );
				}
			} else {
				// Turn conversion OFF.
				$convert = false;
				$pngfile = '';
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' );
			// Check for previous optimization, so long as the force flag is on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( empty( $ewww_webp_only ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && 10 === $compression_level ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type, $convert, $pngfile, 'image/png', $skip_lossy );
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original GIF.
						ewwwio_delete_file( $original );
					}
					$converted   = $filenum;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/png', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			if ( $convert ) {
				$tools = ewww_image_optimizer_path_check(
					false,
					! $skip['optipng'],
					! $skip['gifsicle'],
					! $skip['pngout'],
					! $skip['pngquant'],
					! $skip['webp']
				);
			} else {
				$tools = ewww_image_optimizer_path_check(
					false,
					false,
					! $skip['gifsicle'],
					false,
					false,
					false
				);
			}
			// If gifsicle is disabled.
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
				$result = __( 'GIF optimization is disabled', 'ewww-image-optimizer' );
				// If utility checking is on, and gifsicle is not installed.
			} elseif ( ! $skip['gifsicle'] && ! $tools['GIFSICLE'] ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' );
			} else {
				// Otherwise, turn optimization ON.
				$optimize = true;
			}
			// If optimization is turned ON.
			if ( $optimize ) {
				$tempfile = $file . '.tmp'; // temporary GIF output.
				// Run gifsicle on the GIF.
				exec( "$nice " . $tools['GIFSICLE'] . ' -O3 --careful -o ' . ewww_image_optimizer_escapeshellarg( $tempfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) );
				// Retrieve the filesize of the temporary GIF.
				$new_size = ewww_image_optimizer_filesize( $tempfile );
				// If the new GIF is smaller.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $tempfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller GIF.
				} else {
					if ( ewwwio_is_file( $tempfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $tempfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				// If conversion and optimization are both turned OFF, we are done here.
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tools['CWEBP'], $orig_size !== $new_size );
				break;
			}
			// Get the new filesize for the GIF.
			$new_size = ewww_image_optimizer_filesize( $file );
			// If conversion is ON and the GIF isn't animated.
			if ( $convert && ! ewww_image_optimizer_is_animated( $file ) ) {
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				// If optipng is enabled.
				if ( $tools['OPTIPNG'] ) {
					// Retrieve the optipng optimization level.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && preg_match( '/0.7/', ewww_image_optimizer_tool_found( $tools['OPTIPNG'], 'o' ) ) && ! $keep_metadata ) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// Run optipng on the GIF file.
					exec( "$nice " . $tools['OPTIPNG'] . ' -out ' . ewww_image_optimizer_escapeshellarg( $pngfile ) . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $file ) );
				}
				// If pngout is enabled.
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && $tools['PNGOUT'] ) {
					// Retrieve the pngout optimization level.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					// If $pngfile exists (which means optipng was run already).
					if ( file_exists( $pngfile ) ) {
						// Run pngout on the PNG file.
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					} else {
						// Run pngout on the GIF file.
						exec( "$nice " . $tools['PNGOUT'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $file ) . ' ' . ewww_image_optimizer_escapeshellarg( $pngfile ) );
					}
				}
				// Retrieve the filesize of the PNG.
				$png_size = ewww_image_optimizer_filesize( $pngfile );
				// If the new PNG is smaller than the original GIF.
				if ( $png_size && $new_size > $png_size && ewww_image_optimizer_mimetype( $pngfile, 'i' ) === 'image/png' ) {
					// Store the PNG size as the new filesize.
					$new_size = $png_size;
					// If the user wants original GIFs deleted after successful conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original GIF.
						ewwwio_delete_file( $file );
					}
					// Update the $file location with the new PNG.
					$file = $pngfile;
					// Let webp know what we're dealing with now.
					$type = 'image/png';
					// Normally this would be at the end of the section, but we only want to do webp if the image was successfully converted to a png.
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['CWEBP'], $orig_size !== $new_size );
					// Successful conversion, so we store the increment.
					$converted = $filenum;
				} else {
					$converted = false;
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_delete_file( $pngfile );
					}
				}
			} // End if().
			$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['CWEBP'], $orig_size !== $new_size );
			break;
		case 'application/pdf':
			if ( ! empty( $ewww_webp_only ) ) {
				break;
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' );
			if ( empty( $ewww_force ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( $compression_level > 0 ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
			}
			break;
		default:
			// If not a JPG, PNG, or GIF, tell the user we don't work with strangers.
			return array( false, __( 'Unsupported file type', 'ewww-image-optimizer' ) . ": $type", $converted, $original );
	} // End switch().
	// Allow other plugins to run operations on the images after optimization.
	// NOTE: it is recommended to do any image modifications prior to optimization, otherwise you risk un-optimizing your images here.
	do_action( 'ewww_image_optimizer_post_optimization', $file, $type, $fullsize );
	// If their cloud api license limit has been exceeded.
	if ( 'exceeded' === $result ) {
		return array( false, __( 'License exceeded', 'ewww-image-optimizer' ), $converted, $original );
	}
	if ( ! empty( $new_size ) ) {
		// Set correct file permissions.
		$stat = stat( dirname( $file ) );
		ewwwio_debug_message( 'folder mode: ' . $stat['mode'] );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		ewwwio_debug_message( "attempting chmod with $perms" );
		ewwwio_chmod( $file, $perms );

		$results_msg = ewww_image_optimizer_update_table( $file, $new_size, $orig_size, $original, $backup_hash );
		if ( ! empty( $webp_result ) ) {
			$results_msg .= '<br>' . $webp_result;
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $results_msg, $converted, $original );
	}
	ewwwio_memory( __FUNCTION__ );
	// Otherwise, send back the filename, the results (some sort of error message), the $converted flag, and the name of the original image.
	if ( ! empty( $webp_result ) && ! empty( $ewww_webp_only ) ) {
		$result = $webp_result;
		return array( true, $result, $converted, $original );
	}
	return array( false, $result, $converted, $original );
}

/**
 * Creates WebP images alongside JPG and PNG files.
 *
 * @param string $file The name of the JPG/PNG file.
 * @param int    $orig_size The filesize of the JPG/PNG file.
 * @param string $type The mime-type of the incoming file.
 * @param string $tool The path to the cwebp binary, if installed.
 * @param bool   $recreate True to keep the .webp image even if it is larger than the JPG/PNG.
 * @return string Results of the WebP operation for display.
 */
function ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tool, $recreate = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_force;
	$webpfile = $file . '.webp';
	if ( apply_filters( 'ewww_image_optimizer_bypass_webp', false, $file ) ) {
		ewwwio_debug_message( "webp generation bypassed: $file" );
		return '';
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		return '';
	} elseif ( ! ewwwio_is_file( $file ) ) {
		return esc_html__( 'Could not find file.', 'ewww-image-optimizer' );
	} elseif ( ! is_writable( $file ) ) {
		return esc_html__( 'File is not writable.', 'ewww-image-optimizer' );
	} elseif ( ewwwio_is_file( $webpfile ) && empty( $ewww_force ) && ! $recreate ) {
		ewwwio_debug_message( 'webp file exists, not forcing or recreating' );
		return esc_html__( 'WebP image already exists.', 'ewww-image-optimizer' );
	}
	if ( empty( $tool ) || 'image/gif' === $type ) {
		ewww_image_optimizer_cloud_optimizer( $file, $type, false, $webpfile, 'image/webp' );
	} else {
		$nice = '';
		if ( ! EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			ewww_image_optimizer_define_noexec();
			if ( ! EWWW_IMAGE_OPTIMIZER_NOEXEC && PHP_OS !== 'WINNT' ) {
				// Check to see if 'nice' exists.
				$nice = ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
			}
		}
		// Check to see if we are supposed to strip metadata.
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ) {
			// Don't copy metadata.
			$copy_opt = 'none';
		} else {
			// Copy all the metadata.
			$copy_opt = 'all';
		}
		$quality = (int) apply_filters( 'jpeg_quality', 82, 'image/webp' );
		if ( $quality < 50 ) {
			$quality = 82;
		}
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) && EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
			$lossless = "-q $quality";
		} else {
			$lossless = '-lossless';
		}
		switch ( $type ) {
			case 'image/jpeg':
				ewwwio_debug_message( "$nice " . $tool . " -q $quality -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1' );
				exec( "$nice " . $tool . " -q $quality -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
				break;
			case 'image/png':
				ewwwio_debug_message( "$nice " . $tool . " $lossless -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1' );
				exec( "$nice " . $tool . " $lossless -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
				break;
		}
	}
	$webp_size = ewww_image_optimizer_filesize( $webpfile );
	ewwwio_debug_message( "webp is $webp_size vs. $type is $orig_size" );
	if ( ewwwio_is_file( $webpfile ) && $orig_size < $webp_size && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
		ewwwio_debug_message( 'webp file was too big, deleting' );
		ewwwio_delete_file( $webpfile );
		return esc_html__( 'WebP image was larger than original.', 'ewww-image-optimizer' );
	} elseif ( ewwwio_is_file( $webpfile ) ) {
		// Set correct file permissions.
		$stat  = stat( dirname( $webpfile ) );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		ewwwio_chmod( $webpfile, $perms );
		if ( $orig_size < $webp_size && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
			return esc_html__( 'WebP image larger than original, saved anyway with Force WebP option.', 'ewww-image-optimizer' );
		}
		return 'WebP: ' . ewww_image_optimizer_image_results( $orig_size, $webp_size );
	}
	return esc_html__( 'Image could not be converted to WebP.', 'ewww-image-optimizer' );
}

/**
 * Redirects back to previous page after PNGOUT installation.
 */
function ewww_image_optimizer_install_pngout_wrapper() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'You do not have permission to install image optimizer utilities.', 'ewww-image-optimizer' ) );
	}
	$sendback = ewww_image_optimizer_install_pngout();
	wp_safe_redirect( $sendback );
	ewwwio_memory( __FUNCTION__ );
	exit( 0 );
}

/**
 * Installs pngout from the official site.
 *
 * @return string The url from whence we came (settings page), with success or error parameters added.
 */
function ewww_image_optimizer_install_pngout() {
	if ( ! extension_loaded( 'zlib' ) || ! class_exists( 'PharData' ) ) {
		$pngout_error = __( 'zlib or phar extension missing from PHP', 'ewww-image-optimizer' );
	}
	if ( PHP_OS === 'Linux' ) {
		$os_string = 'linux';
	}
	if ( PHP_OS === 'FreeBSD' ) {
		$os_string = 'bsd';
	}
	$latest    = '20150319';
	$tool_path = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( empty( $pngout_error ) ) {
		if ( PHP_OS === 'Linux' || PHP_OS === 'FreeBSD' ) {
			$download_result = download_url( 'http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz' );
			if ( is_wp_error( $download_result ) ) {
				$pngout_error = $download_result->get_error_message();
			} else {
				if ( ! ewwwio_check_memory_available( filesize( $download_result ) + 1000 ) ) {
					$pngout_error = __( 'insufficient memory available for installation', 'ewww-image-optimizer' );
				} else {
					$arch_type = 'i686';
					if ( ewww_image_optimizer_function_exists( 'php_uname' ) ) {
						$arch_type = php_uname( 'm' );
					}

					$tmpname  = current( explode( '.', $download_result ) );
					$tmpname .= '-' . uniqid() . '.tar.gz';
					rename( $download_result, $tmpname );
					$download_result = $tmpname;

					$pngout_gzipped  = new PharData( $download_result );
					$pngout_tarball  = $pngout_gzipped->decompress();
					$download_result = $pngout_tarball->getPath();
					$pngout_tarball->extractTo(
						EWWW_IMAGE_OPTIMIZER_BINARY_PATH,
						'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static',
						true
					);

					if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static' ) ) {
						if ( ! rename( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', $tool_path . 'pngout-static' ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
							}
						}
						if ( ! chmod( $tool_path . 'pngout-static', 0755 ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not set permissions', 'ewww-image-optimizer' );
							}
						}
						$pngout_version = ewww_image_optimizer_tool_found( ewww_image_optimizer_escapeshellarg( $tool_path ) . 'pngout-static', 'p' );
					} else {
						$pngout_error = __( 'extraction of files failed', 'ewww-image-optimizer' );
					}
				}
			}
		}
		if ( PHP_OS === 'Darwin' ) {
			$latest          = '20150920';
			$download_result = download_url( 'http://static.jonof.id.au/dl/kenutils/pngout-' . $latest . '-darwin.tar.gz' );
			if ( is_wp_error( $download_result ) ) {
				$pngout_error = $download_result->get_error_message();
			} else {
				if ( ! ewwwio_check_memory_available( filesize( $download_result ) + 1000 ) ) {
					$pngout_error = __( 'insufficient memory available for installation', 'ewww-image-optimizer' );
				} else {
					$tmpname  = current( explode( '.', $download_result ) );
					$tmpname .= '-' . uniqid() . '.tar.gz';
					rename( $download_result, $tmpname );
					$download_result = $tmpname;

					$pngout_gzipped  = new PharData( $download_result );
					$pngout_tarball  = $pngout_gzipped->decompress();
					$download_result = $pngout_tarball->getPath();
					$pngout_tarball->extractTo(
						EWWW_IMAGE_OPTIMIZER_BINARY_PATH,
						'pngout-' . $latest . '-darwin/pngout',
						true
					);
					if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-darwin/pngout' ) ) {
						if ( ! rename( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-darwin/pngout', $tool_path . 'pngout-static' ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
							}
						}
						if ( ! chmod( $tool_path . 'pngout-static', 0755 ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not set permissions', 'ewww-image-optimizer' );
							}
						}
						$pngout_version = ewww_image_optimizer_tool_found( ewww_image_optimizer_escapeshellarg( $tool_path ) . 'pngout-static', 'p' );
					} else {
						$pngout_error = __( 'extraction of files failed', 'ewww-image-optimizer' );
					}
				}
			}
		}
	} // End if().
	if ( PHP_OS === 'WINNT' ) {
		$download_result = download_url( 'http://advsys.net/ken/util/pngout.exe' );
		if ( is_wp_error( $download_result ) ) {
			$pngout_error = $download_result->get_error_message();
		} else {
			if ( ! rename( $download_result, $tool_path . 'pngout.exe' ) ) {
				if ( empty( $pngout_error ) ) {
					$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
				}
			}
			$pngout_version = ewww_image_optimizer_tool_found( '"' . $tool_path . 'pngout.exe"', 'p' );
		}
	}
	if ( is_string( $download_result ) && is_writable( $download_result ) ) {
		unlink( $download_result );
	}
	if ( ! empty( $pngout_version ) ) {
		$sendback = add_query_arg( 'ewww_pngout', 'success', remove_query_arg( array( 'ewww_pngout', 'ewww_error' ), wp_get_referer() ) );
	}
	if ( ! isset( $sendback ) ) {
		$sendback = add_query_arg(
			array(
				'ewww_pngout' => 'failed',
				'ewww_error'  => urlencode( $pngout_error ),
			),
			remove_query_arg( array( 'ewww_pngout', 'ewww_error' ), wp_get_referer() )
		);
	}
	return $sendback;
}

/**
 * Removes any binaries that have been installed in the wp-content/ewww/ folder.
 */
function ewww_image_optimizer_remove_binaries() {
	if ( ! class_exists( 'RecursiveIteratorIterator' ) ) {
		return;
	}
	if ( ! is_dir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$path = $file->getPathname();
			if ( is_writable( $path ) ) {
				unlink( $path );
			}
		}
	}
	if ( ! class_exists( 'FilesystemIterator' ) ) {
		return;
	}
	clearstatcache();
	$iterator = new FilesystemIterator( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( ! $iterator->valid() ) {
		rmdir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	}
}
