<?php
/**
 * Class file for EWWW_Image
 *
 * EWWW_Image contains methods for retrieving records from the ewwwio_images table.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieves image records from the database.
 *
 * Usually used to retrieve pending records, provides functions to process conversion of resizes
 * and utility functions during bulk operations. Can also create an object for a new image to
 * ensure proper record-keeping when new images are insterted to the database.
 */
class EWWW_Image {

	/**
	 * The id number of the related attachment.
	 *
	 * @var int $attachment_id
	 */
	public $attachment_id = null;

	/**
	 * The backup reference for a given file.
	 *
	 * @var string|bool $backup
	 */
	public $backup = '';

	/**
	 * The name of the original file if the image was converted. False if not converted.
	 *
	 * @var string|bool $converted
	 */
	public $converted = false;

	/**
	 * The path to the image.
	 *
	 * @var string $file
	 */
	public $file = '';

	/**
	 * The gallery of the image, if applicable. Accepts 'media', 'nextgen', etc.
	 *
	 * @var string $gallery
	 */
	public $gallery = '';

	/**
	 * The id number in the database.
	 *
	 * @var int $id
	 */
	public $id = 0;

	/**
	 * To be appended to converted files if necessary.
	 *
	 * @var int|bool $increment
	 */
	public $increment = false;

	/**
	 * Compression level as an integer.
	 *
	 * @var int $level
	 */
	public $level = 0;

	/**
	 * The optimized size of the image.
	 *
	 * @var int $opt_size
	 */
	public $opt_size = 0;

	/**
	 * The original size of the image.
	 *
	 * @var int $orig_size
	 */
	public $orig_size = 0;

	/**
	 * Raw db record.
	 *
	 * @var array $record
	 */
	public $record = array();

	/**
	 * The size/type of the image, like 'thumbnail', 'medium', 'large'.
	 *
	 * @var string $resize
	 */
	public $resize = null;

	/**
	 * The suffix added to the converted file, to be applied also to thumbs.
	 *
	 * @var string $suffix
	 */
	private $suffix = '';

	/**
	 * The url to the image.
	 *
	 * @var string $url
	 */
	public $url = '';

	/**
	 * Creates an image record, either from a pending record in the database, or from a file path.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A new database connection with super powers.
	 *
	 * @param int    $id Optional. The attachment ID to search for.
	 * @param string $gallery Optional. The type of image to work with. Accepts 'media', 'nextgen', 'flag', or 'nextcellent'.
	 * @param string $path Optional. The absolute path to an image.
	 */
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
		$id = (int) $id;
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$ewwwdb->flush();
		if ( $path && ( ewwwio_is_file( $path ) || ewww_image_optimizer_stream_wrapped( $path ) ) ) {
			ewwwio_debug_message( "creating EWWW_Image with $path" );
			$new_image = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! $new_image ) {
				$this->file      = $path;
				$this->orig_size = ewww_image_optimizer_filesize( $path );
				$this->gallery   = $gallery;
				if ( $id ) {
					$this->attachment_id = (int) $id;
				}
				return;
			} elseif ( is_array( $new_image ) ) {
				if ( $id && empty( $new_image['attachment_id'] ) ) {
					$new_image['attachment_id'] = (int) $id;
				}
				if ( $gallery && empty( $new_image['gallery'] ) && ! empty( $new_image['attachment_id'] ) ) {
					$new_image['gallery'] = $gallery;
				}
			}
		} elseif ( $path ) { // If $path is supplied but is not a file, then bail.
			ewwwio_debug_message( "could not create EWWW_Image with $path, not a file" );
			return;
		} elseif ( $id && $gallery ) {
			ewwwio_debug_message( "looking for $gallery image $id" );
			// Matches $id, $gallery, is 'full', and pending.
			$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery' AND resize = 'full' AND pending = 1 LIMIT 1", ARRAY_A );
			if ( empty( $new_image ) ) {
				// Matches $id, $gallery and pending.
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $id AND gallery = '$gallery' AND pending = 1 LIMIT 1", ARRAY_A );
			}
			if ( empty( $new_image ) ) {
				// Matches $gallery, is 'full' and pending.
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE gallery = '$gallery' AND resize = 'full' AND pending = 1 LIMIT 1", ARRAY_A );
			}
			if ( empty( $new_image ) ) {
				// Pull a random image.
				$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE pending = 1 LIMIT 1", ARRAY_A );
			}
		} else {
			ewwwio_debug_message( 'no id or path, just pulling next image' );
			$new_image = $ewwwdb->get_row( "SELECT * FROM $ewwwdb->ewwwio_images WHERE pending = 1 LIMIT 1", ARRAY_A );
		} // End if().

		if ( empty( $new_image ) ) {
			ewwwio_debug_message( 'failed to find a pending image with the parameters supplied' );
			return;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $new_image, true ) );
		}
		$this->id            = $new_image['id'];
		$this->file          = ewww_image_optimizer_absolutize_path( $new_image['path'] );
		$this->attachment_id = (int) $new_image['attachment_id'];
		$this->opt_size      = (int) $new_image['image_size'];
		$this->orig_size     = (int) $new_image['orig_size'];
		$this->resize        = $new_image['resize'];
		$this->converted     = ewww_image_optimizer_absolutize_path( $new_image['converted'] );
		$this->gallery       = ( empty( $gallery ) || empty( $new_image['attachment_id'] ) ? $new_image['gallery'] : $gallery );
		$this->backup        = $new_image['backup'];
		$this->level         = (int) $new_image['level'];
		$this->record        = $new_image;
	}

	/**
	 * Updates the post mime type field for an attachment after successful conversion.
	 *
	 * @param array $meta The attachment metadata.
	 */
	public function update_converted_attachment( $meta ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->url = wp_get_attachment_url( $this->attachment_id );
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) && ewww_image_optimizer_function_exists( 'print_r' ) ) {
			ewwwio_debug_message( print_r( $this, true ) );
		}
		// Update the file location in the post metadata based on the new path stored in the attachment metadata.
		update_attached_file( $this->attachment_id, $meta['file'] );
		$this->replace_url();

		// If the new image is a JPG.
		if ( preg_match( '/.jpg$/i', $meta['file'] ) ) {
			// Set the mimetype to JPG.
			$mime = 'image/jpeg';
		}
		// If the new image is a PNG.
		if ( preg_match( '/.png$/i', $meta['file'] ) ) {
			// Set the mimetype to PNG.
			$mime = 'image/png';
		}
		if ( preg_match( '/.gif$/i', $meta['file'] ) ) {
			// Set the mimetype to GIF.
			$mime = 'image/gif';
		}
		// Update the attachment post with the new mimetype and id.
		wp_update_post(
			array(
				'ID'             => $this->attachment_id,
				'post_mime_type' => $mime,
			)
		);

		// Possibly update translated replicas (WPML and the like).
		$translated_ids = ewww_image_optimizer_get_translated_media_ids( $this->attachment_id );
		if ( ewww_image_optimizer_iterable( $translated_ids ) ) {
			foreach ( $translated_ids as $translated_id ) {
				update_attached_file( $translated_id, $meta['file'] );
				wp_update_post(
					array(
						'ID'             => $translated_id,
						'post_mime_type' => $mime,
					)
				);
			}
		}
	}

	/**
	 * Converts all the 'resizes' after a successful conversion of the original image.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A new database connection with super powers.
	 *
	 * @param array $meta The attachment metadata.
	 * @return array $meta The updated attachment metadata.
	 */
	public function convert_sizes( $meta ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$sizes_queried = $ewwwdb->get_results( "SELECT * FROM $ewwwdb->ewwwio_images WHERE attachment_id = $this->attachment_id AND resize <> 'full' AND resize <> ''", ARRAY_A );
		/* ewwwio_debug_message( 'found some images in the db: ' . count( $sizes_queried ) ); */
		$sizes = array();
		if ( 'ims_image' === get_post_type( $this->attachment_id ) ) {
			$base_dir = trailingslashit( dirname( $this->file ) ) . '_resized/';
		} else {
			$base_dir = trailingslashit( dirname( $this->file ) );
		}
		ewwwio_debug_message( 'about to process db results' );
		foreach ( $sizes_queried as $size_queried ) {
			$size_queried['path'] = ewww_image_optimizer_absolutize_path( $size_queried['path'] );

			$sizes[ $size_queried['resize'] ] = $size_queried;
			// Convert here.
			$new_name = $this->convert( $size_queried['path'] );
			if ( $new_name ) {
				$this->convert_retina( $size_queried['path'] );
				$this->convert_db_path( $size_queried['path'], $new_name, $size_queried );

				if ( ewww_image_optimizer_iterable( $meta['sizes'] ) && is_array( $meta['sizes'][ $size_queried['resize'] ] ) ) {
					ewwwio_debug_message( 'updating regular size' );
					$meta['sizes'][ $size_queried['resize'] ]['file']      = \wp_basename( $new_name );
					$meta['sizes'][ $size_queried['resize'] ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
					// Store height/width in $sizes to make sure we catch meta dups.
					$sizes[ $size_queried['resize'] ]['width']  = $meta['sizes'][ $size_queried['resize'] ]['width'];
					$sizes[ $size_queried['resize'] ]['height'] = $meta['sizes'][ $size_queried['resize'] ]['height'];
				} elseif ( ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
					$dimensions = str_replace( 'custom-size-', '', $size_queried['resize'] );
					if ( is_array( $meta['custom_sizes'][ $dimensions ] ) ) {
						ewwwio_debug_message( 'updating custom size' );
						$meta['custom_sizes'][ $dimensions ]['file'] = \wp_basename( $new_name );
					}
				}
			}
			ewwwio_debug_message( "converted {$size_queried['resize']} from db query" );
		}

		ewwwio_debug_message( 'next up for conversion search: meta' );
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $data ) {
				/* ewwwio_debug_message( "checking to see if we should convert $size" ); */
				if ( strpos( $size, 'webp' ) === 0 ) {
					/* ewwwio_debug_message( 'skipping webp' ); */
					continue;
				}
				// Skip sizes that were already in ewwwio_images.
				if ( isset( $sizes[ $size ] ) ) {
					/* ewwwio_debug_message( 'skipping size that was in db results' ); */
					continue;
				}
				if ( empty( $data['file'] ) ) {
					/* ewwwio_debug_message( 'skipping size with missing filename' ); */
					continue;
				}
				foreach ( $sizes as $done_size => $done ) {
					if ( empty( $done['height'] ) || empty( $done['width'] ) ) {
						continue;
					}
					if ( $data['height'] === $done['height'] && $data['width'] === $done['width'] ) {
						ewwwio_debug_message( "already did a size with {$done['width']} x {$done['height']}" );
						$meta['sizes'][ $size ]['file']      = $meta['sizes'][ $done_size ]['file'];
						$meta['sizes'][ $size ]['mime-type'] = $meta['sizes'][ $done_size ]['mime-type'];
						continue( 2 );
					}
				}
				$sizes[ $size ] = $data;
				// Convert here.
				$new_name = $this->convert( $base_dir . $data['file'] );
				if ( $new_name ) {
					$this->convert_retina( $base_dir . $data['file'] );
					$this->convert_db_path( $base_dir . $data['file'], $new_name );
					$meta['sizes'][ $size ]['file']      = \wp_basename( $new_name );
					$meta['sizes'][ $size ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
				}
				ewwwio_debug_message( "converted $size from meta" );
			} // End foreach().
		} // End if().

		// Convert sizes from a custom theme.
		if ( isset( $meta['image_meta']['resized_images'] ) && ewww_image_optimizer_iterable( $meta['image_meta']['resized_images'] ) ) {
			ewwwio_debug_message( 'next up for conversion search: image_meta resizes' );
			$imagemeta_resize_pathinfo = pathinfo( $this->file );
			$imagemeta_resize_path     = '';
			foreach ( $meta['image_meta']['resized_images'] as $index => $imagemeta_resize ) {
				if ( isset( $sizes[ 'resized-images-' . $index ] ) ) {
					continue;
				}
				$imagemeta_resize_path = $imagemeta_resize_pathinfo['dirname'] . '/' . $imagemeta_resize_pathinfo['filename'] . '-' . $imagemeta_resize . '.' . $imagemeta_resize_pathinfo['extension'];
				$new_name              = $this->convert( $imagemeta_resize_path );
				if ( $new_name ) {
					$this->convert_retina( $imagemeta_resize_path );
					$this->convert_db_path( $imagemeta_resize_path, $new_name );
				}
			}
		}

		// and another custom theme.
		if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
			ewwwio_debug_message( 'next up for conversion search: custom_sizes' );
			$custom_sizes_pathinfo = pathinfo( $file_path );
			$custom_size_path      = '';
			foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
				if ( isset( $sizes[ 'custom-size-' . $dimensions ] ) ) {
					continue;
				}
				$custom_size_path = $custom_sizes_pathinfo['dirname'] . '/' . $custom_size['file'];
				$new_name         = $this->convert( $custom_size_path );
				if ( $new_name ) {
					$this->convert_retina( $custom_size_path );
					$this->convert_db_path( $custom_size_path, $new_name );
					$meta['custom_sizes'][ $dimensions ]['file'] = \wp_basename( $new_name );
				}
			}
		}

		// Possibly update translated replicas (WPML and the like).
		$translated_ids = ewww_image_optimizer_get_translated_media_ids( $this->attachment_id );
		if ( ewww_image_optimizer_iterable( $translated_ids ) ) {
			foreach ( $translated_ids as $translated_id ) {
				$this->sync_translated_meta( $translated_id, $meta );
			}
		}
		ewwwio_debug_message( 'end ' . __METHOD__ . '()' );
		return $meta;
	}

	/**
	 * Syncs metadata for translated replicas after successful conversion an image.
	 *
	 * @param int   $translated_id The attachment ID of a translated replica.
	 * @param array $meta The (source) attachment metadata that will be copied to the replica.
	 */
	public function sync_translated_meta( $translated_id, $meta ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		ewwwio_debug_message( "syncing $translated_id" );
		$tr_meta = wp_get_attachment_metadata( $translated_id );
		$changed = false;
		if ( ! ewww_image_optimizer_iterable( $tr_meta ) ) {
			return;
		}

		if ( ! empty( $meta['file'] ) && isset( $tr_meta['file'] ) ) {
			$tr_meta['file'] = $meta['file'];
			$changed         = true;
		}
		if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $data ) {
				ewwwio_debug_message( "checking to see if we should sync $size" );
				if ( strpos( $size, 'webp' ) === 0 ) {
					/* ewwwio_debug_message( 'skipping webp' ); */
					continue;
				}
				if ( ! empty( $data['file'] ) && isset( $tr_meta['sizes'][ $size ]['file'] ) ) {
					$tr_meta['sizes'][ $size ]['file'] = $data['file'];
					$changed                           = true;
				}
				if ( ! empty( $data['mime-type'] ) && isset( $tr_meta['sizes'][ $size ]['mime-type'] ) ) {
					$tr_meta['sizes'][ $size ]['mime-type'] = $data['mime-type'];
					$changed                                = true;
				}
				ewwwio_debug_message( "copied $size from meta for $translated_id" );
			} // End foreach().
		} // End if().

		// Another custom theme.
		if ( isset( $meta['custom_sizes'] ) && ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
			ewwwio_debug_message( 'next up for conversion sync: custom_sizes' );
			foreach ( $meta['custom_sizes'] as $dimensions => $custom_size ) {
				ewwwio_debug_message( "checking to see if we should sync $custom_size" );
				if ( ! empty( $custom_size['file'] ) && isset( $tr_meta['custom_sizes'][ $dimensions ]['file'] ) ) {
					$tr_meta['custom_sizes'][ $dimensions ]['file'] = $custom_size['file'];
					$changed                                        = true;
				}
			}
		}

		if ( $changed ) {
			ewwwio_debug_message( 'meta updated, saving' );
			wp_update_attachment_metadata( $translated_id, $tr_meta );
		}
		ewwwio_debug_message( 'end ' . __METHOD__ . '()' );
	}

	/**
	 * Restore a converted image using the metadata.
	 *
	 * @param array $meta The attachment metadata.
	 * @return array The updated attachment metadata.
	 */
	public function restore_with_meta( $meta ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			ewwwio_debug_message( 'invalid meta for restoration' );
			return $meta;
		}
		if ( ! $this->file || ! ewwwio_is_file( $this->file ) || ! $this->converted || ! ewwwio_is_file( $this->converted ) ) {
			ewwwio_debug_message( 'one of the files was not set for restoration (or did not exist)' );
			return $meta;
		}
		$this->restore_db_path( $this->file, $this->converted, $this->id );
		$converted_path = $this->file;
		ewwwio_delete_file( $this->file );
		$this->file      = $this->converted;
		$this->converted = $converted_path;
		$meta['file']    = trailingslashit( dirname( $meta['file'] ) ) . \wp_basename( $this->file );
		$this->update_converted_attachment( $meta );
		$meta = $this->restore_sizes( $meta );
		return $meta;
	}

	/**
	 * Restores all the 'resizes' of a converted image.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A new database connection with super powers.
	 *
	 * @param array $meta The attachment metadata.
	 * @return array $meta The updated attachment metadata.
	 */
	private function restore_sizes( $meta ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

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
			// Restore here.
			if ( empty( $size_queried['converted'] ) ) {
				continue;
			}
			$size_queried['path']      = ewww_image_optimizer_absolutize_path( $size_queried['path'] );
			$size_queried['converted'] = ewww_image_optimizer_absolutize_path( $size_queried['converted'] );

			$new_name = ( empty( $size_queried['converted'] ) ? '' : $size_queried['converted'] );
			if ( $new_name && ewwwio_is_file( $size_queried['path'] ) && ewwwio_is_file( $new_name ) ) {
				$this->restore_db_path( $size_queried['path'], $new_name, $size_queried['id'] );
				$this->replace_url( $new_name, $size_queried['path'] );
				if ( ewww_image_optimizer_iterable( $meta['sizes'] ) && is_array( $meta['sizes'][ $size_queried['resize'] ] ) ) {
					ewwwio_debug_message( 'updating regular size' );
					$meta['sizes'][ $size_queried['resize'] ]['file']      = \wp_basename( $new_name );
					$meta['sizes'][ $size_queried['resize'] ]['mime-type'] = ewww_image_optimizer_quick_mimetype( $new_name );
				} elseif ( ewww_image_optimizer_iterable( $meta['custom_sizes'] ) ) {
					$dimensions = str_replace( 'custom-size-', '', $size_queried['resize'] );
					if ( is_array( $meta['custom_sizes'][ $dimensions ] ) ) {
						ewwwio_debug_message( 'updating custom size' );
						$meta['custom_sizes'][ $dimensions ]['file'] = \wp_basename( $new_name );
					}
				}
				ewwwio_delete_file( $size_queried['path'] );
				// Look for any 'duplicate' sizes that have the same dimensions as the current queried size.
				if ( isset( $meta['sizes'] ) && ewww_image_optimizer_iterable( $meta['sizes'] ) ) {
					foreach ( $meta['sizes'] as $size => $data ) {
						if ( $meta['sizes'][ $size_queried['resize'] ]['height'] === $data['height'] && $meta['sizes'][ $size_queried['resize'] ]['width'] === $data['width'] ) {
							$meta['sizes'][ $size ]['file']      = $meta['sizes'][ $size_queried['resize'] ]['file'];
							$meta['sizes'][ $size ]['mime-type'] = $meta['sizes'][ $size_queried['resize'] ]['mime-type'];
						}
					}
				}
			}
			ewwwio_debug_message( "restored {$size_queried['resize']} from db query" );
			/* ewwwio_debug_message( print_r( $meta, true ) ); */
		} // End foreach().

		// Possibly update translated replicas (WPML and the like).
		$translated_ids = ewww_image_optimizer_get_translated_media_ids( $this->attachment_id );
		if ( ewww_image_optimizer_iterable( $translated_ids ) ) {
			foreach ( $translated_ids as $translated_id ) {
				$this->sync_translated_meta( $translated_id, $meta );
			}
		}
		return $meta;
	}

	/**
	 * Looks for retina images to convert.
	 *
	 * @param string $file The name of the non-retina file.
	 */
	private function convert_retina( $file ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$retina_path = ewww_image_optimizer_get_hidpi_path( $file );
		if ( ! $retina_path ) {
			return;
		}
		$new_name = $this->convert( $retina_path );
		if ( $new_name ) {
			$this->convert_db_path( $retina_path, $new_name );
		}
	}

	/**
	 * Converts a file using built-in PHP functions.
	 *
	 * @access public
	 *
	 * @param string $file The name of the file to convert.
	 * @param bool   $replace_url Default true. Run function to update database with new URL.
	 * @param bool   $check_size Default false. Whether the converted filesize should be compared to the original.
	 * @param string $newfile The name to be used for the converted file. Optional.
	 * @return string The name of the new file.
	 */
	public function convert( $file, $replace_url = true, $check_size = false, $newfile = '' ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( empty( $file ) ) {
			ewwwio_debug_message( 'no file provided to convert' );
			return false;
		}
		if ( ! ewwwio_is_file( $file ) ) {
			ewwwio_debug_message( "$file is not a file, cannot convert" );
			return false;
		}
		if ( ! is_writable( $file ) ) {
			ewwwio_debug_message( "$file is not writable, cannot convert" );
			return false;
		}
		$type = ewww_image_optimizer_mimetype( $file, 'i' );
		if ( ! $type ) {
			ewwwio_debug_message( 'could not find any functions for mimetype detection' );
			return false;
		}
		if ( strpos( $type, 'image' ) === false ) {
			ewwwio_debug_message( "cannot convert mimetype: $type" );
			return false;
		}
		switch ( $type ) {
			case 'image/jpeg':
				$png_size = 0;
				$newfile  = ! empty( $newfile ) && ! ewwwio_is_file( $newfile ) ? $newfile : $this->unique_filename( $file, '.png' );
				ewwwio_debug_message( "attempting to convert JPG to PNG: $newfile" );
				// Convert the JPG to PNG.
				if ( \ewwwio()->gmagick_support() ) {
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
				if ( ! $png_size && \ewwwio()->imagick_support() ) {
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
				if ( ! $png_size && \ewwwio()->gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromjpeg( $file ), $newfile );
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// If the PNG exists, and we didn't end up with an empty file.
				if ( ! $check_size && $png_size && ewwwio_is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( 'JPG to PNG successful' );
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
				} elseif ( $check_size && ewwwio_is_file( $newfile ) && $png_size < ewww_image_optimizer_filesize( $file ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( 'JPG to PNG successful, after comparing size' );
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					if ( ewwwio_is_file( $newfile ) ) {
						ewwwio_delete_file( $newfile );
					}
					return false;
				}
				break;
			case 'image/png':
				$jpg_size = 0;
				$newfile  = ! empty( $newfile ) && ! ewwwio_is_file( $newfile ) ? $newfile : $this->unique_filename( $file, '.jpg' );
				ewwwio_debug_message( "attempting to convert PNG to JPG: $newfile" );
				// If the user set a fill background for transparency.
				$background = ewww_image_optimizer_jpg_background();
				if ( $background ) {
					// Set background color for GD.
					$r = hexdec( '0x' . strtoupper( substr( $background, 0, 2 ) ) );
					$g = hexdec( '0x' . strtoupper( substr( $background, 2, 2 ) ) );
					$b = hexdec( '0x' . strtoupper( substr( $background, 4, 2 ) ) );
				} else {
					$r = '';
					$g = '';
					$b = '';
				}
				// If the user manually set the JPG quality.
				$quality = ewww_image_optimizer_jpg_quality();
				$quality = $quality ? $quality : '82';

				$magick_background = ewww_image_optimizer_jpg_background();
				if ( empty( $magick_background ) ) {
					$magick_background = '000000';
				}
				// Convert the PNG to a JPG with all the proper options.
				if ( \ewwwio()->gmagick_support() ) {
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
						$gmagick->setcompressionquality( $quality );
						$gmagick->writeimage( $newfile );
					} catch ( Exception $gmagick_error ) {
						ewwwio_debug_message( $gmagick_error->getMessage() );
					}
					$jpg_size = ewww_image_optimizer_filesize( $newfile );
				}
				if ( ! $jpg_size && \ewwwio()->imagick_support() ) {
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
				if ( ! $jpg_size && \ewwwio()->gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					// Retrieve the data from the PNG.
					$input = imagecreatefrompng( $file );
					// Retrieve the dimensions of the PNG.
					list( $width, $height ) = wp_getimagesize( $file );
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
					imagejpeg( $output, $newfile, $quality );
					$jpg_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted JPG size: $jpg_size" );
				// If the new JPG is smaller than the original PNG.
				if ( ! $check_size && $jpg_size && ewwwio_is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/jpeg' ) {
					ewwwio_debug_message( 'PNG to JPG successful' );
					// If the user wants originals delted after a conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original PNG.
						ewwwio_delete_file( $file );
					}
				} elseif ( $check_size && ewwwio_is_file( $newfile ) && $jpg_size < ewww_image_optimizer_filesize( $file ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/jpeg' ) {
					ewwwio_debug_message( 'PNG to JPG successful, after comparing size' );
					// If the user wants originals delted after a conversion.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original PNG.
						ewwwio_delete_file( $file );
					}
				} else {
					if ( ewwwio_is_file( $newfile ) ) {
						// Otherwise delete the new JPG.
						ewwwio_delete_file( $newfile );
					}
					return false;
				}
				break;
			case 'image/gif':
				$png_size = 0;
				$newfile  = ! empty( $newfile ) && ! ewwwio_is_file( $newfile ) ? $newfile : $this->unique_filename( $file, '.png' );
				ewwwio_debug_message( "attempting to convert GIF to PNG: $newfile" );
				// Convert the GIF to PNG.
				if ( \ewwwio()->gmagick_support() ) {
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
				if ( ! $png_size && \ewwwio()->imagick_support() ) {
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
				if ( ! $png_size && \ewwwio()->gd_support() ) {
					ewwwio_debug_message( 'converting with GD' );
					imagepng( imagecreatefromgif( $file ), $newfile );
					$png_size = ewww_image_optimizer_filesize( $newfile );
				}
				ewwwio_debug_message( "converted PNG size: $png_size" );
				// If the PNG exists, and we didn't end up with an empty file.
				if ( ! $check_size && $png_size && ewwwio_is_file( $newfile ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( 'GIF to PNG successful' );
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
				} elseif ( $check_size && ewwwio_is_file( $newfile ) && $png_size < ewww_image_optimizer_filesize( $file ) && ewww_image_optimizer_mimetype( $newfile, 'i' ) === 'image/png' ) {
					ewwwio_debug_message( 'GIF to PNG successful, after comparing size' );
					// Check to see if the user wants the originals deleted.
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_delete_originals' ) ) {
						// Delete the original JPG.
						ewwwio_delete_file( $file );
					}
				} else {
					ewwwio_debug_message( 'converted PNG is no good' );
					if ( ewwwio_is_file( $newfile ) ) {
						ewwwio_delete_file( $newfile );
					}
					return false;
				}
				break;
			default:
				return false;
		} // End switch().
		if ( $replace_url ) {
			$this->replace_url( $newfile, $file );
		}
		return $newfile;
	}

	/**
	 * Generate a unique filename for a converted image.
	 *
	 * @param string $file The original name of the file.
	 * @param string $fileext The extension of the new file.
	 * @return string The new filename.
	 */
	public function unique_filename( $file, $fileext ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Strip the file extension.
		$filename = preg_replace( '/\.\w+$/', '', $file );
		if ( ! empty( $this->converted ) && empty( $this->suffix ) ) {
			ewwwio_debug_message( "comparing original {$this->converted} and new {$this->file} to find suffix" );
			$original_basename  = preg_replace( '/\.\w+$/', '', $this->converted );
			$converted_basename = preg_replace( '/\.\w+$/', '', $this->file );
			if ( 0 === strpos( $converted_basename, $original_basename ) ) {
				$potential_suffix = str_replace( $original_basename, '', $converted_basename );
				ewwwio_debug_message( "original and new basenames are aligned, diff is '$potential_suffix'" );
				if ( strlen( $potential_suffix ) < 20 ) {
					$this->suffix = $potential_suffix;
				}
			}
		}
		ewwwio_debug_message( "current basename is $filename" );
		if ( empty( $this->suffix ) && ! ewwwio_is_file( $filename . $fileext ) ) {
			return $filename . $fileext;
		}
		ewwwio_debug_message( 'name collision or pre-existing suffix in play, taking evasive measures' );
		// Set the suffix to 1 ( but allow the user to override it ).
		$filenum = apply_filters( 'ewww_image_optimizer_converted_filename_suffix', $this->increment );
		// But it must be only letters, numbers, or underscores.
		$filenum = ( preg_match( '/^[\w\d]+$/', $filenum ) ? $filenum : 1 );
		$suffix  = ( ! empty( $filenum ) ? '-' . $filenum : '' );
		if ( ! empty( $this->suffix ) ) {
			$suffix            = $this->suffix;
			$potential_filenum = str_replace( '-', '', $suffix );
			if ( is_numeric( $potential_filenum ) ) {
				$filenum = $potential_filenum;
			}
		}
		$dimensions           = '';
		$hidpi_suffix         = '';
		$default_hidpi_suffix = apply_filters( 'ewww_image_optimizer_hidpi_suffix', '@2x' );
		// See if this is a retina image, and strip the suffix.
		if ( preg_match( "/$default_hidpi_suffix$/", $filename ) ) {
			// Strip the dimensions.
			$filename     = str_replace( $default_hidpi_suffix, '', $filename );
			$hidpi_suffix = $default_hidpi_suffix;
		}
		// See if this is a resize, and strip the dimensions.
		if ( preg_match( '/-\d+x\d+(-\d+)*$/', $filename, $fileresize ) ) {
			// Strip the dimensions.
			$filename   = str_replace( $fileresize[0], '', $filename );
			$dimensions = $fileresize[0];
		}
		// While a file exists with the current iterator.
		while ( \ewwwio_is_file( $filename . $suffix . $dimensions . $hidpi_suffix . $fileext ) ) {
			ewwwio_debug_message( "$filenum is not good enough, bumping" );
			// Bump the numerical appendage.
			$filenum++;
			$suffix = '-' . $filenum;
		}
		// All done, let's reconstruct the filename.
		ewwwio_memory( __METHOD__ );
		$this->increment = $filenum;
		$this->suffix    = $suffix;
		return $filename . $suffix . $dimensions . $hidpi_suffix . $fileext;
	}

	/**
	 * Update URLs for a converted image, and check alternate domains/sub-folders.
	 *
	 * @param string $new_path Optional. The URL to the newly converted image.
	 * @param string $old_path Optional. The URL to the old version of the image.
	 */
	public function replace_url( $new_path = '', $old_path = '' ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		$new = ( empty( $new_path ) ? $this->file : $new_path );
		$old = ( empty( $old_path ) ? $this->converted : $old_path );
		if ( empty( $new ) || empty( $old ) ) {
			return;
		}
		if ( empty( $new_path ) && empty( $old_path ) ) {
			$old_url = $this->url;
		} else {
			$old_url = trailingslashit( dirname( $this->url ) ) . \wp_basename( $old );
		}
		$new_url = trailingslashit( dirname( $this->url ) ) . \wp_basename( $new );
		// Construct the new URL based on the filename from the attachment metadata.
		ewwwio_debug_message( "old URL: $old_url" );
		ewwwio_debug_message( "new URL: $new_url" );
		if ( substr( $old_url, -1 ) === '/' || substr( $new_url, -1 ) === '/' ) {
			ewwwio_debug_message( 'could not obtain full url for current and previous image, bailing' );
			return;
		}

		$this->update_db_urls( $new_url, $old_url );

		if ( 2 === (int) \apply_filters( 'wpml_setting', false, 'language_negotiation_type' ) ) {
			$default_domain = wp_parse_url( get_site_url(), \PHP_URL_HOST );
			$wpml_domains   = \apply_filters( 'wpml_setting', array(), 'language_domains' );
			if ( ewww_image_optimizer_iterable( $wpml_domains ) ) {
				foreach ( $wpml_domains as $wpml_domain ) {
					$image_domain = wp_parse_url( $old_url, \PHP_URL_HOST );
					if ( empty( $wpml_domain ) || empty( $image_domain ) ) {
						continue;
					}
					ewwwio_debug_message( "checking image URLs with $wpml_domain" );
					if ( $image_domain === $wpml_domain ) {
						// Check the default domain if/when we detect that one of the language domains matches the domain we already had.
						$new_wpml_url = str_replace( $image_domain, $default_domain, $new_url );
						$old_wpml_url = str_replace( $image_domain, $default_domain, $old_url );
					} else {
						$new_wpml_url = str_replace( $image_domain, $wpml_domain, $new_url );
						$old_wpml_url = str_replace( $image_domain, $wpml_domain, $old_url );
					}
					if ( $new_url !== $new_wpml_url ) {
						$this->update_db_urls( $new_wpml_url, $old_wpml_url );
					}
				}
			}
		} elseif ( 1 === (int) \apply_filters( 'wpml_setting', false, 'language_negotiation_type' ) ) {
		}
		// TODO: Also (maybe) need to check sub-folder style, and maybe make that extensible? Plus be sure to check the default folder if the primary attachment is in a non-default language, just like we did for domains.
		do_action( 'ewwwio_conversion_replace_url_post', $new_url, $old_url );
	}

	/**
	 * Do the actual URL replacement in the database.
	 *
	 * @global object $wpdb
	 *
	 * @param string $new_url The URL to the newly converted image.
	 * @param string $old_url The URL to the old version of the image.
	 */
	public function update_db_urls( $new_url, $old_url ) {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );

		global $wpdb;
		// Retrieve any posts that link the image.
		$esql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %" . $wpdb->esc_like( $old_url ) . '%';
		ewwwio_debug_message( "replacing $old_url with $new_url in $wpdb->posts" );
		ewwwio_debug_message( "using query: $esql" );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_content LIKE %s", '%' . $wpdb->esc_like( $old_url ) . '%' ), ARRAY_A );
		if ( ewww_image_optimizer_iterable( $rows ) ) {
			// While there are posts to process.
			foreach ( $rows as $row ) {
				// Replace all occurences of the old URL with the new URL.
				$post_content = str_replace( $old_url, $new_url, $row['post_content'] );
				ewwwio_debug_message( "replacing $old_url with $new_url in post " . $row['ID'] );
				// Send the updated content back to the database.
				$wpdb->update(
					$wpdb->posts,
					array(
						'post_content' => $post_content,
					),
					array(
						'ID' => $row['ID'],
					)
				);
			}
		}
	}

	/**
	 * Updates records in the ewwwio_images table after conversion.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A new database connection with super powers.
	 *
	 * @param string $path The old path to search for.
	 * @param string $new_path The new path to update.
	 * @param array  $record Optional. Database record for the original image.
	 */
	private function convert_db_path( $path, $new_path, $record = false ) {
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
		if ( ! $record ) {
			$image_record = ewww_image_optimizer_find_already_optimized( $path );
			if ( ! empty( $image_record ) && is_array( $image_record ) && ! empty( $image_record['id'] ) ) {
				$record = $image_record;
			} else { // Insert a new record.
				$ewwwdb->insert(
					$ewwwdb->ewwwio_images,
					array(
						'path'          => ewww_image_optimizer_relativize_path( $new_path ),
						'converted'     => ewww_image_optimizer_relativize_path( $path ),
						'orig_size'     => ewww_image_optimizer_filesize( $new_path ),
						'attachment_id' => $this->attachment_id,
						'results'       => __( 'No savings', 'ewww-image-optimizer' ),
						'updated'       => gmdate( 'Y-m-d H:i:s' ),
						'updates'       => 0,
					)
				);
				return;
			}
		}
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'path'      => ewww_image_optimizer_relativize_path( $new_path ),
				'converted' => ewww_image_optimizer_relativize_path( $path ),
				'results'   => ewww_image_optimizer_image_results( $record['orig_size'], ewww_image_optimizer_filesize( $new_path ) ),
				'updates'   => 0,
				'trace'     => '',
			),
			array(
				'id' => $record['id'],
			)
		);
	}

	/**
	 * Updates records in the ewwwio_images table after the original image is restored.
	 *
	 * @global object $wpdb
	 * @global object $ewwwdb A new database connection with super powers.
	 *
	 * @param string $path The old path to search for.
	 * @param string $new_path The new path to update.
	 * @param int    $id Optional. Database record id for the original image.
	 */
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
				return;
			}
		}
		$ewwwdb->update(
			$ewwwdb->ewwwio_images,
			array(
				'path'       => ewww_image_optimizer_relativize_path( $new_path ),
				'converted'  => '',
				'image_size' => 0,
				'results'    => __( 'Original Restored', 'ewww-image-optimizer' ),
				'updates'    => 0,
				'trace'      => '',
				'level'      => null,
			),
			array(
				'id' => $id,
			)
		);
	}

	/**
	 * Perform an estimate of the time required to optimize an image.
	 *
	 * Estimates are based on the image type, file size, and optimization level using averages from API logs.
	 *
	 * @return int The number of seconds expected to compress the current image.
	 */
	public function time_estimate() {
		ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
		$time       = 0;
		$type       = ewww_image_optimizer_quick_mimetype( $this->file );
		$image_size = ( empty( $this->opt_size ) ? $this->orig_size : $this->opt_size );
		if ( empty( $image_size ) ) {
			$this->orig_size = ewww_image_optimizer_filesize( $this->file );
			$image_size      = $this->orig_size;
			if ( ! $image_size ) {
				return 5;
			}
		}
		switch ( $type ) {
			case 'image/jpeg':
				if ( $image_size > 10000000 ) { // greater than 10MB.
					$time += 20;
				} elseif ( $image_size > 5000000 ) { // greater than 5MB.
					$time += 10;
					if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 25;
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 7;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 2;
					}
				} elseif ( $image_size > 1000000 ) { // greater than 1MB.
					$time += 5;
					if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						if ( $image_size > 2000000 ) { // greater than 2MB.
							$time += 15;
						} else {
							$time += 11;
						}
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 6;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 2;
					}
				} else {
					$time++;
					if ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						if ( $image_size > 200000 ) { // greater than 200k.
							$time += 11;
						} else {
							$time += 5;
						}
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 3;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_jpg_level' ) ) {
						$time += 3;
					}
				} // End if().
				break;
			case 'image/png':
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) > 10 && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$time++;
				}
				if ( $image_size > 2500000 ) { // greater than 2.5MB.
					$time += 35;
				} elseif ( $image_size > 1000000 ) { // greater than 1MB.
					$time += 15;
					if ( 50 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 8;
					} elseif ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						/* $time++; */
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 10;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time++;
					}
				} elseif ( $image_size > 500000 ) { // greater than 500kb.
					$time += 7;
					if ( 50 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 5;
					} elseif ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						/* $time++; */
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 8;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time++;
					}
				} elseif ( $image_size > 100000 ) { // greater than 100kb.
					$time += 4;
					if ( 50 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 5;
					} elseif ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						/* $time++; */
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 9;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time++;
					}
				} else {
					$time++;
					if ( 50 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 2;
					} elseif ( 40 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time ++;
					} elseif ( 30 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time += 3;
					} elseif ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_png_level' ) ) {
						$time++;
					}
				} // End if().
				break;
			case 'image/gif':
				$time++;
				if ( 10 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_gif_level' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_cloud_key' ) ) {
					$time++;
				}
				if ( $image_size > 1000000 ) { // greater than 1MB.
					$time += 5;
				}
				break;
			case 'application/pdf':
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
					$time += 2;
				}
				if ( $image_size > 25000000 ) { // greater than 25MB.
					$time += 20;
					if ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
						$time += 16;
					}
				} elseif ( $image_size > 10000000 ) { // greater than 10MB.
					$time += 10;
					if ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
						$time += 20;
					}
				} elseif ( $image_size > 4000000 ) { // greater than 4MB.
					$time += 3;
					if ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
						$time += 12;
					}
				} elseif ( $image_size > 1000000 ) { // greater than 1MB.
					$time++;
					if ( 20 === (int) ewww_image_optimizer_get_option( 'ewww_image_optimizer_pdf_level' ) ) {
						$time += 10;
					}
				}
				break;
			default:
				$time = 30;
		} // End switch().
		ewwwio_debug_message( "estimated time for this image is $time" );
		return $time;
	}
}
