<?php
/**
 * Class SampleTest
 *
 * @package Ewww_Image_Optimizer
 */

/**
 * Sample test case.
 */
class EWWWIO_Utility_Tests extends WP_UnitTestCase {


	function test_byte_format() {
		$this->assertEquals( 'Reduced by 90.0% (90 B)', ewww_image_optimizer_image_results( 100, 10 ) );
	}
}
