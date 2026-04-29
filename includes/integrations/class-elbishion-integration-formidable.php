<?php
/**
 * Formidable Forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Formidable {

	public static function is_detected() {
		return class_exists( 'FrmEntry' );
	}

	public static function init() {
		add_action( 'frm_after_create_entry', array( __CLASS__, 'capture' ), 10, 2 );
	}

	public static function capture( $entry_id, $form_id ) {
		$form_name = __( 'Formidable Form', 'elbishion' );

		if ( class_exists( 'FrmForm' ) ) {
			$form = FrmForm::getOne( $form_id );
			if ( is_object( $form ) && ! empty( $form->name ) ) {
				$form_name = $form->name;
			}
		}

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'formidable_forms', $form_name, (string) $form_id ) ) {
			return;
		}

		$fields = isset( $_POST['item_meta'] ) && is_array( $_POST['item_meta'] ) ? wp_unslash( $_POST['item_meta'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		Elbishion_Integrations_Manager::save_submission(
			'formidable_forms',
			$form_name,
			$fields,
			array(
				'source_form_id' => (string) $form_id,
				'raw_data'       => array( 'entry_id' => $entry_id, 'item_meta' => $fields ),
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
