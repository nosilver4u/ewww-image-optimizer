<?php
/**
 * Class EWWWIO_Table_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Custom table test cases.
 */
class EWWWIO_Table_Tests extends WP_UnitTestCase {

	/**
	 * The location of the test GIF image.
	 *
	 * @var string $test_gif
	 */
	public static $test_gif = '';

	/**
	 * Downloads test images.
	 */
	public static function set_up_before_class() {
		$wp_upload_dir   = wp_upload_dir();
		$temp_upload_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'testing/';
		wp_mkdir_p( $temp_upload_dir );

		$test_gif = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/gifsiclelogo.gif' );
		rename( $test_gif, $temp_upload_dir . wp_basename( $test_gif ) );
		self::$test_gif = $temp_upload_dir . wp_basename( $test_gif );

		ewwwio()->set_defaults();
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_site_option( 'ewww_image_optimizer_gif_level', 10 );
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
	 * Copies the test GIF to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_gif( $original = false ) {
		if ( ! $original ) {
			$original = self::$test_gif;
		}
		global $ewww_force;
		$ewww_force = 1;
		$filename = $original . ".gif";
		copy( $original, $filename );
		$results = ewww_image_optimizer( $filename, 1 );
		return $results;
	}

	/**
	 * Test updating records in table.
	 */
	function test_ewwwio_images_table() {
		global $wpdb;
		global $ewww_image;
		$orig_size = filesize( self::$test_gif );
		$results = $this->optimize_gif();
		$ewww_image = false;

		$opt_file = $results[0];
		$results_msg = ewww_image_optimizer_check_table( $opt_file, $orig_size );
		$this->assertEmpty( $results_msg );

		$ewww_image = false;
		$file_size = ewww_image_optimizer_filesize( $opt_file );
		$results_msg = ewww_image_optimizer_check_table( $opt_file, $file_size );
		$this->assertStringStartsWith( 'Reduced by', $results_msg );

		$second_results_msg = ewww_image_optimizer_update_table( $opt_file, $file_size - 500, $orig_size );
		$this->assertStringStartsWith( 'Reduced by', $second_results_msg );
		$this->assertNotEquals( $results_msg, $second_results_msg );

		$ewww_image = false;
		$results_msg = ewww_image_optimizer_check_table( $opt_file, $file_size - 500 );
		$this->assertStringStartsWith( $second_results_msg, $results_msg );

		$record = ewww_image_optimizer_find_already_optimized( $opt_file );
		unset( $record['id'] );
		$record['image_size'] = $file_size;
		$record['path']       = ewww_image_optimizer_relativize_path( $opt_file );
		$wpdb->insert( $wpdb->ewwwio_images, $record );
		$record['image_size'] = 0;
		$record['results'] = '';
		$wpdb->insert( $wpdb->ewwwio_images, $record );

		$ewww_image = false;
		$results_msg = ewww_image_optimizer_check_table( $opt_file, $file_size );

		$this->assertStringStartsWith( 'Reduced by', $results_msg );

		unlink( $results[0] );
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
		delete_site_option( 'ewww_image_optimizer_version' );
		parent::tear_down();
	}

	/**
	 * Cleans up the temp images.
	 */
	public static function tear_down_after_class() {
		if ( ewwwio_is_file( self::$test_gif ) ) {
			unlink( self::$test_gif );
		}
		ewww_image_optimizer_remove_binaries();
	}
}
