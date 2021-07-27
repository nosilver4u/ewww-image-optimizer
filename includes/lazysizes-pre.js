/*var ewww_webp_supported = false;
function lazysizesWebP(feature, callback) {
        var kTestImages = {
                alpha: "UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",
                animation: "UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"
        };
        var img = new Image();
        img.onload = function () {
                ewww_webp_supported = (img.width > 0) && (img.height > 0);
                callback();
        };
        img.onerror = function () {
                callback();
        };
        img.src = "data:image/webp;base64," + kTestImages[feature];
}*/
if (typeof ewww_webp_supported === 'undefined') {
	var ewww_webp_supported = false;
}
window.lazySizesConfig = window.lazySizesConfig || {};
/* window.lazySizesConfig.init = false; */
window.lazySizesConfig.expand = document.documentElement.clientHeight > 500 && document.documentElement.clientWidth > 500 ? 1000 : 740;
if (eio_lazy_vars.threshold > 50) {
	window.lazySizesConfig.expand = eio_lazy_vars.threshold;
}
console.log( 'root margin: ' + window.lazySizesConfig.expand );
