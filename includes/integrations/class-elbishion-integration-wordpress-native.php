<?php
/**
 * WordPress native forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_WordPress_Native {

	public static function is_detected() {
		return true;
	}

	public static function init() {
		add_action( 'user_register', array( __CLASS__, 'capture_registration' ), 20, 1 );
		add_action( 'comment_post', array( __CLASS__, 'capture_comment' ), 20, 3 );
		add_action( 'retrieve_password', array( __CLASS__, 'capture_password_reset' ), 20, 1 );
		add_action( 'wp_login', array( __CLASS__, 'capture_login' ), 20, 2 );
	}

	public static function capture_registration( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$data = $user ? array( 'user_login' => $user->user_login, 'user_email' => $user->user_email, 'display_name' => $user->display_name ) : array( 'user_id' => $user_id );

		self::save( 'WordPress User Registration', 'registration', $data, array( 'user_id' => $user_id ) );
	}

	public static function capture_comment( $comment_id, $comment_approved, $commentdata ) {
		unset( $comment_approved );
		self::save( 'WordPress Comment Form', 'comment', (array) $commentdata, array( 'source_form_id' => (string) $comment_id ) );
	}

	public static function capture_password_reset( $user_login ) {
		self::save( 'WordPress Password Reset', 'password_reset', array( 'user_login' => $user_login ) );
	}

	public static function capture_login( $user_login, $user ) {
		self::save( 'WordPress Login', 'login', array( 'user_login' => $user_login ), array( 'user_id' => is_object( $user ) ? absint( $user->ID ) : 0 ) );
	}

	private static function save( $form_name, $form_id, $fields, $meta = array() ) {
		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'wordpress_native', $form_name, $form_id ) ) {
			return;
		}

		$meta['source_form_id'] = $form_id;
		$meta['raw_data']       = $fields;
		$meta['page_url']       = Elbishion_Integrations_Manager::current_page_url();
		$meta['referer_url']    = Elbishion_Integrations_Manager::referer_url();

		Elbishion_Integrations_Manager::save_submission( 'wordpress_native', $form_name, $fields, $meta );
	}
}
