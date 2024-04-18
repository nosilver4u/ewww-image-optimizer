(function(window, factory) {
	var globalInstall = function(){
		factory(window.lazySizes);
		window.removeEventListener('lazyunveilread', globalInstall, true);
	};

	factory = factory.bind(null, window, window.document);

	if(typeof module == 'object' && module.exports){
		factory(require('lazysizes'));
	} else if (typeof define == 'function' && define.amd) {
		define(['lazysizes'], factory);
	} else if(window.lazySizes) {
		globalInstall();
	} else {
		window.addEventListener('lazyunveilread', globalInstall, true);
	}
}(window, function(window, document, lazySizes) {
	/*jshint eqnull:true */
	'use strict';
	var regBgUrlEscape;
	var autosizedElems = [];

	if(document.addEventListener){
		regBgUrlEscape = /\(|\)|\s|'/;

		addEventListener('lazybeforeunveil', function(e){
			if(e.detail.instance != lazySizes){return;}

			var bg, bgWebP;
			if(!e.defaultPrevented) {

				if(e.target.preload == 'none'){
					e.target.preload = 'auto';
				}

				// handle data-back (so as not to conflict with the stock data-bg)
				bg = e.target.getAttribute('data-back');
				if (bg) {
        				if(ewww_webp_supported) {
						console.log('checking for data-back-webp');
						bgWebP = e.target.getAttribute('data-back-webp');
						if (bgWebP) {
							console.log('replacing data-back with data-back-webp');
							bg = bgWebP;
						}
					}
					var dPR = (window.devicePixelRatio || 1);
					var targetWidth  = Math.round(e.target.offsetWidth * dPR);
					var targetHeight = Math.round(e.target.offsetHeight * dPR);
					if ( 0 === bg.search(/\[/) ) {
					} else if (!shouldAutoScale(e.target)){
					} else if (lazySizes.hC(e.target,'wp-block-cover')) {
						console.log('found wp-block-cover with data-back');
						if (lazySizes.hC(e.target,'has-parallax')) {
							console.log('also has-parallax with data-back');
							targetWidth  = Math.round(window.screen.width * dPR);
							targetHeight = Math.round(window.screen.height * dPR);
						} else if (targetHeight<300) {
							targetHeight = 430;
						}
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (lazySizes.hC(e.target,'cover-image')){
						console.log('found .cover-image with data-back');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (lazySizes.hC(e.target,'elementor-bg')){
						console.log('found elementor-bg with data-back');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (lazySizes.hC(e.target,'et_parallax_bg')){
						console.log('found et_parallax_bg with data-back');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else if (lazySizes.hC(e.target,'bg-image-crop')){
						console.log('found bg-image-crop with data-back');
						bg = constrainSrc(bg,targetWidth,targetHeight,'bg-cover');
					} else {
						console.log('found other data-back');
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

	var shouldAutoScale = function(target){
		if (eio_lazy_vars.skip_autoscale == 1) {
			console.log('autoscale disabled globally');
			return false;
		}
		var currentNode = target;
		for (var i = 0; i <= 7; i++) {
			if (currentNode.hasAttributes()) {
				var attrs = currentNode.attributes
				var regNoScale = /skip-autoscale/;
				for (var i = attrs.length - 1; i >= 0; i--) {
					if (regNoScale.test(attrs[i].name)) {
						console.log('autoscale disabled by attr');
						return false;
					}
					if (regNoScale.test(attrs[i].value)) {
						console.log('autoscale disabled by attr value');
						return false;
					}
				}
			}
			if (currentNode.parentNode && currentNode.parentNode.nodeType === 1 && currentNode.parentNode.hasAttributes) {
				currentNode = currentNode.parentNode;
			} else {
				break;
			}
		}
		return true;
	};

	var constrainSrc = function(url,objectWidth,objectHeight,objectType,upScale = false){
		if (url===null){
			return url;
		}
		console.log('constrain ' + url + ' to ' + objectWidth + 'x' + objectHeight + ' with type ' + objectType);
		var regW      = /w=(\d+)/;
		var regFit    = /fit=(\d+),(\d+)/;
		var regResize = /resize=(\d+),(\d+)/;
		var regSVG    = /\.svg(\?.+)?$/;
		var decUrl = decodeURIComponent(url);
		if (regSVG.exec(decUrl)){
			return url;
		}
		console.log('domain to test: ' + eio_lazy_vars.exactdn_domain);
		if (url.search('\\?') > 0 && url.search(eio_lazy_vars.exactdn_domain) > 0){
			console.log('domain matches URL with a ?');
			var resultResize = regResize.exec(decUrl);
			if(resultResize && (objectWidth < resultResize[1] || upScale)){
				if('img-w'===objectType){
					console.log('resize param found, replacing in ' + objectType);
					return decUrl.replace(regResize, 'w=' + objectWidth );
				}
				if('img-h'===objectType){
					console.log('resize param found, replacing in ' + objectType);
					return decUrl.replace(regResize, 'h=' + objectHeight );
				}
				console.log('resize param found, replacing');
				return decUrl.replace(regResize, 'resize=' + objectWidth + ',' + objectHeight);
			}
			var resultW = regW.exec(url);
			if(resultW && (objectWidth <= resultW[1] || upScale)){
				if('img-h'===objectType){
					console.log('w param found, replacing in ' + objectType);
					return decUrl.replace(regW, 'h=' + objectHeight );
				}
				if('bg-cover'===objectType || 'img-crop'===objectType){
					var diff = Math.abs(resultW[1] - objectWidth);
					if ( diff > 20 || objectHeight < 1080 ) {
						console.log('w param found, replacing in ' + objectType);
						return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight );
					}
					console.log('w param found, but only ' + diff + ' pixels off, ignoring');
					return url;
				}
				console.log('w param found, replacing');
				return url.replace(regW, 'w=' + objectWidth);
			}
			var resultFit = regFit.exec(decUrl);
			if(resultFit && (objectWidth < resultFit[1] || upScale)){
				if('bg-cover'===objectType || 'img-crop'===objectType){
					var wDiff = Math.abs(resultFit[1] - objectWidth);
					var hDiff = Math.abs(resultFit[2] - objectHeight);
					if ( wDiff > 20 || hDiff > 20 ) {
						console.log('fit param found, replacing in ' + objectType);
						return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight );
					}
					console.log('fit param found, but only w' + wDiff + '/h' + hDiff + ' pixels off, ignoring');
					return url;
				}
				if('img-w'===objectType){
					console.log('fit param found, replacing in ' + objectType);
					return decUrl.replace(regFit, 'w=' + objectWidth );
				}
				if('img-h'===objectType){
					console.log('fit param found, replacing in ' + objectType);
					return decUrl.replace(regFit, 'h=' + objectHeight );
				}
				console.log('fit param found, replacing');
				return decUrl.replace(regFit, 'fit=' + objectWidth + ',' + objectHeight);
			}
	        if(!resultW && !resultFit && !resultResize){
				console.log('no param found, appending');
				if('img'===objectType){
					console.log('for ' + objectType);
					return url + '&fit=' + objectWidth + ',' + objectHeight;
				}
				if('bg-cover'===objectType || 'img-crop'===objectType){
					console.log('for ' + objectType);
					return url + '&resize=' + objectWidth + ',' + objectHeight;
				}
				if('img-h'===objectType || objectHeight>objectWidth){
					console.log('img-h or fallback height>width, using h param');
					return url + '&h=' + objectHeight;
				}
				console.log('fallback using w param');
				return url + '&w=' + objectWidth;
			}
		}
		if (url.search('\\?') == -1 && url.search(eio_lazy_vars.exactdn_domain) > 0){
			console.log('domain matches URL without a ?, appending query string');
			if('img'===objectType){
				console.log('for ' + objectType);
				return url + '?fit=' + objectWidth + ',' + objectHeight;
			}
			if('bg-cover'===objectType || 'img-crop'===objectType){
				console.log('for ' + objectType);
				return url + '?resize=' + objectWidth + ',' + objectHeight;
			}
			if('img-h'===objectType || objectHeight>objectWidth){
				console.log('img-h or fallback height>width, using h param');
				return url + '?h=' + objectHeight;
			}
			console.log('fallback using w param');
			return url + '?w=' + objectWidth;
		}
		console.log('boo, just using same url');
		return url;
	};

	var getImgType = function(elem){
		if ( lazySizes.hC(elem,'et_pb_jt_filterable_grid_item_image') || lazySizes.hC(elem,'ss-foreground-image') || lazySizes.hC(elem,'img-crop') ) {
			console.log('img that needs a hard crop');
			return 'img-crop';
		} else if (
			lazySizes.hC(elem,'object-cover') &&
			( lazySizes.hC(elem,'object-top') || lazySizes.hC(elem,'object-bottom') )
		) {
			console.log('cover img that needs a width scale');
			return 'img-w';
		} else if (
			lazySizes.hC(elem,'object-cover') &&
			( lazySizes.hC(elem,'object-left') || lazySizes.hC(elem,'object-right') )
		) {
			console.log('cover img that needs a height scale');
			return 'img-h';
		} else if ( lazySizes.hC(elem,'ct-image') && lazySizes.hC(elem,'object-cover') ) {
			console.log('Oxygen cover img that needs a hard crop');
			return 'img-crop';
		} else if ( ! elem.getAttribute('data-srcset') && ! elem.srcset && elem.offsetHeight > elem.offsetWidth && getAspectRatio(elem) > 1 ) {
			console.log('non-srcset img with portrait display, landscape in real life');
			return 'img-crop';
		}
		console.log('plain old img, constraining');
		return 'img';
	};

	var getDimensionsFromURL = function(url){
		var regDims = /-(\d+)x(\d+)\./;
		var resultDims = regDims.exec(url);
		if (resultDims && resultDims[1] > 1 && resultDims[2] > 1) {
			return {w:resultDims[1],h:resultDims[2]};
		}
		return {w:0,h:0};
	};

	var getRealDimensionsFromImg = function(img){
		var realWidth = img.getAttribute('data-eio-rwidth');
		var realHeight = img.getAttribute('data-eio-rheight');
		if (realWidth > 1 && realHeight > 1) {
			return {w:realWidth,h:realHeight};
		}
		return {w:0,h:0};
	};

	var getSrcsetDims = function(img) {
		var srcSet;
		if (img.srcset){
			srcSet = img.srcset.split(',');
		} else {
			var srcSetAttr = img.getAttribute('data-srcset');
			if (srcSetAttr){
				srcSet = srcSetAttr.split(','); 
			}
		}
		if (srcSet){
			var i = 0;
			var len = srcSet.length;
			if (len){
				for (; i < len; i++){
					var src = srcSet[i].trim().split(' ');
					if (src[0].length) {
						var nextDims = getDimensionsFromURL(src[0]);
						if (nextDims.w && nextDims.h){
							var srcSetDims = nextDims;
						}
					}
				}
				if (srcSetDims.w && srcSetDims.h){
					return srcSetDims;
				}
			}
		}
		return {w:0,h:0};
	}

	var getAspectRatio = function(img){
		var width = img.getAttribute('width');
		var height = img.getAttribute('height');
		if (width > 1 && height > 1){
			console.log('found dims ' + width + 'x' + height + ', returning ' + width/height);
			return width / height;
		}
		var src = false;
		if (img.src && img.src.search('http') > -1) {
			src = img.src;
		}
		if (!src) {
			src = img.getAttribute('data-src');
		}
		if (src){
			var urlDims = getDimensionsFromURL(src);
			if (urlDims.w && urlDims.h) {
				console.log('found dims from URL: ' + urlDims.w + 'x' + urlDims.h);
				return urlDims.w / urlDims.h;
			}
		}
		var realDims = getRealDimensionsFromImg(img);
		if (realDims.w && realDims.h){
			console.log('found dims from eio-attrs: ' + realDims.w + 'x' + realDims.h);
			return realDims.w / realDims.h;
		}
		var srcSetDims = getSrcsetDims(img);
		if (srcSetDims.w && srcSetDims.h){
			console.log('largest found dims from srcset: ' + srcSetDims.w + 'x' + srcSetDims.h);
			return srcSetDims.w / srcSetDims.h;
		}
		return 0;
	}

	var updateImgElem = function(target,upScale=false){
		var dPR = (window.devicePixelRatio || 1);
		var targetWidth = Math.round(target.offsetWidth * dPR);
		var targetHeight = Math.round(target.offsetHeight * dPR);

		var src = target.getAttribute('data-src');
		var webpsrc = target.getAttribute('data-src-webp');
		if(ewww_webp_supported && webpsrc && -1 == src.search('webp=1') && !upScale){
			console.log('using data-src-webp');
			src = webpsrc;
		}
		if (!shouldAutoScale(target)){
			return;
		}
		var imgType = getImgType(target);
		var newSrc  = constrainSrc(src,targetWidth,targetHeight,imgType,upScale);
		if (newSrc && src != newSrc){
			console.log('new src: ' + newSrc);
			if (upScale){
				target.setAttribute('src', newSrc);
			}
			target.setAttribute('data-src', newSrc);
		}
	};

	document.addEventListener('lazybeforesizes', function(e){
		var src = e.target.getAttribute('data-src');
		console.log('auto-sizing ' + src + ' to: ' + e.detail.width);
		var imgAspect = getAspectRatio(e.target);
		if (e.target.clientHeight > 1 && imgAspect) {
			var minimum_width = Math.ceil(imgAspect * e.target.clientHeight);
			console.log('minimum_width = ' + minimum_width);
			if (e.detail.width+2 < minimum_width) {
				e.detail.width = minimum_width;
			}
		}
		if (e.target._lazysizesWidth === undefined) {
			return;
		}
		console.log('previous width was ' + e.target._lazysizesWidth);
		if (e.detail.width < e.target._lazysizesWidth) {
			console.log('no way! ' + e.detail.width + ' is smaller than ' + e.target._lazysizesWidth);
			e.detail.width = e.target._lazysizesWidth;
		}
	});

	document.addEventListener('lazybeforeunveil', function(e){
		var target = e.target;
		console.log('loading an image');
		console.log(target);
		var srcset  = target.getAttribute('data-srcset');
	    if (target.naturalWidth && ! srcset) {
			console.log('natural width of ' + target.getAttribute('src') + ' is ' + target.naturalWidth);
			console.log('we have an image with no srcset');
			if ((target.naturalWidth > 1) && (target.naturalHeight > 1)) {
	        	// For each image with a natural width which isn't
	        	// a 1x1 image, check its size.
				var dPR = (window.devicePixelRatio || 1);
				var physicalWidth = target.naturalWidth;
				var physicalHeight = target.naturalHeight;
				var realDims = getRealDimensionsFromImg(target);
				if (realDims.w && realDims.w > physicalWidth) {
					console.log( 'using ' + realDims.w + 'w instead of ' + physicalWidth + 'w and ' + realDims.h + 'h instead of ' + physicalHeight + 'h from data-eio-r*')
					physicalWidth = realDims.w;
					physicalHeight = realDims.h;
				}
	            var wrongWidth = (target.clientWidth && (target.clientWidth * 1.25 * dPR < physicalWidth));
	            var wrongHeight = (target.clientHeight && (target.clientHeight * 1.25 * dPR < physicalHeight));
				console.log('displayed at ' + Math.round(target.clientWidth * dPR) + 'w x ' + Math.round(target.clientHeight * dPR) + 'h, natural/physical is ' +
				physicalWidth + 'w x ' + physicalHeight + 'h!');
				console.log('the data-src: ' + target.getAttribute('data-src') );
	            if (wrongWidth || wrongHeight) {
					updateImgElem(target);
				}
			}
	    }
		if(ewww_webp_supported) {
			console.log('webp supported');
			//console.log(srcset);
			if (srcset) {
				console.log('srcset available');
				var webpsrcset = target.getAttribute('data-srcset-webp');
				if(webpsrcset){
					console.log('replacing data-srcset with data-srcset-webp');
					target.setAttribute('data-srcset', webpsrcset);
				}
			}
			var webpsrc = target.getAttribute('data-src-webp');
			if(!webpsrc){
				console.log('no data-src-webp attr');
				return;
			}
			console.log('replacing data-src with data-src-webp');
			target.setAttribute('data-src', webpsrc);
		}
	});

	// Based on http://modernjavascript.blogspot.de/2013/08/building-better-debounce.html
	var debounce = function(func) {
		var timeout, timestamp;
		var wait = 99;
		var run = function(){
			timeout = null;
			func();
		};
		var later = function() {
			var last = Date.now() - timestamp;

			if (last < wait) {
				setTimeout(later, wait - last);
			} else {
				(window.requestIdleCallback || run)(run);
			}
		};

		return function() {
			timestamp = Date.now();

			if (!timeout) {
				timeout = setTimeout(later, wait);
			}
		};
	};

	var recheckLazyElements = function(event = false) {
		console.log('rechecking elements:');
		if (event.type) {
			console.log(event.type);
			if ('load'===event.type) {
				lazySizes.autoSizer.checkElems();
			}
		}
		var dPR = (window.devicePixelRatio || 1);
		var autosizedElems = document.getElementsByClassName(lazySizes.cfg.loadedClass);
		var i;
		var len = autosizedElems.length;
		if(len){
			i = 0;

			for(; i < len; i++){
				var autosizedElem = autosizedElems[i];
				if (autosizedElem.src && ! autosizedElem.srcset && autosizedElem.naturalWidth > 1 && autosizedElem.naturalHeight > 1 && autosizedElem.clientWidth > 1 && autosizedElem.clientHeight > 1){
					console.log(autosizedElem);
					console.log('natural width of ' + autosizedElem.src + ' is ' + autosizedElem.naturalWidth);
		        	// For each image with a natural width which isn't
		        	// a 1x1 image, check its size.
					var physicalWidth  = autosizedElem.naturalWidth;
					var physicalHeight = autosizedElem.naturalHeight;
					var maxWidth  = window.innerWidth;
					var maxHeight = window.innerHeight;
					var realDims  = getRealDimensionsFromImg(autosizedElem);
					var urlDims   = getDimensionsFromURL(autosizedElem.src);
	
					if (realDims.w) {
						maxWidth = realDims.w;
					} else if (urlDims.w) {
						maxWidth = urlDims.w;
					}
					if (realDims.h) {
						maxHeight = realDims.h;
					} else if (urlDims.h) {
						maxHeight = urlDims.h;
					}
					console.log( 'max image size is ' + maxWidth + 'w, ' + maxHeight + 'h');

					// For upscaling, the goal is to get to 1x dPR, we won't waste bandwidth on retina/2x images.
					var desiredWidth  = autosizedElem.clientWidth;
					var desiredHeight = autosizedElem.clientHeight;
		            var wrongWidth  = (desiredWidth > physicalWidth * 1.1 && maxWidth >= desiredWidth);
		            var wrongHeight = (desiredHeight > physicalHeight * 1.1 && maxHeight >= desiredHeight);
					console.log('displayed at ' + Math.round(desiredWidth) + 'w x ' + Math.round(desiredHeight) + 'h, natural/physical is ' +
					physicalWidth + 'w x ' + physicalHeight + 'h');
		            if (wrongWidth || wrongHeight) {
		            	console.log('requesting upsize');
						updateImgElem(autosizedElem,true);
					}
				}
			}
		}
	};

	var debouncedRecheckElements = debounce(recheckLazyElements);
	
	addEventListener('load', recheckLazyElements);
	addEventListener('resize', debouncedRecheckElements);
	setTimeout(recheckLazyElements, 20000);
}));
