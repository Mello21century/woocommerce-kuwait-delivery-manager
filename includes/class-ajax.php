<?php
/**
 * KDM_Ajax
 *
 * Registers and handles all admin wp_ajax_* actions for city and area management.
 * Every handler verifies nonce + capability, sanitises input, and returns JSON.
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Ajax {

	/**
	 * Registers all wp_ajax hooks.
	 */
	public function __construct() {
		$actions = array(
			// City actions
			'kdm_get_cities',
			'kdm_save_city',
			'kdm_add_city',
			'kdm_delete_city',
			'kdm_toggle_city',
			'kdm_reorder_cities',
			// Area actions
			'kdm_get_areas',
			'kdm_save_area',
			'kdm_add_area',
			'kdm_delete_area',
			'kdm_toggle_area',
			'kdm_reorder_areas',
			'kdm_copy_field_to_all',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'kdm_', '', $action ) ) );
		}
	}

	// ---------------------------------------------------------------------------
	// Shared guard
	// ---------------------------------------------------------------------------

	/**
	 * Verifies the request nonce and user capability.
	 */
	private function verify_request(): void {
		if ( ! check_ajax_referer( 'kdm_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security verification failed. Please refresh the page and try again.', 'kuwait-delivery-manager' ) ),
				403
			);
		}

		if ( ! KDM_Helper::current_user_can_manage() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'kuwait-delivery-manager' ) ),
				403
			);
		}
	}

	// ---------------------------------------------------------------------------
	// City Handlers
	// ---------------------------------------------------------------------------

	/**
	 * Returns all cities for a country.
	 * POST params: country_iso2, nonce
	 */
	public function get_cities(): void {
		$this->verify_request();

		$country = isset( $_POST['country_iso2'] )
			? sanitize_text_field( wp_unslash( $_POST['country_iso2'] ) )
			: '';

		if ( strlen( $country ) !== 2 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid country code.', 'kuwait-delivery-manager' ) ) );
		}

		$cities = KDM_Database::get_all_cities_by_country( $country );

		// Decode JSON city_name for JS consumption.
		foreach ( $cities as &$city ) {
			$city['city_name_decoded'] = KDM_Helper::decode_json_field( $city['city_name'] );
			$city['area_count']        = count( KDM_Database::get_all_areas_by_city( absint( $city['city_id'] ) ) );
		}
		unset( $city );

		wp_send_json_success(
			array(
				'country_iso2' => $country,
				'cities'       => $cities,
			)
		);
	}

	/**
	 * Updates an existing city.
	 * POST params: city_id, name_en, name_ar, nonce
	 */
	public function save_city(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$existing = KDM_Database::get_city( $city_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'City not found in the database.', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$city_name = KDM_Helper::sanitize_json_name( $_POST, 'name' );
		$decoded   = KDM_Helper::decode_json_field( $city_name );

		if ( empty( $decoded['en'] ) && empty( $decoded['ar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'City name is required (at least one language).', 'kuwait-delivery-manager' ) ) );
		}

		$ok = KDM_Database::save_city( $city_id, array( 'city_name' => $city_name ) );

		if ( $ok ) {
			wp_send_json_success(
				array(
					'message' => __( 'City saved successfully.', 'kuwait-delivery-manager' ),
					'city'    => KDM_Database::get_city( $city_id ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Inserts a new city.
	 * POST params: country_iso2, name_en, name_ar, nonce
	 */
	public function add_city(): void {
		$this->verify_request();

		$country = isset( $_POST['country_iso2'] )
			? sanitize_text_field( wp_unslash( $_POST['country_iso2'] ) )
			: '';

		if ( strlen( $country ) !== 2 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid country code.', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$city_name = KDM_Helper::sanitize_json_name( $_POST, 'name' );
		$decoded   = KDM_Helper::decode_json_field( $city_name );

		if ( empty( $decoded['en'] ) && empty( $decoded['ar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'City name is required (at least one language).', 'kuwait-delivery-manager' ) ) );
		}

		$new_id = KDM_Database::add_city(
			array(
				'country_iso2' => $country,
				'city_name'    => $city_name,
				'is_active'    => 1,
			)
		);

		if ( $new_id ) {
			wp_send_json_success(
				array(
					'message' => __( 'City added successfully.', 'kuwait-delivery-manager' ),
					'city'    => KDM_Database::get_city( $new_id ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add city. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Deletes a city and all its areas (cascade).
	 * POST params: city_id, nonce
	 */
	public function delete_city(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$ok = KDM_Database::delete_city( $city_id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'City and all its areas deleted successfully.', 'kuwait-delivery-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Toggles is_active for a city.
	 * POST params: city_id, nonce
	 */
	public function toggle_city(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$new_status = KDM_Database::toggle_city( $city_id );

		if ( false !== $new_status ) {
			wp_send_json_success(
				array(
					'is_active' => $new_status,
					'message'   => $new_status
						? __( 'City enabled.', 'kuwait-delivery-manager' )
						: __( 'City disabled.', 'kuwait-delivery-manager' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle status. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Updates sorting values for cities after drag-and-drop.
	 * POST params: order[] (city IDs in new order), nonce
	 */
	public function reorder_cities(): void {
		$this->verify_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_order = isset( $_POST['order'] ) ? (array) $_POST['order'] : array();

		if ( empty( $raw_order ) ) {
			wp_send_json_error( array( 'message' => __( 'No reorder data provided.', 'kuwait-delivery-manager' ) ) );
		}

		$items = array();
		foreach ( array_values( $raw_order ) as $index => $city_id ) {
			$items[] = array(
				'city_id' => absint( $city_id ),
				'sorting' => $index,
			);
		}

		KDM_Database::update_city_sort_order( $items );

		wp_send_json_success( array( 'message' => __( 'New order saved.', 'kuwait-delivery-manager' ) ) );
	}

	// ---------------------------------------------------------------------------
	// Area Handlers
	// ---------------------------------------------------------------------------

	/**
	 * Returns all areas for a city.
	 * POST params: city_id, nonce
	 */
	public function get_areas(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$city = KDM_Database::get_city( $city_id );
		if ( ! $city ) {
			wp_send_json_error( array( 'message' => __( 'City not found in the database.', 'kuwait-delivery-manager' ) ) );
		}

		$areas = KDM_Database::get_all_areas_by_city( $city_id );

		// Decode JSON fields for JS.
		$city['city_name_decoded'] = KDM_Helper::decode_json_field( $city['city_name'] );

		foreach ( $areas as &$area ) {
			$area['area_name_decoded']      = KDM_Helper::decode_json_field( $area['area_name'] );
			$area['delivery_notes_decoded'] = KDM_Helper::decode_json_field( $area['delivery_notes'] );
		}
		unset( $area );

		wp_send_json_success(
			array(
				'city'  => $city,
				'areas' => $areas,
			)
		);
	}

	/**
	 * Updates an existing area.
	 * POST params: area_id, name_en, name_ar, notes_en, notes_ar,
	 *              delivery_price, express_fee, minimum_order, nonce
	 */
	public function save_area(): void {
		$this->verify_request();

		$area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

		if ( ! $area_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid area ID.', 'kuwait-delivery-manager' ) ) );
		}

		$existing = KDM_Database::get_area( $area_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Area not found in the database.', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$area_name      = KDM_Helper::sanitize_json_name( $_POST, 'name' );
		$delivery_notes = KDM_Helper::sanitize_json_notes( $_POST );
		$decoded_name   = KDM_Helper::decode_json_field( $area_name );

		if ( empty( $decoded_name['en'] ) && empty( $decoded_name['ar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Area name is required (at least one language).', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = KDM_Helper::sanitize_area_data( $_POST );
		$data['area_name']      = $area_name;
		$data['delivery_notes'] = $delivery_notes;

		$ok = KDM_Database::save_area( $area_id, $data );

		if ( $ok ) {
			$refreshed = KDM_Database::get_area( $area_id );
			$refreshed['area_name_decoded']      = KDM_Helper::decode_json_field( $refreshed['area_name'] );
			$refreshed['delivery_notes_decoded'] = KDM_Helper::decode_json_field( $refreshed['delivery_notes'] );

			wp_send_json_success(
				array(
					'message' => __( 'Changes saved successfully.', 'kuwait-delivery-manager' ),
					'area'    => $refreshed,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Inserts a new area.
	 * POST params: city_id, name_en, name_ar, notes_en, notes_ar,
	 *              delivery_price, express_fee, minimum_order, nonce
	 */
	public function add_area(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$city = KDM_Database::get_city( $city_id );
		if ( ! $city ) {
			wp_send_json_error( array( 'message' => __( 'City not found in the database.', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$area_name      = KDM_Helper::sanitize_json_name( $_POST, 'name' );
		$delivery_notes = KDM_Helper::sanitize_json_notes( $_POST );
		$decoded_name   = KDM_Helper::decode_json_field( $area_name );

		if ( empty( $decoded_name['en'] ) && empty( $decoded_name['ar'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Area name is required (at least one language).', 'kuwait-delivery-manager' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = KDM_Helper::sanitize_area_data( $_POST );

		$data = array_merge(
			array(
				'delivery_price' => 0.0,
				'express_fee'    => 0.0,
				'minimum_order'  => 0.0,
				'is_active'      => 1,
			),
			$data,
			array(
				'city_id'        => $city_id,
				'area_name'      => $area_name,
				'delivery_notes' => $delivery_notes,
			)
		);

		$new_id = KDM_Database::add_area( $data );

		if ( $new_id ) {
			$new_area = KDM_Database::get_area( $new_id );
			$new_area['area_name_decoded']      = KDM_Helper::decode_json_field( $new_area['area_name'] );
			$new_area['delivery_notes_decoded'] = KDM_Helper::decode_json_field( $new_area['delivery_notes'] );

			wp_send_json_success(
				array(
					'message' => __( 'Area added successfully.', 'kuwait-delivery-manager' ),
					'area'    => $new_area,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to add area. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Deletes an area.
	 * POST params: area_id, nonce
	 */
	public function delete_area(): void {
		$this->verify_request();

		$area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

		if ( ! $area_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid area ID.', 'kuwait-delivery-manager' ) ) );
		}

		$ok = KDM_Database::delete_area( $area_id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => __( 'Area deleted successfully.', 'kuwait-delivery-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Toggles is_active for an area.
	 * POST params: area_id, nonce
	 */
	public function toggle_area(): void {
		$this->verify_request();

		$area_id = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;

		if ( ! $area_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid area ID.', 'kuwait-delivery-manager' ) ) );
		}

		$new_status = KDM_Database::toggle_area( $area_id );

		if ( false !== $new_status ) {
			wp_send_json_success(
				array(
					'is_active' => $new_status,
					'message'   => $new_status
						? __( 'Area enabled.', 'kuwait-delivery-manager' )
						: __( 'Area disabled.', 'kuwait-delivery-manager' ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle status. Please try again.', 'kuwait-delivery-manager' ) ) );
		}
	}

	/**
	 * Updates sorting values for areas after drag-and-drop.
	 * POST params: order[] (area IDs in new order), nonce
	 */
	public function reorder_areas(): void {
		$this->verify_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_order = isset( $_POST['order'] ) ? (array) $_POST['order'] : array();

		if ( empty( $raw_order ) ) {
			wp_send_json_error( array( 'message' => __( 'No reorder data provided.', 'kuwait-delivery-manager' ) ) );
		}

		$items = array();
		foreach ( array_values( $raw_order ) as $index => $area_id ) {
			$items[] = array(
				'area_id' => absint( $area_id ),
				'sorting' => $index,
			);
		}

		KDM_Database::update_sort_order( $items );

		wp_send_json_success( array( 'message' => __( 'New order saved.', 'kuwait-delivery-manager' ) ) );
	}

	/**
	 * Copies a field value to all areas in a city (bulk update).
	 * POST params: city_id, field_name, field_value, nonce
	 */
	public function copy_field_to_all(): void {
		$this->verify_request();

		$city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

		if ( ! $city_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
		}

		$field_name = isset( $_POST['field_name'] ) ? sanitize_key( wp_unslash( $_POST['field_name'] ) ) : '';
		$is_json    = ( 'delivery_notes' === $field_name );

		if ( $is_json ) {
			// delivery_notes is stored as JSON — accept the full JSON value.
			$field_value = isset( $_POST['field_value'] )
				? sanitize_text_field( wp_unslash( $_POST['field_value'] ) )
				: '';
		} else {
			$field_value = isset( $_POST['field_value'] )
				? KDM_Helper::sanitize_price( $_POST['field_value'] )
				: 0.0;
		}

		$rows_affected = KDM_Database::bulk_update_field( $city_id, $field_name, $field_value );

		if ( false === $rows_affected ) {
			wp_send_json_error( array( 'message' => __( 'Field name not allowed.', 'kuwait-delivery-manager' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of areas updated */
					__( 'Value applied to %d areas.', 'kuwait-delivery-manager' ),
					(int) $rows_affected
				),
				'field_name'    => $field_name,
				'field_value'   => $field_value,
				'is_json'       => $is_json,
				'rows_affected' => (int) $rows_affected,
			)
		);
	}
}
