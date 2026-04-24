<?php
/**
 * Image Detective helps users find improperly scaled images and displays LCP element information.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables plugin to filter the page content and replace img elements with Lazy Load markup.
 */
class Image_Detective extends Base {

	/**
	 * Request URI.
	 *
	 * @var string $request_uri
	 */
	public $request_uri = '';

	/**
	 * Register (once) actions and filters for Lazy Load.
	 */
	public function __construct() {
		parent::__construct();

		$this->request_uri = \add_query_arg( '', '' );

		if ( \is_admin() ) {
			return;
		}

		\add_action( 'wp_head', array( $this, 'load_scripts' ) );
	}

	/**
	 * Loads script to detect scaled images within the page, only enabled for admins.
	 */
	public function load_scripts() {
		if (
			! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ||
			( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === \wp_basename( \sanitize_text_field( \wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) )
		) {
			return;
		}
		if ( ! $this->is_frontend() ) {
			return;
		}
		if ( ! $this->get_option( 'ewww_image_optimizer_resize_detection' ) ) {
			return;
		}
		$plugin_dir = \plugin_dir_path( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE' ) );
		if (
			( \defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ||
			( \defined( \strtoupper( $this->prefix ) . 'SCRIPT_DEBUG' ) && \constant( \strtoupper( $this->prefix ) . 'SCRIPT_DEBUG' ) )
		) {
			$resize_detection_script = \file_get_contents( $plugin_dir . 'includes/resize-detection.js' );
		} else {
			$resize_detection_script = \file_get_contents( $plugin_dir . 'includes/resize-detection.min.js' );
		}
		?>
		<style>
			#wp-admin-bar-resize-detection div.ab-empty-item {
				cursor: pointer;
			}
			#wp-admin-bar-resize-detection {
				opacity: 1;
				-webkit-transition: opacity 0.3s ease-in-out;
				-moz-transition: opacity 0.3s ease-in-out;
				-ms-transition: opacity 0.3s ease-in-out;
				-o-transition: opacity 0.3s ease-in-out;
				transition: opacity 0.3 ease-in-out;
			}
			#wp-admin-bar-resize-detection.ewww-fade {
				opacity: 0;
			}
			#ewww-lcp-tag {
				background-color: #3eadc9;
				color: #fff;
				font-size: 12px;
				font-family: sans-serif;
				margin: 20px;
				padding: 3px 7px;
				position: absolute;
				z-index: 9999;
				top: -1000px;
				left: -1000px;
			}
			#ewww-scaling-error {
				background-color: #ccc;
				color: #000;
				font-size: 13px;
				font-family: sans-serif;
				margin: 20px;
				padding: 5px 10px;
				position: absolute;
				z-index: 10000;
				top: -1000px;
				left: -1000px;
				border: 1px solid #000;
				box-shadow: 0 0 3px 1px #777;
			}
			img.ewww-improperly-scaled {
				box-shadow: 0 0 3px 3px #f18f07;
			}
			.ewww-lcp-element {
				box-shadow: 0 0 3px 3px #3eadc9;
			}
			.ewww-lcp-element.ewww-improperly-scaled {
				box-shadow: 0 0 3px 3px #d10707;
			}
		</style>
		<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1">
			<?php echo $resize_detection_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</script>
		<?php
	}
}
