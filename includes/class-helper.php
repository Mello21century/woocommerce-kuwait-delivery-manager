<?php
/**
 * KDM_Helper
 *
 * Static utilities used throughout the plugin:
 *   - Kuwait governorate definitions (Arabic + English)
 *   - Input sanitisation for area data (supports both language variants)
 *   - Price formatting (KWD — 3 decimal places)
 *   - Capability check
 *   - Governorate key validation
 *
 * @package KuwaitDeliveryManager
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Helper {

	// ---------------------------------------------------------------------------
	// Governorates
	// ---------------------------------------------------------------------------

	/**
	 * Returns the canonical list of Kuwait governorates.
	 *
	 * Each value is an associative array with:
	 *   'ar' => Arabic display name
	 *   'en' => English display name
	 *
	 * @return array<string, array{ar: string, en: string}>
	 */
	public static function get_governorates() {
		return array(
			'capital'   => array( 'ar' => 'العاصمة',       'en' => 'Kuwait Capital' ),
			'hawalli'   => array( 'ar' => 'حولي',           'en' => 'Hawalli' ),
			'farwaniya' => array( 'ar' => 'الفروانية',      'en' => 'Al-Farwaniyah' ),
			'ahmadi'    => array( 'ar' => 'الأحمدي',        'en' => 'Ahmadi' ),
			'jahra'     => array( 'ar' => 'الجهراء',        'en' => 'Al-Jahra' ),
			'mubarak'   => array( 'ar' => 'مبارك الكبير',  'en' => 'Mubarak Al-Kabeer' ),
		);
	}

	/**
	 * Returns only the Arabic names keyed by slug — for backward-compat use.
	 *
	 * @return array<string, string>
	 */
	public static function get_governorates_ar() {
		return array_map( function ( $g ) { return $g['ar']; }, self::get_governorates() );
	}

	/**
	 * Returns the Arabic name for a single governorate.
	 *
	 * @param string $key  Governorate slug.
	 * @return string
	 */
	public static function get_governorate_ar( $key ) {
		$govs = self::get_governorates();
		return isset( $govs[ $key ] ) ? $govs[ $key ]['ar'] : $key;
	}

	/**
	 * Returns the English name for a single governorate.
	 *
	 * @param string $key  Governorate slug.
	 * @return string
	 */
	public static function get_governorate_en( $key ) {
		$govs = self::get_governorates();
		return isset( $govs[ $key ] ) ? $govs[ $key ]['en'] : $key;
	}

	/**
	 * Returns both the Arabic and English names for a governorate as a formatted string.
	 * Used in admin UI where both names are shown together.
	 *
	 * @param string $key Governorate slug.
	 * @return string  e.g. "العاصمة — Kuwait Capital"
	 */
	public static function get_governorate_bilingual( $key ) {
		$govs = self::get_governorates();
		if ( ! isset( $govs[ $key ] ) ) {
			return $key;
		}
		return $govs[ $key ]['ar'] . ' — ' . $govs[ $key ]['en'];
	}

	/**
	 * Validates that a governorate slug exists in the canonical list.
	 *
	 * @param string $key  Governorate slug.
	 * @return bool
	 */
	public static function is_valid_governorate( $key ) {
		return array_key_exists( $key, self::get_governorates() );
	}

	// ---------------------------------------------------------------------------
	// Sanitisation
	// ---------------------------------------------------------------------------

	/**
	 * Sanitises area data from a POST request.
	 * Only keys present in $data are included in the output, so the result
	 * can be passed directly to $wpdb->update / $wpdb->insert.
	 *
	 * Supported keys (all optional unless noted):
	 *   area_name_ar       — Arabic area name
	 *   area_name_en       — English area name
	 *   delivery_price     — float, KWD
	 *   express_fee        — float, KWD
	 *   delivery_notes     — Arabic delivery notes
	 *   delivery_notes_en  — English delivery notes
	 *   minimum_order      — float, KWD
	 *   is_enabled         — 0 or 1
	 *   sort_order         — non-negative integer
	 *
	 * @param array $data  Raw POST data.
	 * @return array       Sanitised data.
	 */
	public static function sanitize_area_data( array $data ) {
		$clean = array();

		// Text fields — both language variants
		foreach ( array( 'area_name_ar', 'area_name_en' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_text_field( wp_unslash( $data[ $field ] ) );
			}
		}

		// Numeric / price fields
		foreach ( array( 'delivery_price', 'express_fee', 'minimum_order' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = self::sanitize_price( $data[ $field ] );
			}
		}

		// Notes — textarea (may contain newlines)
		foreach ( array( 'delivery_notes', 'delivery_notes_en' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$clean[ $field ] = sanitize_textarea_field( wp_unslash( $data[ $field ] ) );
			}
		}

		// Boolean flag
		if ( array_key_exists( 'is_enabled', $data ) ) {
			$clean['is_enabled'] = absint( $data['is_enabled'] ) ? 1 : 0;
		}

		// Sort order
		if ( array_key_exists( 'sort_order', $data ) ) {
			$clean['sort_order'] = absint( $data['sort_order'] );
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
	public static function sanitize_price( $value ) {
		$value = str_replace( ',', '.', (string) $value );
		return round( max( 0.0, floatval( $value ) ), 3 );
	}

	/**
	 * Formats a price for display (3 decimal places, no thousands separator).
	 *
	 * @param float|string $price
	 * @return string  e.g. "1.500"
	 */
	public static function format_price( $price ) {
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
	public static function current_user_can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}
}
