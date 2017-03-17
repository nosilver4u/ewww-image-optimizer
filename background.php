<?php
/**
 * Classes for Background and Async processing.
 *
 * This file contains classes and methods that extend WP_Background_Process and
 * WP_Async_Request to allow parallel and background processing of images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The parent WP_Async_Request class file.
 */
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/wp-async-request.php' );

/**
 * The parent WP_Background_Process class file.
 */
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/wp-background-process.php' );

/**
 * Processes media uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see WP_Background_Process
 */
class EWWWIO_Media_Background_Process extends WP_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_media_optimize';

	/**
	 * Runs task for an item from the Media Library queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual media optimization routine on the specified item.
	 *
	 * @access protected
	 * @global bool $ewww_defer True to defer optimization, false otherwise.
	 *
	 * @param array $item The id of the attachment, how many attempts have been made to process
	 *		 the item, the type of attachment, and whether it is a new upload.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		global $ewww_defer;
		$ewww_defer = false;
		$max_attempts = 15;
		$id = $item['id'];
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
			sleep( 4 ); // On the first attempt, hold off and wait for the db to catch up.
		}
		ewwwio_debug_message( "background processing $id, type: " . $item['type'] );
		$type = $item['type'];
		$image_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
		);
		$meta = wp_get_attachment_metadata( $id, true );
		if ( in_array( $type, $image_types ) && empty( $meta ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "metadata is missing, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( in_array( $type, $image_types ) && empty( $meta ) ) {
			ewwwio_debug_message( 'metadata is missing for image, out of attempts' );
			ewww_image_optimizer_debug_log();
			delete_transient( 'ewwwio-background-in-progress-' . $id );
			return false;
		}
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, true, $item['new'] );
		if ( ! empty( $meta['processing'] ) ) {
			$item['attempts']++;
			ewwwio_debug_message( 'image not finished, try again' );
			ewww_image_optimizer_debug_log();
			return $item;
		}
		wp_update_attachment_metadata( $id, $meta );
		ewww_image_optimizer_debug_log();
		delete_transient( 'ewwwio-background-in-progress-' . $id );
		return false;
	}

	/**
	 * Run when queue processing is complete.
	 *
	 * Flushes the debug information to the log and then runs the parent method.
	 *
	 * @access protected
	 */
	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_media_background;
$ewwwio_media_background = new EWWWIO_Media_Background_Process();

/**
 * Processes a single image in background/async mode.
 *
 * Uses a dual-queue system to track auto-generated images to be optimized, handling them one at a
 * time. This is only used for Nextcellent thumbs currently.
 *
 * @deprecated 3.1.3
 * @see WP_Background_Process
 */
class EWWWIO_Image_Background_Process extends WP_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_image_optimize';

	/**
	 * Runs optimization for a file from the image queue.
	 *
	 * @access protected
	 *
	 * @param string $item The filename of the attachment.
	 * @return bool False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		sleep( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
		ewwwio_debug_message( "background processing $item" );
		ewww_image_optimizer( $item );
		ewww_image_optimizer_debug_log();
		return false;
	}

	/**
	 * Run when queue processing is complete.
	 *
	 * Flushes the debug information to the log and then runs the parent method.
	 *
	 * @access protected
	 */
	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_image_background;
$ewwwio_image_background = new EWWWIO_Image_Background_Process();

/**
 * Processes FlaGallery uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see WP_Background_Process
 */
class EWWWIO_Flag_Background_Process extends WP_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_flag_optimize';

	/**
	 * Runs task for an item from the FlaGallery queue.
	 *
	 * Makes sure an image upload has finished processing and has been stored in the database.
	 * Then runs the usual flag optimization routine on the specified item.
	 *
	 * @access protected
	 * @global bool $ewwwflag
	 *
	 * @param array $item The id of the upload, and how many attempts have been made so far.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		$max_attempts = 15;
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$id = $item['id'];
		ewwwio_debug_message( "background processing flagallery: $id" );
		if ( ! class_exists( 'flagMeta' ) ) {
			require_once( FLAG_ABSPATH . 'lib/meta.php' );
		}
		// Retrieve the metadata for the image.
		$image = new flagMeta( $id );
		if ( empty( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( 'could not retrieve meta, out of attempts' );
			ewww_image_optimizer_debug_log();
			delete_transient( 'ewwwio-background-in-progress-flag-' . $id );
			return false;
		}
		global $ewwwflag;
		$ewwwflag->ewww_added_new_image( $id, $image );
		delete_transient( 'ewwwio-background-in-progress-flag-' . $id );
		sleep( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
		ewww_image_optimizer_debug_log();
		return false;
	}

	/**
	 * Run when queue processing is complete.
	 *
	 * Flushes the debug information to the log and then runs the parent method.
	 *
	 * @access protected
	 */
	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_flag_background;
$ewwwio_flag_background = new EWWWIO_Flag_Background_Process();

/**
 * Processes Nextcellent uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see WP_Background_Process
 */
class EWWWIO_Ngg_Background_Process extends WP_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_ngg_optimize';

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
		$max_attempts = 15;
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$id = $item['id'];
		ewwwio_debug_message( "background processing nextcellent: $id" );
		if ( ! class_exists( 'nggMeta' ) ) {
			require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
		}
		// Retrieve the metadata for the image.
		$image = new nggMeta( $id );
		if ( empty( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( 'could not retrieve meta, out of attempts' );
			ewww_image_optimizer_debug_log();
			delete_transient( 'ewwwio-background-in-progress-ngg-' . $id );
			return false;
		}
		global $ewwwngg;
		$ewwwngg->ewww_added_new_image( $id, $image );
		delete_transient( 'ewwwio-background-in-progress-ngg-' . $id );
		sleep( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
		ewww_image_optimizer_debug_log();
		return false;
	}

	/**
	 * Run when queue processing is complete.
	 *
	 * Flushes the debug information to the log and then runs the parent method.
	 *
	 * @access protected
	 */
	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_ngg_background;
$ewwwio_ngg_background = new EWWWIO_Ngg_Background_Process();

/**
 * Processes NextGEN 2 uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see WP_Background_Process
 */
class EWWWIO_Ngg2_Background_Process extends WP_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_ngg2_optimize';

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
		$max_attempts = 15;
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$id = $item['id'];
		ewwwio_debug_message( "background processing nextgen2: $id" );
		// Creating the 'registry' object for working with nextgen.
		$registry = C_Component_Registry::get_instance();
		// Creating a database storage object from the 'registry' object.
		$storage  = $registry->get_utility( 'I_Gallery_Storage' );
		// Get a NextGEN image object.
		$image = $storage->object->_image_mapper->find( $id );
		if ( ! is_object( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve image, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( 'could not retrieve image, out of attempts' );
			ewww_image_optimizer_debug_log();
			delete_transient( 'ewwwio-background-in-progress-ngg-' . $id );
			return false;
		}
		global $ewwwngg;
		$ewwwngg->ewww_added_new_image( $image, $storage );
		delete_transient( 'ewwwio-background-in-progress-ngg-' . $id );
		sleep( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
		ewww_image_optimizer_debug_log();
		return false;
	}

	/**
	 * Run when queue processing is complete.
	 *
	 * Flushes the debug information to the log and then runs the parent method.
	 *
	 * @access protected
	 */
	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_ngg2_background;
$ewwwio_ngg2_background = new EWWWIO_Ngg2_Background_Process();

/**
 * Handles an async request used to optimize a Media Library image.
 *
 * Used to optimize a single image, like a resize, retina, or the original upload for a
 * Media Library attachment. Done in parallel to increase processing capability.
 *
 * @see WP_Async_Request
 */
class EWWWIO_Async_Request extends WP_Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_async_optimize_media';

	/**
	 * Handles the async media image optimization request.
	 *
	 * Called via a POST to optimize an image from a Media Library attachment using parallel optimization.
	 *
	 * @global object $ewww_image Tracks attributes of the image currently being optimized.
	 */
	protected function handle() {
		session_write_close();
		if ( empty( $_POST['ewwwio_size'] ) ) {
			$size = '';
		} else {
			$size = $_POST['ewwwio_size'];
		}
		if ( empty( $_POST['ewwwio_id'] ) ) {
			$id = 0;
		} else {
			$id = (int) $_POST['ewwwio_id'];
		}
		global $ewww_image;
		if ( ! empty( $_POST['ewwwio_path'] ) && 'full' == $size ) {
			$file_path = $this->find_file( $_POST['ewwwio_path'] );
			if ( ! empty( $file_path ) ) {
				ewwwio_debug_message( "processing async optimization request for {$_POST['ewwwio_path']}" );
				$ewww_image = new EWWW_Image( $id, 'media', $file_path );
				$ewww_image->resize = 'full';
				list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, 1, false, false, true );
			} else {
				ewwwio_debug_message( "could not process async optimization request for {$_POST['ewwwio_path']}" );
			}
		} elseif ( ! empty( $_POST['ewwwio_path'] ) ) {
			$file_path = $this->find_file( $_POST['ewwwio_path'] );
			if ( ! empty( $file_path ) ) {
				ewwwio_debug_message( "processing async optimization request for {$_POST['ewwwio_path']}" );
				$ewww_image = new EWWW_Image( $id, 'media', $file_path );
				$ewww_image->resize = ( empty( $size ) ? null : $size );
				list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path );
			} else {
				ewwwio_debug_message( "could not process async optimization request for {$_POST['ewwwio_path']}" );
			}
		} else {
			ewwwio_debug_message( 'ignored async optimization request' );
			return;
		}
		ewww_image_optimizer_hidpi_optimize( $file_path );
		ewwwio_debug_message( 'checking for: ' . $file_path . '.processing' );
		if ( is_file( $file_path . '.processing' ) ) {
			ewwwio_debug_message( 'removing ' . $file_path . '.processing' );
			unlink( $file_path . '.processing' );
		}
		ewww_image_optimizer_debug_log();
	}

	/**
	 * Finds the absolute path of a file.
	 *
	 * Given a relative path (to avoid tripping security filters), it uses several methods to try and determine the original, absolute path.
	 *
	 * @param string $file_path A partial/relative file path.
	 * @return string The full file path, reconstructed using the upload folder for WP_CONTENT_DIR
	 */
	public function find_file( $file_path ) {
		if ( is_file( $file_path ) ) {
			return $file_path;
		}
		// Retrieve the location of the wordpress upload folder.
		$upload_dir = wp_upload_dir();
		$upload_path = trailingslashit( $upload_dir['basedir'] );
		$file = $upload_path . $file_path;
		if ( is_file( $file ) ) {
			return $file;
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR );
		$file = $upload_path . $file_path;
		if ( is_file( $file ) ) {
			return $file;
		}
		$upload_path .= 'uploads/';
		$file = $upload_path . $file_path;
		if ( is_file( $file ) ) {
			return $file;
		}
		return '';
	}
}

global $ewwwio_async_optimize_media;
$ewwwio_async_optimize_media = new EWWWIO_Async_Request();

/**
 * Handles an async request for validating API keys.
 *
 * Allows periodic verification of an API key without slowing down normal operations.
 *
 * @see WP_Async_Request
 */
class EWWWIO_Async_Key_Verification extends WP_Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_async_key_verification';

	/**
	 * Handles the async key verification request.
	 *
	 * Called via a POST request to verify an API key asynchronously.
	 */
	protected function handle() {
		session_write_close();
		ewww_image_optimizer_cloud_verify( false );
		ewww_image_optimizer_debug_log();
	}
}

global $ewwwio_async_key_verification;
$ewwwio_async_key_verification = new EWWWIO_Async_Key_Verification();

/**
 * Handles an async request used to test the viability of using async requests
 * elsewhere.
 *
 * During a plugin update, an async request is sent with a specific string
 * value to validate that nothing is blocking admin-ajax.php requests from
 * the server to itself. Once verified, full background/parallel processing
 * can be used.
 *
 * @see WP_Async_Request
 */
class EWWWIO_Test_Async_Handler extends WP_Async_Request {

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
		if ( empty( $_POST['ewwwio_test_verify'] ) ) {
			return;
		}
		$item = $_POST['ewwwio_test_verify'];
		ewwwio_debug_message( "testing async handling, received $item" );
		if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
			ewwwio_debug_message( 'detected location lock, not enabling background opt' );
			ewww_image_optimizer_debug_log();
			return;
		}
		if ( '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' != $item ) {
			ewwwio_debug_message( 'wrong item received, not enabling background opt' );
			ewww_image_optimizer_debug_log();
			return;
		}
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', true );
		ewww_image_optimizer_debug_log();
	}
}

global $ewwwio_test_async;
$ewwwio_test_async = new EWWWIO_Test_Async_Handler();
