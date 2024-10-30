<?php

class WPM_ImportArrayHelper {
	private static $instance;

	protected function __construct() {
	}

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Sanitize array
	 *
	 * @param array $data
	 *
	 * @return array $result
	 */
	public function sanitize_array( array $data ) {
		$result = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$result[ $key ] = $this->sanitize_array( $value );
			} else {
				$result[ $key ] = sanitize_text_field( $value );
			}
		}

		return $result;
	}
}