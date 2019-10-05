<?php
/**
 * Class file for EwwwDB
 *
 * EwwwDB contains methods for working with the ewwwio_images table.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * DB class extension to for working with the ewwwio_images table.
 *
 * Provides functions needed to ensure that all data in the table is in utf8 format.
 *
 * @see wpdb
 */
class EwwwDB extends wpdb {

	/**
	 * Ensures use of some variant of utf8 for interacting with the images table.
	 */
	function init_charset() {
		parent::init_charset();
		if ( strpos( $this->charset, 'utf8' ) === false ) {
			$this->charset = 'utf8';
		}
	}

	/**
	 * Inserts multiple records into the table at once.
	 *
	 * Takes an associative array and creates a database record for each array item, using a single
	 * MySQL query. Column names are extracted from the key names of the first record.
	 *
	 * @param string $table Name of table to be used in INSERT statement.
	 * @param array  $data Records to be inserted into table. Default none. Accepts an array of arrays,
	 *             see example below.
	 * @param array  $format List of formats for values in each record. Each sub-array should have the
	 *           same number of items as $formats. Default '%s'. Accepts '%s', '%d', '%f'.
	 * @return int|false Number of rows affected or false on error.
	 */
	function insert_multiple( $table, $data, $format ) {
		if ( empty( $table ) || ! ewww_image_optimizer_iterable( $data ) || ! ewww_image_optimizer_iterable( $format ) ) {
			return false;
		}

		/*
		 * Given a multi-dimensional array like so:
		 * array(
		 *	[0] =>
		 *		'path' => '/some/image/path/here.jpg'
		 *		'gallery' => 'something'
		 *		'orig_size => 5678
		 *		'attachment_id => 2
		 *		'resize' => 'thumb'
		 *		'pending' => 1
		 *	[1] =>
		 *		'path' => '/some/image/path/another.jpg'
		 *		'gallery' => 'something'
		 *		'orig_size => 1234
		 *		'attachment_id => 3
		 *		'resize' => 'full'
		 *		'pending' => 1
		 *	)
		 */
		ewwwio_debug_message( 'we have records to store via ewwwdb' );
		$multi_formats = array();
		$values        = array();
		foreach ( $data as $record ) {
			if ( ! ewww_image_optimizer_iterable( $record ) ) {
				continue;
			}
			$record = $this->process_fields( $table, $record, $format );
			if ( false === $record ) {
				return false;
			}

			$formats = array();
			foreach ( $record as $value ) {
				if ( is_null( $value['value'] ) ) {
					$formats[] = 'NULL';
					continue;
				}

				$formats[] = $value['format'];
				$values[]  = $value['value'];
			}
			$multi_formats[] = '(' . implode( ',', $formats ) . ')';
		}
		$first                     = reset( $data );
		$fields                    = '`' . implode( '`, `', array_keys( $first ) ) . '`';
		$multi_formats             = implode( ',', $multi_formats );
		$this->check_current_query = false;
		return $this->query( $this->prepare( "INSERT INTO `$table` ($fields) VALUES $multi_formats", $values ) );
	}

}
