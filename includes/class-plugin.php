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

		add_action( 'admin_init', array( $this, 'maybe_upgrade_db' ) );
	}

	/**
	 * Runs DB schema creation when stored version is behind.
	 */
	public function maybe_upgrade_db(): void {
		$installed_version = get_option( 'kdm_db_version', '0' );

		if ( version_compare( $installed_version, KDM_DB_VERSION, '<' ) ) {
			KDM_Database::create_tables();
			update_option( 'kdm_db_version', KDM_DB_VERSION );
		}
	}

	/**
	 * Deactivation callback — preserve data.
	 */
	public static function deactivate(): void {
		// Intentionally empty.
	}
}
