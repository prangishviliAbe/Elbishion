<?php
/**
 * Integrations manager.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects and initializes supported integrations.
 */
class Elbishion_Integrations_Manager {

	/**
	 * Supported integrations.
	 *
	 * @return array
	 */
	public static function integrations() {
		$integrations = array(
			'elementor'        => array( 'label' => 'Elementor Forms', 'class' => 'Elbishion_Integration_Elementor', 'available' => true ),
			'contact_form_7'   => array( 'label' => 'Contact Form 7', 'class' => 'Elbishion_Integration_Contact_Form_7', 'available' => true ),
			'wpforms'          => array( 'label' => 'WPForms', 'class' => 'Elbishion_Integration_WPForms', 'available' => true ),
			'gravity_forms'    => array( 'label' => 'Gravity Forms', 'class' => 'Elbishion_Integration_Gravity_Forms', 'available' => true ),
			'fluent_forms'     => array( 'label' => 'Fluent Forms', 'class' => 'Elbishion_Integration_Fluent_Forms', 'available' => true ),
			'ninja_forms'      => array( 'label' => 'Ninja Forms', 'class' => 'Elbishion_Integration_Ninja_Forms', 'available' => true ),
			'formidable_forms' => array( 'label' => 'Formidable Forms', 'class' => 'Elbishion_Integration_Formidable', 'available' => true ),
			'jetformbuilder'   => array( 'label' => 'JetFormBuilder', 'class' => 'Elbishion_Integration_JetFormBuilder', 'available' => true ),
			'forminator'       => array( 'label' => 'Forminator', 'class' => 'Elbishion_Integration_Forminator', 'available' => true ),
			'woocommerce'      => array( 'label' => 'WooCommerce Forms', 'class' => 'Elbishion_Integration_WooCommerce', 'available' => true ),
			'wordpress_native' => array( 'label' => 'WordPress Native Forms', 'class' => 'Elbishion_Integration_WordPress_Native', 'available' => true ),
			'custom_html'      => array( 'label' => 'Custom HTML Capture', 'class' => 'Elbishion_Integration_Custom_HTML', 'available' => true ),
		);

		return apply_filters( 'elbishion_supported_integrations', $integrations );
	}

	/**
	 * Initialize enabled integrations.
	 */
	public static function init() {
		foreach ( self::integrations() as $slug => $integration ) {
			$class = $integration['class'];

			if ( ! class_exists( $class ) || ! self::is_enabled( $slug ) ) {
				continue;
			}

			call_user_func( array( $class, 'init' ) );
		}
	}

	/**
	 * Is integration enabled.
	 *
	 * @param string $slug Integration slug.
	 * @return bool
	 */
	public static function is_enabled( $slug ) {
		$settings = Elbishion_Settings::get_integration_settings( $slug );

		return ! empty( $settings['enabled'] );
	}

	/**
	 * Is plugin active/detected.
	 *
	 * @param string $slug Integration slug.
	 * @return bool
	 */
	public static function is_detected( $slug ) {
		$integrations = self::integrations();

		if ( empty( $integrations[ $slug ]['class'] ) || ! class_exists( $integrations[ $slug ]['class'] ) ) {
			return false;
		}

		return (bool) call_user_func( array( $integrations[ $slug ]['class'], 'is_detected' ) );
	}

	/**
	 * Decide whether one form should be captured.
	 *
	 * @param string $slug Integration slug.
	 * @param string $form_name Form name.
	 * @param string $form_id Form ID.
	 * @return bool
	 */
	public static function should_capture_form( $slug, $form_name = '', $form_id = '' ) {
		$settings = Elbishion_Settings::get_integration_settings( $slug );

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$key_candidates = array_filter(
			array(
				strtolower( trim( (string) $form_name ) ),
				strtolower( trim( (string) $form_id ) ),
			)
		);

		$ignored = self::parse_list( $settings['ignore_forms'] ?? '' );

		if ( array_intersect( $key_candidates, $ignored ) ) {
			return false;
		}

		if ( empty( $settings['capture_all'] ) ) {
			$selected = self::parse_list( $settings['selected_forms'] ?? '' );

			if ( empty( $selected ) || ! array_intersect( $key_candidates, $selected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse newline/comma list.
	 *
	 * @param string $value List value.
	 * @return array
	 */
	private static function parse_list( $value ) {
		$items = preg_split( '/[\r\n,]+/', (string) $value );
		$items = array_filter( array_map( 'trim', (array) $items ) );

		return array_values( array_unique( array_map( 'strtolower', $items ) ) );
	}

	/**
	 * Save an integration submission.
	 *
	 * @param string $slug Integration slug.
	 * @param string $form_name Form name.
	 * @param array  $fields Fields.
	 * @param array  $meta Metadata.
	 * @return int|false
	 */
	public static function save_submission( $slug, $form_name, $fields, $meta = array() ) {
		$settings = Elbishion_Settings::get_integration_settings( $slug );

		$meta['source']        = $slug;
		$meta['source_plugin'] = $slug;

		if ( empty( $meta['source_form_name'] ) ) {
			$meta['source_form_name'] = $form_name;
		}

		if ( empty( $settings['save_ip'] ) ) {
			$meta['user_ip'] = '';
		}

		if ( empty( $settings['save_user_agent'] ) ) {
			$meta['user_agent'] = '';
		}

		return elbishion_save_submission( $form_name, $fields, $meta );
	}

	/**
	 * Current page URL helper.
	 *
	 * @return string
	 */
	public static function current_page_url() {
		return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	/**
	 * Referer helper.
	 *
	 * @return string
	 */
	public static function referer_url() {
		$referer = wp_get_referer();

		return $referer ? esc_url_raw( $referer ) : '';
	}
}
