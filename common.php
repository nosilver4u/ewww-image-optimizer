<?php
// common functions for Standard and Cloud plugins

// TODO: use <picture> element to serve webp
// TODO: see if we can offer a rebuild option, to fill in missing thumbs
// TODO: look at simple_html_dom_node that wp retina uses for parsing
// TODO: so, if lazy loading support sucks, can we roll our own? that's an image "optimization", right?...
// TODO: prevent bad ajax errors from firing when we click the toggle on the Optimization Log, and the plugin status from doing 403s...
// TODO: use a transient to do health checks on the schedule optimizer
// TODO: add a column to track compression level used for each image, and later implement a way to (re)compress at a specific compression level
// TODO: implement (optional) backups with a .bak extension for every file
// TODO: might be able to use the Custom Bulk Actions in 4.7 to support the bulk optimize drop-down menu
// TODO: see if there is a way to query the last couple months (or 30 days) worth of attachments, but only when the user is not using year/month folders. This way, they can catch extraneous images if possible. Use the post_date field, and do a media_scan followed by the folder scan to catch remaining images
// TODO: move resizing options into their own sub-menu
// TODO: see if retina optimization can be delayed until background opt, should be since we check for hidpi versions during resize_from_meta
// TODO: add an override for network admins to allow site admins to configure their own sites
// TODO: even without the override, the size (disabling) settings need to be exposed per-site
// TODO: see if there is a way to make the bulk time elapsed obey locale settings for decimal vs. comma
// TODO: check for Pantheon platform, and use relative path matching somehow: if ( in_array( $_ENV['PANTHEON_ENVIRONMENT'], Array('test', 'live') ) ) and include the CONSTANT in the path when storing in db
// TODO: need to make the scheduler so it can resume without having to re-run the queue population, and then we can probably also flush the queue when scheduled opt starts, but later it would be nice to implement the bulk_loop as the aux_loop so that it could handle media properly
// TODO: implement a search for the bulk table, or maybe we should just move it to it's own page?
// TODO: use new function_exists test for exec
// TODO: check for print_r before using
// TODO: if two records are found for an image, and one is pending, remove it

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EWWW_IMAGE_OPTIMIZER_VERSION', '326.0' );

// initialize a couple globals
$ewww_debug = '';
$ewww_defer = true;

// check the PHP version
if ( ! defined( 'PHP_VERSION_ID' ) ) {
	$php_version = explode( '.', PHP_VERSION );
	define( 'PHP_VERSION_ID', ( $version[0] * 10000 + $version[1] * 100 + $version[2] ) );
}

if ( WP_DEBUG ) {
	$ewww_memory = 'plugin load: ' . memory_get_usage( true ) . "\n";
}

// setup custom $wpdb attribute for our image-tracking table
global $wpdb;
if ( ! isset( $wpdb->ewwwio_images ) ) {
	$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";
}

/**
 * Hooks
 */
if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
	// used to move the W3TC CDN uploads filter
	add_filter( 'wp_handle_upload', 'ewww_image_optimizer_handle_upload' );
	// used to turn off ewwwio_image_editor during uploads
	add_action( 'add_attachment', 'ewww_image_optimizer_add_attachment' );
	// turn off ewwwio_image_editor during Enable Media Replace
	add_filter( 'emr_unfiltered_get_attached_file', 'ewww_image_optimizer_image_sizes' );
	add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	// add hook for PTE confirmation
	add_filter( 'wp_get_attachment_metadata', 'ewww_image_optimizer_pte_check' );
}
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_self', 10, 2 );
// this filter turns off ewwwio_image_editor during save from the actual image editor
// and ensures that we parse the resizes list during the image editor save function
add_filter( 'load_image_to_edit_path', 'ewww_image_optimizer_editor_save_pre' );
// this hook is used to ensure we populate the metadata with webp images
add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment_metadata', 8, 2 );
add_filter( 'manage_media_columns', 'ewww_image_optimizer_columns' );
// filters to set default permissions, anyone can override these if they wish
add_filter( 'ewww_image_optimizer_manual_permissions', 'ewww_image_optimizer_manual_permissions', 8 );
add_filter( 'ewww_image_optimizer_bulk_permissions', 'ewww_image_optimizer_admin_permissions', 8 );
add_filter( 'ewww_image_optimizer_admin_permissions', 'ewww_image_optimizer_admin_permissions', 8 );
add_filter( 'ewww_image_optimizer_superadmin_permissions', 'ewww_image_optimizer_superadmin_permissions', 8 );
// variable for plugin settings link
$plugin = plugin_basename ( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE );
add_filter( "plugin_action_links_$plugin", 'ewww_image_optimizer_settings_link' );
add_filter( 'intermediate_image_sizes_advanced', 'ewww_image_optimizer_image_sizes_advanced' );
add_filter( 'fallback_intermediate_image_sizes', 'ewww_image_optimizer_fallback_sizes' );
add_filter( 'ewww_image_optimizer_settings', 'ewww_image_optimizer_filter_settings_page' );
add_filter( 'myarcade_filter_screenshot', 'ewww_image_optimizer_myarcade_thumbnail' );
add_filter( 'myarcade_filter_thumbnail', 'ewww_image_optimizer_myarcade_thumbnail' );
add_filter( 'jpeg_quality', 'ewww_image_optimizer_set_jpg_quality' );
add_filter( 'ewww_image_optimizer_bypass', 'ewww_image_optimizer_ignore_file', 10, 2 );
add_action( 'manage_media_custom_column', 'ewww_image_optimizer_custom_column', 10, 2 );
add_action( 'plugins_loaded', 'ewww_image_optimizer_preinit' );
add_action( 'init', 'ewww_image_optimizer_gallery_support' );
add_action( 'admin_init', 'ewww_image_optimizer_admin_init' );
add_action( 'admin_action_ewww_image_optimizer_manual_optimize', 'ewww_image_optimizer_manual' );
add_action( 'admin_action_ewww_image_optimizer_manual_restore', 'ewww_image_optimizer_manual' );
add_action( 'admin_action_ewww_image_optimizer_manual_convert', 'ewww_image_optimizer_manual' );
add_action( 'delete_attachment', 'ewww_image_optimizer_delete' );
add_action( 'admin_menu', 'ewww_image_optimizer_admin_menu', 60 );
add_action( 'network_admin_menu', 'ewww_image_optimizer_network_admin_menu' );
add_action( 'load-upload.php', 'ewww_image_optimizer_load_admin_js' );
add_action( 'admin_action_bulk_optimize', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action( 'admin_action_-1', 'ewww_image_optimizer_bulk_action_handler' ); 
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_media_scripts' );
add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_settings_script' );
add_action( 'ewww_image_optimizer_auto', 'ewww_image_optimizer_auto' );
//add_action( 'ewww_image_optimizer_defer', 'ewww_image_optimizer_defer' );
add_action( 'wr2x_retina_file_added', 'ewww_image_optimizer_retina', 20, 2 );
add_action( 'wp_ajax_ewww_webp_rewrite', 'ewww_image_optimizer_webp_rewrite' );
add_action( 'wp_ajax_ewww_manual_optimize', 'ewww_image_optimizer_manual' );
add_action( 'wp_ajax_ewww_manual_restore', 'ewww_image_optimizer_manual' );
add_action( 'wp_enqueue_scripts', 'ewww_image_optimizer_resize_detection_script' );
add_action( 'admin_bar_init', 'ewww_image_optimizer_admin_bar_init' );
add_action( 'admin_action_ewww_image_optimizer_delete_debug_log', 'ewww_image_optimizer_delete_debug_log' );

register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_network_deactivate' );
register_uninstall_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_uninstall' );
//add_action( 'shutdown', 'ewwwio_memory_output' );
if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
	add_action( 'template_redirect', 'ewww_image_optimizer_buffer_start' );
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		add_action( 'wp_enqueue_scripts', 'ewww_image_optimizer_webp_debug_script' );
	} else {
		add_action( 'wp_enqueue_scripts', 'ewww_image_optimizer_webp_load_jquery' );
		add_action( 'wp_head', 'ewww_image_optimizer_webp_inline_script' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'iocli.php' );
}

function ewww_image_optimizer_ignore_self( $skip, $filename ) {
	if ( 0 === strpos( $filename, EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH ) ) {
		return true;
	}
	return $skip;
}

function ewww_image_optimizer_get_plugin_version( $plugin_file ) {
        $default_headers = array(
		'Version' => 'Version',
	);
	$plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );
	return $plugin_data;
}

function ewww_image_optimizer_ce_webp_enabled() {
	if ( class_exists( 'Cache_Enabler' ) ) {
		$ce_options = Cache_Enabler::$options;
		if ( $ce_options['webp'] ) {
			ewwwio_debug_message( 'Cache Enabler webp option enabled' );
			return true;
		}
	}
	return false;
}

// functions to capture all page output, replace image urls with webp derivatives, and add webp fallback 
function ewww_image_optimizer_buffer_start() {
	ob_start( 'ewww_image_optimizer_filter_page_output' );
}

function ewww_image_optimizer_buffer_end() {
	ob_end_flush();
}

// Copies attributes from the original img object to the noscript object
function ewww_image_optimizer_webp_attr_copy( $image, &$nscript, $prefix = 'data-' ) {
	if ( ! is_object( $image ) || ! is_object( $nscript ) ) {
		return;
	}
	$attributes = array( 'align', 'alt', 'border', 'crossorigin', 'height', 'hspace', 'ismap', 'longdesc', 'usemap', 'vspace', 'width', 'accesskey', 'class', 'contenteditable', 'contextmenu', 'dir', 'draggable', 'dropzone', 'hidden', 'id', 'lang', 'spellcheck', 'style', 'tabindex', 'title', 'translate', 'sizes', 'data-lazy-type' );
	foreach ( $attributes as $attribute ) {
		if ( $image->getAttribute( $attribute ) )
			$nscript->setAttribute( $prefix . $attribute, $image->getAttribute( $attribute ) );
	}
}

function ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path ) {
	$srcset_urls = explode( ' ', $srcset );
	$found_webp = false;
	if ( ewww_image_optimizer_iterable( $srcset_urls ) ) {
		ewwwio_debug_message( 'found srcset urls' );
		foreach ( $srcset_urls as $srcurl ) {
			if ( is_numeric( substr( $srcurl, 0, 1 ) ) ) {
				continue;
			}
			$srcfilepath = ABSPATH . str_replace( $home_url, '', $srcurl );
			ewwwio_debug_message( "looking for srcurl on disk: $srcfilepath" );
			if ( $valid_path || file_exists( $srcfilepath . '.webp' ) ) {
				$srcset = preg_replace( "|$srcurl|", $srcurl . '.webp', $srcset );
				$found_webp = true;
				ewwwio_debug_message( "replacing $srcurl in $srcset" );
			}
		}
	}
	if ( $found_webp ) {
		return $srcset;
	} else {
		return false;
	}
}

// look for images, links, list items, etc. and rewrite things to use webp
function ewww_image_optimizer_filter_page_output( $buffer ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ( empty ( $buffer ) || is_admin() ) ) {
		return $buffer;
	}
	if ( ewww_image_optimizer_ce_webp_enabled() ) {
		return $buffer;
	}
	$uri = $_SERVER['REQUEST_URI'];
	if ( strpos( $uri, '&cornerstone=1' ) || strpos( $uri, 'cornerstone-endpoint' ) !== false ) {
		return $buffer;
	}
	// modify buffer here, and then return the updated code
	if ( class_exists( 'DOMDocument' ) ) {
		if ( preg_match( '/<\?xml/', $buffer ) ) {
			return $buffer;
		}
		$expanded_head = false;
		preg_match( '/.+<head ?\>/s', $buffer, $html_head );
		if ( empty( $html_head ) ) {
			ewwwio_debug_message( 'did not find head tag' );
			preg_match( '/.+<head [^>]*>/s', $buffer, $html_head );
			if ( empty( $html_head ) ) {
				ewwwio_debug_message( 'did not find expanded head tag either' );
				return $buffer;
			}
		}
		$html = new DOMDocument;
		$libxml_previous_error_reporting = libxml_use_internal_errors( true );
		$html->formatOutput = false;
		$html->encoding = 'utf-8';
		if ( defined( 'LIBXML_VERSION' ) && LIBXML_VERSION < 20800 ) {
			ewwwio_debug_message( 'libxml version: ' . LIBXML_VERSION );
			// converts the buffer from utf-8 to html-entities
			$buffer = mb_convert_encoding( $buffer, 'HTML-ENTITIES', 'UTF-8' );
		} elseif ( ! defined( 'LIBXML_VERSION' ) ) {
			ewwwio_debug_message( 'cannot detect libxml version' );
			return $buffer;
		}
		if ( preg_match( '/<.DOCTYPE.+xhtml/', $buffer ) ) {
			$html->recover = true;
			$xhtml_parse = $html->loadXML( $buffer );
			ewwwio_debug_message( 'parsing as xhtml' );
		} elseif ( empty( $xhtml_parse ) ) {
			$html->loadHTML( $buffer );
			ewwwio_debug_message( 'parsing as html' );
		}
		$html->encoding = 'utf-8';
		$home_url = get_site_url();
		$webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( ! is_array( $webp_paths ) ) {
			$webp_paths = array();
		}
		$home_relative_url = preg_replace( '/https?:/', '', $home_url );
		$images = $html->getElementsByTagName( 'img' );
		if ( ewww_image_optimizer_iterable( $images ) ) {
			foreach ( $images as $image ) {
				if ( $image->parentNode->tagName == 'noscript' ) {
					continue;
				}
				$srcset = '';
				ewwwio_debug_message( 'parsing an image' );
				$file = $image->getAttribute( 'src' );
				$valid_path = false;
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
					//check path
					foreach ( $webp_paths as $webp_path ) {
						if ( strpos( $file, $webp_path ) !== false ) {
							$valid_path = true;
						}
					}
				}
				if ( strpos( $file, $home_relative_url ) === 0 ) {
					$filepath = str_replace( $home_relative_url, ABSPATH, $file );
				} else {
					$filepath = str_replace( $home_url, ABSPATH, $file );
				}
				ewwwio_debug_message( "the image is at $filepath" );
				// make this pre-emptively check for the domains so that we don't bother checking file_exists()
				if ( ( $valid_path || file_exists( $filepath . '.webp' ) ) && ! strpos( $file, 'assets/images/dummy.png' ) && ! strpos( $file, 'base64,R0lGOD' ) && ! strpos( $file, 'lazy-load/images/1x1' ) ) {
					$nscript = $html->createElement( 'noscript' );
					$nscript->setAttribute( 'data-img', $file );
					$nscript->setAttribute( 'data-webp', $file . '.webp' );
					if ( $image->getAttribute( 'srcset' ) ) {
						$srcset = $image->getAttribute( 'srcset' );
						$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
						if ( $srcset_webp ) {
							$nscript->setAttribute( 'data-srcset-webp', $srcset_webp );
						}
						$nscript->setAttribute( 'data-srcset-img', $srcset );
					}
					ewww_image_optimizer_webp_attr_copy( $image, $nscript );
					$nscript->setAttribute( 'class', 'ewww_webp' );
					$image->parentNode->replaceChild($nscript, $image);
					$nscript->appendChild( $image );
				}
				// NextGEN
				if ( empty( $file ) && $image->getAttribute( 'data-src' ) && $image->getAttribute( 'data-thumbnail' ) ) {
					$file = $image->getAttribute( 'data-src' );
					$thumb = $image->getAttribute( 'data-thumbnail' );
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( $valid_path || file_exists( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-src: $filepath" );
						$image->setAttribute( 'data-webp', $file . '.webp' );
					}
					if ( $valid_path || file_exists( $thumbpath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumbpath" );
						$image->setAttribute( 'data-webp-thumbnail', $thumb . '.webp' );
					}
				}
				// NOTE: lazy loads are shutoff for now, since they don't work consistently
				// WP Retina 2x
				if ( false && empty( $file ) && $image->getAttribute( 'data-srcset' ) && strpos( $image->getAttribute( 'class' ), 'lazyload' ) ) {
					$srcset = $image->getAttribute( 'data-srcset' );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $srcset, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
					if ( $srcset_webp ) {
						$nimage = $html->createElement( 'img' );
						$nimage->setAttribute( 'data-srcset-webp', $srcset_webp );
						$nimage->setAttribute( 'data-srcset-img', $srcset );
						ewww_image_optimizer_webp_attr_copy( $image, $nimage, '' );
						$nimage->setAttribute( 'class', $image->getAttribute( 'class' ) . ' ewww_webp_lazy_retina' );
						$image->parentNode->replaceChild( $nimage, $image );
					}
				}
				// Hueman theme
				if ( false && ! empty( $file ) && strpos( $file, 'image/gif;base64,R0lGOD' ) && $image->getAttribute( 'data-src' ) && $image->getAttribute( 'data-srcset' ) ) {
					$dummy = $file;
					$file = $image->getAttribute( 'data-src' );
					ewwwio_debug_message( "checking webp for hueman data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( $valid_path || file_exists( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for Hueman lazyload: $filepath" );
						$nscript = $html->createElement( 'noscript' );
						$nscript->setAttribute( 'data-src', $dummy );
						$nscript->setAttribute( 'data-img', $file );
						$nscript->setAttribute( 'data-webp-src', $file . '.webp' );
						$image->setAttribute( 'src', $file );
						if ( $image->getAttribute( 'data-srcset' ) ) {
							$srcset = $image->getAttribute( 'data-srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$nscript->setAttribute( 'data-srcset-webp', $srcset_webp );
							}
							$nscript->setAttribute( 'data-srcset-img', $srcset );
						}
						ewww_image_optimizer_webp_attr_copy( $image, $nscript );
						$nscript->setAttribute( 'class', 'ewww_webp_lazy_hueman' );
						$image->parentNode->replaceChild( $nscript, $image );
						$nscript->appendChild( $image );
					}
				}
				// Lazy Load plugin (and hopefully Cherry variant) and BJ Lazy Load
				if ( false && ! empty( $file ) && ( strpos( $file, 'image/gif;base64,R0lGOD' ) || strpos( $file, 'lazy-load/images/1x1' ) ) && $image->getAttribute( 'data-lazy-src' ) && ! empty( $image->nextSibling ) && $image->nextSibling->nodeName == 'noscript' ) {
					$dummy = $file;
					$nimage = $html->createElement( 'img' );
					$nimage->setAttribute( 'src', $dummy );
					$file = $image->getAttribute( 'data-lazy-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-lazy-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( $valid_path || file_exists( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $filepath" );
						//$nscript = $image->nextSibling;
						//$skip_append = true;
						//$nscript->setAttribute( 'data-src', $dummy );
						$nimage->setAttribute( 'data-lazy-img-src', $file );
						$nimage->setAttribute( 'data-lazy-webp-src', $file . '.webp' );
						if ( $image->getAttribute( 'srcset' ) ) {
							$srcset = $image->getAttribute( 'srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$nimage->setAttribute( 'data-srcset-webp', $srcset_webp );
							}
							$nimage->setAttribute( 'data-srcset', $srcset );
						}
						if ( $image->getAttribute( 'data-lazy-srcset' ) ) {
							$srcset = $image->getAttribute( 'data-lazy-srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$nimage->setAttribute( 'data-lazy-srcset-webp', $srcset_webp );
							}
							$nimage->setAttribute( 'data-lazy-srcset-img', $srcset );
						}
						ewww_image_optimizer_webp_attr_copy( $image, $nimage, '' );
						$nimage->setAttribute( 'class', $image->getAttribute( 'class' ) . ' ewww_webp_lazy_load' );
						$image->parentNode->replaceChild( $nimage, $image );
					}
				}
				if ( $image->getAttribute( 'data-lazyload' ) ) {
					$lazyload = $image->getAttribute( 'data-lazyload' );
					if ( ! empty( $lazyload ) ) {
						$lazyloadpath = ABSPATH . str_replace( $home_url, '', $lazyload );
						if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
							//check path
							foreach ( $webp_paths as $webp_path ) {
								if ( strpos( $lazyload, $webp_path ) !== false ) {
									$valid_path = true;
								}
							}
						}
						if ( $valid_path || file_exists( $lazyloadpath . '.webp' ) ) {
							ewwwio_debug_message( "found webp for data-lazyload: $filepath" );
							$image->setAttribute( 'data-webp-lazyload', $lazyload . '.webp' );
						}
					}
				}
			}
		}
		// NextGEN slides
		$links = $html->getElementsByTagName( 'a' );
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $link ) {
				ewwwio_debug_message( 'parsing a link' );
				if ( $link->getAttribute( 'data-src' ) && $link->getAttribute( 'data-thumbnail' ) ) {
					$file = $link->getAttribute( 'data-src' );
					$thumb = $link->getAttribute( 'data-thumbnail' );
					$valid_path = false;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( $valid_path || file_exists( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-src: $filepath" );
						$link->setAttribute( 'data-webp', $file . '.webp' );
					}
					if ( $valid_path || file_exists( $thumbpath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumbpath" );
						$link->setAttribute( 'data-webp-thumbnail', $thumb . '.webp' );
					}
					
				}
			}
		}
		// Revolution Slider
		$listitems = $html->getElementsByTagName( 'li' );
		if ( ewww_image_optimizer_iterable( $listitems ) ) {
			foreach ( $listitems as $listitem ) {
				ewwwio_debug_message( 'parsing a listitem' );
				if ( $listitem->getAttribute( 'data-title' ) === 'Slide' && ( $listitem->getAttribute( 'data-lazyload' ) || $listitem->getAttribute( 'data-thumb' ) ) ) {
					$thumb = $listitem->getAttribute( 'data-thumb' );
					$valid_path = false;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						//check path
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $thumb, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for revslider data-thumb: $thumb" );
					$thumbpath = str_replace( $home_url, ABSPATH, $thumb );
					if ( $valid_path || file_exists( $thumbpath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for revslider data-thumb: $thumbpath" );
						$listitem->setAttribute( 'data-webp-thumb', $thumb . '.webp' );
					}
					$param_num = 1;
					while( $param_num < 11 ) {
						$parameter = '';
						if ( $listitem->getAttribute( 'data-param' . $param_num ) ) {
							$parameter = $listitem->getAttribute( 'data-param' . $param_num );
							ewwwio_debug_message( "checking webp for revslider data-param$param_num: $parameter" );
							if ( ! empty( $parameter ) && strpos( $parameter, 'http' ) === 0 ) {
								$parameter_path = str_replace( $home_url, ABSPATH, $parameter );
								ewwwio_debug_message( "looking for $parameter_path" );
								if ( $valid_path || file_exists( $parameter_path . '.webp' ) ) {
									ewwwio_debug_message( "found webp for data-param$param_num: $parameter_path" );
									$listitem->setAttribute( 'data-webp-param' . $param_num, $parameter . '.webp' );
								}
							}
						}
						$param_num++;
					}
				}
			}
		}
		ewwwio_debug_message( 'preparing to dump page back to $buffer' );
		if ( ! empty( $xhtml_parse ) ) {
			$buffer = $html->saveXML( $html->documentElement );
		} else {
			$buffer = $html->saveHTML( $html->documentElement );
		}
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_previous_error_reporting );
/*		ewwwio_debug_message( 'html head' );
		ewwwio_debug_message( $html_head[0] );
		ewwwio_debug_message( 'buffer beginning' );
		ewwwio_debug_message( substr( $buffer, 0, 500 ) );*/
		if ( ! empty( $html_head ) ) {
			$buffer = preg_replace( '/<html.+>\s.*<head>/', $html_head[0], $buffer );
		}
		// do some cleanup for the Easy Social Share Buttons for WordPress plugin (can't have <li> elements with newlines between them)
		$buffer = preg_replace( '/\s(<li class="essb_item)/', '$1', $buffer );
//		ewwwio_debug_message( 'buffer after replacement' );
//		ewwwio_debug_message( substr( $buffer, 0, 500 ) );
//		ewww_image_optimizer_debug_log();
	}
//	ewww_image_optimizer_debug_log();
	return $buffer;
}

// set permissions for various operations
function ewww_image_optimizer_manual_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'edit_others_posts';
	}
	return $permissions;
}

function ewww_image_optimizer_admin_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'activate_plugins';
	}
	return $permissions;
}

function ewww_image_optimizer_superadmin_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'manage_network_options';
	}
	return $permissions;
}

if ( ! function_exists( 'boolval' ) ) {
	function boolval( $value ) {
		return (bool) $value;
	}
}

// function to check if set_time_limit() is allowed
function ewww_image_optimizer_stl_check() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_safemode_check() ) {
		return false;
	}
	if ( defined( 'EWWW_IMAGE_OPTIMIZER_DISABLE_STL' ) && EWWW_IMAGE_OPTIMIZER_DISABLE_STL ) {
		ewwwio_debug_message( 'stl disabled by user' );
		return false;
	}
	$disabled = ini_get('disable_functions');
	ewwwio_debug_message( "disable_functions = $disabled" );
	if ( preg_match( '/set_time_limit/', $disabled ) ) {
		ewwwio_memory( __FUNCTION__ );
		return false;
	} elseif ( function_exists( 'set_time_limit' ) ) {
		ewwwio_memory( __FUNCTION__ );
		return true;
	} else {
		ewwwio_debug_message( 'set_time_limit does not exist' );
		return false;
	}
}

// checks if a function is disabled or does not exist
function ewww_image_optimizer_function_exists( $function ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'ini_get' ) ) {
		$disabled = @ini_get( 'disable_functions' );
		ewwwio_debug_message( "disable_functions: $disabled" );
	}
//	$disabled = explode( ',', ini_get( 'disable_functions' )
	if ( extension_loaded( 'suhosin' ) && function_exists( 'ini_get' ) ) {
		$suhosin_disabled = @ini_get( 'suhosin.executor.func.blacklist' );
		ewwwio_debug_message( "suhosin_blacklist: $suhosin_disabled" );
		if ( ! empty( $suhosin_disabled ) ) {
			$suhosin_disabled = explode( ',', $suhosin_disabled );
			$suhosin_disabled = array_map( 'trim', $suhosin_disabled );
			$suhosin_disabled = array_map( 'strtolower', $suhosin_disabled );
			if ( function_exists( $function ) && ! in_array( $function, $suhosin_disabled ) ) {
				return true;
			}
			return false;
		}
	}
	return function_exists( $function );
}

function ewww_image_optimizer_preinit() {
	load_plugin_textdomain( EWWW_IMAGE_OPTIMIZER_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
	
function ewww_image_optimizer_gallery_support() {	
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$active_plugins = get_option( 'active_plugins' );
	if ( is_multisite() && is_array( $active_plugins ) ) {
		$sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( is_array( $sitewide_plugins ) ) {
			$active_plugins = array_merge( $active_plugins, array_flip( $sitewide_plugins ) );
		}
	}
	if ( ewww_image_optimizer_iterable( $active_plugins ) ) {
		foreach ( $active_plugins as $active_plugin ) {
			if ( strpos( $active_plugin, '/nggallery.php' ) || strpos( $active_plugin, '\nggallery.php' ) ) {
				$ngg = ewww_image_optimizer_get_plugin_version( trailingslashit( WP_PLUGIN_DIR ) . $active_plugin );
				// include the file that loads the nextgen gallery optimization functions
				ewwwio_debug_message( 'Nextgen version: ' . $ngg['Version'] );
				if (preg_match('/^2\./', $ngg['Version'])) { // for Nextgen 2
					ewwwio_debug_message( "loading nextgen2 support for $active_plugin" );
					require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'nextgen2-integration.php' );
				} else {
					preg_match( '/\d+\.\d+\.(\d+)/', $ngg['Version'], $nextgen_minor_version);
					if ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] < 14 ) {
						ewwwio_debug_message( "NOT loading nextgen legacy support for $active_plugin" );
					} elseif ( ! empty( $nextgen_minor_version[1] ) && $nextgen_minor_version[1] > 13 ) {
						ewwwio_debug_message( "loading nextcellent support for $active_plugin" );
						require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'nextcellent-integration.php' );
					}
				}
			}
			if ( strpos( $active_plugin, '/flag.php' ) || strpos( $active_plugin, '\flag.php' ) ) {
				ewwwio_debug_message( "loading flagallery support for $active_plugin" );
				// include the file that loads the grand flagallery optimization functions
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'flag-integration.php' );
			}
		}
	}
//	ewww_image_optimizer_debug_log();
}

/**
 * Plugin upgrade function
 */
function ewww_image_optimizer_upgrade() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	if ( get_option( 'ewww_image_optimizer_version' ) < EWWW_IMAGE_OPTIMIZER_VERSION ) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		ewww_image_optimizer_enable_background_optimization();
		ewww_image_optimizer_install_table();
		ewww_image_optimizer_set_defaults();
		if ( get_option( 'ewww_image_optimizer_version' ) < 297.5 ) {
			// cleanup background test mess
			wp_clear_scheduled_hook( 'wp_ewwwio_test_optimize_cron' );
			global $wpdb;

			$table  = $wpdb->options;
			$column = 'option_name';

			if ( is_multisite() ) {
				$table  = $wpdb->sitemeta;
				$column = 'meta_key';
			}

			$key = 'wp_ewwwio_test_optimize_batch_%';

			$wpdb->query( "DELETE FROM $table WHERE $column LIKE '$key'" );

		}
		if ( get_option( 'ewww_image_optimizer_version' ) < 280 ) {
			ewww_image_optimizer_migrate_settings_to_levels();
		}
		ewww_image_optimizer_remove_obsolete_settings();
		update_option( 'ewww_image_optimizer_version', EWWW_IMAGE_OPTIMIZER_VERSION );
	}
	ewwwio_memory( __FUNCTION__ );
}

function ewww_image_optimizer_enable_background_optimization() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return;
	}
	global $ewwwio_test_async;
	if ( ! class_exists( 'WP_Background_Process' ) ) {
		require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
	}
	if ( ! is_object( $ewwwio_test_async ) ) {
		$ewwwio_test_async = new EWWWIO_Test_Async_Handler();
	}
	ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', false );
	ewwwio_debug_message( 'running test async handler' );
	$ewwwio_test_async->data( array( 'ewwwio_test_verify' => '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' ) )->dispatch();
	ewww_image_optimizer_debug_log();
}

// Plugin initialization for admin area
function ewww_image_optimizer_admin_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_cloud_init();
	ewww_image_optimizer_upgrade();
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// set the common network settings if they have been POSTed
		if ( isset( $_POST['ewww_image_optimizer_delay'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'ewww_image_optimizer_options-options' ) ) {
			ewwwio_debug_message( print_r( $_POST, true ) );
			$_POST['ewww_image_optimizer_debug'] = ( empty( $_POST['ewww_image_optimizer_debug'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_debug', $_POST['ewww_image_optimizer_debug'] );
			$_POST['ewww_image_optimizer_jpegtran_copy'] = ( empty( $_POST['ewww_image_optimizer_jpegtran_copy'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_jpegtran_copy', $_POST['ewww_image_optimizer_jpegtran_copy'] );
			if ( empty( $_POST['ewww_image_optimizer_jpg_level'] ) ) $_POST['ewww_image_optimizer_jpg_level'] = '';
			update_site_option( 'ewww_image_optimizer_jpg_level', (int) $_POST['ewww_image_optimizer_jpg_level'] );
			if ( empty( $_POST[ 'ewww_image_optimizer_png_level'] ) ) $_POST['ewww_image_optimizer_png_level'] = '';
			update_site_option( 'ewww_image_optimizer_png_level', (int) $_POST['ewww_image_optimizer_png_level'] );
			if ( empty( $_POST['ewww_image_optimizer_gif_level'] ) ) $_POST['ewww_image_optimizer_gif_level'] = '';
			update_site_option( 'ewww_image_optimizer_gif_level', (int) $_POST['ewww_image_optimizer_gif_level'] );
			if ( empty( $_POST['ewww_image_optimizer_pdf_level'] ) ) $_POST['ewww_image_optimizer_pdf_level'] = '';
			update_site_option( 'ewww_image_optimizer_pdf_level', (int) $_POST['ewww_image_optimizer_pdf_level'] );
			$_POST['ewww_image_optimizer_lossy_skip_full'] = ( empty( $_POST['ewww_image_optimizer_lossy_skip_full'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_lossy_skip_full', $_POST['ewww_image_optimizer_lossy_skip_full'] );
			$_POST['ewww_image_optimizer_metadata_skip_full'] = ( empty( $_POST['ewww_image_optimizer_metadata_skip_full'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_metadata_skip_full', $_POST['ewww_image_optimizer_metadata_skip_full'] );
			$_POST['ewww_image_optimizer_delete_originals'] = ( empty( $_POST['ewww_image_optimizer_delete_originals'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_delete_originals', $_POST['ewww_image_optimizer_delete_originals'] );
			$_POST['ewww_image_optimizer_jpg_to_png'] = ( empty( $_POST['ewww_image_optimizer_jpg_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_jpg_to_png', $_POST['ewww_image_optimizer_jpg_to_png'] );
			$_POST['ewww_image_optimizer_png_to_jpg'] = ( empty( $_POST['ewww_image_optimizer_png_to_jpg'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_png_to_jpg', $_POST['ewww_image_optimizer_png_to_jpg'] );
			$_POST['ewww_image_optimizer_gif_to_png'] = ( empty( $_POST['ewww_image_optimizer_gif_to_png'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_gif_to_png', $_POST['ewww_image_optimizer_gif_to_png'] );
			$_POST['ewww_image_optimizer_webp'] = ( empty( $_POST['ewww_image_optimizer_webp'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_webp', $_POST['ewww_image_optimizer_webp'] );
			if (empty($_POST['ewww_image_optimizer_jpg_background'])) $_POST['ewww_image_optimizer_jpg_background'] = '';
			update_site_option( 'ewww_image_optimizer_jpg_background', ewww_image_optimizer_jpg_background( $_POST['ewww_image_optimizer_jpg_background'] ) );
			if (empty($_POST['ewww_image_optimizer_jpg_quality'])) $_POST['ewww_image_optimizer_jpg_quality'] = '';
			update_site_option( 'ewww_image_optimizer_jpg_quality', ewww_image_optimizer_jpg_quality( $_POST['ewww_image_optimizer_jpg_quality'] ) );
			$_POST['ewww_image_optimizer_disable_convert_links'] = ( empty( $_POST['ewww_image_optimizer_disable_convert_links'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_disable_convert_links', $_POST['ewww_image_optimizer_disable_convert_links'] );
			if ( empty( $_POST['ewww_image_optimizer_cloud_key'] ) ) $_POST['ewww_image_optimizer_cloud_key'] = '';
			update_site_option( 'ewww_image_optimizer_cloud_key', ewww_image_optimizer_cloud_key_sanitize( $_POST['ewww_image_optimizer_cloud_key'] ) );
			$_POST['ewww_image_optimizer_auto'] = ( empty( $_POST['ewww_image_optimizer_auto'] ) ? false : true );
			update_site_option('ewww_image_optimizer_auto', $_POST['ewww_image_optimizer_auto']);
			if ( empty( $_POST['ewww_image_optimizer_aux_paths'] ) ) $_POST['ewww_image_optimizer_aux_paths'] = '';
			update_site_option( 'ewww_image_optimizer_aux_paths', ewww_image_optimizer_aux_paths_sanitize( $_POST['ewww_image_optimizer_aux_paths'] ) );
			if ( empty( $_POST['ewww_image_optimizer_exclude_paths'] ) ) $_POST['ewww_image_optimizer_exclude_paths'] = '';
			update_site_option( 'ewww_image_optimizer_exclude_paths', ewww_image_optimizer_exclude_paths_sanitize( $_POST['ewww_image_optimizer_exclude_paths'] ) );
			$_POST['ewww_image_optimizer_enable_cloudinary'] = ( empty( $_POST['ewww_image_optimizer_enable_cloudinary'] ) ? false : true );
			update_site_option('ewww_image_optimizer_enable_cloudinary', $_POST['ewww_image_optimizer_enable_cloudinary']);
			if ( empty( $_POST['ewww_image_optimizer_delay'] ) ) $_POST['ewww_image_optimizer_delay'] = '';
			update_site_option( 'ewww_image_optimizer_delay', (int) $_POST['ewww_image_optimizer_delay'] );
			if ( empty( $_POST['ewww_image_optimizer_maxmediawidth'] ) ) $_POST['ewww_image_optimizer_maxmediawidth'] = 0;
			update_site_option( 'ewww_image_optimizer_maxmediawidth', (int) $_POST['ewww_image_optimizer_maxmediawidth'] );
			if ( empty( $_POST['ewww_image_optimizer_maxmediaheight'] ) ) $_POST['ewww_image_optimizer_maxmediaheight'] = 0;
			update_site_option( 'ewww_image_optimizer_maxmediaheight', (int) $_POST['ewww_image_optimizer_maxmediaheight'] );
			if ( empty( $_POST['ewww_image_optimizer_maxotherwidth'] ) ) $_POST['ewww_image_optimizer_maxotherwidth'] = 0;
			update_site_option( 'ewww_image_optimizer_maxotherwidth', (int) $_POST['ewww_image_optimizer_maxotherwidth'] );
			if ( empty( $_POST['ewww_image_optimizer_maxotherheight'] ) ) $_POST['ewww_image_optimizer_maxotherheight'] = 0;
			update_site_option( 'ewww_image_optimizer_maxotherheight', (int) $_POST['ewww_image_optimizer_maxotherheight'] );
			$_POST['ewww_image_optimizer_resize_detection'] = ( empty( $_POST['ewww_image_optimizer_resize_detection'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_detection', $_POST['ewww_image_optimizer_resize_detection'] );
			$_POST['ewww_image_optimizer_resize_existing'] = ( empty( $_POST['ewww_image_optimizer_resize_existing'] ) ? false : true );
			update_site_option( 'ewww_image_optimizer_resize_existing', $_POST['ewww_image_optimizer_resize_existing'] );
			if (empty($_POST['ewww_image_optimizer_disable_resizes'])) $_POST['ewww_image_optimizer_disable_resizes'] = array();
			update_site_option('ewww_image_optimizer_disable_resizes', $_POST['ewww_image_optimizer_disable_resizes']);
			if (empty($_POST['ewww_image_optimizer_disable_resizes_opt'])) $_POST['ewww_image_optimizer_disable_resizes_opt'] = array();
			update_site_option('ewww_image_optimizer_disable_resizes_opt', $_POST['ewww_image_optimizer_disable_resizes_opt']);
			if (empty($_POST['ewww_image_optimizer_skip_size'])) $_POST['ewww_image_optimizer_skip_size'] = '';
			update_site_option('ewww_image_optimizer_skip_size', (int) $_POST['ewww_image_optimizer_skip_size'] );
			if (empty($_POST['ewww_image_optimizer_skip_png_size'])) $_POST['ewww_image_optimizer_skip_png_size'] = '';
			update_site_option('ewww_image_optimizer_skip_png_size', (int) $_POST['ewww_image_optimizer_skip_png_size'] );
			$_POST['ewww_image_optimizer_parallel_optimization'] = ( empty( $_POST['ewww_image_optimizer_parallel_optimization'] ) ? false : true );
			update_site_option('ewww_image_optimizer_parallel_optimization', $_POST['ewww_image_optimizer_parallel_optimization']);
//			$_POST['ewww_image_optimizer_defer'] = ( empty( $_POST['ewww_image_optimizer_defer'] ) ? false : true );
//			update_site_option('ewww_image_optimizer_defer', $_POST['ewww_image_optimizer_defer']);
			$_POST['ewww_image_optimizer_include_media_paths'] = ( empty( $_POST['ewww_image_optimizer_include_media_paths'] ) ? false : true );
			update_site_option('ewww_image_optimizer_include_media_paths', $_POST['ewww_image_optimizer_include_media_paths']);
			$_POST['ewww_image_optimizer_webp_for_cdn'] = ( empty( $_POST['ewww_image_optimizer_webp_for_cdn'] ) ? false : true );
			update_site_option('ewww_image_optimizer_webp_for_cdn', $_POST['ewww_image_optimizer_webp_for_cdn']);
			$_POST['ewww_image_optimizer_webp_force'] = ( empty( $_POST['ewww_image_optimizer_webp_force'] ) ? false : true );
			update_site_option('ewww_image_optimizer_webp_force', $_POST['ewww_image_optimizer_webp_force']);
			$_POST['ewww_image_optimizer_webp_paths'] = ( empty( $_POST['ewww_image_optimizer_webp_paths'] ) ? '' : $_POST['ewww_image_optimizer_webp_paths'] );
			update_site_option( 'ewww_image_optimizer_webp_paths', ewww_image_optimizer_webp_paths_sanitize( $_POST['ewww_image_optimizer_webp_paths'] ) );
			$_POST['ewww_image_optimizer_backup_files'] = ( empty( $_POST['ewww_image_optimizer_backup_files'] ) ? false : true );
			update_site_option('ewww_image_optimizer_backup_files', $_POST['ewww_image_optimizer_backup_files']);
			add_action('network_admin_notices', 'ewww_image_optimizer_network_settings_saved');
		}
	}
	// register all the common EWWW IO settings
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_debug', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpegtran_copy', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_pdf_level', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_lossy_skip_full', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_metadata_skip_full', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_delete_originals', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_to_png', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_png_to_jpg', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_gif_to_png', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_background', 'ewww_image_optimizer_jpg_background' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_jpg_quality', 'ewww_image_optimizer_jpg_quality' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_convert_links', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_cloud_key', 'ewww_image_optimizer_cloud_key_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_auto', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_aux_paths', 'ewww_image_optimizer_aux_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_exclude_paths', 'ewww_image_optimizer_exclude_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_enable_cloudinary', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_delay', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediawidth', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxmediaheight', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxotherwidth', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_maxotherheight', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_detection', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_resize_existing', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes', 'ewww_image_optimizer_disable_resizes_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_disable_resizes_opt', 'ewww_image_optimizer_disable_resizes_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_skip_size', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_skip_png_size', 'intval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_parallel_optimization', 'boolval' );
//	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_defer', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_include_media_paths', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_for_cdn', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_force', 'boolval' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_webp_paths', 'ewww_image_optimizer_webp_paths_sanitize' );
	register_setting( 'ewww_image_optimizer_options', 'ewww_image_optimizer_backup_files', 'boolval' );
	ewww_image_optimizer_exec_init();
	ewww_image_optimizer_cron_setup( 'ewww_image_optimizer_auto' );
//	ewww_image_optimizer_cron_setup( 'ewww_image_optimizer_defer' );
	// require the files that do the bulk processing 
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php' );
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'mwebp.php' );
	// queue the function that contains custom styling for our progressbars
	add_action('admin_enqueue_scripts', 'ewww_image_optimizer_progressbar_style');
	// alert user if multiple re-optimizations detected
	add_action( 'network_admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
	add_action( 'admin_notices', 'ewww_image_optimizer_notice_reoptimization' );
	ewwwio_memory( __FUNCTION__ );
//	ewww_image_optimizer_debug_log();
}

// setup wp_cron tasks for scheduled and deferred optimization
function ewww_image_optimizer_cron_setup( $event ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// setup scheduled optimization if the user has enabled it, and it isn't already scheduled
	if ( ewww_image_optimizer_get_option( $event ) == TRUE && ! wp_next_scheduled( $event ) ) {
		ewwwio_debug_message( "scheduling $event" );
		wp_schedule_event( time(), apply_filters( 'ewww_image_optimizer_schedule', 'hourly', $event ), $event );
	} elseif ( ewww_image_optimizer_get_option( $event ) == TRUE ) {
		ewwwio_debug_message( "$event already scheduled: " . wp_next_scheduled( $event ) );
	} elseif ( wp_next_scheduled( $event ) ) {
		ewwwio_debug_message( "un-scheduling $event" );
		wp_clear_scheduled_hook( $event );
		if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
			// need to include the plugin library for the is_plugin_active function
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
			global $wpdb;
			$query = $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid );
			$blogs = $wpdb->get_results( $query, ARRAY_A );
			if ( ewww_image_optimizer_iterable( $blogs ) ) {
				foreach ( $blogs as $blog ) {
					switch_to_blog( $blog['blog_id'] );
					wp_clear_scheduled_hook( $event );
				}
				restore_current_blog();
			}
		}
	}
}

// sets all the tool constants to false
function ewww_image_optimizer_disable_tools() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_JPEGTRAN', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_OPTIPNG' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_OPTIPNG', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGOUT' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_PNGOUT', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_GIFSICLE', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_PNGQUANT' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_PNGQUANT', false);
	}
	if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_WEBP' ) ) {
		define('EWWW_IMAGE_OPTIMIZER_WEBP', false);
	}
	ewwwio_memory( __FUNCTION__ );
}

// generates css include for progressbars to match admin style
function ewww_image_optimizer_progressbar_style() {
	wp_add_inline_style( 'jquery-ui-progressbar', ".ui-widget-header { background-color: " . ewww_image_optimizer_admin_background() . "; }" );
	ewwwio_memory( __FUNCTION__ );
}

// determines the background color to use based on the selected theme
function ewww_image_optimizer_admin_background() {
	if ( function_exists( 'wp_add_inline_style' ) ) {
		$user_info = wp_get_current_user();
		switch( $user_info->admin_color ) {
			case 'midnight':
				return "#e14d43";
			case 'blue':
				return "#096484";
			case 'light':
				return "#04a4cc";
			case 'ectoplasm':
				return "#a3b745";
			case 'coffee':
				return "#c7a589";
			case 'ocean':
				return "#9ebaa0";
			case 'sunrise':
				return "#dd823b";
			default:
				return "#0073aa";
		}
	}
	ewwwio_memory( __FUNCTION__ );
}

// tells WP to ignore the 'large network' detection by filtering the results of wp_is_large_network()
function ewww_image_optimizer_large_network() {
	return false;
}

// adds table to db for storing status of auxiliary images that have been optimized
function ewww_image_optimizer_install_table() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	$wpdb->ewwwio_images = $wpdb->prefix . "ewwwio_images";

	// get the current wpdb charset and collation
	$db_collation = $wpdb->get_charset_collate();

	//see if the path column exists, and what collation it uses to determine the column index size
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->ewwwio_images'" ) == $wpdb->ewwwio_images ) {
		ewwwio_debug_message( 'upgrading table and checking collation for path, table exists' );
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET updated = '1971-01-01 00:00:00' WHERE updated < '1001-01-01 00:00:01'" );
		$column_collate = $wpdb->get_col_charset( $wpdb->ewwwio_images, 'path' );
		if ( ! empty( $column_collate ) && $column_collate !== 'utf8mb4' ) {
			$path_index_size = 255;
			ewwwio_debug_message( "current column collation: $column_collate" );
			if ( strpos( $column_collate, 'utf8' ) === false ) {
				ewwwio_debug_message( 'converting path column to utf8' );
				$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CHANGE path path BLOB" );
		                if ( $wpdb->has_cap( 'utf8mb4_520' && strpos( $db_collation, 'utf8mb4' ) ) ) {
					ewwwio_debug_message( 'using mb4 version 5.20' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} elseif ( $wpdb->has_cap( 'utf8mb4' ) && strpos( $db_collation, 'utf8mb4' ) ) {
					ewwwio_debug_message( 'using mb4 version 4' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} else {
					ewwwio_debug_message( 'using plain old utf8' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8, CHANGE path path TEXT" );
				}

			} elseif ( strpos( $column_collate, 'utf8mb4' ) === false && strpos( $db_collation, 'utf8mb4' ) ) {
		                if ( $wpdb->has_cap( 'utf8mb4_520' ) ) {
					ewwwio_debug_message( 'using mb4 version 5.20' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				} elseif ( $wpdb->has_cap( 'utf8mb4' ) ) {
					ewwwio_debug_message( 'using mb4 version 4' );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images DROP INDEX path_image_size" );
					$wpdb->query( "ALTER TABLE $wpdb->ewwwio_images CONVERT TO CHARACTER SET utf8mb4, CHANGE path path TEXT" );
					unset( $path_index_size );
				}
			}
		}
	}

	// if the path column doesn't yet exist, and the default collation is utf8mb4, then we need to lower the column index size
	if ( empty( $path_index_size ) && strpos( $db_collation, 'utf8mb4' ) ) {
		$path_index_size = 191;
	} else {
		$path_index_size = 255;
	}
	ewwwio_debug_message( "path index size: $path_index_size" );

	// create a table with 4 columns: an id, the file path, the md5sum, and the optimization results
	$sql = "CREATE TABLE $wpdb->ewwwio_images (
		id int(14) unsigned NOT NULL AUTO_INCREMENT,
		attachment_id bigint(20) unsigned,
		gallery varchar(10),
		resize varchar(75),
		path text NOT NULL,
		converted text NOT NULL,
		results varchar(75) NOT NULL,
		image_size int(10) unsigned,
		orig_size int(10) unsigned,
		backup varchar(100),
		level int(5) unsigned,
		pending tinyint(1) NOT NULL DEFAULT 0,
		updates int(5) unsigned,
		updated timestamp DEFAULT '1971-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
		trace blob,
		UNIQUE KEY id (id),
		KEY path_image_size (path($path_index_size),image_size),
		KEY attachment_info (gallery(3),attachment_id)
	) $db_collation;";
	
	// include the upgrade library to initialize a table
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	$updates = dbDelta( $sql );
	
	// make sure some of our options are not autoloaded (since they can be huge)
	$bulk_attachments = get_option( 'ewww_image_optimizer_bulk_attachments', '' );
	delete_option( 'ewww_image_optimizer_bulk_attachments' );
	add_option( 'ewww_image_optimizer_bulk_attachments', $bulk_attachments, '', 'no' );
	$bulk_attachments = get_option( 'ewww_image_optimizer_flag_attachments', '' );
	delete_option( 'ewww_image_optimizer_flag_attachments' );
	add_option( 'ewww_image_optimizer_flag_attachments', $bulk_attachments, '', 'no' );
	$bulk_attachments = get_option( 'ewww_image_optimizer_ngg_attachments', '' );
	delete_option( 'ewww_image_optimizer_ngg_attachments' );
	add_option( 'ewww_image_optimizer_ngg_attachments', $bulk_attachments, '', 'no' );
	//$bulk_attachments = get_option( 'ewww_image_optimizer_aux_attachments', '' );
	delete_option( 'ewww_image_optimizer_aux_attachments' );
	//add_option( 'ewww_image_optimizer_aux_attachments', $bulk_attachments, '', 'no' );
	$bulk_attachments = get_option( 'ewww_image_optimizer_defer_attachments', '' );
	delete_option( 'ewww_image_optimizer_defer_attachments' );
	add_option( 'ewww_image_optimizer_defer_attachments', $bulk_attachments, '', 'no' );
}

function ewww_image_optimizer_migrate_settings_to_levels() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_jpegtran' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 0 );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_jpegtran' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 20 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 30 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_jpg' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_level', 40 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_optipng' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 0 );
	}
	if ( ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) || ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_optipng' ) ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png_compress' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 20 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png_compress' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 30 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 40 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_png' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_lossy' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_fast' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 50 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_gifsicle' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 0 );
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_gifsicle' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_gif_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_pdf' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 10 );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_pdf' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_lossy' ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_pdf_level', 20 );
	}
}

function ewww_image_optimizer_remove_obsolete_settings() {
	delete_option( 'ewww_image_optimizer_disable_jpegtran' );
	delete_option( 'ewww_image_optimizer_cloud_jpg' );
	delete_option( 'ewww_image_optimizer_jpg_lossy' );
	delete_option( 'ewww_image_optimizer_lossy_fast' );
	delete_option( 'ewww_image_optimizer_cloud_png' );
	delete_option( 'ewww_image_optimizer_png_lossy' );
	delete_option( 'ewww_image_optimizer_cloud_png_compress' );
	delete_option( 'ewww_image_optimizer_disable_gifsicle' );
	delete_option( 'ewww_image_optimizer_cloud_gif' );
	delete_option( 'ewww_image_optimizer_cloud_pdf' );
	delete_option( 'ewww_image_optimizer_pdf_lossy' );
	delete_option( 'ewww_image_optimizer_skip_check' );
	delete_option( 'ewww_image_optimizer_disable_optipng' );
	delete_option( 'ewww_image_optimizer_interval' );
	delete_option( 'ewww_image_optimizer_jpegtran_path' );
	delete_option( 'ewww_image_optimizer_optipng_path' );
	delete_option( 'ewww_image_optimizer_gifsicle_path' );
	delete_option( 'ewww_image_optimizer_import_status' );
	delete_option( 'ewww_image_optimizer_bulk_image_count' );
	delete_option( 'ewww_image_optimizer_maxwidth' );
	delete_option( 'ewww_image_optimizer_maxheight' );
}

// lets the user know their network settings have been saved
function ewww_image_optimizer_network_settings_saved() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div id='ewww-image-optimizer-settings-saved' class='updated fade'><p><strong>" . esc_html__('Settings saved', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</strong></p></div>";
}

// alert the user when 10 or more re-optimizations detected, and do a reset if they say so back to 1 across the board
function ewww_image_optimizer_notice_reoptimization() {
	// do a reset
	if ( ! empty( $_GET['ewww_reset_reopt_nonce'] ) && wp_verify_nonce( $_GET['ewww_reset_reopt_nonce'], 'reset_reoptimization_counters' ) ) {
		global $wpdb;
		$debug_images = $wpdb->query( "UPDATE $wpdb->ewwwio_images SET updates=1 WHERE updates > 1" );
		delete_transient( 'ewww_image_optimizer_images_reoptimized' );
	} else {
		$reoptimized = get_transient( 'ewww_image_optimizer_images_reoptimized' );
		if ( empty( $reoptimized ) ) {
			global $wpdb;
			$reoptimized = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE updates > 10 AND path NOT LIKE '%wp-content/themes%' AND path NOT LIKE '%wp-content/plugins%'  LIMIT 10" );
			if ( empty( $reoptimized ) ) {
				set_transient( 'ewww_image_optimizer_images_reoptimized', 'zero', HOUR_IN_SECONDS );
			} else {
				set_transient( 'ewww_image_optimizer_images_reoptimized', $reoptimized, HOUR_IN_SECONDS );
			}
		} elseif ( $reoptimized == 'zero' ) {
			$reoptimized = 0;
		}
		// do a check for 10+ optimizations on 5+ images
		if ( ! empty( $reoptimized ) && $reoptimized > 5 ) {
			$debugging_page = admin_url( 'upload.php?page=ewww-image-optimizer-dynamic-debug' );
			$reset_page = wp_nonce_url( $_SERVER['REQUEST_URI'], 'reset_reoptimization_counters', 'ewww_reset_reopt_nonce' );
			// display an alert
			echo "<div id='ewww-image-optimizer-warning-reoptimizations' class='error'><p>" . sprintf( esc_html__( 'The EWWW Image Optimizer has detected excessive re-optimization of multiple images. Please turn on the Debugging setting, wait for approximately 12 hours, and then visit the %s page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), "<a href='$debugging_page'>" . esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>' ) . " <a href='$reset_page'>" . esc_html__( 'Reset Counters' ) . '</a></p></div>';
		}
	}
}

// load the class to extend WP_Image_Editor
function ewww_image_optimizer_load_editor( $editors ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! class_exists( 'EWWWIO_GD_Editor' ) && ! class_exists( 'EWWWIO_Imagick_Editor' ) )
		include( plugin_dir_path( __FILE__ ) . '/image-editor.php' );
	if ( ! in_array( 'EWWWIO_GD_Editor', $editors ) )
		array_unshift( $editors, 'EWWWIO_GD_Editor' );
	if ( ! in_array( 'EWWWIO_Imagick_Editor', $editors ) )
		array_unshift( $editors, 'EWWWIO_Imagick_Editor' );
	if ( ! in_array( 'EWWWIO_Gmagick_Editor', $editors ) && class_exists( 'WP_Image_Editor_Gmagick' ) )
		array_unshift( $editors, 'EWWWIO_Gmagick_Editor' );
	ewwwio_debug_message( "loading image editors: " . print_r( $editors, true ) );
	ewwwio_memory( __FUNCTION__ );
	return $editors;
}

// register the filter that will remove the image_editor hooks when an attachment is added
function ewww_image_optimizer_add_attachment() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_filter( 'intermediate_image_sizes_advanced', 'ewww_image_optimizer_image_sizes', 200 );
	add_filter( 'fallback_intermediate_image_sizes', 'ewww_image_optimizer_image_sizes', 200 );
}

// remove the image editor filter, and add a new filter that will restore it later
function ewww_image_optimizer_image_sizes( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	add_filter( 'wp_generate_attachment_metadata', 'ewww_image_optimizer_restore_editor_hooks', 1 );
	return $sizes;
}

// restore the image editor filter after the resizes have completed
function ewww_image_optimizer_restore_editor_hooks( $metadata ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	return $metadata;
}

// when an image has been edited, remove the image editor filter, and add a new filter that will restore it later
function ewww_image_optimizer_editor_save_pre( $image ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_noauto' ) ) {
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_restore_editor_hooks', 1 );
		add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
	}
	add_filter( 'intermediate_image_sizes', 'ewww_image_optimizer_image_sizes_advanced' );
	return $image;
}

// check for PTE confirm, separate from crop&save, and register the update attachment meta filter to process any modified resizes
function ewww_image_optimizer_pte_check( $data ) {
	if ( ! empty( $_GET['pte-action'] ) ) {
		if ( $_GET['pte-action'] == 'confirm-images' ) {
			add_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_resize_from_meta_data', 15, 2 );
		}
	}
	return $data;
}

// filter the image sizes generated by Wordpress, themes, and plugins allowing users to disable specific sizes
function ewww_image_optimizer_image_sizes_advanced( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes' );
	$flipped = false;
	if ( ! empty( $disabled_sizes ) ) {
		//ewwwio_debug_message( print_r( $sizes, true ) );
		if ( ! empty( $sizes[0] ) ) {
			$sizes = array_flip( $sizes );
			$flipped = true;
		}
		ewwwio_debug_message( print_r( $sizes, true ) );
		if ( ewww_image_optimizer_iterable( $disabled_sizes ) ) {
			foreach ( $disabled_sizes as $size => $disabled ) {
				if ( ! empty( $disabled ) ) {
					ewwwio_debug_message( "size disabled: $size" );
					unset( $sizes[$size] );
				}
			}
		}
		if ( $flipped ) {
			$sizes = array_flip( $sizes );
		}
		//ewwwio_debug_message( print_r( $sizes, true ) );
	}
	return $sizes;
}

// filter the image previews generated for pdf files and other non-image types
function ewww_image_optimizer_fallback_sizes( $sizes ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes' );
	$flipped = false;
	if ( ! empty( $disabled_sizes ) ) {
/*		if ( ! empty( $sizes[0] ) ) {
			$sizes = array_flip( $sizes );
			$flipped = true;
		}*/
		ewwwio_debug_message( print_r( $sizes, true ) );
		if ( ewww_image_optimizer_iterable( $sizes ) && ewww_image_optimizer_iterable( $disabled_sizes ) ) {
			if ( ! empty( $disabled_sizes['pdf-full'] ) ) {
				return array();
			}
			foreach ( $sizes as $i => $size ) {
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
					ewwwio_debug_message( "size disabled: $size" );
					unset( $sizes[ $i ] );
				}
			}
/*			foreach ( $disabled_sizes as $size => $disabled ) {
				if ( ! empty( $disabled ) ) {
					ewwwio_debug_message( "size disabled: $size" );
					unset( $sizes[$size] );
				}*/
		}
		//if ( $flipped ) {
		//	$sizes = array_flip( $sizes );
		//}
	}
	return $sizes;
}

// during an upload, handle resizing, and set the 'new_image' global
function ewww_image_optimizer_handle_upload( $params ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_new_image;
	$ewww_new_image = true;
	// NOTE: if you use the ewww_image_optimizer_defer_resizing filter to defer the resize operation, only the "other" dimensions will apply
	// resize here unless the user chose to defer resizing or imsanity is enabled with a max size
	if ( ! apply_filters( 'ewww_image_optimizer_defer_resizing', false ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		$file_path = $params['file'];
		if ( ( ! is_wp_error( $params ) ) && is_file( $file_path ) && in_array( $params['type'], array( 'image/png', 'image/gif', 'image/jpeg' ) ) ) {
			ewww_image_optimizer_resize_upload( $file_path );
		}
	}
	return $params;
}

// this is the delayed wrapper for the W3TC CDN function that runs after optimization (priority 20)
/*function ewww_image_optimizer_update_attached_file_w3tc( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	$w3_plugin_cdn = w3_instance( 'W3_Plugin_Cdn' );
	$w3_plugin_cdn->update_attached_file( $file_path, $id );
	return $meta;
}*/

function ewww_image_optimizer_w3tc_update_files( $files ) {
	global $ewww_attachment;
	list( $file, $upload_path ) = ewww_image_optimizer_attachment_path( $ewww_attachment['meta'], $ewww_attachment['id'] );
	$file_info = array();
	if ( function_exists( 'w3_upload_info' ) ) {
		$upload_info = w3_upload_info();
	} else {
		$upload_info = ewww_image_optimizer_upload_info();
	}
	if ( $upload_info ) {
		$remote_file = ltrim( $upload_info['baseurlpath'] . $ewww_attachment['meta']['file'], '/' );
		$home_url = get_site_url();
		$original_url = $home_url . $file;
		$file_info[] = array( 'local_path' => $file,
			'remote_path' => $remote_file,
			'original_url' => $original_url );
		$files = array_merge( $files, $file_info );
	}
	return $files;
}

function ewww_image_optimizer_upload_info() {
	$upload_info = @wp_upload_dir( null, false );

	if ( empty( $upload_info['error'] ) ) {
		$parse_url = @parse_url( $upload_info['baseurl'] );

		if ( $parse_url ) {
			$baseurlpath = ( ! empty( $parse_url['path'] ) ? trim( $parse_url['path'], '/' ) : '' );
		} else {
			$baseurlpath = 'wp-content/uploads';
		}
		$upload_info['baseurlpath'] = '/' . $baseurlpath . '/';
	} else {
		$upload_info = false;
	}
	return $upload_info;
}

// runs scheduled optimization of various auxiliary images
function ewww_image_optimizer_auto() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( get_transient( 'ewww_image_optimizer_no_scheduled_optimization' ) ) {
		ewwwio_debug_message( 'detected bulk operation in progress, bailing' );
		ewww_image_optimizer_debug_log();
		return;
	}
	global $ewww_defer;
	$ewww_defer = false;
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'bulk.php' );
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'aux-optimize.php' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_auto' ) == TRUE ) {
		ewwwio_debug_message( 'running scheduled optimization' );
		ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
		// generate our own nonce value, wp_create_nonce() will return the same value for 12-24 hours
		$nonce = wp_hash( time() . '|' . 'ewww-image-optimizer-auto' );
		update_option( 'ewww_image_optimizer_aux_resume', $nonce );
		$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );		
		$count = ewww_image_optimizer_aux_images_table_count_pending();
		//$attachments = get_option( 'ewww_image_optimizer_aux_attachments' );
		if ( ! empty( $count ) ) {
			global $wpdb;
			if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
				ewww_image_optimizer_db_init();
				global $ewwwdb;
			} else {
				$ewwwdb = $wpdb;
			}
			$i = 0;
			while ( $i < $count && $attachment = $ewwwdb->get_row( "SELECT id,path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_A ) ) {
//			foreach ( $attachments as $attachment ) {
				// if the nonce has changed since we started, bail out, since that means another aux scan/optimize is running
				// we do a query using $wpdb, because get_option() is cached
				$current_nonce = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'ewww_image_optimizer_aux_resume'" );
				if ( $nonce !== $current_nonce ) {
					ewwwio_debug_message( 'detected another optimization, nonce changed, bailing' );
					ewww_image_optimizer_debug_log();
					return;
				} else {
					ewwwio_debug_message( "$nonce is fine, compared to $current_nonce" );
				}
				ewww_image_optimizer_aux_images_loop( $attachment, true );
				if ( ! empty( $delay ) ) {
					sleep( $delay );
				}
				ewww_image_optimizer_debug_log();
				$i++;
			}
		}
		ewww_image_optimizer_aux_images_cleanup( true );
	}
	ewwwio_memory( __FUNCTION__ );
	return;
}

// optimizes the images that have been deferred for later processing
function ewww_image_optimizer_defer() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	$ewww_defer = false;
	$deferred_attachments = get_option( 'ewww_image_optimizer_defer_attachments' );
	if ( empty( $deferred_attachments ) ) {
		return;
	}
	$start_time = time();
	$delay = ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
	if ( ewww_image_optimizer_iterable( $deferred_attachments ) ) {
		foreach ( $deferred_attachments as $image ) {
			list( $type, $id ) = explode( ',', $image, 2 );
			switch ( $type ) {
				case 'media':
					ewwwio_debug_message( "processing deferred $type: $id" );
					$meta = wp_get_attachment_metadata( $id, true );
					// do the optimization for the current attachment (including resizes)
					$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, false );
					// update the metadata for the current attachment
					wp_update_attachment_metadata( $id, $meta );
					break;
				case 'nextgen2':
					ewwwio_debug_message( "processing deferred $type: $id" );
					// creating the 'registry' object for working with nextgen
					$registry = C_Component_Registry::get_instance();
					// creating a database storage object from the 'registry' object
					$storage  = $registry->get_utility( 'I_Gallery_Storage' );
					// get an image object
					$ngg_image = $storage->object->_image_mapper->find( $id );
					global $ewwwngg;
					$ewwwngg->ewww_added_new_image( $ngg_image, $storage );
					break;
				case 'nextcellent':
					ewwwio_debug_message( "processing deferred $type: $id" );
					global $ewwwngg;
					$ewwwngg->ewww_ngg_optimize( $id );
					break;
				case 'flag':
					ewwwio_debug_message( "processing deferred $type: $id" );
					$flag_image = flagdb::find_image( $id );
					ewwwflag::ewww_added_new_image ($flag_image);
					break;
				case 'file':
					ewwwio_debug_message( "processing deferred $type: $id" );
					ewww_image_optimizer( $id );
					break;
				default:
					ewwwio_debug_message( "unknown type in deferrred queue: $type, $id" );
			}
			ewww_image_optimizer_remove_deferred_attachment( $image );
			$elapsed_time = time() - $start_time;
			ewwwio_debug_message( "time elapsed during deferred opt: $elapsed_time" );
			$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
			if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
				ewwwio_debug_message( 'Deferred opt aborted, license exceeded' );
				die();
			}
			ewww_image_optimizer_debug_log();
			if ( ! empty( $delay ) ) {
				sleep( $delay );
			}
			// prevent running longer than an hour
			if ( $elapsed_time > 3600 ) {
				return;
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return;
}

// add an attachment/image to the queue
function ewww_image_optimizer_add_deferred_attachment( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "adding $id to the queue" );
	$deferred_attachments = get_option( 'ewww_image_optimizer_defer_attachments' );
	$deferred_attachments[] = $id;
	update_option( 'ewww_image_optimizer_defer_attachments', $deferred_attachments, false );
	ewwwio_memory( __FUNCTION__ );
}

// remove a processed attachment/image from the queue
function ewww_image_optimizer_remove_deferred_attachment( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "removing $id from the queue" );
	$deferred_attachments = get_option( 'ewww_image_optimizer_defer_attachments' );
	if ( ( $key = array_search( $id, $deferred_attachments ) ) !== false ) {
		unset( $deferred_attachments[$key] );
	}
	update_option( 'ewww_image_optimizer_defer_attachments', $deferred_attachments, false );
	ewwwio_memory( __FUNCTION__ );
}

// removes the network settings when the plugin is deactivated
function ewww_image_optimizer_network_deactivate( $network_wide ) {
	global $wpdb;
	wp_clear_scheduled_hook( 'ewww_image_optimizer_auto' );
	wp_clear_scheduled_hook( 'ewww_image_optimizer_defer' );
	if ( $network_wide ) {
		$query = $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid );
		$blogs = $wpdb->get_results( $query, ARRAY_A );
		if ( ewww_image_optimizer_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				wp_clear_scheduled_hook( 'ewww_image_optimizer_auto' );
				wp_clear_scheduled_hook( 'ewww_image_optimizer_defer' );
			}
			restore_current_blog();
		}
	}
}

function ewww_image_optimizer_uninstall() {
	insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', '' );
}

// adds a global settings page to the network admin settings menu
function ewww_image_optimizer_network_admin_menu() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// add options page to the settings menu
		$permissions = apply_filters( 'ewww_image_optimizer_superadmin_permissions', '' );
		$ewww_network_options_page = add_submenu_page(
			'settings.php',				//slug of parent
			'EWWW Image Optimizer',			//Title
			'EWWW Image Optimizer',			//Sub-menu title
			$permissions,				//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,	//File to open
			'ewww_image_optimizer_options'		//Function to call
		);
	} 
}

// adds the bulk optimize and settings page to the admin menu
function ewww_image_optimizer_admin_menu() {
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	// adds bulk optimize to the media library menu
	$ewww_bulk_page = add_media_page( esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-bulk', 'ewww_image_optimizer_bulk_preview' );
//	$ewww_unoptimized_page = add_media_page( esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-unoptimized', 'ewww_image_optimizer_display_unoptimized_media' );
	$ewww_webp_migrate_page = add_submenu_page( null, esc_html__( 'Migrate WebP Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Migrate WebP Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-webp-migrate', 'ewww_image_optimizer_webp_migrate_preview' );
	if ( ! function_exists( 'is_plugin_active' ) ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( ! is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		// add options page to the settings menu
		$ewww_options_page = add_options_page(
			'EWWW Image Optimizer',		//Title
			'EWWW Image Optimizer',		//Sub-menu title
			apply_filters( 'ewww_image_optimizer_admin_permissions', '' ),		//Security
			EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE,			//File to open
			'ewww_image_optimizer_options'	//Function to call
		);
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		add_media_page( esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-dynamic-debug', 'ewww_image_optimizer_dynamic_image_debug' );
		add_media_page( esc_html__( 'Image Queue Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Image Queue Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $permissions, 'ewww-image-optimizer-queue-debug', 'ewww_image_optimizer_image_queue_debug' );
	}
	if ( is_plugin_active( 'image-store/ImStore.php' ) || is_plugin_active_for_network( 'image-store/ImStore.php' ) ) {
		$ims_menu ='edit.php?post_type=ims_gallery';
		$ewww_ims_page = add_submenu_page( $ims_menu, esc_html__( 'Image Store Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html__( 'Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'ims_change_settings', 'ewww-ims-optimize', 'ewww_image_optimizer_ims');
//		add_action( 'admin_footer-' . $ewww_ims_page, 'ewww_image_optimizer_debug' );
	}
}

// check WP Retina images, fixes filenames in the database, and makes sure all derivatives are optimized
function ewww_image_optimizer_retina( $id, $retina_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$file_info = pathinfo( $retina_path );
	$extension = '.' . $file_info['extension'];
	preg_match ( '/-(\d+x\d+)@2x$/', $file_info['filename'], $fileresize );
	$dimensions = explode ( 'x', $fileresize[1]);
	$no_ext_path = $file_info['dirname'] . '/' . preg_replace( '/\d+x\d+@2x$/', '', $file_info['filename'] ) . $dimensions[0] * 2 . 'x' . $dimensions[1] * 2 . '-tmp';
	$temp_path = $no_ext_path . $extension;
	ewwwio_debug_message( "temp path: $temp_path" );
	// check for any orphaned webp retina images also, and fix their paths
	ewwwio_debug_message( "retina path: $retina_path" );
	$webp_path = $temp_path . '.webp';
	ewwwio_debug_message( "retina webp path: $webp_path" );
	if ( file_exists( $webp_path ) ) {
		rename( $webp_path, $retina_path . '.webp' );
	}
	$opt_size = ewww_image_optimizer_filesize( $retina_path );
	ewwwio_debug_message( "retina size: $opt_size" );
	$optimized_query = ewww_image_optimizer_find_already_optimized( $temp_path );
	if ( is_array( $optimized_query ) && $optimized_query['image_size'] == $opt_size ) {
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		// store info on the current image for future reference
		$ewwwdb->update( $ewwwdb->ewwwio_images,
			array(
				'path' => $retina_path,
				'attachment_id' => $id,
				'gallery' => 'media',
			),
			array(
				'id' => $optimized_query['id'],
			));
	} else {
		if ( ewww_image_optimizer_test_parallel_opt( '', $id ) ) {
			if ( ! empty( $_REQUEST['ewww_force'] ) ) {
				$force = true;
			} else {
				$force = false;
			}
			session_write_close();
			add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
			global $ewwwio_async_optimize_media;
			if ( ! class_exists( 'WP_Background_Process' ) ) {
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
			}
			if ( ! is_object( $ewwwio_async_optimize_media ) ) {
				$ewwwio_async_optimize_media = new EWWWIO_Async_Request();
			}
			$async_path = str_replace( ABSPATH, '', $retina_path );
			$ewwwio_async_optimize_media->data( array( 'ewwwio_id' => $id , 'ewwwio_path' => $async_path, 'ewww_force' => $force ) )->dispatch();
		} else {
			global $ewww_image;
			$ewww_image = new EWWW_Image( $id, 'media', $retina_path );
			ewww_image_optimizer( $retina_path );
		}
	}
	ewwwio_memory( __FUNCTION__ );
}

// list IMS images and optimization status
function ewww_image_optimizer_ims() {
	global $wpdb;
	$ims_columns = get_column_headers( 'ims_gallery' );
	echo "<div class='wrap'><h1>" . esc_html__('Image Store Optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</h1>";
	if ( empty( $_REQUEST['ewww_gid'] ) ) {
		$galleries = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'ims_gallery' ORDER BY ID" );
		if ( ewww_image_optimizer_iterable( $galleries ) ) {
			$gallery_string = implode( ',', $galleries );
			echo '<p>' . esc_html__('Choose a gallery or', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gallery_string'>" . esc_html__('optimize all galleries', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a></p>';
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>' . esc_html__('Gallery ID', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Gallery Name', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			foreach ( $galleries as $gid ) {
				$image_count = $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_type = 'ims_image' AND post_mime_type LIKE '%%image%%' AND post_parent = $gid" );
				$gallery_name = get_the_title( $gid );
				echo "<tr><td>$gid</td>";
				echo "<td><a href='edit.php?post_type=ims_gallery&page=ewww-ims-optimize&ewww_gid=$gid'>$gallery_name</a></td>";
				echo "<td>$image_count</td>";
				echo "<td><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gid'>" . esc_html__('Optimize Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></td></tr>";
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'No galleries found', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>';
		}
	} else {
		$gid = (int) $_REQUEST['ewww_gid'];
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'ims_image' AND post_mime_type LIKE '%%image%%' AND post_parent = $gid ORDER BY ID" );
		if ( ewww_image_optimizer_iterable( $attachments ) ) {
			echo "<p><a href='upload.php?page=ewww-image-optimizer-bulk&ids=$gid'>" . esc_html__('Optimize Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></p>";
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>ID</th><th>&nbsp;</th><th>' . esc_html__('Title', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Gallery', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			$alternate = true;
			foreach ( $attachments as $ID ) {
				$meta = get_metadata( 'post', $ID );
				if ( empty( $meta['_wp_attachment_metadata'] ) ) {
					continue;
				}
				$meta = maybe_unserialize( $meta['_wp_attachment_metadata'][0] );
				$image_name = get_the_title( $ID );
				$gallery_name = get_the_title( $gid );
				$image_url = esc_url( $meta['sizes']['mini']['url'] );
?>				<tr<?php if( $alternate ) echo " class='alternate'"; ?>><td><?php echo $ID; ?></td>
<?php				echo "<td style='width:80px' class='column-icon'><img src='$image_url' /></td>";
				echo "<td class='title'>$image_name</td>";
				echo "<td>$gallery_name</td><td>";
				ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $ID );
				echo "</td></tr>";
				$alternate = !$alternate;
			}
		echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'No images found', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>';
		}
	}
	echo '</div>';
	return;	
}

// optimize MyArcade screenshots and thumbs
function ewww_image_optimizer_myarcade_thumbnail( $url ) {
	ewwwio_debug_message( "thumb url passed: $url" );
	if ( ! empty( $url ) ) {
        	$thumb_path = str_replace( get_option('siteurl') . '/', ABSPATH, $url );
		ewwwio_debug_message( "myarcade thumb path generated: $thumb_path" );
		ewww_image_optimizer( $thumb_path );
	}
	return $url;
}

//load full webp script for debugging
function ewww_image_optimizer_webp_debug_script() {
	if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
		wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	}
}

// enqueue script dependency for alt webp rewriting
function ewww_image_optimizer_webp_load_jquery() {
	if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
		wp_enqueue_script('jquery');
	}
}

// load minified inline version of webp script from jscompress.com
function ewww_image_optimizer_webp_inline_script() {
	if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
?>
<script>
function check_webp_feature(a,b){var c={alpha:"UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",animation:"UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"},d=!1,e=new Image;e.onload=function(){var a=e.width>0&&e.height>0;d=!0,b(a)},e.onerror=function(){d=!1,b(!1)},e.src="data:image/webp;base64,"+c[a]}function ewww_load_images(a){jQuery(document).arrive(".ewww_webp",function(){ewww_load_images(a)}),function(b){function d(a,d){for(var e=["align","alt","border","crossorigin","height","hspace","ismap","longdesc","usemap","vspace","width","accesskey","class","contenteditable","contextmenu","dir","draggable","dropzone","hidden","id","lang","spellcheck","style","tabindex","title","translate","sizes"],f=0,g=e.length;f<g;f++){var h=b(a).attr(c+e[f]);"undefined"!=typeof h&&h!==!1&&b(d).attr(e[f],h)}return d}var c="data-";a&&(b(".batch-image img, .image-wrapper a, .ngg-pro-masonry-item a").each(function(){var a=b(this).attr("data-webp");"undefined"!=typeof a&&a!==!1&&b(this).attr("data-src",a);var a=b(this).attr("data-webp-thumbnail");"undefined"!=typeof a&&a!==!1&&b(this).attr("data-thumbnail",a)}),b(".image-wrapper a, .ngg-pro-masonry-item a").each(function(){var a=b(this).attr("data-webp");"undefined"!=typeof a&&a!==!1&&b(this).attr("href",a)}),b(".rev_slider ul li").each(function(){var a=b(this).attr("data-webp-thumb");"undefined"!=typeof a&&a!==!1&&b(this).attr("data-thumb",a);for(var c=1;c<11;){var a=b(this).attr("data-webp-param"+c);"undefined"!=typeof a&&a!==!1&&b(this).attr("data-param"+c,a),c++}}),b(".rev_slider img").each(function(){var a=b(this).attr("data-webp-lazyload");"undefined"!=typeof a&&a!==!1&&b(this).attr("data-lazyload",a)})),b("img.ewww_webp_lazy_retina").each(function(){if(a){var c=b(this).attr("data-srcset-webp");"undefined"!=typeof c&&c!==!1&&b(this).attr("data-srcset",c)}else{var c=b(this).attr("data-srcset-img");"undefined"!=typeof c&&c!==!1&&b(this).attr("data-srcset",c)}b(this).removeClass("ewww_webp_lazy_retina")}),b("img.ewww_webp_lazy_load").each(function(){if(a){b(this).attr("data-lazy-src",b(this).attr("data-lazy-webp-src"));var c=b(this).attr("data-srcset-webp");"undefined"!=typeof c&&c!==!1&&b(this).attr("srcset",c);var c=b(this).attr("data-lazy-srcset-webp");"undefined"!=typeof c&&c!==!1&&b(this).attr("data-lazy-srcset",c)}else{b(this).attr("data-lazy-src",b(this).attr("data-lazy-img-src"));var c=b(this).attr("data-srcset");"undefined"!=typeof c&&c!==!1&&b(this).attr("srcset",c);var c=b(this).attr("data-lazy-srcset-img");"undefined"!=typeof c&&c!==!1&&b(ewww_img).attr("data-lazy-srcset",c)}b(this).removeClass("ewww_webp_lazy_load")}),b(".ewww_webp_lazy_hueman").each(function(){var c=document.createElement("img");if(b(c).attr("src",b(this).attr("data-src")),a){b(c).attr("data-src",b(this).attr("data-webp-src"));var e=b(this).attr("data-srcset-webp");"undefined"!=typeof e&&e!==!1&&b(c).attr("data-srcset",e)}else{b(c).attr("data-src",b(this).attr("data-img"));var e=b(this).attr("data-srcset-img");"undefined"!=typeof e&&e!==!1&&b(c).attr("data-srcset",e)}c=d(this,c),b(this).after(c),b(this).removeClass("ewww_webp_lazy_hueman")}),b(".ewww_webp").each(function(){var c=document.createElement("img");if(a){b(c).attr("src",b(this).attr("data-webp"));var e=b(this).attr("data-srcset-webp");"undefined"!=typeof e&&e!==!1&&b(c).attr("srcset",e)}else{b(c).attr("src",b(this).attr("data-img"));var e=b(this).attr("data-srcset-img");"undefined"!=typeof e&&e!==!1&&b(c).attr("srcset",e)}c=d(this,c),b(this).after(c),b(this).removeClass("ewww_webp")})}(jQuery),jQuery.fn.isotope&&jQuery.fn.imagesLoaded&&(jQuery(".fusion-posts-container-infinite").imagesLoaded(function(){jQuery(".fusion-posts-container-infinite").hasClass("isotope")&&jQuery(".fusion-posts-container-infinite").isotope()}),jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").imagesLoaded(function(){jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").isotope()}))}var Arrive=function(a,b,c){"use strict";function l(a,b,c){e.addMethod(b,c,a.unbindEvent),e.addMethod(b,c,a.unbindEventWithSelectorOrCallback),e.addMethod(b,c,a.unbindEventWithSelectorAndCallback)}function m(a){a.arrive=j.bindEvent,l(j,a,"unbindArrive"),a.leave=k.bindEvent,l(k,a,"unbindLeave")}if(a.MutationObserver&&"undefined"!=typeof HTMLElement){var d=0,e=function(){var b=HTMLElement.prototype.matches||HTMLElement.prototype.webkitMatchesSelector||HTMLElement.prototype.mozMatchesSelector||HTMLElement.prototype.msMatchesSelector;return{matchesSelector:function(a,c){return a instanceof HTMLElement&&b.call(a,c)},addMethod:function(a,b,c){var d=a[b];a[b]=function(){return c.length==arguments.length?c.apply(this,arguments):"function"==typeof d?d.apply(this,arguments):void 0}},callCallbacks:function(a){for(var c,b=0;c=a[b];b++)c.callback.call(c.elem)},checkChildNodesRecursively:function(a,b,c,d){for(var g,f=0;g=a[f];f++)c(g,b,d)&&d.push({callback:b.callback,elem:g}),g.childNodes.length>0&&e.checkChildNodesRecursively(g.childNodes,b,c,d)},mergeArrays:function(a,b){var d,c={};for(d in a)c[d]=a[d];for(d in b)c[d]=b[d];return c},toElementsArray:function(b){return"undefined"==typeof b||"number"==typeof b.length&&b!==a||(b=[b]),b}}}(),f=function(){var a=function(){this._eventsBucket=[],this._beforeAdding=null,this._beforeRemoving=null};return a.prototype.addEvent=function(a,b,c,d){var e={target:a,selector:b,options:c,callback:d,firedElems:[]};return this._beforeAdding&&this._beforeAdding(e),this._eventsBucket.push(e),e},a.prototype.removeEvent=function(a){for(var c,b=this._eventsBucket.length-1;c=this._eventsBucket[b];b--)a(c)&&(this._beforeRemoving&&this._beforeRemoving(c),this._eventsBucket.splice(b,1))},a.prototype.beforeAdding=function(a){this._beforeAdding=a},a.prototype.beforeRemoving=function(a){this._beforeRemoving=a},a}(),g=function(b,d){var g=new f,h=this,i={fireOnAttributesModification:!1};return g.beforeAdding(function(c){var i,e=c.target;c.selector,c.callback;e!==a.document&&e!==a||(e=document.getElementsByTagName("html")[0]),i=new MutationObserver(function(a){d.call(this,a,c)});var j=b(c.options);i.observe(e,j),c.observer=i,c.me=h}),g.beforeRemoving(function(a){a.observer.disconnect()}),this.bindEvent=function(a,b,c){b=e.mergeArrays(i,b);for(var d=e.toElementsArray(this),f=0;f<d.length;f++)g.addEvent(d[f],a,b,c)},this.unbindEvent=function(){var a=e.toElementsArray(this);g.removeEvent(function(b){for(var d=0;d<a.length;d++)if(this===c||b.target===a[d])return!0;return!1})},this.unbindEventWithSelectorOrCallback=function(a){var f,b=e.toElementsArray(this),d=a;f="function"==typeof a?function(a){for(var e=0;e<b.length;e++)if((this===c||a.target===b[e])&&a.callback===d)return!0;return!1}:function(d){for(var e=0;e<b.length;e++)if((this===c||d.target===b[e])&&d.selector===a)return!0;return!1},g.removeEvent(f)},this.unbindEventWithSelectorAndCallback=function(a,b){var d=e.toElementsArray(this);g.removeEvent(function(e){for(var f=0;f<d.length;f++)if((this===c||e.target===d[f])&&e.selector===a&&e.callback===b)return!0;return!1})},this},h=function(){function h(a){var b={attributes:!1,childList:!0,subtree:!0};return a.fireOnAttributesModification&&(b.attributes=!0),b}function i(a,b){a.forEach(function(a){var c=a.addedNodes,d=a.target,f=[];null!==c&&c.length>0?e.checkChildNodesRecursively(c,b,k,f):"attributes"===a.type&&k(d,b,f)&&f.push({callback:b.callback,elem:node}),e.callCallbacks(f)})}function k(a,b,f){if(e.matchesSelector(a,b.selector)&&(a._id===c&&(a._id=d++),b.firedElems.indexOf(a._id)==-1)){if(b.options.onceOnly){if(0!==b.firedElems.length)return;b.me.unbindEventWithSelectorAndCallback.call(b.target,b.selector,b.callback)}b.firedElems.push(a._id),f.push({callback:b.callback,elem:a})}}var f={fireOnAttributesModification:!1,onceOnly:!1,existing:!1};j=new g(h,i);var l=j.bindEvent;return j.bindEvent=function(a,b,c){"undefined"==typeof c?(c=b,b=f):b=e.mergeArrays(f,b);var d=e.toElementsArray(this);if(b.existing){for(var g=[],h=0;h<d.length;h++)for(var i=d[h].querySelectorAll(a),j=0;j<i.length;j++)g.push({callback:c,elem:i[j]});if(b.onceOnly&&g.length)return c.call(g[0].elem);setTimeout(e.callCallbacks,1,g)}l.call(this,a,b,c)},j},i=function(){function d(a){var b={childList:!0,subtree:!0};return b}function f(a,b){a.forEach(function(a){var c=a.removedNodes,f=(a.target,[]);null!==c&&c.length>0&&e.checkChildNodesRecursively(c,b,h,f),e.callCallbacks(f)})}function h(a,b){return e.matchesSelector(a,b.selector)}var c={};k=new g(d,f);var i=k.bindEvent;return k.bindEvent=function(a,b,d){"undefined"==typeof d?(d=b,b=c):b=e.mergeArrays(c,b),i.call(this,a,b,d)},k},j=new h,k=new i;b&&m(b.fn),m(HTMLElement.prototype),m(NodeList.prototype),m(HTMLCollection.prototype),m(HTMLDocument.prototype),m(Window.prototype);var n={};return l(j,n,"unbindAllArrive"),l(k,n,"unbindAllLeave"),n}}(window,"undefined"==typeof jQuery?null:jQuery,void 0);"undefined"!=typeof jQuery&&check_webp_feature("alpha",ewww_load_images);
</script>
<?php	} // current length 9069
}

// enqueue custom jquery stylesheet for bulk optimizer
function ewww_image_optimizer_media_scripts( $hook ) {
	if ( $hook == 'upload.php' ) {
		add_thickbox();
		wp_enqueue_script( 'jquery-ui-tooltip' );
		wp_enqueue_script( 'ewwwmediascript', plugins_url( '/includes/media.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		wp_enqueue_style( 'jquery-ui-tooltip-custom', plugins_url( '/includes/jquery-ui-1.10.1.custom.css', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
		// submit a couple variables to the javascript to work with
		$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
		$loading_image = plugins_url( '/images/spinner.gif', __FILE__ );
		wp_localize_script( 
			'ewwwmediascript',
			'ewww_vars',
			array(
				//'operation_interrupted' => esc_html__( 'Operation Interrupted', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
				'optimizing' => "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>",
				'restoring' => "<p>" . esc_html__( 'Restoring', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>",
			)
		);
	}
}

// adds a link on the Plugins page for the EWWW IO settings
function ewww_image_optimizer_settings_link( $links ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	// load the html for the settings link
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$settings_link = '<a href="network/settings.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
	} else {
		$settings_link = '<a href="options-general.php?page=' . plugin_basename( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
	}
	// load the settings link into the plugin links array
	array_unshift( $links, $settings_link );
	// send back the plugin links array
	return $links;
}

// check for GD support of both PNG and JPG
function ewww_image_optimizer_gd_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'gd_info' ) ) {
		$gd_support = gd_info();
		ewwwio_debug_message( "GD found, supports:" ); 
		if ( ewww_image_optimizer_iterable( $gd_support ) ) {
			foreach ( $gd_support as $supports => $supported ) {
				 ewwwio_debug_message( "$supports: $supported" );
			}
			ewwwio_memory( __FUNCTION__ );
			if ( ( ! empty( $gd_support["JPEG Support"] ) || ! empty( $gd_support["JPG Support"] ) ) && ! empty( $gd_support["PNG Support"] ) ) {
				return true;
			}
		}
	}
	return false;
}

// check for IMagick support of both PNG and JPG
function ewww_image_optimizer_imagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'imagick' ) ) {
		$imagick = new Imagick();
		$formats = $imagick->queryFormats();
		if ( in_array( 'PNG', $formats ) && in_array( 'JPG', $formats ) ) {
			return true;
		}
	}
	return false;
}

// check for IMagick support of both PNG and JPG
function ewww_image_optimizer_gmagick_support() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( extension_loaded( 'gmagick' ) ) {
		$gmagick = new Gmagick();
		$formats = $gmagick->queryFormats();
		if ( in_array( 'PNG', $formats ) && in_array( 'JPG', $formats ) ) {
			return true;
		}
	}
	return false;
}

function ewww_image_optimizer_disable_resizes_sanitize( $disabled_resizes ) {
	if ( is_array( $disabled_resizes ) ) {
		return $disabled_resizes;
	} else {
		return '';
	}
}

function ewww_image_optimizer_aux_paths_sanitize( $input ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $input ) ) {
		return '';
	}
	$path_array = array();
	$paths = explode( "\n", $input );
	if ( ewww_image_optimizer_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			$path = sanitize_text_field( $path );
			ewwwio_debug_message( "validating auxiliary path: $path" );
			// retrieve the location of the wordpress upload folder
			$upload_dir = apply_filters( 'ewww_image_optimizer_folder_restriction', wp_upload_dir( null, false ) );
			// retrieve the path of the upload folder
			$upload_path = trailingslashit( $upload_dir['basedir'] );
			if ( is_dir( $path ) && ( strpos( $path, ABSPATH ) === 0 || strpos( $path, $upload_path ) === 0 ) ) {
				$path_array[] = $path;
				continue;
			}
			// what if they put in a relative path
			if ( is_dir( ABSPATH . ltrim( $path, '/' ) ) ) {
				$path_array[] = ABSPATH . ltrim( $path, '/' );
				continue;
			}
			// or a relative to the upload dir?
			if ( is_dir( $upload_path . ltrim( $path, '/' ) ) ) {
				$path_array[] = $upload_path . ltrim( $path, '/' );
				continue;
			}
			// what if they put in a url?
			$pathabsurl = ABSPATH . ltrim( str_replace( get_site_url(), '', $path ), '/' );
			if ( is_dir( $pathabsurl ) ) {
				$path_array[] = $pathabsurl;
				continue;
			}
			// or a url in the uploads folder?
			$pathupurl = $upload_path . ltrim( str_replace( $upload_dir['baseurl'], '', $path ), '/' );
			if ( is_dir( $pathupurl ) ) {
				$path_array[] = $pathupurl;
				continue;
			}
			if ( ! empty( $path ) ) {
				add_settings_error( 'ewww_image_optimizer_aux_paths', "ewwwio-aux-paths-$i", sprintf( esc_html__( 'Could not save Folder to Optimize: %s. Please ensure that it is a valid location on the server.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html( $path ) ) );
			}
		}
	}
//	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	return $path_array;
}

function ewww_image_optimizer_exclude_paths_sanitize( $input ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $input ) ) {
		return '';
	}
	$path_array = array();
	$paths = explode("\n", $input);
	if ( ewww_image_optimizer_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			ewwwio_debug_message( "validating path exclusion: $path" );
			$path = sanitize_text_field( $path );
			if ( ! empty( $path ) ) {
				$path_array[] = $path;
			}
		}
	}
//	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	return $path_array;
}

function ewww_image_optimizer_webp_paths_sanitize( $paths ) {
	if ( empty( $paths ) ) {
		return '';
	}
	$paths_entered = explode( "\n", $paths );
	$paths_saved = array();
	if ( ewww_image_optimizer_iterable( $paths_entered ) ) {
		$i = 0;
		foreach ( $paths_entered as $path ) {
			$i++;
			$original_path = esc_html( $path );
			$path = esc_url( $path, null, 'db' );
			if ( ! empty( $path ) ) {
				if ( ! substr_count( $path, '.' ) ) {
					add_settings_error( 'ewww_image_optimizer_webp_paths', "ewwwio-webp-paths-$i", sprintf( esc_html__( 'Could not save WebP URL: %s.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), esc_html( $original_path ) ) . ' ' . esc_html__( 'Please enter a valid url including the domain name.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
					continue;
				}
				$paths_saved[] = str_replace( 'http://', '', $path );
			}
		}
	}
	return $paths_saved;
}

// replacement for escapeshellarg() that won't kill non-ASCII characters
function ewww_image_optimizer_escapeshellarg( $arg ) {
	if ( PHP_OS === 'WINNT' ) {
		$safe_arg = '"' . $arg . '"';
	} else {
		$safe_arg = "'" . str_replace("'", "'\"'\"'", $arg) . "'";
	}
	ewwwio_memory( __FUNCTION__ );
	return $safe_arg;
}

// Retrieves/sanitizes jpg background fill setting or returns null for png2jpg conversions
function ewww_image_optimizer_jpg_background( $background = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( $background === null ) {
		// retrieve the user-supplied value for jpg background color
		$background = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_background' );
	}
	//verify that the supplied value is in hex notation
	if ( preg_match( '/^\#*([0-9a-fA-F]){6}$/', $background ) ) {
		// we remove a leading # symbol, since we take care of it later
		$background = ltrim( $background, '#' );
		// send back the verified, cleaned-up background color
		ewwwio_debug_message( "background: $background" );
		ewwwio_memory( __FUNCTION__ );
		return $background;
	} else {
		if ( ! empty( $background ) ) {
			add_settings_error( 'ewww_image_optimizer_jpg_background', 'ewwwio-jpg-background', esc_html__( 'Could not save the JPG background color, please enter a six-character, hexadecimal value.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		// send back a blank value
		ewwwio_memory( __FUNCTION__ );
		return NULL;
	}
}

// Retrieves/sanitizes the jpg quality setting for png2jpg conversion or returns null
function ewww_image_optimizer_jpg_quality( $quality = null ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( $quality === null ) {
		// retrieve the user-supplied value for jpg quality
		$quality = ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_quality' );
	}
	// verify that the quality level is an integer, 1-100
	if ( preg_match( '/^(100|[1-9][0-9]?)$/', $quality ) ) {
		ewwwio_debug_message( "quality: $quality" );
		// send back the valid quality level
		ewwwio_memory( __FUNCTION__ );
		return $quality;
	} else {
		if ( ! empty( $quality ) ) {
			add_settings_error( 'ewww_image_optimizer_jpg_quality', 'ewwwio-jpg-quality', esc_html__( 'Could not save the JPG quality, please enter an integer between 1 and 100.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		// send back nothing
		ewwwio_memory( __FUNCTION__ );
		return NULL;
	}
}

// filter the filename past any folders the user wants to ignore
function ewww_image_optimizer_ignore_file( $bypass, $filename ) {
	$ignore_folders = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' );
	if ( ! ewww_image_optimizer_iterable( $ignore_folders ) ) {
		return $bypass;
	}
	foreach( $ignore_folders as $ignore_folder ) {
		if ( strpos( $filename, $ignore_folder ) !== false ) {
			return true;
		}
	}
	return $bypass;
}

function ewww_image_optimizer_set_jpg_quality( $quality ) {
	$new_quality = ewww_image_optimizer_jpg_quality();
	if ( ! empty( $new_quality ) ) {
		return $new_quality;
	}
	return $quality;
}

// check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared
function ewww_image_optimizer_filesize( $file ) {
	if ( is_file( $file ) ) {
		// flush the cache for filesize
		clearstatcache();
		// find out the size of the new PNG file
		return filesize( $file );
	} else {
		return 0;
	}
}

// make sure an array/object can be parsed by a foreach()
function ewww_image_optimizer_iterable( $var ) {
	return ! empty( $var ) && ( is_array( $var ) || is_object( $var ) );
}

/**
 * Manually process an image from the Media Library
 */
function ewww_image_optimizer_manual() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_defer;
	$ewww_defer = false;
	// check permissions of current user
	$permissions = apply_filters( 'ewww_image_optimizer_manual_permissions', '' );
	if ( false === current_user_can( $permissions ) ) {
		// display error message if insufficient permissions
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'You do not have permission to optimize images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		wp_die( json_encode( array( 'error' => esc_html__( 'You do not have permission to optimize images.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	// make sure we didn't accidentally get to this page without an attachment to work on
	if ( false === isset( $_REQUEST['ewww_attachment_ID'] ) ) {
		// display an error message since we don't have anything to work on
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		wp_die( json_encode( array( 'error' => esc_html__( 'No attachment ID was provided.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	session_write_close();
	// store the attachment ID value
	$attachment_ID = intval( $_REQUEST['ewww_attachment_ID']) ;
	if ( empty( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_manual_nonce'], "ewww-manual" ) ) {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		wp_die( json_encode( array( 'error' => esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	// retrieve the existing attachment metadata
	$original_meta = wp_get_attachment_metadata( $attachment_ID );
	// if the call was to optimize...
	if ( $_REQUEST['action'] === 'ewww_image_optimizer_manual_optimize' || $_REQUEST['action'] === 'ewww_manual_optimize' ) {
		// call the optimize from metadata function and store the resulting new metadata
		$new_meta = ewww_image_optimizer_resize_from_meta_data( $original_meta, $attachment_ID );
	} elseif ( $_REQUEST['action'] === 'ewww_image_optimizer_manual_restore' || $_REQUEST['action'] === 'ewww_manual_restore' ) {
		$new_meta = ewww_image_optimizer_restore_from_meta_data( $original_meta, $attachment_ID );
	} else {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		wp_die( json_encode( array( 'error' => esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
	}
	$basename = '';
	if ( is_array( $new_meta ) && ! empty( $new_meta['file'] ) ) {
		$basename = basename( $new_meta['file'] );
	}
	// update the attachment metadata in the database
	$meta_saved = wp_update_attachment_metadata( $attachment_ID, $new_meta );
	if ( ! $meta_saved ) {
		ewwwio_debug_message( 'failed to save meta' );
	}
	if ( get_transient( 'ewww_image_optimizer_cloud_status' ) == 'exceeded' || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		die( json_encode( array( 'error' => esc_html__( 'License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN) ) ) );
	} 
	$success = ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $attachment_ID, $new_meta, true );
	ewww_image_optimizer_debug_log();
	// do a redirect, if this was called via GET
	if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
		// store the referring webpage location
		$sendback = wp_get_referer();
		// sanitize the referring webpage location
		$sendback = preg_replace('|[^a-z0-9-~+_.?#=&;,/:]|i', '', $sendback);
		// send the user back where they came from
		wp_redirect( $sendback );
		return;
	}
	// we are done, nothing to see here
	ewwwio_memory( __FUNCTION__ );
	die( json_encode( array( 'success' => $success, 'basename' => $basename ) ) );
}

/**
 * Manually restore a converted image
 */
function ewww_image_optimizer_restore_from_meta_data( $meta, $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$db_image = $ewwwdb->get_results( "SELECT id,path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND resize = 'full'", ARRAY_A );
	if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
		// get the filepath based on the meta and id
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$db_image = ewww_image_optimizer_find_already_optimized( $file_path );
		if ( empty( $db_image ) || ! is_array( $db_image ) || empty( $db_image['path'] ) ) {
			return $meta;
		}
	}
	$ewww_image = new EWWW_Image( $id, 'media', $db_image['path'] );
	return $ewww_image->restore_with_meta( $meta );
}

// deletes 'orig_file' when an attachment is being deleted
function ewww_image_optimizer_delete( $id ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	// finds non-meta images to remove from disk, and from db, as well as converted originals
	if ( $optimized_images = $ewwwdb->get_results( "SELECT path,converted FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media'", ARRAY_A ) ) {
		if ( ewww_image_optimizer_iterable( $optimized_images ) ) {
			foreach ( $optimized_images as $image ) {
				if ( ! empty( $image['path'] )&& is_file( $image['path'] ) ) {
					unlink( $image['path'] );
					if ( is_file( $image['path'] . '.webp' ) ) {
						unlink( $image['path'] . '.webp' );
					}
				}
				if ( ! empty( $image['converted'] ) && is_file( $image['converted'] ) ) {
					unlink( $image['converted'] );
					if ( is_file( $image['converted'] . '.webp' ) ) {
						unlink( $image['converted'] . '.webp' );
					}
				}
			}
		}
		$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'attachment_id' => $id ) );
	}
	// retrieve the image metadata
	$meta = wp_get_attachment_metadata($id);
	// if the attachment has an original file set
	if ( ! empty( $meta['orig_file'] ) ) {
		unset($rows);
		// get the filepath from the metadata
		$file_path = $meta['orig_file'];
		// get the filename
		$filename = basename( $file_path );
		// delete any residual webp versions
		$webpfile = $filename . '.webp';
		$webpfileold = preg_replace( '/\.\w+$/', '.webp', $filename );
		if ( is_file( $webpfile) ) {
			unlink( $webpfile );
		}
		if ( is_file( $webpfileold) ) {
			unlink( $webpfileold );
		}
		// retrieve any posts that link the original image
		$esql = "SELECT ID, post_content FROM $ewwwdb->posts WHERE post_content LIKE '%$filename%' LIMIT 1";
		$rows = $ewwwdb->get_row( $esql );
		// if the original file still exists and no posts contain links to the image
		if ( file_exists( $file_path ) && empty( $rows ) ) {
			unlink( $file_path );
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => $file_path ) );
		}
	}
	// remove the regular image from the ewwwio_images tables
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => $file_path ) );
	// resized versions, so we can continue
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// one way or another, $file_path is now set, and we can get the base folder name
		$base_dir = dirname( $file_path ) . '/';
		// check each resized version
		foreach( $meta['sizes'] as $size => $data ) {
			// delete any residual webp versions
			$webpfile = $base_dir . $data['file'] . '.webp';
			$webpfileold = preg_replace( '/\.\w+$/', '.webp', $base_dir . $data['file'] );
			if ( file_exists( $webpfile ) ) {
				unlink( $webpfile );
			}
			if ( file_exists( $webpfileold ) ) {
				unlink( $webpfileold );
			}
			$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => $base_dir . $data['file'] ) );
			// if the original resize is set, and still exists
			if ( ! empty( $data['orig_file'] ) && file_exists( $base_dir . $data['orig_file'] ) ) {
				unset( $srows );
				// retrieve the filename from the metadata
				$filename = $data['orig_file'];
				// retrieve any posts that link the image
				$esql = "SELECT ID, post_content FROM $ewwwdb->posts WHERE post_content LIKE '%$filename%' LIMIT 1";
				$srows = $ewwwdb->get_row( $esql );
				// if there are no posts containing links to the original, delete it
				if( empty( $srows ) ) {
					unlink( $base_dir . $data['orig_file'] );
					$ewwwdb->delete( $ewwwdb->ewwwio_images, array( 'path' => $base_dir . $data['orig_file'] ) );
				}
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return;
}

function ewww_image_optimizer_cloud_key_sanitize( $key ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$key = trim( $key );
	ewwwio_debug_message( print_r( $_REQUEST, true ) );
	if ( ewww_image_optimizer_cloud_verify( false, $key ) ) {
		add_settings_error( 'ewww_image_optimizer_cloud_key', "ewwwio-cloud-key", esc_html__( 'Successfully validated API key, happy optimizing!', EWWW_IMAGE_OPTIMIZER_DOMAIN ), 'updated' );
		ewwwio_debug_message( 'sanitize (verification) successful' );
		ewwwio_memory( __FUNCTION__ );
//		ewww_image_optimizer_debug_log();
		return $key;
	} else {
		if ( ! empty( $key ) )
			add_settings_error( 'ewww_image_optimizer_cloud_key', "ewwwio-cloud-key", esc_html__( 'Could not validate API key, please copy and paste your key to ensure it is correct.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		ewwwio_debug_message( 'sanitize (verification) failed' );
		ewwwio_memory( __FUNCTION__ );
//		ewww_image_optimizer_debug_log();
		return '';
	}
}

function ewww_image_optimizer_full_cloud() {
//	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 ) {
//		ewwwio_debug_message( 'all cloud mode enabled, no local' );
		return true;
	} elseif ( EWWW_IMAGE_OPTIMIZER_DOMAIN == 'ewww-image-optimizer-cloud' ) {
//		ewwwio_debug_message( 'cloud-only plugin, no local' );
		return true;
	}
//	ewwwio_debug_message( 'local mode allowed' );
	return false;
}

// turns on the cloud settings when they are all disabled
function ewww_image_optimizer_cloud_enable() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewww_image_optimizer_set_option('ewww_image_optimizer_jpg_level', 20);
	ewww_image_optimizer_set_option('ewww_image_optimizer_png_level', 20);
	ewww_image_optimizer_set_option('ewww_image_optimizer_gif_level', 10);
	ewww_image_optimizer_set_option('ewww_image_optimizer_pdf_level', 10);
}

// adds our version to the useragent for http requests
function ewww_image_optimizer_cloud_useragent( $useragent ) {
	$useragent .= ' EWWW/' . EWWW_IMAGE_OPTIMIZER_VERSION . ' ';
	ewwwio_memory( __FUNCTION__ );
	return $useragent;
}

// submits the api key for verification
function ewww_image_optimizer_cloud_verify( $cache = true, $api_key = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $api_key ) && ! ( ! empty( $_REQUEST['option_page'] ) && $_REQUEST['option_page'] == 'ewww_image_optimizer_options' ) ) {
		$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	} elseif ( empty( $api_key ) && ! empty( $_POST['ewww_image_optimizer_cloud_key'] ) ) {
		$api_key = $_POST['ewww_image_optimizer_cloud_key'];
	}
	if ( empty( $api_key ) ) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) > 10 ) {
			update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
			update_option( 'ewww_image_optimizer_jpg_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) != 40 ) {
			update_site_option( 'ewww_image_optimizer_png_level', 10 );
			update_option( 'ewww_image_optimizer_png_level', 10 );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) > 0 ) {
			update_site_option( 'ewww_image_optimizer_pdf_level', 0 );
			update_option( 'ewww_image_optimizer_pdf_level', 0 );
		}
		return false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 ); 
		ewwwio_debug_message( 'license exceeded notice has not expired' );
		return 'exceeded';
	}
	add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent' );
	$ewww_cloud_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$ewww_cloud_ip = get_transient( 'ewww_image_optimizer_cloud_ip' );
	$ewww_cloud_transport = get_transient( 'ewww_image_optimizer_cloud_transport' );
	if ( ! ewww_image_optimizer_detect_wpsf_location_lock() && $cache && preg_match( '/^(\d{1,3}\.){3}\d{1,3}$/', $ewww_cloud_ip ) && preg_match( '/http/', $ewww_cloud_transport ) && preg_match( '/great/', $ewww_cloud_status ) ) {
		ewwwio_debug_message( 'using cached verification' );
		global $ewwwio_async_key_verification;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_key_verification ) ) {
			$ewwwio_async_key_verification = new EWWWIO_Async_Key_Verification();
		}
		$ewwwio_async_key_verification->dispatch();
		return $ewww_cloud_status;
	}
	if ( $ewww_cloud_transport !== 'https' && $ewww_cloud_transport !== 'http' ) {
		$ewww_cloud_transport = 'https';
	}
	if ( preg_match( '/^(\d{1,3}\.){3}\d{1,3}$/', $ewww_cloud_ip ) ) {
		ewwwio_debug_message( 'using cached ip' );
		$result = ewww_image_optimizer_cloud_post_key( $ewww_cloud_ip, $ewww_cloud_transport, $api_key );
		if ( is_wp_error( $result ) ) {
			$ewww_cloud_transport = 'http';
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "verification failed: $error_message" );
			$result = ewww_image_optimizer_cloud_post_key( $ewww_cloud_ip, $ewww_cloud_transport, $api_key );
		}
		if ( is_wp_error( $result ) ) {
			$error_message = $result->get_error_message();
			ewwwio_debug_message( "verification failed: $error_message" );
		} elseif ( ! empty( $result['body'] ) && preg_match( '/(great|exceeded)/', $result['body'] ) ) {
			$verified = $result['body'];
			ewwwio_debug_message( "verification success via: $ewww_cloud_transport://$ewww_cloud_ip" );
		} else {
			ewwwio_debug_message( "verification failed via: $ewww_cloud_ip" );
			ewwwio_debug_message( print_r( $result, true ) );
		}
	}
	if ( empty( $verified ) ) {
		$ewww_cloud_transport = 'https';
		$servers = gethostbynamel( 'optimize.exactlywww.com' );
		if ( empty ( $servers ) ) {
			ewwwio_debug_message( 'unable to resolve servers' );
			return false;
		}
		if ( ewww_image_optimizer_iterable( $servers ) ) {
			foreach ( $servers as $ip ) {
				$result = ewww_image_optimizer_cloud_post_key( $ip, $ewww_cloud_transport, $api_key );
				if ( is_wp_error( $result ) ) {
					$ewww_cloud_transport = 'http';
					$error_message = $result->get_error_message();
					ewwwio_debug_message( "verification failed: $error_message" );
				} elseif ( ! empty( $result['body'] ) && preg_match( '/(great|exceeded)/', $result['body'] ) ) {
					$verified = $result['body'];
					if ( preg_match( '/exceeded/', $verified ) ) {
						ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', time() + 300 );
					}
					$ewww_cloud_ip = $ip;
					ewwwio_debug_message( "verification success via: $ewww_cloud_transport://$ewww_cloud_ip" );
					break;
				} else {
					ewwwio_debug_message( "verification failed via: $ip" );
					ewwwio_debug_message( print_r( $result, true ) );
				}
			}
		} else {
			ewwwio_debug_message( 'unable to parse server list' );
		}
	}
	if ( empty( $verified ) ) {
		ewwwio_memory( __FUNCTION__ );
		return false;
	} else {
		set_transient( 'ewww_image_optimizer_cloud_status', $verified, 3600 ); 
		set_transient( 'ewww_image_optimizer_cloud_ip', $ewww_cloud_ip, 3600 );
		set_transient( 'ewww_image_optimizer_cloud_transport', $ewww_cloud_transport, 3600 ); 
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) < 20 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 0 ) {
			ewww_image_optimizer_cloud_enable();
		}
		ewwwio_debug_message( "verification body contents: {$result['body']}" );
		ewwwio_memory( __FUNCTION__ );
		return $verified;
	}
}

function ewww_image_optimizer_cloud_post_key( $ip, $transport, $key ) {
	$result = wp_remote_post( "$transport://$ip/verify/", array(
		'timeout' => 5,
		'sslverify' => false,
		'body' => array( 'api_key' => $key )
	) );
	return $result;
}

// checks the provided api key for quota information
function ewww_image_optimizer_cloud_quota() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
//	$ewww_cloud_ip = get_transient( 'ewww_image_optimizer_cloud_ip' );
	$ewww_cloud_transport = get_transient( 'ewww_image_optimizer_cloud_transport' );
	if ( empty( $ewww_cloud_transport ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) { 
			return '';
		} else {
			$ewww_cloud_transport = get_transient( 'ewww_image_optimizer_cloud_transport' );
		}
	}
	if ( empty( $ewww_cloud_transport ) ) {
		$ewww_cloud_transport = 'https';
	}
	$api_key = ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' );
	$url = "$ewww_cloud_transport://optimize.exactlywww.com/quota/";
	$result = wp_remote_post( $url, array(
		'timeout' => 5,
		'sslverify' => false,
		'body' => array( 'api_key' => $api_key )
	) );
	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
		ewwwio_debug_message( "quota request failed: $error_message" );
		ewwwio_memory( __FUNCTION__ );
		return '';
	} elseif ( ! empty( $result['body'] ) ) {
		ewwwio_debug_message( "quota data retrieved: {$result['body']}" );
		$quota = explode(' ', $result['body']);
		ewwwio_memory( __FUNCTION__ );
		if ( $quota[0] == 0 && $quota[1] > 0 ) {
			return esc_html( sprintf( _n( 'optimized %1$d images, usage will reset in %2$d day.', 'optimized %1$d images, usage will reset in %2$d days.', $quota[2], EWWW_IMAGE_OPTIMIZER_DOMAIN ), $quota[1], $quota[2] ) );
		} elseif ( $quota[0] == 0 && $quota[1] < 0 ) {
			return esc_html( sprintf( _n( '%1$d image credit remaining.', '%1$d image credits remaining.', abs( $quota[1] ), EWWW_IMAGE_OPTIMIZER_DOMAIN ), abs( $quota[1] ) ) );
		} elseif ( $quota[0] > 0 && $quota[1] < 0 ) {
			$real_quota = $quota[0] - $quota[1];
			return esc_html( sprintf( _n( '%1$d image credit remaining.', '%1$d image credits remaining.', $real_quota, EWWW_IMAGE_OPTIMIZER_DOMAIN ), $real_quota ) );
		} else {
			return esc_html( sprintf( _n( 'used %1$d of %2$d, usage will reset in %3$d day.', 'used %1$d of %2$d, usage will reset in %3$d days.', $quota[2], EWWW_IMAGE_OPTIMIZER_DOMAIN ), $quota[1], $quota[0], $quota[2] ) );
		}
	}
}

/* submits an image to the cloud optimizer and saves the optimized image to disk
 *
 * Returns an array of the $file, $converted, possibly a $msg, and the $new_size
 *
 * @param   string $file		Full absolute path to the image file
 * @param   string $type		mimetype of $file
 * @param   boolean $convert		true says we want to attempt conversion of $file
 * @param   string $newfile		filename of new converted image
 * @param   string $newtype		mimetype of $newfile
 * @param   boolean $fullsize		is this the full-size original?
 * @param   array $jpg_params		r, g, b values and jpg quality setting for conversion
 * @returns array
*/
function ewww_image_optimizer_cloud_optimizer( $file, $type, $convert = false, $newfile = null, $newtype = null, $fullsize = false, $jpg_params = array( 'r' => '255', 'g' => '255', 'b' => '255', 'quality' => null ) ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$ewww_cloud_ip = get_transient( 'ewww_image_optimizer_cloud_ip' );
	$ewww_cloud_transport = get_transient( 'ewww_image_optimizer_cloud_transport' );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	$started = microtime( true );
	if ( empty( $ewww_cloud_ip ) || empty( $ewww_cloud_transport ) || preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! ewww_image_optimizer_cloud_verify() ) { 
			return array( $file, false, 'key verification failed', 0, '' );
		} else {
			$ewww_cloud_ip = get_transient( 'ewww_image_optimizer_cloud_ip' );
			$ewww_cloud_transport = get_transient( 'ewww_image_optimizer_cloud_transport' );
		}
	}
	// calculate how much time has elapsed since we started
	$elapsed = microtime( true ) - $started;
	// output how much time has elapsed since we started
	ewwwio_debug_message( sprintf( 'Cloud verify took %.3f seconds', $elapsed ) );
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_exceeded' ) > time() ) {
		ewwwio_debug_message( 'license exceeded, image not processed' );
		return array( $file, false, 'exceeded', 0, '' );
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) && $fullsize ) {
		$metadata = 1;
	} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpegtran_copy' ) ){
        	// don't copy metadata
                $metadata = 0;
        } else {
                // copy all the metadata
                $metadata = 1;
        }
	if ( empty( $convert ) ) {
		$convert = 0;
	} else {
		$convert = 1;
	}
	$lossy_fast = 0;
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full') && $fullsize ) {
		$lossy = 0;
	} elseif ( $type == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) >= 40 ) {
		$lossy = 1;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
			$lossy_fast = 1;
		}
	} elseif ( $type == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) >= 30 ) {
		$lossy = 1;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 30 ) {
			$lossy_fast = 1;
		}
	} elseif ( $type == 'application/pdf' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
		$lossy = 1;
	} else {
		$lossy = 0;
	}
	if ( $newtype == 'image/webp' ) {
		$webp = 1;
	} else {
		$webp = 0;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
		$png_compress = 1;
	} else {
		$png_compress = 0;
	}
	if ( ! $webp && ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' )
			&& strpos( $file, '/wp-admin/' ) === false
			&& strpos( $file, '/wp-includes/' ) === false
			&& strpos( $file, '/wp-content/themes/' ) === false
			&& strpos( $file, '/wp-content/plugins/' ) === false
		//	&& strpos( $file, '@2x.' ) === false
			&& strpos( $file, '/cache/' ) === false
			&& strpos( $file, '/thumbs/' ) === false
			&& strpos( $file, '/dynamic/' ) === false
		//	&& ! preg_match( '/\d{1,4}x\d{1,4}\.\w+$/', $file )
	) {
			$hash = uniqid() . hash( 'sha256', $file );
			$domain = parse_url( get_site_url(), PHP_URL_HOST );
	} else {
		$hash = '';
		$domain = parse_url( get_site_url(), PHP_URL_HOST );
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "type: $type" );
	ewwwio_debug_message( "convert: $convert" );
	ewwwio_debug_message( "newfile: $newfile" );
	ewwwio_debug_message( "newtype: $newtype" );
	ewwwio_debug_message( "webp: $webp" );
	ewwwio_debug_message( "jpg_params: " . print_r($jpg_params, true) );
	$api_key = ewww_image_optimizer_get_option('ewww_image_optimizer_cloud_key');
	$url = "$ewww_cloud_transport://$ewww_cloud_ip/";
	$boundary = wp_generate_password(24, false);

	$headers = array(
        	'content-type' => 'multipart/form-data; boundary=' . $boundary,
		'timeout' => 90,
		'httpversion' => '1.0',
		'blocking' => true
		);
	$post_fields = array(
		'filename' => $file, 
		'convert' => $convert, 
		'metadata' => $metadata, 
		'api_key' => $api_key,
		'red' => $jpg_params['r'],
		'green' => $jpg_params['g'],
		'blue' => $jpg_params['b'],
		'quality' => $jpg_params['quality'],
		'compress' => $png_compress,
		'lossy' => $lossy,
		'lossy_fast' => $lossy_fast,
		'webp' => $webp,
		'backup' => $hash,
		'domain' => $domain,
	);

	$payload = '';
	foreach ( $post_fields as $name => $value ) {
        	$payload .= '--' . $boundary;
	        $payload .= "\r\n";
	        $payload .= 'Content-Disposition: form-data; name="' . $name .'"' . "\r\n\r\n";
	        $payload .= $value;
	        $payload .= "\r\n";
	}

	$payload .= '--' . $boundary;
	$payload .= "\r\n";
	$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file) . '"' . "\r\n";
	$payload .= 'Content-Type: ' . $type . "\r\n";
	$payload .= "\r\n";
	$payload .= file_get_contents($file);
	$payload .= "\r\n";
	$payload .= '--' . $boundary;
	$payload .= 'Content-Disposition: form-data; name="submitHandler"' . "\r\n";
	$payload .= "\r\n";
	$payload .= "Upload\r\n";
	$payload .= '--' . $boundary . '--';

	// retrieve the time when the optimizer starts
//	$started = microtime(true);
	$response = wp_remote_post( $url, array(
		'timeout' => 90,
		'headers' => $headers,
		'sslverify' => false,
		'body' => $payload,
		) );
//	$elapsed = microtime(true) - $started;
//	$ewww_debug .= "processing image via cloud took $elapsed seconds<br>";
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		ewwwio_debug_message( "optimize failed: $error_message" );
		return array( $file, false, 'cloud optimize failed', 0, '' );
	} else {
		$tempfile = $file . ".tmp";
		file_put_contents( $tempfile, $response['body'] );
		$orig_size = filesize( $file );
		$newsize = $orig_size;
		$converted = false;
		$msg = '';
		if ( preg_match( '/exceeded/', $response['body'] ) ) {
			ewwwio_debug_message( 'License Exceeded' );
			set_transient( 'ewww_image_optimizer_cloud_status', 'exceeded', 3600 );
			$msg = 'exceeded';
			unlink( $tempfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == $type ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $file );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == 'image/webp' ) {
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $newfile );
		} elseif ( ewww_image_optimizer_mimetype( $tempfile, 'i' ) == $newtype ) {
			$converted = true;
			$newsize = filesize( $tempfile );
			ewwwio_debug_message( "cloud results: $newsize (new) vs. $orig_size (original)" );
			rename( $tempfile, $newfile );
			$file = $newfile;
		} else {
			unlink( $tempfile );
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $converted, $msg, $newsize, $hash );
	}
}

function ewww_image_optimizer_db_init() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewwwdb, $table_prefix;
	require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/ewww-db.php' );
	if ( ! isset( $ewwwdb ) ) {
		$ewwwdb = new ewwwdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	}

	if ( !empty( $ewwwdb->error ) )
		dead_db();

	$ewwwdb->field_types = array( 'post_author' => '%d', 'post_parent' => '%d', 'menu_order' => '%d', 'term_id' => '%d', 'term_group' => '%d', 'term_taxonomy_id' => '%d',
		'parent' => '%d', 'count' => '%d','object_id' => '%d', 'term_order' => '%d', 'ID' => '%d', 'comment_ID' => '%d', 'comment_post_ID' => '%d', 'comment_parent' => '%d',
		'user_id' => '%d', 'link_id' => '%d', 'link_owner' => '%d', 'link_rating' => '%d', 'option_id' => '%d', 'blog_id' => '%d', 'meta_id' => '%d', 'post_id' => '%d',
		'user_status' => '%d', 'umeta_id' => '%d', 'comment_karma' => '%d', 'comment_count' => '%d',
		// multisite:
		'active' => '%d', 'cat_id' => '%d', 'deleted' => '%d', 'lang_id' => '%d', 'mature' => '%d', 'public' => '%d', 'site_id' => '%d', 'spam' => '%d',
	);

	$prefix = $ewwwdb->set_prefix( $table_prefix );

	if ( is_wp_error( $prefix ) ) {
		wp_load_translations_early();
		wp_die(
			/* translators: 1: $table_prefix 2: wp-config.php */
			sprintf( __( '<strong>ERROR</strong>: %1$s in %2$s can only contain numbers, letters, and underscores.' ),
				'<code>$table_prefix</code>',
				'<code>wp-config.php</code>'
			)
		);
	}
	if ( ! isset( $ewwwdb->ewwwio_images ) ) {
		$ewwwdb->ewwwio_images = $ewwwdb->prefix . "ewwwio_images";
	}
}

// inserts multiple records into the table at once
// each sub-array should have the same number of items as $columns & $formats
// if $format isn't specified, default to string (%s)
function ewww_image_optimizer_mass_insert( $table, $images, $format ) {
	if ( empty( $table ) || ! ewww_image_optimizer_iterable( $images ) || ! ewww_image_optimizer_iterable( $format ) ) {
		return false;
	}
	ewww_image_optimizer_db_init();
	global $ewwwdb;
	$ewwwdb->insert_multiple( $table, $images, $format );	
}

// check the database to see if we've done this image before
function ewww_image_optimizer_check_table( $file, $orig_size ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "checking for $file with size: $orig_size" );
	$image = ewww_image_optimizer_find_already_optimized( $file );
	if ( is_array( $image ) && $image['image_size'] == $orig_size ) {
		$prev_string = " - " . __( 'Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		if ( preg_match( '/' . __( 'License exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '/', $image['results'] ) ) {
			return;
		}
		$already_optimized = preg_replace( "/$prev_string/", '', $image['results'] );
		$already_optimized = $already_optimized . $prev_string;
		ewwwio_debug_message( "already optimized: {$image['path']} - $already_optimized" );
		ewwwio_memory( __FUNCTION__ );
		// make sure the image isn't pending
		if ( $image['pending'] ) {
			global $wpdb;
			$wpdb->update( $wpdb->ewwwio_images,
				array(
					'pending' => 0
				),
				array(
					'id' => $image['id'],
				)
			);
		}
		return $already_optimized;
	}
}

// receives a path, optimized size, and an original size to insert into ewwwwio_images table
function ewww_image_optimizer_update_table( $attachment, $opt_size, $orig_size, $original = '', $backup_hash = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_image;
	// first check if the image was converted, so we don't orphan records
	if ( $original && $original != $attachment ) {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $original );
		$converted = $original;
	} else {
		$already_optimized = ewww_image_optimizer_find_already_optimized( $attachment );
		if ( is_array( $already_optimized ) && ! empty( $already_optimized['converted'] ) ) {
			$converted = $already_optimized['converted'];
		} else {
			$converted = '';
		}
	}
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['updates'] ) && $opt_size >= $orig_size ) {
		$prev_string = ' - ' . __( 'Previously Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$prev_string = '';
	}
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['orig_size'] ) && $already_optimized['orig_size'] > $orig_size ) {
		$orig_size = $already_optimized['orig_size'];
	}
	ewwwio_debug_message( "savings: $opt_size (new) vs. $orig_size (orig)" );
	// calculate how much space was saved
	$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string );
	
	$updates = array(
		'path' => $attachment,
		'converted' => $converted,
		'image_size' => $opt_size,
		'results' => $results_msg,
		'updates' => 1,
		'backup' => preg_replace( '/[^\w]/', '', $backup_hash ),
	);
	if ( ! seems_utf8( $updates['path'] ) ) {
		$updates['path'] = utf8_encode( $updates['path'] );
	}
	// store info on the current image for future reference
	if ( empty( $already_optimized ) || ! is_array( $already_optimized ) ) {
		ewwwio_debug_message( "creating new record, path: $attachment, size: $opt_size" );
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->gallery ) $updates['gallery'] = $ewww_image->gallery;
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->attachment_id ) $updates['attachment_id'] = $ewww_image->attachment_id;
		if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->resize ) $updates['resize'] = $ewww_image->resize;
		$updates['orig_size'] = $orig_size;
		$updates['updated'] = date( 'Y-m-d H:i:s' );
		$ewwwdb->insert( $ewwwdb->ewwwio_images, $updates );
	} else {
		if ( is_array( $already_optimized ) && empty( $already_optimized['orig_size'] ) ) {
			$updates['orig_size'] = $orig_size;
		}
		ewwwio_debug_message( "updating existing record ({$already_optimized['id']}), path: $attachment, size: $opt_size" );
		if ( $already_optimized['updates'] ) {
			$updates['updates'] = $already_optimized['updates']++;
		}
		$updates['pending'] = 0;
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && $already_optimized['updates'] > 1 ) {
			$updates['trace'] = ewwwio_debug_backtrace();
		}
		if ( empty( $already_optimized['gallery'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->gallery ) {
			$updates['gallery'] = $ewww_image->gallery;
		}
		if ( empty( $already_optimized['attachment_id'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->attachment_id ) {
			$updates['attachment_id'] = $ewww_image->attachment_id;
		}
		if ( empty( $already_optimized['resize'] ) && is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image && $ewww_image->resize ) {
			$updates['resize'] = $ewww_image->resize;
		}
		ewwwio_debug_message( print_r( $updates, true ) );
		// store info on the current image for future reference
		$record_updated = $ewwwdb->update( $ewwwdb->ewwwio_images,
			$updates,
			array(
				'id' => $already_optimized['id'],
			)
		);
		if ( false === $record_updated ) {
			ewwwio_debug_message( "db error: " . print_r( $wpdb->last_error, true ) );
		} else {
			ewwwio_debug_message( "updated $record_updated records successfully" );
		}
	}
	ewwwio_memory( __FUNCTION__ );
	$ewwwdb->flush();
	ewwwio_memory( __FUNCTION__ );
	return $results_msg;
}

// returns a human-readable message based on the original and optimized sizes, taking into account the possibility that the image was previously optimized
function ewww_image_optimizer_image_results( $orig_size, $opt_size, $prev_string = '' ) {
	if ( $opt_size >= $orig_size ) {
		ewwwio_debug_message( "original and new file are same size (or something weird made the new one larger), no savings" );
		$results_msg = __( 'No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		// calculate how much space was saved
		$savings = intval( $orig_size ) - intval( $opt_size );
		// convert it to human readable format
		$savings_str = size_format( $savings, 1 );
		// replace spaces and extra decimals with proper html entity encoding
		$savings_str = preg_replace( '/\.0 B /', ' B', $savings_str );
		$savings_str = str_replace( ' ', '&nbsp;', $savings_str );
		// determine the percentage savings
		$percent = number_format_i18n( 100 - ( 100 * ( $opt_size / $orig_size ) ), 1 ) . '%';
		// use the percentage and the savings size to output a nice message to the user
		$results_msg = sprintf( __( 'Reduced by %1$s (%2$s)', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
			$percent,
			$savings_str
		) . $prev_string;
		ewwwio_debug_message( "original and new file are different size: $results_msg" );
	}
	return $results_msg;
}

// called to process each image in the loop for images outside of media library
function ewww_image_optimizer_aux_images_loop( $attachment = null, $auto = false, $cli = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_defer;
	$ewww_defer = false;
	$output = array();
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( empty( $_REQUEST['ewww_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		$output['error'] = esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		echo json_encode( $output );
		die();
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_wpnonce'] ) ) {
		// find out if our nonce is on it's last leg/tick
		$tick = wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' );
		if ( $tick === 2 ) {
			ewwwio_debug_message( 'nonce on its last leg' );
			$output['new_nonce'] = wp_create_nonce( 'ewww-image-optimizer-bulk' );
		} else {
			ewwwio_debug_message( 'nonce still alive and kicking' );
			$output['new_nonce'] = '';
		}
	}
	// retrieve the time when the optimizer starts
	$started = microtime( true );
	if ( ewww_image_optimizer_stl_check() && ini_get( 'max_execution_time' ) < 60 ) {
		set_time_limit( 0 );
	}
	// get the next image in the queue
	if ( empty( $attachment ) ) {
		list( $id, $attachment ) = $ewwwdb->get_row( "SELECT id,path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1", ARRAY_N );
	} else {
		$id = $attachment['id'];
		$attachment = $attachment['path'];
	}
	// do the optimization for the current image
	$results = ewww_image_optimizer( $attachment );
	if ( ! $results[0] && is_numeric( $id ) ) {
		$ewwwdb->delete(
			$ewwwdb->ewwwio_images,
			array(
				'id' => $id
			),
			array(
				'%d'
			)
		);
	}
	$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
	if ( ! empty ( $ewww_status ) && preg_match( '/exceeded/', $ewww_status ) ) {
		if ( ! $auto ) {
			$output['error'] = esc_html__( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			echo json_encode( $output );
		}
		if ( $cli ) {
			WP_CLI::error( __( 'License Exceeded', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
		}
		die();
	}
	// store the updated list of attachment IDs back in the 'bulk_attachments' option
//	update_option( 'ewww_image_optimizer_aux_attachments', $attachments, false );
	if ( ! $auto ) {
		// output the path
		$output['results'] = sprintf( "<p>" . esc_html__( 'Optimized', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <strong>%s</strong><br>", esc_html( $attachment ) );
		// tell the user what the results were for the original image
		$output['results'] .= sprintf( "%s<br>", $results[1] );
		// calculate how much time has elapsed since we started
		$elapsed = microtime( true ) - $started;
		// output how much time has elapsed since we started
		$output['results'] .= sprintf( esc_html__( 'Elapsed: %.3f seconds', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>", $elapsed );
		if ( get_site_option( 'ewww_image_optimizer_debug' ) ) {
			global $ewww_debug;
			$output['results'] .= '<div style="background-color:#ffff99;">' . $ewww_debug . '</div>';
		}
		$next_file = $wpdb->get_var( "SELECT path FROM $wpdb->ewwwio_images WHERE pending=1 LIMIT 1" );
		if ( ! empty( $next_file ) ) {
//			$next_file = array_shift( $attachments );
			$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
			$output['next_file'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' <b>' . esc_html( $next_file ) . "</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		}
		echo json_encode( $output );
		ewwwio_memory( __FUNCTION__ );
		die();
	}
	if ( $cli ) {
		return $results[1];
	}
	ewwwio_memory( __FUNCTION__ );
}

// processes metadata and looks for any webp version to insert in the meta
function ewww_image_optimizer_update_attachment_metadata( $meta, $ID ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( "attachment id: $ID" );
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $ID );
	// don't do anything else if the attachment path can't be retrieved
	if ( ! is_file( $file_path ) ) {
		ewwwio_debug_message( "could not retrieve path" );
		return $meta;
	}
	ewwwio_debug_message( "retrieved file path: $file_path" );
	if ( is_file( $file_path . '.webp' ) ) {
		$meta['sizes']['webp-full'] = array(
			'file' => pathinfo( $file_path, PATHINFO_BASENAME ) . '.webp',
			'width' => 0,
			'height' => 0,
			'mime-type' => 'image/webp',
		);
		
	}
	// resized versions, so we can continue
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		ewwwio_debug_message( 'processing resizes for webp updates' );
		// meta sizes don't contain a path, so we use the foldername from the original to generate one
		$base_dir = trailingslashit( dirname( $file_path ) );
		// process each resized version
		$processed = array();
		foreach( $meta['sizes'] as $size => $data ) {
			if ( empty( $data['file'] ) ) {
				continue;
			}
			$resize_path = $base_dir . $data['file'];
			// update the webp paths
			if ( is_file( $resize_path . '.webp' ) ) {
				$meta['sizes'][ 'webp-' . $size ] = array(
					'file' => $data['file'] . '.webp',
					'width' => 0,
					'height' => 0,
					'mime-type' => 'image/webp',
				);
			}
		}
	}
	ewwwio_memory( __FUNCTION__ );
	// send back the updated metadata
	return $meta;
}

// looks for a retina version of the original file so that we can optimize that too
function ewww_image_optimizer_hidpi_optimize( $orig_path, $return_path = false, $validate_file = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
	$pathinfo = pathinfo( $orig_path );
	if ( empty( $pathinfo['dirname'] ) || empty( $pathinfo['filename'] ) || empty( $pathinfo['extension'] ) ) {
		return;
	}
	$hidpi_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . $hidpi_suffix . '.' . $pathinfo['extension'];
	if ( $validate_file && ! file_exists( $hidpi_path ) ) {
		return;
	}
	if ( $return_path ) {
		ewwwio_debug_message( "found retina at $hidpi_path" );
		return $hidpi_path;
	}
	global $ewww_image;
	if ( is_object( $ewww_image ) && $ewww_image instanceof EWWW_Image ) {
		$ID = $ewww_image->attachment_id;
		$gallery = 'media';
		$size = $ewww_image->resize;
	} else {
		$ID = 0;
		$gallery = '';
		$size = null;
	}
	global $ewww_image;
	$ewww_image = new EWWW_Image( $ID, $gallery, $hidpi_path );
	$ewww_image->resize = $size . '-retina';
	ewww_image_optimizer( $hidpi_path );
}

function ewww_image_optimizer_remote_fetch( $id, $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! function_exists( 'download_url' ) ) {
		require_once( ABSPATH . '/wp-admin/includes/file.php' );
	}
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		global $as3cf;
		$full_url = get_attached_file( $id );
		if ( strpos( $full_url, 's3' ) === 0 ) {
			$full_url = $as3cf->get_attachment_url( $id, null, null, $meta );
		}
		$filename = get_attached_file( $id, true );
		ewwwio_debug_message( "amazon s3 fullsize url: $full_url" );
		ewwwio_debug_message( "unfiltered fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			rename( $temp_file, $filename );
		}
		// resized versions, so we'll grab those too
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			ewwwio_debug_message( 'retrieving resizes' );
			// meta sizes don't contain a path, so we calculate one
			$base_dir = trailingslashit( dirname( $filename) );
			// process each resized version
			$processed = array();
			foreach( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match( '/webp/', $size ) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[$size] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				// initialize $dup_size
				$dup_size = false;
				// check through all the sizes we've processed so far
				foreach ( $processed as $proc => $scan ) {
					// if a previous resize had identical dimensions
					if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
						// found a duplicate resize
						$dup_size = true;
					}
				}
				// if this is a unique size
				if ( ! $dup_size ) {
					$resize_path = $base_dir . $data['file'];
					$resize_url = $as3cf->get_attachment_url( $id, null, $size, $meta );
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						rename( $temp_file, $resize_path );
					}
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}
		}
	}
	if ( class_exists( 'WindowsAzureStorageUtil' ) && get_option( 'azure_storage_use_for_default_upload' ) ) {
		$full_url = $meta['url'];
		$filename = $meta['file'];
		ewwwio_debug_message( "azure fullsize url: $full_url" );
		ewwwio_debug_message( "fullsize path: $filename" );
		$temp_file = download_url( $full_url );
		if ( ! is_wp_error( $temp_file ) ) {
			rename( $temp_file, $filename );
		}
		// resized versions, so we'll grab those too
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			ewwwio_debug_message( 'retrieving resizes' );
			// meta sizes don't contain a path, so we calculate one
			$base_dir = trailingslashit( dirname( $filename) );
			$base_url = trailingslashit( dirname( $full_url ) );
			// process each resized version
			$processed = array();
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "processing size: $size" );
				if ( preg_match('/webp/', $size) ) {
					continue;
				}
				if ( ! empty( $disabled_sizes[$size] ) ) {
					continue;
				}
				if ( empty( $data['file'] ) ) {
					continue;
				}
				// initialize $dup_size
				$dup_size = false;
				// check through all the sizes we've processed so far
				foreach( $processed as $proc => $scan ) {
					// if a previous resize had identical dimensions
					if ($scan['height'] == $data['height'] && $scan['width'] == $data['width']) {
						// found a duplicate resize
						$dup_size = true;
					}
				}
				// if this is a unique size
				if ( ! $dup_size ) {
					$resize_path = $base_dir . $data['file'];
					$resize_url = $base_url . $data['file'];
					ewwwio_debug_message( "fetching $resize_url to $resize_path" );
					$temp_file = download_url( $resize_url );
					if ( ! is_wp_error( $temp_file ) ) {
						rename( $temp_file, $resize_path );
					}
				}
				// store info on the sizes we've processed, so we can check the list for duplicate sizes
				$processed[$size]['width'] = $data['width'];
				$processed[$size]['height'] = $data['height'];
			}
		}
	}
	if ( ! empty( $filename ) && file_exists( $filename ) ) {
		return $filename;
	} else {
		return false;
	}
}

function ewww_image_optimizer_check_table_as3cf( $meta, $ID, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$local_path = get_attached_file( $ID, true );
	ewwwio_debug_message( "unfiltered local path: $local_path" );
	if ( $local_path !== $s3_path ) {
		ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path );
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		ewwwio_debug_message( 'updating s3 resizes' );
		// meta sizes don't contain a path, so we calculate one
		$local_dir = trailingslashit( dirname( $local_path ) );
		$s3_dir = trailingslashit( dirname( $s3_path ) );
		// process each resized version
		$processed = array();
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( strpos( $size, 'webp') === 0 ) {
				continue;
			}
			// check through all the sizes we've processed so far
			foreach ( $processed as $proc => $scan ) {
				// if a previous resize had identical dimensions
				if ( $scan['height'] === $data['height'] && $scan['width'] === $data['width'] ) {
					// found a duplicate resize
					continue;
				}
			}
			// if this is a unique size
			$local_resize_path = $local_dir . $data['file'];
			$s3_resize_path = $s3_dir . $data['file'];
			if ( $local_resize_path !== $s3_resize_path ) {
				ewww_image_optimizer_update_table_as3cf( $local_resize_path, $s3_resize_path );
			}
			// store info on the sizes we've processed, so we can check the list for duplicate sizes
			$processed[ $size ]['width'] = $data['width'];
			$processed[ $size ]['height'] = $data['height'];
		}
	}
	global $wpdb;
	$wpdb->flush();
}

function ewww_image_optimizer_update_table_as3cf( $local_path, $s3_path ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// first we need to see if anything matches the s3 path
	$s3_image = ewww_image_optimizer_find_already_optimized( $s3_path );
	ewwwio_debug_message( "looking for $s3_path" );
	if ( is_array( $s3_image ) ) {
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		ewwwio_debug_message( "found $s3_path in db" );
		// when we find a match by the s3 path, we need to find out if there are already records for the local path
		$found_local_image = ewww_image_optimizer_find_already_optimized( $local_path );
		ewwwio_debug_message( "looking for $local_path" );
		// if we found records for both local and s3 paths, we delete the s3 record, but store the original size in the local record
		if ( ! empty( $found_local_image ) && is_array( $found_local_image ) ) {
			ewwwio_debug_message( "found $local_path in db" );
			$ewwwdb->delete( $ewwwdb->ewwwio_images,
				array(
					'id' => $s3_image['id'],
				),
				array(
					'%d'
				)
			);
			if ( $s3_image['orig_size'] > $found_local_image['orig_size'] ) {
				$ewwwdb->update( $ewwwdb->ewwwio_images,
					array(
						'orig_size' => $s3_image['orig_size'],
						'results' => $s3_image['results'],
					),
					array(
						'id' => $found_local_image['id'],
					)
				);
			}
		// if we just found an s3 path and no local match, then we just update the path in the table to the local path
		} else {
			ewwwio_debug_message( "just updating s3 to local" );
			$ewwwdb->update( $ewwwdb->ewwwio_images,
				array(
					'path' => $local_path,
				),
				array(
					'id' => $s3_image['id'],
				)
			);
		}
	}
}	

// resizes Media Library uploads based on the maximum dimensions specified by the user
function ewww_image_optimizer_resize_upload( $file ) {
	// parts adapted from Imsanity (THANKS Jason!)
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! $file ) {
		return false;
	}
//	ewwwio_debug_message( print_r( $_POST, true ) );
	if ( ! empty( $_REQUEST['post_id'] ) || ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] === 'upload-attachment' ) || ( ! empty( $_SERVER['HTTP_REFERER'] ) && strpos( $_SERVER['HTTP_REFERER'], 'media-new.php' ) ) ) {
		$maxwidth = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' );
		ewwwio_debug_message( 'resizing image from media library or attached to post' );
	} else {
		$maxwidth = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' );
		$maxheight = ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' );
		ewwwio_debug_message( 'resizing images from somewhere else' );
	}

	// allow other developers to modify the dimensions to their liking based on whatever parameters they might choose
	list( $maxwidth, $maxheight ) = apply_filters( 'ewww_image_optimizer_resize_dimensions', array( $maxwidth, $maxheight ) );

	//check that options are not == 0
	if ( $maxwidth == 0 && $maxheight == 0 ) {
		return false;
	}
	//check file type
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( strpos( $type, 'image' ) === false ) {
		ewwwio_debug_message( 'not an image, cannot resize' );
		return false;
	}
	//check file size (dimensions)
	list( $oldwidth, $oldheight ) = getimagesize( $file );
	if ( $oldwidth <= $maxwidth && $oldheight <= $maxheight ) {
		ewwwio_debug_message( 'image too small for resizing' );
		return false;
	}
	list( $newwidth, $newheight ) = wp_constrain_dimensions( $oldwidth, $oldheight, $maxwidth, $maxheight );
	if ( ! function_exists( 'wp_get_image_editor' ) ) {
		ewwwio_debug_message( 'no image editor function' );
		return false;
	}
	remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		ewwwio_debug_message( 'could not get image editor' );
		return false;
	}
	if ( function_exists( 'exif_read_data' ) && $type === 'image/jpeg' ) {
		$exif = @exif_read_data( $file );
		if ( is_array( $exif ) && array_key_exists( 'Orientation', $exif ) ) {
			$orientation = $exif['Orientation'];
			switch( $orientation ) {
				case 3:
					$editor->rotate( 180 );
					break;
				case 6:
					$editor->rotate( -90 );
					break;
				case 8:
					$editor->rotate( 90 );
					break;
			}
		}
	}
	$resized_image = $editor->resize( $newwidth, $newheight );
	if ( is_wp_error( $resized_image ) ) {
		ewwwio_debug_message( 'error during resizing' );
		return false;
	}
	$new_file = $editor->generate_filename( 'tmp' );
	$orig_size = filesize( $file );
	ewwwio_debug_message( "before resizing: $orig_size" );
	$saved = $editor->save( $new_file );
	if ( is_wp_error( $saved ) ) {
		ewwwio_debug_message( 'error saving resized image' );
	}
	add_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
	$new_size = ewww_image_optimizer_filesize( $new_file );
	if ( $new_size && $new_size < $orig_size ) {
/*		global $wr2x_admin;
		if ( is_object( $wr2x_admin ) && method_exists( $wr2x_admin, 'is_pro' ) && $wr2x_admin->is_pro() ) {
		// generate a retina file from the full original if they have WP Retina 2x Pro
//		if ( function_exists( 'wr2x_is_pro' ) && wr2x_is_pro() ) {
			//$full_size_needed = get_option( 'wr2x_full_size' );
			if ( get_option( 'wr2x_full_size' ) ) {
				// Is the file related to this size there?
				$retina_file = '';
	
				$pathinfo = pathinfo( $file ) ;
				$retina_extension = function_exists( 'wr2x_retina_extension' ) ? wr2x_retina_extension() : '@2x';
				$retina_file = trailingslashit( $pathinfo['dirname'] ) . $pathinfo['filename'] . $retina_extension . $pathinfo['extension'];
	
				if ( $retina_file && ! file_exists( $retina_file ) && wr2x_are_dimensions_ok( $oldwidth, $oldheight, $newwidth * 2, $newheight * 2 ) ) {
					$image = wr2x_vt_resize( $file, $newwidth * 2, $newheight * 2, false, $retina_file );
				}
			}
		}*/
		// Use this action to perform any operations on the original file before it is overwritten with the new, smaller file
		do_action( 'ewww_image_optimizer_image_resized', $file, $new_file );
		rename( $new_file, $file );
		// store info on the current image for future reference
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
		// if the original file has never been optimized, then just update the record that was created with the proper filename (because the resized file has usually been optimized)
		if ( empty( $already_optimized ) ) {
			$tmp_exists = $ewwwdb->update( $ewwwdb->ewwwio_images,
				array(
					'path' => $file,
					'orig_size' => $orig_size,
				),
				array(
					'path' => $new_file,
				)
			);
			// if the tmp file didn't get optimized (and it shouldn't), then just insert a dummy record to be updated shortly
			if ( ! $tmp_exists ) {
				$ewwwdb->insert( $ewwwdb->ewwwio_images, array(
					'path' => $file,
					'orig_size' => $orig_size,
				) );
			}
		// otherwise, we delete the record created from optimizing the resized file
		} else {
			$temp_optimized = ewww_image_optimizer_find_already_optimized( $new_file );
			if ( is_array( $temp_optimized ) && ! empty( $temp_optimized['id'] ) ) {
				$ewwwdb->delete( $ewwwdb->ewwwio_images,
					array(
						'id' => $temp_optimized['id'],
					),
					array(
						'%d',
					)
				);
			}
		}
		return array( $newwidth, $newheight );
	}
	if ( file_exists( $new_file ) ) {
		ewwwio_debug_message( "resizing did not create a smaller image: $new_size" );
		unlink( $new_file );
	}
	return false;
}

function ewww_image_optimizer_find_already_optimized( $attachment ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$maybe_return_image = false;
	$query = $ewwwdb->prepare( "SELECT * FROM $ewwwdb->ewwwio_images WHERE path = %s", $attachment );
	$optimized_query = $ewwwdb->get_results( $query, ARRAY_A );
	if ( ewww_image_optimizer_iterable( $optimized_query ) ) {
		foreach ( $optimized_query as $image ) {
			if ( $image['path'] != $attachment ) {
				ewwwio_debug_message( "{$image['path']} does not match $attachment, continuing our search" );
			} elseif ( ! $maybe_return_image ) {
				ewwwio_debug_message( "found a match for $attachment" );
				$maybe_return_image = $image;
			//	return $image;
			} else {
				if ( empty( $duplicates ) ) {
					$duplicates = array( $maybe_return_image, $image );
				} else {
					$duplicates[] = $image;
				}
			}
		}
	}
	// do something with duplicates
/*	if ( ! empty( $duplicates ) && is_array( $duplicates ) ) {
		$keeper = ewww_image_optimizer_remove_duplicate_records( $duplicates );
		if ( ! empty( $keeper ) && is_array( $keeper ) ) {
			$maybe_return_image = $keeper;
		}
	}*/
	return $maybe_return_image;
	//return false;
}

function ewww_image_optimizer_remove_duplicate_records( $duplicates ) {
	if ( empty( $duplicates ) ) {
		return false;
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	if ( ! is_array( $duplicates[0] ) ) {
		// retrieve records for the ID #s passed
		$duplicate_ids = implode( ',', array_map( 'intval', $duplicates ) );
		$duplicates = $ewwwdb->get_results( "SELECT * FROM $ewwwdb->ewwwio_images WHERE id IN ($duplicate_ids)" ); 
	}
	if ( ! is_array( $duplicates ) || ! is_array( $duplicates[0] ) ) {
		return false;
	}
	$image_size = ewww_image_optimizer_filesize( $duplicates[0]['path'] );
	$discard = array();
	// first look for an image size match
	foreach ( $duplicates as $duplicate ) {
		if ( empty( $keeper ) && ! empty( $duplicate['image_size'] ) && $image_size == $duplicate['image_size'] ) {
			$keeper = $duplicate;
		} else {
			$discard[] = $duplicate;
		}
	}
	if ( empty( $keeper ) ) {
		foreach ( $duplicates as $duplicate ) {
			if ( empty( $keeper ) && ! empty( $duplicate['image_size'] ) && $image_size == $duplicate['image_size'] ) {
				$keeper = $duplicate;
			} else {
				$discard[] = $duplicate;
			}
		}
	}
}

// WAS used only for background WP_Image_Editor requests, not for processing uploaded image attachments, that one uses the wpsf_location_lock function directly
function ewww_image_optimizer_test_background_opt( $type = '' ) {
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		return false;
	}
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		return false;
	}
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return false;
	}
	if ( $type == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( $type == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	if ( $type == 'image/gif' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return apply_filters( 'ewww_image_optimizer_defer_conversion', false );
	}
	global $ewww_defer;
	return (bool) apply_filters( 'ewww_image_optimizer_background_optimization', $ewww_defer );
}

function ewww_image_optimizer_test_parallel_opt( $type = '', $id = 0 ) {
	if ( defined( 'EWWW_DISABLE_ASYNC' ) && EWWW_DISABLE_ASYNC ) {
		return false;
	}
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		return false;
	}
	if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_parallel_optimization' ) ) {
		return false;
	}
	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_background_optimization' ) ) {
		return false;
	}
	if ( empty( $id ) ) {
		return true;
	}
	if ( ! empty( $_REQUEST['ewww_convert'] ) ) {
		return false;
	}
	if ( empty( $type ) ) {
		$type = get_post_mime_type( $id );
	}
	if ( $type == 'image/jpeg' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) {
		return false;
	}
	if ( $type == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) ) {
		return false;
	}
	if ( $type == 'image/gif' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) ) {
		return false;
	}
	if ( $type == 'application/pdf' ) {
		return false;
	}
	return true;
}

function ewww_image_optimizer_rebuild_meta( $attachment_id ) {
	$file = get_attached_file( $attachment_id );
	if ( file_exists( $file ) ) {
		remove_filter( 'wp_image_editors', 'ewww_image_optimizer_load_editor', 60 );
		remove_all_filters( 'wp_generate_attachment_metadata' );
		ewwwio_debug_message( "generating new meta for $attachment_id" );
		$meta = wp_generate_attachment_metadata( $attachment_id, $file );
		ewwwio_debug_message( "generated new meta for $attachment_id" );
		$updated = update_post_meta( $attachment_id, '_wp_attachment_metadata', $meta );
		if ( $updated ) {
			ewwwio_debug_message( "updated meta for $attachment_id" );
		} else {
			ewwwio_debug_message( "failed meta update for $attachment_id" );
		}
		return $meta;
	}
}

/**
 * Read the image paths from an attachment's meta data and process each image
 * with ewww_image_optimizer().
 *
 * This method also adds a `ewww_image_optimizer` meta key for use in the media library 
 * and may add a 'converted' and 'orig_file' key if conversion is enabled.
 *
 * Called after `wp_generate_attachment_metadata` is completed.
 */
function ewww_image_optimizer_resize_from_meta_data( $meta, $ID = null, $log = true, $background_new = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! is_array( $meta ) && empty( $meta ) ) {
		$meta = array();
	} elseif ( ! is_array( $meta ) ) {
		if ( is_string( $meta )  && is_numeric( $ID ) && 'processing' == $meta ) {
			ewwwio_debug_message( "attempting to rebuild attachment meta for $ID" );
			$new_meta = ewww_image_optimizer_rebuild_meta( $ID );
			if ( ! is_array( $new_meta ) ) {
				ewwwio_debug_message( 'attempt to rebuild attachment meta failed' );
				return $meta;
			} else {
				$meta = $new_meta;
			}
		} else {
			ewwwio_debug_message( 'attachment meta is not a usable array' );
			return $meta;
		}
	} elseif ( is_array( $meta ) && ! empty( $meta[0] ) && 'processing' == $meta[0] ) {
		ewwwio_debug_message( "attempting to rebuild attachment meta for $ID" );
		$new_meta = ewww_image_optimizer_rebuild_meta( $ID );
		if ( ! is_array( $new_meta ) ) {
			ewwwio_debug_message( 'attempt to rebuild attachment meta failed' );
			return $meta;
		} else {
			$meta = $new_meta;
		}
	}
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	global $ewww_defer;
	global $ewww_new_image;
	global $ewww_image;
	$gallery_type = 1;
	ewwwio_debug_message( "attachment id: $ID" );
	
	session_write_close();
	if ( ! empty( $ewww_new_image ) ) {
		ewwwio_debug_message( 'this is a newly uploaded image with no metadata yet' );
		$new_image = true;
	} else {
		ewwwio_debug_message( 'this image already has metadata, so it is not new' );
		$new_image = false;
	}
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $ID );
	// if the attachment has been uploaded via the image store plugin
	if ( 'ims_image' == get_post_type( $ID ) ) {
		$gallery_type = 6;
	}
	if ( ! $new_image && class_exists( 'Amazon_S3_And_CloudFront' ) && strpos( $file_path, 's3' ) === 0 ) {
		ewww_image_optimizer_check_table_as3cf( $meta, $ID, $file_path );
	}
	// if the local file is missing and we have valid metadata, see if we can fetch via CDN
	if ( ! is_file( $file_path ) || strpos( $file_path, 's3' ) === 0 ) {
		$file_path = ewww_image_optimizer_remote_fetch( $ID, $meta );
		if ( ! $file_path ) {
			ewwwio_debug_message( 'could not retrieve path' );
			//$meta['ewww_image_optimizer'] = __( 'Could not find image', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			return $meta;
		}
	}
	ewwwio_debug_message( "retrieved file path: $file_path" );
	$type = ewww_image_optimizer_mimetype( $file_path, 'i' );
	$supported_types = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'application/pdf',
	);
	if ( ! in_array( $type, $supported_types ) ) {
		ewwwio_debug_message( "mimetype not supported: $ID" );
		return $meta;
	}
	$fullsize_size = ewww_image_optimizer_filesize( $file_path );
	// see if this is a new image and Imsanity resized it (which means it could be already optimized)
	if ( ! empty( $new_image ) && function_exists( 'imsanity_get_max_width_height' ) && strpos( $type, 'image' ) !== false ) {
		list( $maxW, $maxH ) = imsanity_get_max_width_height( IMSANITY_SOURCE_LIBRARY );
		list( $oldW, $oldH ) = getimagesize( $file_path );
		list( $newW, $newH ) = wp_constrain_dimensions( $oldW, $oldH, $maxW, $maxH );
		$path_parts = pathinfo( $file_path );
		$imsanity_path = trailingslashit( $path_parts['dirname'] ) . $path_parts['filename'] . '-' . $newW . 'x' . $newH . '.' . $path_parts['extension'];
		ewwwio_debug_message( "imsanity path: $imsanity_path" );
		$image_size = ewww_image_optimizer_filesize( $file_path );
		$already_optimized = ewww_image_optimizer_find_already_optimized( $imsanity_path );
		if ( is_array( $already_optimized ) ) {
			ewwwio_debug_message( "updating existing record, path: $file_path, size: " . $image_size );
			// store info on the current image for future reference
			$ewwwdb->update( $ewwwdb->ewwwio_images,
				array(
					'path' => $file_path,
					'attachment_id' => $ID,
					'resize' => 'full',
					'gallery' => 'media',
				),
				array(
					'id' => $already_optimized['id'],
				));
		}
	}
	// resize here so long as this is not a new image AND resize existing is enabled, and imsanity isn't enabled with a max size
	if ( ( empty( $new_image ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		$new_dimensions = ewww_image_optimizer_resize_upload( $file_path );
		if ( is_array( $new_dimensions ) ) {
			$meta['width'] = $new_dimensions[0];
			$meta['height'] = $new_dimensions[1];
		}
	}
	if ( ewww_image_optimizer_test_background_opt( $type ) ) {
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		global $ewwwio_media_background;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_media_background ) ) {
			$ewwwio_media_background = new EWWWIO_Media_Background_Process();
		}
		ewwwio_debug_message( "backgrounding optimization for $ID" );
		$ewwwio_media_background->push_to_queue( array(
			'id' => $ID,
			'new' => $new_image,
			'type' => $type,
		) );
		$ewwwio_media_background->save()->dispatch();
		set_transient( 'ewwwio-background-in-progress-' . $ID, true, 24 * HOUR_IN_SECONDS );
		if ( $log ) {
			ewww_image_optimizer_debug_log();
		}
		return $meta;
	}
	if ( $background_new ) {
		$new_image = true;
	}
	// resize here if the user has used the filter to defer resizing, we have a new image OR resize existing is enabled, and imsanity isn't enabled with a max size
	if ( apply_filters( 'ewww_image_optimizer_defer_resizing', false ) && ( ! empty( $new_image ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) ) && ! function_exists( 'imsanity_get_max_width_height' ) ) {
		$new_dimensions = ewww_image_optimizer_resize_upload( $file_path );
		if ( is_array( $new_dimensions ) ) {
			$meta['width'] = $new_dimensions[0];
			$meta['height'] = $new_dimensions[1];
		}
	}
	// this gets a bit long, so here goes:
	// we run in parallel if we didn't detect breakage (test_parallel_opt), and there are enough resizes to make it worthwhile (or if the API is enabled)
	if ( ewww_image_optimizer_test_parallel_opt( $type, $ID ) && isset( $meta['sizes'] ) && ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) || count( $meta['sizes'] ) > 5 ) ) {
		ewwwio_debug_message( 'running in parallel' );
		$parallel_opt = true;
	} else {
		ewwwio_debug_message( 'running in sequence' );
		$parallel_opt = false;
	}
	$parallel_sizes = array();
	if ( $parallel_opt ) {
		add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
		$parallel_sizes['full'] = $file_path;
		global $ewwwio_async_optimize_media;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_optimize_media ) ) {
			$ewwwio_async_optimize_media = new EWWWIO_Async_Request();
		}
	} else {
		$ewww_image = new EWWW_Image( $ID, 'media', $file_path );
		$ewww_image->resize = 'full';
		list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, $gallery_type, false, $new_image, true );

		if ( $file === false ) {
			return $meta;
		}
		// if the file was converted
		if ( $conv !== false ) {
			if ( $conv ) {
				$ewww_image->increment = $conv;
			}
			$ewww_image->file = $file;
			$ewww_image->converted = $original;
			$meta['file'] = trailingslashit( dirname( $meta['file'] ) ) . basename( $file );
			$ewww_image->update_converted_attachment( $meta );
			$meta = $ewww_image->convert_sizes( $meta );
			ewwwio_debug_message( 'image was converted' );
		} else {
			remove_filter( 'wp_update_attachment_metadata', 'ewww_image_optimizer_update_attachment', 10 );
		}
		ewww_image_optimizer_hidpi_optimize( $file );
	}
	// resized versions, so we can continue
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
		ewwwio_debug_message( 'processing resizes' );
		// meta sizes don't contain a path, so we calculate one
		if ( $gallery_type === 6 ) {
			$base_ims_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
		}
		$base_dir = trailingslashit( dirname( $file_path ) );
		// process each resized version
		$processed = array();
		foreach ( $meta['sizes'] as $size => $data ) {
			ewwwio_debug_message( "processing size: $size" );
			if ( strpos( $size, 'webp' ) === 0 ) {
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
			if ( $gallery_type === 6 ) {
				//we reset base_dir, because base_dir potentially gets overwritten with base_ims_dir
				$base_dir = trailingslashit( dirname( $file_path ) );
				$image_path = $base_dir . $data['file'];
				$ims_path = $base_ims_dir . $data['file'];
				if ( file_exists( $ims_path ) ) {
					ewwwio_debug_message( 'ims resize already exists, wahoo' );
					ewwwio_debug_message( "ims path: $ims_path" );
					$image_size = ewww_image_optimizer_filesize( $ims_path );
					$already_optimized = ewww_image_optimizer_find_already_optimized( $image_path );
					if ( is_array( $already_optimized ) ) {
						ewwwio_debug_message( "updating existing record, path: $ims_path, size: " . $image_size );
						// store info on the current image for future reference
						$ewwwdb->update( $ewwwdb->ewwwio_images,
							array(
								'path' => $ims_path,
							),
							array(
								'id' => $already_optimized['id'],
							));
					}
					$base_dir = $base_ims_dir;
				}
			}
			// check through all the sizes we've processed so far
			foreach ( $processed as $proc => $scan ) {
				// if a previous resize had identical dimensions
				if ( $scan['height'] == $data['height'] && $scan['width'] == $data['width'] ) {
					// found a duplicate resize
					// point this resize at the same image as the previous one
					$meta['sizes'][ $size ]['file'] = $meta['sizes'][ $proc ]['file'];
					continue( 2 );
				}
			}
			// if this is a unique size
			$resize_path = $base_dir . $data['file'];
			if ( $type == 'application/pdf' && $size == 'full' ) {
				$size = 'pdf-full';
				ewwwio_debug_message( 'processing full size pdf preview' );
			}
			// run the optimization and store the results
			if ( $parallel_opt && file_exists( $resize_path ) ) {
				$parallel_sizes[ $size ] = $resize_path;
			} else {
				$ewww_image = new EWWW_Image( $ID, 'media', $resize_path );
				$ewww_image->resize = $size;
				list( $optimized_file, $results, $resize_conv, $original ) = ewww_image_optimizer( $resize_path ); //, $gallery_type, $conv, $new_image );
			}
			// optimize retina images, if they exist
			if ( function_exists( 'wr2x_get_retina' ) ) {
				$retina_path = wr2x_get_retina( $resize_path );
			} else {
				$retina_path = false;
			}
			if ( $retina_path && is_file( $retina_path ) ) {
				if ( $parallel_opt ) {
					$async_path = str_replace( $upload_path, '', $retina_path );
					$ewwwio_async_optimize_media->data( array( 'ewwwio_id' => $ID, 'ewwwio_path' => $async_path, 'ewwwio_size' => '', 'ewww_force' => $force ) )->dispatch();
				} else {
					$ewww_image = new EWWW_Image( $ID, 'media', $retina_path );
					$ewww_image->resize = $size . '-retina';
					ewww_image_optimizer( $retina_path );
				}
			} elseif ( ! $parallel_opt ) {
				ewww_image_optimizer_hidpi_optimize( $resize_path );
			}
			// store info on the sizes we've processed, so we can check the list for duplicate sizes
			$processed[ $size ]['width'] = $data['width'];
			$processed[ $size ]['height'] = $data['height'];
		}
	}

	// process size from a custom theme
	if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
		$imagemeta_resize_pathinfo = pathinfo( $file_path );
		$imagemeta_resize_path = '';
		foreach ( $meta['image_meta']['resized_images'] as $imagemeta_resize ) {
			$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
			if ( $parallel_opt && file_exists( $imagemeta_resize_path ) ) {
				$async_path = str_replace( $upload_path, '', $imagemeta_resize_path );
				$ewwwio_async_optimize_media->data( array( 'ewwwio_id' => $ID, 'ewwwio_path' => $async_path, 'ewwwio_size' => '', 'ewww_force' => $force ) )->dispatch();
			} else {
				$ewww_image = new EWWW_Image( $ID, 'media', $imagemeta_resize_path );
				ewww_image_optimizer( $imagemeta_resize_path );
			}
		}
		
	}

	// and another custom theme
	if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
		$custom_sizes_pathinfo = pathinfo( $file_path );
		$custom_size_path = '';
		foreach ( $meta['custom_sizes'] as $custom_size ) {
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
			if ( $parallel_opt && file_exists( $custom_size_path ) ) {
				$async_path = str_replace( $upload_path, '', $custom_size_path );
				$ewwwio_async_optimize_media->data( array( 'ewwwio_id' => $ID, 'ewwwio_path' => $async_path, 'ewwwio_size' => '', 'ewww_force' => $force ) )->dispatch();
			} else {
				$ewww_image = new EWWW_Image( $ID, 'media', $custom_size_path );
				ewww_image_optimizer( $custom_size_path );
			}
		}
	}

	if ( $parallel_opt && count( $parallel_sizes ) > 0 ) {
		$max_threads = (int) apply_filters( 'ewww_image_optimizer_max_parallel_threads', 5 );
		$processing = true;
		$timer = (int) apply_filters( 'ewww_image_optimizer_background_timer_init', 1 );
		$increment = (int) apply_filters( 'ewww_image_optimizer_background_timer_increment', 1 );
		$timer_max = (int) apply_filters( 'ewww_image_optimizer_background_timer_max', 20 );
		$processing_sizes = array();
		if ( ! empty( $_REQUEST['ewww_force'] ) ) {
			$force = true;
		} else {
			$force = false;
		}
		global $ewwwio_async_optimize_media;
		if ( ! class_exists( 'WP_Background_Process' ) ) {
			require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
		}
		if ( ! is_object( $ewwwio_async_optimize_media ) ) {
			$ewwwio_async_optimize_media = new EWWWIO_Async_Request();
		}
		while ( $parallel_opt && count( $parallel_sizes ) > 0 ) {
			$threads = $max_threads;
			ewwwio_debug_message( 'sizes left to queue: ' . count( $parallel_sizes ) );
			// phase 1, add $max_threads items to the queue and dispatch
			foreach ( $parallel_sizes as $size => $filename ) {
				if ( $threads < 1 ) {
					continue;
				}
				if ( ! file_exists( $filename ) ) {
					unset( $parallel_sizes[ $size ] );
					continue;
				}
				ewwwio_debug_message( "queueing size $size - $filename" );
				$processing_sizes[ $size ] = $filename;
				unset( $parallel_sizes[ $size ] );
				touch( $filename . '.processing' );
				$async_path = str_replace( $upload_path, '', $filename );
				ewwwio_debug_message( "sending off $async_path in folder $upload_path" );
				$ewwwio_async_optimize_media->data( array( 'ewwwio_id' => $ID, 'ewwwio_path' => $async_path, 'ewwwio_size' => $size, 'ewww_force' => $force ) )->dispatch();
				$threads--;
				ewwwio_debug_message( 'sizes left to queue: ' . count( $parallel_sizes ) );
				$processing = true;
			}
			// phase 2, we start checking to see what sizes are done, and populate the metadata with the results
			while ( $parallel_opt && $processing ) {
				$processing = false;
				foreach ( $processing_sizes as $size => $filename ) {
					if ( is_file( $filename . '.processing' ) ) {
						ewwwio_debug_message( "still processing $size" );
						$processing = true;
						continue;
					}
					//if ( $size == 'full' ) {
						//$image = ewww_image_optimizer_find_already_optimized( $filename );
					//	$meta['ewww_image_optimizer'] = $image['results'];
						//unset( $processing_sizes[ $size ] );
						//ewwwio_debug_message( "got results for $size size" );
					//} else {
						$image = ewww_image_optimizer_find_already_optimized( $filename );
					//	$meta['sizes'][ $size ]['ewww_image_optimizer'] = $image['results'];
						unset( $processing_sizes[ $size ] );
						ewwwio_debug_message( "got results for $size size" );
					//}
				}
				if ( $processing ) {
					ewwwio_debug_message( "sleeping for $timer seconds" );
					sleep( $timer );
					$timer += $increment;
					clearstatcache();
				}
				if ( $timer > $timer_max ) {
					break;
				}
				if ( $log ) {
					ewww_image_optimizer_debug_log();
				}
			}
			if ( $timer > $timer_max ) {
				foreach ( $processing_sizes as $filename ) {
					if ( is_file( $filename . '.processing' ) ) {
						unlink( $filename . '.processing' );
					}
				}
				$meta['processing'] = 1;
				if ( $log ) {
					ewww_image_optimizer_debug_log();
				}
				return $meta;
			}
			if ( $log ) {
				ewww_image_optimizer_debug_log();
			}
		}
	}
	unset( $meta['processing'] );
	if ( ! empty( $new_image) ) {
		$meta = ewww_image_optimizer_update_attachment_metadata( $meta, $ID );
	}
	global $ewww_attachment;
	$ewww_attachment['id'] = $ID;
	$ewww_attachment['meta'] = $meta;
	add_filter( 'w3tc_cdn_update_attachment_metadata', 'ewww_image_optimizer_w3tc_update_files' );

	// in case we used parallel opt, $file might not be set
	if ( empty( $file ) ) {
		$file = $file_path;
	}
	$fullsize_opt_size = ewww_image_optimizer_filesize( $file );
	if ( $fullsize_opt_size && $fullsize_opt_size < $fullsize_size && class_exists( 'Amazon_S3_And_CloudFront' ) ) {
 		global $as3cf;
		if ( method_exists( $as3cf, 'wp_update_attachment_metadata' ) ) {
			$as3cf->wp_update_attachment_metadata( $meta, $ID );
		} elseif ( method_exists( $as3cf, 'wp_generate_attachment_metadata' ) ) {
			$as3cf->wp_generate_attachment_metadata( $meta, $ID );
		}
		ewwwio_debug_message( 'uploading to Amazon S3' );
	}
	if ( $fullsize_opt_size && $fullsize_opt_size < $fullsize_size && class_exists( 'DreamSpeed_Services' ) ) {
		global $dreamspeed;
		$dreamspeed->wp_generate_attachment_metadata( $meta, $ID );
		ewwwio_debug_message( 'uploading to Dreamspeed' );
	}
	if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( "api_secret" ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_enable_cloudinary' ) && ! empty( $new_image ) ) {
		try {
			$result = CloudinaryUploader::upload( $file, array( 'use_filename' => true ) );
		} catch( Exception $e ) {
			$error = $e->getMessage();
		}
		if ( ! empty( $error ) ) {
			ewwwio_debug_message( "Cloudinary error: $error" );
		} else {
			ewwwio_debug_message( 'successfully uploaded to Cloudinary' );
			// register the attachment in the database as a cloudinary attachment
			$old_url = wp_get_attachment_url($ID);
			wp_update_post(array('ID' => $ID,
				'guid' => $result['url']));
			update_attached_file($ID, $result['url']);
			$meta['cloudinary'] = TRUE;
			$errors = array();
			// update the image location for the attachment
			CloudinaryPlugin::update_image_src_all( $ID, $result, $old_url, $result['url'], TRUE, $errors );
			if ( count( $errors ) > 0 ) {
				ewwwio_debug_message( "Cannot migrate the following posts:" );
				foreach( $errors as $error ) {
					ewwwio_debug_message( $error );
				}
			}
		}
	}
	if ( $log ) {
		ewww_image_optimizer_debug_log();
	}
	ewwwio_memory( __FUNCTION__ );
	// send back the updated metadata
	return $meta;
}

function ewww_image_optimizer_detect_wpsf_location_lock() {
	if ( class_exists( 'ICWP_Wordpress_Simple_Firewall' ) ) {
		$shield_user_man = ewww_image_optimizer_get_option( 'icwp_wpsf_user_management_options' );
		ewwwio_debug_message( print_r( $shield_user_man, true ) );
		if ( $shield_user_man['session_lock_location'] == 'Y' ) {
//		if ( $shield_user_man['enable_user_management'] == 'Y' ) {
			return true;
		}
	}
	return false;
}

/**
 * Update the attachment's meta data after being converted 
 */
function ewww_image_optimizer_update_attachment( $meta, $ID ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// update the file location in the post metadata based on the new path stored in the attachment metadata
	update_attached_file( $ID, $meta['file'] );
	$guid = wp_get_attachment_url( $ID );
	if ( empty( $meta['real_orig_file'] ) ) {
		$old_guid = dirname( $guid ) . "/" . basename( $meta['orig_file'] );
	} else {
		$old_guid = dirname( $guid ) . "/" . basename( $meta['real_orig_file'] );
		unset( $meta['real_orig_file'] );
	}
	// construct the new guid based on the filename from the attachment metadata
	ewwwio_debug_message( "old guid: $old_guid" );
	ewwwio_debug_message( "new guid: $guid" );
	if ( substr( $old_guid, -1 ) == '/' || substr( $guid, -1 ) == '/' ) {
		ewwwio_debug_message( 'could not obtain full url for current and previous image, bailing' );
		return $meta;
	}
	// retrieve any posts that link the image
	$esql = $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%%%s%%'", $old_guid );
	ewwwio_debug_message( "using query: $esql" );
	// while there are posts to process
	$rows = $wpdb->get_results( $esql, ARRAY_A );
	if ( ewww_image_optimizer_iterable( $rows ) ) {
		foreach ( $rows as $row ) {
			// replace all occurences of the old guid with the new guid
			$post_content = str_replace( $old_guid, $guid, $row['post_content'] );
			ewwwio_debug_message( "replacing $old_guid with $guid in post " . $row['ID'] );
			// send the updated content back to the database
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $post_content ),
				array( 'ID' => $row['ID'] )
			);
		}
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// for each resized version
		foreach( $meta['sizes'] as $size => $data ) {
			// if the resize was converted
			if ( isset( $data['converted'] ) ) {
				// generate the url for the old image
				if ( empty( $data['real_orig_file'] ) ) {
					$old_sguid = dirname( $old_guid ) . "/" . basename( $data['orig_file'] );
				} else {
					$old_sguid = dirname( $old_guid) . "/" . basename( $data['real_orig_file'] );
					unset ($meta['sizes'][$size]['real_orig_file'] );
				}
				ewwwio_debug_message( "processing: $size" );
				ewwwio_debug_message( "old sguid: $old_sguid" );
				// generate the url for the new image
				$sguid = dirname( $old_guid ) . "/" . basename( $data['file'] );
				ewwwio_debug_message( "new sguid: $sguid" );
				if ( substr( $old_sguid, -1 ) == '/' || substr( $sguid, -1 ) == '/' ) {
					ewwwio_debug_message( 'could not obtain full url for current and previous resized image, bailing' );
					continue;
				}
				// retrieve any posts that link the resize
				$ersql = $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%%%s%%'", $old_sguid );
				//ewwwio_debug_message( "using query: $ersql" );
				$rows = $wpdb->get_results( $ersql, ARRAY_A );
				// while there are posts to process
				if ( ewww_image_optimizer_iterable( $rows ) ) {
					foreach ( $rows as $row ) {
						// replace all occurences of the old guid with the new guid
						$post_content = str_replace( $old_sguid, $sguid, $row['post_content'] );
						ewwwio_debug_message( "replacing $old_sguid with $sguid in post " . $row['ID'] );
						// send the updated content back to the database
						$wpdb->update(
							$wpdb->posts,
							array( 'post_content' => $post_content ),
							array( 'ID' => $row['ID'] )
						);
					}
				}
			}
		}
	}
	// if the new image is a JPG
	if ( preg_match( '/.jpg$/i', basename( $meta['file'] ) ) ) {
		// set the mimetype to JPG
		$mime = 'image/jpeg';
	}
	// if the new image is a PNG
	if ( preg_match( '/.png$/i', basename( $meta['file'] ) ) ) {
		// set the mimetype to PNG
		$mime = 'image/png';
	}
	if ( preg_match( '/.gif$/i', basename( $meta['file'] ) ) ) {
		// set the mimetype to GIF
		$mime = 'image/gif';
	}
	// update the attachment post with the new mimetype and id
	wp_update_post( array(
		'ID' => $ID,
		'post_mime_type' => $mime 
		)
	);
	ewww_image_optimizer_debug_log();
	ewwwio_memory( __FUNCTION__ );
	return $meta;
}

// retrieves path of an attachment via the $id and the $meta
// returns a $file_path and $upload_path
function ewww_image_optimizer_attachment_path( $meta, $ID, $file = '', $refresh_cache = true ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	// retrieve the location of the wordpress upload folder
	$upload_dir = wp_upload_dir( null, false, $refresh_cache );
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	if ( ! $file ) {
		$file = get_post_meta( $ID, '_wp_attached_file', true );
	} else {
		ewwwio_debug_message( 'using prefetched _wp_attached_file' );
	}
	$file_path = ( 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ? $upload_path . $file : $file );
	$filtered_file_path = apply_filters( 'get_attached_file', $file_path, $ID );
	ewwwio_debug_message( "WP (filtered) thinks the file is at: $filtered_file_path" );
	if ( ( strpos( $filtered_file_path, 's3' ) === false || in_array( 's3', stream_get_wrappers() ) ) && is_file( $filtered_file_path ) ) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $filtered_file_path ), $upload_path );
	}
	ewwwio_debug_message( "WP (unfiltered) thinks the file is at: $file_path" );
	if ( ( strpos( $file_path, 's3' ) === false || in_array( 's3', stream_get_wrappers() ) ) && is_file( $file_path ) ) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $file_path ), $upload_path );
	}
	if ( 'ims_image' == get_post_type( $ID ) && is_array( $meta ) && ! empty( $meta['file'] ) ) {
		ewwwio_debug_message( "finding path for IMS image: $ID " );
		if ( is_dir( $file_path ) && is_file( $file_path . $meta['file'] ) ) {
			// generate the absolute path
			$file_path =  $file_path . $meta['file'];
			$upload_path = ewww_image_optimizer_upload_path( $file_path, $upload_path );
			ewwwio_debug_message( "found path for IMS image: $file_path" );
		} elseif ( is_file( $meta['file'] ) ) {
			$file_path = $meta['file'];
			$upload_path = ewww_image_optimizer_upload_path( $file_path, $upload_path );
			ewwwio_debug_message( "found path for IMS image: $file_path" );
		} else {
			$upload_path = trailingslashit( WP_CONTENT_DIR );
			$file_path = $upload_path . ltrim( $meta['file'], '/' );
			ewwwio_debug_message( "checking path for IMS image: $file_path" );
			if ( ! file_exists( $file_path ) ) {
				$file_path = '';
			}
		}
		return array( $file_path, $upload_path );
	}
	if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
		$file_path = $meta['file'];
		if ( strpos( $file_path, 's3' ) === 0 && ! in_array( 's3', stream_get_wrappers() ) ) {
			return array( '', $upload_path );
		}
		ewwwio_debug_message( "looking for file at $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$file_path = trailingslashit( $upload_path ) . $file_path;
		ewwwio_debug_message( "that did not work, try it with the upload_dir: $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/';
		$file_path = $upload_path . $meta['file'];
		ewwwio_debug_message( "one last shot, using the wp-content/ constant: $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return array( '', $upload_path );
}

// takes a file and upload folder, and makes sure that the file is within the folder. if not, it returns an empty string so we don't break things with async/parallel processing
function ewww_image_optimizer_upload_path( $file, $upload_path ) {
	if ( strpos( $file, $upload_path ) === 0 ) {
		return $upload_path; 
	} else {
		return '';
	}
}

// takes a human-readable size, and generates an approximate byte-size
function ewww_image_optimizer_size_unformat( $formatted ) {
	$size_parts = explode( '&nbsp;', $formatted );
	switch ( $size_parts[1] ) {
		case 'B':
			return intval( $size_parts[0] );
		case 'kB':
			return intval( $size_parts[0] * 1024 );
		case 'MB':
			return intval( $size_parts[0] * 1048576 );
		case 'GB':
			return intval( $size_parts[0] * 1073741824 );
		case 'TB':
			return intval( $size_parts[0] * 1099511627776 );
		default:
			return 0;
	}
}

// generate a unique filename for a converted image
function ewww_image_optimizer_unique_filename( $file, $fileext ) {
	// strip the file extension
	$filename = preg_replace( '/\.\w+$/', '', $file );
	if ( ! is_file( $filename . $fileext ) ) {
		return array( $filename . $fileext, '' );
	}
	// set the increment to 1 ( but allow the user to override it )
	$filenum = apply_filters( 'ewww_image_optimizer_converted_filename_suffix', 1 );
	//but it must be only letters, numbers, or underscores
	$filenum = ( preg_match( '/^[\w\d]*$/', $filenum ) ? $filenum : 1 );
	$suffix = ( ! empty( $filenum ) ? '-' . $filenum : '' );
	// while a file exists with the current increment
	while ( file_exists( $filename . $suffix . $fileext ) ) {
		// increment the increment...
		$filenum++;
		$suffix = '-' . $filenum;
	}
	// all done, let's reconstruct the filename
	ewwwio_memory( __FUNCTION__ );
	return array( $filename . $suffix . $fileext, $filenum );
}

// test mimetype based on file extension instead of file contents
// only use for places where speed outweighs accuracy
function ewww_image_optimizer_quick_mimetype( $path ) {
	$pathextension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	switch ( $pathextension ) {
		case 'jpg':
		case 'jpeg':
		case 'jpe':
			return 'image/jpeg';
		case 'png':
			return 'image/png';
		case 'gif':
			return 'image/gif';
		case 'pdf':
			return 'application/pdf';
		default:
			return false;
	}
}

/**
 * Check the submitted PNG to see if it has transparency
 */
function ewww_image_optimizer_png_alpha( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// determine what color type is stored in the file
	$color_type = ord( @file_get_contents( $filename, NULL, NULL, 25, 1 ) );
	ewwwio_debug_message( "color type: $color_type" );
	// if it is set to RGB alpha or Grayscale alpha
	if ( $color_type == 4 || $color_type == 6 ) {
		ewwwio_debug_message( 'transparency found' );
		return true;
	} elseif ( $color_type == 3 && ewww_image_optimizer_gd_support() ) {
		$image = imagecreatefrompng( $filename );
		if ( imagecolortransparent( $image ) >= 0 ) {
			ewwwio_debug_message( 'transparency found' );
			return true;
		}
		list( $width, $height ) = getimagesize( $filename );
		ewwwio_debug_message( "image dimensions: $width x $height" );
		ewwwio_debug_message( 'preparing to scan image' );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$color = imagecolorat( $image, $x, $y );
				$rgb = imagecolorsforindex( $image, $color );
				if ( $rgb['alpha'] > 0 ) {
					ewwwio_debug_message( 'transparency found' );
					return true;
				}
			}
		}
	}
	ewwwio_debug_message( 'no transparency' );
	ewwwio_memory( __FUNCTION__ );
	return false;
}

/**
 * Check the submitted GIF to see if it is animated
 */
function ewww_image_optimizer_is_animated( $filename ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// if we can't open the file in read-only buffered mode
	if(!($fh = @fopen($filename, 'rb'))) {
		return false;
	}
	// initialize $count
	$count = 0;
   
	// We read through the file til we reach the end of the file, or we've found
	// at least 2 frame headers
	while(!feof($fh) && $count < 2) {
		$chunk = fread($fh, 1024 * 100); //read 100kb at a time
		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
	}
	fclose($fh);
	ewwwio_debug_message( "scanned GIF and found $count frames" );
	// return TRUE if there was more than one frame, or FALSE if there was only one
	ewwwio_memory( __FUNCTION__ );
	return $count > 1;
}

// count how many sizes are in the metadata, accounting for those with duplicate dimensions
function ewww_image_optimizer_resize_count( $sizes ) {
	if ( empty( $sizes ) || ! is_array( $sizes ) ) {
		return 0;
	}
	$size_count = 0;
	$processed = array();
	foreach ( $sizes as $size => $data ) {
		if ( strpos( $size, 'webp' ) === 0 ) {
			continue;
		}
		if ( empty( $data['file'] ) ) {
			continue;
		}
		// check through all the sizes we've processed so far
		foreach ( $processed as $proc => $scan ) {
			// if a previous resize had identical dimensions
			if ( $scan['height'] == $data['height'] && $scan['width'] == $data['width'] ) {
				continue( 2 );
			}
		}
		// if this is a unique size
		$size_count++;
		// store info on the sizes we've processed, so we can check the list for duplicate sizes
		$processed[ $size ]['width'] = $data['width'];
		$processed[ $size ]['height'] = $data['height'];
	}
	return $size_count;
}

/**
 * Print column header for optimizer results in the media library using
 * the `manage_media_columns` hook.
 */
function ewww_image_optimizer_columns( $defaults ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$defaults['ewww-image-optimizer'] = esc_html__( 'Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	ewwwio_memory( __FUNCTION__ );
	return $defaults;
}

/**
 * Print column data for optimizer results in the media library using
 * the `manage_media_custom_column` hook.
 */
function ewww_image_optimizer_custom_column( $column_name, $id, $meta = null, $return_output = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// once we get to the EWWW IO custom column
	if ( $column_name == 'ewww-image-optimizer' ) {
		$output = '';
		if ( $meta == null ) {
			// retrieve the metadata
			$meta = wp_get_attachment_metadata( $id );
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ! $return_output ) {
			$print_meta = print_r( $meta, TRUE );
			$print_meta = preg_replace( array('/ /', '/\n+/' ), array( '&nbsp;', '<br />' ), $print_meta );
			$output .= '<div style="background-color:#ffff99;font-size: 10px;padding: 10px;margin:-10px -10px 10px;line-height: 1.1em">' . $print_meta . '</div>';
		}
		$output .= "<div id='ewww-media-status-$id'>";
		$ewww_cdn = false;
		if( is_array( $meta ) && ! empty( $meta['cloudinary'] ) ) {
			$output .= esc_html__( 'Cloudinary image', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		}
		if ( is_array( $meta ) & class_exists( 'WindowsAzureStorageUtil' ) && ! empty( $meta['url'] ) ) {
			$output .= '<div>' . esc_html__( 'Azure Storage image', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
			$ewww_cdn = true;
		}
		if ( is_array( $meta ) && class_exists( 'Amazon_S3_And_CloudFront' ) && preg_match( '/^(http|s3)\w*:/', get_attached_file( $id ) ) ) {
			$output .= '<div>' . esc_html__( 'Amazon S3 image', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
			$ewww_cdn = true;
		}
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		// if the file does not exist
		if ( empty( $file_path ) && ! $ewww_cdn ) {
			$output .= esc_html__( 'Could not retrieve file path.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
			ewww_image_optimizer_debug_log();
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		}
		if ( is_array( $meta ) && ( ! empty( $meta['ewww_image_optimizer'] ) || ! empty( $meta['converted'] ) ) ) {
			$meta = ewww_image_optimizer_migrate_meta_to_db( $id, $meta );
		}
		$msg = '';
		$convert_desc = '';
		$convert_link = '';
		if ( $ewww_cdn ) {
			$type = get_post_mime_type( $id );
		} else {
			// retrieve the mimetype of the attachment
			$type = ewww_image_optimizer_mimetype( $file_path, 'i' );
			// get a human readable filesize
			$file_size = size_format( filesize( $file_path ), 2 );
			$file_size = preg_replace( '/\.00 B /', ' B', $file_size );
		}
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_JPEGTRAN' ) ) {
			ewww_image_optimizer_tool_init();
			ewww_image_optimizer_notice_utils( 'quiet' );
		}
		$skip = ewww_image_optimizer_skip_tools();
		// run the appropriate code based on the mimetype
		switch( $type ) {
			case 'image/jpeg':
				// if jpegtran is missing, tell them that
				if( ! EWWW_IMAGE_OPTIMIZER_JPEGTRAN && ! $skip['jpegtran'] ) {
					$valid = false;
					$msg = '<div>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN), '<em>jpegtran</em>' ), array( 'em' => array() ) ) . '</div>';
				} else {
					$convert_link = esc_html__('JPG to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__( 'WARNING: Removes metadata. Requires GD or ImageMagick. PNG is generally much better than JPG for logos and other images with a limited range of colors.', EWWW_IMAGE_OPTIMIZER_DOMAIN );
				}
				break; 
			case 'image/png':
				// if pngout and optipng are missing, tell the user
				if( ! EWWW_IMAGE_OPTIMIZER_PNGOUT && ! EWWW_IMAGE_OPTIMIZER_OPTIPNG && ! $skip['optipng'] && ! $skip['pngout'] ) {
					$valid = false;
					$msg = '<div>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<em>optipng/pngout</em>' ), array( 'em' => array() ) ) . '</div>';
				} else {
					$convert_link = esc_html__('PNG to JPG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__('WARNING: This is not a lossless conversion and requires GD or ImageMagick. JPG is much better than PNG for photographic use because it compresses the image and discards data. Transparent images will only be converted if a background color has been set.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			case 'image/gif':
				// if gifsicle is missing, tell the user
				if( ! EWWW_IMAGE_OPTIMIZER_GIFSICLE && ! $skip['gifsicle'] ) {
					$valid = false;
					$msg = '<div>' . wp_kses( sprintf( __( '%s is missing', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<em>gifsicle</em>' ), array( 'em' => array() ) ) . '</div>';
				} else {
					$convert_link = esc_html__('GIF to PNG', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$convert_desc = esc_attr__('PNG is generally better than GIF, but does not support animation. Animated images will not be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				break;
			case 'application/pdf':
				$convert_desc = '';
				break;
			default:
				// not a supported mimetype
				$output .= esc_html__( 'Unsupported file type', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
				ewww_image_optimizer_debug_log();
				if ( $return_output ) {
					return $output;
				}
				echo $output;
				return;
		}
		$ewww_manual_nonce = wp_create_nonce( "ewww-manual" );
		global $wpdb;
		$in_progress = false;
		$migrated = false;
		$optimized_images = false;
		if ( $ewww_cdn ) {
			if ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
				$output .= '<div>' . esc_html__( 'In Progress', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
				$in_progress = true;
			}
			if ( ! $in_progress ) {
				$optimized_images = $wpdb->get_results( "SELECT image_size,orig_size,resize,converted,level FROM $wpdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", ARRAY_A );
				if ( ! $optimized_images ) {
					// attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure
					$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
				}
				if ( $migrated ) {
					$optimized_images = $wpdb->get_results( "SELECT image_size,orig_size,resize,converted,level FROM $wpdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", ARRAY_A );
				}
			}
			// if optimizer data exists in the db
			if ( ! empty( $optimized_images ) ) {
				$orig_size = 0;
				$opt_size = 0;
				$level = 0;
				$converted = false;
				$sizes_to_opt = 0;
				$detail_output = '<table class="striped"><tr><th>&nbsp;</th><th>' . esc_html__( 'Image Size', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th><th>' . esc_html__( 'Savings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th></tr>';
				foreach ( $optimized_images as $optimized_image ) {
					$orig_size += $optimized_image['orig_size'];
					$opt_size += $optimized_image['image_size'];
					if ( $optimized_image['resize'] == 'full' ) {
						$level = $optimized_image['level'];
					}
					if ( ! empty( $optimized_image['converted'] ) ) {
						$converted = $optimized_image['converted'];
					}
					$sizes_to_opt++;
					if ( ! empty( $optimized_image['resize'] ) ) {
						$display_size = size_format( $optimized_image['image_size'], 2 );
						$display_size = preg_replace( '/\.00 B /', ' B', $display_size );
		        			$detail_output .= '<tr><td><strong>' . ucfirst( $optimized_image['resize'] ) . "</strong></td><td>$display_size</td><td>" . esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . '</td></tr>';
					}
				}
				$detail_output .= '</table>';

				$output .= '<div>' . sprintf( esc_html__( '%d sizes compressed',EWWW_IMAGE_OPTIMIZER_DOMAIN ), $sizes_to_opt );
				$output .= " <a href='#TB_inline?width=550&height=450&inlineId=ewww-attachment-detail-$id' class='thickbox'>(+)</a></div>";
				$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size );
				// output the optimizer results
				$output .= '<div>' . esc_html( $results_msg ) . '</div>';
				$display_size = size_format( $opt_size, 2 );
				$display_size = preg_replace( '/\.00 B /', ' B', $display_size );
				// output the filesize
				$detail_output .= '<div><strong>' . sprintf( esc_html__( 'Total Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $display_size ) . '</strong></div>';
				$output .= "<div id='ewww-attachment-detail-$id' class='ewww-attachment-detail-container'><div class='ewww-attachment-detail'>$detail_output</div></div>";
				// output the optimizer results
				if ( current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
					// output a link to re-optimize manually
					$output .= '<div>' . sprintf( "<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
						$id,
						esc_html__( 'Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) . '</div>';
				}
			} elseif ( ! $in_progress && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				ewww_image_optimizer_migrate_meta_to_db( $id, $meta );
				// and give the user the option to optimize the image right now
				if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					$disabled_sizes_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
					$disabled_count = ( is_array( $disabled_sizes_opt ) ? count( $disabled_sizes_opt ) : 0 );
					$resizes_to_opt = ewww_image_optimizer_resize_count( $meta['sizes'] );
					$sizes_to_opt = $resizes_to_opt + 1 - $disabled_count;
					$output .= '<div>' . sprintf( esc_html__( '%d sizes to compress',EWWW_IMAGE_OPTIMIZER_DOMAIN ), $sizes_to_opt ) . '</div>';
				}
				$output .= '<div>' . sprintf( "<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' ref=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) . '</div>';
			}
			$output .= '</div>';
			if ( $return_output ) {
				return $output;
			}
			echo $output;
			return;
		} // end output for CDN images
		if ( get_transient( 'ewwwio-background-in-progress-' . $id ) ) {
			$output .= esc_html__( 'In Progress', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			$in_progress = true;
		// if optimizer data exists
		}
		if ( ! $in_progress ) {
			$optimized_images = $wpdb->get_results( "SELECT image_size,orig_size,resize,converted,level FROM $wpdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", ARRAY_A );
			if ( ! $optimized_images ) {
				// attempt migration, but only if the original image is in the db, $migrated will be metadata on success, false on failure
				$migrated = ewww_image_optimizer_migrate_meta_to_db( $id, $meta, true );
			}
			if ( $migrated ) {
				$optimized_images = $wpdb->get_results( "SELECT image_size,orig_size,resize,converted,level FROM $wpdb->ewwwio_images WHERE attachment_id = $id AND gallery = 'media' AND image_size <> 0 ORDER BY orig_size DESC", ARRAY_A );
			}
		}
		if ( ! empty( $optimized_images ) ) {
			$orig_size = 0;
			$opt_size = 0;
			$level = 0;
			$converted = false;
			$sizes_to_opt = 0;
			$detail_output = '<table class="striped"><tr><th>&nbsp;</th><th>' . esc_html__( 'Image Size', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th><th>' . esc_html__( 'Savings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th></tr>';
			foreach ( $optimized_images as $optimized_image ) {
				$orig_size += $optimized_image['orig_size'];
				$opt_size += $optimized_image['image_size'];
				if ( $optimized_image['resize'] == 'full' ) {
					$level = $optimized_image['level'];
				}
				if ( ! empty( $optimized_image['converted'] ) ) {
					$converted = $optimized_image['converted'];
				}
				$sizes_to_opt++;
				if ( ! empty( $optimized_image['resize'] ) ) {
					$display_size = size_format( $optimized_image['image_size'], 2 );
					$display_size = preg_replace( '/\.00 B /', ' B', $display_size );
		                        $detail_output .= '<tr><td><strong>' . ucfirst( $optimized_image['resize'] ) . "</strong></td><td>$display_size</td><td>" . esc_html( ewww_image_optimizer_image_results( $optimized_image['orig_size'], $optimized_image['image_size'] ) ) . '</td></tr>';
				}
			}
			$detail_output .= '</table>';
			$output .= '<div>' . sprintf( esc_html__( '%d sizes compressed',EWWW_IMAGE_OPTIMIZER_DOMAIN ), $sizes_to_opt );
			$output .= " <a href='#TB_inline?width=550&height=450&inlineId=ewww-attachment-detail-$id' class='thickbox'>(+)</a></div>";
			$results_msg = ewww_image_optimizer_image_results( $orig_size, $opt_size );
			// output the optimizer results
			$output .= '<div>' . esc_html( $results_msg ) . '</div>';
			$display_size = size_format( $opt_size, 2 );
			$display_size = preg_replace( '/\.00 B /', ' B', $display_size );
			// output the filesize
			$detail_output .= '<div><strong>' . sprintf( esc_html__( 'Total Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $display_size ) . '</strong></div>';
			$output .= "<div id='ewww-attachment-detail-$id' class='ewww-attachment-detail-container'><div class='ewww-attachment-detail'>$detail_output</div></div>";
			
			// link to webp upgrade script
			$oldwebpfile = preg_replace('/\.\w+$/', '.webp', $file_path);
			if ( file_exists( $oldwebpfile ) && current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
				$output .= "<div><a href='options.php?page=ewww-image-optimizer-webp-migrate'>" . esc_html__( 'Run WebP upgrade', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></div>";
			}

			// determine filepath for webp
			$webpfile = $file_path . '.webp';
			$webp_size = ewww_image_optimizer_filesize( $webpfile );
			if ( $webp_size ) {
				$webp_size = size_format( $webp_size, 2 );
				$webpurl = esc_url( wp_get_attachment_url( $id ) . '.webp' );
				// get a human readable filesize
				$webp_size = preg_replace( '/\.00 B /', ' B', $webp_size );
				$output .= "<div>WebP: <a href='$webpurl'>$webp_size</a></div>";
			}

			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// output a link to re-optimize manually
				$output .= '<div>' . sprintf("<a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_force=1&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Re-optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' != get_post_type( $id ) && ! empty( $convert_desc ) ) {
					$output .= " | <a class='ewww-manual-convert' data-id='$id' data-nonce='$ewww_manual_nonce' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
				$output .= '</div>';
			} else {
				$output .= $msg;
			}
			$restorable = false;
			if ( $converted && is_file( $converted ) ) {
				$restorable = true;
			}
			if ( $restorable && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				$output .= '<div>' . sprintf( "<a class='ewww-manual-restore' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_restore&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>",
					$id,
					esc_html__( 'Restore original', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) . '</div>';
			}
		} elseif ( ! $in_progress ) {
			// otherwise, this must be an image we haven't processed
			if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
				$disabled_sizes_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
				$disabled_count = ( is_array( $disabled_sizes_opt ) ? count( $disabled_sizes_opt ) : 0 );
				$resizes_to_opt = ewww_image_optimizer_resize_count( $meta['sizes'] );
				$sizes_to_opt = $resizes_to_opt + 1 - $disabled_count;
				$output .= '<div>' . sprintf( esc_html__( '%d sizes to compress',EWWW_IMAGE_OPTIMIZER_DOMAIN ), $sizes_to_opt ) . '</div>';
			} else {
				$output .= '<div>' . esc_html__( 'Not processed', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</div>';
			}
			// tell them the filesize
			$output .= '<div>' . sprintf( esc_html__( 'Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file_size ) . '</div>';
			if ( empty( $msg ) && current_user_can( apply_filters( 'ewww_image_optimizer_manual_permissions', '' ) ) ) {
				// and give the user the option to optimize the image right now
				$output .= sprintf( "<div><a class='ewww-manual-optimize' data-id='$id' data-nonce='$ewww_manual_nonce' href=\"admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=%d\">%s</a>", $id, esc_html__( 'Optimize now!', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_convert_links' ) && 'ims_image' != get_post_type( $id ) && ! empty( $convert_desc ) ) {
					$output .= " | <a class='ewww-manual-convert' data-id='$id' data-nonce='$ewww_manual_nonce' title='$convert_desc' href='admin.php?action=ewww_image_optimizer_manual_optimize&amp;ewww_manual_nonce=$ewww_manual_nonce&amp;ewww_attachment_ID=$id&amp;ewww_convert=1&amp;ewww_force=1'>$convert_link</a>";
				}
				$output .= '</div>';
			} else {
				$output .= $msg;
			}
		}
		$output .= '</div>';
		if ( $return_output ) {
			ewww_image_optimizer_debug_log();
			return $output;
		}
		echo $output;
	}
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
}

function ewww_image_optimizer_clean_meta( $meta ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( is_array( $meta ) && ! empty( $meta['ewww_image_optimizer'] ) ) {
		unset( $meta['ewww_image_optimizer'] );
	}
	if ( is_array( $meta ) && ! empty( $meta['converted'] ) ) {
		unset( $meta['converted'] );
	}
	if ( is_array( $meta ) && ! empty( $meta['orig_file'] ) ) {
		unset( $meta['orig_file'] );
	}
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size => $data ) {
			if ( is_array( $data ) && ! empty( $data['ewww_image_optimizer'] ) ) {
				unset( $meta['sizes'][ $size ]['ewww_image_optimizer'] );
			}
			if ( is_array( $data ) && ! empty( $data['converted'] ) ) {
				unset( $meta['sizes'][ $size ]['converted'] );
			}
			if ( is_array( $data ) && ! empty( $data['orig_file'] ) ) {
				unset( $meta['sizes'][ $size ]['orig_file'] );
			}
		}
	}
	return $meta;
}

function ewww_image_optimizer_update_file_from_meta( $file, $gallery = false, $attachment_id = false, $size = false, $converted = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$already_optimized = ewww_image_optimizer_find_already_optimized( $file );
	if ( is_array( $already_optimized ) && ! empty( $already_optimized['id'] ) ) {
	//	ewwwio_debug_message( "found record for $file" );
	//	ewwwio_debug_message( print_r( $already_optimized, true ) );
		$updates = array();
		if ( $gallery && empty( $already_optimized['gallery'] ) ) {
			$updates['gallery'] = $gallery;
		}
		if ( $attachment_id && empty( $already_optimized['attachment_id'] ) ) {
			$updates['attachment_id'] = $attachment_id;
		}
		if ( $size && empty( $already_optimized['resize'] ) ) {
			$updates['resize'] = $size;
		}
		if ( $converted && empty( $already_optimized['converted'] ) ) {
			$updates['converted'] = $converted;
		}
		if ( $updates ) {
			ewwwio_debug_message( "running update for $file" );
			$updates['updated'] = $already_optimized['updated'];
		//ewwwio_debug_message( print_r( $updates, true ) );
			global $wpdb;
			// update the values given for the record we found
			$updated = $wpdb->update(
				$wpdb->ewwwio_images,
				$updates,
				array(
					'id' => $already_optimized['id'],
				)
			);
			if ( false === $updated ) {
				ewwwio_debug_message( "failed to update record for $file" );
			}
			if ( ! $updated ) {
				ewwwio_debug_message( "no records updated for $file and {$already_optimized['id']}" );
			}
			return $updated;
		}
	}
	return false;
}

function ewww_image_optimizer_migrate_meta_to_db( $id, $meta, $bail_early = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $meta ) ) {
		ewwwio_debug_message( "empty meta for $id" );
		return $meta;
	}
	//ewwwio_debug_message( print_r( $meta, true ) );
	list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
	if ( ! is_file( $file_path ) && ( class_exists( 'WindowsAzureStorageUtil' ) || class_exists( 'Amazon_S3_And_CloudFront' ) ) ) {
		// construct a $file_path and proceed IF a supported CDN plugin is installed
		$file_path = get_attached_file( $selected_id );
		if ( ! $file_path ) {
		ewwwio_debug_message( 'no file found for remote attachment' );
		// TODO: once we've kicked the tires about a million times, and we're convinced this can't happen in error, then let's clean the meta
		//	$meta = ewww_image_optimizer_clean_meta( $meta );
			return $meta;
		}
	} elseif ( ! $file_path ) {
		ewwwio_debug_message( 'no file found for attachment' );
	// TODO: ditto
	//	$meta = ewww_image_optimizer_clean_meta( $meta );
		return $meta;
	}
	$converted = ( is_array( $meta ) && ! empty( $meta['converted'] ) && ! empty( $meta['orig_file'] ) ? trailingslashit( dirname( $file_path ) ) . basename( $meta['orig_file'] ) : false );
	$full_size_update = ewww_image_optimizer_update_file_from_meta( $file_path, 'media', $id, 'full', $converted );
	if ( ! $full_size_update && $bail_early ) {
		ewwwio_debug_message( "bailing early for migration of $id" );
		return false;
	}
	$retina_path = ewww_image_optimizer_hidpi_optimize( $file_path, true, false );
	if ( $retina_path ) {
		ewww_image_optimizer_update_file_from_meta( $retina_path, 'media', $id, 'full-retina' );
	}
	$type = ewww_image_optimizer_quick_mimetype( $file_path );
	// resized versions, so we can continue
	if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
		// meta sizes don't contain a path, so we calculate one
		if ( 'ims_image' == get_post_type( $id ) ) {
			$base_dir = trailingslashit( dirname( $file_path ) ) . '_resized/';
		} else {
			$base_dir = trailingslashit( dirname( $file_path ) );
		}
		foreach ( $meta['sizes'] as $size => $data ) {
			ewwwio_debug_message( "checking for size: $size" );
			if ( strpos( $size, 'webp') === 0 ) {
				continue;
			}
			if ( empty( $data ) || ! is_array( $data ) || empty( $data['file'] ) ) {
				continue;
			}
			if ( $size == 'full' && $type == 'application/pdf' ) {
				$size = 'pdf-full';
			} elseif ( $size == 'full' ) {
				continue;
			}

			$resize_path = $base_dir . $data['file'];
			$converted = ( is_array( $data ) && ! empty( $data['converted'] ) && ! empty( $data['orig_file'] ) ? trailingslashit( dirname( $resize_path ) ) . basename( $data['orig_file'] ) : false );
			ewww_image_optimizer_update_file_from_meta( $resize_path, 'media', $id, $size, $converted );
			// optimize retina images, if they exist
			if ( function_exists( 'wr2x_get_retina' ) && $retina_path = wr2x_get_retina( $resize_path ) ) {
				ewww_image_optimizer_update_file_from_meta( $retina_path, 'media', $id, $size . '-retina' );
			} elseif ( $retina_path = ewww_image_optimizer_hidpi_optimize( $resize_path, true, false ) ) {
				ewww_image_optimizer_update_file_from_meta( $retina_path, 'media', $id, $size . '-retina' );
			}
		}
	}

	// queue sizes from a custom theme
	if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
		$imagemeta_resize_pathinfo = pathinfo( $file_path );
		$imagemeta_resize_path = '';
		foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
			$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
			ewww_image_optimizer_update_file_from_meta( $imagemeta_resize_path, 'media', $id, 'resized-images-' . $index );
		}		
	}

	// and another custom theme
	if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
		$custom_sizes_pathinfo = pathinfo( $file_path );
		$custom_size_path = '';
		foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
			$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
			ewww_image_optimizer_update_file_from_meta( $custom_size_path, 'media', $id, 'custom-size-' . $dimensions );
		}
	}
	$meta = ewww_image_optimizer_clean_meta( $meta );
	ewwwio_debug_message( print_r( $meta, true ) );
	update_post_meta( $id, '_wp_attachment_metadata', $meta );
	return $meta;
}

function ewww_image_optimizer_load_admin_js() {
	add_action( 'admin_print_footer_scripts', 'ewww_image_optimizer_add_bulk_actions_via_javascript' ); 
}

// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/
// adds a bulk optimize action to the drop-down on the media library page
function ewww_image_optimizer_add_bulk_actions_via_javascript() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_bulk_permissions', '' ) ) ) {
		return;
	}
?>
	<script type="text/javascript"> 
		jQuery(document).ready(function($){ 
			$('select[name^="action"] option:last-child').before('<option value="bulk_optimize"><?php esc_html_e('Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN); ?></option>');
			$('.ewww-manual-convert').tooltip();
		}); 
	</script>
<?php } 

// Handles the bulk actions POST 
// Borrowed from http://www.viper007bond.com/wordpress-plugins/regenerate-thumbnails/ 
function ewww_image_optimizer_bulk_action_handler() { 
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// if the requested action is blank, or not a bulk_optimize, do nothing
	if ( ( empty( $_REQUEST['action'] ) || 'bulk_optimize' != $_REQUEST['action'] ) && ( empty( $_REQUEST['action2'] ) || 'bulk_optimize' != $_REQUEST['action2'] ) ) {
		return;
	}
	// if there is no media to optimize, do nothing
	if ( empty( $_REQUEST['media'] ) || ! is_array( $_REQUEST['media'] ) ) {
		return; 
	}
	// check the referring page
	check_admin_referer( 'bulk-media' ); 
	// prep the attachment IDs for optimization
	$ids = implode( ',', array_map( 'intval', $_REQUEST['media'] ) ); 
	wp_redirect( add_query_arg( array( 'page' => 'ewww-image-optimizer-bulk', '_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-bulk' ), 'goback' => 1, 'ids' => $ids ), admin_url( 'upload.php' ) ) ); 
	ewwwio_memory( __FUNCTION__ );
	exit(); 
}

// display a page of unprocessed images from Media library
function ewww_image_optimizer_display_unoptimized_media() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$bulk_resume = get_option( 'ewww_image_optimizer_bulk_resume' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	$attachments = ewww_image_optimizer_count_optimized( 'media', true );
	update_option( 'ewww_image_optimizer_bulk_resume', $bulk_resume );
	echo "<div class='wrap'><h1>" . esc_html__( 'Unoptimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h1>";
	printf( '<p>' . esc_html__( 'We have %d images to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>', count( $attachments ) );
	if ( count( $attachments ) != 0 ) {
		sort( $attachments, SORT_NUMERIC );
		$image_string = implode( ',', $attachments );
		echo '<form method="post" action="upload.php?page=ewww-image-optimizer-bulk">'
			. "<input type='hidden' name='ids' value='$image_string' />"
			. '<input type="submit" class="button-secondary action" value="' . esc_html__( 'Optimize All Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '" />'
			. '</form>';
		if ( count( $attachments ) < 500 ) {
			sort( $attachments, SORT_NUMERIC );
			$image_string = implode( ',', $attachments );
			echo '<table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>ID</th><th>&nbsp;</th><th>' . esc_html__('Title', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th><th>' . esc_html__('Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</th></tr></thead>';
			$alternate = true;
			foreach ( $attachments as $ID ) {
				$image_name = get_the_title( $ID );
?>				<tr<?php if( $alternate ) echo " class='alternate'"; ?>><td><?php echo $ID; ?></td>
<?php				echo "<td style='width:80px' class='column-icon'>" . wp_get_attachment_image( $ID, 'thumbnail' ) . "</td>";
				echo "<td class='title'>$image_name</td>";
				echo "<td>";
				ewww_image_optimizer_custom_column( 'ewww-image-optimizer', $ID );
				echo "</td></tr>";
				$alternate = ! $alternate;
			}
			echo '</table>';
		} else {
			echo '<p>' . esc_html__( 'There are too many images to display.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</p>'; 
		}
	}
	echo '</div>';
	if ( ewww_image_optimizer_get_option ( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_debug;
		echo '<div id="ewww-debug-info" style="clear:both;background:#ffff99;margin-left:-20px;padding:10px">' . $ewww_debug . '</div>';
	}
	return;	
}

// retrieve an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_get_option( $option_name ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$option_value = get_site_option( $option_name );
	} else {
		$option_value = get_option( $option_name );
	}
	return $option_value;
}

// set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting
function ewww_image_optimizer_set_option( $option_name, $option_value ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		$success = update_site_option( $option_name, $option_value );
	} else {
		$success = update_option( $option_name, $option_value );
	}
	return $success;
}

// check for a list of attachments for which we do not rebuild meta
function ewww_image_optimizer_get_bad_attachments() {
	$bad_attachment = get_transient( 'ewww_image_optimizer_rebuilding_attachment' );
	if ( $bad_attachment ) {
		$bad_attachments = (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_bad_attachments' );
		$bad_attachments[] = $bad_attachment;
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_bad_attachments', $bad_attachments, false );
	} else {
		$bad_attachments = (array) ewww_image_optimizer_get_option( 'ewww_image_optimizer_bad_attachments' );
	}
	delete_transient( 'ewww_image_optimizer_rebuilding_attachment' );
	return array( $bad_attachments, $bad_attachment );
}

function ewww_image_optimizer_settings_script( $hook ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
//	global $wpdb;
	// make sure we are being called from the settings page
	if ( strpos( $hook,'settings_page_ewww-image-optimizer' ) !== 0 ) {
		return;
	}
	wp_enqueue_script( 'ewwwbulkscript', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
	wp_enqueue_script( 'postbox' );
	wp_enqueue_script( 'dashboard' );
	wp_localize_script( 'ewwwbulkscript', 'ewww_vars', array(
			'_wpnonce' => wp_create_nonce( 'ewww-image-optimizer-settings' ),
		)
	);
	ewwwio_memory( __FUNCTION__ );
	return;
}

function ewww_image_optimizer_savings() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
		ewwwio_debug_message( 'querying savings for multi-site' );
		if ( function_exists( 'get_sites' ) ) {
			ewwwio_debug_message( 'retrieving list of sites the easy way (4.6+)' );
			add_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );
			$blogs = get_sites( array(
				'fields' => 'ids',
				'number' => 10000,
			) );
			remove_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );

		} elseif ( function_exists( 'wp_get_sites' ) ) {
			ewwwio_debug_message( 'retrieving list of sites the easy way (pre 4.6)' );
			add_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );
			$blogs = wp_get_sites( array(
				'network_id' => $wpdb->siteid,
				'limit' => 10000
			) );
			remove_filter( 'wp_is_large_network', 'ewww_image_optimizer_large_network', 20, 0 );
		}
		$total_savings = 0;
		if ( ewww_image_optimizer_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				if ( is_array( $blog ) ) {
					$blog_id = $blog['blog_id'];
				} else {
					$blog_id = $blog;
				}
				switch_to_blog( $blog_id );
				ewwwio_debug_message( "getting savings for site: $blog_id" );
				$table_name = $wpdb->prefix . 'ewwwio_images';
				if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
					ewww_image_optimizer_install_table();
				}
				$wpdb->query( "DELETE FROM $table_name WHERE image_size > orig_size" );
				$total_query = "SELECT SUM(orig_size-image_size) FROM $table_name";
				//ewwwio_debug_message( "query to be performed: $total_query" );
				$savings = $wpdb->get_var( $total_query );
				ewwwio_debug_message( "savings found: $savings" );
				$total_savings += $savings;
				//ewwwio_debug_message( "savings so far: $total_savings" );
			}
			restore_current_blog();
		}
	} else {
		ewwwio_debug_message( 'querying savings for single site' );
		$total_savings = 0;
		$table_name = $wpdb->ewwwio_images;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			ewww_image_optimizer_install_table();
		}
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'ewwwio_images WHERE image_size > orig_size' );
		$total_query = "SELECT SUM(orig_size-image_size) FROM $wpdb->ewwwio_images";
		ewwwio_debug_message( "query to be performed: $total_query" );
		$total_savings = $wpdb->get_var($total_query);
		ewwwio_debug_message( "savings found: $total_savings" );
	}
	return $total_savings;
}

function ewww_image_optimizer_htaccess_path() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$htpath = get_home_path();
	if ( get_option( 'siteurl' ) !== get_option( 'home' ) ) {
		ewwwio_debug_message( 'WordPress Address and Site Address are different, possible subdir install' );
		$path_diff = str_replace(  get_option( 'home' ), '', get_option( 'siteurl' ) );
		$newhtpath = trailingslashit( $htpath . $path_diff ) . '.htaccess';
		if ( is_file( $newhtpath ) ) {
			ewwwio_debug_message( 'subdir install confirmed' );
			return $newhtpath;
		}
	}
	return $htpath . '.htaccess';
}

function ewww_image_optimizer_webp_rewrite() {
	// verify that the user is properly authorized
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-settings' ) ) {
		wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	if ( $ewww_rules = ewww_image_optimizer_webp_rewrite_verify() ) {
		if ( insert_with_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO', $ewww_rules ) && ! ewww_image_optimizer_webp_rewrite_verify() ) {
			esc_html_e( 'Insertion successful', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		} else {
			esc_html_e( 'Insertion failed', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		}
	}
	die();
}

// if rules are present, stay silent, otherwise, give us some rules to insert!
function ewww_image_optimizer_webp_rewrite_verify() {
	$current_rules = extract_from_markers( ewww_image_optimizer_htaccess_path(), 'EWWWIO' ) ;
	$ewww_rules = array(
		"<IfModule mod_rewrite.c>",
		"RewriteEngine On",
		"RewriteCond %{HTTP_ACCEPT} image/webp",
		"RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$",
		"RewriteCond %{REQUEST_FILENAME}.webp -f",
		"RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_FILENAME}.webp [T=image/webp,E=accept:1]",
		"</IfModule>",
		"<IfModule mod_headers.c>",
		"Header append Vary Accept env=REDIRECT_accept",
		"</IfModule>",
		"AddType image/webp .webp",
	);
	if ( array_diff( $ewww_rules, $current_rules ) ) {
		ewwwio_memory( __FUNCTION__ );
		return $ewww_rules;
	} else {
		ewwwio_memory( __FUNCTION__ );
		return;
	}
}

function ewww_image_optimizer_get_image_sizes() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $_wp_additional_image_sizes;
	$sizes = array();
	$image_sizes = get_intermediate_image_sizes();
	ewwwio_debug_message( print_r( $image_sizes, true ) );
	//ewwwio_debug_message( print_r( $_wp_additional_image_sizes, true ) );
	if ( ewww_image_optimizer_iterable( $image_sizes ) ) {
		foreach( $image_sizes as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				if ( $_size === 'medium_large' && $sizes[ $_size ]['width'] == 0 ) {
					$sizes[ $_size ]['width'] = '768';
				}
				if ( $_size === 'medium_large' && $sizes[ $_size ]['height'] == 0 ) {
					$sizes[ $_size ]['height'] = '9999';
				}
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array( 
					'width' => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
				);
			}
		}
	}
	$sizes['pdf-full'] = array(
		'width' => 99999,
		'height' => 99999,
	);
		
	ewwwio_debug_message( print_r( $sizes, true ) );
	return $sizes;
}

// displays the EWWW IO options and provides one-click install for the optimizer utilities
function ewww_image_optimizer_options () {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	ewwwio_debug_message( 'ABSPATH: ' . ABSPATH );
	ewwwio_debug_message( 'WP_CONTENT_DIR: ' . WP_CONTENT_DIR );
	ewwwio_debug_message( 'home url: ' . get_home_url() );
	ewwwio_debug_message( 'site url: ' . get_site_url() );

	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// need to include the plugin library for the is_plugin_active function
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	$output = array();
	if (isset($_REQUEST['ewww_pngout'])) {
		if ($_REQUEST['ewww_pngout'] == 'success') {
			$output[] = "<div id='ewww-image-optimizer-pngout-success' class='updated fade'>\n";
			$output[] = '<p>' . esc_html__('Pngout was successfully installed, check the Plugin Status area for version information.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>\n";
			$output[] = "</div>\n";
		}
		if ($_REQUEST['ewww_pngout'] == 'failed') {
			$output[] = "<div id='ewww-image-optimizer-pngout-failure' class='error'>\n";
			$output[] = '<p>' . sprintf( esc_html__('Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', EWWW_IMAGE_OPTIMIZER_DOMAIN), sanitize_text_field( $_REQUEST['ewww_error'] ), EWWW_IMAGE_OPTIMIZER_TOOL_PATH) . "</p>\n";
			$output[] = "</div>\n";
		}
	}
	$output[] = "<script type='text/javascript'>\n" .
		'jQuery(document).ready(function($) {$(".fade").fadeTo(5000,1).fadeOut(3000);$(".updated").fadeTo(5000,1).fadeOut(3000);});' . "\n" .
		"</script>\n";
	$output[] = "<style>\n" .
		".ewww-tab a { font-size: 15px; font-weight: 700; color: #555; text-decoration: none; line-height: 36px; padding: 0 10px; }\n" .
		".ewww-tab a:hover { color: #464646; }\n" .
		".ewww-tab { margin: 0 0 0 5px; padding: 0px; border-width: 1px 1px 1px; border-style: solid solid none; border-image: none; border-color: #ccc; display: inline-block; background-color: #e4e4e4 }\n" .
		".ewww-tab:hover { background-color: #fff }\n" .
		".ewww-selected { background-color: #f1f1f1; margin-bottom: -1px; border-bottom: 1px solid #f1f1f1 }\n" .
		".ewww-selected a { color: #000; }\n" .
		".ewww-selected:hover { background-color: #f1f1f1; }\n" .
		".ewww-tab-nav { list-style: none; margin: 10px 0 0; padding-left: 5px; border-bottom: 1px solid #ccc; }\n" .
	"</style>\n";
	$output[] = "<div class='wrap'>\n";
	$output[] = "<h1>EWWW Image Optimizer</h1>\n";
	$output[] = "<div id='ewww-container-left' style='float: left; margin-right: 225px;'>\n";
	$output[] = "<p><a href='https://wordpress.org/extend/plugins/ewww-image-optimizer/'>" . esc_html__( 'Plugin Home Page', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> | " .
		"<a href='https://wordpress.org/extend/plugins/ewww-image-optimizer/installation/'>" .  esc_html__( 'Installation Instructions', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> | " .
		"<a href='https://ewww.io/contact-us/'>" . esc_html__( 'Plugin Support', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> | " .
		"<a href='https://ewww.io/status/'>" . esc_html__( 'Cloud Status', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> | " .
		"<a href='https://ewww.io/downloads/s3-image-optimizer/'>" . esc_html__( 'S3 Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a></p>\n";
		if ( is_multisite() && is_plugin_active_for_network( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
			$bulk_link = esc_html__( 'Media Library', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' -> ' . esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN );
		} else {
			$bulk_link = '<a href="upload.php?page=ewww-image-optimizer-bulk">' . esc_html__( 'Bulk Optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
		}
	$output[] = "<p>" . wp_kses( sprintf( __( 'New images uploaded to the Media Library will be optimized automatically. If you have existing images you would like to optimize, you can use the %s tool.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $bulk_link ), array( 'a' => array( 'href' => array() ) ) ) . " " . wp_kses( __( 'Images stored in an Amazon S3 bucket can be optimized using our <a href="https://ewww.io/downloads/s3-image-optimizer/">S3 Image Optimizer</a>.' ), array( 'a' => array( 'href' => array() ) ) ) . "</p>\n";
	//if ( EWWW_IMAGE_OPTIMIZER_CLOUD ) {
	if ( ewww_image_optimizer_full_cloud() ) {
		$collapsed = '';
	} else {
		$collapsed = "$('#ewww-status').toggleClass('closed');\n";
	}
	$output[] = "<div id='ewww-widgets' class='metabox-holder'><div class='meta-box-sortables'><div id='ewww-status' class='postbox'>\n" .
		"<button type='button' class='handlediv button-link' aria-expanded='true'>" .
			"<span class='screen-reader-text'>" . esc_html__( 'Click to toggle', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</span>" .
			"<span class='toggle-indicator' aria-hidden='true'></span>" .
		"</button>" .
		"<h2 class='hndle'>" . esc_html__('Plugin Status', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "&emsp;" .
			"<span id='ewww-status-ok' style='display: none; color: green;'>" . esc_html__('All Clear', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>" . 
			"<span id='ewww-status-attention' style='color: red;'>" . esc_html__('Requires Attention', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>"  .
		"</h2>\n" .
			"<div class='inside'>" .
			"<b>" . esc_html__('Total Savings:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b> <span id='ewww-total-savings'>" . size_format( ewww_image_optimizer_savings(), 2 ) . "</span><br>";
			ewwwio_debug_message( ewww_image_optimizer_aux_images_table_count() . " images have been optimized" );
			$collapsible = true;
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				$output[] = '<p><b>' . esc_html__( 'Cloud optimization API Key', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ":</b> ";
				ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_exceeded', 0 );
				$verify_cloud = ewww_image_optimizer_cloud_verify(); 
				if ( preg_match( '/great/', $verify_cloud ) ) {
					$output[] = '<span style="color: green">' . esc_html__( 'Verified,', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' </span>' . ewww_image_optimizer_cloud_quota();
				} elseif ( preg_match( '/exceeded/', $verify_cloud ) ) { 
					$output[] = '<span style="color: orange">' . esc_html__( 'Verified,', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ' </span>' . ewww_image_optimizer_cloud_quota();
					$collapsible = false;
				} else { 
					$output[] = '<span style="color: red">' . esc_html__( 'Not Verified', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</span>';
					$collapsible = false;
				}
				$output[] = "</p>\n";
				$disable_level = '';
			} else {
				if ( EWWW_IMAGE_OPTIMIZER_DOMAIN == 'ewww-image-optimizer-cloud' ) {
					$collapsible = false;
				}
				$disable_level = "disabled='disabled'";
			}
			if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
				$output[] = '<p><span style="color: orange">' . esc_html__('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span> ' . esc_html__( "You are using Shield's Lock to Location feature, which prevents the use of Background & Parallel Optimization for faster processing time.", EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</p>'; // this one inactive
//				$output[] = '<p><span style="color: orange">' . esc_html__('NOTICE:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span> ' . esc_html__( "You are using Shield's User Management feature, which prevents the use of Background & Parallel Optimization for faster processing time.", EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</p>';
				$collapsible = false;
			}
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_bundle' ) && ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<p>" . esc_html__( 'If updated versions are available below you may either download the newer versions and install them yourself, or uncheck "Use System Paths" and use the bundled tools. ', EWWW_IMAGE_OPTIMIZER_DOMAIN)  . "<br />\n" .
					"<i>*" . esc_html__('Updates are optional, but may contain increased optimization or security patches', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</i></p>\n";
			} elseif ( ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<p>" . sprintf( esc_html__( 'If updated versions are available below, you may need to enable write permission on the %s folder to use the automatic installs.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<i>' . EWWW_IMAGE_OPTIMIZER_TOOL_PATH . '</i>' ) . "<br />\n" .
					"<i>*" . esc_html__( 'Updates are optional, but may contain increased optimization or security patches', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</i></p>\n";
			}
			if ( ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				list ( $jpegtran_src, $optipng_src, $gifsicle_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst ) = ewww_image_optimizer_install_paths();
			}
			$skip = ewww_image_optimizer_skip_tools();
			$output[] = "<p>\n";
			if ( ! $skip['jpegtran']  && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>jpegtran:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_JPEGTRAN ) {
					$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'j' );
					if ( ! $jpegtran_installed ) {
						$jpegtran_installed = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_JPEGTRAN, 'jb' );
					}
				}
				if ( ! empty( $jpegtran_installed ) ) {
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;' . esc_html__('version', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $jpegtran_installed . "<br />\n"; 
				} else { 
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
					$collapsible = false;
				}
			}
			if ( ! $skip['optipng'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>optipng:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_OPTIPNG ) {
					$optipng_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'o' );
					if ( ! $optipng_version ) {
						$optipng_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_OPTIPNG, 'ob' );
					}
				}
				if ( ! empty( $optipng_version ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;' . esc_html__('version', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $optipng_version . "<br />\n"; 
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
					$collapsible = false;
				}
			}
			if ( ! $skip['pngout'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>pngout:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_PNGOUT ) {
					$pngout_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGOUT, 'p' );
					if ( ! $pngout_version ) {
						$pngout_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGOUT, 'pb' );
					}
				}
				if ( ! empty( $pngout_version ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__( 'Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</span>&emsp;' . esc_html__( 'version', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ': ' . preg_replace( '/PNGOUT \[.*\)\s*?/', '', $pngout_version ) . "<br />\n";
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;' . esc_html__('Install', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' <a href="admin.php?action=ewww_image_optimizer_install_pngout">' . esc_html__('automatically', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a> | <a href="http://advsys.net/ken/utils.htm">' . esc_html__('manually', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</a> - ' . esc_html__('Pngout is free closed-source software that can produce drastically reduced filesizes for PNGs, but can be very time consuming to process images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "<br />\n"; 
					$collapsible = false;
				}
			}
			if ( $skip['pngout'] && ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 10 ) {
					$output[] = '<b>pngout:</b> ' . esc_html__( 'Not installed, enable in Advanced Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "<br />\n";
			}
			if ( ! $skip['gifsicle'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>gifsicle:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_GIFSICLE ) {
					$gifsicle_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'g' );
					if ( ! $gifsicle_version ) {
						$gifsicle_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_GIFSICLE, 'gb' );
					}
				}
				if ( ! empty( $gifsicle_version ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;' . esc_html__('version', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $gifsicle_version . "<br />\n";
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
					$collapsible = false;
				}
			}
			if ( ! $skip['pngquant'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>pngquant:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_PNGQUANT ) {
					$pngquant_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGQUANT, 'q' );
					if ( ! $pngquant_version ) {
						$pngquant_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_PNGQUANT, 'qb' );
					}
				}
				if ( ! empty( $pngquant_version ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;' . esc_html__('version', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $pngquant_version . "<br />\n"; 
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
					$collapsible = false;
				}
			}
			if ( EWWW_IMAGE_OPTIMIZER_WEBP && ! $skip['webp'] && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$output[] = "<b>webp:</b> ";
				if ( EWWW_IMAGE_OPTIMIZER_WEBP ) {
					$webp_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_WEBP, 'w' );
					if ( ! $webp_version ) {
						$webp_version = ewww_image_optimizer_tool_found( EWWW_IMAGE_OPTIMIZER_WEBP, 'wb' );
					}
				}
				if ( ! empty( $webp_version ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__( 'Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</span>&emsp;' . esc_html__( 'version', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ': ' . $webp_version . "<br />\n"; 
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
					$collapsible = false;
				}
			}
			if ( ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				if ( ewww_image_optimizer_safemode_check() ) {
					$output[] = 'safe mode: <span style="color: red; font-weight: bolder">' . esc_html__('On', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
					$collapsible = false;
				} else {
					$output[] = 'safe mode: <span style="color: green; font-weight: bolder">' . esc_html__('Off', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
				}
				if ( ewww_image_optimizer_exec_check() ) {
					$output[] = 'exec(): <span style="color: red; font-weight: bolder">' . esc_html__('Disabled', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
					$collapsible = false;
				} else {
					$output[] = 'exec(): <span style="color: green; font-weight: bolder">' . esc_html__('Enabled', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
				}
				$output[] = "<br />\n";
				$output[] = wp_kses( sprintf( __( "%s only need one, used for conversion, not optimization", EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<b>' . __( 'Graphics libraries', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</b> - ' ), array( 'b' => array() ) );
				$output[] = '<br>';
				$toolkit_found = false;
				if ( ewww_image_optimizer_gd_support() ) {
					$output[] = 'GD: <span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$toolkit_found = true;
				} else {
					$output[] = 'GD: <span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				$output[] = '</span>&emsp;&emsp;' .
					"Gmagick: ";
				if ( ewww_image_optimizer_gmagick_support() ) {
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$toolkit_found = true;
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				$output[] = '</span>&emsp;&emsp;' .
					"Imagick: ";
				if ( ewww_image_optimizer_imagick_support() ) {
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN);
					$toolkit_found = true;
				} else {
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN);
				}
				$output[] = "</span>"; //&emsp;&emsp;Imagemagick 'convert': ";
/*				if ( 'WINNT' == PHP_OS && ewww_image_optimizer_find_win_binary( 'convert', 'i' ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>';
					$toolkit_found = true;
				} elseif ( 'WINNT' != PHP_OS && ewww_image_optimizer_find_nix_binary( 'convert', 'i' ) ) { 
					$output[] = '<span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>';
					$toolkit_found = true;
				} else { 
					$output[] = '<span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>';
				}*/
				if ( ! $toolkit_found && ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) || ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) ) ) {
					$collapsible = false;
				}
				$output[] = "<br />\n";
			}
			$output[] = '<b>' . esc_html__('Only need one of these:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' </b><br>';
			// initialize this variable to check for the 'file' command if we don't have any php libraries we can use
			$file_command_check = true;
			if ( function_exists( 'finfo_file' ) ) {
				$output[] = 'finfo: <span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
				$file_command_check = false;
			} else {
				$output[] = 'finfo: <span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
			}
			if ( function_exists( 'getimagesize' ) ) {
				$output[] = 'getimagesize(): <span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
				if ( ewww_image_optimizer_full_cloud() || EWWW_IMAGE_OPTIMIZER_NOEXEC || PHP_OS == 'WINNT' ) {
					$file_command_check = false;
				}
			} else {
				$output[] = 'getimagesize(): <span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span>&emsp;&emsp;';
			}
			if (function_exists('mime_content_type')) {
				$output[] = 'mime_content_type(): <span style="color: green; font-weight: bolder">' . esc_html__('Installed', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
				$file_command_check = false;
			} else {
				$output[] = 'mime_content_type(): <span style="color: red; font-weight: bolder">' . esc_html__('Missing', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />' . "\n";
			}
			if ( PHP_OS != 'WINNT' && ! ewww_image_optimizer_full_cloud() && ! EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				if ($file_command_check && !ewww_image_optimizer_find_nix_binary('file', 'f')) {
					$output[] = '<span style="color: red; font-weight: bolder">file: ' . esc_html__('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</span><br />';
					$collapsible = false;
				}
				if (!ewww_image_optimizer_find_nix_binary('nice', 'n')) {
					$output[] = '<span style="color: orange; font-weight: bolder">nice: ' . esc_html__('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' (' . esc_html__('not required', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ')</span><br />';
				}
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && ! $skip['pngout'] && PHP_OS != 'SunOS' && ! ewww_image_optimizer_find_nix_binary( 'tar', 't' ) ) {
					$output[] = '<span style="color: red; font-weight: bolder">tar: ' . esc_html__('command not found on your system', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' (' . esc_html__('required for automatic pngout installer', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ')</span><br />';
					$collapsible = false;
				}
			} elseif ( $file_command_check ) {
				$collapsible = false;
			}
			$output[] = '</div><!-- end .inside -->';
			if ( $collapsible ) {
				$output[] = "<script type='text/javascript'>\n" .
					"jQuery(document).ready(function($) {\n" .
					$collapsed .
					"$('#ewww-status-attention').hide();\n" .
					"$('#ewww-status-ok').show();\n" .
					"});\n" .
					"</script>\n";
			}
			$output[] = "</div></div></div>\n";
	$output[] = "<ul class='ewww-tab-nav'>\n" .
//		"<li class='ewww-tab ewww-cloud-nav'><span class='ewww-tab-hidden'><a class='ewww-cloud-nav' href='#'>" . esc_html__('Cloud Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></span></li>\n" .
		"<li class='ewww-tab ewww-general-nav'><span class='ewww-tab-hidden'><a class='ewww-general-nav' href='#'>" . esc_html__('Basic Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></span></li>\n" .
		"<li class='ewww-tab ewww-optimization-nav'><span class='ewww-tab-hidden'><a class='ewww-optimization-nav' href='#'>" .  esc_html__('Advanced Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></span></li>\n" .
		"<li class='ewww-tab ewww-conversion-nav'><span class='ewww-tab-hidden'><a class='ewww-conversion-nav' href='#'>" . esc_html__('Conversion Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></span></li>\n" .
		"<li class='ewww-tab ewww-webp-nav'><span class='ewww-tab-hidden'><a class='ewww-conversion-web' href='#'>" . esc_html__('WebP Settings', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></span></li>\n" .
	"</ul>\n";
			if ( is_multisite() && is_plugin_active_for_network(EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE_REL ) ) {
				$output[] = "<form method='post' action=''>\n";
			} else {
				$output[] = "<form method='post' action='options.php'>\n";
			}
			$output[] = "<input type='hidden' name='option_page' value='ewww_image_optimizer_options' />\n";
		        $output[] = "<input type='hidden' name='action' value='update' />\n";
		        $output[] = wp_nonce_field( "ewww_image_optimizer_options-options", '_wpnonce', true, false ) . "\n";
			$output[] = "<div id='ewww-general-settings'>\n";
			$output[] = "<table class='form-table'>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_cloud_key'>" . esc_html__( 'Cloud optimization API Key', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</label></th><td><input type='password' id='ewww_image_optimizer_cloud_key' name='ewww_image_optimizer_cloud_key' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) . "' size='32' /> " . esc_html__('API Key will be validated when you save your settings.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <a href='https://ewww.io/plans/'>" . esc_html__('Purchase an API key.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</a></td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_debug'>" . esc_html__('Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_debug' name='ewww_image_optimizer_debug' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_debug') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_jpegtran_copy'>" . esc_html__('Remove metadata', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><input type='checkbox' id='ewww_image_optimizer_jpegtran_copy' name='ewww_image_optimizer_jpegtran_copy' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_jpegtran_copy') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('This will remove ALL metadata: EXIF, comments, color profiles, and anything else that is not pixel data.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "remove metadata: " . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpegtran_copy' ) == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th>&nbsp;</th><td>";
				$output[] = "<p class='nocloud'>* <a href='https://ewww.io/plans/'>" . esc_html__( 'Purchase an API key to unlock additional compression levels below.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a></p>\n";
				$output[] = "<p class='description'>" . esc_html__( 'Lossless compression keeps the original quality of the image.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ' ' . esc_html__('While most users will not notice a difference in image quality, lossy means there IS a loss in image quality.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_jpg_level'>" . esc_html__('JPG Optimization Level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_jpg_level' name='ewww_image_optimizer_jpg_level'>\n" .
				"<option value='0'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ), 0, false ) . '>' . esc_html__( 'No Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n";
				if ( EWWW_IMAGE_OPTIMIZER_DOMAIN !== 'ewww-image-optimizer-cloud' ) {
					$output[] = "<option value='10'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ), 10, false ) . '>' . esc_html__( 'Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</option>\n";
				}
				$output[] = "<option $disable_level value='20'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ), 20, false ) . '>' . esc_html__( 'Maximum Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"<option $disable_level value='30'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ), 30, false ) . '>' . esc_html__( 'Lossy Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"<option $disable_level value='40'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ), 40, false ) . '>' . esc_html__( 'Maximum Lossy Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"</select></td></tr>\n";
				ewwwio_debug_message( "jpg level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level') );
				$output[] = "<tr><th><label for='ewww_image_optimizer_png_level'>" . esc_html__('PNG Optimization Level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_png_level' name='ewww_image_optimizer_png_level'>\n" .
				"<option value='0'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 0, false ) . '>' . esc_html__( 'No Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n";
				if ( EWWW_IMAGE_OPTIMIZER_DOMAIN !== 'ewww-image-optimizer-cloud' ) {
					$output[] = "<option value='10'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 10, false ) . '>' . esc_html__( 'Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</option>\n";
				}
				$output[] = "<option $disable_level value='20'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 20, false ) . '>' . esc_html__( 'Better Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"<option $disable_level value='30'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 30, false ) . '>' . esc_html__( 'Maximum Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"<option value='40'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 40, false ) . '>' . esc_html__( 'Lossy Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</option>\n" .
				"<option $disable_level value='50'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ), 50, false ) . '>' . esc_html__( 'Maximum Lossy Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"</select></td></tr>\n";
				ewwwio_debug_message( "png level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_png_level') );
				$output[] = "<tr><th><label for='ewww_image_optimizer_gif_level'>" . esc_html__('GIF Optimization Level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_gif_level' name='ewww_image_optimizer_gif_level'>\n" .
				"<option value='0'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_level' ), 0, false ) . '>' . esc_html__( 'No Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"<option value='10'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_level' ), 10, false ) . '>' . esc_html__( 'Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</option>\n" .
				"</select></td></tr>\n";
				ewwwio_debug_message( "gif level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_gif_level') );
				$output[] = "<tr><th><label for='ewww_image_optimizer_pdf_level'>" . esc_html__('PDF Optimization Level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_pdf_level' name='ewww_image_optimizer_pdf_level'>\n" .
				"<option value='0'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level' ), 0, false ) . '>' . esc_html__( 'No Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"<option $disable_level value='10'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level' ), 10, false ) . '>' . esc_html__( 'Lossless Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"<option $disable_level value='20'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level' ), 20, false ) . '>' . esc_html__( 'Lossy Compression', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " *</option>\n" .
				"</select></td></tr>\n";
				ewwwio_debug_message( "pdf level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level') );
				$output[] = "<tr><th><label for='ewww_image_optimizer_delay'>" . esc_html__('Bulk Delay', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='text' id='ewww_image_optimizer_delay' name='ewww_image_optimizer_delay' size='5' value='" . ewww_image_optimizer_get_option('ewww_image_optimizer_delay') . "'> " . esc_html__('Choose how long to pause between images (in seconds, 0 = disabled)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "bulk delay: " . ewww_image_optimizer_get_option('ewww_image_optimizer_delay') );
//				$output[] = "<tr><th><label for='ewww_image_optimizer_backup_files'>" . esc_html__('Backup Originals', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_backup_files' name='ewww_image_optimizer_backup_files' value='true' " .
//					( ewww_image_optimizer_get_option('ewww_image_optimizer_backup_files') ? "checked='true'" : "" ) . " $disable_level > " . esc_html__( 'Store a copy of your original images on our secure server for 30 days. *Requires an active API key.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</td></tr>\n";
//				ewwwio_debug_message( "backup mode: " . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_backup_files' ) ? "on" : "off" ) );
	if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( 'api_secret' ) ) {
				$output[] = "<tr><th><label for='ewww_image_optimizer_enable_cloudinary'>" . esc_html__('Automatic Cloudinary upload', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_enable_cloudinary' name='ewww_image_optimizer_enable_cloudinary' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_enable_cloudinary') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "cloudinary upload: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_enable_cloudinary') == TRUE ? "on" : "off" ) );
	}
			$output[] = "</table>\n</div>\n";
			$output[] = "<div id='ewww-optimization-settings'>\n";
			$output[] = "<table class='form-table'>\n";
			if ( ewww_image_optimizer_full_cloud() ) {
				$output[] = "<input id='ewww_image_optimizer_optipng_level' name='ewww_image_optimizer_optipng_level' type='hidden' value='2'>\n" .
					"<input id='ewww_image_optimizer_pngout_level' name='ewww_image_optimizer_pngout_level' type='hidden' value='2'>\n" .
					"<input id='ewww_image_optimizer_disable_pngout' name='ewww_image_optimizer_disable_pngout' type='hidden' value='true'/>\n";
			} else {
				$output[] = "<tr class='nocloud'><th><label for='ewww_image_optimizer_optipng_level'>" . esc_html__('optipng optimization level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_optipng_level' name='ewww_image_optimizer_optipng_level'>\n" .
				"<option value='1'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level'), 1, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1) . ': ' . sprintf(esc_html__('%d trial', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1) . "</option>\n" .
				"<option value='2'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level'), 2, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 2) . ': ' . sprintf(esc_html__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 8) . "</option>\n" .
				"<option value='3'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level'), 3, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 3) . ': ' . sprintf(esc_html__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 16) . "</option>\n" .
				"<option value='4'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level'), 4, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 4) . ': ' . sprintf(esc_html__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 24) . "</option>\n" .
				"<option value='5'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level'), 5, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 5) . ': ' . sprintf(esc_html__('%d trials', EWWW_IMAGE_OPTIMIZER_DOMAIN), 48) . "</option>\n" .
				"</select> (" . esc_html__('default', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "=2)</span>\n" .
				"<p class='description'>" . esc_html__( 'Levels 4 and above are unlikely to yield any additional savings.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				ewwwio_debug_message( "optipng level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_optipng_level') );
				$output[] = "<tr class='nocloud'><th><label for='ewww_image_optimizer_disable_pngout'>" . esc_html__('disable', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " pngout</label></th><td><input type='checkbox' id='ewww_image_optimizer_disable_pngout' name='ewww_image_optimizer_disable_pngout' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') == TRUE  ? "checked='true'" : "" ) . " /></td><tr>\n";
				ewwwio_debug_message( "pngout disabled: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_disable_pngout') == TRUE ? "yes" : "no" ) );
				$output[] = "<tr class='nocloud'><th><label for='ewww_image_optimizer_pngout_level'>" . esc_html__('pngout optimization level', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th>\n" .
				"<td><span><select id='ewww_image_optimizer_pngout_level' name='ewww_image_optimizer_pngout_level'>\n" .
				"<option value='0'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level'), 0, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 0) . ': ' . esc_html__('Xtreme! (Slowest)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"<option value='1'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level'), 1, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 1) . ': ' . esc_html__('Intense (Slow)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"<option value='2'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level'), 2, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 2) . ': ' . esc_html__('Longest Match (Fast)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"<option value='3'" . selected( ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level'), 3, false ) . '>' . sprintf(esc_html__('Level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 3) . ': ' . esc_html__('Huffman Only (Faster)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</option>\n" .
				"</select> (" . esc_html__('default', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "=2)</span>\n" .
				"<p class='description'>" . sprintf(esc_html__('If you have CPU cycles to spare, go with level %d', EWWW_IMAGE_OPTIMIZER_DOMAIN), 0) . "</p></td></tr>\n";
				ewwwio_debug_message( "pngout level: " . ewww_image_optimizer_get_option('ewww_image_optimizer_pngout_level') );
			}
			//	$output[] = "<tr><th><label for='ewww_image_optimizer_defer'>" . esc_html__( 'Deferred Optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_defer' name='ewww_image_optimizer_defer' value='true' " . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_defer' ) == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__( 'Optimize images later via wp_cron, after image upload or generation is complete.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</td></tr>\n";
			//	ewwwio_debug_message( "deferred optimization: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_defer') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><span><label for='ewww_image_optimizer_jpg_quality'>" . esc_html__('JPG quality level:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='text' id='ewww_image_optimizer_jpg_quality' name='ewww_image_optimizer_jpg_quality' class='small-text' value='" . ewww_image_optimizer_jpg_quality() . "' /> " . esc_html__('Valid values are 1-100.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "\n<p class='description'>" . esc_html__( 'Use this to override the default WordPress quality level of 82. This only applies to edited images, resizes, and converted PNG files. Original images are uploaded unmodified.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_parallel_optimization'>" . esc_html__('Parallel optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_parallel_optimization' name='ewww_image_optimizer_parallel_optimization' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_parallel_optimization') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('All resizes generated from a single upload are optimized in parallel for faster optimization. If this is causing performance issues, disable parallel optimization to reduce the load on your server.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "parallel optimization: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_parallel_optimization') == TRUE ? "on" : "off" ) );
				ewwwio_debug_message( "background optimization: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_background_optimization') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_auto'>" . esc_html__('Scheduled optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_auto' name='ewww_image_optimizer_auto' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('This will enable scheduled optimization of unoptimized images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "scheduled optimization: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_auto') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_include_media_paths'>" . esc_html__('Include Media Library Folders', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_include_media_paths' name='ewww_image_optimizer_include_media_paths' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_include_media_paths') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('Include the latest two folders from the Media Library in Scheduled Optimization and Optimize Everything Else.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "include media library: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_include_media_paths') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_aux_paths'>" . esc_html__('Folders to optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td>" . sprintf(esc_html__('One path per line, must be within %s. Use full paths, not relative paths.', EWWW_IMAGE_OPTIMIZER_DOMAIN), ABSPATH) . "<br>\n";
				$output[] = "<textarea id='ewww_image_optimizer_aux_paths' name='ewww_image_optimizer_aux_paths' rows='3' cols='60'>" . ( ( $aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) ? implode( "\n", $aux_paths ) : "" ) . "</textarea>\n";
				$output[] = "<p class='description'>" . esc_html__( 'Provide paths containing images to be optimized using "Scan and Optimize" on the Bulk Optimize page or by Scheduled Optimization.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p></td></tr>\n";
				if ( ewww_image_optimizer_iterable( $aux_paths ) ) {
					ewwwio_debug_message( "folders to optimize:" );
					foreach ( $aux_paths as $aux_path ) {
						ewwwio_debug_message( $aux_path );
					}
				}

				$output[] = "<tr><th><label for='ewww_image_optimizer_exclude_paths'>" . esc_html__('Folders to ignore', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td>" . sprintf(esc_html__('One path per line, partial paths allowed, but no urls.', EWWW_IMAGE_OPTIMIZER_DOMAIN), ABSPATH) . "<br>\n";
				$output[] = "<textarea id='ewww_image_optimizer_exclude_paths' name='ewww_image_optimizer_exclude_paths' rows='3' cols='60'>" . ( ( $exclude_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_exclude_paths' ) ) ? implode( "\n", $exclude_paths ) : "" ) . "</textarea>\n";
				$output[] = "<p class='description'>" . esc_html__( 'A file that matches any pattern or path provided will not be optimized.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p></td></tr>\n";
				if ( ewww_image_optimizer_iterable( $exclude_paths ) ) {
					ewwwio_debug_message( "folders to ignore:" );
					foreach ( $exclude_paths as $exclude_path ) {
						ewwwio_debug_message( $exclude_path );
					}
				}

//				$output[] = "<tr><th><label for='ewww_image_optimizer_resize_detection'>" . esc_html__( 'Resize Detection', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_resize_detection' name='ewww_image_optimizer_resize_detection' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_resize_detection') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__( 'Will highlight images that need to be resized because the browser is scaling them down. Only visible for Admin users and adds a button to the admin bar to detect scaled images that have been lazy loaded.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</td></tr>\n";
//				ewwwio_debug_message( 'resize detection: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) == TRUE ? 'on' : 'off' ) );
				
				if ( function_exists( 'imsanity_get_max_width_height' ) ) {
					$output[] = '<tr><th>&nbsp;</th><td>';
					$output[] = '<p><span style="color: green">' . esc_html__( '*Imsanity settings override the EWWW resize dimensions.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</span></p></td></tr>\n";
				}
				$output[] = "<tr><th>" . esc_html__( 'Resize Media Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</th><td><label for='ewww_image_optimizer_maxmediawidth'>" . esc_html__( 'Max Width', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediawidth' name='ewww_image_optimizer_maxmediawidth' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) . "' /> <label for='ewww_image_optimizer_maxmediaheight'>" . esc_html__( 'Max Height', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxmediaheight' name='ewww_image_optimizer_maxmediaheight' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) . "' /> " . esc_html__( 'in pixels', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "\n" .
				"<p class='description'>" . esc_html__('Resizes images uploaded directly to the Media Library and those uploaded within a post or page.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .
				"</td></tr>\n";
				ewwwio_debug_message( "max media dimensions: " . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediawidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxmediaheight' ) );
				$output[] = "<tr><th>" . esc_html__( 'Resize Other Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</th><td><label for='ewww_image_optimizer_maxotherwidth'>" . esc_html__( 'Max Width', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxotherwidth' name='ewww_image_optimizer_maxotherwidth' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) . "' /> <label for='ewww_image_optimizer_maxotherheight'>" . esc_html__( 'Max Height', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> <input type='number' step='1' min='0' class='small-text' id='ewww_image_optimizer_maxotherheight' name='ewww_image_optimizer_maxotherheight' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' ) . ( function_exists( 'imsanity_get_max_width_height' ) ? "' disabled='disabled" : '' ) . "' /> " . esc_html__( 'in pixels', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "\n" .
				"<p class='description'>" . esc_html__('Resizes images uploaded indirectly to the Media Library, like theme images or front-end uploads.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .
				"</td></tr>\n";
				ewwwio_debug_message( "max other dimensions: " . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherwidth' ) . ' x ' . ewww_image_optimizer_get_option( 'ewww_image_optimizer_maxotherheight' ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_resize_existing'>" . esc_html__( 'Resize Existing Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_resize_existing' name='ewww_image_optimizer_resize_existing' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_resize_existing') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__( 'Allow resizing of existing Media Library images.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( 'resize existing images: ' . ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_existing' ) == TRUE ? 'on' : 'off' ) );

				$output[] = "<tr><th>" . esc_html__( 'Disable Resizes', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</th><td><p>" . esc_html__( 'Wordpress, your theme, and other plugins generate various image sizes. You may disable optimization for certain sizes, or completely prevent those sizes from being created.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>\n";
				$image_sizes = ewww_image_optimizer_get_image_sizes();
				$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes' );
				$disabled_sizes_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
				$output[] = '<table><tr><th>' . esc_html__( 'Disable Optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th><th>' . esc_html__( 'Disable Creation', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</th></tr>\n";
				ewwwio_debug_message( 'disabled resizes:' );
				foreach ( $image_sizes as $size => $dimensions ) {
					if ( $size == 'thumbnail' ) {
						$output [] = "<tr><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[$size] ) ? "checked='true'" : "" ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' disabled /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
					} elseif ( $size == 'pdf-full' ) {
						$output [] = "<tr><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[$size] ) ? "checked='true'" : "" ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[$size] ) ? "checked='true'" : "" ) . " /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - <span class='description'>" . esc_html__( 'Disabling creation of the full-size preview for PDF files will disable all PDF preview sizes', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</span></label></td></tr>\n";
					} else {
						$output [] = "<tr><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_opt_$size' name='ewww_image_optimizer_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[$size] ) ? "checked='true'" : "" ) . " /></td><td><input type='checkbox' id='ewww_image_optimizer_disable_resizes_$size' name='ewww_image_optimizer_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[$size] ) ? "checked='true'" : "" ) . " /></td><td><label for='ewww_image_optimizer_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
					}
					ewwwio_debug_message( $size . ': ' . ( ! empty( $disabled_sizes_opt[$size] ) ? "optimization=disabled " : "optimization=enabled " ) . ( ! empty( $disabled_sizes[$size] ) ? "creation=disabled" : "creation=enabled" ) );
				}
				$output[] = "</table>\n";
				$output[] = "</td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_skip_size'>" . esc_html__( 'Skip Small Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</label></th><td><input type='text' id='ewww_image_optimizer_skip_size' name='ewww_image_optimizer_skip_size' size='8' value='" . ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) . "'> " . esc_html__( 'Do not optimize images smaller than this (in bytes)', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</td></tr>\n";
				ewwwio_debug_message( "skip images smaller than: " . ewww_image_optimizer_get_option('ewww_image_optimizer_skip_size') . " bytes" );
				$output[] = "<tr><th><label for='ewww_image_optimizer_skip_png_size'>" . esc_html__('Skip Large PNG Images', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='text' id='ewww_image_optimizer_skip_png_size' name='ewww_image_optimizer_skip_png_size' size='8' value='" . ewww_image_optimizer_get_option('ewww_image_optimizer_skip_png_size') . "'> " . esc_html__('Do not optimize PNG images larger than this (in bytes)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "skip PNG images larger than: " . ewww_image_optimizer_get_option('ewww_image_optimizer_skip_png_size') . " bytes" );
				$output[] = "<tr><th><label for='ewww_image_optimizer_lossy_skip_full'>" . esc_html__('Exclude full-size images from lossy optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_lossy_skip_full' name='ewww_image_optimizer_lossy_skip_full' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full') == TRUE ? "checked='true'" : "" ) . " /></td></tr>\n";
				ewwwio_debug_message( "exclude originals from lossy: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_lossy_skip_full') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_metadata_skip_full'>" . esc_html__('Exclude full-size images from metadata removal', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_metadata_skip_full' name='ewww_image_optimizer_metadata_skip_full' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_metadata_skip_full') == TRUE ? "checked='true'" : "" ) . " /></td></tr>\n";
				ewwwio_debug_message( "exclude originals from metadata removal: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_metadata_skip_full') == TRUE ? "on" : "off" ) );
				$output[] = "<tr class='nocloud'><th><label for='ewww_image_optimizer_skip_bundle'>" . esc_html__('Use System Paths', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_skip_bundle' name='ewww_image_optimizer_skip_bundle' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle') == TRUE ? "checked='true'" : "" ) . " /> " . sprintf(esc_html__('If you have already installed the utilities in a system location, such as %s or %s, use this to force the plugin to use those versions and skip the auto-installers.', EWWW_IMAGE_OPTIMIZER_DOMAIN), '/usr/local/bin', '/usr/bin') . "</td></tr>\n";
				ewwwio_debug_message( "use system binaries: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_skip_bundle') == TRUE ? "yes" : "no" ) );
			$output[] = "</table>\n</div>\n";
			$output[] = "<div id='ewww-conversion-settings'>\n";
			$output[] = "<p>" . esc_html__('Conversion is only available for images in the Media Library (except WebP). By default, all images have a link available in the Media Library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "<br />\n" .
				"<b>" . esc_html__('NOTE:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b> " . esc_html__('The plugin will attempt to update image locations for any posts that contain the images. You may still need to manually update locations/urls for converted images.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "\n" .
			"</p>\n";
			$output[] = "<table class='form-table'>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_disable_convert_links'>" . esc_html__('Hide Conversion Links', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label</th><td><input type='checkbox' id='ewww_image_optimizer_disable_convert_links' name='ewww_image_optimizer_disable_convert_links' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_disable_convert_links') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_delete_originals'>" . esc_html__('Delete originals', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><input type='checkbox' id='ewww_image_optimizer_delete_originals' name='ewww_image_optimizer_delete_originals' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('This will remove the original image from the server after a successful conversion.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</td></tr>\n";
				ewwwio_debug_message( "delete originals: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_delete_originals') == TRUE ? "on" : "off" ) );
//				$output[] = "<tr><th><label for='ewww_image_optimizer_webp_cdn_path'>" . esc_html__('WebP CDN URL', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_webp_cdn_path' name='ewww_image_optimizer_webp_cdn_path' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_for_cdn') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('Uses output buffering and libxml functionality from PHP. Use this if the Apache rewrite rules do not work, or if your images are served from a CDN.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .  "</span></td></tr>";
				$output[] = "<tr><th><label for='ewww_image_optimizer_jpg_to_png'>" . sprintf(esc_html__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'JPG', 'PNG') . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_jpg_to_png' name='ewww_image_optimizer_jpg_to_png' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') == TRUE ? "checked='true'" : "" ) . " /> <b>" . esc_html__('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b> " . esc_html__('Removes metadata and increases cpu usage dramatically.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>\n" .
				"<p class='description'>" . esc_html__('PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				ewwwio_debug_message( "jpg2png: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_to_png') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_png_to_jpg'>" . sprintf(esc_html__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'PNG', 'JPG') . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_png_to_jpg' name='ewww_image_optimizer_png_to_jpg' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') == TRUE ? "checked='true'" : "" ) . " /> <b>" . esc_html__('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b> " . esc_html__('This is not a lossless conversion.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>\n" .
				"<p class='description'>" . esc_html__('JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>\n" .
				"<span><label for='ewww_image_optimizer_jpg_background'> " . esc_html__('JPG background color:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> #<input type='text' id='ewww_image_optimizer_jpg_background' name='ewww_image_optimizer_jpg_background' size='6' value='" . ewww_image_optimizer_jpg_background() . "' /> <span style='padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #" . ewww_image_optimizer_jpg_background() . "'>&nbsp;</span> " . esc_html__('HEX format (#123def)', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ".</span>\n" .
				"<p class='description'>" . esc_html__('Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
			//	"<span><label for='ewww_image_optimizer_jpg_quality'>" . esc_html__('JPG quality level:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label> <input type='text' id='ewww_image_optimizer_jpg_quality' name='ewww_image_optimizer_jpg_quality' class='small-text' value='" . ewww_image_optimizer_jpg_quality() . "' /> " . esc_html__('Valid values are 1-100.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>\n" .
//				"<p class='description'>" . esc_html__('If JPG quality is blank, the plugin will attempt to set the optimal quality level or default to 92. Remember, this is a lossy conversion, so you are losing pixels, and it is not recommended to actually set the level here unless you want noticable loss of image quality.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				ewwwio_debug_message( "png2jpg: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_png_to_jpg') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_gif_to_png'>" . sprintf(esc_html__('enable %s to %s conversion', EWWW_IMAGE_OPTIMIZER_DOMAIN), 'GIF', 'PNG') . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_gif_to_png' name='ewww_image_optimizer_gif_to_png' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('No warnings here, just do it.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</span>\n" .
				"<p class='description'> " . esc_html__('PNG is generally better than GIF, but animated images cannot be converted.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p></td></tr>\n";
				ewwwio_debug_message( "gif2png: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_to_png') == TRUE ? "on" : "off" ) );
			$output[] = "</table>\n</div>\n";
			$output[] = "<div id='ewww-webp-settings'>\n";
			$output[] = "<table class='form-table'>\n";
				$output[] = "<tr><th><label for='ewww_image_optimizer_webp'>" . esc_html__('JPG/PNG to WebP', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_webp' name='ewww_image_optimizer_webp' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp') == TRUE ? "checked='true'" : "" ) . " /> <b>" . esc_html__('WARNING:', EWWW_IMAGE_OPTIMIZER_DOMAIN) . '</b> ' . esc_html__('JPG to WebP conversion is lossy, but quality loss is minimal. PNG to WebP conversion is lossless.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .  "</span>\n" .
				"<p class='description'>" . esc_html__('Originals are never deleted, and WebP images should only be served to supported browsers.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . " <a href='#webp-rewrite'>" .  ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ? esc_html__('You can use the rewrite rules below to serve WebP images with Apache.', EWWW_IMAGE_OPTIMIZER_DOMAIN) : '' ) . "</a></td></tr>\n";
				ewwwio_debug_message( "webp conversion: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp') == TRUE ? "on" : "off" ) );
				$output[] = "<tr><th><label for='ewww_image_optimizer_webp_force'>" . esc_html__('Force WebP', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_webp_force' name='ewww_image_optimizer_webp_force' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_force') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('WebP images will be generated and saved for all JPG/PNG images regardless of their size. The Alternative WebP Rewriting will not check if a file exists, only that the domain matches the home url.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .  "</span></td></tr>\n";
				ewwwio_debug_message( "forced webp: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_force') == TRUE ? "on" : "off" ) );	
//				ewwwio_debug_message( "webp paths: " . esc_attr( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_paths') ) );
				if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
					$output[] = "<tr><th><label for='ewww_image_optimizer_webp_paths'>" . esc_html__('WebP URLs', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td>" . esc_html__('If Force WebP is enabled, enter URL patterns that should be permitted for Alternative WebP Rewriting. One pattern per line, may be partial URLs, but must include the domain name.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "<br>";
					$output[] = "<textarea id='ewww_image_optimizer_webp_paths' name='ewww_image_optimizer_webp_paths' rows='3' cols='60'>" . ( ( $webp_paths = ewww_image_optimizer_get_option('ewww_image_optimizer_webp_paths') ) ? esc_html( implode( "\n", $webp_paths ) ) : "" ) . "</textarea></td></tr>\n";
					if ( ewww_image_optimizer_iterable( $webp_paths ) ) {
						ewwwio_debug_message( "webp paths:" );
						foreach ( $webp_paths as $webp_path ) {
							ewwwio_debug_message( $webp_path );
						}
					}
					$output[] = "<tr><th><label for='ewww_image_optimizer_webp_for_cdn'>" . esc_html__('Alternative WebP Rewriting', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</label></th><td><span><input type='checkbox' id='ewww_image_optimizer_webp_for_cdn' name='ewww_image_optimizer_webp_for_cdn' value='true' " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_for_cdn') == TRUE ? "checked='true'" : "" ) . " /> " . esc_html__('Uses output buffering and libxml functionality from PHP. Use this if the Apache rewrite rules do not work, or if your images are served from a CDN.', EWWW_IMAGE_OPTIMIZER_DOMAIN) .  ' ' . sprintf( esc_html__( 'Sites using a CDN may also use the WebP option in the %s plugin.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), '<a href="https://wordpress.org/plugins/cache-enabler/">Cache Enabler</a>' ). "</span></td></tr>";
				}
				ewwwio_debug_message( "alt webp rewriting: " . ( ewww_image_optimizer_get_option('ewww_image_optimizer_webp_for_cdn') == TRUE ? "on" : "off" ) );
			$output[] = "</table>\n</div>\n";
			$output[] = "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__('Save Changes', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "' /></p>\n";
		$output[] = "</form>\n";
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() ) {
		$output[] = "<form id='ewww-webp-rewrite'>\n";
			$output[] = "<p>" . esc_html__('There are many ways to serve WebP images to visitors with supported browsers. You may choose any you wish, but it is recommended to serve them with an .htaccess file using mod_rewrite and mod_headers. The plugin can insert the rules for you if the file is writable, or you can edit .htaccess yourself.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>\n";
			if ( ! ewww_image_optimizer_webp_rewrite_verify() ) {
				$output[] = "<img id='webp-image' src='" . plugins_url('/images/test.png', __FILE__) . "' style='float: right; padding: 0 0 10px 10px;'>\n" .
				"<p id='ewww-webp-rewrite-status'><b>" . esc_html__('Rules verified successfully', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</b></p>\n";
				ewwwio_debug_message( 'webp .htaccess rewriting enabled' );
			} else {
				$output[] = "<pre id='webp-rewrite-rules' style='background: white; font-color: black; border: 1px solid black; clear: both; padding: 10px;'>\n" .
					"&lt;IfModule mod_rewrite.c&gt;\n" .
					"RewriteEngine On\n" .
					"RewriteCond %{HTTP_ACCEPT} image/webp\n" .
					"RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$\n" .
					"RewriteCond %{REQUEST_FILENAME}\.webp -f\n" .
					"RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_FILENAME}.webp [T=image/webp,E=accept:1]\n" .
					"&lt;/IfModule&gt;\n" .
					"&lt;IfModule mod_headers.c&gt;\n" .
					"Header append Vary Accept env=REDIRECT_accept\n" .
					"&lt;/IfModule&gt;\n" .
					"AddType image/webp .webp</pre>\n" .
					"<img id='webp-image' src='" . plugins_url('/images/test.png', __FILE__) . "' style='float: right; padding-left: 10px;'>\n" .
					"<p id='ewww-webp-rewrite-status'>" . esc_html__('The image to the right will display a WebP image with WEBP in white text, if your site is serving WebP images and your browser supports WebP.', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</p>\n" .
					"<button type='submit' class='button-secondary action'>" . esc_html__('Insert Rewrite Rules', EWWW_IMAGE_OPTIMIZER_DOMAIN) . "</button>\n";
				ewwwio_debug_message( 'webp .htaccess rules not detected' );

			}
		$output[] = "</form>\n";
		} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() ) {
				$test_webp_image = plugins_url('/images/test.png.webp', __FILE__);
				$test_png_image = plugins_url('/images/test.png', __FILE__);
				$output[] = "<noscript  data-img='$test_png_image' data-webp='$test_webp_image' data-style='float: right; padding: 0 0 10px 10px;' class='ewww_webp'><img src='$test_png_image' style='float: right; padding: 0 0 10px 10px;'></noscript>\n";
		}
		$output[] = "</div><!-- end container left -->\n";
		$output[] = "<div id='ewww-container-right' style='border: 1px solid #e5e5e5; float: right; margin-left: -215px; padding: 0em 1.5em 1em; background-color: #fff; box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04); display: inline-block; width: 174px;'>\n" .
			"<h2>" . esc_html__( 'Support EWWW I.O.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h2>\n" .
			"<p>" . esc_html__( 'Would you like to help support development of this plugin?', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>\n" .
			"<p><a href='https://www.gofundme.com/ewww-image-optimizer-mac-dev-laptop'>" . esc_html__( 'Keep Mac OS support alive, and help me buy a Mac.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a></p>\n" .
			"<p><a href='https://translate.wordpress.org/projects/wp-plugins/ewww-image-optimizer/'>" . esc_html__( 'Help translate EWWW I.O.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a></p>\n" .
			"<p><a href='https://wordpress.org/support/view/plugin-reviews/ewww-image-optimizer#postform'>" . esc_html__( 'Write a review.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a></p>\n" .
			"<p>" . wp_kses( sprintf( __( 'Contribute directly via %s.',  EWWW_IMAGE_OPTIMIZER_DOMAIN ), "<a href='https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&amp;hosted_button_id=MKMQKCBFFG3WW'>Paypal</a>" ), array( 'a' => array( 'href' => array() ) ) ) . "</p>\n" .
			"<p>" . esc_html__( 'Use any of these referral links to show your appreciation:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>\n" .
			"<p><b>" . esc_html__( 'Web Hosting:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</b><br>\n" .
				"<a href='http://www.a2hosting.com/?aid=b6322137'>A2 Hosting:</a> " . esc_html_x( 'with automatic EWWW IO setup', 'A2 Hosting:', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "<br>\n" .
				"<a href='http://www.bluehost.com/track/nosilver4u'>Bluehost</a><br>\n" .
				"<a href='http://www.dreamhost.com/r.cgi?132143'>Dreamhost</a>\n" .
			"</p>\n" .
			"<p><b>" . esc_html_x( 'VPS:', 'abbreviation for Virtual Private Server', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</b><br>\n" .
				"<a href='https://www.digitalocean.com/?refcode=89ef0197ec7e'>DigitalOcean</a><br>\n" .
				"<a href='https://clientarea.ramnode.com/aff.php?aff=1469'>RamNode</a><br>\n" .
			"</p>\n" .
			"<p><b>" . esc_html_x( 'CDN:', 'abbreviation for Content Delivery Network', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</b><br><a target='_blank' href='http://tracking.maxcdn.com/c/91625/36539/378'>" . esc_html__( 'Add MaxCDN to increase website speeds dramatically! Sign Up Now and Save 25%.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> " . esc_html__( 'Integrate MaxCDN within Wordpress using the W3 Total Cache plugin.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</p>\n" .
		"</div>\n" .
	"</div>\n";
	ewwwio_debug_message( 'max_execution_time: ' . ini_get('max_execution_time') );
	ewww_image_optimizer_stl_check();
	if ( ! ewww_image_optimizer_function_exists( 'sleep' ) ) {
		ewwwio_debug_message( 'sleep disabled' );
	}
	ewwwio_check_memory_available();
	echo apply_filters( 'ewww_image_optimizer_settings', $output );
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) && ! ewww_image_optimizer_ce_webp_enabled() ) {
		ewww_image_optimizer_webp_inline_script();
	}

	//ewwwio_debug_version_info();
	if ( ewww_image_optimizer_get_option ( 'ewww_image_optimizer_debug' ) ) {
		?>
<script type="text/javascript">
    function selectText(containerid) {
        if (document.selection) {
            var range = document.body.createTextRange();
            range.moveToElementText(document.getElementById(containerid));
            range.select();
        } else if (window.getSelection) {
            var range = document.createRange();
            range.selectNode(document.getElementById(containerid));
            window.getSelection().addRange(range);
        }
    }
</script>
		<?php
		global $ewww_debug;
		$debug_log_url = plugins_url('/debug.log', __FILE__);
		echo '<p style="clear:both"><b>' . esc_html__( 'Debugging Information', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . ':</b> <button onclick="selectText(' . "'ewww-debug-info'" . ')">' . esc_html__( 'Select All', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</button>';
		if ( is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
			echo "&emsp;<a href='$debug_log_url'>" . esc_html( 'View Debug Log', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</a> - <a href='admin.php?action=ewww_image_optimizer_delete_debug_log'>" . esc_html( 'Remove Debug Log', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</a>';
		}
		echo '</p>';
		echo '<div id="ewww-debug-info" style="background:#ffff99;margin-left:-20px;padding:10px" contenteditable="true">' . $ewww_debug . '</div>';
	}
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
}

function ewww_image_optimizer_filter_settings_page( $input ) {
	$output = '';
	foreach ( $input as $line ) {
		if ( ewww_image_optimizer_full_cloud() && preg_match( "/class='nocloud'/", $line ) ) {
			continue;
		} else {
			$output .= $line;
		}
	}
	ewwwio_memory( __FUNCTION__ );
	return $output;
}

// used to detect scaled images within the page, only enabled for admins
function ewww_image_optimizer_resize_detection_script() {
	if ( ! is_super_admin() || 'wp-login.php' == basename( $_SERVER['SCRIPT_NAME'] ) ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		wp_enqueue_script( 'ewww-resize-detection', plugins_url( '/includes/resize_detection.js', __FILE__ ), array(), EWWW_IMAGE_OPTIMIZER_VERSION );
	}
}

// checks if admin bar is visible, and then adds the admin_bar_menu action
function ewww_image_optimizer_admin_bar_init() {
	if ( ! is_super_admin() || ! is_admin_bar_showing() || 'wp-login.php' == basename( $_SERVER['SCRIPT_NAME'] ) || is_admin() ) {
		return;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_resize_detection' ) ) {
		add_action( 'admin_bar_menu', 'ewww_image_optimizer_admin_bar_menu', 99 );
	}
}

// add a resize detection button to the wp admin bar
function ewww_image_optimizer_admin_bar_menu() {
	global $wp_admin_bar;
	$wp_admin_bar->add_menu( array(
		'id'     => 'resize-detection',
		'parent' => 'top-secondary',
		'title'  => __('Detect Scaled Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
	) );
}

function ewwwio_debug_message( $message ) {
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		$memory_limit = ewwwio_memory_limit();
		if ( strlen( $message ) + 4000000 + memory_get_usage( true ) <= $memory_limit ) {
			global $ewww_debug;
			global $ewww_version_dumped;
			if ( empty( $ewww_debug ) && empty( $ewww_version_dumped ) ) {
				ewwwio_debug_version_info();
				$ewww_version_dumped = true;
			}
			$ewww_debug .= "$message<br>";
		} else {
			global $ewww_debug;
			$ewww_debug = "not logging message, memory limit is $memory_limit";
		}
	}
}

// used to output debug messages to a logfile in the plugin folder in cases where output to the screen is a bad idea
function ewww_image_optimizer_debug_log() {
//	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_debug;
	if ( ! empty( $ewww_debug ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		$memory_limit = ewwwio_memory_limit();
		clearstatcache();
		$timestamp = date( 'y-m-d h:i:s.u' ) . "\n";
		if ( ! file_exists( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
			touch( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' );
		} else {
			if ( filesize( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) + 4000000 + memory_get_usage( true ) > $memory_limit ) {
				unlink( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' );
				touch( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' );
			}
		}
		if ( filesize( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) + strlen( $ewww_debug ) + 4000000 + memory_get_usage( true ) <= $memory_limit ) {
//		if ( ewwwio_check_memory_available( filesize( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) + strlen( $ewww_debug ) + 4000000 ) ) {
			$ewww_debug_log = str_replace( '<br>', "\n", $ewww_debug );
			file_put_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log', $timestamp . $ewww_debug_log, FILE_APPEND );
		}
	}
	$ewww_debug = '';
	ewwwio_memory( __FUNCTION__ );
}

function ewww_image_optimizer_delete_debug_log() {
	if ( is_file( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' ) ) {
		unlink( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'debug.log' );
	}
	$sendback = wp_get_referer();
	wp_redirect( esc_url_raw( $sendback) );
	exit;
}

function ewwwio_debug_version_info() {
//	$disabled = ini_get( 'disable_functions' );
//	if ( ! preg_match( '/get_current_user/', $disabled ) ) {
	global $ewww_debug;
	if ( ! extension_loaded( 'suhosin' ) && function_exists( 'get_current_user' ) ) {
		$ewww_debug .= get_current_user() . '<br>';
	}

	$ewww_debug .= 'EWWW IO version: ' . EWWW_IMAGE_OPTIMIZER_VERSION . '<br>';

	// check the WP version
	global $wp_version;
	$my_version = substr( $wp_version, 0, 3 );
	$ewww_debug .= "WP version: $wp_version<br>" ;

	if ( defined( 'PHP_VERSION_ID' ) ) {
		$ewww_debug .= 'PHP version: ' . PHP_VERSION_ID . '<br>';
	}
	if ( defined( 'LIBXML_VERSION' ) ) {
		$ewww_debug .= 'libxml version: ' . LIBXML_VERSION . '<br>';
	}
}

function ewwwio_debug_backtrace() {
	if ( defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) ) {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	} else {
		$backtrace = debug_backtrace( false );
	}
	array_shift( $backtrace );
	array_shift( $backtrace );
	return maybe_serialize( $backtrace );
}

function ewww_image_optimizer_dynamic_image_debug() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	echo "<div class='wrap'><h1>" . esc_html__( 'Dynamic Image Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h1>";
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$debug_images = $ewwwdb->get_results( "SELECT path,updates,updated,trace FROM $ewwwdb->ewwwio_images WHERE trace IS NOT NULL ORDER BY updates DESC LIMIT 100" );
	if ( count( $debug_images ) != 0 ) {
		foreach ( $debug_images as $image ) {
			$trace = unserialize( $image->trace );
			echo '<p><b>' . esc_html__( 'File path', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->path . '</b><br>';
			echo esc_html__( 'Number of attempted optimizations', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->updates . '<br>';
			echo esc_html__( 'Last attempted', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ': ' . $image->updated . '<br>';
			echo esc_html__( 'PHP trace', EWWW_IMAGE_OPTIMIZER_DOMAIN) . ':<br>';
			$i = 0;
			if ( is_array( $trace ) ) {
				foreach ( $trace as $function ) {
					if ( ! empty( $function['file'] ) && ! empty( $function['line'] ) ) {
						echo "#$i {$function['function']}() called at {$function['file']}:{$function['line']}<br>";
					} else {
						echo "#$i {$function['function']}() called<br>";
					}
					$i++;
				}
			} else {
				esc_html_e( 'Cannot display trace',  EWWW_IMAGE_OPTIMIZER_DOMAIN );
			}
			echo '</p>';
		}
	}
	echo '</div>';
	return;
}

function ewww_image_optimizer_image_queue_debug() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// let user clear a queue, or all queues
	if ( isset( $_POST['ewww_image_optimizer_clear_queue'] ) && current_user_can( 'manage_options' ) && wp_verify_nonce( $_POST['ewww_nonce'], 'ewww_image_optimizer_clear_queue' ) ) {
		if ( is_numeric( $_POST['ewww_image_optimizer_clear_queue'] ) ) {
			global $ewwwio_media_background;
			if ( ! class_exists( 'WP_Background_Process' ) ) {
				require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
			}
			if ( ! is_object( $ewwwio_media_background ) ) {
				$ewwwio_media_background = new EWWWIO_Media_Background_Process();
			}
			ewwwio_debug_message( "backgrounding optimization for $ID" );
			$queues = (int) $_POST['ewww_image_optimizer_clear_queue'];
			while( $queues ) {
				$ewwwio_media_background->cancel_process();
				$queues--;
			}
	       		if ( ! empty( $_POST['ids'] ) && preg_match( '/^[\d,]+$/', $_POST['ids'], $request_ids ) ) {
				$ids = explode( ',', $request_ids[0] );
				foreach( $ids as $id ) {
					delete_transient( 'ewwwio-background-in-progress-' . $id );
				}
			}
		} else {
			delete_site_option( sanitize_text_field( $_POST['ewww_image_optimizer_clear_queue'] ) );
	       		if ( ! empty( $_POST['ids'] ) && preg_match( '/^[\d,]+$/', $_POST['ids'], $request_ids ) ) {
				$ids = explode( ',', $request_ids[0] );
				foreach( $ids as $id ) {
					delete_transient( 'ewwwio-background-in-progress-' . $id );
				}
			}
		}
	}
	echo "<div class='wrap'><h1>" . esc_html__( 'Image Queue Debugging', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "</h1>";
	global $wpdb;

	$table        = $wpdb->options;
	$column       = 'option_name';
	$key_column   = 'option_id';
	$value_column = 'option_value';

	if ( is_multisite() ) {
		$table        = $wpdb->sitemeta;
		$column       = 'meta_key';
		$key_column   = 'meta_id';
		$value_column = 'meta_value';
	}

	$key = 'wp_ewwwio_media_optimize_batch_%';
	$queues = $wpdb->get_results( 
		$wpdb->prepare( "
			SELECT *
			FROM {$table}
			WHERE {$column} LIKE %s
			ORDER BY {$key_column} ASC
			", $key ),
		ARRAY_A );

	$nonce = wp_create_nonce( 'ewww_image_optimizer_clear_queue' );
	if ( empty( $queues ) ) {
		esc_html_e( 'Nothing to see here, go upload some images!', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$all_ids = array();
		foreach ( $queues as $queue ) {
			$ids = array();
			echo "<strong>{$queue[ $key_column ]}</strong> - {$queue[ $column ]}<br>";
			$items = maybe_unserialize( $queue[ $value_column ] );
			foreach ( $items as $item ) {
				echo "{$item['id']} - {$item['type']}<br>";
				$all_ids[] = $item['id'];
				$ids[] = $item['id']; 
			}
			$ids = implode( ',', $ids );
		?>	<form id="ewww-queue-clear-<?php echo $queue[ $key_column ]; ?>" method="post" style="margin-bottom: 1.5em;" action="">
			<input type="hidden" id="ewww_nonce" name="ewww_nonce" value="<?php echo $nonce; ?>">
			<input type="hidden" name="ewww_image_optimizer_clear_queue" value="<?php echo $queue[ $column ]; ?>">
			<input type="hidden" name="ids" value="<?php echo $ids; ?>">
			<button type="submit" class="button-secondary action"><?php esc_html_e( 'Clear this queue', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
		</form>
<?php		}
		$all_ids = implode( ',', $all_ids );
?>		<form id="ewww-queue-clear-all" method="post" style="margin: 2em 0;" action="">
			<input type="hidden" id="ewww_nonce" name="ewww_nonce" value="<?php echo $nonce; ?>">
			<input type="hidden" name="ewww_image_optimizer_clear_queue" value="<?php echo count( $queues ); ?>">
			<input type="hidden" name="ids" value="<?php echo $all_ids; ?>">
			<button type="submit" class="button-secondary action"><?php esc_html_e( 'Clear all queues', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
		</form>
<?php	}
}

function ewwwio_memory_limit() {
	if ( defined( 'EWWW_MEMORY_LIMIT' ) && EWWW_MEMORY_LIMIT ) {
		$memory_limit = EWWW_MEMORY_LIMIT;
	} elseif ( function_exists( 'ini_get' ) ) {
		$memory_limit = ini_get( 'memory_limit' );
	} else {
		if ( ! defined( 'EWWW_MEMORY_LIMIT' ) ) {
			$current_memory = memory_get_usage( true );
			$memory_limit = round( $current_memory / ( 1024 * 1024 ) ) + 16;
			define( 'EWWW_MEMORY_LIMIT', $memory_limit );
			// conservative default.
		//	$memory_limit = '64M';
		}
	}
	if ( ! $memory_limit || -1 === $memory_limit ) {
		// Unlimited, set to 32GB.
		$memory_limit = '32000M';
	}
	if ( strpos( $memory_limit, 'G' ) ) {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024 * 1024;
	} else {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024;
	}
	return $memory_limit;
}

// see if the current usage + padding will fit within the memory_limit defined by PHP. If not, return false, otherwise, proceed with true.
function ewwwio_check_memory_available( $padding = 1049000 ) {
	$memory_limit = ewwwio_memory_limit();

	$current_memory = memory_get_usage( true ) + $padding;
	if ( $current_memory >= $memory_limit ) {
	ewwwio_debug_message( "detected memory limit is not enough: $memory_limit" );
		return false;
	}
	ewwwio_debug_message( "detected memory limit is: $memory_limit" );
	return true;
}

function ewwwio_memory( $function ) {
	return;
	if ( WP_DEBUG ) {
		global $ewww_memory;
		$ewww_memory .= $function . ': ' . memory_get_usage(true) . "\n";
		ewwwio_memory_output();
	}
}

function ewwwio_memory_output() {
	if ( WP_DEBUG ) {
		global $ewww_memory;
		$timestamp = date( 'y-m-d h:i:s.u' ) . "  ";
		if ( ! file_exists( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log' ) )
			touch( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log' );
		file_put_contents( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'memory.log', $timestamp . $ewww_memory, FILE_APPEND );
		$ewww_memory = '';
	}
}
?>
