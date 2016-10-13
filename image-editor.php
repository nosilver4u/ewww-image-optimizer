<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( class_exists( 'Bbpp_Animated_Gif' ) ) {
	class EWWWIO_GD_Editor extends Bbpp_Animated_Gif {
		public function save( $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) )
				ewww_image_optimizer_cloud_init();
			$saved = parent::save( $filename, $mimetype );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (AGR gd) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
		public function multi_resize( $sizes ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) )
				ewww_image_optimizer_cloud_init();
			$metadata = parent::multi_resize( $sizes );
			ewwwio_debug_message( 'image editor (AGR gd) multi resize' );
			ewwwio_debug_message( print_r( $metadata, true ) );
			ewwwio_debug_message( print_r( $this, true ) );
			$info = pathinfo( $this->file );
			$dir = $info['dirname'];
			if ( ewww_image_optimizer_iterable( $metadata ) ) {
				foreach ( $metadata as $size ) {
					$filename = trailingslashit( $dir ) . $size['file'];
					if ( file_exists( $filename ) ) {
				//		if ( ! ewww_image_optimizer_test_background_opt() ) {
							ewww_image_optimizer( $filename );
							ewwwio_debug_message( "image editor (AGR gd) saved: $filename" );
							$image_size = ewww_image_optimizer_filesize( $filename );
							ewwwio_debug_message( "image editor size: $image_size" );
				/*		} else {
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
						}*/
					}
				}
			}
			ewww_image_optimizer_debug_log();
			ewwwio_memory( __FUNCTION__ );
			return $metadata;
		}
	}
} elseif ( class_exists( 'WP_Thumb_Image_Editor_GD' ) ) {
	class EWWWIO_GD_Editor extends WP_Thumb_Image_Editor_GD {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) )
				ewww_image_optimizer_cloud_init();
			$saved = parent::_save( $image, $filename, $mime_type );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (wpthumb GD) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_GD' ) ) {
	class EWWWIO_GD_Editor extends BFI_Image_Editor_GD {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$saved = parent::_save( $image, $filename, $mime_type );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (BFI GD) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} else {
	class EWWWIO_GD_Editor extends WP_Image_Editor_GD {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) ) {
				ewww_image_optimizer_cloud_init();
			}
			$saved = parent::_save( $image, $filename, $mime_type );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (gd) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
}
if ( class_exists( 'WP_Thumb_Image_Editor_Imagick' ) ) {
	class EWWWIO_Imagick_Editor extends WP_Thumb_Image_Editor_Imagick {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD'))
				ewww_image_optimizer_cloud_init();
			$saved = parent::_save($image, $filename, $mime_type);
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (wpthumb imagick) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} elseif ( class_exists( 'BFI_Image_Editor_Imagick' ) ) {
	class EWWWIO_Imagick_Editor extends BFI_Image_Editor_Imagick {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) )
				ewww_image_optimizer_cloud_init();
			$saved = parent::_save( $image, $filename, $mime_type );
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (BFI imagick) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
} else {
	class EWWWIO_Imagick_Editor extends WP_Image_Editor_Imagick {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if (!defined('EWWW_IMAGE_OPTIMIZER_CLOUD'))
				ewww_image_optimizer_cloud_init();
			$saved = parent::_save($image, $filename, $mime_type);
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (imagick) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
}
if ( class_exists( 'WP_Image_Editor_Gmagick' ) ) {
	class EWWWIO_Gmagick_Editor extends WP_Image_Editor_Gmagick {
		protected function _save( $image, $filename = null, $mime_type = null ) {
			global $ewww_defer;
			if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) )
				ewww_image_optimizer_cloud_init();
			$saved = parent::_save($image, $filename, $mime_type);
			if ( ! is_wp_error( $saved ) ) {
				if ( ! $filename ) {
					$filename = $saved['path'];
				}
				if ( file_exists( $filename ) ) {
				//	if ( ! ewww_image_optimizer_test_background_opt() ) {
						ewww_image_optimizer( $filename );
						ewwwio_debug_message( "image editor (gmagick) saved: $filename" );
						$image_size = ewww_image_optimizer_filesize( $filename );
						ewwwio_debug_message( "image editor size: $image_size" );
				/*	} else {
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
					}*/
				}
				ewww_image_optimizer_debug_log();
			}
			ewwwio_memory( __FUNCTION__ );
			return $saved;
		}
	}
}
