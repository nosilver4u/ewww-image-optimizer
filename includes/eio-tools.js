jQuery(document).ready(function($) {
	var ewww_total_pages = 0;
	var ewww_clean_meta_total = 0;
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
			ewwwUpdateNonce(ewww_response);
			ewwwRestoreOriginals();
		})
		.fail(function() {
			$('#ewww-restore-originals-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			setTimeout(function() {
				ewwwRestoreOriginals();
			}, 15000);
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
			//$('#ewww-clean-originals-progress').html('<p> 0/' + ewww_total_originals + '</p>');
			$('#ewww-clean-originals-progressbar').show();
			$('#ewww-clean-originals-action').show();
			$('#ewww-clean-originals-progress').show();
			$('#ewww-clean-originals-messages').show();
			ewwwDeleteOriginalByID();
		});
		return false;
	});
	function ewwwDeleteOriginalByID(){
		var attachment_id = ewww_original_attachments.pop();
		var completed = ewww_total_originals - ewww_original_attachments.length;
		var ewww_originals_data = {
			action: 'bulk_aux_images_delete_original',
			ewww_wpnonce: ewww_vars._wpnonce,
			attachment_id: attachment_id,
			completed: completed,
			total: ewww_total_originals,
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
			if (ewww_response.error) {
				$('#ewww-clean-originals-progressbar').hide();
				$('#ewww-clean-originals-progress').html('<span style="color: red"><b>' + ewww_response.error + '</b></span>');
				return false;
			}
			if (ewww_response.deleted) {
				$('#ewww-clean-originals-messages p').append(ewww_response.deleted + '<br>');
			}
			if (!ewww_original_attachments.length) {
				var ewww_originals_data = {
					action: 'bulk_aux_images_delete_original',
					ewww_wpnonce: ewww_vars._wpnonce,
					delete_originals_done: 1,
				};
				$.post(ajaxurl, ewww_originals_data);
				$('#ewww-clean-originals-progress').html(ewww_vars.finished);
				return false;
			}
			completed = ewww_total_originals - ewww_original_attachments.length;
			$('#ewww-clean-originals-progressbar').progressbar("option", "value", completed);
			//$('#ewww-clean-originals-progress').html('<p>' + completed + '/' + ewww_total_originals + '</p>');
			$('#ewww-clean-originals-progress').html('<p>' + ewww_response.progress + '</p>');
			ewwwUpdateNonce(ewww_response);
			ewwwDeleteOriginalByID();
		})
		.fail(function() {
			$('#ewww-clean-originals-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			ewww_original_attachments.push(attachment_id);
			setTimeout(function() {
				ewwwDeleteOriginalByID();
			}, 15000);
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
			$('#ewww-clean-converted-messages').show();

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
			if (ewww_response.messages) {
				$('#ewww-clean-converted-messages').append(ewww_response.messages);
			}
			ewwwUpdateNonce(ewww_response);
			ewwwCleanConvertedOriginals(converted_offset);
		})
		.fail(function() {
			$('#ewww-clean-converted-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			setTimeout(function() {
				ewwwCleanConvertedOriginals(converted_offset);
			}, 15000);
		});
	}
	var ewww_total_webp          = 0;
	var ewww_webp_cleaned        = 0;
	var ewww_webp_images_removed = 0;
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
			$('#ewww-clean-webp-removed').show();
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
			if (ewww_response.removed) {
				ewww_webp_images_removed += ewww_response.removed;
				$('#ewww-clean-webp-removed-total').html(ewww_webp_images_removed);
			}
			$('#ewww-clean-webp-progressbar').progressbar("option", "value", ewww_webp_cleaned);
			$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage1 + ' ' + ewww_webp_cleaned + '/' + ewww_total_webp + '</p>');
			ewwwUpdateNonce(ewww_response);
			ewwwRemoveWebPByID();
		})
		.fail(function() {
			$('#ewww-clean-webp-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			setTimeout(function() {
				ewwwRemoveWebPByID();
			}, 15000);
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
			ewww_webp_cleaned        += ewww_response.completed;
			if (ewww_response.removed) {
				ewww_webp_images_removed += ewww_response.removed;
				$('#ewww-clean-webp-removed-total').html(ewww_webp_images_removed);
			}
			$('#ewww-clean-webp-progressbar').progressbar("option", "value", ewww_webp_cleaned);
			$('#ewww-clean-webp-progress').html('<p>' + ewww_vars.stage2 + ' ' + ewww_webp_cleaned + '/' + ewww_total_webp + '</p>');
			ewwwUpdateNonce(ewww_response);
			ewwwRemoveWebP();
		})
		.fail(function() {
			$('#ewww-clean-webp-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			setTimeout(function() {
				ewwwRemoveWebP();
			}, 15000);
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
				console.log(err);
				console.log(response);
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
			ewwwUpdateNonce(ewww_response);
			ewwwCleanup(total_pages);
		})
		.fail(function() {
			$('#ewww-clean-table-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			total_pages++;
			setTimeout(function() {
				ewwwCleanup(total_pages);
			}, 15000);
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
			ewwwUpdateNonce(ewww_response);
			ewwwCleanupMeta();
		})
		.fail(function() {
			$('#ewww-clean-meta-progress').append('<p><strong>' + ewww_vars.network_error + '</strong></p>');
			setTimeout(function() {
				ewwwCleanupMeta();
			}, 15000);
		});
	}
	function ewwwUpdateNonce(ewww_response) {
		if (ewww_response.new_nonce.length > 0) {
			ewww_vars._wpnonce = ewww_response.new_nonce;
		}
	}
});
