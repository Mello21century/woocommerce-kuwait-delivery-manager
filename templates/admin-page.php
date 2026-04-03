<?php
/**
 * Admin page template — v1.2.0
 *
 * Renders the RTL two-column layout.
 * All static strings use __() / _e() for full i18n support
 * (WP core .mo, WPML String Translation, Polylang Strings Translations).
 *
 * Sidebar (right): clickable governorate list with bilingual names.
 * Content (left):  areas table, loaded via AJAX by admin.js.
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;

$governorates  = KDM_Helper::get_governorates();
$first_gov_key = array_key_first( $governorates );
?>
<div class="wrap kdm-wrap" dir="rtl" lang="ar">

	<!-- ------------------------------------------------------------------ -->
	<!-- Page header                                                          -->
	<!-- ------------------------------------------------------------------ -->
	<div class="kdm-page-header">
		<h1 class="kdm-page-title">
			<span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'إدارة مناطق التوصيل — الكويت', 'kuwait-delivery-manager' ); ?>
		</h1>
		<p class="kdm-page-subtitle">
			<?php esc_html_e( 'اختر محافظة من القائمة لعرض مناطقها وتعديل أسعار التوصيل.', 'kuwait-delivery-manager' ); ?>
		</p>
	</div>

	<!-- ------------------------------------------------------------------ -->
	<!-- Admin notices (populated by JS)                                     -->
	<!-- ------------------------------------------------------------------ -->
	<div id="kdm-notices" role="alert" aria-live="polite" aria-atomic="true"></div>

	<!-- ------------------------------------------------------------------ -->
	<!-- Two-column layout: sidebar (right) + content (left)                 -->
	<!-- ------------------------------------------------------------------ -->
	<div class="kdm-layout">

		<!-- RIGHT: Governorates sidebar -->
		<nav class="kdm-sidebar" aria-label="<?php esc_attr_e( 'قائمة المحافظات', 'kuwait-delivery-manager' ); ?>">
			<div class="kdm-sidebar-header">
				<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
				<h2><?php esc_html_e( 'المحافظات', 'kuwait-delivery-manager' ); ?></h2>
			</div>

			<ul class="kdm-gov-list" role="list">
				<?php foreach ( $governorates as $key => $names ) : ?>
					<li class="kdm-gov-item<?php echo ( $key === $first_gov_key ) ? ' active' : ''; ?>"
						data-key="<?php echo esc_attr( $key ); ?>"
						role="button"
						tabindex="0"
						aria-pressed="<?php echo ( $key === $first_gov_key ) ? 'true' : 'false'; ?>">

						<span class="kdm-gov-dot" aria-hidden="true"></span>

						<!-- Bilingual governorate name -->
						<span class="kdm-gov-names">
							<span class="kdm-gov-name-ar"><?php echo esc_html( $names['ar'] ); ?></span>
							<span class="kdm-gov-name-en"><?php echo esc_html( $names['en'] ); ?></span>
						</span>

						<span class="kdm-gov-count" id="kdm-count-<?php echo esc_attr( $key ); ?>"></span>
						<span class="kdm-gov-arrow dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="kdm-sidebar-footer">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<?php esc_html_e( 'الكويت · 6 محافظات', 'kuwait-delivery-manager' ); ?>
			</div>
		</nav>

		<!-- LEFT: Areas content panel -->
		<main class="kdm-content" id="kdm-content-panel"
			  aria-label="<?php esc_attr_e( 'مناطق المحافظة', 'kuwait-delivery-manager' ); ?>">
			<div id="kdm-areas-content">
				<!-- Initial spinner — JS replaces this immediately on load -->
				<div class="kdm-loading-initial">
					<span class="kdm-spinner"></span>
					<p><?php esc_html_e( 'جاري تحميل البيانات…', 'kuwait-delivery-manager' ); ?></p>
				</div>
			</div>
		</main>

	</div><!-- .kdm-layout -->

</div><!-- .wrap.kdm-wrap -->
