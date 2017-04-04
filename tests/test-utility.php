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
		$this->assertEquals( 'Reduced by 90.0% (90 B)', ewww_image_optimizer_image_results( 100, 10 ) );
	}
}
