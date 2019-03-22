lazysizesWebP('alpha', lazySizes.init);
function constrainSrc(bg,objectWidth,objectHeight){
	var regW      = /w=(\d+)/;
	var regFit    = /fit=(\d+),(\d+)/;
	var regResize = /resize=(\d+),(\d+)/;
	if (bg.search('\\?') > 0 && bg.search(ewww_lazy_vars.exactdn_domain) > 0){
		var resultW = regW.exec(bg);
		if(resultW && objectWidth <= resultW[1]){
			return bg.replace(regW, 'resize=' + objectWidth + ',' + objectHeight);
		}
		var resultFit = regFit.exec(bg);
		if(resultFit && objectWidth < resultFit[1]){
			return bg.replace(regFit, 'fit=' + objectWidth + ',' + objectHeight);
		}
		var resultResize = regResize.exec(bg);
		if(resultResize && objectWidth < resultResize[1]){
			return bg.replace(regResize, 'resize=' + objectWidth + ',' + objectHeight);
		}
                if(!resultW && !resultFit && !resultResize){
			return bg + '&resize=' + objectWidth + ',' + objectHeight;
		}
	}
	if (bg.search('\\?') == -1 && bg.search(ewww_lazy_vars.exactdn_domain) > 0){
		return bg + '?resize=' + objectWidth + ',' + objectHeight;
	}
	return bg;
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
                	var wrongWidth = (target.clientWidth * 1.25 < target.naturalWidth);
                	var wrongHeight = (target.clientHeight * 1.25 < target.naturalHeight);
                	if (wrongWidth || wrongHeight) {
                        	console.log(target.clientWidth + "x" + target.clientHeight + ", natural is " +
                        	target.naturalWidth + "x" + target.naturalHeight + "!");
				var regW      = /w=(\d+)/;
				var regFit    = /fit=(\d+),(\d+)/;
				var regResize = /resize=(\d+),(\d+)/;
				var src = target.getAttribute('data-src');
        			var webpsrc = target.getAttribute('data-src-webp');
        			if(ewww_webp_supported && webpsrc && -1 == src.search('webp=1')){
					console.log('using data-src-webp');
					src = webpsrc;
				}
				if (src.search('\\?') > 0 && src.search(ewww_lazy_vars.exactdn_domain) > 0){
					console.log('existing params');
					var resultResize = regResize.exec(src);
					if(resultResize){
						console.log('resize param replacing');
						target.setAttribute('data-src', src.replace(regResize, 'resize=' + target.clientWidth + ',' + target.clientHeight));
					}
					var resultW = regW.exec(src);
					if(resultW){
						console.log('replacing w param');
						target.setAttribute('data-src', src.replace(regW, 'resize=' + target.clientWidth + ',' + target.clientHeight));
					}
					var resultFit = regFit.exec(src);
					if(resultFit){
						console.log('replacing fit param');
						target.setAttribute('data-src', src.replace(regFit, 'resize=' + target.clientWidth + ',' + target.clientHeight));
					}
			                if(!resultW && !resultFit && !resultResize){
						console.log('appending');
						target.setAttribute('data-src', src + '&resize=' + target.clientWidth + ',' + target.clientHeight);
					}
				}
				if (src.search('\\?') == -1 && src.search(ewww_lazy_vars.exactdn_domain) > 0){
					console.log('no params yet, adding');
					target.setAttribute('data-src', src + '?resize=' + target.clientWidth + ',' + target.clientHeight);
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
