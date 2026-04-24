(function(window, document) {
	let started;
	const scalingError = document.createElement('div');
	scalingError.id = 'ewww-scaling-error';

	window.onload = function() {
		started = Date.now();
		checkImageSizes();
		document.body.append(scalingError);
		const adminBarButton = document.getElementById('wp-admin-bar-resize-detection');
		if (adminBarButton) {
			adminBarButton.onclick = function() {
				adminBarButton.classList.toggle('ewww-fade');
				clearScaledImages();
				checkImageSizes();
				setTimeout(function() {
					adminBarButton.classList.toggle('ewww-fade');
				}, 500);
			};
		}
		initCriticalImages();
	};

	const lcpElements = [];
	const initCriticalImages = function() {
		if (! atfImagesLoaded() && Date.now() - started < 5000) {
			console.log('hold up, need to wait a bit...');
			setTimeout(initCriticalImages, 100);
			return;
		}
		console.log('lets get the LCP image!');
		setupLCPObserver();
	};

	const setupLCPObserver = function() {
		const lcpTag = document.createElement('div');
		lcpTag.id = 'ewww-lcp-tag';
		lcpTag.innerText = 'LCP';
		const lcpObserver = new PerformanceObserver((list) => {
			const lcpEntries = list.getEntries();
			if (lcpElements.length > 0) {
				for (let i = 0, len = lcpElements.length; i < len; i++){
					lcpElements[i].element.classList.remove('ewww-lcp-element');
				}
			} else {
				document.body.append(lcpTag);
			}
			const lastLCP = lcpEntries[lcpEntries.length - 1];
			matchPosition(lastLCP.element, lcpTag);
			console.log('LCP Element:', lastLCP.element);
			lastLCP.element.classList.add('ewww-lcp-element');
			// TODO: check the sizing of the LCP element--should already, but how will that work?
			// Probably just need a special CSS rule for the combined classes. But possibly also something to handle having the dimension data and LCP tag at the same time.
			lcpElements.push(lastLCP);
		});
		lcpObserver.observe({type: 'largest-contentful-paint', buffered: true});
		setTimeout(
			function() {
				lcpObserver.disconnect();
			},
			60000
		);
		for (const type of ['keydown', 'click', 'visibilitychange']) {
			addEventListener(type, function() {
				lcpObserver.disconnect();
			});
		}
	};

	const matchPosition = function(sourceElem, destElem) {
		const rect = sourceElem.getBoundingClientRect();
		destElem.style.top = (rect.top + window.scrollY) + 'px';
		destElem.style.left = (rect.left + window.scrollX) + 'px';
		destElem.style.zIndex = sourceElem.style.zIndex + 999;
		destElem.style.maxWidth = (rect.width - 40) + 'px';
	};

	const showScalingError = function() {
		scalingError.innerText = this.dataset.ewwwScalingError;
		if (hasClass(this, 'ewww-lcp-element')) {
			scalingError.style.marginTop = '60px';
		} else {
			scalingError.style.removeProperty('margin-top');
		}
		matchPosition(this, scalingError);
	}

	const hideScalingError = function() {
		scalingError.innerText = '';
		scalingError.style.top = '-1000px';
		scalingError.style.left = '-1000px';
	}

	const clearScalingError = function(elem) {
		elem.classList.remove('ewww-improperly-scaled');
		delete elem.dataset.ewwwScalingError;
		elem.removeEventListener('mouseover', showScalingError);
	}

	const clearScaledImages = function() {
		const scaledImages = document.querySelectorAll('img.ewww-improperly-scaled');
		for (let i = 0, len = scaledImages.length; i < len; i++){
			clearScalingError(scaledImages[i]);
		}
	}

	const checkImageSizes = function() {
		// Find images which have width or height greater than their natural
		// width or height, highlight them, and store an error message.
		const imgs = document.getElementsByTagName('img');
		for (i = 0; i < imgs.length; i++) {
			clearScalingError(imgs[i]);
			checkImageScale(imgs[i]);
		}
		return false;
	};

	const atfImages = [];
	let isBodyHidden;

	const getATFImages = function() {
		if (atfImages.length > 0) {
			return;
		}
		const imgs = document.getElementsByTagName('img');
		for (i = 0; i < imgs.length; i++) {
			if (imageInView(imgs[i])) {
				atfImages.push(imgs[i]);
			}
		}
	};

	const atfImagesLoaded = function() {
		getATFImages();
		for (i = 0; i < atfImages.length; i++) {
			if ( ! imageLoaded(atfImages[i])) {
				console.log(atfImages[i].src + ' not loaded yet:');
				console.log(atfImages[i]);
				return false;
			}
		}
		return true;
	};

	const imageLoaded = function(img) {
		if (! img.complete) {
			return false;
		}
		if (0 === img.naturalWidth) {
			return false;
		}
		if (hasClass(img, 'lazyload')) {
			return false;
		}
		return true;
	};

	const imageInView = function(img) {
		const rect = img.getBoundingClientRect();
		const screenWidth = window.innerWidth || document.documentElement.clientWidth;
		const screenHeight = window.innerHeight || document.documentElement.clientHeight;
		if (
			(rect.bottom || rect.right || rect.left || rect.top) &&
			(
				(rect.top >= 0 && rect.top <= screenHeight) ||
				(rect.bottom >= 0 && rect.bottom <= screenHeight)
			) &&
			(
				(rect.left >= 0 && rect.left <= screenWidth) ||
				(rect.right >= 0 && rect.right <= screenWidth)
			) &&
			isVisible(img)
		) {
			return true;
		}
		/*console.log(img.dataset.src);
		console.log(rect.top + ' >= 0');
		console.log(rect.left + ' >= 0');
		console.log(rect.right + ' <= ' + (window.innerWidth || document.documentElement.clientWidth));
		console.log(rect.bottom + ' <= ' + (window.innerHeight || document.documentElement.clientHeight));*/
		return false;
	};

	const isVisible = function (elem) {
		if (isBodyHidden == null) {
			isBodyHidden = getCSS(document.body, 'visibility') == 'hidden';
		}
		return isBodyHidden || !(getCSS(elem.parentNode, 'visibility') == 'hidden' && getCSS(elem, 'visibility') == 'hidden');
	};

	const getCSS = function (elem, style) {
		return (getComputedStyle(elem, null) || {})[style];
	};

	const regClassCache = {};
	const hasClass = function(ele, cls) {
		if(!regClassCache[cls]){
			regClassCache[cls] = new RegExp('(\\s|^)'+cls+'(\\s|$)');
		}
		return regClassCache[cls].test(ele.getAttribute('class') || '') && regClassCache[cls];
	};

	const checkImageScale = function(img) {
		if (!img.src) {
			return;
		}
		if ('string' == typeof img.src && img.src.search(/\.svg/) > -1) {
			return;
		}
		if ('string' == typeof img.src && img.src.search(/data:image/) > -1) {
			return;
		}
		console.log('checking size of: ' + img.src);
		if (img.naturalWidth) {
			if (img.naturalWidth > 25 && img.naturalHeight > 25 && img.clientWidth > 25 && img.clientHeight > 25) {
				// For each image with a natural width which isn't
				// a 1x1 image, check its size.
				const dPR = (window.devicePixelRatio || 1);
				const wrongWidth = (img.clientWidth * 1.5 * dPR < img.naturalWidth);
				const wrongHeight = (img.clientHeight * 1.5 * dPR < img.naturalHeight);
				if (wrongWidth || wrongHeight) {
					img.classList.add('ewww-improperly-scaled');
					img.dataset.ewwwScalingError = "Forced to wrong size: " +
						img.clientWidth + "x" + img.clientHeight + ", natural is " +
						img.naturalWidth + "x" + img.naturalHeight + "!";
					img.addEventListener('mouseover', showScalingError);
					// img.addEventListener('mouseout', hideScalingError);
				}
			}
		}
	};

	document.addEventListener('lazyloaded', function(e) {
		clearScalingError(e.target);
		checkImageScale(e.target);
	});
})(window, document);