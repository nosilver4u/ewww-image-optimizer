<?php
/**
 * Class EWWWIO_Convert_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Conversion test cases.
 */
class EWWWIO_Convert_Tests extends WP_UnitTestCase {

	/**
	 * The location of the test JPG image.
	 *
	 * @var string $test_jpg
	 */
	public static $test_jpg = '';

	/**
	 * The location of the test PNG image.
	 *
	 * @var string $test_png
	 */
	public static $test_png = '';

	/**
	 * The location of the test GIF image.
	 *
	 * @var string $test_gif
	 */
	public static $test_gif = '';

	/**
	 * Downloads test images.
	 */
	public static function setUpBeforeClass() {
		self::$test_jpg = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/DCClogo.jpg' );
		self::$test_png = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/common-loon.png' );
		self::$test_gif = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/xhtml11.gif' );
		ewww_image_optimizer_set_defaults();
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_option( 'ewww_image_optimizer_png_level', 40 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_png_level', 40 );
		ewww_image_optimizer_install_tools();
		ewww_image_optimizer_install_pngout();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
	}

	/**
	 * Initializes the plugin and installs the ewwwio_images table.
	 */
	function setUp() {
		parent::setUp();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		ewww_image_optimizer_install_table();
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
	}

	/**
	 * Copies the test JPG to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_jpg( $original = false ) {
		if ( ! $original ) {
			$original = self::$test_jpg;
		}
		$_REQUEST['ewww_force'] = 1;
		$filename = $original . ".jpg";
		copy( $original, $filename );
		$results = ewww_image_optimizer( $filename, 1, false, false, true );
		return $results;
	}

	/**
	 * Copies the test PNG to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_png( $original = false ) {
		if ( ! $original ) {
			$original = self::$test_png;
		}
		$_REQUEST['ewww_force'] = 1;
		$filename = $original . ".png";
		copy( $original, $filename );
		$results = ewww_image_optimizer( $filename, 1, false, false, true );
		return $results;
	}

	/**
	 * Copies the test GIF to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_gif( $original = false ) {
		if ( ! $original ) {
			$original = self::$test_gif;
		}
		$_REQUEST['ewww_force'] = 1;
		$filename = $original . ".gif";
		copy( $original, $filename );
		$results = ewww_image_optimizer( $filename, 1, false, false, true );
		return $results;
	}

	/**
	 * Test JPG to PNG conversion.
	 */
	function test_convert_jpg_to_png() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_jpg_to_png', true );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_jpg_to_png', true );

		$results = $this->optimize_jpg();
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_jpg_level', 20 );
		$results = $this->optimize_jpg();
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		update_option( 'ewww_image_optimizer_jpg_to_png', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_jpg_to_png', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
	}

	/**
	 * Test PNG to JPG conversion.
	 */
	function test_convert_png_to_jpg() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_png_to_jpg', true );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', true );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_png_to_jpg', true );
		$results = $this->optimize_png();
		update_option( 'ewww_image_optimizer_png_to_jpg', '' );
		update_site_option( 'ewww_image_optimizer_png_to_jpg', '' );
		$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );
	}

	/**
	 * Test PNG to JPG conversion with alpha.
	 */
	function test_convert_png_to_jpg_alpha() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_png_to_jpg', true );
		update_option( 'ewww_image_optimizer_jpg_background', '' );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', true );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_png_to_jpg', true );
		update_site_option( 'ewww_image_optimizer_jpg_background', '' );

		// No background, conversion will fail.
		$test_png = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/books.png' );
		$results = $this->optimize_png( $test_png );
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		// Set background, conversion will succeed.
		update_option( 'ewww_image_optimizer_jpg_background', 'ffffff' );
		update_site_option( 'ewww_image_optimizer_jpg_background', 'ffffff' );
		$results = $this->optimize_png( $test_png );
		$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		// No background, conversion will fail, using API.
		update_option( 'ewww_image_optimizer_png_level', 20 );
		update_option( 'ewww_image_optimizer_jpg_background', '' );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_png_level', 20 );
		update_site_option( 'ewww_image_optimizer_jpg_background', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_png( $test_png );
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		// Set background, conversion will succeed, using API.
		update_option( 'ewww_image_optimizer_jpg_background', 'ffffff' );
		update_site_option( 'ewww_image_optimizer_jpg_background', 'ffffff' );
		$results = $this->optimize_png( $test_png );
		$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		update_option( 'ewww_image_optimizer_png_to_jpg', '' );
		update_option( 'ewww_image_optimizer_jpg_background', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_png_to_jpg', '' );
		update_site_option( 'ewww_image_optimizer_jpg_background', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		unlink( $test_png );
	}

	/**
	 * Tests autoconvert by uploading a PNG attachment.
	 */
	function test_autoconvert_png_local() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );

		$upload_png = self::$test_png . '.png';
		copy( self::$test_png, $upload_png );
		$id = $this->factory->attachment->create_upload_object( $upload_png );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $file_path, 'i' ) );

		$test_png = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/books.png' );
		$upload_png = $test_png . '.png';
		copy( $test_png, $upload_png );
		$id = $this->factory->attachment->create_upload_object( $upload_png );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $file_path, 'i' ) );

		unlink( $test_png );
	}

	/**
	 * Tests attachment conversion by uploading a PNG attachment and then converting it.
	 */
	function test_convert_png_attachment() {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 0 );
		define( 'EWWW_IMAGE_OPTIMIZER_DISABLE_AUTOCONVERT', true );

		$upload_png = self::$test_png . '.png';
		copy( self::$test_png, $upload_png );
		$id = $this->factory->attachment->create_upload_object( $upload_png );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $file_path, 'i' ) );

		global $ewww_new_image;
		$ewww_new_image = true;
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_level', 10 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_to_jpg', true );
		$meta = ewww_image_optimizer_resize_from_meta_data( $meta, $id, false, true );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $file_path, 'i' ) );

		$base_dir = trailingslashit( dirname( $file_path ) );
		foreach ( $meta['sizes'] as $size => $data ) {
			$image_path = $base_dir . $data['file'];
			$this->assertEquals( 'image/jpeg', ewww_image_optimizer_mimetype( $image_path, 'i' ) );
		}

		ewww_image_optimizer_set_option( 'ewww_image_optimizer_png_to_jpg', '' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_disable_autoconvert', '' );
	}

	/**
	 * Test GIF to PNG conversion.
	 */
	function test_convert_gif_to_png() {
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_gif_to_png', true );
		update_site_option( 'ewww_image_optimizer_gif_level', 10 );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_gif_to_png', true );

		$results = $this->optimize_gif();
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_gif();
		$this->assertEquals( 'image/png', ewww_image_optimizer_mimetype( $results[0], 'i' ) );
		unlink( $results[0] );

		update_option( 'ewww_image_optimizer_gif_to_png', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_gif_to_png', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
	}

	/**
	 * Cleans up ewwwio_images table.
	 */
	function tearDown() {
		global $wpdb;
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		$wpdb->query( "DROP TABLE IF EXISTS $wpdb->ewwwio_images" );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		delete_option( 'ewww_image_optimizer_version' );
		delete_option( 'ewww_image_optimizer_cloud_key' );
		delete_site_option( 'ewww_image_optimizer_version' );
		delete_site_option( 'ewww_image_optimizer_cloud_key' );
		parent::tearDown();
	}

	/**
	 * Cleans up the temp images.
	 */
	public static function tearDownAfterClass() {
		if ( ewwwio_is_file( self::$test_jpg ) ) {
			unlink( self::$test_jpg );
		}
		if ( ewwwio_is_file( self::$test_png ) ) {
			unlink( self::$test_png );
		}
		if ( ewwwio_is_file( self::$test_gif ) ) {
			unlink( self::$test_gif );
		}
		ewww_image_optimizer_remove_binaries();
	}
}
