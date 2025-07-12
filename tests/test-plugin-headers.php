<?php
/**
 * Test Plugin Readme and PHP Headers. Adapted from @peterwilsoncc's plugin template.
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

const OPTIONAL  = 0;
const REQUIRED  = 1;
const FORBIDDEN = 2;

/**
 * Test Plugin Readme and PHP Headers.
 */
class EWWWIO_Test_Plugin_Headers extends WP_UnitTestCase {

	/**
	 * Readme headers specification.
	 *
	 * @var array<string,int> Headers defined in the readme spec. Key: Header; Value: OPTIONAL, REQUIRED, FORBIDDEN.
	 */
	public static $readme_headers = array(
		'Contributors'      => REQUIRED,
		'Tags'              => OPTIONAL,
		'Donate link'       => OPTIONAL,
		'Tested up to'      => REQUIRED,
		'Stable tag'        => REQUIRED,
		'License'           => REQUIRED,
		'License URI'       => OPTIONAL,

		// Plugin file headers that do not belong in the readme.
		'Plugin Name'       => FORBIDDEN,
		'Plugin URI'        => FORBIDDEN,
		'Description'       => FORBIDDEN,
		'Version'           => FORBIDDEN,
		'Author'            => FORBIDDEN,
		'Author URI'        => FORBIDDEN,
		'Text Domain'       => FORBIDDEN,
		'Domain Path'       => FORBIDDEN,
		'Network'           => FORBIDDEN,
		'Update URI'        => FORBIDDEN,
		'Requires at least' => FORBIDDEN, // Both WP and the plugin directory prefer the version in the plugin file.
		'Requires PHP'      => FORBIDDEN, // Both WP and the plugin directory prefer the version in the plugin file.
		'Requires Plugins'  => FORBIDDEN,
	);

	/**
	 * Plugin headers specification
	 *
	 * @var array<string,int> Headers defined in the plugin spec. Key: Header; Value: OPTIONAL, REQUIRED, FORBIDDEN.
	 */
	public static $plugin_headers = array(
		'Plugin Name'       => REQUIRED,
		'Plugin URI'        => OPTIONAL,
		'Description'       => REQUIRED,
		'Version'           => REQUIRED,
		'Requires at least' => REQUIRED, // Not required by the spec but I'm enforcing it.
		'Requires PHP'      => REQUIRED, // Not required by the spec but I'm enforcing it.
		'Author'            => REQUIRED,
		'Author URI'        => OPTIONAL,
		'License'           => REQUIRED,
		'License URI'       => OPTIONAL,
		'Text Domain'       => OPTIONAL,
		'Domain Path'       => OPTIONAL,
		'Network'           => OPTIONAL,
		'Update URI'        => OPTIONAL,
		'Requires Plugins'  => OPTIONAL,

		// Readme file headers that do not belong in the plugin file.
		'Contributors'      => FORBIDDEN,
		'Tags'              => FORBIDDEN,
		'Donate link'       => FORBIDDEN,
		'Stable tag'        => FORBIDDEN,

		/*
		 * Opinionated: Allowed by the spec.
		 *
		 * The WordPress plugin directory will use the plugin file headers if
		 * it exists, and fall back to the readme file if it does not.
		 *
		 * However, the 10up Github Action for deploying updates to the
		 * directory will require a version bump if the plugin file is
		 * modified, so it's best to keep tested up to in the readme file.
		 *
		 * WordPress Core doesn't use the header, it pulls the data in
		 * from the plugin API.
		 */
		'Tested up to'      => FORBIDDEN,
	);

	/**
	 * Headers defined in the plugins readme.text file.
	 *
	 * @var string[] Headers defined in the readme spec Header => value.
	 */
	public static $defined_readme_headers = array();

	/**
	 * Headers defined in the plugin file.
	 *
	 * @var string[] Headers defined in the plugin spec Header => value.
	 */
	public static $defined_plugin_headers = array();

	/**
	 * Set up shared fixtures.
	 */
	public static function wpSetupBeforeClass() {
		// Get the readme headers.
		$readme_file_data = array();
		foreach ( self::$readme_headers as $header => $required ) {
			$readme_file_data[ $header ] = $header;
		}
		self::$defined_readme_headers = get_file_data(
			__DIR__ . '/../readme.txt',
			$readme_file_data
		);
		self::$defined_readme_headers = array_filter( self::$defined_readme_headers );

		// Get the plugin headers.
		// Plugin name.
		$plugin_file_name = basename( dirname( __DIR__ ) ) . '.php';
		if ( ! file_exists( __DIR__ . "/../{$plugin_file_name}" ) ) {
			// Fallback to the generic plugin file name.
			$plugin_file_name = 'plugin.php';
		}

		$plugin_file_data = array();
		foreach ( self::$plugin_headers as $header => $required ) {
			$plugin_file_data[ $header ] = $header;
		}

		self::$defined_plugin_headers = get_file_data(
			__DIR__ . "/../{$plugin_file_name}",
			$plugin_file_data
		);
		self::$defined_plugin_headers = array_filter( self::$defined_plugin_headers );
	}

	/**
	 * Test that the readme file has all required headers.
	 *
	 * @dataProvider data_required_readme_headers
	 *
	 * @param string $header Header to test.
	 */
	public function test_required_readme_headers( $header ) {
		$this->assertArrayHasKey( $header, self::$defined_readme_headers, "The readme file header '{$header}' is missing." );
		$this->assertNotEmpty( self::$defined_readme_headers[ $header ], "The readme file header '{$header}' is empty." );
	}

	/**
	 * Data provider for test_required_readme_headers.
	 *
	 * @return array[] Data provider.
	 */
	public function data_required_readme_headers() {
		$required_headers = array_filter(
			self::$readme_headers,
			function ( $status ) {
				return REQUIRED === $status;
			}
		);
		$headers          = array();
		foreach ( $required_headers as $header => $required ) {
			$headers[ $header ] = array( $header );
		}
		return $headers;
	}

	/**
	 * Test that the readme file does not have any forbidden headers.
	 *
	 * @dataProvider data_forbidden_readme_headers
	 *
	 * @param string $header Header to test.
	 */
	public function test_forbidden_readme_headers( $header ) {
		$this->assertArrayNotHasKey( $header, self::$defined_readme_headers, "The readme file header '{$header}' is forbidden." );
	}

	/**
	 * Data provider for test_forbidden_readme_headers.
	 *
	 * @return array[] Data provider.
	 */
	public function data_forbidden_readme_headers() {
		$forbidden_headers = array_filter(
			self::$readme_headers,
			function ( $status ) {
				return FORBIDDEN === $status;
			}
		);
		$headers           = array();
		foreach ( $forbidden_headers as $header => $required ) {
			$headers[ $header ] = array( $header );
		}
		return $headers;
	}

	/**
	 * Test that the plugin file has all required headers.
	 *
	 * @dataProvider data_required_plugin_headers
	 *
	 * @param string $header Header to test.
	 */
	public function test_required_plugin_headers( $header ) {
		$this->assertArrayHasKey( $header, self::$defined_plugin_headers, "The plugin file header '{$header}' is missing." );
		$this->assertNotEmpty( self::$defined_plugin_headers[ $header ], "The readme file header '{$header}' is empty." );
	}

	/**
	 * Data provider for test_required_plugin_headers.
	 *
	 * @return array[] Data provider.
	 */
	public function data_required_plugin_headers() {
		$required_headers = array_filter(
			self::$plugin_headers,
			function ( $status ) {
				return REQUIRED === $status;
			}
		);
		$headers          = array();
		foreach ( $required_headers as $header => $required ) {
			$headers[ $header ] = array( $header );
		}
		return $headers;
	}

	/**
	 * Test that the plugin file does not have any forbidden headers.
	 *
	 * @dataProvider data_forbidden_plugin_headers
	 *
	 * @param string $header Header to test.
	 */
	public function test_forbidden_plugin_headers( $header ) {
		$this->assertArrayNotHasKey( $header, self::$defined_plugin_headers, "The plugin file header '{$header}' is forbidden." );
	}

	/**
	 * Data provider for test_forbidden_plugin_headers.
	 *
	 * @return array[] Data provider.
	 */
	public function data_forbidden_plugin_headers() {
		$forbidden_headers = array_filter(
			self::$plugin_headers,
			function ( $status ) {
				return FORBIDDEN === $status;
			}
		);
		$headers           = array();
		foreach ( $forbidden_headers as $header => $required ) {
			$headers[ $header ] = array( $header );
		}
		return $headers;
	}

	/**
	 * Test that headers defined in both the readme and plugin file match.
	 *
	 * @dataProvider data_common_headers_match
	 *
	 * @param string      $plugin_header_name Plugin file header name to test.
	 * @param string|null $readme_header_name Readme file header name to test. If null, the plugin header name will be used.
	 */
	public function test_common_headers_match( $plugin_header_name, $readme_header_name = null ) {
		$readme_header_name = $readme_header_name ?? $plugin_header_name;
		if ( empty( self::$defined_plugin_headers[ $plugin_header_name ] ) || empty( self::$defined_readme_headers[ $readme_header_name ] ) ) {
			// The header is not common to both files so the test passes.
			$this->assertTrue( true );
			return;
		}

		$plugin_header = self::$defined_plugin_headers[ $plugin_header_name ];
		$readme_header = self::$defined_readme_headers[ $readme_header_name ];

		$message = "The header '{$plugin_header_name}' does not match between the readme and plugin file.";
		if ( $plugin_header_name !== $readme_header_name ) {
			$message = "The plugin header '{$plugin_header_name}' does not match the readme header '{$readme_header_name}'.";
		}

		$this->assertSame( $plugin_header, $readme_header, $message );
	}

	/**
	 * Data provider for test_common_headers_match.
	 *
	 * @return array[] Data provider.
	 */
	public function data_common_headers_match() {
		// Can't use the defined headers as they are not defined until after this is called.
		$common_headers = array_intersect_key(
			self::$readme_headers,
			self::$plugin_headers
		);

		$headers = array();
		// Always test the version matches the stable tag.
		$headers['Stable tag matches version'] = array( 'Version', 'Stable tag' );

		foreach ( $common_headers as $header => $value ) {
			$headers[ $header ] = array( $header );
		}
		return $headers;
	}
}
