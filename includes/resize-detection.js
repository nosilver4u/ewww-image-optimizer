(function(window, document) {
	let started;
	const scalingError = document.createElement('div');
	scalingError.id = 'ewww-scaling-error';

	window.onload = function() {
		started = Date.now();
		checkImageSizes();
		document.body.append(scalingError);
		const adminBarCheckButton = document.getElementById('wp-admin-bar-image-detective-check');
		if (adminBarCheckButton) {
			adminBarCheckButton.onclick = function() {
				adminBarCheckButton.classList.toggle('ewww-fade');
				clearScaledImages();
				setTimeout(function() {
					checkImageSizes();
					adminBarCheckButton.classList.toggle('ewww-fade');
				}, 500);
				return false;
			};
		}
		const adminBarClearButton = document.getElementById('wp-admin-bar-image-detective-clear');
		if (adminBarClearButton) {
			adminBarClearButton.onclick = function() {
				adminBarClearButton.classList.toggle('ewww-fade');
				shouldTagLCP = false;
				clearLCPMarker();
				clearScaledImages();
				setTimeout(function() {
					adminBarClearButton.classList.toggle('ewww-fade');
				}, 500);
				return false;
			};
		}
		initCriticalImages();
	};

	const lcpElements = [];
	const lcpTag = document.createElement('div');
	const excludeMenuTag = document.createElement('div');
	excludeMenuTag.id = 'ewww-lazy-exclude-menu';
	let excludeButtonVisible = false;
	let excludeMenuVisible = false;
	let lastLCP = false;
	let shouldTagLCP = true;
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
		lcpTag.id = 'ewww-lcp-tag';
		lcpTag.innerText = 'LCP';
		const lcpObserver = new PerformanceObserver((list) => {
			const lcpEntries = list.getEntries();
			if (lcpElements.length > 0) {
				clearLCPMarker();
			} else {
				document.body.append(lcpTag);
				document.body.append(excludeMenuTag);
			}
			lastLCP = lcpEntries[lcpEntries.length - 1];
			tagLCP(lastLCP.element);
			lcpElements.push(lastLCP);
		});
		lcpObserver.observe({type: 'largest-contentful-paint', buffered: true});
		setTimeout(
			function() {
				lcpObserver.disconnect();
			},
			60000
		);
		for (const type of ['keydown', 'click']) {
			addEventListener(type, function() {
				shouldTagLCP = false;
			});
		}
	};

	const tagLCP = function(lcpElement) {
		if (! shouldTagLCP) {
			console.log('nope, do not tag LCP anymore');
			return;
		}
		matchPosition(lcpElement, lcpTag);
		console.log('LCP Element:', lcpElement);
		lcpElement.classList.add('ewww-lcp-element');
		if (imageDetectiveVars.llActive && lcpElement.tagName.toLowerCase() === 'img' && (hasClass(lcpElement,'lazyload') || hasClass(lcpElement,'lazyloaded'))) {
			lcpElement.addEventListener('mouseover', showExcludeMenu);
		}
	};

	const matchPosition = function(sourceElem, destElem) {
		const rect = sourceElem.getBoundingClientRect();
		destElem.style.top = (rect.top + window.scrollY) + 'px';
		destElem.style.left = (rect.left + window.scrollX) + 'px';
		destElem.style.zIndex = sourceElem.style.zIndex + 999;
		destElem.style.maxWidth = (rect.width - 60) + 'px';
	};

	const hideExcludeMenu = function(e) {
		if (hasClass(excludeMenuTag, 'ewww-error')) {
			document.removeEventListener('mouseup', hideExcludeMenu);
			return;
		}
		const currentLCP = document.querySelector('.ewww-lcp-element');
		if (!currentLCP.contains(e.target) && ! excludeMenuTag.contains(e.target) && 'ewww-lcp-tag' !== e.target.id) {
			excludeMenuTag.style.display = 'none';
			excludeButtonVisible = false;
			currentLCP.addEventListener('mouseover', showExcludeMenu);
			document.removeEventListener('mouseup', hideExcludeMenu);
		}
	}

	const sendExclusion = async function() {
		this.removeEventListener('click', sendExclusion);
		const exclusion = this.dataset.ewwwLazyExcludeValue;
		console.log('sending exclusion:', exclusion, this.dataset.globalExclusion);
		const exclusionData = new FormData();
		exclusionData.append('action', 'ewww_add_lazy_exclusion');
		exclusionData.append('ewww_wpnonce', imageDetectiveVars.nonce);
		exclusionData.append('global', this.dataset.globalExclusion);
		exclusionData.append('exclusion', exclusion);
		exclusionData.append('post_id', imageDetectiveVars.postId);
		exclusionData.append('request_uri', imageDetectiveVars.requestUri);

		try {
			const exclusionResponse = await fetch(
				imageDetectiveVars.ajaxUrl,
				{
					method: 'POST',
					body: exclusionData,
				}
			);
			if (!exclusionResponse.ok) {
				excludeMenuTag.innerHTML = imageDetectiveVars.invalid_response;
				excludeMenuTag.classList.add('ewww-error');
				window.console.log(`Attempt to add Lazy Load Exclusion resulted in an HTTP error with code ${exclusionResponse.status}`);
				return;
			}
			const exclusionResult = await exclusionResponse.json();
			if (exclusionResult.success && exclusionResult.message) {
				excludeMenuTag.innerHTML = exclusionResult.message;
				excludeMenuTag.classList.add('ewww-success');
			} else if (exclusionResult.data) {
				excludeMenuTag.innerHTML = exclusionResult.data;
				excludeMenuTag.classList.add('ewww-error');
			} else {
				excludeMenuTag.innerHTML = imageDetectiveVars.invalid_response;
				excludeMenuTag.classList.add('ewww-error');
				window.console.log('Attempt to add Lazy Load Exclusion encountered an invalid response:', exclusionResult);
			}
		} catch (error) {
			excludeMenuTag.innerHTML = imageDetectiveVars.invalid_response;
			excludeMenuTag.classList.add('ewww-error');
			window.console.log('Attempt to add Lazy Load Exclusion resulted in an error:', error);
		}
		excludeMenuVisible = false;
	}

	const sendIgnoreImage = async function() {
		event.preventDefault();
		this.removeEventListener('click', sendIgnoreImage);
		currentScalingErrorElem.removeEventListener('mouseover', showScalingError);
		// How do we display success? Just nuke the error message?
		// But likely need to make sure we remove the ignoreTag
		const ignorePath = this.dataset.imagePath;
		console.log('sending ignore request:', ignorePath );
		const ignoreData = new FormData();
		ignoreData.append('action', 'ewww_add_ignore_scaling_rule');
		ignoreData.append('ewww_wpnonce', imageDetectiveVars.nonce);
		ignoreData.append('ignore', ignorePath);
		ignoreData.append('post_id', imageDetectiveVars.postId);
		ignoreData.append('request_uri', imageDetectiveVars.requestUri);

		try {
			const ignoreResponse = await fetch(
				imageDetectiveVars.ajaxUrl,
				{
					method: 'POST',
					body: ignoreData,
				}
			);
			if (!ignoreResponse.ok) {
				scalingError.innerHTML = imageDetectiveVars.invalid_response;
				scalingError.classList.add('ewww-error');
				window.console.log(`Attempt to add Scaling Ignore Rule resulted in an HTTP error with code ${ignoreResponse.status}`);
				return false;
			}
			const ignoreResult = await ignoreResponse.json();
			if (ignoreResult.success && ignoreResult.message) {
				currentScalingErrorElem.classList.remove('ewww-improperly-scaled');
				scalingError.innerHTML = ignoreResult.message;
				scalingError.classList.add('ewww-success');
				imageDetectiveVars.scalingExclusions.push(ignorePath);
			} else if (ignoreResult.data) {
				scalingError.innerHTML = ignoreResult.data;
				scalingError.classList.add('ewww-error');
			} else {
				scalingError.innerHTML = imageDetectiveVars.invalid_response;
				scalingError.classList.add('ewww-error');
				window.console.log('Attempt to add Scaling Ignore Rule encountered an invalid response:', ignoreResult);
			}
		} catch (error) {
			scalingError.innerHTML = imageDetectiveVars.invalid_response;
			scalingError.classList.add('ewww-error');
			window.console.log('Attempt to add Scaling Ignore Rule resulted in an error:', error);
		}
		return false;
	}

	const addExcludeMenuItem = function(exclusion,global) {
		const exclusionMenuItem = document.createElement('li');
		if (global) {
			exclusionMenuItem.innerHTML = imageDetectiveVars.menuItemGlobalText;
			exclusionMenuItem.dataset.globalExclusion = 1;
		} else {
			exclusionMenuItem.innerHTML = imageDetectiveVars.menuItemPageText;
			exclusionMenuItem.dataset.globalExclusion = 0;
		}
		exclusionMenuItem.dataset.ewwwLazyExcludeValue = exclusion;
		exclusionMenuItem.querySelector('span.ewww-lazy-exclude-item-name').innerText = exclusion;
		exclusionMenuItem.addEventListener('click', sendExclusion);
		return exclusionMenuItem;
	}

	const getImagePath = function(img) {
		if ('string' == typeof img.src && img.src.search(/data:image/) === -1) {
			console.log('attempting to get image path:', img.src);
			try {
				const imageURL = new URL(img.src);
				if (imageURL.pathname.length > 0) {
					return imageURL.pathname;
				}
			} catch (error) {
				console.log('could not parse', error);
			}
		}
		return '';
	}

	const showExcludeMenu = function() {
		this.removeEventListener('mouseover', showExcludeMenu);
		if (hasClass(excludeMenuTag, 'ewww-error')) {
			return;
		}
		if (hasClass(excludeMenuTag, 'ewww-success')) {
			excludeMenuTag.classList.remove('ewww-success');
			excludeMenuTag.innerHTML = '';
		}
		excludeButtonVisible = true;
		const excludeMenuItems = [];
		let excludeMenuList = excludeMenuTag.querySelector('ul');
		if (null === excludeMenuList) {
			const imagePath = getImagePath(this);
			if (imagePath) {
				console.log('adding menu items with ' + imagePath);
				excludeMenuItems.push(addExcludeMenuItem(imagePath, true));
				excludeMenuItems.push(addExcludeMenuItem(imagePath, false));
			}
			if (this.classList.length > 0) {
				const ignoredClasses = ['ewww-lcp-element','lazyload','lazyloaded','lazyloading','lazyautosizes','ls-is-cached','ewww-improperly-scaled'];
				this.classList.forEach(function(classValue){
					if (ignoredClasses.includes(classValue)) {
						return;
					}
					excludeMenuItems.push(addExcludeMenuItem(classValue, true));
				});
			}
			if (this.id.length > 0) {
				excludeMenuItems.push(addExcludeMenuItem(this.id, true));
			}
		}
		if (excludeMenuItems.length > 0) {
			console.log('creating menu list');
			const excludeMenuLabel = document.createElement('div');
			excludeMenuLabel.id = 'ewww-lazy-exclude-menu-label';
			excludeMenuLabel.innerText = imageDetectiveVars.excludeMenuText;
			excludeMenuLabel.onclick = function() {
				if (excludeMenuVisible) {
					excludeMenuVisible = false;
					excludeMenuTag.querySelector('ul').style.display = 'none';
				} else {
					excludeMenuVisible = true;
					excludeMenuTag.querySelector('ul').style.display = 'block';
				}
			};
			excludeMenuTag.append(excludeMenuLabel);
			excludeMenuList = document.createElement('ul');
			for (let i = 0, len = excludeMenuItems.length; i < len; i++) {
				console.log('adding menu list item');
				excludeMenuList.append(excludeMenuItems[i]);
			}
			excludeMenuTag.append(excludeMenuList);
		}
		if (hasClass(this, 'ewww-improperly-scaled')) {
			excludeMenuTag.style.marginTop = '110px';
			excludeMenuTag.dataset.badScale = 1;
		} else {
			excludeMenuTag.dataset.badScale = 0;
		}
		matchPosition(this, excludeMenuTag);
		if ('none' === excludeMenuTag.style.display) {
			excludeMenuTag.style.display = 'flex';
		}
		document.addEventListener('mouseup', hideExcludeMenu);
	}

	let currentScalingErrorElem = {};
	const showScalingError = function() {
		currentScalingErrorElem = this;
		scalingError.classList.remove('ewww-error');
		scalingError.classList.remove('ewww-success');
		scalingError.innerHTML = imageDetectiveVars.scalingErrorText;
		scalingError.querySelector('span.ewww-scaling-error-natural-width').innerText = this.srcsetImg && this.srcsetImg.naturalWidth > 0 ? this.srcsetImg.naturalWidth : this.naturalWidth;
		scalingError.querySelector('span.ewww-scaling-error-natural-height').innerText = this.srcsetImg && this.srcsetImg.naturalHeight > 0 ? this.srcsetImg.naturalHeight : this.naturalHeight;
		scalingError.querySelector('span.ewww-scaling-error-container-width').innerText = this.clientWidth;
		scalingError.querySelector('span.ewww-scaling-error-container-height').innerText = this.clientHeight;
		if (hasClass(this, 'ewww-lcp-element')) {
			scalingError.style.marginTop = '60px';
		} else {
			scalingError.style.removeProperty('margin-top');
			const imagePath = getImagePath(this);
			if (imagePath) {
				let ignoreTag = document.createElement('a');
				ignoreTag.classList.add('ewww-ignore-scaling-error');
				ignoreTag.href = '#';
				ignoreTag.innerText = imageDetectiveVars.ignoreErrorText;
				ignoreTag.addEventListener('click', sendIgnoreImage);
				ignoreTag.dataset.imagePath = imagePath;
				scalingError.append(' ', ignoreTag);
			}
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
		for (let i = 0, len = scaledImages.length; i < len; i++) {
			clearScalingError(scaledImages[i]);
		}
		hideScalingError();
	}

	const clearLCPMarker = function() {
		for (let i = 0, len = lcpElements.length; i < len; i++){
			lcpElements[i].element.classList.remove('ewww-lcp-element');
			lcpElements[i].element.removeEventListener('mouseover', showExcludeMenu);
		}
		lcpTag.style.top = '-1000px';
		lcpTag.style.left = '-1000px';
	}

	const checkImageSizes = function() {
		// Find images which have width or height greater than their natural
		// width or height and highlight them.
		const imgs = document.getElementsByTagName('img');
		for (i = 0; i < imgs.length; i++) {
			clearScalingError(imgs[i]);
			checkImageScale(imgs[i]);
		}
		if (lastLCP) {
			shouldTagLCP = true;
			tagLCP(lastLCP.element);
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
		const imagePath = getImagePath(img);
		if (imageDetectiveVars.scalingExclusions.includes(imagePath)) {
			return;
		}
		if (img.complete && img.naturalWidth > 0) {
			console.log('checking size of: ' + img.src);
			if (img.naturalWidth > 25 && img.naturalHeight > 25 && img.clientWidth > 25 && img.clientHeight > 25) {
				// For each image with a natural width greater than 25x25...
				if (img.srcset && img.currentSrc && img.srcsetImg && img.srcsetImg.src == img.currentSrc) {
					compareNaturalToExpected(img, img.srcsetImg.naturalWidth, img.srcsetImg.naturalHeight);
				} else if (img.srcset && img.currentSrc && 'string' == typeof img.currentSrc) {
					img.srcsetImg = new Image();
					img.srcsetImg.onload = function() {
						console.log(`natural dimensions from srcset source ${img.srcsetImg.src} are ${img.srcsetImg.naturalWidth}x${img.srcsetImg.naturalHeight}`);
						compareNaturalToExpected(img, img.srcsetImg.naturalWidth, img.srcsetImg.naturalHeight);
					}
					img.srcsetImg.src = img.currentSrc;
				} else {
					compareNaturalToExpected(img, img.naturalWidth, img.naturalHeight);
				}
			}
		} else {
			console.log('defering check until onload: ' + img.src);
			img.onload = function() {
				checkImageScale(this);
			}
		}
	};

	const compareNaturalToExpected = function(img, physicalWidth, physicalHeight) {
		const dPR = (window.devicePixelRatio || 1);
		console.log(`comparing natural ${physicalWidth}x${physicalHeight} vs. expected ${img.clientWidth}x${img.clientHeight} using dpr ${dPR}`);
		const wrongWidth = (img.clientWidth * 1.25 * dPR < physicalWidth) || (img.clientWidth > 768 && img.clientWidth * dPR + 100 < physicalWidth);
		const wrongHeight = (img.clientHeight * 1.25 * dPR < physicalHeight) || (img.clientHeight > 768 && img.clientHeight * dPR + 100 < physicalHeight);
		const widthDiff = physicalWidth - img.clientWidth * dPR;
		const heightDiff = physicalHeight - img.clientHeight * dPR;
		console.log(`width difference is ${widthDiff} and height difference is ${heightDiff}`);
		if ((wrongWidth && heightDiff > 25) || (wrongHeight && widthDiff > 25)) {
			img.classList.add('ewww-improperly-scaled');
			img.addEventListener('mouseover', showScalingError);
		}
	}

	document.addEventListener('lazyloaded', function(e) {
		clearScalingError(e.target);
		checkImageScale(e.target);
	});
})(window, document);