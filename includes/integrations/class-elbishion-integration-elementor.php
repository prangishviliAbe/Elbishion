<?php
/**
 * Elementor Pro Forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Elementor {

	public static function is_detected() {
		return did_action( 'elementor_pro/init' ) || class_exists( '\ElementorPro\Plugin' );
	}

	public static function init() {
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'capture' ), 10, 2 );
	}

	public static function capture( $record, $handler ) {
		unset( $handler );

		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$form_name = method_exists( $record, 'get_form_settings' ) ? sanitize_text_field( (string) $record->get_form_settings( 'form_name' ) ) : '';
		$form_name = $form_name ? $form_name : __( 'Elementor Form', 'elbishion' );
		$form_id   = method_exists( $record, 'get_form_settings' ) ? sanitize_text_field( (string) $record->get_form_settings( 'id' ) ) : '';

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'elementor', $form_name, $form_id ) ) {
			return;
		}

		$fields = array();
		$raw    = $record->get( 'fields' );

		if ( is_array( $raw ) ) {
			foreach ( $raw as $key => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$fields[] = array(
					'id'    => $field['id'] ?? $key,
					'label' => $field['title'] ?? ( $field['id'] ?? $key ),
					'type'  => $field['type'] ?? '',
					'value' => $field['value'] ?? '',
				);
			}
		}

		$meta = array(
			'source_form_id' => $form_id,
			'raw_data'       => is_array( $raw ) ? $raw : array(),
			'page_url'       => self::page_url( $record ),
			'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
		);

		Elbishion_Integrations_Manager::save_submission( 'elementor', $form_name, $fields, $meta );
	}

	private static function page_url( $record ) {
		$meta = method_exists( $record, 'get' ) ? $record->get( 'meta' ) : array();

		if ( is_array( $meta ) ) {
			foreach ( array( 'page_url', 'referer', 'referrer' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ) {
					return esc_url_raw( $meta[ $key ] );
				}
			}
		}

		return Elbishion_Integrations_Manager::referer_url();
	}
}
