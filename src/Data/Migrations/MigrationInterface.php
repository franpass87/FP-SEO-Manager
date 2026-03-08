<?php
/**
 * Migration interface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Migrations;

/**
 * Interface for database migrations.
 */
interface MigrationInterface {

	/**
	 * Get the migration version.
	 *
	 * @return string Migration version (e.g., '1.0.0').
	 */
	public function get_version(): string;

	/**
	 * Run the migration (up).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function up(): bool;

	/**
	 * Rollback the migration (down).
	 *
	 * @return bool True on success, false on failure.
	 */
	public function down(): bool;

	/**
	 * Get migration description.
	 *
	 * @return string Description.
	 */
	public function get_description(): string;
}



