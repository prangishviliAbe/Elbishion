<?php
/**
 * Plugin Name: Elbishion
 * Description: Universal WordPress form submissions manager with a standalone inbox, integrations, CSV export, privacy controls, and developer APIs.
 * Version: 2.0.2
 * Author: Abe Prangishvili
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elbishion
 * Domain Path: /languages
 *
 * @package Elbishion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELBISHION_VERSION', '2.0.2' );
define( 'ELBISHION_PLUGIN_FILE', __FILE__ );
define( 'ELBISHION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELBISHION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELBISHION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-activator.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-database.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-migrations.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-privacy.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-normalizer.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-integrations-manager.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-submission-handler.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-admin-menu.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-admin-list.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-export.php';
require_once ELBISHION_PLUGIN_DIR . 'includes/class-elbishion-settings.php';

foreach ( glob( ELBISHION_PLUGIN_DIR . 'includes/integrations/class-elbishion-integration-*.php' ) as $elbishion_integration_file ) {
	require_once $elbishion_integration_file;
}

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
 * Public helper for querying submissions.
 *
 * @param array $args Query arguments.
 * @return array
 */
function elbishion_get_submissions( $args = array() ) {
	return Elbishion_Database::get_submissions( $args );
}

/**
 * Public helper for fetching one submission.
 *
 * @param int $id Submission ID.
 * @return object|null
 */
function elbishion_get_submission( $id ) {
	return Elbishion_Database::get_submission( $id );
}

/**
 * Public helper for updating one submission status.
 *
 * @param int    $id Submission ID.
 * @param string $status Status key.
 * @return int|false
 */
function elbishion_update_submission_status( $id, $status ) {
	return Elbishion_Database::update_status( array( $id ), $status );
}

/**
 * Developer action API.
 *
 * Example:
 * do_action( 'elbishion_save_submission', 'Contact Form', array( 'email' => 'name@example.com' ) );
 *
 * @param string $form_name      Human-readable form name.
 * @param array  $submitted_data Submitted field data.
 * @param string $source         Optional source plugin.
 * @param array  $meta           Optional metadata.
 */
function elbishion_handle_save_submission_action( $form_name, $submitted_data, $source = 'api', $meta = array() ) {
	$args = is_array( $meta ) ? $meta : array();

	if ( is_string( $source ) && '' !== $source ) {
		$args['source_plugin'] = $source;
		$args['source']        = $source;
	}

	elbishion_save_submission( $form_name, $submitted_data, $args );
}
add_action( 'elbishion_save_submission', 'elbishion_handle_save_submission_action', 10, 4 );

/**
 * Boot the plugin after WordPress is ready.
 */
function elbishion_bootstrap() {
	load_plugin_textdomain( 'elbishion', false, dirname( ELBISHION_PLUGIN_BASENAME ) . '/languages' );

	Elbishion_Activator::maybe_upgrade();
	Elbishion_Settings::init();
	Elbishion_Integrations_Manager::init();
	Elbishion_Submission_Handler::init();

	if ( is_admin() ) {
		Elbishion_Admin_Menu::init();
		Elbishion_Export::init();
	}
}
add_action( 'plugins_loaded', 'elbishion_bootstrap' );
