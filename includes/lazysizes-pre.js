if (typeof ewww_webp_supported === 'undefined') {
	var ewww_webp_supported = false;
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
