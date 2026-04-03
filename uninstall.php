<?php
/**
 * Uninstall handler.
 *
 * WordPress calls this file automatically when the plugin is deleted
 * from the Plugins screen.
 *
 * WARNING: This permanently drops both delivery tables and removes
 * all plugin options. There is no recovery path after this runs.
 *
 * @package KuwaitDeliveryManager
 */

// Security: bail if not called by WordPress uninstall process.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load only what we need.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Drop both custom tables (areas first, then cities).
KDM_Database::drop_tables();

// Remove plugin options.
delete_option( 'kdm_db_version' );
delete_option( 'kdm_express_enabled' );
