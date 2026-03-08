<?php
/**
 * Migration: Create score history table.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Migrations;

use wpdb;

/**
 * Migration to create the score history table.
 */
class CreateScoreHistoryTable implements MigrationInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb WordPress database instance.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Get the migration version.
	 *
	 * @return string Migration version.
	 */
	public function get_version(): string {
		return '1.0.0';
	}

	/**
	 * Run the migration (up).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up(): bool {
		$table_name = $this->wpdb->prefix . 'fp_seo_score_history';

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			score tinyint(3) unsigned NOT NULL,
			status varchar(20) NOT NULL,
			checks_passed tinyint(3) unsigned NOT NULL DEFAULT 0,
			checks_warned tinyint(3) unsigned NOT NULL DEFAULT 0,
			checks_failed tinyint(3) unsigned NOT NULL DEFAULT 0,
			recorded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY recorded_at (recorded_at),
			KEY post_recorded (post_id, recorded_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Check if table was created
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		) === $table_name;

		return $table_exists;
	}

	/**
	 * Rollback the migration (down).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function down(): bool {
		$table_name = $this->wpdb->prefix . 'fp_seo_score_history';
		$result     = $this->wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result !== false;
	}

	/**
	 * Get migration description.
	 *
	 * @return string Description.
	 */
	public function get_description(): string {
		return 'Create score history table';
	}
}



