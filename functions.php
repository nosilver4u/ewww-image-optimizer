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
 * Check if file exists, and that it is local rather than using a protocol like http:// or phar://
 *
 * @param string $file The path of the file to check.
 * @return bool True if the file exists and is local, false otherwise.
 */
function ewwwio_is_file( $file ) {
	return ewwwio()->is_file( $file );
}

/**
 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
 *
 * @param string $file The name of the file.
 * @return int The size of the file or zero.
 */
function ewww_image_optimizer_filesize( $file ) {
	return ewwwio()->filesize( $file );
}

/**
 * Check the mimetype of the given file with magic mime strings/patterns.
 *
 * @param string $path The absolute path to the file.
 * @param string $type The type of file we are checking. Default 'i' for
 *                     images/pdfs or 'b' for binary.
 * @return bool|string A valid mime-type or false.
 */
function ewww_image_optimizer_mimetype( $path, $type = 'i' ) {
	return ewwwio()->mimetype( $path, $type );
}

/**
 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
 *
 * @param string $path The name of the file.
 * @return string|bool The mime type based on the extension or false.
 */
function ewww_image_optimizer_quick_mimetype( $path ) {
	return ewwwio()->quick_mimetype( $path );
}

/**
 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
 * same-named constant. Overrides are only available for integers, booleans, and specifically supported options.
 *
 * @param string $option_name The name of the option to retrieve.
 * @param mixed  $default_value The default to use if not found/set, defaults to false, but not currently used.
 * @param bool   $single Use single-site setting regardless of multisite activation. Default is off/false.
 * @return mixed The value of the option.
 */
function ewww_image_optimizer_get_option( $option_name, $default_value = false, $single = false ) {
	return ewwwio()->get_option( $option_name, $default_value, $single );
}

/**
 * Set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * @param string $option_name The name of the option to save.
 * @param mixed  $option_value The value to save for the option.
 * @return bool True if the operation was successful.
 */
function ewww_image_optimizer_set_option( $option_name, $option_value ) {
	return ewwwio()->set_option( $option_name, $option_value );
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
 * Checks if the S3 Uploads plugin is installed and active.
 *
 * @return bool True if it is fully active and rewriting/offloding media, false otherwise.
 */
function ewww_image_optimizer_s3_uploads_enabled() {
	return ewwwio()->s3_uploads_enabled();
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
