<?php
/**
 * Class and methods to integrate with the WP_Image_Editor_GD class and other extensions.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'Bbpp_Animated_Gif' ) ) {
	// Do nothing, they should just get rid of that unsupported plugin.
} elseif ( class_exists( 'WP_Thumb_Image_Editor_GD' ) ) {
	/**
	 * Extension of the WP_Thumb_Image_Editor_GD class to auto-compress edited images.
	 * The parent class is from the WPThumb library, developed at Human Made, but no longer maintained.
	 *
	 * @see WP_Image_Editor_GD
	 */
	class EWWWIO_GD_Editor extends WP_Thumb_Image_Editor_GD {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image A GD image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>(wpthumb)' . __METHOD__ . '()</b>' );
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
					ewwwio_debug_message( "image editor (wpthumb GD) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __METHOD__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_GD' ) ) {
	/**
	 * Extension of the BFI_Image_Editor_GD class to auto-compress edited images.
	 * Also no longer maintained, if you're using this, update your code!.
	 *
	 * @see WP_Image_Editor_GD
	 */
	class EWWWIO_GD_Editor extends BFI_Image_Editor_GD {
		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image A GD image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>(bfi)::' . __METHOD__ . '()</b>' );
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
					ewwwio_debug_message( "image editor (BFI GD) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __METHOD__ );
			return $saved;
		}
	}
} else {
	/**
	 * Extension of the WP_Image_Editor_GD class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_GD
	 */
	class EWWWIO_GD_Editor extends WP_Image_Editor_GD {

		/**
		 * Resizes current image.
		 *
		 * Requires width or height, crop is optional. Uses gifsicle to preserve GIF animations.
		 * Also use API in future for better quality resizing.
		 *
		 * @since 4.4.0
		 *
		 * @param int|null $max_w Image width.
		 * @param int|null $max_h Image height.
		 * @param bool     $crop Optional. Scale by default, crop if true.
		 * @return bool|WP_Error
		 */
		protected function _resize( $max_w, $max_h, $crop = false ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
			if ( ! $dims ) {
				return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ), $this->file );
			}
			list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;
			ewwwio_debug_message( "dst_x $dst_x, dst_y $dst_y, src_x $src_x, src_y $src_y, dst_w $dst_w, dst_h $dst_h, src_w $src_w, src_h $src_h" );

			if ( defined( 'EWWWIO_EDITOR_AGR' ) && ! EWWWIO_EDITOR_AGR ) {
				ewwwio_debug_message( 'AGR disabled' );
				return parent::_resize( $max_w, $max_h, $crop );
			}
			if ( defined( 'EWWWIO_EDITOR_BETTER_RESIZE' ) && ! EWWW_IO_EDITOR_BETTER_RESIZE ) {
				ewwwio_debug_message( 'API resize disabled' );
				return parent::_resize( $max_w, $max_h, $crop );
			}
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
						return parent::_resize( $max_w, $max_h, $crop );
					}
				}
			}
			if ( 'image/gif' !== $this->mime_type && false === strpos( $ewww_status, 'great' ) ) {
				if ( ! ewww_image_optimizer_cloud_verify( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) ) {
					ewwwio_debug_message( 'no API to resize the image' );
					return parent::_resize( $max_w, $max_h, $crop );
				}
			}
			if ( 'image/gif' !== $this->mime_type || ! ewww_image_optimizer_is_animated( $this->file ) ) {
				ewwwio_debug_message( 'not an animated GIF' );
				return parent::_resize( $max_w, $max_h, $crop );
			}
			// TODO: Possibly handle crop, rotate, and flip down the road.
			if ( ! empty( $this->modified ) ) {
				ewwwio_debug_message( 'GIF already altered! Too late, so leave it alone...' );
				return parent::_resize( $max_w, $max_h, $crop );
			}
			if ( ! $this->file || ewww_image_optimizer_stream_wrapped( $this->file ) || 0 === strpos( $this->file, 'http' ) || 0 === strpos( $this->file, 'ftp' ) || ! ewwwio_is_file( $this->file ) ) {
				ewwwio_debug_message( 'could not load original file, or remote path detected' );
				return parent::_resize( $max_w, $max_h, $crop );
			}
			$resize_result = ewww_image_optimizer_better_resize( $this->file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
			if ( is_wp_error( $resize_result ) ) {
				return $resize_result;
			}

			$new_size = getimagesizefromstring( $resize_result );
			if ( empty( $new_size ) ) {
				return new WP_Error( 'image_resize_error', __( 'Image resize failed.' ) );
			}
			$this->update_size( $new_size[0], $new_size[1] );
			ewwwio_memory( __METHOD__ );
			return $resize_result;
		}

		/**
		 * Resizes current image.
		 * Wraps _resize, since _resize returns a GD Resource.
		 *
		 * At minimum, either a height or width must be provided.
		 * If one of the two is set to null, the resize will
		 * maintain aspect ratio according to the provided dimension.
		 *
		 * @since 4.4.0
		 *
		 * @param int|null $max_w Image width.
		 * @param int|null $max_h Image height.
		 * @param bool     $crop Optional. Scale by default, crop if true.
		 * @return true|WP_Error
		 */
		public function resize( $max_w, $max_h, $crop = false ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( (int) $this->size['width'] === (int) $max_w && (int) $this->size['height'] === (int) $max_h ) {
				return true;
			}

			$resized = $this->_resize( $max_w, $max_h, $crop );

			if ( is_resource( $resized ) || ( is_object( $resized ) && $resized instanceof GdImage ) ) {
				imagedestroy( $this->image );
				$this->image = $resized;
				return true;
			} elseif ( is_string( $resized ) ) {
				$this->ewww_image = $resized;
				imagedestroy( $this->image );
				$this->image = @imagecreatefromstring( $resized );
				return true;
			} elseif ( is_wp_error( $resized ) ) {
				return $resized;
			}
			return new WP_Error( 'image_resize_error', __( 'Image resize failed.' ), $this->file );
		}

		/**
		 * Create an image sub-size and return the image meta data value for it.
		 *
		 * @since 5.3.0
		 *
		 * @param array $size_data {
		 *     Array of size data.
		 *
		 *     @type int  $width  The maximum width in pixels.
		 *     @type int  $height The maximum height in pixels.
		 *     @type bool $crop   Whether to crop the image to exact dimensions.
		 * }
		 * @return array|WP_Error The image data array for inclusion in the `sizes` array in the image meta,
		 *                        WP_Error object on error.
		 */
		public function make_subsize( $size_data ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
			}

			$orig_size = $this->size;

			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}

			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}

			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$resized = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

			if ( is_string( $resized ) ) {
				$this->ewww_image = $resized;
			}
			if ( is_wp_error( $resized ) ) {
				$saved = $resized;
			} else {
				$saved = $this->_save( $resized );
			}

			$this->size = $orig_size;

			if ( ! is_wp_error( $saved ) ) {
				unset( $saved['path'] );
			}

			return $saved;
		}

		/**
		 * Resize multiple images from a single source.
		 *
		 * @since 4.4.0
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
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			global $wp_version;
			if ( version_compare( $wp_version, '5.3' ) >= 0 ) {
				return parent::multi_resize( $sizes );
			}
			$metadata  = array();
			$orig_size = $this->size;
			foreach ( $sizes as $size => $size_data ) {
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

				$image     = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );
				$duplicate = ( (int) $orig_size['width'] === (int) $size_data['width'] && (int) $orig_size['height'] === (int) $size_data['height'] );

				if ( ! is_wp_error( $image ) && ! $duplicate ) {
					if ( is_resource( $image ) || ( is_object( $image ) && $image instanceof GdImage ) ) {
						$resized = $this->_save( $image );
						imagedestroy( $image );
					} elseif ( is_string( $image ) ) {
						$resized = $this->_save_ewwwio_file( $image );
						unset( $image );
					}
					if ( ! is_wp_error( $resized ) && $resized ) {
						unset( $resized['path'] );
						$metadata[ $size ] = $resized;
					}
				}

				$this->size = $orig_size;
			}
			return $metadata;
		}

		/**
		 * Crops Image.
		 *
		 * Currently does nothing more than call the parent and let us know the image is modified.
		 *
		 * @since 4.4.0
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
		 * @since 4.4.0
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
		 * @since 4.4.0
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
		 * @since 4.4.0
		 *
		 * @param string $image A string containing the image contents.
		 * @param string $filename Optional. The name of the file to be saved to.
		 * @param string $mime_type Optional. The mimetype of the file.
		 * @return WP_Error|array The full path, base filename, and mimetype.
		 */
		protected function _save_ewwwio_file( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );
			if ( ! $filename ) {
				$filename = $this->generate_filename( null, null, $extension );
			}
			if ( wp_is_stream( $filename ) ) {
				$image = @imagecreatefromstring( $image );
				unset( $this->ewww_image );
				return parent::_save( $image, $filename, $mime_type );
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
				ewwwio_debug_message( "image editor (gd-api-enhanced) saved: $filename" );
				if ( is_writable( $filename ) ) {
					$stat  = stat( dirname( $filename ) );
					$perms = $stat['mode'] & 0000666; // Same permissions as parent folder with executable bits stripped.
					ewwwio_chmod( $filename, $perms );
				}
				ewwwio_memory( __METHOD__ );
				return array(
					'path'      => $filename,
					'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
					'width'     => $this->size['width'],
					'height'    => $this->size['height'],
					'mime-type' => $mime_type,
				);
			}
			ewwwio_memory( __METHOD__ );
			return new WP_Error( 'image_save_error', __( 'Image Editor Save Failed' ) );
		}

		/**
		 * Saves a file from the image editor.
		 *
		 * @since 1.7.0
		 *
		 * @param resource $image A GD image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error|array The full path, base filename, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
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
					ewwwio_debug_message( "image editor (gd) saved: $filename" );
					$image_size = ewww_image_optimizer_filesize( $filename );
					ewwwio_debug_message( "image editor size: $image_size" );
				}
			}
			ewwwio_memory( __METHOD__ );
			return $saved;
		}
	}
} // End if().
