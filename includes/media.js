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
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.success );
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
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.success );
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
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.success );
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
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-media-status-' + post_id ).html( ewww_manual_response.success );
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
	};
	jQuery.post(ajaxurl, ewww_dismiss_media_data, function(response) {
		if (response) {
			console.log(response);
		}
	});
});
