<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/wp-async-request.php' );
require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'classes/wp-background-process.php' );

class EWWWIO_Media_Background_Process extends WP_Background_Process {

	protected $action = 'ewwwio_media_optimize';

	protected function task( $item ) {
		session_write_close();
		global $ewww_defer;
		$ewww_defer = false;
		$max_attempts = 15;
		$id = $item['id'];
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
			sleep(4); // on the first attempt, hold off and wait for the db to catchup
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
			ewwwio_debug_message( "metadata is missing for image, out of attempts" );
			ewww_image_optimizer_debug_log();
			delete_transient( 'ewwwio-background-in-progress-' . $id );
			return false;
		}
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, true, $item['new'] );
		if ( ! empty( $meta['processing'] ) ) {
			$item['attempts']++;
			ewwwio_debug_message( "image not finished, try again" );
			ewww_image_optimizer_debug_log();
			return $item;
		}
		wp_update_attachment_metadata( $id, $meta );
		ewww_image_optimizer_debug_log();
		delete_transient( 'ewwwio-background-in-progress-' . $id );
		return false;
	}

	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_media_background;
$ewwwio_media_background = new EWWWIO_Media_Background_Process();

class EWWWIO_Image_Background_Process extends WP_Background_Process {

	protected $action = 'ewwwio_image_optimize';

	protected function task( $item ) {
		session_write_close();
		sleep( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delay' ) );
		ewwwio_debug_message( "background processing $item" );
		ewww_image_optimizer( $item );
		ewww_image_optimizer_debug_log();
		return false;
	}

	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_image_background;
$ewwwio_image_background = new EWWWIO_Image_Background_Process();

class EWWWIO_Flag_Background_Process extends WP_Background_Process {

	protected $action = 'ewwwio_flag_optimize';

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
		// retrieve the metadata for the image
		$image = new flagMeta( $id );
		if ( empty( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( "could not retrieve meta, out of attempts" );
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

	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_flag_background;
$ewwwio_flag_background = new EWWWIO_Flag_Background_Process();

class EWWWIO_Ngg_Background_Process extends WP_Background_Process {

	protected $action = 'ewwwio_ngg_optimize';

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
		// retrieve the metadata for the image
		$image = new nggMeta( $id );
		if ( empty( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve meta, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( "could not retrieve meta, out of attempts" );
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

	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_ngg_background;
$ewwwio_ngg_background = new EWWWIO_Ngg_Background_Process();

class EWWWIO_Ngg2_Background_Process extends WP_Background_Process {

	protected $action = 'ewwwio_ngg2_optimize';

	protected function task( $item ) {
		session_write_close();
		$max_attempts = 15;
		if ( empty( $item['attempts'] ) ) {
			$item['attempts'] = 0;
		}
		$id = $item['id'];
		ewwwio_debug_message( "background processing nextgen2: $id" );
		// creating the 'registry' object for working with nextgen
		$registry = C_Component_Registry::get_instance();
		// creating a database storage object from the 'registry' object
		$storage  = $registry->get_utility('I_Gallery_Storage');
		// get an image object
		$image = $storage->object->_image_mapper->find( $id );
		if ( ! is_object( $image ) && $item['attempts'] < $max_attempts ) {
			$item['attempts']++;
			sleep( 4 );
			ewwwio_debug_message( "could not retrieve image, requeueing {$item['attempts']}" );
			ewww_image_optimizer_debug_log();
			return $item;
		} elseif ( empty( $image ) ) {
			ewwwio_debug_message( "could not retrieve image, out of attempts" );
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

	protected function complete() {
		ewww_image_optimizer_debug_log();
		parent::complete();
	}
}

global $ewwwio_ngg2_background;
$ewwwio_ngg2_background = new EWWWIO_Ngg2_Background_Process();

class EWWWIO_Async_Request extends WP_Async_Request {

	protected $action = 'ewwwio_async_optimize_media';

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
		if ( ! empty( $_POST['ewwwio_path'] ) && $size == 'full' ) {
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
	
	public function find_file( $file_path ) {
		if ( is_file( $file_path ) ) {
			return $file_path;
		}
		// retrieve the location of the wordpress upload folder
		$upload_dir = wp_upload_dir();
		// retrieve the path of the upload folder
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

class EWWWIO_Async_Key_Verification extends WP_Async_Request {

	protected $action = 'ewwwio_async_key_verification';

	protected function handle() {
		session_write_close();
		ewww_image_optimizer_cloud_verify( false );
		ewww_image_optimizer_debug_log();
	}
}

global $ewwwio_async_key_verification;
$ewwwio_async_key_verification = new EWWWIO_Async_Key_Verification();

class EWWWIO_Test_Async_Handler extends WP_Async_Request {

	protected $action = 'ewwwio_test_optimize';

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
		if ( $item != '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b162932' ) {
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
