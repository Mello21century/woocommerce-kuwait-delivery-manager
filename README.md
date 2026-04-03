## === Kuwait Delivery Manager ===

- Contributors: ***Ahmed Safaa***
- Tags: woocommerce, delivery, kuwait, shipping, zones
- Requires at least: 5.8
- Tested up to: 6.6
- Requires PHP: 7.4
- Stable tag: 1.2.0
- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Kuwait delivery areas and pricing from the WooCommerce admin dashboard with an Arabic RTL interface.

### == Description ==

Kuwait Delivery Manager provides a professional admin interface for managing delivery zones across all six Kuwait
governorates:
العاصمة · حولي · الفروانية · الأحمدي · الجهراء · مبارك الكبير

#### **Features:**

* Arabic RTL admin interface
* Sidebar governorate selector — switch areas without page reload (AJAX)
* Inline row editing — click Edit, change values, click Save
* Drag-and-drop sort order within each governorate
* Per-area status toggle (enabled / disabled) with immediate AJAX save
* Add new areas without leaving the page
* Delete areas with confirmation
* Full data persistence in a custom database table
* Seeded with real Kuwait area names and typical KWD pricing
* WooCommerce checkout integration architecture ready

#### **Table columns per area:**

* المنطقة (Area name)
* سعر التوصيل (Normal delivery price — KWD)
* رسوم التوصيل السريع (Express delivery fee — KWD)
* ملاحظات التوصيل (Delivery notes)
* أقل قيمة للطلب (Minimum order amount — KWD)
* الحالة (Enable / disable toggle)

#### == Installation ==

1. Download the plugin zip file.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. After installation, click **Activate Plugin**.
5. The plugin will automatically:
    - Create the `wp_kdm_delivery_areas` database table
    - Seed all 6 Kuwait governorates with default areas and prices
6. If WooCommerce is active, find the manager under **WooCommerce → مناطق الكويت**.
   Otherwise, a top-level **مناطق الكويت** menu item is added.

#### == Usage ==

1. Navigate to the delivery manager admin page.
2. Click a governorate name in the right sidebar.
3. The left panel loads all areas for that governorate.
4. **Edit a row:** Click the تعديل button, modify any field, click حفظ.
5. **Toggle status:** Click the ON/OFF switch — saves immediately.
6. **Add an area:** Click إضافة منطقة, fill in the new row, click إضافة.
7. **Delete an area:** Click the trash icon and confirm.
8. **Reorder:** Drag rows by the ≡ handle on the left side of each row.

#### == WooCommerce Checkout Integration (Future) ==

The database schema already stores all pricing data needed for checkout
integration. To wire it up in a future release or custom extension:

1. Create `includes/class-checkout.php`
2. On the checkout page, output a **Governorate** select populated from
   `KDM_Helper::get_governorates()`.
3. On governorate change (JS), fetch areas via `kdm_get_areas` (same AJAX
   action already registered) and populate an **Area** select.
4. On area selection, read `delivery_price` from the response and apply it
   via WooCommerce's `woocommerce_package_rates` filter or a custom
   shipping method extending `WC_Shipping_Method`.
5. Store the selected governorate/area in `WC()->session` and apply the
   fee at `woocommerce_cart_calculate_fees`.

#### Key WooCommerce hooks to use:

- `woocommerce_checkout_fields` — add the two custom selects
- `woocommerce_checkout_process` — validate selection
- `woocommerce_checkout_update_order_meta` — persist to order meta
- `woocommerce_package_rates` — filter/replace shipping rates

#### == Frequently Asked Questions ==

##### = Does the plugin work without WooCommerce? =

Yes. The admin data-management interface works standalone. An admin notice
warns you that checkout integration requires WooCommerce.

##### = Is the data safe when I deactivate the plugin? =

Yes. Deactivation preserves all data. Only **deleting** the plugin (via the
Plugins screen) will drop the table via uninstall.php.

##### = Can I add more governorates? =

Yes. Add a new entry to `KDM_Helper::get_governorates()` and re-seed, or
add areas manually via the admin interface.

##### = What currency is used? =

KWD (Kuwaiti Dinar). Prices are stored and displayed with 3 decimal places
per the KWD standard.

##### == Changelog ==

= 1.2.0 =

* NEW: Full bilingual (AR/EN) support — admin UI and checkout adapt to active language.
* NEW: WPML and Polylang integration — area names, notes, and governorate names are translatable via both plugins.
* NEW: Dual-language fields for every area: Arabic name, English name, Arabic notes, English notes.
* NEW: English columns added to database schema (area_name_en, governorate_name_en, delivery_notes_en).
* NEW: All admin UI strings wrapped in __() / _e() for full .po/.mo localisation.
* NEW: KDM_I18n class — language detection, string registration, context-aware name helpers.
* NEW: Inline editing now covers all fields: prices, notes (both languages), area names (both languages).
* NEW: "Copy to all" feature — apply one row's value to every area in the governorate.
* NEW: Column header clipboard button — copy first row value to all rows in one click.
* NEW: Per-row push button in edit mode — copy that row's value to all rows.
* NEW: Governorate sidebar shows bilingual names (Arabic + English subtitle).
* IMPROVED: Admin.js rewritten for bilingual cells, copy-to-all, textarea notes with auto-resize.
* IMPROVED: Checkout area/governorate selects use language-aware labels (KDM_I18n).

= 1.1.0 =

* NEW: KDM_Checkout class — full WooCommerce checkout integration.
* NEW: Governorate + Area selects injected into billing section (RTL, required).
* NEW: Delivery type select (توصيل عادي / توصيل سريع) — hidden when no express surcharge.
* NEW: Live price preview panel on checkout page updates instantly on selection.
* NEW: Session-based fee calculation via woocommerce_cart_calculate_fees.
* NEW: AJAX actions kdm_get_areas_public and kdm_set_area_session (guest-safe, nopriv).
* NEW: Minimum order validation at checkout — shows clear Arabic error notice.
* NEW: Delivery details saved as order meta (governorate, area, type, price).
* NEW: Delivery info block shown on Thank-You page, My Account, all emails, admin order screen.
* NEW: checkout.js + checkout.css assets, enqueued only on checkout page.
* NEW: WooCommerce HPOS (High-Performance Order Storage) compatibility declared.
* IMPROVED: class-plugin.php now conditionally boots KDM_Checkout when WC is active.

= 1.0.0 =

* Initial release.
* Full admin UI with RTL Arabic layout.
* All 6 Kuwait governorates seeded with default areas and prices.
* AJAX-powered governorate switching, inline editing, add/delete, toggle, sort.
