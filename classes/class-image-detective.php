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
		if ( ! $this->get_option( $this->prefix . 'image_detective' ) ) {
			return;
		}

		$this->request_uri = \add_query_arg( '', '' );

		// NOTE: Be careful that anything for admin users does not accidentally run for guests.
		\add_action( 'wp_ajax_ewww_add_lazy_exclusion', array( $this, 'ajax_add_lazy_exclusion' ) );
		\add_action( 'wp_ajax_ewww_add_ignore_scaling_rule', array( $this, 'ajax_add_ignore_scaling_rule' ) );

		if ( \is_admin() ) {
			return;
		}

		\add_action( 'init', array( $this, 'handle_clear_settings_request' ) );

		\add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 99 );

		\add_action( 'wp_head', array( $this, 'load_scripts' ) );
	}

	/**
	 * AJAX handler for adding a lazy load exclusion.
	 */
	public function ajax_add_lazy_exclusion() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \is_string( $exclusion ) || empty( $exclusion ) || \strlen( $exclusion ) > 500 ) {
			$this->debug_message( 'invalid exclusion value:' );
			$this->debug_message( $exclusion );
			return;
		}
		$exclusions = $this->get_option( $this->prefix . 'll_exclude' );
		if ( empty( $exclusions ) ) {
			$exclusions = array();
		} elseif ( \is_string( $exclusions ) ) {
			$exclusions = \explode( "\n", $exclusions );
		} else {
			$this->debug_message( 'invalid exclusions value, not a string or array, check the db' );
			return;
		}
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
	 * AJAX handler for adding an image to the ignore list for scaling issues.
	 */
	public function ajax_add_ignore_scaling_rule() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\check_ajax_referer( 'ewww-image-detective', 'ewww_wpnonce' );
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			\wp_send_json_error( \esc_html__( 'You do not have permission to perform this action.', 'ewww-image-optimizer' ) );
		}
		$this->ob_clean();
		if ( empty( $_REQUEST['ignore'] ) ) {
			\wp_send_json_error( \esc_html__( 'Cannot save empty image path.', 'ewww-image-optimizer' ) );
		}
		$exclusion = sanitize_text_field( wp_unslash( $_REQUEST['ignore'] ) );
		if ( empty( $_REQUEST['post_id'] ) && empty( $_REQUEST['request_uri'] ) ) {
			\wp_send_json_error( \esc_html__( 'Cannot add image to ignore list without a valid post ID or request URI.', 'ewww-image-optimizer' ) );
		}
		if ( ! empty( $_REQUEST['post_id'] ) ) {
			$post_identifier = (int) $_REQUEST['post_id'];
		} else {
			$post_identifier = \sanitize_text_field( \wp_unslash( $_REQUEST['request_uri'] ) );
		}
		$this->debug_message( "adding $exclusion to ignore list for post/page $post_identifier" );
		$this->update_page_scaling_detection_exclusions( $post_identifier, $exclusion );
		$this->ob_clean();
		\wp_send_json(
			array(
				'success' => true,
				'message' => \esc_html__( 'The image has been added to the ignore list.', 'ewww-image-optimizer' ),
			)
		);
	}

	/**
	 * Update page-specific scaling-detection exclusions.
	 *
	 * @param int|string $post_identifier The post ID or request URI of the page for which to add the exclusion.
	 * @param string     $exclusion The exclusion to add.
	 */
	private function update_page_scaling_detection_exclusions( $post_identifier, $exclusion ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
			$exclusions        = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['scale_detection_exclude'] ) ? $eio_page_settings['scale_detection_exclude'] : array();
			$new_exclusions    = $this->merge_exclusions( $exclusions, $exclusion );
			if ( is_array( $new_exclusions ) && ! empty( $new_exclusions ) && count( $new_exclusions ) > count( $exclusions ) ) {
				$this->debug_message( 'saving updated exclusions in postmeta' );
				if ( ! is_array( $eio_page_settings ) ) {
					$eio_page_settings = array();
				}
				$eio_page_settings['scale_detection_exclude'] = $new_exclusions;
				\update_post_meta( $post_identifier, 'eio_page_settings', \serialize( $eio_page_settings ) );
			}
		} elseif ( \is_string( $post_identifier ) ) {
			$eio_page_record   = $this->get_page_settings( $post_identifier );
			$eio_page_settings = isset( $eio_page_record['data'] ) && is_array( $eio_page_record['data'] ) ? $eio_page_record['data'] : array();
			$exclusions        = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['scale_detection_exclude'] ) ? $eio_page_settings['scale_detection_exclude'] : array();
			$new_exclusions    = $this->merge_exclusions( $exclusions, $exclusion );
			if ( is_array( $new_exclusions ) && ! empty( $new_exclusions ) && count( $new_exclusions ) > count( $exclusions ) ) {
				$this->debug_message( 'saving updated exclusions in ewwwio_pages table' );
				if ( ! is_array( $eio_page_record ) || empty( $eio_page_record ) || ! isset( $eio_page_record['id'] ) ) {
					$eio_page_record = array(
						'page' => $post_identifier,
						'data' => array(),
					);
				}
				$eio_page_record['data']['scale_detection_exclude'] = $new_exclusions;
				$this->update_page_settings( $eio_page_record );
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
	 * Adds Image Detective menu to the wp admin bar.
	 *
	 * @param object $wp_admin_bar The WP Admin Bar object, passed by reference.
	 */
	public function admin_bar_menu( $wp_admin_bar ) {
		if (
			! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ||
			! \is_admin_bar_showing() ||
			( ! empty( $_SERVER['SCRIPT_NAME'] ) && 'wp-login.php' === \wp_basename( \sanitize_text_field( \wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) )
		) {
			return;
		}
		$wp_admin_bar->add_node(
			array(
				'id'    => 'image-detective',
				'title' => __( 'Image Detective', 'ewww-image-optimizer' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'image-detective-check',
				'href'   => '#',
				'parent' => 'image-detective',
				'title'  => __( 'Check again', 'ewww-image-optimizer' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'image-detective-clear',
				'href'   => '#',
				'parent' => 'image-detective',
				'title'  => __( 'Clear detected images', 'ewww-image-optimizer' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'image-detective-remove-per-page-settings',
				'href'   => wp_nonce_url(
					add_query_arg(
						array(
							'_action'     => 'ewww_remove_per_page_settings',
							'post_id'     => is_singular() ? get_queried_object_id() : '',
							'request_uri' => ! is_singular() ? $this->parse_url( $this->request_uri, PHP_URL_PATH ) : '',
						),
					),
					'ewww-remove-page-settings'
				),
				'parent' => 'image-detective',
				'title'  => __( 'Clear settings for current page', 'ewww-image-optimizer' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'image-detective-remove-all-page-settings',
				'href'   => wp_nonce_url(
					add_query_arg(
						array(
							'_action' => 'ewww_remove_all_page_settings',
						),
					),
					'ewww-remove-page-settings'
				),
				'parent' => 'image-detective',
				'title'  => __( 'Clear all per-page settings', 'ewww-image-optimizer' ),
			)
		);
	}

	/**
	 * Handle requests to clear page settings.
	 */
	public function handle_clear_settings_request() {
		if ( ! \current_user_can( \apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
			return;
		}
		if ( empty( $_GET['_action'] ) || ! \in_array( $_GET['_action'], array( 'ewww_remove_per_page_settings', 'ewww_remove_all_page_settings' ), true ) ) {
			return;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_GET['_wpnonce'] ), 'ewww-remove-page-settings' ) ) {
			return;
		}
		if ( 'ewww_remove_per_page_settings' === $_GET['_action'] ) {
			$post_identifier = '';
			if ( ! empty( $_REQUEST['post_id'] ) ) {
				$post_identifier = (int) $_REQUEST['post_id'];
				\delete_post_meta( $post_identifier, 'eio_page_settings' );
			} elseif ( ! empty( $_REQUEST['request_uri'] ) ) {
				$post_identifier = \sanitize_text_field( \wp_unslash( $_REQUEST['request_uri'] ) );
				$this->remove_per_page_settings( $post_identifier );
			}
		} elseif ( 'ewww_remove_all_page_settings' === $_GET['_action'] ) {
			\update_option( $this->prefix . 'll_manual_page_settings', false );
			$this->remove_all_page_settings();
		}
		\wp_safe_redirect( \remove_query_arg( array( '_action', '_wpnonce', 'post_id', 'request_uri' ) ) );
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
		if ( ! $this->get_option( 'ewww_image_optimizer_image_detective' ) ) {
			return;
		}
		$plugin_dir = \plugin_dir_path( \constant( \strtoupper( $this->prefix ) . 'PLUGIN_FILE' ) );
		if (
			( \defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ||
			( \defined( \strtoupper( $this->prefix ) . 'SCRIPT_DEBUG' ) && \constant( \strtoupper( $this->prefix ) . 'SCRIPT_DEBUG' ) )
		) {
			$image_detective_script = \file_get_contents( $plugin_dir . 'includes/resize-detection.js' );
		} else {
			$image_detective_script = \file_get_contents( $plugin_dir . 'includes/resize-detection.min.js' );
		}
		$scaling_exclusions = array();
		$post_id            = 0;
		if ( \is_singular() ) {
			$post_id            = \get_queried_object_id();
			$eio_page_settings  = \maybe_unserialize( \get_post_meta( $post_id, 'eio_page_settings', true ) );
			$scaling_exclusions = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['scale_detection_exclude'] ) ? $eio_page_settings['scale_detection_exclude'] : array();
		}
		$request_uri = $this->parse_url( $this->request_uri, PHP_URL_PATH );
		if ( empty( $request_uri ) ) {
			$request_uri = '/';
		}
		if ( empty( $post_id ) && ! empty( $request_uri ) ) {
			$eio_page_record    = $this->get_page_settings( $request_uri );
			$eio_page_settings  = isset( $eio_page_record['data'] ) && is_array( $eio_page_record['data'] ) ? $eio_page_record['data'] : array();
			$scaling_exclusions = $this->is_iterable( $eio_page_settings ) && isset( $eio_page_settings['scale_detection_exclude'] ) ? $eio_page_settings['scale_detection_exclude'] : array();
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
			#ewww-lazy-exclude-menu.ewww-error, #ewww-scaling-error.ewww-error {
				background-color: #fff;
				color: #d10707;
				padding: 6px 10px;
				border: #d10707 solid 1px;
			}
			#ewww-lazy-exclude-menu.ewww-success, #ewww-scaling-error.ewww-success {
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
			.ewww-ignore-scaling-error {
				color: #1e8da9;
				padding-left: 10px;
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
		<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" id="ewww-image-detective-inline-script">
			<?php echo $image_detective_script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			var imageDetectiveVars = {
				ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
				nonce: '<?php echo esc_js( wp_create_nonce( 'ewww-image-detective' ) ); ?>',
				llActive: <?php echo $this->get_option( $this->prefix . 'lazy_load' ) ? 'true' : 'false'; ?>,
				excludeMenuText: '<?php echo esc_js( __( 'Add Lazy Load Exclusion', 'ewww-image-optimizer' ) ); ?>',
				invalid_response: '<?php echo esc_js( __( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'ewww-image-optimizer' ) ); ?>',
				<?php /* translators: %s is a placeholder for the image name or class. */ ?>
				menuItemGlobalText: '<?php printf( esc_js( __( 'Exclude %s', 'ewww-image-optimizer' ) ), '<span class="ewww-lazy-exclude-item-name"></span>' ); ?>',
				<?php /* translators: %s is a placeholder for the image name. */ ?>
				menuItemPageText: '<?php printf( esc_js( __( 'Exclude %s on this page only', 'ewww-image-optimizer' ) ), '<span class="ewww-lazy-exclude-item-name"></span>' ); ?>',
				<?php /* translators: 1: Natural image width, 2: Natural image height, 3: Container width, 4: Container height. Note that width and height have an 'x' between them. */ ?>
				scalingErrorText: '<?php printf( esc_js( __( 'Forced to wrong size: natural image size is %1$sx%2$s, but should be %3$sx%4$s', 'ewww-image-optimizer' ) ), '<span class="ewww-scaling-error-natural-width"></span>', '<span class="ewww-scaling-error-natural-height"></span>', '<span class="ewww-scaling-error-container-width"></span>', '<span class="ewww-scaling-error-container-height"></span>' ); ?>',
				ignoreErrorText: '<?php echo esc_js( __( 'Ignore', 'ewww-image-optimizer' ) ); ?>',
				scalingExclusions: <?php echo wp_json_encode( $scaling_exclusions ); ?>,
				postId: <?php echo (int) $post_id; ?>,
				requestUri: '<?php echo esc_js( $request_uri ); ?>',
			};
		</script>
		<?php
	}
}
