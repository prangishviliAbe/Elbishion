<?php
/**
 * Fluent Forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Fluent_Forms {

	public static function is_detected() {
		return defined( 'FLUENTFORM' ) || function_exists( 'wpFluentForm' );
	}

	public static function init() {
		add_action( 'fluentform_submission_inserted', array( __CLASS__, 'capture' ), 10, 3 );
	}

	public static function capture( $entry_id, $form_data, $form ) {
		$form_id   = is_object( $form ) && isset( $form->id ) ? (string) $form->id : '';
		$form_name = is_object( $form ) && isset( $form->title ) ? $form->title : __( 'Fluent Form', 'elbishion' );

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'fluent_forms', $form_name, $form_id ) ) {
			return;
		}

		Elbishion_Integrations_Manager::save_submission(
			'fluent_forms',
			$form_name,
			(array) $form_data,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => array( 'entry_id' => $entry_id, 'form_data' => $form_data ),
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
