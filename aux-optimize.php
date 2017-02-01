<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// displays the 'Optimize Everything Else' section of the Bulk Optimize page
function ewww_image_optimizer_aux_images () {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $wpdb;
	// Retrieve the value of the 'aux resume' option and set the button text for the form to use
	$aux_resume = get_option( 'ewww_image_optimizer_aux_resume' );
	if ( ! empty( $aux_resume ) && $aux_resume !== 'scanning' ) {
		$button_text = esc_attr__( 'Resume previous optimization', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	} else {
		$button_text = esc_attr__( 'Scan and optimize', EWWW_IMAGE_OPTIMIZER_DOMAIN );
	}
	// find out if the auxiliary image table has anything in it
	$already_optimized = ewww_image_optimizer_aux_images_table_count();
	// see if the auxiliary image table needs converting from md5sums to image sizes
	$column_query = "SHOW COLUMNS FROM $wpdb->ewwwio_images LIKE 'image_md5'";
	$column = $wpdb->get_row( $column_query, ARRAY_N );
	if ( ! empty( $column ) ) {
		ewwwio_debug_message( "image_md5 column exists, checking for image_md5 values"  );
		$convert_query = "SELECT image_md5 FROM $wpdb->ewwwio_images WHERE image_md5 <> ''";
		$db_convert = $wpdb->get_results( $convert_query, ARRAY_N );
	}
//	ewwwio_debug_message( print_r( $column, true ) );
	// check the last time the auxiliary optimizer was run
	$lastaux = get_option( 'ewww_image_optimizer_aux_last' );
	// set the timezone according to the blog settings
	$site_timezone = get_option( 'timezone_string' );
	if ( empty( $site_timezone ) ) {
		$site_timezone = 'UTC';
	}
	date_default_timezone_set( $site_timezone );
	?>
		<div id="ewww-aux-forms">
		<?php if ( ! empty( $db_convert ) ) { ?>
			<p class="ewww-bulk-info"><?php esc_html_e( 'The database schema has changed, you need to convert to the new format.', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></p>
			<form method="post" id="ewww-aux-convert" class="ewww-bulk-form" action="">
				<?php wp_nonce_field( 'ewww-image-optimizer-aux-images-convert', 'ewww_wpnonce' ); ?>
				<input type="hidden" name="ewww_convert" value="1">
				<button id="ewww-table-convert" type="submit" class="button-secondary action"><?php esc_html_e( 'Convert Table', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
			</form>
		<?php } ?>	
		<?php if ( ! empty( $lastaux ) ) { ?>
	<!--		<p id="ewww-lastaux" class="ewww-bulk-info"><?php printf( esc_html__( 'Last optimization was completed on %1$s at %2$s and optimized %3$d images', EWWW_IMAGE_OPTIMIZER_DOMAIN ), date( get_option( 'date_format' ), $lastaux[0] ), date( get_option( 'time_format' ), $lastaux[0] ), (int) $lastaux[1] ); ?></p>-->
		<?php } ?>
<?php		// if the 'bulk resume' option was not empty, offer to reset it so the user can start back from the beginning
		if ( empty( $already_optimized ) ) {
			$display = ' style="display:none"';
		} else {
			$display = '';
		}
?>
			<p id="ewww-table-info" class="ewww-bulk-info"<?php echo "$display>"; printf( esc_html__( 'The plugin keeps track of already optimized images to prevent re-optimization. There are %d images that have been optimized so far.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $already_optimized ); ?></p>
			<form id="ewww-show-table" class="ewww-bulk-form" method="post" action=""<?php echo $display; ?>>
				<button type="submit" class="button-secondary action"><?php esc_html_e( 'Show Optimized Images', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></button>
			</form>
			<div class="tablenav ewww-aux-table" style="display:none">
			<div class="tablenav-pages ewww-aux-table">
			<span class="displaying-num ewww-aux-table"></span>
			<span id="paginator" class="pagination-links ewww-aux-table">
				<a id="first-images" class="first-page" style="display:none">&laquo;</a>
				<a id="prev-images" class="prev-page" style="display:none">&lsaquo;</a>
				<?php esc_html_e( 'page', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?> <span class="current-page"></span> <?php esc_html_e( 'of', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?> 
				<span class="total-pages"></span>
				<a id="next-images" class="next-page" style="display:none">&rsaquo;</a>
				<a id="last-images" class="last-page" style="display:none">&raquo;</a>
			</span>
			</div>
			</div>
			<div id="ewww-bulk-table" class="ewww-aux-table"></div>
			<span id="ewww-pointer" style="display:none">0</span>
		</div>
	</div>
<?php
	if ( ewww_image_optimizer_get_option ( 'ewww_image_optimizer_debug' ) ) {
		global $ewww_debug;
		echo '<div id="ewww-debug-info" style="clear:both;background:#ffff99;margin-left:-20px;padding:10px">' . $ewww_debug . '</div>';
	}
	ewwwio_memory( __FUNCTION__ );
}

// displays 50 records from the auxiliary images table
function ewww_image_optimizer_aux_images_table() {
	// verify that an authorized user has called function
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	} 
	global $wpdb;
	if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
		ewww_image_optimizer_db_init();
		global $ewwwdb;
	} else {
		$ewwwdb = $wpdb;
	}
	$offset = 50 * (int) $_POST['ewww_offset'];
	$query = "SELECT path,results,image_size,id FROM $ewwwdb->ewwwio_images WHERE pending=0 ORDER BY id DESC LIMIT $offset,50";
	$already_optimized = $ewwwdb->get_results( $query, ARRAY_N );
        $upload_info = wp_upload_dir();
	$upload_path = $upload_info['basedir'];
	echo '<br /><table class="wp-list-table widefat media" cellspacing="0"><thead><tr><th>&nbsp;</th><th>' . esc_html__( 'Filename', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th><th>' . esc_html__( 'Image Type', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th><th>' . esc_html__( 'Image Optimizer', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</th></tr></thead>';
	$alternate = true;
	foreach ( $already_optimized as $optimized_image ) {
		$image_name = str_replace( ABSPATH, '', $optimized_image[0] );
		$image_url = esc_url( trailingslashit( get_site_url() ) . $image_name );
		$savings = esc_html( $optimized_image[1] );
		// if the path given is not the absolute path
		if ( file_exists( $optimized_image[0] ) ) {
			// retrieve the mimetype of the attachment
			$type = ewww_image_optimizer_mimetype( $optimized_image[0], 'i' );
			// get a human readable filesize
			$file_size = size_format( $optimized_image[2], 2 );
			$file_size = str_replace( '.00 B ', ' B', $file_size );
?>			<tr<?php if ( $alternate ) { echo " class='alternate'"; } ?> id="ewww-image-<?php echo $optimized_image[3]; ?>">
				<td style='width:80px' class='column-icon'><img width='50' height='50' src="<?php echo $image_url; ?>" /></td>
				<td class='title'>...<?php echo $image_name; ?></td>
				<td><?php echo $type; ?></td>
				<td><?php echo "$savings <br>" . sprintf( esc_html__( 'Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file_size ); ?><br><a class="removeimage" onclick="ewwwRemoveImage( <?php echo $optimized_image[3]; ?> )"><?php esc_html_e( 'Remove from table', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></a></td>
			</tr>
<?php			$alternate = ! $alternate;
		} elseif ( strpos( $optimized_image[0], 's3' ) === 0 ) {
			// retrieve the mimetype of the attachment
			$type = esc_html__( 'Amazon S3 image', EWWW_IMAGE_OPTIMIZER_DOMAIN );
			// get a human readable filesize
			$file_size = size_format( $optimized_image[2], 2 );
			$file_size = str_replace( '.00 B ', ' B', $file_size );
?>			<tr<?php if ( $alternate ) { echo " class='alternate'"; } ?> id="ewww-image-<?php echo $optimized_image[3]; ?>">
				<td style='width:80px' class='column-icon'>&nbsp;</td>
				<td class='title'><?php echo $image_name; ?></td>
				<td><?php echo $type; ?></td>
				<td><?php echo "$savings <br>" . sprintf( esc_html__( 'Image Size: %s', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $file_size ); ?><br><a class="removeimage" onclick="ewwwRemoveImage( <?php echo $optimized_image[3]; ?> )"><?php esc_html_e( 'Remove from table', EWWW_IMAGE_OPTIMIZER_DOMAIN ); ?></a></td>
			</tr>
<?php			$alternate = ! $alternate;
		}
	}
	echo '</table>';
	ewwwio_memory( __FUNCTION__ );
	ewww_image_optimizer_debug_log();
	die();
}

// removes an image from the auxiliary images table
function ewww_image_optimizer_aux_images_remove() {
	// verify that an authorized user has called function
	if ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) ) {
		wp_die( esc_html__( 'Access token has expired, please reload the page.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	} 
	global $wpdb;
	if ( $wpdb->delete( $wpdb->ewwwio_images, array( 'id' => $_POST['ewww_image_id'] ) ) ) {
		echo "1";
	}
	ewwwio_memory( __FUNCTION__ );
	die();
}

// find the number of optimized images in the ewwwio_images table
function ewww_image_optimizer_aux_images_table_count() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE pending=0 AND image_size > 0" );
	if ( ! empty( $_REQUEST['ewww_inline'] ) ) {
		echo $count;
		ewwwio_memory( __FUNCTION__ );
		die();
	}
	ewwwio_memory( __FUNCTION__ );
	return $count;
}

// find the number of un-optimized images in the ewwwio_images table
function ewww_image_optimizer_aux_images_table_count_pending() {
	global $wpdb;
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->ewwwio_images WHERE pending=1" );
	return $count;
}

// remove all un-optimized images from the ewwwio_images table
function ewww_image_optimizer_delete_pending() {
	global $wpdb;
	$wpdb->query( "DELETE from $wpdb->ewwwio_images WHERE pending=1 AND (image_size IS NULL OR image_size = 0)" );
	$wpdb->update( $wpdb->ewwwio_images,
				array(
					'pending' => 0,
				),
				array(
					'pending' => 1,
				) );
}

// scan a folder for images and return them as an array
function ewww_image_optimizer_image_scan( $dir, $started = 0 ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$folders_completed = get_option( 'ewww_image_optimizer_aux_folders_completed' );
	if ( ! is_array( $folders_completed ) ) {
		$folders_completed = array();
	}
	if ( in_array( $dir, $folders_completed ) ) {
		return;
	}
	global $wpdb;
	global $optimized_list;
	$images = array();
	$reset_images = array();
	if ( ! is_dir( $dir ) ) {
		return; //$images;
	}
	ewwwio_debug_message( "scanning folder for images: $dir" );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ), RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
	$start = microtime( true );
	if ( empty( $optimized_list ) || ! is_array( $optimized_list ) ) {
		ewww_image_optimizer_optimized_list();
	}
	$file_counter = 0; // track total files
	$image_count = 0; // track number of files since last queue update
	$enabled_types = array();
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_jpg_level' ) ) {
		$enabled_types[] = 'image/jpeg';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_png_level' ) ) {
		$enabled_types[] = 'image/png';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_gif_level' ) ) {
		$enabled_types[] = 'image/gif';
	}
	if ( ewww_image_optimizer_get_option('ewww_image_optimizer_pdf_level' ) ) {
		$enabled_types[] = 'application/pdf';
	}
	foreach ( $iterator as $file ) {
		if ( get_transient( 'ewww_image_optimizer_aux_iterator' ) && get_transient( 'ewww_image_optimizer_aux_iterator' ) > $file_counter ) {
			continue;
		}
		if ( $started && ! empty( $_REQUEST['ewww_scan'] ) && $file_counter % 100 === 0 && microtime( true ) - $started > apply_filters( 'ewww_image_optimizer_timeout', 15 ) ) {
			if ( ! empty( $reset_images ) ) {
				$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' );
			}
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // keep track of where we left off, minus 20 to be safe
			$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
			die( json_encode( array( 'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>", 'notice' => '' ) ) );
		}
		// TODO: can we tailor this for scheduled opt also?
		if ( ! empty( $_REQUEST['ewww_scan'] ) && $file_counter % 100 === 0 && ! ewwwio_check_memory_available( 2097000 ) ) {
			if ( $file_counter < 100 ) {
				die( json_encode( array( 'error' => esc_html__( 'Stage 2 unable to complete due to memory restrictions. Please increase the memory_limit setting for PHP and try again.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) ) ) );
			}
			if ( ! empty( $reset_images ) ) {
				$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' );
			}
			if ( ! empty( $images ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
			}
			set_transient( 'ewww_image_optimizer_aux_iterator', $file_counter - 20, 300 ); // keep track of where we left off, minus 20 to be safe
			$loading_image = plugins_url('/images/wpspin.gif', __FILE__);
			die( json_encode( array( 'remaining' => '<p>' . esc_html__( 'Stage 2, please wait.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . "&nbsp;<img src='$loading_image' /></p>", 'notice' => '' ) ) );
		}
		$file_counter++;
		if ( $file->isFile() ) {
			$path = $file->getPathname();
			if ( preg_match( '/(\/|\\\\)\./', $path ) && apply_filters( 'ewww_image_optimizer_ignore_hidden_files', true ) ) {
				continue;
			}
			$mime = ewww_image_optimizer_quick_mimetype( $path );
			if ( ! in_array( $mime, $enabled_types ) ) {
				continue;
			}
			if ( apply_filters( 'ewww_image_optimizer_bypass', false, $path ) === true ) {
				ewwwio_debug_message( "skipping $path as instructed" );
				continue;
			}

			$already_optimized = false;
			if ( $optimized_list === 'low_memory' ) {
				$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
			}

			if ( $already_optimized || isset( $optimized_list[ $path ] ) ) {
				if ( ! $already_optimized ) {
					$already_optimized = $optimized_list[ $path ];
				}
				if ( ! empty( $already_optimized['pending'] ) ) {
					ewwwio_debug_message( "pending record for $path" );
					continue;
				}
				$image_size = $file->getSize();
				if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
					ewwwio_debug_message( "file skipped due to filesize: $path" );
					continue;
				}
				if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				if ( $already_optimized['image_size'] == $image_size && empty( $_REQUEST['ewww_force'] ) ) {
					ewwwio_debug_message( "match found for $path" );
					continue;
				} else {
					$reset_images[] = (int) $already_optimized['id'];
					ewwwio_debug_message( "mismatch found for $path, db says " . $already_optimized['image_size'] . " vs. current $image_size" );
				}
			} else {
				$image_size = $file->getSize();
				if ( $image_size < ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_size' ) ) {
					ewwwio_debug_message( "file skipped due to filesize: $path" );
					continue;
				}
				if ( $mime == 'image/png' && ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) && $image_size > ewww_image_optimizer_get_option( 'ewww_image_optimizer_skip_png_size' ) ) {
					ewwwio_debug_message( "file skipped due to PNG filesize: $path" );
					continue;
				}
				ewwwio_debug_message( "queuing $path" );
				if ( seems_utf8( $path ) ) {
					$utf8_file_path = $path;
				} else {
					$utf8_file_path = utf8_encode( $path );
				}
				//$images[] = "('" . esc_sql( $utf8_file_path ) . "',$image_size,1)";
				$images[] = array(
					'path' => $utf8_file_path,
					'orig_size' => $image_size,
					'pending' => 1,
				);
				$image_count++;
			}
			if ( $image_count > 1000 ) {
				// let's dump what we have so far to the db
				$image_count = 0;
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
				//$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,orig_size,pending) VALUES " . implode( ',', $images );
				//$wpdb->query( $insert_query );
				$images = array();
			}
		}
//		ewww_image_optimizer_debug_log();
	}
	if ( ! empty( $images ) ) {
		ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $images, array( '%s', '%d', '%d' ) );
		//$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,orig_size,pending) VALUES " . implode( ',', $images );
		//$wpdb->query( $insert_query );
	}
	if ( ! empty( $reset_images ) ) {
		$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id IN (" . implode( ',', $reset_images ) . ')' );
	}
	delete_transient( 'ewww_image_optimizer_aux_iterator' );
	$end = microtime( true ) - $start;
        ewwwio_debug_message( "query time for $file_counter files (seconds): $end" );
	clearstatcache();
	ewwwio_memory( __FUNCTION__ );
	$folders_completed[] = $dir;
	update_option( 'ewww_image_optimizer_aux_folders_completed', $folders_completed, false );
}

// convert all records in table to use filesize rather than md5sum
function ewww_image_optimizer_aux_images_convert() {
	global $wpdb;
	$query = "SELECT id,path,image_md5 FROM $wpdb->ewwwio_images";
	$old_records = $wpdb->get_results( $query, ARRAY_A );
	foreach ( $old_records as $record ) {
		if ( empty( $record['image_md5'] ) ) {
			continue;
		}
		$image_md5 = md5_file( $record['path'] );
		if ( $image_md5 === $record['image_md5'] ) {
			$filesize = filesize( $record['path'] );
			$wpdb->update( $wpdb->ewwwio_images,
				array(
					'image_md5' => null,
					'image_size' => $filesize,
				),
				array(
					'id' => $record['id'],
				) );
		} else {
			$wpdb->delete( $wpdb->ewwwio_images,
				array(
					'id' => $record['id'],
				) );
		}
	}
}

// prepares the bulk operation and includes the javascript functions
function ewww_image_optimizer_aux_images_script( $hook = '' ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// make sure we are being called from the proper page
	if ( 'ewww-image-optimizer-auto' !== $hook && empty( $_REQUEST['ewww_scan'] ) ) {
		return;
	}
	session_write_close();
	if ( ! empty( $_REQUEST['ewww_force'] ) ) {
		ewwwio_debug_message( 'forcing re-optimize: true' );
	}
	// retrieve the time when the scan starts
	$started = microtime( true );
	if ( ! get_transient( 'ewww_image_optimizer_skip_aux' ) ) {
		update_option( 'ewww_image_optimizer_aux_resume', 'scanning' );
		ewwwio_debug_message( 'getting fresh list of files to optimize' );
		// collect a list of images from the current theme
		$child_path = get_stylesheet_directory();
		$parent_path = get_template_directory();
		ewww_image_optimizer_image_scan( $child_path, $started );
		if ( $child_path !== $parent_path ) {
			ewww_image_optimizer_image_scan( $parent_path, $started );
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// need to include the plugin library for the is_plugin_active function
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		// collect a list of images for buddypress
		if ( is_plugin_active( 'buddypress/bp-loader.php' ) || is_plugin_active_for_network( 'buddypress/bp-loader.php' ) ) {
			// get the value of the wordpress upload directory
		        $upload_dir = wp_upload_dir();
			// scan the 'avatars' and 'group-avatars' folders for images
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/avatars', $started );
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/group-avatars', $started );
		}
		if ( is_plugin_active( 'buddypress-activity-plus/bpfb.php' ) || is_plugin_active_for_network( 'buddypress-activity-plus/bpfb.php' ) ) {
			// get the value of the wordpress upload directory
		        $upload_dir = wp_upload_dir();
			ewww_image_optimizer_image_scan( $upload_dir['basedir'] . '/bpfb', $started );
		}
		if ( is_plugin_active( 'grand-media/grand-media.php' ) || is_plugin_active_for_network( 'grand-media/grand-media.php' ) ) {
			// scan the grand media folder for images
			ewww_image_optimizer_image_scan( WP_CONTENT_DIR . '/grand-media', $started );
		}
		if ( is_plugin_active( 'wp-symposium/wp-symposium.php' ) || is_plugin_active_for_network( 'wp-symposium/wp-symposium.php' ) ) {
			ewww_image_optimizer_image_scan( get_option( 'symposium_img_path', $started ) );
		}
		if ( is_plugin_active( 'ml-slider/ml-slider.php' ) || is_plugin_active_for_network( 'ml-slider/ml-slider.php' ) ) {
			global $wpdb;
			$slide_paths = array();
			$slides = $wpdb->get_col( 
				"
				SELECT wpposts.ID 
				FROM $wpdb->posts wpposts 
				INNER JOIN $wpdb->term_relationships term_relationships
						ON wpposts.ID = term_relationships.object_id
				INNER JOIN $wpdb->terms wpterms 
						ON term_relationships.term_taxonomy_id = wpterms.term_id
				INNER JOIN $wpdb->term_taxonomy term_taxonomy
						ON wpterms.term_id = term_taxonomy.term_id
				WHERE 	term_taxonomy.taxonomy = 'ml-slider'
					AND wpposts.post_type = 'attachment'
				"
			);
			if ( ewww_image_optimizer_iterable( $slides ) ) {
				foreach ( $slides as $slide ) {
					$type = get_post_meta( $slide, 'ml-slider_type', true );
					$type = $type ? $type : 'image'; // backwards compatibility, fall back to 'image'
					if ( $type != 'image' ) {
						continue;
					}
					$backup_sizes = get_post_meta( $slide, '_wp_attachment_backup_sizes', true );
					if ( ewww_image_optimizer_iterable( $backup_sizes ) ) {
						foreach ( $backup_sizes as $backup_size => $meta ) {
							if ( preg_match( '/resized-/', $backup_size ) ) {
								$path = $meta['path'];
								$image_size = ewww_image_optimizer_filesize( $path );
								if ( ! $image_size ) {
									continue;
								}
								$already_optimized = ewww_image_optimizer_find_already_optimized( $path );
								// pending record already present
								if ( ! empty( $already_optimized ) && empty( $already_optimized['image_size'] ) ) {
									continue;
								}
								$mimetype = ewww_image_optimizer_mimetype( $path, 'i' );
								// brand new image
								if ( preg_match( '/^image\/(jpeg|png|gif)/', $mimetype ) && empty( $already_optimized ) ) {
									$slide_paths[] = array( 'path' => $path, 'orig_size' => $image_size );
								// changed image
								} elseif ( preg_match( '/^image\/(jpeg|png|gif)/', $mimetype ) && ! empty( $already_optimized ) && $already_optimized['image_size'] != $image_size ) {
									$wpdb->query( "UPDATE $wpdb->ewwwio_images SET pending = 1 WHERE id = {$already_optimized['id']}" );
								}
							}
						}
					}
				}
			}
			if ( ! empty( $slide_paths ) ) {
				ewww_image_optimizer_mass_insert( $wpdb->ewwwio_images, $slide_paths, array( '%s', '%d' ) );
	//			$insert_query = "INSERT INTO $wpdb->ewwwio_images (path,orig_size) VALUES " . implode( ',', $slide_paths );
	//			$wpdb->query( $insert_query );
			}
		}
		// collect a list of images in auxiliary folders provided by user
		if ( $aux_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_aux_paths' ) ) {
			if ( ewww_image_optimizer_iterable( $aux_paths ) ) {
				foreach ( $aux_paths as $aux_path ) {
					ewww_image_optimizer_image_scan( $aux_path, $started );
				}
			}
		}
		// scan images in two most recent media library folders if the option is enabled, and this is a scheduled optimization
		if ( 'ewww-image-optimizer-auto' == $hook && ewww_image_optimizer_get_option( 'ewww_image_optimizer_include_media_paths' ) ) {
			// retrieve the location of the wordpress upload folder
			$upload_dir = wp_upload_dir();
			// retrieve the path of the upload folder
			$upload_path = $upload_dir['basedir'];
			$this_month = date( 'm' );
			$this_year = date( 'Y' );
			ewww_image_optimizer_image_scan( "$upload_path/$this_year/$this_month/", $started );
			if ( class_exists( 'DateTime' ) ) {
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1M' ) );
				$last_year = $date->format( 'Y' );
				$last_month = $date->format( 'm' );
				ewww_image_optimizer_image_scan( "$upload_path/$last_year/$last_month/", $started );
			}

		}
	}
	$image_count = ewww_image_optimizer_aux_images_table_count_pending();
	ewwwio_debug_message( "found $image_count images to optimize while scanning" );
	update_option( 'ewww_image_optimizer_aux_folders_completed', array(), false );
	update_option( 'ewww_image_optimizer_aux_resume', '' );
	update_option( 'ewww_image_optimizer_bulk_resume', '' );
	ewww_image_optimizer_debug_log();
	if ( ! empty( $_REQUEST['ewww_scan'] ) ) {
		ewwwio_memory( __FUNCTION__ );
		$ready_msg = sprintf( esc_html__( 'There are %d images ready to optimize.', EWWW_IMAGE_OPTIMIZER_DOMAIN ), $image_count );
		die( json_encode( array( 'ready' => $image_count, 'message' => $ready_msg ) ) );
	}
	ewwwio_memory( __FUNCTION__ );
	return $image_count;
}

// called by javascript to initialize some output
function ewww_image_optimizer_aux_images_initialize( $auto = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	session_write_close();
	$output = array(); 
	// update the 'aux resume' option to show that an operation is in progress
	update_option( 'ewww_image_optimizer_aux_resume', 'true' );
	// store the time and number of images for later display
	$count = ewww_image_optimizer_aux_images_table_count_pending();
	update_option( 'ewww_image_optimizer_aux_last', array( time(), $count ) );
	// let the user know that we are beginning
	if ( ! $auto ) {
		// generate the WP spinner image for display
		$loading_image = plugins_url( '/images/wpspin.gif', __FILE__ );
/*		$attachments = get_option( 'ewww_image_optimizer_aux_attachments' );
		if ( ! is_array( $attachments ) && ! empty( $attachments ) ) {
			$attachments = unserialize( $attachments );
		}
		if ( ! is_array( $attachments ) ) {
			$output['error'] = esc_html__( 'Error retrieving list of images' );
			echo json_encode( $output );
			die();
		}*/
		global $wpdb;
		if ( strpos( $wpdb->charset, 'utf8' ) === false ) {
			ewww_image_optimizer_db_init();
			global $ewwwdb;
		} else {
			$ewwwdb = $wpdb;
		}
		$file = $ewwwdb->get_var( "SELECT path FROM $ewwwdb->ewwwio_images WHERE pending=1 LIMIT 1" );//array_shift( $attachments );
		$output['results'] = "<p>" . esc_html__( 'Optimizing', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . " <b>$file</b>&nbsp;<img src='$loading_image' alt='loading'/></p>";
		echo json_encode( $output );
		ewwwio_memory( __FUNCTION__ );
		die();
	}
}

// called by javascript to cleanup after ourselves
function ewww_image_optimizer_aux_images_cleanup( $auto = false ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// verify that an authorized user has started the optimizer
	$permissions = apply_filters( 'ewww_image_optimizer_bulk_permissions', '' );
	if ( ! $auto && ( ! wp_verify_nonce( $_REQUEST['ewww_wpnonce'], 'ewww-image-optimizer-bulk' ) || ! current_user_can( $permissions ) ) ) {
		wp_die( esc_html__( 'Access denied.', EWWW_IMAGE_OPTIMIZER_DOMAIN ) );
	}
	$stored_last = get_option( 'ewww_image_optimizer_aux_last' );
	update_option( 'ewww_image_optimizer_aux_last', array( time(), $stored_last[1] ) );
	// all done, so we can update the bulk options with empty values
	update_option( 'ewww_image_optimizer_aux_resume', '' );
//	update_option( 'ewww_image_optimizer_aux_attachments', '', false );
	if ( ! $auto ) {
		// and let the user know we are done
		echo '<p><b>' . esc_html__( 'Finished', EWWW_IMAGE_OPTIMIZER_DOMAIN ) . '</b></p>';
		ewwwio_memory( __FUNCTION__ );
		die();
	}
}

//add_action( 'admin_enqueue_scripts', 'ewww_image_optimizer_aux_images_script' );
//add_action( 'wp_ajax_bulk_aux_images_scan', 'ewww_image_optimizer_aux_images_script' );
add_action( 'wp_ajax_bulk_aux_images_table', 'ewww_image_optimizer_aux_images_table' );
add_action( 'wp_ajax_bulk_aux_images_table_count', 'ewww_image_optimizer_aux_images_table_count' );
add_action( 'wp_ajax_bulk_aux_images_remove', 'ewww_image_optimizer_aux_images_remove' );
//add_action( 'wp_ajax_bulk_aux_images_init', 'ewww_image_optimizer_aux_images_initialize' );
//add_action( 'wp_ajax_bulk_aux_images_loop', 'ewww_image_optimizer_aux_images_loop' );
//add_action( 'wp_ajax_bulk_aux_images_cleanup', 'ewww_image_optimizer_aux_images_cleanup' );
?>
