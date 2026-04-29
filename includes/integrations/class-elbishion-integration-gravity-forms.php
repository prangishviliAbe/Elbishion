<?php
/**
 * Gravity Forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Gravity_Forms {

	public static function is_detected() {
		return class_exists( 'GFForms' );
	}

	public static function init() {
		add_action( 'gform_after_submission', array( __CLASS__, 'capture' ), 10, 2 );
	}

	public static function capture( $entry, $form ) {
		$form_id   = isset( $form['id'] ) ? (string) $form['id'] : '';
		$form_name = isset( $form['title'] ) ? $form['title'] : __( 'Gravity Form', 'elbishion' );

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'gravity_forms', $form_name, $form_id ) ) {
			return;
		}

		$fields = array();

		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			$field_id = is_object( $field ) && isset( $field->id ) ? (string) $field->id : '';
			$fields[] = array(
				'id'    => $field_id,
				'label' => is_object( $field ) && isset( $field->label ) ? $field->label : $field_id,
				'type'  => is_object( $field ) && isset( $field->type ) ? $field->type : '',
				'value' => $entry[ $field_id ] ?? '',
			);
		}

		Elbishion_Integrations_Manager::save_submission(
			'gravity_forms',
			$form_name,
			$fields,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => array( 'entry' => $entry, 'form' => $form ),
				'page_url'       => isset( $entry['source_url'] ) ? $entry['source_url'] : Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
