<?php
/**
 * Data service provider.
 *
 * Registers repositories and database migrations.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Data\Migrations\MigrationManager;
use FP\SEO\Data\Migrations\CreateRedirectsTable;
use FP\SEO\Data\Migrations\CreateScoreHistoryTable;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use wpdb;

/**
 * Data service provider.
 *
 * Registers data layer services (repositories, migrations).
 */
class DataServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
		);
	}

	/**
	 * Register data services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Migration Manager
		$container->singleton( MigrationManager::class, function( Container $container ) {
			global $wpdb;
			$logger = $container->get( LoggerInterface::class );
			return new MigrationManager( $wpdb, $logger );
		} );
	}

	/**
	 * Boot data services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Migrations are run during activation, not during boot
	}

	/**
	 * Run activation routines for data services.
	 *
	 * @return void
	 */
	public function activate(): void {
		global $wpdb;

		// Run migrations
		// Note: During activation, we run migrations directly since container may not be fully initialized
		$migrations = array(
			new CreateScoreHistoryTable( $wpdb ),
			new CreateRedirectsTable( $wpdb ),
		);

		$current_version = get_option( 'fp_seo_migration_version', '0.0.0' );

		foreach ( $migrations as $migration ) {
			$migration_version = $migration->get_version();

			// Skip if already migrated
			if ( version_compare( $migration_version, $current_version, '<=' ) ) {
				continue;
			}

			// Run migration
			try {
				if ( $migration->up() ) {
					update_option( 'fp_seo_migration_version', $migration_version, false );
					$current_version = $migration_version;
				}
			} catch ( \Throwable $e ) {
				// Log error but don't block activation
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					error_log( 'FP SEO: Migration failed: ' . $e->getMessage() );
				}
			}
		}
	}
}



