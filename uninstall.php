<?php
/**
 * Uninstall handler.
 *
 * WordPress calls this file automatically when the plugin is deleted
 * from the Plugins screen. It runs with no plugin code loaded, so we
 * require only the Database class.
 *
 * WARNING: This permanently drops the delivery areas table and removes
 * all plugin options. There is no recovery path after this runs.
 *
 * @package KuwaitDeliveryManager
 */

// Security: bail if not called by WordPress uninstall process
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Load only what we need — no need for the full plugin stack
require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';

// Drop the custom table
KDM_Database::drop_table();

// Remove plugin options stored in wp_options
delete_option( 'kdm_db_version' );
