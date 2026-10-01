<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/geonolis
 * @since             1.0.0
 * @package           Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 *
 * @wordpress-plugin
 * Plugin Name:       Geniki Taxydromiki Woo Vouchers v.3
 * Plugin URI:        https://github.com/geonolis/create-geniki-taxydromiki-vouchers-for-woo
 * Description:       This is a description of the plugin.
 * Version:           1.0.0
 * Author:            Γεώργιος Παπαμανώλης
 * Author URI:        https://github.com/geonolis/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       create-geniki-taxydromiki-vouchers-for-woo-v3
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CREATE_GENIKI_TAXYDROMIKI_VOUCHERS_FOR_WOO_V3_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-activator.php
 */
function activate_create_geniki_taxydromiki_vouchers_for_woo_v3() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-activator.php';
	Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-deactivator.php
 */
function deactivate_create_geniki_taxydromiki_vouchers_for_woo_v3() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-deactivator.php';
	Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_create_geniki_taxydromiki_vouchers_for_woo_v3' );
register_deactivation_hook( __FILE__, 'deactivate_create_geniki_taxydromiki_vouchers_for_woo_v3' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_create_geniki_taxydromiki_vouchers_for_woo_v3() {

	$plugin = new Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3();
	$plugin->run();

}
run_create_geniki_taxydromiki_vouchers_for_woo_v3();
