function shouldAutoScale(target){
	if (eio_lazy_vars.skip_autoscale == 1) {
		console.log('autoscale disabled globally');
		return false;
	}
	if (target.hasAttributes()) {
		var attrs = target.attributes
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
	return true;
}
function constrainSrc(url,objectWidth,objectHeight,objectType){
	if (url===null){
		return url;
	}
	console.log('constrain ' + url + ' to ' + objectWidth + 'x' + objectHeight + ' with type ' + objectType);
	var regW      = /w=(\d+)/;
	var regFit    = /fit=(\d+),(\d+)/;
	var regResize = /resize=(\d+),(\d+)/;
	var decUrl = decodeURIComponent(url);
	if (typeof eio_lazy_vars === 'undefined'){
		console.log('setting failsafe lazy vars');
		eio_lazy_vars = {"exactdn_domain":".exactdn.com"};
	}
	console.log('domain to test: ' + eio_lazy_vars.exactdn_domain);
	if (url.search('\\?') > 0 && url.search(eio_lazy_vars.exactdn_domain) > 0){
		console.log('domain matches URL with a ?');
		var resultResize = regResize.exec(decUrl);
		if(resultResize && objectWidth < resultResize[1]){
			console.log('resize param found, replacing');
			return decUrl.replace(regResize, 'resize=' + objectWidth + ',' + objectHeight);
		}
		var resultW = regW.exec(url);
		if(resultW && objectWidth <= resultW[1]){
			if('bg-cover'===objectType || 'img-crop'===objectType){
				var diff = resultW[1] - objectWidth;
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
		if(resultFit && objectWidth < resultFit[1]){
			if('bg-cover'===objectType || 'img-crop'===objectType){
				var wDiff = resultFit[1] - objectWidth;
				var hDiff = resultFit[2] - objectHeight;
				if ( wDiff > 20 || hDiff > 20 ) {
					console.log('fit param found, replacing in ' + objectType);
					return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight );
				}
				console.log('fit param found, but only ' + wDiff + '/' + hDiff + ' pixels off, ignoring');
				return url;
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
			if(objectHeight>objectWidth){
				console.log('fallback height>width, using h param');
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
		if(objectHeight>objectWidth){
			console.log('fallback height>width, using h param');
			return url + '?h=' + objectHeight;
		}
		console.log('fallback using w param');
		return url + '?w=' + objectWidth;
	}
	console.log('boo, just using same url');
	return url;
}
document.addEventListener('lazybeforesizes', function(e){
	var src = e.target.getAttribute('data-src');
	console.log('auto-sizing ' + src + ' to: ' + e.detail.width);
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
	var wrongSize = false;
	var srcset  = target.getAttribute('data-srcset');
	console.log('natural width of ' + target.getAttribute('src') + ' is ' + target.naturalWidth);
        if (target.naturalWidth && ! srcset) {
		console.log('we have an image with no srcset');
        	if ((target.naturalWidth > 1) && (target.naturalHeight > 1)) {
                	// For each image with a natural width which isn't
                	// a 1x1 image, check its size.
			var dPR = (window.devicePixelRatio || 1);
			var physicalWidth = target.naturalWidth;
			var physicalHeight = target.naturalHeight;
			var realWidth = target.getAttribute('data-eio-rwidth');
			var realHeight = target.getAttribute('data-eio-rheight');
			if (realWidth && realWidth > physicalWidth) {
				console.log( 'using ' + realWidth + 'w instead of ' + physicalWidth + 'w and ' + realHeight + 'h instead of ' + physicalHeight + 'h from data-eio-r*')
				physicalWidth = realWidth;
				physicalHeight = realHeight;
			}
                	var wrongWidth = (target.clientWidth && (target.clientWidth * 1.25 < physicalWidth));
                	var wrongHeight = (target.clientHeight && (target.clientHeight * 1.25 < physicalHeight));
			console.log('displayed at ' + Math.round(target.clientWidth * dPR) + 'w x ' + Math.round(target.clientHeight * dPR) + 'h, natural/physical is ' +
				physicalWidth + 'w x ' + physicalHeight + 'h!');
			console.log('the data-src: ' + target.getAttribute('data-src') );
                	if (wrongWidth || wrongHeight) {
				var targetWidth = Math.round(target.offsetWidth * dPR);
				var targetHeight = Math.round(target.offsetHeight * dPR);

				var src = target.getAttribute('data-src');
        			var webpsrc = target.getAttribute('data-src-webp');
        			if(ewww_webp_supported && webpsrc && -1 == src.search('webp=1')){
					console.log('using data-src-webp');
					src = webpsrc;
				}
				if (!shouldAutoScale(target)||!shouldAutoScale(target.parentNode)){
					var newSrc = false;
				} else if ( window.lazySizes.hC(target,'et_pb_jt_filterable_grid_item_image') || window.lazySizes.hC(target,'ss-foreground-image') || window.lazySizes.hC(target,'img-crop') ) {
					console.log('img that needs a hard crop');
					var newSrc = constrainSrc(src,targetWidth,targetHeight,'img-crop');
				} else if ( window.lazySizes.hC(target,'ct-image') && window.lazySizes.hC(target,'object-cover') ) {
					console.log('Oxygen cover img that needs a hard crop');
					var newSrc = constrainSrc(src,targetWidth,targetHeight,'img-crop');
				} else {
					console.log('plain old img, constraining');
					var newSrc = constrainSrc(src,targetWidth,targetHeight,'img');
				}
				if (newSrc && src != newSrc){
					target.setAttribute('data-src', newSrc);
				}
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
/* lazySizes.init(); */
