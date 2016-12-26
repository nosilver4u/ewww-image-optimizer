// @name          Show scaled images
// @namespace     http://nedbatchelder.com/

window.onload = function() {
	checkImageSizes();
	document.getElementById('wp-admin-bar-resize-detection').onclick = function() {
		checkImageSizes();
	};
}
function checkImageSizes() {
    // Find images which have width or height different than their natural
    // width or height, and give them a stark and ugly marker, as well
    // as a useful title.
    var imgs = document.getElementsByTagName("img");
    for (i = 0; i < imgs.length; i++) {
        var img = imgs[i];
        if (img.naturalWidth) {
            if ((img.naturalWidth != 1) && (img.naturalHeight != 1)) {
                // For each image with a natural width which isn't
                // a 1x1 image, check its size.
                var wrongWidth = (img.width != img.naturalWidth);
                var wrongHeight = (img.height != img.naturalHeight);
                if (wrongWidth || wrongHeight) {
                    img.style.border = "3px red dotted";
                    img.style.margin = "-3px";
                    img.style.background = "yellow";
                    img.title = "Forced to wrong size: " +
                        img.width + "x" + img.height + ", natural is " +
                        img.naturalWidth + "x" + img.naturalHeight + "!";
                }
            }
        }
    }
    return false;
}
