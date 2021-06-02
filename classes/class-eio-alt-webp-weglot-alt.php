<?php
/**
 * Implements integration with WebP rewriting and Weglot.
 *
 * @link https://ewww.io
 * @package EIO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the data-alt and data-title attribute of noscript elements to the list of DOM checkers for Weglot.
 *
 * @param array $dom_checkers Contains the list of all the classes Weglot is checking by default.
 * @return array The updated list of classes.
 */
function eio_weglot_dom_check( $dom_checkers ) {
	ewwwio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	if ( ! ewww_image_optimizer_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
		return $dom_checkers;
	}

	/**
	 * A lovely little class to send 'alt' hints to Weglot.
	 */
	class EIO_Alt_Webp_Weglot_Alt extends Weglot\Parser\Check\Dom\AbstractDomChecker {

		/**
		 * Type of tag we want Weglot to detect.
		 */
		const DOM = 'noscript';

		/**
		 * Name of the attribute in that tag we want Weglot to detect.
		 */
		const PROPERTY = 'data-alt';

		/**
		 * Do not change unless it's not text but a media URL like a .pdf file for example.
		 */
		const WORD_TYPE = Weglot\Client\Api\Enum\WordType::TEXT;
	}

	/**
	 * A lovely little class to send 'title' hints to Weglot.
	 */
	class EIO_Alt_Webp_Weglot_Title extends Weglot\Parser\Check\Dom\AbstractDomChecker {

		/**
		 * Type of tag we want Weglot to detect.
		 */
		const DOM = 'noscript';

		/**
		 * Name of the attribute in that tag we want Weglot to detect.
		 */
		const PROPERTY = 'data-title';

		/**
		 * Do not change unless it's not text but a media URL like a .pdf file for example.
		 */
		const WORD_TYPE = Weglot\Client\Api\Enum\WordType::TEXT;
	}

	ewwwio_debug_message( 'registering Weglot integration for JS WebP (data-* attrs on noscript)' );
	$dom_checkers[] = '\EIO_Alt_Webp_Weglot_Alt';
	$dom_checkers[] = '\EIO_Alt_Webp_Weglot_Title';
	return $dom_checkers;
}
// Instruct Weglot to filter data-alt tags on noscript elements.
add_filter( 'weglot_get_dom_checkers', 'eio_weglot_dom_check' );
