<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package Elbishion
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'elbishion_settings', array() );

if ( is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] ) ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'elbishion_submissions';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	delete_option( 'elbishion_settings' );
	delete_option( 'elbishion_db_version' );
}
