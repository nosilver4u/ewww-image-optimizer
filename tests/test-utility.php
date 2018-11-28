<?php
/**
 * Class EWWWIO_Utility_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Utility test cases.
 */
class EWWWIO_Utility_Tests extends WP_UnitTestCase {

	/**
	 * Test our image results function to ensure proper formatting.
	 */
	function test_byte_format() {
		$this->assertEquals( 'reduced by 90.0% (90 b)', strtolower( ewww_image_optimizer_image_results( 100, 10 ) ) );
		$this->assertEquals( 'reduced by 29.8% (29.2 kb)', strtolower( ewww_image_optimizer_image_results( 100235, 70384 ) ) );
		$this->assertEquals( 'reduced by 36.8% (1.1 mb)', strtolower( ewww_image_optimizer_image_results( 3202350, 2023840 ) ) );
	}

	/**
	 * Test the checksum function to be sure all our binaries are in the list.
	 */
	function test_sha256sum() {
		$binaries = scandir( EWWW_IMAGE_OPTIMIZER_BINARY_PATH );
		foreach ( $binaries as $binary ) {
			$binary = trailingslashit( EWWW_IMAGE_OPTIMIZER_BINARY_PATH ) . $binary;
			if ( ! is_file( $binary ) ) {
				continue;
			}
			$this->assertTrue( ewww_image_optimizer_md5check( $binary ) );
		}
	}

	/**
	 * Test the mimetype function to be sure all our binaries validate.
	 */
	function test_mimetype() {
		$binaries = scandir( EWWW_IMAGE_OPTIMIZER_BINARY_PATH );
		global $ewww_debug;
		$ewww_debug .= '';
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_debug', true );
		foreach ( $binaries as $binary ) {
			$binary = trailingslashit( EWWW_IMAGE_OPTIMIZER_BINARY_PATH ) . $binary;
			if ( ! is_file( $binary ) ) {
				continue;
			}
			$this->assertTrue( (bool) ewww_image_optimizer_mimetype( $binary, 'b' ), $binary . ":\n" . str_replace( '<br>', "\n", $ewww_debug ) );
		}
	}

	/**
	 * Tests that shell commands get escaped properly (replaces spaces in binary names).
	 */
	function test_shellcmdesc() {
		$this->assertEquals( ewww_image_optimizer_escapeshellcmd( 'jpeg tran' ), 'jpeg\ tran' );
	}

	/**
	 * Tests that shell args get escaped properly (quotes and such).
	 */
	function test_shellargesc() {
		$this->assertEquals( ewww_image_optimizer_escapeshellarg( "file'name" ), "'file'\"'\"'name'" );
	}

	/**
	 * Tests that GIF animation is detected properly.
	 */
	function test_animated() {
		$test_gif = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/gifsiclelogo.gif' );
		$this->assertTrue( ewww_image_optimizer_is_animated( $test_gif ) );
		unlink( $test_gif );
	}

	/**
	 * Tests that PNG transparency is detected properly.
	 */
	function test_transparency() {
		$test_png = download_url( 'https://s3-us-west-2.amazonaws.com/exactlywww/books.png' );
		$this->assertTrue( ewww_image_optimizer_png_alpha( $test_png ) );
		unlink( $test_png );
	}

	/**
	 * Test that EWWW IO plugin images are ignored using the filter function.
	 */
	function test_skipself() {
		$test_image = EWWW_IMAGE_OPTIMIZER_PLUGIN_PATH . 'images/test.png';
		$this->assertTrue( ewww_image_optimizer_ignore_self( false, $test_image ) );
	}

	/**
	 * Test relative path functions.
	 */
	function test_relative_paths() {
		if ( ! defined( 'EWWW_IMAGE_OPTIMIZER_RELATIVE' ) ) {
			define( 'EWWW_IMAGE_OPTIMIZER_RELATIVE', true );
		}
		$test_image = trailingslashit( ABSPATH ) . 'images/test.png';
		$relative_test_image_path = ewww_image_optimizer_relativize_path( $test_image );
		$this->assertEquals( 'ABSPATHimages/test.png', $relative_test_image_path );
		$replaced_test_image = ewww_image_optimizer_absolutize_path( $relative_test_image_path );
		$this->assertEquals( $test_image, $replaced_test_image );
	}
}
