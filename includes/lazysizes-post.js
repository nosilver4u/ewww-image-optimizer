lazysizesWebP('alpha', lazySizes.init);
function constrainSrc(url,objectWidth,objectHeight){
	var regW      = /w=(\d+)/;
	var regFit    = /fit=(\d+),(\d+)/;
	var regResize = /resize=(\d+),(\d+)/;
	if (url.search('\\?') > 0 && url.search(ewww_lazy_vars.exactdn_domain) > 0){
		var resultResize = regResize.exec(url);
		if(resultResize && objectWidth < resultResize[1]){
			return url.replace(regResize, 'resize=' + objectWidth + ',' + objectHeight);
		}
		var resultW = regW.exec(url);
		if(resultW && objectWidth <= resultW[1]){
			return url.replace(regW, 'resize=' + objectWidth + ',' + objectHeight);
		}
		var resultFit = regFit.exec(url);
		if(resultFit && objectWidth < resultFit[1]){
			return url.replace(regFit, 'resize=' + objectWidth + ',' + objectHeight);
		}
                if(!resultW && !resultFit && !resultResize){
			return url + '&resize=' + objectWidth + ',' + objectHeight;
		}
	}
	if (url.search('\\?') == -1 && url.search(ewww_lazy_vars.exactdn_domain) > 0){
		return url + '?resize=' + objectWidth + ',' + objectHeight;
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
		console.log('we have something');
        	if ((target.naturalWidth > 1) && (target.naturalHeight > 1)) {
                	// For each image with a natural width which isn't
                	// a 1x1 image, check its size.
			var dPR = (window.devicePixelRatio || 1);
                	var wrongWidth = (target.clientWidth * 1.25 < target.naturalWidth);
                	var wrongHeight = (target.clientHeight * 1.25 < target.naturalHeight);
                	if (wrongWidth || wrongHeight) {
				console.log(Math.round(target.clientWidth * dPR) + "x" + Math.round(target.clientHeight * dPR) + ", natural is " +
					target.naturalWidth + "x" + target.naturalHeight + "!");
				var targetWidth = Math.round(target.offsetWidth * dPR);
				var targetHeight = Math.round(target.offsetHeight * dPR);

				var src = target.getAttribute('data-src');
        			var webpsrc = target.getAttribute('data-src-webp');
        			if(ewww_webp_supported && webpsrc && -1 == src.search('webp=1')){
					console.log('using data-src-webp');
					src = webpsrc;
				}
				var newSrc = constrainSrc(src,targetWidth,targetHeight);
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
