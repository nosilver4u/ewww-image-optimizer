jQuery(document).ready(function($) {
	$('#ewww-copy-debug').click(function() {
		selectText('ewww-debug-info');
		try {
			var successful = document.execCommand('copy');
			if ( successful ) {
				unselectText();
			}
		} catch(err) {
			console.log('browser cannot copy');
			console.log(err);
		}
	});
	function HSregister() {
		if (typeof(Beacon) !== 'undefined' ) {
			$('.ewww-overrides-nav').click(function() {
				event.preventDefault();
				Beacon('article', '59710ce4042863033a1b45a6', { type: 'modal' });
			});
			$('.ewww-docs-root').click(function() {
				event.preventDefault();
				Beacon('navigate', '/answers/')
				Beacon('open');
			});
			$('.ewww-help-beacon-multi').click(function() {
				var hsids = $(this).attr('data-beacon-articles');
				hsids = hsids.split(',');
				event.preventDefault();
				Beacon('suggest', hsids);
				Beacon('navigate', '/answers/');
				Beacon('open');
			});
			$('.ewww-help-beacon-single').click(function() {
				var hsid = $(this).attr('data-beacon-article');
				event.preventDefault();
				Beacon('article', hsid, { type: 'modal' });
			});
		}
	}
	HSregister();
	function selectText(containerid) {
		var debug_node = document.getElementById(containerid);
		if (document.selection) {
			var range = document.body.createTextRange();
			range.moveToElementText(debug_node);
			range.select();
		} else if (window.getSelection) {
			window.getSelection().selectAllChildren(debug_node);
		}
	}
	function unselectText() {
		var sel;
		if ( (sel = document.selection) && sel.empty) {
			sel.empty();
		} else if (window.getSelection) {
			window.getSelection().removeAllRanges();
		}
	}
});
