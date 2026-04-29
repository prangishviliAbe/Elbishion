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
		Elbishion_Migrations::install_or_upgrade();

		if ( false === get_option( 'elbishion_settings' ) ) {
			add_option( 'elbishion_settings', Elbishion_Settings::get_defaults() );
		}

		update_option( 'elbishion_db_version', ELBISHION_VERSION );
	}

	/**
	 * Run lightweight database upgrades when the plugin version changes.
	 */
	public static function maybe_upgrade() {
		Elbishion_Migrations::install_or_upgrade();
	}
}
