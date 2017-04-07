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
}
