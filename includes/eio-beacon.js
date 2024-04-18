jQuery(document).ready(function($) {
	$('#ewww-copy-debug').on( 'click', function() {
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
	if (typeof(Beacon) !== 'undefined' ) {
		Beacon( 'on', 'ready', function() {
			$('.ewww-overrides-nav').on( 'click', function() {
				Beacon('article', '59710ce4042863033a1b45a6', { type: 'modal' });
				return false;
			});
			$('.ewww-contact-root').on( 'click', function() {
				Beacon('navigate', '/ask/')
				Beacon('open');
				return false;
			});
			$('.ewww-docs-root').on( 'click', function() {
				Beacon('navigate', '/answers/')
				Beacon('open');
				return false;
			});
			$('.ewww-help-beacon-multi').on( 'click', function() {
				var hsids = $(this).attr('data-beacon-articles');
				hsids = hsids.split(',');
				Beacon('suggest', hsids);
				Beacon('navigate', '/answers/');
				Beacon('open');
				return false;
			});
			$('.ewww-help-beacon-single').on( 'click', function() {
				var hsid = $(this).attr('data-beacon-article');
				Beacon('article', hsid, { type: 'modal' });
				return false;
			});
		});
	}
});
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
