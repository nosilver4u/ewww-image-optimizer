jQuery(document).ready(function($) {
	var ewww_error_counter = 30;
	var init_action        = 'webp_init';
	var loop_action        = 'webp_loop';
	var cleanup_action     = 'webp_cleanup';
	var ewww_webp_images   = ewww_vars.webp_images;
	var slice_start        = 0;
	var slice_end          = 500;
	var init_data          = {
		action: init_action,
		ewww_wpnonce: ewww_vars.ewww_wpnonce,
	};
	$('#webp-start').submit(function() {
		startMigrate();
		return false;
	});
	function startMigrate () {
		$('.webp-form').hide();
		$.post(ajaxurl, init_data, function(response) {
			$('#webp-loading').html(response);
			processLoop();
		});
	}
	function processLoop () {
		var webp_slice = ewww_webp_images.slice(slice_start, slice_end);
		var loop_data = {
			action: loop_action,
			ewww_wpnonce: ewww_vars.ewww_wpnonce,
			webp_images: webp_slice,
		};
		$.post(ajaxurl, loop_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#webp-loading').html('<p style="color: red"><b>' + ewww_vars.invalid_response + '</b></p>');
				$('#webp-loading').show();
				console.log(err);
				console.log(response);
				return false;
			}
			if (ewww_response.error) {
				$('#webp-loading').html('<p style="color: red"><b>' + ewww_response.error + '</b></p>');
				$('#webp-loading').show();
				return false;
			} else if (ewww_response.output && ewww_webp_images.length > slice_end) {
				ewww_error_counter = 30;
				slice_start += 500;
				slice_end   += 500;
				$('#webp-status').append( ewww_response.output );
				$('#webp-loading').hide();
				processLoop();
			} else {
				if (ewww_response.output) {
					$('#webp-status').append( ewww_response.output );
				}
				var cleanup_data = {
					action: cleanup_action,
					ewww_wpnonce: ewww_vars.ewww_wpnonce,
				};
				$.post(ajaxurl, cleanup_data, function(response) {
					$('#webp-loading').hide();
					$('#webp-status').append(response);
				});
			}
		})
		.fail(function() {
			if (ewww_error_counter == 0) {
				$('#webp-loading').html('<p style="color: red"><b>' + ewww_vars.interrupted + '</b></p>');
				$('#webp-loading').show();
			} else {
				$('#webp-loading').html('<p style="color: red"><b>' + ewww_vars.retrying + ' ' + ewww_error_counter + '</b></p>');
				$('#webp-loading').show();
				ewww_error_counter--;
				setTimeout(function() {
					processLoop();
				}, 5000);
			}
		});
	}
});
