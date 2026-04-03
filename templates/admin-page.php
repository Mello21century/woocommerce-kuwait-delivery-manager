<?php
/**
 * Admin template — Delivery Areas page.
 * Country dropdown + AJAX-loaded city sidebar + areas table.
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
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Delivery Areas Manager', 'kuwait-delivery-manager' ); ?>
		</h1>
		<p class="kdm-page-subtitle">
			<?php esc_html_e( 'Select a city to view and edit its delivery areas.', 'kuwait-delivery-manager' ); ?>
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

	<div class="kdm-layout">

		<!-- Sidebar: cities list (populated via AJAX) -->
		<nav class="kdm-sidebar" aria-label="<?php esc_attr_e( 'Cities', 'kuwait-delivery-manager' ); ?>">
			<div class="kdm-sidebar-header">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'Cities', 'kuwait-delivery-manager' ); ?></h2>
			</div>

			<ul class="kdm-city-list" id="kdm-city-list" role="list">
				<li class="kdm-loading-initial">
					<span class="kdm-spinner"></span>
					<span><?php esc_html_e( 'Loading...', 'kuwait-delivery-manager' ); ?></span>
				</li>
			</ul>
		</nav>

		<!-- Main content: areas table -->
		<main class="kdm-content" id="kdm-content-panel"
			  aria-label="<?php esc_attr_e( 'City areas', 'kuwait-delivery-manager' ); ?>">
			<div id="kdm-areas-content">
				<div class="kdm-loading-initial">
					<span class="kdm-spinner"></span>
					<p><?php esc_html_e( 'Loading data...', 'kuwait-delivery-manager' ); ?></p>
				</div>
			</div>
		</main>

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
