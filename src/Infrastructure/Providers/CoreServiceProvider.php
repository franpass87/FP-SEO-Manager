<?php
/**
 * Core service provider.
 *
 * Registers fundamental services like Cache, Logger (static), and HealthChecker.
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
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\SiteHealth\SeoHealth;
use FP\SEO\Utils\AdvancedCache;
use FP\SEO\Perf\Signals;
use FP\SEO\History\ScoreHistory;
use FP\SEO\Utils\Logger;

/**
 * Core service provider.
 *
 * Registers essential services that other providers depend on.
 */
class CoreServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ConditionalServiceTrait;
	use HookHelperTrait;

	/**
	 * Register core services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Advanced Cache - fundamental service, always load
		$container->singleton( AdvancedCache::class );

		// Site Health - registers WordPress Site Health checks
		$container->singleton( SeoHealth::class, function() use ( $container ) {
			return new SeoHealth( new Signals() );
		} );

		// Score History - tracks SEO score changes over time
		$container->singleton( ScoreHistory::class );
	}

	/**
	 * Boot core services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register Site Health checks
		$this->boot_service(
			$container,
			SeoHealth::class,
			'warning',
			'Failed to register SeoHealth'
		);

		// Register Score History (defer to admin_init for admin-only hook)
		if ( $this->is_admin_context() ) {
			$this->defer_to_admin_init( $container, function( Container $container ) {
				$this->boot_service(
					$container,
					ScoreHistory::class,
					'warning',
					'Failed to register ScoreHistory'
				);
			}, 20 );
		}
	}

	/**
	 * Run activation routines for core services.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Create Score History table
		// La classe è nel namespace corretto, l'autoloader la caricherà automaticamente
		try {
			$score_history = new ScoreHistory();
			$score_history->create_table();
		} catch ( \Throwable $e ) {
			// Log errore ma non bloccare l'attivazione
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Failed to create ScoreHistory table on activation', array(
					'error' => $e->getMessage(),
				) );
			}
		}
	}
}
