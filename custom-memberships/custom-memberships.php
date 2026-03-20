<?php
/**
 * Plugin Name: Custom Memberships
 * Plugin URI:  https://github.com/MikohMick/customsubscriptions
 * Description: Membership management with session tracking, WooCommerce checkout, REST API, and automated renewal emails.
 * Version:     1.0.0
 * Author:      MikohMick
 * Text Domain: custom-memberships
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CM_VERSION', '1.0.0' );
define( 'CM_PLUGIN_FILE', __FILE__ );
define( 'CM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CM_TABLE_PACKAGES', $GLOBALS['wpdb']->prefix . 'cm_packages' );
define( 'CM_TABLE_MEMBERS',  $GLOBALS['wpdb']->prefix . 'cm_members' );
define( 'CM_TABLE_API_KEYS', $GLOBALS['wpdb']->prefix . 'cm_api_keys' );

// Autoload includes.
foreach ( [
    'class-database',
    'class-packages',
    'class-memberships',
    'class-woocommerce',
    'class-frontend',
    'class-api',
    'class-emails',
] as $file ) {
    require_once CM_PLUGIN_DIR . 'includes/' . $file . '.php';
}

require_once CM_PLUGIN_DIR . 'admin/class-admin.php';

register_activation_hook( __FILE__, [ 'CM_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'CM_Database', 'deactivate' ] );

add_action( 'plugins_loaded', 'cm_init', 20 );

function cm_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Custom Memberships</strong> requires WooCommerce to be active.</p></div>';
        } );
        return;
    }

    CM_Packages::init();
    CM_Memberships::init();
    CM_WooCommerce::init();
    CM_Frontend::init();
    CM_API::init();
    CM_Emails::init();
    CM_Admin::init();
}
