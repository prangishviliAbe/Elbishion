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
		);
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
						<label for="elbishion-notification-email"><?php esc_html_e( 'შეტყობინების ელფოსტა', 'elbishion' ); ?></label>
						<p><?php esc_html_e( 'მისამართი, რომელზეც ახალი განაცხადის შეტყობინება გაიგზავნება.', 'elbishion' ); ?></p>
					</div>
					<input id="elbishion-notification-email" type="email" name="elbishion_settings[notification_email]" value="<?php echo esc_attr( $settings['notification_email'] ); ?>">
				</div>

				<?php submit_button( __( 'პარამეტრების შენახვა', 'elbishion' ), 'primary elbishion-primary-button' ); ?>
			</form>
		</div>
		<?php
	}
}
