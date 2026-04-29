<?php
/**
 * Ninja Forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Ninja_Forms {

	public static function is_detected() {
		return class_exists( 'Ninja_Forms' );
	}

	public static function init() {
		add_action( 'ninja_forms_after_submission', array( __CLASS__, 'capture' ), 10, 1 );
	}

	public static function capture( $form_data ) {
		$form_id   = isset( $form_data['form_id'] ) ? (string) $form_data['form_id'] : '';
		$form_name = isset( $form_data['settings']['title'] ) ? $form_data['settings']['title'] : __( 'Ninja Form', 'elbishion' );

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'ninja_forms', $form_name, $form_id ) ) {
			return;
		}

		$fields = array();

		foreach ( (array) ( $form_data['fields'] ?? array() ) as $field ) {
			$fields[] = array(
				'id'    => $field['key'] ?? ( $field['id'] ?? '' ),
				'label' => $field['label'] ?? ( $field['key'] ?? '' ),
				'type'  => $field['type'] ?? '',
				'value' => $field['value'] ?? '',
			);
		}

		Elbishion_Integrations_Manager::save_submission(
			'ninja_forms',
			$form_name,
			$fields,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => (array) $form_data,
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
