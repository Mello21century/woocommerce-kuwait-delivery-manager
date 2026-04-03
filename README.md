## === Kuwait Delivery Manager ===

- Contributors: ***Ahmed Safaa***
- Tags: woocommerce, delivery, shipping, zones, multi-country
- Requires at least: 5.8
- Tested up to: 6.6
- Requires PHP: 7.4
- Stable tag: 1.3.0
- License: GPLv2 or later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-country delivery area and pricing manager for WooCommerce — manage cities, areas, and delivery fees from a dedicated admin panel.

### == Description ==

Kuwait Delivery Manager provides a professional admin interface for managing delivery zones across multiple countries. Define cities, areas, delivery prices, express fees, and minimum order thresholds — all from a dedicated top-level admin menu.

#### **Features:**

* Multi-country support — manage delivery for any country via WooCommerce's country list
* Two-table database schema: cities and areas with full JSON bilingual (EN/AR) names
* Own top-level admin menu with 4 subpages: Areas, Cities, Import, Settings
* AJAX-driven city sidebar — switch areas without page reload
* Inline row editing — click Edit, change values, click Save
* Drag-and-drop sort order within each city
* Per-area and per-city status toggle with immediate AJAX save
* CSV import wizard — bulk-import cities or areas with column mapping
* Free delivery threshold — set minimum order amount per area (cart >= threshold → free delivery)
* Express delivery fee support — optional per-area surcharge
* Bilingual fields — Arabic and English names and delivery notes stored as JSON
* WooCommerce checkout integration with searchable combo dropdown
* Live price preview panel on checkout page
* WPML and Polylang compatible
* WooCommerce HPOS compatible
* All UI strings in English with full i18n support via `__()`

#### **Area fields:**

* Area Name (EN / AR)
* Delivery Price
* Express Fee
* Delivery Notes (EN / AR)
* Minimum Order (free delivery threshold)
* Status (active / inactive)

#### == Installation ==

1. Download the plugin zip file.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip file and click **Install Now**.
4. After installation, click **Activate Plugin**.
5. The plugin will automatically:
    - Create the `kdm_delivery_cities` and `kdm_delivery_areas` database tables
    - Seed 6 Kuwait cities with default areas and prices
6. Find the manager under the **Delivery Manager** top-level menu.

#### == Usage ==

1. Navigate to **Delivery Manager → Areas**.
2. Select a country from the dropdown at the top.
3. Click a city name in the sidebar to load its areas.
4. **Edit a row:** Click the Edit button, modify any field, click Save.
5. **Toggle status:** Click the ON/OFF switch — saves immediately.
6. **Add an area:** Click Add New Area, fill in the new row, click Add.
7. **Delete an area:** Click the trash icon and confirm.
8. **Reorder:** Drag rows by the handle on the left side of each row.
9. **Copy to all:** Copy delivery notes from one area to all others in the same city.

#### Managing Cities

1. Navigate to **Delivery Manager → Cities**.
2. Select a country and manage cities: add, edit bilingual names, toggle, reorder, or delete.
3. Deleting a city removes all its areas (cascade).

#### CSV Import

1. Navigate to **Delivery Manager → Import**.
2. Select country, import type (Cities or Areas), and upload a CSV file.
3. Map CSV columns to database fields.
4. For area imports, choose city matching mode: by ID, by English name, or by Arabic name.

#### == Frequently Asked Questions ==

##### = Does the plugin work without WooCommerce? =

Yes. The admin data-management interface works standalone. An admin notice
warns you that checkout integration requires WooCommerce.

##### = Is the data safe when I deactivate the plugin? =

Yes. Deactivation preserves all data. Only **deleting** the plugin (via the
Plugins screen) will drop the tables via uninstall.php.

##### = Can I add more countries? =

Yes. Any country from WooCommerce's country list can be used. Add cities for
a country and areas will appear in checkout for customers from that country.

##### = What about minimum order amounts? =

The minimum_order field acts as a free delivery threshold. If a customer's cart
total meets or exceeds the threshold, delivery fees become zero. Set to 0 to
always charge delivery fees.

##### == Changelog ==

= 1.3.0 =

* BREAKING: Complete database schema rewrite — two tables (`kdm_delivery_cities` + `kdm_delivery_areas`) replace the single `kdm_delivery_areas` table. JSON `longtext` columns for bilingual names and notes (MySQL 5.6 compatible).
* NEW: Multi-country support — manage delivery for any country, not just Kuwait. Country dropdown powered by WooCommerce's country list.
* NEW: Own top-level admin menu group (Delivery Manager) with 4 subpages: Areas, Cities, Import, Settings.
* NEW: Cities management page — full CRUD with bilingual names, toggle, drag-sort.
* NEW: CSV import wizard — 2-step process (upload → column mapping) for bulk-importing cities or areas. Supports 3 city matching modes (by ID, by English name, by Arabic name).
* NEW: Free delivery threshold — `minimum_order` field per area. Cart total >= threshold = free delivery; set to 0 to always charge.
* NEW: Dynamic checkout — combo dropdown adapts to billing country. Countries without cities in DB show no delivery fields.
* NEW: Manual cascade delete — deleting a city removes all child areas.
* NEW: CSV file security — randomized filenames, `.htaccess` deny-all, transient-based path storage.
* IMPROVED: All hardcoded Arabic strings replaced with English wrapped in `__()` for full i18n.
* IMPROVED: Removed all hardcoded `direction: rtl` from CSS — theme/browser handles text direction.
* IMPROVED: Checkout session keys updated (`kdm_city_id` replaces `kdm_gov_key`).
* IMPROVED: Currency symbol from `get_woocommerce_currency_symbol()` instead of hardcoded.
* REMOVED: Governorate system — replaced entirely by database-driven cities.

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
