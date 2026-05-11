<?php
/**
 * Implements methods for working with page settings/rules.
 *
 * @package EIO
 * @link https://ewww.io
 */

namespace EWWW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utility functions for child classes.
 */
trait Page_Settings {

	/**
	 * Get the page settings by URI from the ewwwio_pages table.
	 *
	 * @param string $post_identifier The URI of the page for which to retrieve settings.
	 * @return array Page settings or empty array if not found.
	 */
	protected function get_page_settings( $post_identifier ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( ! empty( $post_identifier ) ) {
			$page_settings = $wpdb->get_row( $wpdb->prepare( "SELECT id,data FROM $wpdb->ewwwio_pages WHERE page = %s", $post_identifier ), \ARRAY_A );
			if ( ! empty( $page_settings ) && isset( $page_settings['data'] ) ) {
				$page_settings['data'] = \maybe_unserialize( $page_settings['data'] );
				return $page_settings;
			}
		}
		return array();
	}

	/**
	 * Update page settings in the ewwwio_pages table.
	 *
	 * @param array $page_settings The page settings to update, including 'id' and 'data'.
	 */
	protected function update_page_settings( $page_settings ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( ! empty( $page_settings['id'] ) && isset( $page_settings['data'] ) ) {
			$this->debug_message( "updating page settings for page ID {$page_settings['id']}" );
			$wpdb->update(
				$wpdb->ewwwio_pages,
				array( 'data' => \maybe_serialize( $page_settings['data'] ) ),
				array( 'id' => (int) $page_settings['id'] ),
				array( '%s' ),
				array( '%d' )
			);
		} elseif ( ! empty( $page_settings['data'] ) && isset( $page_settings['page'] ) ) {
			$this->debug_message( "inserting new page settings for page {$page_settings['page']}" );
			$wpdb->insert(
				$wpdb->ewwwio_pages,
				array(
					'page' => $page_settings['page'],
					'data' => \maybe_serialize( $page_settings['data'] ),
				),
				array(
					'%s',
					'%s',
				)
			);
		} else {
			$this->debug_message( 'invalid page settings data:' );
			$this->debug_message( print_r( $page_settings, true ) );
		}
		if ( $wpdb->last_error ) {
			$this->debug_message( 'Error updating page settings: ' . $wpdb->last_error );
		}
	}

	/**
	 * Remove the page settings by URI from the ewwwio_pages table.
	 *
	 * @param string $post_identifier The URI of the page for which to remove settings.
	 */
	protected function remove_per_page_settings( $post_identifier ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		if ( ! empty( $post_identifier ) ) {
			$wpdb->delete(
				$wpdb->ewwwio_pages,
				array( 'page' => $post_identifier ),
				array( '%s' )
			);
		}
	}

	/**
	 * Remove all page settings from the ewwwio_pages and postmeta tables.
	 */
	protected function remove_all_page_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		global $wpdb;
		$wpdb->query( "TRUNCATE $wpdb->ewwwio_pages" );
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => 'eio_page_settings' ),
			array( '%s' )
		);
	}
}
