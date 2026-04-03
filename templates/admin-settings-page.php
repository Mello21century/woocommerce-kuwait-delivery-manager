<?php
/**
 * Admin template — Settings page.
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap kdm-wrap">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-admin-settings" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#2271b1;"></span>
		<?php esc_html_e( 'Delivery Manager Settings', 'kuwait-delivery-manager' ); ?>
	</h1>
	<hr class="wp-header-end">
	<form method="post" action="options.php">
		<?php
		settings_fields( 'kdm_settings_group' );
		do_settings_sections( 'kdm-delivery-settings' );
		submit_button( __( 'Save Settings', 'kuwait-delivery-manager' ) );
		?>
	</form>
</div>
