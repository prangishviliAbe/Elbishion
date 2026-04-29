<?php
/**
 * Settings page and option sanitization.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Elbishion settings.
 */
class Elbishion_Settings {

	/**
	 * Initialize settings hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'init', array( __CLASS__, 'maybe_cleanup_old_submissions' ) );
	}

	/**
	 * Run lightweight retention cleanup once per day.
	 */
	public static function maybe_cleanup_old_submissions() {
		$settings = self::get_settings();
		$days     = absint( $settings['retention_days'] ?? 0 );

		if ( 0 === $days || get_transient( 'elbishion_retention_cleanup' ) ) {
			return;
		}

		Elbishion_Database::delete_older_than( $days );
		set_transient( 'elbishion_retention_cleanup', 1, DAY_IN_SECONDS );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'save_ip'              => 1,
			'save_user_agent'      => 1,
			'delete_on_uninstall'  => 0,
			'items_per_page'       => 20,
			'email_notifications'  => 0,
			'notification_email'   => get_option( 'admin_email' ),
			'duplicate_prevention' => 1,
			'duplicate_window'     => 90,
			'mask_ip'              => 0,
			'retention_days'       => 0,
			'store_raw_data'       => 0,
			'exclude_hidden_fields' => 0,
			'exclude_field_keywords' => implode( "\n", Elbishion_Privacy::default_sensitive_keywords() ),
			'integrations'         => self::default_integrations(),
			'elementor_capture'    => 1,
			'elementor_selected'   => 0,
			'elementor_allowlist'  => '',
		);
	}

	/**
	 * Current merged settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( 'elbishion_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_defaults() );
	}

	/**
	 * Default integration settings.
	 *
	 * @return array
	 */
	public static function default_integrations() {
		$defaults = array();

		foreach ( array( 'elementor', 'contact_form_7', 'wpforms', 'gravity_forms', 'fluent_forms', 'ninja_forms', 'formidable_forms', 'jetformbuilder', 'forminator', 'woocommerce', 'wordpress_native', 'custom_html' ) as $slug ) {
			$defaults[ $slug ] = array(
				'enabled'             => in_array( $slug, array( 'wordpress_native', 'woocommerce' ), true ) ? 0 : 1,
				'capture_all'         => 1,
				'selected_forms'      => '',
				'ignore_forms'        => '',
				'save_ip'             => 1,
				'save_user_agent'     => 1,
				'email_notifications' => 0,
				'capture_checkout'    => 0,
			);
		}

		return $defaults;
	}

	/**
	 * Get settings for one integration merged with defaults.
	 *
	 * @param string $slug Integration slug.
	 * @return array
	 */
	public static function get_integration_settings( $slug ) {
		$settings     = self::get_settings();
		$defaults     = self::default_integrations();
		$slug         = sanitize_key( $slug );
		$integration  = isset( $settings['integrations'][ $slug ] ) && is_array( $settings['integrations'][ $slug ] ) ? $settings['integrations'][ $slug ] : array();
		$base         = isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : array();

		return wp_parse_args( $integration, $base );
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			'elbishion_settings_group',
			'elbishion_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_defaults();
		$email    = isset( $input['notification_email'] ) ? sanitize_email( wp_unslash( $input['notification_email'] ) ) : $defaults['notification_email'];

		if ( empty( $email ) || ! is_email( $email ) ) {
			$email = $defaults['notification_email'];
		}

		return array(
			'save_ip'              => empty( $input['save_ip'] ) ? 0 : 1,
			'save_user_agent'      => empty( $input['save_user_agent'] ) ? 0 : 1,
			'delete_on_uninstall'  => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
			'items_per_page'       => min( 100, max( 5, absint( $input['items_per_page'] ?? $defaults['items_per_page'] ) ) ),
			'email_notifications'  => empty( $input['email_notifications'] ) ? 0 : 1,
			'notification_email'   => $email,
			'duplicate_prevention' => empty( $input['duplicate_prevention'] ) ? 0 : 1,
			'duplicate_window'     => min( 3600, max( 1, absint( $input['duplicate_window'] ?? $defaults['duplicate_window'] ) ) ),
			'mask_ip'              => empty( $input['mask_ip'] ) ? 0 : 1,
			'retention_days'       => max( 0, absint( $input['retention_days'] ?? 0 ) ),
			'store_raw_data'       => empty( $input['store_raw_data'] ) ? 0 : 1,
			'exclude_hidden_fields' => empty( $input['exclude_hidden_fields'] ) ? 0 : 1,
			'exclude_field_keywords' => isset( $input['exclude_field_keywords'] ) ? self::sanitize_allowlist( $input['exclude_field_keywords'] ) : $defaults['exclude_field_keywords'],
			'integrations'         => isset( $input['integrations'] ) ? self::sanitize_integrations( $input['integrations'] ) : ( self::get_settings()['integrations'] ?? self::default_integrations() ),
			'elementor_capture'    => empty( $input['elementor_capture'] ) ? 0 : 1,
			'elementor_selected'   => empty( $input['elementor_selected'] ) ? 0 : 1,
			'elementor_allowlist'  => isset( $input['elementor_allowlist'] ) ? self::sanitize_allowlist( $input['elementor_allowlist'] ) : '',
		);
	}

	/**
	 * Normalize a comma/newline separated form name allowlist.
	 *
	 * @param string $allowlist Raw allowlist.
	 * @return string
	 */
	private static function sanitize_allowlist( $allowlist ) {
		$allowlist = sanitize_textarea_field( wp_unslash( $allowlist ) );
		$items     = preg_split( '/[\r\n,]+/', $allowlist );
		$items     = array_filter( array_map( 'trim', (array) $items ) );

		return implode( "\n", array_unique( $items ) );
	}

	/**
	 * Sanitize integration settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	private static function sanitize_integrations( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::default_integrations();
		$clean    = array();

		foreach ( $defaults as $slug => $settings ) {
			$row = isset( $input[ $slug ] ) && is_array( $input[ $slug ] ) ? $input[ $slug ] : array();

			$clean[ $slug ] = array(
				'enabled'             => empty( $row['enabled'] ) ? 0 : 1,
				'capture_all'         => empty( $row['capture_all'] ) ? 0 : 1,
				'selected_forms'      => isset( $row['selected_forms'] ) ? self::sanitize_allowlist( $row['selected_forms'] ) : '',
				'ignore_forms'        => isset( $row['ignore_forms'] ) ? self::sanitize_allowlist( $row['ignore_forms'] ) : '',
				'save_ip'             => empty( $row['save_ip'] ) ? 0 : 1,
				'save_user_agent'     => empty( $row['save_user_agent'] ) ? 0 : 1,
				'email_notifications' => empty( $row['email_notifications'] ) ? 0 : 1,
				'capture_checkout'    => empty( $row['capture_checkout'] ) ? 0 : 1,
			);
		}

		return $clean;
	}

	/**
	 * Render settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'ამ გვერდზე წვდომის უფლება არ გაქვთ.', 'elbishion' ) );
		}

		$settings = self::get_settings();
		?>
		<div class="wrap elbishion-admin elbishion-settings-page">
			<div class="elbishion-page-header">
				<div>
					<h1><?php esc_html_e( 'Elbishion პარამეტრები', 'elbishion' ); ?></h1>
					<p><?php esc_html_e( 'მართეთ მონაცემების შენახვა, კონფიდენციალურობა, გვერდებად დაყოფა და შეტყობინებები.', 'elbishion' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php" class="elbishion-card elbishion-settings-card">
				<?php settings_fields( 'elbishion_settings_group' ); ?>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-save-ip"><?php esc_html_e( 'IP მისამართების შენახვა', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'თითოეულ განაცხადთან ერთად შეინახება გამომგზავნის IP მისამართი.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-save-ip" type="checkbox" name="elbishion_settings[save_ip]" value="1" <?php checked( $settings['save_ip'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-save-agent"><?php esc_html_e( 'ბრაუზერის ინფორმაციის შენახვა', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'შეინახება ბრაუზერის user-agent ინფორმაცია კონტექსტისა და დიაგნოსტიკისთვის.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-save-agent" type="checkbox" name="elbishion_settings[save_user_agent]" value="1" <?php checked( $settings['save_user_agent'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-delete-uninstall"><?php esc_html_e( 'წაშლა დეინსტალაციისას', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'პლაგინის წაშლისას Elbishion-ის განაცხადები და პარამეტრები სამუდამოდ წაიშლება.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-delete-uninstall" type="checkbox" name="elbishion_settings[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-items-per-page"><?php esc_html_e( 'ჩანაწერები ერთ გვერდზე', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'რამდენი განაცხადი გამოჩნდეს ადმინისტრირების ერთ გვერდზე.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-items-per-page" type="number" min="5" max="100" name="elbishion_settings[items_per_page]" value="<?php echo esc_attr( $settings['items_per_page'] ); ?>">
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-email-notifications"><?php esc_html_e( 'ელფოსტის შეტყობინებები', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'ახალი განაცხადის შენახვისას გაიგზავნება ელფოსტის შეტყობინება.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-email-notifications" type="checkbox" name="elbishion_settings[email_notifications]" value="1" <?php checked( $settings['email_notifications'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-elementor-capture"><?php esc_html_e( 'Enable Elementor Forms Capture', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Automatically save Elementor Pro form submissions into Elbishion without changing Elementor emails, redirects, webhooks, or other actions.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-elementor-capture" type="checkbox" name="elbishion_settings[elementor_capture]" value="1" <?php checked( $settings['elementor_capture'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-elementor-selected"><?php esc_html_e( 'Capture only selected Elementor forms', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'When enabled, only Elementor forms whose form name appears in the allowlist below will be captured.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-elementor-selected" type="checkbox" name="elbishion_settings[elementor_selected]" value="1" <?php checked( $settings['elementor_selected'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-elementor-allowlist"><?php esc_html_e( 'Elementor form name allowlist', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Enter one Elementor form name per line, or separate names with commas.', 'elbishion' ); ?></p>
					</div>
					<textarea id="elbishion-elementor-allowlist" name="elbishion_settings[elementor_allowlist]" rows="5"><?php echo esc_textarea( $settings['elementor_allowlist'] ); ?></textarea>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-notification-email"><?php esc_html_e( 'შეტყობინების ელფოსტა', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'მისამართი, რომელზეც ახალი განაცხადის შეტყობინება გაიგზავნება.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-notification-email" type="email" name="elbishion_settings[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>">
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-duplicate-prevention"><?php esc_html_e( 'Duplicate prevention', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Prevents the same submission from being stored multiple times when several hooks fire for one event.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-duplicate-prevention" type="checkbox" name="elbishion_settings[duplicate_prevention]" value="1" <?php checked( $settings['duplicate_prevention'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-duplicate-window"><?php esc_html_e( 'Duplicate time window in seconds', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Submissions with the same hash inside this time window are ignored.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-duplicate-window" type="number" min="1" max="3600" name="elbishion_settings[duplicate_window]" value="<?php echo esc_attr( $settings['duplicate_window'] ); ?>">
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-mask-ip"><?php esc_html_e( 'Mask IP address', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Stores a partially masked IP when IP saving is enabled.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-mask-ip" type="checkbox" name="elbishion_settings[mask_ip]" value="1" <?php checked( $settings['mask_ip'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-retention-days"><?php esc_html_e( 'Automatically delete old submissions after X days', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Set 0 to disable automatic retention cleanup.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-retention-days" type="number" min="0" name="elbishion_settings[retention_days]" value="<?php echo esc_attr( $settings['retention_days'] ); ?>">
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-store-raw-data"><?php esc_html_e( 'Store raw data', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Stores sanitized raw plugin payloads for troubleshooting. Leave disabled unless needed.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-store-raw-data" type="checkbox" name="elbishion_settings[store_raw_data]" value="1" <?php checked( $settings['store_raw_data'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-exclude-hidden-fields"><?php esc_html_e( 'Exclude hidden fields', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'Sensitive fields such as passwords, card numbers, CVV, tokens, and secrets are always excluded by keyword.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-exclude-hidden-fields" type="checkbox" name="elbishion_settings[exclude_hidden_fields]" value="1" <?php checked( $settings['exclude_hidden_fields'], 1 ); ?>>
				</div>

				<div class="elbishion-setting-row">
					<div>
						<label for="elbishion-exclude-field-keywords"><?php esc_html_e( 'Exclude fields by keywords', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'One keyword per line or comma-separated.', 'elbishion' ); ?></p>
					</div>
					<textarea id="elbishion-exclude-field-keywords" name="elbishion_settings[exclude_field_keywords]" rows="5"><?php echo esc_textarea( $settings['exclude_field_keywords'] ); ?></textarea>
				</div>

				<?php submit_button( __( 'პარამეტრების შენახვა', 'elbishion' ), 'primary elbishion-primary-button' ); ?>
			</form>
		</div>
		<?php
	}
}
