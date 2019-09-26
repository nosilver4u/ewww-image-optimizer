jQuery(document).ready(function($) {
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_table_count_action = 'bulk_aux_images_table_count';
	$('#ewww-show-table').submit(function() {
		var ewww_pointer = 0;
		var ewww_total_pages = Math.ceil(ewww_vars.image_count / 50);
		$('#ewww-table-info').hide();
		$('#ewww-show-table').hide();
		$('.ewww-aux-table').show();
		if (ewww_vars.image_count >= 50) {
			$('.tablenav').show();
			$('#next-images').show();
			$('.last-page').show();
		}
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
	        };
		$('.displaying-num').text(ewww_vars.count_string);
		$.post(ajaxurl, ewww_table_data, function(response) {
			$('#ewww-bulk-table').html(response);
		});
		$('.current-page').text(ewww_pointer + 1);
		$('.total-pages').text(ewww_total_pages);
		$('#ewww-pointer').text(ewww_pointer);
		return false;
	});
	$('#next-images').click(function() {
		var ewww_pointer = $('#ewww-pointer').text();
		ewww_pointer++;
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			$('#ewww-bulk-table').html(response);
		});
		if (ewww_vars.image_count <= ((ewww_pointer + 1) * 50)) {
			$('#next-images').hide();
			$('.last-page').hide();
		}
		$('.current-page').text(ewww_pointer + 1);
		$('#ewww-pointer').text(ewww_pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('#prev-images').click(function() {
		var ewww_pointer = $('#ewww-pointer').text();
		ewww_pointer--;
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			$('#ewww-bulk-table').html(response);
		});
		if (!ewww_pointer) {
			$('#prev-images').hide();
			$('.first-page').hide();
		}
		$('.current-page').text(ewww_pointer + 1);
		$('#ewww-pointer').text(ewww_pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('.last-page').click(function() {
		var ewww_pointer = $('.total-pages').text();
		ewww_pointer--;
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			$('#ewww-bulk-table').html(response);
		});
		$('#next-images').hide();
		$('.last-page').hide();
		$('.current-page').text(ewww_pointer + 1);
		$('#ewww-pointer').text(ewww_pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('.first-page').click(function() {
		var ewww_pointer = 0;
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			$('#ewww-bulk-table').html(response);
		});
		$('#prev-images').hide();
		$('.first-page').hide();
		$('.current-page').text(ewww_pointer + 1);
		$('#ewww-pointer').text(ewww_pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('#ewww-clear-table').submit(function() {
	        var ewww_table_data = {
	                action: 'bulk_aux_images_table_clear',
			ewww_wpnonce: ewww_vars._wpnonce,
	        };
		if (confirm(ewww_vars.erase_warning)) {
			$.post(ajaxurl, ewww_table_data, function(response) {
				$('#ewww-table-info').hide();
				$('#ewww-show-table').hide();
				$('#ewww-clear-table').hide();
				$('#ewww-clear-table-info').html(response);
			});
		}
		return false;
	});
});
function ewwwRemoveImage(imageID) {
	var ewww_image_removal = {
		action: 'bulk_aux_images_remove',
		ewww_wpnonce: ewww_vars._wpnonce,
		ewww_image_id: imageID,
	};
	jQuery.post(ajaxurl, ewww_image_removal, function(response) {
		if(response == '1') {
			jQuery('#ewww-image-' + imageID).remove();
			var ewww_prev_count = ewww_vars.image_count;
			ewww_vars.image_count--;
			ewww_vars.count_string = ewww_vars.count_string.replace( ewww_prev_count, ewww_vars.image_count );
			jQuery('.displaying-num').text(ewww_vars.count_string);
		} else {
			alert(ewww_vars.remove_failed);
		}
	});
}
function ewwwRestoreImage(imageID) {
	var ewww_image_restore = {
		action: 'ewww_manual_cloud_restore_single',
		ewww_wpnonce: ewww_vars._wpnonce,
		ewww_image_id: imageID,
	};
	var original_html = jQuery('#ewww-image-' + imageID + ' td:last-child').html();
	jQuery('#ewww-image-' + imageID + ' td:last-child').html(ewww_vars.restoring);
	jQuery.post(ajaxurl, ewww_image_restore, function(response) {
		var is_json = true;
		try {
			var ewww_response = jQuery.parseJSON(response);
		} catch (err) {
			is_json = false;
		}
		if ( ! is_json ) {
			alert( ewww_vars.invalid_response );
			console.log( response );
			return false;
		}
		if ( ewww_response.success == '1') {
			jQuery('#ewww-image-' + imageID + ' td:last-child').html(ewww_vars.original_restored);
			return false;
		} else if (ewww_response.error) {
			jQuery('#ewww-image-' + imageID + ' td:last-child').html(original_html);
			alert(ewww_response.error);
			return false;
		}
	});
}
