<?php
/**
 * Implements basic and common utility functions for all sub-classes.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for child classes.
 */
class Base {

	/**
	 * Data that has been sent to the debugger, appended as the plugin operates.
	 *
	 * @access public
	 * @var string $debug_data
	 */
	public static $debug_data = '';

	/**
	 * Temporarily enable debug mode, used to collect system info on specific pages.
	 *
	 * @access public
	 * @var bool $temp_debug
	 */
	public static $temp_debug = false;

	/**
	 * System info, gathered from the debugger and debug_info() functions.
	 *
	 * @access public
	 * @var string $system_info
	 */
	public static $system_info = '';

	/**
	 * Whether the site is multisite, network activated, and not configured for per-site settings.
	 *
	 * @access public
	 * @var bool $use_network_options
	 */
	public static $use_network_options = null;

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
	 * Home domain.
	 *
	 * @access public
	 * @var string $home_domain
	 */
	public $home_domain = '';

	/**
	 * Relative home (URL) for the plugin to use.
	 *
	 * @access public
	 * @var string $relative_home_url
	 */
	public $relative_home_url = '';

	/**
	 * Upload directory (URL).
	 *
	 * @access public
	 * @var string $upload_url
	 */
	public $upload_url = '';

	/**
	 * Upload domain.
	 *
	 * @access public
	 * @var string $upload_domain
	 */
	public $upload_domain = '';

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
	 * A WP_Filesystem_Direct object for various file operations.
	 *
	 * @access protected
	 * @var object $filesystem
	 */
	protected $filesystem = false;

	/**
	 * GD support information.
	 *
	 * @access protected
	 * @var string $gd_info
	 */
	protected $gd_info = '';

	/**
	 * GD support status.
	 *
	 * @access protected
	 * @var string|bool $gd_support
	 */
	protected $gd_support = false;

	/**
	 * GD WebP support status.
	 *
	 * @access protected
	 * @var string|bool $gd_supports_webp
	 */
	protected $gd_supports_webp = false;

	/**
	 * Gmagick support information.
	 *
	 * @access protected
	 * @var string $gmagick_info
	 */
	protected $gmagick_info = '';

	/**
	 * Gmagick support status.
	 *
	 * @access protected
	 * @var string|bool $gmagick_support
	 */
	protected $gmagick_support = false;

	/**
	 * Imagick support information.
	 *
	 * @access protected
	 * @var string $imagick_info
	 */
	protected $imagick_info = '';

	/**
	 * Imagick support status.
	 *
	 * @access protected
	 * @var string|bool $imagick_support
	 */
	protected $imagick_support = false;

	/**
	 * Imagick WebP support status.
	 *
	 * @access protected
	 * @var string|bool $imagick_supports_webp
	 */
	protected $imagick_supports_webp = false;

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
	 * The S3 object prefix.
	 *
	 * @access public
	 * @var bool $s3_object_prefix
	 */
	public $s3_object_prefix = '';

	/**
	 * Do offloaded URLs contain versioning?
	 *
	 * @access public
	 * @var bool $s3_object_version
	 */
	public $s3_object_version = false;

	/**
	 * Set class properties for children.
	 *
	 * @param bool $debug Whether or not paths should be sent to the debugger.
	 */
	public function __construct( $debug = false ) {
		$this->home_url          = \trailingslashit( \get_site_url() );
		$this->relative_home_url = \preg_replace( '/https?:/', '', $this->home_url );
		$this->home_domain       = $this->parse_url( $this->home_url, PHP_URL_HOST );

		if ( 'EWWW' === __NAMESPACE__ ) {
			$this->content_url = \content_url( 'ewww/' );
			$this->content_dir = $this->set_content_dir( '/ewww/' );
			$this->version     = EWWW_IMAGE_OPTIMIZER_VERSION;
		} elseif ( 'EasyIO' === __NAMESPACE__ ) {
			$this->content_url = \content_url( 'easyio/' );
			$this->content_dir = $this->set_content_dir( '/easyio/' );
			$this->version     = EASYIO_VERSION;
			$this->prefix      = 'easyio_';
		}

		if ( ! $debug ) {
			return;
		}

		// Check to see if we're in the wp-admin to enable debugging temporarily.
		// Done after the above, because this means we are constructing the Plugin() object
		// which is the very first object initialized.
		if (
			! self::$temp_debug &&
			is_admin() &&
			! wp_doing_ajax() &&
			! $this->get_option( $this->prefix . 'debug' )
		) {
				self::$temp_debug = true;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "plugin (resource) content_url: $this->content_url" );
		$this->debug_message( "plugin (resource) content_dir: $this->content_dir" );
		$this->debug_message( "home url: $this->home_url" );
		$this->debug_message( "relative home url: $this->relative_home_url" );
		$this->debug_message( "home domain: $this->home_domain" );
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
	public function set_content_dir( $sub_folder ) {
		if (
			\defined( 'EWWWIO_CONTENT_DIR' ) &&
			\trailingslashit( WP_CONTENT_DIR ) . \trailingslashit( 'ewww' ) !== EWWWIO_CONTENT_DIR
		) {
			$content_dir       = EWWWIO_CONTENT_DIR;
			$this->content_url = \str_replace( WP_CONTENT_DIR, WP_CONTENT_URL, $content_dir );
			return $content_dir;
		}
		$content_dir = WP_CONTENT_DIR . $sub_folder;
		if ( ! \is_writable( WP_CONTENT_DIR ) || ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
			$upload_dir = \wp_get_upload_dir();
			if ( false === \strpos( $upload_dir['basedir'], '://' ) && \is_writable( $upload_dir['basedir'] ) ) {
				$content_dir = $upload_dir['basedir'] . $sub_folder;
				// Also need to update the corresponding URL.
				$this->content_url = $upload_dir['baseurl'] . $sub_folder;
			}
		}
		return $content_dir;
	}

	/**
	 * Get the path to the current debug log, if one exists. Otherwise, generate a new filename.
	 *
	 * @return string The full path to the debug log.
	 */
	public function debug_log_path() {
		if ( is_dir( $this->content_dir ) ) {
			$potential_logs = \scandir( $this->content_dir );
			if ( $this->is_iterable( $potential_logs ) ) {
				foreach ( $potential_logs as $potential_log ) {
					if ( $this->str_ends_with( $potential_log, '.log' ) && false !== strpos( $potential_log, strtolower( __NAMESPACE__ ) . '-debug-' ) && is_file( $this->content_dir . $potential_log ) ) {
						return $this->content_dir . $potential_log;
					}
				}
			}
		}
		return $this->content_dir . strtolower( __NAMESPACE__ ) . '-debug-' . uniqid() . '.log';
	}

	/**
	 * Saves the in-memory debug log to a logfile in the plugin folder.
	 */
	public function debug_log() {
		$debug_log = $this->debug_log_path();
		if ( ! \is_dir( $this->content_dir ) && \is_writable( WP_CONTENT_DIR ) ) {
			\wp_mkdir_p( $this->content_dir );
		}
		if (
			! empty( self::$debug_data ) &&
			empty( self::$temp_debug ) &&
			$this->get_option( $this->prefix . 'debug' ) &&
			\is_dir( $this->content_dir ) &&
			\is_writable( $this->content_dir )
		) {
			$memory_limit = $this->memory_limit();
			\clearstatcache();
			$timestamp = \gmdate( 'Y-m-d H:i:s' ) . "\n";
			if ( ! \file_exists( $debug_log ) ) {
				\touch( $debug_log );
			} else {
				if ( \filesize( $debug_log ) + 4000000 + \memory_get_usage( true ) > $memory_limit ) {
					\unlink( $debug_log );
					\clearstatcache();
					$debug_log = $this->debug_log_path();
					\touch( $debug_log );
				}
			}
			if ( \filesize( $debug_log ) + \strlen( self::$debug_data ) + 4000000 + \memory_get_usage( true ) <= $memory_limit && \is_writable( $debug_log ) ) {
				self::$debug_data = \str_replace( '<br>', "\n", self::$debug_data );
				\file_put_contents( $debug_log, $timestamp . self::$debug_data, FILE_APPEND );
			}
		}
		self::$debug_data = '';
	}

	/**
	 * Adds information to the in-memory debug log.
	 *
	 * @param string $message Debug information to add to the log.
	 */
	public function debug_message( $message ) {
		if ( ! \is_string( $message ) && ! \is_int( $message ) && ! \is_float( $message ) ) {
			return;
		}
		if ( \defined( 'EIO_PHPUNIT' ) && EIO_PHPUNIT ) {
			if (
				! empty( $_SERVER['argv'] ) &&
				( \in_array( '--debug', $_SERVER['argv'], true ) || \in_array( '--verbose', $_SERVER['argv'], true ) )
			) {
				$message = \str_replace( '<br>', "\n", $message );
				$message = \str_replace( '<b>', '+', $message );
				$message = \str_replace( '</b>', '+', $message );
				echo \esc_html( $message ) . "\n";
			}
		}
		$message = "$message";
		if ( \defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::debug( $message );
			return;
		}
		if ( self::$temp_debug || $this->get_option( $this->prefix . 'debug' ) ) {
			$memory_limit = $this->memory_limit();
			if ( \strlen( $message ) + 4000000 + \memory_get_usage( true ) <= $memory_limit ) {
				$message           = \str_replace( "\n\n\n", '<br>', $message );
				$message           = \str_replace( "\n\n", '<br>', $message );
				$message           = \str_replace( "\n", '<br>', $message );
				self::$debug_data .= "$message<br>";
			} else {
				self::$debug_data = "not logging message, memory limit is $memory_limit";
			}
		}
	}

	/**
	 * Clears temp debugging mode and flushes the debug data if needed.
	 */
	public function temp_debug_end() {
		if ( ! $this->get_option( $this->prefix . 'debug' ) ) {
			self::$debug_data = '';
		}
		self::$temp_debug = false;
	}

	/**
	 * Escape any spaces in the filename.
	 *
	 * @param string $path The path to a binary file.
	 * @return string The path with spaces escaped.
	 */
	public function escapeshellcmd( $path ) {
		return ( \preg_replace( '/ /', '\ ', $path ) );
	}

	/**
	 * Replacement for escapeshellarg() that won't kill non-ASCII characters.
	 *
	 * @param string $arg A value to sanitize/escape for commmand-line usage.
	 * @return string The value after being escaped.
	 */
	public function escapeshellarg( $arg ) {
		if ( PHP_OS === 'WINNT' ) {
			$safe_arg = \str_replace( '%', ' ', $arg );
			$safe_arg = \str_replace( '!', ' ', $safe_arg );
			$safe_arg = \str_replace( '"', ' ', $safe_arg );
			return '"' . $safe_arg . '"';
		}
		$safe_arg = "'" . \str_replace( "'", "'\\''", $arg ) . "'";
		return $safe_arg;
	}

	/**
	 * Checks if a function is disabled or does not exist.
	 *
	 * @param string $function_name The name of a function to test.
	 * @param bool   $debug Whether to output debugging.
	 * @return bool True if the function is available, False if not.
	 */
	public function function_exists( $function_name, $debug = false ) {
		if ( \function_exists( '\ini_get' ) ) {
			$disabled = @\ini_get( 'disable_functions' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "disable_functions: $disabled" );
			}
		}
		if ( \extension_loaded( 'suhosin' ) && \function_exists( '\ini_get' ) ) {
			$suhosin_disabled = @\ini_get( 'suhosin.executor.func.blacklist' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $debug ) {
				$this->debug_message( "suhosin_blacklist: $suhosin_disabled" );
			}
			if ( ! empty( $suhosin_disabled ) ) {
				$suhosin_disabled = \explode( ',', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'trim', $suhosin_disabled );
				$suhosin_disabled = \array_map( 'strtolower', $suhosin_disabled );
				if ( \function_exists( $function_name ) && ! \in_array( \trim( $function_name, '\\' ), $suhosin_disabled, true ) ) {
					return true;
				}
				return false;
			}
		}
		return \function_exists( $function_name );
	}

	/**
	 * Check for GD support of both PNG and JPG.
	 *
	 * @return string|bool The version of GD if full support is detected, false otherwise.
	 */
	public function gd_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gd_info && $this->function_exists( '\gd_info' ) ) {
			$this->gd_info = \gd_info();
			$this->debug_message( 'GD found, supports:' );
			if ( $this->is_iterable( $this->gd_info ) ) {
				foreach ( $this->gd_info as $supports => $supported ) {
					$this->debug_message( "$supports: $supported" );
				}
				if ( ( ! empty( $this->gd_info['JPEG Support'] ) || ! empty( $this->gd_info['JPG Support'] ) ) && ! empty( $this->gd_info['PNG Support'] ) ) {
					$this->gd_support = ! empty( $this->gd_info['GD Version'] ) ? $this->gd_info['GD Version'] : '1';
				}
			}
		}
		return $this->gd_support;
	}

	/**
	 * Check for GMagick support of both PNG and JPG.
	 *
	 * @return bool True if full Gmagick support is detected.
	 */
	public function gmagick_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gmagick_info && \extension_loaded( 'gmagick' ) && \class_exists( '\Gmagick' ) ) {
			$gmagick            = new \Gmagick();
			$this->gmagick_info = $gmagick->queryFormats();
			$this->debug_message( implode( ',', $this->gmagick_info ) );
			if ( \in_array( 'PNG', $this->gmagick_info, true ) && \in_array( 'JPG', $this->gmagick_info, true ) ) {
				$this->gmagick_support = true;
			} else {
				$this->debug_message( 'gmagick found, but PNG or JPG not supported' );
			}
		}
		return $this->gmagick_support;
	}

	/**
	 * Check for IMagick support of both PNG and JPG.
	 *
	 * @return bool True if full Imagick support is detected.
	 */
	public function imagick_support() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->imagick_info && \extension_loaded( 'imagick' ) && \class_exists( '\Imagick' ) ) {
			$imagick            = new \Imagick();
			$this->imagick_info = $imagick->queryFormats();
			$this->debug_message( \implode( ',', $this->imagick_info ) );
			if ( \in_array( 'PNG', $this->imagick_info, true ) && \in_array( 'JPG', $this->imagick_info, true ) ) {
				$this->imagick_support = true;
			} else {
				$this->debug_message( 'imagick found, but PNG or JPG not supported' );
			}
		}
		return $this->imagick_support;
	}

	/**
	 * Check for GD support of WebP format.
	 *
	 * @return bool True if proper WebP support is detected.
	 */
	public function gd_supports_webp() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->gd_supports_webp ) {
			$gd_version = $this->gd_support();
			if ( $gd_version ) {
				if (
					\function_exists( '\imagewebp' ) &&
					\function_exists( '\imagepalettetotruecolor' ) &&
					\function_exists( '\imageistruecolor' ) &&
					\function_exists( '\imagealphablending' ) &&
					\function_exists( '\imagesavealpha' )
				) {
					if ( \version_compare( $gd_version, '2.2.5', '>=' ) ) {
						$this->debug_message( 'yes it does' );
						$this->gd_supports_webp = true;
					}
				}
			}

			if ( ! $this->gd_supports_webp ) {
				if ( ! \function_exists( '\imagewebp' ) ) {
					$this->debug_message( 'imagewebp() missing' );
				} elseif ( ! \function_exists( '\imagepalettetotruecolor' ) ) {
					$this->debug_message( 'imagepalettetotruecolor() missing' );
				} elseif ( ! \function_exists( '\imageistruecolor' ) ) {
					$this->debug_message( 'imageistruecolor() missing' );
				} elseif ( ! \function_exists( '\imagealphablending' ) ) {
					$this->debug_message( 'imagealphablending() missing' );
				} elseif ( ! \function_exists( '\imagesavealpha' ) ) {
					$this->debug_message( 'imagesavealpha() missing' );
				} elseif ( $gd_version ) {
					$this->debug_message( "version: $gd_version" );
				}
				$this->debug_message( 'sorry nope' );
			}
		}
		return $this->gd_supports_webp;
	}

	/**
	 * Check for Imagick support of WebP.
	 *
	 * @return bool True if WebP support is detected.
	 */
	public function imagick_supports_webp() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! $this->imagick_supports_webp ) {
			if ( $this->imagick_support() ) {
				if ( \in_array( 'WEBP', $this->imagick_info, true ) ) {
					$this->debug_message( 'yes it does' );
					$this->imagick_supports_webp = true;
				}
			}
			if ( ! $this->imagick_supports_webp ) {
				$this->debug_message( 'sorry nope' );
			}
		}
		return apply_filters( 'ewwwio_imagick_supports_webp', $this->imagick_supports_webp );
	}

	/**
	 * Get a list of which image/file types are supported.
	 *
	 * @param string $select Defaults to 'enabled' to only list those types which have optimization enabled. Specify 'all' to return all possible types.
	 * @return array A list of file/mime types.
	 */
	public function get_supported_types( $select = 'enabled' ) {
		$supported_types = array();
		if ( $this->get_option( 'ewww_image_optimizer_jpg_level' ) || $this->get_option( 'ewww_image_optimizer_webp' ) || 'all' === $select ) {
			$supported_types[] = 'image/jpeg';
		}
		if ( $this->get_option( 'ewww_image_optimizer_png_level' ) || $this->get_option( 'ewww_image_optimizer_webp' ) || 'all' === $select ) {
			$supported_types[] = 'image/png';
		}
		if ( $this->get_option( 'ewww_image_optimizer_gif_level' ) || 'all' === $select ) {
			$supported_types[] = 'image/gif';
		}
		if ( $this->get_option( 'ewww_image_optimizer_webp_level' ) || 'all' === $select ) {
			$supported_types[] = 'image/webp';
		}
		if ( $this->get_option( 'ewww_image_optimizer_pdf_level' ) || 'all' === $select ) {
			$supported_types[] = 'application/pdf';
		}
		if ( $this->get_option( 'ewww_image_optimizer_svg_level' ) || 'all' === $select ) {
			$supported_types[] = 'image/svg+xml';
		}
		if ( $this->get_option( 'ewww_image_optimizer_bmp_convert' ) || $this->get_option( 'ewww_image_optimizer_jpg_level' ) || 'all' === $select ) {
			$supported_types[] = 'image/bmp';
		}
		return $supported_types;
	}

	/**
	 * Get a list of which image types can be converted to WebP with the current configuration.
	 *
	 * @return A list of mime-types suitable for WebP conversion.
	 */
	public function get_webp_types() {
		$webp_types = array( 'image/jpeg' );
		if ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			$webp_types[] = 'image/png';
			$webp_types[] = 'image/gif';
		} elseif ( ! $this->get_option( 'ewww_image_optimizer_jpg_only_mode' ) ) {
			$webp_types[] = 'image/png';
		}
		return $webp_types;
	}

	/**
	 * Checks if the S3 Uploads plugin is installed and active.
	 *
	 * @return bool True if it is fully active and rewriting/offloading media, false otherwise.
	 */
	public function s3_uploads_enabled() {
		// For version 3.x.
		if ( \class_exists( '\S3_Uploads\Plugin', false ) && \function_exists( '\S3_Uploads\enabled' ) && \S3_Uploads\enabled() ) {
			return true;
		}
		// Pre version 3.
		if ( \class_exists( '\S3_Uploads', false ) && \function_exists( '\s3_uploads_enabled' ) && \s3_uploads_enabled() ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if Easy IO is active in Perfect Images plugin.
	 *
	 * @return bool True if Easy IO is enabled via PI, false otherwise.
	 */
	public function perfect_images_easyio_domain() {
		if ( class_exists( '\Meow_WR2X_Core' ) ) {
			global $wr2x_core;
			if ( is_object( $wr2x_core ) && method_exists( $wr2x_core, 'get_option' ) ) {
				if ( ! empty( $wr2x_core->get_option( 'easyio_domain' ) ) ) {
					return $wr2x_core->get_option( 'easyio_domain' );
				}
			}
		}
		return false;
	}

	/**
	 * Sanitize the folders/patterns to exclude from optimization.
	 *
	 * @param string $input A list of filesystem paths, from a textarea.
	 * @return array The sanitized list of paths/patterns to exclude.
	 */
	public function exclude_paths_sanitize( $input ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $input ) ) {
			return '';
		}
		$path_array = array();
		if ( \is_array( $input ) ) {
			$paths = $input;
		} elseif ( \is_string( $input ) ) {
			$paths = \explode( "\n", $input );
		}
		if ( $this->is_iterable( $paths ) ) {
			foreach ( $paths as $path ) {
				$this->debug_message( "validating path exclusion: $path" );
				$path = \trim( \sanitize_text_field( $path ), '*' );
				if ( ! empty( $path ) ) {
					$path_array[] = $path;
				}
			}
		}
		return $path_array;
	}

	/**
	 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
	 *
	 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
	 * same-named constant. Overrides are only available for integers, booleans, and specifically supported options.
	 *
	 * @param string $option_name The name of the option to retrieve.
	 * @param mixed  $default_value The default to use if not found/set, defaults to false, but not currently used.
	 * @param bool   $single Use single-site setting regardless of multisite activation. Default is off/false.
	 * @return mixed The value of the option.
	 */
	public function get_option( $option_name, $default_value = false, $single = false ) {
		$constant_name = \strtoupper( $option_name );
		if ( \defined( $constant_name ) && ( \is_int( \constant( $constant_name ) ) || \is_bool( \constant( $constant_name ) ) ) ) {
			return \constant( $constant_name );
		}
		if ( 'ewww_image_optimizer_cloud_key' === $option_name && \defined( $constant_name ) ) {
			$option_value = \constant( $constant_name );
			if ( \is_string( $option_value ) && ! empty( $option_value ) ) {
				return \trim( $option_value );
			}
		}

		if (
			(
				'ewww_image_optimizer_exclude_paths' === $option_name ||
				'exactdn_exclude' === $option_name ||
				'easyio_ll_exclude' === $option_name ||
				'ewww_image_optimizer_ll_exclude' === $option_name ||
				'ewww_image_optimizer_webp_rewrite_exclude' === $option_name
			)
			&& \defined( $constant_name )
		) {
			return $this->exclude_paths_sanitize( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_ll_all_things' === $option_name && \defined( $constant_name ) ) {
			return \sanitize_text_field( \constant( $constant_name ) );
		}
		if ( 'easyio_ll_all_things' === $option_name && \defined( $constant_name ) ) {
			return \sanitize_text_field( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_aux_paths' === $option_name && \defined( $constant_name ) ) {
			return \ewww_image_optimizer_aux_paths_sanitize( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_webp_paths' === $option_name && \defined( $constant_name ) ) {
			return \ewww_image_optimizer_webp_paths_sanitize( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_disable_resizes' === $option_name && \defined( $constant_name ) ) {
			return \ewww_image_optimizer_disable_resizes_sanitize( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_disable_resizes_opt' === $option_name && \defined( $constant_name ) ) {
			return \ewww_image_optimizer_disable_resizes_sanitize( \constant( $constant_name ) );
		}
		if ( 'ewww_image_optimizer_jpg_background' === $option_name && \defined( $constant_name ) ) {
			return \ewww_image_optimizer_jpg_background( \constant( $constant_name ) );
		}
		// NOTE: For Easy IO, we bail here, because we don't have a network settings page AND because there's no 'allow_multisite_override' option,
		// which would slow things down due to lack of auto-loading. If/when we add those things, then we can unlock the multi-site logic below.
		if ( 'EasyIO' === __NAMESPACE__ ) {
			return \get_option( $option_name );
		}
		if ( \is_null( self::$use_network_options ) ) {
			self::$use_network_options = false;
			if ( ! \function_exists( 'is_plugin_active_for_network' ) && \is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if (
				\is_multisite() &&
				\defined( \strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) &&
				\is_plugin_active_for_network( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) &&
				! \get_site_option( $this->prefix . 'allow_multisite_override' )
			) {
				self::$use_network_options = true;
			}
		}
		if ( ! $single && self::$use_network_options ) {
			$option_value = \get_site_option( $option_name );
			if ( 'ewww_image_optimizer_exactdn' === $option_name && ! $option_value ) {
				$option_value = \get_option( $option_name );
			}
		} else {
			$option_value = \get_option( $option_name );
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
	public function implode( $delimiter, $data = '' ) {
		if ( \is_array( $delimiter ) ) {
			$temp_data = $delimiter;
			$delimiter = $data;
			$data      = $temp_data;
		}
		if ( \is_array( $delimiter ) ) {
			return '';
		}
		$output = '';
		foreach ( $data as $value ) {
			if ( \is_string( $value ) || \is_numeric( $value ) ) {
				$output .= $value . $delimiter;
			} elseif ( \is_bool( $value ) ) {
				$output .= ( $value ? 'true' : 'false' ) . $delimiter;
			} elseif ( \is_array( $value ) ) {
				$output .= 'Array,';
			}
		}
		return \rtrim( $output, ',' );
	}

	/**
	 * Checks to see if the current page being output is an AMP page.
	 *
	 * @return bool True for an AMP endpoint, false otherwise.
	 */
	public function is_amp() {
		// Just return false if we can't properly check yet.
		if ( ! \did_action( 'parse_request' ) ) {
			return false;
		}
		if ( ! \did_action( 'parse_query' ) ) {
			return false;
		}
		if ( ! \did_action( 'wp' ) ) {
			return false;
		}

		if ( \function_exists( '\amp_is_request' ) && \amp_is_request() ) {
			return true;
		}
		if ( \function_exists( '\is_amp_endpoint' ) && \is_amp_endpoint() ) {
			return true;
		}
		if ( \function_exists( '\ampforwp_is_amp_endpoint' ) && \ampforwp_is_amp_endpoint() ) {
			return true;
		}
		return false;
	}

	/**
	 * Make sure an array/object can be parsed by a foreach().
	 *
	 * @param mixed $value A variable to test for iteration ability.
	 * @return bool True if the variable is iterable and not empty.
	 */
	public function is_iterable( $value ) {
		return ! empty( $value ) && is_iterable( $value );
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
	public function is_json( $buffer ) {
		if ( '{' === \substr( $buffer, 0, 1 ) && '}' === \substr( $buffer, -1 ) ) {
			return true;
		}
		if ( '[' === \substr( $buffer, 0, 1 ) && ']' === \substr( $buffer, -1 ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Make sure this is really and truly a "front-end request", excluding page builders and such.
	 * NOTE: this is not currently used anywhere, each module has it's own list.
	 *
	 * @return bool True for front-end requests, false for admin/builder requests.
	 */
	public function is_frontend() {
		if ( \is_admin() ) {
			return false;
		}
		$uri = \add_query_arg( '', '' );
		if (
			\strpos( $uri, 'cornerstone=' ) !== false ||
			\strpos( $uri, 'cornerstone-endpoint' ) !== false ||
			\strpos( $uri, 'ct_builder=' ) !== false ||
			\did_action( 'cornerstone_boot_app' ) || \did_action( 'cs_before_preview_frame' ) ||
			'/print/' === substr( $uri, -7 ) ||
			\strpos( $uri, 'elementor-preview=' ) !== false ||
			\strpos( $uri, 'et_fb=' ) !== false ||
			\strpos( $uri, 'is-editor-iframe=' ) !== false ||
			\strpos( $uri, 'vc_editable=' ) !== false ||
			\strpos( $uri, 'tatsu=' ) !== false ||
			( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === \sanitize_text_field( \wp_unslash( $_POST['action'] ) ) ) || // phpcs:ignore WordPress.Security.NonceVerification
			\strpos( $uri, 'wp-login.php' ) !== false ||
			\is_embed() ||
			\is_feed() ||
			\is_preview() ||
			\is_customize_preview() ||
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
	public function is_lazy_placeholder( $image ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if (
			\strpos( $image, 'base64,R0lGOD' ) ||
			\strpos( $image, 'lazy-load/images/1x1' ) ||
			\strpos( $image, '/assets/images/lazy' ) ||
			\strpos( $image, '/assets/images/dummy.png' ) ||
			\strpos( $image, '/assets/images/transparent.png' ) ||
			\strpos( $image, '/lazy/placeholder' )
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
	public function is_file( $file ) {
		if ( empty( $file ) ) {
			return false;
		}
		if ( false !== \strpos( $file, '://' ) ) {
			return false;
		}
		if ( false !== \strpos( $file, 'phar://' ) ) {
			return false;
		}
		return \is_file( $file );
	}

	/**
	 * Check if a file/directory is readable.
	 *
	 * @param string $file The path to check.
	 * @return bool True if it is, false if it ain't.
	 */
	public function is_readable( $file ) {
		$this->get_filesystem();
		return $this->filesystem->is_readable( $file );
	}

	/**
	 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
	 *
	 * @param string $file The name of the file.
	 * @return int The size of the file or zero.
	 */
	public function filesize( $file ) {
		$file = \realpath( $file );
		if ( $this->is_file( $file ) ) {
			$this->get_filesystem();
			// Flush the cache for filesize.
			\clearstatcache();
			// Find out the size of the new PNG file.
			return $this->filesystem->size( $file );
		} else {
			return 0;
		}
	}

	/**
	 * Check if file is in an approved location and remove it.
	 *
	 * @param string $file The path of the file to check.
	 * @param string $dir The path of the folder constraint. Optional.
	 * @return bool True if the file was removed, false otherwise.
	 */
	public function delete_file( $file, $dir = '' ) {
		$file = \realpath( $file );
		if ( ! empty( $dir ) ) {
			return \wp_delete_file_from_directory( $file, $dir );
		}

		$wp_dir      = \realpath( ABSPATH );
		$upload_dir  = \wp_get_upload_dir();
		$upload_dir  = \realpath( $upload_dir['basedir'] );
		$content_dir = \realpath( WP_CONTENT_DIR );

		if ( false !== \strpos( $file, $upload_dir ) ) {
			return \wp_delete_file_from_directory( $file, $upload_dir );
		}
		if ( false !== \strpos( $file, $content_dir ) ) {
			return \wp_delete_file_from_directory( $file, $content_dir );
		}
		if ( false !== \strpos( $file, $wp_dir ) ) {
			return \wp_delete_file_from_directory( $file, $wp_dir );
		}
		return false;
	}

	/**
	 * Setup the filesystem class.
	 */
	public function get_filesystem() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			\define( 'FS_CHMOD_DIR', ( \fileperms( ABSPATH ) & 0777 | 0755 ) );
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			\define( 'FS_CHMOD_FILE', ( \fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}
		if ( ! \is_object( $this->filesystem ) ) {
			$this->filesystem = new \WP_Filesystem_Direct( '' );
		}
	}

	/**
	 * Check the mimetype of the given file with magic mime strings/patterns.
	 *
	 * @param string $path The absolute path to the file.
	 * @param string $category The type of file we are checking. Default 'i' for
	 *                     images/pdfs or 'b' for binary.
	 * @return bool|string A valid mime-type or false.
	 */
	public function mimetype( $path, $category = 'i' ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "testing mimetype: $path" );
		$type = false;
		// For S3 images/files, don't attempt to read the file, just use the quick (filename) mime check.
		if ( 'i' === $category && $this->stream_wrapped( $path ) ) {
			return $this->quick_mimetype( $path );
		}
		$path = \realpath( $path );
		if ( ! $this->is_file( $path ) ) {
			$this->debug_message( "$path is not a file, or out of bounds" );
			return $type;
		}
		if ( ! \is_readable( $path ) ) {
			$this->debug_message( "$path is not readable" );
			return $type;
		}
		if ( 'i' === $category ) {
			$file_handle   = \fopen( $path, 'rb' );
			$file_contents = \fread( $file_handle, 4096 );
			if ( $file_contents ) {
				// Read first 12 bytes, which equates to 24 hex characters.
				$magic = \bin2hex( \substr( $file_contents, 0, 12 ) );
				$this->debug_message( $magic );
				if ( '424d' === \substr( $magic, 0, 4 ) ) {
					$type = 'image/bmp';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( 0 === \strpos( $magic, '52494646' ) && 16 === \strpos( $magic, '57454250' ) ) {
					$type = 'image/webp';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( 'ffd8ff' === \substr( $magic, 0, 6 ) ) {
					$type = 'image/jpeg';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '89504e470d0a1a0a' === \substr( $magic, 0, 16 ) ) {
					$type = 'image/png';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '474946383761' === \substr( $magic, 0, 12 ) || '474946383961' === \substr( $magic, 0, 12 ) ) {
					$type = 'image/gif';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( '25504446' === \substr( $magic, 0, 8 ) ) {
					$type = 'application/pdf';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				if ( \preg_match( '/<svg/', $file_contents ) ) {
					$type = 'image/svg+xml';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				$this->debug_message( "match not found for image: $magic" );
			} else {
				$this->debug_message( 'could not open for reading' );
			}
		}
		if ( 'b' === $category ) {
			$file_handle   = fopen( $path, 'rb' );
			$file_contents = fread( $file_handle, 12 );
			if ( $file_contents ) {
				// Read first 4 bytes, which equates to 8 hex characters.
				$magic = \bin2hex( \substr( $file_contents, 0, 4 ) );
				$this->debug_message( $magic );
				// Mac (Mach-O) binary.
				if ( 'cffaedfe' === $magic || 'feedface' === $magic || 'feedfacf' === $magic || 'cefaedfe' === $magic || 'cafebabe' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				// ELF (Linux or BSD) binary.
				if ( '7f454c46' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				// MS (DOS) binary.
				if ( '4d5a9000' === $magic ) {
					$type = 'application/x-executable';
					$this->debug_message( "ewwwio type: $type" );
					return $type;
				}
				$this->debug_message( "match not found for binary: $magic" );
			} else {
				$this->debug_message( 'could not open for reading' );
			}
		}
		return false;
	}

	/**
	 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
	 *
	 * @param string $path The name of the file.
	 * @return string|bool The mime type based on the extension or false.
	 */
	public function quick_mimetype( $path ) {
		$pathextension = \strtolower( \pathinfo( $path, PATHINFO_EXTENSION ) );
		switch ( $pathextension ) {
			case 'bmp':
				return 'image/bmp';
			case 'jpg':
			case 'jpeg':
			case 'jpe':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			case 'gif':
				return 'image/gif';
			case 'webp':
				return 'image/webp';
			case 'pdf':
				return 'application/pdf';
			case 'svg':
				return 'image/svg+xml';
			default:
				if ( empty( $pathextension ) && ! $this->stream_wrapped( $path ) && $this->is_file( $path ) ) {
					return $this->mimetype( $path, 'i' );
				}
				return false;
		}
	}

	/**
	 * Get the PNG type: PNG8, PNG24, PNG32, or other.
	 *
	 * From https://stackoverflow.com/questions/57547818/php-detect-png8-or-png24
	 *
	 * @param string $path The name of the file.
	 * @return string The type of PNG, or an empty string.
	 */
	public function get_png_depth( $path ) {
		$png_type = '';
		if ( $this->filesize( $path ) < 24 ) {
			return $png_type;
		}

		$file_handle = fopen( $path, 'rb' );

		$png_header = fread( $file_handle, 4 );
		if ( chr( 0x89 ) . 'PNG' !== $png_header ) {
			return $png_type;
		}

		// Move forward 8 bytes.
		fread( $file_handle, 8 );
		$png_ihdr = fread( $file_handle, 4 );

		// Make sure we have an IHDR.
		if ( 'IHDR' !== $png_ihdr ) {
			return $png_type;
		}

		// Skip past the dimensions.
		$dimensions = fread( $file_handle, 8 );

		// Bit depth: 1 byte
		// Bit depth is a single-byte integer giving the number of bits per sample or
		// per palette index (not per pixel).
		//
		// Valid values are 1, 2, 4, 8, and 16, although not all values are allowed for all color types.
		$bit_depth = ord( (string) fread( $file_handle, 1 ) );

		// Color type is a single-byte integer that describes the interpretation of the image data.
		// Color type codes represent sums of the following values:
		// 1 (palette used), 2 (color used), and 4 (alpha channel used).
		// The valid color types are:
		// 0 => Grayscale
		// 2 => Truecolor
		// 3 => Indexed
		// 4 => Greyscale with alpha
		// 6 => Truecolour with alpha
		//
		// Valid values are 0, 2, 3, 4, and 6.
		$color_type = ord( (string) fread( $file_handle, 1 ) );

		// Note that none of these names are "official", and some we have made up to reference particular bit-depths.
		// If the bitdepth is 1 and the colortype is 3 (Indexed color) you have a PNG1 with 2 colors.
		if ( 1 === $bit_depth && 3 === $color_type ) {
			$png_type = 'PNG1';
		}

		// If the bitdepth is 2 and the colortype is 3 (Indexed color) you have a PNG2 with 4 colors.
		if ( 2 === $bit_depth && 3 === $color_type ) {
			$png_type = 'PNG2';
		}

		// If the bitdepth is 4 and the colortype is 3 (Indexed color) you have a PNG4 with 16 colors.
		if ( 4 === $bit_depth && 3 === $color_type ) {
			$png_type = 'PNG4';
		}

		// If the bitdepth is 8 and the colortype is 3 (Indexed color) you have a PNG8 with 256 colors.
		if ( 8 === $bit_depth && 3 === $color_type ) {
			$png_type = 'PNG8';
		}

		// If the bitdepth is 8 and colortype is 2 (Truecolor) you have a PNG24.
		if ( 8 === $bit_depth && 2 === $color_type ) {
			$png_type = 'PNG24';
		}

		// If the bitdepth is 8 and colortype is 6 (Truecolor with alpha) you have a PNG32.
		if ( 8 === $bit_depth && 6 === $color_type ) {
			$png_type = 'PNG32';
		}
		return $png_type;
	}

	/**
	 * Checks if there is enough memory still available.
	 *
	 * Looks to see if the current usage + padding will fit within the memory_limit defined by PHP.
	 *
	 * @param int $padding Optional. The amount of memory needed to continue. Default 1050000.
	 * @return True to proceed, false if there is not enough memory.
	 */
	public function check_memory_available( $padding = 1050000 ) {
		$memory_limit = $this->memory_limit();

		$current_memory = \memory_get_usage( true ) + $padding;
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
	public function memory_limit() {
		if ( \defined( 'EIO_MEMORY_LIMIT' ) && EIO_MEMORY_LIMIT ) {
			$memory_limit = EIO_MEMORY_LIMIT;
		} elseif ( \function_exists( 'ini_get' ) ) {
			$memory_limit = \ini_get( 'memory_limit' );
		} else {
			if ( ! \defined( 'EIO_MEMORY_LIMIT' ) ) {
				// Conservative default, current usage + 16M.
				$current_memory = \memory_get_usage( true );
				$memory_limit   = \round( $current_memory / ( 1024 * 1024 ) ) + 16;
				define( 'EIO_MEMORY_LIMIT', $memory_limit );
			}
		}
		if ( \defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::debug( "memory limit is set at $memory_limit" );
		}
		if ( ! $memory_limit || -1 === \intval( $memory_limit ) ) {
			// Unlimited, set to 32GB.
			$memory_limit = '32000M';
		}
		if ( \stripos( $memory_limit, 'g' ) ) {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024 * 1024;
		} else {
			$memory_limit = \intval( $memory_limit ) * 1024 * 1024;
		}
		return $memory_limit;
	}

	/**
	 * Clear output buffers without throwing a fit.
	 */
	public function ob_clean() {
		if ( \ob_get_length() ) {
			\ob_end_clean();
		}
	}

	/**
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	public function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}

		$len = \strlen( $needle );

		return 0 === \substr_compare( $haystack, $needle, -$len, $len );
	}

	/**
	 * Trims the given 'needle' from the end of the 'haystack'.
	 *
	 * @param string $haystack The string to be modified if it contains needle.
	 * @param string $needle The string to remove if it is at the end of the haystack.
	 * @return string The haystack with needle removed from the end.
	 */
	public function remove_from_end( $haystack, $needle ) {
		$needle_length = strlen( $needle );
		if ( substr( $haystack, -$needle_length ) === $needle ) {
			return substr( $haystack, 0, -$needle_length );
		}
		return $haystack;
	}

	/**
	 * Set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
	 *
	 * @param string $option_name The name of the option to save.
	 * @param mixed  $option_value The value to save for the option.
	 * @return bool True if the operation was successful.
	 */
	public function set_option( $option_name, $option_value ) {
		if ( \is_null( self::$use_network_options ) ) {
			self::$use_network_options = false;
			if ( ! \function_exists( '\is_plugin_active_for_network' ) && \is_multisite() ) {
				// Need to include the plugin library for the is_plugin_active function.
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if (
				\is_multisite() &&
				\is_plugin_active_for_network( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE_REL' ) ) &&
				! \get_site_option( $this->prefix . 'allow_multisite_override' )
			) {
				self::$use_network_options = true;
			}
		}
		if ( self::$use_network_options ) {
			$success = \update_site_option( $option_name, $option_value );
		} else {
			$success = \update_option( $option_name, $option_value );
		}
		return $success;
	}

	/**
	 * Convert a gallery name to the corresponding integer/ID.
	 *
	 * Used for the $gallery_type parameter of the ewww_image_optimizer() function.
	 *
	 * @param string $gallery_name The gallery identificer, like 'media', 'nextgen', etc.
	 * @return int The ID for the gallery/type of image.
	 */
	public function gallery_name_to_id( $gallery_name ) {
		switch ( $gallery_name ) {
			case 'media':
				return 1;
			case 'nextgen':
			case 'nextcell':
				return 2;
			case 'flag':
				return 3;
			default:
				return 4;
		}
	}

	/**
	 * Checks the filename for an S3 or GCS stream wrapper.
	 *
	 * @param string $filename The filename to be searched.
	 * @return bool True if a stream wrapper is found, false otherwise.
	 */
	public function stream_wrapped( $filename ) {
		if ( false !== \strpos( $filename, '://' ) ) {
			if ( \strpos( $filename, 's3' ) === 0 ) {
				return true;
			}
			if ( \strpos( $filename, 'gs' ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Attempts to reverse a CDN (or multi-lingual) URL to a local path to test for file existence.
	 *
	 * Used for supporting pull-mode CDNs mostly, or push-mode if local copies exist.
	 *
	 * @param string $url The image URL to mangle.
	 * @return string The path to a local file correlating to the CDN URL, an empty string otherwise.
	 */
	public function cdn_to_local( $url ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $this->site_url ) ) {
			$this->content_url();
		}
		if ( ! $this->is_iterable( $this->allowed_domains ) ) {
			return false;
		}
		if ( 0 === \strpos( $url, $this->home_url ) ) {
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
				false !== \strpos( $url, $this->s3_active ) &&
				(
					( false !== \strpos( $this->s3_active, '/' ) ) ||
					( ! empty( $this->s3_object_prefix ) && false !== \strpos( $url, $this->s3_object_prefix ) )
				)
			) {
				// We will wait until the paths loop to fix this one.
				$this->debug_message( 'skipping domains and going to URLs' );
				continue;
			}
			if ( false !== \strpos( $url, $allowed_domain ) ) {
				$local_url = \str_replace( $allowed_domain, $this->home_domain, $url );
				$this->debug_message( "found $allowed_domain, replaced with $this->home_domain to get $local_url" );
				$path = $this->url_to_path_exists( $local_url );
				if ( $path ) {
					return $path;
				}
			}
		}
		foreach ( $this->allowed_urls as $allowed_url ) {
			if ( false === \strpos( $allowed_url, 'http' ) ) {
				continue;
			}
			$this->debug_message( "looking for path $allowed_url in $url" );
			if ( ! empty( $this->s3_active ) && ! empty( $this->s3_object_prefix ) ) {
				$this->debug_message( "checking first for $this->s3_active and $allowed_url" . $this->s3_object_prefix );
			}
			if (
				! empty( $this->s3_active ) && // We've got an S3 configuration, and...
				false !== \strpos( $url, $this->s3_active ) && // the S3 domain is present in the URL, and...
				! empty( $this->s3_object_prefix ) && // there could be an S3 object prefix to contend with, and...
				0 === \strpos( $url, $allowed_url . $this->s3_object_prefix ) // "allowed_url" + the object prefix matches the URL.
			) {
				$local_url = \str_replace( $allowed_url . $this->s3_object_prefix, $this->upload_url, $url );
				$this->debug_message( "found $allowed_url (and $this->s3_object_prefix), replaced with $this->upload_url to get $local_url" );
				$path = $this->url_to_path_exists( $local_url );
				if ( $path ) {
					return $path;
				}
			}
			if ( false !== \strpos( $url, $allowed_url ) ) {
				$local_url = \str_replace( $allowed_url, $this->upload_url, $url );
				$this->debug_message( "found $allowed_url, replaced with $this->upload_url to get $local_url" );
				$path = $this->url_to_path_exists( $local_url );
				if ( ! $path ) {
					// This won't work if the upload dir is outside ABSPATH, but normally it does fix sub-folder multisites.
					$local_url = \str_replace( $allowed_url, $this->home_url, $url );
					$this->debug_message( "found $allowed_url, replaced with $this->home_url to get $local_url" );
					$path = $this->url_to_path_exists( $local_url );
				}
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
	public function url_to_path_exists( $url, $extension = '' ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $this->site_url ) ) {
			$this->content_url();
		}
		$this->debug_message( "trying to find path for $url" );
		$url  = $this->maybe_strip_object_version( $url );
		$path = '';
		if ( '/' === \substr( $url, 0, 1 ) && '/' !== \substr( $url, 1, 1 ) ) {
			$this->debug_message( "found relative URL: $url" );
			$url = '//' . $this->upload_domain . $url;
			$this->debug_message( "and changed to $url for path checking" );
		}
		if ( 0 === \strpos( $url, WP_CONTENT_URL ) ) {
			$path = \str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $url );
			$this->debug_message( "trying $path based on " . WP_CONTENT_URL );
		} elseif ( 0 === \strpos( $url, $this->relative_home_url ) ) {
			$path = \str_replace( $this->relative_home_url, ABSPATH, $url );
			$this->debug_message( "trying $path based on " . $this->relative_home_url );
		} elseif ( 0 === \strpos( $url, $this->home_url ) ) {
			$path = \str_replace( $this->home_url, ABSPATH, $url );
			$this->debug_message( "trying $path based on " . $this->home_url );
		} else {
			$this->debug_message( 'not a valid local image' );
			return false;
		}
		$path_parts = \explode( '?', $path );
		if ( $this->is_file( $path_parts[0] . $extension ) ) {
			$this->debug_message( 'local file found' );
			return $path_parts[0];
		}
		if ( \class_exists( '\HMWP_Classes_ObjController' ) ) {
			$hmwp_file_handler = \HMWP_Classes_ObjController::getClass( 'HMWP_Models_Files' );
			if ( \is_object( $hmwp_file_handler ) ) {
				$original_url = $hmwp_file_handler->getOriginalURL( $url );
				$this->debug_message( "found $original_url from HMWP" );
				$path = $hmwp_file_handler->getOriginalPath( $original_url );
				$this->debug_message( "trying $path from HMWP" );
				$path_parts = \explode( '?', $path );
				if ( $this->is_file( $path_parts[0] . $extension ) ) {
					$this->debug_message( 'local file found' );
					return $path_parts[0];
				}
			}
		}
		return false;
	}

	/**
	 * Remove S3 object versioning from URL.
	 *
	 * @param string $url The image URL with a potential version string embedded.
	 * @return string The URL without a version string.
	 */
	public function maybe_strip_object_version( $url ) {
		if ( ! empty( $this->s3_object_version ) ) {
			$possible_version = \wp_basename( \dirname( $url ) );
			if (
				! empty( $possible_version ) &&
				8 === \strlen( $possible_version ) &&
				\ctype_digit( $possible_version )
			) {
				$url = \str_replace( '/' . $possible_version . '/', '/', $url );
				$this->debug_message( "removed version $possible_version from $url" );
			} elseif (
				! empty( $possible_version ) &&
				14 === \strlen( $possible_version ) &&
				\ctype_digit( $possible_version )
			) {
				$year  = \substr( $possible_version, 0, 4 );
				$month = \substr( $possible_version, 4, 2 );
				$url   = \str_replace( '/' . $possible_version . '/', "/$year/$month/", $url );
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
	public function parse_url( $url, $component = -1 ) {
		if ( empty( $url ) ) {
			return false;
		}
		if ( 0 === \strpos( $url, '//' ) ) {
			$url = ( \is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( false === \strpos( $url, 'http' ) && '/' !== \substr( $url, 0, 1 ) ) {
			$url = ( \is_ssl() ? 'https://' : 'http://' ) . $url;
		}
		// Because encoded ampersands in the filename break things.
		$url = \html_entity_decode( $url );
		return \parse_url( $url, $component );
	}

	/**
	 * Get the shortest version of the content URL.
	 *
	 * NOTE: there might, maybe, be cases where the upload URL does not match the detected site URL.
	 * If that happens, we'll want to extend $this->content_url() to compensate using the URL from wp_get_site_url().
	 *
	 * Also, home_url is intended to be a "local" content URL, simply using get_site_url().
	 * It is NOT the actual home URL value/setting, which would normally point to the "home" page.
	 * The site_url, on the other hand, is intended to be the shortest version of the content/upload URL.
	 * Thus it might be different than home_url for a sub-directory install:
	 * site_url = https://example.com/ vs. home_url = https://example.com/wordpress/
	 * It would also be different if the site is using cloud storage: https://example.s3.amazonaws.com
	 *
	 * @return string The URL where the content lives.
	 */
	public function content_url() {
		if ( $this->site_url ) {
			return $this->site_url;
		}
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->site_url = \get_home_url();
		global $as3cf;
		if ( \class_exists( '\Amazon_S3_And_CloudFront' ) && \is_object( $as3cf ) ) {
			$s3_scheme = $as3cf->get_url_scheme();
			$s3_region = $as3cf->get_setting( 'region' );
			$s3_bucket = $as3cf->get_setting( 'bucket' );
			if ( \is_wp_error( $s3_region ) ) {
				$s3_region = '';
			}
			$s3_domain = '';
			if ( ! empty( $s3_bucket ) && ! \is_wp_error( $s3_bucket ) && \method_exists( $as3cf, 'get_provider' ) ) {
				$s3_domain = $as3cf->get_provider()->get_url_domain( $s3_bucket, $s3_region, null, array(), true );
			} elseif ( ! empty( $s3_bucket ) && ! \is_wp_error( $s3_bucket ) && \method_exists( $as3cf, 'get_storage_provider' ) ) {
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
					$this->allowed_urls[]    = $s3_scheme . '://' . \trailingslashit( $delivery_domain ) . \trailingslashit( \trim( $this->s3_object_prefix, '/' ) );
					$this->allowed_domains[] = $delivery_domain;
					$this->debug_message( "found WOM delivery domain of $delivery_domain" );
				}
			}
			if ( $as3cf->get_setting( 'object-versioning' ) ) {
				$this->s3_object_version = true;
				$this->debug_message( 'object versioning enabled' );
			}
		}

		if ( $this->s3_uploads_enabled() ) {
			if ( \method_exists( '\S3_Uploads\Plugin', 'get_instance' ) && \method_exists( '\S3_Uploads\Plugin', 'get_s3_url' ) ) {
				$s3_uploads_instance = \S3_Uploads\Plugin::get_instance();
				$s3_uploads_url      = $s3_uploads_instance->get_s3_url();
			} elseif ( \method_exists( '\S3_Uploads', 'get_instance' ) && \method_exists( '\S3_Uploads', 'get_s3_url' ) ) {
				$s3_uploads_instance = \S3_Uploads::get_instance();
				$s3_uploads_url      = $s3_uploads_instance->get_s3_url();
			}
			if ( ! empty( $s3_uploads_url ) ) {
				$this->allowed_urls[] = $s3_uploads_url;
				$this->debug_message( "found S3 URL from S3_Uploads: $s3_uploads_url" );
				$s3_domain       = $this->parse_url( $s3_uploads_url, PHP_URL_HOST );
				$s3_scheme       = $this->parse_url( $s3_uploads_url, PHP_URL_SCHEME );
				$this->s3_active = $s3_domain;
			}
		}

		if ( \class_exists( '\wpCloud\StatelessMedia\EWWW' ) && \function_exists( '\ud_get_stateless_media' ) ) {
			$sm = \ud_get_stateless_media();
			if ( \method_exists( $sm, 'get' ) && \method_exists( $sm, 'get_gs_host' ) ) {
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
		if ( __NAMESPACE__ . '\ExactDN' !== \get_class( $this ) && __NAMESPACE__ . '\Base' !== \get_class( $this ) && \function_exists( '\swis' ) && \is_object( \swis()->settings ) && \swis()->settings->get_option( 'cdn_domain' ) ) {
			$this->allowed_urls[]    = \swis()->settings->get_option( 'cdn_domain' );
			$this->allowed_domains[] = $this->parse_url( \swis()->settings->get_option( 'cdn_domain' ), PHP_URL_HOST );
		}

		$upload_dir = \wp_get_upload_dir();
		if ( $this->s3_active ) {
			$this->site_url = \defined( 'EXACTDN_LOCAL_DOMAIN' ) && EXACTDN_LOCAL_DOMAIN ? EXACTDN_LOCAL_DOMAIN : $s3_scheme . '://' . $s3_domain;
		} else {
			// Normally, we use this one, as it will be shorter for sub-directory (not multi-site) installs.
			$home_url    = \get_home_url();
			$site_url    = \get_site_url();
			$home_domain = $this->parse_url( $home_url, PHP_URL_HOST );
			$site_domain = $this->parse_url( $site_url, PHP_URL_HOST );
			// If the home domain does not match the upload url, and the site domain does match...
			if ( $home_domain && false === \strpos( $upload_dir['baseurl'], $home_domain ) && $site_domain && false !== \strpos( $upload_dir['baseurl'], $site_domain ) ) {
				$this->debug_message( "using WP URL (via get_site_url) with $site_domain rather than $home_domain" );
				$home_url = $site_url;
			}
			$this->site_url = \defined( 'EXACTDN_LOCAL_DOMAIN' ) && EXACTDN_LOCAL_DOMAIN ? EXACTDN_LOCAL_DOMAIN : $home_url;
		}
		// This is used by the WebP parsers, and by the Lazy Load via get_image_dimensions_by_url().
		$this->upload_url = \trailingslashit( ! empty( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : \content_url( 'uploads' ) );
		if ( \is_multisite() ) {
			// Check for a site-specific suffix and remove it.
			$current_blog_id = get_current_blog_id();
			if ( \str_ends_with( $this->upload_url, 'sites/' . $current_blog_id . '/' ) ) {
				$this->upload_url = $this->remove_from_end( $this->upload_url, 'sites/' . $current_blog_id . '/' );
			} elseif ( \str_ends_with( $this->upload_url, '/' . $current_blog_id . '/' ) ) {
				$this->upload_url = $this->remove_from_end( $this->upload_url, $current_blog_id . '/' );
			}
		}

		// But this is used by Easy IO, so it should be derived from the above logic instead, which already matches the site/home URLs against the upload URL.
		$this->upload_domain     = $this->parse_url( $this->site_url, PHP_URL_HOST );
		$this->allowed_domains[] = $this->upload_domain;
		// For when plugins don't do a very good job of updating URLs for mapped multi-site domains.
		if ( ! $this->s3_active && \is_multisite() && false === \strpos( $upload_dir['baseurl'], $this->upload_domain ) ) {
			$this->debug_message( 'upload domain does not match the home URL' );
			$origin_upload_domain = $this->parse_url( $upload_dir['baseurl'], PHP_URL_HOST );
			if ( $origin_upload_domain ) {
				$this->allowed_domains[] = $origin_upload_domain;
			}
		}
		// Grab domain aliases that might point to the same place as the upload_domain.
		if ( ! $this->s3_active && 0 !== \strpos( $this->upload_domain, 'www' ) ) {
			$this->allowed_domains[] = 'www.' . $this->upload_domain;
		} elseif ( 0 === \strpos( $this->upload_domain, 'www.' ) ) {
			$nonwww = \ltrim( \ltrim( $this->upload_domain, 'w' ), '.' );
			if ( $nonwww && $nonwww !== $this->upload_domain ) {
				$this->allowed_domains[] = $nonwww;
			}
		}
		if ( ! $this->s3_active || __NAMESPACE__ . '\ExactDN' !== \get_class( $this ) ) {
			$wpml_domains = \apply_filters( 'wpml_setting', array(), 'language_domains' );
			if ( $this->is_iterable( $wpml_domains ) ) {
				$this->debug_message( 'wpml domains: ' . \implode( ',', $wpml_domains ) );
				$this->allowed_domains[] = $this->parse_url( \get_option( 'home' ), PHP_URL_HOST );
				$wpml_scheme             = $this->parse_url( $this->upload_url, PHP_URL_SCHEME );
				foreach ( $wpml_domains as $wpml_domain ) {
					$this->allowed_domains[] = $wpml_domain;
					$this->allowed_urls[]    = $wpml_scheme . '://' . $wpml_domain;
				}
			}
		}
		$this->debug_message( "site/upload url: $this->site_url" );
		$this->debug_message( "site/upload domain: $this->upload_domain" );
		$this->debug_message( "upload_url: $this->upload_url" );
		return $this->site_url;
	}

	/**
	 * Takes the list of allowed URLs and parses out the domain names.
	 */
	public function get_allowed_domains() {
		if ( ! $this->is_iterable( $this->allowed_urls ) ) {
			return;
		}
		foreach ( $this->allowed_urls as $allowed_url ) {
			$allowed_domain = $this->parse_url( $allowed_url, PHP_URL_HOST );
			if ( $allowed_domain && ! \in_array( $allowed_domain, $this->allowed_domains, true ) ) {
				$this->allowed_domains[] = $allowed_domain;
			}
		}
	}
}
