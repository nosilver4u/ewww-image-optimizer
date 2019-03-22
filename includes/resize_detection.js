window.onload = function() {
	checkImageSizes();
	var adminBarButton = document.getElementById('wp-admin-bar-resize-detection');
	if (adminBarButton) {
		adminBarButton.onclick = function() {
			clearScaledImages();
			checkImageSizes();
		};
	}
}
function checkImageSizes() {
	// Find images which have width or height different than their natural
	// width or height, and give them a stark and ugly marker, as well
	// as a useful title.
	var imgs = document.getElementsByTagName("img");
	for (i = 0; i < imgs.length; i++) {
		imgs[i].classList.remove('scaled-image');
		checkImageScale(imgs[i]);
	}
	return false;
}
function checkImageScale(img) {
        if (img.naturalWidth) {
        	if ((img.naturalWidth != 1) && (img.naturalHeight != 1)) {
                	// For each image with a natural width which isn't
                	// a 1x1 image, check its size.
			var dPR = (window.devicePixelRatio || 1);
			var wrongWidth = (img.clientWidth * 1.5 * dPR < img.naturalWidth);
			var wrongHeight = (img.clientHeight * 1.5 * dPR < img.naturalHeight);
			if (wrongWidth || wrongHeight) {
				img.classList.add('scaled-image');
                		img.title = "Forced to wrong size: " +
                        	img.clientWidth + "x" + img.clientHeight + ", natural is " +
                        	img.naturalWidth + "x" + img.naturalHeight + "!";
			}
        	}
        }
}
function clearScaledImages() {
	var scaledImages = document.querySelectorAll('img.scaled-image');
	for (var i = 0, len = scaledImages.length; i < len; i++){
		scaledImages[i].classList.remove('scaled-image');
	}
}
document.addEventListener('lazyloaded', function(e){
	e.target.classList.remove('scaled-image');
        e.target.title = '';
	checkImageScale(e.target);
});
