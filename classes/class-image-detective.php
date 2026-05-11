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

	use Page_Settings;

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

		\add_action( 'wp_ajax_ewww_add_lazy_exclusion', array( $this, 'ajax_add_lazy_exclusion' ) );

		if ( \is_admin() ) {
			return;
		}

		\add_action( 'wp_head', array( $this, 'load_scripts' ) );
	}

	/**
	 * AJAX handler for adding a lazy load exclusion.
	 */
	public function ajax_add_lazy_exclusion() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		\check_ajax_referer( 'ewww-image-detective', 'ewww_wpnonce' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission to perform this action.', 'ewww-image-optimizer' ) );
		}
		$this->ob_clean();
		if ( empty( $_REQUEST['exclusion'] ) ) {
			\wp_send_json_error( \esc_html__( 'Cannot save empty exclusion value.', 'ewww-image-optimizer' ) );
		}
		$global    = ! empty( $_REQUEST['global'] ) ? true : false;
		$exclusion = sanitize_text_field( wp_unslash( $_REQUEST['exclusion'] ) );
		if ( $global ) {
			$this->update_global_lazy_exclusions( $exclusion );
		} else {
			if ( empty( $_REQUEST['post_id'] ) && empty( $_REQUEST['request_uri'] ) ) {
				\wp_send_json_error( \esc_html__( 'Cannot save page exclusion without a valid post ID or request URI.', 'ewww-image-optimizer' ) );
			}
			if ( ! empty( $_REQUEST['post_id'] ) ) {
				$post_identifier = (int) $_REQUEST['post_id'];
			} else {
				$post_identifier = \sanitize_text_field( \wp_unslash( $_REQUEST['request_uri'] ) );
			}
			$this->debug_message( "adding exclusion $exclusion for post/page $post_identifier" );
			$this->update_page_lazy_exclusions( $post_identifier, $exclusion );
		}
		$this->ob_clean();
		\wp_send_json(
			array(
				'success' => true,
				'message' => \esc_html__( 'The lazy load exclusion has been added successfully. Refresh the page to confirm there are no scaling issues.', 'ewww-image-optimizer' ),
			)
		);
	}

	/**
	 * Update the global lazy load exclusions.
	 *
	 * @param string $exclusion The exclusion to add.
	 */
	private function update_global_lazy_exclusions( $exclusion ) {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! \is_string( $exclusion ) || empty( $exclusion ) || \strlen( $exclusion ) > 500 ) {
			$this->debug_message( 'invalid exclusion value:' );
			$this->debug_message( $exclusion );
			return;
		}
		$exclusions     = $this->get_option( $this->prefix . 'll_exclude' );
		$new_exclusions = $this->merge_exclusions( $exclusions, $exclusion );
		if ( is_array( $new_exclusions ) && ! empty( $new_exclusions ) && count( $new_exclusions ) > count( $exclusions ) ) {
			$this->debug_message( 'saving updated exclusions' );
			$this->set_option( $this->prefix . 'll_exclude', $new_exclusions );
		}
	}

	/**
	 * Update page-specific lazy load exclusions.
	 *
	 * @param int|string $post_identifier The post ID or request URI of the page for which to add the exclusion.
	 * @param string     $exclusion The exclusion to add.
	 */
	private function update_page_lazy_exclusions( $post_identifier, $exclusion ) {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ! \is_string( $exclusion ) || empty( $exclusion ) || \strlen( $exclusion ) > 500 ) {
			$this->debug_message( 'invalid exclusion value:' );
			$this->debug_message( $exclusion );
			return;
		}
		if ( empty( $post_identifier ) ) {
			$this->debug_message( 'invalid post identifier' );
			return;
		}
		if ( \is_int( $post_identifier ) ) {
			$eio_page_settings = \maybe_unserialize( \get_post_meta( $post_identifier, 'eio_page_settings', true ) );
			$exclusions        = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['ll_exclude'] ) ? $eio_page_settings['ll_exclude'] : array();
			$new_exclusions    = $this->merge_exclusions( $exclusions, $exclusion );
			if ( is_array( $new_exclusions ) && ! empty( $new_exclusions ) && count( $new_exclusions ) > count( $exclusions ) ) {
				$this->debug_message( 'saving updated exclusions in postmeta' );
				if ( ! is_array( $eio_page_settings ) ) {
					$eio_page_settings = array();
				}
				$eio_page_settings['ll_exclude'] = $new_exclusions;
				\update_post_meta( $post_identifier, 'eio_page_settings', \serialize( $eio_page_settings ) );
				// Set a flag to indicate that manual page rules exist, so that we don't waste time checking the db otherwise.
				if ( ! \get_option( $this->prefix . 'll_manual_page_settings' ) ) {
					\update_option( $this->prefix . 'll_manual_page_settings', true );
				}
			}
		} elseif ( \is_string( $post_identifier ) ) {
			$eio_page_record   = $this->get_page_settings( $post_identifier );
			$eio_page_settings = isset( $eio_page_record['data'] ) && is_array( $eio_page_record['data'] ) ? $eio_page_record['data'] : array();
			$exclusions        = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['ll_exclude'] ) ? $eio_page_settings['ll_exclude'] : array();
			$new_exclusions    = $this->merge_exclusions( $exclusions, $exclusion );
			if ( is_array( $new_exclusions ) && ! empty( $new_exclusions ) && count( $new_exclusions ) > count( $exclusions ) ) {
				$this->debug_message( 'saving updated exclusions in ewwwio_pages table' );
				if ( ! is_array( $eio_page_record ) || empty( $eio_page_record ) || ! isset( $eio_page_record['id'] ) ) {
					$eio_page_record = array(
						'page' => $post_identifier,
						'data' => array(),
					);
				}
				$eio_page_record['data']['ll_exclude'] = $new_exclusions;
				$this->update_page_settings( $eio_page_record );
				// Set a flag to indicate that manual page rules exist, so that we don't waste time checking the db otherwise.
				if ( ! \get_option( $this->prefix . 'll_manual_page_settings' ) ) {
					\update_option( $this->prefix . 'll_manual_page_settings', true );
				}
			}
		}
	}

	/**
	 * Merge a new exclusion into the existing exclusions.
	 *
	 * @param array  $exclusions The existing exclusions.
	 * @param string $new_exclusion The new exclusion to add.
	 * @return array The merged exclusions.
	 */
	private function merge_exclusions( $exclusions, $new_exclusion ) {
		if ( ! empty( $exclusions ) ) {
			if ( \is_string( $exclusions ) ) {
				$exclusions = array( $exclusions );
			}
			if ( ! \is_array( $exclusions ) ) {
				$this->debug_message( 'invalid exclusions value, not a string or array, check the db' );
				return $exclusions;
			}
			if ( ! \in_array( $new_exclusion, $exclusions, true ) ) {
				$exclusions[] = $new_exclusion;
			}
		} else {
			$exclusions = array( $new_exclusion );
		}
		return $exclusions;
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
		$post_id = 0;
		if ( \is_singular() ) {
			$post_id = \get_queried_object_id();
		}
		$request_uri = $this->parse_url( $this->request_uri, PHP_URL_PATH );
		if ( empty( $request_uri ) ) {
			$request_uri = '/';
		}
		?>
		<style id="ewww-image-detective-inline-styles">
			#wp-admin-bar-image-detective div.ab-empty-item {
				cursor: pointer;
			}
			#wp-admin-bar-image-detective-check, #wp-admin-bar-image-detective-clear {
				opacity: 1;
				-webkit-transition: opacity 0.3s ease-in-out;
				-moz-transition: opacity 0.3s ease-in-out;
				-ms-transition: opacity 0.3s ease-in-out;
				-o-transition: opacity 0.3s ease-in-out;
				transition: opacity 0.3 ease-in-out;
			}
			#wp-admin-bar-image-detective-check.ewww-fade, #wp-admin-bar-image-detective-clear.ewww-fade {
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
			#ewww-lazy-exclude-menu {
				margin: 60px 20px 0;
				position: absolute;
				display: flex;
				flex-direction: column;
				z-index: 9999;
				top: -1000px;
				left: -1000px;
			}
			#ewww-lazy-exclude-menu.ewww-error {
				background-color: #fff;
				color: #d10707;
				padding: 6px 10px;
				border: #d10707 solid 1px;
			}
			#ewww-lazy-exclude-menu.ewww-success {
				background-color: #fff;
				color: #272727;
				padding: 6px 10px;
				border: #3eadc9 solid 1px;
			}
			#ewww-lazy-exclude-menu-label {
				background-color: #272727;
				color: #fff;
				font-size: 13px;
				font-family: sans-serif;
				padding: 6px 10px;
				cursor: pointer;
				margin: 0;
				position: relative;
				width: fit-content;
				border: #272727 solid 1px;
			}
			#ewww-lazy-exclude-menu ul {
				background-color: #fff;
				color: #272727;
				padding: 0;
				margin: 0;
				text-indent: 0;
				border: #272727 solid 1px;
				display: none;
			}
			#ewww-lazy-exclude-menu li {
				position: relative;
				list-style: none;
				font-size: 13px;
				font-family: sans-serif;
				padding: 6px 10px;
				cursor: pointer;
				margin: 0;
				text-indent: 0;
			}
			#ewww-lazy-exclude-menu-label:hover, #ewww-lazy-exclude-menu li:hover {
				color: #3eadc9;
				background-color: #fff;
			}
			#ewww-scaling-error {
				background-color: #ccc;
				color: #272727;
				font-size: 13px;
				font-family: sans-serif;
				margin: 20px;
				padding: 5px 10px;
				position: absolute;
				z-index: 10000;
				top: -1000px;
				left: -1000px;
				border: 1px solid #272727;
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

			var imageDetectiveVars = {
				ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ewww-image-detective' ) ); ?>',
				excludeMenuText: '<?php echo esc_js( __( 'Add Lazy Load Exclusion', 'ewww-image-optimizer' ) ); ?>',
				invalid_response: '<?php echo esc_js( __( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ) ); ?>',
				<?php /* translators: %s is a placeholder for the image name or class. */ ?>
				menuItemGlobalText: '<?php printf( esc_js( __( 'Exclude %s', 'ewww-image-optimizer' ) ), '<span class="ewww-lazy-exclude-item-name"></span>' ); ?>',
				<?php /* translators: %s is a placeholder for the image name. */ ?>
				menuItemPageText: '<?php printf( esc_js( __( 'Exclude %s on this page only', 'ewww-image-optimizer' ) ), '<span class="ewww-lazy-exclude-item-name"></span>' ); ?>',
				postId: <?php echo (int) $post_id; ?>,
				requestUri: '<?php echo esc_js( $request_uri ); ?>',
			};
		</script>
		<?php
	}
}
