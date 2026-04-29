<?php
/**
 * Plugin activation routines.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates required database structures and default options.
 */
class Elbishion_Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		self::create_table();

		if ( false === get_option( 'elbishion_settings' ) ) {
			add_option( 'elbishion_settings', Elbishion_Settings::get_defaults() );
		}

		update_option( 'elbishion_db_version', ELBISHION_VERSION );
	}

	/**
	 * Run lightweight database upgrades when the plugin version changes.
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'elbishion_db_version' );

		if ( ELBISHION_VERSION === $installed_version ) {
			return;
		}

		self::create_table();
		update_option( 'elbishion_db_version', ELBISHION_VERSION );
	}

	/**
	 * Create the submissions table.
	 */
	private static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Elbishion_Database::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_name VARCHAR(191) NOT NULL,
			page_url TEXT NULL,
			user_ip VARCHAR(100) NULL,
			user_agent TEXT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'api',
			submitted_data LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'unread',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY form_name (form_name),
			KEY source (source),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
