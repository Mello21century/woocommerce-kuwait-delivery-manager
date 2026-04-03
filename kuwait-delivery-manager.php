<?php
/**
 * Plugin Name: Woocommerce Kuwait Delivery Manager
 * Plugin URI: https://ahmedsafaa.com/
 * Description: Manage delivery areas and pricing across multiple countries from the WooCommerce admin dashboard. Full bilingual (AR/EN) support, WooCommerce checkout integration, CSV import, inline editing, WPML & Polylang compatible.
 * Version: 1.3.0
 * Author: Ahmed Safaa
 * Author URI: https://ahmedsafaa.com/
 * Text Domain: kuwait-delivery-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
const KDM_VERSION     = '1.3.0';
const KDM_PLUGIN_FILE = __FILE__;
define( 'KDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
const KDM_DB_VERSION  = '2.0'; // 2.0: two-table schema (cities + areas) with JSON columns

// ---------------------------------------------------------------------------
// Load required class files
// ---------------------------------------------------------------------------
require_once KDM_PLUGIN_DIR . 'includes/class-helper.php';
require_once KDM_PLUGIN_DIR . 'includes/class-i18n.php';
require_once KDM_PLUGIN_DIR . 'includes/class-database.php';
require_once KDM_PLUGIN_DIR . 'includes/class-admin.php';
require_once KDM_PLUGIN_DIR . 'includes/class-ajax.php';
require_once KDM_PLUGIN_DIR . 'includes/class-ajax-import.php';
require_once KDM_PLUGIN_DIR . 'includes/class-csv-importer.php';
require_once KDM_PLUGIN_DIR . 'includes/class-checkout.php';
require_once KDM_PLUGIN_DIR . 'includes/class-plugin.php';

// ---------------------------------------------------------------------------
// Declare WooCommerce HPOS compatibility
// ---------------------------------------------------------------------------
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( FeaturesUtil::class ) ) {
        FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__
        );
    }
} );

// ---------------------------------------------------------------------------
// Activation hook: create DB tables and seed default data
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'kdm_activate' );

/**
 * Runs on plugin activation.
 */
function kdm_activate(): void {
    KDM_Database::create_tables();
    KDM_Database::seed_default_data();
    update_option( 'kdm_db_version', KDM_DB_VERSION );
}

// ---------------------------------------------------------------------------
// Bootstrap the plugin on plugins_loaded
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'kdm_init' );

/**
 * Initializes the plugin after all plugins are loaded.
 */
function kdm_init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'kdm_woocommerce_missing_notice' );
    }

    $plugin = new KDM_Plugin();
    $plugin->init();
}

/**
 * Displays an admin notice when WooCommerce is not active.
 */
function kdm_woocommerce_missing_notice(): void {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <strong>Kuwait Delivery Manager:</strong>
            <?php esc_html_e( 'WooCommerce is not active. The delivery areas manager will still work, but checkout integration requires WooCommerce.', 'kuwait-delivery-manager' ); ?>
        </p>
    </div>
    <?php
}
