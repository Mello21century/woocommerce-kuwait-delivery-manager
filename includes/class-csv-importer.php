<?php
/**
 * KDM_CSV_Importer
 *
 * Handles the actual CSV parsing and database insertion for both
 * city and area imports.
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_CSV_Importer {

	/** @var string */
	private $file_path;

	/** @var string */
	private $country_iso2;

	/** @var string 'cities' or 'areas' */
	private $type;

	/** @var array CSV column index => DB field mapping */
	private $mapping;

	/** @var array Base default values for area fields */
	private $defaults;

	/** @var string 'by_id', 'by_name_en', 'by_name_ar' */
	private $city_resolve;

	/** @var array Error messages collected during import */
	private $errors = array();

	/** @var int */
	private $success_count = 0;

	/**
	 * @param string $file_path     Full path to the uploaded CSV file.
	 * @param string $country_iso2  Two-letter country code.
	 * @param string $type          'cities' or 'areas'.
	 * @param array  $mapping       [ csv_col_index => field_name, ... ]
	 * @param array  $defaults      Base values: delivery_price, express_fee, minimum_order.
	 * @param string $city_resolve  How to resolve city_id for areas: 'by_id', 'by_name_en', 'by_name_ar'.
	 */
	public function __construct(
		string $file_path,
		string $country_iso2,
		string $type,
		array $mapping,
		array $defaults = array(),
		string $city_resolve = 'by_id'
	) {
		$this->file_path    = $file_path;
		$this->country_iso2 = sanitize_text_field( $country_iso2 );
		$this->type         = $type;
		$this->mapping      = $mapping;
		$this->defaults     = $defaults;
		$this->city_resolve = $city_resolve;
	}

	/**
	 * Runs the import and returns results.
	 *
	 * @return array{success: int, errors: array}
	 */
	public function run(): array {
		if ( 'cities' === $this->type ) {
			$this->import_cities();
		} else {
			$this->import_areas();
		}

		return array(
			'success' => $this->success_count,
			'errors'  => $this->errors,
		);
	}

	/**
	 * Imports cities from CSV.
	 */
	private function import_cities(): void {
		$handle = fopen( $this->file_path, 'r' );
		if ( ! $handle ) {
			$this->errors[] = __( 'Could not open CSV file.', 'kuwait-delivery-manager' );
			return;
		}

		// Skip header row.
		fgetcsv( $handle );

		$row_num = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			$name_en = '';
			$name_ar = '';

			foreach ( $this->mapping as $col_index => $field ) {
				$col_index = absint( $col_index );
				$value     = isset( $row[ $col_index ] ) ? trim( $row[ $col_index ] ) : '';

				if ( 'name_en' === $field ) {
					$name_en = sanitize_text_field( $value );
				} elseif ( 'name_ar' === $field ) {
					$name_ar = sanitize_text_field( $value );
				}
			}

			if ( empty( $name_en ) && empty( $name_ar ) ) {
				$this->errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: No city name provided. Skipped.', 'kuwait-delivery-manager' ),
					$row_num
				);
				continue;
			}

			$city_name = wp_json_encode(
				array( 'en' => $name_en, 'ar' => $name_ar ),
				JSON_UNESCAPED_UNICODE
			);

			$result = KDM_Database::add_city(
				array(
					'country_iso2' => $this->country_iso2,
					'city_name'    => $city_name,
					'is_active'    => 1,
					'sorting'      => 100,
				)
			);

			if ( $result ) {
				$this->success_count++;
			} else {
				$this->errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: Database insert failed.', 'kuwait-delivery-manager' ),
					$row_num
				);
			}
		}

		fclose( $handle );
	}

	/**
	 * Imports areas from CSV.
	 */
	private function import_areas(): void {
		$handle = fopen( $this->file_path, 'r' );
		if ( ! $handle ) {
			$this->errors[] = __( 'Could not open CSV file.', 'kuwait-delivery-manager' );
			return;
		}

		// Build city lookup map for name-based resolution.
		$city_lookup = $this->build_city_lookup();

		// Skip header row.
		fgetcsv( $handle );

		$row_num = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			$name_en        = '';
			$name_ar        = '';
			$notes_en       = '';
			$notes_ar       = '';
			$delivery_price = (float) ( $this->defaults['delivery_price'] ?? 0 );
			$express_fee    = (float) ( $this->defaults['express_fee'] ?? 0 );
			$minimum_order  = (float) ( $this->defaults['minimum_order'] ?? 0 );
			$city_ref       = '';

			foreach ( $this->mapping as $col_index => $field ) {
				$col_index = absint( $col_index );
				$value     = isset( $row[ $col_index ] ) ? trim( $row[ $col_index ] ) : '';

				switch ( $field ) {
					case 'name_en':
						$name_en = sanitize_text_field( $value );
						break;
					case 'name_ar':
						$name_ar = sanitize_text_field( $value );
						break;
					case 'delivery_price':
						if ( '' !== $value ) {
							$delivery_price = KDM_Helper::sanitize_price( $value );
						}
						break;
					case 'express_fee':
						if ( '' !== $value ) {
							$express_fee = KDM_Helper::sanitize_price( $value );
						}
						break;
					case 'minimum_order':
						if ( '' !== $value ) {
							$minimum_order = KDM_Helper::sanitize_price( $value );
						}
						break;
					case 'delivery_notes':
						$notes_en = sanitize_textarea_field( $value );
						break;
					case 'city_ref':
						$city_ref = $value;
						break;
				}
			}

			if ( empty( $name_en ) && empty( $name_ar ) ) {
				$this->errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: No area name provided. Skipped.', 'kuwait-delivery-manager' ),
					$row_num
				);
				continue;
			}

			// Resolve city_id.
			$city_id = $this->resolve_city_id( $city_ref, $city_lookup );

			if ( ! $city_id ) {
				$this->errors[] = sprintf(
					/* translators: 1: row number 2: city reference value */
					__( 'Row %1$d: Could not resolve city "%2$s". Skipped.', 'kuwait-delivery-manager' ),
					$row_num,
					sanitize_text_field( $city_ref )
				);
				continue;
			}

			$area_name = wp_json_encode(
				array( 'en' => $name_en, 'ar' => $name_ar ),
				JSON_UNESCAPED_UNICODE
			);

			$delivery_notes = wp_json_encode(
				array( 'en' => $notes_en, 'ar' => $notes_ar ),
				JSON_UNESCAPED_UNICODE
			);

			$result = KDM_Database::add_area(
				array(
					'city_id'        => $city_id,
					'area_name'      => $area_name,
					'is_active'      => 1,
					'sorting'        => 100,
					'delivery_price' => $delivery_price,
					'express_fee'    => $express_fee,
					'delivery_notes' => $delivery_notes,
					'minimum_order'  => $minimum_order,
				)
			);

			if ( $result ) {
				$this->success_count++;
			} else {
				$this->errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: Database insert failed.', 'kuwait-delivery-manager' ),
					$row_num
				);
			}
		}

		fclose( $handle );
	}

	/**
	 * Builds a lookup array for resolving city references by name.
	 *
	 * @return array
	 */
	private function build_city_lookup(): array {
		$cities = KDM_Database::get_all_cities_by_country( $this->country_iso2 );
		$lookup = array(
			'by_id'      => array(),
			'by_name_en' => array(),
			'by_name_ar' => array(),
		);

		foreach ( $cities as $city ) {
			$cid   = absint( $city['city_id'] );
			$names = KDM_Helper::decode_json_field( $city['city_name'] );

			$lookup['by_id'][ $cid ] = $cid;

			if ( ! empty( $names['en'] ) ) {
				$lookup['by_name_en'][ mb_strtolower( $names['en'] ) ] = $cid;
			}
			if ( ! empty( $names['ar'] ) ) {
				$lookup['by_name_ar'][ mb_strtolower( $names['ar'] ) ] = $cid;
			}
		}

		return $lookup;
	}

	/**
	 * Resolves a CSV cell value to a city_id.
	 *
	 * @param string $value  The raw value from the CSV column.
	 * @param array  $lookup Pre-built lookup arrays.
	 * @return int|null
	 */
	private function resolve_city_id( string $value, array $lookup ): ?int {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		switch ( $this->city_resolve ) {
			case 'by_id':
				$id = absint( $value );
				return isset( $lookup['by_id'][ $id ] ) ? $id : null;

			case 'by_name_en':
				$key = mb_strtolower( $value );
				return $lookup['by_name_en'][ $key ] ?? null;

			case 'by_name_ar':
				$key = mb_strtolower( $value );
				return $lookup['by_name_ar'][ $key ] ?? null;

			default:
				return null;
		}
	}
}
