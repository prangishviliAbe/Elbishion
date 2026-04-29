<?php
/**
 * Forminator integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Forminator {

	public static function is_detected() {
		return defined( 'FORMINATOR_VERSION' ) || class_exists( 'Forminator' );
	}

	public static function init() {
		add_action( 'forminator_form_after_save_entry', array( __CLASS__, 'capture' ), 10, 2 );
	}

	public static function capture( $form_id, $response = array() ) {
		$form_name = sprintf( 'Forminator #%s', absint( $form_id ) );
		$fields    = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'forminator', $form_name, (string) $form_id ) ) {
			return;
		}

		Elbishion_Integrations_Manager::save_submission(
			'forminator',
			$form_name,
			$fields,
			array(
				'source_form_id' => (string) $form_id,
				'raw_data'       => array( 'post' => $fields, 'response' => $response ),
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
