<?php
/**
 * Admin template — Cities management page.
 * Country dropdown + AJAX-driven cities table.
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;

$selected_country = $this->get_selected_country();
$countries        = $this->get_country_list();
?>
<div class="wrap kdm-wrap">

	<div class="kdm-page-header">
		<h1 class="kdm-page-title">
			<span class="dashicons dashicons-building" aria-hidden="true"></span>
			<?php esc_html_e( 'Delivery Cities', 'kuwait-delivery-manager' ); ?>
		</h1>
		<p class="kdm-page-subtitle">
			<?php esc_html_e( 'Manage delivery cities for each country.', 'kuwait-delivery-manager' ); ?>
		</p>
	</div>

	<!-- Country dropdown -->
	<div class="kdm-country-selector">
		<label for="kdm-country-select"><?php esc_html_e( 'Country:', 'kuwait-delivery-manager' ); ?></label>
		<select id="kdm-country-select" class="kdm-country-select">
			<?php foreach ( $countries as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_country, $code ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div id="kdm-notices" role="alert" aria-live="polite" aria-atomic="true"></div>

	<!-- Cities table content (populated via AJAX) -->
	<div class="kdm-cities-content" id="kdm-cities-content">
		<div class="kdm-loading-initial">
			<span class="kdm-spinner"></span>
			<p><?php esc_html_e( 'Loading cities...', 'kuwait-delivery-manager' ); ?></p>
		</div>
	</div>

</div>

<script>
jQuery(function($) {
	$('#kdm-country-select').on('change', function() {
		var url = new URL(window.location.href);
		url.searchParams.set('country', $(this).val());
		window.location.href = url.toString();
	});
});
</script>
