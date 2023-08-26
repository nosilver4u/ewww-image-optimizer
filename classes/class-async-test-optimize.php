<?php
/**
 * Class for Background of Media Library images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles a simulated async request used to test async requests for the debugger.
 *
 * @see EWWW\Async_Request
 */
class Async_Test_Optimize extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_test_optimize';

	/**
	 * Handles the test async request.
	 *
	 * Called via a POST request to verify that nothing is blocking or altering requests from the server to itself.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		if ( empty( $_POST['ewwwio_test_verify'] ) ) {
			return;
		}
	}
}
