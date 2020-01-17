lazysizesWebP('alpha', lazySizes.init);
function shouldAutoScale(target){
	if (target.hasAttributes()) {
		var attrs = target.attributes
		var regNoScale = /skip-autoscale/;
		for (var i = attrs.length - 1; i >= 0; i--) {
			if (regNoScale.test(attrs[i].name)) {
				return false;
			}
			if (regNoScale.test(attrs[i].value)) {
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
	var regW      = /w=(\d+)/;
	var regFit    = /fit=(\d+),(\d+)/;
	var regResize = /resize=(\d+),(\d+)/;
	var decUrl = decodeURIComponent(url);
	if (typeof eio_lazy_vars === 'undefined'){
		var eio_lazy_vars = {"exactdn_domain":".exactdn.com"};
	}
	if (url.search('\\?') > 0 && url.search(eio_lazy_vars.exactdn_domain) > 0){
		var resultResize = regResize.exec(decUrl);
		if(resultResize && objectWidth < resultResize[1]){
			return decUrl.replace(regResize, 'resize=' + objectWidth + ',' + objectHeight);
		}
		var resultW = regW.exec(url);
		if(resultW && objectWidth <= resultW[1]){
			if('bg-cover'===objectType || 'img-crop'===objectType){
				return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight );
			}
			return url.replace(regW, 'w=' + objectWidth);
		}
		var resultFit = regFit.exec(decUrl);
		if(resultFit && objectWidth < resultFit[1]){
			if('bg-cover'===objectType || 'img-crop'===objectType){
				return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight );
			}
			return decUrl.replace(regFit, 'fit=' + objectWidth + ',' + objectHeight);
		}
                if(!resultW && !resultFit && !resultResize){
			if('img'===objectType){
				return url + '&fit=' + objectWidth + ',' + objectHeight;
			}
			if('bg-cover'===objectType || 'img-crop'===objectType){
				return url + '?resize=' + objectWidth + ',' + objectHeight;
			}
			if(objectHeight>objectWidth){
				return url + '&h=' + objectHeight;
			}
			return url + '&w=' + objectWidth;
		}
	}
	if (url.search('\\?') == -1 && url.search(eio_lazy_vars.exactdn_domain) > 0){
		if('img'===objectType){
			return url + '?fit=' + objectWidth + ',' + objectHeight;
		}
		if('bg-cover'===objectType || 'img-crop'===objectType){
			return url + '?resize=' + objectWidth + ',' + objectHeight;
		}
		if(objectHeight>objectWidth){
			return url + '?h=' + objectHeight;
		}
		return url + '?w=' + objectWidth;
	}
	return url;
}
document.addEventListener('lazybeforeunveil', function(e){
        var target = e.target;
	//console.log('the target');
	//console.log(target);
	//console.log('loading an image');
	var wrongSize = false;
	var srcset = target.getAttribute('data-srcset');
        if ( ! srcset && target.naturalWidth) {
		//console.log('we have something');
        	if ((target.naturalWidth > 1) && (target.naturalHeight > 1)) {
                	// For each image with a natural width which isn't
                	// a 1x1 image, check its size.
			var dPR = (window.devicePixelRatio || 1);
                	var wrongWidth = (target.clientWidth * 1.25 < target.naturalWidth);
                	var wrongHeight = (target.clientHeight * 1.25 < target.naturalHeight);
			/*console.log(Math.round(target.clientWidth * dPR) + "x" + Math.round(target.clientHeight * dPR) + ", natural is " +
				target.naturalWidth + "x" + target.naturalHeight + "!");
			console.log( target.getAttribute('data-src') );*/
                	if (wrongWidth || wrongHeight) {
				var targetWidth = Math.round(target.offsetWidth * dPR);
				var targetHeight = Math.round(target.offsetHeight * dPR);

				var src = target.getAttribute('data-src');
        			var webpsrc = target.getAttribute('data-src-webp');
        			if(ewww_webp_supported && webpsrc && -1 == src.search('webp=1')){
					//console.log('using data-src-webp');
					src = webpsrc;
				}
				if (!shouldAutoScale(target)||!shouldAutoScale(target.parentNode)){
					var newSrc = false;
				} else if ( window.lazySizes.hC(target,'et_pb_jt_filterable_grid_item_image')) {
					var newSrc = constrainSrc(src,targetWidth,targetHeight,'img-crop');
				} else {
					var newSrc = constrainSrc(src,targetWidth,targetHeight,'img');
				}
				if (newSrc && src != newSrc){
					target.setAttribute('data-src', newSrc);
				}
			}
        	}
        }
        if(ewww_webp_supported) {
		//console.log('we could load webp');
		//console.log(srcset);
		if (srcset && -1 < srcset.search('webp=1')){
			//console.log('srcset already contains webp ' + srcset);
			return;
		}
		if (srcset) {
        		var webpsrcset = target.getAttribute('data-srcset-webp');
                	if(webpsrcset){
				//console.log('replacing webp srcset attr');
				target.setAttribute('data-srcset', webpsrcset);
			}
		}
		var src = target.getAttribute('data-src');
		if (src && -1 < src.search('webp=1')){
			//console.log('src already webp');
			return;
		} else {
			//console.log('src missing webp ' + src);
		}
        	var webpsrc = target.getAttribute('data-src-webp');
                if(!webpsrc){
			//console.log('no webp attr');
			return;
		}
		//console.log('replacing webp src attr');
		target.setAttribute('data-src', webpsrc);
        }
});
