<?php
/**
 * KDM_Database
 *
 * All database interactions for the plugin.
 * Schema version: 1.1  (adds area_name_en, governorate_name_en, delivery_notes_en)
 *
 * @package KuwaitDeliveryManager
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Database {

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Returns the fully-qualified table name including the WP prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'kdm_delivery_areas';
	}

	/**
	 * Creates or upgrades the delivery areas table using dbDelta.
	 * Safe to call multiple times — dbDelta only applies missing changes.
	 *
	 * Schema changes since v1.0:
	 *   v1.1: added area_name_en, governorate_name_en, delivery_notes_en
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::get_table_name();
		$collate = $wpdb->get_charset_collate();

		// NOTE: dbDelta requires exactly two spaces before PRIMARY KEY.
		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  governorate_key varchar(50) NOT NULL DEFAULT '',
  governorate_name_ar varchar(100) NOT NULL DEFAULT '',
  governorate_name_en varchar(100) NOT NULL DEFAULT '',
  area_name_ar varchar(150) NOT NULL DEFAULT '',
  area_name_en varchar(150) NOT NULL DEFAULT '',
  delivery_price decimal(10,3) NOT NULL DEFAULT 0.000,
  express_fee decimal(10,3) NOT NULL DEFAULT 0.000,
  delivery_notes text DEFAULT NULL,
  delivery_notes_en text DEFAULT NULL,
  minimum_order decimal(10,3) NOT NULL DEFAULT 0.000,
  is_enabled tinyint(1) NOT NULL DEFAULT 1,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY governorate_key (governorate_key),
  KEY is_enabled (is_enabled),
  KEY sort_order (sort_order)
) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Inserts the default Kuwait governorates and areas (bilingual).
	 * Skips silently if rows already exist — idempotent.
	 */
	public static function seed_default_data() {
		global $wpdb;

		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0 ) {
			return;
		}

		// ----------------------------------------------------------------
		// Seed structure: [ area_ar, area_en, price, express, min_order ]
		// ----------------------------------------------------------------
		$data = array(
			'capital' => array(
				'ar' => 'العاصمة', 'en' => 'Kuwait Capital',
				'areas' => array(
					array( 'الكويت',      'Kuwait City',      1.500, 1.000, 0.000 ),
					array( 'الشويخ',      'Shuwaikh',         1.500, 1.000, 0.000 ),
					array( 'الداسمة',     'Dasma',            1.500, 1.000, 0.000 ),
					array( 'القادسية',    'Qadisiya',         1.500, 1.000, 0.000 ),
					array( 'الروضة',      'Rawda',            1.500, 1.000, 0.000 ),
					array( 'الصالحية',    'Salehiya',         1.500, 1.000, 0.000 ),
					array( 'السرة',       'Surra',            1.500, 1.000, 0.000 ),
					array( 'النزهة',      'Nuzha',            1.500, 1.000, 0.000 ),
					array( 'كيفان',       'Kaifan',           1.500, 1.000, 0.000 ),
					array( 'الشامية',     'Shamiya',          1.500, 1.000, 0.000 ),
					array( 'المرقاب',     'Mirqab',           1.500, 1.000, 0.000 ),
					array( 'قرطبة',       'Qortuba',          1.500, 1.000, 0.000 ),
					array( 'الفيحاء',     'Faiha',            1.500, 1.000, 0.000 ),
				),
			),
			'hawalli' => array(
				'ar' => 'حولي', 'en' => 'Hawalli',
				'areas' => array(
					array( 'حولي',           'Hawalli',         1.500, 1.000, 0.000 ),
					array( 'الرميثية',       'Rumaithiya',      1.500, 1.000, 0.000 ),
					array( 'سلوى',           'Salwa',           1.500, 1.000, 0.000 ),
					array( 'الشعب',          'Sha\'ab',         1.500, 1.000, 0.000 ),
					array( 'بيان',           'Bayan',           1.750, 1.000, 0.000 ),
					array( 'مشرف',           'Mishref',         1.500, 1.000, 0.000 ),
					array( 'الجابرية',       'Jabriya',         1.500, 1.000, 0.000 ),
					array( 'السالمية',       'Salmiya',         1.500, 1.000, 0.000 ),
					array( 'الرأي',          'Ar-Rai',          1.500, 1.000, 0.000 ),
					array( 'ميدان حولي',     'Hawalli Square',  1.500, 1.000, 0.000 ),
					array( 'بيان الجنوبية',  'South Bayan',     1.750, 1.250, 0.000 ),
				),
			),
			'farwaniya' => array(
				'ar' => 'الفروانية', 'en' => 'Al-Farwaniyah',
				'areas' => array(
					array( 'الفروانية',    'Farwaniya',         1.500, 1.000, 0.000 ),
					array( 'العارضية',     'Ardhiya',           1.500, 1.000, 0.000 ),
					array( 'الرابية',      'Rabiya',            1.500, 1.000, 0.000 ),
					array( 'خيطان',        'Khaitan',           1.500, 1.000, 0.000 ),
					array( 'الرغة',        'Regai',             1.500, 1.000, 0.000 ),
					array( 'أبو فطيرة',    'Abu Futaira',       1.750, 1.250, 0.000 ),
					array( 'العمرية',      'Omairiya',          1.500, 1.000, 0.000 ),
					array( 'الأندلس',      'Andalus',           1.500, 1.000, 0.000 ),
					array( 'جليب الشيوخ',  'Jleeb Al-Shuyoukh', 1.750, 1.250, 0.000 ),
					array( 'الضجيج',       'Dhajej',            2.000, 1.500, 0.000 ),
					array( 'العباسية',     'Abbasiya',          1.500, 1.000, 0.000 ),
				),
			),
			'ahmadi' => array(
				'ar' => 'الأحمدي', 'en' => 'Ahmadi',
				'areas' => array(
					array( 'الأحمدي',    'Ahmadi',        2.000, 1.500, 0.000 ),
					array( 'أبو حليفة', 'Abu Halifa',    2.000, 1.500, 0.000 ),
					array( 'الفنطاس',   'Fintaas',       2.000, 1.500, 0.000 ),
					array( 'المهبولة',  'Mahboula',      2.000, 1.500, 0.000 ),
					array( 'الصباحية',  'Sabahiya',      2.000, 1.500, 0.000 ),
					array( 'العقيلة',   'Aqila',         2.250, 1.500, 0.000 ),
					array( 'الرقعي',    'Ruqai',         2.250, 1.500, 0.000 ),
					array( 'الفحيحيل',  'Fahaheel',      2.000, 1.500, 0.000 ),
					array( 'المنقف',    'Mangaf',        2.000, 1.500, 0.000 ),
					array( 'الوفرة',    'Wafra',         3.000, 2.000, 0.000 ),
					array( 'الزور',     'Zour',          3.500, 2.500, 0.000 ),
					array( 'الخيران',   'Khairan',       4.000, 3.000, 5.000 ),
				),
			),
			'jahra' => array(
				'ar' => 'الجهراء', 'en' => 'Al-Jahra',
				'areas' => array(
					array( 'الجهراء',     'Jahra',          2.000, 1.500, 0.000 ),
					array( 'العيون',      'Uyun',           2.000, 1.500, 0.000 ),
					array( 'القصر',       'Qasr',           2.000, 1.500, 0.000 ),
					array( 'السلبية',     'Sulaibiya',      2.250, 1.500, 0.000 ),
					array( 'تيماء',       'Tayma',          2.500, 2.000, 0.000 ),
					array( 'كاظمة',       'Kadhima',        2.500, 2.000, 0.000 ),
					array( 'الواحة',      'Waha',           2.000, 1.500, 0.000 ),
					array( 'النعيم',      'Naeem',          2.000, 1.500, 0.000 ),
					array( 'القيروان',    'Qairawan',       2.000, 1.500, 0.000 ),
					array( 'الصليبيخات', 'Sulaibikhat',    2.500, 2.000, 0.000 ),
				),
			),
			'mubarak' => array(
				'ar' => 'مبارك الكبير', 'en' => 'Mubarak Al-Kabeer',
				'areas' => array(
					array( 'مبارك الكبير',  'Mubarak Al-Kabeer', 1.750, 1.250, 0.000 ),
					array( 'أبو الحصانية', 'Abu Hasaniya',       1.750, 1.250, 0.000 ),
					array( 'القصور',        'Qusor',             1.750, 1.250, 0.000 ),
					array( 'صبحان',         'Subhan',            1.750, 1.250, 0.000 ),
					array( 'الفنيطيس',      'Fintas',            1.750, 1.250, 0.000 ),
					array( 'المسيلة',       'Masaeel',           2.000, 1.500, 0.000 ),
					array( 'الصديق',        'Siddeeq',           1.750, 1.250, 0.000 ),
					array( 'البيتاء',       'Baitah',            1.750, 1.250, 0.000 ),
				),
			),
		);

		$sort = 0;
		$now  = current_time( 'mysql' );

		foreach ( $data as $gov_key => $gov ) {
			foreach ( $gov['areas'] as $area ) {
				$wpdb->insert(
					$table,
					array(
						'governorate_key'     => $gov_key,
						'governorate_name_ar' => $gov['ar'],
						'governorate_name_en' => $gov['en'],
						'area_name_ar'        => $area[0],
						'area_name_en'        => $area[1],
						'delivery_price'      => (float) $area[2],
						'express_fee'         => (float) $area[3],
						'delivery_notes'      => '',
						'delivery_notes_en'   => '',
						'minimum_order'       => (float) $area[4],
						'is_enabled'          => 1,
						'sort_order'          => $sort++,
						'created_at'          => $now,
						'updated_at'          => $now,
					),
					array( '%s','%s','%s','%s','%s','%f','%f','%s','%s','%f','%d','%d','%s','%s' )
				);
			}
		}
	}

	// ---------------------------------------------------------------------------
	// Read
	// ---------------------------------------------------------------------------

	/**
	 * Fetches all delivery areas for a governorate, ordered by sort_order ASC.
	 *
	 * @param string $gov_key
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_areas_by_governorate( $gov_key ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE governorate_key = %s ORDER BY sort_order ASC, id ASC",
				$gov_key
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches a single area row by ID.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null
	 */
	public static function get_area( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	/**
	 * Returns all enabled areas grouped by governorate key.
	 * Used by the checkout combo dropdown pre-load — one query instead of N per-gov calls.
	 *
	 * @return array<string, array>  [ gov_key => [ area_row, ... ], ... ]
	 */
	public static function get_all_areas_grouped() {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE is_enabled = 1 ORDER BY governorate_key, sort_order ASC, id ASC",
			ARRAY_A
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$grouped[ $row['governorate_key'] ][] = $row;
		}
		return $grouped;
	}

	// ---------------------------------------------------------------------------
	// Write
	// ---------------------------------------------------------------------------

	/**
	 * Updates an existing area row.
	 *
	 * @param int   $id
	 * @param array $data  Column => value pairs (already sanitised).
	 * @return bool
	 */
	public static function save_area( $id, array $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update(
			$table,
			$data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Inserts a new area row.
	 * Auto-assigns sort_order = MAX + 1 for the governorate.
	 *
	 * @param array $data
	 * @return int|false  New row ID or false.
	 */
	public static function add_area( array $data ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sort_order) FROM {$table} WHERE governorate_key = %s",
				$data['governorate_key']
			)
		);

		$data['sort_order'] = is_null( $max ) ? 0 : (int) $max + 1;
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		return $wpdb->insert( $table, $data ) ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Deletes a single area by ID.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_area( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Toggles is_enabled for a single area.
	 *
	 * @param int $id
	 * @return int|false  New status value (0 or 1) or false if not found.
	 */
	public static function toggle_area( $id ) {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_enabled FROM {$table} WHERE id = %d", $id )
		);

		if ( is_null( $current ) ) {
			return false;
		}

		$new = $current ? 0 : 1;

		return false !== $wpdb->update(
			$table,
			array( 'is_enabled' => $new, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		) ? $new : false;
	}

	/**
	 * Bulk-updates sort_order values after a drag-and-drop reorder.
	 *
	 * @param array $items  [ ['id' => int, 'sort_order' => int], ... ]
	 * @return bool
	 */
	public static function update_sort_order( array $items ) {
		global $wpdb;
		$table = self::get_table_name();

		foreach ( $items as $item ) {
			$wpdb->update(
				$table,
				array( 'sort_order' => absint( $item['sort_order'] ) ),
				array( 'id'         => absint( $item['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Copies a single numeric field's value to all areas in a governorate.
	 * Only the whitelisted price fields are allowed — column name cannot be
	 * parameterised in a prepared statement, so a strict whitelist is used.
	 *
	 * @param string $gov_key     Governorate slug.
	 * @param string $field_name  Column name — must be in the allowed list.
	 * @param float  $value       Value to set.
	 * @return int|false  Number of rows affected, or false on failure / bad field.
	 */
	public static function bulk_update_field( $gov_key, $field_name, $value ) {
		global $wpdb;
		$table = self::get_table_name();

		// Strict whitelists — column name cannot be parameterised, so it is interpolated
		// only after passing through an explicit allow-list.
		$numeric_fields = array( 'delivery_price', 'express_fee', 'minimum_order' );
		$text_fields    = array( 'delivery_notes', 'delivery_notes_en' );

		if ( in_array( $field_name, $numeric_fields, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET `{$field_name}` = %f, updated_at = %s WHERE governorate_key = %s",
					(float) $value,
					current_time( 'mysql' ),
					$gov_key
				)
			);
		}

		if ( in_array( $field_name, $text_fields, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET `{$field_name}` = %s, updated_at = %s WHERE governorate_key = %s",
					(string) $value,
					current_time( 'mysql' ),
					$gov_key
				)
			);
		}

		return false;
	}

	// ---------------------------------------------------------------------------
	// Uninstall
	// ---------------------------------------------------------------------------

	/**
	 * Drops the table entirely. Only called from uninstall.php.
	 */
	public static function drop_table() {
		global $wpdb;
		$table = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
