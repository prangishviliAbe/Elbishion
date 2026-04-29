<?php
/**
 * Plugin Name: Elbishion
 * Description: Standalone form submissions manager with shortcode, developer API, CSV export, and optional Elementor Pro Forms capture.
 * Version: 1.0.0
 * Author: Abe Prangishvili
 * Text Domain: elbishion
 * Domain Path: /languages
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELBISHION_VERSION', '1.0.0' );
define( 'ELBISHION_PLUGIN_FILE', __FILE__ );
define( 'ELBISHION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELBISHION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELBISHION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-activator.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-database.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-submission-handler.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-admin-menu.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-admin-list.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-export.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-settings.php';

register_activation_hook( __FILE__, array( 'Elbishion_Activator', 'activate' ) );

/**
 * Public helper for developers who prefer a function call.
 *
 * @param string $form_name      Human-readable form name.
 * @param array  $submitted_data Submitted field data.
 * @param array  $args           Optional metadata such as page_url, user_ip, user_agent, status.
 * @return int|false Insert ID on success, false on failure.
 */
function elbishion_save_submission( $form_name, $submitted_data, $args = array() ) {
	return Elbishion_Database::insert_submission( $form_name, $submitted_data, $args );
}

/**
 * Developer action API.
 *
 * Example:
 * do_action( 'elbishion_save_submission', 'Contact Form', array( 'email' => 'name@example.com' ) );
 *
 * @param string $form_name      Human-readable form name.
 * @param array  $submitted_data Submitted field data.
 */
function elbishion_handle_save_submission_action( $form_name, $submitted_data ) {
	elbishion_save_submission( $form_name, $submitted_data );
}
add_action( 'elbishion_save_submission', 'elbishion_handle_save_submission_action', 10, 2 );

/**
 * Boot the plugin after WordPress is ready.
 */
function elbishion_bootstrap() {
	load_plugin_textdomain( 'elbishion', false, dirname( ELBISHION_PLUGIN_BASENAME ) . '/languages' );

	Elbishion_Settings::init();
	Elbishion_Submission_Handler::init();

	if ( is_admin() ) {
		Elbishion_Admin_Menu::init();
		Elbishion_Export::init();
	}
}
add_action( 'plugins_loaded', 'elbishion_bootstrap' );
