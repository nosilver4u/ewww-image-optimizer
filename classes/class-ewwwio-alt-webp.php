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
	 * The Alt WebP inline script contents. Current length 11772.
	 *
	 * @access private
	 * @var string $inline_script
	 */
	private $inline_script = 'var Arrive=function(w,e,c){"use strict";if(w.MutationObserver&&"undefined"!=typeof HTMLElement){var a,t,r=0,d=(a=HTMLElement.prototype.matches||HTMLElement.prototype.webkitMatchesSelector||HTMLElement.prototype.mozMatchesSelector||HTMLElement.prototype.msMatchesSelector,{matchesSelector:function(e,t){return e instanceof HTMLElement&&a.call(e,t)},addMethod:function(e,t,a){var r=e[t];e[t]=function(){return a.length==arguments.length?a.apply(this,arguments):"function"==typeof r?r.apply(this,arguments):void 0}},callCallbacks:function(e,t){t&&t.options.onceOnly&&1==t.firedElems.length&&(e=[e[0]]);for(var a,r=0;a=e[r];r++)a&&a.callback&&a.callback.call(a.elem,a.elem);t&&t.options.onceOnly&&1==t.firedElems.length&&t.me.unbindEventWithSelectorAndCallback.call(t.target,t.selector,t.callback)},checkChildNodesRecursively:function(e,t,a,r){for(var i,n=0;i=e[n];n++)a(i,t,r)&&r.push({callback:t.callback,elem:i}),0<i.childNodes.length&&d.checkChildNodesRecursively(i.childNodes,t,a,r)},mergeArrays:function(e,t){var a,r={};for(a in e)e.hasOwnProperty(a)&&(r[a]=e[a]);for(a in t)t.hasOwnProperty(a)&&(r[a]=t[a]);return r},toElementsArray:function(e){return void 0===e||"number"==typeof e.length&&e!==w||(e=[e]),e}}),u=((t=function(){this._eventsBucket=[],this._beforeAdding=null,this._beforeRemoving=null}).prototype.addEvent=function(e,t,a,r){var i={target:e,selector:t,options:a,callback:r,firedElems:[]};return this._beforeAdding&&this._beforeAdding(i),this._eventsBucket.push(i),i},t.prototype.removeEvent=function(e){for(var t,a=this._eventsBucket.length-1;t=this._eventsBucket[a];a--)if(e(t)){this._beforeRemoving&&this._beforeRemoving(t);var r=this._eventsBucket.splice(a,1);r&&r.length&&(r[0].callback=null)}},t.prototype.beforeAdding=function(e){this._beforeAdding=e},t.prototype.beforeRemoving=function(e){this._beforeRemoving=e},t),l=function(i,n){var l=new u,s=this,o={fireOnAttributesModification:!1};return l.beforeAdding(function(t){var e,a=t.target;a!==w.document&&a!==w||(a=document.getElementsByTagName("html")[0]),e=new MutationObserver(function(e){n.call(this,e,t)});var r=i(t.options);e.observe(a,r),t.observer=e,t.me=s}),l.beforeRemoving(function(e){e.observer.disconnect()}),this.bindEvent=function(e,t,a){t=d.mergeArrays(o,t);for(var r=d.toElementsArray(this),i=0;i<r.length;i++)l.addEvent(r[i],e,t,a)},this.unbindEvent=function(){var a=d.toElementsArray(this);l.removeEvent(function(e){for(var t=0;t<a.length;t++)if(this===c||e.target===a[t])return!0;return!1})},this.unbindEventWithSelectorOrCallback=function(a){var e,r=d.toElementsArray(this),i=a;e="function"==typeof a?function(e){for(var t=0;t<r.length;t++)if((this===c||e.target===r[t])&&e.callback===i)return!0;return!1}:function(e){for(var t=0;t<r.length;t++)if((this===c||e.target===r[t])&&e.selector===a)return!0;return!1},l.removeEvent(e)},this.unbindEventWithSelectorAndCallback=function(a,r){var i=d.toElementsArray(this);l.removeEvent(function(e){for(var t=0;t<i.length;t++)if((this===c||e.target===i[t])&&e.selector===a&&e.callback===r)return!0;return!1})},this},i=new function(){var o={fireOnAttributesModification:!1,onceOnly:!1,existing:!1};function n(e,t,a){return!(!d.matchesSelector(e,t.selector)||(e._id===c&&(e._id=r++),-1!=t.firedElems.indexOf(e._id))||(t.firedElems.push(e._id),0))}var w=(i=new l(function(e){var t={attributes:!1,childList:!0,subtree:!0};return e.fireOnAttributesModification&&(t.attributes=!0),t},function(e,i){e.forEach(function(e){var t=e.addedNodes,a=e.target,r=[];null!==t&&0<t.length?d.checkChildNodesRecursively(t,i,n,r):"attributes"===e.type&&n(a,i)&&r.push({callback:i.callback,elem:a}),d.callCallbacks(r,i)})})).bindEvent;return i.bindEvent=function(e,t,a){void 0===a?(a=t,t=o):t=d.mergeArrays(o,t);var r=d.toElementsArray(this);if(t.existing){for(var i=[],n=0;n<r.length;n++)for(var l=r[n].querySelectorAll(e),s=0;s<l.length;s++)i.push({callback:a,elem:l[s]});if(t.onceOnly&&i.length)return a.call(i[0].elem,i[0].elem);setTimeout(d.callCallbacks,1,i)}w.call(this,e,t,a)},i},s=new function(){var r={};function i(e,t){return d.matchesSelector(e,t.selector)}var n=(s=new l(function(){return{childList:!0,subtree:!0}},function(e,r){e.forEach(function(e){var t=e.removedNodes,a=[];null!==t&&0<t.length&&d.checkChildNodesRecursively(t,r,i,a),d.callCallbacks(a,r)})})).bindEvent;return s.bindEvent=function(e,t,a){void 0===a?(a=t,t=r):t=d.mergeArrays(r,t),n.call(this,e,t,a)},s};e&&h(e.fn),h(HTMLElement.prototype),h(NodeList.prototype),h(HTMLCollection.prototype),h(HTMLDocument.prototype),h(Window.prototype);var n={};return o(i,n,"unbindAllArrive"),o(s,n,"unbindAllLeave"),n}function o(e,t,a){d.addMethod(t,a,e.unbindEvent),d.addMethod(t,a,e.unbindEventWithSelectorOrCallback),d.addMethod(t,a,e.unbindEventWithSelectorAndCallback)}function h(e){e.arrive=i.bindEvent,o(i,e,"unbindArrive"),e.leave=s.bindEvent,o(s,e,"unbindLeave")}}(window,"undefined"==typeof jQuery?null:jQuery,void 0);function check_webp_feature(e,t){var a=new Image;a.onload=function(){ewww_webp_supported=0<a.width&&0<a.height,t(ewww_webp_supported)},a.onerror=function(){t(ewww_webp_supported=!1)},a.src="data:image/webp;base64,"+{alpha:"UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",animation:"UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"}[e]}function ewww_load_images(t){!function(n){n.fn.extend({ewwwattr:function(e,t){return void 0!==t&&!1!==t&&this.attr(e,t),this}});t&&(n(".batch-image img, .image-wrapper a, .ngg-pro-masonry-item a, .ngg-galleria-offscreen-seo-wrapper a").each(function(){n(this).ewwwattr("data-src",n(this).attr("data-webp")),n(this).ewwwattr("data-thumbnail",n(this).attr("data-webp-thumbnail"))}),n(".image-wrapper a, .ngg-pro-masonry-item a").each(function(){n(this).ewwwattr("href",n(this).attr("data-webp"))}),n(".rev_slider ul li").each(function(){n(this).ewwwattr("data-thumb",n(this).attr("data-webp-thumb"));for(var e=1;e<11;)n(this).ewwwattr("data-param"+e,n(this).attr("data-webp-param"+e)),e++}),n(".rev_slider img").each(function(){n(this).ewwwattr("data-lazyload",n(this).attr("data-webp-lazyload"))}),n("div.woocommerce-product-gallery__image").each(function(){n(this).ewwwattr("data-thumb",n(this).attr("data-webp-thumb"))})),n("video").each(function(){t?n(this).ewwwattr("poster",n(this).attr("data-poster-webp")):n(this).ewwwattr("poster",n(this).attr("data-poster-image"))}),n("img.ewww_webp_lazy_load").each(function(){if(t){n(this).ewwwattr("data-lazy-srcset",n(this).attr("data-lazy-srcset-webp")),n(this).ewwwattr("data-srcset",n(this).attr("data-srcset-webp")),n(this).ewwwattr("data-lazy-src",n(this).attr("data-lazy-src-webp")),n(this).ewwwattr("data-src",n(this).attr("data-src-webp")),n(this).ewwwattr("data-orig-file",n(this).attr("data-webp-orig-file")),n(this).ewwwattr("data-medium-file",n(this).attr("data-webp-medium-file")),n(this).ewwwattr("data-large-file",n(this).attr("data-webp-large-file"));var e=n(this).attr("srcset");void 0!==e&&!1!==e&&e.includes("R0lGOD")&&n(this).ewwwattr("src",n(this).attr("data-lazy-src-webp"))}n(this).removeClass("ewww_webp_lazy_load")}),n(".ewww_webp").each(function(){var e=document.createElement("img");t?(n(e).ewwwattr("src",n(this).attr("data-webp")),n(e).ewwwattr("srcset",n(this).attr("data-srcset-webp")),n(e).ewwwattr("data-orig-file",n(this).attr("data-orig-file")),n(e).ewwwattr("data-orig-file",n(this).attr("data-webp-orig-file")),n(e).ewwwattr("data-medium-file",n(this).attr("data-medium-file")),n(e).ewwwattr("data-medium-file",n(this).attr("data-webp-medium-file")),n(e).ewwwattr("data-large-file",n(this).attr("data-large-file")),n(e).ewwwattr("data-large-file",n(this).attr("data-webp-large-file")),n(e).ewwwattr("data-large_image",n(this).attr("data-large_image")),n(e).ewwwattr("data-large_image",n(this).attr("data-webp-large_image")),n(e).ewwwattr("data-src",n(this).attr("data-src")),n(e).ewwwattr("data-src",n(this).attr("data-webp-src"))):(n(e).ewwwattr("src",n(this).attr("data-img")),n(e).ewwwattr("srcset",n(this).attr("data-srcset-img")),n(e).ewwwattr("data-orig-file",n(this).attr("data-orig-file")),n(e).ewwwattr("data-medium-file",n(this).attr("data-medium-file")),n(e).ewwwattr("data-large-file",n(this).attr("data-large-file")),n(e).ewwwattr("data-large_image",n(this).attr("data-large_image")),n(e).ewwwattr("data-src",n(this).attr("data-src"))),e=function(e,t){for(var a=["align","alt","border","crossorigin","height","hspace","ismap","longdesc","usemap","vspace","width","accesskey","class","contenteditable","contextmenu","dir","draggable","dropzone","hidden","id","lang","spellcheck","style","tabindex","title","translate","sizes","data-caption","data-attachment-id","data-permalink","data-orig-size","data-comments-opened","data-image-meta","data-image-title","data-image-description","data-event-trigger","data-highlight-color","data-highlight-opacity","data-highlight-border-color","data-highlight-border-width","data-highlight-border-opacity","data-no-lazy","data-lazy","data-large_image_width","data-large_image_height"],r=0,i=a.length;r<i;r++)n(t).ewwwattr(a[r],n(e).attr("data-"+a[r]));return t}(this,e),n(this).after(e),n(this).removeClass("ewww_webp")})}(jQuery),jQuery.fn.isotope&&jQuery.fn.imagesLoaded&&(jQuery(".fusion-posts-container-infinite").imagesLoaded(function(){jQuery(".fusion-posts-container-infinite").hasClass("isotope")&&jQuery(".fusion-posts-container-infinite").isotope()}),jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").imagesLoaded(function(){jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").isotope()}))}var ewww_webp_supported=!1,ewww_jquery_waiting_timer=0;function ewww_ngg_plus_parse_galleries(e){e&&jQuery.each(galleries,function(e,t){galleries[e].images_list=ewww_ngg_plus_parse_image_list(t.images_list)})}function ewww_ngg_plus_load_galleries(e){var i;e&&((i=jQuery)(window).on("ngg.galleria.themeadded",function(e,t){window.ngg_galleria._create_backup=window.ngg_galleria.create,window.ngg_galleria.create=function(e,t){var a=i(e).data("id");return galleries["gallery_"+a].images_list=ewww_ngg_plus_parse_image_list(galleries["gallery_"+a].images_list),window.ngg_galleria._create_backup(e,t)}}),i(window).on("override_nplModal_methods",function(e,r){r._set_events_backup=r.set_events,r.set_events=function(){return i("#npl_content").bind("npl_images_ready",function(e,t){var a=r.fetch_images.gallery_image_cache[t];a=ewww_ngg_plus_parse_image_list(a)}),r._set_events_backup()}}))}function ewww_ngg_plus_parse_image_list(i){var e;return(e=jQuery).each(i,function(a,r){void 0!==r["image-webp"]&&(i[a].image=r["image-webp"],delete i[a]["image-webp"]),void 0!==r["thumb-webp"]&&(i[a].thumb=r["thumb-webp"],delete i[a]["thumb-webp"]),void 0!==r.full_image_webp&&(i[a].full_image=r.full_image_webp,delete i[a].full_image_webp),void 0!==r.srcsets&&e.each(r.srcsets,function(e,t){void 0!==r.srcsets[e+"-webp"]&&(i[a].srcsets[e]=r.srcsets[e+"-webp"],delete i[a].srcsets[e+"-webp"])}),void 0!==r.full_srcsets&&e.each(r.full_srcsets,function(e,t){void 0!==r.full_srcsets[e+"-webp"]&&(i[a].full_srcsets[e]=r.full_srcsets[e+"-webp"],delete i[a].full_srcsets[e+"-webp"])})}),i}ewww_jquery_waiting=setInterval(function(){if(window.jQuery){check_webp_feature("alpha",ewww_load_images),check_webp_feature("alpha",ewww_ngg_plus_load_galleries),document.arrive(".ewww_webp",function(){ewww_load_images(ewww_webp_supported)});var e=0,t=setInterval(function(){"undefined"!=typeof galleries&&(check_webp_feature("alpha",ewww_ngg_plus_parse_galleries),clearInterval(t)),1e3<(e+=25)&&clearInterval(t)},25);clearInterval(ewww_jquery_waiting)}1e4<(ewww_jquery_waiting_timer+=100)&&clearInterval(ewww_jquery_waiting)},100);';

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
			$this->set_attribute( $nscript, 'data-orig-file', $data_orig_file, true );
		}
		$data_medium_file = $this->get_attribute( $image, 'data-medium-file' );
		if ( $data_medium_file ) {
			ewwwio_debug_message( "looking for data-medium-file: $data_medium_file" );
			if ( $this->validate_image_url( $data_medium_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-medium-file', $this->generate_url( $data_medium_file ) );
				ewwwio_debug_message( "replacing $data_medium_file in data-medium-file" );
			}
			$this->set_attribute( $nscript, 'data-medium-file', $data_medium_file, true );
		}
		$data_large_file = $this->get_attribute( $image, 'data-large-file' );
		if ( $data_large_file ) {
			ewwwio_debug_message( "looking for data-large-file: $data_large_file" );
			if ( $this->validate_image_url( $data_large_file ) ) {
				$this->set_attribute( $nscript, 'data-webp-large-file', $this->generate_url( $data_large_file ) );
				ewwwio_debug_message( "replacing $data_large_file in data-large-file" );
			}
			$this->set_attribute( $nscript, 'data-large-file', $data_large_file, true );
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

		/* TODO: detect non-utf8 encoding and convert the buffer (if necessary). */

		$images = $this->get_images_from_html( preg_replace( '/<noscript.*?\/noscript>/', '', $buffer ), false );
		if ( ewww_image_optimizer_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				$file = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				if ( strpos( $image, 'jetpack-lazy-image' ) && $this->validate_image_url( $file ) ) {
					$new_image = $image;
					$new_image = $this->jetpack_replace( $image, $new_image );
					$real_file = $this->get_attribute( $new_image, 'data-lazy-src' );
					ewwwio_debug_message( 'checking webp for Jetpack Lazy Load data-lazy-src' );
					if ( $real_file && $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $real_file" );
						$this->set_attribute( $new_image, 'data-lazy-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-lazy-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-lazy-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				} elseif ( $this->validate_image_url( $file ) && false === strpos( $image, 'lazyload' ) ) {
					// If a CDN path match was found, or .webp image existsence is confirmed, and this is not a lazy-load 'dummy' image.
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
					ewwwio_debug_message( "going to swap\n$image\nwith\n$nscript" . $image . '</noscript>' );
					$buffer = str_replace( $image, $nscript . $image . '</noscript>', $buffer );
				} elseif ( ! empty( $file ) && strpos( $image, 'data-lazy-src=' ) ) {
					// BJ Lazy Load & WP Rocket.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-lazy-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-lazy-src: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( "found webp for Lazy Load: $real_file" );
						$this->set_attribute( $new_image, 'data-lazy-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-lazy-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-lazy-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				} elseif ( ! empty( $file ) && strpos( $image, 'data-src=' ) && strpos( $image, 'data-lazy-type="image' ) ) {
					// a3 Lazy Load.
					$new_image = $image;
					$real_file = $this->get_attribute( $new_image, 'data-src' );
					ewwwio_debug_message( "checking webp for Lazy Load data-src: $real_file" );
					if ( $this->validate_image_url( $real_file ) ) {
						ewwwio_debug_message( 'found webp for Lazy Load' );
						$this->set_attribute( $new_image, 'data-src-webp', $this->generate_url( $real_file ) );
					}
					$srcset = $this->get_attribute( $new_image, 'data-srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset );
						if ( $srcset_webp ) {
							$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
						}
					}
					if ( $new_image !== $image ) {
						$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
						$buffer = str_replace( $image, $new_image, $buffer );
					}
				}
				// Rev Slider data-lazyload attribute on image elements.
				if ( $this->get_attribute( $image, 'data-lazyload' ) ) {
					$new_image = $image;
					$lazyload  = $this->get_attribute( $new_image, 'data-lazyload' );
					if ( $lazyload ) {
						if ( $this->validate_image_url( $lazyload ) ) {
							$this->set_attribute( $new_image, 'data-webp-lazyload', $this->generate_url( $lazyload ) );
							ewwwio_debug_message( "replacing with webp for data-lazyload: $lazyload" );
							$buffer = str_replace( $image, $new_image, $buffer );
						}
					}
				}
			} // End foreach().
		} // End if().
		// Look for images to parse WP Retina Lazy Load.
		if ( class_exists( 'Meow_WR2X_Core' ) && strpos( $buffer, ' lazyload' ) ) {
			$images = $this->get_elements_from_html( $buffer, 'img' );
			if ( ewww_image_optimizer_iterable( $images ) ) {
				foreach ( $images as $index => $image ) {
					$file = $this->get_attribute( $image, 'src' );
					if ( ( empty( $file ) || strpos( $image, 'R0lGODlhAQABAIAAAAAAAP' ) ) && strpos( $image, ' data-srcset=' ) && strpos( $this->get_attribute( $image, 'class' ), 'lazyload' ) ) {
						$new_image = $image;
						$srcset    = $this->get_attribute( $new_image, 'data-srcset' );
						ewwwio_debug_message( 'checking webp for Retina Lazy Load data-src' );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset );
							if ( $srcset_webp ) {
								$this->set_attribute( $new_image, 'data-srcset-webp', $srcset_webp );
							}
						}
						if ( $new_image !== $image ) {
							$this->set_attribute( $new_image, 'class', $this->get_attribute( $new_image, 'class' ) . ' ewww_webp_lazy_load', true );
							$buffer = str_replace( $image, $new_image, $buffer );
						}
					}
				}
			}
		}
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
		if ( strpos( $image, 'assets/images/dummy.png' ) || strpos( $image, 'base64,R0lGOD' ) || strpos( $image, 'lazy-load/images/1x1' ) || strpos( $image, 'assets/images/transparent.png' ) || strpos( $image, 'assets/images/lazy' ) ) {
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
		$path_parts = explode( '?', $imagepath );
		if ( is_file( $path_parts[0] . '.webp' ) || is_file( $imagepath . '.webp' ) ) {
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
			$path_parts = explode( '?', $url );
			return $path_parts[0] . '.webp' . ( ! empty( $path_parts[1] ) && 'is-pending-load=1' !== $path_parts[1] ? '?' . $path_parts[1] : '' );
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
