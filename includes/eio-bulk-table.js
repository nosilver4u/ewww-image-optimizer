jQuery(document).ready(function($) {
	var ewww_i = 0; // Tracks how many images optimized (foreground) in a single session.
	var ewww_j = 0; // Vs. tracks how many images optimized (foreground) since the last pause/resume.
	var ewww_k = 0;
	var ewww_background = 0;
	var ewww_aux_folders = 1;
	var ewww_force = 0;
	var ewww_force_smart = 0;
	var ewww_scan_only = 0;
	var ewww_webp_only = 0;
	var ewww_delay = 0;
	var ewww_batch_limit = 0;
	var ewwwTimeoutHandler, bulkResultsSlideTimer;
	var ewwwNumberFormat = new Intl.NumberFormat();
	var ewww_tiny_skip = '';
	var ewww_pending = 0;
	var ewww_hid_pending = false;
	var ewww_autopoll_timeout = 0;
	var ewww_table_visible = false;
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_total_pages = 0;
	var ewww_total_images = 0;
	var ewww_total_pending = 0;
	var ewww_pointer = 0;
	var ewww_size_sort = false;
	var ewww_bulk_start_time = 0;
	var ewww_bulk_elapsed_time = 0;
	var ewww_time_per_image = 0;
	var ewww_time_remaining = 0;
	var ewww_days_remaining = 0;
	var ewww_hours_remaining = 0;
	var ewww_minutes_remaining = 0;
	var ewww_seconds_remaining = 0;
	var ewww_countdown = false;
	var ewww_error_counter = 30;
	var ewwwBulkFirstPage = false;
	var ewwwBulkFirstLoop = false;
	var ewww_quota_update;
	if (ewww_bulk.scan_only_mode) {
		ewww_scan_only = true;
	}
	if (ewww_bulk.selected_ids.length > 0) {
		ewww_autopoll = false;
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
	// If auto-poll is enabled, then the bulk process is already running, and this will not be needed.
	if (! ewww_autopoll) {
		// Populate the bulk information: media uploads, image sizes, dimensions, etc.
		var ewww_get_bulk_info_data = {
			action: 'ewww_get_bulk_info',
			ids: ewww_bulk.selected_ids,
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		$.post(ajaxurl, ewww_get_bulk_info_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-bulk-queue-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-queue-images').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
			} else if ( ewww_response.html ) {
				$('#ewww-bulk-queue-images').html( ewww_response.html );
				if (ewww_response.show_bulk_controls) {
					$('#ewww-bulk-controls').show();
				} else {
					$('#ewww-bulk-controls').remove();
				}
			}
			if (ewww_bulk.bulk_init) {
				if (ewww_bulk.selected_ids.length > 0) {
					$('#ewww-background').parent().hide();
					$('#ewww-scan-only').parent().hide();
					$('#ewww-aux-folders').parent().hide();
				}
				$('#ewww-optimize-local-images a.button-primary').trigger('click');
			}
		})
		.fail(function() {
			$('#ewww-bulk-queue-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
		});
	}
	// Show the bulk interface when the button is clicked.
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
	$('#ewww-bulk-queue-images').on('click', '#ewww-bulk-start-optimizing', function() {
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-bulk-queue-confirm').show();
		return false;
	});
	$('#ewww-bulk-confirm-optimizing').on('click', function() {
		ewwwCheckBulkOptions();
		$(this).hide();
		$('.ewww-bulk-confirm-info').hide();
		if (ewww_background) {
			$('#ewww-bulk-background-notice').show();
			ewwwAsyncInit();
		} else {
			$('#ewww-bulk-foreground-notice').show();
			$('#ewww-show-table').hide();
			ewww_autopoll = false;
			ewwwScanInit();
		}
		bulkResultsSlideTimer = setTimeout(function() {
			if (ewww_table_visible) {
				$('#ewww-bulk-queue-confirm').slideUp(400, function() {
					if (ewww_background) {
						$('#ewww-hide-table').show();
					}
				});
			} else {
				$('#ewww-bulk-results').slideUp(400, function() {
					$('#ewww-bulk-queue-confirm').hide();
					if (ewww_background) {
						$('#ewww-show-table').show();
						$('#ewww-hide-table').hide();
					}
					ewww_pending = 0;
				});
			}
		}, 7000);
		$('.ewww-bulk-spinner').show();
		return false;
	});
	function ewwwCheckBulkOptions() {
		if ($('#ewww-background:checkbox:checked').val()) {
			ewww_background = 1;
		} else if (ewww_bulk.easymode) {
			ewww_scan_only = 1;
		}
		if ($('#ewww-scan-only:checkbox:checked').val()) {
			ewww_scan_only = 1;
		}
		if ( ! $('#ewww-aux-folders:checkbox:checked').val()) {
			ewww_aux_folders = 0;
		}
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
	}
	function ewwwScanInit() {
		var ewww_scan_data = {
			action: 'ewww_bulk_scan_init',
			ewww_aux_folders: ewww_aux_folders,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_scan_only: ewww_scan_only,
			ewww_delay: ewww_delay,
			ewww_scan: true,
			ewww_wpnonce: ewww_bulk._wpnonce,
			ids: ewww_bulk.selected_ids,
		};
		$.post(ajaxurl, ewww_scan_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
				$('.ewww-bulk-spinner').hide();
			} else if ( ewww_response.message ) {
				$('#ewww-optimize-local-images').html(ewww_response.message);
				ewwwRunScan();
			} else {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				console.log( response );
				return false;
			}
		})
		.fail(function() {
			$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
			$('.ewww-bulk-spinner').hide();
			console.log( response );
			return false;
		});
	}
	function ewwwRunScan() {
		var ewww_scan_data = {
			action: 'ewww_bulk_scan',
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_scan_only: ewww_scan_only,
			ewww_scan: true,
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		$.post(ajaxurl, ewww_scan_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
				$('.ewww-bulk-spinner').hide();
			} else if ( ewww_response.remaining ) {
				$('.ewww-aux-table').hide();
				$('#ewww-show-table').hide();
				$('#ewww-optimize-local-images').html( ewww_response.remaining );
				if ( ewww_response.notice ) {
					$('#ewww-optimize-local-images').append( '<br>' + ewww_response.notice );
				}
				if ( ewww_response.tiny_skip ) {
					$('#ewww-optimize-local-images').append( '<br>' + ewww_response.tiny_skip );
					ewww_tiny_skip = ewww_response.tiny_skip;
					console.log( 'skipped some tiny images' );
				}
				if ( ewww_response.bad_attachment ) {
					$('#ewww-optimize-local-images').append( '<br>' + ewww_bulk.bad_attachment + ' ' + ewww_response.bad_attachment );
				}
				ewww_scan_failures = 0;
				ewwwRunScan();
			} else if ( ewww_response.ready ) {
				ewww_total_pending = ewww_response.ready;
				$('#ewww-optimize-local-images').html(ewww_response.message);
				if ( ewww_tiny_skip ) {
					$('#ewww-optimize-local-images').append( '<br><i>' + ewww_tiny_skip + '</i>' );
					console.log( 'done, skipped some tiny images' );
				}
				if (ewww_scan_only) {
					$('.ewww-bulk-spinner').hide();
					$('.ewww-start-optimization').removeClass('button-secondary');
					$('.ewww-start-optimization').addClass('button-primary');
					$('.ewww-start-optimization').addClass('bulk-foreground');
					$('.ewww-start-optimization').show();
					$('.ewww-clear-queue').show();
					if (!ewww_table_visible && !ewww_hid_pending) {
						ewww_pending = 1;
						ewww_table_visible = true;
						$('#ewww-search-pending').hide();
						$('#ewww-search-optimized').hide();
						$('#ewww-showing-queue').show();
						ewwwUpdateTable();
					}
				} else {
					ewwwStartOpt();
				}
			} else if ( ewww_response.ready === 0 ) {
				$('.ewww-bulk-spinner').hide();
				$('#ewww-show-table').show();
				$('#ewww-optimize-local-images').html(ewww_response.message);
				if ( ewww_tiny_skip ) {
					$('#ewww-optimize-local-images').append( '<br><i>' + ewww_tiny_skip + '</i>' );
					console.log( 'done, skipped some tiny images' );
				}
			}
		})
		.fail(function() {
			ewww_scan_failures++;
			if (ewww_scan_failures > 10) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.scan_fail + ':</b> ' + ewww_bulk.bulk_fail_more + '</span>');
				$('.ewww-bulk-spinner').hide();
			} else {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.scan_incomplete + '</b></span>');
				setTimeout(function() {
					ewwwStartScan();
				}, 1000);
			}
		});
	}
	$('.ewww-action-container').on('click', '.bulk-foreground.ewww-resume-scan', function() {
		$('.bulk-foreground.ewww-resume-scan').hide();
		$('.ewww-clear-queue').hide();
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-optimize-local-images').html('');
		$('.ewww-bulk-spinner').show();
		ewwwCheckBulkOptions();
		ewwwRunScan();
		return false;
	});
	$('.ewww-action-container').on('click', '.bulk-foreground.ewww-start-optimization', function() {
		clearTimeout(bulkResultsSlideTimer);
		$('#ewww-bulk-queue-confirm').slideUp();
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		if (0===ewww_total_pending && ewww_previous_pending > 0) {
			ewww_total_pending = ewww_previous_pending;
		}
		ewwwCheckBulkOptions();
		ewwwStartOpt();
		return false;
	});
	$('.ewww-action-container').on('click', '.bulk-foreground.ewww-pause-optimization', function() {
		ewww_k = 9;
		ewww_j = 0;
		$('.ewww-bulk-spinner').hide();
		$('#ewww-bulk-timer').hide();
		$('#ewww-bulk-counter').hide();
		$('.bulk-foreground.ewww-pause-optimization').hide();
		clearTimeout(ewwwTimeoutHandler);
		$('#ewww-optimize-local-images').html(ewww_bulk.operation_stopped);
		$('.ewww-resume-optimization').addClass('bulk-foreground');
		$('.ewww-resume-optimization').show();
		$('.ewww-clear-queue').show();
		return false;
	});
	$('.ewww-action-container').on('click', '.bulk-foreground.ewww-resume-optimization', function() {
		if (9===ewww_k) {
			ewwwResumeOpt();
		} else {
			ewwwStartOpt();
		}
		return false;
	});
	function ewwwUpdateQuota() {
		var ewww_quota_update_data = {
			action: 'bulk_quota_update',
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		var existing_quota_data = $('#ewww-bulk-credits-available').html();
		if (existing_quota_data && existing_quota_data.length > 0) {
			$.post(ajaxurl, ewww_quota_update_data, function(response) {
				$('#ewww-bulk-credits-available').html(response);
				if (response.length > 0) {
					$('#ewww-bulk-credits-available').fadeIn();
				}
			});
		} else {
			clearInterval(ewww_quota_update);
		}
	}
	function ewwwResumeOpt () {
		ewww_k = 0;
		ewww_quota_update = setInterval( ewwwUpdateQuota, 60000 );
		ewwwUpdateQuota();
		$('.ewww-resume-optimization').hide();
		$('.ewww-start-optimization').hide();
		$('.ewww-clear-queue').hide();
		$('#ewww-hide-table').hide();
		$('#ewww-show-table').hide(0,function(){
			$('.ewww-pause-optimization').show();
			$('.ewww-bulk-spinner').fadeIn();
		});
		ewww_bulk_start_time = Date.now();
		$('#ewww-optimize-local-images').html('');
		$('#ewww-bulk-progressbar').fadeIn();
		$('#ewww-bulk-timer').fadeIn();
		$('#ewww-bulk-counter').fadeIn();
		// Reset the table to page 1 (cached).
		ewww_pointer = 0;
		ewww_pending = 0;
		$('#ewww-search-pending').show();
		$('#ewww-search-optimized').hide();
		$('.ewww-search-input').val('');
		$('.current-page-info .current-page').text(1);
		ewwwBulkFirstPage.clone().replaceAll('#ewww-bulk-table table');
		if (ewwwBulkFirstPage.find('tbody tr').length < 50) {
			$('.next-page').addClass('disabled');
			$('.last-page').addClass('disabled');
		}
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
		ewwwProcessImage();
	}
	function ewwwStartOpt () {
		ewww_k = 0;
		ewww_quota_update = setInterval( ewwwUpdateQuota, 60000 );
		ewwwUpdateQuota();
		$('#ewwwio-settings-menu').hide();
		$('#ewww-settings-wrap').hide();
		$('.ewww-pause-optimization').addClass('bulk-foreground');
		$('.ewww-resume-optimization').hide();
		$('.ewww-start-optimization').hide();
		$('.ewww-clear-queue').hide();
		$('#ewww-show-table').hide(0,function(){
			$('#ewwwio-bulk-header .ewww-action-container.ewwwio-flex-space-between').addClass('full-width');
			$('.ewww-pause-optimization').show();
			$('.ewww-bulk-spinner').fadeIn();
		});
		if (ewww_delay) {
			ewww_batch_limit = 1;
		}
		ewww_bulk_start_time = Date.now();
		var ewww_init_data = {
			action: 'ewww_bulk_init',
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_scan_only: ewww_scan_only,
			ewww_delay: ewww_delay,
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		$.post(ajaxurl, ewww_init_data, function(response) {
			var is_json = true;
			try {
				var ewww_init_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json || ! response ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				console.log( response );
				return false;
			}
			if ( ewww_init_response.error ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_init_response.error + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				if ( ewww_init_response.data ) {
					console.log( ewww_init_response.data );
				}
			} else {
				$('#ewww-optimize-local-images').html(ewww_init_response.results);
				$('#ewww-bulk-progressbar').progressbar({ max: ewww_total_pending });
				$('#ewww-bulk-progressbar').slideDown();
				$('#ewww-bulk-counter .optimized-images-count').text(0);
				$('#ewww-bulk-counter .total-images-count').text(ewww_total_pending);
				$('#ewww-bulk-counter').fadeIn();
				// Initialize the table, but don't show it until we have a result.
				ewww_pointer = 0;
				ewww_pending = 0;
				$('.ewww-search-input').val('');
				$('#ewww-show-table').hide();
				$('#ewww-hide-table').hide();
				var ewww_table_data = {
					action: ewww_table_action,
					ewww_wpnonce: ewww_bulk._wpnonce,
					ewww_offset: ewww_pointer,
					ewww_search: '',
					ewww_pending: ewww_pending,
					ewww_size_sort: false,
				};
				$.post(ajaxurl, ewww_table_data, function(response) {
					try {
						var ewww_response = JSON.parse(response);
					} catch (err) {
						$('#ewww-optimize-local-images').html('<span style="color: red"><b>' + ewww_bulk.invalid_response + '</b></span>');
						$('.ewww-pause-optimization').hide();
						$('.ewww-bulk-spinner').hide();
						console.log( response );
						return false;
					}
					if ( ewww_response.error ) {
						$('#ewww-optimize-local-images').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
						$('.ewww-pause-optimization').hide();
						$('.ewww-bulk-spinner').hide();
						return false;
					}
					ewwwBulkFirstPage = $(ewww_response.table);
					ewwwBulkFirstLoop = ewww_response;
					ewww_total_images = ewww_response.total_images;
					$('.next-page').addClass('disabled');
					$('.last-page').addClass('disabled');
					$('.prev-page').addClass('disabled');
					$('.first-page').addClass('disabled');
					ewwwProcessImage();
				});
			}
		});
	}
	function ewwwProcessImage() {
		var ewww_loop_data = {
			action: 'ewww_bulk_loop',
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_batch_limit: ewww_batch_limit,
			ewww_error_counter: ewww_error_counter,
		};
		$.post(ajaxurl, ewww_loop_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json || ! response ) {
				$('.ewww-bulk-spinner').hide();
				$('#ewww-bulk-timer').hide();
				$('#ewww-bulk-counter').hide();
				$('.ewww-pause-optimization').hide();
				$('#ewww-optimize-local-images').append('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				ewww_countdown = false;
				if ( ! response ) {
					console.log( 'empty response' );
				} else {
					console.log( response );
				}
				return false;
			}
			ewww_i += ewww_response.completed;
			ewww_j += ewww_response.completed;
			if ( ewww_response.add_to_total > 0 ) {
				ewww_total_pending = ewww_total_pending + ewww_response.add_to_total;
				$('#ewww-bulk-progressbar').progressbar({ max: ewww_total_pending });
				$('#ewww-bulk-counter .total-images-count').text(ewww_total_pending);
			}
			$('#ewww-bulk-progressbar').progressbar( "option", "value", ewww_i );
			$('#ewww-bulk-counter .optimized-images-count').text(ewww_i);
			if ( ewww_response.update_meta ) {
	        		var ewww_updatemeta_data = {
					action: 'ewww_bulk_update_meta',
					attachment_id: ewww_response.update_meta,
					ewww_wpnonce: ewww_bulk._wpnonce,
				};
				$.post(ajaxurl, ewww_updatemeta_data);
			}
			if ( ewww_response.error ) {
				$('.ewww-bulk-spinner').hide();
				$('#ewww-bulk-timer').hide();
				$('#ewww-bulk-counter').hide();
				$('.ewww-pause-optimization').hide();
				$('#ewww-optimize-local-images').append('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				ewwwUpdateQuota();
				ewww_countdown = false;
			}
			else if (ewww_k == 9) {
				if ( ewww_response.results ) {
					ewwwAddTableRow(ewww_response.results);
				}
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				ewww_countdown = false;
			}
			else if ( response == 0 ) {
				$('.ewww-bulk-spinner').hide();
				$('#ewww-bulk-timer').hide();
				$('#ewww-bulk-counter').hide();
				$('.ewww-pause-optimization').hide();
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				ewww_countdown = false;
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.operation_stopped + '</b></span>');
			}
			else if ( ewww_i < ewww_total_pending && ! ewww_response.done ) {
				if ( ewww_bulk_start_time ) {
					ewww_bulk_elapsed_time = Date.now() - ewww_bulk_start_time;
					ewww_time_per_image = (ewww_bulk_elapsed_time + (ewww_delay * 1000)) / ewww_j;
					ewww_time_remaining = Math.floor((ewww_total_pending - ewww_i) * ewww_time_per_image / 1000);
					ewwwTimeIncrementsUpdate();
					if ( ! ewww_countdown) {
						$('#ewww-bulk-timer').fadeIn();
						$('#ewww-bulk-timer .time-remaining').html(ewww_days_remaining + ':' + ewww_hours_remaining + ':' + ewww_minutes_remaining + ':' + ewww_seconds_remaining);
						ewww_countdown = setInterval( ewwwCountDown, 1000 );
					}
				}
				if ( ewww_response.results ) {
					ewwwAddTableRow(ewww_response.results);
				}
				if ( ewww_response.next_file ) {
					$('#ewww-optimize-local-images').html(ewww_response.next_file);
				}
				if ( ewww_response.new_nonce ) {
					ewww_bulk._wpnonce = ewww_response.new_nonce;
				}
				ewww_error_counter = 30;
				ewwwTimeoutHandler = setTimeout(ewwwProcessImage, ewww_delay * 1000);
			}
			else {
				if ( ewww_response.results ) {
					ewwwAddTableRow(ewww_response.results);
				}
				var ewww_cleanup_data = {
					action: 'ewww_bulk_cleanup',
					ewww_wpnonce: ewww_bulk._wpnonce,
				};
				$.post(ajaxurl, ewww_cleanup_data, function(response) {
					$('#ewww-optimize-local-images').html(response);
					$('.ewww-bulk-spinner').hide();
					$('#ewww-bulk-timer').hide();
					$('#ewww-search-pending').hide();
					$('.ewww-pause-optimization').hide();
					$('#ewww-hide-table').show();
					$('#ewwwio-settings-menu').show();
					$('#ewww-settings-wrap').show();
					ewwwBulkCleanup();
				});
			}
		})
		.fail(function() {
			if (ewww_error_counter == 0) {
				$('#ewww-optimize-local-images').html('<p class="ewww-bulk-error"><b>' + ewww_bulk.operation_interrupted + ':</b> ' + ewww_bulk.bulk_fail_more + '</p>');
				$('.ewww-bulk-spinner').hide();
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
			} else {
				$('#ewww-optimize-local-images').html('<p class="ewww-bulk-error"><b>' + ewww_bulk.temporary_failure + ' ' + ewww_error_counter + ' (' + ewww_bulk.bulk_fail_more + ')</b></p>');
				ewww_error_counter--;
				ewwwTimeoutHandler = setTimeout(function() {
					ewwwProcessImage();
				}, 1000);
			}
		});
	}
	function ewwwBulkCleanup() {
		clearInterval(ewww_quota_update);
		clearInterval(ewww_countdown);
		ewww_countdown = false;
		ewww_total_pending = 0;
		ewww_i = 0;
		ewww_force = 0;
		ewww_force_smart = 0;
		ewww_webp_only = 0;
	}
	function ewwwCountDown() {
		if (ewww_time_remaining > 1) {
			ewww_time_remaining--;
		}
		ewwwTimeIncrementsUpdate();
		$('#ewww-bulk-timer .time-remaining').text(ewww_days_remaining + ':' + ewww_hours_remaining + ':' + ewww_minutes_remaining + ':' + ewww_seconds_remaining);
	}
	function ewwwTimeIncrementsUpdate() {
		ewww_days_remaining = Math.floor(ewww_time_remaining / 86400);
		ewww_hours_remaining = Math.floor((ewww_time_remaining - (ewww_days_remaining * 86400)) / 3600);
		ewww_minutes_remaining = Math.floor((ewww_time_remaining - (ewww_days_remaining * 86400) - (ewww_hours_remaining * 3600)) / 60);
		ewww_seconds_remaining = ewww_time_remaining - (ewww_days_remaining * 86400) - (ewww_hours_remaining * 3600) - (ewww_minutes_remaining * 60);
		if (ewww_days_remaining < 10) { ewww_days_remaining = '0'+ewww_days_remaining; }
		if (ewww_hours_remaining < 10) { ewww_hours_remaining = '0'+ewww_hours_remaining; }
		if (ewww_minutes_remaining < 10) { ewww_minutes_remaining = '0'+ewww_minutes_remaining; }
		if (ewww_seconds_remaining < 10) { ewww_seconds_remaining = '0'+ewww_seconds_remaining; }
	}
	function ewwwAddTableRow(image_rows) {
		if (ewwwBulkFirstLoop && ewwwBulkFirstLoop.table) {
			ewwwBulkFirstPage.find('.ewww-no-images').remove();
			$('#ewww-bulk-table').html(ewwwBulkFirstPage);
			$('.ewww-search-count').text(ewwwBulkFirstLoop.search_result);
			$('#ewww-search-optimized').hide();
			$('#ewww-search-pending').show();
			$('#ewww-showing-queue').hide();
			if ( ewwwBulkFirstLoop.total_pages > 0 ) {
				ewww_total_pages  = ewwwBulkFirstLoop.total_pages;
			}
			$('.current-page-info').html(ewwwBulkFirstLoop.pagination);
			if (ewwwBulkFirstLoop.total_images_text) {
				$('.ewww-tablenav-pages .displaying-num').html(ewwwBulkFirstLoop.total_images_text);
			}
			ewwwBulkFirstLoop = false;
			$('.ewww-aux-table').show();
		}
		$('#ewww-bulk-table-wrapper').show();
		$('#ewww-bulk-results').slideDown();
		ewww_table_visible = true;
		// We store a copy of the last 50 image records in s3io_bulk_first_page, and need to use it here,
		// in case they are on a page other than the first one.
		var isAlternate = ewwwBulkFirstPage.find('tbody tr').first().hasClass('alternate');
		// NOT sure if this is the right logic, see what we need to loop through all the tr elements in image_rows.
		$.each(image_rows, function(index, value) {
			ewww_total_images++;
			var image_row = $(value);
			if (! isAlternate) {
				image_row.addClass('alternate');
			}
			if (ewww_pointer === 0 && ! ewww_pending) {
				$('.prev-page').addClass('disabled');
				$('.first-page').addClass('disabled');
				image_row.prependTo('#ewww-bulk-table tbody').hide().fadeIn(400,function() {
					if ($('#ewww-bulk-table tbody').children().length > 50) {
						// Remove the last row, as it belongs on the next page now.
						$('#ewww-bulk-table tbody').children().last().remove();
					}
					ewwwBulkFirstPage = $('#ewww-bulk-table table').clone();
				});
			} else {
				ewwwBulkFirstPage.find('tbody').prepend(image_row);
				if (ewwwBulkFirstPage.find('tbody tr').length > 50) {
					// Remove the last row, as it belongs on the next page now.
					ewwwBulkFirstPage.find('tbody tr').last().remove();
				}
			}
			isAlternate = ! isAlternate;
		});
		if (ewww_total_images >= 50) {
			ewww_total_pages = Math.ceil(ewww_total_images / 50);
			$('.current-page-info .total-pages').text(ewwwNumberFormat.format(ewww_total_pages));
			$('.next-page').removeClass('disabled');
			$('.last-page').removeClass('disabled');
		} else {
			$('.next-page').addClass('disabled');
			$('.last-page').addClass('disabled');
		}
		$('.displaying-num .total-images').text(ewwwNumberFormat.format(ewww_total_images));
	}
	function ewwwAsyncInit() {
		var ewww_async_init_data = {
			action: 'ewww_bulk_async_init',
			ewww_aux_folders: ewww_aux_folders,
			ewww_delay: ewww_delay,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_scan_only: ewww_scan_only,
			ewww_webp_only: ewww_webp_only,
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		$.post(ajaxurl, ewww_async_init_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
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
			$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
			$('.ewww-bulk-spinner').hide();
		});
	}
	if (ewww_autopoll) {
		$('.ewww-bulk-spinner').show();
		ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
	}
	function ewwwUpdateAsyncBulkStatus() {
		var ewww_update_status_data = {
			action: 'ewww_bulk_async_get_status',
			ewww_wpnonce: ewww_bulk._wpnonce,
		};
		$.post(ajaxurl, ewww_update_status_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-optimize-local-images').html('<span class="ewww-bulk-error"><b>' + ewww_bulk.invalid_response + '</b></span>');
				$('.ewww-bulk-spinner').hide();
				console.log( response );
				return false;
			}
			if (ewww_response.new_nonce) {
				ewww_bulk._wpnonce = ewww_response.new_nonce;
			}
			if (ewww_scan_only) {
				$('.ewww-start-optimization').show();
				if (!ewww_table_visible && !ewww_hid_pending && ewww_response.images_queued > 0) {
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
			$('#ewww-optimize-local-images').html(ewww_bulk.bulk_refresh_error);
		});
	}
	function ewwwUpdateTable() {
		console.log('refreshing table/results');
		ewww_pointer = 0;
		var ewww_search = $('.ewww-search-input').val();
		if ( ! ewww_countdown ) {
			$('#ewww-show-table').hide();
			$('#ewww-hide-table').show();
		}
		$('#ewww-bulk-queue-images').hide();
		$('#ewww-bulk-controls').hide();
		$('#ewww-bulk-table-wrapper').show()
		$('#ewww-bulk-results').slideDown();
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			var ewww_response = ewwwParseTableResponse(response);
			$('.ewww-aux-table').show();
			if (ewww_response && ewww_pending == 0 && ewww_response.show_pending_button) {
				$('#ewww-search-pending').show();
			}
			if (! ewww_pending) {
				ewww_total_images = ewww_response.total_images;
			}
			if (ewww_response && ewww_response.total_images > 50) {
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			} else {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
			}
		});
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
	}
	function ewwwParseTableResponse(response) {
		try {
			var ewww_response = JSON.parse(response);
		} catch (err) {
			$('#ewww-bulk-table').html('<span style="color: red"><b>' + ewww_bulk.invalid_response + '</b></span>');
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
			ewww_total_pages  = ewww_response.total_pages;
		}
		$('.current-page-info').html(ewww_response.pagination);
		if (ewww_response.total_images_text) {
			$('.ewww-tablenav-pages .displaying-num').html(ewww_response.total_images_text);
		}
		return ewww_response;
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
		if (ewwwBulkFirstPage && ewwwBulkFirstPage.find('tbody tr').length > 0) {
			$('.current-page-info .current-page').text(1);
			ewwwBulkFirstPage.clone().replaceAll('#ewww-bulk-table table');
			$('.displaying-num .total-images').text(ewwwNumberFormat.format(ewww_total_images));
			if (ewwwBulkFirstPage.find('tbody tr').length >= 50) {
				ewww_total_pages = Math.ceil(ewww_total_images / 50);
				$('.current-page-info .total-pages').text(ewwwNumberFormat.format(ewww_total_pages));
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			}
			$('.prev-page').addClass('disabled');
			$('.first-page').addClass('disabled');
		} else {
			ewwwUpdateTable();
		}
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
			if ( ! ewww_countdown ) {
				$('.ewww-bulk-spinner').hide();
			}
			clearTimeout(ewww_autopoll_timeout);
		} else if (ewww_autopoll) {
			$('.ewww-bulk-spinner').show();
			ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
		}
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			var ewww_response = ewwwParseTableResponse(response);
			ewww_total_pages  = ewww_response.total_pages;
			if (ewww_response && ewww_response.total_images > 50) {
				$('.next-page').removeClass('disabled');
				$('.last-page').removeClass('disabled');
			} else {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
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
		if ( ! ewww_countdown ) {
			$('.ewww-bulk-spinner').hide();
		}
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer++;
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			var ewww_response = ewwwParseTableResponse(response);
			if (ewww_response && ewww_response.total_images <= ((ewww_pointer + 1) * 50)) {
				$('.next-page').addClass('disabled');
				$('.last-page').addClass('disabled');
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
		if ( ! ewww_countdown ) {
			$('.ewww-bulk-spinner').hide();
		}
		var ewww_search = $('.ewww-search-input').val();
		ewww_pointer--;
		if (! ewww_search && ! ewww_pending && 0 === ewww_pointer && ewwwBulkFirstPage && ewwwBulkFirstPage.find('tbody tr').length > 0) {
			$('.current-page-info .current-page').text(1);
			ewwwBulkFirstPage.clone().replaceAll('#ewww-bulk-table table');
		} else {
			var ewww_table_data = {
				action: ewww_table_action,
				ewww_wpnonce: ewww_bulk._wpnonce,
				ewww_offset: ewww_pointer,
				ewww_search: ewww_search,
				ewww_pending: ewww_pending,
				ewww_size_sort: ewww_size_sort,
			};
			$.post(ajaxurl, ewww_table_data, function(response) {
				ewwwParseTableResponse(response);
			});
		}
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
		if ( ! ewww_countdown ) {
			$('.ewww-bulk-spinner').hide();
		}
		clearTimeout(ewww_autopoll_timeout);
		var ewww_search     = $('.ewww-search-input').val();
		ewww_pointer        = ewww_total_pages - 1;
		var ewww_table_data = {
			action: ewww_table_action,
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_offset: ewww_pointer,
			ewww_search: ewww_search,
			ewww_pending: ewww_pending,
			ewww_size_sort: ewww_size_sort,
		};
		$.post(ajaxurl, ewww_table_data, function(response) {
			ewwwParseTableResponse(response);
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
		if (! ewww_search && ! ewww_pending && ewwwBulkFirstPage && ewwwBulkFirstPage.find('tbody tr').length > 0) {
			$('.current-page-info .current-page').text(1);
			ewwwBulkFirstPage.clone().replaceAll('#ewww-bulk-table table');
			$('.next-page').removeClass('disabled');
			$('.last-page').removeClass('disabled');
		} else {
			var ewww_table_data = {
				action: ewww_table_action,
				ewww_wpnonce: ewww_bulk._wpnonce,
				ewww_offset: ewww_pointer,
				ewww_search: ewww_search,
				ewww_pending: ewww_pending,
				ewww_size_sort: ewww_size_sort,
			};
			$.post(ajaxurl, ewww_table_data, function(response) {
				var ewww_response = ewwwParseTableResponse(response);
				if (ewww_response && ewww_response.total_images <= 50) {
					$('.next-page').addClass('disabled');
					$('.last-page').addClass('disabled');
				} else {
					$('.next-page').removeClass('disabled');
					$('.last-page').removeClass('disabled');
				}
				if (ewww_autopoll && ! ewww_search) {
					$('.ewww-bulk-spinner').show();
					ewww_autopoll_timeout = setTimeout(ewwwUpdateAsyncBulkStatus,20000);
				}
			});
		}
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-remove-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_removal = {
			action: 'bulk_aux_images_remove',
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_image_id: imageID,
			ewww_pending: ewww_pending,
		};
		$.post(ajaxurl, ewww_image_removal, function(response) {
			if(response == '1') {
				$('.ewww-image-' + imageID).remove();
				if (ewww_pending) {
					ewww_total_pending--;
					$('#ewww-optimize-local-images .ready-to-optimize-count').text(ewwwNumberFormat.format(ewww_total_pending));
					$('.displaying-num .total-images').text(ewwwNumberFormat.format(ewww_total_pending));
				} else {
					ewww_total_images--;
					$('.displaying-num .total-images').text(ewwwNumberFormat.format(ewww_total_images));
				}
			} else {
				alert(ewww_bulk.remove_failed);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-exclude-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_exclusion = {
			action: 'bulk_aux_images_exclude',
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_image_id: imageID,
		};
		$.post(ajaxurl, ewww_image_exclusion, function(response) {
			if(response == '1') {
				$('.ewww-image-' + imageID).remove();
				ewww_total_pending--;
				$('#ewww-optimize-local-images .ready-to-optimize-count').text(ewwwNumberFormat.format(ewww_total_pending));
				$('.displaying-num .total-images').text(ewwwNumberFormat.format(ewww_total_pending));
			} else {
				alert(ewww_bulk.remove_failed);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on( 'click', '.ewww-restore-image', function() {
		var imageID = $(this).data('id');
		var ewww_image_restore = {
			action: 'ewww_manual_image_restore_single',
			ewww_wpnonce: ewww_bulk._wpnonce,
			ewww_image_id: imageID,
		};
		var original_html = $('.ewww-image-' + imageID + ' td:last-child').html();
		$('.ewww-image-' + imageID + ' td:last-child').html(ewww_bulk.restoring);
		$.post(ajaxurl, ewww_image_restore, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				alert( ewww_bulk.invalid_response );
				console.log( response );
				return false;
			}
			if ( ewww_response.success == '1') {
				$('.ewww-image-' + imageID + ' td:last-child').html(ewww_bulk.original_restored);
			} else if (ewww_response.error) {
				$('.ewww-image-' + imageID + ' td:last-child').html(original_html);
				alert(ewww_response.error);
			}
		});
		return false;
	});
	$('.ewww-aux-table').on('click', '.ewww-show-debug-meta', function() {
		var post_id = $(this).data('id');
		$('.ewww-debug-meta-' + post_id).toggle();
	});
});
