<?php
/**
 * Simple logger that keeps a rolling list of recent events in an option.
 *
 * @package NutriciaAiOpisnik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nutricia_AI_Logger {

	const OPTION_KEY  = 'nutricia_ai_log';
	const MAX_ENTRIES = 100;

	/**
	 * Add a log entry.
	 *
	 * @param string $level   info|success|warning|error.
	 * @param string $message Human readable message.
	 * @param int    $product_id Optional related product ID.
	 */
	public static function log( $level, $message, $product_id = 0 ) {
		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'       => current_time( 'mysql' ),
				'level'      => sanitize_key( $level ),
				'message'    => wp_strip_all_tags( (string) $message ),
				'product_id' => absint( $product_id ),
			)
		);

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		update_option( self::OPTION_KEY, $log, false );
	}

	public static function info( $message, $product_id = 0 ) {
		self::log( 'info', $message, $product_id );
	}

	public static function success( $message, $product_id = 0 ) {
		self::log( 'success', $message, $product_id );
	}

	public static function warning( $message, $product_id = 0 ) {
		self::log( 'warning', $message, $product_id );
	}

	public static function error( $message, $product_id = 0 ) {
		self::log( 'error', $message, $product_id );
	}

	/**
	 * Return the log entries.
	 *
	 * @return array
	 */
	public static function get_entries() {
		$log = get_option( self::OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}
}
