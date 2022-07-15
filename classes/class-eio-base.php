<?php
/**
 * Implements basic and common utility functions for all sub-classes.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EIO_Base' ) ) {
	/**
	 * HTML element and attribute parsing, replacing, etc.
	 */
	class EIO_Base {

		/**
		 * Content directory (URL) for the plugin to use.
		 *
		 * @access protected
		 * @var string $content_url
		 */
		protected $content_url = WP_CONTENT_URL . 'ewww/';

		/**
		 * Content directory (path) for the plugin to use.
		 *
		 * @access protected
		 * @var string $content_dir
		 */
		protected $content_dir = WP_CONTENT_DIR . '/ewww/';

		/**
		 * Site (URL) for the plugin to use.
		 *
		 * @access public
		 * @var string $site_url
		 */
		public $site_url = '';

		/**
		 * Home (URL) for the plugin to use.
		 *
		 * @access public
		 * @var string $home_url
		 */
		public $home_url = '';

		/**
		 * Allowed paths for URL mangling.
		 *
		 * @access protected
		 * @var array $allowed_urls
		 */
		protected $allowed_urls = array();

		/**
		 * Allowed domains for URL mangling.
		 *
		 * @access protected
		 * @var array $allowed_domains
		 */
		protected $allowed_domains = array();

		/**
		 * Plugin version for the plugin.
		 *
		 * @access protected
		 * @var float $version
		 */
		protected $version = 1.1;

		/**
		 * Prefix to be used by plugin in option and hook names.
		 *
		 * @access protected
		 * @var string $prefix
		 */
		protected $prefix = 'ewww_image_optimizer_';

		/**
		 * Is media offload to S3 (or similar)?
		 *
		 * @access public
		 * @var bool $s3_active
		 */
		public $s3_active = false;

		/**
		 * Set class properties for children.
		 *
		 * @param string $child_class_path The location of the child class extending the base class.
		 */
		function __construct( $child_class_path = '' ) {
			$this->home_url          = trailingslashit( get_site_url() );
			$this->relative_home_url = preg_replace( '/https?:/', '', $this->home_url );
			$this->home_domain       = $this->parse_url( $this->home_url, PHP_URL_HOST );
			if ( strpos( $child_class_path, 'plugins/ewww' ) ) {
				$this->content_url = content_url( 'ewww/' );
				$this->content_dir = $this->set_content_dir( '/ewww/' );
				$this->version     = EWWW_IMAGE_OPTIMIZER_VERSION;
			} elseif ( strpos( $child_class_path, 'plugins/easy' ) ) {
				$this->content_url = content_url( 'easyio/' );
				$this->content_dir = $this->set_content_dir( '/easyio/' );
				$this->version     = EASYIO_VERSION;
				$this->prefix      = 'easyio_';
			} else {
				$this->content_url = content_url( 'ewww/' );
			}
			/**
			 * NOTE: there might, maybe, be cases where the upload URL does not match the detected site URL.
			 * If that happens, we'll want to extend $this->content_url() to compensate using the URL from wp_get_site_url().
			 *
			 * Also, home_url is intended to be a "local" content URL, simply using get_site_url().
			 * It is NOT the actual home URL value/setting, which would normally point to the "home" page.
			 * The site_url, on the other hand, is intended to be the shortest version of the content/upload URL.
			 * Thus it might be different than home_url for a sub-directory install:
			 * site_url = https://example.com/ vs. home_url = https://example.com/wordpress/
			 * It would also be different if the site is using cloud storage: https://example.s3.amazonaws.com
			 */
			$this->content_url();
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$this->debug_message( "plugin (resource) content_url: $this->content_url" );
			$this->debug_message( "plugin (resource) content_dir: $this->content_dir" );
			$this->debug_message( "home url: $this->home_url" );
			$this->debug_message( "relative home url: $this->relative_home_url" );
			$this->debug_message( "home domain: $this->home_domain" );
			$this->debug_message( "site/upload url: $this->site_url" );
			$this->debug_message( "site/upload domain: $this->upload_domain" );
		}

		/**
		 * Finds a writable location to store plugin resources.
		 *
		 * Checks to see if the wp-content/ directory is writable, and uses the upload dir
		 * as fall-back. If neither location works, the original wp-content/ folder will be
		 * used, and other functions will need to make sure the resource folder is writable.
		 *
		 * @param string $sub_folder The sub-folder to use for plugin resources, with slashes on both ends.
		 * @return string The full path to a writable plugin resource folder.
		 */
		function set_content_dir( $sub_folder ) {
			if (
				defined( 'EWWWIO_CONTENT_DIR' ) &&
				trailingslashit( WP_CONTENT_DIR ) . trailingslashit( 'ewww' ) !== EWWWIO_CONTENT_DIR
			) {
				$content_dir       = EWWWIO_CONTENT_DIR;
				$this->content_url = str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $content_dir );
				return $content_dir;
			}
			$content_dir = WP_CONTENT_DIR . $sub_folder;
			if ( ! is_writable( WP_CONTENT_DIR ) || ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
				$upload_dir = wp_get_upload_dir();
				if ( false === strpos( $upload_dir['basedir'], '://' ) && is_writable( $upload_dir['basedir'] ) ) {
					$content_dir = $upload_dir['basedir'] . $sub_folder;
					// Also need to update the corresponding URL.
					$this->content_url = $upload_dir['baseurl'] . $sub_folder;
				}
			}
			return $content_dir;
		}

		/**
		 * Saves the in-memory debug log to a logfile in the plugin folder.
		 *
		 * @global string $eio_debug The in-memory debug log.
		 */
		function debug_log() {
			global $eio_debug;
			global $ewwwio_temp_debug;
			global $easyio_temp_debug;
			$debug_log = $this->content_dir . 'debug.log';
			if ( ! is_dir( $this->content_dir ) && is_writable( WP_CONTENT_DIR ) ) {
				wp_mkdir_p( $this->content_dir );
			}
			$debug_enabled = $this->get_option( $this->prefix . 'debug' );
			if (
				! empty( $eio_debug ) &&
				empty( $easyio_temp_debug ) &&
				$debug_enabled &&
				is_dir( $this->content_dir ) &&
				is_writable( $this->content_dir )
			) {
				$memory_limit = $this->memory_limit();
				clearstatcache();
				$timestamp = gmdate( 'Y-m-d H:i:s' ) . "\n";
				if ( ! file_exists( $debug_log ) ) {
					touch( $debug_log );
				} else {
					if ( filesize( $debug_log ) + 4000000 + memory_get_usage( true ) > $memory_limit ) {
						unlink( $debug_log );
						touch( $debug_log );
					}
				}
				if ( filesize( $debug_log ) + strlen( $eio_debug ) + 4000000 + memory_get_usage( true ) <= $memory_limit && is_writable( $debug_log ) ) {
					$eio_debug = str_replace( '<br>', "\n", $eio_debug );
					file_put_contents( $debug_log, $timestamp . $eio_debug, FILE_APPEND );
				}
			}
			$eio_debug = '';
		}

		/**
		 * Adds information to the in-memory debug log.
		 *
		 * @global string $eio_debug The in-memory debug log.
		 * @global bool   $easyio_temp_debug Indicator that we are temporarily debugging on the wp-admin.
		 * @global bool   $ewwwio_temp_debug Indicator that we are temporarily debugging on the wp-admin.
		 *
		 * @param string $message Debug information to add to the log.
		 */
		function debug_message( $message ) {
			if ( ! is_string( $message ) && ! is_int( $message ) && ! is_float( $message ) ) {
				return;
			}
			$message = "$message";
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::debug( $message );
				return;
			}
			global $ewwwio_temp_debug;
			global $easyio_temp_debug;
			if ( $easyio_temp_debug || $ewwwio_temp_debug || $this->get_option( $this->prefix . 'debug' ) ) {
				$memory_limit = $this->memory_limit();
				if ( strlen( $message ) + 4000000 + memory_get_usage( true ) <= $memory_limit ) {
					global $eio_debug;
					$message    = str_replace( "\n\n\n", '<br>', $message );
					$message    = str_replace( "\n\n", '<br>', $message );
					$message    = str_replace( "\n", '<br>', $message );
					$eio_debug .= "$message<br>";
				} else {
					global $eio_debug;
					$eio_debug = "not logging message, memory limit is $memory_limit";
				}
			}
		}

		/**
		 * Checks if a function is disabled or does not exist.
		 *
		 * @param string $function The name of a function to test.
		 * @param bool   $debug Whether to output debugging.
		 * @return bool True if the function is available, False if not.
		 */
		function function_exists( $function, $debug = false ) {
			if ( function_exists( 'ini_get' ) ) {
				$disabled = @ini_get( 'disable_functions' );
				if ( $debug ) {
					$this->debug_message( "disable_functions: $disabled" );
				}
			}
			if ( extension_loaded( 'suhosin' ) && function_exists( 'ini_get' ) ) {
				$suhosin_disabled = @ini_get( 'suhosin.executor.func.blacklist' );
				if ( $debug ) {
					$this->debug_message( "suhosin_blacklist: $suhosin_disabled" );
				}
				if ( ! empty( $suhosin_disabled ) ) {
					$suhosin_disabled = explode( ',', $suhosin_disabled );
					$suhosin_disabled = array_map( 'trim', $suhosin_disabled );
					$suhosin_disabled = array_map( 'strtolower', $suhosin_disabled );
					if ( function_exists( $function ) && ! in_array( $function, $suhosin_disabled, true ) ) {
						return true;
					}
					return false;
				}
			}
			return function_exists( $function );
		}

		/**
		 * Check for GD support.
		 *
		 * @return bool Debug True if GD support detected.
		 */
		function gd_support() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( function_exists( 'gd_info' ) ) {
				$gd_support = gd_info();
				$this->debug_message( 'GD found, supports:' );
				if ( $this->is_iterable( $gd_support ) ) {
					foreach ( $gd_support as $supports => $supported ) {
						$this->debug_message( "$supports: $supported" );
					}
					if ( ( ! empty( $gd_support['JPEG Support'] ) || ! empty( $gd_support['JPG Support'] ) ) && ! empty( $gd_support['PNG Support'] ) ) {
						return ! empty( $gd_support['GD Version'] ) ? $gd_support['GD Version'] : '1';
					}
				}
			}
			return false;
		}

		/**
		 * Check for IMagick support of both PNG and JPG.
		 *
		 * @return bool True if full Imagick support is detected.
		 */
		function imagick_support() {
			$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
			if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
				$imagick = new Imagick();
				$formats = $imagick->queryFormats();
				$this->debug_message( implode( ',', $formats ) );
				if ( in_array( 'PNG', $formats, true ) && in_array( 'JPG', $formats, true ) ) {
					return true;
				}
				$this->debug_message( 'imagick found, but PNG or JPG not supported' );
			}
			return false;
		}

		/**
		 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
		 *
		 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
		 * same-named constant. Overrides are only available for integer and boolean options.
		 *
		 * @param string $option_name The name of the option to retrieve.
		 * @param mixed  $default The default to use if not found/set, defaults to false, but not currently used.
		 * @param bool   $single Use single-site setting regardless of multisite activation. Default is off/false.
		 * @return mixed The value of the option.
		 */
		function get_option( $option_name, $default = false, $single = false ) {
			if ( 'easyio_' === $this->prefix && function_exists( 'easyio_get_option' ) ) {
				return easyio_get_option( $option_name );
			}
			if ( 'ewww_image_optimizer_' === $this->prefix && function_exists( 'ewww_image_optimizer_get_option' ) ) {
				return ewww_image_optimizer_get_option( $option_name, $default, $single );
			}
			$constant_name = strtoupper( $option_name );
			if ( defined( $constant_name ) && ( is_int( constant( $constant_name ) ) || is_bool( constant( $constant_name ) ) ) ) {
				return constant( $constant_name );
			}
			if ( false !== strpos( $option_name, 'easyio' ) ) {
				return get_option( $option_name );
			}
			if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			if (
				! $single &&
				is_multisite() &&
				defined( strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) &&
				is_plugin_active_for_network( constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) &&
				! get_site_option( $this->prefix . 'allow_multisite_override' )
			) {
				$option_value = get_site_option( $option_name );
			} else {
				$option_value = get_option( $option_name );
			}
			return $option_value;
		}

		/**
		 * Implode a multi-dimensional array without throwing errors. Arguments can be reverse order, same as implode().
		 *
		 * @param string $delimiter The character to put between the array items (the glue).
		 * @param array  $data The array to output with the glue.
		 * @return string The array values, separated by the delimiter.
		 */
		function implode( $delimiter, $data = '' ) {
			if ( is_array( $delimiter ) ) {
				$temp_data = $delimiter;
				$delimiter = $data;
				$data      = $temp_data;
			}
			if ( is_array( $delimiter ) ) {
				return '';
			}
			$output = '';
			foreach ( $data as $value ) {
				if ( is_string( $value ) || is_numeric( $value ) ) {
					$output .= $value . $delimiter;
				} elseif ( is_bool( $value ) ) {
					$output .= ( $value ? 'true' : 'false' ) . $delimiter;
				} elseif ( is_array( $value ) ) {
					$output .= 'Array,';
				}
			}
			return rtrim( $output, ',' );
		}

		/**
		 * Checks to see if the current page being output is an AMP page.
		 *
		 * @return bool True for an AMP endpoint, false otherwise.
		 */
		function is_amp() {
			// Just return false if we can't properly check yet.
			if ( ! did_action( 'parse_request' ) ) {
				return false;
			}
			if ( ! did_action( 'wp' ) ) {
				return false;
			}
			global $wp_query;
			if ( ! isset( $wp_query ) || ! ( $wp_query instanceof WP_Query ) ) {
				return false;
			}

			if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
				return true;
			}
			if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
				return true;
			}
			if ( function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint() ) {
				return true;
			}
			return false;
		}

		/**
		 * Checks to see if the current buffer/output is a JSON-encoded string.
		 *
		 * Specifically, we are looking for JSON objects/strings, not just ANY JSON value.
		 * Thus, the check is rather "loose", only looking for {} or [] at the start/end.
		 *
		 * @param string $buffer The content to check for JSON.
		 * @return bool True for JSON, false for everything else.
		 */
		function is_json( $buffer ) {
			if ( '{' === substr( $buffer, 0, 1 ) && '}' === substr( $buffer, -1 ) ) {
				return true;
			}
			if ( '[' === substr( $buffer, 0, 1 ) && ']' === substr( $buffer, -1 ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Make sure this is really and truly a "front-end request", excluding page builders and such.
		 *
		 * @return bool True for front-end requests, false for admin/builder requests.
		 */
		function is_frontend() {
			if ( is_admin() ) {
				return false;
			}
			$uri = add_query_arg( null, null );
			if (
				strpos( $uri, 'cornerstone=' ) !== false ||
				strpos( $uri, 'cornerstone-endpoint' ) !== false ||
				strpos( $uri, 'ct_builder=' ) !== false ||
				did_action( 'cornerstone_boot_app' ) || did_action( 'cs_before_preview_frame' ) ||
				'/print/' === substr( $uri, -7 ) ||
				strpos( $uri, 'elementor-preview=' ) !== false ||
				strpos( $uri, 'et_fb=' ) !== false ||
				strpos( $uri, 'vc_editable=' ) !== false ||
				strpos( $uri, 'tatsu=' ) !== false ||
				( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) || // phpcs:ignore WordPress.Security.NonceVerification
				strpos( $uri, 'wp-login.php' ) !== false ||
				is_embed() ||
				is_feed() ||
				is_preview() ||
				is_customize_preview() ||
				( defined( 'REST_REQUEST' ) && REST_REQUEST )
			) {
				return false;
			}
			return true;
		}

		/**
		 * Checks if the image URL points to a lazy load placeholder.
		 *
		 * @param string $image The image URL (or an image element).
		 * @return bool True if it matches a known placeholder pattern, false otherwise.
		 */
		function is_lazy_placeholder( $image ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if (
				strpos( $image, 'base64,R0lGOD' ) ||
				strpos( $image, 'lazy-load/images/1x1' ) ||
				strpos( $image, '/assets/images/lazy' ) ||
				strpos( $image, '/assets/images/dummy.png' ) ||
				strpos( $image, '/assets/images/transparent.png' ) ||
				strpos( $image, '/lazy/placeholder' )
			) {
				$this->debug_message( 'lazy load placeholder' );
				return true;
			}
			return false;
		}

		/**
		 * Check if file exists, and that it is local rather than using a protocol like http:// or phar://
		 *
		 * @param string $file The path of the file to check.
		 * @return bool True if the file exists and is local, false otherwise.
		 */
		function is_file( $file ) {
			if ( false !== strpos( $file, '://' ) ) {
				return false;
			}
			if ( false !== strpos( $file, 'phar://' ) ) {
				return false;
			}
			$file       = realpath( $file );
			$wp_dir     = realpath( ABSPATH );
			$upload_dir = wp_get_upload_dir();
			$upload_dir = realpath( $upload_dir['basedir'] );

			$content_dir = realpath( WP_CONTENT_DIR );
			if ( empty( $content_dir ) ) {
				$content_dir = $wp_dir;
			}
			if ( empty( $upload_dir ) ) {
				$upload_dir = $content_dir;
			}
			$plugin_dir = realpath( constant( strtoupper( $this->prefix ) . 'PLUGIN_PATH' ) );
			if (
				false === strpos( $file, $upload_dir ) &&
				false === strpos( $file, $content_dir ) &&
				false === strpos( $file, $wp_dir ) &&
				false === strpos( $file, $plugin_dir )
			) {
				return false;
			}
			return is_file( $file );
		}


		/**
		 * Make sure an array/object can be parsed by a foreach().
		 *
		 * @param mixed $var A variable to test for iteration ability.
		 * @return bool True if the variable is iterable.
		 */
		function is_iterable( $var ) {
			return ! empty( $var ) && ( is_array( $var ) || $var instanceof Traversable );
		}

		/**
		 * Checks if there is enough memory still available.
		 *
		 * Looks to see if the current usage + padding will fit within the memory_limit defined by PHP.
		 *
		 * @param int $padding Optional. The amount of memory needed to continue. Default 1050000.
		 * @return True to proceed, false if there is not enough memory.
		 */
		function check_memory_available( $padding = 1050000 ) {
			$memory_limit = $this->memory_limit();

			$current_memory = memory_get_usage( true ) + $padding;
			if ( $current_memory >= $memory_limit ) {
				$this->debug_message( "detected memory limit is not enough: $memory_limit" );
				return false;
			}
			$this->debug_message( "detected memory limit is: $memory_limit" );
			return true;
		}

		/**
		 * Finds the current PHP memory limit or a reasonable default.
		 *
		 * @return int The memory limit in bytes.
		 */
		function memory_limit() {
			if ( defined( 'EIO_MEMORY_LIMIT' ) && EIO_MEMORY_LIMIT ) {
				$memory_limit = EIO_MEMORY_LIMIT;
			} elseif ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			} else {
				if ( ! defined( 'EIO_MEMORY_LIMIT' ) ) {
					// Conservative default, current usage + 16M.
					$current_memory = memory_get_usage( true );
					$memory_limit   = round( $current_memory / ( 1024 * 1024 ) ) + 16;
					define( 'EIO_MEMORY_LIMIT', $memory_limit );
				}
			}
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::debug( "memory limit is set at $memory_limit" );
			}
			if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32000M';
			}
			if ( stripos( $memory_limit, 'g' ) ) {
				$memory_limit = intval( $memory_limit ) * 1024 * 1024 * 1024;
			} else {
				$memory_limit = intval( $memory_limit ) * 1024 * 1024;
			}
			return $memory_limit;
		}

		/**
		 * Set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
		 *
		 * @param string $option_name The name of the option to save.
		 * @param mixed  $option_value The value to save for the option.
		 * @return bool True if the operation was successful.
		 */
		function set_option( $option_name, $option_value ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			if (
				is_multisite() &&
				is_plugin_active_for_network( constant( strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) &&
				! get_site_option( $this->prefix . 'allow_multisite_override' )
			) {
				$success = update_site_option( $option_name, $option_value );
			} else {
				$success = update_option( $option_name, $option_value );
			}
			return $success;
		}

		/**
		 * Attempts to reverse a CDN (or multi-lingual) URL to a local path to test for file existence.
		 *
		 * Used for supporting pull-mode CDNs mostly, or push-mode if local copies exist.
		 *
		 * @param string $url The image URL to mangle.
		 * @return string The path to a local file correlating to the CDN URL, an empty string otherwise.
		 */
		function cdn_to_local( $url ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! $this->is_iterable( $this->allowed_domains ) ) {
				return false;
			}
			if ( 0 === strpos( $url, $this->home_url ) ) {
				$this->debug_message( "$url contains $this->home_url, short-circuiting" );
				return $this->url_to_path_exists( $url );
			}
			foreach ( $this->allowed_domains as $allowed_domain ) {
				if ( $allowed_domain === $this->home_domain ) {
					continue;
				}
				$this->debug_message( "looking for domain $allowed_domain in $url" );
				if (
					! empty( $this->s3_active ) &&
					false !== strpos( $url, $this->s3_active ) &&
					(
						( false !== strpos( $this->s3_active, '/' ) ) ||
						( ! empty( $this->s3_object_prefix ) && false !== strpos( $url, $this->s3_object_prefix ) )
					)
				) {
					// We will wait until the paths loop to fix this one.
					$this->debug_message( 'skipping domains and going to URLs' );
					continue;
				}
				if ( false !== strpos( $url, $allowed_domain ) ) {
					$local_url = str_replace( $allowed_domain, $this->home_domain, $url );
					$this->debug_message( "found $allowed_domain, replaced with $this->home_domain to get $local_url" );
					$path = $this->url_to_path_exists( $local_url );
					if ( $path ) {
						return $path;
					}
				}
			}
			foreach ( $this->allowed_urls as $allowed_url ) {
				if ( false === strpos( $allowed_url, 'http' ) ) {
					continue;
				}
				$this->debug_message( "looking for path $allowed_url in $url" );
				if ( ! empty( $this->s3_active ) && ! empty( $this->s3_object_prefix ) ) {
					$this->debug_message( "checking first for $this->s3_active and $allowed_url" . $this->s3_object_prefix );
				}
				if (
					! empty( $this->s3_active ) && // We've got an S3 configuration, and...
					false !== strpos( $url, $this->s3_active ) && // the S3 domain is present in the URL, and...
					! empty( $this->s3_object_prefix ) && // there could be an S3 object prefix to contend with, and...
					0 === strpos( $url, $allowed_url . $this->s3_object_prefix ) // "allowed_url" + the object prefix matches the URL.
				) {
					$local_url = str_replace( $allowed_url . $this->s3_object_prefix, $this->upload_url, $url );
					$this->debug_message( "found $allowed_url (and $this->s3_object_prefix), replaced with $this->upload_url to get $local_url" );
					$path = $this->url_to_path_exists( $local_url );
					if ( $path ) {
						return $path;
					}
				}
				if ( false !== strpos( $url, $allowed_url ) ) {
					$local_url = str_replace( $allowed_url, $this->upload_url, $url );
					$this->debug_message( "found $allowed_url, replaced with $this->upload_url to get $local_url" );
					$path = $this->url_to_path_exists( $local_url );
					if ( $path ) {
						return $path;
					}
				}
			}
			return false;
		}

		/**
		 * Converts a URL to a file-system path and checks if the resulting path exists.
		 *
		 * @param string $url The URL to mangle.
		 * @param string $extension An optional extension to append during is_file().
		 * @return bool|string The path if a local file exists correlating to the URL, false otherwise.
		 */
		function url_to_path_exists( $url, $extension = '' ) {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			$url = $this->maybe_strip_object_version( $url );
			if ( '/' === substr( $url, 0, 1 ) && '/' !== substr( $url, 1, 1 ) ) {
				$this->debug_message( "found relative URL: $url" );
				$url = '//' . $this->upload_domain . $url;
				$this->debug_message( "and changed to $url for path checking" );
			}
			if ( 0 === strpos( $url, WP_CONTENT_URL ) ) {
				$path = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $url );
				$this->debug_message( "trying $path based on " . WP_CONTENT_URL );
			} elseif ( 0 === strpos( $url, $this->relative_home_url ) ) {
				$path = str_replace( $this->relative_home_url, ABSPATH, $url );
				$this->debug_message( "trying $path based on " . $this->relative_home_url );
			} elseif ( 0 === strpos( $url, $this->home_url ) ) {
				$path = str_replace( $this->home_url, ABSPATH, $url );
				$this->debug_message( "trying $path based on " . $this->home_url );
			} else {
				$this->debug_message( 'not a valid local image' );
				return false;
			}
			$path_parts = explode( '?', $path );
			if ( $this->is_file( $path_parts[0] . $extension ) ) {
				$this->debug_message( 'local file found' );
				return $path_parts[0];
			}
			return false;
		}

		/**
		 * Remove S3 object versioning from URL.
		 *
		 * @param string $url The image URL with a potential version string embedded.
		 * @return string The URL without a version string.
		 */
		function maybe_strip_object_version( $url ) {
			if ( ! empty( $this->s3_object_version ) ) {
				$possible_version = basename( dirname( $url ) );
				if (
					! empty( $possible_version ) &&
					8 === strlen( $possible_version ) &&
					ctype_digit( $possible_version )
				) {
					$url = str_replace( '/' . $possible_version . '/', '/', $url );
					$this->debug_message( "removed version $possible_version from $url" );
				} elseif (
					! empty( $possible_version ) &&
					14 === strlen( $possible_version ) &&
					ctype_digit( $possible_version )
				) {
					$year  = substr( $possible_version, 0, 4 );
					$month = substr( $possible_version, 4, 2 );
					$url   = str_replace( '/' . $possible_version . '/', "/$year/$month/", $url );
					$this->debug_message( "removed version $possible_version from $url" );
				}
			}
			return $url;
		}

		/**
		 * A wrapper for PHP's parse_url, prepending assumed scheme for network path
		 * URLs. PHP versions 5.4.6 and earlier do not correctly parse without scheme.
		 *
		 * @param string  $url The URL to parse.
		 * @param integer $component Retrieve specific URL component.
		 * @return mixed Result of parse_url.
		 */
		function parse_url( $url, $component = -1 ) {
			if ( 0 === strpos( $url, '//' ) ) {
				$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
			}
			if ( false === strpos( $url, 'http' ) && '/' !== substr( $url, 0, 1 ) ) {
				$url = ( is_ssl() ? 'https://' : 'http://' ) . $url;
			}
			// Because encoded ampersands in the filename break things.
			$url = str_replace( '&#038;', '&', $url );
			return parse_url( $url, $component );
		}

		/**
		 * Get the shortest version of the content URL.
		 *
		 * @return string The URL where the content lives.
		 */
		function content_url() {
			$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( $this->site_url ) {
				return $this->site_url;
			}
			$this->site_url = get_home_url();
			global $as3cf;
			if ( class_exists( 'Amazon_S3_And_CloudFront' ) && is_object( $as3cf ) ) {
				$s3_scheme = $as3cf->get_url_scheme();
				$s3_region = $as3cf->get_setting( 'region' );
				$s3_bucket = $as3cf->get_setting( 'bucket' );
				if ( is_wp_error( $s3_region ) ) {
					$s3_region = '';
				}
				$s3_domain = '';
				if ( ! empty( $s3_bucket ) && ! is_wp_error( $s3_bucket ) && method_exists( $as3cf, 'get_provider' ) ) {
					$s3_domain = $as3cf->get_provider()->get_url_domain( $s3_bucket, $s3_region, null, array(), true );
				} elseif ( ! empty( $s3_bucket ) && ! is_wp_error( $s3_bucket ) && method_exists( $as3cf, 'get_storage_provider' ) ) {
					$s3_domain = $as3cf->get_storage_provider()->get_url_domain( $s3_bucket, $s3_region );
				}
				if ( $as3cf->get_setting( 'enable-object-prefix' ) ) {
					$this->s3_object_prefix = $as3cf->get_setting( 'object-prefix' );
					$this->debug_message( $this->s3_object_prefix );
				} else {
					$this->s3_object_prefix = '';
					$this->debug_message( 'no WOM prefix' );
				}
				if ( ! empty( $s3_domain ) && $as3cf->get_setting( 'serve-from-s3' ) ) {
					$this->s3_active = $s3_domain;
					$this->debug_message( "found S3 domain of $s3_domain with bucket $s3_bucket and region $s3_region" );
					$this->allowed_urls[] = $s3_scheme . '://' . $s3_domain . '/';
					if ( $as3cf->get_setting( 'enable-delivery-domain' ) && $as3cf->get_setting( 'delivery-domain' ) ) {
						$delivery_domain         = $as3cf->get_setting( 'delivery-domain' );
						$this->allowed_urls[]    = $s3_scheme . '://' . \trailingslashit( $delivery_domain ) . trailingslashit( trim( $this->s3_object_prefix, '/' ) );
						$this->allowed_domains[] = $delivery_domain;
						$this->debug_message( "found WOM delivery domain of $delivery_domain" );
					}
				}
				if ( $as3cf->get_setting( 'object-versioning' ) ) {
					$this->s3_object_version = true;
					$this->debug_message( 'object versioning enabled' );
				}
			}

			if (
				class_exists( 'S3_Uploads' ) &&
				function_exists( 's3_uploads_enabled' ) && s3_uploads_enabled() &&
				method_exists( 'S3_Uploads', 'get_instance' ) && method_exists( 'S3_Uploads', 'get_s3_url' )
			) {
				$s3_uploads_instance  = \S3_Uploads::get_instance();
				$s3_uploads_url       = $s3_uploads_instance->get_s3_url();
				$this->allowed_urls[] = $s3_uploads_url;
				$this->debug_message( "found S3 URL from S3_Uploads: $s3_uploads_url" );
				$s3_domain       = $this->parse_url( $s3_uploads_url, PHP_URL_HOST );
				$s3_scheme       = $this->parse_url( $s3_uploads_url, PHP_URL_SCHEME );
				$this->s3_active = $s3_domain;
			}

			if (
				class_exists( 'S3_Uploads\Plugin' ) &&
				function_exists( 's3_uploads_enabled' ) && s3_uploads_enabled() &&
				method_exists( 'S3_Uploads\Plugin', 'get_instance' ) && method_exists( 'S3_Uploads', 'get_s3_url\Plugin' )
			) {
				$s3_uploads_instance  = \S3_Uploads\Plugin::get_instance();
				$s3_uploads_url       = $s3_uploads_instance->get_s3_url();
				$this->allowed_urls[] = $s3_uploads_url;
				$this->debug_message( "found S3 URL from S3_Uploads: $s3_uploads_url" );
				$s3_domain       = $this->parse_url( $s3_uploads_url, PHP_URL_HOST );
				$s3_scheme       = $this->parse_url( $s3_uploads_url, PHP_URL_SCHEME );
				$this->s3_active = $s3_domain;
			}

			if ( class_exists( 'wpCloud\StatelessMedia\EWWW' ) && function_exists( 'ud_get_stateless_media' ) ) {
				$sm = ud_get_stateless_media();
				if ( method_exists( $sm, 'get' ) && method_exists( $sm, 'get_gs_host' ) ) {
					$sm_mode = $sm->get( 'sm.mode' );
					if ( 'disabled' !== $sm_mode ) {
						$sm_host              = $sm->get_gs_host();
						$this->allowed_urls[] = $sm_host;
						$this->debug_message( "found cloud storage URL from WP Stateless: $sm_host" );
						$s3_domain       = $this->parse_url( $sm_host, PHP_URL_HOST );
						$s3_scheme       = $this->parse_url( $sm_host, PHP_URL_SCHEME );
						$this->s3_active = $s3_domain;
					}
				}
			}

			// NOTE: we don't want this for Easy IO as they might be using SWIS to deliver
			// JS/CSS from a different CDN domain, and that will break with Easy IO!
			if ( 'ExactDN' !== get_class( $this ) && 'EIO_Base' !== get_class( $this ) && function_exists( 'swis' ) && is_object( swis()->settings ) && swis()->settings->get_option( 'cdn_domain' ) ) {
				$this->allowed_urls[]    = swis()->settings->get_option( 'cdn_domain' );
				$this->allowed_domains[] = $this->parse_url( swis()->settings->get_option( 'cdn_domain' ), PHP_URL_HOST );
			}

			$upload_dir = wp_get_upload_dir();
			if ( $this->s3_active ) {
				$this->site_url = defined( 'EXACTDN_LOCAL_DOMAIN' ) && EXACTDN_LOCAL_DOMAIN ? EXACTDN_LOCAL_DOMAIN : $s3_scheme . '://' . $s3_domain;
			} else {
				// Normally, we use this one, as it will be shorter for sub-directory (not multi-site) installs.
				$home_url    = get_home_url();
				$site_url    = get_site_url();
				$home_domain = $this->parse_url( $home_url, PHP_URL_HOST );
				$site_domain = $this->parse_url( $site_url, PHP_URL_HOST );
				// If the home domain does not match the upload url, and the site domain does match...
				if ( $home_domain && false === strpos( $upload_dir['baseurl'], $home_domain ) && $site_domain && false !== strpos( $upload_dir['baseurl'], $site_domain ) ) {
					$this->debug_message( "using WP URL (via get_site_url) with $site_domain rather than $home_domain" );
					$home_url = $site_url;
				}
				$this->site_url = defined( 'EXACTDN_LOCAL_DOMAIN' ) && EXACTDN_LOCAL_DOMAIN ? EXACTDN_LOCAL_DOMAIN : $home_url;
			}
			// This is used by the WebP parsers, and by the Lazy Load via get_image_dimensions_by_url().
			$this->upload_url = trailingslashit( ! empty( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : content_url( 'uploads' ) );
			$this->debug_message( "upload_url: $this->upload_url" );

			// But this is used by Easy IO, so it should be derived from the above logic instead, which already matches the site/home URLs against the upload URL.
			$this->upload_domain     = $this->parse_url( $this->site_url, PHP_URL_HOST );
			$this->allowed_domains[] = $this->upload_domain;
			// For when plugins don't do a very good job of updating URLs for mapped multi-site domains.
			if ( is_multisite() && false === strpos( $upload_dir['baseurl'], $this->upload_domain ) ) {
				$this->debug_message( 'upload domain does not match the home URL' );
				$origin_upload_domain = $this->parse_url( $upload_dir['baseurl'], PHP_URL_HOST );
				if ( $origin_upload_domain ) {
					$this->allowed_domains[] = $origin_upload_domain;
				}
			}
			// Grab domain aliases that might point to the same place as the upload_domain.
			if ( ! $this->s3_active && 0 !== strpos( $this->upload_domain, 'www' ) ) {
				$this->allowed_domains[] = 'www.' . $this->upload_domain;
			} elseif ( 0 === strpos( $this->upload_domain, 'www.' ) ) {
				$nonwww = ltrim( ltrim( $this->upload_domain, 'w' ), '.' );
				if ( $nonwww && $nonwww !== $this->upload_domain ) {
					$this->allowed_domains[] = $nonwww;
				}
			}
			if ( ! $this->s3_active || 'ExactDN' !== get_class( $this ) ) {
				$wpml_domains = apply_filters( 'wpml_setting', array(), 'language_domains' );
				if ( $this->is_iterable( $wpml_domains ) ) {
					$this->debug_message( 'wpml domains: ' . implode( ',', $wpml_domains ) );
					$this->allowed_domains[] = $this->parse_url( get_option( 'home' ), PHP_URL_HOST );
					$wpml_scheme             = $this->parse_url( $this->upload_url, PHP_URL_SCHEME );
					foreach ( $wpml_domains as $wpml_domain ) {
						$this->allowed_domains[] = $wpml_domain;
						$this->allowed_urls[]    = $wpml_scheme . '://' . $wpml_domain;
					}
				}
			}
			return $this->site_url;
		}

		/**
		 * Takes the list of allowed URLs and parses out the domain names.
		 */
		function get_allowed_domains() {
			if ( ! $this->is_iterable( $this->allowed_urls ) ) {
				return;
			}
			foreach ( $this->allowed_urls as $allowed_url ) {
				$allowed_domain = $this->parse_url( $allowed_url, PHP_URL_HOST );
				if ( $allowed_domain && ! in_array( $allowed_domain, $this->allowed_domains, true ) ) {
					$this->allowed_domains[] = $allowed_domain;
				}
			}
		}
	}
}
