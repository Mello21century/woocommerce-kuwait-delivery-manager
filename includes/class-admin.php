<?php
/**
 * KDM_Admin
 *
 * Registers the WordPress admin menu page, enqueues assets,
 * and renders the admin interface template.
 *
 * @package KuwaitDeliveryManager
 */

defined( 'ABSPATH' ) || exit;

class KDM_Admin {

	/**
	 * Hooks into WordPress admin actions.
	 */
	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
	}

	/**
	 * Registers the admin menu page.
	 * Appears under WooCommerce if active, otherwise as a top-level menu.
	 */
	public function register_menu() {
		$capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
		$parent     = class_exists( 'WooCommerce' ) ? 'woocommerce'        : 'kdm-delivery-areas';

		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				__( 'مناطق التوصيل - الكويت', 'kuwait-delivery-manager' ),
				__( 'مناطق الكويت', 'kuwait-delivery-manager' ),
				$capability,
				'kdm-delivery-areas',
				array( $this, 'render_page' )
			);
		} else {
			add_menu_page(
				__( 'مناطق التوصيل - الكويت', 'kuwait-delivery-manager' ),
				__( 'مناطق الكويت', 'kuwait-delivery-manager' ),
				$capability,
				'kdm-delivery-areas',
				array( $this, 'render_page' ),
				'dashicons-location-alt',
				56
			);
		}

		// Settings page — appears under WooCommerce menu or as submenu of standalone menu
		add_submenu_page(
			$parent,
			__( 'إعدادات مناطق التوصيل', 'kuwait-delivery-manager' ),
			__( 'إعدادات التوصيل', 'kuwait-delivery-manager' ),
			$capability,
			'kdm-delivery-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers the plugin's options with the WordPress Settings API.
	 * Hooked on admin_init.
	 */
	public function register_settings() {
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
			__( 'إعدادات التوصيل', 'kuwait-delivery-manager' ),
			null,
			'kdm-delivery-settings'
		);

		add_settings_field(
			'kdm_express_enabled',
			__( 'التوصيل السريع', 'kuwait-delivery-manager' ),
			array( $this, 'render_express_field' ),
			'kdm-delivery-settings',
			'kdm_delivery_section'
		);
	}

	/** Renders the express delivery checkbox field. */
	public function render_express_field() {
		$enabled = get_option( 'kdm_express_enabled', 1 );
		?>
		<label>
			<input type="checkbox"
				   name="kdm_express_enabled"
				   value="1"
				   <?php checked( 1, (int) $enabled ); ?>>
			<?php esc_html_e( 'تفعيل خيار التوصيل السريع', 'kuwait-delivery-manager' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'عند التعطيل: يتم إخفاء عمود رسوم التوصيل السريع من لوحة الإدارة وإزالة حقل نوع التوصيل من صفحة الدفع.', 'kuwait-delivery-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page() {
		$capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'ليس لديك صلاحية الوصول إلى هذه الصفحة.', 'kuwait-delivery-manager' ), 403 );
		}
		?>
		<div class="wrap" dir="rtl">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-admin-settings" style="font-size:28px;width:28px;height:28px;vertical-align:middle;color:#2271b1;"></span>
				<?php esc_html_e( 'إعدادات Kuwait Delivery Manager', 'kuwait-delivery-manager' ); ?>
			</h1>
			<hr class="wp-header-end">
			<form method="post" action="options.php">
				<?php
				settings_fields( 'kdm_settings_group' );
				do_settings_sections( 'kdm-delivery-settings' );
				submit_button( __( 'حفظ الإعدادات', 'kuwait-delivery-manager' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueues CSS and JavaScript assets.
	 * Assets are only loaded on the plugin's own admin page to avoid conflicts.
	 *
	 * @param string $hook  Current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook ) {
		// Only enqueue on our own page
		if ( false === strpos( $hook, 'kdm-delivery-areas' ) ) {
			return;
		}

		// jQuery UI Sortable is bundled with WordPress — no CDN needed
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Plugin stylesheet
		wp_enqueue_style(
			'kdm-admin-style',
			KDM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			KDM_VERSION
		);

		// Plugin script
		wp_enqueue_script(
			'kdm-admin-script',
			KDM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			KDM_VERSION,
			true  // Load in footer
		);

		// Pass PHP data to JavaScript
		wp_localize_script(
			'kdm-admin-script',
			'kdmData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'kdm_nonce' ),
				'governorates'   => KDM_Helper::get_governorates(),
				'expressEnabled' => (bool) get_option( 'kdm_express_enabled', 1 ),

				// All strings pass through __() so they are translatable via .po files,
				// WPML String Translation, and Polylang Strings Translations.
				'strings' => array(
					// Status / feedback
					'loading'         => __( 'جاري التحميل…',                                                              'kuwait-delivery-manager' ),
					'error'           => __( 'حدث خطأ، يرجى المحاولة مجدداً',                                             'kuwait-delivery-manager' ),
					'unsavedChanges'  => __( 'لديك تغييرات غير محفوظة. هل تريد مواصلة التنقل؟',                          'kuwait-delivery-manager' ),
					'noAreas'         => __( 'لا توجد مناطق لهذه المحافظة. اضغط «إضافة منطقة» لإضافة أول منطقة.',        'kuwait-delivery-manager' ),
					'orderSaved'      => __( 'تم حفظ الترتيب',                                                             'kuwait-delivery-manager' ),
					'close'           => __( 'إغلاق',                                                                      'kuwait-delivery-manager' ),
					// Row actions
					'editArea'        => __( 'تعديل',     'kuwait-delivery-manager' ),
					'saveArea'        => __( 'حفظ',       'kuwait-delivery-manager' ),
					'cancelEdit'      => __( 'إلغاء',     'kuwait-delivery-manager' ),
					'deleteArea'      => __( 'حذف',       'kuwait-delivery-manager' ),
					'addNewArea'      => __( 'إضافة منطقة', 'kuwait-delivery-manager' ),
					'addBtn'          => __( 'إضافة',     'kuwait-delivery-manager' ),
					// In-progress labels
					'saving'          => __( 'جاري الحفظ…',     'kuwait-delivery-manager' ),
					'adding'          => __( 'جاري الإضافة…',   'kuwait-delivery-manager' ),
					// Confirm dialogs
					'confirmDelete'   => __( 'هل أنت متأكد من حذف هذه المنطقة؟ لا يمكن التراجع.',                        'kuwait-delivery-manager' ),
					// Copy-to-all — use {value}, {field}, {count} as placeholders
					'copyFirstToAll'  => __( 'نسخ قيمة الصف الأول إلى جميع الصفوف',                                     'kuwait-delivery-manager' ),
					'pushToAll'       => __( 'تطبيق هذه القيمة على جميع المناطق',                                        'kuwait-delivery-manager' ),
					'copyConfirm'     => __( 'نسخ {field} = {value} إلى جميع {count} منطقة؟',                           'kuwait-delivery-manager' ),
					'pushConfirm'     => __( 'تطبيق {field} = {value} على جميع {count} منطقة؟',                         'kuwait-delivery-manager' ),
					// Field labels used inside copy confirm messages
					'field_delivery_price'    => __( 'سعر التوصيل',              'kuwait-delivery-manager' ),
					'field_express_fee'       => __( 'رسوم التوصيل السريع',      'kuwait-delivery-manager' ),
					'field_minimum_order'     => __( 'أقل قيمة للطلب',           'kuwait-delivery-manager' ),
					'field_delivery_notes'    => __( 'ملاحظات التوصيل (عربي)',   'kuwait-delivery-manager' ),
					'field_delivery_notes_en' => __( 'Delivery Notes (English)', 'kuwait-delivery-manager' ),
					// Table column headers
					'colArea'         => __( 'المنطقة',                  'kuwait-delivery-manager' ),
					'colPrice'        => __( 'سعر التوصيل',              'kuwait-delivery-manager' ),
					'colExpress'      => __( 'التوصيل السريع',           'kuwait-delivery-manager' ),
					'colNotes'        => __( 'ملاحظات التوصيل',          'kuwait-delivery-manager' ),
					'colMinOrder'     => __( 'أقل قيمة',                 'kuwait-delivery-manager' ),
					'colStatus'       => __( 'الحالة',                   'kuwait-delivery-manager' ),
					'colActions'      => __( 'إجراءات',                  'kuwait-delivery-manager' ),
					// Toggle labels
					'toggleOn'        => __( 'مفعّل',  'kuwait-delivery-manager' ),
					'toggleOff'       => __( 'موقوف',  'kuwait-delivery-manager' ),
					// Validation
					'nameRequired'    => __( 'اسم المنطقة (عربي) حقل مطلوب.', 'kuwait-delivery-manager' ),
					// Drag sort
					'dragSort'        => __( 'اسحب لإعادة الترتيب', 'kuwait-delivery-manager' ),
					// New row auto-status
					'autoEnabled'     => __( 'مفعّل تلقائياً', 'kuwait-delivery-manager' ),
					// Input placeholders
					'placeholderNameAr'  => __( 'اسم المنطقة بالعربي',  'kuwait-delivery-manager' ),
					'placeholderNameEn'  => __( 'Area Name in English',  'kuwait-delivery-manager' ),
					'placeholderNotesAr' => __( 'ملاحظات بالعربي',      'kuwait-delivery-manager' ),
					'placeholderNotesEn' => __( 'Notes in English',      'kuwait-delivery-manager' ),
					// Currency
					'kwd'             => __( 'د.ك', 'kuwait-delivery-manager' ),
				),
			)
		);
	}

	/**
	 * Renders the admin page by including the template file.
	 */
	public function render_page() {
		if ( ! KDM_Helper::current_user_can_manage() ) {
			wp_die(
				esc_html__( 'ليس لديك صلاحية الوصول إلى هذه الصفحة.', 'kuwait-delivery-manager' ),
				403
			);
		}

		include KDM_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
