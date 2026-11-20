<?php
/**
 * Class EWWWIO_CLI_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP_CLI stand-in, so the CLI class can run inside the WP test suite.
	 */
	class WP_CLI {
		/**
		 * Output a line (ignored).
		 *
		 * @param string $message The message.
		 */
		public static function line( $message = '' ) {}

		/**
		 * Output a success message (ignored).
		 *
		 * @param string $message The message.
		 */
		public static function success( $message = '' ) {}

		/**
		 * Confirm a prompt (always yes).
		 *
		 * @param string $message The message.
		 */
		public static function confirm( $message = '' ) {}

		/**
		 * Register a command (ignored).
		 *
		 * @param string $name The command name.
		 * @param string $callable The command class.
		 */
		public static function add_command( $name, $callable ) {}

		/**
		 * Stop on an error.
		 *
		 * @param string $message The message.
		 * @throws Exception Always.
		 */
		public static function error( $message = '' ) {
			throw new Exception( $message );
		}
	}
}
if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Minimal WP_CLI_Command stand-in.
	 */
	class WP_CLI_Command {}
}

/**
 * WP-CLI test cases.
 */
class EWWWIO_CLI_Tests extends WP_UnitTestCase {

	/**
	 * Load the CLI class once.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-cli.php';
	}

	/**
	 * Remove the request flags and plugin state set by the CLI, so later tests run with the defaults.
	 */
	public function tear_down() {
		unset( $_REQUEST['ewww_webp_only'], $_REQUEST['ewww_force'], $_REQUEST['ewww_batch_limit'] );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', '' );
		ewwwio()->webp_only           = false;
		ewwwio()->force               = false;
		ewwwio()->defer               = true;
		ewwwio()->cloud_async_allowed = false;
		remove_filter( 'ewww_image_optimizer_timeout', 'ewww_image_optimizer_cli_timeout', 200 );
		parent::tear_down();
	}

	/**
	 * The --webp-only flag must survive the bulk scan, which re-reads the bulk options from the request.
	 */
	function test_optimize_media_keeps_webp_only() {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_webp', true );

		// The CLI flushes and closes every output buffer, so reopen them afterwards.
		$ob_level = ob_get_level();
		$cli      = new EWWWIO_CLI();
		$cli->optimize(
			array( 'media', 0 ),
			array(
				'webp-only' => true,
				'noprompt'  => true,
				'reset'     => true,
			)
		);
		while ( ob_get_level() < $ob_level ) {
			ob_start();
		}

		$this->assertTrue( ewwwio()->webp_only );
	}
}
