<?php
/**
 * Migration: Create redirects table.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Migrations;

use wpdb;

/**
 * Migration to create the redirects table for 301/302 redirects.
 */
class CreateRedirectsTable implements MigrationInterface {

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
		return '1.1.0';
	}

	/**
	 * Run the migration (up).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up(): bool {
		$table_name      = $this->wpdb->prefix . 'fp_seo_redirects';
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(500) NOT NULL,
			target_url varchar(500) NOT NULL,
			redirect_type varchar(10) NOT NULL DEFAULT '301',
			is_regex tinyint(1) unsigned NOT NULL DEFAULT 0,
			is_active tinyint(1) unsigned NOT NULL DEFAULT 1,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_url (source_url(191)),
			KEY redirect_type (redirect_type),
			KEY is_active (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

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
		$table_name = $this->wpdb->prefix . 'fp_seo_redirects';
		$result     = $this->wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $result !== false;
	}

	/**
	 * Get migration description.
	 *
	 * @return string Description.
	 */
	public function get_description(): string {
		return 'Create redirects table for 301/302 redirect manager';
	}
}
