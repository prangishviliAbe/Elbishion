<?php
/**
 * WooCommerce forms integration.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elbishion_Integration_WooCommerce {

	public static function is_detected() {
		return class_exists( 'WooCommerce' );
	}

	public static function init() {
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'capture_registration' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'capture_checkout' ), 20, 3 );
	}

	public static function capture_registration( $customer_id, $new_customer_data = array(), $password_generated = false ) {
		unset( $password_generated );

		$form_name = 'WooCommerce Registration';

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'woocommerce', $form_name, 'registration' ) ) {
			return;
		}

		$data = is_array( $new_customer_data ) ? $new_customer_data : array( 'customer_id' => $customer_id );

		Elbishion_Integrations_Manager::save_submission(
			'woocommerce',
			$form_name,
			$data,
			array(
				'source_form_id' => 'registration',
				'user_id'        => absint( $customer_id ),
				'raw_data'       => $data,
				'page_url'       => Elbishion_Integrations_Manager::current_page_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}

	public static function capture_checkout( $order_id, $posted_data = array(), $order = null ) {
		$settings = Elbishion_Settings::get_integration_settings( 'woocommerce' );

		if ( empty( $settings['capture_checkout'] ) ) {
			return;
		}

		$form_name = 'WooCommerce Checkout';

		if ( ! Elbishion_Integrations_Manager::should_capture_form( 'woocommerce', $form_name, 'checkout' ) ) {
			return;
		}

		unset( $order );

		Elbishion_Integrations_Manager::save_submission(
			'woocommerce',
			$form_name,
			(array) $posted_data,
			array(
				'source_form_id' => 'checkout',
				'raw_data'       => array( 'order_id' => $order_id, 'posted_data' => $posted_data ),
				'page_url'       => Elbishion_Integrations_Manager::current_page_url(),
				'referer_url'    => Elbishion_Integrations_Manager::referer_url(),
			)
		);
	}
}
