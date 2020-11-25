jQuery(document).on( 'click', '.ewww-manual-optimize', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_ngg_manual',
		ewww_manual_nonce: ewww_nonce,
		ewww_force: 1,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_vars.optimizing );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_manual_response.success );
		}
	});
	return false;
});
jQuery(document).on( 'click', '.ewww-manual-cloud-restore', function() {
	var post_id = jQuery(this).data('id');
	var ewww_nonce = jQuery(this).data('nonce');
	var ewww_manual_optimize_data = {
		action: 'ewww_ngg_cloud_restore',
		ewww_manual_nonce: ewww_nonce,
		ewww_attachment_ID: post_id,
	};
	jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_vars.restoring );
	jQuery.post(ajaxurl, ewww_manual_optimize_data, function(response) {
		var ewww_manual_response = JSON.parse(response);
		if (ewww_manual_response.error) {
			jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_manual_response.error );
		} else if (ewww_manual_response.success) {
			jQuery('#ewww-nextcellent-status-' + post_id ).html( ewww_manual_response.success );
		}
	});
	return false;
});
