<?php
/**
 * Submission normalization.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts different form plugin payloads into one storage shape.
 */
class Elbishion_Normalizer {

	/**
	 * Normalize a submission before storage.
	 *
	 * @param array $submission Raw submission args.
	 * @return array
	 */
	public static function normalize_submission( $submission ) {
		$settings = Elbishion_Settings::get_settings();
		$fields   = self::normalize_fields( $submission['fields'] ?? array(), $settings );
		$raw_data = ! empty( $settings['store_raw_data'] ) ? self::sanitize_raw_data( $submission['raw_data'] ?? array(), $settings ) : array();

		$form_name = sanitize_text_field( (string) ( $submission['form_name'] ?? '' ) );
		$form_name = '' !== $form_name ? $form_name : __( 'Untitled Form', 'elbishion' );

		$source_plugin = sanitize_key( (string) ( $submission['source_plugin'] ?? ( $submission['source'] ?? 'api' ) ) );
		$source_plugin = '' !== $source_plugin ? $source_plugin : 'api';

		$page_url    = isset( $submission['page_url'] ) ? esc_url_raw( $submission['page_url'] ) : self::current_page_url();
		$referer_url = isset( $submission['referer_url'] ) ? esc_url_raw( $submission['referer_url'] ) : self::referer_url();
		$user_id     = isset( $submission['user_id'] ) ? absint( $submission['user_id'] ) : get_current_user_id();
		$user_ip     = isset( $submission['user_ip'] ) ? $submission['user_ip'] : self::current_ip();
		$user_agent  = isset( $submission['user_agent'] ) ? $submission['user_agent'] : self::current_user_agent();

		$normalized = array(
			'source'           => $source_plugin,
			'source_plugin'    => $source_plugin,
			'source_form_id'   => sanitize_text_field( (string) ( $submission['source_form_id'] ?? '' ) ),
			'source_form_name' => sanitize_text_field( (string) ( $submission['source_form_name'] ?? $form_name ) ),
			'form_name'        => $form_name,
			'page_url'         => $page_url,
			'referer_url'      => $referer_url,
			'user_id'          => $user_id,
			'user_ip'          => Elbishion_Privacy::prepare_ip( $user_ip, $settings ),
			'user_agent'       => Elbishion_Privacy::prepare_user_agent( $user_agent, $settings ),
			'submitted_data'   => array(
				'fields'      => $fields,
				'attachments' => self::extract_attachments( $fields ),
			),
			'raw_data'         => $raw_data,
			'status'           => self::normalize_status( $submission['status'] ?? 'unread' ),
			'has_attachments'  => self::has_attachments( $fields ) ? 1 : 0,
		);

		$normalized['submission_hash'] = self::submission_hash( $normalized, $settings );

		return apply_filters( 'elbishion_submission_data_before_save', $normalized );
	}

	/**
	 * Normalize fields into readable objects.
	 *
	 * @param mixed $fields Raw fields.
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public static function normalize_fields( $fields, $settings = array() ) {
		$normalized = array();

		if ( ! is_array( $fields ) ) {
			return $normalized;
		}

		foreach ( $fields as $key => $field ) {
			$item = self::normalize_field( $key, $field );

			if ( Elbishion_Privacy::should_exclude_field( $item['field_id'], $item['field_label'], $item['field_type'], $settings ) ) {
				continue;
			}

			$normalized[] = $item;
		}

		return $normalized;
	}

	/**
	 * Normalize one field.
	 *
	 * @param string|int $key Field key.
	 * @param mixed      $field Field payload.
	 * @return array
	 */
	private static function normalize_field( $key, $field ) {
		if ( is_array( $field ) && ( isset( $field['field_id'] ) || isset( $field['id'] ) || isset( $field['label'] ) || isset( $field['value'] ) ) ) {
			$field_id = $field['field_id'] ?? ( $field['id'] ?? $key );
			$label    = $field['field_label'] ?? ( $field['label'] ?? ( $field['name'] ?? $field_id ) );
			$type     = $field['field_type'] ?? ( $field['type'] ?? self::detect_field_type( $field['value'] ?? '' ) );
			$value    = $field['field_value'] ?? ( $field['value'] ?? '' );
		} else {
			$field_id = $key;
			$label    = self::humanize_label( $key );
			$type     = self::detect_field_type( $field );
			$value    = $field;
		}

		return array(
			'field_id'    => sanitize_text_field( (string) $field_id ),
			'field_label' => sanitize_text_field( self::humanize_label( $label ) ),
			'field_type'  => sanitize_key( (string) $type ),
			'field_value' => self::sanitize_value( $value ),
		);
	}

	/**
	 * Make technical keys readable.
	 *
	 * @param string|int $value Label/key.
	 * @return string
	 */
	public static function humanize_label( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return __( 'Field', 'elbishion' );
		}

		$value = str_replace( array( '_', '-' ), ' ', $value );
		$value = preg_replace( '/\s+/', ' ', $value );

		return ucwords( $value );
	}

	/**
	 * Detect field type from value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function detect_field_type( $value ) {
		if ( self::is_file_value( $value ) ) {
			return 'file';
		}

		if ( is_array( $value ) ) {
			return 'array';
		}

		if ( is_email( (string) $value ) ) {
			return 'email';
		}

		return 'text';
	}

	/**
	 * Sanitize a field value.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sanitize_value( $value ) {
		if ( self::is_file_value( $value ) ) {
			return self::sanitize_file_value( $value );
		}

		if ( is_array( $value ) ) {
			$clean = array();

			foreach ( $value as $key => $item ) {
				$clean[ sanitize_text_field( (string) $key ) ] = self::sanitize_value( $item );
			}

			return $clean;
		}

		if ( is_scalar( $value ) || null === $value ) {
			return sanitize_textarea_field( (string) $value );
		}

		return '';
	}

	/**
	 * Detect file metadata arrays.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_file_value( $value ) {
		return is_array( $value ) && ( isset( $value['url'] ) || isset( $value['path'] ) || isset( $value['file'] ) || isset( $value['name'] ) && isset( $value['tmp_name'] ) );
	}

	/**
	 * Sanitize file metadata.
	 *
	 * @param array $value File metadata.
	 * @return array
	 */
	private static function sanitize_file_value( $value ) {
		return array(
			'name' => isset( $value['name'] ) ? sanitize_file_name( $value['name'] ) : '',
			'url'  => isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '',
			'path' => isset( $value['path'] ) ? sanitize_text_field( $value['path'] ) : ( isset( $value['file'] ) ? sanitize_text_field( $value['file'] ) : '' ),
			'type' => isset( $value['type'] ) ? sanitize_mime_type( $value['type'] ) : '',
			'size' => isset( $value['size'] ) ? absint( $value['size'] ) : 0,
		);
	}

	/**
	 * Extract attachment fields.
	 *
	 * @param array $fields Normalized fields.
	 * @return array
	 */
	private static function extract_attachments( $fields ) {
		$attachments = array();

		foreach ( $fields as $field ) {
			if ( 'file' === $field['field_type'] ) {
				$attachments[] = array(
					'field_id'    => $field['field_id'],
					'field_label' => $field['field_label'],
					'file'        => $field['field_value'],
				);
			}
		}

		return $attachments;
	}

	/**
	 * Check whether fields include attachments.
	 *
	 * @param array $fields Normalized fields.
	 * @return bool
	 */
	private static function has_attachments( $fields ) {
		foreach ( $fields as $field ) {
			if ( 'file' === $field['field_type'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitize raw data recursively.
	 *
	 * @param mixed $data Raw data.
	 * @param array $settings Settings.
	 * @return mixed
	 */
	private static function sanitize_raw_data( $data, $settings = array() ) {
		if ( is_array( $data ) ) {
			$clean = array();

			foreach ( $data as $key => $value ) {
				if ( Elbishion_Privacy::should_exclude_field( $key, $key, '', $settings ) ) {
					continue;
				}

				$clean[ sanitize_text_field( (string) $key ) ] = self::sanitize_raw_data( $value, $settings );
			}

			return $clean;
		}

		if ( is_scalar( $data ) || null === $data ) {
			return sanitize_textarea_field( (string) $data );
		}

		return '';
	}

	/**
	 * Normalize status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function normalize_status( $status ) {
		$status = sanitize_key( $status );

		return in_array( $status, Elbishion_Database::ALLOWED_STATUSES, true ) ? $status : 'unread';
	}

	/**
	 * Build duplicate-prevention hash.
	 *
	 * @param array $submission Normalized submission.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function submission_hash( $submission, $settings = array() ) {
		$window = ! empty( $settings['duplicate_window'] ) ? max( 1, absint( $settings['duplicate_window'] ) ) : 90;
		$bucket = floor( time() / $window );

		$payload = array(
			'source_plugin'  => $submission['source_plugin'],
			'source_form_id' => $submission['source_form_id'],
			'page_url'       => $submission['page_url'],
			'fields'         => $submission['submitted_data']['fields'],
			'bucket'         => $bucket,
		);

		return hash( 'sha256', wp_json_encode( $payload ) );
	}

	/**
	 * Current request URL.
	 *
	 * @return string
	 */
	private static function current_page_url() {
		return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	/**
	 * Current referer URL.
	 *
	 * @return string
	 */
	private static function referer_url() {
		$referer = wp_get_referer();

		return $referer ? esc_url_raw( $referer ) : '';
	}

	/**
	 * Current IP.
	 *
	 * @return string
	 */
	private static function current_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Current user agent.
	 *
	 * @return string
	 */
	private static function current_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}
}
