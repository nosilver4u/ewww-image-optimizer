jQuery(document).ready(function($) {
	var ewww_force = 0;
	var ewww_force_smart = 0;
	var ewww_scan_only = 0;
	var ewww_webp_only = 0;
	var ewww_delay = 0;
	var ewww_pending = 0;
	var ewww_hid_pending = false;
	var ewww_autopoll_timeout = 0;
	var ewww_table_visible = false;
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_total_pages = 0;
	var ewww_pointer = 0;
	var ewww_search_total = 0;
	var ewww_size_sort = false;
	if (ewww_vars.scan_only_mode) {
		ewww_scan_only = true;
	}
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
	$('#ewww-optimize-local-images a.button-primary').on('click', function() {
		ewww_table_visible = false;
		$(this).hide();
		$('#ewww-bulk-queue-images').show();
		$('#ewww-bulk-controls').show();
		$('#ewww-bulk-table-wrapper').hide()
		$('#ewww-bulk-results').slideDown();
		$('#ewww-show-table').show();
		$('#ewww-hide-table').hide();
		return false;
	});
	$('#ewww-bulk-start-optimizing').on('click', function() {
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-bulk-queue-confirm').show();
		return false;
	});
	$('#ewww-bulk-confirm-optimizing').on('click', function() {
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		if ($('#ewww-force-smart:checkbox:checked').val()) {
			ewww_force_smart = 1;
		}
		if ($('#ewww-scan-only:checkbox:checked').val()) {
			ewww_scan_only = 1;
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
			$('#ewww-bulk-results').slideUp(400, function() {
				$('#ewww-bulk-queue-confirm').hide();
				$('#ewww-show-table').show();
				$('#ewww-hide-table').hide();
				ewww_pending = 0;
			});
		}, 7000);
		$('.ewww-bulk-spinner').show();
		var ewww_async_init_data = {
			action: 'ewww_bulk_async_init',
			ewww_delay: ewww_delay,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_scan_only: ewww_scan_only,
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
				$('.ewww-bulk-spinner').hide();
			} else if ( ewww_response.media_remaining ) {
				$('.ewww-bulk-spinner').show();
				$('#ewww-optimize-local-images').html( ewww_response.media_remaining );
				if (!ewww_pending) {
					$('#ewww-search-pending').show();
				}
				if (ewww_scan_only) {
					$('.ewww-clear-queue').show();
				} else {
					$('.ewww-pause-optimization').show();
					$('.ewww-clear-queue').show();
				}
				ewww_autopoll = true;
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
			if (ewww_scan_only) {
				$('.ewww-start-optimization').show();
				if (!ewww_table_visible && !ewww_hid_pending) {
					ewww_pending = 1;
					ewww_table_visible = true;
					$('#ewww-search-pending').hide();
					$('#ewww-search-optimized').show();
					ewwwUpdateTable();
				}
			}
			if (ewww_response.media_remaining) {
				$('#ewww-optimize-local-images').html( ewww_response.media_remaining );
				if (!ewww_pending) {
					$('#ewww-search-pending').show();
				}
			} else if (ewww_response.images_remaining) {
				$('#ewww-optimize-local-images').html( ewww_response.images_remaining );
				if (!ewww_pending) {
					$('#ewww-search-pending').show();
				}
				if (ewww_scan_only) {
					$('.ewww-bulk-spinner').hide();
					ewww_autopoll = false;
					return;
				}
				if (ewww_table_visible) {
					ewwwUpdateTable();
				}
			} else {
				$('.ewww-queue-controls').hide();
				$('.ewww-bulk-spinner').hide();
				if (ewww_response.error) {
					$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
				} else if (ewww_response.complete) {
					if (ewww_table_visible) {
						ewwwUpdateTable();
					}
					$('#ewww-optimize-local-images').html( ewww_response.complete );
					$('#ewww-search-pending').hide();
				}
				ewww_autopoll = false;
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
		var ewww_search = $('.ewww-search-input').val();
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
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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

			if ( ewww_response.total_pages > 0 ) {
				ewww_search_total = ewww_response.total_pages;
			}
			if (ewww_response.total_images > 50) {
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			} else {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
			}
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
		});
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
	}
	$('.ewww-clear-queue').on('click', function () {
			$('.ewww-bulk-spinner').hide();
			clearTimeout(ewww_autopoll_timeout);
	});
	$('#ewww-hide-table').on('click', function() {
		ewww_table_visible = false;
		$('#ewww-bulk-results').hide();
		if (ewww_scan_only) {
			ewww_hid_pending = true;
		}
		$(this).hide();
		$('#ewww-show-table').show();
		$('#ewww-optimize-local-images a.button-primary').show();
		return false;
	});
	$('#ewww-show-table').on('click', function() {
		$('#ewww-optimize-local-images a.button-primary').show();
		ewww_table_visible = true;
		ewwwUpdateTable();
		return false;
	});
	$('#ewww-search-pending').on('click', function() {
		ewww_pointer = 0;
		ewww_pending = 1;
		$(this).hide();
		$('#ewww-search-optimized').show();
		ewwwUpdateTable();
		return false;
	});
	$('#ewww-search-optimized').on('click', function() {
		ewww_pointer = 0;
		ewww_pending = 0;
		$(this).hide();
		$('#ewww-search-pending').show();
		ewwwUpdateTable();
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-sort-size', function() {
		ewww_pointer = 0;
		if (!ewww_size_sort) {
			ewww_size_sort = 'asc';
		} else if ('asc'===ewww_size_sort) {
			ewww_size_sort = 'desc';
		} else {
			ewww_size_sort = false;
		}
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
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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
			ewww_search_total = ewww_response.total_pages;
			if (ewww_response.total_images > 50) {
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			} else {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
			}
			$('.current-page').text(ewww_response.pagination);
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
		});
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
		return false;
	});
	$('.next-page').on( 'click', function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
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
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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
			if ( ewww_response.total_pages > 0 ) {
				ewww_search_total = ewww_response.total_pages;
			}
			if (ewww_response.total_images <= ((ewww_pointer + 1) * 50)) {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
			}
			$('.current-page').text(ewww_response.pagination);
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
		});
		$('.prev-page').removeClass('disabled');
		$('.first-page').removeClass('disabled');
		return false;
	});
	$('.prev-page').on( 'click', function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
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
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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
			if ( ewww_response.total_pages > 0 ) {
				ewww_search_total = ewww_response.total_pages;
			}
			$('.current-page').text(ewww_response.pagination);
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
		});
		if (!ewww_pointer) {
			$('.prev-page').addClass('disabled');
			$('.first-page').addClass('disabled');
			if (ewww_autopoll && ! ewww_search) {
				$('.ewww-bulk-spinner').show();
				ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
			}
		}
		$('.next-page').removeClass('disabled');
		$('.last-page').removeClass('disabled');
		return false;
	});
	$('.last-page').on( 'click', function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
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
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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
			if ( ewww_response.total_pages > 0 ) {
				ewww_search_total = ewww_response.total_pages;
			}
			$('.current-page').text(ewww_response.pagination);
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
		});
		$('.next-page').addClass('disabled');
		$('.last-page').addClass('disabled');
		$('.prev-page').removeClass('disabled');
		$('.first-page').removeClass('disabled');
		return false;
	});
	$('.first-page').on( 'click', function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		ewww_pointer = 0;
		var ewww_search = $('.ewww-search-input').val();
        var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_total_pages: ewww_total_pages,
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
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
			if ( ewww_response.total_pages > 0 ) {
				ewww_search_total = ewww_response.total_pages;
			}
			if (ewww_response.total_images <= 50) {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
			} else {
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			}
			$('.prev-page').addClass('disabled');
			$('.first-page').addClass('disabled');
			$('.current-page').text(ewww_response.pagination);
			if (ewww_response.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').text(ewww_response.total_images_text);
			}
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
			ewww_pending: ewww_pending,
		};
		$.post(ajaxurl, ewww_image_removal, function(response) {
			if(response == '1') {
				$('#ewww-image-' + imageID).remove();
			} else {
				alert(ewww_vars.remove_failed);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-exclude-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_exclusion = {
			action: 'bulk_aux_images_exclude',
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_image_id: imageID,
		};
		$.post(ajaxurl, ewww_image_exclusion, function(response) {
			if(response == '1') {
				$('#ewww-image-' + imageID).remove();
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
