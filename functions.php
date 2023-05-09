<?php
/**
 * Wrapper functions for commonly used functions that haven't been fully migrated to oop OR failsafes for backwards compat.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check the mimetype of the given file with magic mime strings/patterns.
 *
 * @param string $path The absolute path to the file.
 * @param string $case The type of file we are checking. Accepts 'i' for
 *                     images/pdfs or 'b' for binary.
 * @return bool|string A valid mime-type or false.
 */
function ewww_image_optimizer_mimetype( $path, $case ) {
	return ewwwio()->mimetype( $path, $case );
}

/**
 * Escape any spaces in the filename.
 *
 * @param string $path The path to a binary file.
 * @return string The path with spaces escaped.
 */
function ewww_image_optimizer_escapeshellcmd( $path ) {
	return ewwwio()->escapeshellcmd( $path );
}

/**
 * Replacement for escapeshellarg() that won't kill non-ASCII characters.
 *
 * @param string $arg A value to sanitize/escape for commmand-line usage.
 * @return string The value after being escaped.
 */
function ewww_image_optimizer_escapeshellarg( $arg ) {
	return ewwwio()->escapeshellarg( $arg );
}

/**
 * Adds information to the in-memory debug log.
 *
 * @param string $message Debug information to add to the log.
 */
function ewwwio_debug_message( $message ) {
	ewwwio()->debug_message( $message );
}

/**
 * Saves the in-memory debug log to a logfile in the plugin folder.
 */
function ewww_image_optimizer_debug_log() {
	ewwwio()->debug_log();
}

