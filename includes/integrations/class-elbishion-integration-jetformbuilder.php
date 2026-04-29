<?php
/**
 * JetFormBuilder integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_JetFormBuilder {

	public static function is_detected() {
		return defined( 'JET_FORM_BUILDER_VERSION' ) || class_exists( '\Jet_Form_Builder\Plugin' );
	}

	public static function init() {
		add_action( 'jet-form-builder/form-handler/after-send', array( __CLASS__, 'capture' ), 10, 2 );
	}

	public static function capture( $handler = null, $is_success = true ) {
		if ( false === $is_success ) {
			return;
		}

		$form_id   = is_object( $handler ) && isset( $handler->form_id ) ? (string) $handler->form_id : '';
		$form_name = $form_id ? sprintf( 'JetFormBuilder #%s', $form_id ) : __( 'JetFormBuilder Form', 'elbishion' );
		$fields    = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'jetformbuilder', $form_name, $form_id ) ) {
			return;
		}

		Elbishion_Integrations_Manager::save_submission(
			'jetformbuilder',
			$form_name,
			$fields,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => $fields,
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
