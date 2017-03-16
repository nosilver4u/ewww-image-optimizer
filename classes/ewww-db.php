<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ewwwdb extends wpdb {

	// Makes sure we use some variant of utf8 for checking images.
	function init_charset() {
		parent::init_charset();
		if ( strpos( $this->charset, 'utf8' ) === false ) {
			$this->charset = 'utf8';
		}
	}

	/**
	 * Inserts multiple records into the table at once.
	 *
	 * Takes an associative array and creates a database record for each array item, using a single MySQL query.
	 * 
	 * @param string $table
	 * @param array $data
	 * @param array $format
	 * Each sub-array should have the same number of items as $formats.
	 * If $format isn't specified, default to string (%s).
	 * The fields/columns are extracted from the very first item in the $data array().
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
		$multi_formats = $values = array();
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
				$values[] = $value['value'];
			}
			$multi_formats[] = '(' . implode( ',', $formats ) . ')';
		}
		$first = reset( $data );
		$fields = '`' . implode( '`, `', array_keys( $first ) ) . '`';
		$multi_formats = implode( ',', $multi_formats );
		$sql = "INSERT INTO `$table` ($fields) VALUES $multi_formats";
		$this->check_current_query = false;
		return $this->query( $this->prepare( $sql, $values ) );
	}

}
