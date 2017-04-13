<?php
/**
 * Class EWWWIO_Option_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

// TODO: run tests for some of the fancier sanitation stuff like folders to optimize and webp paths.
/**
 * Option test cases.
 */
class EWWWIO_Option_Tests extends WP_UnitTestCase {

	/**
	 * Test the jpg background funtion to ensure proper formatting.
	 */
	function test_jpg_background() {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', '#fff333' );
		$this->assertEquals( 'fff333', ewww_image_optimizer_jpg_background() );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', '' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', '#fff' );
		$this->assertNull( ewww_image_optimizer_jpg_background() );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', '' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', 'black' );
		$this->assertNull( ewww_image_optimizer_jpg_background() );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_background', '' );
	}

	/**
	 * Test the jpg/webp quality sanitizer.
	 */
	function test_jpg_quality() {
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', 1000 );
		$this->assertNull( ewww_image_optimizer_jpg_quality() );
		$this->assertEquals( 82, (int) apply_filters( 'jpeg_quality', 82, 'image/webp' ) );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', '' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', 75 );
		$this->assertEquals( 75, ewww_image_optimizer_jpg_quality() );
		$this->assertEquals( 75, (int) apply_filters( 'jpeg_quality', 82, 'image/webp' ) );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', '' );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', 'spinach' );
		$this->assertNull( ewww_image_optimizer_jpg_quality() );
		ewww_image_optimizer_set_option( 'ewww_image_optimizer_jpg_quality', '' );
	}
}
