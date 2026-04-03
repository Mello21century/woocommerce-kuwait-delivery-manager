<?php
/**
 * Plugin Name:       Kuwait Delivery Manager
 * Plugin URI:        https://example.com/kuwait-delivery-manager
 * Description:       Manage Kuwait delivery areas and pricing from the WooCommerce admin dashboard. Full bilingual (AR/EN) support, WooCommerce checkout integration, inline editing, copy-to-all, WPML & Polylang compatible.
 * Version:           1.2.0
 * Author:            Kuwait Delivery Manager
 * Text Domain:       kuwait-delivery-manager
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.5
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------
define( 'KDM_VERSION',     '1.2.0' );
define( 'KDM_PLUGIN_FILE', __FILE__ );
define( 'KDM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'KDM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'KDM_DB_VERSION',  '1.1' ); // 1.1: added area_name_en, governorate_name_en, delivery_notes_en

// ---------------------------------------------------------------------------
// Load required class files
// ---------------------------------------------------------------------------
require_once KDM_PLUGIN_DIR . 'includes/class-helper.php';
require_once KDM_PLUGIN_DIR . 'includes/class-i18n.php';
require_once KDM_PLUGIN_DIR . 'includes/class-database.php';
require_once KDM_PLUGIN_DIR . 'includes/class-admin.php';
require_once KDM_PLUGIN_DIR . 'includes/class-ajax.php';
require_once KDM_PLUGIN_DIR . 'includes/class-checkout.php';
require_once KDM_PLUGIN_DIR . 'includes/class-plugin.php';

// ---------------------------------------------------------------------------
// Declare WooCommerce HPOS compatibility
// ---------------------------------------------------------------------------
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// ---------------------------------------------------------------------------
// Activation hook: create DB table and seed default data
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'kdm_activate' );

/**
 * Runs on plugin activation.
 * Creates the custom DB table and inserts default seed data.
 */
function kdm_activate() {
	KDM_Database::create_table();
	KDM_Database::seed_default_data();
	update_option( 'kdm_db_version', KDM_DB_VERSION );
}

// ---------------------------------------------------------------------------
// Bootstrap the plugin on plugins_loaded
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'kdm_init' );

/**
 * Initialises the plugin after all plugins are loaded.
 */
function kdm_init() {
	// Warn if WooCommerce is not active (plugin still works for data management)
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'kdm_woocommerce_missing_notice' );
	}

	$plugin = new KDM_Plugin();
	$plugin->init();
}

/**
 * Displays an admin notice when WooCommerce is not active.
 */
function kdm_woocommerce_missing_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong>Kuwait Delivery Manager:</strong>
			<?php esc_html_e( 'WooCommerce is not active. The delivery areas manager will still work, but checkout integration requires WooCommerce.', 'kuwait-delivery-manager' ); ?>
		</p>
	</div>
	<?php
}
