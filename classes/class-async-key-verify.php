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
 * Handles an async request for validating API keys.
 *
 * Allows periodic verification of an API key without slowing down normal operations.
 *
 * @see EWWW\Async_Request
 */
class Async_Key_Verify extends Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_async_key_verify';

	/**
	 * Handles the async key verification request.
	 *
	 * Called via a POST request to verify an API key asynchronously.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
	}
}
