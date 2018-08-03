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
	 * The Alt WebP inline script contents. Current length 10794.
	 *
	 * @access private
	 * @var string $inline_script
	 */
	private $inline_script = 'var Arrive=function(d,t,c){"use strict";if(d.MutationObserver&&"undefined"!=typeof HTMLElement){var a,e,i=0,h=(a=HTMLElement.prototype.matches||HTMLElement.prototype.webkitMatchesSelector||HTMLElement.prototype.mozMatchesSelector||HTMLElement.prototype.msMatchesSelector,{matchesSelector:function(t,e){return t instanceof HTMLElement&&a.call(t,e)},addMethod:function(t,e,a){var i=t[e];t[e]=function(){return a.length==arguments.length?a.apply(this,arguments):"function"==typeof i?i.apply(this,arguments):void 0}},callCallbacks:function(t){for(var e,a=0;e=t[a];a++)e.callback.call(e.elem)},checkChildNodesRecursively:function(t,e,a,i){for(var r,n=0;r=t[n];n++)a(r,e,i)&&i.push({callback:e.callback,elem:r}),0<r.childNodes.length&&h.checkChildNodesRecursively(r.childNodes,e,a,i)},mergeArrays:function(t,e){var a,i={};for(a in t)i[a]=t[a];for(a in e)i[a]=e[a];return i},toElementsArray:function(t){return void 0===t||"number"==typeof t.length&&t!==d||(t=[t]),t}}),u=((e=function(){this._eventsBucket=[],this._beforeAdding=null,this._beforeRemoving=null}).prototype.addEvent=function(t,e,a,i){var r={target:t,selector:e,options:a,callback:i,firedElems:[]};return this._beforeAdding&&this._beforeAdding(r),this._eventsBucket.push(r),r},e.prototype.removeEvent=function(t){for(var e,a=this._eventsBucket.length-1;e=this._eventsBucket[a];a--)t(e)&&(this._beforeRemoving&&this._beforeRemoving(e),this._eventsBucket.splice(a,1))},e.prototype.beforeAdding=function(t){this._beforeAdding=t},e.prototype.beforeRemoving=function(t){this._beforeRemoving=t},e),o=function(r,n){var o=new u,s=this,l={fireOnAttributesModification:!1};return o.beforeAdding(function(e){var t,a=e.target;e.selector,e.callback;a!==d.document&&a!==d||(a=document.getElementsByTagName("html")[0]),t=new MutationObserver(function(t){n.call(this,t,e)});var i=r(e.options);t.observe(a,i),e.observer=t,e.me=s}),o.beforeRemoving(function(t){t.observer.disconnect()}),this.bindEvent=function(t,e,a){e=h.mergeArrays(l,e);for(var i=h.toElementsArray(this),r=0;r<i.length;r++)o.addEvent(i[r],t,e,a)},this.unbindEvent=function(){var a=h.toElementsArray(this);o.removeEvent(function(t){for(var e=0;e<a.length;e++)if(this===c||t.target===a[e])return!0;return!1})},this.unbindEventWithSelectorOrCallback=function(a){var t,i=h.toElementsArray(this),r=a;t="function"==typeof a?function(t){for(var e=0;e<i.length;e++)if((this===c||t.target===i[e])&&t.callback===r)return!0;return!1}:function(t){for(var e=0;e<i.length;e++)if((this===c||t.target===i[e])&&t.selector===a)return!0;return!1},o.removeEvent(t)},this.unbindEventWithSelectorAndCallback=function(a,i){var r=h.toElementsArray(this);o.removeEvent(function(t){for(var e=0;e<r.length;e++)if((this===c||t.target===r[e])&&t.selector===a&&t.callback===i)return!0;return!1})},this},r=new function(){var l={fireOnAttributesModification:!1,onceOnly:!1,existing:!1};function n(t,e,a){if(h.matchesSelector(t,e.selector)&&(t._id===c&&(t._id=i++),-1==e.firedElems.indexOf(t._id))){if(e.options.onceOnly){if(0!==e.firedElems.length)return;e.me.unbindEventWithSelectorAndCallback.call(e.target,e.selector,e.callback)}e.firedElems.push(t._id),a.push({callback:e.callback,elem:t})}}var d=(r=new o(function(t){var e={attributes:!1,childList:!0,subtree:!0};return t.fireOnAttributesModification&&(e.attributes=!0),e},function(t,r){t.forEach(function(t){var e=t.addedNodes,a=t.target,i=[];null!==e&&0<e.length?h.checkChildNodesRecursively(e,r,n,i):"attributes"===t.type&&n(a,r,i)&&i.push({callback:r.callback,elem:node}),h.callCallbacks(i)})})).bindEvent;return r.bindEvent=function(t,e,a){void 0===a?(a=e,e=l):e=h.mergeArrays(l,e);var i=h.toElementsArray(this);if(e.existing){for(var r=[],n=0;n<i.length;n++)for(var o=i[n].querySelectorAll(t),s=0;s<o.length;s++)r.push({callback:a,elem:o[s]});if(e.onceOnly&&r.length)return a.call(r[0].elem);setTimeout(h.callCallbacks,1,r)}d.call(this,t,e,a)},r},s=new function(){var i={};function r(t,e){return h.matchesSelector(t,e.selector)}var n=(s=new o(function(t){return{childList:!0,subtree:!0}},function(t,i){t.forEach(function(t){var e=t.removedNodes,a=(t.target,[]);null!==e&&0<e.length&&h.checkChildNodesRecursively(e,i,r,a),h.callCallbacks(a)})})).bindEvent;return s.bindEvent=function(t,e,a){void 0===a?(a=e,e=i):e=h.mergeArrays(i,e),n.call(this,t,e,a)},s};t&&f(t.fn),f(HTMLElement.prototype),f(NodeList.prototype),f(HTMLCollection.prototype),f(HTMLDocument.prototype),f(Window.prototype);var n={};return l(r,n,"unbindAllArrive"),l(s,n,"unbindAllLeave"),n}function l(t,e,a){h.addMethod(e,a,t.unbindEvent),h.addMethod(e,a,t.unbindEventWithSelectorOrCallback),h.addMethod(e,a,t.unbindEventWithSelectorAndCallback)}function f(t){t.arrive=r.bindEvent,l(r,t,"unbindArrive"),t.leave=s.bindEvent,l(s,t,"unbindLeave")}}(window,"undefined"==typeof jQuery?null:jQuery,void 0);function check_webp_feature(t,e){var a=new Image;a.onload=function(){var t=0<a.width&&0<a.height;!0,e(t)},a.onerror=function(){e(!1)},a.src="data:image/webp;base64,"+{alpha:"UklGRkoAAABXRUJQVlA4WAoAAAAQAAAAAAAAAAAAQUxQSAwAAAARBxAR/Q9ERP8DAABWUDggGAAAABQBAJ0BKgEAAQAAAP4AAA3AAP7mtQAAAA==",animation:"UklGRlIAAABXRUJQVlA4WAoAAAASAAAAAAAAAAAAQU5JTQYAAAD/////AABBTk1GJgAAAAAAAAAAAAAAAAAAAGQAAABWUDhMDQAAAC8AAAAQBxAREYiI/gcA"}[t]}function ewww_load_images(i){jQuery(document).arrive(".ewww_webp",function(){ewww_load_images(i)}),function(o){function a(t,e){for(var a=["align","alt","border","crossorigin","height","hspace","ismap","longdesc","usemap","vspace","width","accesskey","class","contenteditable","contextmenu","dir","draggable","dropzone","hidden","id","lang","spellcheck","style","tabindex","title","translate","sizes","data-caption","data-attachment-id","data-permalink","data-orig-size","data-comments-opened","data-image-meta","data-image-title","data-image-description","data-event-trigger","data-highlight-color","data-highlight-opacity","data-highlight-border-color","data-highlight-border-width","data-highlight-border-opacity","data-no-lazy","data-lazy","data-large_image_width","data-large_image_height"],i=0,r=a.length;i<r;i++){var n=o(t).attr("data-"+a[i]);void 0!==n&&!1!==n&&o(e).attr(a[i],n)}return e}i&&(o(".batch-image img, .image-wrapper a, .ngg-pro-masonry-item a, .ngg-galleria-offscreen-seo-wrapper a").each(function(){var t;void 0!==(t=o(this).attr("data-webp"))&&!1!==t&&o(this).attr("data-src",t),void 0!==(t=o(this).attr("data-webp-thumbnail"))&&!1!==t&&o(this).attr("data-thumbnail",t)}),o(".image-wrapper a, .ngg-pro-masonry-item a").each(function(){var t=o(this).attr("data-webp");void 0!==t&&!1!==t&&o(this).attr("href",t)}),o(".rev_slider ul li").each(function(){void 0!==(e=o(this).attr("data-webp-thumb"))&&!1!==e&&o(this).attr("data-thumb",e);for(var t=1;t<11;){var e;void 0!==(e=o(this).attr("data-webp-param"+t))&&!1!==e&&o(this).attr("data-param"+t,e),t++}}),o(".rev_slider img").each(function(){var t=o(this).attr("data-webp-lazyload");void 0!==t&&!1!==t&&o(this).attr("data-lazyload",t)}),o("div.woocommerce-product-gallery__image").each(function(){var t=o(this).attr("data-webp-thumb");void 0!==t&&!1!==t&&o(this).attr("data-thumb",t)})),o("img.ewww_webp_lazy_retina").each(function(){var t;i?void 0!==(t=o(this).attr("data-srcset-webp"))&&!1!==t&&o(this).attr("data-srcset",t):void 0!==(t=o(this).attr("data-srcset-img"))&&!1!==t&&o(this).attr("data-srcset",t);o(this).removeClass("ewww_webp_lazy_retina")}),o("video").each(function(){var t;i?void 0!==(t=o(this).attr("data-poster-webp"))&&!1!==t&&o(this).attr("poster",t):void 0!==(t=o(this).attr("data-poster-image"))&&!1!==t&&o(this).attr("poster",t)}),o("img.ewww_webp_lazy_load").each(function(){var t;i?(o(this).attr("data-lazy-src",o(this).attr("data-lazy-webp-src")),void 0!==(t=o(this).attr("data-srcset-webp"))&&!1!==t&&o(this).attr("srcset",t),void 0!==(t=o(this).attr("data-lazy-srcset-webp"))&&!1!==t&&o(this).attr("data-lazy-srcset",t)):(o(this).attr("data-lazy-src",o(this).attr("data-lazy-img-src")),void 0!==(t=o(this).attr("data-srcset"))&&!1!==t&&o(this).attr("srcset",t),void 0!==(t=o(this).attr("data-lazy-srcset-img"))&&!1!==t&&o(ewww_img).attr("data-lazy-srcset",t));o(this).removeClass("ewww_webp_lazy_load")}),o(".ewww_webp_lazy_hueman").each(function(){var t,e=document.createElement("img");(o(e).attr("src",o(this).attr("data-src")),i)?(o(e).attr("data-src",o(this).attr("data-webp-src")),void 0!==(t=o(this).attr("data-srcset-webp"))&&!1!==t&&o(e).attr("data-srcset",t)):(o(e).attr("data-src",o(this).attr("data-img")),void 0!==(t=o(this).attr("data-srcset-img"))&&!1!==t&&o(e).attr("data-srcset",t));e=a(this,e),o(this).after(e),o(this).removeClass("ewww_webp_lazy_hueman")}),o(".ewww_webp").each(function(){var t=document.createElement("img");if(i){if(o(t).attr("src",o(this).attr("data-webp")),void 0!==(e=o(this).attr("data-srcset-webp"))&&!1!==e&&o(t).attr("srcset",e),void 0!==(e=o(this).attr("data-webp-orig-file"))&&!1!==e)o(t).attr("data-orig-file",e);else void 0!==(e=o(this).attr("data-orig-file"))&&!1!==e&&o(t).attr("data-orig-file",e);if(void 0!==(e=o(this).attr("data-webp-medium-file"))&&!1!==e)o(t).attr("data-medium-file",e);else void 0!==(e=o(this).attr("data-medium-file"))&&!1!==e&&o(t).attr("data-medium-file",e);if(void 0!==(e=o(this).attr("data-webp-large-file"))&&!1!==e)o(t).attr("data-large-file",e);else void 0!==(e=o(this).attr("data-large-file"))&&!1!==e&&o(t).attr("data-large-file",e);if(void 0!==(e=o(this).attr("data-webp-large_image"))&&!1!==e)o(t).attr("data-large_image",e);else void 0!==(e=o(this).attr("data-large_image"))&&!1!==e&&o(t).attr("data-large_image",e);if(void 0!==(e=o(this).attr("data-webp-src"))&&!1!==e)o(t).attr("data-src",e);else void 0!==(e=o(this).attr("data-src"))&&!1!==e&&o(t).attr("data-src",e)}else{var e;o(t).attr("src",o(this).attr("data-img")),void 0!==(e=o(this).attr("data-srcset-img"))&&!1!==e&&o(t).attr("srcset",e),void 0!==(e=o(this).attr("data-orig-file"))&&!1!==e&&o(t).attr("data-orig-file",e),void 0!==(e=o(this).attr("data-medium-file"))&&!1!==e&&o(t).attr("data-medium-file",e),void 0!==(e=o(this).attr("data-large-file"))&&!1!==e&&o(t).attr("data-large-file",e),void 0!==(e=o(this).attr("data-large_image"))&&!1!==e&&o(t).attr("data-large_image",e),void 0!==(e=o(this).attr("data-src"))&&!1!==e&&o(t).attr("data-src",e)}t=a(this,t),o(this).after(t),o(this).removeClass("ewww_webp")})}(jQuery),jQuery.fn.isotope&&jQuery.fn.imagesLoaded&&(jQuery(".fusion-posts-container-infinite").imagesLoaded(function(){jQuery(".fusion-posts-container-infinite").hasClass("isotope")&&jQuery(".fusion-posts-container-infinite").isotope()}),jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").imagesLoaded(function(){jQuery(".fusion-portfolio:not(.fusion-recent-works) .fusion-portfolio-wrapper").isotope()}))}"undefined"!=typeof jQuery&&check_webp_feature("alpha",ewww_load_images);';


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
				$trailing = ' ';
				if ( ',' === substr( $srcurl, -1 ) ) {
					$trailing = ',';
					$srcurl   = rtrim( $srcurl, ',' );
				}
				$srcfilepath = ABSPATH . str_replace( $home_url, '', $srcurl );
				ewwwio_debug_message( "looking for srcurl on disk: $srcfilepath" );
				if ( $this->parsing_exactdn || $valid_path || is_file( $srcfilepath . '.webp' ) ) {
					if ( $this->parsing_exactdn ) {
						if ( false !== strpos( $srcurl, $this->exactdn_domain ) ) {
							$srcset = str_replace( $srcurl . $trailing, add_query_arg( 'webp', 1, $srcurl ) . $trailing, $srcset );
						}
					} else {
						$srcset = str_replace( $srcurl . $trailing, $srcurl . '.webp' . $trailing, $srcset );
					}
					$found_webp = true;
					/* ewwwio_debug_message( "replacing $srcurl in $srcset" ); */
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
			if ( $this->parsing_exactdn || $valid_path || is_file( $origfilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn ) {
					if ( false !== strpos( $data_orig_file, $this->exactdn_domain ) ) {
						$this->set_attribute( $nscript, 'data-webp-orig-file', add_query_arg( 'webp', 1, $data_orig_file ) );
					}
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
			if ( $this->parsing_exactdn || $valid_path || is_file( $mediumfilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn ) {
					if ( false !== strpos( $data_medium_file, $this->exactdn_domain ) ) {
						$this->set_attribute( $nscript, 'data-webp-medium-file', add_query_arg( 'webp', 1, $data_medium_file ) );
					}
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
			if ( $this->parsing_exactdn || $valid_path || is_file( $largefilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn ) {
					if ( false !== strpos( $data_large_file, $this->exactdn_domain ) ) {
						$this->set_attribute( $nscript, 'data-webp-large-file', add_query_arg( 'webp', 1, $data_large_file ) );
					}
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
			if ( $this->parsing_exactdn || $valid_path || is_file( $largefilepath . '.webp' ) ) {
				if ( $this->parsing_exactdn ) {
					if ( false !== strpos( $data_large_image, $this->exactdn_domain ) ) {
						$this->set_attribute( $nscript, 'data-webp-large_image', add_query_arg( 'webp', 1, $data_large_image ) );
					}
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
			if ( $this->parsing_exactdn || $valid_path || is_file( $srcpath . '.webp' ) ) {
				if ( $this->parsing_exactdn ) {
					if ( false !== strpos( $data_src, $this->exactdn_domain ) ) {
						$this->set_attribute( $nscript, 'data-webp-src', add_query_arg( 'webp', 1, $data_src ) );
					}
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
			$this->exactdn_domain = $exactdn->get_exactdn_domain();
			$home_url_parts       = $exactdn->parse_url( $home_url );
			if ( ! empty( $home_url_parts['host'] ) && $this->exactdn_domain ) {
				$home_url = str_replace( $home_url_parts['host'], $this->exactdn_domain, $home_url );
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
				if ( strpos( $file, $home_relative_url ) === 0 ) {
					$filepath = str_replace( $home_relative_url, ABSPATH, $file );
				} else {
					$filepath = str_replace( $home_url, ABSPATH, $file );
				}
				ewwwio_debug_message( "the image is at $filepath" );
				// If a CDN path match was found, or .webp image existsence is confirmed, and this is not a lazy-load 'dummy' image.
				if ( ( $this->parsing_exactdn || $valid_path || is_file( $filepath . '.webp' ) ) && ! strpos( $file, 'assets/images/dummy.png' ) && ! strpos( $file, 'base64,R0lGOD' ) && ! strpos( $file, 'lazy-load/images/1x1' ) ) {
					ewwwio_debug_message( 'found a webp image or forced path' );
					$nscript = '<noscript>';
					$this->set_attribute( $nscript, 'data-img', $file );
					if ( $this->parsing_exactdn ) {
						if ( false !== strpos( $file, $this->exactdn_domain ) ) {
							$this->set_attribute( $nscript, 'data-webp', add_query_arg( 'webp', 1, $file ) );
						}
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
						if ( ! $valid_path && ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
							// Check the image for configured CDN paths.
							foreach ( $webp_paths as $webp_path ) {
								if ( strpos( $lazyload, $webp_path ) !== false ) {
									$valid_path = true;
								}
							}
						}
						if ( $this->parsing_exactdn || $valid_path || is_file( $lazyloadpath . '.webp' ) ) {
							if ( $this->parsing_exactdn ) {
								if ( false !== strpos( $lazyload, $this->exactdn_domain ) ) {
									$this->set_attribute( $image, 'data-webp-lazyload', add_query_arg( 'webp', 1, $lazyload ) );
								}
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
		// TODO: make sure webp is working for every layout in NextGEN...
		// NextGEN parsing disabled until we have a full working solution.
		// NextGEN images listed as picture/source elements.
		/* $pictures = $this->get_picture_tags_from_html( $buffer ); */
		if ( ewww_image_optimizer_iterable( $pictures ) ) {
			foreach ( $pictures as $index => $picture ) {
				$sources = $this->get_elements_from_html( $picture, 'source' );
				if ( ewww_image_optimizer_iterable( $sources ) ) {
					foreach ( $sources as $source ) {
						ewwwio_debug_message( "parsing a picture: $source" );
						if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
							// Check for CDN paths within the img src attribute.
							foreach ( $webp_paths as $webp_path ) {
								if ( strpos( $source, $webp_path ) !== false ) {
									$valid_path = true;
									ewwwio_debug_message( 'found valid forced/cdn path' );
								}
							}
						}
						$srcset = $this->get_attribute( $source, 'srcset' );
						if ( $srcset ) {
							$srcset_webp = $this->srcset_replace( $srcset, $home_url, $valid_path );
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
		/* $links = $this->get_elements_from_html( $buffer, 'a' ); */
		if ( ewww_image_optimizer_iterable( $links ) ) {
			foreach ( $links as $index => $link ) {
				ewwwio_debug_message( "parsing a link $link" );
				$file  = $this->get_attribute( $link, 'data-src' );
				$thumb = $this->get_attribute( $link, 'data-thumbnail' );
				if ( $file && $thumb ) {
					$valid_path = false;
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
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
					if ( $this->parsing_exactdn || $valid_path || is_file( $filepath . '.webp' ) ) {
						if ( $this->parsing_exactdn ) {
							if ( false !== strpos( $file, $this->exactdn_domain ) ) {
								$this->set_attribute( $link, 'data-webp', add_query_arg( 'webp', 1, $file ) );
							}
						} else {
							$this->set_attribute( $link, 'data-webp', $file . '.webp' );
						}
						ewwwio_debug_message( "found webp for ngg data-src: $filepath" );
					}
					if ( $this->parsing_exactdn || $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn ) {
							if ( false !== strpos( $thumb, $this->exactdn_domain ) ) {
								$this->set_attribute( $link, 'data-webp-thumbnail', add_query_arg( 'webp', 1, $thumb ) );
							}
						} else {
							$this->set_attribute( $link, 'data-webp-thumbnail', $thumb . '.webp' );
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
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $thumb, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for revslider data-thumb: $thumb" );
					$thumbpath = str_replace( $home_url, ABSPATH, $thumb );
					if ( $this->parsing_exactdn || $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn ) {
							if ( false !== strpos( $thumb, $this->exactdn_domain ) ) {
								$this->set_attribute( $listitem, 'data-webp-thumb', add_query_arg( 'webp', 1, $thumb ) );
							}
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
								if ( $this->parsing_exactdn || $valid_path || is_file( $parameter_path . '.webp' ) ) {
									if ( $this->parsing_exactdn ) {
										if ( false !== strpos( $parameter, $this->exactdn_domain ) ) {
											$this->set_attribute( $listitem, 'data-webp-param' . $param_num, add_query_arg( 'webp', 1, $parameter ) );
										}
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
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $thumb, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for WC data-thumb: $thumb" );
					$thumbpath = ABSPATH . str_replace( $home_url, '', $thumb );
					if ( $this->parsing_exactdn || $valid_path || is_file( $thumbpath . '.webp' ) ) {
						if ( $this->parsing_exactdn ) {
							if ( false !== strpos( $thumb, $this->exactdn_domain ) ) {
								$this->set_attribute( $div, 'data-webp-thumb', add_query_arg( 'webp', 1, $thumb ) );
							}
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
					if ( ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_force' ) ) {
						// Check the image for configured CDN paths.
						foreach ( $webp_paths as $webp_path ) {
							if ( strpos( $file, $webp_path ) !== false ) {
								$valid_path = true;
							}
						}
					}
					ewwwio_debug_message( "checking webp for video poster: $file" );
					$filepath = ABSPATH . str_replace( $home_url, '', $file );
					if ( $this->parsing_exactdn || $valid_path || is_file( $filepath . '.webp' ) ) {
						if ( $this->parsing_exactdn ) {
							if ( $this->parsing_exactdn && false !== strpos( $file, $this->exactdn_domain ) ) {
								$this->set_attribute( $video, 'data-poster-webp', add_query_arg( 'webp', 1, $file ) );
							}
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
