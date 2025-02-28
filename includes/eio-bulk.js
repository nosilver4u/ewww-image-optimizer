jQuery(document).ready(function($) {
	var ewww_error_counter = 30;
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
	var ewww_attachments = ewww_vars.attachments;
	var ewww_i = 0;
	var ewww_k = 0;
	var ewww_import_total = 0;
	var ewww_force = 0;
	var ewww_force_smart = 0;
	var ewww_webp_only = 0;
	var ewww_delay = 0;
	var ewww_batch_limit = 0;
	var ewww_aux = false;
	var ewww_main = false;
	var ewww_quota_update = 0;
	var ewww_scan_failures = 0;
	var ewww_bulk_start_time = 0;
	var ewww_bulk_elapsed_time = 0;
	var ewww_time_per_image = 0;
	var ewww_time_remaining = 0;
	var ewww_days_remaining = 0;
	var ewww_hours_remaining = 0;
	var ewww_minutes_remaining = 0;
	var ewww_seconds_remaining = 0;
	var ewww_countdown = false;
	var ewww_tiny_skip = '';
	// initialize the ajax actions for the appropriate bulk page
	var ewww_quota_update_data = {
		action: 'bulk_quota_update',
		ewww_wpnonce: ewww_vars._wpnonce,
	};
	if (ewww_vars.gallery == 'flag') {
		var ewww_init_action = 'bulk_flag_init';
		var ewww_loop_action = 'bulk_flag_loop';
		var ewww_cleanup_action = 'bulk_flag_cleanup';
	} else if (ewww_vars.gallery == 'nextgen') {
		var ewww_init_action = 'bulk_ngg_init';
		var ewww_loop_action = 'bulk_ngg_loop';
		var ewww_cleanup_action = 'bulk_ngg_cleanup';
	} else {
		var ewww_scan_action = 'bulk_scan';
		var ewww_init_action = 'bulk_init';
		var ewww_loop_action = 'bulk_loop';
		var ewww_cleanup_action = 'bulk_cleanup';
		ewww_main = true;
	}
	var ewww_init_data = {
		action: ewww_init_action,
		ewww_wpnonce: ewww_vars._wpnonce,
	};
	var ewww_table_action = 'bulk_aux_images_table';
	var ewww_table_count_action = 'bulk_aux_images_table_count';
	var ewww_import_init_action = 'bulk_import_init';
	var ewww_import_loop_action = 'bulk_import_loop';
	$(document).on('click', '.ewww-show-debug-meta', function() {
		var post_id = $(this).data('id');
		$('.ewww-debug-meta-' + post_id).toggle();
	});
	$('.ewww-hndle').click(function() {
		return;
		$(this).next('.inside').toggle();
		var button = $(this).prev('.button-link');
		if ('true' == button.attr('aria-expanded')) {
			button.attr('aria-expanded', 'false');
			button.closest('.postbox').addClass('closed');
			button.children('.toggle-indicator').attr('aria-hidden', 'true');
		} else {
			button.attr('aria-expanded', 'true');
			button.closest('.postbox').removeClass('closed');
			button.children('.toggle-indicator').attr('aria-hidden', 'false');
		}
	});
	$('.ewww-handlediv').click(function() {
		$(this).parent().children('.inside').toggle();
		if ('true' == $(this).attr('aria-expanded')) {
			$(this).attr('aria-expanded', 'false');
			$(this).closest('.postbox').addClass('closed');
			$(this).children('.toggle-indicator').attr('aria-hidden', 'true');
		} else {
			$(this).attr('aria-expanded', 'true');
			$(this).closest('.postbox').removeClass('closed');
			$(this).children('.toggle-indicator').attr('aria-hidden', 'false');
		}
	});
	$('#ewww-aux-start').submit(function() {
		ewww_aux = true;
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		if ($('#ewww-force-smart:checkbox:checked').val()) {
			ewww_force_smart = 1;
		}
		if ($('#ewww-webp-only:checkbox:checked').val()) {
			ewww_webp_only = 1;
		}
		$('#ewww-aux-start').hide();
		$('.ewww-bulk-info').hide();
		$('.ewww-aux-table').hide();
		$('#ewww-show-table').hide();
		$('#ewww-scanning').show();
		ewwwStartScan();
		return false;
	});
	function ewwwStartScan() {
		var ewww_scan_data = {
			action: ewww_scan_action,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_scan: true,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_scan_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json ) {
				$('#ewww-scanning').html('<span class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></span>');
				console.log( response );
				return false;
			}
			ewww_init_data = {
				action: ewww_init_action,
				ewww_wpnonce: ewww_vars._wpnonce,
			};
			if ( ewww_response.error ) {
				$('#ewww-scanning').html('<span class="ewww-bulk-error"><b>' + ewww_response.error + '</b></span>');
			} else if ( ewww_response.remaining ) {
				$('.ewww-aux-table').hide();
				$('#ewww-show-table').hide();
				$('#ewww-scanning').html( ewww_response.remaining );
				if ( ewww_response.notice ) {
					$('#ewww-scanning').append( '<br>' + ewww_response.notice );
				}
				if ( ewww_response.tiny_skip ) {
					$('#ewww-scanning').append( '<br>' + ewww_response.tiny_skip );
					ewww_tiny_skip = ewww_response.tiny_skip;
					console.log( 'skipped some tiny images' );
				}
				if ( ewww_response.bad_attachment ) {
					$('#ewww-scanning').append( '<br>' + ewww_vars.bad_attachment + ' ' + ewww_response.bad_attachment );
				}
				ewww_scan_failures = 0;
				ewwwStartScan();
			} else if ( ewww_response.ready ) {
				ewww_attachments = ewww_response.ready;
				$('#ewww-scanning').html(ewww_response.message);
				if ( ewww_tiny_skip ) {
					$('#ewww-scanning').append( '<br><i>' + ewww_tiny_skip + '</i>' );
					console.log( 'done, skipped some tiny images' );
				}
				$('#ewww-bulk-first').val(ewww_response.start_button);
				$('#ewww-bulk-start').show();
			} else if ( ewww_response.ready === 0 ) {
				$('#ewww-scanning').hide();
				$('#ewww-nothing').show();
				if ( ewww_tiny_skip ) {
					$('#ewww-nothing').append( '<br><i>' + ewww_tiny_skip + '</i>' );
					console.log( 'done, skipped some tiny images' );
				}
			}
		})
		.fail(function() {
			ewww_scan_failures++;
			if (ewww_scan_failures > 10) {
				$('#ewww-scanning').html('<span class="ewww-bulk-error"><b>' + ewww_vars.scan_fail + ':</b> ' + ewww_vars.bulk_fail_more + '</span>');
			} else {
				$('#ewww-scanning').html('<span class="ewww-bulk-error"><b>' + ewww_vars.scan_incomplete + '</b></span>');
				setTimeout(function() {
					ewwwStartScan();
				}, 1000);
			}
		});
	}
	$('#ewww-bulk-start').submit(function() {
		ewwwStartOpt();
		return false;
	});
	function ewwwUpdateQuota() {
		if ($('#ewww-bulk-credits-available').length > 0) {
			ewww_quota_update_data.ewww_wpnonce = ewww_vars._wpnonce;
			$.post(ajaxurl, ewww_quota_update_data, function(response) {
				$('#ewww-bulk-credits-available').html(response);
			});
		}
	}
	function ewwwStartOpt () {
		ewww_k = 0;
		ewww_quota_update = setInterval( ewwwUpdateQuota, 60000 );
		$('#ewww-bulk-stop').submit(function() {
			ewww_k = 9;
			$('#ewww-bulk-stop').hide();
			return false;
		});
		if ( ! $('#ewww-delay').val().match( /^[1-9][0-9]*$/) ) {
			ewww_delay = 0;
		} else {
			ewww_delay = $('#ewww-delay').val();
		}
		if (ewww_delay) {
			ewww_batch_limit = 1;
			$('#ewww-bulk-last h2').html( ewww_vars.last_image_header );
		}
		$('.ewww-aux-table').hide();
		$('#ewww-bulk-stop').show();
		$('.ewww-bulk-form').hide();
		$('.ewww-bulk-info').hide();
		$('#ewww-bulk-forms').hide();
		$('h2').hide();
		$.post(ajaxurl, ewww_init_data, function(response) {
			var is_json = true;
			try {
				var ewww_init_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json || ! response ) {
				$('#ewww-bulk-loading').append('<p class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></p>');
				console.log( response );
				return false;
			}
			if ( ewww_init_response.error ) {
				$('#ewww-bulk-loading').append('<p class="ewww-bulk-error"><b>' + ewww_init_response.error + '</b></p>');
				if ( ewww_init_response.data ) {
					console.log( ewww_init_response.data );
				}
			} else {
				if ( ewww_init_response.start_time ) {
					ewww_bulk_start_time = ewww_init_response.start_time;
				}
				$('#ewww-bulk-loading').html(ewww_init_response.results);
				$('#ewww-bulk-progressbar').progressbar({ max: ewww_attachments });
				$('#ewww-bulk-counter').html( ewww_vars.optimized + ' 0/' + ewww_attachments);
				ewwwProcessImage();
			}
		});
	}
	function ewwwProcessImage() {
		if ($('#ewww-force:checkbox:checked').val()) {
			ewww_force = 1;
		}
		if ($('#ewww-force-smart:checkbox:checked').val()) {
			ewww_force_smart = 1;
		}
		if ($('#ewww-webp-only:checkbox:checked').val()) {
			ewww_webp_only = 1;
		}
		var ewww_loop_data = {
			action: ewww_loop_action,
			ewww_wpnonce: ewww_vars._wpnonce,
			ewww_force: ewww_force,
			ewww_force_smart: ewww_force_smart,
			ewww_webp_only: ewww_webp_only,
			ewww_batch_limit: ewww_batch_limit,
			ewww_error_counter: ewww_error_counter,
		};
		var ewww_jqxhr = $.post(ajaxurl, ewww_loop_data, function(response) {
			var is_json = true;
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				is_json = false;
			}
			if ( ! is_json || ! response ) {
				$('#ewww-bulk-loading').append('<p class="ewww-bulk-error"><b>' + ewww_vars.invalid_response + '</b></p>');
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				if ( ! response ) {
					console.log( 'empty response' );
				} else {
					console.log( response );
				}
				return false;
			}
			ewww_i += ewww_response.completed;
			if ( ewww_response.add_to_total > 0 ) {
				ewww_attachments = ewww_attachments + ewww_response.add_to_total;
				$('#ewww-bulk-progressbar').progressbar({ max: ewww_attachments });
			}
			$('#ewww-bulk-progressbar').progressbar( "option", "value", ewww_i );
			$('#ewww-bulk-counter').html(ewww_vars.optimized + ' ' + ewww_i + '/' + ewww_attachments);
			if ( ewww_response.update_meta ) {
	        		var ewww_updatemeta_data = {
					action: 'ewww_bulk_update_meta',
					attachment_id: ewww_response.update_meta,
					ewww_wpnonce: ewww_vars._wpnonce,
				};
				$.post(ajaxurl, ewww_updatemeta_data);
			}
			if ( ewww_response.error ) {
				$('#ewww-bulk-loading img').hide();
				$('#ewww-bulk-progressbar').hide();
				$('#ewww-bulk-timer').hide();
				$('#ewww-bulk-counter').hide();
				$('#ewww-bulk-stop').hide();
				$('#ewww-bulk-loading').append('<p class="ewww-bulk-error"><b>' + ewww_response.error + '</b></p>');
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				ewwwUpdateQuota();
			}
			else if (ewww_k == 9) {
				if ( ewww_response.results ) {
					$('#ewww-bulk-last .inside').html( ewww_response.results );
					$('#ewww-bulk-status .inside').append( ewww_response.results );
				}
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				$('#ewww-bulk-loading').html('<p class="ewww-bulk-error"><b>' + ewww_vars.operation_stopped + '</b></p>');
			}
			else if ( response == 0 ) {
				clearInterval(ewww_quota_update);
				clearInterval(ewww_countdown);
				$('#ewww-bulk-loading').html('<p class="ewww-bulk-error"><b>' + ewww_vars.operation_stopped + '</b></p>');
			}
			else if ( ewww_i < ewww_attachments && ! ewww_response.done ) {
				if ( ewww_bulk_start_time && ewww_response.current_time ) {
					ewww_bulk_elapsed_time = ewww_response.current_time - ewww_bulk_start_time;
					ewww_time_per_image = ewww_bulk_elapsed_time / ewww_i;
					ewww_time_remaining = Math.floor((ewww_attachments - ewww_i) * ewww_time_per_image);
					ewwwTimeIncrementsUpdate();
					if ( ! ewww_countdown) {
						$('#ewww-bulk-timer').html(ewww_days_remaining + ':' + ewww_hours_remaining + ':' + ewww_minutes_remaining + ':' + ewww_seconds_remaining + ' ' + ewww_vars.time_remaining);
						ewww_countdown = setInterval( ewwwCountDown, 1000 );
					}
				}
				$('#ewww-bulk-widgets').show();
				$('#ewww-bulk-status h2').show();
				$('#ewww-bulk-last h2').show();
				if ( ewww_response.results ) {
					$('#ewww-bulk-last .inside').html( ewww_response.results );
					$('#ewww-bulk-status .inside').append( ewww_response.results );
				}
				if ( ewww_response.next_file ) {
					$('#ewww-bulk-loading').html(ewww_response.next_file);
				}
				if ( ewww_response.new_nonce ) {
					ewww_vars._wpnonce = ewww_response.new_nonce;
				}
				ewww_error_counter = 30;
				setTimeout(ewwwProcessImage, ewww_delay * 1000);
			}
			else {
				if ( ewww_response.results ) {
					$('#ewww-bulk-widgets').show();
					$('#ewww-bulk-status h2').show();
		                	$('#ewww-bulk-status .inside').append( ewww_response.results );
				}
				var ewww_cleanup_data = {
					action: ewww_cleanup_action,
					ewww_wpnonce: ewww_vars._wpnonce,
				};
				$.post(ajaxurl, ewww_cleanup_data, function(response) {
					$('#ewww-bulk-loading').html(response);
					$('#ewww-bulk-stop').hide();
					$('#ewww-bulk-last').hide();
					ewwwAuxCleanup();
				});
			}
		})
		.fail(function() {
			if (ewww_error_counter == 0) {
				$('#ewww-bulk-loading').html('<p class="ewww-bulk-error"><b>' + ewww_vars.operation_interrupted + ':</b> ' + ewww_vars.bulk_fail_more + '</p>');
			} else {
				$('#ewww-bulk-loading').html('<p class="ewww-bulk-error"><b>' + ewww_vars.temporary_failure + ' ' + ewww_error_counter + ' (' + ewww_vars.bulk_fail_more + ')</b></p>');
				ewww_error_counter--;
				setTimeout(function() {
					ewwwProcessImage();
				}, 1000);
			}
		});
	}
	function ewwwAuxCleanup() {
		if (ewww_main == true) {
			clearInterval(ewww_quota_update);
			clearInterval(ewww_countdown);
			var ewww_table_count_data = {
				action: ewww_table_count_action,
				ewww_wpnonce: ewww_vars._wpnonce,
				ewww_inline: 1,
			};
			$.post(ajaxurl, ewww_table_count_data, function(response) {
				ewww_vars.image_count = response;
			});
			$('#ewww-show-table').show();
			$('#ewww-table-info').show();
			$('#ewww-bulk-timer').hide();
			if (ewww_aux == true) {
				$('#ewww-aux-first').hide();
			} else {
				$('#ewww-bulk-first').hide();
			}
			ewww_attachments = ewww_vars.attachments;
			ewww_init_action = 'bulk_init';
			ewww_loop_action = 'bulk_loop';
			ewww_cleanup_action = 'bulk_cleanup';
			ewww_init_data = {
			        action: ewww_init_action,
				ewww_wpnonce: ewww_vars._wpnonce,
			};
			ewww_aux = false;
			ewww_i = 0;
			ewww_force = 0;
			ewww_force_smart = 0;
			ewww_webp_only = 0;
		}
	}
	function ewwwCountDown() {
		if (ewww_time_remaining > 1) {
			ewww_time_remaining--;
		}
		ewwwTimeIncrementsUpdate();
		$('#ewww-bulk-timer').html(ewww_days_remaining + ':' + ewww_hours_remaining + ':' + ewww_minutes_remaining + ':' + ewww_seconds_remaining + ' ' + ewww_vars.time_remaining);
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
});
