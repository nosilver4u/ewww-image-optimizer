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
	/**
	 * Extension of the WP_Image_Editor_GD class to auto-compress edited images.
	 *
	 * @see WP_Image_Editor_GD
	 */
	class EWWWIO_GD_Editor extends Bbpp_Animated_Gif {

		/**
		 * Saves a file from the image editor.
		 *
		 * @param resource $image A GD image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
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
						'path' => $filename,
						'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
						'width' => $this->size['width'],
						'height' => $this->size['height'],
						'mime-type' => $mime_type,
					);
				}
			}
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$saved = parent::_save( $image, $filename, $mimetype );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
					/* if ( ! ewww_image_optimizer_test_background_opt() ) { */
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (AGR gd) saved: $filename" );
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
						ewwwio_debug_message( "image editor (AGR gd) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
		/**
		 * Resize multiple images from a single source.
		 *
		 * @param array $sizes An array of image size arrays. Default sizes are 'small', 'medium', 'medium_large', 'large'.
		 * @return array An array of resized images' metadata by size.
		 */
		public function multi_resize( $sizes ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$metadata = parent::multi_resize( $sizes );
			ewwwio_debug_message( 'image editor (AGR gd) multi resize' );
			if ( ewww_image_optimizer_function_exists( 'print_r' ) ) {
				ewwwio_debug_message( print_r( $metadata, true ) );
				ewwwio_debug_message( print_r( $this, true ) );
			}
			$info = pathinfo( $this->file );
			$dir = $info['dirname'];
			if ( ewww_image_optimizer_iterable( $metadata ) ) {
				foreach ( $metadata as $size ) {
					$filename = trailingslashit( $dir ) . $size['file'];
					if ( file_exists( $filename ) ) {
						/* if ( ! ewww_image_optimizer_test_background_opt() ) {*/
							ewww_image_optimizer( $filename );
							ewwwio_debug_message( "image editor (AGR gd) saved: $filename" );
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
							ewwwio_debug_message( "image editor (AGR gd) queued: $filename" );
						}
						*/
					}
				}
			}
			ewww_image_optimizer_debug_log();
			ewwwio_memory( __FUNCTION__ );
			return $metadata;
		}
	}
} elseif ( class_exists( 'WP_Thumb_Image_Editor_GD' ) ) {
	/**
	 * Extension of the WP_Image_Editor_GD class to auto-compress edited images.
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
			global $ewww_defer;
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
						'path' => $filename,
						'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
						'width' => $this->size['width'],
						'height' => $this->size['height'],
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
						ewwwio_debug_message( "image editor (wpthumb GD) saved: $filename" );
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
						ewwwio_debug_message( "image editor (wpthumb GD) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_GD' ) ) {
	/**
	 * Extension of the WP_Image_Editor_GD class to auto-compress edited images.
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
			global $ewww_defer;
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
						'path' => $filename,
						'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
						'width' => $this->size['width'],
						'height' => $this->size['height'],
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
						ewwwio_debug_message( "image editor (BFI GD) saved: $filename" );
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
						ewwwio_debug_message( "image editor (BFI GD) queued: $filename" );
					}
					*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
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
		 * Saves a file from the image editor.
		 *
		 * @param resource $image A GD image object.
		 * @param string   $filename Optional. The name of the file to be saved to.
		 * @param string   $mime_type Optional. The mimetype of the file.
		 * @return WP_Error| array The full path, base filename, width, height, and mimetype.
		 */
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
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
						'path' => $filename,
						'file' => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
						'width' => $this->size['width'],
						'height' => $this->size['height'],
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
						ewwwio_debug_message( "image editor (gd) saved: $filename" );
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
						ewwwio_debug_message( "image editor (gd) queued: $filename" );
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
