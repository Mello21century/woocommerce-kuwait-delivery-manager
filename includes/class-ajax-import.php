<?php
/**
 * KDM_Ajax_Import
 *
 * Handles the two-step CSV import AJAX workflow:
 *   Step 1: Upload CSV, store file, return headers + preview.
 *   Step 2: Apply column mapping and import rows into the database.
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Ajax_Import {

	/** Maximum upload size in bytes (2 MB). */
	const MAX_UPLOAD_SIZE = 2 * 1024 * 1024;

	/** Transient TTL in seconds (10 minutes). */
	const TRANSIENT_TTL = 600;

	public function __construct() {
		add_action( 'wp_ajax_kdm_import_upload', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_kdm_import_execute', array( $this, 'handle_execute' ) );
	}

	// ---------------------------------------------------------------------------
	// Shared guard
	// ---------------------------------------------------------------------------

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
	// Step 1: Upload
	// ---------------------------------------------------------------------------

	/**
	 * Receives the CSV file upload, validates it, stores it securely,
	 * and returns the CSV headers + preview rows.
	 */
	public function handle_upload(): void {
		$this->verify_request();

		// Validate file presence.
		if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'kuwait-delivery-manager' ) ) );
		}

		$file = $_FILES['csv_file'];

		// Validate size.
		if ( (int) $file['size'] > self::MAX_UPLOAD_SIZE ) {
			wp_send_json_error( array( 'message' => __( 'File size exceeds the 2 MB limit.', 'kuwait-delivery-manager' ) ) );
		}

		// Validate MIME type.
		$allowed_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		$finfo         = finfo_open( FILEINFO_MIME_TYPE );
		$mime          = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'kuwait-delivery-manager' ) ) );
		}

		// Prepare upload directory with .htaccess protection.
		$upload_dir = wp_upload_dir();
		$import_dir = $upload_dir['basedir'] . '/kdm-imports';

		if ( ! file_exists( $import_dir ) ) {
			wp_mkdir_p( $import_dir );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $import_dir . '/.htaccess', "Order deny,allow\nDeny from all\n" );
		}

		// Store with randomized filename.
		$filename  = wp_unique_filename( $import_dir, 'kdm-import-' . wp_generate_password( 8, false ) . '.csv' );
		$dest_path = $import_dir . '/' . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save uploaded file.', 'kuwait-delivery-manager' ) ) );
		}

		// Read headers and up to 5 preview rows.
		$handle = fopen( $dest_path, 'r' );
		if ( ! $handle ) {
			wp_delete_file( $dest_path );
			wp_send_json_error( array( 'message' => __( 'Could not read CSV file.', 'kuwait-delivery-manager' ) ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			wp_delete_file( $dest_path );
			wp_send_json_error( array( 'message' => __( 'CSV file appears to be empty.', 'kuwait-delivery-manager' ) ) );
		}

		// Sanitize headers.
		$headers = array_map( 'sanitize_text_field', $headers );

		$preview = array();
		$count   = 0;
		while ( $count < 5 && ( $row = fgetcsv( $handle ) ) !== false ) {
			$preview[] = array_map( 'sanitize_text_field', $row );
			$count++;
		}

		fclose( $handle );

		// Store file path in a short-lived transient keyed to user ID.
		$user_id       = get_current_user_id();
		$transient_key = 'kdm_import_file_' . $user_id;
		set_transient( $transient_key, $dest_path, self::TRANSIENT_TTL );

		wp_send_json_success(
			array(
				'headers' => $headers,
				'preview' => $preview,
				'message' => __( 'File uploaded successfully. Map the columns below.', 'kuwait-delivery-manager' ),
			)
		);
	}

	// ---------------------------------------------------------------------------
	// Step 2: Execute
	// ---------------------------------------------------------------------------

	/**
	 * Retrieves the uploaded file from transient, applies column mapping,
	 * and imports the data.
	 */
	public function handle_execute(): void {
		$this->verify_request();

		// Retrieve file path from transient.
		$user_id       = get_current_user_id();
		$transient_key = 'kdm_import_file_' . $user_id;
		$file_path     = get_transient( $transient_key );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Upload session expired. Please upload the file again.', 'kuwait-delivery-manager' ) )
			);
		}

		// Validate inputs.
		$country = isset( $_POST['country_iso2'] )
			? sanitize_text_field( wp_unslash( $_POST['country_iso2'] ) )
			: '';

		if ( strlen( $country ) !== 2 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid country code.', 'kuwait-delivery-manager' ) ) );
		}

		$import_type = isset( $_POST['import_type'] )
			? sanitize_key( wp_unslash( $_POST['import_type'] ) )
			: '';

		if ( ! in_array( $import_type, array( 'cities', 'areas' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import type.', 'kuwait-delivery-manager' ) ) );
		}

		// Decode column mapping JSON.
		$mapping_raw = isset( $_POST['column_mapping'] )
			? sanitize_text_field( wp_unslash( $_POST['column_mapping'] ) )
			: '{}';
		$mapping     = json_decode( $mapping_raw, true );

		if ( ! is_array( $mapping ) || empty( $mapping ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid column mapping.', 'kuwait-delivery-manager' ) ) );
		}

		// Base defaults for area imports.
		$defaults = array(
			'delivery_price' => isset( $_POST['base_delivery_price'] )
				? KDM_Helper::sanitize_price( $_POST['base_delivery_price'] )
				: 0.0,
			'express_fee' => isset( $_POST['base_express_fee'] )
				? KDM_Helper::sanitize_price( $_POST['base_express_fee'] )
				: 0.0,
			'minimum_order' => isset( $_POST['base_minimum_order'] )
				? KDM_Helper::sanitize_price( $_POST['base_minimum_order'] )
				: 0.0,
		);

		$city_resolve = isset( $_POST['city_resolve_mode'] )
			? sanitize_key( wp_unslash( $_POST['city_resolve_mode'] ) )
			: 'by_id';

		if ( ! in_array( $city_resolve, array( 'by_id', 'by_name_en', 'by_name_ar' ), true ) ) {
			$city_resolve = 'by_id';
		}

		// Run the importer.
		$importer = new KDM_CSV_Importer(
			$file_path,
			$country,
			$import_type,
			$mapping,
			$defaults,
			$city_resolve
		);

		$result = $importer->run();

		// Clean up: delete the file and the transient.
		wp_delete_file( $file_path );
		delete_transient( $transient_key );

		wp_send_json_success(
			array(
				'success_count' => $result['success'],
				'error_count'   => count( $result['errors'] ),
				'errors'        => $result['errors'],
				'message'       => sprintf(
					/* translators: 1: success count 2: error count */
					__( 'Import complete: %1$d succeeded, %2$d failed.', 'kuwait-delivery-manager' ),
					$result['success'],
					count( $result['errors'] )
				),
			)
		);
	}
}
