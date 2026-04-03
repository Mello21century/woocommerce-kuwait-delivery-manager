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
 *         - Pick the right language variant of area / governorate names.
 *         - Translate a string through whichever translation plugin is active.
 *
 * Translation plugin priority (highest wins):
 *   Polylang → WPML → TranslatePress → WP core locale
 *
 * Text domain: kuwait-delivery-manager
 * Language files must live in:
 *   /wp-content/plugins/kuwait-delivery-manager/languages/
 *   OR the global /wp-content/languages/plugins/ directory.
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
	 * Loads the plugin's .mo translation file.
	 * WP loads the file from /languages/ inside the plugin folder, falling back
	 * to the global /wp-content/languages/plugins/ directory.
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
	 * These strings then appear in WPML's String Translation screen and
	 * Polylang's Strings Translations screen.
	 */
	public function register_strings() {
		// Build the flat list of strings to register
		$strings = $this->get_registrable_strings();

		foreach ( $strings as $name => $value ) {
			// Polylang
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $name, $value, self::STRING_GROUP );
			}

			// WPML
			if ( has_action( 'wpml_register_single_string' ) ) {
				do_action( 'wpml_register_single_string', self::STRING_GROUP, $name, $value );
			}
		}
	}

	/**
	 * Returns the list of static strings to register with translation plugins.
	 *
	 * @return array<string, string>  name => original_value
	 */
	private function get_registrable_strings() {
		$strings = array(
			// Page headings
			'page_title'    => __( 'Kuwait Delivery Zones Manager', 'kuwait-delivery-manager' ),
			'page_subtitle' => __( 'Select a governorate to view and edit its delivery areas.', 'kuwait-delivery-manager' ),

			// Column headers
			'col_area'      => __( 'Area', 'kuwait-delivery-manager' ),
			'col_price'     => __( 'Delivery Price', 'kuwait-delivery-manager' ),
			'col_express'   => __( 'Express Fee', 'kuwait-delivery-manager' ),
			'col_notes'     => __( 'Delivery Notes', 'kuwait-delivery-manager' ),
			'col_min_order' => __( 'Min. Order', 'kuwait-delivery-manager' ),
			'col_status'    => __( 'Status', 'kuwait-delivery-manager' ),

			// Checkout field labels
			'checkout_gov'       => __( 'Governorate', 'kuwait-delivery-manager' ),
			'checkout_area'      => __( 'Delivery Area', 'kuwait-delivery-manager' ),
			'checkout_type'      => __( 'Delivery Type', 'kuwait-delivery-manager' ),
			'checkout_normal'    => __( 'Standard Delivery', 'kuwait-delivery-manager' ),
			'checkout_express'   => __( 'Express Delivery (extra fee)', 'kuwait-delivery-manager' ),
			'checkout_fee_label' => __( 'Delivery Fee', 'kuwait-delivery-manager' ),
		);

		// Governorate names (both languages)
		foreach ( KDM_Helper::get_governorates() as $key => $names ) {
			$strings[ 'gov_ar_' . $key ] = $names['ar'];
			$strings[ 'gov_en_' . $key ] = $names['en'];
		}

		return $strings;
	}

	// ---------------------------------------------------------------------------
	// Language detection
	// ---------------------------------------------------------------------------

	/**
	 * Returns the active two-letter language code (e.g. 'ar', 'en').
	 * Checks Polylang → WPML → TranslatePress → WP locale, in that order.
	 *
	 * @return string  Lowercase two-letter code, e.g. 'ar', 'en', 'fr'.
	 */
	public static function get_current_lang() {
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

		// TranslatePress / GTranslate — stored in cookie or session
		// (No standard API; fall through to locale)

		// WordPress locale (e.g. 'ar', 'ar_KW', 'en_US')
		$locale = is_admin() ? get_user_locale() : get_locale();
		return strtolower( substr( $locale, 0, 2 ) );
	}

	/**
	 * Returns true when the active language is Arabic.
	 *
	 * @return bool
	 */
	public static function is_arabic() {
		return in_array( self::get_current_lang(), array( 'ar' ), true );
	}

	/**
	 * Returns true when the current WordPress admin interface is RTL.
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		return is_rtl();
	}

	// ---------------------------------------------------------------------------
	// Language-aware content helpers
	// ---------------------------------------------------------------------------

	/**
	 * Returns the appropriate area name based on the current language.
	 * Falls back to Arabic if the English name is empty.
	 *
	 * @param array $area  A row from the kdm_delivery_areas table.
	 * @return string
	 */
	public static function area_name( array $area ) {
		if ( ! self::is_arabic() && ! empty( $area['area_name_en'] ) ) {
			return $area['area_name_en'];
		}
		return $area['area_name_ar'];
	}

	/**
	 * Returns the appropriate delivery notes string for the current language.
	 * Falls back to Arabic if the English version is empty.
	 *
	 * @param array $area  A row from the kdm_delivery_areas table.
	 * @return string
	 */
	public static function delivery_notes( array $area ) {
		if ( ! self::is_arabic() && ! empty( $area['delivery_notes_en'] ) ) {
			return $area['delivery_notes_en'];
		}
		return $area['delivery_notes'] ?? '';
	}

	/**
	 * Returns the governorate display name for the current language.
	 *
	 * @param string $key  Governorate slug.
	 * @return string
	 */
	public static function governorate_name( $key ) {
		$govs = KDM_Helper::get_governorates();
		if ( ! isset( $govs[ $key ] ) ) {
			return $key;
		}
		if ( ! self::is_arabic() && ! empty( $govs[ $key ]['en'] ) ) {
			return $govs[ $key ]['en'];
		}
		return $govs[ $key ]['ar'];
	}

	/**
	 * Translates a string through the active translation plugin.
	 * Falls through to the original string if no translation plugin is found.
	 *
	 * @param string $original  Original English string.
	 * @param string $name      Unique string identifier (used by WPML/Polylang).
	 * @return string
	 */
	public static function translate_string( $original, $name ) {
		// Polylang: pll__() returns the translation if registered
		if ( function_exists( 'pll__' ) ) {
			return pll__( $original );
		}

		// WPML: apply_filters wraps their translation lookup
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			return apply_filters( 'wpml_translate_single_string', $original, self::STRING_GROUP, $name );
		}

		// Default: standard WP i18n (depends on loaded .mo file)
		return $original;
	}
}
