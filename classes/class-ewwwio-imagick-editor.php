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
	 * Extension of the WP_Thumb_Image_Editor_Imagick class to auto-compress edited images.
	 * Note that WPThumb is no longer maintained and you should update your code.
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
			if ( ! empty( $ewww_preempt_editor ) || ! defined( 'EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR ) {
				return parent::_save( $image, $filename, $mime_type );
			}
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && ewwwio_is_file( $filename ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = wp_getimagesize( $filename );
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
					ewww_image_optimizer( $filename );
					ewwwio_debug_message( "image editor (wpthumb imagick) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_Imagick' ) ) {
	/**
	 * Extension of the BFI_Image_Editor_Imagick class to auto-compress edited images.
	 * The BFI project is no longer maintained, stop using it and update your code!
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
			if ( ! empty( $ewww_preempt_editor ) || ! defined( 'EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR ) {
				return parent::_save( $image, $filename, $mime_type );
			}
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && ewwwio_is_file( $filename ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = wp_getimagesize( $filename );
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
					ewww_image_optimizer( $filename );
					ewwwio_debug_message( "image editor (BFI imagick) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
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
			if ( ! empty( $ewww_preempt_editor ) || ! defined( 'EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR ) {
				return parent::_save( $image, $filename, $mime_type );
			}
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && ewwwio_is_file( $filename ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = wp_getimagesize( $filename );
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
					ewww_image_optimizer( $filename );
					ewwwio_debug_message( "image editor (resp imagick) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
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
			ewwwio_debug_message( '<b>wp_image_editor_imagick::' . __FUNCTION__ . '()</b>' );
			if ( (int) $this->size['width'] === (int) $max_w && (int) $this->size['height'] === (int) $max_h ) {
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
			ewwwio_debug_message( '<b>wp_image_editor_imagick::' . __FUNCTION__ . '()</b>' );
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
				ewww_image_optimizer_path_check( false, false, true, false, false, false, false );
			}
			if ( 'image/gif' === $this->mime_type && ( ! defined( 'EWWW_IMAGE_OPTIMIZER_GIFSICLE' ) || ! EWWW_IMAGE_OPTIMIZER_GIFSICLE ) ) {
				if ( false === strpos( $ewww_status, 'great' ) ) {
					if ( ! ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) {
						ewwwio_debug_message( 'no gifsicle or API to resize an animated GIF' );
						$return_parent = true;
					}
				}
			}
			if ( 'image/gif' !== $this->mime_type && false === strpos( $ewww_status, 'great' ) ) {
				if ( ! ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) {
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
				ewwwio_debug_message( 'image already altered, leave it alone' );
				$return_parent = true;
			}
			if ( ! $this->file || ewww_image_optimizer_stream_wrapped( $this->file ) || 0 === strpos( $this->file, 'http' ) || 0 === strpos( $this->file, 'ftp' ) || ! ewwwio_is_file( $this->file ) ) {
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
				$duplicate     = ( (int) $orig_size['width'] === (int) $size_data['width'] && (int) $orig_size['height'] === (int) $size_data['height'] );

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
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && ewwwio_is_file( $filename ) && empty( $ewww_preempt_editor ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = wp_getimagesize( $filename );
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
			// Write out the image (substitute for $this->make_image()).
			wp_mkdir_p( dirname( $filename ) );
			$result = file_put_contents( $filename, $image );
			if ( $result ) {
				ewwwio_debug_message( "image editor (imagick-api-enhanced) saved: $filename" );
				if ( is_writable( $filename ) ) {
					$stat  = stat( dirname( $filename ) );
					$perms = $stat['mode'] & 0000666; // Same permissions as parent folder with executable bits stripped.
					ewwwio_chmod( $filename, $perms );
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
			if ( ! empty( $ewww_preempt_editor ) || ! defined( 'EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR' ) || ! EWWW_IMAGE_OPTIMIZER_ENABLE_EDITOR ) {
				return parent::_save( $image, $filename, $mime_type );
			}
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( ( ! defined( 'EWWWIO_EDITOR_OVERWRITE' ) || ! EWWWIO_EDITOR_OVERWRITE ) && ewwwio_is_file( $filename ) ) {
				ewwwio_debug_message( "detected existing file: $filename" );
				$current_size = wp_getimagesize( $filename );
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
					ewww_image_optimizer( $filename );
					ewwwio_debug_message( "image editor (imagick) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} // End if().
