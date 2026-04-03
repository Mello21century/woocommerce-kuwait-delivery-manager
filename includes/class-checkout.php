<?php
/**
 * KDM_Checkout
 *
 * Wires the delivery-areas data into the WooCommerce checkout flow:
 *
 *   1.  Adds two required billing selects — Governorate + Area — and a
 *       Delivery Type select (normal / express) that is hidden when the
 *       chosen area has no express surcharge.
 *
 *   2.  Pre-populates selects from WC session so a page refresh does not
 *       lose the customer's selection.
 *
 *   3.  Registers two front-end AJAX actions (support guests):
 *         kdm_get_areas_public  — returns enabled areas for a governorate
 *         kdm_set_area_session  — saves selection to WC session and triggers
 *                                 a checkout totals refresh
 *
 *   4.  Applies the delivery fee (or express fee) in
 *       woocommerce_cart_calculate_fees using session data.
 *
 *   5.  Validates fields on checkout submit (required, area enabled,
 *       minimum order check).
 *
 *   6.  Saves governorate / area / price to order meta on order creation.
 *
 *   7.  Displays delivery details in:
 *         - Thank-you page / My Account order view
 *         - All WooCommerce order emails
 *         - Admin order edit screen (billing panel)
 *
 * This class is only instantiated when WooCommerce is active (see KDM_Plugin).
 *
 * @package KuwaitDeliveryManager
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Checkout {

	// WC session keys used throughout this class
	const SESSION_GOV_KEY       = 'kdm_gov_key';
	const SESSION_AREA_ID       = 'kdm_area_id';
	const SESSION_DELIVERY_TYPE = 'kdm_delivery_type';

	// Order meta keys
	const META_GOV_KEY        = '_kdm_governorate_key';
	const META_GOV_NAME       = '_kdm_governorate_name';
	const META_AREA_ID        = '_kdm_area_id';
	const META_AREA_NAME      = '_kdm_area_name';
	const META_DELIVERY_TYPE  = '_kdm_delivery_type';
	const META_DELIVERY_PRICE = '_kdm_delivery_price';

	// ---------------------------------------------------------------------------
	// Constructor — registers all hooks
	// ---------------------------------------------------------------------------

	public function __construct() {
		// Add fields to the billing section
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_fields' ), 20 );

		// Register the custom 'kdm_combo' field type renderer
		add_filter( 'woocommerce_form_field_kdm_combo', array( $this, 'render_combo_field' ), 10, 4 );

		// Enqueue checkout assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Validation, save, fee
		add_action( 'woocommerce_checkout_process',          array( $this, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_cart_calculate_fees',        array( $this, 'apply_delivery_fee' ) );

		// Display delivery info in order views and emails
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_order_delivery' ), 5 );
		add_action( 'woocommerce_email_after_order_table',         array( $this, 'display_email_delivery' ), 5, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_delivery' ) );

		// Front-end AJAX — must work for guests (nopriv) as well
		add_action( 'wp_ajax_kdm_get_areas_public',        array( $this, 'ajax_get_areas' ) );
		add_action( 'wp_ajax_nopriv_kdm_get_areas_public', array( $this, 'ajax_get_areas' ) );
		add_action( 'wp_ajax_kdm_set_area_session',        array( $this, 'ajax_set_session' ) );
		add_action( 'wp_ajax_nopriv_kdm_set_area_session', array( $this, 'ajax_set_session' ) );

		// Clean up session when the cart is emptied
		add_action( 'woocommerce_cart_emptied', array( $this, 'clear_session' ) );
	}

	// ---------------------------------------------------------------------------
	// 1. Billing fields
	// ---------------------------------------------------------------------------

	/**
	 * Injects the three custom checkout fields into the billing section.
	 * Priority 45-47 places them after the country field, before the address.
	 *
	 * @param array $fields  Existing WooCommerce billing fields.
	 * @return array
	 */
	public function add_billing_fields( array $fields ) {
		$session         = WC()->session;
		$express_enabled = (bool) get_option( 'kdm_express_enabled', 1 );

		// Single combined area combo field (custom type rendered by render_combo_field)
		$fields['billing_area_id_kdm'] = array(
			'type'     => 'kdm_combo',
			'label'    => __( 'منطقة التوصيل', 'kuwait-delivery-manager' ),
			'required' => true,
			'class'    => array( 'form-row-wide', 'kdm-field-combo' ),
			'priority' => 45,
		);

		// Delivery type select — only registered when express delivery is globally enabled
		if ( $express_enabled ) {
			$saved_type = $session ? (string) $session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal';

			$fields['billing_delivery_type_kdm'] = array(
				'label'    => __( 'نوع التوصيل', 'kuwait-delivery-manager' ),
				'type'     => 'select',
				'required' => false,
				'class'    => array( 'form-row-wide', 'kdm-field-type' ),
				'options'  => array(
					'normal'  => __( 'توصيل عادي',                 'kuwait-delivery-manager' ),
					'express' => __( 'توصيل سريع (رسوم إضافية)',  'kuwait-delivery-manager' ),
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

	/**
	 * Renders the combined governorate + area searchable combo dropdown.
	 * Called via the woocommerce_form_field_kdm_combo filter.
	 *
	 * @param string $field  Initial field HTML (empty for unknown types).
	 * @param string $key    Field key (billing_area_id_kdm).
	 * @param array  $args   Field arguments array.
	 * @param mixed  $value  Current field value (unused — restored from session).
	 * @return string  Full field HTML.
	 */
	public function render_combo_field( $field, $key, $args, $value ) {
		$session      = WC()->session;
		$saved_area   = $session ? (int) $session->get( self::SESSION_AREA_ID, 0 ) : 0;
		$saved_gov    = '';
		$saved_name   = '';

		// Pre-load all enabled areas grouped by governorate
		$governorates = KDM_Helper::get_governorates();
		$grouped      = KDM_Database::get_all_areas_grouped();

		// Find saved area details for the display label and gov hidden input
		if ( $saved_area ) {
			foreach ( $grouped as $gov_key => $areas ) {
				foreach ( $areas as $area ) {
					if ( (int) $area['id'] === $saved_area ) {
						$saved_gov  = $gov_key;
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

		ob_start();
		?>
		<div class="form-row <?php echo esc_attr( $class_str ); ?> kdm-combo-wrap" id="<?php echo esc_attr( $key ); ?>_field">
			<label><?php echo wp_kses_post( $args['label'] . $required_html ); ?></label>

			<?php /* Hidden input carries area_id for WC form processing */ ?>
			<input type="hidden"
				   name="<?php echo esc_attr( $key ); ?>"
				   id="<?php echo esc_attr( $key ); ?>"
				   value="<?php echo esc_attr( $saved_area ?: '' ); ?>">

			<?php /* Hidden input carries resolved gov key for validate_fields / save_order_meta */ ?>
			<input type="hidden"
				   name="billing_governorate_kdm"
				   id="billing_governorate_kdm"
				   value="<?php echo esc_attr( $saved_gov ); ?>">

			<?php /* Visible trigger button */ ?>
			<div class="kdm-combo-trigger"
				 id="kdm-combo-trigger"
				 tabindex="0"
				 role="combobox"
				 aria-haspopup="listbox"
				 aria-expanded="false"
				 aria-controls="kdm-combo-panel">
				<span class="kdm-combo-placeholder<?php echo $saved_name ? ' has-value' : ''; ?>">
					<?php echo $saved_name ? esc_html( $saved_name ) : esc_html__( '— اختر المنطقة —', 'kuwait-delivery-manager' ); ?>
				</span>
				<span class="kdm-combo-arrow dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</div>

			<?php /* Dropdown panel */ ?>
			<div class="kdm-combo-panel" id="kdm-combo-panel" role="listbox" style="display:none">
				<div class="kdm-combo-search-wrap">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="text"
						   class="kdm-combo-search"
						   placeholder="<?php esc_attr_e( 'ابحث عن منطقة…', 'kuwait-delivery-manager' ); ?>"
						   autocomplete="off"
						   aria-label="<?php esc_attr_e( 'البحث في المناطق', 'kuwait-delivery-manager' ); ?>">
				</div>
				<div class="kdm-combo-list">
					<?php foreach ( $grouped as $gov_key => $areas ) :
						$gov_names = $governorates[ $gov_key ] ?? array( 'ar' => $gov_key, 'en' => $gov_key );
					?>
					<div class="kdm-combo-group" data-gov="<?php echo esc_attr( $gov_key ); ?>">
						<div class="kdm-combo-group-header">
							<span class="kdm-group-ar"><?php echo esc_html( $gov_names['ar'] ); ?></span>
							<span class="kdm-group-en"><?php echo esc_html( $gov_names['en'] ); ?></span>
						</div>
						<?php foreach ( $areas as $area ) :
							$display     = KDM_I18n::area_name( $area );
							$is_selected = ( $saved_area === (int) $area['id'] );
						?>
						<div class="kdm-combo-item<?php echo $is_selected ? ' kdm-selected' : ''; ?>"
							 role="option"
							 aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
							 data-value="<?php echo esc_attr( $area['id'] ); ?>"
							 data-gov="<?php echo esc_attr( $gov_key ); ?>"
							 data-price="<?php echo esc_attr( $area['delivery_price'] ); ?>"
							 data-express="<?php echo esc_attr( $area['express_fee'] ); ?>"
							 data-name="<?php echo esc_attr( $display ); ?>">
							<span class="kdm-item-name"><?php echo esc_html( $display ); ?></span>
							<span class="kdm-item-price"><?php echo esc_html( KDM_Helper::format_price( $area['delivery_price'] ) ); ?> <?php esc_html_e( 'د.ك', 'kuwait-delivery-manager' ); ?></span>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="kdm-combo-no-results" style="display:none">
					<?php esc_html_e( 'لا توجد نتائج تطابق البحث', 'kuwait-delivery-manager' ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ---------------------------------------------------------------------------
	// 2. Assets
	// ---------------------------------------------------------------------------

	/**
	 * Enqueues checkout-specific CSS and JS — only on the checkout page.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

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
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'kdm_checkout_nonce' ),
				'savedAreaId'       => WC()->session ? (int)    WC()->session->get( self::SESSION_AREA_ID, 0 )              : 0,
				'savedDeliveryType' => WC()->session ? (string) WC()->session->get( self::SESSION_DELIVERY_TYPE, 'normal' ) : 'normal',
				'expressEnabled'    => (bool) get_option( 'kdm_express_enabled', 1 ),
				'strings'           => array(
					'selectArea'    => __( '— اختر المنطقة —',         'kuwait-delivery-manager' ),
					'noResults'     => __( 'لا توجد نتائج تطابق البحث', 'kuwait-delivery-manager' ),
					'priceNormal'   => __( 'توصيل عادي',               'kuwait-delivery-manager' ),
					'priceExpress'  => __( 'توصيل سريع',               'kuwait-delivery-manager' ),
					'kwd'           => __( 'د.ك',                       'kuwait-delivery-manager' ),
					'fieldRequired' => __( 'منطقة التوصيل حقل مطلوب.', 'kuwait-delivery-manager' ),
				),
			)
		);
	}

	// ---------------------------------------------------------------------------
	// 3. AJAX handlers (front-end, support guest checkout)
	// ---------------------------------------------------------------------------

	/**
	 * Returns all enabled areas for a governorate.
	 * Used by the checkout JS to populate the area select.
	 *
	 * POST params: governorate_key, nonce
	 */
	public function ajax_get_areas() {
		// Verify the checkout-specific nonce (prevents cross-site request forgery)
		if ( ! check_ajax_referer( 'kdm_checkout_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ), 403 );
		}

		$gov_key = isset( $_POST['governorate_key'] ) ? sanitize_key( wp_unslash( $_POST['governorate_key'] ) ) : '';

		if ( ! KDM_Helper::is_valid_governorate( $gov_key ) ) {
			wp_send_json_error( array( 'message' => 'المحافظة غير صالحة.' ) );
		}

		$governorates = KDM_Helper::get_governorates();
		$areas        = KDM_Database::get_areas_by_governorate( $gov_key );

		// Filter to enabled only — disabled areas should not be selectable at checkout
		$areas = array_values(
			array_filter( $areas, function ( $a ) {
				return (int) $a['is_enabled'] === 1;
			} )
		);

		$gov_names = $governorates[ $gov_key ] ?? array( 'ar' => $gov_key, 'en' => $gov_key );

		wp_send_json_success(
			array(
				'governorate_key'     => $gov_key,
				'governorate_name'    => $gov_names['ar'], // legacy key
				'governorate_name_ar' => $gov_names['ar'],
				'governorate_name_en' => $gov_names['en'],
				'areas'               => $areas,
			)
		);
	}

	/**
	 * Saves the customer's governorate / area / delivery-type selection to
	 * WC session so the fee can be calculated server-side on the next
	 * checkout totals refresh.
	 *
	 * POST params: area_id, governorate_key, delivery_type, nonce
	 */
	public function ajax_set_session() {
		if ( ! check_ajax_referer( 'kdm_checkout_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'فشل التحقق الأمني.' ), 403 );
		}

		$area_id       = isset( $_POST['area_id'] )       ? absint( $_POST['area_id'] )                                        : 0;
		$gov_key       = isset( $_POST['governorate_key'] ) ? sanitize_key( wp_unslash( $_POST['governorate_key'] ) )           : '';
		$delivery_type = isset( $_POST['delivery_type'] ) ? sanitize_key( wp_unslash( $_POST['delivery_type'] ) )               : 'normal';

		// Validate delivery type value
		if ( ! in_array( $delivery_type, array( 'normal', 'express' ), true ) ) {
			$delivery_type = 'normal';
		}

		if ( WC()->session ) {
			WC()->session->set( self::SESSION_GOV_KEY,       $gov_key );
			WC()->session->set( self::SESSION_AREA_ID,       $area_id );
			WC()->session->set( self::SESSION_DELIVERY_TYPE, $delivery_type );
		}

		// Return the fee preview so JS can show it before the full checkout refresh
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
	 * Adds the delivery fee to the WooCommerce cart.
	 * Fired by woocommerce_cart_calculate_fees on every totals recalculation.
	 *
	 * @param WC_Cart $cart Current cart instance.
	 */
	public function apply_delivery_fee( $cart ) {
		// Prevent running in admin contexts outside of AJAX totals refresh
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

	/**
	 * Calculates the fee amount and label for a given area and delivery type.
	 * Returns null if the area is not found or is disabled.
	 *
	 * @param int    $area_id       Area ID from session.
	 * @param string $delivery_type 'normal' or 'express'.
	 * @return array{label:string, amount:float}|null
	 */
	private function calculate_fee_data( $area_id, $delivery_type ) {
		if ( ! $area_id ) {
			return null;
		}

		$area = KDM_Database::get_area( $area_id );

		if ( ! $area || ! (int) $area['is_enabled'] ) {
			return null;
		}

		$normal_price  = (float) $area['delivery_price'];
		$express_extra = (float) $area['express_fee'];

		$area_name = KDM_I18n::area_name( $area );

		if ( 'express' === $delivery_type && $express_extra > 0 ) {
			return array(
				/* translators: %s: area name */
				'label'  => sprintf( __( 'رسوم التوصيل السريع — %s', 'kuwait-delivery-manager' ), $area_name ),
				'amount' => round( $normal_price + $express_extra, 3 ),
			);
		}

		return array(
			/* translators: %s: area name */
			'label'  => sprintf( __( 'رسوم التوصيل — %s', 'kuwait-delivery-manager' ), $area_name ),
			'amount' => $normal_price,
		);
	}

	// ---------------------------------------------------------------------------
	// 5. Validation
	// ---------------------------------------------------------------------------

	/**
	 * Validates the custom fields before the order is placed.
	 * Errors are shown to the customer via wc_add_notice.
	 */
	public function validate_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$area_id       = isset( $_POST['billing_area_id_kdm'] )         ? absint( $_POST['billing_area_id_kdm'] )                              : 0;
		$delivery_type = isset( $_POST['billing_delivery_type_kdm'] )   ? sanitize_key( wp_unslash( $_POST['billing_delivery_type_kdm'] ) )    : 'normal';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Area is required when billing country is Kuwait
		if ( ! $area_id ) {
			wc_add_notice(
				'<strong>' . esc_html__( 'منطقة التوصيل', 'kuwait-delivery-manager' ) . '</strong> ' .
				esc_html__( 'حقل مطلوب عند اختيار الكويت دولةً للشحن.', 'kuwait-delivery-manager' ),
				'error'
			);
			return;
		}

		$area = KDM_Database::get_area( $area_id );

		if ( ! $area ) {
			wc_add_notice( esc_html__( 'المنطقة المختارة غير موجودة. يرجى تحديث الصفحة والمحاولة مجدداً.', 'kuwait-delivery-manager' ), 'error' );
			return;
		}

		if ( ! (int) $area['is_enabled'] ) {
			wc_add_notice(
				sprintf(
					/* translators: %s: area name */
					esc_html__( 'منطقة %s غير متاحة للتوصيل حالياً.', 'kuwait-delivery-manager' ),
					'<strong>' . esc_html( $area['area_name_ar'] ) . '</strong>'
				),
				'error'
			);
			return;
		}

		// Minimum order check
		$min_order = (float) $area['minimum_order'];
		if ( $min_order > 0 && WC()->cart ) {
			$cart_subtotal = (float) WC()->cart->get_subtotal();
			if ( $cart_subtotal < $min_order ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: area name, 2: min price, 3: cart total */
						esc_html__( 'الحد الأدنى للطلب في منطقة %1$s هو %2$s د.ك. قيمة سلتك الحالية: %3$s د.ك.', 'kuwait-delivery-manager' ),
						'<strong>' . esc_html( $area['area_name_ar'] ) . '</strong>',
						KDM_Helper::format_price( $min_order ),
						KDM_Helper::format_price( $cart_subtotal )
					),
					'error'
				);
			}
		}

		// Express fallback — if express selected but unavailable, silently downgrade
		if ( 'express' === $delivery_type && (float) $area['express_fee'] <= 0 ) {
			$_POST['billing_delivery_type_kdm'] = 'normal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	// ---------------------------------------------------------------------------
	// 6. Save order meta
	// ---------------------------------------------------------------------------

	/**
	 * Persists the delivery selection to order meta after the order is created.
	 *
	 * @param int $order_id Newly created order ID.
	 */
	public function save_order_meta( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$area_id       = isset( $_POST['billing_area_id_kdm'] )        ? absint( $_POST['billing_area_id_kdm'] )                              : 0;
		$delivery_type = isset( $_POST['billing_delivery_type_kdm'] )  ? sanitize_key( wp_unslash( $_POST['billing_delivery_type_kdm'] ) )    : 'normal';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $area_id ) {
			$area = KDM_Database::get_area( $area_id );
			if ( $area ) {
				// Derive gov from the area row — no separate POST field needed
				$gov_key      = $area['governorate_key'];
				$governorates = KDM_Helper::get_governorates();
				$gov_names    = $governorates[ $gov_key ] ?? array( 'ar' => $gov_key, 'en' => $gov_key );
				$gov_name     = $gov_names['ar'];

				$fee_data = $this->calculate_fee_data( $area_id, $delivery_type );

				update_post_meta( $order_id, self::META_GOV_KEY,        $gov_key );
				update_post_meta( $order_id, self::META_GOV_NAME,       $gov_name );
				update_post_meta( $order_id, self::META_AREA_ID,        $area_id );
				update_post_meta( $order_id, self::META_AREA_NAME,      $area['area_name_ar'] );
				update_post_meta( $order_id, self::META_DELIVERY_TYPE,  $delivery_type );
				update_post_meta( $order_id, self::META_DELIVERY_PRICE, $fee_data ? $fee_data['amount'] : 0 );
			}
		}

		// Clear session once order is placed
		$this->clear_session();
	}

	// ---------------------------------------------------------------------------
	// 7. Display in order views / emails / admin
	// ---------------------------------------------------------------------------

	/**
	 * Shows delivery details on the Thank-You page and My-Account order view.
	 *
	 * @param WC_Order $order  Current order.
	 */
	public function display_order_delivery( $order ) {
		$gov_name  = get_post_meta( $order->get_id(), self::META_GOV_NAME, true );
		$area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
		$type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
		$price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

		if ( ! $gov_name && ! $area_name ) {
			return;
		}

		$type_label = ( 'express' === $type ) ? 'توصيل سريع' : 'توصيل عادي';
		?>
		<section class="kdm-order-delivery woocommerce-order-details" dir="rtl" style="margin-bottom:24px;">
			<h2 class="woocommerce-order-details__title" style="font-size:18px;margin-bottom:12px;">تفاصيل التوصيل</h2>
			<table class="woocommerce-table woocommerce-table--order-details shop_table" style="width:100%;">
				<tbody>
					<?php if ( $gov_name ) : ?>
					<tr>
						<th scope="row" style="text-align:right;padding:8px 12px;">المحافظة</th>
						<td style="padding:8px 12px;"><?php echo esc_html( $gov_name ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $area_name ) : ?>
					<tr>
						<th scope="row" style="text-align:right;padding:8px 12px;">المنطقة</th>
						<td style="padding:8px 12px;"><?php echo esc_html( $area_name ); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row" style="text-align:right;padding:8px 12px;">نوع التوصيل</th>
						<td style="padding:8px 12px;"><?php echo esc_html( $type_label ); ?></td>
					</tr>
					<?php if ( $price ) : ?>
					<tr>
						<th scope="row" style="text-align:right;padding:8px 12px;">رسوم التوصيل</th>
						<td style="padding:8px 12px;font-weight:600;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?> د.ك</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Shows delivery details in WooCommerce order notification emails.
	 *
	 * @param WC_Order $order         Current order.
	 * @param bool     $sent_to_admin Whether this is an admin email.
	 */
	public function display_email_delivery( $order, $sent_to_admin ) {
		$gov_name  = get_post_meta( $order->get_id(), self::META_GOV_NAME, true );
		$area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
		$type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
		$price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

		if ( ! $gov_name && ! $area_name ) {
			return;
		}

		$type_label = ( 'express' === $type ) ? 'توصيل سريع' : 'توصيل عادي';
		?>
		<div dir="rtl" style="margin-bottom:24px;font-family:Arial,sans-serif;">
			<h2 style="font-size:18px;color:#333;border-bottom:2px solid #e5e5e5;padding-bottom:8px;">تفاصيل التوصيل</h2>
			<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
				<?php if ( $gov_name ) : ?>
				<tr>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;width:40%;text-align:right;">المحافظة</td>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $gov_name ); ?></td>
				</tr>
				<?php endif; ?>
				<?php if ( $area_name ) : ?>
				<tr>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;text-align:right;">المنطقة</td>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $area_name ); ?></td>
				</tr>
				<?php endif; ?>
				<tr>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;font-weight:bold;text-align:right;">نوع التوصيل</td>
					<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $type_label ); ?></td>
				</tr>
				<?php if ( $price ) : ?>
				<tr>
					<td style="padding:8px 12px;font-weight:bold;text-align:right;">رسوم التوصيل</td>
					<td style="padding:8px 12px;font-weight:600;color:#2271b1;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?> د.ك</td>
				</tr>
				<?php endif; ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Shows delivery details in the WordPress admin order edit screen.
	 *
	 * @param WC_Order $order  Current order object.
	 */
	public function display_admin_delivery( $order ) {
		$gov_name  = get_post_meta( $order->get_id(), self::META_GOV_NAME, true );
		$area_name = get_post_meta( $order->get_id(), self::META_AREA_NAME, true );
		$type      = get_post_meta( $order->get_id(), self::META_DELIVERY_TYPE, true );
		$price     = get_post_meta( $order->get_id(), self::META_DELIVERY_PRICE, true );

		if ( ! $gov_name && ! $area_name ) {
			return;
		}

		$type_label = ( 'express' === $type ) ? '⚡ توصيل سريع' : 'توصيل عادي';
		?>
		<div class="kdm-admin-delivery" style="margin-top:12px;padding:12px 14px;background:#f9fafb;border:1px solid #dcdcde;border-radius:5px;direction:rtl;text-align:right;" dir="rtl">
			<strong style="display:block;margin-bottom:8px;font-size:13px;color:#1d2327;">📦 منطقة التوصيل</strong>
			<?php if ( $gov_name ) : ?>
				<p style="margin:4px 0;font-size:13px;">
					<span style="color:#646970;">المحافظة:</span> <strong><?php echo esc_html( $gov_name ); ?></strong>
				</p>
			<?php endif; ?>
			<?php if ( $area_name ) : ?>
				<p style="margin:4px 0;font-size:13px;">
					<span style="color:#646970;">المنطقة:</span> <strong><?php echo esc_html( $area_name ); ?></strong>
				</p>
			<?php endif; ?>
			<p style="margin:4px 0;font-size:13px;">
				<span style="color:#646970;">نوع التوصيل:</span> <strong><?php echo esc_html( $type_label ); ?></strong>
			</p>
			<?php if ( $price ) : ?>
				<p style="margin:4px 0;font-size:13px;">
					<span style="color:#646970;">الرسوم المطبّقة:</span>
					<strong style="color:#2271b1;"><?php echo esc_html( KDM_Helper::format_price( $price ) ); ?> د.ك</strong>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ---------------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------------

	/**
	 * Clears all KDM-related WC session values.
	 * Called after order placement and on cart empty.
	 */
	public function clear_session() {
		if ( WC()->session ) {
			WC()->session->__unset( self::SESSION_GOV_KEY );
			WC()->session->__unset( self::SESSION_AREA_ID );
			WC()->session->__unset( self::SESSION_DELIVERY_TYPE );
		}
	}
}
