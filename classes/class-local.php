<?php
/**
 * Class for local optimization/conversion features.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for local optimization/conversion features.
 */
class Local extends Base {

	/**
	 * List of local tools with paths and enabled status.
	 *
	 * @access protected
	 * @var array
	 */
	protected $tools = array(
		'cwebp'      => array( 'enabled' => false ),
		'gifsicle'   => array( 'enabled' => true ),
		'jpegtran'   => array( 'enabled' => true ),
		'optipng'    => array( 'enabled' => true ),
		'pngout'     => array( 'enabled' => false ),
		'pngquant'   => array( 'enabled' => false ),
		'svgcleaner' => array( 'enabled' => false ),
	);

	/**
	 * Whether exec() function is allowed.
	 *
	 * @access protected
	 * @var bool
	 */
	protected $exec_enabled;

	/**
	 * Initialize the local properties we'll need later, since tools are late-initialized except on specific pages.
	 */
	public function __construct() {
		parent::__construct();
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->exec_check();
		$this->skip_tools();
	}

	/**
	 * Disables all the local tools.
	 */
	public function disable_tools() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		foreach ( $this->tools as $tool => $status ) {
			$this->tools[ $tool ]['enabled'] = false;
			$this->tools[ $tool ]['path']    = '';
		}
	}

	/**
	 * Checks if exec() is allowed.
	 *
	 * @return bool True if exec() is enabled.
	 */
	public function exec_check() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( isset( $this->exec_enabled ) ) {
			return (bool) $this->exec_enabled;
		}
		if (
			\defined( 'WPCOMSH_VERSION' ) ||
			! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ||
			\defined( 'WPE_PLUGIN_VERSION' ) ||
			\defined( 'FLYWHEEL_CONFIG_DIR' ) ||
			\defined( 'KINSTAMU_VERSION' ) ||
			\defined( 'WPNET_INIT_PLUGIN_VERSION' )
		) {
			$this->disable_tools();
			$this->exec_enabled = false;
			return false;
		}
		if ( $this->function_exists( '\exec' ) ) {
			$this->exec_enabled = true;
			return true;
		}
		$this->debug_message( 'exec appears to be disabled' );
		$this->disable_tools();
		$this->exec_enabled = false;
		return false;
	}

	/**
	 * Check if local mode is supported on this operating system.
	 *
	 * @return bool True if the PHP_OS is supported, false otherwise.
	 */
	public function os_supported() {
		$supported_oss = array(
			'Linux',
			'Darwin',
			'FreeBSD',
			'WINNT',
		);
		return \in_array( PHP_OS, $supported_oss, true );
	}

	/**
	 * Checks which tools should be skipped or enabled.
	 */
	public function skip_tools() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// If the user has disabled a tool, we aren't going to bother checking to see if it is there.
		foreach ( $this->tools as $tool => $status ) {
			if ( $this->tool_enabled( $tool ) ) {
				$this->debug_message( "enabled: $tool" );
				$this->tools[ $tool ]['enabled'] = true;
			} else {
				$this->tools[ $tool ]['enabled'] = false;
			}
		}
	}

	/**
	 * Checks if a given tool should be enabled.
	 *
	 * @param string $tool The name of the tool to check/test.
	 * @return bool True if the tool should be enabled.
	 */
	public function tool_enabled( $tool ) {
		if ( ! $this->exec_enabled ) {
			return false;
		}
		if ( ! $this->os_supported() ) {
			return false;
		}
		switch ( $tool ) {
			case 'jpegtran':
				if ( 10 === (int) $this->get_option( 'ewww_image_optimizer_jpg_level' ) ) {
					return true;
				}
				break;
			case 'optipng':
				if ( $this->get_option( 'ewww_image_optimizer_png_level' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					return true;
				}
				if ( 10 === (int) $this->get_option( 'ewww_image_optimizer_png_level' ) ) {
					return true;
				}
				break;
			case 'gifsicle':
				if ( $this->get_option( 'ewww_image_optimizer_gif_level' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					return true;
				}
				break;
			case 'pngout':
				if ( ! $this->get_option( 'ewww_image_optimizer_disable_pngout' ) && $this->get_option( 'ewww_image_optimizer_png_level' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					return true;
				}
				break;
			case 'pngquant':
				if ( 40 === (int) $this->get_option( 'ewww_image_optimizer_png_level' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					return true;
				}
				break;
			case 'cwebp':
				if ( $this->get_option( 'ewww_image_optimizer_webp' ) && ! ( $this->get_option( 'ewww_image_optimizer_cloud_key' ) && $this->get_option( 'ewww_image_optimizer_jpg_level' ) > 10 && $this->get_option( 'ewww_image_optimizer_png_level' ) > 10 ) ) {
					return true;
				}
				break;
			case 'svgcleaner':
				if ( ! $this->get_option( 'ewww_image_optimizer_disable_svgcleaner' ) && $this->get_option( 'ewww_image_optimizer_svg_level' ) && ! $this->get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					return true;
				}
				break;
		}
		return false;
	}

	/**
	 * Generates the source and destination paths for the executables that we bundle with the plugin.
	 *
	 * Paths are determined based on the operating system and architecture.
	 */
	public function install_paths() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$src_folder = \trailingslashit( EWWW_IMAGE_OPTIMIZER_BINARY_PATH );
		$dst_folder = \trailingslashit( $this->content_dir );
		if ( PHP_OS === 'WINNT' ) {
			$gifsicle_src = $src_folder . 'gifsicle.exe';
			$optipng_src  = $src_folder . 'optipng.exe';
			$jpegtran_src = $src_folder . 'jpegtran.exe';
			$pngquant_src = $src_folder . 'pngquant.exe';
			$webp_src     = $src_folder . 'cwebp.exe';
			$gifsicle_dst = $dst_folder . 'gifsicle.exe';
			$optipng_dst  = $dst_folder . 'optipng.exe';
			$jpegtran_dst = $dst_folder . 'jpegtran.exe';
			$pngquant_dst = $dst_folder . 'pngquant.exe';
			$webp_dst     = $dst_folder . 'cwebp.exe';
		}
		if ( PHP_OS === 'Darwin' ) {
			$gifsicle_src = $src_folder . 'gifsicle-mac';
			$optipng_src  = $src_folder . 'optipng-mac';
			$jpegtran_src = $src_folder . 'jpegtran-mac';
			$pngquant_src = $src_folder . 'pngquant-mac';
			$webp_src     = $src_folder . 'cwebp-mac15';
			$gifsicle_dst = $dst_folder . 'gifsicle';
			$optipng_dst  = $dst_folder . 'optipng';
			$jpegtran_dst = $dst_folder . 'jpegtran';
			$pngquant_dst = $dst_folder . 'pngquant';
			$webp_dst     = $dst_folder . 'cwebp';
		}
		if ( PHP_OS === 'SunOS' ) {
			$gifsicle_src = $src_folder . 'gifsicle-sol';
			$optipng_src  = $src_folder . 'optipng-sol';
			$jpegtran_src = $src_folder . 'jpegtran-sol';
			$pngquant_src = $src_folder . 'pngquant-sol';
			$webp_src     = $src_folder . 'cwebp-sol';
			$gifsicle_dst = $dst_folder . 'gifsicle';
			$optipng_dst  = $dst_folder . 'optipng';
			$jpegtran_dst = $dst_folder . 'jpegtran';
			$pngquant_dst = $dst_folder . 'pngquant';
			$webp_dst     = $dst_folder . 'cwebp';
		}
		if ( PHP_OS === 'FreeBSD' ) {
			if ( $this->function_exists( '\php_uname' ) ) {
				$arch_type = \php_uname( 'm' );
				$this->debug_message( "CPU architecture: $arch_type" );
			} else {
				$this->debug_message( 'CPU architecture unknown, php_uname disabled' );
			}
			$gifsicle_src = $src_folder . 'gifsicle-fbsd';
			$optipng_src  = $src_folder . 'optipng-fbsd';
			$jpegtran_src = $src_folder . 'jpegtran-fbsd';
			$pngquant_src = $src_folder . 'pngquant-fbsd';
			$webp_src     = $src_folder . 'cwebp-fbsd';
			$gifsicle_dst = $dst_folder . 'gifsicle';
			$optipng_dst  = $dst_folder . 'optipng';
			$jpegtran_dst = $dst_folder . 'jpegtran';
			$pngquant_dst = $dst_folder . 'pngquant';
			$webp_dst     = $dst_folder . 'cwebp';
		}
		if ( PHP_OS === 'Linux' ) {
			if ( $this->function_exists( '\php_uname' ) ) {
				$arch_type = \php_uname( 'm' );
				$this->debug_message( "CPU architecture: $arch_type" );
			} else {
				$this->debug_message( 'CPU architecture unknown, php_uname disabled' );
			}
			$gifsicle_src = $src_folder . 'gifsicle-linux';
			$optipng_src  = $src_folder . 'optipng-linux';
			$jpegtran_src = $src_folder . 'jpegtran-linux';
			$pngquant_src = $src_folder . 'pngquant-linux';
			$webp_src     = $src_folder . 'cwebp-linux';
			$gifsicle_dst = $dst_folder . 'gifsicle';
			$optipng_dst  = $dst_folder . 'optipng';
			$jpegtran_dst = $dst_folder . 'jpegtran';
			$pngquant_dst = $dst_folder . 'pngquant';
			$webp_dst     = $dst_folder . 'cwebp';
		}
		$this->debug_message( "generated paths:<br>$jpegtran_src<br>$optipng_src<br>$gifsicle_src<br>$pngquant_src<br>$webp_src<br>$jpegtran_dst<br>$optipng_dst<br>$gifsicle_dst<br>$pngquant_dst<br>$webp_dst" );
		return array( $jpegtran_src, $optipng_src, $gifsicle_src, $pngquant_src, $webp_src, $jpegtran_dst, $optipng_dst, $gifsicle_dst, $pngquant_dst, $webp_dst );
	}

	/**
	 * Makes sure permissions on a file/folder are adequate.
	 *
	 * @param string $file The file or folder to test.
	 * @param string $minimum The minimum file permissions needed.
	 * @return bool True if permissions are equal to or better than what is required.
	 */
	public function check_permissions( $file, $minimum ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$perms = \fileperms( $file );
		$this->debug_message( "permissions for $file: " . \substr( \sprintf( '%o', $perms ), -4 ) );
		if ( ! $this->is_file( $file ) ) {
			$this->debug_message( 'file not found' );
			return false;
		}
		if ( \is_readable( $file ) && \is_executable( $file ) ) {
			$this->debug_message( 'permissions ok' );
			return true;
		}
		$this->debug_message( 'permissions insufficient' );
		return false;
	}

	/**
	 * Installs the executables that are bundled with the plugin.
	 */
	public function install_tools() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( 'Checking/Installing tools in ' . $this->content_dir );
		$this->skip_tools();
		$toolfail = false;
		if ( $this->function_exists( '\php_uname' ) ) {
			$arch_type = \php_uname( 'm' );
			$this->debug_message( "CPU architecture: $arch_type" );
			if ( 'aarch64' === $arch_type && PHP_OS === 'Linux' ) {
				return;
			}
		}
		if ( ! \is_dir( $this->content_dir ) && \is_writable( \dirname( $this->content_dir ) ) ) {
			$this->debug_message( 'folder does not exist, creating...' );
			if ( ! \wp_mkdir_p( $this->content_dir ) ) {
				$this->debug_message( 'could not create folder' );
				return;
			}
		} elseif ( \is_dir( $this->content_dir ) ) {
			if ( ! \is_writable( $this->content_dir ) ) {
				$this->debug_message( 'wp-content/ewww is not writable, not installing anything' );
				return;
			} elseif ( ! \is_executable( $this->content_dir ) && PHP_OS !== 'WINNT' ) {
				$this->debug_message( 'wp-content/ewww is not executable (non-Windows), not installing anything' );
				return;
			} elseif ( ! \is_readable( $this->content_dir ) ) {
				$this->debug_message( 'wp-content/ewww is not readable, not installing anything' );
				return;
			}
			$ewww_perms = \substr( \sprintf( '%o', \fileperms( $this->content_dir ) ), -4 );
			$this->debug_message( "wp-content/ewww permissions: $ewww_perms" );
		} else {
			$this->debug_message( \dirname( $this->content_dir ) . ' is not writable, and the ewww/ folder does not exist' );
			return;
		}
		list(
			$jpegtran_src,
			$optipng_src,
			$gifsicle_src,
			$pngquant_src,
			$cwebp_src,
			$jpegtran_dst,
			$optipng_dst,
			$gifsicle_dst,
			$pngquant_dst,
			$cwebp_dst
		) = $this->install_paths();

		if ( $this->tools['jpegtran']['enabled'] && ( ! $this->is_file( $jpegtran_dst ) || \filesize( $jpegtran_dst ) !== \filesize( $jpegtran_src ) ) ) {
			$this->debug_message( 'jpegtran not found or different size, installing' );
			if ( ! \copy( $jpegtran_src, $jpegtran_dst ) ) {
				$toolfail = true;
				$this->debug_message( 'could not copy jpegtran' );
			}
		}
		if ( $this->tools['gifsicle']['enabled'] && ( ! $this->is_file( $gifsicle_dst ) || \filesize( $gifsicle_dst ) !== \filesize( $gifsicle_src ) ) ) {
			$this->debug_message( 'gifsicle not found or different size, installing' );
			if ( ! \copy( $gifsicle_src, $gifsicle_dst ) ) {
				$toolfail = true;
				$this->debug_message( 'could not copy gifsicle' );
			}
		}
		if ( $this->tools['optipng']['enabled'] && ( ! $this->is_file( $optipng_dst ) || \filesize( $optipng_dst ) !== \filesize( $optipng_src ) ) ) {
			$this->debug_message( 'optipng not found or different size, installing' );
			if ( ! \copy( $optipng_src, $optipng_dst ) ) {
				$toolfail = true;
				$this->debug_message( 'could not copy optipng' );
			}
		}
		if ( ! $this->is_file( $pngquant_dst ) || \filesize( $pngquant_dst ) !== \filesize( $pngquant_src ) ) {
			$this->debug_message( 'pngquant not found or different size, installing' );
			if ( ! \copy( $pngquant_src, $pngquant_dst ) ) {
				$toolfail = true;
				$this->debug_message( 'could not copy pngquant' );
			}
		}
		if ( $this->tools['cwebp']['enabled'] && ( ! $this->is_file( $cwebp_dst ) || \filesize( $cwebp_dst ) !== \filesize( $cwebp_src ) ) ) {
			$this->debug_message( 'webp not found or different size, installing' );
			if ( ! \copy( $cwebp_src, $cwebp_dst ) ) {
				$toolfail = true;
				$this->debug_message( 'could not copy webp' );
			}
		}

		if ( PHP_OS !== 'WINNT' && ! $toolfail ) {
			$this->debug_message( 'Linux/UNIX style OS, checking permissions' );
			// NOTE: check_permissions() looks to make sure it is executable. If the tool is not executable,
			// then we error if we can't write to the file to make it so, or if we are unable to run chmod().
			if ( $this->tools['jpegtran']['enabled'] && ! $this->check_permissions( $jpegtran_dst, 'rwxr-xr-x' ) ) {
				if ( ! \is_writable( $jpegtran_dst ) || ! \chmod( $jpegtran_dst, 0755 ) ) {
					$toolfail = true;
					$this->debug_message( 'could not set jpegtran permissions' );
				}
			}
			if ( $this->tools['gifsicle']['enabled'] && ! $this->check_permissions( $gifsicle_dst, 'rwxr-xr-x' ) ) {
				if ( ! \is_writable( $gifsicle_dst ) || ! \chmod( $gifsicle_dst, 0755 ) ) {
					$toolfail = true;
					$this->debug_message( 'could not set gifsicle permissions' );
				}
			}
			if ( $this->tools['optipng']['enabled'] && ! $this->check_permissions( $optipng_dst, 'rwxr-xr-x' ) ) {
				if ( ! \is_writable( $optipng_dst ) || ! \chmod( $optipng_dst, 0755 ) ) {
					$toolfail = true;
					$this->debug_message( 'could not set optipng permissions' );
				}
			}
			if ( ! $this->check_permissions( $pngquant_dst, 'rwxr-xr-x' ) ) {
				if ( ! \is_writable( $pngquant_dst ) || ! \chmod( $pngquant_dst, 0755 ) ) {
					$toolfail = true;
					$this->debug_message( 'could not set pngquant permissions' );
				}
			}
			if ( $this->tools['cwebp']['enabled'] && ! $this->check_permissions( $cwebp_dst, 'rwxr-xr-x' ) ) {
				if ( ! \is_writable( $cwebp_dst ) || ! \chmod( $cwebp_dst, 0755 ) ) {
					$toolfail = true;
					$this->debug_message( 'could not set cwebp permissions' );
				}
			}
		}
		if ( $toolfail ) {
			\add_action( 'network_admin_notices', array( $this, 'tool_installation_failed_notice' ) );
			\add_action( 'admin_notices', array( $this, 'tool_installation_failed_notice' ) );
		}
	}

	/**
	 * Alert the user when tool installation fails.
	 */
	public function tool_installation_failed_notice() {
		echo "<div id='ewww-image-optimizer-warning-tool-install' class='notice notice-error'><p><strong>" .
			/* translators: %s: Folder location where executables should be installed */
			\sprintf( \esc_html__( 'EWWW Image Optimizer could not install tools in %s', 'ewww-image-optimizer' ), \esc_html( $this->content_dir ) ) . '.</strong> ' .
			/* translators: %s: Installation Instructions */
			\sprintf( \esc_html__( 'For more details, see the %s.', 'ewww-image-optimizer' ), "<a href='https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something'>" . \esc_html__( 'Installation Instructions', 'ewww-image-optimizer' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Checks the binary against a list of valid sha256 checksums.
	 *
	 * @param string $path The filename of a binary to check for a match.
	 * @return bool True if the sha256sum is validated.
	 */
	public function check_integrity( $path ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$binary_sum = \hash_file( 'sha256', $path );
		$this->debug_message( "$path: $binary_sum" );
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
			'd2f3f9c1fb90a56a4afad411e70f5bb596c0a373e7799104f5c3ff79bf617697', // jpegtran-fbsd  9d, EWWW 6.1.0.
			'c0a44f6f16ddc78d8d027ffd3e09c512d637876833c5ffaaf5b1e7acd5ce3cda', // jpegtran-linux 9d, EWWW 6.1.0.
			'68bbeddef97f9aca5f76fcf71f3394dae98559c4107c3497f4f097196f09ff89', // jpegtran-mac   9d, EWWW 6.1.0.
			'9f728b5fd533a73d46138c14514ba5424ef153386d80f75669ab9b64df02aef6', // jpegtran-sol   9d, EWWW 6.1.0.
			'39096e3dd3d9c375de23e5d6ac2c4e0000c994d768d9de5c60680a1d5b99cb7b', // jpegtran.exe   9d, EWWW 6.1.0.
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
			'4c6efd6ef91f277f6887ba38d025d3060f4e6539838fb359e3057960c84e3cda', // optipng-fbsd  0.7.7 stripped EWWW 6.7.0.
			'4404076a4f9119d4dfbb7acb00eb65345e804186a019c7136d8f8e87fb0cb997', // optipng-linux 0.7.7 EWWW 4.1.0.
			'5a14f6e8fda7a8c5e571525b279a5cf264116a62afd3cc2885e4c9ea8ef0f24b', // optipng-linux 0.7.7 stripped EWWW 6.7.0.
			'36535c1b262e0c457bbb0ed2bc71e812a49e26a6cada63b6acbd8d809c68a5a1', // optipng-mac   0.7.7 EWWW 4.1.0.
			'41a4c78e6c97ea26836f4b021157b34f1812a9e5c2341502aad8cde942b18576', // optipng-sol   0.7.7 EWWW 4.1.0.
			'6a321e07eca8e28fa8a969b5db3c1d3cc008a2064d636cf74762bbe4364b7b14', // optipng.exe   0.7.7 EWWW 4.1.0.
			'7f51fe39778d1a0da733efa7e518bb0612acfceaee2409945abe89ec1c91682f', // optipng-fbsd  0.7.8 EWWW 7.5.0.
			'c81b54f299a1284c6312448abf9fb79351d424f64709081dfb5e9afa3b7ad9c8', // optipng-linux 0.7.8 EWWW 7.5.0.
			'd38e6c9162ae5dc7c801b56de92dfa144d1dee7171f011233ce0acf24718b29b', // optipng-mac   0.7.8 EWWW 7.5.0.
			'3464cd6c7fc9cb893f368111eb19b634b2acf14dcc94958d0758251071cfcbd9', // optipng.exe   0.7.8 EWWW 7.5.0.
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
			'3f59274d214a9c4f7a3bf68755ff75b6801c94f3e9e73b5a95767e2d7ec0fc42', // gifsicle.exe   1.92, EWWW 6.1.0.
			'3b745d61a6be2b546424523848f699db5c60765a69659c328621daf39be199a1', // gifsicle-fbsd  1.93, EWWW 6.7.0.
			'205abe804d1060375f713d990c45b0285cbc4b56226da1612e9f1d2d2e2c5369', // gifsicle-linux 1.93, EWWW 6.7.0.
			'fbd269135c779acf8f96e38116cea3e2f429fb4fada3f876f2cedea8511830ba', // gifsicle-mac   1.93, EWWW 6.7.0.
			'6f60cc7f696ab4b861bf9e6fb5b4fd940b3cb6b9731e2ef04708334af95a7de4', // gifsicle.exe   1.95, EWWW 7.5.0.
			'8e69f9ff4807c14613986348d7a06e99eabff2a30c9f1efd32363ab8e5a23c07', // gifsicle-fbsd  1.95, EWWW 7.5.0.
			'7fc9de52fff727604655a349b579918ba34b76ea371de3460d6272774bd896c6', // gifsicle-linux 1.95, EWWW 7.5.0.
			'a4ae039ce0d4fb788c97a5b130c1865091fcd12f98345cb8aa4bc1a8e098326e', // gifsicle-mac   1.95, EWWW 7.5.0.
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
			'0b1483c00f495d6341bb3d5941d14184c8c3be68d140470828b6bc1183d815a6', // 20200115-bsd/i686/pngout
			'42af74a2a2ea71234d9098d1e405ed7b0e402e6b3334c86bb2d25c733143e53b', // 20200115-bsd/amd64/pngout
			'6d6c3b9d821e5562e68511e8daeaf7a239afdfb2587e520df47f5dfa673a8008', // 20200115-bsd-static/i686/pngout-static
			'30c8043dbcff879a060c463d7ea1aa253344eedaafbf62687956f589f94bdcb0', // 20200115-bsd-static/amd64/pngout-static
			'8b9eb97b000592844725def8ede4e45c15cad83c5accd672dad76cf9c47e52cd', // 20200115-linux/i686/pngout
			'c509286fccedd7529b32dfdee2b39906f06d35350034df6dfbf75a4c7dc9a0b5', // 20200115-linux/amd64/pngout
			'fcac0af92eca59a87ed8d446ab707cdf39d8c7961e0feab27b5bec862d1b11d5', // 20200115-linux-static/i686/pngout-static
			'9339c71b57dc71cf4d7c1d027383b76c1f426305ae8b7557d0d68f1ca396a06c', // 20200115-linux-static/amd64/pngout-static
			'020c15f908f26aac59988eff77296e57b546cc0e784746efb9ec84e4316edca1', // 20200115-macos/pngout
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
			'1ab09e21dd0c8aafe482227c2b53f13faf00fa9ba2b9046c1e9f8c4d4d851b9d', // pngquant-fbsd  2.12.5 EWWW 5.1.0.
			'b580c7d68c3ec7cd7685fb388cdbb2635aae92c7d520e54e8f67c57fc6215db0', // pngquant-linux 2.12.5 EWWW 5.1.0.
			'ddec62d4074d54d76dde9313302b6a95025286ad82006a0f83eb0452cc86da6c', // pngquant-mac   2.12.5 EWWW 5.1.0.
			'7c10d643f936114aaa307c5fa2024c5bd5f9a25fa90a07e7f2b100c161f15898', // pngquant-sol   2.12.5 EWWW 5.1.0.
			'6b1d4e685a4f5b3cbed9b9c7b71c7f75ae860684783e4a8274cdc66247d11fae', // pngquant.exe   2.12.5 EWWW 5.1.0.
			'f5a4258d284542c44fe2847fc1a0058bb819418160069ee6010071ab1aefd7f9', // pngquant-fbsd  2.13.1 EWWW 6.1.0.
			'b52b6b90385f1eed71d265fd181c15b515a10b64c959e974f7d6301695a689cf', // pngquant-linux 2.13.1 EWWW 6.1.0.
			'd8f5cfeb240ade34cf2f4b06dd66d29a28b8fd38e275d4caa9278bc83a39571f', // pngquant-mac   2.13.1 EWWW 6.1.0.
			'199365d719c045a291596fc47cddc0111125cc3ff9d55235cabffdf476db4ca4', // pngquant-sol   2.13.1 EWWW 6.1.0.
			'1e93bc6991d7e77ad7a1f48560d62a1b80faa99df38ddf56030e23d48476769e', // pngquant.exe   2.13.1 EWWW 6.1.0.
			'c2918bb09fcf1a07ad6982c3d7c9d93a50c9f201d9277e26778b2ede2f950423', // pngquant-fbsd  2.17.0 EWWW 6.7.0.
			'd4521b01d134351d9d398ab1086406cfc8f706fc300e88d1a4b9b914a33d9229', // pngquant-linux 2.17.0 EWWW 6.7.0.
			'0ac4981983faa3a2334c0ae7abf9a26480eddc77ed2c581a11dada0eee5a5e2d', // pngquant-mac   2.17.0 EWWW 6.7.0.
			'7aadad6a50aa5c40cfcb20f9478c92f6030360a3424bc262ce383dc1e97fb86a', // pngquant.exe   2.17.0 EWWW 6.7.0.
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
			'709804fe0c89ce7b3a23df11a503c663c6476cbb02f2ed0135245af9e81ac24b', // cwebp-fbsd  1.2.0, EWWW 6.1.0.
			'5fec3397c56b74b8a8ac8c9bac99dc11d40f9528a6c05e4108f1cd65d5a0a4fc', // cwebp-linux 1.2.0, EWWW 6.1.0.
			'fc25866344efb604b3e70dc3e5519199605da13b550ccee4b7bbdcdeb0b5e6be', // cwebp-mac15 1.2.0, EWWW 6.1.0.
			'488410937dbbc4ec55fddfc0fa6835b862f7024680744a5e5ac8b88be9270fcc', // cwebp-sol   1.2.0, EWWW 6.1.0.
			'2849fd06012a9eb311b02a4f8918ae4b16775693bc21e95f4cc6a382eac299f9', // cwebp.exe   1.2.0, EWWW 6.1.0.
			'b8094b40d73e5eb51fa9f68cc9d5c5a1bf610b0589f1b65698729d27fe2c327f', // cwebp-fbsd  1.3.2, EWWW 7.5.0.
			'52dde413dc4547abf607d8f1e5426ab8110ae9f02e685c1d7c49537ea75be9ca', // cwebp-linux 1.3.2, EWWW 7.5.0.
			'6eda6785dac4c23fc363e5db2dc45cfaab71225435a8bad95f3b56c1b7ee026d', // cwebp-mac15 1.3.2, EWWW 7.5.0.
			'f317c8bc61624db206f5aa254f3bbc46d5cafdcb91862378ce7a0371dbf61b03', // cwebp.exe   1.3.2, EWWW 7.5.0.
			// end cwebp.
			'15d8b7d54b73059a9a63ab3d5ca8201cd30c2f6fc59fc068f7bd6c85e6a22420', // svgcleaner-linux 0.9.5.
			'c88c1961374b3edc93a29376ccbd447a514c1cda335fe6a868c0dac6d77c79fa', // svgcleaner-mac 0.9.5.
			'5f0b5d64e7975275cd8649f4b29bd0526ba06961aef92aa9812e26443e454fe0', // svgcleaner.exe 0.9.5.
			// end svgcleaner.
		);
		if ( \in_array( $binary_sum, $valid_sums, true ) ) {
				$this->debug_message( 'checksum verified, binary is intact' );
				return true;
		}
		$this->debug_message( 'invalid checksum' );
		return false;
	}

	/**
	 * Check if open_basedir restriction is in effect, and that the path is allowed and exists.
	 *
	 * Note that when the EWWWIO_OPEN_BASEDIR constant is defined, is_file() will be skipped.
	 *
	 * @param string $file The path of the file to check.
	 * @return bool False if open_basedir setting cannot be retrieved, or the file is "out of bounds", true if the file exists.
	 */
	protected function system_binary_exists( $file ) {
		if ( ! $this->function_exists( '\ini_get' ) && ! \defined( 'EWWWIO_OPEN_BASEDIR' ) ) {
			return false;
		}
		if ( \defined( 'EWWWIO_OPEN_BASEDIR' ) ) {
			$basedirs = EWWWIO_OPEN_BASEDIR;
		} else {
			$basedirs = \ini_get( 'open_basedir' );
		}
		if ( empty( $basedirs ) ) {
			return \defined( 'EWWWIO_OPEN_BASEDIR' ) ? true : \is_file( $file );
		}
		$basedirs = \explode( PATH_SEPARATOR, $basedirs );
		foreach ( $basedirs as $basedir ) {
			$basedir = \trim( $basedir );
			if ( 0 === \strpos( $file, $basedir ) ) {
				return \defined( 'EWWWIO_OPEN_BASEDIR' ) ? true : \is_file( $file );
			}
		}
		return false;
	}

	/**
	 * Checks all tools to see if any are missing.
	 *
	 * Normally, we only check the tools we need. On certain admin pages, we check all the tools so we can alert the user if necessary.
	 *
	 * @return array The list of tools with enabled and path indices.
	 */
	public function check_all_tools() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		foreach ( $this->tools as $tool => $status ) {
			if ( $status['enabled'] && ! isset( $status['path'] ) ) {
				$this->check_tool( $tool );
			}
		}
		return $this->tools;
	}

	/**
	 * Get the filesystem path for a given tool, if enabled.
	 *
	 * @param string $tool The optimization tool to retrieve.
	 * @param bool   $override True to bypass tool_enabled() check.
	 * @return string The path to the requested tool.
	 */
	public function get_path( $tool, $override = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( $this->exec_enabled && ( $override || $this->tool_enabled( $tool ) ) ) {
			if ( isset( $this->tools[ $tool ]['path'] ) ) {
				return $this->tools[ $tool ]['path'];
			}
			$this->check_tool( $tool, $override );
			return $this->tools[ $tool ]['path'];
		} elseif ( ! $this->tool_enabled( $tool ) ) {
			$this->debug_message( "$tool disabled" );
		}
		return '';
	}

	/**
	 * Sends each tool to the binary checker appropriate for the operating system.
	 *
	 * @param string $tool The name of the tool to check/test.
	 * @param bool   $override True to bypass tool_enabled() check.
	 */
	public function check_tool( $tool, $override = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( isset( $this->tools[ $tool ]['path'] ) ) {
			return;
		}
		if ( ! $this->tool_enabled( $tool ) && ! $override ) {
			$this->tools[ $tool ]['path'] = '';
			return;
		}
		if ( \defined( \strtoupper( $this->prefix . $tool ) ) ) {
			$defined_path = \constant( \strtoupper( $this->prefix . $tool ) );
			if ( 'WINNT' === PHP_OS ) {
				$this->tools[ $tool ]['path'] = '"' . $defined_path . '"';
			} else {
				$this->tools[ $tool ]['path'] = $this->escapeshellcmd( $defined_path );
			}
		} elseif ( 'WINNT' === PHP_OS ) {
			$this->tools[ $tool ]['path'] = $this->find_win_binary( $tool );
		} else {
			$this->tools[ $tool ]['path'] = $this->find_nix_binary( $tool );
			if ( empty( $this->tools[ $tool ]['path'] ) ) {
				$blind                        = true;
				$this->tools[ $tool ]['path'] = $this->find_nix_binary( $tool, $blind );
			}
		} // End if().
		if ( $this->tools[ $tool ]['path'] ) {
			$this->debug_message( 'using: ' . $this->tools[ $tool ]['path'] );
		}
	}

	/**
	 * Searches for the given $binary on a Windows system.
	 *
	 * Checks the bundled tool, as well as -custom and -alt suffixes, and looks for a system-installed
	 * executable if all else fails.
	 *
	 * @param string $binary Path to the executable.
	 * @return string A validated executable path.
	 */
	public function find_win_binary( $binary ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $binary ) ) {
			return '';
		}
		$tool_path = \trailingslashit( $this->content_dir );
		if ( ! $this->get_option( 'ewww_image_optimizer_skip_bundle' ) ) {
			if ( $this->is_file( $tool_path . $binary . '.exe' ) ) {
				$binary_path = $tool_path . $binary . '.exe';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->check_integrity( $binary_path ) && $this->test_binary( '"' . $binary_path . '"', $binary ) ) {
					return '"' . $binary_path . '"';
				}
			}
			if ( $this->is_file( $tool_path . $binary . '-custom.exe' ) ) {
				$binary_path = $tool_path . $binary . '-custom.exe';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->test_binary( '"' . $binary_path . '"', $binary ) ) {
					return '"' . $binary_path . '"';
				}
			}
			if ( $this->is_file( $tool_path . $binary . '-alt.exe' ) ) {
				$binary_path = $tool_path . $binary . '-alt.exe';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->test_binary( '"' . $binary_path . '"', $binary ) ) {
					return '"' . $binary_path . '"';
				}
			}
		}
		if ( ! \defined( 'EWWWIO_SKIP_SYSTEM_BINARIES' ) || ! EWWWIO_SKIP_SYSTEM_BINARIES ) {
			// If we still haven't found a usable binary, try a system-installed version.
			if ( $this->test_binary( $binary . '.exe', $binary ) ) {
				return $binary . '.exe';
			}
		}
		return '';
	}

	/**
	 * Searches for the given $binary on a *nix system.
	 *
	 * Checks the bundled tool, as well as -custom and -alt suffixes, searches several system folders,
	 * and looks for a system-installed binary if all else fails.
	 *
	 * @param string $binary Path to the binary.
	 * @param string $blind Process a sample image and do a filesize check instead of looking for the version string.
	 * @return string A validated binary path.
	 */
	public function find_nix_binary( $binary, $blind = false ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $binary ) ) {
			return '';
		}
		$blind_suffix = '';
		if ( $blind ) {
			$blind_suffix = '-blind';
		}
		$tool_path = \trailingslashit( $this->content_dir );
		// First check for the binary in the ewww tool folder.
		if ( ! $this->get_option( 'ewww_image_optimizer_skip_bundle' ) ) {
			$this->debug_message( 'checking for bundled tool' );
			if ( 'pngout' === $binary && $this->is_file( $tool_path . $binary . '-static' ) ) {
				$binary_path = $tool_path . $binary . '-static';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->check_integrity( $binary_path ) && $this->mimetype( $binary_path, 'b' ) ) {
					$binary_path = $this->escapeshellcmd( $binary_path );
					if ( $this->test_binary( $binary_path, $binary . $blind_suffix ) ) {
						return $binary_path;
					}
				}
			}
			if ( $this->is_file( $tool_path . $binary ) ) {
				$binary_path = $tool_path . $binary;
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->check_integrity( $binary_path ) && $this->mimetype( $binary_path, 'b' ) ) {
					$binary_path = $this->escapeshellcmd( $binary_path );
					if ( $this->test_binary( $binary_path, $binary . $blind_suffix ) ) {
						return $binary_path;
					}
				}
			}
			// If the standard binary didn't work, see if the user custom compiled one and check that.
			if ( $this->is_file( $tool_path . $binary . '-custom' ) ) {
				$binary_path = $tool_path . $binary . '-custom';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->filesize( $binary_path ) > 15000 && $this->mimetype( $binary_path, 'b' ) ) {
					$binary_path = $this->escapeshellcmd( $binary_path );
					if ( $this->test_binary( $binary_path, $binary . $blind_suffix ) ) {
						return $binary_path;
					}
				}
			}
			// See if the alternative binary works.
			if ( $this->is_file( $tool_path . $binary . '-alt' ) ) {
				$binary_path = $tool_path . $binary . '-alt';
				$this->debug_message( "found $binary_path, testing..." );
				if ( $this->filesize( $binary_path ) > 15000 && $this->mimetype( $binary_path, 'b' ) ) {
					$binary_path = $this->escapeshellcmd( $binary_path );
					if ( $this->test_binary( $binary_path, $binary . $blind_suffix ) ) {
						return $binary_path;
					}
				}
			}
		}
		if ( ! \defined( 'EWWWIO_SKIP_SYSTEM_BINARIES' ) || ! EWWWIO_SKIP_SYSTEM_BINARIES ) {
			// If we still haven't found a usable binary, try a system-installed version.
			if ( $this->system_binary_exists( '/usr/bin/' . $binary ) && $this->test_binary( '/usr/bin/' . $binary, $binary . $blind_suffix ) ) {
				return '/usr/bin/' . $binary;
			} elseif ( $this->system_binary_exists( '/usr/local/bin/' . $binary ) && $this->test_binary( '/usr/local/bin/' . $binary, $binary . $blind_suffix ) ) {
				return '/usr/local/bin/' . $binary;
			} elseif ( $this->system_binary_exists( '/usr/gnu/bin/' . $binary ) && $this->test_binary( '/usr/gnu/bin/' . $binary, $binary . $blind_suffix ) ) {
				return '/usr/gnu/bin/' . $binary;
			} elseif ( $this->system_binary_exists( '/usr/syno/bin/' . $binary ) && $this->test_binary( '/usr/syno/bin/' . $binary, $binary . $blind_suffix ) ) {
				// For synology diskstation OS.
				return '/usr/syno/bin/' . $binary;
			} elseif ( $this->test_binary( $binary, $binary . $blind_suffix ) ) {
				return $binary;
			}
		}
		return '';
	}

	/**
	 * Test the given binary to see if it returns a valid version string.
	 *
	 * @param string $path The absolute path to a binary file.
	 * @param string $tool The specific tool to test. Append '-blind' for blind to do a 'live' test rather than a version check.
	 * @return bool|string True (or truthy) if found.
	 */
	public function test_binary( $path, $tool ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_message( "testing case: $tool at $path" );

		// '*-blind' cases are 'blind' testing in case we can't get at the version string, but the binaries are actually working, we run a test compression, and compare the resulting filesize with what it should be.
		switch ( $tool ) {
			case 'jpegtran':
				// In case you forget, it is not any slower to run jpegtran this way (with a sample file to operate on) than the other tools.
				\exec( $path . ' -v ' . EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'sample.jpg 2>&1', $jpegtran_version );
				if ( $this->is_iterable( $jpegtran_version ) ) {
					$this->debug_message( "$path: {$jpegtran_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				foreach ( $jpegtran_version as $jout ) {
					if ( \preg_match( '/Independent JPEG Group/', $jout ) ) {
						$this->debug_message( 'optimizer found' );
						return $jout;
					}
				}
				break;
			case 'jpegtran-blind':
				$upload_dir = \wp_upload_dir();
				$testjpg    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.jpg';
				\exec( $path . ' -copy none -optimize -outfile ' . $this->escapeshellarg( $testjpg ) . ' ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.jpg' ) );
				$testjpgsize = $this->filesize( $testjpg );
				$this->debug_message( "blind testing jpegtran, is $testjpgsize smaller than 5700?" );
				if ( $testjpgsize ) {
					\unlink( $testjpg );
				}
				if ( 0 < $testjpgsize && $testjpgsize < 5700 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'optipng':
				\exec( $path . ' -v 2>&1', $optipng_version );
				if ( $this->is_iterable( $optipng_version ) ) {
					$this->debug_message( "$path: {$optipng_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $optipng_version ) && \strpos( $optipng_version[0], 'OptiPNG' ) === 0 ) {
					$this->debug_message( 'optimizer found' );
					return $optipng_version[0];
				}
				break;
			case 'optipng-blind':
				$upload_dir = \wp_upload_dir();
				$testpng    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
				\exec( $path . ' -out ' . $this->escapeshellarg( $testpng ) . ' -o1 -quiet -strip all ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) );
				$testpngsize = $this->filesize( $testpng );
				$this->debug_message( "blind testing optipng, is $testpngsize smaller than 110?" );
				if ( $testpngsize ) {
					\unlink( $testpng );
				}
				if ( 0 < $testpngsize && $testpngsize < 110 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'gifsicle':
				\exec( $path . ' --version 2>&1', $gifsicle_version );
				if ( $this->is_iterable( $gifsicle_version ) ) {
					$this->debug_message( "$path: {$gifsicle_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $gifsicle_version ) && \strpos( $gifsicle_version[0], 'LCDF Gifsicle' ) === 0 ) {
					$this->debug_message( 'optimizer found' );
					return $gifsicle_version[0];
				}
				break;
			case 'gifsicle-blind':
				$upload_dir = \wp_upload_dir();
				$testgif    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.gif';
				\exec( $path . ' -O3 -o ' . $this->escapeshellarg( $testgif ) . ' ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.gif' ) );
				$testgifsize = $this->filesize( $testgif );
				$this->debug_message( "blind testing gifsicle, is $testgifsize smaller than 12000?" );
				if ( $testgifsize ) {
					\unlink( $testgif );
				}
				if ( 0 < $testgifsize && $testgifsize < 12000 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'pngout':
				\exec( "$path 2>&1", $pngout_version );
				if ( $this->is_iterable( $pngout_version ) ) {
					$this->debug_message( "$path: {$pngout_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $pngout_version ) && \strpos( $pngout_version[0], 'PNGOUT' ) === 0 ) {
					$this->debug_message( 'optimizer found' );
					return $pngout_version[0];
				}
				break;
			case 'pngout-blind':
				$upload_dir = \wp_upload_dir();
				$testpng    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
				\exec( $path . ' -s3 -q ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) . ' ' . $this->escapeshellarg( $testpng ) );
				$testpngsize = $this->filesize( $testpng );
				$this->debug_message( "blind testing pngout, is $testpngsize smaller than 110?" );
				if ( $testpngsize ) {
					\unlink( $testpng );
				}
				if ( 0 < $testpngsize && $testpngsize < 110 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'pngquant': // pngquant.
				\exec( $path . ' -V 2>&1', $pngquant_version );
				if ( $this->is_iterable( $pngquant_version ) ) {
					$this->debug_message( "$path: {$pngquant_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $pngquant_version ) && \preg_match( '/^\d\.\d{1,2}\.\d{1,2}/', $pngquant_version[0] ) && \substr( $pngquant_version[0], 0, 3 ) >= 2.0 ) {
					$this->debug_message( 'optimizer found' );
					return $pngquant_version[0];
				}
				break;
			case 'pngquant-blind':
				$upload_dir = \wp_upload_dir();
				$testpng    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
				\exec( $path . ' -o ' . $this->escapeshellarg( $testpng ) . ' ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) );
				$testpngsize = $this->filesize( $testpng );
				$this->debug_message( "blind testing pngquant, is $testpngsize smaller than 114?" );
				if ( $testpngsize ) {
					\unlink( $testpng );
				}
				if ( 0 < $testpngsize && $testpngsize < 114 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'nice': // nice.
				if ( PHP_OS === 'WINNT' ) {
					return false;
				}
				\exec( "$path 2>&1", $nice_output );
				if ( $this->is_iterable( $nice_output ) && isset( $nice_output[0] ) ) {
					$this->debug_message( "$path: {$nice_output[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( \is_array( $nice_output ) && isset( $nice_output[0] ) && \preg_match( '/usage/', $nice_output[0] ) ) {
					$this->debug_message( 'nice found' );
					return true;
				} elseif ( \is_array( $nice_output ) && isset( $nice_output[0] ) && \preg_match( '/^\d+$/', $nice_output[0] ) ) {
					$this->debug_message( 'nice found' );
					return true;
				}
				break;
			case 'cwebp': // cwebp.
				\exec( "$path -version 2>&1", $webp_version );
				if ( $this->is_iterable( $webp_version ) ) {
					$this->debug_message( "$path: {$webp_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $webp_version ) && \preg_match( '/\d\.\d\.\d/', $webp_version[0] ) ) {
					$this->debug_message( 'optimizer found' );
					return $webp_version[0];
				}
				break;
			case 'cwebp-blind':
				$upload_dir = \wp_upload_dir();
				$testpng    = \trailingslashit( $upload_dir['basedir'] ) . 'testopti.png';
				\exec( $path . ' -lossless -quiet ' . $this->escapeshellarg( EWWW_IMAGE_OPTIMIZER_IMAGES_PATH . 'testorig.png' ) . ' -o ' . $this->escapeshellarg( $testpng ) );
				$testpngsize = $this->filesize( $testpng );
				$this->debug_message( "blind testing cwebp, is $testpngsize smaller than 114?" );
				if ( $testpngsize ) {
					\unlink( $testpng );
				}
				if ( 0 < $testpngsize && $testpngsize < 114 ) {
					$this->debug_message( 'optimizer found' );
					return \esc_html__( 'unknown', 'ewww-image-optimizer' );
				}
				break;
			case 'svgcleaner': // svgcleaner.
				\exec( "$path --version 2>&1", $svgcleaner_version );
				if ( $this->is_iterable( $svgcleaner_version ) ) {
					$this->debug_message( "$path: {$svgcleaner_version[0]}" );
				} else {
					$this->debug_message( "$path: invalid output" );
					break;
				}
				if ( ! empty( $svgcleaner_version ) && \strpos( $svgcleaner_version[0], 'svgcleaner' ) === 0 ) {
					$svgcleaner_out = \explode( ' ', $svgcleaner_version[0] );
					$this->debug_message( 'optimizer found' );
					return $svgcleaner_out[1];
				}
				break;
		} // End switch().
		$this->debug_message( 'tool not found' );
		return false;
	}
}
