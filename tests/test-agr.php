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
	 * The API key used for API-based tests.
	 *
	 * @var string $api_key
	 */
	public static $api_key = '';

	/**
	 * Downloads test images.
	 */
	public static function set_up_before_class() {
		self::$test_gif = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/rain.gif' );
		self::$api_key  = getenv( 'EWWWIO_API_KEY' );

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
	function test_agr_gd_active() {
		$this->assertNotEmpty( \ewwwio()->gd_support() );
	}

	/**
	 * Test local (gifsicle) AGR.
	 */
	function test_agr_local() {
		$upload_gif = self::$test_gif . '.gif';
		copy( self::$test_gif, $upload_gif );
		$id = self::factory()->attachment->create_upload_object( $upload_gif );
		$meta = wp_get_attachment_metadata( $id );
		$file_path = ewww_image_optimizer_attachment_path( $meta, $id );
		$thumb_path = trailingslashit( dirname( $file_path ) ) . wp_basename( $meta['sizes']['thumbnail']['file'] );
		$this->assertTrue( ewww_image_optimizer_is_animated( $thumb_path ) );

		\wp_delete_file( $upload_gif );
	}

	/**
	 * Test API-based AGR.
	 */
	function test_agr_api() {
		if ( empty( self::$api_key ) ) {
			self::markTestSkipped( 'No API key available.' );
		}
		$upload_gif = self::$test_gif . '.gif';
		copy( self::$test_gif, $upload_gif );
		update_option( 'ewww_image_optimizer_cloud_key', self::$api_key );
		update_site_option( 'ewww_image_optimizer_cloud_key', self::$api_key );
		$id = self::factory()->attachment->create_upload_object( $upload_gif );
		$meta = wp_get_attachment_metadata( $id );
		$file_path = ewww_image_optimizer_attachment_path( $meta, $id );
		$thumb_path = trailingslashit( dirname( $file_path ) ) . wp_basename( $meta['sizes']['thumbnail']['file'] );
		$this->assertTrue( ewww_image_optimizer_is_animated( $thumb_path ) );

		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		\wp_delete_file( $upload_gif );
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
		if ( ewwwio()->is_file( self::$test_gif ) ) {
			\wp_delete_file( self::$test_gif );
		}
		ewww_image_optimizer_remove_binaries();
	}
}
