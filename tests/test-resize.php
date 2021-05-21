<?php
/**
 * Class EWWWIO_Resize_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Resizing test cases.
 */
class EWWWIO_Resize_Tests extends WP_UnitTestCase {

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
		self::$test_jpg = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/20170314_174658.jpg' );
		copy( self::$test_jpg, self::$test_jpg . '.jpg' );
		self::$test_jpg .= '.jpg';
		ewww_image_optimizer_set_defaults();
		update_option( 'ewww_image_optimizer_jpg_level', '10' );
		ewww_image_optimizer_install_tools();
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
	 * Creates a JPG attachment while resizing is enabled (no cropping) using jpegtran.
	 */
	function test_scale_jpg_local() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherwidth', 1024 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherheight', 1024 );
		$id = $this->factory->attachment->create_upload_object( self::$test_jpg );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		list( $width, $height ) = wp_getimagesize( $file_path );
		$this->assertEquals( 576, $width );
		$this->assertEquals( 1024, $height );
	}

	/**
	 * Creates a JPG attachment while resizing is enabled (crop-mode) using jpegtran.
	 */
	function test_crop_jpg_local() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherwidth', 1024 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherheight', 1024 );
		add_filter( 'ewww_image_optimizer_crop_image', '__return_true' );
		$id = $this->factory->attachment->create_upload_object( self::$test_jpg );
		remove_filter( 'ewww_image_optimizer_crop_image', '__return_true' );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		list( $width, $height ) = wp_getimagesize( $file_path );
		$this->assertEquals( 1024, $width );
		$this->assertEquals( 1024, $height );
	}

	/**
	 * Creates a JPG attachment while resizing is enabled (no cropping) using API.
	 */
	function test_scale_jpg_cloud() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 20 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherwidth', 1024 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherheight', 1024 );
		$id = $this->factory->attachment->create_upload_object( self::$test_jpg );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		list( $width, $height ) = wp_getimagesize( $file_path );
		$this->assertEquals( 576, $width );
		$this->assertEquals( 1024, $height );
	}

	/**
	 * Creates a JPG attachment while resizing is enabled (crop-mode) using API.
	 */
	function test_crop_jpg_cloud() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 20 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherwidth', 1024 );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_maxotherheight', 1024 );
		add_filter( 'ewww_image_optimizer_crop_image', '__return_true' );
		$id = $this->factory->attachment->create_upload_object( self::$test_jpg );
		remove_filter( 'ewww_image_optimizer_crop_image', '__return_true' );
		$meta = wp_get_attachment_metadata( $id );
		list( $file_path, $upload_path ) = ewww_image_optimizer_attachment_path( $meta, $id );
		list( $width, $height ) = wp_getimagesize( $file_path );
		$this->assertEquals( 1024, $width );
		$this->assertEquals( 1024, $height );
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
	}
}
