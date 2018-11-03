<?php
/**
 * Class and methods to integrate with the WP_Image_Editor_Imagick class and other extensions.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'WP_Thumb_Image_Editor_Imagick' ) ) {
	/**
	 * Extension of the WP_Image_Editor_Imagick class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_Imagick
	 */
	class EWWWIO_Imagick_Editor extends WP_Thumb_Image_Editor_Imagick {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image An Imagick image object.
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
				if ( $current_size && $this->size['width'] == $current_size[0] && $this->size['height'] == $current_size[1] ) {
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
						ewwwio_debug_message( "image editor (wpthumb imagick) saved: $filename" );
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
						ewwwio_debug_message( "image editor (wpthumb imagick) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_Imagick' ) ) {
	/**
	 * Extension of the WP_Image_Editor_Imagick class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_Imagick
	 */
	class EWWWIO_Imagick_Editor extends BFI_Image_Editor_Imagick {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image An Imagick image object.
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
				if ( $current_size && $this->size['width'] == $current_size[0] && $this->size['height'] == $current_size[1] ) {
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
						ewwwio_debug_message( "image editor (BFI imagick) saved: $filename" );
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
						ewwwio_debug_message( "image editor (BFI imagick) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'WP_Image_Editor_Respimg' ) ) {
	/**
	 * Extension of the WP_Image_Editor_Respimg class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_Respimg
	 */
	class EWWWIO_Imagick_Editor extends WP_Image_Editor_Respimg {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image An Imagick image object.
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
				if ( $current_size && $this->size['width'] == $current_size[0] && $this->size['height'] == $current_size[1] ) {
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
					ewww_image_optimizer( $filename );
					ewwwio_debug_message( "image editor (resp imagick) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'S3_Uploads_Image_Editor_Imagick' ) ) {
	/**
	 * Extension of the WP_Image_Editor_Imagick class to auto-compress edited S3 images.
	 *
	 * We extend the WP class directly, because extending the S3 class would be too late to work.
	 * So we pretty much have to duplicate the S3 Uploads class with the IO function thrown in
	 * the middle: https://github.com/humanmade/S3-Uploads/blob/master/inc/class-s3-uploads-image-editor-imagick.php
	 *
	 * @see WP_Image_Editor_Imagick
	 */
	class EWWWIO_Imagick_Editor extends WP_Image_Editor_Imagick {

		/**
		 * A temp file created during pdf_setup().
		 *
		 * @since 4.4.0
		 * @var string $temp_file_to_cleanup
		 */
		protected $temp_file_to_cleanup = null;

		/**
		 * Saves a file from the image editor and sends it to S3 after optimization.
		 *
		 * @param resource $image An Imagick image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_preempt_editor;
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			global $s3_uploads_image;
			$s3_uploads_image = $filename;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$upload_dir = wp_upload_dir();

			$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );

			$saved = parent::_save( $image, $temp_filename, $mime_type );

			if ( is_wp_error( $saved ) ) {
				unlink( $temp_filename );
				unset( $s3_uploads_image );
				return $saved;
			}
			if ( is_file( $saved['path'] ) && empty( $ewww_preempt_editor ) ) {
				$temp_filename = $saved['path'];
				ewww_image_optimizer( $temp_filename );
				ewwwio_debug_message( "image editor (s3 uploads) saved: $temp_filename" );
				$image_size = ewww_image_optimizer_filesize( $temp_filename );
				ewwwio_debug_message( "image editor size: $image_size" );
			}
			$copy_result = copy( $saved['path'], $filename );
			if ( is_file( $saved['path'] ) ) {
				unlink( $saved['path'] );
			}
			if ( is_file( $temp_filename ) ) {
				unlink( $temp_filename );
			}
			if ( ! $copy_result ) {
				unset( $s3_uploads_image );
				return new WP_Error( 'unable-to-copy-to-s3', 'Unable to copy the temp image to S3' );
			}
			$saved['path'] = $filename;
			$saved['file'] = wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) );
			unset( $s3_uploads_image );
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}

		/**
		 * Custom loader for S3 Uploads.
		 *
		 * @since 4.4.0
		 *
		 * @return bool|WP_Error True on success, WP_Error on failure.
		 */
		public function load() {
			$result = parent::load();

			// `load` can call pdf_setup() which has to copy the file to a temp local copy.
			// In this event we want to clean it up once `load` has been completed.
			if ( $this->temp_file_to_cleanup ) {
				unlink( $this->temp_file_to_cleanup );
				$this->temp_file_to_cleanup = null;
			}
			return $result;
		}

		/**
		 * Sets up Imagick for PDF processing.
		 * Increases rendering DPI and only loads first page.
		 *
		 * @since 4.4.0
		 *
		 * @return string|WP_Error File to load or WP_Error on failure.
		 */
		protected function pdf_setup() {
			$temp_filename              = tempnam( get_temp_dir(), 's3-uploads' );
			$this->temp_file_to_cleanup = $temp_filename;
			copy( $this->file, $temp_filename );

			try {
				// By default, PDFs are rendered in a very low resolution.
				// We want the thumbnail to be readable, so increase the rendering DPI.
				$this->image->setResolution( 128, 128 );

				// Only load the first page.
				return $temp_filename . '[0]';
			} catch ( Exception $e ) {
				return new WP_Error( 'pdf_setup_failed', $e->getMessage(), $this->file );
			}
		}
	}
} else {
	/**
	 * Extension of the WP_Image_Editor_Imagick class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_Imagick
	 */
	class EWWWIO_Imagick_Editor extends WP_Image_Editor_Imagick {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image An Imagick image object.
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
				if ( $current_size && $this->size['width'] == $current_size[0] && $this->size['height'] == $current_size[1] ) {
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
						ewwwio_debug_message( "image editor (imagick) saved: $filename" );
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
						ewwwio_debug_message( "image editor (imagick) queued: $filename" );
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
