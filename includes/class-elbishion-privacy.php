<?php
/**
 * Privacy and field filtering helpers.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central privacy rules for submitted data.
 */
class Elbishion_Privacy {

	/**
	 * Default sensitive keywords.
	 *
	 * @return array
	 */
	public static function default_sensitive_keywords() {
		return array( 'password', 'pass', 'credit_card', 'card_number', 'card-number', 'cardnumber', 'cc_number', 'cvv', 'cvc', 'token', 'secret', 'api_key', 'apikey' );
	}

	/**
	 * Determine whether a field should be excluded.
	 *
	 * @param string $field_id Field ID/name.
	 * @param string $label Field label.
	 * @param string $type Field type.
	 * @param array  $settings Plugin settings.
	 * @return bool
	 */
	public static function should_exclude_field( $field_id, $label = '', $type = '', $settings = array() ) {
		$field_id = strtolower( (string) $field_id );
		$label    = strtolower( (string) $label );
		$type     = strtolower( (string) $type );

		if ( self::is_acceptance_field( $field_id, $label, $type ) ) {
			return true;
		}

		if ( in_array( $type, array( 'password', 'credit_card', 'card', 'card_number', 'cvv', 'cvc' ), true ) ) {
			return true;
		}

		if ( ! empty( $settings['exclude_hidden_fields'] ) && 'hidden' === $type ) {
			return true;
		}

		$keywords = self::exclude_keywords( $settings );

		foreach ( $keywords as $keyword ) {
			if ( '' === $keyword ) {
				continue;
			}

			if ( false !== strpos( $field_id, $keyword ) || false !== strpos( $label, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect fields that only confirm terms/acceptance and should not be stored.
	 *
	 * @param string $field_id Field ID/name.
	 * @param string $label Field label.
	 * @param string $type Field type.
	 * @return bool
	 */
	private static function is_acceptance_field( $field_id, $label = '', $type = '' ) {
		if ( in_array( $type, array( 'acceptance', 'terms', 'terms_conditions' ), true ) ) {
			return true;
		}

		foreach ( array( $field_id, $label ) as $value ) {
			if ( preg_match( '/(^|[_\-\s])acceptance([_\-\s]|$)/', (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get configured exclude keywords.
	 *
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public static function exclude_keywords( $settings = array() ) {
		$configured = isset( $settings['exclude_field_keywords'] ) ? (string) $settings['exclude_field_keywords'] : '';
		$items      = preg_split( '/[\r\n,]+/', $configured );
		$items      = array_filter( array_map( 'trim', (array) $items ) );

		if ( empty( $items ) ) {
			$items = self::default_sensitive_keywords();
		}

		return array_values( array_unique( array_map( 'strtolower', $items ) ) );
	}

	/**
	 * Sanitize and optionally mask IP addresses.
	 *
	 * @param string $ip IP address.
	 * @param array  $settings Plugin settings.
	 * @return string
	 */
	public static function prepare_ip( $ip, $settings = array() ) {
		if ( empty( $settings['save_ip'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( (string) $ip );

		if ( empty( $settings['mask_ip'] ) || '' === $ip ) {
			return $ip;
		}

		if ( false !== strpos( $ip, ':' ) ) {
			$parts = explode( ':', $ip );
			array_splice( $parts, max( 2, count( $parts ) - 4 ) );
			return implode( ':', $parts ) . '::';
		}

		$parts = explode( '.', $ip );

		if ( 4 === count( $parts ) ) {
			$parts[3] = '0';
		}

		return implode( '.', $parts );
	}

	/**
	 * Prepare user agent for storage.
	 *
	 * @param string $user_agent User agent.
	 * @param array  $settings Plugin settings.
	 * @return string
	 */
	public static function prepare_user_agent( $user_agent, $settings = array() ) {
		if ( empty( $settings['save_user_agent'] ) ) {
			return '';
		}

		return sanitize_textarea_field( (string) $user_agent );
	}
}
