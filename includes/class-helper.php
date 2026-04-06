<?php
/**
 * KDM_Helper
 *
 * Static utilities used throughout the plugin:
 *   - Input sanitisation for city and area data
 *   - JSON field encoding/decoding helpers
 *   - Price formatting (KWD — 3 decimal places)
 *   - Capability check
 *
 * @package KuwaitDeliveryManager
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Helper {

	// ---------------------------------------------------------------------------
	// JSON Field Helpers
	// ---------------------------------------------------------------------------

	/**
	 * Safely decodes a JSON column value into an associative array.
	 * Returns ['en' => '', 'ar' => ''] on null, empty, or invalid JSON.
	 *
	 * @param mixed $value  Raw JSON string from database.
	 * @return array{en: string, ar: string}
	 */
	public static function decode_json_field( $value ): array {
		$default = array( 'en' => '', 'ar' => '' );

		if ( empty( $value ) ) {
			return $default;
		}

		$decoded = json_decode( (string) $value, true );

		if ( ! is_array( $decoded ) ) {
			return $default;
		}

		return array(
			'en' => isset( $decoded['en'] ) ? (string) $decoded['en'] : '',
			'ar' => isset( $decoded['ar'] ) ? (string) $decoded['ar'] : '',
		);
	}

	/**
	 * Builds a JSON-encoded name string from POST data.
	 * Looks for keys like {$prefix}_en and {$prefix}_ar.
	 *
	 * @param array  $data   Raw POST data.
	 * @param string $prefix Key prefix, e.g. 'name' looks for 'name_en', 'name_ar'.
	 * @return string JSON string.
	 */
	public static function sanitize_json_name( array $data, string $prefix = 'name' ): string {
		$en = isset( $data[ $prefix . '_en' ] )
			? sanitize_text_field( wp_unslash( $data[ $prefix . '_en' ] ) )
			: '';
		$ar = isset( $data[ $prefix . '_ar' ] )
			? sanitize_text_field( wp_unslash( $data[ $prefix . '_ar' ] ) )
			: '';

		return wp_json_encode( array( 'en' => $en, 'ar' => $ar ), JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Builds a JSON-encoded delivery notes string from POST data.
	 *
	 * @param array $data  Raw POST data with 'notes_en' and/or 'notes_ar'.
	 * @return string JSON string.
	 */
	public static function sanitize_json_notes( array $data ): string {
		$en = isset( $data['notes_en'] )
			? sanitize_textarea_field( wp_unslash( $data['notes_en'] ) )
			: '';
		$ar = isset( $data['notes_ar'] )
			? sanitize_textarea_field( wp_unslash( $data['notes_ar'] ) )
			: '';

		return wp_json_encode( array( 'en' => $en, 'ar' => $ar ), JSON_UNESCAPED_UNICODE );
	}

	// ---------------------------------------------------------------------------
	// Sanitisation
	// ---------------------------------------------------------------------------

	/**
	 * Sanitises area data from a POST request.
	 * Only keys present in $data are included in the output.
	 *
	 * @param array $data  Raw POST data.
	 * @return array       Sanitised data.
	 */
	public static function sanitize_area_data( array $data ): array {
		$clean = array();

		// Numeric / price fields.
		foreach ( array( 'delivery_price', 'express_fee', 'minimum_order', 'free_minimum_order' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = self::sanitize_price( $data[ $field ] );
			}
		}

		// Boolean flag.
		if ( array_key_exists( 'is_active', $data ) ) {
			$clean['is_active'] = absint( $data['is_active'] ) ? 1 : 0;
		}

		// Sort order.
		if ( array_key_exists( 'sorting', $data ) ) {
			$clean['sorting'] = absint( $data['sorting'] );
		}

		return $clean;
	}

	/**
	 * Sanitises city data from a POST request.
	 *
	 * @param array $data  Raw POST data.
	 * @return array       Sanitised data.
	 */
	public static function sanitize_city_data( array $data ): array {
		$clean = array();

		if ( array_key_exists( 'is_active', $data ) ) {
			$clean['is_active'] = absint( $data['is_active'] ) ? 1 : 0;
		}

		if ( array_key_exists( 'sorting', $data ) ) {
			$clean['sorting'] = absint( $data['sorting'] );
		}

		return $clean;
	}

	// ---------------------------------------------------------------------------
	// Price helpers
	// ---------------------------------------------------------------------------

	/**
	 * Sanitises and normalises a monetary value.
	 * Accepts comma or dot as decimal separator (handles Arabic locale input).
	 * Returns a float rounded to 3 decimal places (KWD standard).
	 *
	 * @param mixed $value  Raw value.
	 * @return float
	 */
	public static function sanitize_price( $value ): float {
		$value = str_replace( ',', '.', (string) $value );
		return round( max( 0.0, floatval( $value ) ), 3 );
	}

	/**
	 * Formats a price for display (3 decimal places, no thousands separator).
	 *
	 * @param float|string $price
	 * @return string  e.g. "1.500"
	 */
	public static function format_price( $price ): string {
		return number_format( (float) $price, 3, '.', '' );
	}

	// ---------------------------------------------------------------------------
	// Security
	// ---------------------------------------------------------------------------

	/**
	 * Returns true when the current user can manage delivery areas.
	 * Accepts manage_options (admins) or manage_woocommerce (shop managers).
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}
}
