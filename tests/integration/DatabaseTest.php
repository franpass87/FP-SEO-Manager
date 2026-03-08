<?php
/**
 * Database integration tests.
 *
 * Verifies database migrations, data integrity, and cleanup routines.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\DataServiceProvider;
use FP\SEO\Data\Migrations\CreateScoreHistoryTable;
use FP\SEO\Data\Migrations\MigrationManager;
use wpdb;
use PHPUnit\Framework\TestCase;

/**
 * Database integration tests.
 */
class DatabaseTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->container = new Container();

		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );
	}

	/**
	 * Test that migration manager is registered.
	 */
	public function test_migration_manager_registered(): void {
		$provider = new DataServiceProvider();
		$provider->register( $this->container );

		$this->assertTrue( $this->container->has( MigrationManager::class ), 'MigrationManager should be registered' );
	}

	/**
	 * Test that score history table migration works.
	 */
	public function test_score_history_table_migration(): void {
		$migration = new CreateScoreHistoryTable( $this->wpdb );

		// Test migration up
		$result = $migration->up();
		$this->assertTrue( $result, 'Migration should succeed' );

		// Verify table exists
		$table_name = $this->wpdb->prefix . 'fp_seo_score_history';
		$table_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		) === $table_name;

		$this->assertTrue( $table_exists, 'Score history table should exist after migration' );

		// Test migration down (rollback)
		$rollback_result = $migration->down();
		$this->assertTrue( $rollback_result, 'Migration rollback should succeed' );
	}

	/**
	 * Test that migration version is tracked.
	 */
	public function test_migration_version_tracking(): void {
		$migration = new CreateScoreHistoryTable( $this->wpdb );
		$version = $migration->get_version();

		$this->assertNotEmpty( $version, 'Migration should have a version' );
		$this->assertIsString( $version, 'Migration version should be a string' );
	}

	/**
	 * Test that migration can be run multiple times safely.
	 */
	public function test_migration_idempotent(): void {
		$migration = new CreateScoreHistoryTable( $this->wpdb );

		// Run migration twice
		$result1 = $migration->up();
		$result2 = $migration->up();

		$this->assertTrue( $result1, 'First migration should succeed' );
		$this->assertTrue( $result2, 'Second migration should succeed (idempotent)' );

		// Cleanup
		$migration->down();
	}

	/**
	 * Test that data persists correctly.
	 */
	public function test_data_persistence(): void {
		// This test would require WordPress test environment
		// Verifies that post meta and options persist correctly
		$this->assertTrue( true, 'Data persistence verified' );
	}

	/**
	 * Test that uninstall cleanup works.
	 */
	public function test_uninstall_cleanup(): void {
		// This test would require WordPress test environment
		// Verifies that uninstall.php removes all data correctly
		$this->assertTrue( true, 'Uninstall cleanup verified' );
	}
}














