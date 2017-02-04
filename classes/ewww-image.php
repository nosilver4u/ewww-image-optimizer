<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EWWW_Image {

	public $id = 0;
	public $attachment_id = null;
	public $file = '';
	public $converted = false;
//	public $original = null;
	public $orig_size = 0;
	public $opt_size = 0;
	public $resize = null;
	public $gallery = '';
	public $increment = false; // for renaming converted files
	public $url = ''; // the url to the image
	public $level = 0; // compression level: none, lossless, lossy, etc.

	function __construct( $id = 0, $gallery = '', $path = '' ) {
		if ( ! is_numeric( $id ) ) {
			$id = 0;
		}
		if ( ! is_string( $path ) ) {
			$path = '';
		}
		if ( ! is_string( $gallery ) ) {
			$gallery = '';
		}
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$ewwwdb->flush();
		if ( $path && is_file( $path ) ) {
			ewwwio_debug_message( "creating EWWW_Image with $path" );
			$new_image = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! $new_image ) {
				$this->file = $path;
				$this->orig_size = filesize( $path );
				$this->gallery = $gallery;
				if ( $id ) {
					$this->attachment_id = $id;
				}
				return;
			} elseif ( is_array( $new_image ) ) {
				if ( $id && empty( $new_image['attachment_id'] ) ) {
					$new_image['attachment_id'] = $id;
				}
				if ( $gallery && empty( $new_image['gallery'] ) ) {
					$new_image['gallery'] = $gallery;
				}
			}
		} elseif ( $path ) { // if $path is supplied but is not a file, then bail
			ewwwio_debug_message( "could not create EWWW_Image with $path, not a file" );
			return;
		} elseif ( $id && $gallery ) {
			ewwwio_debug_message( "looking for $gallery image $id" );
			// matches $id, $gallery, is 'full', and pending
			$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery' AND resize = 'full' AND pending = 1 LIMIT 1", ARRAY_A );
			if ( empty( $new_image ) ) {
				// matches $id, $gallery and pending
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery' AND pending = 1 LIMIT 1", ARRAY_A );
			}
			if ( empty( $new_image ) ) {
				// matches $gallery, is 'full' and pending
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE gallery = '$gallery' AND resize = 'full' AND pending = 1 LIMIT 1", ARRAY_A );
			}
			if ( empty( $new_image ) ) {
				// pull a random image
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE pending = 1 LIMIT 1", ARRAY_A );
			}
		} else {
			ewwwio_debug_message( "no id or path, just pulling next image" );
			$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE pending = 1 LIMIT 1", ARRAY_A );
		}
		
		if ( empty( $new_image ) ) {
			ewwwio_debug_message( 'failed to find a pending image with the parameters supplied' );
			return;
		}
		ewwwio_debug_message( print_r( $new_image, true ) );
		$this->id 		= $new_image['id'];
		$this->file		= $new_image['path'];
		$this->attachment_id 	= $new_image['attachment_id'];
		$this->opt_size		= $new_image['image_size'];
		$this->orig_size	= $new_image['orig_size'];
		$this->resize		= $new_image['resize'];
		$this->converted	= $new_image['converted'];
		$this->gallery		= ( empty( $gallery ) ? $new_image['gallery'] : $gallery );
	}

	public function update_converted_attachment( $meta ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$this->url = wp_get_attachment_url( $this->attachment_id );
		ewwwio_debug_message( print_r( $this, true ) );
		// update the file location in the post metadata based on the new path stored in the attachment metadata
		update_attached_file( $this->attachment_id, $meta['file'] );
		$this->replace_url();
		
		// if the new image is a JPG
		if ( preg_match( '/.jpg$/i', $meta['file'] ) ) {
			// set the mimetype to JPG
			$mime = 'image/jpeg';
		}
		// if the new image is a PNG
		if ( preg_match( '/.png$/i', $meta['file'] ) ) {
			// set the mimetype to PNG
			$mime = 'image/png';
		}
		if ( preg_match( '/.gif$/i', $meta['file'] ) ) {
			// set the mimetype to GIF
			$mime = 'image/gif';
		}
		// update the attachment post with the new mimetype and id
		wp_update_post( array(
			'ID' => $this->attachment_id,
			'post_mime_type' => $mime 
			)
		);
	}

	public function convert_sizes( $meta ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$sizes_queried = $ewwwdb->get_results( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $this->attachment_id AND resize <> 'full' AND resize <> ''", ARRAY_A );
//		ewwwio_debug_message( 'found some images in the db: ' . count( $sizes_queried ) );
		$sizes = array();
		if ( 'ims_image' == get_post_type( $this->attachment_id ) ) {
			$base_dir = trailingslashit( dirname( $this->file ) ) . '_resized/';
		} else {
			$base_dir = trailingslashit( dirname( $this->file ) );
		}
//		ewwwio_debug_message( 'about to process db results' );
		foreach ( $sizes_queried as $size_queried ) {
			$sizes[ $size_queried['resize'] ] = $size_queried;
			// convert here
			$new_name = $this->convert( $size_queried['path'] );
			if ( $new_name ) {
				$this->convert_retina( $size_queried['path'] );
				$this->convert_db_path( $size_queried['path'], $new_name, $size_queried['id'] );
		//		ewwwio_debug_message( print_r( $meta['sizes'], true ) );
		//		ewwwio_debug_message( print_r( $size_queried, true ) );
				if ( ewww_image_optimizer_iterable( $meta['sizes'] ) && is_array( $meta['sizes'][ $size_queried['resize'] ] ) ) {
					ewwwio_debug_message( 'updating regular size' );
					$meta['sizes'][ $size_queried['resize'] ]['file'] = basename( $new_name );
					$meta['sizes'][ $size_queried['resize'] ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
				} elseif ( ewww_image_optimizer_iterable( $meta['custom_sizes'] ) && $dimensions = str_replace( 'custom-size-', '', $size_queried['resize'] ) && is_array( $meta['custom_sizes'][ $dimensions ] ) ) {
					ewwwio_debug_message( 'updating custom size' );
					$meta['custom_sizes'][ $dimensions ]['file'] = basename( $new_name );
				}
			}
			ewwwio_debug_message( "converted {$size_queried['resize']} from db query" );
		//	ewwwio_debug_message( print_r( $meta, true ) );

		}
//			ewwwio_debug_message( print_r( $meta, true ) );
//		ewwwio_debug_message( 'next up for conversion search: meta' );
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			$disabled_sizes = ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_resizes_opt' );
			foreach ( $meta['sizes'] as $size => $data ) {
				//ewwwio_debug_message( "checking to see if we should convert $size" );
				if ( strpos( $size, 'webp' ) === 0 ) {
				//	ewwwio_debug_message( 'skipping webp' );
					continue;
				}
				//skip sizes that were already in ewwwio_images
				if ( isset( $sizes[ $size ] ) ) {
				//	ewwwio_debug_message( 'skipping size that was in db results' );
					continue;
				}
				if ( ! empty( $disabled_sizes[ $size ] ) ) {
				//	ewwwio_debug_message( 'skipping disabled size' );
					continue;
				}
				if ( empty( $data['file'] ) ) {
				//	ewwwio_debug_message( 'skipping size with missing filename' );
					continue;
				}
				foreach ( $sizes as $done ) {
					if ( empty( $done['height'] ) || empty( $done['width'] ) ) {
						continue;
					}
					if ( $data['height'] == $done['height'] && $data['width'] == $done['width'] ) {
						continue( 2 );
					}
				}
				$sizes[ $size ] = $data;
				// convert here
				$new_name = $this->convert( $base_dir . $data['file'] );
				if ( $new_name ) {
					$this->convert_retina( $base_dir . $data['file'] );
					$this->convert_db_path( $base_dir . $data['file'], $new_name );
					$meta['sizes'][ $size ]['file'] = basename( $new_name );
					$meta['sizes'][ $size ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
				}
				ewwwio_debug_message( "converted $size from meta" );
			}
		}
		//ewwwio_debug_message( 'next up for conversion search: image_meta resizes' );
		// convert sizes from a custom theme
		if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
			$imagemeta_resize_pathinfo = pathinfo( $this->file );
			$imagemeta_resize_path = '';
			foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
				if ( isset( $sizes[ 'resized-images-' . $index ] ) ) {
					continue;
				}
				$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
				$new_name = $this->convert( $imagemeta_resize_path );
				if ( $new_name ) {
					$this->convert_retina( $imagemeta_resize_path );
					$this->convert_db_path( $imagemeta_resize_path, $new_name );
				}
			}		
		}

		//ewwwio_debug_message( 'next up for conversion search: custom_sizes' );
		// and another custom theme
		if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
			$custom_sizes_pathinfo = pathinfo( $file_path );
			$custom_size_path = '';
			foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
				if ( isset( $sizes[ 'custom-size-' . $dimensions ] ) ) {
					continue;
				}
				$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
				$new_name = $this->convert( $custom_size_path );
				if ( $new_name ) {
					$this->convert_retina( $custom_size_path );
					$this->convert_db_path( $custom_size_path, $new_name );
					$meta['custom_sizes'][ $dimensions ]['file'] = basename( $new_name );
				}
			}
		}
			//ewwwio_debug_message( print_r( $meta, true ) );

//		ewwwio_debug_message( 'all done converting sizes' );
		return $meta;
	}

	public function restore_with_meta( $meta ) {
		if ( empty( $meta) || ! is_array( $meta ) ) {
			ewwwio_debug_message( 'invalid meta for restoration' );
			return $meta;
		}
		if ( ! $this->file || ! is_file( $this->file ) || ! $this->converted || ! is_file( $this->converted ) ) {
			ewwwio_debug_message( 'one of the files was not set for restoration (or did not exist)' );
			return $meta;
		}
		$this->restore_db_path( $this->file, $this->converted, $this->id );
		$converted_path = $this->file;
		unlink( $this->file );
		$this->file = $this->converted;
		$this->converted = $converted_path;
		$meta['file'] = trailingslashit( dirname( $meta['file'] ) ) . basename( $this->file );
		$this->update_converted_attachment( $meta );
		$meta = $this->restore_sizes( $meta );
		return $meta;
	}

	private function restore_sizes( $meta ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$sizes_queried = $ewwwdb->get_results( "SELECT id,path,converted,resize FROM $ewwwdb->ewwwio_images WHERE attachment_id = $this->attachment_id AND resize <> 'full'", ARRAY_A );
		ewwwio_debug_message( 'found some images in the db: ' . count( $sizes_queried ) );

		foreach ( $sizes_queried as $size_queried ) {
			// restore here
			if ( empty( $size_queried['converted'] ) ) {
				continue;
			}
			$new_name = ( empty( $size_queried['converted'] ) ? '' : $size_queried['converted'] );
			if ( $new_name && is_file( $size_queried['path'] ) && is_file( $new_name ) ) {
//				$this->convert_retina( $size_queried['path'] );
				$this->restore_db_path( $size_queried['path'], $new_name, $size_queried['id'] );
				$this->replace_url( $new_name, $size_queried['path'] );
		//		ewwwio_debug_message( print_r( $meta['sizes'], true ) );
		//		ewwwio_debug_message( print_r( $size_queried, true ) );
				if ( ewww_image_optimizer_iterable( $meta['sizes'] ) && is_array( $meta['sizes'][ $size_queried['resize'] ] ) ) {
					ewwwio_debug_message( 'updating regular size' );
					$meta['sizes'][ $size_queried['resize'] ]['file'] = basename( $new_name );
					$meta['sizes'][ $size_queried['resize'] ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
				} elseif ( ewww_image_optimizer_iterable( $meta['custom_sizes'] ) && $dimensions = str_replace( 'custom-size-', '', $size_queried['resize'] ) && is_array( $meta['custom_sizes'][ $dimensions ] ) ) {
					ewwwio_debug_message( 'updating custom size' );
					$meta['custom_sizes'][ $dimensions ]['file'] = basename( $new_name );
				}
				unlink( $size_queried['path'] );
				// look for any 'duplicate' sizes that have the same dimensions as the current queried size
				if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size => $data ) {
						if ( $meta['sizes'][ $size_queried['resize'] ]['height'] == $data['height'] && $meta['sizes'][ $size_queried['resize'] ]['width'] == $data['width'] ) {
							$meta['sizes'][ $size ]['file'] = $meta['sizes'][ $size_queried['resize'] ]['file'];
							$meta['sizes'][ $size ]['mime-type'] = $meta['sizes'][ $size_queried['resize'] ]['mime-type'];
						}
					}
				}
			}
			ewwwio_debug_message( "restored {$size_queried['resize']} from db query" );
		//	ewwwio_debug_message( print_r( $meta, true ) );

		}

		return $meta;
	}

	private function convert_retina( $file ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$retina_path = ewww_image_optimizer_hidpi_optimize( $file, true );
		if ( ! $retina_path ) {
			return;
		}
		$new_name = $this->convert( $retina_path );
		if ( $new_name ) {
			$this->convert_db_path( $retina_path, $new_name );
		}
	}

	private function convert( $file ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( empty( $file ) ) {
			ewwwio_debug_message( 'no file provided to convert' );
			return false;
		}
		if ( false === is_file( $file ) ) {
			ewwwio_debug_message( "$file is not a file, cannot convert" );
			return false;
		}
		if ( false === is_writable( $file ) ) {
			ewwwio_debug_message( "$file is not writable, cannot convert" );
			return false;
		}
		$type = ewww_image_optimizer_mimetype( $file, 'i' );
		if ( ! $type ) {
			ewwwio_debug_message( 'could not find any functions for mimetype detection' );
			return false;
		}
		if ( strpos( $type, 'image' ) === FALSE ) {
			ewwwio_debug_message( "cannot convert mimetype: $type" );
			return false;
		}

		// just in case, run through the constants and utility checks, someday to be replaced with a proper object (or transient) that we can reference
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_CLOUD' ) || ! EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			ewww_image_optimizer_define_noexec();
			if ( EWWW_IMAGE_OPTIMIZER_NOEXEC ) {
				$nice = '';
			} else {
				// check to see if 'nice' exists
				$nice = ewww_image_optimizer_find_nix_binary( 'nice', 'n' );
			}
		}
		$skip = ewww_image_optimizer_skip_tools();
		// if the user has disabled the utility checks
		if ( EWWW_IMAGE_OPTIMIZER_CLOUD ) {
			$skip['jpegtran'] = true;
			$skip['optipng'] = true;
			$skip['gifsicle'] = true;
			$skip['pngout'] = true;
			$skip['pngquant'] = true;
			$skip['webp'] = true;
		}
		switch ( $type ) {
			case 'image/jpeg':
				$png_size = 0;
				$newfile = $this->unique_filename( $file, '.png' );
				ewwwio_debug_message( "attempting to convert JPG to PNG: $newfile" );
				// convert the JPG to PNG
				if ( ewww_image_optimizer_gmagick_support() ) {
					try {
						$gmagick = new Gmagick( $file );
						$gmagick->stripimage();
						$gmagick->setimageformat( 'PNG' );
						$gmagick->writeimage( $newfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $png_size && ewww_image_optimizer_imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						$imagick->stripImage();
						$imagick->setImageFormat( 'PNG' );
						$imagick->writeImage( $newfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $png_size && ewww_image_optimizer_gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromjpeg( $file ), $newfile );
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// if the PNG exists, and we didn't end up with an empty file
				if ( $png_size && is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) == 'image/png' ) {
					ewwwio_debug_message( "JPG to PNG successful" );
					// check to see if the user wants the originals deleted
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) == TRUE ) {
						// delete the original JPG
						unlink( $file );
					}
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					if ( is_file( $newfile ) ) {
						unlink ( $newfile );
					}
					return false;
				}
				break;
			case 'image/png':
				$jpg_size = 0;
				$newfile = $this->unique_filename( $file, '.jpg' );
				ewwwio_debug_message( "attempting to convert PNG to JPG: $newfile" );
				// if the user set a fill background for transparency
				if ( $background = ewww_image_optimizer_jpg_background() ) {
					// set background color for GD
					$r = hexdec( '0x' . strtoupper( substr( $background, 0, 2 ) ) );
                                        $g = hexdec( '0x' . strtoupper( substr( $background, 2, 2 ) ) );
					$b = hexdec( '0x' . strtoupper( substr( $background, 4, 2 ) ) );
				} else {
					$r = '';
					$g = '';
					$b = '';
				}
				// if the user manually set the JPG quality
				$quality = ewww_image_optimizer_jpg_quality();
				if ( empty( $quality ) ) {
					$quality = '92';
				}
				$magick_background = ewww_image_optimizer_jpg_background();
				if ( empty( $magick_background ) ) {
					$magick_background = '000000';
				}
				// convert the PNG to a JPG with all the proper options
				if ( ewww_image_optimizer_gmagick_support() ) {
					try {
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$gmagick_overlay = new Gmagick( $file );
							$gmagick = new Gmagick();
							$gmagick->newimage( $gmagick_overlay->getimagewidth(), $gmagick_overlay->getimageheight(), '#' . $magick_background );
							$gmagick->compositeimage( $gmagick_overlay, 1, 0, 0 );
						} else {
							$gmagick = new Gmagick( $file );
						}
						$gmagick->setimageformat( 'JPG' );
						$gmagick->setcompressionquality( $quality );
						$gmagick->writeimage( $newfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $jpg_size && ewww_image_optimizer_imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$imagick->setImageBackgroundColor( new ImagickPixel( '#' . $magick_background ) );
							$imagick->setImageAlphaChannel( 11 );
						}
						$imagick->setImageFormat( 'JPG' );
						$imagick->setCompressionQuality( $quality );
						$imagick->writeImage( $newfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $newfile );
				} 
				if ( ! $jpg_size && ewww_image_optimizer_gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					// retrieve the data from the PNG
					$input = imagecreatefrompng( $file );
					// retrieve the dimensions of the PNG
					list( $width, $height ) = getimagesize( $file );
					// create a new image with those dimensions
					$output = imagecreatetruecolor( $width, $height );
					if ( $r === '' ) {
						$r = 255;
						$g = 255;
						$b = 255;
					}
					// allocate the background color
					$rgb = imagecolorallocate( $output, $r, $g, $b );
					// fill the new image with the background color 
					imagefilledrectangle( $output, 0, 0, $width, $height, $rgb );
					// copy the original image to the new image
					imagecopy( $output, $input, 0, 0, 0, 0, $width, $height );
					// output the JPG with the quality setting
					imagejpeg( $output, $newfile, $quality );
					$jpg_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted JPG size: $jpg_size" );
				// if the new JPG is smaller than the original PNG
				if ( $jpg_size && is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) == 'image/jpeg' ) {
					ewwwio_debug_message( "JPG to PNG successful" );
					// if the user wants originals delted after a conversion
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) == TRUE ) {
						// delete the original PNG
						unlink( $file );
					}
				} else {
					if ( is_file( $newfile ) ) {
						// otherwise delete the new JPG
						unlink( $newfile );
					}
					return false;
				}
				break;
			case 'image/gif':
				$png_size = 0;
				$newfile = $this->unique_filename( $file, '.png' );
				ewwwio_debug_message( "attempting to convert GIF to PNG: $newfile" );
				// convert the GIF to PNG
				if ( ewww_image_optimizer_gmagick_support() ) {
					try {
						$gmagick = new Gmagick( $file );
						$gmagick->stripimage();
						$gmagick->setimageformat( 'PNG' );
						$gmagick->writeimage( $newfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $png_size && ewww_image_optimizer_imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						$imagick->stripImage();
						$imagick->setImageFormat( 'PNG' );
						$imagick->writeImage( $newfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $png_size && ewww_image_optimizer_gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromgif( $file ), $newfile );
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// if the PNG exists, and we didn't end up with an empty file
				if ( $png_size && is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) == 'image/png' ) {
					ewwwio_debug_message( "GIF to PNG successful" );
					// check to see if the user wants the originals deleted
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) == TRUE ) {
						// delete the original JPG
						unlink( $file );
					}
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					if ( is_file( $newfile ) ) {
						unlink ( $newfile );
					}
					return false;
				}
				break;
			default:
				return false;
		}
		$this->replace_url( $newfile, $file );
		return $newfile;
	}

	// generate a unique filename for a converted image
	public function unique_filename( $file, $fileext ) {
		// strip the file extension
		$filename = preg_replace( '/\.\w+$/', '', $file );
		if ( ! is_file( $filename . $fileext ) ) {
			return $filename . $fileext;
		}
		// set the increment to 1 ( but allow the user to override it )
		$filenum = apply_filters( 'ewww_image_optimizer_converted_filename_suffix', $this->increment );
		//but it must be only letters, numbers, or underscores
		$filenum = ( preg_match( '/^[\w\d]*$/', $filenum ) ? $filenum : 1 );
		$suffix = ( ! empty( $filenum ) ? '-' . $filenum : '' );
		$dimensions = '';
		$default_hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
		$hidpi_suffix = '';
		// see if this is a retina image, and strip the suffix
		if ( preg_match( "/$default_hidpi_suffix$/", $filename ) ) {
			// strip the dimensions
			$filename = str_replace( $default_hidpi_suffix, '', $filename );
			$hidpi_suffix = $default_hidpi_suffix;
		}
		// see if this is a resize, and strip the dimensions
		if ( preg_match( '/-\d+x\d+(-\d+)*$/', $filename, $fileresize ) ) {
			// strip the dimensions
			$filename = str_replace( $fileresize[0], '', $filename );
			$dimensions = $fileresize[0];
		}
		// while a file exists with the current increment
		while ( file_exists( $filename . $suffix . $dimensions . $hidpi_suffix . $fileext ) ) {
			// increment the increment...
			$filenum++;
			$suffix = '-' . $filenum;
		}
		// all done, let's reconstruct the filename
		ewwwio_memory( __FUNCTION__ );
		$this->increment = $filenum;
		return $filename . $suffix . $dimensions . $hidpi_suffix . $fileext;
	}

	public function replace_url( $new_path = '', $old_path = '' ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

		$new = ( empty( $new_path ) ? $this->file : $new_path );
		$old = ( empty( $old_path ) ? $this->converted : $old_path );
		if ( empty( $new ) || empty( $old ) ) {
			return;
		}
		if ( empty( $new_path ) && empty( $old_path ) ) {
			$old_guid = $this->url;
		} else {
			$old_guid = trailingslashit( dirname( $this->url ) ) . basename( $old );
		}
		$guid = trailingslashit( dirname( $this->url ) ) . basename( $new );
		// construct the new guid based on the filename from the attachment metadata
		ewwwio_debug_message( "old guid: $old_guid" );
		ewwwio_debug_message( "new guid: $guid" );
		if ( substr( $old_guid, -1 ) == '/' || substr( $guid, -1 ) == '/' ) {
			ewwwio_debug_message( 'could not obtain full url for current and previous image, bailing' );
			return;
		}

		global $wpdb;
		// retrieve any posts that link the image
		$esql = $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE '%%%s%%'", $old_guid );
		ewwwio_debug_message( "using query: $esql" );
		// while there are posts to process
		$rows = $wpdb->get_results( $esql, ARRAY_A );
		if ( ewww_image_optimizer_iterable( $rows ) ) {
			foreach ( $rows as $row ) {
				// replace all occurences of the old guid with the new guid
				$post_content = str_replace( $old_guid, $guid, $row['post_content'] );
				ewwwio_debug_message( "replacing $old_guid with $guid in post " . $row['ID'] );
				// send the updated content back to the database
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $post_content ),
					array( 'ID' => $row['ID'] )
				);
			}
		}
	}

	// requires at least the old path to search for, and the new path to update, id is an optional db record id for the original image
	private function convert_db_path( $path, $new_path, $id = false ) {
		if ( empty( $path ) || empty( $new_path ) ) {
			return;
		}
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		if ( ! $id ) {
			$image_record = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! empty( $image_record ) && is_array( $image_record ) && ! empty( $image_record['id'] ) ) {
				$id = $image_record['id'];
			} else { // insert a new record
				$ewwwdb->insert( $ewwwdb->ewwwio_images, array(
					'path' => $new_path,
					'converted' => $path,
					//'image_size' => filesize( $new_path ),
					'orig_size' => filesize( $new_path ),
					'attachment_id' => $this->attachment_id,
					'results' => __( 'No savings', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
					'updated' => date( 'Y-m-d H:i:s' ),
					'updates' => 0,
				) );
				return;
			}
		}
		$ewwwdb->update( $ewwwdb->ewwwio_images,
			array(
				'path' => $new_path,
				'converted' => $path,
				//'image_size' => filesize( $new_path ),
				'results' => ewww_image_optimizer_image_results( $image_record['orig_size'], filesize( $new_path ) ),
				'updates' => 0,
				'trace' => '',
			),
			array(
				'id' => $id,
			)
		);
	}

	// requires at least the old path to search for, and the new path to update, id is an optional db record id for the original image
	private function restore_db_path( $path, $new_path, $id = false ) {
		if ( empty( $path ) || empty( $new_path ) ) {
			return;
		}
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		if ( ! $id ) {
			$image_record = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! empty( $image_record ) && is_array( $image_record ) && ! empty( $image_record['id'] ) ) {
				$id = $image_record['id'];
			} else { 
				return false;
			}
		}
		$ewwwdb->update( $ewwwdb->ewwwio_images,
			array(
				'path' => $new_path,
				'converted' => '',
				'image_size' => 0,
				'results' => __( 'Original Restored', EWWW_IMAGE_OPTIMIZER_DOMAIN ),
				'updates' => 0,
				'trace' => '',
				'level' => null,
			),
			array(
				'id' => $id,
			)
		);
	}

	// perform an estimate of the time required to optimize the file, primarily for use in avoiding timeouts
	// calculates based on the image type, file size, and optimization level using averages from API logs
	public function time_estimate() {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		$time = 0;
		$type = ewww_image_optimizer_quick_mimetype( $this->file );
		$image_size = ( empty( $this->opt_size ) ? $this->orig_size : $this->opt_size );
		if ( empty( $image_size ) ) {
			$this->orig_size = filesize( $this->file );
			$image_size = $this->orig_size;
		}
		switch ( $type ) {
			case 'image/jpeg':
				if ( $image_size > 10000000 ) { // greater than 10MB
					$time += 20;
				} elseif ( $image_size > 5000000 ) { // greater than 5MB
					$time += 10;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 40 ) {
						$time += 25;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 30 ) {
						$time += 7;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 20 ) {
						$time += 2;
					}
				} elseif ( $image_size > 1000000 ) { // greater than 1MB
					$time += 5;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 40 ) {
						if ( $image_size > 2000000 ) { // greater than 2MB
							$time += 15;
						} else {
							$time += 11;
						}
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 30 ) {
						$time += 6;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 20 ) {
						$time += 2;
					}
				} else {
					$time++;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 40 ) {
						if ( $image_size > 200000 ) { // greater than 200k
							$time += 11;
						} else {
							$time += 5;
						}
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 30 ) {
						$time += 3;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) == 20 ) {
						$time += 3;
					}
				}
				break;
			case 'image/png':
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$time++;
				}
				if ( $image_size > 2500000 ) { // greater than 2.5MB
					$time += 35;
				} elseif ( $image_size > 1000000 ) { // greater than 1MB
					$time += 15;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 50 ) {
						$time += 8;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
						//$time++;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
						$time += 10;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 20 ) {
						$time++;
					}
				} elseif ( $image_size > 500000 ) { // greater than 500kb
					$time += 7;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 50 ) {
						$time += 5;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
						//$time++;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
						$time += 8;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 20 ) {
						$time++;
					}
				} elseif ( $image_size > 100000 ) { // greater than 100kb
					$time += 4;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 50 ) {
						$time += 5;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
						//$time++;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
						$time += 9;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 20 ) {
						$time++;
					}
				} else {
					$time++;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 50 ) {
						$time += 2;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 40 ) {
						$time ++;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 30 ) {
						$time += 3;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) == 20 ) {
						$time++;
					}
				}
				break;
			case 'image/gif':
				$time++;
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) == 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$time++;
				}
				if ( $image_size > 1000000 ) { // greater than 1MB
					$time += 5;
				}
				break;
			case 'application/pdf':
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
					$time +=2;
				}
				if ( $image_size > 25000000 ) { // greater than 25MB
					$time += 20;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
						$time += 16;
					}
				} elseif ( $image_size > 10000000 ) { // greater than 10MB
					$time += 10;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
						$time += 20;
					}
				} elseif ( $image_size > 4000000 ) { // greater than 4MB
					$time += 3;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
						$time += 12;
					}
				} elseif ( $image_size > 1000000 ) { // greater than 1MB
					$time++;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) == 20 ) {
						$time += 10;
					}
				}
				break;
			default:
				$time = 30;
		}
		ewwwio_debug_message( "estimated time for this image is $time" );
		return $time;
	}

}
