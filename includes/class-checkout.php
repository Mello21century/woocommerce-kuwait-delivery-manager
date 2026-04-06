<?php
/**
 * KDM_Checkout
 *
 * Wires delivery-areas data into the WooCommerce checkout flow:
 *   - Adds billing fields: City + Area combo select, Delivery Type select
 *   - Session-based fee calculation with free delivery threshold
 *   - Dynamic country support (works with any country that has cities in DB)
 *   - Order meta persistence and display
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Checkout {

    // WC session keys.
    const SESSION_CITY_ID = 'kdm_city_id';
    const SESSION_AREA_ID = 'kdm_area_id';
    const SESSION_DELIVERY_TYPE = 'kdm_delivery_type';

    // Order meta keys.
    const META_CITY_ID = '_kdm_city_id';
    const META_CITY_NAME = '_kdm_city_name';
    const META_AREA_ID = '_kdm_area_id';
    const META_AREA_NAME = '_kdm_area_name';
    const META_DELIVERY_TYPE = '_kdm_delivery_type';
    const META_DELIVERY_PRICE = '_kdm_delivery_price';

    // ---------------------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------------------

    public function __construct() {
        add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ), 20 );
        add_filter( 'woocommerce_form_field_kdm_combo', array( $this, 'render_combo_field' ), 10, 4 );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_delivery_fee' ) );

        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_delivery' ), 5 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_email_delivery' ), 5, 2 );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_delivery' ) );

        add_action( 'wp_ajax_kdm_get_areas_public', array( $this, 'ajax_get_areas' ) );
        add_action( 'wp_ajax_nopriv_kdm_get_areas_public', array( $this, 'ajax_get_areas' ) );
        add_action( 'wp_ajax_kdm_set_area_session', array( $this, 'ajax_set_session' ) );
        add_action( 'wp_ajax_nopriv_kdm_set_area_session', array( $this, 'ajax_set_session' ) );

        add_action( 'woocommerce_cart_emptied', array( $this, 'clear_session' ) );
    }

    // ---------------------------------------------------------------------------
    // 1. Billing fields
    // ---------------------------------------------------------------------------

    public function add_billing_fields( array $fields ): array {
        $session         = WC()->session;
        $express_enabled = (bool) get_option( 'kdm_express_enabled', 1 );

        $fields['billing_area_id_kdm'] = array(
                'type'     => 'kdm_combo',
                'label'    => __( 'Delivery Area', 'kuwait-delivery-manager' ),
                'required' => true,
                'class'    => array( 'form-row-wide', 'kdm-field-combo' ),
                'priority' => 45,
        );

        if ( $express_enabled ) {
            $saved_type = $session ? (string) $session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal';

            $fields['billing_delivery_type_kdm'] = array(
                    'label'    => __( 'Delivery Type', 'kuwait-delivery-manager' ),
                    'type'     => 'select',
                    'required' => false,
                    'class'    => array( 'form-row-wide', 'kdm-field-type' ),
                    'options'  => array(
                            'normal'  => __( 'Standard Delivery', 'kuwait-delivery-manager' ),
                            'express' => __( 'Express Delivery (extra fee)', 'kuwait-delivery-manager' ),
                    ),
                    'default'  => $saved_type,
                    'priority' => 46,
            );
        }

        return $fields;
    }

    // ---------------------------------------------------------------------------
    // Custom field type: kdm_combo
    // ---------------------------------------------------------------------------

    public function render_combo_field( $field, $key, $args, $value ): string {
        $session    = WC()->session;
        $saved_area = $session ? (int) $session->get( self::SESSION_AREA_ID, 0 ) : 0;
        $saved_city = '';
        $saved_name = '';

        // Determine billing country for pre-loading areas.
        $billing_country = '';
        if ( WC()->customer ) {
            $billing_country = WC()->customer->get_billing_country();
        }
        if ( empty( $billing_country ) && function_exists( 'wc_get_base_location' ) ) {
            $base            = wc_get_base_location();
            $billing_country = $base['country'] ?? 'KW';
        }

        $grouped = KDM_Database::get_all_areas_grouped_by_city( sanitize_text_field( $billing_country ) );

        // Find saved area details.
        if ( $saved_area ) {
            foreach ( $grouped as $cid => $group ) {
                foreach ( $group['areas'] as $area ) {
                    if ( (int) $area['area_id'] === $saved_area ) {
                        $saved_city = $cid;
                        $saved_name = KDM_I18n::area_name( $area );
                        break 2;
                    }
                }
            }
        }

        $required_html = ! empty( $args['required'] )
                ? ' <abbr class="required" title="' . esc_attr_x( 'required', 'required field', 'woocommerce' ) . '">*</abbr>'
                : '';

        $class_str = implode( ' ', array_map( 'sanitize_html_class', (array) ( $args['class'] ?? array() ) ) );

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
                ? get_woocommerce_currency_symbol()
                : __( 'KWD', 'kuwait-delivery-manager' );

        ob_start();
        ?>
        <div class="form-row <?php echo esc_attr( $class_str ); ?> kdm-combo-wrap"
             id="<?php echo esc_attr( $key ); ?>_field">
            <label><?php echo wp_kses_post( $args['label'] . $required_html ); ?></label>

            <input type="hidden"
                   name="<?php echo esc_attr( $key ); ?>"
                   id="<?php echo esc_attr( $key ); ?>"
                   value="<?php echo esc_attr( $saved_area ?: '' ); ?>">

            <input type="hidden"
                   name="billing_city_id_kdm"
                   id="billing_city_id_kdm"
                   value="<?php echo esc_attr( $saved_city ); ?>">

            <div class="kdm-combo-trigger"
                 id="kdm-combo-trigger"
                 tabindex="0"
                 role="combobox"
                 aria-haspopup="listbox"
                 aria-expanded="false"
                 aria-controls="kdm-combo-panel">
				<span class="kdm-combo-placeholder<?php echo $saved_name ? ' has-value' : ''; ?>">
					<?php echo $saved_name ? esc_html( $saved_name ) : esc_html__( '-- Select area --', 'kuwait-delivery-manager' ); ?>
				</span>
                <span class="kdm-combo-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </div>

            <div class="kdm-combo-panel" id="kdm-combo-panel" role="listbox" style="display:none">
                <div class="kdm-combo-search-wrap">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="text"
                           class="kdm-combo-search"
                           placeholder="<?php esc_attr_e( 'Search for an area...', 'kuwait-delivery-manager' ); ?>"
                           autocomplete="off"
                           aria-label="<?php esc_attr_e( 'Search areas', 'kuwait-delivery-manager' ); ?>">
                </div>
                <div class="kdm-combo-list">
                    <?php foreach ( $grouped as $cid => $group ) :
                        $city_names = KDM_Helper::decode_json_field( $group['city']['city_name'] );
                        ?>
                        <div class="kdm-combo-group" data-city="<?php echo esc_attr( $cid ); ?>">
                            <div class="kdm-combo-group-header">
                                <span class="kdm-group"><?php echo esc_html( $city_names[ KDM_I18n::get_current_lang() ] ); ?></span>
                            </div>
                            <?php foreach ( $group['areas'] as $area ) :
                                $display = KDM_I18n::area_name( $area );
                                $is_selected = ( $saved_area === (int) $area['area_id'] );
                                ?>
                                <div class="kdm-combo-item<?php echo $is_selected ? ' kdm-selected' : ''; ?>"
                                     role="option"
                                     aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
                                     data-value="<?php echo esc_attr( $area['area_id'] ); ?>"
                                     data-city="<?php echo esc_attr( $cid ); ?>"
                                     data-price="<?php echo esc_attr( $area['delivery_price'] ); ?>"
                                     data-express="<?php echo esc_attr( $area['express_fee'] ); ?>"
                                     data-minimum="<?php echo esc_attr( $area['minimum_order'] ); ?>"
                                     data-freeminimum="<?php echo esc_attr( $area['free_minimum_order'] ?? 0 ); ?>"
                                     data-name="<?php echo esc_attr( $display ); ?>">
                                    <span class="kdm-item-name"><?php echo esc_html( $display ); ?></span>
                                    <span class="kdm-item-price"><?php echo esc_html( KDM_Helper::format_price( $area['delivery_price'] ) ); ?><?php echo esc_html( $currency_symbol ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="kdm-combo-no-results" style="display:none">
                    <?php esc_html_e( 'No results match your search', 'kuwait-delivery-manager' ); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---------------------------------------------------------------------------
    // 2. Assets
    // ---------------------------------------------------------------------------

    public function enqueue_scripts(): void {
        if ( ! is_checkout() ) {
            return;
        }

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' )
                ? get_woocommerce_currency_symbol()
                : __( 'KWD', 'kuwait-delivery-manager' );

        wp_enqueue_style(
                'kdm-checkout-style',
                KDM_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                KDM_VERSION
        );

        wp_enqueue_script(
                'kdm-checkout-script',
                KDM_PLUGIN_URL . 'assets/js/checkout.js',
                array( 'jquery' ),
                KDM_VERSION,
                true
        );

        wp_localize_script(
                'kdm-checkout-script',
                'kdmCheckout',
                array(
                        'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
                        'nonce'               => wp_create_nonce( 'kdm_checkout_nonce' ),
                        'savedAreaId'         => WC()->session ? (int) WC()->session->get( self::SESSION_AREA_ID, 0 ) : 0,
                        'savedDeliveryType'   => WC()->session ? (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal',
                        'expressEnabled'      => (bool) get_option( 'kdm_express_enabled', 1 ),
                        'countriesWithCities' => KDM_Database::get_countries_with_cities(),
                        'cartSubtotal'        => WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0,
                        'strings'             => array(
                                'selectArea'    => __( '-- Select area --', 'kuwait-delivery-manager' ),
                                'noResults'     => __( 'No results match your search', 'kuwait-delivery-manager' ),
                                'priceNormal'   => __( 'Standard Delivery', 'kuwait-delivery-manager' ),
                                'priceExpress'  => __( 'Express Delivery', 'kuwait-delivery-manager' ),
                                'currency'      => $currency_symbol,
                                'fieldRequired' => __( 'Delivery area is a required field.', 'kuwait-delivery-manager' ),
                                'freeDelivery'  => __( 'Free', 'kuwait-delivery-manager' ),
                            /* translators: %s: formatted minimum order price */
                                'freeOver'      => __( 'Free on orders over %s', 'kuwait-delivery-manager' ),
                            /* translators: %s: formatted minimum order price */
                                'minOrderNote'  => __( 'Min. order: %s', 'kuwait-delivery-manager' ),
                        ),
                )
        );
    }

    // ---------------------------------------------------------------------------
    // 3. AJAX handlers (front-end, guest-safe)
    // ---------------------------------------------------------------------------

    /**
     * Returns all enabled areas for a city.
     */
    public function ajax_get_areas(): void {
        if ( ! check_ajax_referer( 'kdm_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'kuwait-delivery-manager' ) ), 403 );
        }

        $city_id = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;

        if ( ! $city_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid city ID.', 'kuwait-delivery-manager' ) ) );
        }

        $areas = KDM_Database::get_areas_by_city( $city_id );

        foreach ( $areas as &$area ) {
            $area['area_name_decoded']      = KDM_Helper::decode_json_field( $area['area_name'] );
            $area['delivery_notes_decoded'] = KDM_Helper::decode_json_field( $area['delivery_notes'] );
        }
        unset( $area );

        wp_send_json_success( array( 'areas' => $areas ) );
    }

    /**
     * Saves customer's selection to WC session.
     */
    public function ajax_set_session(): void {
        if ( ! check_ajax_referer( 'kdm_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security verification failed.', 'kuwait-delivery-manager' ) ), 403 );
        }

        $area_id       = isset( $_POST['area_id'] ) ? absint( $_POST['area_id'] ) : 0;
        $city_id       = isset( $_POST['city_id'] ) ? absint( $_POST['city_id'] ) : 0;
        $delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) ) : 'normal';

        if ( ! in_array( $delivery_type, array( 'normal', 'express' ), true ) ) {
            $delivery_type = 'normal';
        }

        if ( WC()->session ) {
            WC()->session->set( self::SESSION_CITY_ID, $city_id );
            WC()->session->set( self::SESSION_AREA_ID, $area_id );
            WC()->session->set( self::SESSION_DELIVERY_TYPE, $delivery_type );
        }

        $fee_data = $this->calculate_fee_data( $area_id, $delivery_type );

        wp_send_json_success(
                array(
                        'area_id'       => $area_id,
                        'delivery_type' => $delivery_type,
                        'fee_amount'    => $fee_data ? KDM_Helper::format_price( $fee_data['amount'] ) : '0.000',
                        'fee_label'     => $fee_data ? $fee_data['label'] : '',
                )
        );
    }

    // ---------------------------------------------------------------------------
    // 4. Cart fee
    // ---------------------------------------------------------------------------

    /**
     * Calculates fee amount and label.
     * free_minimum_order: if > 0 and cart subtotal >= free_minimum_order, fees become 0.
     */
    private function calculate_fee_data( int $area_id, string $delivery_type ): ?array {
        if ( ! $area_id ) {
            return null;
        }

        $area = KDM_Database::get_area( $area_id );

        if ( ! $area || ! (int) $area['is_active'] ) {
            return null;
        }

        $normal_price  = (float) $area['delivery_price'];
        $express_extra = (float) $area['express_fee'];
        $free_min      = (float) ( $area['free_minimum_order'] ?? 0 );

        // Free delivery threshold.
        if ( $free_min > 0 && WC()->cart ) {
            $cart_subtotal = (float) WC()->cart->get_subtotal();
            if ( $cart_subtotal >= $free_min ) {
                $normal_price  = 0.0;
                $express_extra = 0.0;
            }
        }

        $area_name = KDM_I18n::area_name( $area );

        if ( 'express' === $delivery_type && $express_extra > 0 ) {
            return array(
                /* translators: %s: area name */
                    'label'  => sprintf( __( 'Express delivery fee - %s', 'kuwait-delivery-manager' ), $area_name ),
                    'amount' => round( $normal_price + $express_extra, 3 ),
            );
        }

        return array(
            /* translators: %s: area name */
                'label'  => sprintf( __( 'Delivery fee - %s', 'kuwait-delivery-manager' ), $area_name ),
                'amount' => $normal_price,
        );
    }

    public function apply_delivery_fee( $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! WC()->session ) {
            return;
        }

        $area_id       = (int) WC()->session->get( self::SESSION_AREA_ID, 0 );
        $delivery_type = (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' );

        $fee_data = $this->calculate_fee_data( $area_id, $delivery_type );

        if ( $fee_data && $fee_data['amount'] > 0 ) {
            $cart->add_fee( $fee_data['label'], $fee_data['amount'], false );
        }
    }

    // ---------------------------------------------------------------------------
    // 5. Validation
    // ---------------------------------------------------------------------------

    public function validate_fields(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $area_id       = isset( $_POST['billing_area_id_kdm'] ) ? absint( $_POST['billing_area_id_kdm'] ) : 0;
        $delivery_type = isset( $_POST['billing_delivery_type_kdm'] ) ? sanitize_key( wp_unslash( $_POST['billing_delivery_type_kdm'] ) ) : 'normal';
        $country       = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Only validate if the billing country has delivery cities.
        $countries_with_cities = KDM_Database::get_countries_with_cities();
        if ( ! in_array( $country, $countries_with_cities, true ) ) {
            // Country doesn't have delivery data — clear any stale session.
            $this->clear_session();

            return;
        }

        if ( ! $area_id ) {
            wc_add_notice(
                    '<strong>' . esc_html__( 'Delivery Area', 'kuwait-delivery-manager' ) . '</strong> ' .
                    esc_html__( 'is a required field.', 'kuwait-delivery-manager' ),
                    'error'
            );

            return;
        }

        $area = KDM_Database::get_area( $area_id );

        if ( ! $area ) {
            wc_add_notice(
                    esc_html__( 'The selected area was not found. Please refresh the page and try again.', 'kuwait-delivery-manager' ),
                    'error'
            );

            return;
        }

        if ( ! (int) $area['is_active'] ) {
            wc_add_notice(
                    sprintf(
                    /* translators: %s: area name */
                            esc_html__( 'Area %s is currently unavailable for delivery.', 'kuwait-delivery-manager' ),
                            '<strong>' . esc_html( KDM_I18n::area_name( $area ) ) . '</strong>'
                    ),
                    'error'
            );

            return;
        }

        // Minimum order check — area is unavailable if cart total is below threshold.
        $min_order = (float) ( $area['minimum_order'] ?? 0 );
        if ( $min_order > 0 ) {
            $subtotal = WC()->cart ? (float) WC()->cart->get_subtotal() : 0.0;
            if ( $subtotal < $min_order ) {
                wc_add_notice(
                        sprintf(
                        /* translators: 1: area name 2: minimum order price */
                                esc_html__( 'A minimum order of %2$s is required to deliver to %1$s.', 'kuwait-delivery-manager' ),
                                '<strong>' . esc_html( KDM_I18n::area_name( $area ) ) . '</strong>',
                                '<strong>' . wc_price( $min_order ) . '</strong>'
                        ),
                        'error'
                );

                return;
            }
        }

        // Express fallback.
        if ( 'express' === $delivery_type && (float) $area['express_fee'] <= 0 ) {
            $_POST['billing_delivery_type_kdm'] = 'normal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
    }

    // ---------------------------------------------------------------------------
    // 6. Save order meta
    // ---------------------------------------------------------------------------

    public function clear_session(): void {
        if ( WC()->session ) {
            WC()->session->__unset( self::SESSION_CITY_ID );
            WC()->session->__unset( self::SESSION_AREA_ID );
            WC()->session->__unset( self::SESSION_DELIVERY_TYPE );
        }
    }

    // ---------------------------------------------------------------------------
    // 7. Display in order views / emails / admin
    // ---------------------------------------------------------------------------

    public function save_order_meta( $order_id ): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $area_id       = isset( $_POST['billing_area_id_kdm'] ) ? absint( $_POST['billing_area_id_kdm'] ) : 0;
        $delivery_type = isset( $_POST['billing_delivery_type_kdm'] ) ? sanitize_key( wp_unslash( $_POST['billing_delivery_type_kdm'] ) ) : 'normal';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $area_id ) {
            $area = KDM_Database::get_area( $area_id );
            if ( $area ) {
                $city      = KDM_Database::get_city( absint( $area['city_id'] ) );
                $city_name = $city ? KDM_I18n::city_name( $city ) : '';
                $area_name = KDM_I18n::area_name( $area );

                $fee_data = $this->calculate_fee_data( $area_id, $delivery_type );

                update_post_meta( $order_id, self::META_CITY_ID, absint( $area['city_id'] ) );
                update_post_meta( $order_id, self::META_CITY_NAME, $city_name );
                update_post_meta( $order_id, self::META_AREA_ID, $area_id );
                update_post_meta( $order_id, self::META_AREA_NAME, $area_name );
                update_post_meta( $order_id, self::META_DELIVERY_TYPE, $delivery_type );
                update_post_meta( $order_id, self::META_DELIVERY_PRICE, $fee_data ? $fee_data['amount'] : 0 );
            }
        }

        $this->clear_session();
    }

    public function display_order_delivery( $order ): void {
        $city_name = get_post_meta( $order->get_id(), self::META_CITY_NAME, true );
        $area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
        $type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
        $price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

        if ( ! $city_name && ! $area_name ) {
            return;
        }

        $type_label = ( 'express' === $type )
                ? __( 'Express Delivery', 'kuwait-delivery-manager' )
                : __( 'Standard Delivery', 'kuwait-delivery-manager' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : __( 'KWD', 'kuwait-delivery-manager' );
        ?>
        <section class="kdm-order-delivery woocommerce-order-details" style="margin-bottom:24px;">
            <h2 class="woocommerce-order-details__title"
                style="font-size:18px;margin-bottom:12px;"><?php esc_html_e( 'Delivery Details', 'kuwait-delivery-manager' ); ?></h2>
            <table class="woocommerce-table woocommerce-table--order-details shop_table" style="width:100%;">
                <tbody>
                <?php if ( $city_name ) : ?>
                    <tr>
                        <th scope="row"
                            style="padding:8px 12px;"><?php esc_html_e( 'City', 'kuwait-delivery-manager' ); ?></th>
                        <td style="padding:8px 12px;"><?php echo esc_html( $city_name ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $area_name ) : ?>
                    <tr>
                        <th scope="row"
                            style="padding:8px 12px;"><?php esc_html_e( 'Area', 'kuwait-delivery-manager' ); ?></th>
                        <td style="padding:8px 12px;"><?php echo esc_html( $area_name ); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row"
                        style="padding:8px 12px;"><?php esc_html_e( 'Delivery Type', 'kuwait-delivery-manager' ); ?></th>
                    <td style="padding:8px 12px;"><?php echo esc_html( $type_label ); ?></td>
                </tr>
                <?php if ( $price ) : ?>
                    <tr>
                        <th scope="row"
                            style="padding:8px 12px;"><?php esc_html_e( 'Delivery Fee', 'kuwait-delivery-manager' ); ?></th>
                        <td style="padding:8px 12px;font-weight:600;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?><?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
    }

    public function display_email_delivery( $order, $sent_to_admin ): void {
        $city_name = get_post_meta( $order->get_id(), self::META_CITY_NAME, true );
        $area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
        $type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
        $price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

        if ( ! $city_name && ! $area_name ) {
            return;
        }

        $type_label = ( 'express' === $type )
                ? __( 'Express Delivery', 'kuwait-delivery-manager' )
                : __( 'Standard Delivery', 'kuwait-delivery-manager' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : __( 'KWD', 'kuwait-delivery-manager' );
        ?>
        <div style="margin-bottom:24px;font-family:Arial,sans-serif;">
            <h2 style="font-size:18px;color:#333;border-bottom:2px solid #e5e5e5;padding-bottom:8px;"><?php esc_html_e( 'Delivery Details', 'kuwait-delivery-manager' ); ?></h2>
            <table style="width:100%;border-collapse:collapse;">
                <?php if ( $city_name ) : ?>
                    <tr>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;"><?php esc_html_e( 'City', 'kuwait-delivery-manager' ); ?></td>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $city_name ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $area_name ) : ?>
                    <tr>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;"><?php esc_html_e( 'Area', 'kuwait-delivery-manager' ); ?></td>
                        <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $area_name ); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;"><?php esc_html_e( 'Delivery Type', 'kuwait-delivery-manager' ); ?></td>
                    <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $type_label ); ?></td>
                </tr>
                <?php if ( $price ) : ?>
                    <tr>
                        <td style="padding:8px 12px;font-weight:bold;"><?php esc_html_e( 'Delivery Fee', 'kuwait-delivery-manager' ); ?></td>
                        <td style="padding:8px 12px;font-weight:600;color:#2271b1;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?><?php echo esc_html( $currency ); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    public function display_admin_delivery( $order ): void {
        $city_name = get_post_meta( $order->get_id(), self::META_CITY_NAME, true );
        $area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
        $type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
        $price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

        if ( ! $city_name && ! $area_name ) {
            return;
        }

        $type_label = ( 'express' === $type )
                ? __( 'Express Delivery', 'kuwait-delivery-manager' )
                : __( 'Standard Delivery', 'kuwait-delivery-manager' );
        $currency   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : __( 'KWD', 'kuwait-delivery-manager' );
        ?>
        <div class="kdm-admin-delivery"
             style="margin-top:12px;padding:12px 14px;background:#f9fafb;border:1px solid #dcdcde;border-radius:5px;">
            <strong style="display:block;margin-bottom:8px;font-size:13px;color:#1d2327;"><?php esc_html_e( 'Delivery Area', 'kuwait-delivery-manager' ); ?></strong>
            <?php if ( $city_name ) : ?>
                <p style="margin:4px 0;font-size:13px;">
                    <span style="color:#646970;"><?php esc_html_e( 'City:', 'kuwait-delivery-manager' ); ?></span>
                    <strong><?php echo esc_html( $city_name ); ?></strong>
                </p>
            <?php endif; ?>
            <?php if ( $area_name ) : ?>
                <p style="margin:4px 0;font-size:13px;">
                    <span style="color:#646970;"><?php esc_html_e( 'Area:', 'kuwait-delivery-manager' ); ?></span>
                    <strong><?php echo esc_html( $area_name ); ?></strong>
                </p>
            <?php endif; ?>
            <p style="margin:4px 0;font-size:13px;">
                <span style="color:#646970;"><?php esc_html_e( 'Delivery Type:', 'kuwait-delivery-manager' ); ?></span>
                <strong><?php echo esc_html( $type_label ); ?></strong>
            </p>
            <?php if ( $price ) : ?>
                <p style="margin:4px 0;font-size:13px;">
                    <span style="color:#646970;"><?php esc_html_e( 'Fee:', 'kuwait-delivery-manager' ); ?></span>
                    <strong style="color:#2271b1;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?><?php echo esc_html( $currency ); ?></strong>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
