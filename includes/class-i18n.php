<?php
/**
 * KDM_I18n
 *
 * Centralises all internationalisation concerns for Kuwait Delivery Manager:
 *
 *   1.  Loads the plugin text domain so WP core i18n, WPML, and Polylang can
 *       all serve translated .mo files from the /languages directory.
 *
 *   2.  Registers static UI strings with WPML (via wpml_register_single_string)
 *       and Polylang (via pll_register_string) so they appear in those plugins'
 *       string-translation screens.
 *
 *   3.  Provides static helpers used throughout the plugin to:
 *         - Detect the current front-end language code (AR / EN / other).
 *         - Pick the right language variant of city / area names from JSON.
 *         - Translate a string through whichever translation plugin is active.
 *
 * Translation plugin priority (highest wins):
 *   Polylang → WPML → TranslatePress → WP core locale
 *
 * Text domain: kuwait-delivery-manager
 *
 * @package KuwaitDeliveryManager
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_I18n {

	/** String group name used when registering with WPML / Polylang. */
	const STRING_GROUP = 'Kuwait Delivery Manager';

	// ---------------------------------------------------------------------------
	// Boot
	// ---------------------------------------------------------------------------

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'register_strings' ), 20 );
	}

	/**
	 * Returns true when the current WordPress interface is RTL.
	 *
	 * @return bool
	 */
	public static function is_rtl(): bool {
		return is_rtl();
	}

	/**
	 * Returns the appropriate area name based on the current language.
	 * Decodes the JSON area_name column.
	 *
	 * @param array $area A row from the kdm_delivery_areas table.
	 *
	 * @return string
	 */
	public static function area_name( array $area ): string {
		$names = KDM_Helper::decode_json_field( $area['area_name'] ?? '' );

		if ( ! self::is_arabic() && ! empty( $names['en'] ) ) {
			return $names['en'];
		}

		return ! empty( $names['ar'] ) ? $names['ar'] : $names['en'];
	}

	/**
	 * Returns true when the active language is Arabic.
	 *
	 * @return bool
	 */
	public static function is_arabic(): bool {
		return 'ar' === self::get_current_lang();
	}

	// ---------------------------------------------------------------------------
	// Language detection
	// ---------------------------------------------------------------------------

	/**
	 * Returns the active two-letter language code (e.g. 'ar', 'en').
	 *
	 * @return string
	 */
	public static function get_current_lang(): string {
		// Polylang
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			if ( $lang ) {
				return strtolower( $lang );
			}
		}

		// WPML
		if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
			return strtolower( ICL_LANGUAGE_CODE );
		}

		// WordPress locale
		$locale = is_admin() ? get_user_locale() : get_locale();
		
		return strtolower( substr( $locale, 0, 2 ) );
	}

	/**
	 * Returns the appropriate delivery notes for the current language.
	 * Decodes the JSON delivery_notes column.
	 *
	 * @param array $area A row from the kdm_delivery_areas table.
	 *
	 * @return string
	 */
	public static function delivery_notes( array $area ): string {
		$notes = KDM_Helper::decode_json_field( $area['delivery_notes'] ?? '' );

		if ( ! self::is_arabic() && ! empty( $notes['en'] ) ) {
			return $notes['en'];
		}

		return ! empty( $notes['ar'] ) ? $notes['ar'] : $notes['en'];
	}

	/**
	 * Returns the appropriate city name for the current language.
	 * Decodes the JSON city_name column.
	 *
	 * @param array $city A row from the kdm_delivery_cities table.
	 *
	 * @return string
	 */
	public static function city_name( array $city ): string {
		$names = KDM_Helper::decode_json_field( $city['city_name'] ?? '' );

		if ( ! self::is_arabic() && ! empty( $names['en'] ) ) {
			return $names['en'];
		}

		return ! empty( $names['ar'] ) ? $names['ar'] : $names['en'];
	}

	// ---------------------------------------------------------------------------
	// Language-aware content helpers (JSON columns)
	// ---------------------------------------------------------------------------

	/**
	 * Translates a string through the active translation plugin.
	 *
	 * @param string $original Original string.
	 * @param string $name Unique string identifier.
	 *
	 * @return string
	 */
	public static function translate_string( string $original, string $name ): string {
		if ( function_exists( 'pll__' ) ) {
			return pll__( $original );
		}

		if ( has_filter( 'wpml_translate_single_string' ) ) {
			return apply_filters( 'wpml_translate_single_string', $original, self::STRING_GROUP, $name );
		}

		return $original;
	}

	/**
	 * Loads the plugin's .mo translation file.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'kuwait-delivery-manager',
			false,
			dirname( plugin_basename( KDM_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Registers translatable strings with active translation plugins.
	 */
	public function register_strings() {
		$strings = $this->get_registrable_strings();

		foreach ( $strings as $name => $value ) {
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $name, $value, self::STRING_GROUP );
			}

			if ( has_action( 'wpml_register_single_string' ) ) {
				do_action( 'wpml_register_single_string', self::STRING_GROUP, $name, $value );
			}
		}
	}

	/**
	 * Returns the list of static strings to register with translation plugins.
	 *
	 * @return array<string, string>
	 */
	private function get_registrable_strings(): array {
		return array(
			// Page headings
			'page_title'         => __( 'Delivery Areas Manager', 'kuwait-delivery-manager' ),
			'page_subtitle'      => __( 'Select a city to view and edit its delivery areas.', 'kuwait-delivery-manager' ),

			// Column headers
			'col_area'           => __( 'Area', 'kuwait-delivery-manager' ),
			'col_price'          => __( 'Delivery Price', 'kuwait-delivery-manager' ),
			'col_express'        => __( 'Express Fee', 'kuwait-delivery-manager' ),
			'col_notes'          => __( 'Delivery Notes', 'kuwait-delivery-manager' ),
			'col_min_order'      => __( 'Min. Order', 'kuwait-delivery-manager' ),
			'col_status'         => __( 'Status', 'kuwait-delivery-manager' ),

			// Checkout field labels
			'checkout_city'      => __( 'City', 'kuwait-delivery-manager' ),
			'checkout_area'      => __( 'Delivery Area', 'kuwait-delivery-manager' ),
			'checkout_type'      => __( 'Delivery Type', 'kuwait-delivery-manager' ),
			'checkout_normal'    => __( 'Standard Delivery', 'kuwait-delivery-manager' ),
			'checkout_express'   => __( 'Express Delivery (extra fee)', 'kuwait-delivery-manager' ),
			'checkout_fee_label' => __( 'Delivery Fee', 'kuwait-delivery-manager' ),
		);
	}
}
