<?php
/**
 * WPForms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_WPForms {

	public static function is_detected() {
		return defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' );
	}

	public static function init() {
		add_action( 'wpforms_process_complete', array( __CLASS__, 'capture' ), 10, 4 );
	}

	public static function capture( $fields, $entry, $form_data, $entry_id ) {
		$form_id   = isset( $form_data['id'] ) ? (string) $form_data['id'] : '';
		$form_name = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : __( 'WPForms Form', 'elbishion' );

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'wpforms', $form_name, $form_id ) ) {
			return;
		}

		$normalized = array();

		foreach ( (array) $fields as $field ) {
			$normalized[] = array(
				'id'    => $field['id'] ?? '',
				'label' => $field['name'] ?? ( $field['id'] ?? '' ),
				'type'  => $field['type'] ?? '',
				'value' => $field['value'] ?? '',
			);
		}

		Elbishion_Integrations_Manager::save_submission(
			'wpforms',
			$form_name,
			$normalized,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => array( 'fields' => $fields, 'entry' => $entry, 'entry_id' => $entry_id ),
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
