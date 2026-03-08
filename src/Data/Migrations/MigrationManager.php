<?php
/**
 * Migration manager - handles database migrations.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Migrations;

use wpdb;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Manages database migrations.
 */
class MigrationManager {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Option name for storing migration version.
	 *
	 * @var string
	 */
	private string $version_option = 'fp_seo_migration_version';

	/**
	 * Constructor.
	 *
	 * @param wpdb                  $wpdb   WordPress database instance.
	 * @param LoggerInterface|null $logger Optional logger instance.
	 */
	public function __construct( wpdb $wpdb, ?LoggerInterface $logger = null ) {
		$this->wpdb   = $wpdb;
		$this->logger = $logger;
	}

	/**
	 * Run pending migrations.
	 *
	 * @param array<MigrationInterface> $migrations Available migrations (sorted by version).
	 * @return bool True on success, false on failure.
	 */
	public function migrate( array $migrations ): bool {
		$current_version = $this->get_current_version();
		$migrated        = false;

		foreach ( $migrations as $migration ) {
			$migration_version = $migration->get_version();

			// Skip if already migrated
			if ( version_compare( $migration_version, $current_version, '<=' ) ) {
				continue;
			}

			// Run migration
			if ( $this->logger ) {
				$this->logger->info(
					'Running migration: ' . $migration->get_description(),
					array( 'version' => $migration_version )
				);
			}

			if ( $migration->up() ) {
				$this->set_version( $migration_version );
				$current_version = $migration_version;
				$migrated        = true;
			} else {
				if ( $this->logger ) {
					$this->logger->error(
						'Migration failed: ' . $migration->get_description(),
						array( 'version' => $migration_version )
					);
				}
				return false;
			}
		}

		return $migrated;
	}

	/**
	 * Rollback to a specific version.
	 *
	 * @param array<MigrationInterface> $migrations Available migrations (sorted by version, descending).
	 * @param string                    $target_version Target version to rollback to.
	 * @return bool True on success, false on failure.
	 */
	public function rollback( array $migrations, string $target_version ): bool {
		$current_version = $this->get_current_version();

		// Sort migrations by version descending
		usort(
			$migrations,
			function ( MigrationInterface $a, MigrationInterface $b ) {
				return version_compare( $b->get_version(), $a->get_version() );
			}
		);

		foreach ( $migrations as $migration ) {
			$migration_version = $migration->get_version();

			// Skip if already at or below target version
			if ( version_compare( $migration_version, $target_version, '<=' ) ) {
				break;
			}

			// Skip if not yet migrated
			if ( version_compare( $migration_version, $current_version, '>' ) ) {
				continue;
			}

			// Rollback migration
			if ( $this->logger ) {
				$this->logger->info(
					'Rolling back migration: ' . $migration->get_description(),
					array( 'version' => $migration_version )
				);
			}

			if ( $migration->down() ) {
				// Find previous version
				$previous_version = $target_version;
				foreach ( $migrations as $m ) {
					if ( version_compare( $m->get_version(), $migration_version, '<' ) ) {
						$previous_version = $m->get_version();
						break;
					}
				}
				$this->set_version( $previous_version );
				$current_version = $previous_version;
			} else {
				if ( $this->logger ) {
					$this->logger->error(
						'Rollback failed: ' . $migration->get_description(),
						array( 'version' => $migration_version )
					);
				}
				return false;
			}
		}

		return true;
	}

	/**
	 * Get current migration version.
	 *
	 * @return string Current version or '0.0.0' if none.
	 */
	public function get_current_version(): string {
		return get_option( $this->version_option, '0.0.0' );
	}

	/**
	 * Set migration version.
	 *
	 * @param string $version Version to set.
	 * @return void
	 */
	private function set_version( string $version ): void {
		update_option( $this->version_option, $version, false );
	}
}



