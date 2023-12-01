<?php
/**
 * Class for Background processing of NextGEN Gallery images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes NextGEN uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see EWWW\Background_Process
 */
class Background_Process_Ngg2 extends Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_ngg2_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'nextg-async';

	/**
	 * Runs task for an item from the NextGEN queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual nextgen optimization routine on the specified item.
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
		$id = $item['id'];
		ewwwio_debug_message( "background processing nextgen2: $id" );
		if ( ! defined( 'NGG_PLUGIN_VERSION' ) ) {
			return false;
		}
		global $ewwwngg;
		// Get a NextGEN image object.
		$image = $ewwwngg->get_ngg_image( $id );
		if ( ! is_object( $image ) ) {
			++$item['attempts'];
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve image, requeueing {$item['attempts']}" );
			return $item;
		}
		$ewwwngg->ewww_added_new_image( $image );
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
		if ( empty( $item['id'] ) ) {
			return;
		}
		if ( ! defined( 'NGG_PLUGIN_VERSION' ) ) {
			return false;
		}
		// Get a NextGEN image object.
		global $ewwwngg;
		$image     = $ewwwngg->get_ngg_image( $item['id'] );
		$file_path = $ewwwngg->get_image_abspath( $image, 'full' );
		if ( ! empty( $file_path ) ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
	}
}
