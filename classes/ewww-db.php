<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ewwwdb extends wpdb {

	// makes sure we use some variant of utf8 for checking images
	function init_charset() {
		parent::init_charset();
		if ( strpos( $this->charset, 'utf8' ) === false ) {
			$this->charset = 'utf8';
		}
	}

	// inserts multiple records into the table at once
	// each sub-array should have the same number of items as $formats
	// if $format isn't specified, default to string (%s)
	// fields/columns are extracted from the very first item in the $data array()
	function insert_multiple( $table, $data, $format ) {
		if ( empty( $table ) || ! ewww_image_optimizer_iterable( $data ) || ! ewww_image_optimizer_iterable( $format ) ) {
			return false;
		}
		//given a multi-dimensional array like so
		// array(
		//	[0] =>
		//		'path' => '/some/image/path/here.jpg'
		//		'gallery' => 'something'
		//		'orig_size => 5678
		//		'attachment_id => 2
		//		'resize' => 'thumb'
		//		'pending' => 1
		//	[1] =>
		//		'path' => '/some/image/path/another.jpg'
		//		'gallery' => 'something'
		//		'orig_size => 1234
		//		'attachment_id => 3
		//		'resize' => 'full'
		//		'pending' => 1
		//	)
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
//ewwwio_debug_message( $sql );
//ewwwio_debug_message( print_r( $values, true ) );
		$this->check_current_query = false;
		return $this->query( $this->prepare( $sql, $values ) );
	}

}
