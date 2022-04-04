(function(window, factory) {
	var globalInstall = function(){
		factory(window.lazySizes);
		window.removeEventListener('lazyunveilread', globalInstall, true);
	};

	factory = factory.bind(null, window, window.document);

	if(typeof module == 'object' && module.exports){
		factory(require('lazysizes'));
	} else if(window.lazySizes) {
		globalInstall();
	} else {
		window.addEventListener('lazyunveilread', globalInstall, true);
	}
}(window, function(window, document, lazySizes) {
	/*jshint eqnull:true */
	'use strict';
	var bgLoad, regBgUrlEscape;
	var uniqueUrls = {};

	if(document.addEventListener){
		regBgUrlEscape = /\(|\)|\s|'/;

		addEventListener('lazybeforeunveil', function(e){
			if(e.detail.instance != lazySizes){return;}

			var load, bg, bgWebP, poster;
			if(!e.defaultPrevented) {

				if(e.target.preload == 'none'){
					e.target.preload = 'auto';
				}

				// handle data-bg
				bg = e.target.getAttribute('data-bg');
				if (bg) {
        				if(ewww_webp_supported) {
						console.log('checking for data-bg-webp');
						bgWebP = e.target.getAttribute('data-bg-webp');
						if (bgWebP) {
							console.log('replacing data-bg with data-bg-webp');
							bg = bgWebP;
						}
					}
					var dPR = (window.devicePixelRatio || 1);
					var targetWidth  = Math.round(e.target.offsetWidth * dPR);
					var targetHeight = Math.round(e.target.offsetHeight * dPR);
					if ( 0 === bg.search(/\[/) ) {
					} else if (!shouldAutoScale(e.target)||!shouldAutoScale(e.target.parentNode)){
					} else if (window.lazySizes.hC(e.target,'wp-block-cover')) {
						console.log('found wp-block-cover with data-bg');
						if (window.lazySizes.hC(e.target,'has-parallax')) {
							console.log('also has-parallax with data-bg');
							targetWidth  = Math.round(window.screen.width * dPR);
							targetHeight = Math.round(window.screen.height * dPR);
						} else if (targetHeight<300) {
							targetHeight = 430;
						}
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (window.lazySizes.hC(e.target,'elementor-bg')){
						console.log('found elementor-bg with data-bg');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (window.lazySizes.hC(e.target,'et_parallax_bg')){
						console.log('found et_parallax_bg with data-bg');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (window.lazySizes.hC(e.target,'bg-image-crop')){
						console.log('found bg-image-crop with data-bg');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else {
						console.log('found other data-bg');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg');
					}
					if ( e.target.style.backgroundImage && -1 === e.target.style.backgroundImage.search(/^initial/) ) {
						// Convert JSON for multiple URLs.
						if ( 0 === bg.search(/\[/) ) {
							console.log('multiple URLs to append');
							bg = JSON.parse(bg);
							bg.forEach(
								function(bg_url){
									bg_url = (regBgUrlEscape.test(bg_url) ? JSON.stringify(bg_url) : bg_url );
								}
							);
							bg = 'url("' + bg.join('"), url("') + '"';
							var new_bg = e.target.style.backgroundImage + ', ' + bg;
							console.log('setting .backgroundImage: ' + new_bg );
							e.target.style.backgroundImage = new_bg;
						} else {
							console.log( 'appending bg url: ' + e.target.style.backgroundImage + ', url(' + (regBgUrlEscape.test(bg) ? JSON.stringify(bg) : bg ) + ')' );
							e.target.style.backgroundImage = e.target.style.backgroundImage + ', url("' + (regBgUrlEscape.test(bg) ? JSON.stringify(bg) : bg ) + '")';
						}
					} else {
						// Convert JSON for multiple URLs.
						if ( 0 === bg.search(/\[/) ) {
							console.log('multiple URLs to insert');
							bg = JSON.parse(bg);
							bg.forEach(
								function(bg_url){
									bg_url = (regBgUrlEscape.test(bg_url) ? JSON.stringify(bg_url) : bg_url );
								}
							);
							bg = 'url("' + bg.join('"), url("') + '"';
							console.log('setting .backgroundImage: ' + bg );
							e.target.style.backgroundImage = bg;
						} else {
							console.log('setting .backgroundImage: ' + 'url(' + (regBgUrlEscape.test(bg) ? JSON.stringify(bg) : bg ) + ')');
							e.target.style.backgroundImage = 'url(' + (regBgUrlEscape.test(bg) ? JSON.stringify(bg) : bg ) + ')';
						}
					}
				}
			}
		}, false);
	}
}));
