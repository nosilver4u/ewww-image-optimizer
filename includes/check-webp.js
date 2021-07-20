var ewww_webp_supported = false;
// webp detection adapted from https://developers.google.com/speed/webp/faq#how_can_i_detect_browser_support_using_javascript
function check_webp_feature(feature, callback) {
	callback = (typeof callback !== 'undefined') ? callback : function(){};
	if (ewww_webp_supported) {
                callback(ewww_webp_supported);
		return;
	}
        var kTestImages = {
                alpha: "UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",
        };
        var img = new Image();
        img.onload = function () {
                ewww_webp_supported = (img.width > 0) && (img.height > 0);
		if (callback) {
                	callback(ewww_webp_supported);
		}
        };
        img.onerror = function () {
		if (callback) {
                	callback(false);
		}
        };
        img.src = "data:image/webp;base64," + kTestImages[feature];
}
check_webp_feature('alpha');
