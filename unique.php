<?php
/**
 * Unique functions for Standard EWWW IO plugins.
 *
 * This file contains functions that are unique to the regular EWWW IO plugin.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Installation routine for PNGOUT.
add_action( 'admin_action_ewww_image_optimizer_install_pngout', 'ewww_image_optimizer_install_pngout_wrapper' );
// Installation routine for SVGCLEANER.
add_action( 'admin_action_ewww_image_optimizer_install_svgcleaner', 'ewww_image_optimizer_install_svgcleaner_wrapper' );
// Removes the binaries when the plugin is deactivated.
register_deactivation_hook( EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE, 'ewww_image_optimizer_remove_binaries' );

/**
 * Resizes an image with gifsicle to preserve animations.
 *
 * @param string $file The file to resize.
 * @param int    $dst_x X-coordinate of destination image (usually 0).
 * @param int    $dst_y Y-coordinate of destination image (usually 0).
 * @param int    $src_x X-coordinate of source image (usually 0 unless cropping).
 * @param int    $src_y Y-coordinate of source image (usually 0 unless cropping).
 * @param int    $dst_w Desired image width.
 * @param int    $dst_h Desired image height.
 * @param int    $src_w Source width.
 * @param int    $src_h Source height.
 * @return string|WP_Error The image contents or the error message.
 */
function ewww_image_optimizer_gifsicle_resize( $file, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$tools = array(
		'gifsicle' => ewwwio()->local->get_path( 'gifsicle' ),
	);
	if ( empty( $tools['gifsicle'] ) ) {
		ewwwio_debug_message( 'no gifsicle found for resizing' );
		return new WP_Error(
			'image_resize_error',
			/* translators: %s: name of a tool like jpegtran */
			sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' )
		);
	}
	ewwwio_debug_message( "file: $file " );
	ewwwio_debug_message( "width: $dst_w" );
	ewwwio_debug_message( "height: $dst_h" );

	list( $orig_w, $orig_h ) = wp_getimagesize( $file );

	$outfile = "$file.tmp";
	// Run gifsicle.
	if ( (int) $orig_w !== (int) $src_w || (int) $orig_h !== (int) $src_h ) {
		$dim_string  = $dst_w . 'x' . $dst_h;
		$crop_string = $src_x . ',' . $src_y . '+' . $src_w . 'x' . $src_h;
		ewwwio_debug_message( "resize to $dim_string" );
		ewwwio_debug_message( "crop to $crop_string" );
		$cmd = "{$tools['gifsicle']} --crop $crop_string  -o " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file );
		ewwwio_debug_message( "running: $cmd" );
		exec( $cmd, $output, $exit );
		$cmd = "{$tools['gifsicle']} --resize-fit $dim_string  -b " . ewww_image_optimizer_escapeshellarg( $outfile );
		ewwwio_debug_message( "running: $cmd" );
		exec( $cmd, $output, $exit );
	} else {
		$dim_string = $dst_w . 'x' . $dst_h;
		$cmd        = "{$tools['gifsicle']} --resize-fit $dim_string  -o " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file );
		ewwwio_debug_message( "running: $cmd" );
		exec( $cmd, $output, $exit );
	}
	ewwwio_debug_message( "$file resized to $outfile" );

	if ( ewwwio_is_file( $outfile ) ) {
		$new_type = ewww_image_optimizer_mimetype( $outfile, 'i' );
		// Check the filesize of the new JPG.
		$new_size = ewww_image_optimizer_filesize( $outfile );
		ewwwio_debug_message( "$outfile exists, testing type and size" );
	} else {
		return new WP_Error( 'image_resize_error', 'file does not exist' );
	}

	if ( ! $new_size || 'image/gif' !== $new_type ) {
		unlink( $outfile );
		return new WP_Error( 'image_resize_error', 'wrong type or zero bytes' );
	}
	ewwwio_debug_message( 'resize success' );
	$image = file_get_contents( $outfile );
	unlink( $outfile );
	return $image;
}

/**
 * Automatically corrects JPG rotation using local jpegtran tool.
 *
 * @param string $file Name of the file to fix.
 * @param string $type File type of the file.
 * @param int    $orientation The EXIF orientation value.
 *
 * @return bool True if the rotation was successful.
 */
function ewww_image_optimizer_jpegtran_autorotate( $file, $type, $orientation ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( 'image/jpeg' !== $type ) {
		ewwwio_debug_message( 'not a JPG, go away!' );
		return false;
	}
	if ( ! $orientation || 1 === (int) $orientation ) {
		return false;
	}
	$nice = '';
	if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
		// Check to see if 'nice' exists.
		$nice = ewwwio()->local->find_nix_binary( 'nice' );
	}
	$tools = array(
		'jpegtran' => ewwwio()->local->get_path( 'jpegtran' ),
	);
	if ( empty( $tools['jpegtran'] ) ) {
		return false;
	}
	switch ( $orientation ) {
		case 1:
			return false;
		case 2:
			$transform = '-flip horizontal';
			break;
		case 3:
			$transform = '-rotate 180';
			break;
		case 4:
			$transform = '-flip vertical';
			break;
		case 5:
			$transform = '-transpose';
			break;
		case 6:
			$transform = '-rotate 90';
			break;
		case 7:
			$transform = '-transverse';
			break;
		case 8:
			$transform = '-rotate 270';
			break;
		default:
			return false;
	}
	$outfile = "$file.rotate";
	// Run jpegtran.
	$cmd = "$nice {$tools['jpegtran']} -trim -copy all $transform -outfile " . ewww_image_optimizer_escapeshellarg( $outfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file );
	ewwwio_debug_message( "running: $cmd" );
	exec( $cmd, $output, $exit );
	ewwwio_debug_message( "$file rotated to $outfile" );

	if ( ewwwio_is_file( $outfile ) ) {
		$new_type = ewww_image_optimizer_mimetype( $outfile, 'i' );
		// Check the filesize of the new JPG.
		$new_size = filesize( $outfile );
		ewwwio_debug_message( "$outfile exists, testing type and size" );
	} else {
		return false;
	}

	if ( ! $new_size || 'image/jpeg' !== $new_type ) {
		unlink( $outfile );
		return false;
	}
	ewwwio_debug_message( 'rotation success' );
	rename( $outfile, $file );
	return true;
}

/**
 * Process an image.
 *
 * @param string $file Full absolute path to the image file.
 * @param int    $gallery_type 1=WordPress, 2=nextgen, 3=flagallery, 4=aux_images, 5=image editor,
 *                             6=imagestore.
 * @param bool   $converted True if this is a resize and the full image was converted to a
 *                          new format. Deprecated, always false now.
 * @param bool   $new True if this is a new image, so it should attempt conversion regardless of
 *                    previous results.
 * @param bool   $fullsize True if this is a full size (original) image.
 * @return array {
 *     Status of the optimization attempt.
 *
 *     @type string $file The filename or false on error.
 *     @type string $results The results of the optimization.
 *     @type bool $converted True if an image changes formats.
 *     @type string The original filename if converted.
 * }
 */
function ewww_image_optimizer( $file, $gallery_type = 4, $converted = false, $new = false, $fullsize = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	session_write_close();
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	if ( apply_filters( 'ewww_image_optimizer_bypass', false, $file ) ) {
		ewwwio_debug_message( "optimization bypassed: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	global $ewww_image;
	global $ewww_force;
	global $ewww_force_smart;
	global $ewww_convert;
	global $ewww_webp_only;
	// Initialize the original filename.
	$original = $file;
	$result   = '';
	if ( false !== strpos( $file, '../' ) || false !== strpos( $file, '..\\' ) ) {
		$msg = __( 'Path traversal in filename not allowed.', 'ewww-image-optimizer' );
		ewwwio_debug_message( "file is using ../ potential path traversal blocked: $file" );
		return array( false, $msg, $converted, $original );
	}
	if ( ! ewwwio_is_file( $file ) ) {
		/* translators: %s: Image filename */
		$msg = sprintf( __( 'Could not find %s', 'ewww-image-optimizer' ), $file );
		ewwwio_debug_message( "file doesn't appear to exist: $file" );
		return array( false, $msg, $converted, $original );
	}
	if ( ! is_writable( $file ) ) {
		/* translators: %s: Image filename */
		$msg = sprintf( __( '%s is not writable', 'ewww-image-optimizer' ), $file );
		ewwwio_debug_message( "couldn't write to the file $file" );
		return array( false, $msg, $converted, $original );
	}
	$file_perms = 'unknown';
	if ( ewww_image_optimizer_function_exists( 'fileperms' ) ) {
		$file_perms = substr( sprintf( '%o', fileperms( $file ) ), -4 );
	}
	$file_owner = 'unknown';
	$file_group = 'unknown';
	if ( function_exists( 'posix_getpwuid' ) ) {
		$file_owner = posix_getpwuid( fileowner( $file ) );
		if ( $file_owner ) {
			$file_owner = 'xxxxxxxx' . substr( $file_owner['name'], -4 );
		} else {
			$file_owner = 'unknown';
		}
	}
	if ( function_exists( 'posix_getgrgid' ) ) {
		$file_group = posix_getgrgid( filegroup( $file ) );
		if ( $file_group ) {
			$file_group = 'xxxxx' . substr( $file_group['name'], -5 );
		} else {
			$file_group = 'unknown';
		}
	}
	ewwwio_debug_message( "permissions: $file_perms, owner: $file_owner, group: $file_group" );
	$type = ewww_image_optimizer_mimetype( $file, 'i' );
	if ( ! $type ) {
		ewwwio_debug_message( 'could not find any functions for mimetype detection' );
		// Otherwise we store an error message since we couldn't get the mime-type.
		return array( false, __( 'Unknown file type', 'ewww-image-optimizer' ), $converted, $original );
	}
	// Not an image or pdf.
	if ( strpos( $type, 'image' ) === false && strpos( $type, 'pdf' ) === false ) {
		ewwwio_debug_message( "unsupported mimetype: $type" );
		return array( false, __( 'Unsupported file type', 'ewww-image-optimizer' ) . ": $type", $converted, $original );
	}
	if ( ! is_object( $ewww_image ) || ! $ewww_image instanceof EWWW_Image || $ewww_image->file !== $file ) {
		$ewww_image = new EWWW_Image( 0, '', $file );
	}
	$nice = '';
	if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
		// Check to see if 'nice' exists.
		$nice = ewwwio()->local->find_nix_binary( 'nice' );
	}
	$tools = array();
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_skip_full' ) && $fullsize ) {
		$keep_metadata = true;
	} else {
		$keep_metadata = false;
	}
	if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_lossy_skip_full' ) && $fullsize ) {
		$skip_lossy = true;
	} else {
		$skip_lossy = false;
	}
	if ( ini_get( 'max_execution_time' ) < 90 && ewww_image_optimizer_stl_check() ) {
		set_time_limit( 0 );
	}
	// Get the original image size.
	$orig_size = ewww_image_optimizer_filesize( $file );
	ewwwio_debug_message( "original filesize: $orig_size" );
	if ( $orig_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
		ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	if ( 'image/png' === $type && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $orig_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
		ewwwio_debug_message( "optimization bypassed due to filesize: $file" );
		// Tell the user optimization was skipped.
		return array( false, __( 'Optimization skipped', 'ewww-image-optimizer' ), $converted, $file );
	}
	$backup_hash = '';
	$new_size    = 0;
	// Set the optimization process to OFF.
	$optimize = false;
	// Toggle the convert process to ON.
	$convert = true;
	// Allow other plugins to mangle the image however they like prior to optimization.
	do_action( 'ewww_image_optimizer_pre_optimization', $file, $type, $fullsize );
	// Run the appropriate optimization/conversion for the mime-type.
	switch ( $type ) {
		case 'image/jpeg':
			$png_size = 0;
			// If jpg2png conversion is enabled, and this image is in the WordPress media library.
			if (
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_to_png' ) || ! empty( $ewww_convert ) ) &&
				empty( $ewww_webp_only )
			) {
				// Generate the filename for a PNG:
				// If this is a resize version.
				if ( $converted ) {
					// just change the file extension.
					$pngfile = preg_replace( '/\.\w+$/', '.png', $file );
				} else {
					// If this is a full size image.
					// Get a unique filename for the png image.
					$pngfile = ewww_image_optimizer_unique_filename( $file, '.png' );
				}
			} else {
				// Otherwise, turn conversion OFF.
				$convert = false;
				$pngfile = '';
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' );
			// Check for previous optimization, so long as the force flag is not on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( $compression_level > 10 && empty( $ewww_webp_only ) ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type, $convert, $pngfile, 'image/png', $skip_lossy );
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $original );
					}
					$converted   = true;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/png', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			$tools['jpegtran'] = ewwwio()->local->get_path( 'jpegtran' );
			$tools['cwebp']    = ewwwio()->local->get_path( 'cwebp' );
			if ( $convert ) {
				$tools['optipng']  = ewwwio()->local->get_path( 'optipng' );
				$tools['pngout']   = ewwwio()->local->get_path( 'pngout' );
				$tools['pngquant'] = ewwwio()->local->get_path( 'pngquant' );
			}
			// For exec-deprived servers, or those where jpegtran doesn't want to work.
			if ( 10 === (int) $compression_level && empty( $tools['jpegtran'] ) ) {
				if ( empty( $ewww_webp_only ) ) {
					list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
				}
				$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				break;
			}
			// If we get this far, we are using local (jpegtran) optimization, so do an autorotate on the image.
			ewww_image_optimizer_autorotate( $file );
			// Get the (possibly new) original image size.
			$orig_size = ewww_image_optimizer_filesize( $file );
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
				// Store an appropriate message in $result.
				$result = __( 'JPG optimization is disabled', 'ewww-image-optimizer' );
				// Otherwise, if jpegtran doesn't exist.
			} elseif ( empty( $tools['jpegtran'] ) ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>jpegtran</em>' );
				// Otherwise, things should be good, so...
			} else {
				// Set the optimization process to ON.
				$optimize = true;
			}
			// If local optimization is turned ON.
			if ( $optimize ) {
				ewwwio_debug_message( 'attempting to optimize JPG...' );
				// Generate temporary file-name.
				$progfile = $file . '.prog';
				// Check to see if we are supposed to strip metadata.
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && ! $keep_metadata ) {
					// Don't copy metadata.
					$copy_opt = 'none';
				} else {
					// Copy all the metadata.
					$copy_opt = 'all';
				}
				if ( $orig_size > 10240 ) {
					$progressive = '-progressive';
				} else {
					$progressive = '';
				}
				// Run jpegtran.
				$cmd = "$nice " . $tools['jpegtran'] . " -copy $copy_opt -optimize $progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file );
				ewwwio_debug_message( "running: $cmd" );
				exec( $cmd, $output, $exit );
				// Check the filesize of the new JPG.
				$new_size = ewww_image_optimizer_filesize( $progfile );
				ewwwio_debug_message( "optimized JPG size: $new_size" );
				// If the best-optimized is smaller than the original JPG, and we didn't create an empty JPG.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $progfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $progfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller JPG.
				} else {
					if ( ewwwio_is_file( $progfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $progfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				ewwwio_debug_message( 'calling webp, but neither convert or optimize' );
				// If conversion and optimization are both turned OFF, finish the JPG processing.
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tools['cwebp'] );
				break;
			} // End if().
			// If the conversion process is turned ON, or if this is a resize and the full-size was converted.
			if ( $convert ) {
				ewwwio_debug_message( "attempting to convert JPG to PNG: $pngfile" );
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				// Convert the JPG to PNG.
				if ( ewwwio()->gmagick_support() ) {
					try {
						$gmagick = new Gmagick( $file );
						$gmagick->stripimage();
						$gmagick->setimageformat( 'PNG' );
						$gmagick->writeimage( $pngfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				if ( ! $png_size && ewwwio()->imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						$imagick->stripImage();
						$imagick->setImageFormat( 'PNG' );
						$imagick->writeImage( $pngfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				if ( ! $png_size && ewwwio()->gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromjpeg( $file ), $pngfile );
					$png_size = ewww_image_optimizer_filesize( $pngfile );
				}
				// If lossy optimization is ON and full-size exclusion is not active.
				if ( $png_size && 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && $tools['pngquant'] && ! $skip_lossy ) {
					ewwwio_debug_message( 'attempting lossy reduction' );
					$cmd = "$nice " . $tools['pngquant'] . ' ' . ewww_image_optimizer_escapeshellarg( $pngfile );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
					$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $pngfile );
					if ( ewwwio_is_file( $quantfile ) && filesize( $pngfile ) > filesize( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is better: original - ' . filesize( $pngfile ) . ' vs. lossy - ' . filesize( $quantfile ) );
						rename( $quantfile, $pngfile );
					} elseif ( ewwwio_is_file( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is worse: original - ' . filesize( $pngfile ) . ' vs. lossy - ' . filesize( $quantfile ) );
						ewwwio_delete_file( $quantfile );
					} else {
						ewwwio_debug_message( 'pngquant did not produce any output' );
					}
				}
				// If optipng isn't disabled.
				if ( $png_size && $tools['optipng'] ) {
					// Retrieve the optipng optimization level.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if (
						ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) &&
						preg_match( '/0.7/', ewwwio()->local->test_binary( $tools['optipng'], 'optipng' ) ) &&
						! $keep_metadata
					) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// If the PNG file was created.
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_debug_message( 'optimizing converted PNG with optipng' );
						// Run optipng on the new PNG.
						$cmd = "$nice " . $tools['optipng'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $pngfile );
						ewwwio_debug_message( "running: $cmd" );
						exec( $cmd, $output, $exit );
					}
				}
				// If pngout isn't disabled.
				if ( $png_size && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ) {
					// Retrieve the pngout optimization level.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					// If the PNG file was created.
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_debug_message( 'optimizing converted PNG with pngout' );
						// Run pngout on the new PNG.
						$cmd = "$nice " . $tools['pngout'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile );
						ewwwio_debug_message( "running: $cmd" );
						exec( $cmd, $output, $exit );
					}
				}
				$png_size = ewww_image_optimizer_filesize( $pngfile );
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// If the PNG is smaller than the original JPG, and we didn't end up with an empty file.
				if ( $png_size && $new_size > $png_size && ewww_image_optimizer_mimetype( $pngfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( "converted PNG is better: $png_size vs. $new_size" );
					// Store the size of the converted PNG.
					$new_size = $png_size;
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
					// Store the location of the PNG file.
					$file = $pngfile;
					// Let webp know what we're dealing with now.
					$type = 'image/png';
					// Successful conversion and we store the increment.
					$converted = true;
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					// Otherwise delete the PNG.
					$converted = false;
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_delete_file( $pngfile );
					}
				}
			} // End if().
			$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['cwebp'], $orig_size !== $new_size );
			break;
		case 'image/png':
			$jpg_size = 0;
			// Png2jpg conversion is turned on, and the image is in the WordPress media library.
			// We check for transparency later, after optimization, because optipng might fix an empty alpha channel.
			$apng = ewww_image_optimizer_is_animated_png( $file );
			if ( $apng ) {
				$keep_metadata = true;
				$skip_lossy    = true;
			}
			if (
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_to_jpg' ) || ! empty( $ewww_convert ) ) &&
				! $skip_lossy &&
				empty( $ewww_webp_only )
			) {
				ewwwio_debug_message( 'PNG to JPG conversion turned on' );
				$cloud_background = '';
				$r                = '';
				$g                = '';
				$b                = '';
				// If the user set a fill background for transparency.
				$background = ewww_image_optimizer_jpg_background();
				if ( $background ) {
					$cloud_background = "#$background";
					// Set background color for GD.
					$r = hexdec( '0x' . strtoupper( substr( $background, 0, 2 ) ) );
					$g = hexdec( '0x' . strtoupper( substr( $background, 2, 2 ) ) );
					$b = hexdec( '0x' . strtoupper( substr( $background, 4, 2 ) ) );
					// Set the background flag for 'convert'.
					$background = '-background ' . '"' . "#$background" . '"';
				}
				$gquality = ewww_image_optimizer_jpg_quality();
				$gquality = $gquality ? $gquality : '82';
				// If this is a resize version.
				if ( $converted ) {
					// Just replace the file extension with a .jpg.
					$jpgfile = preg_replace( '/\.\w+$/', '.jpg', $file );
					// If this is a full version.
				} else {
					// Construct the filename for the new JPG.
					$jpgfile = ewww_image_optimizer_unique_filename( $file, '.jpg' );
				}
			} else {
				ewwwio_debug_message( 'PNG to JPG conversion turned off' );
				// Turn the conversion process OFF.
				$convert          = false;
				$jpgfile          = '';
				$r                = null;
				$g                = null;
				$b                = null;
				$cloud_background = '';
				$gquality         = null;
			} // End if().
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' );
			// Check for previous optimization, so long as the force flag is not on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if (
				$compression_level >= 20 &&
				ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) &&
				empty( $ewww_webp_only )
			) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer(
					$file,
					$type,
					$convert,
					$jpgfile,
					'image/jpeg',
					$skip_lossy,
					$cloud_background,
					$gquality
				);
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $original );
					}
					$converted   = true;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/jpeg', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			// For exec-deprived servers, allow free WebP conversion.
			if ( 10 >= (int) $compression_level && ! ewwwio()->local->exec_check() ) {
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, null, $orig_size !== $new_size );
				break;
			}
			$tools['optipng']  = ewwwio()->local->get_path( 'optipng' );
			$tools['pngout']   = ewwwio()->local->get_path( 'pngout' );
			$tools['pngquant'] = ewwwio()->local->get_path( 'pngquant' );
			$tools['cwebp']    = ewwwio()->local->get_path( 'cwebp' );
			if ( $convert ) {
				$tools['jpegtran'] = ewwwio()->local->get_path( 'jpegtran' );
			}
			// Check if we can (and should) do local PNG optimization.
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
				// Tell the user all PNG tools are disabled.
				$result = __( 'PNG optimization is disabled', 'ewww-image-optimizer' );
				// If the utility checking is on, optipng is enabled, but optipng cannot be found.
			} elseif ( empty( $tools['optipng'] ) ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>optipng</em>' );
				// If the utility checking is on, pngout is enabled, but pngout cannot be found.
			} else {
				// Turn optimization on if we made it through all the checks.
				$optimize = true;
			}
			// If optimization is turned on.
			if ( $optimize ) {
				// If lossy optimization is ON and full-size exclusion is not active.
				if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) && $tools['pngquant'] && ! $skip_lossy ) {
					ewwwio_debug_message( 'attempting lossy reduction' );
					$cmd = "$nice " . $tools['pngquant'] . ' ' . ewww_image_optimizer_escapeshellarg( $file );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
					$quantfile = preg_replace( '/\.\w+$/', '-fs8.png', $file );
					if ( ewwwio_is_file( $quantfile ) && filesize( $file ) > filesize( $quantfile ) && ewww_image_optimizer_mimetype( $quantfile, 'i' ) === $type ) {
						ewwwio_debug_message( 'lossy reduction is better: original - ' . filesize( $file ) . ' vs. lossy - ' . filesize( $quantfile ) );
						rename( $quantfile, $file );
					} elseif ( ewwwio_is_file( $quantfile ) ) {
						ewwwio_debug_message( 'lossy reduction is worse: original - ' . filesize( $file ) . ' vs. lossy - ' . filesize( $quantfile ) );
						ewwwio_delete_file( $quantfile );
					} else {
						ewwwio_debug_message( 'pngquant did not produce any output' );
					}
				}
				$tempfile = $file . '.tmp.png';
				copy( $file, $tempfile );
				// If optipng is enabled.
				if ( $tools['optipng'] ) {
					// Retrieve the optimization level for optipng.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					$strip         = '';
					if (
						ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) &&
						preg_match( '/0.7/', ewwwio()->local->test_binary( $tools['optipng'], 'optipng' ) ) &&
						! $keep_metadata
					) {
						$strip = '-strip all ';
					}
					// Run optipng on the PNG file.
					$cmd = "$nice " . $tools['optipng'] . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $tempfile );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
				}
				// If pngout is enabled.
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) ) {
					// Retrieve the optimization level for pngout.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					$strip        = '';
					if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) || $keep_metadata ) {
						$strip = '-k1';
					}
					// Run pngout on the PNG file.
					$cmd = "$nice " . $tools['pngout'] . " -s$pngout_level -k1 -q " . ewww_image_optimizer_escapeshellarg( $tempfile );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
				}
				// Retrieve the filesize of the temporary PNG.
				$new_size = ewww_image_optimizer_filesize( $tempfile );
				// If the new PNG is smaller.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $tempfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller PNG.
				} else {
					if ( ewwwio_is_file( $tempfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $tempfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				// If conversion and optimization are both disabled we are done here.
				ewwwio_debug_message( 'calling webp, but neither convert or optimize' );
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tools['cwebp'] );
				break;
			} // End if().
			// Retrieve the new filesize of the PNG.
			$new_size = ewww_image_optimizer_filesize( $file );
			// Double check for png2jpg conversion to see if we have an alpha image.
			if ( $convert && ewww_image_optimizer_png_alpha( $file ) && ! ewww_image_optimizer_jpg_background() ) {
				ewwwio_debug_message( 'PNG to JPG conversion turned off due to alpha' );
				$convert = false;
			}
			// If conversion is on and the PNG doesn't have transparency or the user set a background color to replace transparency.
			if ( $convert ) {
				ewwwio_debug_message( "attempting to convert PNG to JPG: $jpgfile" );
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				$magick_background = ewww_image_optimizer_jpg_background();
				if ( empty( $magick_background ) ) {
					$magick_background = '000000';
				}
				// Convert the PNG to a JPG with all the proper options.
				if ( ewwwio()->gmagick_support() ) {
					try {
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$gmagick_overlay = new Gmagick( $file );
							$gmagick         = new Gmagick();
							$gmagick->newimage( $gmagick_overlay->getimagewidth(), $gmagick_overlay->getimageheight(), '#' . $magick_background );
							$gmagick->compositeimage( $gmagick_overlay, 1, 0, 0 );
						} else {
							$gmagick = new Gmagick( $file );
						}
						$gmagick->setimageformat( 'JPG' );
						$gmagick->setcompressionquality( $gquality );
						$gmagick->writeimage( $jpgfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				}
				if ( ! $jpg_size && ewwwio()->imagick_support() ) {
					try {
						$imagick = new Imagick( $file );
						if ( ewww_image_optimizer_png_alpha( $file ) ) {
							$imagick->setImageBackgroundColor( new ImagickPixel( '#' . $magick_background ) );
							$imagick->setImageAlphaChannel( 11 );
						}
						$imagick->setImageFormat( 'JPG' );
						$imagick->setCompressionQuality( $gquality );
						$imagick->writeImage( $jpgfile );
					} catch ( Exception $imagick_error ) {
						ewwwio_debug_message( $imagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				}
				if ( ! $jpg_size && ewwwio()->gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					// Retrieve the data from the PNG.
					$input = imagecreatefrompng( $file );
					// Retrieve the dimensions of the PNG.
					list($width, $height) = wp_getimagesize( $file );
					// Create a new image with those dimensions.
					$output = imagecreatetruecolor( $width, $height );
					if ( '' === $r ) {
						$r = 255;
						$g = 255;
						$b = 255;
					}
					// Allocate the background color.
					$rgb = imagecolorallocate( $output, $r, $g, $b );
					// Fill the new image with the background color.
					imagefilledrectangle( $output, 0, 0, $width, $height, $rgb );
					// Copy the original image to the new image.
					imagecopy( $output, $input, 0, 0, 0, 0, $width, $height );
					// Output the JPG with the quality setting.
					imagejpeg( $output, $jpgfile, $gquality );
				}
				$jpg_size = ewww_image_optimizer_filesize( $jpgfile );
				if ( $jpg_size ) {
					ewwwio_debug_message( "converted JPG filesize: $jpg_size" );
				} else {
					ewwwio_debug_message( 'unable to convert to JPG' );
				}
				// Next we need to optimize that JPG if jpegtran is enabled.
				if ( 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) && ewwwio_is_file( $jpgfile ) && ! empty( $tools['jpegtran'] ) ) {
					// Generate temporary file-name.
					$progfile = $jpgfile . '.prog';
					// Check to see if we are supposed to strip metadata.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) && ! $keep_metadata ) {
						// Don't copy metadata.
						$copy_opt = 'none';
					} else {
						// Copy all the metadata.
						$copy_opt = 'all';
					}
					if ( $jpg_size > 10240 ) {
						$progressive = '-progressive';
					} else {
						$progressive = '';
					}
					// Run jpegtran.
					$cmd = "$nice " . $tools['jpegtran'] . " -copy $copy_opt -optimize $progressive -outfile " . ewww_image_optimizer_escapeshellarg( $progfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $jpgfile );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
					// Check the filesize of the new JPG.
					$opt_jpg_size = ewww_image_optimizer_filesize( $progfile );
					// If the best-optimized is smaller than the original JPG, and we didn't create an empty JPG.
					if ( $opt_jpg_size && $jpg_size > $opt_jpg_size ) {
						// Replace the original with the optimized file.
						rename( $progfile, $jpgfile );
						// Store the size of the optimized JPG.
						$jpg_size = $opt_jpg_size;
						ewwwio_debug_message( 'optimized JPG was smaller than un-optimized version' );
						// If the optimization didn't produce a smaller JPG.
					} elseif ( ewwwio_is_file( $progfile ) ) {
						ewwwio_delete_file( $progfile );
					}
				}
				ewwwio_debug_message( "converted JPG size: $jpg_size" );
				// If the new JPG is smaller than the original PNG.
				if ( $jpg_size && $new_size > $jpg_size && ewww_image_optimizer_mimetype( $jpgfile, 'i' ) === 'image/jpeg' ) {
					// Store the size of the JPG as the new filesize.
					$new_size = $jpg_size;
					// If the user wants originals delted after a conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original PNG.
						ewwwio_delete_file( $file );
					}
					// Update the $file location to the new JPG.
					$file = $jpgfile;
					// Let webp know what we're dealing with now.
					$type = 'image/jpeg';
					// Successful conversion, so we store the increment.
					$converted = true;
				} else {
					$converted = false;
					if ( ewwwio_is_file( $jpgfile ) ) {
						// Otherwise delete the new JPG.
						ewwwio_delete_file( $jpgfile );
					}
				}
			} // End if().
			$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['cwebp'], $orig_size !== $new_size );
			break;
		case 'image/gif':
			// If gif2png is turned on, and the image is in the WordPress media library.
			if (
				empty( $ewww_webp_only ) &&
				1 === (int) $gallery_type &&
				$fullsize &&
				( ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_to_png' ) || ! empty( $ewww_convert ) ) &&
				! ewww_image_optimizer_is_animated( $file )
			) {
				// Generate the filename for a PNG:
				// if this is a resize version...
				if ( $converted ) {
					// just change the file extension.
					$pngfile = preg_replace( '/\.\w+$/', '.png', $file );
				} else {
					// If this is the full version...
					// construct the filename for the new PNG.
					$pngfile = ewww_image_optimizer_unique_filename( $file, '.png' );
				}
			} else {
				// Turn conversion OFF.
				$convert = false;
				$pngfile = '';
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' );
			// Check for previous optimization, so long as the force flag is on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) && ! ( $new && $convert ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( empty( $ewww_webp_only ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && 10 === $compression_level ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type, $convert, $pngfile, 'image/png', $skip_lossy );
				if ( $converted ) {
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original GIF.
						ewwwio_delete_file( $original );
					}
					$converted   = true;
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, 'image/png', null, $orig_size !== $new_size );
				} else {
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, null, $orig_size !== $new_size );
				}
				break;
			}
			$tools['gifsicle'] = ewwwio()->local->get_path( 'gifsicle' );
			if ( $convert ) {
				// NOTE: we can only do local WebP if a GIF is converted to PNG.
				$tools['cwebp']    = ewwwio()->local->get_path( 'cwebp' );
				$tools['optipng']  = ewwwio()->local->get_path( 'optipng' );
				$tools['pngout']   = ewwwio()->local->get_path( 'pngout' );
				$tools['pngquant'] = ewwwio()->local->get_path( 'pngquant' );
			}
			// Check if we can (and should) do local GIF optimization.
			if ( ! empty( $ewww_webp_only ) ) {
				$optimize = false;
			} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) ) {
				$result = __( 'GIF optimization is disabled', 'ewww-image-optimizer' );
				// If utility checking is on, and gifsicle is not installed.
			} elseif ( empty( $tools['gifsicle'] ) ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>gifsicle</em>' );
			} else {
				// Otherwise, turn optimization ON.
				$optimize = true;
			}
			// If local optimization is turned ON.
			if ( $optimize ) {
				$tempfile = $file . '.tmp'; // temporary GIF output.
				// Run gifsicle on the GIF.
				$cmd = "$nice " . $tools['gifsicle'] . ' -O3 --careful -o ' . ewww_image_optimizer_escapeshellarg( $tempfile ) . ' ' . ewww_image_optimizer_escapeshellarg( $file );
				ewwwio_debug_message( "running: $cmd" );
				exec( $cmd, $output, $exit );
				// Retrieve the filesize of the temporary GIF.
				$new_size = ewww_image_optimizer_filesize( $tempfile );
				// If the new GIF is smaller.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $tempfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller GIF.
				} else {
					if ( ewwwio_is_file( $tempfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $tempfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			} elseif ( ! $convert ) {
				// This is for WebP-only mode, no conversion/optimization, and it'll be done via API.
				$webp_result = ewww_image_optimizer_webp_create( $file, $orig_size, $type, null, $orig_size !== $new_size );
				break;
			}
			// Get the new filesize for the GIF.
			$new_size = ewww_image_optimizer_filesize( $file );
			// If conversion is ON and the GIF isn't animated.
			if ( $convert && ! ewww_image_optimizer_is_animated( $file ) ) {
				if ( empty( $new_size ) ) {
					$new_size = $orig_size;
				}
				// If optipng is enabled.
				if ( $tools['optipng'] ) {
					// Retrieve the optipng optimization level.
					$optipng_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_optipng_level' );
					if (
						ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) &&
						preg_match( '/0.7/', ewwwio()->local->test_binary( $tools['optipng'], 'optipng' ) ) &&
						! $keep_metadata
					) {
						$strip = '-strip all ';
					} else {
						$strip = '';
					}
					// Run optipng on the GIF file.
					$cmd = "$nice " . $tools['optipng'] . ' -out ' . ewww_image_optimizer_escapeshellarg( $pngfile ) . " -o$optipng_level -quiet $strip " . ewww_image_optimizer_escapeshellarg( $file );
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
				}
				// If pngout is enabled.
				if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_disable_pngout' ) && $tools['pngout'] ) {
					// Retrieve the pngout optimization level.
					$pngout_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pngout_level' );
					// Run pngout on the GIF file directly (if optipng didn't work or isn't available).
					$cmd = "$nice " . $tools['pngout'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $file ) . ' ' . ewww_image_optimizer_escapeshellarg( $pngfile );
					// BUT, if $pngfile exists, which means optipng was successful at converting the GIF.
					if ( ewwwio_is_file( $pngfile ) ) {
						// Run pngout on the PNG file.
						$cmd = "$nice " . $tools['pngout'] . " -s$pngout_level -q " . ewww_image_optimizer_escapeshellarg( $pngfile );
					}
					ewwwio_debug_message( "running: $cmd" );
					exec( $cmd, $output, $exit );
				}
				// Retrieve the filesize of the PNG.
				$png_size = ewww_image_optimizer_filesize( $pngfile );
				// If the new PNG is smaller than the original GIF.
				if ( $png_size && $new_size > $png_size && ewww_image_optimizer_mimetype( $pngfile, 'i' ) === 'image/png' ) {
					// Store the PNG size as the new filesize.
					$new_size = $png_size;
					// If the user wants original GIFs deleted after successful conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original GIF.
						ewwwio_delete_file( $file );
					}
					// Update the $file location with the new PNG.
					$file = $pngfile;
					// Let webp know what we're dealing with now.
					$type = 'image/png';
					// Normally this would be at the end of the section, but we only want to do webp if the image was successfully converted to a png.
					$webp_result = ewww_image_optimizer_webp_create( $file, $new_size, $type, $tools['cwebp'], $orig_size !== $new_size );
					// Successful conversion, so we store the increment.
					$converted = true;
				} else {
					$converted = false;
					if ( ewwwio_is_file( $pngfile ) ) {
						ewwwio_delete_file( $pngfile );
					}
				}
			} // End if().
			break;
		case 'application/pdf':
			if ( ! empty( $ewww_webp_only ) ) {
				break;
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' );
			if ( empty( $ewww_force ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 20 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( $compression_level > 0 ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
			}
			break;
		case 'image/svg+xml':
			if ( ! empty( $ewww_webp_only ) ) {
				break;
			}
			$compression_level = (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' );
			// Check for previous optimization, so long as the force flag is not on and this isn't a new image that needs converting.
			if ( empty( $ewww_force ) ) {
				$results_msg = ewww_image_optimizer_check_table( $file, $orig_size );
				$smart_reopt = ! empty( $ewww_force_smart ) && ewww_image_optimizer_level_mismatch( $ewww_image->level, $compression_level ) ? true : false;
				if ( $smart_reopt ) {
					ewwwio_debug_message( "smart re-opt found level mismatch for $file, db says " . $ewww_image->level . " vs. current $compression_level" );
					// If the current compression level is less than what was previously used, and the previous level was premium (or premium plus).
					if ( $compression_level && $compression_level < $ewww_image->level && $ewww_image->level > 0 ) {
						ewwwio_debug_message( "smart re-opt triggering restoration for $file" );
						ewww_image_optimizer_cloud_restore_single_image( $ewww_image->record );
					}
				} elseif ( $results_msg ) {
					return array( $file, $results_msg, $converted, $original );
				}
			}
			$ewww_image->level = $compression_level;
			if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) && $compression_level > 0 ) {
				list( $file, $converted, $result, $new_size, $backup_hash ) = ewww_image_optimizer_cloud_optimizer( $file, $type );
				break;
			}
			$tools['svgcleaner'] = ewwwio()->local->get_path( 'svgcleaner' );
			// If svgcleaner is disabled.
			if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) ) {
				$result = __( 'SVG optimization is disabled', 'ewww-image-optimizer' );
			} elseif ( empty( $tools['svgcleaner'] ) ) {
				/* translators: %s: name of a tool like jpegtran */
				$result = sprintf( __( '%s is missing', 'ewww-image-optimizer' ), '<em>svgcleaner</em>' );
			} else {
				// Otherwise, turn optimization ON.
				$optimize = true;
			}
			// If local optimization is turned ON.
			if ( $optimize ) {
				$tempfile = $file . '.tmp.svg'; // temporary SVG output (must end with .svg)
				// Run svgcleaner on the SVG.
				$svgcleaner_options = array(
					'--allow-bigger-file',
					'--quiet',
				);
				if ( 1 === $compression_level ) {
					array_push(
						$svgcleaner_options,
						'--paths-to-relative=no',
						'--remove-unused-segments=no',
						'--convert-segments=no',
						'--merge-gradients=no',
						'--trim-ids=no',
						'--trim-colors=no',
						'--simplify-transforms=no',
						'--resolve-use=no'
					);
				}
				$cmd = "$nice " . $tools['svgcleaner'] . ' ' . implode( ' ', $svgcleaner_options ) . ' ' . ewww_image_optimizer_escapeshellarg( $file ) . ' ' . ewww_image_optimizer_escapeshellarg( $tempfile );
				ewwwio_debug_message( "running: $cmd" );
				exec( $cmd, $output, $exit );
				// Retrieve the filesize of the temporary SVG.
				$new_size = ewww_image_optimizer_filesize( $tempfile );
				// If the new SVG is smaller.
				if ( $new_size && $orig_size > $new_size && ewww_image_optimizer_mimetype( $tempfile, 'i' ) === $type ) {
					// Replace the original with the optimized file.
					rename( $tempfile, $file );
					// Store the results of the optimization.
					$result = "$orig_size vs. $new_size";
					// If the optimization didn't produce a smaller SVG.
				} else {
					if ( ewwwio_is_file( $tempfile ) ) {
						// Delete the optimized file.
						ewwwio_delete_file( $tempfile );
					}
					// Store the results.
					$result   = 'unchanged';
					$new_size = $orig_size;
				}
			}
			break;
		default:
			// If not a JPG, PNG, GIF, or SVG tell the user we don't work with strangers.
			return array( false, __( 'Unsupported file type', 'ewww-image-optimizer' ) . ": $type", $converted, $original );
	} // End switch().
	// Allow other plugins to run operations on the images after optimization.
	// NOTE: it is recommended to do any image modifications prior to optimization, otherwise you risk un-optimizing your images here.
	do_action( 'ewww_image_optimizer_post_optimization', $file, $type, $fullsize );
	// If their cloud api license limit has been exceeded.
	if ( 'exceeded' === $result ) {
		return array( false, __( 'License exceeded', 'ewww-image-optimizer' ), $converted, $original );
	} elseif ( 'exceeded quota' === $result ) {
		return array( false, __( 'Soft Quota Reached', 'ewww-image-optimizer' ), $converted, $original );
	}
	if ( ! empty( $new_size ) ) {
		// Set correct file permissions.
		$stat = stat( dirname( $file ) );
		ewwwio_debug_message( 'folder mode: ' . $stat['mode'] );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		ewwwio_debug_message( "attempting chmod with $perms" );
		ewwwio_chmod( $file, $perms );

		$results_msg = ewww_image_optimizer_update_table( $file, $new_size, $orig_size, $original, $backup_hash );
		if ( ! empty( $webp_result ) ) {
			$results_msg .= '<br>' . $webp_result;
		}
		ewwwio_memory( __FUNCTION__ );
		return array( $file, $results_msg, $converted, $original );
	}
	ewwwio_memory( __FUNCTION__ );
	// Otherwise, send back the filename, the results (some sort of error message), the $converted flag, and the name of the original image.
	if ( ! empty( $webp_result ) && ! empty( $ewww_webp_only ) ) {
		$result = $webp_result;
		return array( true, $result, $converted, $original );
	}
	return array( false, $result, $converted, $original );
}

/**
 * Creates WebP images alongside JPG and PNG files.
 *
 * @param string $file The name of the JPG/PNG file.
 * @param int    $orig_size The filesize of the JPG/PNG file.
 * @param string $type The mime-type of the incoming file.
 * @param string $tool The path to the cwebp binary, if installed.
 * @param bool   $recreate True to keep the .webp image even if it is larger than the JPG/PNG.
 * @return string Results of the WebP operation for display.
 */
function ewww_image_optimizer_webp_create( $file, $orig_size, $type, $tool, $recreate = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $ewww_force;
	$orig_size = ewww_image_optimizer_filesize( $file );
	$webpfile  = $file . '.webp';
	if ( apply_filters( 'ewww_image_optimizer_bypass_webp', false, $file ) ) {
		ewwwio_debug_message( "webp generation bypassed: $file" );
		return '';
	} elseif ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp' ) ) {
		return '';
	} elseif ( ! ewwwio_is_file( $file ) ) {
		ewwwio_debug_message( 'original file not found' );
		return esc_html__( 'Could not find file.', 'ewww-image-optimizer' );
	} elseif ( ! is_writable( $file ) ) {
		ewwwio_debug_message( 'original file not writable' );
		return esc_html__( 'File is not writable.', 'ewww-image-optimizer' );
	} elseif ( ewwwio_is_file( $webpfile ) && empty( $ewww_force ) && ! $recreate ) {
		ewwwio_debug_message( 'webp file exists, not forcing or recreating' );
		return esc_html__( 'WebP image already exists.', 'ewww-image-optimizer' );
	} elseif ( 'image/png' === $type && ewww_image_optimizer_is_animated_png( $file ) ) {
		ewwwio_debug_message( 'APNG found, WebP not possible' );
		return esc_html__( 'APNG cannot be converted to WebP.', 'ewww-image-optimizer' );
	}
	list( $width, $height ) = wp_getimagesize( $file );
	if ( $width > 16383 || $height > 16383 ) {
		return esc_html__( 'Image dimensions too large for WebP conversion.', 'ewww-image-optimizer' );
	}
	if ( empty( $tool ) || 'image/gif' === $type ) {
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
			ewww_image_optimizer_cloud_optimizer( $file, $type, false, $webpfile, 'image/webp' );
		} elseif ( ewwwio()->imagick_supports_webp() ) {
			ewww_image_optimizer_imagick_create_webp( $file, $type, $webpfile );
		} elseif ( ewwwio()->gd_supports_webp() ) {
			ewww_image_optimizer_gd_create_webp( $file, $type, $webpfile );
		} else {
			ewww_image_optimizer_cloud_optimizer( $file, $type, false, $webpfile, 'image/webp' );
		}
	} else {
		$nice = '';
		if ( PHP_OS !== 'WINNT' && ! ewwwio()->cloud_mode && ewwwio()->local->exec_check() ) {
			// Check to see if 'nice' exists.
			$nice = ewwwio()->local->find_nix_binary( 'nice' );
		}
		// Check to see if we are supposed to strip metadata.
		$copy_opt = ewww_image_optimizer_get_option( 'ewww_image_optimizer_metadata_remove' ) ? 'icc' : 'all';
		$quality  = (int) apply_filters( 'webp_quality', 75, 'image/webp' );
		if ( $quality < 50 || $quality > 100 ) {
			$quality = 75;
		}
		$sharp_yuv = defined( 'EIO_WEBP_SHARP_YUV' ) && EIO_WEBP_SHARP_YUV ? '-sharp_yuv' : '';
		if ( empty( $sharp_yuv ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_sharpen' ) ) {
			$sharp_yuv = '-sharp_yuv';
		}
		$lossless = '-lossless';
		if ( defined( 'EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP' ) && EWWW_IMAGE_OPTIMIZER_LOSSY_PNG2WEBP ) {
			$lossless = "-q $quality $sharp_yuv";
		}
		switch ( $type ) {
			case 'image/jpeg':
				ewwwio_debug_message( "$nice " . $tool . " -q $quality $sharp_yuv -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1' );
				exec( "$nice " . $tool . " -q $quality $sharp_yuv -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
				if ( ! ewwwio_is_file( $webpfile ) && ewwwio()->supports_webp() && ewww_image_optimizer_is_cmyk( $file ) ) {
					ewwwio_debug_message( 'cmyk image skipped, trying imagick' );
					ewww_image_optimizer_imagick_create_webp( $file, $type, $webpfile );
				} elseif ( ewwwio_is_file( $webpfile ) && 'image/webp' !== ewww_image_optimizer_mimetype( $webpfile, 'i' ) ) {
					ewwwio_debug_message( 'non-webp file produced' );
				}
				break;
			case 'image/png':
				ewwwio_debug_message( "$nice " . $tool . " $lossless -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1' );
				exec( "$nice " . $tool . " $lossless -metadata $copy_opt -quiet " . ewww_image_optimizer_escapeshellarg( $file ) . ' -o ' . ewww_image_optimizer_escapeshellarg( $webpfile ) . ' 2>&1', $cli_output );
				break;
		}
	}
	$webp_size = ewww_image_optimizer_filesize( $webpfile );
	ewwwio_debug_message( "webp is $webp_size vs. $type is $orig_size" );
	if ( ewwwio_is_file( $webpfile ) && $orig_size < $webp_size && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
		ewwwio_debug_message( 'webp file was too big, deleting' );
		ewwwio_delete_file( $webpfile );
		return esc_html__( 'WebP image was larger than original.', 'ewww-image-optimizer' );
	} elseif ( ewwwio_is_file( $webpfile ) && 'image/webp' === ewww_image_optimizer_mimetype( $webpfile, 'i' ) ) {
		// Set correct file permissions.
		$stat  = stat( dirname( $webpfile ) );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		ewwwio_chmod( $webpfile, $perms );
		if ( $orig_size < $webp_size && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
			return esc_html__( 'WebP image larger than original, saved anyway with Force WebP option.', 'ewww-image-optimizer' );
		}
		return 'WebP: ' . ewww_image_optimizer_image_results( $orig_size, $webp_size );
	} elseif ( ewwwio_is_file( $webpfile ) ) {
		ewwwio_debug_message( 'webp file mimetype did not validate, deleting' );
		ewwwio_delete_file( $webpfile );
		return esc_html__( 'WebP conversion error.', 'ewww-image-optimizer' );
	}
	return esc_html__( 'Image could not be converted to WebP.', 'ewww-image-optimizer' );
}

/**
 * Redirects back to previous page after PNGOUT installation.
 */
function ewww_image_optimizer_install_pngout_wrapper() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'You do not have permission to install image optimizer utilities.', 'ewww-image-optimizer' ) );
	}
	$sendback = ewww_image_optimizer_install_pngout();
	wp_safe_redirect( $sendback );
	ewwwio_memory( __FUNCTION__ );
	exit( 0 );
}

/**
 * Installs pngout from the official site.
 *
 * @return string The url from whence we came (settings page), with success or error parameters added.
 */
function ewww_image_optimizer_install_pngout() {
	if ( ! extension_loaded( 'zlib' ) || ! class_exists( 'PharData' ) ) {
		$pngout_error = __( 'zlib or phar extension missing from PHP', 'ewww-image-optimizer' );
	}
	if ( PHP_OS === 'Linux' ) {
		$os_string = 'linux';
	}
	if ( PHP_OS === 'FreeBSD' ) {
		$os_string = 'bsd';
	}
	$latest    = '20200115';
	$tool_path = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( empty( $pngout_error ) ) {
		if ( PHP_OS === 'Linux' || PHP_OS === 'FreeBSD' ) {
			$download_result = download_url( 'http://www.jonof.id.au/files/kenutils/pngout-' . $latest . '-' . $os_string . '-static.tar.gz' );
			if ( is_wp_error( $download_result ) ) {
				$pngout_error = $download_result->get_error_message();
			} else {
				if ( ! ewwwio_check_memory_available( filesize( $download_result ) + 1000 ) ) {
					$pngout_error = __( 'insufficient memory available for installation', 'ewww-image-optimizer' );
				} else {
					$arch_type = 'i686';
					if ( ewww_image_optimizer_function_exists( 'php_uname' ) ) {
						$arch_type = php_uname( 'm' );
						if ( 'x86_64' === $arch_type ) {
							$arch_type = 'amd64';
						}
					}

					$tmpname  = current( explode( '.', $download_result ) );
					$tmpname .= '-' . uniqid() . '.tar.gz';
					rename( $download_result, $tmpname );
					$download_result = $tmpname;

					$pngout_gzipped  = new PharData( $download_result );
					$pngout_tarball  = $pngout_gzipped->decompress();
					$download_result = $pngout_tarball->getPath();
					$pngout_tarball->extractTo(
						EWWW_IMAGE_OPTIMIZER_BINARY_PATH,
						'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static',
						true
					);

					if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static' ) ) {
						if ( ! rename( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-' . $os_string . '-static/' . $arch_type . '/pngout-static', $tool_path . 'pngout-static' ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
							}
						}
						if ( ! chmod( $tool_path . 'pngout-static', 0755 ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not set permissions', 'ewww-image-optimizer' );
							}
						}
						$pngout_version = ewwwio()->local->test_binary( ewww_image_optimizer_escapeshellarg( $tool_path ) . 'pngout-static', 'pngout' );
					} else {
						$pngout_error = __( 'extraction of files failed', 'ewww-image-optimizer' );
					}
				}
			}
		} elseif ( PHP_OS === 'Darwin' ) {
			$latest          = '20200115';
			$os_ext          = 'tar.gz';
			$os_ext          = 'zip';
			$download_result = download_url( 'http://www.jonof.id.au/files/kenutils/pngout-' . $latest . '-macos.' . $os_ext );
			if ( is_wp_error( $download_result ) ) {
				$pngout_error = $download_result->get_error_message();
			} else {
				if ( ! ewwwio_check_memory_available( filesize( $download_result ) + 1000 ) ) {
					$pngout_error = __( 'insufficient memory available for installation', 'ewww-image-optimizer' );
				} else {
					$tmpname  = current( explode( '.', $download_result ) );
					$tmpname .= '-' . uniqid() . '.' . $os_ext;
					rename( $download_result, $tmpname );
					$download_result = $tmpname;

					if ( 'zip' === $os_ext ) {
						WP_Filesystem();
						$unzipped = unzip_file(
							$download_result,
							EWWW_IMAGE_OPTIMIZER_BINARY_PATH
						);
					} else {
						$pngout_gzipped  = new PharData( $download_result );
						$pngout_tarball  = $pngout_gzipped->decompress();
						$download_result = $pngout_tarball->getPath();
						$pngout_tarball->extractTo(
							EWWW_IMAGE_OPTIMIZER_BINARY_PATH,
							'pngout-' . $latest . '-darwin/pngout',
							true
						);
					}
					if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-macos/pngout' ) ) {
						if ( ! rename( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . 'pngout-' . $latest . '-macos/pngout', $tool_path . 'pngout-static' ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
							}
						}
						if ( ! chmod( $tool_path . 'pngout-static', 0755 ) ) {
							if ( empty( $pngout_error ) ) {
								$pngout_error = __( 'could not set permissions', 'ewww-image-optimizer' );
							}
						}
						$pngout_version = ewwwio()->local->test_binary( ewww_image_optimizer_escapeshellarg( $tool_path ) . 'pngout-static', 'pngout' );
					} elseif ( ! empty( $unzipped ) && is_wp_error( $unzipped ) ) {
						$pngout_error = $unzipped->get_error_message();
					} else {
						$pngout_error = __( 'extraction of files failed', 'ewww-image-optimizer' );
					}
				}
			}
		}
	} // End if().
	if ( PHP_OS === 'WINNT' ) {
		$download_result = download_url( 'http://advsys.net/ken/util/pngout.exe' );
		if ( is_wp_error( $download_result ) ) {
			$pngout_error = $download_result->get_error_message();
		} else {
			if ( ! rename( $download_result, $tool_path . 'pngout.exe' ) ) {
				if ( empty( $pngout_error ) ) {
					$pngout_error = __( 'could not move pngout', 'ewww-image-optimizer' );
				}
			}
			$pngout_version = ewwwio()->local->test_binary( '"' . $tool_path . 'pngout.exe"', 'pngout' );
		}
	}
	if ( is_string( $download_result ) && is_writable( $download_result ) ) {
		unlink( $download_result );
	}
	if ( ! empty( $pngout_version ) ) {
		$sendback = add_query_arg( 'ewww_pngout', 'success', remove_query_arg( array( 'ewww_pngout', 'ewww_error' ), wp_get_referer() ) );
	}
	if ( ! isset( $sendback ) ) {
		$sendback = add_query_arg(
			array(
				'ewww_pngout' => 'failed',
				'ewww_error'  => urlencode( $pngout_error ),
			),
			remove_query_arg( array( 'ewww_pngout', 'ewww_error' ), wp_get_referer() )
		);
	}
	return $sendback;
}

/**
 * Redirects back to previous page after SVGCLEANER installation.
 */
function ewww_image_optimizer_install_svgcleaner_wrapper() {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( ! current_user_can( apply_filters( 'ewww_image_optimizer_admin_permissions', '' ) ) ) {
		wp_die( esc_html__( 'You do not have permission to install image optimizer utilities.', 'ewww-image-optimizer' ) );
	}
	$sendback = ewww_image_optimizer_install_svgcleaner();
	wp_safe_redirect( $sendback );
	ewwwio_memory( __FUNCTION__ );
	exit( 0 );
}

/**
 * Installs svgcleaner from the official site.
 *
 * @return string The url from whence we came (settings page), with success or error parameters added.
 */
function ewww_image_optimizer_install_svgcleaner() {
	if ( ! extension_loaded( 'zlib' ) || ! class_exists( 'PharData' ) ) {
		$download_error = __( 'zlib or phar extension missing from PHP', 'ewww-image-optimizer' );
	}
	$os_chmod  = true;
	$os_binary = 'svgcleaner';
	$os_ext    = 'tar.gz';
	if ( PHP_OS === 'Linux' ) {
		$arch_type = 'x86_64';
		if ( ewww_image_optimizer_function_exists( 'php_uname' ) ) {
			$arch_type = php_uname( 'm' );
		}
		$os_string = 'linux_' . $arch_type;
	} elseif ( PHP_OS === 'Darwin' ) {
		$os_string = 'macos';
		$os_ext    = 'zip';
	} elseif ( PHP_OS === 'WINNT' ) {
		$os_chmod  = false;
		$os_string = 'win32';
		$os_binary = 'svgcleaner.exe';
		$os_ext    = 'zip';
	}
	$latest    = '0.9.5';
	$tool_path = trailingslashit( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( empty( $download_error ) ) {
		$download_result = download_url( 'https://github.com/RazrFalcon/svgcleaner/releases/download/v' . $latest . '/svgcleaner_' . $os_string . '_' . $latest . '.' . $os_ext );
		if ( is_wp_error( $download_result ) ) {
			$download_error = $download_result->get_error_message();
		} else {
			if ( ! ewwwio_check_memory_available( filesize( $download_result ) + 1000 ) ) {
				$download_error = __( 'insufficient memory available for installation', 'ewww-image-optimizer' );
			} else {
				$tmpname  = current( explode( '.', $download_result ) );
				$tmpname .= '-' . uniqid() . '.' . $os_ext;
				rename( $download_result, $tmpname );
				$download_result = $tmpname;

				if ( 'zip' === $os_ext ) {
					WP_Filesystem();
					$unzipped = unzip_file(
						$download_result,
						EWWW_IMAGE_OPTIMIZER_BINARY_PATH
					);
					if ( is_wp_error( $unzipped ) ) {
						$download_error = $unzipped->get_error_message();
					}
				} else {
					$pkg_gzipped     = new PharData( $download_result );
					$pkg_tarball     = $pkg_gzipped->decompress();
					$download_result = $pkg_tarball->getPath();
					$pkg_tarball->extractTo(
						EWWW_IMAGE_OPTIMIZER_BINARY_PATH,
						'svgcleaner',
						true
					);
				}
				if ( ewwwio_is_file( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . $os_binary ) ) {
					if ( ! rename( EWWW_IMAGE_OPTIMIZER_BINARY_PATH . $os_binary, $tool_path . $os_binary ) ) {
						if ( empty( $download_error ) ) {
							$download_error = __( 'could not move svgcleaner', 'ewww-image-optimizer' );
						}
					}
					if ( $os_chmod && ! chmod( $tool_path . $os_binary, 0755 ) ) {
						if ( empty( $download_error ) ) {
							$download_error = __( 'could not set permissions', 'ewww-image-optimizer' );
						}
					}
					if ( PHP_OS === 'WINNT' ) {
						$pkg_version = ewwwio()->local->test_binary( '"' . $tool_path . $os_binary . '"', 'svgcleaner' );
					} else {
						$pkg_version = ewwwio()->local->test_binary( ewww_image_optimizer_escapeshellarg( $tool_path ) . $os_binary, 'svgcleaner' );
					}
				} else {
					$download_error = __( 'extraction of files failed', 'ewww-image-optimizer' );
				}
			}
		}
	}
	if ( is_string( $download_result ) && is_writable( $download_result ) ) {
		unlink( $download_result );
	}
	if ( ! empty( $pkg_version ) ) {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_disable_svgcleaner', false );
		if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_svg_level' ) ) {
			ewww_image_optimizer_set_option( 'ewww_image_optimizer_svg_level', 10 );
		}
		$sendback = add_query_arg( 'ewww_svgcleaner', 'success', remove_query_arg( array( 'ewww_svgcleaner', 'ewww_error' ), wp_get_referer() ) );
	}
	if ( ! isset( $sendback ) ) {
		$sendback = add_query_arg(
			array(
				'ewww_svgcleaner' => 'failed',
				'ewww_error'      => urlencode( $download_error ),
			),
			remove_query_arg( array( 'ewww_svgcleaner', 'ewww_error' ), wp_get_referer() )
		);
	}
	return $sendback;
}

/**
 * Removes any binaries that have been installed in the wp-content/ewww/ folder.
 */
function ewww_image_optimizer_remove_binaries() {
	if ( ! class_exists( 'RecursiveIteratorIterator' ) ) {
		return;
	}
	if ( ! is_dir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( EWWW_IMAGE_OPTIMIZER_TOOL_PATH ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$path = $file->getPathname();
			if ( is_writable( $path ) ) {
				unlink( $path );
			}
		}
	}
	if ( ! class_exists( 'FilesystemIterator' ) ) {
		return;
	}
	clearstatcache();
	$iterator = new FilesystemIterator( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	if ( ! $iterator->valid() ) {
		rmdir( EWWW_IMAGE_OPTIMIZER_TOOL_PATH );
	}
}
