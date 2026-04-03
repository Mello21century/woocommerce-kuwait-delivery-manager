<?php
/**
 * KDM_Plugin
 *
 * Main orchestrator class. Instantiates sub-components and registers
 * any cross-cutting hooks that don't belong to a single sub-class.
 *
 * Architecture note
 * -----------------
 * This class is intentionally thin. Each concern lives in its own class:
 *   KDM_Database  — all DB interaction
 *   KDM_Admin     — admin menu + asset enqueueing
 *   KDM_Ajax      — all AJAX request handlers
 *   KDM_Helper    — stateless utilities
 *
 * To extend for WooCommerce checkout integration in a future version,
 * create a new `class-checkout.php` and instantiate it here when
 * WooCommerce is active.
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;

class KDM_Plugin {

	/**
	 * Boots all plugin components.
	 * Called from `kdm_init()` on the `plugins_loaded` hook.
	 */
	public function init() {
		// i18n — always loaded first so text domain is ready for all other classes
		new KDM_I18n();

		// Admin UI and admin-only AJAX handlers
		if ( is_admin() ) {
			new KDM_Admin();
			new KDM_Ajax();
		}

		// Checkout integration — instantiated for both front-end and admin-ajax
		// (WC session + cart calculations run in admin-ajax.php too)
		if ( class_exists( 'WooCommerce' ) ) {
			new KDM_Checkout();
		}

		// Check if DB needs upgrading (useful for future plugin updates)
		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );
	}

	/**
	 * Runs DB migrations when the stored DB version is behind the current one.
	 * Safe to call on every page load — returns immediately when already current.
	 */
	public function maybe_upgrade_db() {
		$installed_version = get_option( 'kdm_db_version', '0' );

		if ( version_compare( $installed_version, KDM_DB_VERSION, '<' ) ) {
			KDM_Database::create_table(); // dbDelta handles safe upgrades
			update_option( 'kdm_db_version', KDM_DB_VERSION );
		}
	}

	/**
	 * Deactivation callback (registered in the main plugin file).
	 * No data is removed on deactivation — uninstall.php handles cleanup.
	 */
	public static function deactivate() {
		// Intentionally empty — preserve data on deactivation.
	}
}
