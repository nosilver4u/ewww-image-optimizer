<?php
/**
 * Class for Background processing of Nextcellent images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes Nextcellent uploads in background/async mode.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Ngg extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_ngg_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'nextc-async';

	/**
	 * Runs task for an item from the Nextcellent queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual nextcellent optimization routine on the specified item.
	 *
	 * @access protected
	 * @global bool $ewwwngg
	 *
	 * @param array $item The id of the upload, and how many attempts have been made so far.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$attachment_id = $item['attachment_id'];
		ewwwio_debug_message( "background processing nextcellent: $attachment_id" );
		if ( ! class_exists( 'nggMeta' ) ) {
			if ( defined( 'NGGALLERY_ABSPATH' ) && ewwwio_is_file( NGGALLERY_ABSPATH . 'lib/meta.php' ) ) {
				require_once NGGALLERY_ABSPATH . '/lib/meta.php';
			} else {
				return false;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new nggMeta( $attachment_id );
		if ( empty( $meta ) ) {
			++$item['attempts'];
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			return $item;
		}
		global $ewwwngg;
		$ewwwngg->ewww_added_new_image( $attachment_id, $meta );
		return false;
	}

	/**
	 * Runs failure routine for an item from the queue.
	 *
	 * @access protected
	 *
	 * @param array $item The id of the attachment, how many attempts have been made to process
	 *                    the item and whether it is a new upload.
	 */
	protected function failure( $item ) {
		if ( empty( $item['attachment_id'] ) ) {
			return;
		}
		if ( ! class_exists( 'nggMeta' ) ) {
			if ( defined( 'NGGALLERY_ABSPATH' ) && ewwwio_is_file( NGGALLERY_ABSPATH . 'lib/meta.php' ) ) {
				require_once NGGALLERY_ABSPATH . '/lib/meta.php';
			} else {
				return;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new nggMeta( $item['attachment_id'] );
		if ( ! empty( $meta ) && isset( $meta->image->imagePath ) ) {
			ewww_image_optimizer_add_file_exclusion( $meta->image->imagePath );
		}
	}
}
