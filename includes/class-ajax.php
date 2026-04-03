<?php
/**
 * KDM_Ajax
 *
 * Registers and handles all wp_ajax_* actions for the delivery areas manager.
 * Every handler:
 *   1. Verifies the nonce (kdm_nonce)
 *   2. Checks user capability
 *   3. Sanitises all input
 *   4. Returns JSON via wp_send_json_success / wp_send_json_error
 *
 * Registered actions (admin-only, authenticated users):
 *   kdm_get_areas          — fetch areas for a governorate (bilingual)
 *   kdm_save_area          — update an existing area (bilingual fields)
 *   kdm_add_area           — insert a new area (bilingual fields)
 *   kdm_delete_area        — delete an area
 *   kdm_toggle_area        — flip is_enabled for an area
 *   kdm_reorder_areas      — bulk-update sort_order after drag-and-drop
 *   kdm_copy_field_to_all  — copy one numeric field value to all areas in a governorate
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;

class KDM_Ajax {

	/**
	 * Registers all wp_ajax hooks.
	 */
	public function __construct() {
		$actions = array(
			'kdm_get_areas',
			'kdm_save_area',
			'kdm_add_area',
			'kdm_delete_area',
			'kdm_toggle_area',
			'kdm_reorder_areas',
			'kdm_copy_field_to_all',
		);

		foreach ( $actions as $action ) {
			// Admin-only actions (no nopriv — checkout has its own handlers in KDM_Checkout)
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'kdm_', '', $action ) ) );
		}
	}

	// ---------------------------------------------------------------------------
	// Shared guard — every public handler calls this first
	// ---------------------------------------------------------------------------

	/**
	 * Verifies the request nonce and user capability.
	 * Terminates with a JSON error response if either check fails.
	 */
	private function verify_request() {
		if ( ! check_ajax_referer( 'kdm_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'فشل التحقق الأمني. يرجى تحديث الصفحة وإعادة المحاولة.' ), 403 );
		}

		if ( ! KDM_Helper::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => 'ليس لديك صلاحية تنفيذ هذا الإجراء.' ), 403 );
		}
	}

	// ---------------------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------------------

	/**
	 * Returns all areas for the requested governorate.
	 *
	 * POST params: governorate_key, nonce
	 */
	public function get_areas() {
		$this->verify_request();

		$gov_key = isset( $_POST['governorate_key'] ) ? sanitize_key( wp_unslash( $_POST['governorate_key'] ) ) : '';

		if ( ! KDM_Helper::is_valid_governorate( $gov_key ) ) {
			wp_send_json_error( array( 'message' => 'مفتاح المحافظة غير صالح.' ) );
		}

		$governorates = KDM_Helper::get_governorates();
		$areas        = KDM_Database::get_areas_by_governorate( $gov_key );

		wp_send_json_success(
			array(
				'governorate_key'     => $gov_key,
				'governorate_name_ar' => $governorates[ $gov_key ]['ar'],
				'governorate_name_en' => $governorates[ $gov_key ]['en'],
				// Keep legacy key for backwards-compat with any custom integrations
				'governorate_name'    => $governorates[ $gov_key ]['ar'],
				'areas'               => $areas,
			)
		);
	}

	/**
	 * Copies one numeric field value to all areas in a governorate (bulk update).
	 *
	 * POST params: governorate_key, field_name, field_value, nonce
	 */
	public function copy_field_to_all() {
		$this->verify_request();

		$gov_key = isset( $_POST['governorate_key'] ) ? sanitize_key( wp_unslash( $_POST['governorate_key'] ) ) : '';
		if ( ! KDM_Helper::is_valid_governorate( $gov_key ) ) {
			wp_send_json_error( array( 'message' => 'مفتاح المحافظة غير صالح.' ) );
		}

		$field_name  = isset( $_POST['field_name'] ) ? sanitize_key( wp_unslash( $_POST['field_name'] ) ) : '';
		$text_fields = array( 'delivery_notes', 'delivery_notes_en' );
		$is_text     = in_array( $field_name, $text_fields, true );

		// Use appropriate sanitisation depending on field type
		if ( $is_text ) {
			$field_value = isset( $_POST['field_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['field_value'] ) ) : '';
		} else {
			$field_value = isset( $_POST['field_value'] ) ? KDM_Helper::sanitize_price( $_POST['field_value'] ) : 0.0;
		}

		$rows_affected = KDM_Database::bulk_update_field( $gov_key, $field_name, $field_value );

		if ( false === $rows_affected ) {
			wp_send_json_error( array( 'message' => 'اسم الحقل غير مسموح به.' ) );
		}

		// Format display value for the success message
		$display_value = $is_text
			? ( mb_strlen( (string) $field_value ) > 40
				? mb_substr( (string) $field_value, 0, 40 ) . '…'
				: (string) $field_value )
			: KDM_Helper::format_price( $field_value );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: field value or text excerpt, 2: number of areas */
					__( 'تم تطبيق القيمة %1$s على %2$d منطقة.', 'kuwait-delivery-manager' ),
					$display_value,
					(int) $rows_affected
				),
				'field_name'    => $field_name,
				'field_value'   => $field_value,
				'is_text'       => $is_text,
				'rows_affected' => (int) $rows_affected,
			)
		);
	}

	/**
	 * Updates an existing area row.
	 *
	 * POST params: id, nonce, area_name_ar, delivery_price, express_fee,
	 *              delivery_notes, minimum_order
	 */
	public function save_area() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'معرّف المنطقة غير صالح.' ) );
		}

		// Confirm the row exists before updating
		$existing = KDM_Database::get_area( $id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => 'المنطقة المطلوبة غير موجودة في قاعدة البيانات.' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
		$data = KDM_Helper::sanitize_area_data( $_POST );

		if ( empty( $data['area_name_ar'] ) ) {
			wp_send_json_error( array( 'message' => 'اسم المنطقة حقل مطلوب.' ) );
		}

		$ok = KDM_Database::save_area( $id, $data );

		if ( $ok ) {
			wp_send_json_success(
				array(
					'message' => 'تم حفظ التغييرات بنجاح.',
					'area'    => KDM_Database::get_area( $id ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'فشل الحفظ. يرجى المحاولة مجدداً.' ) );
		}
	}

	/**
	 * Inserts a new area row.
	 *
	 * POST params: governorate_key, nonce, area_name_ar, delivery_price,
	 *              express_fee, delivery_notes, minimum_order, is_enabled
	 */
	public function add_area() {
		$this->verify_request();

		$gov_key = isset( $_POST['governorate_key'] ) ? sanitize_key( wp_unslash( $_POST['governorate_key'] ) ) : '';

		if ( ! KDM_Helper::is_valid_governorate( $gov_key ) ) {
			wp_send_json_error( array( 'message' => 'مفتاح المحافظة غير صالح.' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
		$data = KDM_Helper::sanitize_area_data( $_POST );

		if ( empty( $data['area_name_ar'] ) ) {
			wp_send_json_error( array( 'message' => 'اسم المنطقة حقل مطلوب.' ) );
		}

		$governorates = KDM_Helper::get_governorates();

		// Merge computed / default fields
		$data = array_merge(
			array(
				'delivery_price'  => 0.0,
				'express_fee'     => 0.0,
				'delivery_notes'  => '',
				'minimum_order'   => 0.0,
				'is_enabled'      => 1,
			),
			$data,
			array(
				'governorate_key'     => $gov_key,
				'governorate_name_ar' => $governorates[ $gov_key ],
			)
		);

		$new_id = KDM_Database::add_area( $data );

		if ( $new_id ) {
			wp_send_json_success(
				array(
					'message' => 'تمت إضافة المنطقة بنجاح.',
					'area'    => KDM_Database::get_area( $new_id ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'فشلت الإضافة. يرجى المحاولة مجدداً.' ) );
		}
	}

	/**
	 * Deletes an area row.
	 *
	 * POST params: id, nonce
	 */
	public function delete_area() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'معرّف المنطقة غير صالح.' ) );
		}

		$ok = KDM_Database::delete_area( $id );

		if ( $ok ) {
			wp_send_json_success( array( 'message' => 'تم حذف المنطقة بنجاح.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'فشل الحذف. يرجى المحاولة مجدداً.' ) );
		}
	}

	/**
	 * Toggles the is_enabled flag for an area.
	 *
	 * POST params: id, nonce
	 */
	public function toggle_area() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'معرّف المنطقة غير صالح.' ) );
		}

		$new_status = KDM_Database::toggle_area( $id );

		if ( false !== $new_status ) {
			wp_send_json_success(
				array(
					'is_enabled' => $new_status,
					'message'    => $new_status ? 'تم تفعيل المنطقة.' : 'تم إيقاف المنطقة.',
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'فشل تغيير الحالة. يرجى المحاولة مجدداً.' ) );
		}
	}

	/**
	 * Updates sort_order values after a drag-and-drop reorder.
	 *
	 * POST params: order[] (array of area IDs in new order), nonce
	 */
	public function reorder_areas() {
		$this->verify_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
		$raw_order = isset( $_POST['order'] ) ? (array) $_POST['order'] : array();

		if ( empty( $raw_order ) ) {
			wp_send_json_error( array( 'message' => 'لا توجد بيانات للترتيب.' ) );
		}

		// Build items array with sanitised IDs and computed sort_order
		$items = array();
		foreach ( array_values( $raw_order ) as $index => $area_id ) {
			$items[] = array(
				'id'         => absint( $area_id ),
				'sort_order' => $index,
			);
		}

		KDM_Database::update_sort_order( $items );

		wp_send_json_success( array( 'message' => 'تم حفظ الترتيب الجديد.' ) );
	}
}
