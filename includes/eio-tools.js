jQuery(document).ready(function($) {
	var ewww_pending = 0;
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_total_pages = 0;
	var ewww_pointer = 0;
	var ewww_search_total = 0;
	var ewww_size_sort = false;
	var ewww_clean_meta_total = 0;
	var ewww_table_debug = 0;
	function ewwwInitTable() {
		ewww_pointer = 0;
		ewww_total_pages = Math.ceil(ewww_vars.image_count / 50);
		var ewww_search = $('.ewww-search-input').val();
		$('#ewww-table-info').hide();
		$('#ewww-show-table').hide();
		$('#ewww-debug-table-info').hide();
		$('#ewww-show-debug-table').hide();
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_debug: ewww_table_debug,
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

			if (ewww_vars.image_count >= 50) {
				//$('.tablenav').show(); ?do we need this?
			}
		});
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
	}
	$('#ewww-show-table').on('submit',function() {
		ewwwInitTable();
		return false;
	});
	$('#ewww-search-pending').on('click', function() {
		ewww_pointer = 0;
		ewww_pending = 1;
		$(this).hide();
		$('#ewww-search-optimized').show();
		ewwwInitTable();
		return false;
	});
	$('#ewww-search-optimized').on('click', function() {
		ewww_pointer = 0;
		ewww_pending = 0;
		$(this).hide();
		$('#ewww-search-pending').show();
		ewwwInitTable();
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
		ewwwInitTable();
		return false;
	});
	$('#ewww-show-debug-table').on( 'submit', function() {
		ewww_table_debug = 1;
		ewww_pointer = 0;
		ewwwInitTable();
		document.body.scrollTop = 0; // For Safari.
		document.documentElement.scrollTop = 0; // For everyone else.
		return false;
	});
	$('.ewww-search-form').on( 'submit', function() {
		ewww_pointer = 0;
		var ewww_search = $('.ewww-search-input').val();
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_debug: ewww_table_debug,
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
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer++;
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_debug: ewww_table_debug,
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
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer--;
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_debug: ewww_table_debug,
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
		}
		$('.next-page').removeClass('disabled');
		$('.last-page').removeClass('disabled');
		return false;
	});
	$('.last-page').on( 'click', function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer = ewww_total_pages - 1;
		if (ewww_search || ewww_table_debug) {
			ewww_pointer = ewww_search_total - 1;
		}
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_debug: ewww_table_debug,
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
			ewww_debug: ewww_table_debug,
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
		});
		return false;
	});
	$('#ewww-clear-table').on( 'submit', function() {
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
	var ewww_total_restored = 0;
	$('#ewww-restore-originals').on( 'submit', function() {
		if (!confirm(ewww_vars.tool_warning)) {
			return false;
		}
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		$('.ewww-tool-info').hide();
		$('.ewww-tool-form').hide();
		$('.ewww-tool-divider').hide();
		$('#ewww-restore-originals-progressbar').progressbar({ max: ewww_vars.restorable_images });
		$('#ewww-restore-originals-progress').html('<p> 0/' + ewww_vars.restorable_images + '</p>');
		$('#ewww-restore-originals-progressbar').show();
		$('#ewww-restore-originals-progress').show();
		ewwwRestoreOriginals();
		return false;
	});
	function ewwwRestoreOriginals(){
		var ewww_originals_data = {
			action: 'bulk_aux_images_restore_original',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_originals_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-restore-originals-progressbar').hide();
				$('#ewww-restore-originals-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-restore-originals-progressbar').hide();
				$('#ewww-restore-originals-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(ewww_response.finished) {
				$('#ewww-restore-originals-messages').append(ewww_vars.finished);
				$('#ewww-restore-originals-messages').show();
				return false;
			}
			if (ewww_response.messages) {
				$('#ewww-restore-originals-messages').append(ewww_response.messages);
				$('#ewww-restore-originals-messages').show();
			}
			ewww_total_restored += ewww_response.completed;
			$('#ewww-restore-originals-progressbar').progressbar("option", "value", ewww_total_restored);
			$('#ewww-restore-originals-progress').html('<p>' + ewww_total_restored + '/' + ewww_vars.restorable_images + '</p>');
			if ( ewww_total_restored > ewww_vars.restorable_images + 100 ) {
				$('#ewww-restore-originals-messages').append('<p><b>' + ewww_vars.too_far) + '</b></p>';
			}
			ewwwRestoreOriginals();
		});
	}
	var ewww_total_originals = 0;
	var ewww_original_attachments = false;
	$('#ewww-clean-originals').on( 'submit', function() {
		if (!confirm(ewww_vars.tool_warning)) {
			return false;
		}
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		var ewww_originals_data = {
			action: 'ewwwio_get_all_attachments',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_originals_data, function(response) {
			try {
				ewww_original_attachments = JSON.parse(response);
			} catch (err) {
				$('.ewww-tool-info').hide();
				$('.ewww-tool-form').hide();
				$('.ewww-tool-divider').hide();
				$('#ewww-clean-originals-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				$('#ewww-clean-originals-progress').show();
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_original_attachments.error ) {
				$('.ewww-tool-info').hide();
				$('.ewww-tool-form').hide();
				$('.ewww-tool-divider').hide();
				$('#ewww-clean-originals-progress').html(ewww_original_attachments.error);
				$('#ewww-clean-originals-progress').show();
				return false;
			}
			ewww_total_originals = ewww_original_attachments.length;
			$('.ewww-tool-info').hide();
			$('.ewww-tool-form').hide();
			$('.ewww-tool-divider').hide();
			$('#ewww-clean-originals-progressbar').progressbar({ max: ewww_total_originals });
			$('#ewww-clean-originals-progress').html('<p> 0/' + ewww_total_originals + '</p>');
			$('#ewww-clean-originals-progressbar').show();
			$('#ewww-clean-originals-progress').show();
			ewwwDeleteOriginalByID();
		});
		return false;
	});
	function ewwwDeleteOriginalByID(){
		var attachment_id = ewww_original_attachments.pop();
		var ewww_originals_data = {
			action: 'bulk_aux_images_delete_original',
			ewww_wpnonce: ewww_vars._wpnonce,
			attachment_id: attachment_id,
		};
		$.post(ajaxurl, ewww_originals_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-originals-progressbar').hide();
				$('#ewww-clean-originals-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-originals-progressbar').hide();
				$('#ewww-clean-originals-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(!ewww_original_attachments.length) {
				var ewww_originals_data = {
					action: 'bulk_aux_images_delete_original',
					ewww_wpnonce: ewww_vars._wpnonce,
					delete_originals_done: 1,
				};
				$.post(ajaxurl, ewww_originals_data);
				$('#ewww-clean-originals-progress').html(ewww_vars.finished);
				return false;
			}
			var completed = ewww_total_originals - ewww_original_attachments.length;
			$('#ewww-clean-originals-progressbar').progressbar("option", "value", completed);
			$('#ewww-clean-originals-progress').html('<p>' + completed + '/' + ewww_total_originals + '</p>');
			ewwwDeleteOriginalByID();
		});
	}
	var ewww_total_converted = 0;
	$('#ewww-clean-converted').on( 'submit', function() {
		var ewww_converted_data = {
			action: 'bulk_aux_images_count_converted',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		$.post(ajaxurl, ewww_converted_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-converted-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			ewww_total_converted = ewww_response.total_converted;
			$('.ewww-tool-info').hide();
			$('.ewww-tool-form').hide();
			$('.ewww-tool-divider').hide();
			$('#ewww-clean-converted-progressbar').progressbar({ max: ewww_total_converted });
			$('#ewww-clean-converted-progress').html('<p> 0/' + ewww_total_converted + '</p>');
			$('#ewww-clean-converted-progressbar').show();
			$('#ewww-clean-converted-progress').show();
			ewwwCleanConvertedOriginals(0);
		});
		return false;
	});
	function ewwwCleanConvertedOriginals(converted_offset){
		var ewww_converted_data = {
			action: 'bulk_aux_images_converted_clean',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_converted_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-converted-progressbar').hide();
				$('#ewww-clean-converted-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-converted-progressbar').hide();
				$('#ewww-clean-converted-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(ewww_response.finished) {
				$('#ewww-clean-converted-progress').html(ewww_vars.finished);
				return false;
			}
			converted_offset += ewww_response.completed;
			$('#ewww-clean-converted-progressbar').progressbar("option", "value", converted_offset);
			$('#ewww-clean-converted-progress').html('<p>' + converted_offset + '/' + ewww_total_converted + '</p>');
			ewwwCleanConvertedOriginals(converted_offset);
		});
	}
	var ewww_total_webp   = 0;
	var ewww_webp_cleaned = 0;
	$('#ewww-clean-webp').on( 'submit', function() {
		var ewww_webp_data = {
			action: 'ewwwio_webp_attachment_count',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		$.post(ajaxurl, ewww_webp_data, function(response) {
			try {
				ewww_webp_attachments = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-webp-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			ewww_total_webp = ewww_webp_attachments.total;
			$('.ewww-tool-info').hide();
			$('.ewww-tool-form').hide();
			$('.ewww-tool-divider').hide();
			$('#ewww-clean-webp-progressbar').progressbar({ max: ewww_total_webp });
			$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage1 + ' 0/' + ewww_total_webp + '</p>');
			$('#ewww-clean-webp-progressbar').show();
			$('#ewww-clean-webp-progress').show();
			ewwwRemoveWebPByID();
		});
		return false;
	});
	function ewwwRemoveWebPByID(){
		var ewww_webp_data = {
			action: 'bulk_aux_images_delete_webp',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-webp-progressbar').hide();
				$('#ewww-clean-webp-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-webp-progressbar').hide();
				$('#ewww-clean-webp-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(ewww_response.finished) {
				ewww_total_webp   = ewww_vars.webp_cleanable;
				ewww_webp_cleaned = 0;
				$('#ewww-clean-webp-progressbar').progressbar({ max: ewww_total_webp });
				$('#ewww-clean-webp-progressbar').progressbar("option", "value", 0);
				$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage2 + ' 0/' + ewww_total_webp + '</p>');
				ewwwRemoveWebP();
				return false;
			}
			ewww_webp_cleaned++;
			$('#ewww-clean-webp-progressbar').progressbar("option", "value", ewww_webp_cleaned);
			$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage1 + ' ' + ewww_webp_cleaned + '/' + ewww_total_webp + '</p>');
			ewwwRemoveWebPByID();
		});
	}
	function ewwwRemoveWebP(){
		var ewww_webp_data = {
			action: 'bulk_aux_images_webp_clean',
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-webp-progressbar').hide();
				$('#ewww-clean-webp-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log(err);
				console.log(response);
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-webp-progressbar').hide();
				$('#ewww-clean-webp-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(ewww_response.finished) {
				$('#ewww-clean-webp-progress').html(ewww_vars.finished);
				return false;
			}
			ewww_webp_cleaned += ewww_response.completed;
			$('#ewww-clean-webp-progressbar').progressbar("option", "value", ewww_webp_cleaned);
			$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage2 + ' ' + ewww_webp_cleaned + '/' + ewww_total_webp + '</p>');
			ewwwRemoveWebP();
		});
	}
	$('#ewww-clean-table').on( 'submit', function() {
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		ewww_total_pages = Math.ceil(ewww_vars.image_count / 500);
		$('.ewww-tool-info').hide();
		$('.ewww-tool-form').hide();
		$('.ewww-tool-divider').hide();
		$('#ewww-clean-table-progressbar').progressbar({ max: ewww_total_pages });
		$('#ewww-clean-table-progress').html('<p>' + ewww_vars.batch + ' 0/' + ewww_total_pages + '</p>');
		$('#ewww-clean-table-progressbar').show();
		$('#ewww-clean-table-progress').show();
		var total_pages = ewww_total_pages;
		ewwwCleanup(total_pages);
		return false;
	});
	function ewwwCleanup(total_pages){
		total_pages--;
		var ewww_table_data = {
			action: 'bulk_aux_images_table_clean',
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: total_pages,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-table-progressbar').hide();
				$('#ewww-clean-table-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-table-progressbar').hide();
				$('#ewww-clean-table-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(!total_pages>0) {
				$('#ewww-clean-table-progress').html(ewww_vars.finished);
				$('#ewww-clean-table-progressbar').progressbar("option", "value", ewww_total_pages);
				return;
			}
			$('#ewww-clean-table-progressbar').progressbar("option", "value", ewww_total_pages-total_pages);
			$('#ewww-clean-table-progress').html('<p>' + ewww_vars.batch + ' ' + (ewww_total_pages-total_pages) + '/' + ewww_total_pages + '</p>');
			ewwwCleanup(total_pages);
		});
	}
	$('#ewww-clean-meta').on( 'submit', function() {
		var header_label = $(this).find('input[type="submit"]').val();
		if (header_label) {
			$('#ewwwio-tools-header').html(header_label);
		}
		$('.ewww-tool-info').hide();
		$('.ewww-tool-form').hide();
		$('.ewww-tool-divider').hide();
		$('#ewww-clean-meta-progressbar').progressbar({ max: ewww_vars.attachment_count });
		console.log( $('#ewww-clean-meta-progressbar').progressbar("option","max"));
		$('#ewww-clean-meta-progress').html('<p>0/' + ewww_vars.attachment_string + '</p>');
		$('#ewww-clean-meta-progressbar').show();
		$('#ewww-clean-meta-progress').show();
		ewwwCleanupMeta();
		return false;
	});
	function ewwwCleanupMeta(){
		var ewww_cleanmeta_data = {
			action: 'bulk_aux_images_meta_clean',
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_offset: ewww_clean_meta_total,
		};
		$.post(ajaxurl, ewww_cleanmeta_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewww-clean-meta-progressbar').hide();
				$('#ewww-clean-meta-progress').html('<span style="color: red"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-clean-meta-progressbar').hide();
				$('#ewww-clean-meta-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if(ewww_response.done) {
				$('#ewww-clean-meta-progress').html(ewww_vars.finished);
				$('#ewww-clean-meta-progressbar').progressbar("value", parseInt(ewww_vars.attachment_count));
				return;
			}
			ewww_clean_meta_total += ewww_response.success;
			if (ewww_clean_meta_total > ewww_vars.attachment_count) {
				ewww_clean_meta_total = ewww_vars.attachment_count;
			}
			$('#ewww-clean-meta-progressbar').progressbar("value", ewww_clean_meta_total);
			$('#ewww-clean-meta-progress').html('<p>' + ewww_clean_meta_total + '/' + ewww_vars.attachment_string + '</p>');
			ewwwCleanupMeta();
		});
	}
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
