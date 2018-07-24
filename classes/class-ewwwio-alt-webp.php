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
	 * The Alt WebP inline script contents. Current length 10781.
	 *
	 * @access private
	 * @var string $inline_script
	 */
	private $inline_script = 'function check_webp_feature(t,e){var a={alpha:"UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",animation:"UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"},i=!1,r=new Image;r.onload=function(){var t=r.width>0&&r.height>0;i=!0,e(t)},r.onerror=function(){i=!1,e(!1)},r.src="data:image/webp;base64,"+a[t]}function ewww_load_images(t){jQuery(document).arrive(".ewww_webp",function(){ewww_load_images(t)}),function(e){function a(t,a){for(var r=["align","alt","border","crossorigin","height","hspace","ismap","longdesc","usemap","vspace","width","accesskey","class","contenteditable","contextmenu","dir","draggable","dropzone","hidden","id","lang","spellcheck","style","tabindex","title","translate","sizes","data-caption","data-attachment-id","data-permalink","data-orig-size","data-comments-opened","data-image-meta","data-image-title","data-image-description","data-event-trigger","data-highlight-color","data-highlight-opacity","data-highlight-border-color","data-highlight-border-width","data-highlight-border-opacity","data-no-lazy","data-lazy","data-large_image_width","data-large_image_height"],n=0,o=r.length;n<o;n++){var s=e(t).attr(i+r[n]);void 0!==s&&!1!==s&&e(a).attr(r[n],s)}return a}var i="data-";t&&(e(".batch-image img, .image-wrapper a, .ngg-pro-masonry-item a").each(function(){var t=e(this).attr("data-webp");void 0!==t&&!1!==t&&e(this).attr("data-src",t),void 0!==(t=e(this).attr("data-webp-thumbnail"))&&!1!==t&&e(this).attr("data-thumbnail",t)}),e(".image-wrapper a, .ngg-pro-masonry-item a").each(function(){var t=e(this).attr("data-webp");void 0!==t&&!1!==t&&e(this).attr("href",t)}),e(".rev_slider ul li").each(function(){var t=e(this).attr("data-webp-thumb");void 0!==t&&!1!==t&&e(this).attr("data-thumb",t);for(var a=1;a<11;)void 0!==(t=e(this).attr("data-webp-param"+a))&&!1!==t&&e(this).attr("data-param"+a,t),a++}),e(".rev_slider img").each(function(){var t=e(this).attr("data-webp-lazyload");void 0!==t&&!1!==t&&e(this).attr("data-lazyload",t)}),e("div.woocommerce-product-gallery__image").each(function(){var t=e(this).attr("data-webp-thumb");void 0!==t&&!1!==t&&e(this).attr("data-thumb",t)})),e("img.ewww_webp_lazy_retina").each(function(){if(t)void 0!==(a=e(this).attr("data-srcset-webp"))&&!1!==a&&e(this).attr("data-srcset",a);else{var a=e(this).attr("data-srcset-img");void 0!==a&&!1!==a&&e(this).attr("data-srcset",a)}e(this).removeClass("ewww_webp_lazy_retina")}),e("video").each(function(){if(t)void 0!==(a=e(this).attr("data-poster-webp"))&&!1!==a&&e(this).attr("poster",a);else{var a=e(this).attr("data-poster-image");void 0!==a&&!1!==a&&e(this).attr("poster",a)}}),e("img.ewww_webp_lazy_load").each(function(){if(t)e(this).attr("data-lazy-src",e(this).attr("data-lazy-webp-src")),void 0!==(a=e(this).attr("data-srcset-webp"))&&!1!==a&&e(this).attr("srcset",a),void 0!==(a=e(this).attr("data-lazy-srcset-webp"))&&!1!==a&&e(this).attr("data-lazy-srcset",a);else{e(this).attr("data-lazy-src",e(this).attr("data-lazy-img-src"));var a=e(this).attr("data-srcset");void 0!==a&&!1!==a&&e(this).attr("srcset",a),void 0!==(a=e(this).attr("data-lazy-srcset-img"))&&!1!==a&&e(ewww_img).attr("data-lazy-srcset",a)}e(this).removeClass("ewww_webp_lazy_load")}),e(".ewww_webp_lazy_hueman").each(function(){var i=document.createElement("img");if(e(i).attr("src",e(this).attr("data-src")),t)e(i).attr("data-src",e(this).attr("data-webp-src")),void 0!==(r=e(this).attr("data-srcset-webp"))&&!1!==r&&e(i).attr("data-srcset",r);else{e(i).attr("data-src",e(this).attr("data-img"));var r=e(this).attr("data-srcset-img");void 0!==r&&!1!==r&&e(i).attr("data-srcset",r)}i=a(this,i),e(this).after(i),e(this).removeClass("ewww_webp_lazy_hueman")}),e(".ewww_webp").each(function(){var i=document.createElement("img");if(t)e(i).attr("src",e(this).attr("data-webp")),void 0!==(r=e(this).attr("data-srcset-webp"))&&!1!==r&&e(i).attr("srcset",r),void 0!==(r=e(this).attr("data-webp-orig-file"))&&!1!==r?e(i).attr("data-orig-file",r):void 0!==(r=e(this).attr("data-orig-file"))&&!1!==r&&e(i).attr("data-orig-file",r),void 0!==(r=e(this).attr("data-webp-medium-file"))&&!1!==r?e(i).attr("data-medium-file",r):void 0!==(r=e(this).attr("data-medium-file"))&&!1!==r&&e(i).attr("data-medium-file",r),void 0!==(r=e(this).attr("data-webp-large-file"))&&!1!==r?e(i).attr("data-large-file",r):void 0!==(r=e(this).attr("data-large-file"))&&!1!==r&&e(i).attr("data-large-file",r),void 0!==(r=e(this).attr("data-webp-large_image"))&&!1!==r?e(i).attr("data-large_image",r):void 0!==(r=e(this).attr("data-large_image"))&&!1!==r&&e(i).attr("data-large_image",r),void 0!==(r=e(this).attr("data-webp-src"))&&!1!==r?e(i).attr("data-src",r):void 0!==(r=e(this).attr("data-src"))&&!1!==r&&e(i).attr("data-src",r);else{e(i).attr("src",e(this).attr("data-img"));var r=e(this).attr("data-srcset-img");void 0!==r&&!1!==r&&e(i).attr("srcset",r),void 0!==(r=e(this).attr("data-orig-file"))&&!1!==r&&e(i).attr("data-orig-file",r),void 0!==(r=e(this).attr("data-medium-file"))&&!1!==r&&e(i).attr("data-medium-file",r),void 0!==(r=e(this).attr("data-large-file"))&&!1!==r&&e(i).attr("data-large-file",r),void 0!==(r=e(this).attr("data-large_image"))&&!1!==r&&e(i).attr("data-large_image",r),void 0!==(r=e(this).attr("data-src"))&&!1!==r&&e(i).attr("data-src",r)}i=a(this,i),e(this).after(i),e(this).removeClass("ewww_webp")})}(jQuery),jQuery.fn.isotope&&jQuery.fn.imagesLoaded&&(jQuery(".fusion-posts-container-infinite").imagesLoaded(function(){jQuery(".fusion-posts-container-infinite").hasClass("isotope")&&jQuery(".fusion-posts-container-infinite").isotope()}),jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").imagesLoaded(function(){jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").isotope()}))}var Arrive=function(t,e,a){"use strict";function i(t,e,a){o.addMethod(e,a,t.unbindEvent),o.addMethod(e,a,t.unbindEventWithSelectorOrCallback),o.addMethod(e,a,t.unbindEventWithSelectorAndCallback)}function r(t){t.arrive=l.bindEvent,i(l,t,"unbindArrive"),t.leave=c.bindEvent,i(c,t,"unbindLeave")}if(t.MutationObserver&&"undefined"!=typeof HTMLElement){var n=0,o=function(){var e=HTMLElement.prototype.matches||HTMLElement.prototype.webkitMatchesSelector||HTMLElement.prototype.mozMatchesSelector||HTMLElement.prototype.msMatchesSelector;return{matchesSelector:function(t,a){return t instanceof HTMLElement&&e.call(t,a)},addMethod:function(t,e,a){var i=t[e];t[e]=function(){return a.length==arguments.length?a.apply(this,arguments):"function"==typeof i?i.apply(this,arguments):void 0}},callCallbacks:function(t){for(var e,a=0;e=t[a];a++)e.callback.call(e.elem)},checkChildNodesRecursively:function(t,e,a,i){for(var r,n=0;r=t[n];n++)a(r,e,i)&&i.push({callback:e.callback,elem:r}),r.childNodes.length>0&&o.checkChildNodesRecursively(r.childNodes,e,a,i)},mergeArrays:function(t,e){var a,i={};for(a in t)i[a]=t[a];for(a in e)i[a]=e[a];return i},toElementsArray:function(e){return void 0===e||"number"==typeof e.length&&e!==t||(e=[e]),e}}}(),s=function(){var t=function(){this._eventsBucket=[],this._beforeAdding=null,this._beforeRemoving=null};return t.prototype.addEvent=function(t,e,a,i){var r={target:t,selector:e,options:a,callback:i,firedElems:[]};return this._beforeAdding&&this._beforeAdding(r),this._eventsBucket.push(r),r},t.prototype.removeEvent=function(t){for(var e,a=this._eventsBucket.length-1;e=this._eventsBucket[a];a--)t(e)&&(this._beforeRemoving&&this._beforeRemoving(e),this._eventsBucket.splice(a,1))},t.prototype.beforeAdding=function(t){this._beforeAdding=t},t.prototype.beforeRemoving=function(t){this._beforeRemoving=t},t}(),d=function(e,i){var r=new s,n=this,d={fireOnAttributesModification:!1};return r.beforeAdding(function(a){var r,o=a.target;a.selector,a.callback;o!==t.document&&o!==t||(o=document.getElementsByTagName("html")[0]),r=new MutationObserver(function(t){i.call(this,t,a)});var s=e(a.options);r.observe(o,s),a.observer=r,a.me=n}),r.beforeRemoving(function(t){t.observer.disconnect()}),this.bindEvent=function(t,e,a){e=o.mergeArrays(d,e);for(var i=o.toElementsArray(this),n=0;n<i.length;n++)r.addEvent(i[n],t,e,a)},this.unbindEvent=function(){var t=o.toElementsArray(this);r.removeEvent(function(e){for(var i=0;i<t.length;i++)if(this===a||e.target===t[i])return!0;return!1})},this.unbindEventWithSelectorOrCallback=function(t){var e,i=o.toElementsArray(this),n=t;e="function"==typeof t?function(t){for(var e=0;e<i.length;e++)if((this===a||t.target===i[e])&&t.callback===n)return!0;return!1}:function(e){for(var r=0;r<i.length;r++)if((this===a||e.target===i[r])&&e.selector===t)return!0;return!1},r.removeEvent(e)},this.unbindEventWithSelectorAndCallback=function(t,e){var i=o.toElementsArray(this);r.removeEvent(function(r){for(var n=0;n<i.length;n++)if((this===a||r.target===i[n])&&r.selector===t&&r.callback===e)return!0;return!1})},this},l=new function(){function t(t,e,i){if(o.matchesSelector(t,e.selector)&&(t._id===a&&(t._id=n++),-1==e.firedElems.indexOf(t._id))){if(e.options.onceOnly){if(0!==e.firedElems.length)return;e.me.unbindEventWithSelectorAndCallback.call(e.target,e.selector,e.callback)}e.firedElems.push(t._id),i.push({callback:e.callback,elem:t})}}var e={fireOnAttributesModification:!1,onceOnly:!1,existing:!1},i=(l=new d(function(t){var e={attributes:!1,childList:!0,subtree:!0};return t.fireOnAttributesModification&&(e.attributes=!0),e},function(e,a){e.forEach(function(e){var i=e.addedNodes,r=e.target,n=[];null!==i&&i.length>0?o.checkChildNodesRecursively(i,a,t,n):"attributes"===e.type&&t(r,a,n)&&n.push({callback:a.callback,elem:node}),o.callCallbacks(n)})})).bindEvent;return l.bindEvent=function(t,a,r){void 0===r?(r=a,a=e):a=o.mergeArrays(e,a);var n=o.toElementsArray(this);if(a.existing){for(var s=[],d=0;d<n.length;d++)for(var l=n[d].querySelectorAll(t),c=0;c<l.length;c++)s.push({callback:r,elem:l[c]});if(a.onceOnly&&s.length)return r.call(s[0].elem);setTimeout(o.callCallbacks,1,s)}i.call(this,t,a,r)},l},c=new function(){function t(t,e){return o.matchesSelector(t,e.selector)}var e={},a=(c=new d(function(t){return{childList:!0,subtree:!0}},function(e,a){e.forEach(function(e){var i=e.removedNodes,r=(e.target,[]);null!==i&&i.length>0&&o.checkChildNodesRecursively(i,a,t,r),o.callCallbacks(r)})})).bindEvent;return c.bindEvent=function(t,i,r){void 0===r?(r=i,i=e):i=o.mergeArrays(e,i),a.call(this,t,i,r)},c};e&&r(e.fn),r(HTMLElement.prototype),r(NodeList.prototype),r(HTMLCollection.prototype),r(HTMLDocument.prototype),r(Window.prototype);var h={};return i(l,h,"unbindAllArrive"),i(c,h,"unbindAllLeave"),h}}(window,"undefined"==typeof jQuery?null:jQuery,void 0);"undefined"!=typeof jQuery&&check_webp_feature("alpha",ewww_load_images);';


	/**
	 * Indicates if we are filtering ExactDN urls.
	 *
	 * @access protected
	 * @var bool $parsing_exactdn
	 */
	protected $parsing_exactdn = false;

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
	 * @param string $home_url The 'home' url for the WordPress site.
	 * @param bool   $valid_path True if a CDN path has been specified and matches the img element.
	 * @return bool|string False if no changes were made, or the new srcset if any WebP images replaced the originals.
	 */
	function srcset_replace( $srcset, $home_url, $valid_path ) {
		$srcset_urls = explode( ' ', $srcset );
		$found_webp  = false;
		if ( ewww_image_optimizer_iterable( $srcset_urls ) ) {
			ewwwio_debug_message( 'found srcset urls' );
			foreach ( $srcset_urls as $srcurl ) {
				if ( is_numeric( substr( $srcurl, 0, 1 ) ) ) {
					continue;
				}
				$srcfilepath = ABSPATH . str_replace( $home_url, '', $srcurl );
				ewwwio_debug_message( "looking for srcurl on disk: $srcfilepath" );
				if ( $valid_path || is_file( $srcfilepath . '.webp' ) ) {
					if ( $this->parsing_exactdn && $valid_path ) {
						$srcset = str_replace( $srcurl . ',', add_query_arg( 'webp', 1, $srcurl ) . ',', $srcset );
						$srcset = str_replace( $srcurl . ' ', add_query_arg( 'webp', 1, $srcurl ) . ' ', $srcset );
					} else {
						$srcset = str_replace( $srcurl, $srcurl . '.webp', $srcset );
					}
					$found_webp = true;
					ewwwio_debug_message( "replacing $srcurl in $srcset" );
				}
			}
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
	 * @param string $home_url The 'home' url for the WordPress site.
	 * @param bool   $valid_path True if a CDN path has been specified and matches the img element.
	 * @return string The modified noscript tag.
	 */
	function jetpack_replace( $image, $nscript, $home_url, $valid_path ) {
		$data_orig_file = $this->get_attribute( $image, 'data-orig-file' );
		if ( $data_orig_file ) {
			$origfilepath = ABSPATH . str_replace( $home_url, '', $data_orig_file );
			ewwwio_debug_message( "looking for data-orig-file on disk: $origfilepath" );
			if ( $valid_path || is_file( $origfilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn && $valid_path ) {
					$this->set_attribute( $nscript, 'data-webp-orig-file', add_query_arg( 'webp', 1, $data_orig_file ) );
				} else {
					$this->set_attribute( $nscript, 'data-webp-orig-file', $data_orig_file . '.webp' );
				}
				ewwwio_debug_message( "replacing $origfilepath in data-orig-file" );
			}
			$this->set_attribute( $nscript, 'data-orig-file', $data_orig_file );
		}
		$data_medium_file = $this->get_attribute( $image, 'data-medium-file' );
		if ( $data_medium_file ) {
			$mediumfilepath = ABSPATH . str_replace( $home_url, '', $data_medium_file );
			ewwwio_debug_message( "looking for data-medium-file on disk: $mediumfilepath" );
			if ( $valid_path || is_file( $mediumfilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn && $valid_path ) {
					$this->set_attribute( $nscript, 'data-webp-medium-file', add_query_arg( 'webp', 1, $data_medium_file ) );
				} else {
					$this->set_attribute( $nscript, 'data-webp-medium-file', $data_medium_file . '.webp' );
				}
				ewwwio_debug_message( "replacing $mediumfilepath in data-medium-file" );
			}
			$this->set_attribute( $nscript, 'data-medium-file', $data_medium_file );
		}
		$data_large_file = $this->get_attribute( $image, 'data-large-file' );
		if ( $data_large_file ) {
			$largefilepath = ABSPATH . str_replace( $home_url, '', $data_large_file );
			ewwwio_debug_message( "looking for data-large-file on disk: $largefilepath" );
			if ( $valid_path || is_file( $largefilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn && $valid_path ) {
					$this->set_attribute( $nscript, 'data-webp-large-file', add_query_arg( 'webp', 1, $data_large_file ) );
				} else {
					$this->set_attribute( $nscript, 'data-webp-large-file', $data_large_file . '.webp' );
				}
				ewwwio_debug_message( "replacing $largefilepath in data-large-file" );
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
	 * @param string $home_url The 'home' url for the WordPress site.
	 * @param bool   $valid_path True if a CDN path has been specified and matches the img element.
	 * @return string The modified noscript tag.
	 */
	function woocommerce_replace( $image, $nscript, $home_url, $valid_path ) {
		$data_large_image = $this->get_attribute( $image, 'data-large_image' );
		if ( $data_large_image ) {
			$largefilepath = ABSPATH . str_replace( $home_url, '', $data_large_image );
			ewwwio_debug_message( "looking for data-large_image on disk: $largefilepath" );
			if ( $valid_path || is_file( $largefilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn && $valid_path ) {
					$this->set_attribute( $nscript, 'data-webp-large_image', add_query_arg( 'webp', 1, $data_large_image ) );
				} else {
					$this->set_attribute( $nscript, 'data-webp-large_image', $data_large_image . '.webp' );
				}
				ewwwio_debug_message( "replacing $largefilepath in data-large_image" );
			}
			$this->set_attribute( $nscript, 'data-large_image', $data_large_image );
		}
		$data_src = $this->get_attribute( $image, 'data-src' );
		if ( $data_src ) {
			$srcpath = ABSPATH . str_replace( $home_url, '', $data_src );
			ewwwio_debug_message( "looking for data-src on disk: $srcpath" );
			if ( $valid_path || is_file( $srcpath . '.webp' ) ) {
				if ( $this->parsing_exactdn && $valid_path ) {
					$this->set_attribute( $nscript, 'data-webp-src', add_query_arg( 'webp', 1, $data_src ) );
				} else {
					$this->set_attribute( $nscript, 'data-webp-src', $data_src . '.webp' );
				}
				ewwwio_debug_message( "replacing $srcpath in data-src" );
			}
			$this->set_attribute( $nscript, 'data-src', $data_src );
		}
		return $nscript;
	}

	/**
	 * Search for amp-img elements and rewrite them with fallback elements for WebP replacement.
	 *
	 * Any amp-img elements will be given the fallback attribute and wrapped inside another amp-img
	 * element with a WebP src attribute if WebP derivatives exist.
	 *
	 * @param string $buffer The entire HTML page from the output buffer.
	 * @return string The altered buffer containing the full page with WebP images inserted.
	 */
	function filter_amp( $buffer ) {
		ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		// Modify buffer here, and then return the updated code.
		if ( class_exists( 'DOMDocument' ) ) {
			$html = new DOMDocument;

			$libxml_previous_error_reporting = libxml_use_internal_errors( true );

			$html->formatOutput = false;
			$html->encoding     = 'utf-8';
			if ( defined( 'LIBXML_VERSION' ) && LIBXML_VERSION < 20800 ) {
				ewwwio_debug_message( 'libxml version too old for amp: ' . LIBXML_VERSION );
				return $buffer;
			} elseif ( ! defined( 'LIBXML_VERSION' ) ) {
				// When libxml is too old, we dare not modify the buffer.
				ewwwio_debug_message( 'cannot detect libxml version for amp' );
				return $buffer;
			}
			$html->loadHTML( $buffer );
			ewwwio_debug_message( 'parsing AMP as html' );
			$html->encoding = 'utf-8';
			$home_url       = get_site_url();
			$webp_paths     = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
			if ( ! is_array( $webp_paths ) ) {
				$webp_paths = array();
			}
			$home_relative_url = preg_replace( '/https?:/', '', $home_url );
			$images            = $html->getElementsByTagName( 'amp-img' );
			if ( ewww_image_optimizer_iterable( $images ) ) {
				foreach ( $images as $image ) {
					if ( 'amp-img' == $image->parentNode->tagName ) {
						continue;
					}
					$srcset = '';
					ewwwio_debug_message( 'parsing an AMP image' );
					$file       = $image->getAttribute( 'src' );
					$valid_path = false;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check for CDN paths within the img src attribute.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( strpos( $file, $home_relative_url ) === 0 ) {
						$filepath = str_replace( $home_relative_url, ABSPATH, $file );
					} else {
						$filepath = str_replace( $home_url, ABSPATH, $file );
					}
					ewwwio_debug_message( "the AMP image is at $filepath" );
					// If a CDN path match was found, or .webp image existsence is confirmed, and this is not a lazy-load 'dummy' image.
					if ( ( $valid_path || is_file( $filepath . '.webp' ) ) && ! strpos( $file, 'assets/images/dummy.png' ) && ! strpos( $file, 'base64,R0lGOD' ) && ! strpos( $file, 'lazy-load/images/1x1' ) ) {
						$parent_image = $image->cloneNode();
						$parent_image->setAttribute( 'src', $file . '.webp' );
						if ( $image->getAttribute( 'srcset' ) ) {
							$srcset      = $image->getAttribute( 'srcset' );
							$srcset_webp = $this->srcset_replace( $srcset, $home_url, $valid_path );
							if ( $srcset_webp ) {
								$parent_image->setAttribute( 'srcset', $srcset_webp );
							}
						}
						$fallback_attr = $html->createAttribute( 'fallback' );
						$image->appendChild( $fallback_attr );
						$image->parentNode->replaceChild( $parent_image, $image );
						$parent_image->appendChild( $image );
					}
				} // End foreach().
			} // End if().
			ewwwio_debug_message( 'preparing to dump page back to $buffer' );
			$buffer = $html->saveHTML( $html->documentElement );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_previous_error_reporting );
			if ( false ) { // Set to true for extra debugging.
				ewwwio_debug_message( 'buffer after replacement' );
				ewwwio_debug_message( substr( $buffer, 0, 500 ) );
			}
		} // End if().
		if ( true ) { // Set to true for extra logging.
			ewww_image_optimizer_debug_log();
		}
		return $buffer;
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
		if ( ! empty( $noscript_images ) ) {
			ewwwio_debug_message( 'noscript-encased images found, bailing' );
			return $buffer;
		}
		// TODO: detect non-utf8 encoding and convert (if necessary).
		$home_url = get_site_url();
		ewwwio_debug_message( "home url: $home_url" );
		if ( class_exists( 'ExactDN' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_exactdn' ) ) {
			global $exactdn;
			$exactdn_domain = $exactdn->get_exactdn_domain();
			$home_url_parts = $exactdn->parse_url( $home_url );
			if ( ! empty( $home_url_parts['host'] ) && $exactdn_domain ) {
				$home_url = str_replace( $home_url_parts['host'], $exactdn_domain, $home_url );
				ewwwio_debug_message( "new home url: $home_url" );
				$this->parsing_exactdn = true;
			}
		}
		$webp_paths = ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_paths' );
		if ( ! is_array( $webp_paths ) ) {
			$webp_paths = array();
		}
		$home_url          = trailingslashit( $home_url );
		$home_relative_url = trailingslashit( preg_replace( '/https?:/', '', $home_url ) );
		$images            = $this->get_images_from_html( $buffer, false );
		if ( ewww_image_optimizer_iterable( $images[0] ) ) {
			foreach ( $images[0] as $index => $image ) {
				$srcset     = '';
				$valid_path = false;
				$file       = $images['img_url'][ $index ];
				ewwwio_debug_message( "parsing an image: $file" );
				if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
					// Check for CDN paths within the img src attribute.
					foreach ( $webp_paths as $webp_path ) {
						if ( strpos( $file, $webp_path ) !== false ) {
							$valid_path = true;
							ewwwio_debug_message( 'found valid forced/cdn path' );
						}
					}
				}
				if ( $this->parsing_exactdn && false !== strpos( $file, $exactdn_domain ) ) {
					$valid_path = true;
				} elseif ( strpos( $file, $home_relative_url ) === 0 ) {
					$filepath = str_replace( $home_relative_url, ABSPATH, $file );
				} else {
					$filepath = str_replace( $home_url, ABSPATH, $file );
				}
				ewwwio_debug_message( "the image is at $filepath" );
				// If a CDN path match was found, or .webp image existsence is confirmed, and this is not a lazy-load 'dummy' image.
				if ( ( $valid_path || is_file( $filepath . '.webp' ) ) && ! strpos( $file, 'assets/images/dummy.png' ) && ! strpos( $file, 'base64,R0lGOD' ) && ! strpos( $file, 'lazy-load/images/1x1' ) ) {
					ewwwio_debug_message( 'found a webp image or forced path' );
					$nscript = '<noscript>';
					$this->set_attribute( $nscript, 'data-img', $file );
					if ( $this->parsing_exactdn && $valid_path ) {
						$this->set_attribute( $nscript, 'data-webp', add_query_arg( 'webp', 1, $file ) );
					} else {
						$this->set_attribute( $nscript, 'data-webp', $file . '.webp' );
					}
					$srcset = $this->get_attribute( $image, 'srcset' );
					if ( $srcset ) {
						$srcset_webp = $this->srcset_replace( $srcset, $home_url, $valid_path );
						if ( $srcset_webp ) {
							$this->set_attribute( $nscript, 'data-srcset-webp', $srcset_webp );
						}
						$this->set_attribute( $nscript, 'data-srcset-img', $srcset );
					}
					if ( $this->get_attribute( $image, 'data-orig-file' ) && $this->get_attribute( $image, 'data-medium-file' ) && $this->get_attribute( $image, 'data-large-file' ) ) {
						$nscript = $this->jetpack_replace( $image, $nscript, $home_url, $valid_path );
					}
					if ( $this->get_attribute( $image, 'data-large_image' ) && $this->get_attribute( $image, 'data-src' ) ) {
						$nscript = $this->woocommerce_replace( $image, $nscript, $home_url, $valid_path );
					}
					$nscript = $this->attr_copy( $image, $nscript );
					$this->set_attribute( $nscript, 'class', 'ewww_webp' );
					$buffer = str_replace( $image, $nscript . $image . '</noscript>', $buffer );
				}
				// TODO: Do we need this nextgen bit anymore? See what Imagely rep says.
				// Look for NextGEN attributes that need to be altered.
				if ( empty( $file ) && $this->get_attribute( $image, 'data-src' ) && $this->get_attribute( $image, 'data-thumbnail' ) ) {
					$file  = $this->get_attribute( $image, 'data-src' );
					$thumb = $this->get_attribute( $image, 'data-thumbnail' );
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-src: $filepath" );
						$this->set_attribute( $image, 'data-webp', $file . '.webp' );
						$buffer = str_replace( $images['img_tag'][ $index ], $image, $buffer );
					}
					if ( $valid_path || is_file( $thumbpath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumbpath" );
						$this->set_attribute( $image, 'data-webp-thumbnail', $thumb . '.webp' );
						$buffer = str_replace( $images['img_tag'][ $index ], $image, $buffer );
					}
				}
				// NOTE: lazy loads are shutoff for now, since they don't work consistently
				// WP Retina 2x lazy loads.
				if ( false && empty( $file ) && $image->getAttribute( 'data-srcset' ) && strpos( $image->getAttribute( 'class' ), 'lazyload' ) ) {
					$srcset = $image->getAttribute( 'data-srcset' );
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $srcset, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
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
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						ewwwio_debug_message( "found webp for Hueman lazyload: $filepath" );
						$nscript = $html->createElement( 'noscript' );
						$nscript->setAttribute( 'data-src', $dummy );
						$nscript->setAttribute( 'data-img', $file );
						$nscript->setAttribute( 'data-webp-src', $file . '.webp' );
						$image->setAttribute( 'src', $file );
						if ( $image->getAttribute( 'data-srcset' ) ) {
							$srcset      = $image->getAttribute( 'data-srcset' );
							$srcset_webp = ewww_image_optimizer_webp_srcset_replace( $srcset, $home_url, $valid_path );
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
					if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
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
				// TODO: check to see if this is related to Rev Slider, and mark accordingly. Otherwise, see if we can figure out where this came from and if it is necessary.
				if ( $this->get_attribute( $image, 'data-lazyload' ) ) {
					$lazyload = $this->get_attribute( $image, 'data-lazyload' );
					if ( ! empty( $lazyload ) ) {
						$lazyloadpath = ABSPATH . str_replace( $home_url, '', $lazyload );
						if ( ! $valid_path && $this->parsing_exactdn && false !== strpos( $file, $exactdn_domain ) ) {
							$valid_path = true;
						} elseif ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
							// Check the image for configured CDN paths.
							foreach ( $webp_paths as $webp_path ) {
								if ( strpos( $lazyload, $webp_path ) !== false ) {
									$valid_path = true;
								}
							}
						}
						if ( $valid_path || is_file( $lazyloadpath . '.webp' ) ) {
							if ( $this->parsing_exactdn && $valid_path ) {
								$this->set_attribute( $image, 'data-webp-lazyload', add_query_arg( 'webp', 1, $lazyload ) );
							} else {
								$this->set_attribute( $image, 'data-webp-lazyload', $lazyload . '.webp' );
							}
							ewwwio_debug_message( "found webp for data-lazyload: $filepath" );
							$buffer = str_replace( $images[0][ $index ], $image, $buffer );
						}
					}
				}
			} // End foreach().
		} // End if().
		// TODO: need to test Slider Revolution stuff above and below.
		// TODO: NextGEN <picture> and <source> elements in Pro Horizontal Filmstrip (and possibly others).
		// NextGEN slides listed as 'a' elements.
		$links = $this->get_elements_from_html( $buffer, 'a' );
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				ewwwio_debug_message( "parsing a link $link" );
				$file  = $this->get_attribute( $link, 'data-src' );
				$thumb = $this->get_attribute( $link, 'data-thumbnail' );
				if ( $file && $thumb ) {
					$valid_path = false;
					if ( $this->parsing_exactdn && false !== strpos( $file, $exactdn_domain ) ) {
						$valid_path = true;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for ngg data-src: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					ewwwio_debug_message( "checking webp for ngg data-thumbnail: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						if ( $this->parsing_exactdn && $valid_path ) {
							$this->set_attribute( $link, 'data-webp', add_query_arg( 'webp', 1, $file ) );
						} else {
							$link->set_attribute( $link, 'data-webp', $file . '.webp' );
						}
						ewwwio_debug_message( "found webp for ngg data-src: $filepath" );
					}
					if ( $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn && $valid_path ) {
							$this->set_attribute( $link, 'data-webp-thumbnail', add_query_arg( 'webp', 1, $thumb ) );
						} else {
							$link->set_attribute( $link, 'data-webp-thumbnail', $thumb . '.webp' );
						}
						ewwwio_debug_message( "found webp for ngg data-thumbnail: $thumbpath" );
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
					$thumb      = $this->get_attribute( $listitem, 'data-thumb' );
					$valid_path = false;
					if ( $this->parsing_exactdn && false !== strpos( $thumb, $exactdn_domain ) ) {
						$valid_path = true;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $thumb, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for revslider data-thumb: $thumb" );
					$thumbpath = str_replace( $home_url, ABSPATH, $thumb );
					if ( $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn && $valid_path ) {
							$this->set_attribute( $listitem, 'data-webp-thumb', add_query_arg( 'webp', 1, $thumb ) );
						} else {
							$this->set_attribute( $listitem, 'data-webp-thumb', $thumb . '.webp' );
						}
						ewwwio_debug_message( "found webp for revslider data-thumb: $thumbpath" );
					}
					$param_num = 1;
					while ( $param_num < 11 ) {
						$parameter = $this->get_attribute( $listitem, 'data-param' . $param_num );
						if ( $parameter ) {
							ewwwio_debug_message( "checking webp for revslider data-param$param_num: $parameter" );
							if ( strpos( $parameter, 'http' ) === 0 ) {
								$parameter_path = str_replace( $home_url, ABSPATH, $parameter );
								ewwwio_debug_message( "looking for $parameter_path" );
								if ( $valid_path || is_file( $parameter_path . '.webp' ) ) {
									if ( $this->parsing_exactdn && $valid_path ) {
										$this->set_attribute( $listitem, 'data-webp-param' . $param_num, add_query_arg( 'webp', 1, $parameter ) );
									} else {
										$this->set_attribute( $listitem, 'data-webp-param' . $param_num, $parameter . '.webp' );
									}
									ewwwio_debug_message( "found webp for data-param$param_num: $parameter_path" );
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
					$valid_path = false;
					if ( $this->parsing_exactdn && false !== strpos( $thumb, $exactdn_domain ) ) {
						$valid_path = true;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $thumb, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for WC data-thumb: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn && $valid_path ) {
							$this->set_attribute( $div, 'data-webp-thumb', add_query_arg( 'webp', 1, $thumb ) );
						} else {
							$this->set_attribute( $div, 'data-webp-thumb', $thumb . '.webp' );
						}
						ewwwio_debug_message( "found webp for WC data-thumb: $thumbpath" );
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
					$valid_path = false;
					if ( $this->parsing_exactdn && false !== strpos( $file, $exactdn_domain ) ) {
						$valid_path = true;
					} elseif ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for video poster: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( $valid_path || is_file( $filepath . '.webp' ) ) {
						if ( $this->parsing_exactdn && $valid_path ) {
							$this->set_attribute( $video, 'data-poster-webp', add_query_arg( 'webp', 1, $file ) );
						} else {
							$this->set_attribute( $video, 'data-poster-webp', $file . '.webp' );
						}
						$this->set_attribute( $video, 'data-poster-image', $file );
						$this->remove_attribute( $video, 'poster' );
						ewwwio_debug_message( "found webp for video poster: $filepath" );
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
	 * Load full webp script when SCRIPT_DEBUG is enabled.
	 */
	function debug_script() {
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Load minified webp script when EWWW_IMAGE_OPTIMIZER_WEBP_EXTERNAL_SCRIPT is set.
	 */
	function min_external_script() {
		if ( ! ewww_image_optimizer_ce_webp_enabled() ) {
			wp_enqueue_script( 'ewww-webp-load-script', plugins_url( '/includes/load_webp.min.js', __FILE__ ), array( 'jquery' ), EWWW_IMAGE_OPTIMIZER_VERSION );
		}
	}

	/**
	 * Enqueue script dependency for alt webp rewriting when running inline.
	 */
	function load_jquery() {
		wp_enqueue_script( 'jquery' );
		ewwwio_debug_message( 'loading webp script with wp_add_inline_script' );
		wp_add_inline_script( 'jquery-migrate', $this->inline_script );
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
