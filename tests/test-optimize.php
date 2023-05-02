<?php
/**
 * Class EWWWIO_Optimize_Tests
 *
 * @link https://ewww.io
 * @package Ewww_Image_Optimizer
 */

/**
 * Optimization test cases.
 */
class EWWWIO_Optimize_Tests extends WP_UnitTestCase {

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
	 * The location of the test PDF image.
	 *
	 * @var string $test_pdf
	 */
	public static $test_pdf = '';

	/**
	 * The location of the test SVG image.
	 *
	 * @var string $test_svg
	 */
	public static $test_svg = '';

	/**
	 * Downloads test images.
	 */
	public static function set_up_before_class() {
		$wp_upload_dir   = wp_upload_dir();
		$temp_upload_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'testing/';
		wp_mkdir_p( $temp_upload_dir );

		$test_jpg = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/20170314_174658.jpg' );
		rename( $test_jpg, $temp_upload_dir . wp_basename( $test_jpg ) );
		self::$test_jpg = $temp_upload_dir . wp_basename( $test_jpg );

		$test_png = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/books.png' );
		rename( $test_png, $temp_upload_dir . wp_basename( $test_png ) );
		self::$test_png = $temp_upload_dir . wp_basename( $test_png );

		$test_gif = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/gifsiclelogo.gif' );
		rename( $test_gif, $temp_upload_dir . wp_basename( $test_gif ) );
		self::$test_gif = $temp_upload_dir . wp_basename( $test_gif );

		$test_pdf = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/tomtempleartist-bio-2008.pdf' );
		rename( $test_pdf, $temp_upload_dir . wp_basename( $test_pdf ) );
		self::$test_pdf = $temp_upload_dir . wp_basename( $test_pdf );

		$test_svg = download_url( 'https://ewwwio-test.sfo2.digitaloceanspaces.com/unit-tests/image-x-generic.svg' );
		rename( $test_svg, $temp_upload_dir . wp_basename( $test_svg ) );
		self::$test_svg = $temp_upload_dir . wp_basename( $test_svg );

		ewwwio()->set_defaults();
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_option( 'ewww_image_optimizer_png_level', 40 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_png_level', 40 );
		ewwwio()->local->install_tools();
		ewww_image_optimizer_install_pngout();
		ewww_image_optimizer_install_svgcleaner();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
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
	 * Copies the test JPG to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_jpg() {
		global $ewww_force;
		$ewww_force = 1;
		$filename = self::$test_jpg . ".jpg";
		copy( self::$test_jpg, $filename );
		$results = ewww_image_optimizer( $filename );
		return $results;
	}

	/**
	 * Copies the test PNG to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_png() {
		global $ewww_force;
		$ewww_force = 1;
		$filename = self::$test_png . ".png";
		copy( self::$test_png, $filename );
		$results = ewww_image_optimizer( $filename );
		return $results;
	}

	/**
	 * Copies the test GIF to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_gif() {
		global $ewww_force;
		$ewww_force = 1;
		$filename = self::$test_gif . ".gif";
		copy( self::$test_gif, $filename );
		$results = ewww_image_optimizer( $filename );
		return $results;
	}

	/**
	 * Copies the test PDF to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_pdf() {
		global $ewww_force;
		$ewww_force = 1;
		$filename = self::$test_pdf . ".pdf";
		copy( self::$test_pdf, $filename );
		$results = ewww_image_optimizer( $filename );
		return $results;
	}

	/**
	 * Copies the test SVG to a temp file, optimizes it, and returns the results.
	 *
	 * @return array The results of the ewww_image_optimizer() function.
	 */
	protected function optimize_svg() {
		global $ewww_force;
		$ewww_force = 1;
		$filename = self::$test_svg . ".svg";
		copy( self::$test_svg, $filename );
		$results = ewww_image_optimizer( $filename );
		return $results;
	}

	/**
	 * Test default JPG optimization with WebP.
	 */
	function test_optimize_jpg_10() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		$this->assertEquals( 1348499, filesize( $results[0] ) );
		unlink( $results[0] );
		$this->assertEquals( 200048, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test lossless JPG and keeps meta with WebP and autorotation tests.
	 */
	function test_optimize_jpg_10_keep_meta() {
		update_option( 'ewww_image_optimizer_metadata_remove', '' );
		update_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_metadata_remove', '' );
		update_site_option( 'ewww_image_optimizer_jpg_level', 10 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		// size post opt.
		$this->assertEquals( 1368047, filesize( $results[0] ) );
		// orientation pre-rotation.
		$this->assertEquals( ewww_image_optimizer_get_orientation( self::$test_jpg, 'image/jpeg' ), 8 );
		// orientation post-rotation should always be 1, no matter the image.
		$this->assertEquals( ewww_image_optimizer_get_orientation( $results[0], 'image/jpeg' ), 1 );
		unlink( $results[0] );
		// size of webp with meta.
		$this->assertEquals( 219630, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test Max Lossless JPG optimization with WebP (API level 20).
	 */
	function test_optimize_jpg_20() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 1339854, filesize( $results[0] ) );
		unlink( $results[0] );
		$this->assertEquals( 187866, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test lossless JPG via API and keeps meta with WebP and autorotation check.
	 */
	function test_optimize_jpg_20_keep_meta() {
		update_option( 'ewww_image_optimizer_metadata_remove', '' );
		update_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_option( 'ewww_image_optimizer_webp', true );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_metadata_remove', '' );
		update_site_option( 'ewww_image_optimizer_jpg_level', 20 );
		update_site_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		// size post opt.
		$this->assertEquals( 1359406, filesize( $results[0] ) );
		// orientation pre-rotation.
		$this->assertEquals( ewww_image_optimizer_get_orientation( $results[0], 'image/jpeg' ), 1 );
		unlink( $results[0] );
		// size of webp with meta.
		$this->assertEquals( 207452, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test Regular Lossy JPG optimization (API level 30).
	 */
	function test_optimize_jpg_30() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 30 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 30 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 348295, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test Max Lossy JPG optimization (API level 40).
	 */
	function test_optimize_jpg_40() {
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_jpg_level', 40 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_jpg_level', 40 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_jpg();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 310924, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossless PNG with optipng.
	 */
	function test_optimize_png_10_optipng() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', true );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_webp', true );
		$results = $this->optimize_png();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		$this->assertEquals( 188043, filesize( $results[0] ) );
		unlink( $results[0] );
		$this->assertLessThanOrEqual( 138000, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test lossless PNG with optipng, keeping metadata.
	 */
	function test_optimize_png_10_optipng_keep_meta() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', '' );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', true );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', '' );
		$results = $this->optimize_png();
		$this->assertLessThanOrEqual( 190775, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossless PNG with optipng and PNGOUT.
	 */
	function test_optimize_png_10_optipng_pngout() {
		update_option( 'ewww_image_optimizer_png_level', 10 );
		update_option( 'ewww_image_optimizer_disable_pngout', '' );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_pngout_level', 1 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_png_level', 10 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', '' );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_pngout_level', 1 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		$results = $this->optimize_png();
		$this->assertEquals( 180779, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossy local PNG with optipng.
	 */
	function test_optimize_png_40_optipng() {
		update_option( 'ewww_image_optimizer_png_level', 40 );
		update_option( 'ewww_image_optimizer_disable_pngout', true );
		update_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_png_level', 40 );
		update_site_option( 'ewww_image_optimizer_disable_pngout', true );
		update_site_option( 'ewww_image_optimizer_optipng_level', 2 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		$results = $this->optimize_png();
		$this->assertLessThanOrEqual( 39000, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test "better" lossless PNG with API, no meta.
	 */
	function test_optimize_png_20() {
		update_option( 'ewww_image_optimizer_png_level', 20 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_png_level', 20 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_png();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertLessThanOrEqual( 176857, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test regular lossy PNG with API.
	 */
	function test_optimize_png_40() {
		update_option( 'ewww_image_optimizer_png_level', 40 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_png_level', 40 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_png();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertLessThanOrEqual( 39100, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test max lossy PNG with API.
	 */
	function test_optimize_png_50() {
		update_option( 'ewww_image_optimizer_png_level', 50 );
		update_option( 'ewww_image_optimizer_metadata_remove', true );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_png_level', 50 );
		update_site_option( 'ewww_image_optimizer_metadata_remove', true );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_png();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 32267, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossless GIF.
	 */
	function test_optimize_gif_10() {
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_site_option( 'ewww_image_optimizer_gif_level', 10 );
		$results = $this->optimize_gif();
		$this->assertEquals( 8900, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossless GIF via API.
	 */
	function test_optimize_gif_10_api() {
		update_option( 'ewww_image_optimizer_gif_level', 10 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_option( 'ewww_image_optimizer_webp', true );
		update_site_option( 'ewww_image_optimizer_gif_level', 10 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_webp', true );
		$results = $this->optimize_gif();
		update_option( 'ewww_image_optimizer_webp', '' );
		update_site_option( 'ewww_image_optimizer_webp', '' );
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 8900, filesize( $results[0] ) );
		unlink( $results[0] );
		$this->assertEquals( 8014, filesize( $results[0] . '.webp' ) );
		if ( ewwwio_is_file( $results[0] . '.webp' ) ) {
			unlink( $results[0] . '.webp' );
		}
	}

	/**
	 * Test lossless PDF via API.
	 */
	function test_optimize_pdf_10() {
		update_option( 'ewww_image_optimizer_pdf_level', 10 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_pdf_level', 10 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_pdf();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 144907, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test lossy PDF via API.
	 */
	function test_optimize_pdf_20() {
		update_option( 'ewww_image_optimizer_pdf_level', 20 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_pdf_level', 20 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_pdf();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertLessThan( 129000, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test minimal SVG locally.
	 */
	function test_optimize_svg_01() {
		update_option( 'ewww_image_optimizer_svg_level', 1 );
		update_site_option( 'ewww_image_optimizer_svg_level', 1 );
		$results = $this->optimize_svg();
		$this->assertEquals( 10792, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test default SVG locally.
	 */
	function test_optimize_svg_10() {
		update_option( 'ewww_image_optimizer_svg_level', 10 );
		update_site_option( 'ewww_image_optimizer_svg_level', 10 );
		$results = $this->optimize_svg();
		$this->assertEquals( 9518, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test minimal SVG via API.
	 */
	function test_optimize_svg_01_api() {
		update_option( 'ewww_image_optimizer_svg_level', 1 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_svg_level', 1 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_svg();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 10792, filesize( $results[0] ) );
		unlink( $results[0] );
	}

	/**
	 * Test default SVG via API.
	 */
	function test_optimize_svg_10_api() {
		update_option( 'ewww_image_optimizer_svg_level', 10 );
		update_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		update_site_option( 'ewww_image_optimizer_svg_level', 10 );
		update_site_option( 'ewww_image_optimizer_cloud_key', 'abc123' );
		$results = $this->optimize_svg();
		update_option( 'ewww_image_optimizer_cloud_key', '' );
		update_site_option( 'ewww_image_optimizer_cloud_key', '' );
		$this->assertEquals( 9518, filesize( $results[0] ) );
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
		delete_option( 'ewww_image_optimizer_cloud_key' );
		delete_site_option( 'ewww_image_optimizer_version' );
		delete_site_option( 'ewww_image_optimizer_cloud_key' );
		parent::tear_down();
	}

	/**
	 * Cleans up the temp images.
	 */
	public static function tear_down_after_class() {
		if ( ewwwio_is_file( self::$test_jpg ) ) {
			unlink( self::$test_jpg );
		}
		if ( ewwwio_is_file( self::$test_png ) ) {
			unlink( self::$test_png );
		}
		if ( ewwwio_is_file( self::$test_gif ) ) {
			unlink( self::$test_gif );
		}
		if ( ewwwio_is_file( self::$test_pdf ) ) {
			unlink( self::$test_pdf );
		}
		ewww_image_optimizer_remove_binaries();
	}
}
