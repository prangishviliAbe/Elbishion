<?php
/**
 * Custom HTML capture integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Custom_HTML {

	public static function is_detected() {
		return true;
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_capture' ), 1 );
	}

	public static function maybe_capture() {
		if ( empty( $_POST['elbishion_capture'] ) || '1' !== sanitize_text_field( wp_unslash( $_POST['elbishion_capture'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'custom_html', self::form_name(), '' ) ) {
			return;
		}

		$fields = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		unset( $fields['elbishion_capture'], $fields['elbishion_form_name'], $fields['_wpnonce'], $fields['_wp_http_referer'] );

		Elbishion_Integrations_Manager::save_submission(
			'custom_html',
			self::form_name(),
			$fields,
			array(
				'raw_data'    => $fields,
				'page_url'    => Elbishion_Integrations_Manager::current_page_url(),
				'referer_url' => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}

	private static function form_name() {
		return ! empty( $_POST['elbishion_form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['elbishion_form_name'] ) ) : __( 'Custom HTML Form', 'elbishion' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
}
