<?php
/**
 * Class EWWWIO_AGR_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * AGR (animated GIF resizing) test cases.
 */
class EWWWIO_AGR_Tests extends WP_UnitTestCase {

	/**
	 * The location of the test GIF image.
	 *
	 * @var string $test_gif
	 */
	public static $test_gif = '';

	/**
	 * Downloads test images.
	 */
	public static function set_up_before_class() {
		self::$test_gif = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/rain.gif' );

		ewwwio()->set_defaults();
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		ewwwio()->local->install_tools();
	}

	/**
	 * Initializes the plugin and installs the ewwwio_images table.
	 */
	function set_up() {
		parent::set_up();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		ewww_image_optimizer_install_table();
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
	}

	/**
	 * Test that GD is active and Imagick is not -- otherwise our tests are bogus.
	 */
	function test_gd_active() {
		$this->assertNotEmpty( \ewwwio()->gd_support() );
		$this->assertFalse( \ewwwio()->imagick_support() );
	}

	/**
	 * Test local (gifsicle) AGR.
	 */
	function test_local_agr() {
		$upload_gif = self::$test_gif . '.gif';
		copy( self::$test_gif, $upload_gif );
		$id = $this->factory->attachment->create_upload_object( $upload_gif );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$thumb_path = trailingslashit( dirname( $file_path ) ) . wp_basename( $meta['sizes']['thumbnail']['file'] );
		$this->assertTrue( ewww_image_optimizer_is_animated( $thumb_path ) );

		unlink( $upload_gif );
	}

	/**
	 * Test API-based AGR.
	 */
	function test_api_agr() {
		$upload_gif = self::$test_gif . '.gif';
		copy( self::$test_gif, $upload_gif );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$id = $this->factory->attachment->create_upload_object( $upload_gif );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$thumb_path = trailingslashit( dirname( $file_path ) ) . wp_basename( $meta['sizes']['thumbnail']['file'] );
		$this->assertTrue( ewww_image_optimizer_is_animated( $thumb_path ) );

		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		unlink( $upload_gif );
	}

	/**
	 * Cleans up ewwwio_images table.
	 */
	function tear_down() {
		global $wpdb;
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->ewwwio_images" );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		delete_option( 'ewww_image_optimizer_version' );
		delete_option( 'ewww_image_optimizer_cloud_key' );
		delete_site_option( 'ewww_image_optimizer_version' );
		delete_site_option( 'ewww_image_optimizer_cloud_key' );
		parent::tear_down();
	}

	/**
	 * Cleans up the temp images.
	 */
	public static function tear_down_after_class() {
		if ( ewwwio_is_file( self::$test_gif ) ) {
			unlink( self::$test_gif );
		}
		ewww_image_optimizer_remove_binaries();
	}
}
