<?php
/**
 * Test Plugin Versions match. Inspired by @peterwilsoncc.
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Test Plugin Readme and PHP Headers
 */
class EWWWIO_Test_Plugin_Versions extends WP_UnitTestCase {

	/**
	 * Test Stable Tag in readme.txt matches Version header in plugin file.
	 */
	public function test_stable_tag_matches_plugin_version_header() {
		$readme_file = __DIR__ . '/../readme.txt';
		$readme_data = get_file_data(
			$readme_file,
			array(
				'Stable tag' => 'Stable tag',
			)
		);

		// Get the plugin headers.
		// Plugin name.
		$plugin_file_name = basename( dirname( __DIR__ ) ) . '.php';
		if ( ! file_exists( __DIR__ . "/../{$plugin_file_name}" ) ) {
			// Fallback to the generic plugin file name.
			$plugin_file_name = 'plugin.php';
		}

		$plugin_file_data = get_file_data(
			__DIR__ . "/../{$plugin_file_name}",
			array(
				'Version' => 'Version',
			)
		);

		$this->assertSame( $readme_data['Stable tag'], $plugin_file_data['Version'], "The Stable tag {$readme_data['Stable tag']} in readme.txt does not match the Version header {$plugin_file_data['Version']}." );
	}
}
