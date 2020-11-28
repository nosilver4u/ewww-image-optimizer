jQuery(document).ready(function($) {
	function removeQueryArg(url) {
		return url.split('?')[0];
	}
	$('#ewww-webp-rewrite #ewww-webp-insert').click(function() {
		var ewww_webp_rewrite_action = 'ewww_webp_rewrite';
		var ewww_webp_rewrite_data = {
			action: ewww_webp_rewrite_action,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_rewrite_data, function(response) {
			$('#ewww-webp-rewrite-status').html('<b>' + response + '</b>');
			ewww_webp_image = document.getElementById("webp-image").src;
			document.getElementById("webp-image").src = removeQueryArg(ewww_webp_image) + '?m=' + new Date().getTime();
		});
		return false;
	});
	$('#ewww-webp-rewrite #ewww-webp-remove').click(function() {
		var ewww_webp_rewrite_action = 'ewww_webp_unwrite';
		var ewww_webp_rewrite_data = {
			action: ewww_webp_rewrite_action,
			ewww_wpnonce: ewww_vars._wpnonce,
		};
		$.post(ajaxurl, ewww_webp_rewrite_data, function(response) {
			$('#ewww-webp-rewrite-status').html('<b>' + response + '</b>');
			ewww_webp_image = document.getElementById("webp-image").src;
			document.getElementById("webp-image").src = removeQueryArg(ewww_webp_image) + '?m' + new Date().getTime();
		});
		return false;
	});
	$('#ewww-webp-settings').hide();
	if (exactdn_enabled) {
		$('#ewww-exactdn-settings').show();
		$('#ewww-general-settings').hide();
		$('li.ewww-exactdn-nav').addClass('ewww-selected');
	} else {
		$('#ewww-exactdn-settings').hide();
		$('#ewww-general-settings').show();
		$('li.ewww-general-nav').addClass('ewww-selected');
	}
	if($('#ewww_image_optimizer_debug').length){
		$('#ewww-resize-settings').hide();
		console.log($('#ewww_image_optimizer_debug').length);
	}
	$('#ewww-optimization-settings').hide();
	$('#ewww-conversion-settings').hide();
	$('#ewww-support-settings').hide();
	$('#ewww-contribute-settings').hide();
	$('.ewww-webp-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-webp-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').show();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-general-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-general-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').show();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-exactdn-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-exactdn-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').show();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-optimization-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-optimization-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').show();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-resize-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-resize-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').show();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-conversion-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-conversion-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').show();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-support-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-support-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').show();
		$('#ewww-contribute-settings').hide();
	});
	$('.ewww-contribute-nav').click(function() {
		$('.ewww-tab-nav li').removeClass('ewww-selected');
		$('li.ewww-contribute-nav').addClass('ewww-selected');
		$('.ewww-tab a').blur();
		$('#ewww-webp-settings').hide();
		$('#ewww-general-settings').hide();
		$('#ewww-exactdn-settings').hide();
		$('#ewww-optimization-settings').hide();
		$('#ewww-resize-settings').hide();
		$('#ewww-conversion-settings').hide();
		$('#ewww-support-settings').hide();
		$('#ewww-contribute-settings').show();
	});
	$('.ewww-guage').tooltip({
		items: '.ewww-guage',
		content: function() {
			return $(this).next('.ewww-recommend').html();
		},
		open: function( event, ui ) {
			HSregister();
		},
		close: function(event, ui) {
			ui.tooltip.hover(function() {
				$(this).stop(true).fadeTo(400, 1);
			},
			function() {
				$(this).fadeOut('400', function() {
					$(this).remove();
				});
			});
		},
	});
});
