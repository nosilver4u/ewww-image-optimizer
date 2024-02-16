function ewwwUpdateStatus(post_id,ewww_nonce,convert,poll_time,start_time) {
	var ewww_get_status_data = {
		action: 'ewww_manual_get_status',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	jQuery.post(ajaxurl, ewww_get_status_data, function(response) {
		if (poll_time<30000) { // Keep increasing the poll time until it reaches 30 seconds.
			poll_time=poll_time+1000;
		}
		var ewww_status_response = JSON.parse(response);
		if (ewww_status_response.pending) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_status_response.output );
			if (Math.round(performance.now()/1000)-start_time>360) { // Stop polling at 6 minutes.
				jQuery('#ewww-status-loading-' + post_id ).remove();	
				console.log('ewwwUpdateStatus exceeded 300 seconds');
				return;
			}
			console.log('checking status again in ' + poll_time + 'ms for image #' + post_id);
			setTimeout(ewwwUpdateStatus, poll_time, post_id, ewww_nonce, 0, poll_time, start_time);
		} else if (ewww_status_response.output) {
			jQuery('#ewww-media-status-' + post_id).parent().html( ewww_status_response.output );
		}
		if (convert && ewww_status_response.basename) {
			var attachment_span = jQuery('#post-' + post_id + ' .column-title .filename .screen-reader-text').html();
			jQuery('#post-' + post_id + ' .column-title .filename').html('<span class="screen-reader-text">' + attachment_span + '</span>' + ewww_status_response.basename);
		}
	});
}
jQuery(document).on('click', '.ewww-manual-optimize', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_optimize',
		ewww_manual_nonce: ewww_nonce,
		ewww_force: 1,
		ewww_attachment_ID: post_id,
	};
	post_id = jQuery(this).closest('.ewww-media-status').data('id');
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.optimizing );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.success );
			if (1==ewww_vars.async_allowed) {
				jQuery('#ewww-media-status-' + post_id ).parent().append('<div id="ewww-status-loading-'+ post_id + '">' + ewww_vars.loading_img + '</div>');
				setTimeout(ewwwUpdateStatus, 3000, post_id, ewww_nonce, 0, 3000, Math.round(performance.now()/1000));
			}
		}
		if (ewww_manual_response.basename) {
			var attachment_span = jQuery('#post-' + post_id + ' .column-title .filename .screen-reader-text').html();
			jQuery('#post-' + post_id + ' .column-title .filename').html('<span class="screen-reader-text">' + attachment_span + '</span>' + ewww_manual_response.basename);
		}
	});
	return false;
});
jQuery(document).on('click', '.ewww-manual-convert', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_optimize',
		ewww_manual_nonce: ewww_nonce,
		ewww_force: 1,
		ewww_convert: 1,
		ewww_attachment_ID: post_id,
	};
	post_id = jQuery(this).closest('.ewww-media-status').data('id');
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.optimizing );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.success );
			if (1==ewww_vars.async_allowed) {
				jQuery('#ewww-media-status-' + post_id ).parent().append('<div id="ewww-status-loading-'+ post_id + '">' + ewww_vars.loading_img + '</div>');
				setTimeout(ewwwUpdateStatus, 3000, post_id, ewww_nonce, 1, 3000, Math.round(performance.now()/1000));
			}
		}
		if (ewww_manual_response.basename) {
			var attachment_span = jQuery('#post-' + post_id + ' .column-title .filename .screen-reader-text').html();
			jQuery('#post-' + post_id + ' .column-title .filename').html('<span class="screen-reader-text">' + attachment_span + '</span>' + ewww_manual_response.basename);
		}
	});
	return false;
});
jQuery(document).on('click', '.ewww-manual-restore', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_restore',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	post_id = jQuery(this).closest('.ewww-media-status').data('id');
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.restoring );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.success );
		}
		if (ewww_manual_response.basename) {
			var attachment_span = jQuery('#post-' + post_id + ' .column-title .filename .screen-reader-text').html();
			jQuery('#post-' + post_id + ' .column-title .filename').html('<span class="screen-reader-text">' + attachment_span + '</span>' + ewww_manual_response.basename);
		}
	});
	return false;
});
jQuery(document).on('click', '.ewww-manual-image-restore', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_image_restore',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	post_id = jQuery(this).closest('.ewww-media-status').data('id');
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.restoring );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).replaceWith( ewww_manual_response.success );
		}
	});
	return false;
});
jQuery(document).on('click', '.ewww-show-debug-meta', function() {
	var post_id = jQuery(this).data('id');
	jQuery('#ewww-debug-meta-' + post_id).toggle();
});
jQuery(document).on('click', '#ewww-image-optimizer-media-listmode .notice-dismiss', function() {
	var ewww_dismiss_media_data = {
		action: 'ewww_dismiss_media_notice',
		_wpnonce: ewww_vars.notice_nonce,
	};
	jQuery.post(ajaxurl, ewww_dismiss_media_data, function(response) {
		if (response) {
			console.log(response);
		}
	});
});
