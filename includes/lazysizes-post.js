lazysizesWebP('alpha', lazySizes.init);
function constrainSrc(bg,objectWidth,objectHeight){
	var regW      = /w=(\d+)/;
	var regFit    = /fit=(\d+),(\d+)/;
	var regResize = /resize=(\d+),(\d+)/;
	if (bg.search('\\?') > 0 && bg.search(ewww_lazy_vars.exactdn_domain) > 0){
		var resultW = regW.exec(bg);
		if(resultW && objectWidth < resultW[1]){
			return bg.replace(regW, 'w=' + objectWidth);
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
                        return bg + '&w=' + objectWidth;
                }
	}
	if (bg.search('\\?') == -1 && bg.search(ewww_lazy_vars.exactdn_domain) > 0){
		return bg + '?w=' + objectWidth;
	}
	return bg;
}
document.addEventListener('lazybeforeunveil', function(e){
        var target = e.target;
	//console.log('the target');
	//console.log(target);
	//console.log('loading an image');
        if(ewww_webp_supported) {
		//console.log('we could load webp');
		var srcset = target.getAttribute('data-srcset');
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
