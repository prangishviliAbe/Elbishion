<?php
/**
 * Submission capture handlers.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles shortcode submissions, notifications, and optional Elementor capture.
 */
class Elbishion_Submission_Handler {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_shortcode( 'elbishion_form', array( __CLASS__, 'render_shortcode_form' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_shortcode_submission' ) );
		add_action( 'elbishion_submission_saved', array( __CLASS__, 'maybe_send_notification' ), 10, 4 );
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'capture_elementor_submission_v2' ), 10, 2 );
	}

	/**
	 * Render the frontend shortcode form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode_form( $atts ) {
		$atts      = shortcode_atts(
			array(
				'name' => __( 'საკონტაქტო ფორმა', 'elbishion' ),
			),
			$atts,
			'elbishion_form'
		);
		$form_name = sanitize_text_field( $atts['name'] );
		$sent      = isset( $_GET['elbishion_sent'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['elbishion_sent'] ) );
		$form_id   = wp_unique_id( 'elbishion-form-' );

		ob_start();
		?>
		<form id="<?php echo esc_attr( $form_id ); ?>" class="elbishion-form" method="post">
			<style>
				#<?php echo esc_html( $form_id ); ?> {
					display: grid;
					gap: 16px;
					max-width: 680px;
				}
				#<?php echo esc_html( $form_id ); ?> p {
					margin: 0;
				}
				#<?php echo esc_html( $form_id ); ?> label {
					display: block;
					margin-bottom: 6px;
					font-weight: 700;
				}
				#<?php echo esc_html( $form_id ); ?> input,
				#<?php echo esc_html( $form_id ); ?> textarea {
					width: 100%;
					box-sizing: border-box;
					border: 1px solid #d0d5dd;
					border-radius: 8px;
					padding: 12px 14px;
					font: inherit;
				}
				#<?php echo esc_html( $form_id ); ?> button {
					border: 0;
					border-radius: 8px;
					padding: 12px 18px;
					background: #A61832;
					color: #fff;
					font-weight: 700;
					cursor: pointer;
				}
				#<?php echo esc_html( $form_id ); ?> .elbishion-form-notice {
					padding: 12px 14px;
					border-radius: 8px;
					background: #ecfdf3;
					color: #027a48;
					font-weight: 700;
				}
			</style>
			<?php if ( $sent ) : ?>
				<div class="elbishion-form-notice" role="status"><?php esc_html_e( 'გმადლობთ. თქვენი შეტყობინება მიღებულია.', 'elbishion' ); ?></div>
			<?php endif; ?>

			<input type="hidden" name="elbishion_shortcode_form" value="1">
			<input type="hidden" name="elbishion_form_name" value="<?php echo esc_attr( $form_name ); ?>">
			<?php wp_nonce_field( 'elbishion_shortcode_submit', 'elbishion_nonce' ); ?>

			<p>
				<label for="<?php echo esc_attr( $form_id . '-name' ); ?>"><?php esc_html_e( 'სახელი', 'elbishion' ); ?></label>
				<input id="<?php echo esc_attr( $form_id . '-name' ); ?>" type="text" name="elbishion_fields[name]" required>
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id . '-email' ); ?>"><?php esc_html_e( 'ელფოსტა', 'elbishion' ); ?></label>
				<input id="<?php echo esc_attr( $form_id . '-email' ); ?>" type="email" name="elbishion_fields[email]" required>
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id . '-phone' ); ?>"><?php esc_html_e( 'ტელეფონი', 'elbishion' ); ?></label>
				<input id="<?php echo esc_attr( $form_id . '-phone' ); ?>" type="tel" name="elbishion_fields[phone]">
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id . '-subject' ); ?>"><?php esc_html_e( 'თემა', 'elbishion' ); ?></label>
				<input id="<?php echo esc_attr( $form_id . '-subject' ); ?>" type="text" name="elbishion_fields[subject]">
			</p>
			<p>
				<label for="<?php echo esc_attr( $form_id . '-message' ); ?>"><?php esc_html_e( 'შეტყობინება', 'elbishion' ); ?></label>
				<textarea id="<?php echo esc_attr( $form_id . '-message' ); ?>" name="elbishion_fields[message]" rows="6" required></textarea>
			</p>
			<p>
				<button type="submit"><?php esc_html_e( 'გაგზავნა', 'elbishion' ); ?></button>
			</p>
		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Handle shortcode form posts.
	 */
	public static function maybe_handle_shortcode_submission() {
		if ( empty( $_POST['elbishion_shortcode_form'] ) ) {
			return;
		}

		if ( empty( $_POST['elbishion_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['elbishion_nonce'] ) ), 'elbishion_shortcode_submit' ) ) {
			wp_die( esc_html__( 'ფორმის გაგზავნა არასწორია.', 'elbishion' ) );
		}

		$raw_fields = isset( $_POST['elbishion_fields'] ) && is_array( $_POST['elbishion_fields'] ) ? wp_unslash( $_POST['elbishion_fields'] ) : array();
		$form_name  = isset( $_POST['elbishion_form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['elbishion_form_name'] ) ) : __( 'საკონტაქტო ფორმა', 'elbishion' );
		$fields     = array(
			'name'    => sanitize_text_field( $raw_fields['name'] ?? '' ),
			'email'   => sanitize_email( $raw_fields['email'] ?? '' ),
			'phone'   => sanitize_text_field( $raw_fields['phone'] ?? '' ),
			'subject' => sanitize_text_field( $raw_fields['subject'] ?? '' ),
			'message' => sanitize_textarea_field( $raw_fields['message'] ?? '' ),
		);

		if ( empty( $fields['name'] ) || empty( $fields['email'] ) || ! is_email( $fields['email'] ) || empty( $fields['message'] ) ) {
			wp_die( esc_html__( 'გთხოვთ, სავალდებულო ველები სწორად შეავსოთ.', 'elbishion' ) );
		}

		elbishion_save_submission(
			$form_name,
			$fields,
			array(
				'page_url' => wp_get_referer(),
				'source'   => 'shortcode',
			)
		);

		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( esc_url_raw( add_query_arg( 'elbishion_sent', '1', $redirect ) ) );
		exit;
	}

	/**
	 * Capture Elementor Pro Forms if Elementor Pro is present.
	 *
	 * @param object $record  Elementor record.
	 * @param object $handler Elementor handler.
	 */
	public static function capture_elementor_submission( $record, $handler ) {
		unset( $handler );

		self::capture_elementor_submission_v2( $record, null );
		return;

		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$form_name = method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'form_name' ) : '';
		$form_name = $form_name ? $form_name : __( 'Elementor ფორმა', 'elbishion' );
		$fields    = $record->get( 'fields' );
		$data      = array();

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$label          = ! empty( $field['title'] ) ? $field['title'] : ( $field['id'] ?? __( 'ველი', 'elbishion' ) );
				$data[ $label ] = $field['value'] ?? '';
			}
		}

		if ( empty( $data ) ) {
			return;
		}

		elbishion_save_submission(
			$form_name,
			$data,
			array(
				'page_url' => wp_get_referer(),
			)
		);
	}

	/**
	 * Capture Elementor Pro Forms without interfering with native Elementor actions.
	 *
	 * @param object $record  Elementor record.
	 * @param object $handler Elementor handler.
	 */
	public static function capture_elementor_submission_v2( $record, $handler ) {
		unset( $handler );

		$settings = Elbishion_Settings::get_settings();

		if ( empty( $settings['elementor_capture'] ) ) {
			return;
		}

		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) ) {
			return;
		}

		$form_name = self::elementor_form_name( $record );

		if ( ! self::should_capture_elementor_form( $form_name, $settings ) ) {
			return;
		}

		$fields = $record->get( 'fields' );
		$data   = array(
			'form'   => array(
				'name' => $form_name,
			),
			'fields' => array(),
		);

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field_key => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$field_id = ! empty( $field['id'] ) ? $field['id'] : $field_key;
				$label    = ! empty( $field['title'] ) ? $field['title'] : $field_id;

				if ( empty( $label ) ) {
					$label = __( 'Field', 'elbishion' );
				}

				$data['fields'][] = array(
					'id'    => $field_id,
					'label' => $label,
					'value' => self::normalize_elementor_field_value( $field['value'] ?? '' ),
				);
			}
		}

		if ( empty( $data['fields'] ) ) {
			return;
		}

		elbishion_save_submission(
			$form_name,
			$data,
			array(
				'page_url' => self::elementor_page_url( $record ),
				'source'   => 'elementor',
			)
		);
	}

	/**
	 * Get a stable Elementor form name.
	 *
	 * @param object $record Elementor record.
	 * @return string
	 */
	private static function elementor_form_name( $record ) {
		$form_name = method_exists( $record, 'get_form_settings' ) ? $record->get_form_settings( 'form_name' ) : '';
		$form_name = sanitize_text_field( (string) $form_name );

		return $form_name ? $form_name : __( 'Elementor Form', 'elbishion' );
	}

	/**
	 * Check Elementor capture settings for one form name.
	 *
	 * @param string $form_name Form name.
	 * @param array  $settings Plugin settings.
	 * @return bool
	 */
	private static function should_capture_elementor_form( $form_name, $settings ) {
		if ( empty( $settings['elementor_selected'] ) ) {
			return true;
		}

		$allowlist = self::parse_elementor_allowlist( $settings['elementor_allowlist'] ?? '' );

		if ( empty( $allowlist ) ) {
			return false;
		}

		return in_array( strtolower( trim( $form_name ) ), $allowlist, true );
	}

	/**
	 * Parse selected Elementor form names.
	 *
	 * @param string $allowlist Raw allowlist.
	 * @return array
	 */
	private static function parse_elementor_allowlist( $allowlist ) {
		$items = preg_split( '/[\r\n,]+/', (string) $allowlist );
		$items = array_filter( array_map( 'trim', (array) $items ) );

		return array_values( array_unique( array_map( 'strtolower', $items ) ) );
	}

	/**
	 * Keep Elementor field values JSON-safe.
	 *
	 * @param mixed $value Field value.
	 * @return mixed
	 */
	private static function normalize_elementor_field_value( $value ) {
		if ( is_scalar( $value ) || is_array( $value ) || null === $value ) {
			return $value;
		}

		return '';
	}

	/**
	 * Resolve the Elementor submission page URL without depending on Elementor internals.
	 *
	 * @param object $record Elementor record.
	 * @return string
	 */
	private static function elementor_page_url( $record ) {
		$meta = $record->get( 'meta' );

		if ( is_array( $meta ) ) {
			foreach ( array( 'page_url', 'referer', 'referrer' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) && is_scalar( $meta[ $key ] ) ) {
					return esc_url_raw( $meta[ $key ] );
				}
			}
		}

		$referer = wp_get_referer();

		if ( $referer ) {
			return esc_url_raw( $referer );
		}

		return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	/**
	 * Send optional email notification.
	 *
	 * @param int    $submission_id Submitted row ID.
	 * @param string $form_name Form name.
	 * @param array  $submitted_data Submitted data.
	 * @param array  $args Metadata.
	 */
	public static function maybe_send_notification( $submission_id, $form_name, $submitted_data, $args ) {
		$settings = Elbishion_Settings::get_settings();

		if ( empty( $settings['email_notifications'] ) || empty( $settings['notification_email'] ) || ! is_email( $settings['notification_email'] ) ) {
			return;
		}

		$page_url = ! empty( $args['page_url'] ) ? esc_url_raw( $args['page_url'] ) : '';
		$subject  = sprintf(
			/* translators: %s: form name. */
			__( 'ახალი Elbishion განაცხადი: %s', 'elbishion' ),
			$form_name
		);
		$lines    = array(
			sprintf( __( 'ფორმა: %s', 'elbishion' ), $form_name ),
			sprintf( __( 'თარიღი: %s', 'elbishion' ), current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			sprintf( __( 'განაცხადის ID: %d', 'elbishion' ), absint( $submission_id ) ),
		);

		if ( $page_url ) {
			$lines[] = sprintf( __( 'გვერდის ბმული: %s', 'elbishion' ), $page_url );
		}

		$lines[] = '';
		$lines[] = __( 'შევსებული ველები:', 'elbishion' );

		foreach ( $submitted_data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}

			$lines[] = sprintf( '%s: %s', sanitize_text_field( $key ), sanitize_textarea_field( (string) $value ) );
		}

		wp_mail( $settings['notification_email'], $subject, implode( "\n", $lines ) );
	}
}
