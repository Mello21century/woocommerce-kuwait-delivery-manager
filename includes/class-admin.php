<?php
/**
 * KDM_Admin
 *
 * Registers the admin menu group with 4 subpages (Areas, Cities, Import, Settings),
 * enqueues assets per-page, and renders templates.
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    // ---------------------------------------------------------------------------
    // Menu
    // ---------------------------------------------------------------------------

    public function register_menu(): void {
        $capability = KDM_Helper::current_user_can_manage() ? 'manage_options' : 'manage_woocommerce';

        // Top-level menu.
        add_menu_page(
                __( 'Delivery Manager', 'kuwait-delivery-manager' ),
                __( 'Delivery Manager', 'kuwait-delivery-manager' ),
                $capability,
                'kdm-delivery-areas',
                array( $this, 'render_areas_page' ),
                'dashicons-location-alt',
                56
        );

        // Submenu: Areas (re-labels the parent).
        add_submenu_page(
                'kdm-delivery-areas',
                __( 'Delivery Areas', 'kuwait-delivery-manager' ),
                __( 'Areas', 'kuwait-delivery-manager' ),
                $capability,
                'kdm-delivery-areas',
                array( $this, 'render_areas_page' )
        );

        // Submenu: Cities.
        add_submenu_page(
                'kdm-delivery-areas',
                __( 'Delivery Cities', 'kuwait-delivery-manager' ),
                __( 'Cities', 'kuwait-delivery-manager' ),
                $capability,
                'kdm-delivery-cities',
                array( $this, 'render_cities_page' )
        );

        // Submenu: Import.
        add_submenu_page(
                'kdm-delivery-areas',
                __( 'Import', 'kuwait-delivery-manager' ),
                __( 'Import', 'kuwait-delivery-manager' ),
                $capability,
                'kdm-delivery-import',
                array( $this, 'render_import_page' )
        );

        // Submenu: Settings.
        add_submenu_page(
                'kdm-delivery-areas',
                __( 'Delivery Settings', 'kuwait-delivery-manager' ),
                __( 'Settings', 'kuwait-delivery-manager' ),
                $capability,
                'kdm-delivery-settings',
                array( $this, 'render_settings_page' )
        );
    }

    // ---------------------------------------------------------------------------
    // Settings API
    // ---------------------------------------------------------------------------

    public function register_settings(): void {
        register_setting(
                'kdm_settings_group',
                'kdm_express_enabled',
                array(
                        'type'              => 'integer',
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                )
        );

        add_settings_section(
                'kdm_delivery_section',
                __( 'Delivery Settings', 'kuwait-delivery-manager' ),
                null,
                'kdm-delivery-settings'
        );

        add_settings_field(
                'kdm_express_enabled',
                __( 'Express Delivery', 'kuwait-delivery-manager' ),
                array( $this, 'render_express_field' ),
                'kdm-delivery-settings',
                'kdm_delivery_section'
        );
    }

    public function render_express_field(): void {
        $enabled = get_option( 'kdm_express_enabled', 1 );
        ?>
        <label>
            <input type="checkbox"
                   name="kdm_express_enabled"
                   value="1"
                    <?php checked( 1, (int) $enabled ); ?>>
            <?php esc_html_e( 'Enable express delivery option', 'kuwait-delivery-manager' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled: the express fee column is hidden from the admin panel and the delivery type field is removed from checkout.', 'kuwait-delivery-manager' ); ?>
        </p>
        <?php
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    public function render_areas_page(): void {
        if ( ! KDM_Helper::current_user_can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kuwait-delivery-manager' ), 403 );
        }
        include KDM_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function render_cities_page(): void {
        if ( ! KDM_Helper::current_user_can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kuwait-delivery-manager' ), 403 );
        }
        include KDM_PLUGIN_DIR . 'templates/admin-cities-page.php';
    }

    public function render_import_page(): void {
        if ( ! KDM_Helper::current_user_can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kuwait-delivery-manager' ), 403 );
        }
        include KDM_PLUGIN_DIR . 'templates/admin-import-page.php';
    }

    // ---------------------------------------------------------------------------
    // Page renderers
    // ---------------------------------------------------------------------------

    public function render_settings_page(): void {
        if ( ! KDM_Helper::current_user_can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'kuwait-delivery-manager' ), 403 );
        }
        include KDM_PLUGIN_DIR . 'templates/admin-settings-page.php';
    }

    public function enqueue_scripts( $hook ): void {
        // Only on our pages.
        if ( false === strpos( $hook, 'kdm-delivery' ) ) {
            return;
        }

        $country  = $this->get_selected_country();
        $currency = $this->get_currency_symbol();

        // Shared admin CSS for all KDM pages.
        wp_enqueue_style(
                'kdm-admin-style',
                KDM_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                KDM_VERSION
        );

        // Common localized data.
        $common_data = array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'kdm_nonce' ),
                'selectedCountry' => $country,
                'expressEnabled'  => (bool) get_option( 'kdm_express_enabled', 1 ),
                'currency'        => $currency,
        );

        // --- Areas page ---
        if ( false !== strpos( $hook, 'kdm-delivery-areas' ) ) {
            wp_enqueue_script( 'jquery-ui-sortable' );

            wp_enqueue_script(
                    'kdm-admin-script',
                    KDM_PLUGIN_URL . 'assets/js/admin.js',
                    array( 'jquery', 'jquery-ui-sortable' ),
                    KDM_VERSION,
                    true
            );

            wp_localize_script(
                    'kdm-admin-script',
                    'kdmData',
                    array_merge( $common_data, array(
                            'strings' => $this->get_area_strings(),
                    ) )
            );
        }

        // --- Cities page ---
        if ( false !== strpos( $hook, 'kdm-delivery-cities' ) ) {
            wp_enqueue_script( 'jquery-ui-sortable' );

            wp_enqueue_script(
                    'kdm-admin-cities-script',
                    KDM_PLUGIN_URL . 'assets/js/admin-cities.js',
                    array( 'jquery', 'jquery-ui-sortable' ),
                    KDM_VERSION,
                    true
            );

            wp_localize_script(
                    'kdm-admin-cities-script',
                    'kdmCitiesData',
                    array_merge( $common_data, array(
                            'strings' => $this->get_city_strings(),
                    ) )
            );
        }

        // --- Import page ---
        if ( false !== strpos( $hook, 'kdm-delivery-import' ) ) {
            wp_enqueue_script(
                    'kdm-admin-import-script',
                    KDM_PLUGIN_URL . 'assets/js/admin-import.js',
                    array( 'jquery' ),
                    KDM_VERSION,
                    true
            );

            wp_localize_script(
                    'kdm-admin-import-script',
                    'kdmImportData',
                    array_merge( $common_data, array(
                            'strings' => $this->get_import_strings(),
                    ) )
            );
        }
    }

    /**
     * Returns the selected country code from $_GET, defaulting to 'KW'.
     */
    private function get_selected_country(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $country = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : 'KW';

        return strlen( $country ) === 2 ? strtoupper( $country ) : 'KW';
    }

    /**
     * Returns the currency symbol.
     */
    private function get_currency_symbol(): string {
        return function_exists( 'get_woocommerce_currency_symbol' )
                ? get_woocommerce_currency_symbol()
                : __( 'KWD', 'kuwait-delivery-manager' );
    }

    // ---------------------------------------------------------------------------
    // Asset enqueuing
    // ---------------------------------------------------------------------------

    private function get_area_strings(): array {
        return array(
            // Status / feedback
                'loading'                  => __( 'Loading...', 'kuwait-delivery-manager' ),
                'error'                    => __( 'An error occurred. Please try again.', 'kuwait-delivery-manager' ),
                'unsavedChanges'           => __( 'You have unsaved changes. Continue navigating?', 'kuwait-delivery-manager' ),
                'noAreas'                  => __( 'No areas for this city. Click "Add Area" to create the first one.', 'kuwait-delivery-manager' ),
                'orderSaved'               => __( 'Order saved', 'kuwait-delivery-manager' ),
                'close'                    => __( 'Close', 'kuwait-delivery-manager' ),
            // Row actions
                'editArea'                 => __( 'Edit', 'kuwait-delivery-manager' ),
                'saveArea'                 => __( 'Save', 'kuwait-delivery-manager' ),
                'cancelEdit'               => __( 'Cancel', 'kuwait-delivery-manager' ),
                'deleteArea'               => __( 'Delete', 'kuwait-delivery-manager' ),
                'addNewArea'               => __( 'Add Area', 'kuwait-delivery-manager' ),
                'addBtn'                   => __( 'Add', 'kuwait-delivery-manager' ),
            // In-progress
                'saving'                   => __( 'Saving...', 'kuwait-delivery-manager' ),
                'adding'                   => __( 'Adding...', 'kuwait-delivery-manager' ),
            // Confirm
                'confirmDelete'            => __( 'Are you sure you want to delete this area? This cannot be undone.', 'kuwait-delivery-manager' ),
            // Copy-to-all
                'copyFirstToAll'           => __( 'Copy first row value to all rows', 'kuwait-delivery-manager' ),
                'pushToAll'                => __( 'Apply this value to all areas', 'kuwait-delivery-manager' ),
                'copyConfirm'              => __( 'Copy {field} = {value} to all {count} areas?', 'kuwait-delivery-manager' ),
                'pushConfirm'              => __( 'Apply {field} = {value} to all {count} areas?', 'kuwait-delivery-manager' ),
            // Field labels
                'field_delivery_price'     => __( 'Delivery Price', 'kuwait-delivery-manager' ),
                'field_express_fee'        => __( 'Express Fee', 'kuwait-delivery-manager' ),
                'field_minimum_order'      => __( 'Min. Order', 'kuwait-delivery-manager' ),
                'field_free_minimum_order' => __( 'Free Delivery Min.', 'kuwait-delivery-manager' ),
                'field_delivery_notes'     => __( 'Delivery Notes', 'kuwait-delivery-manager' ),
            // Column headers
                'colArea'                  => __( 'Area', 'kuwait-delivery-manager' ),
                'colPrice'                 => __( 'Delivery Price', 'kuwait-delivery-manager' ),
                'colExpress'               => __( 'Express Fee', 'kuwait-delivery-manager' ),
                'colNotes'                 => __( 'Delivery Notes', 'kuwait-delivery-manager' ),
                'colMinOrder'              => __( 'Min. Order', 'kuwait-delivery-manager' ),
                'colFreeMinOrder'          => __( 'Free Delivery Min.', 'kuwait-delivery-manager' ),
                'colStatus'                => __( 'Status', 'kuwait-delivery-manager' ),
                'colActions'               => __( 'Actions', 'kuwait-delivery-manager' ),
            // Toggle
                'toggleOn'                 => __( 'Enabled', 'kuwait-delivery-manager' ),
                'toggleOff'                => __( 'Disabled', 'kuwait-delivery-manager' ),
            // Validation
                'nameRequired'             => __( 'Area name is required (at least one language).', 'kuwait-delivery-manager' ),
            // Drag sort
                'dragSort'                 => __( 'Drag to reorder', 'kuwait-delivery-manager' ),
            // New row
                'autoEnabled'              => __( 'Enabled by default', 'kuwait-delivery-manager' ),
            // Placeholders
                'placeholderNameAr'        => __( 'Name (AR)', 'kuwait-delivery-manager' ),
                'placeholderNameEn'        => __( 'Name (EN)', 'kuwait-delivery-manager' ),
                'placeholderNotesAr'       => __( 'Notes (AR)', 'kuwait-delivery-manager' ),
                'placeholderNotesEn'       => __( 'Notes (EN)', 'kuwait-delivery-manager' ),
        );
    }

    // ---------------------------------------------------------------------------
    // Localized string sets
    // ---------------------------------------------------------------------------

    private function get_city_strings(): array {
        return array(
                'loading'           => __( 'Loading...', 'kuwait-delivery-manager' ),
                'error'             => __( 'An error occurred. Please try again.', 'kuwait-delivery-manager' ),
                'noCities'          => __( 'No cities for this country. Click "Add City" to create the first one.', 'kuwait-delivery-manager' ),
                'orderSaved'        => __( 'Order saved', 'kuwait-delivery-manager' ),
                'editCity'          => __( 'Edit', 'kuwait-delivery-manager' ),
                'saveCity'          => __( 'Save', 'kuwait-delivery-manager' ),
                'cancelEdit'        => __( 'Cancel', 'kuwait-delivery-manager' ),
                'deleteCity'        => __( 'Delete', 'kuwait-delivery-manager' ),
                'addNewCity'        => __( 'Add City', 'kuwait-delivery-manager' ),
                'addBtn'            => __( 'Add', 'kuwait-delivery-manager' ),
                'saving'            => __( 'Saving...', 'kuwait-delivery-manager' ),
                'adding'            => __( 'Adding...', 'kuwait-delivery-manager' ),
                'confirmDelete'     => __( 'Are you sure you want to delete this city and all its areas? This cannot be undone.', 'kuwait-delivery-manager' ),
                'toggleOn'          => __( 'Enabled', 'kuwait-delivery-manager' ),
                'toggleOff'         => __( 'Disabled', 'kuwait-delivery-manager' ),
                'nameRequired'      => __( 'City name is required (at least one language).', 'kuwait-delivery-manager' ),
                'dragSort'          => __( 'Drag to reorder', 'kuwait-delivery-manager' ),
                'placeholderNameAr' => __( 'Name (AR)', 'kuwait-delivery-manager' ),
                'placeholderNameEn' => __( 'Name (EN)', 'kuwait-delivery-manager' ),
                'colCity'           => __( 'City', 'kuwait-delivery-manager' ),
                'colStatus'         => __( 'Status', 'kuwait-delivery-manager' ),
                'colActions'        => __( 'Actions', 'kuwait-delivery-manager' ),
                'colAreas'          => __( 'Areas', 'kuwait-delivery-manager' ),
        );
    }

    private function get_import_strings(): array {
        return array(
                'uploading'        => __( 'Uploading...', 'kuwait-delivery-manager' ),
                'importing'        => __( 'Importing...', 'kuwait-delivery-manager' ),
                'error'            => __( 'An error occurred. Please try again.', 'kuwait-delivery-manager' ),
                'selectFile'       => __( 'Please select a CSV file.', 'kuwait-delivery-manager' ),
                'mapColumns'       => __( 'Map Columns', 'kuwait-delivery-manager' ),
                'importBtn'        => __( 'Import', 'kuwait-delivery-manager' ),
                'backBtn'          => __( 'Back', 'kuwait-delivery-manager' ),
                'skipColumn'       => __( '-- Skip --', 'kuwait-delivery-manager' ),
                'nameEn'           => __( 'English Name', 'kuwait-delivery-manager' ),
                'nameAr'           => __( 'Arabic Name', 'kuwait-delivery-manager' ),
                'deliveryPrice'    => __( 'Delivery Price', 'kuwait-delivery-manager' ),
                'expressFee'       => __( 'Express Fee', 'kuwait-delivery-manager' ),
                'deliveryNotes'    => __( 'Delivery Notes', 'kuwait-delivery-manager' ),
                'minimumOrder'     => __( 'Minimum Order', 'kuwait-delivery-manager' ),
                'freeMinimumOrder' => __( 'Free Delivery Min.', 'kuwait-delivery-manager' ),
                'cityRef'          => __( 'City Reference', 'kuwait-delivery-manager' ),
                'byCityId'         => __( 'By City ID', 'kuwait-delivery-manager' ),
                'byCityNameEn'     => __( 'By English City Name', 'kuwait-delivery-manager' ),
                'byCityNameAr'     => __( 'By Arabic City Name', 'kuwait-delivery-manager' ),
                'csvColumn'        => __( 'CSV Column', 'kuwait-delivery-manager' ),
                'mapTo'            => __( 'Map To', 'kuwait-delivery-manager' ),
                'preview'          => __( 'Preview', 'kuwait-delivery-manager' ),
        );
    }

    /**
     * Returns the WooCommerce country list or a minimal fallback.
     */
    private function get_country_list(): array {
        if ( class_exists( 'WooCommerce' ) && WC()->countries ) {
            return WC()->countries->get_countries();
        }

        return array( 'KW' => 'Kuwait' );
    }
}
