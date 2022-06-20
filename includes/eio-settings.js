jQuery(document).ready(function($) {
	// Wizard bits:
	$('input:radio[name="ewww_image_optimizer_budget"]').on(
		'change',
		function() {
			if (this.checked && this.value === 'pay') {
				$('.ewwwio-premium-setup').show();
			} else {
				$('.ewwwio-premium-setup').hide();
				$('#ewww-image-optimizer-warning-opt-missing').show();
				$('#ewww-image-optimizer-warning-exec').show();
			}
		}
	);
	var ewww_wizard_required_checkboxes = $('#ewwwio-wizard-step-1 :checkbox[required]');
	ewww_wizard_required_checkboxes.on(
		'change',
		function() {
			if (ewww_wizard_required_checkboxes.is(':checked')) {
				ewww_wizard_required_checkboxes.removeAttr('required');
			} else {
				ewww_wizard_required_checkboxes.attr('required', 'required');
			}
		}
	);
	$('#ewwwio-wizard-step-1').on(
		'submit',
		function() {
			return;
			$(this).hide();
			$('#ewwwio-wizard-step-2').show();
			return false;
		}
	);
	$('.ewwwio-wizard-back').on(
		'click',
		function() {
			return;
			$('#ewwwio-wizard-step-2').hide();
			$('#ewwwio-wizard-step-1').show();
			return false;
		}
	);
	function removeQueryArg(url) {
		return url.split('?')[0];
	}
	$('.fade').fadeTo(5000,1).fadeOut(3000);
	var speed_bar_width = $('#ewww-speed-fill').data('score');
	$('#ewww-speed-fill').animate( {
		width: speed_bar_width + '%',
	}, 1000 );
	var ewww_save_bar_width = $('#ewww-savings-fill').data('score');
	$('#ewww-savings-fill').animate( {
		width: ewww_save_bar_width + '%',
	}, 1000 );
	var easy_save_bar_width = $('#easyio-savings-fill').data('score');
	$('#easyio-savings-fill').animate( {
		width: easy_save_bar_width + '%',
	}, 1000 );
	$('#ewww-show-recommendations a').on(
		'click',
		function() {
			$('.ewww-recommend').toggle();
			$('#ewww-show-recommendations a').toggle();
			return false;
		}
	);
	$('#ewww_image_optimizer_cloud_key').on('keydown', function(event){
		if (event.which === 13) {
			$('#ewwwio-api-activate').trigger('click');
			return false;
		}
	});
	$('#ewwwio-api-activate').on('click', function() {
		var ewww_post_action = 'ewww_cloud_key_verify';
		var ewww_post_data = {
			action: ewww_post_action,
			compress_api_key: $('#ewww_image_optimizer_cloud_key').val(),
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$('#ewwwio-api-activate').hide();
		$('#ewwwio-api-activation-processing').show();
		$('#ewwwio-api-activation-result').hide();
		$.post(ajaxurl, ewww_post_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewwwio-api-activation-processing').hide();
				$('#ewwwio-api-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-api-activation-result').addClass('error');
				$('#ewwwio-api-activation-result').show();
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewwwio-api-activation-processing').hide();
				$('#ewwwio-api-activate').show();
				$('#ewwwio-api-activation-result').html(ewww_response.error);
				$('#ewwwio-api-activation-result').addClass('error');
				$('#ewwwio-api-activation-result').show();
			} else if ( ! ewww_response.success ) {
				$('#ewwwio-api-activation-processing').hide();
				$('#ewwwio-api-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-api-activation-result').addClass('error');
				$('#ewwwio-api-activation-result').show();
				console.log( response );
			} else {
				$('#ewwwio-api-activation-processing').hide();
				$('#ewwwio-api-activate').html('<span class="dashicons dashicons-yes"></span>');
				$('#ewwwio-api-activate').show();
				$('#ewwwio-api-activation-result').html(ewww_response.success);
				$('#ewwwio-api-activation-result').removeClass('error');
				$('#ewwwio-api-activation-result').show();
				$('.easyio-manual-ui').hide();
				$('.easyio-cloud-key-ui').show();
				$('#ewww_image_optimizer_cloud_key').attr('type', 'password');
				ewww_vars.easy_autoreg = 1;
				ewwwCloudEnable();
			}
		});
		return false;
	});
	function ewwwCloudEnable() {
		if (
			$('#ewww_image_optimizer_jpg_level').val() < 20 &&
			$('#ewww_image_optimizer_png_level').val() < 20 &&
			$('#ewww_image_optimizer_gif_level').val() < 20 &&
			$('#ewww_image_optimizer_pdf_level').val() < 10
		) {
			$('#ewww_image_optimizer_jpg_level option').prop('disabled', false);
			$('#ewww_image_optimizer_png_level option').prop('disabled', false);
			$('#ewww_image_optimizer_pdf_level option').prop('disabled', false);
			$('#ewww_image_optimizer_svg_level option').prop('disabled', false);
			$('#ewww_image_optimizer_backup_files').prop('disabled', false);
			$('#ewww_image_optimizer_jpg_level').val(30);
			$('#ewww_image_optimizer_png_level').val(20);
			$('#ewww_image_optimizer_gif_level').val(10);
			$('#ewww_image_optimizer_pdf_level').val(10);
			$('#ewww_image_optimizer_svg_level').val(10);
			$('#ewww_image_optimizer_backup_files').prop('checked', true);
		}
	}
	var easyio_registration_error = '';
	$('#ewwwio-easy-activate').on( 'click', function() {
		$('#ewwwio-easy-activate').hide();
		$('#ewwwio-easy-deregister').hide();
		$('#ewwwio-easy-activation-result').hide();
		$('#ewwwio-easy-activation-processing').show();
		if (! ewww_vars.easyio_site_registered && ewww_vars.easy_autoreg) {
			var ewww_post_data = {
				action: 'ewww_exactdn_register_site',
				ewww_wpnonce: ewww_vars._wpnonce,
				blog_id: ewww_vars.blog_id,
			};
			$.post(ajaxurl, ewww_post_data, function(response) {
				try {
					var ewww_response = JSON.parse(response);
				} catch (err) {
					$('#ewwwio-easy-activation-processing').hide();
					$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
					$('#ewwwio-easy-activation-result').addClass('error');
					$('#ewwwio-easy-activation-result').show();
					console.log( 'registration response: ' + response );
					return false;
				}
				if ( ewww_response.error ) {
					easyio_registration_error = ewww_response.error;
				} else if ( ! ewww_response.status ) {
					$('#ewwwio-easy-activation-processing').hide();
					$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
					$('#ewwwio-easy-activation-result').addClass('error');
					$('#ewwwio-easy-activation-result').show();
					console.log( 'registration response: ' + response );
					return false;
				}
				setTimeout( activateExactDNSite, 5000 );
				return false;
			});
		} else {
			activateExactDNSite();
			return false;
		}
		return false;
	});
	function activateExactDNSite() {
		var ewww_post_action = 'ewww_exactdn_activate';
		var ewww_post_data = {
			action: ewww_post_action,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_post_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				console.log( response );
				return false;
			}
			if ( ewww_response.error ) {
				if ( easyio_registration_error ) {
					ewww_response.error = easyio_registration_error + '<br>' + ewww_response.error;
				}
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activate').show();
				$('#ewwwio-easy-activation-result').html(ewww_response.error);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
			} else if ( ! ewww_response.success ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				console.log( response );
			} else {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_response.success);
				$('#ewwwio-easy-activation-result').removeClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('.ewwwio-exactdn-options input').prop('disabled', false);
				$('.ewwwio-exactdn-options').show();
				$('.ewwwio-easy-setup-instructions').hide();
				$('#ewww_image_optimizer_webp_container').hide();
				$('.ewww_image_optimizer_webp_setting_container').hide();
				$('.ewww_image_optimizer_webp_rewrite_setting_container').hide();
				$('#ewww_image_optimizer_webp_easyio_container').show();
			}
		});
		return false;
	}
	var exactdn_blog_ids   = [];
	var exactdn_blog_count = 0;
	var exactdn_cancelled  = false;
	$('#ewwwio-easy-activate-network').on( 'click', function() {
		if (!confirm(ewww_vars.exactdn_network_warning)) {
			return false;
		}
		exactdn_blog_ids   = Array.from(ewww_vars.network_blog_ids);
		exactdn_blog_count = exactdn_blog_ids.length;
		$('#ewww_image_optimizer_exactdn_container .button-secondary').hide();
		$('#ewwwio-easy-cancel-network-operation').show();
		$('#ewwwio-easy-activation-result').hide();
		$('#ewwwio-easy-activation-processing').show();
		$('#ewwwio-easy-activation-progressbar').progressbar({ max: exactdn_blog_count });
		$('#ewwwio-easy-activation-progressbar').show();
		activateExactDNMultiSite();
		return false;
	});
	function activateExactDNMultiSite() {
		var ewww_post_data = {
			action: 'ewww_exactdn_activate_site',
			ewww_wpnonce: ewww_vars._wpnonce,
			blog_id: exactdn_blog_ids.pop(),
		};
		$.post(ajaxurl, ewww_post_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				console.log( response );
				return false;
			}
			var exactdn_blogs_done = exactdn_blog_count - exactdn_blog_ids.length;
			if ( ewww_response.error ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-errors').append('<p>' + ewww_response.error + '</p>');
				$('#ewwwio-easy-activation-errors').show();
				$('#ewwwio-easy-activation-progressbar').progressbar('option', 'value', exactdn_blogs_done);
			} else if ( ! ewww_response.success ) {
				$('#ewwwio-easy-activate-network').show();
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				console.log( response );
				return false;
			} else {
				$('#ewwwio-easy-activation-processing').hide();
				$('.ewwwio-easy-setup-instructions').hide();
				$('p.ewwwio-easy-description').hide();
				$('#ewwwio-easy-activation-progressbar').progressbar('option', 'value', exactdn_blogs_done);
			}
			if ( exactdn_blog_ids.length ) {
				activateExactDNMultiSite();
			} else {
				$('#ewwwio-easy-cancel-network-operation').hide();
				if (exactdn_cancelled) {
					$('#ewwwio-easy-activation-result').html(ewww_vars.operation_stopped);
				} else {
					$('#ewwwio-easy-activation-result').html(ewww_vars.exactdn_network_success);
				}
				$('#ewwwio-easy-activation-result').removeClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('.ewwwio-exactdn-options input').prop('disabled', false);
				$('.ewwwio-exactdn-options').show();
				$('#ewwwio-easy-activation-progressbar').hide();
				$('#ewww_image_optimizer_webp_container').hide();
				$('.ewww_image_optimizer_webp_setting_container').hide();
				$('.ewww_image_optimizer_webp_rewrite_setting_container').hide();
				$('#ewww_image_optimizer_webp_easyio_container').show();
			}
		});
		return false;
	}
	$('#ewwwio-easy-register-network').on( 'click', function() {
		if (!confirm(ewww_vars.easyio_register_warning)) {
			return false;
		}
		exactdn_blog_ids   = Array.from(ewww_vars.network_blog_ids);
		exactdn_blog_count = exactdn_blog_ids.length;
		$('#ewww_image_optimizer_exactdn_container .button-secondary').hide();
		$('#ewwwio-easy-cancel-network-operation').show();
		$('#ewwwio-easy-activation-result').hide();
		$('#ewwwio-easy-activation-processing').show();
		$('#ewwwio-easy-activation-progressbar').progressbar({ max: exactdn_blog_count });
		$('#ewwwio-easy-activation-progressbar').show();
		registerExactDNSite();
		return false;
	});
	function registerExactDNSite() {
		var ewww_post_data = {
			action: 'ewww_exactdn_register_site',
			ewww_wpnonce: ewww_vars._wpnonce,
			blog_id: exactdn_blog_ids.pop(),
		};
		$.post(ajaxurl, ewww_post_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				console.log( response );
				return false;
			}
			var exactdn_blogs_done = exactdn_blog_count - exactdn_blog_ids.length;
			if ( ewww_response.error ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-register-network').show();
				$('#ewwwio-easy-activation-result').html(ewww_response.error);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				return false;
			} else if ( ! ewww_response.status ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				console.log( response );
				return false;
			} else {
				$('#ewwwio-easy-activation-processing').hide();
				$('.ewwwio-easy-setup-instructions').hide();
				$('p.ewwwio-easy-description').hide();
				$('#ewwwio-easy-activation-progressbar').progressbar('option', 'value', exactdn_blogs_done);
			}
			if ( exactdn_blog_ids.length ) {
				registerExactDNSite();
			} else {
				if (exactdn_cancelled) {
					$('#ewwwio-easy-activation-result').html(ewww_vars.operation_stopped);
				} else {
					$('#ewwwio-easy-activation-result').html(ewww_vars.easyio_register_success);
				}
				$('#ewwwio-easy-activation-result').removeClass('error');
				$('#ewwwio-easy-activation-result').show();
				$('#ewwwio-easy-cancel-network-operation').hide();
				$('#ewwwio-easy-activation-progressbar').hide();
				$('#ewwwio-easy-activate-network').show();
			}
		});
		return false;
	}
	$('#ewwwio-easy-cancel-network-operation').on('click', function() {
		exactdn_blog_ids  = [];
		exactdn_cancelled = true;
		$('#ewwwio-easy-cancel-network-operation').hide();
		return false;
	});
	$('#ewwwio-easy-deregister').on( 'click', function() {
		$('#ewwwio-easy-activate').hide();
		$('#ewwwio-easy-deregister').hide();
		$('#ewwwio-easy-activation-result').hide();
		$('#ewwwio-easy-activation-processing').show();
		if (ewww_vars.easyio_site_id) {
			deregisterExactDNSite();
		} else {
			$('#ewwwio-easy-activation-processing').hide();
			$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
			$('#ewwwio-easy-activation-result').addClass('error');
			$('#ewwwio-easy-activation-result').show();
		}
		return false;
	});
	function deregisterExactDNSite() {
		var ewww_post_data = {
			action: 'ewww_exactdn_deregister_site',
			ewww_wpnonce: ewww_vars._wpnonce,
			site_id: ewww_vars.easyio_site_id,
		};
		$.post(ajaxurl, ewww_post_data, function(response) {
			try {
				var ewww_response = JSON.parse(response);
			} catch (err) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				console.log( 'de-registration response: ' + response );
				return false;
			}
			if ( ewww_response.error ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_response.error);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
			} else if ( ! ewww_response.success ) {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_vars.invalid_response);
				$('#ewwwio-easy-activation-result').addClass('error');
				$('#ewwwio-easy-activation-result').show();
				console.log( 'de-registration response: ' + response );
			} else {
				$('#ewwwio-easy-activation-processing').hide();
				$('#ewwwio-easy-activation-result').html(ewww_response.success);
				$('#ewwwio-easy-activation-result').removeClass('error');
				$('#ewwwio-easy-activation-result').show();
			}
		});
		return false;
	}
	$('#ewww_image_optimizer_webp').on(
		'click',
		function() {
			if ( $(this).prop('checked') && ewww_vars.save_space ) {
				$('#ewwwio-webp-storage-warning').fadeIn();
				return false;
			} else if ($(this).prop('checked')) {
				$('.ewww_image_optimizer_webp_setting_container').fadeIn();
				if ($('#ewww_image_optimizer_webp_for_cdn').prop('checked') || $('#ewww_image_optimizer_picture_webp').prop('checked')) {
					$('.ewww_image_optimizer_webp_rewrite_setting_container').fadeIn();
				}
			} else {
				$('.ewww_image_optimizer_webp_setting_container').fadeOut();
				$('.ewww_image_optimizer_webp_rewrite_setting_container').fadeOut();
			}
		}
	);
	$('#ewwwio-cancel-webp').on(
		'click',
		function() {
			$('#ewwwio-webp-storage-warning').fadeOut();
			return false;
		}
	);
	$('#ewwwio-easyio-webp-info').on(
		'click',
		function() {
			$('#ewwwio-webp-storage-warning').fadeOut();
			$('.ewwwio-premium-setup').show();
		}
	)
	$('#ewwwio-confirm-webp').on(
		'click',
		function() {
			$('#ewwwio-webp-storage-warning').fadeOut();
			$('#ewww_image_optimizer_webp').prop('checked', true);
			$('.ewww_image_optimizer_webp_setting_container').fadeIn();
			if ($('#ewww_image_optimizer_webp_for_cdn').prop('checked') || $('#ewww_image_optimizer_picture_webp').prop('checked')) {
				$('.ewww_image_optimizer_webp_rewrite_setting_container').fadeIn();
			}
			return false;
		}
	);
	$('#ewww-webp-rewrite #ewww-webp-insert').on( 'click', function() {
		var ewww_webp_rewrite_action = 'ewww_webp_rewrite';
		var ewww_webp_rewrite_data = {
			action: ewww_webp_rewrite_action,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_rewrite_data, function(response) {
			$('#ewww-webp-rewrite-result').html(response);
			$('#ewww-webp-rewrite-result').show();
			$('#ewww-webp-rewrite-status').hide();
			$('#webp-rewrite-rules').hide();
			$('#ewww-webp-insert').hide();
			ewww_webp_image = document.getElementById('ewww-webp-image').src;
			document.getElementById('ewww-webp-image').src = removeQueryArg(ewww_webp_image) + '?m=' + new Date().getTime();
		});
		return false;
	});
	$('#ewww-webp-rewrite #ewww-webp-remove').on( 'click', function() {
		var ewww_webp_rewrite_action = 'ewww_webp_unwrite';
		var ewww_webp_rewrite_data = {
			action: ewww_webp_rewrite_action,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_rewrite_data, function(response) {
			$('#ewww-webp-rewrite-result').html(response);
			$('#ewww-webp-rewrite-result').show();
			$('#ewww-webp-rewrite-status').hide();
			$('#ewww-webp-remove').hide();
			ewww_webp_image = document.getElementById('ewww-webp-image').src;
			document.getElementById('ewww-webp-image').src = removeQueryArg(ewww_webp_image) + '?m' + new Date().getTime();
		});
		return false;
	});
	$('#exactdn_site_url').on( 'mouseenter',
		function() {
			$('#exactdn-site-url-copy').fadeIn();
		}
	);
	$('#exactdn_site_url').on( 'mouseleave',
		function() {
			$('#exactdn-site-url-copy').fadeOut();
			$('#exactdn-site-url-copied').fadeOut();
		}
	);
	$('#exactdn_site_url').on( 'click', function() {
		this.select();
		this.setSelectionRange(0,300); // For mobile.
		try {
			var successful = document.execCommand('copy');
			if ( successful ) {
				unselectText();
				$('#exactdn-site-url-copy').hide();
				$('#exactdn-site-url-copied').fadeIn();
			}
		} catch(err) {
			console.log('browser cannot copy');
			console.log(err);
		}
	});
	$('#ewww_image_optimizer_exactdn').on( 'click', function() {
		if($(this).prop('checked')) {
			$('.ewwwio-exactdn-options').show();
		} else {
			$('.ewwwio-exactdn-options').hide();
		}
	});
	$('#ewww_image_optimizer_lazy_load').on( 'click', function() {
		if($(this).prop('checked')) {
			$('#ewww_image_optimizer_ll_exclude_container').fadeIn();
			$('#ewww_image_optimizer_ll_autoscale_container').fadeIn();
			$('#ewww_image_optimizer_ll_all_things_container').fadeIn();
			$('#ewww_image_optimizer_siip_container').fadeIn();
			$('#ewww_image_optimizer_lqip_container').fadeIn();
		} else {
			$('#ewww_image_optimizer_ll_exclude_container').fadeOut();
			$('#ewww_image_optimizer_ll_autoscale_container').fadeOut();
			$('#ewww_image_optimizer_ll_all_things_container').fadeOut();
			$('#ewww_image_optimizer_siip_container').fadeOut();
			$('#ewww_image_optimizer_lqip_container').fadeOut();
		}
	});
	$('#ewww_image_optimizer_webp_for_cdn, #ewww_image_optimizer_picture_webp').on(
		'click',
		function() {
			if ( $(this).prop('checked') && ewww_vars.cloud_media ) {
				var webp_delivery_confirm = confirm(ewww_vars.webp_cloud_warning);
				if (! webp_delivery_confirm) {
					return false;
				}
			}
			if ( ! $('#ewww_image_optimizer_webp_for_cdn').prop('checked') && ! $('#ewww_image_optimizer_picture_webp').prop('checked') ) {
				$('.ewww_image_optimizer_webp_rewrite_setting_container').fadeOut();
			} else {
				$('.ewww_image_optimizer_webp_rewrite_setting_container').fadeIn();
			}
		}
	);
	//$('#ewww-webp-settings').hide();
	//$('#ewww-exactdn-settings').hide();
	$('#ewww-general-settings').show();
	$('li.ewww-general-nav').addClass('ewww-selected');
	if($('#ewww_image_optimizer_debug').length){
		$('#ewww-resize-settings').hide();
	}
	// New tabs.
	$('#ewww-local-settings').hide();
	$('#ewww-advanced-settings').hide();

	//$('#ewww-optimization-settings').hide();
	$('#ewww-conversion-settings').hide();
	$('#ewww-support-settings').hide();
	$('#ewww-contribute-settings').hide();
	$('.ewww-webp-nav').on('click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-webp-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').show();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-general-nav').on('click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-general-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').show();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-exactdn-nav').on('click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-exactdn-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').show();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-optimization-nav').on('click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-optimization-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').show();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-local-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-local-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').show();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-advanced-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-advanced-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').show();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-resize-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-resize-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').show();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-conversion-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-conversion-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').show();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-support-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-support-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').show();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-contribute-nav').on( 'click', function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-contribute-nav').addClass('ewww-selected');
		$('.ewww-tab a').trigger('blur');
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-local-settings').hide();
		$('#ewww-advanced-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').show();
	});
});
