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
 * @param string $type The type of file we are checking. Accepts 'i' for
 *                     images/pdfs or 'b' for binary.
 * @return bool|string A valid mime-type or false.
 */
function ewww_image_optimizer_mimetype( $path, $type ) {
	return ewwwio()->mimetype( $path, $type );
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
 * Sanitize the folders/patterns to exclude from optimization.
 *
 * @param string $input A list of filesystem paths, from a textarea.
 * @return array The sanitized list of paths/patterns to exclude.
 */
function ewww_image_optimizer_exclude_paths_sanitize( $input ) {
	return ewwwio()->exclude_paths_sanitize( $input );
}

/**
 * Checks if a function is disabled or does not exist.
 *
 * @param string $function_name The name of a function to test.
 * @param bool   $debug Whether to output debugging.
 * @return bool True if the function is available, False if not.
 */
function ewww_image_optimizer_function_exists( $function_name, $debug = false ) {
	return ewwwio()->function_exists( $function_name, $debug );
}

/**
 * Make sure an array/object can be parsed by a foreach().
 *
 * @param mixed $value A variable to test for iteration ability.
 * @return bool True if the variable is iterable.
 */
function ewww_image_optimizer_iterable( $value ) {
	return ewwwio()->is_iterable( $value );
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
