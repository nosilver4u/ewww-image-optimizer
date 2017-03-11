jQuery(document).on( 'click', '.ewww-manual-optimize', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_flag_manual',
		ewww_manual_nonce: ewww_nonce,
		ewww_force: 1,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-flag-status-' + post_id ).html( ewww_vars.optimizing );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = jQuery.parseJSON(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-flag-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-flag-status-' + post_id ).html( ewww_manual_response.success );
		}
	});
	return false;
});
/*jQuery(document).on( 'click', '.ewww-manual-convert', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_optimize',
		ewww_manual_nonce: ewww_nonce,
		ewww_force: 1,
		ewww_convert: 1,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.optimizing );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = jQuery.parseJSON(response);
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
jQuery(document).on( 'click', '.ewww-manual-restore', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_manual_restore',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-media-status-' + post_id ).html( ewww_vars.restoring );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = jQuery.parseJSON(response);
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
});*/
jQuery(document).on( 'click', '.ewww-manual-cloud-restore', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_flag_cloud_restore',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-flag-status-' + post_id ).html( ewww_vars.restoring );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = jQuery.parseJSON(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-flag-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-flag-status-' + post_id ).html( ewww_manual_response.success );
		}
	});
	return false;
});
