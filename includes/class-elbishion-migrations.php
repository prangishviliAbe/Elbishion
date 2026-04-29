<?php
/**
 * Database migrations.
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles schema creation and safe upgrades.
 */
class Elbishion_Migrations {

	/**
	 * Install or upgrade the submissions table.
	 */
	public static function install_or_upgrade() {
		$installed_version = get_option( 'elbishion_db_version' );

		if ( ELBISHION_VERSION === $installed_version && self::has_required_columns() ) {
			return;
		}

		self::create_or_update_table();
		self::backfill_universal_columns();

		update_option( 'elbishion_db_version', ELBISHION_VERSION );
	}

	/**
	 * Create or update the submissions table through dbDelta.
	 */
	private static function create_or_update_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Elbishion_Database::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(50) NOT NULL DEFAULT 'api',
			source_plugin VARCHAR(50) NOT NULL DEFAULT 'api',
			source_form_id VARCHAR(191) NULL,
			source_form_name VARCHAR(191) NULL,
			form_name VARCHAR(191) NOT NULL,
			page_url TEXT NULL,
			referer_url TEXT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			user_ip VARCHAR(100) NULL,
			user_agent TEXT NULL,
			submitted_data LONGTEXT NOT NULL,
			raw_data LONGTEXT NULL,
			submission_hash VARCHAR(64) NULL,
			has_attachments TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'unread',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY source_plugin (source_plugin),
			KEY source_form_id (source_form_id),
			KEY form_name (form_name),
			KEY status (status),
			KEY created_at (created_at),
			KEY submission_hash (submission_hash),
			KEY has_attachments (has_attachments),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Backfill new universal columns for existing rows without deleting data.
	 */
	private static function backfill_universal_columns() {
		global $wpdb;

		$table = Elbishion_Database::table_name();

		if ( ! self::table_exists() ) {
			return;
		}

		$wpdb->query( "UPDATE {$table} SET source_plugin = source WHERE (source_plugin IS NULL OR source_plugin = '') AND source <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET source_plugin = 'api' WHERE source_plugin IS NULL OR source_plugin = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET source = source_plugin WHERE source IS NULL OR source = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET source_form_name = form_name WHERE source_form_name IS NULL OR source_form_name = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET referer_url = page_url WHERE (referer_url IS NULL OR referer_url = '') AND page_url <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check required columns exist.
	 *
	 * @return bool
	 */
	private static function has_required_columns() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table   = Elbishion_Database::table_name();
		$columns = $wpdb->get_col( "DESC {$table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$needed  = array( 'source_plugin', 'source_form_id', 'source_form_name', 'referer_url', 'user_id', 'raw_data', 'submission_hash', 'has_attachments' );

		return empty( array_diff( $needed, $columns ) );
	}

	/**
	 * Check table existence.
	 *
	 * @return bool
	 */
	private static function table_exists() {
		global $wpdb;

		$table = Elbishion_Database::table_name();

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
