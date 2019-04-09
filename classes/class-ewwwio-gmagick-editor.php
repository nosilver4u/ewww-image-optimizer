<?php
/**
 * Class and methods to integrate with the WP_Image_Editor_Gmagick class and other extensions.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'WP_Image_Editor_Gmagick' ) ) {
	/**
	 * Extension of the WP_Image_Editor_Gmagick class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_Gmagick
	 */
	class EWWWIO_Gmagick_Editor extends WP_Image_Editor_Gmagick {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image A Gmagick image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			global $ewww_preempt_editor;
			if ( ! empty( $ewww_preempt_editor ) ) {
				return parent::_save( $image, $filename, $mime_type );
			}
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && is_file( $filename ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = getimagesize( $filename );
				if ( $current_size && (int) $this->size['width'] === (int) $current_size[0] && (int) $this->size['height'] === (int) $current_size[1] ) {
					ewwwio_debug_message( "existing file has same dimensions, not saving $filename" );
					return array(
						'path'      => $filename,
						'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
						'width'     => $this->size['width'],
						'height'    => $this->size['height'],
						'mime-type' => $mime_type,
					);
				}
			}
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$saved = parent::_save( $image, $filename, $mime_type );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
					/* if ( ! ewww_image_optimizer_test_background_opt() ) { */
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (gmagick) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );

					/*
					} else {
						add_filter( 'http_headers_useragent', 'ewww_image_optimizer_cloud_useragent', PHP_INT_MAX );
						global $ewwwio_image_background;
						if ( ! class_exists( 'WP_Background_Process' ) ) {
							require_once( EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'background.php' );
						}
						if ( ! is_object( $ewwwio_image_background ) ) {
							$ewwwio_image_background = new EWWWIO_Image_Background_Process();
						}
						$ewwwio_image_background->push_to_queue( $filename );
						$ewwwio_image_background->save()->dispatch();
						ewwwio_debug_message( "image editor (gmagick) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} // End if().
