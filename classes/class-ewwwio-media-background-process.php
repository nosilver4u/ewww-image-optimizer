<?php
/**
 * Classes for Background and Async processing.
 *
 * This file contains classes and methods that extend EWWWIO_Background_Process and
 * WP_Async_Request to allow parallel and background processing of images.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The (grand)parent WP_Async_Request class file.
 */
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'vendor/wp-async-request.php' );

/**
 * The parent EWWWIO_Background_Process class file.
 */
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/class-ewwwio-background-process.php' );

/**
 * Processes media uploads in background/async mode.
 *
 * Uses a db queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see EWWWIO_Background_Process
 */
class EWWWIO_Media_Background_Process extends EWWWIO_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_media_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'media-async';

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
	 *                    the item, the type of attachment, and whether it is a new upload.
	 * @return bool|array If the item is not complete, return it. False indicates completion.
	 */
	protected function task( $item ) {
		session_write_close();
		global $ewww_defer;
		$ewww_defer   = false;
		$max_attempts = 15;
		$id           = $item['id'];
		if ( empty( $item['attempts'] ) ) {
			ewwwio_debug_message( 'first attempt, going to sleep for a bit' );
			$item['attempts'] = 0;
			sleep( 1 ); // On the first attempt, hold off and wait for the db to catch up.
		}
		$type = get_post_mime_type( $id );
		if ( empty( $type ) ) {
			ewwwio_debug_message( "mime is missing, requeueing {$item['attempts']}" );
			sleep( 4 );
			return $item;
		}
		ewwwio_debug_message( "background processing $id, type: " . $type );
		$image_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
		);

		if ( in_array( $type, $image_types, true ) && $item['new'] && class_exists( 'wpCloud\StatelessMedia\EWWW' ) ) {
			$meta = wp_get_attachment_metadata( $id );
		} else {
			// This is unfiltered for performance, because we don't often need filtered meta.
			$meta = wp_get_attachment_metadata( $id, true );
		}
		if ( in_array( $type, $image_types, true ) && empty( $meta ) ) {
			ewwwio_debug_message( "metadata is missing, requeueing {$item['attempts']}" );
			sleep( 4 );
			return $item;
		}
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, true, $item['new'] );
		if ( ! empty( $meta['processing'] ) ) {
			ewwwio_debug_message( 'image not finished, try again' );
			return $item;
		}
		if ( class_exists( 'wpCloud\StatelessMedia\EWWW' ) ) {
			ewwwio_debug_message( 'async optimize complete, triggering wp_update_attachment_metadata filter with existing meta' );
			$meta = apply_filters( 'wp_update_attachment_metadata', wp_get_attachment_metadata( $image->attachment_id ), $image->attachment_id );
		} else {
			ewwwio_debug_message( 'async optimize complete, running wp_update_attachment_metadata()' );
			wp_update_attachment_metadata( $id, $meta );
		}
		return false;
	}

	/**
	 * Runs failure routine for an item from the Media Library queue.
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
		$file_path = false;
		$meta      = wp_get_attachment_metadata( $item['id'] );
		if ( ! empty( $meta ) ) {
			list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $item['id'] );
		}

		if ( $file_path ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
	}
}
global $ewwwio_media_background;
$ewwwio_media_background = new EWWWIO_Media_Background_Process();

/**
 * Processes a single image in background/async mode.
 *
 * @see EWWWIO_Background_Process
 */
class EWWWIO_Image_Background_Process extends EWWWIO_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_image_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'single-async';

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
		$id = (int) $item['id'];
		ewwwio_debug_message( "background processing $id" );
		$file_path = ewww_image_optimizer_find_file_by_id( $id );
		if ( $file_path ) {
			$attachment = array(
				'id'   => $id,
				'path' => $file_path,
			);
			ewwwio_debug_message( "processing background optimization request for $file_path" );
			ewww_image_optimizer_aux_images_loop( $attachment, true );
		} else {
			ewwwio_debug_message( "could not find file to process background optimization request for $id" );
			return false;
		}
		$delay = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' );
		if ( $delay && ewww_image_optimizer_function_exists( 'sleep' ) ) {
			sleep( $delay );
		}
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
		global $wpdb;
		$file_path = ewww_image_optimizer_find_file_by_id( $item['id'] );
		if ( $file_path ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
		$wpdb->query( $wpdb->prepare( "DELETE from $wpdb->ewwwio_images WHERE id=%d pending=1 AND (image_size IS NULL OR image_size = 0)", $item['id'] ) );
	}
}
global $ewwwio_image_background;
$ewwwio_image_background = new EWWWIO_Image_Background_Process();

/**
 * Processes FlaGallery uploads in background/async mode.
 *
 * @see EWWWIO_Background_Process
 */
class EWWWIO_Flag_Background_Process extends EWWWIO_Background_Process {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_flag_optimize';

	/**
	 * The queue name for this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $active_queue = 'flag-async';

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
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$id = $item['id'];
		ewwwio_debug_message( "background processing flagallery: $id" );
		if ( ! class_exists( 'flagMeta' ) ) {
			if ( defined( 'FLAG_ABSPATH' ) && ewwwio_is_file( FLAG_ABSPATH . 'lib/meta.php' ) ) {
				require_once( FLAG_ABSPATH . 'lib/meta.php' );
			} else {
				return false;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new flagMeta( $id );
		if ( empty( $meta ) ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			return $item;
		}
		global $ewwwflag;
		$ewwwflag->ewww_added_new_image( $id, $meta );
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
		if ( ! class_exists( 'flagMeta' ) ) {
			if ( defined( 'FLAG_ABSPATH' ) && ewwwio_is_file( FLAG_ABSPATH . 'lib/meta.php' ) ) {
				require_once( FLAG_ABSPATH . 'lib/meta.php' );
			} else {
				return;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new flagMeta( $item['id'] );
		if ( ! empty( $meta ) && isset( $meta->image->imagePath ) ) {
			ewww_image_optimizer_add_file_exclusion( $meta->image->imagePath );
		}
	}
}
global $ewwwio_flag_background;
$ewwwio_flag_background = new EWWWIO_Flag_Background_Process();

/**
 * Processes Nextcellent uploads in background/async mode.
 *
 * @see EWWWIO_Background_Process
 */
class EWWWIO_Ngg_Background_Process extends EWWWIO_Background_Process {

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
		$id = $item['id'];
		ewwwio_debug_message( "background processing nextcellent: $id" );
		if ( ! class_exists( 'nggMeta' ) ) {
			if ( defined( 'NGGALLERY_ABSPATH' ) && ewwwio_is_file( NGGALLERY_ABSPATH . 'lib/meta.php' ) ) {
				require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
			} else {
				return false;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new nggMeta( $id );
		if ( empty( $meta ) ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			return $item;
		}
		global $ewwwngg;
		$ewwwngg->ewww_added_new_image( $id, $meta );
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
		if ( ! class_exists( 'nggMeta' ) ) {
			if ( defined( 'NGGALLERY_ABSPATH' ) && ewwwio_is_file( NGGALLERY_ABSPATH . 'lib/meta.php' ) ) {
				require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
			} else {
				return;
			}
		}
		// Retrieve the metadata for the image.
		$meta = new nggMeta( $item['id'] );
		if ( ! empty( $meta ) && isset( $meta->image->imagePath ) ) {
			ewww_image_optimizer_add_file_exclusion( $meta->image->imagePath );
		}
	}
}
global $ewwwio_ngg_background;
$ewwwio_ngg_background = new EWWWIO_Ngg_Background_Process();

/**
 * Processes NextGEN 2 uploads in background/async mode.
 *
 * Uses a dual-queue system to track uploads to be optimized, handling them one at a time.
 *
 * @see EWWWIO_Background_Process
 */
class EWWWIO_Ngg2_Background_Process extends EWWWIO_Background_Process {

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
		// Creating the 'registry' object for working with nextgen.
		$registry = C_Component_Registry::get_instance();
		// Creating a database storage object from the 'registry' object.
		$storage = $registry->get_utility( 'I_Gallery_Storage' );
		// Get a NextGEN image object.
		$image = $storage->object->_image_mapper->find( $id );
		if ( ! is_object( $image ) ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve image, requeueing {$item['attempts']}" );
			return $item;
		}
		global $ewwwngg;
		$ewwwngg->ewww_added_new_image( $image, $storage );
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
		// Creating the 'registry' object for working with nextgen.
		$registry = C_Component_Registry::get_instance();
		// Creating a database storage object from the 'registry' object.
		$storage = $registry->get_utility( 'I_Gallery_Storage' );
		// Get a NextGEN image object.
		$image     = $storage->object->_image_mapper->find( $item['id'] );
		$file_path = $storage->get_image_abspath( $image, 'full' );
		if ( ! empty( $file_path ) ) {
			ewww_image_optimizer_add_file_exclusion( $file_path );
		}
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
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		global $ewww_force;
		if ( empty( $_POST['ewwwio_size'] ) ) {
			$size = '';
		} else {
			$size = sanitize_key( $_POST['ewwwio_size'] );
		}
		if ( empty( $_POST['ewwwio_attachment_id'] ) ) {
			$id = 0;
		} else {
			$id = (int) $_POST['ewwwio_attachment_id'];
		}
		if ( empty( $_POST['ewwwio_id'] ) ) {
			return;
		}
		$ewww_force = ! empty( $_REQUEST['ewww_force'] ) ? true : false;
		$ewwwio_id  = (int) $_POST['ewwwio_id'];
		global $ewww_image;
		$file_path = ewww_image_optimizer_find_file_by_id( $ewwwio_id );
		if ( $file_path && 'full' === $size ) {
			ewwwio_debug_message( "processing async optimization request for $file_path" );
			$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
			$ewww_image->resize = 'full';

			list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path, 1, false, false, true );
		} elseif ( $file_path ) {
			ewwwio_debug_message( "processing async optimization request for $file_path" );
			$ewww_image         = new EWWW_Image( $id, 'media', $file_path );
			$ewww_image->resize = ( empty( $size ) ? null : $size );

			list( $file, $msg, $conv, $original ) = ewww_image_optimizer( $file_path );
		} else {
			if ( $ewwwio_id && ! $file_path ) {
				ewwwio_debug_message( "could not find file to process async optimization request for $ewwwio_id" );
			} else {
				ewwwio_debug_message( 'ignored async optimization request' );
			}
			return;
		}
		ewww_image_optimizer_hidpi_optimize( $file_path );
		ewwwio_debug_message( 'checking for: ' . $file_path . '.processing' );
		if ( ewwwio_is_file( $file_path . '.processing' ) ) {
			ewwwio_debug_message( 'removing ' . $file_path . '.processing' );
			$upload_path = wp_get_upload_dir();
			ewwwio_delete_file( $file_path . '.processing' );
		}
	}
}
global $ewwwio_async_optimize_media;
$ewwwio_async_optimize_media = new EWWWIO_Async_Request();

/**
 * Handles an async request to scan for unoptimized images. Subsequent calls will resume from the previous request.
 *
 * @see WP_Async_Request
 */
class EWWWIO_Scan_Async_Handler extends WP_Async_Request {

	/**
	 * The action name used to trigger this class extension.
	 *
	 * @access protected
	 * @var string $action
	 */
	protected $action = 'ewwwio_scan_async';

	/**
	 * Handles the async scan request.
	 */
	protected function handle() {
		session_write_close();
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		global $ewww_scan;
		$ewww_scan = empty( $_REQUEST['ewww_scan'] ) ? '' : sanitize_key( $_REQUEST['ewww_scan'] );
		ewww_image_optimizer_aux_images_script( 'ewww-image-optimizer-auto' );
	}
}
global $ewwwio_scan_async;
$ewwwio_scan_async = new EWWWIO_Scan_Async_Handler();

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
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		check_ajax_referer( $this->identifier, 'nonce' );
		ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ), false );
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
	protected $action = 'ewwwio_test_async';

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
		$item = sanitize_key( $_POST['ewwwio_test_verify'] );
		ewwwio_debug_message( "testing async handling, received $item" );
		if ( ewww_image_optimizer_detect_wpsf_location_lock() ) {
			ewwwio_debug_message( 'detected location lock, not enabling background opt' );
			return;
		}
		if ( '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' !== $item ) {
			ewwwio_debug_message( 'wrong item received, not enabling background opt' );
			return;
		}
		ewwwio_debug_message( 'setting background option to true' );
		$success = ewww_image_optimizer_set_option( 'ewww_image_optimizer_background_optimization', true );
		if ( $success ) {
			ewwwio_debug_message( 'hurrah, async enabled!' );
		}
	}
}
global $ewwwio_test_async;
$ewwwio_test_async = new EWWWIO_Test_Async_Handler();

/**
 * Handles a simulated async request used to test async requests for the debugger.
 *
 * @see WP_Async_Request
 */
class EWWWIO_Test_Optimize_Handler extends WP_Async_Request {

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
global $ewwwio_test_optimize;
$ewwwio_test_optimize = new EWWWIO_Test_Optimize_Handler();
