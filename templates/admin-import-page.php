<?php
/**
 * Admin template — CSV Import wizard (2 steps).
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
			<span class="dashicons dashicons-upload" aria-hidden="true"></span>
			<?php esc_html_e( 'Import from CSV', 'kuwait-delivery-manager' ); ?>
		</h1>
		<p class="kdm-page-subtitle">
			<?php esc_html_e( 'Upload a CSV file to bulk-import cities or areas.', 'kuwait-delivery-manager' ); ?>
		</p>
	</div>

	<div id="kdm-notices" role="alert" aria-live="polite" aria-atomic="true"></div>

	<!-- ============================================================ -->
	<!-- STEP 1: Upload                                                -->
	<!-- ============================================================ -->
	<div class="kdm-import-step" id="kdm-import-step1">
		<div class="kdm-card">
			<h2><?php esc_html_e( 'Step 1: Upload File', 'kuwait-delivery-manager' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="kdm-import-country"><?php esc_html_e( 'Country', 'kuwait-delivery-manager' ); ?></label>
					</th>
					<td>
						<select id="kdm-import-country" class="kdm-country-select">
							<?php foreach ( $countries as $code => $name ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_country, $code ); ?>>
									<?php echo esc_html( $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Import Type', 'kuwait-delivery-manager' ); ?></th>
					<td>
						<label>
							<input type="radio" name="kdm_import_type" value="cities" checked>
							<?php esc_html_e( 'Cities', 'kuwait-delivery-manager' ); ?>
						</label>
						<br>
						<label>
							<input type="radio" name="kdm_import_type" value="areas">
							<?php esc_html_e( 'Areas', 'kuwait-delivery-manager' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<!-- Area base values (shown only when Areas is selected) -->
			<div id="kdm-import-area-defaults" style="display:none;">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="kdm-base-price"><?php esc_html_e( 'Base Delivery Price', 'kuwait-delivery-manager' ); ?></label>
						</th>
						<td>
							<input type="number" id="kdm-base-price" step="0.001" min="0" value="0.000" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="kdm-base-express"><?php esc_html_e( 'Base Express Fee', 'kuwait-delivery-manager' ); ?></label>
						</th>
						<td>
							<input type="number" id="kdm-base-express" step="0.001" min="0" value="0.000" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="kdm-base-minimum"><?php esc_html_e( 'Base Minimum Order', 'kuwait-delivery-manager' ); ?></label>
						</th>
						<td>
							<input type="number" id="kdm-base-minimum" step="0.001" min="0" value="0.000" class="small-text">
						</td>
					</tr>
				</table>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="kdm-csv-file"><?php esc_html_e( 'CSV File', 'kuwait-delivery-manager' ); ?></label>
					</th>
					<td>
						<input type="file" id="kdm-csv-file" accept=".csv">
						<p class="description"><?php esc_html_e( 'Maximum 2 MB. The first row must contain column headers.', 'kuwait-delivery-manager' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" class="button button-primary" id="kdm-import-upload-btn">
					<?php esc_html_e( 'Upload & Continue', 'kuwait-delivery-manager' ); ?>
				</button>
			</p>
		</div>
	</div>

	<!-- ============================================================ -->
	<!-- STEP 2: Column Mapping                                        -->
	<!-- ============================================================ -->
	<div class="kdm-import-step" id="kdm-import-step2" style="display:none;">
		<div class="kdm-card">
			<h2><?php esc_html_e( 'Step 2: Map Columns', 'kuwait-delivery-manager' ); ?></h2>

			<!-- City resolve mode (shown only for Areas import) -->
			<div id="kdm-city-resolve-wrap" style="display:none;">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'City Matching Mode', 'kuwait-delivery-manager' ); ?></th>
						<td>
							<select id="kdm-city-resolve-mode">
								<option value="by_id"><?php esc_html_e( 'By City ID', 'kuwait-delivery-manager' ); ?></option>
								<option value="by_name_en"><?php esc_html_e( 'By English City Name', 'kuwait-delivery-manager' ); ?></option>
								<option value="by_name_ar"><?php esc_html_e( 'By Arabic City Name', 'kuwait-delivery-manager' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<!-- Mapping table (populated by JS) -->
			<table class="widefat kdm-mapping-table" id="kdm-mapping-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'CSV Column', 'kuwait-delivery-manager' ); ?></th>
						<th><?php esc_html_e( 'Map To', 'kuwait-delivery-manager' ); ?></th>
						<th><?php esc_html_e( 'Preview', 'kuwait-delivery-manager' ); ?></th>
					</tr>
				</thead>
				<tbody id="kdm-mapping-body">
					<!-- Populated by JS -->
				</tbody>
			</table>

			<p class="submit">
				<button type="button" class="button" id="kdm-import-back-btn">
					<?php esc_html_e( 'Back', 'kuwait-delivery-manager' ); ?>
				</button>
				<button type="button" class="button button-primary" id="kdm-import-execute-btn">
					<?php esc_html_e( 'Import', 'kuwait-delivery-manager' ); ?>
				</button>
			</p>
		</div>

		<!-- Results -->
		<div class="kdm-card" id="kdm-import-results" style="display:none;">
			<h3><?php esc_html_e( 'Import Results', 'kuwait-delivery-manager' ); ?></h3>
			<div id="kdm-import-results-content"></div>
		</div>
	</div>

</div>
