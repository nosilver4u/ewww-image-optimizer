if (typeof ewww_webp_supported === 'undefined') {
	var ewww_webp_supported = false;
}
if (typeof swis_lazy_css_images === 'undefined') {
	var swis_lazy_css_images = {};
}
window.lazySizesConfig = window.lazySizesConfig || {};
window.lazySizesConfig.expand = document.documentElement.clientHeight > 500 && document.documentElement.clientWidth > 500 ? 1000 : 740;
window.lazySizesConfig.iframeLoadMode = 1;
if (typeof eio_lazy_vars === 'undefined'){
	console.log('setting failsafe lazy vars');
	eio_lazy_vars = {
		exactdn_domain: '.exactdn.com',
		threshold: 0,
		skip_autoscale: 0,
		use_dpr: 0,
	};
}
if (eio_lazy_vars.threshold > 50) {
	window.lazySizesConfig.expand = eio_lazy_vars.threshold;
}
console.log( 'root margin: ' + window.lazySizesConfig.expand );
for ( const [css_index, css_image] of Object.entries(swis_lazy_css_images)){
	console.log('processing css image ' + css_index + ': ' + css_image[0].url);
	try {
		document.querySelectorAll(css_image[0].selector).forEach((el) => {
			if (!el.classList.contains('lazyload')) {
				console.log('adding lazyload to css image ' + css_index + ': ' + css_image[0].url);
				el.classList.add('lazyload');
				el.dataset.swisLazyId = css_index;
				if (css_image[0].rwidth > 5 && css_image[0].rheight > 5) {
					el.dataset.eioRwidth = css_image[0].rwidth;
					el.dataset.eioRheight = css_image[0].rheight;
				}
			}
		});
	} catch (e) {
		console.log('error processing css image(s) for "' + css_index[0].selector + '": ' + e);
	}
}
