<?php
/**
 * Class and methods to integrate EWWW IO and NextGEN Gallery.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $_REQUEST['page'] ) && 'ngg_other_options' !== $_REQUEST['page'] && ! class_exists( 'EWWW_Nextgen_Gallery_Storage' ) && class_exists( 'Mixin' ) && class_exists( 'C_Gallery_Storage' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	/**
	 * Extension for NextGEN image generation.
	 */
	class EWWW_Nextgen_Gallery_Storage extends Mixin {
		/**
		 * Generates an image size (via the parent class) and then optimizes it.
		 *
		 * @param int|object $image A nextgen image object or the image ID number.
		 * @param string     $size The name of the size.
		 * @param array      $params Image generation parameters: width, height, and crop_frame.
		 * @param bool       $skip_defaults I have no idea, ask the NextGEN devs...
		 */
		public function generate_image_size( $image, $size, $params = null, $skip_defaults = false ) {
			ewwwio_debug_message( '<b>' . __METHOD__ . '()</b>' );
			$success = $this->call_parent( 'generate_image_size', $image, $size, $params, $skip_defaults );
			if ( $success ) {
				$filename = $success->fileName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				ewww_image_optimizer( $filename );
				ewwwio_debug_message( "nextgen dynamic thumb saved via extension: $filename" );
				$image_size = ewww_image_optimizer_filesize( $filename );
				ewwwio_debug_message( "optimized size: $image_size" );
			}
			ewww_image_optimizer_debug_log();
			ewwwio_memory( __METHOD__ );
			return $success;
		}
	}
	$storage = C_Gallery_Storage::get_instance();
	$storage->get_wrapped_instance()->add_mixin( 'EWWW_Nextgen_Gallery_Storage' );
}
