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
			ewwwio_debug_message( '<b>wp_image_editor_imagick(wpthumb)::' . __FUNCTION__ . '()</b>' );
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
			ewwwio_debug_message( '<b>wp_image_editor_imagick(bfi)::' . __FUNCTION__ . '()</b>' );
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
			ewwwio_debug_message( '<b>wp_image_editor_imagick(respimg)::' . __FUNCTION__ . '()</b>' );
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
			ewwwio_debug_message( '<b>wp_image_editor_imagick(s3uploads)::' . __FUNCTION__ . '()</b>' );
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
		 * Resizes current image.
		 * Wraps _resize, since _resize returns a GD Resource.
		 *
		 * At minimum, either a height or width must be provided.
		 * If one of the two is set to null, the resize will
		 * maintain aspect ratio according to the provided dimension.
		 *
		 * @since 4.6.0
		 *
		 * @param int|null $max_w Image width.
		 * @param int|null $max_h Image height.
		 * @param bool     $crop Optional. Scale by default, crop if true.
		 * @return true|WP_Error
		 */
		public function resize( $max_w, $max_h, $crop = false ) {
			ewwwio_debug_message( '<b>wp_image_editor_gd::' . __FUNCTION__ . '()</b>' );
			if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) ) {
				return true;
			}

			$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
			if ( ! $dims ) {
				return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ) );
			}
			list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

			$resized = $this->_resize( $dims, $crop );

			if ( is_string( $resized ) ) {
				$this->ewww_image = $resized;
				return $resized;
			} elseif ( is_wp_error( $resized ) ) {
				return $resized;
			}
			return $this->update_size( $dst_w, $dst_h );
		}

		/**
		 * Resizes current image.
		 *
		 * Uses gifsicle to preserve GIF animations.
		 *
		 * @since 4.6.0
		 *
		 * @param array $dims {
		 *     All parameters necessary for resizing.
		 *
		 *     @type int $dst_x X-coordinate of destination image (usually 0).
		 *     @type int $dst_y Y-coordinate of destination image (usually 0).
		 *     @type int $src_x X-coordinate of source image (usually 0 unless cropping).
		 *     @type int $src_y Y-coordinate of source image (usually 0 unless cropping).
		 *     @type int $dst_w Desired image width.
		 *     @type int $dst_h Desired image height.
		 *     @type int $src_w Source width.
		 *     @type int $src_h Source height.
		 * }
		 * @param bool  $crop Should we crop or should we scale.
		 * @return bool|WP_Error
		 */
		protected function _resize( $dims, $crop ) {
			ewwwio_debug_message( '<b>wp_image_editor_gd::' . __FUNCTION__ . '()</b>' );
			list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;
			if ( defined( 'EWWWIO_EDITOR_AGR' ) && ! EWWWIO_EDITOR_AGR ) {
				ewwwio_debug_message( 'AGR disabled' );
				if ( $crop ) {
					return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
				}
				return $this->thumbnail_image( $dst_w, $dst_h );
			}
			if ( defined( 'EWWWIO_EDITOR_BETTER_RESIZE' ) && ! EWWW_IO_EDITOR_BETTER_RESIZE ) {
				ewwwio_debug_message( 'API resize disabled' );
				if ( $crop ) {
					return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
				}
				return $this->thumbnail_image( $dst_w, $dst_h );
			}
			$return_parent = false; // An indicator for whether we should short-circuit and use the parent thumbnail_image method.
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$ewww_status = get_transient( 'ewww_image_optimizer_cloud_status' );
			if ( 'image/gif' === $this->mime_type && function_exists( 'ewww_image_optimizer_path_check' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
				ewww_image_optimizer_path_check( false, false, true, false, false, false );
			}
			if ( 'image/gif' === $this->mime_type && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) || ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) ) {
				if ( false === strpos( $ewww_status, 'great' ) ) {
					if ( ! ewww_image_optimizer_cloud_verify() ) {
						ewwwio_debug_message( 'no gifsicle or API to resize an animated GIF' );
						$return_parent = true;
					}
				}
			}
			if ( 'image/gif' !== $this->mime_type && false === strpos( $ewww_status, 'great' ) ) {
				if ( ! ewww_image_optimizer_cloud_verify() ) {
					ewwwio_debug_message( 'no API to resize the image' );
					$return_parent = true;
				}
			}
			if ( 'image/gif' !== $this->mime_type || ! ewww_image_optimizer_is_animated( $this->file ) ) {
				ewwwio_debug_message( 'not an animated GIF' );
				$return_parent = true;
			}
			// TODO: Possibly handle crop, rotate, and flip down the road.
			if ( ! empty( $this->modified ) ) {
				ewwwio_debug_message( 'GIF already altered, leave it alone' );
				$return_parent = true;
			}
			if ( ! $this->file || ewww_image_optimizer_stream_wrapped( $this->file ) || 0 === strpos( $this->file, 'http' ) || 0 === strpos( $this->file, 'ftp' ) || ! is_file( $this->file ) ) {
				ewwwio_debug_message( 'could not load original file, or remote path detected' );
				$return_parent = true;
			}
			if ( $return_parent ) {
				if ( $crop ) {
					return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
				}
				return $this->thumbnail_image( $dst_w, $dst_h );
			}
			$resize_result = ewww_image_optimizer_better_resize( $this->file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
			if ( is_wp_error( $resize_result ) ) {
				return $resize_result;
			}

			$imagick  = new Imagick();
			$new_size = array();
			try {
				$imagick->clear();
				$imagick->readImageBlob( $resize_result );
				if ( function_exists( 'getimagesizefromstring' ) ) {
					$gd_size_info = getimagesizefromstring( $resize_result );
				}
				if ( ! empty( $gd_size_info ) ) {
					$new_size['width']  = $gd_size_info[0];
					$new_size['height'] = $gd_size_info[1];
				} else {
					$new_size = $imagick->getImageGeometry();
				}
				ewwwio_debug_message( 'new size: ' . implode( 'x', $new_size ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'image_resize_error', $e->getMessage(), $this->file );
			}
			$this->image = $imagick;
			if ( empty( $new_size ) ) {
				return new WP_Error( 'image_resize_error', __( 'Image resize failed.' ) );
			}
			$this->update_size( $new_size['width'], $new_size['height'] );
			ewwwio_memory( __FUNCTION__ );
			return $resize_result;
		}

		/**
		 * Resize multiple images from a single source.
		 *
		 * @since 4.6.0
		 *
		 * @param array $sizes {
		 *     An array of image size arrays. Default sizes are 'small', 'medium', 'medium_large', 'large'.
		 *
		 *     Either a height or width must be provided.
		 *     If one of the two is set to null, the resize will
		 *     maintain aspect ratio according to the provided dimension.
		 *     Likewise, if crop is false, aspect ratio will also be preserved.
		 *
		 *     @type array $size {
		 *         Array of height, width values, and whether to crop.
		 *
		 *         @type int  $width  Image width. Optional if `$height` is specified.
		 *         @type int  $height Image height. Optional if `$width` is specified.
		 *         @type bool $crop   Optional. Whether to crop the image. Default false.
		 *     }
		 * }
		 * @return array An array of resized images' metadata by size.
		 */
		public function multi_resize( $sizes ) {
			ewwwio_debug_message( '<b>wp_image_editor_imagick::' . __FUNCTION__ . '()</b>' );
			$metadata   = array();
			$orig_size  = $this->size;
			$orig_image = $this->image->getImage();
			foreach ( $sizes as $size => $size_data ) {
				if ( ! $this->image ) {
					$this->image = $orig_image->getImage();
				}
				if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
					continue;
				}
				if ( ! isset( $size_data['width'] ) ) {
					$size_data['width'] = null;
				}
				if ( ! isset( $size_data['height'] ) ) {
					$size_data['height'] = null;
				}
				if ( ! isset( $size_data['crop'] ) ) {
					$size_data['crop'] = false;
				}

				$resize_result = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
				$duplicate     = ( ( $orig_size['width'] == $size_data['width'] ) && ( $orig_size['height'] == $size_data['height'] ) );

				if ( ! is_wp_error( $resize_result ) && ! $duplicate ) {
					if ( is_string( $resize_result ) ) {
						$resized = $this->_save_ewwwio_file( $resize_result );
						unset( $resize_result );
						// Note sure this bit is necessary, but just to be safe.
						$this->image->clear();
						$this->image->destroy();
						$this->image = null;
					} else {
						$resized = $this->_save( $this->image );
						$this->image->clear();
						$this->image->destroy();
						$this->image = null;
					}
					if ( ! is_wp_error( $resized ) && $resized ) {
						unset( $resized['path'] );
						$metadata[ $size ] = $resized;
					}
				}
				$this->size = $orig_size;
			}
			$this->image = $orig_image;
			return $metadata;
		}

		/**
		 * Crops Image.
		 *
		 * Currently does nothing more than call the parent and let us know the image is modified.
		 *
		 * @since 4.6.0
		 *
		 * @param int  $src_x The start x position to crop from.
		 * @param int  $src_y The start y position to crop from.
		 * @param int  $src_w The width to crop.
		 * @param int  $src_h The height to crop.
		 * @param int  $dst_w Optional. The destination width.
		 * @param int  $dst_h Optional. The destination height.
		 * @param bool $src_abs Optional. If the source crop points are absolute.
		 * @return bool|WP_Error
		 */
		public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
			$this->modified = 'crop';
			return parent::crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs );
		}

		/**
		 * Rotates current image counter-clockwise by $angle.
		 *
		 * Currently does nothing more than call the parent and let us know the image is modified.
		 *
		 * @since 4.6.0
		 *
		 * @param float $angle The number of degrees for rotation.
		 * @return true|WP_Error
		 */
		public function rotate( $angle ) {
			$this->modified = 'rotate';
			return parent::rotate( $angle );
		}

		/**
		 * Flips current image.
		 *
		 * Currently does nothing more than call the parent and let us know the image is modified.
		 *
		 * @since 4.6.0
		 *
		 * @param bool $horz Flip along Horizontal Axis.
		 * @param bool $vert Flip along Vertical Axis.
		 * @return true|WP_Error
		 */
		public function flip( $horz, $vert ) {
			$this->modified = 'flip';
			return parent::flip( $horz, $vert );
		}

		/**
		 * Saves a file from the EWWW IO API-enabled image editor.
		 *
		 * @since 4.6.0
		 *
		 * @param string $image A string containing the image contents.
		 * @param string $filename Optional. The name of the file to be saved to.
		 * @param string $mime_type Optional. The mimetype of the file.
		 * @return WP_Error|array The full path, base filename, and mimetype.
		 */
		protected function _save_ewwwio_file( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>wp_image_editor_imagick::' . __FUNCTION__ . '()</b>' );
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( wp_is_stream( $filename ) ) {
				$imagick = new Imagick();
				try {
					$imagick->readImageBlob( $image );
				} catch ( Exception $e ) {
					return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
				}
				unset( $this->ewww_image );
				return parent::_save( $imagick, $filename, $mime_type );
			}
			if ( ! is_string( $image ) ) {
				unset( $this->ewww_image );
				return parent::_save( $image, $filename, $mime_type );
			}
			global $ewww_preempt_editor;
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && is_file( $filename ) && empty( $ewww_preempt_editor ) ) {
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
			// Write out the image (substitute for $this->make_image()).
			wp_mkdir_p( dirname( $filename ) );
			$result = file_put_contents( $filename, $image );
			if ( $result ) {
				ewwwio_debug_message( "image editor (imagick-api-enhanced) saved: $filename" );
				if ( is_writable( $filename ) ) {
					$stat  = stat( dirname( $filename ) );
					$perms = $stat['mode'] & 0000666; // Same permissions as parent folder with executable bits stripped.
					chmod( $filename, $perms );
				}
				ewwwio_memory( __FUNCTION__ );
				return array(
					'path'      => $filename,
					'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
					'width'     => $this->size['width'],
					'height'    => $this->size['height'],
					'mime-type' => $mime_type,
				);
			}
			ewwwio_memory( __FUNCTION__ );
			return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
		}

		/**
		 * Saves a file from the image editor.
		 *
		 * @since 1.7.0
		 *
		 * @param resource $image An Imagick image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>wp_image_editor_imagick::' . __FUNCTION__ . '()</b>' );
			global $ewww_defer;
			global $ewww_preempt_editor;
			if ( ! empty( $this->ewww_image ) && empty( $this->modified ) ) {
				return $this->_save_ewwwio_file( $this->ewww_image, $filename, $mime_type );
			}
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
