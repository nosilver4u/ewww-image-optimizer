<?php
/**
 * Implements WebP rewriting using page parsing and JS functionality.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables EWWW IO to filter the page content and replace img elements with WebP markup.
 */
class EWWWIO_Alt_Webp extends EWWWIO_Page_Parser {

	/**
	 * The Alt WebP inline script contents. Current length 12941.
	 *
	 * @access private
	 * @var string $inline_script
	 */
	private $inline_script = 'var Arrive=function(d,t,c){"use strict";if(d.MutationObserver&&"undefined"!=typeof HTMLElement){var a,e,i=0,u=(a=HTMLElement.prototype.matches||HTMLElement.prototype.webkitMatchesSelector||HTMLElement.prototype.mozMatchesSelector||HTMLElement.prototype.msMatchesSelector,{matchesSelector:function(t,e){return t instanceof HTMLElement&&a.call(t,e)},addMethod:function(t,e,a){var i=t[e];t[e]=function(){return a.length==arguments.length?a.apply(this,arguments):"function"==typeof i?i.apply(this,arguments):void 0}},callCallbacks:function(t,e){e&&e.options.onceOnly&&1==e.firedElems.length&&(t=[t[0]]);for(var a,i=0;a=t[i];i++)a&&a.callback&&a.callback.call(a.elem,a.elem);e&&e.options.onceOnly&&1==e.firedElems.length&&e.me.unbindEventWithSelectorAndCallback.call(e.target,e.selector,e.callback)},checkChildNodesRecursively:function(t,e,a,i){for(var r,n=0;r=t[n];n++)a(r,e,i)&&i.push({callback:e.callback,elem:r}),0<r.childNodes.length&&u.checkChildNodesRecursively(r.childNodes,e,a,i)},mergeArrays:function(t,e){var a,i={};for(a in t)t.hasOwnProperty(a)&&(i[a]=t[a]);for(a in e)e.hasOwnProperty(a)&&(i[a]=e[a]);return i},toElementsArray:function(t){return void 0===t||"number"==typeof t.length&&t!==d||(t=[t]),t}}),h=((e=function(){this._eventsBucket=[],this._beforeAdding=null,this._beforeRemoving=null}).prototype.addEvent=function(t,e,a,i){var r={target:t,selector:e,options:a,callback:i,firedElems:[]};return this._beforeAdding&&this._beforeAdding(r),this._eventsBucket.push(r),r},e.prototype.removeEvent=function(t){for(var e,a=this._eventsBucket.length-1;e=this._eventsBucket[a];a--)if(t(e)){this._beforeRemoving&&this._beforeRemoving(e);var i=this._eventsBucket.splice(a,1);i&&i.length&&(i[0].callback=null)}},e.prototype.beforeAdding=function(t){this._beforeAdding=t},e.prototype.beforeRemoving=function(t){this._beforeRemoving=t},e),s=function(r,n){var s=new h,o=this,l={fireOnAttributesModification:!1};return s.beforeAdding(function(e){var t,a=e.target;a!==d.document&&a!==d||(a=document.getElementsByTagName("html")[0]),t=new MutationObserver(function(t){n.call(this,t,e)});var i=r(e.options);t.observe(a,i),e.observer=t,e.me=o}),s.beforeRemoving(function(t){t.observer.disconnect()}),this.bindEvent=function(t,e,a){e=u.mergeArrays(l,e);for(var i=u.toElementsArray(this),r=0;r<i.length;r++)s.addEvent(i[r],t,e,a)},this.unbindEvent=function(){var a=u.toElementsArray(this);s.removeEvent(function(t){for(var e=0;e<a.length;e++)if(this===c||t.target===a[e])return!0;return!1})},this.unbindEventWithSelectorOrCallback=function(a){var t,i=u.toElementsArray(this),r=a;t="function"==typeof a?function(t){for(var e=0;e<i.length;e++)if((this===c||t.target===i[e])&&t.callback===r)return!0;return!1}:function(t){for(var e=0;e<i.length;e++)if((this===c||t.target===i[e])&&t.selector===a)return!0;return!1},s.removeEvent(t)},this.unbindEventWithSelectorAndCallback=function(a,i){var r=u.toElementsArray(this);s.removeEvent(function(t){for(var e=0;e<r.length;e++)if((this===c||t.target===r[e])&&t.selector===a&&t.callback===i)return!0;return!1})},this},r=new function(){var l={fireOnAttributesModification:!1,onceOnly:!1,existing:!1};function n(t,e,a){return!(!u.matchesSelector(t,e.selector)||(t._id===c&&(t._id=i++),-1!=e.firedElems.indexOf(t._id))||(e.firedElems.push(t._id),0))}var d=(r=new s(function(t){var e={attributes:!1,childList:!0,subtree:!0};return t.fireOnAttributesModification&&(e.attributes=!0),e},function(t,r){t.forEach(function(t){var e=t.addedNodes,a=t.target,i=[];null!==e&&0<e.length?u.checkChildNodesRecursively(e,r,n,i):"attributes"===t.type&&n(a,r)&&i.push({callback:r.callback,elem:a}),u.callCallbacks(i,r)})})).bindEvent;return r.bindEvent=function(t,e,a){void 0===a?(a=e,e=l):e=u.mergeArrays(l,e);var i=u.toElementsArray(this);if(e.existing){for(var r=[],n=0;n<i.length;n++)for(var s=i[n].querySelectorAll(t),o=0;o<s.length;o++)r.push({callback:a,elem:s[o]});if(e.onceOnly&&r.length)return a.call(r[0].elem,r[0].elem);setTimeout(u.callCallbacks,1,r)}d.call(this,t,e,a)},r},o=new function(){var i={};function r(t,e){return u.matchesSelector(t,e.selector)}var n=(o=new s(function(){return{childList:!0,subtree:!0}},function(t,i){t.forEach(function(t){var e=t.removedNodes,a=[];null!==e&&0<e.length&&u.checkChildNodesRecursively(e,i,r,a),u.callCallbacks(a,i)})})).bindEvent;return o.bindEvent=function(t,e,a){void 0===a?(a=e,e=i):e=u.mergeArrays(i,e),n.call(this,t,e,a)},o};t&&g(t.fn),g(HTMLElement.prototype),g(NodeList.prototype),g(HTMLCollection.prototype),g(HTMLDocument.prototype),g(Window.prototype);var n={};return l(r,n,"unbindAllArrive"),l(o,n,"unbindAllLeave"),n}function l(t,e,a){u.addMethod(e,a,t.unbindEvent),u.addMethod(e,a,t.unbindEventWithSelectorOrCallback),u.addMethod(e,a,t.unbindEventWithSelectorAndCallback)}function g(t){t.arrive=r.bindEvent,l(r,t,"unbindArrive"),t.leave=o.bindEvent,l(o,t,"unbindLeave")}}(window,"undefined"==typeof jQuery?null:jQuery,void 0);function check_webp_feature(t,e){var a=new Image;a.onload=function(){ewww_webp_supported=0<a.width&&0<a.height,e(ewww_webp_supported)},a.onerror=function(){e(ewww_webp_supported=!1)},a.src="data:image/webp;base64,"+{alpha:"UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",animation:"UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"}[t]}function ewww_load_images(i){!function(s){function a(t,e){for(var a=["align","alt","border","crossorigin","height","hspace","ismap","longdesc","usemap","vspace","width","accesskey","class","contenteditable","contextmenu","dir","draggable","dropzone","hidden","id","lang","spellcheck","style","tabindex","title","translate","sizes","data-caption","data-attachment-id","data-permalink","data-orig-size","data-comments-opened","data-image-meta","data-image-title","data-image-description","data-event-trigger","data-highlight-color","data-highlight-opacity","data-highlight-border-color","data-highlight-border-width","data-highlight-border-opacity","data-no-lazy","data-lazy","data-large_image_width","data-large_image_height"],i=0,r=a.length;i<r;i++){var n=s(t).attr("data-"+a[i]);void 0!==n&&!1!==n&&s(e).attr(a[i],n)}return e}i&&(s(".batch-image img, .image-wrapper a, .ngg-pro-masonry-item a, .ngg-galleria-offscreen-seo-wrapper a").each(function(){var t;void 0!==(t=s(this).attr("data-webp"))&&!1!==t&&s(this).attr("data-src",t),void 0!==(t=s(this).attr("data-webp-thumbnail"))&&!1!==t&&s(this).attr("data-thumbnail",t)}),s(".image-wrapper a, .ngg-pro-masonry-item a").each(function(){var t=s(this).attr("data-webp");void 0!==t&&!1!==t&&s(this).attr("href",t)}),s(".rev_slider ul li").each(function(){void 0!==(e=s(this).attr("data-webp-thumb"))&&!1!==e&&s(this).attr("data-thumb",e);for(var t=1;t<11;){var e;void 0!==(e=s(this).attr("data-webp-param"+t))&&!1!==e&&s(this).attr("data-param"+t,e),t++}}),s(".rev_slider img").each(function(){var t=s(this).attr("data-webp-lazyload");void 0!==t&&!1!==t&&s(this).attr("data-lazyload",t)}),s("div.woocommerce-product-gallery__image").each(function(){var t=s(this).attr("data-webp-thumb");void 0!==t&&!1!==t&&s(this).attr("data-thumb",t)})),s("img.ewww_webp_lazy_retina").each(function(){var t;i?void 0!==(t=s(this).attr("data-srcset-webp"))&&!1!==t&&s(this).attr("data-srcset",t):void 0!==(t=s(this).attr("data-srcset-img"))&&!1!==t&&s(this).attr("data-srcset",t);s(this).removeClass("ewww_webp_lazy_retina")}),s("video").each(function(){var t;i?void 0!==(t=s(this).attr("data-poster-webp"))&&!1!==t&&s(this).attr("poster",t):void 0!==(t=s(this).attr("data-poster-image"))&&!1!==t&&s(this).attr("poster",t)}),s("img.ewww_webp_lazy_load").each(function(){var t;i?(s(this).attr("data-lazy-src",s(this).attr("data-lazy-webp-src")),void 0!==(t=s(this).attr("data-srcset-webp"))&&!1!==t&&s(this).attr("srcset",t),void 0!==(t=s(this).attr("data-lazy-srcset-webp"))&&!1!==t&&s(this).attr("data-lazy-srcset",t)):(s(this).attr("data-lazy-src",s(this).attr("data-lazy-img-src")),void 0!==(t=s(this).attr("data-srcset"))&&!1!==t&&s(this).attr("srcset",t),void 0!==(t=s(this).attr("data-lazy-srcset-img"))&&!1!==t&&s(ewww_img).attr("data-lazy-srcset",t));s(this).removeClass("ewww_webp_lazy_load")}),s(".ewww_webp_lazy_hueman").each(function(){var t,e=document.createElement("img");(s(e).attr("src",s(this).attr("data-src")),i)?(s(e).attr("data-src",s(this).attr("data-webp-src")),void 0!==(t=s(this).attr("data-srcset-webp"))&&!1!==t&&s(e).attr("data-srcset",t)):(s(e).attr("data-src",s(this).attr("data-img")),void 0!==(t=s(this).attr("data-srcset-img"))&&!1!==t&&s(e).attr("data-srcset",t));e=a(this,e),s(this).after(e),s(this).removeClass("ewww_webp_lazy_hueman")}),s(".ewww_webp").each(function(){var t=document.createElement("img");if(i){if(s(t).attr("src",s(this).attr("data-webp")),void 0!==(e=s(this).attr("data-srcset-webp"))&&!1!==e&&s(t).attr("srcset",e),void 0!==(e=s(this).attr("data-webp-orig-file"))&&!1!==e)s(t).attr("data-orig-file",e);else void 0!==(e=s(this).attr("data-orig-file"))&&!1!==e&&s(t).attr("data-orig-file",e);if(void 0!==(e=s(this).attr("data-webp-medium-file"))&&!1!==e)s(t).attr("data-medium-file",e);else void 0!==(e=s(this).attr("data-medium-file"))&&!1!==e&&s(t).attr("data-medium-file",e);if(void 0!==(e=s(this).attr("data-webp-large-file"))&&!1!==e)s(t).attr("data-large-file",e);else void 0!==(e=s(this).attr("data-large-file"))&&!1!==e&&s(t).attr("data-large-file",e);if(void 0!==(e=s(this).attr("data-webp-large_image"))&&!1!==e)s(t).attr("data-large_image",e);else void 0!==(e=s(this).attr("data-large_image"))&&!1!==e&&s(t).attr("data-large_image",e);if(void 0!==(e=s(this).attr("data-webp-src"))&&!1!==e)s(t).attr("data-src",e);else void 0!==(e=s(this).attr("data-src"))&&!1!==e&&s(t).attr("data-src",e)}else{var e;s(t).attr("src",s(this).attr("data-img")),void 0!==(e=s(this).attr("data-srcset-img"))&&!1!==e&&s(t).attr("srcset",e),void 0!==(e=s(this).attr("data-orig-file"))&&!1!==e&&s(t).attr("data-orig-file",e),void 0!==(e=s(this).attr("data-medium-file"))&&!1!==e&&s(t).attr("data-medium-file",e),void 0!==(e=s(this).attr("data-large-file"))&&!1!==e&&s(t).attr("data-large-file",e),void 0!==(e=s(this).attr("data-large_image"))&&!1!==e&&s(t).attr("data-large_image",e),void 0!==(e=s(this).attr("data-src"))&&!1!==e&&s(t).attr("data-src",e)}t=a(this,t),s(this).after(t),s(this).removeClass("ewww_webp")})}(jQuery),jQuery.fn.isotope&&jQuery.fn.imagesLoaded&&(jQuery(".fusion-posts-container-infinite").imagesLoaded(function(){jQuery(".fusion-posts-container-infinite").hasClass("isotope")&&jQuery(".fusion-posts-container-infinite").isotope()}),jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").imagesLoaded(function(){jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").isotope()}))}var ewww_webp_supported=!1,ewww_jquery_waiting_timer=0;function ewww_ngg_plus_parse_galleries(t){t&&jQuery.each(galleries,function(t,e){galleries[t].images_list=ewww_ngg_plus_parse_image_list(e.images_list)})}function ewww_ngg_plus_load_galleries(t){var r;t&&((r=jQuery)(window).on("ngg.galleria.themeadded",function(t,e){console.log(e),window.ngg_galleria._create_backup=window.ngg_galleria.create,window.ngg_galleria.create=function(t,e){var a=r(t).data("id");return console.log(a),galleries["gallery_"+a].images_list=ewww_ngg_plus_parse_image_list(galleries["gallery_"+a].images_list),window.ngg_galleria._create_backup(t,e)}}),r(window).on("override_nplModal_methods",function(t,i){i._set_events_backup=i.set_events,i.set_events=function(){return r("#npl_content").bind("npl_images_ready",function(t,e){var a=i.fetch_images.gallery_image_cache[e];a=ewww_ngg_plus_parse_image_list(a)}),i._set_events_backup()}}))}function ewww_ngg_plus_parse_image_list(r){var t;return(t=jQuery).each(r,function(a,i){void 0!==i["image-webp"]&&(r[a].image=i["image-webp"],delete r[a]["image-webp"]),void 0!==i["thumb-webp"]&&(r[a].thumb=i["thumb-webp"],delete r[a]["thumb-webp"]),void 0!==i.full_image_webp&&(r[a].full_image=i.full_image_webp,delete r[a].full_image_webp),void 0!==i.srcsets&&t.each(i.srcsets,function(t,e){void 0!==i.srcsets[t+"-webp"]&&(r[a].srcsets[t]=i.srcsets[t+"-webp"],delete r[a].srcsets[t+"-webp"])}),void 0!==i.full_srcsets&&t.each(i.full_srcsets,function(t,e){void 0!==i.full_srcsets[t+"-webp"]&&(r[a].full_srcsets[t]=i.full_srcsets[t+"-webp"],delete r[a].full_srcsets[t+"-webp"])})}),r}ewww_jquery_waiting=setInterval(function(){if(window.jQuery){check_webp_feature("alpha",ewww_load_images),check_webp_feature("alpha",ewww_ngg_plus_load_galleries),document.arrive(".ewww_webp",function(){ewww_load_images(ewww_webp_supported)});var t=0,e=setInterval(function(){"undefined"!=typeof galleries&&(check_webp_feature("alpha",ewww_ngg_plus_parse_galleries),clearInterval(e)),1e3<(t+=25)&&clearInterval(e)},25);clearInterval(ewww_jquery_waiting)}1e4<(ewww_jquery_waiting_timer+=100)&&clearInterval(ewww_jquery_waiting)},100);';


	/**
	 * Indicates if we are filtering ExactDN urls.
	 *
	 * @access protected
	 * @var bool $parsing_exactdn
	 */
	protected $parsing_exactdn = false;

	/**
	 * Allowed paths for "forced" WebP.
	 *
	 * @access protected
	 * @var array $webp_paths
	 */
	protected $webp_paths = array();

	/**
	 * Register (once) actions and filters for Alt WebP.
	 */
	function __construct() {
		global $ewwwio_alt_webp;
		if ( is_object( $ewwwio_alt_webp ) ) {
			return 'you are doing it wrong';
		}
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return false;
		}
		// Start an output buffer before any output starts.
		add_action( 'template_redirect', array( $this, 'buffer_start' ), 0 );
		// Filter for NextGEN image urls within JS.
		add_filter( 'ngg_pro_lightbox_images_queue', array( $this, 'ngg_pro_lightbox_images_queue' ), 11 );

		$this->home_url = trailingslashit( get_site_url() );
		ewwwio_debug_message( "home url: $this->home_url" );
		$this->relative_home_url = preg_replace( '/https?:/', '', $this->home_url );
		ewwwio_debug_message( "relative home url: $this->relative_home_url" );

		$this->webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( ! is_array( $this->webp_paths ) ) {
			$this->webp_paths = array();
		}
		ewwwio_debug_message( 'forcing any images matching these patterns to webp: ' . implode( ',', $this->webp_paths ) );
		if ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			global $exactdn;
			$this->exactdn_domain = $exactdn->get_exactdn_domain();
			if ( $this->exactdn_domain ) {
				$this->parsing_exactdn = true;
				ewwwio_debug_message( 'parsing an exactdn page' );
			}
		}

		// Load the appropriate JS.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			// Load the non-minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'debug_script' ) );
		} elseif ( defined( 'EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT' ) && EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT ) {
			// Load the minified, non-inline version of the webp rewrite script.
			add_action( 'wp_enqueue_scripts', array( $this, 'min_external_script' ) );
		} else {
			// Loads jQuery and the minified inline webp rewrite script.
			if ( function_exists( 'wp_add_inline_script' ) && ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_inline_fallback' ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'load_jquery' ) );
			} else {
				add_action( 'wp_head', array( $this, 'inline_script' ) );
			}
		}
	}


	/**
	 * Starts an output buffer and registers the callback function to do WebP replacement.
	 */
	function buffer_start() {
		ob_start( array( $this, 'filter_page_output' ) );
	}

	/**
	 * Copies attributes from the original img element to the noscript element.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be given all the (known) attributes of $image.
	 * @param string $prefix Optional. Value to prepend to all attribute names. Default 'data-'.
	 * @return string The modified noscript tag.
	 */
	function attr_copy( $image, $nscript, $prefix = 'data-' ) {
		if ( ! is_string( $image ) || ! is_string( $nscript ) ) {
			return $nscript;
		}
		$attributes = array(
			'align',
			'alt',
			'border',
			'crossorigin',
			'height',
			'hspace',
			'ismap',
			'longdesc',
			'usemap',
			'vspace',
			'width',
			'accesskey',
			'class',
			'contenteditable',
			'contextmenu',
			'dir',
			'draggable',
			'dropzone',
			'hidden',
			'id',
			'lang',
			'spellcheck',
			'style',
			'tabindex',
			'title',
			'translate',
			'sizes',
			'data-caption',
			'data-lazy-type',
			'data-attachment-id',
			'data-permalink',
			'data-orig-size',
			'data-comments-opened',
			'data-image-meta',
			'data-image-title',
			'data-image-description',
			'data-event-trigger',
			'data-highlight-color',
			'data-highlight-opacity',
			'data-highlight-border-color',
			'data-highlight-border-width',
			'data-highlight-border-opacity',
			'data-no-lazy',
			'data-lazy',
			'data-large_image_width',
			'data-large_image_height',
		);
		foreach ( $attributes as $attribute ) {
			$attr_value = $this->get_attribute( $image, $attribute );
			if ( $attr_value ) {
				$this->set_attribute( $nscript, $prefix . $attribute, $attr_value );
			}
		}
		return $nscript;
	}

	/**
	 * Replaces images within a srcset attribute with their .webp derivatives.
	 *
	 * @param string $srcset A valid srcset attribute from an img element.
	 * @return bool|string False if no changes were made, or the new srcset if any WebP images replaced the originals.
	 */
	function srcset_replace( $srcset ) {
		$srcset_urls = explode( ' ', $srcset );
		$found_webp  = false;
		if ( ewww_image_optimizer_iterable( $srcset_urls ) && count( $srcset_urls ) > 1 ) {
			ewwwio_debug_message( 'parsing srcset urls' );
			foreach ( $srcset_urls as $srcurl ) {
				if ( is_numeric( substr( $srcurl, 0, 1 ) ) ) {
					continue;
				}
				$trailing = ' ';
				if ( ',' === substr( $srcurl, -1 ) ) {
					$trailing = ',';
					$srcurl   = rtrim( $srcurl, ',' );
				}
				ewwwio_debug_message( "looking for $srcurl from srcset" );
				if ( $this->validate_image_url( $srcurl ) ) {
					$srcset = str_replace( $srcurl . $trailing, $this->generate_url( $srcurl ) . $trailing, $srcset );
					ewwwio_debug_message( "replaced $srcurl in srcset" );
					$found_webp = true;
				}
			}
		} elseif ( $this->validate_image_url( $srcset ) ) {
			return $this->generate_url( $srcset );
		}
		if ( $found_webp ) {
			return $srcset;
		} else {
			return false;
		}
	}

	/**
	 * Replaces images with the Jetpack data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be assigned the jetpack data attributes.
	 * @return string The modified noscript tag.
	 */
	function jetpack_replace( $image, $nscript ) {
		$data_orig_file = $this->get_attribute( $image, 'data-orig-file' );
		if ( $data_orig_file ) {
			ewwwio_debug_message( "looking for data-orig-file: $data_orig_file" );
			if ( $this->validate_image_url( $data_orig_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-orig-file', $this->generate_url( $data_orig_file ) );
				ewwwio_debug_message( "replacing $data_orig_file in data-orig-file" );
			}
			$this->set_attribute( $nscript, 'data-orig-file', $data_orig_file );
		}
		$data_medium_file = $this->get_attribute( $image, 'data-medium-file' );
		if ( $data_medium_file ) {
			ewwwio_debug_message( "looking for data-medium-file: $data_medium_file" );
			if ( $this->validate_image_url( $data_medium_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-medium-file', $this->generate_url( $data_medium_file ) );
				ewwwio_debug_message( "replacing $data_medium_file in data-medium-file" );
			}
			$this->set_attribute( $nscript, 'data-medium-file', $data_medium_file );
		}
		$data_large_file = $this->get_attribute( $image, 'data-large-file' );
		if ( $data_large_file ) {
			ewwwio_debug_message( "looking for data-large-file: $data_large_file" );
			if ( $this->validate_image_url( $data_large_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-large-file', $this->generate_url( $data_large_file ) );
				ewwwio_debug_message( "replacing $data_large_file in data-large-file" );
			}
			$this->set_attribute( $nscript, 'data-large-file', $data_large_file );
		}
		return $nscript;
	}

	/**
	 * Replaces images with the WooCommerce data attributes with their .webp derivatives.
	 *
	 * @param string $image The full text of the img element.
	 * @param string $nscript A noscript element that will be assigned the WooCommerce data attributes.
	 * @return string The modified noscript tag.
	 */
	function woocommerce_replace( $image, $nscript ) {
		$data_large_image = $this->get_attribute( $image, 'data-large_image' );
		if ( $data_large_image ) {
			ewwwio_debug_message( "looking for data-large_image: $data_large_image" );
			if ( $this->validate_image_url( $data_large_image ) ) {
				$this->set_attribute( $nscript, 'data-webp-large_image', $this->generate_url( $data_large_image ) );
				ewwwio_debug_message( "replacing $data_large_image in data-large_image" );
			}
			$this->set_attribute( $nscript, 'data-large_image', $data_large_image );
		}
		$data_src = $this->get_attribute( $image, 'data-src' );
		if ( $data_src ) {
			ewwwio_debug_message( "looking for data-src: $data_src" );
			if ( $this->validate_image_url( $data_src ) ) {
				$this->set_attribute( $nscript, 'data-webp-src', $this->generate_url( $data_src ) );
				ewwwio_debug_message( "replacing $data_src in data-src" );
			}
			$this->set_attribute( $nscript, 'data-src', $data_src );
		}
		return $nscript;
	}

	/**
	 * Search for img elements and rewrite them with noscript elements for WebP replacement.
	 *
	 * Any img elements or elements that may be used in place of img elements by JS are checked to see
	 * if WebP derivatives exist. The element is then wrapped within a noscript element for fallback,
	 * and noscript element receives a copy of the attributes from the img along with webp replacement
	 * values for those attributes.
	 *
	 * @param string $buffer The full HTML page generated since the output buffer was started.
	 * @return string The altered buffer containing the full page with WebP images inserted.
	 */
	function filter_page_output( $buffer ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// If this is an admin page, don't filter it.
		if ( ( empty( $buffer ) || is_admin() ) ) {
			return $buffer;
		}
		// If Cache Enabler's WebP option is enabled, don't filter it.
		if ( ewww_image_optimizer_ce_webp_enabled() ) {
			return $buffer;
		}
		$uri = $_SERVER['REQUEST_URI'];
		// Based on the uri, if this is a cornerstone editing page, don't filter the response.
		if ( ! empty( $_GET['cornerstone'] ) || strpos( $uri, 'cornerstone-endpoint' ) !== false ) {
			return $buffer;
		}
		if ( ! empty( $_GET['et_fb'] ) ) {
			return $buffer;
		}
		if ( ! empty( $_GET['tatsu'] ) ) {
			return $buffer;
		}
		if ( ! empty( $_POST['action'] ) && 'tatsu_get_concepts' === $_POST['action'] ) {
			return $buffer;
		}
		// If this is XML (not XHTML), don't modify the page.
		if ( preg_match( '/<\?xml/', $buffer ) ) {
			return $buffer;
		}
		if ( strpos( $buffer, 'amp-boilerplate' ) ) {
			ewwwio_debug_message( 'AMP page processing' );
			return $buffer;
		}

		// TODO: Eventually route through a custom parsing routine for noscript images.
		$noscript_images = $this->get_noscript_images_from_html( $buffer );
		if ( ! empty( $noscript_images ) && isset( $noscript_images[0] ) ) {
			foreach ( $noscript_images[0] as $nindex => $noscript_image ) {
				if ( strpos( $noscript_image, 'facebook.com/tr?' ) ) {
					unset( $noscript_images[0][ $nindex ] );
				}
			}
			if ( empty( $noscript_images[0] ) ) {
				$noscript_images = false;
			} else {
				ewwwio_debug_message( 'noscript-encased images found, will not process any img elements' );
			}
		}
		/* TODO: detect non-utf8 encoding and convert the buffer (if necessary). */

		$images = $this->get_images_from_html( $buffer, false );
		if ( empty( $noscript_images ) && ewww_image_optimizer_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				$file = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				// If a CDN path match was found, or .webp image existsence is confirmed, and this is not a lazy-load 'dummy' image.
				if ( $this->validate_image_url( $file ) ) {
					ewwwio_debug_message( 'found a webp image or forced path' );
					$nscript = '<noscript>';
					$this->set_attribute( $nscript, 'data-img', $file );
					$this->set_attribute( $nscript, 'data-webp', $this->generate_url( $file ) );
					$srcset = $this->get_attribute( $image, 'srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $nscript, 'data-srcset-webp', $srcset_webp );
						}
						$this->set_attribute( $nscript, 'data-srcset-img', $srcset );
					}
					if ( $this->get_attribute( $image, 'data-orig-file' ) && $this->get_attribute( $image, 'data-medium-file' ) && $this->get_attribute( $image, 'data-large-file' ) ) {
						$nscript = $this->jetpack_replace( $image, $nscript );
					}
					if ( $this->get_attribute( $image, 'data-large_image' ) && $this->get_attribute( $image, 'data-src' ) ) {
						$nscript = $this->woocommerce_replace( $image, $nscript );
					}
					$nscript = $this->attr_copy( $image, $nscript );
					$this->set_attribute( $nscript, 'class', 'ewww_webp' );
					$buffer = str_replace( $image, $nscript . $image . '</noscript>', $buffer );
				}
				// NOTE: lazy loads are shutoff for now, since they don't work consistently
				// WP Retina 2x lazy loads.
				if ( false && empty( $file ) && $image->getAttribute( 'data-srcset' ) && strpos( $image->getAttribute( 'class' ), 'lazyload' ) ) {
					$srcset      = $image->getAttribute( 'data-srcset' );
					$srcset_webp = $this->srcset_replace( $srcset );
					if ( $srcset_webp ) {
						$nimage = $html->createElement( 'img' );
						$nimage->setAttribute( 'data-srcset-webp', $srcset_webp );
						$nimage->setAttribute( 'data-srcset-img', $srcset );
						ewww_image_optimizer_webp_attr_copy( $image, $nimage, '' );
						$nimage->setAttribute( 'class', $image->getAttribute( 'class' ) . ' ewww_webp_lazy_retina' );
						$image->parentNode->replaceChild( $nimage, $image );
					}
				}
				// Hueman theme lazy-loads.
				if ( false && ! empty( $file ) && strpos( $file, 'image/gif;base64,R0lGOD' ) && $image->getAttribute( 'data-src' ) && $image->getAttribute( 'data-srcset' ) ) {
					$dummy = $file;
					$file  = $image->getAttribute( 'data-src' );
					ewwwio_debug_message( "checking webp for hueman data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for Hueman lazyload: $filepath" );
						$nscript = $html->createElement( 'noscript' );
						$nscript->setAttribute( 'data-src', $dummy );
						$nscript->setAttribute( 'data-img', $file );
						$nscript->setAttribute( 'data-webp-src', $file . '.webp' );
						$image->setAttribute( 'src', $file );
						if ( $image->getAttribute( 'data-srcset' ) ) {
							$srcset      = $image->getAttribute( 'data-srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$nscript->setAttribute( 'data-srcset-webp', $srcset_webp );
							}
							$nscript->setAttribute( 'data-srcset-img', $srcset );
						}
						ewww_image_optimizer_webp_attr_copy( $image, $nscript );
						$nscript->setAttribute( 'class', 'ewww_webp_lazy_hueman' );
						$image->parentNode->replaceChild( $nscript, $image );
						$nscript->appendChild( $image );
					}
				}
				// Lazy Load plugin (and hopefully Cherry variant) and BJ Lazy Load.
				if ( false && ! empty( $file ) && ( strpos( $file, 'image/gif;base64,R0lGOD' ) || strpos( $file, 'lazy-load/images/1x1' ) ) && $image->getAttribute( 'data-lazy-src' ) && ! empty( $image->nextSibling ) && 'noscript' == $image->nextSibling->nodeName ) {
					$dummy  = $file;
					$nimage = $html->createElement( 'img' );
					$nimage->setAttribute( 'src', $dummy );
					$file = $image->getAttribute( 'data-lazy-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-lazy-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $filepath" );
						$nimage->setAttribute( 'data-lazy-img-src', $file );
						$nimage->setAttribute( 'data-lazy-webp-src', $file . '.webp' );
						if ( $image->getAttribute( 'srcset' ) ) {
							$srcset      = $image->getAttribute( 'srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$nimage->setAttribute( 'data-srcset-webp', $srcset_webp );
							}
							$nimage->setAttribute( 'data-srcset', $srcset );
						}
						if ( $image->getAttribute( 'data-lazy-srcset' ) ) {
							$srcset      = $image->getAttribute( 'data-lazy-srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$nimage->setAttribute( 'data-lazy-srcset-webp', $srcset_webp );
							}
							$nimage->setAttribute( 'data-lazy-srcset-img', $srcset );
						}
						ewww_image_optimizer_webp_attr_copy( $image, $nimage, '' );
						$nimage->setAttribute( 'class', $image->getAttribute( 'class' ) . ' ewww_webp_lazy_load' );
						$image->parentNode->replaceChild( $nimage, $image );
					}
				} // End if().
				// Rev Slider data-lazyload attribute on image elements.
				if ( $this->get_attribute( $image, 'data-lazyload' ) ) {
					$lazyload = $this->get_attribute( $image, 'data-lazyload' );
					if ( $lazyload ) {
						if ( $this->validate_image_url( $lazyload ) ) {
							$this->set_attribute( $image, 'data-webp-lazyload', $this->generate_url( $lazyload ) );
							ewwwio_debug_message( "replacing with webp for data-lazyload: $lazyload" );
							$buffer = str_replace( $images[0][ $index ], $image, $buffer );
						}
					}
				}
			} // End foreach().
		} // End if().
		// Images listed as picture/source elements. Mostly for NextGEN, but should work anywhere.
		$pictures = $this->get_picture_tags_from_html( $buffer );
		if ( ewww_image_optimizer_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				$sources = $this->get_elements_from_html( $picture, 'source' );
				if ( ewww_image_optimizer_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						ewwwio_debug_message( "parsing a picture source: $source" );
						$srcset = $this->get_attribute( $source, 'srcset' );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$source_webp = str_replace( $srcset, $srcset_webp, $source );
								$this->set_attribute( $source_webp, 'type', 'image/webp' );
								$picture = str_replace( $source, $source_webp . $source, $picture );
							}
						}
					}
					if ( $picture != $pictures[ $index ] ) {
						ewwwio_debug_message( 'found webp for picture element' );
						$buffer = str_replace( $pictures[ $index ], $picture, $buffer );
					}
				}
			}
		}
		// NextGEN slides listed as 'a' elements.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				ewwwio_debug_message( "parsing a link $link" );
				$file  = $this->get_attribute( $link, 'data-src' );
				$thumb = $this->get_attribute( $link, 'data-thumbnail' );
				if ( $file && $thumb ) {
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					if ( $this->validate_image_url( $file ) ) {
						$this->set_attribute( $link, 'data-webp', $this->generate_url( $file ) );
						ewwwio_debug_message( "found webp for ngg data-src: $file" );
					}
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $link, 'data-webp-thumbnail', $this->generate_url( $thumb ) );
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumb" );
					}
				}
				if ( $link != $links[ $index ] ) {
					$buffer = str_replace( $links[ $index ], $link, $buffer );
				}
			}
		}
		// Revolution Slider 'li' elements.
		$listitems = $this->get_elements_from_html( $buffer, 'li' );
		if ( ewww_image_optimizer_iterable( $listitems ) ) {
			foreach ( $listitems as $index => $listitem ) {
				ewwwio_debug_message( 'parsing a listitem' );
				if ( $this->get_attribute( $listitem, 'data-title' ) === 'Slide' && ( $this->get_attribute( $listitem, 'data-lazyload' ) || $this->get_attribute( $listitem, 'data-thumb' ) ) ) {
					$thumb = $this->get_attribute( $listitem, 'data-thumb' );
					ewwwio_debug_message( "checking webp for revslider data-thumb: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $listitem, 'data-webp-thumb', $this->generate_url( $thumb ) );
						ewwwio_debug_message( "found webp for revslider data-thumb: $thumb" );
					}
					$param_num = 1;
					while ( $param_num < 11 ) {
						$parameter = $this->get_attribute( $listitem, 'data-param' . $param_num );
						if ( $parameter ) {
							ewwwio_debug_message( "checking webp for revslider data-param$param_num: $parameter" );
							if ( strpos( $parameter, 'http' ) === 0 ) {
								ewwwio_debug_message( "looking for $parameter" );
								if ( $this->validate_image_url( $parameter ) ) {
									$this->set_attribute( $listitem, 'data-webp-param' . $param_num, $this->generate_url( $parameter ) );
									ewwwio_debug_message( "found webp for data-param$param_num: $parameter" );
								}
							}
						}
						$param_num++;
					}
					if ( $listitem != $listitems[ $index ] ) {
						$buffer = str_replace( $listitems[ $index ], $listitem, $buffer );
					}
				}
			} // End foreach().
		} // End if().
		// WooCommerce thumbs listed as 'div' elements.
		$divs = $this->get_elements_from_html( $buffer, 'div' );
		if ( ewww_image_optimizer_iterable( $divs ) ) {
			foreach ( $divs as $index => $div ) {
				ewwwio_debug_message( 'parsing a div' );
				$thumb     = $this->get_attribute( $div, 'data-thumb' );
				$div_class = $this->get_attribute( $div, 'class' );
				if ( $div_class && $thumb && strpos( $div_class, 'woocommerce-product-gallery__image' ) !== false ) {
					ewwwio_debug_message( "checking webp for WC data-thumb: $thumb" );
					if ( $this->validate_image_url( $thumb ) ) {
						$this->set_attribute( $div, 'data-webp-thumb', $this->generate_url( $thumb ) );
						ewwwio_debug_message( "found webp for WC data-thumb: $thumb" );
						$buffer = str_replace( $divs[ $index ], $div, $buffer );
					}
				}
			}
		}
		// Video elements, looking for poster attributes that are images.
		$videos = $this->get_elements_from_html( $buffer, 'video' );
		if ( ewww_image_optimizer_iterable( $videos ) ) {
			foreach ( $videos as $index => $video ) {
				ewwwio_debug_message( 'parsing a video element' );
				$file = $this->get_attribute( $video, 'poster' );
				if ( $file ) {
					ewwwio_debug_message( "checking webp for video poster: $file" );
					if ( $this->validate_image_url( $file ) ) {
						$this->set_attribute( $video, 'data-poster-webp', $this->generate_url( $file ) );
						$this->set_attribute( $video, 'data-poster-image', $file );
						$this->remove_attribute( $video, 'poster' );
						ewwwio_debug_message( "found webp for video poster: $file" );
						$buffer = str_replace( $videos[ $index ], $video, $buffer );
					}
				}
			}
		}
		ewwwio_debug_message( 'all done parsing page for alt webp' );
		if ( true ) { // Set to true for extra logging.
			ewww_image_optimizer_debug_log();
		}
		return $buffer;
	}

	/**
	 * Handle image urls within the NextGEN pro lightbox displays.
	 *
	 * @param array $images An array of NextGEN images and associate attributes.
	 * @return array The array of images with WebP versions added.
	 */
	function ngg_pro_lightbox_images_queue( $images ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( ewww_image_optimizer_iterable( $images ) ) {
			foreach ( $images as $index => $image ) {
				if ( ! empty( $image['image'] ) && $this->validate_image_url( $image['image'] ) ) {
					$images[ $index ]['image-webp'] = $this->generate_url( $image['image'] );
				}
				if ( ! empty( $image['thumb'] ) && $this->validate_image_url( $image['thumb'] ) ) {
					$images[ $index ]['thumb-webp'] = $this->generate_url( $image['thumb'] );
				}
				if ( ! empty( $image['full_image'] ) && $this->validate_image_url( $image['full_image'] ) ) {
					$images[ $index ]['full_image_webp'] = $this->generate_url( $image['full_image'] );
				}
				if ( ewww_image_optimizer_iterable( $image['srcsets'] ) ) {
					foreach ( $image['srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset );
						}
					}
				}
				if ( ewww_image_optimizer_iterable( $image['full_srcsets'] ) ) {
					foreach ( $image['full_srcsets'] as $size => $srcset ) {
						if ( $this->validate_image_url( $srcset ) ) {
							$images[ $index ]['full_srcsets'][ $size . '-webp' ] = $this->generate_url( $srcset );
						}
					}
				}
			}
		}
		return $images;
	}

	/**
	 * Checks if the path is a valid "forced" WebP image.
	 *
	 * @param string $image The image file.
	 * @return bool True if the file matches a forced path, false otherwise.
	 */
	function validate_image_url( $image ) {
		ewwwio_debug_message( "webp validation for $image" );
		if ( strpos( $image, 'assets/images/dummy.png' ) || strpos( $image, 'base64,R0lGOD' ) || strpos( $image, 'lazy-load/images/1x1' ) || strpos( $image, 'assets/images/transparent.png' ) ) {
			ewwwio_debug_message( 'lazy load placeholder' );
			return false;
		}
		if ( $this->parsing_exactdn && false !== strpos( $image, $this->exactdn_domain ) ) {
			ewwwio_debug_message( 'exactdn image' );
			return true;
		}
		if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) && $this->webp_paths ) {
			// Check the image for configured CDN paths.
			foreach ( $this->webp_paths as $webp_path ) {
				if ( strpos( $image, $webp_path ) !== false ) {
					ewwwio_debug_message( 'forced cdn image' );
					return true;
				}
			}
		}
		if ( 0 === strpos( $image, $this->relative_home_url ) ) {
			$imagepath = str_replace( $this->relative_home_url, ABSPATH, $image );
		} elseif ( 0 === strpos( $image, $this->home_url ) ) {
			$imagepath = str_replace( $this->home_url, ABSPATH, $image );
		} else {
			ewwwio_debug_message( 'not a valid local image' );
			return false;
		}
		if ( is_file( $imagepath . '.webp' ) ) {
			ewwwio_debug_message( 'local .webp image found' );
			return true;
		}
		return false;
	}

	/**
	 * Generate a WebP url.
	 *
	 * Adds .webp to the end, or adds a webp parameter for ExactDN urls.
	 *
	 * @param string $url The image url.
	 * @return string The WebP version of the image url.
	 */
	function generate_url( $url ) {
		if ( $this->parsing_exactdn && false !== strpos( $url, $this->exactdn_domain ) ) {
			return add_query_arg( 'webp', 1, $url );
		} else {
			return $url . '.webp';
		}
		return $url;
	}

	/**
	 * Load full webp script when SCRIPT_DEBUG is enabled.
	 */
	function debug_script() {
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified webp script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	function min_external_script() {
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.min.js', EWWW_IMAGE_OPTIMIZER_PLUGIN_FILE ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Enqueue script dependency for alt webp rewriting when running inline.
	 */
	function load_jquery() {
		if ( ! wp_script_is( 'jquery', 'done' ) ) {
			wp_enqueue_script( 'jquery' );
		}
		ewwwio_debug_message( 'loading webp script with wp_add_inline_script' );
		wp_add_inline_script( 'jquery-core', $this->inline_script );
	}

	/**
	 * Load minified inline version of webp script (from jscompress.com).
	 */
	function inline_script() {
		ewwwio_debug_message( 'loading webp script without wp_add_inline_script' );
		echo "<script>$this->inline_script</script>";
	}
}

global $ewwwio_alt_webp;
$ewwwio_alt_webp = new EWWWIO_Alt_Webp();
