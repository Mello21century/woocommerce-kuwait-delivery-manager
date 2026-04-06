<?php
/**
 * KDM_Plugin
 *
 * Main orchestrator class. Instantiates sub-components.
 *
 * @package KuwaitDeliveryManager
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Plugin {

	/**
	 * Boots all plugin components.
	 */
	public function init(): void {
		// Run pending DB migrations early (before any DB reads).
		KDM_Database::maybe_upgrade();

		// i18n — always loaded first.
		new KDM_I18n();

		// Admin UI and admin-only AJAX handlers.
		if ( is_admin() ) {
			new KDM_Admin();
			new KDM_Ajax();
			new KDM_Ajax_Import();
		}

		// Checkout integration (needs WooCommerce).
		if ( class_exists( 'WooCommerce' ) ) {
			new KDM_Checkout();
		}
	}

	/**
	 * Deactivation callback — preserve data.
	 */
	public static function deactivate(): void {
		// Intentionally empty.
	}
}
