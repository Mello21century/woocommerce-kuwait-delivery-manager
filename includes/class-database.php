<?php
/**
 * KDM_Database
 *
 * All database interactions for the plugin.
 * Schema version: 2.1  (two tables: kdm_delivery_cities + kdm_delivery_areas with JSON columns + free_minimum_order)
 *
 * @package KuwaitDeliveryManager
 * @since   1.3.0
 */

defined( 'ABSPATH' ) || exit;

class KDM_Database {

	// ---------------------------------------------------------------------------
	// Table Names
	// ---------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public static function get_cities_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'kdm_delivery_cities';
	}

	/**
	 * @return string
	 */
	public static function get_areas_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'kdm_delivery_areas';
	}

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Creates or upgrades both delivery tables using dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		$cities_table = self::get_cities_table_name();
		$areas_table  = self::get_areas_table_name();

		$sql_cities = "CREATE TABLE {$cities_table} (
  city_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  country_iso2 varchar(2) NOT NULL DEFAULT '',
  city_name longtext NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  sorting int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (city_id),
  KEY country_active_sort (country_iso2, is_active, sorting)
) {$collate};";

		$sql_areas = "CREATE TABLE {$areas_table} (
  area_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  city_id bigint(20) unsigned NOT NULL,
  area_name longtext NOT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  sorting int(11) NOT NULL DEFAULT 0,
  delivery_price decimal(10,3) NOT NULL DEFAULT 0.000,
  express_fee decimal(10,3) NOT NULL DEFAULT 0.000,
  delivery_notes longtext DEFAULT NULL,
  minimum_order decimal(10,3) NOT NULL DEFAULT 0.000,
  free_minimum_order decimal(10,3) NOT NULL DEFAULT 0.000,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (area_id),
  KEY city_active_sort (city_id, is_active, sorting)
) {$collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_cities );
		dbDelta( $sql_areas );
	}

	/**
	 * Runs pending schema migrations on plugins_loaded.
	 * Compares the stored db version to KDM_DB_VERSION and applies
	 * each migration in sequence.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'kdm_db_version', '1.0' );

		if ( version_compare( $installed, KDM_DB_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $installed, '2.0', '<' ) ) {
			// Full table creation covers pre-2.0 installs.
			self::create_tables();
		}

		if ( version_compare( $installed, '2.1', '<' ) ) {
			self::upgrade_to_2_1();
		}

		update_option( 'kdm_db_version', KDM_DB_VERSION );
	}

	/**
	 * Migration: adds free_minimum_order column to kdm_delivery_areas.
	 */
	private static function upgrade_to_2_1(): void {
		global $wpdb;

		$table = self::get_areas_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cols = $wpdb->get_col( "DESC `{$table}`", 0 );

		if ( ! in_array( 'free_minimum_order', $cols, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE `{$table}` ADD COLUMN `free_minimum_order` decimal(10,3) NOT NULL DEFAULT 0.000 AFTER `minimum_order`"
			);
		}
	}

	// ---------------------------------------------------------------------------
	// Seed
	// ---------------------------------------------------------------------------

	/**
	 * Inserts default Kuwait cities and areas.
	 * Skips if cities already exist — idempotent.
	 */
	public static function seed_default_data(): void {
		global $wpdb;

		$cities_table = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cities_table}" ) > 0 ) {
			return;
		}

		$now = current_time( 'mysql' );

		// Seed structure: city => [ name_ar, name_en, areas => [ [ar, en, price, express, min_order, free_min_order] ] ]
		$data = array(
			array(
				'ar'    => "\xd8\xa7\xd9\x84\xd8\xb9\xd8\xa7\xd8\xb5\xd9\x85\xd8\xa9",
				'en'    => 'Kuwait Capital',
				'areas' => array(
					array( "\xd8\xa7\xd9\x84\xd9\x83\xd9\x88\xd9\x8a\xd8\xaa", 'Kuwait City', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb4\xd9\x88\xd9\x8a\xd8\xae", 'Shuwaikh', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xaf\xd8\xa7\xd8\xb3\xd9\x85\xd8\xa9", 'Dasma', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x82\xd8\xa7\xd8\xaf\xd8\xb3\xd9\x8a\xd8\xa9", 'Qadisiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd9\x88\xd8\xb6\xd8\xa9", 'Rawda', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb5\xd8\xa7\xd9\x84\xd8\xad\xd9\x8a\xd8\xa9", 'Salehiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb3\xd8\xb1\xd8\xa9", 'Surra', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x86\xd8\xb2\xd9\x87\xd8\xa9", 'Nuzha', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd9\x83\xd9\x8a\xd9\x81\xd8\xa7\xd9\x86", 'Kaifan', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb4\xd8\xa7\xd9\x85\xd9\x8a\xd8\xa9", 'Shamiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x85\xd8\xb1\xd9\x82\xd8\xa7\xd8\xa8", 'Mirqab', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd9\x82\xd8\xb1\xd8\xb7\xd8\xa8\xd8\xa9", 'Qortuba', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x81\xd9\x8a\xd8\xad\xd8\xa7\xd8\xa1", 'Faiha', 1.500, 1.000, 0.000, 0.000 ),
				),
			),
			array(
				'ar'    => "\xd8\xad\xd9\x88\xd9\x84\xd9\x8a",
				'en'    => 'Hawalli',
				'areas' => array(
					array( "\xd8\xad\xd9\x88\xd9\x84\xd9\x8a", 'Hawalli', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd9\x85\xd9\x8a\xd8\xab\xd9\x8a\xd8\xa9", 'Rumaithiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xb3\xd9\x84\xd9\x88\xd9\x89", 'Salwa', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb4\xd8\xb9\xd8\xa8", 'Sha\'ab', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa8\xd9\x8a\xd8\xa7\xd9\x86", 'Bayan', 1.750, 1.000, 0.000, 0.000 ),
					array( "\xd9\x85\xd8\xb4\xd8\xb1\xd9\x81", 'Mishref', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xac\xd8\xa7\xd8\xa8\xd8\xb1\xd9\x8a\xd8\xa9", 'Jabriya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb3\xd8\xa7\xd9\x84\xd9\x85\xd9\x8a\xd8\xa9", 'Salmiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd8\xa3\xd9\x8a", 'Ar-Rai', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd9\x85\xd9\x8a\xd8\xaf\xd8\xa7\xd9\x86 \xd8\xad\xd9\x88\xd9\x84\xd9\x8a", 'Hawalli Square', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa8\xd9\x8a\xd8\xa7\xd9\x86 \xd8\xa7\xd9\x84\xd8\xac\xd9\x86\xd9\x88\xd8\xa8\xd9\x8a\xd8\xa9", 'South Bayan', 1.750, 1.250, 0.000, 0.000 ),
				),
			),
			array(
				'ar'    => "\xd8\xa7\xd9\x84\xd9\x81\xd8\xb1\xd9\x88\xd8\xa7\xd9\x86\xd9\x8a\xd8\xa9",
				'en'    => 'Al-Farwaniyah',
				'areas' => array(
					array( "\xd8\xa7\xd9\x84\xd9\x81\xd8\xb1\xd9\x88\xd8\xa7\xd9\x86\xd9\x8a\xd8\xa9", 'Farwaniya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb9\xd8\xa7\xd8\xb1\xd8\xb6\xd9\x8a\xd8\xa9", 'Ardhiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd8\xa7\xd8\xa8\xd9\x8a\xd8\xa9", 'Rabiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xae\xd9\x8a\xd8\xb7\xd8\xa7\xd9\x86", 'Khaitan', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd8\xba\xd8\xa9", 'Regai', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa3\xd8\xa8\xd9\x88 \xd9\x81\xd8\xb7\xd9\x8a\xd8\xb1\xd8\xa9", 'Abu Futaira', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb9\xd9\x85\xd8\xb1\xd9\x8a\xd8\xa9", 'Omairiya', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xa3\xd9\x86\xd8\xaf\xd9\x84\xd8\xb3", 'Andalus', 1.500, 1.000, 0.000, 0.000 ),
					array( "\xd8\xac\xd9\x84\xd9\x8a\xd8\xa8 \xd8\xa7\xd9\x84\xd8\xb4\xd9\x8a\xd9\x88\xd8\xae", 'Jleeb Al-Shuyoukh', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb6\xd8\xac\xd9\x8a\xd8\xac", 'Dhajej', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb9\xd8\xa8\xd8\xa7\xd8\xb3\xd9\x8a\xd8\xa9", 'Abbasiya', 1.500, 1.000, 0.000, 0.000 ),
				),
			),
			array(
				'ar'    => "\xd8\xa7\xd9\x84\xd8\xa3\xd8\xad\xd9\x85\xd8\xaf\xd9\x8a",
				'en'    => 'Ahmadi',
				'areas' => array(
					array( "\xd8\xa7\xd9\x84\xd8\xa3\xd8\xad\xd9\x85\xd8\xaf\xd9\x8a", 'Ahmadi', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa3\xd8\xa8\xd9\x88 \xd8\xad\xd9\x84\xd9\x8a\xd9\x81\xd8\xa9", 'Abu Halifa', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x81\xd9\x86\xd8\xb7\xd8\xa7\xd8\xb3", 'Fintaas', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x85\xd9\x87\xd8\xa8\xd9\x88\xd9\x84\xd8\xa9", 'Mahboula', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb5\xd8\xa8\xd8\xa7\xd8\xad\xd9\x8a\xd8\xa9", 'Sabahiya', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb9\xd9\x82\xd9\x8a\xd9\x84\xd8\xa9", 'Aqila', 2.250, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb1\xd9\x82\xd8\xb9\xd9\x8a", 'Ruqai', 2.250, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x81\xd8\xad\xd9\x8a\xd8\xad\xd9\x8a\xd9\x84", 'Fahaheel', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x85\xd9\x86\xd9\x82\xd9\x81", 'Mangaf', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x88\xd9\x81\xd8\xb1\xd8\xa9", 'Wafra', 3.000, 2.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb2\xd9\x88\xd8\xb1", 'Zour', 3.500, 2.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xae\xd9\x8a\xd8\xb1\xd8\xa7\xd9\x86", 'Khairan', 4.000, 3.000, 5.000, 0.000 ),
				),
			),
			array(
				'ar'    => "\xd8\xa7\xd9\x84\xd8\xac\xd9\x87\xd8\xb1\xd8\xa7\xd8\xa1",
				'en'    => 'Al-Jahra',
				'areas' => array(
					array( "\xd8\xa7\xd9\x84\xd8\xac\xd9\x87\xd8\xb1\xd8\xa7\xd8\xa1", 'Jahra', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb9\xd9\x8a\xd9\x88\xd9\x86", 'Uyun', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x82\xd8\xb5\xd8\xb1", 'Qasr', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb3\xd9\x84\xd8\xa8\xd9\x8a\xd8\xa9", 'Sulaibiya', 2.250, 1.500, 0.000, 0.000 ),
					array( "\xd8\xaa\xd9\x8a\xd9\x85\xd8\xa7\xd8\xa1", 'Tayma', 2.500, 2.000, 0.000, 0.000 ),
					array( "\xd9\x83\xd8\xa7\xd8\xb8\xd9\x85\xd8\xa9", 'Kadhima', 2.500, 2.000, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x88\xd8\xa7\xd8\xad\xd8\xa9", 'Waha', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x86\xd8\xb9\xd9\x8a\xd9\x85", 'Naeem', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x82\xd9\x8a\xd8\xb1\xd9\x88\xd8\xa7\xd9\x86", 'Qairawan', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb5\xd9\x84\xd9\x8a\xd8\xa8\xd9\x8a\xd8\xae\xd8\xa7\xd8\xaa", 'Sulaibikhat', 2.500, 2.000, 0.000, 0.000 ),
				),
			),
			array(
				'ar'    => "\xd9\x85\xd8\xa8\xd8\xa7\xd8\xb1\xd9\x83 \xd8\xa7\xd9\x84\xd9\x83\xd8\xa8\xd9\x8a\xd8\xb1",
				'en'    => 'Mubarak Al-Kabeer',
				'areas' => array(
					array( "\xd9\x85\xd8\xa8\xd8\xa7\xd8\xb1\xd9\x83 \xd8\xa7\xd9\x84\xd9\x83\xd8\xa8\xd9\x8a\xd8\xb1", 'Mubarak Al-Kabeer', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa3\xd8\xa8\xd9\x88 \xd8\xa7\xd9\x84\xd8\xad\xd8\xb5\xd8\xa7\xd9\x86\xd9\x8a\xd8\xa9", 'Abu Hasaniya', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x82\xd8\xb5\xd9\x88\xd8\xb1", 'Qusor', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xb5\xd8\xa8\xd8\xad\xd8\xa7\xd9\x86", 'Subhan', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x81\xd9\x86\xd9\x8a\xd8\xb7\xd9\x8a\xd8\xb3", 'Fintas', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd9\x85\xd8\xb3\xd9\x8a\xd9\x84\xd8\xa9", 'Masaeel', 2.000, 1.500, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xb5\xd8\xaf\xd9\x8a\xd9\x82", 'Siddeeq', 1.750, 1.250, 0.000, 0.000 ),
					array( "\xd8\xa7\xd9\x84\xd8\xa8\xd9\x8a\xd8\xaa\xd8\xa7\xd8\xa1", 'Baitah', 1.750, 1.250, 0.000, 0.000 ),
				),
			),
		);

		$city_sort = 0;
		foreach ( $data as $city_data ) {
			$city_name = wp_json_encode(
				array( 'en' => $city_data['en'], 'ar' => $city_data['ar'] ),
				JSON_UNESCAPED_UNICODE
			);

			$wpdb->insert(
				$cities_table,
				array(
					'country_iso2' => 'KW',
					'city_name'    => $city_name,
					'is_active'    => 1,
					'sorting'      => $city_sort++,
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%s', '%s', '%d', '%d', '%s', '%s' )
			);

			$city_id = (int) $wpdb->insert_id;
			if ( ! $city_id ) {
				continue;
			}

			$areas_table = self::get_areas_table_name();
			$area_sort   = 0;

			foreach ( $city_data['areas'] as $area ) {
				$area_name = wp_json_encode(
					array( 'en' => $area[1], 'ar' => $area[0] ),
					JSON_UNESCAPED_UNICODE
				);

				$wpdb->insert(
					$areas_table,
					array(
						'city_id'            => $city_id,
						'area_name'          => $area_name,
						'is_active'          => 1,
						'sorting'            => $area_sort++,
						'delivery_price'     => (float) $area[2],
						'express_fee'        => (float) $area[3],
						'delivery_notes'     => null,
						'minimum_order'      => (float) $area[4],
						'free_minimum_order' => (float) $area[5],
						'created_at'         => $now,
						'updated_at'         => $now,
					),
					array( '%d', '%s', '%d', '%d', '%f', '%f', '%s', '%f', '%f', '%s', '%s' )
				);
			}
		}
	}

	// ---------------------------------------------------------------------------
	// City — Read
	// ---------------------------------------------------------------------------

	/**
	 * Fetches all cities for a country (admin — includes inactive).
	 *
	 * @param string $country_iso2
	 * @return array
	 */
	public static function get_all_cities_by_country( string $country_iso2 ): array {
		global $wpdb;
		$table = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE country_iso2 = %s ORDER BY sorting ASC, city_id ASC",
				sanitize_text_field( $country_iso2 )
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches active cities for a country.
	 *
	 * @param string $country_iso2
	 * @return array
	 */
	public static function get_cities_by_country( string $country_iso2 ): array {
		global $wpdb;
		$table = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE country_iso2 = %s AND is_active = 1 ORDER BY sorting ASC, city_id ASC",
				sanitize_text_field( $country_iso2 )
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches a single city by ID.
	 *
	 * @param int $city_id
	 * @return array|null
	 */
	public static function get_city( int $city_id ): ?array {
		global $wpdb;
		$table = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE city_id = %d", absint( $city_id ) ),
			ARRAY_A
		);
	}

	// ---------------------------------------------------------------------------
	// City — Write
	// ---------------------------------------------------------------------------

	/**
	 * Inserts a new city. Auto-assigns sorting = MAX + 1 for the country.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function add_city( array $data ) {
		global $wpdb;
		$table = self::get_cities_table_name();

		$country = sanitize_text_field( $data['country_iso2'] ?? '' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sorting) FROM {$table} WHERE country_iso2 = %s",
				$country
			)
		);

		$now = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table,
			array(
				'country_iso2' => $country,
				'city_name'    => $data['city_name'],
				'is_active'    => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
				'sorting'      => is_null( $max ) ? 0 : (int) $max + 1,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing city.
	 *
	 * @param int   $city_id
	 * @param array $data
	 * @return bool
	 */
	public static function save_city( int $city_id, array $data ): bool {
		global $wpdb;
		$table = self::get_cities_table_name();

		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update(
			$table,
			$data,
			array( 'city_id' => absint( $city_id ) ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Deletes a city and all its child areas (manual cascade).
	 *
	 * @param int $city_id
	 * @return bool
	 */
	public static function delete_city( int $city_id ): bool {
		global $wpdb;

		$city_id = absint( $city_id );

		// Delete child areas first.
		$areas_table = self::get_areas_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$areas_table} WHERE city_id = %d", $city_id )
		);

		$cities_table = self::get_cities_table_name();
		return false !== $wpdb->delete( $cities_table, array( 'city_id' => $city_id ), array( '%d' ) );
	}

	/**
	 * Toggles is_active for a city.
	 *
	 * @param int $city_id
	 * @return int|false
	 */
	public static function toggle_city( int $city_id ) {
		global $wpdb;
		$table   = self::get_cities_table_name();
		$city_id = absint( $city_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE city_id = %d", $city_id )
		);

		if ( is_null( $current ) ) {
			return false;
		}

		$new = $current ? 0 : 1;

		$result = $wpdb->update(
			$table,
			array( 'is_active' => $new, 'updated_at' => current_time( 'mysql' ) ),
			array( 'city_id' => $city_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result ? $new : false;
	}

	/**
	 * Bulk-updates sorting values for cities after drag-and-drop.
	 *
	 * @param array $items [ ['city_id' => int, 'sorting' => int], ... ]
	 * @return bool
	 */
	public static function update_city_sort_order( array $items ): bool {
		global $wpdb;
		$table = self::get_cities_table_name();

		foreach ( $items as $item ) {
			$wpdb->update(
				$table,
				array( 'sorting' => absint( $item['sorting'] ) ),
				array( 'city_id' => absint( $item['city_id'] ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	// ---------------------------------------------------------------------------
	// Area — Read
	// ---------------------------------------------------------------------------

	/**
	 * Fetches all areas for a city (admin — includes inactive).
	 *
	 * @param int $city_id
	 * @return array
	 */
	public static function get_all_areas_by_city( int $city_id ): array {
		global $wpdb;
		$table = self::get_areas_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE city_id = %d ORDER BY sorting ASC, area_id ASC",
				absint( $city_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches active areas for a city.
	 *
	 * @param int $city_id
	 * @return array
	 */
	public static function get_areas_by_city( int $city_id ): array {
		global $wpdb;
		$table = self::get_areas_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE city_id = %d AND is_active = 1 ORDER BY sorting ASC, area_id ASC",
				absint( $city_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches a single area by ID.
	 *
	 * @param int $area_id
	 * @return array|null
	 */
	public static function get_area( int $area_id ): ?array {
		global $wpdb;
		$table = self::get_areas_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE area_id = %d", absint( $area_id ) ),
			ARRAY_A
		);
	}

	/**
	 * Returns all enabled areas grouped by city for a country.
	 * Used by the checkout combo dropdown.
	 *
	 * @param string $country_iso2
	 * @return array [ city_id => ['city' => city_row, 'areas' => [area_rows...]], ... ]
	 */
	public static function get_all_areas_grouped_by_city( string $country_iso2 ): array {
		global $wpdb;

		$cities_table = self::get_cities_table_name();
		$areas_table  = self::get_areas_table_name();
		$country      = sanitize_text_field( $country_iso2 );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, c.city_name, c.country_iso2
				 FROM {$areas_table} a
				 INNER JOIN {$cities_table} c ON a.city_id = c.city_id
				 WHERE c.country_iso2 = %s AND c.is_active = 1 AND a.is_active = 1
				 ORDER BY c.sorting ASC, c.city_id ASC, a.sorting ASC, a.area_id ASC",
				$country
			),
			ARRAY_A
		);

		$grouped = array();
		foreach ( $rows as $row ) {
			$cid = (int) $row['city_id'];
			if ( ! isset( $grouped[ $cid ] ) ) {
				$grouped[ $cid ] = array(
					'city' => array(
						'city_id'      => $cid,
						'city_name'    => $row['city_name'],
						'country_iso2' => $row['country_iso2'],
					),
					'areas' => array(),
				);
			}
			$grouped[ $cid ]['areas'][] = $row;
		}

		return $grouped;
	}

	/**
	 * Returns all country ISO2 codes that have at least one active city.
	 *
	 * @return array e.g. ['KW', 'SA']
	 */
	public static function get_countries_with_cities(): array {
		global $wpdb;
		$table = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_col(
			"SELECT DISTINCT country_iso2 FROM {$table} WHERE is_active = 1"
		);

		return $results ?: array();
	}

	// ---------------------------------------------------------------------------
	// Area — Write
	// ---------------------------------------------------------------------------

	/**
	 * Updates an existing area.
	 *
	 * @param int   $area_id
	 * @param array $data
	 * @return bool
	 */
	public static function save_area( int $area_id, array $data ): bool {
		global $wpdb;
		$table = self::get_areas_table_name();

		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update(
			$table,
			$data,
			array( 'area_id' => absint( $area_id ) ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Inserts a new area. Auto-assigns sorting = MAX + 1 for the city.
	 *
	 * @param array $data
	 * @return int|false
	 */
	public static function add_area( array $data ) {
		global $wpdb;
		$table   = self::get_areas_table_name();
		$city_id = absint( $data['city_id'] ?? 0 );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(sorting) FROM {$table} WHERE city_id = %d",
				$city_id
			)
		);

		$now = current_time( 'mysql' );

		$insert_data = array(
			'city_id'        => $city_id,
			'area_name'      => $data['area_name'] ?? wp_json_encode( array( 'en' => '', 'ar' => '' ) ),
			'is_active'      => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			'sorting'        => isset( $data['sorting'] ) ? absint( $data['sorting'] ) : ( is_null( $max ) ? 0 : (int) $max + 1 ),
			'delivery_price' => (float) ( $data['delivery_price'] ?? 0 ),
			'express_fee'    => (float) ( $data['express_fee'] ?? 0 ),
			'delivery_notes' => $data['delivery_notes'] ?? null,
			'minimum_order'  => (float) ( $data['minimum_order'] ?? 0 ),
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		$result = $wpdb->insert(
			$table,
			$insert_data,
			array( '%d', '%s', '%d', '%d', '%f', '%f', '%s', '%f', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Deletes a single area by ID.
	 *
	 * @param int $area_id
	 * @return bool
	 */
	public static function delete_area( int $area_id ): bool {
		global $wpdb;
		$table = self::get_areas_table_name();

		return false !== $wpdb->delete( $table, array( 'area_id' => absint( $area_id ) ), array( '%d' ) );
	}

	/**
	 * Toggles is_active for an area.
	 *
	 * @param int $area_id
	 * @return int|false
	 */
	public static function toggle_area( int $area_id ) {
		global $wpdb;
		$table   = self::get_areas_table_name();
		$area_id = absint( $area_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE area_id = %d", $area_id )
		);

		if ( is_null( $current ) ) {
			return false;
		}

		$new = $current ? 0 : 1;

		$result = $wpdb->update(
			$table,
			array( 'is_active' => $new, 'updated_at' => current_time( 'mysql' ) ),
			array( 'area_id' => $area_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return false !== $result ? $new : false;
	}

	/**
	 * Bulk-updates sorting values for areas after drag-and-drop.
	 *
	 * @param array $items [ ['area_id' => int, 'sorting' => int], ... ]
	 * @return bool
	 */
	public static function update_sort_order( array $items ): bool {
		global $wpdb;
		$table = self::get_areas_table_name();

		foreach ( $items as $item ) {
			$wpdb->update(
				$table,
				array( 'sorting' => absint( $item['sorting'] ) ),
				array( 'area_id' => absint( $item['area_id'] ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return true;
	}

	/**
	 * Copies a field value to all areas in a city.
	 * Column name is whitelisted — cannot be parameterised in prepared statements.
	 *
	 * @param int    $city_id
	 * @param string $field_name
	 * @param mixed  $value
	 * @return int|false
	 */
	public static function bulk_update_field( int $city_id, string $field_name, $value ) {
		global $wpdb;
		$table   = self::get_areas_table_name();
		$city_id = absint( $city_id );

		$numeric_fields = array( 'delivery_price', 'express_fee', 'minimum_order' );
		$json_fields    = array( 'delivery_notes' );

		if ( in_array( $field_name, $numeric_fields, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET `{$field_name}` = %f, updated_at = %s WHERE city_id = %d",
					(float) $value,
					current_time( 'mysql' ),
					$city_id
				)
			);
		}

		if ( in_array( $field_name, $json_fields, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET `{$field_name}` = %s, updated_at = %s WHERE city_id = %d",
					(string) $value,
					current_time( 'mysql' ),
					$city_id
				)
			);
		}

		return false;
	}

	// ---------------------------------------------------------------------------
	// Uninstall
	// ---------------------------------------------------------------------------

	/**
	 * Drops both tables. Only called from uninstall.php.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$areas  = self::get_areas_table_name();
		$cities = self::get_cities_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$areas}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$cities}" );
	}
}
