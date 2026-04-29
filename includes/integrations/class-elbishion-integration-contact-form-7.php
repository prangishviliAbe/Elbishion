<?php
/**
 * Contact Form 7 integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_Contact_Form_7 {

	public static function is_detected() {
		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
	}

	public static function init() {
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'capture' ), 10, 1 );
	}

	public static function capture( $contact_form ) {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return;
		}

		$form_id   = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (string) $contact_form->id() : '';
		$form_name = is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? $contact_form->title() : __( 'Contact Form 7 Form', 'elbishion' );

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'contact_form_7', $form_name, $form_id ) ) {
			return;
		}

		$posted = $submission->get_posted_data();
		$files  = $submission->uploaded_files();

		foreach ( (array) $files as $key => $value ) {
			$file_path = is_array( $value ) ? reset( $value ) : $value;
			$posted[ $key ] = array(
				'path' => sanitize_text_field( (string) $file_path ),
				'name' => sanitize_file_name( basename( (string) $file_path ) ),
			);
		}

		Elbishion_Integrations_Manager::save_submission(
			'contact_form_7',
			$form_name,
			$posted,
			array(
				'source_form_id' => $form_id,
				'raw_data'       => $posted,
				'page_url'       => Elbishion_Integrations_Manager::referer_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
