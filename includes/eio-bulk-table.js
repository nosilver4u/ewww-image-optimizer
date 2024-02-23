jQuery(document).ready(function($) {
	var ewww_error_counter = 30;
	var ewww_force = 0;
	var ewww_force_smart = 0;
	var ewww_webp_only = 0;
	var ewww_delay = 0;
	var ewww_autopoll_timeout = 0;
	var ewww_table_visible = false;
	$("#ewww-delay-slider").slider({
		min: 0,
		max: 30,
		value: $("#ewww-delay").val(),
		slide: function(event, ui) {
			$("#ewww-delay").val(ui.value);
		}
	});
	var ewwwdelayinput = document.getElementById("ewww-delay");
	if (ewwwdelayinput) {
		ewwwdelayinput.onblur = function() {
			if (isNaN(this.value)) {
				this.value = 0;
			} else {
				this.value = Math.ceil(this.value);
			}
		};
	}
	$('#ewww-optimize-local-images').on('click', function() {
		$(this).hide();
		$('#ewww-bulk-queue-images').show();
		$('#ewww-bulk-controls').show();
		$('#ewww-bulk-table-wrapper').hide()
		$('#ewww-bulk-results').slideDown();
		return false;
	});
	$('#ewww-bulk-start-optimizing').on('click', function() {
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-bulk-queue-confirm').show();
	});
	$('#ewww-bulk-confirm-optimizing').on('click', function() {
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		if ($('#ewww-force-smart:checkbox:checked').val()) {
			ewww_force_smart = 1;
		}
		if ($('#ewww-webp-only:checkbox:checked').val()) {
			ewww_webp_only = 1;
		}
		if ( ! $('#ewww-delay').val().match( /^[1-9][0-9]*$/) ) {
			ewww_delay = 0;
		} else {
			ewww_delay = $('#ewww-delay').val();
		}
		$(this).hide();
		$('.ewww-bulk-confirm-info').hide();
		$('#ewww-bulk-async-notice').show();
		setTimeout(function() {
			$('#ewww-bulk-results').slideUp();
		}, 7000);
		$('#ewww-optimize-local-images').replaceWith('<div id="ewww-optimize-local-images"></div>');
		$('#ewww-optimize-local-images').html('<img src="' + ewww_vars.loading_image_url + '">');
		var ewww_async_init_data = {
			action: 'ewww_bulk_async_init',
			ewww_delay: ewww_delay,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_async_init_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
			} else if ( ewww_response.media_remaining ) {
				$('.ewww-bulk-spinner').show();
				$('#ewww-optimize-local-images').html( ewww_response.media_remaining );
				$('.ewww-queue-controls').show();
				ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
			}
		})
		.fail(function() {
			$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></span>');
		});
		return false;
	});
	if (ewww_autopoll) {
		$('.ewww-bulk-spinner').show();
		ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
	}
	function ewwwUpdateAsyncBulkStatus() {
		var ewww_update_status_data = {
			action: 'ewww_bulk_async_get_status',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_update_status_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				console.log( response );
				return false;
			}
			if (ewww_response.new_nonce) {
				ewww_vars._wpnonce = ewww_response.new_nonce;
			}
			if (ewww_response.media_remaining) {
				$('#ewww-optimize-local-images').html( ewww_response.media_remaining );
			} else if (ewww_response.images_remaining) {
				$('#ewww-optimize-local-images').html( ewww_response.images_remaining );
				if (ewww_table_visible) {
					ewwwUpdateTable();
				}
			} else {
				$('#ewww-optimize-local-images').hide();
				$('.ewww-queue-controls').hide();
				$('.ewww-bulk-spinner').hide();
				return;
			}
			console.log('checking status again in 20s');
			ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
		})
		.fail(function() {
			$('.ewww-bulk-spinner').hide();
			$('#ewww-optimize-local-images').html(ewww_vars.bulk_refresh_error);
		});
	}
	function ewwwUpdateTable() {
		console.log('refreshing table/results');
		ewww_pointer = 0;
		ewww_total_pages = Math.ceil(ewww_vars.image_count / 50);
		$('.displaying-num').text(ewww_vars.count_string);
		$('#ewww-show-table').hide();
		$('#ewww-hide-table').show();
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-bulk-table-wrapper').show()
		$('#ewww-bulk-results').slideDown();
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-aux-table').show();
			$('.ewww-search-count').text(ewww_response.search_result);
			$('.current-page').text(ewww_response.pagination);
			// from here
			if ( ewww_response.search_total > 0 ) {
				ewww_search_total = ewww_response.search_total;
			}
			if (ewww_response.search_count < 50) {
				$('.next-page').hide();
				$('.last-page').hide();
			}
			// to here
			if (ewww_vars.image_count >= 50) {
				$('.tablenav').show();
				$('.next-page').show();
				$('.last-page').show();
			}
		});
	}
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_total_pages = 0;
	var ewww_pointer = 0;
	var ewww_search_total = 0;
	$('#ewww-hide-table').on('click', function() {
		$('#ewww-bulk-results').hide();
		$(this).hide();
		$('#ewww-show-table').show();
		return false;
	});
	$('#ewww-show-table').on('click', function() {
		ewww_table_visible = true;
		ewwwUpdateTable();
		return false;
	});
	$('.ewww-search-form').on( 'submit', function() {
		ewww_pointer = 0;
		var ewww_search = $('.ewww-search-input').val();
		if (ewww_search) {
			$('.ewww-bulk-spinner').hide();
			clearTimeout(ewww_autopoll_timeout);
		} else if (ewww_autopoll) {
			$('.ewww-bulk-spinner').show();
			ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
		}
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-search-count').text(ewww_response.search_result);
			ewww_search_total = ewww_response.search_total;
			if (ewww_response.search_count < 50) {
				$('.next-page').hide();
				$('.last-page').hide();
			}
			$('.current-page').text(ewww_response.pagination);
		});
		$('.prev-page').hide();
		$('.first-page').hide();
		$('.next-page').show();
		$('.last-page').show();
		return false;
	});
	$('.next-page').on( 'click', function() {
		clearTimeout(ewww_autopoll_timeout);
		$('.ewww-bulk-spinner').hide();
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer++;
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-search-count').text(ewww_response.search_result);
			if (ewww_response.search_count < 50) {
				$('.next-page').hide();
				$('.last-page').hide();
			}
			$('.current-page').text(ewww_response.pagination);
		});
		if (ewww_vars.image_count <= ((ewww_pointer + 1) * 50)) {
			$('.next-page').hide();
			$('.last-page').hide();
		}
		$('.prev-page').show();
		$('.first-page').show();
		return false;
	});
	$('.prev-page').on( 'click', function() {
		clearTimeout(ewww_autopoll_timeout);
		$('.ewww-bulk-spinner').hide();
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer--;
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-search-count').text(ewww_response.search_result);
			$('.current-page').text(ewww_response.pagination);
		});
		if (!ewww_pointer) {
			$('.prev-page').hide();
			$('.first-page').hide();
			if (ewww_autopoll && ! ewww_search) {
				$('.ewww-bulk-spinner').show();
				ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
			}
		}
		$('.next-page').show();
		$('.last-page').show();
		return false;
	});
	$('.last-page').on( 'click', function() {
		$('.ewww-bulk-spinner').hide();
		clearTimeout(ewww_autopoll_timeout);
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer = ewww_total_pages - 1;
		if (ewww_search) {
			ewww_pointer = ewww_search_total - 1;
		}
	        var ewww_table_data = {
	                action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
	        };
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-search-count').text(ewww_response.search_result);
			$('.current-page').text(ewww_response.pagination);
		});
		$('.next-page').hide();
		$('.last-page').hide();
		$('.prev-page').show();
		$('.first-page').show();
		return false;
	});
	$('.first-page').on( 'click', function() {
		ewww_pointer = 0;
		var ewww_search = $('.ewww-search-input').val();
        var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
	    };
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			$('#ewww-bulk-table').html(ewww_response.table);
			$('.ewww-search-count').text(ewww_response.search_result);
			if (ewww_response.search_count < 50) {
				$('.next-page').hide();
				$('.last-page').hide();
			} else {
				$('.next-page').show();
				$('.last-page').show();
			}
			$('.prev-page').hide();
			$('.first-page').hide();
			$('.current-page').text(ewww_response.pagination);
			if (ewww_autopoll && ! ewww_search) {
				$('.ewww-bulk-spinner').show();
				ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-remove-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_removal = {
			action: 'bulk_aux_images_remove',
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_image_id: imageID,
		};
		$.post(ajaxurl, ewww_image_removal, function(response) {
			if(response == '1') {
				$('#ewww-image-' + imageID).remove();
				var ewww_prev_count = ewww_vars.image_count;
				ewww_vars.image_count--;
				ewww_vars.count_string = ewww_vars.count_string.replace( ewww_prev_count, ewww_vars.image_count );
				$('.displaying-num').text(ewww_vars.count_string);
			} else {
				alert(ewww_vars.remove_failed);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-restore-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_restore = {
			action: 'ewww_manual_image_restore_single',
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_image_id: imageID,
		};
		var original_html = $('#ewww-image-' + imageID + ' td:last-child').html();
		$('#ewww-image-' + imageID + ' td:last-child').html(ewww_vars.restoring);
		$.post(ajaxurl, ewww_image_restore, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				alert( ewww_vars.invalid_response );
				console.log( response );
				return false;
			}
			if ( ewww_response.success == '1') {
				$('#ewww-image-' + imageID + ' td:last-child').html(ewww_vars.original_restored);
			} else if (ewww_response.error) {
				$('#ewww-image-' + imageID + ' td:last-child').html(original_html);
				alert(ewww_response.error);
			}
		});
		return false;
	});
});
