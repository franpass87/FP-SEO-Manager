<?php
/**
 * Performance service provider.
 *
 * Registers performance optimization services.
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
use FP\SEO\Infrastructure\Traits\FactoryHelperTrait;
use FP\SEO\Infrastructure\Config\ServiceConfig;
use FP\SEO\Utils\PerformanceOptimizer;
use FP\SEO\Utils\AdvancedCache;
use FP\SEO\Utils\RateLimiter;
use FP\SEO\Utils\PerformanceMonitor;
use FP\SEO\Utils\DatabaseOptimizer;
use FP\SEO\Utils\AssetOptimizer;
use FP\SEO\Utils\HealthChecker;
use FP\SEO\Admin\PerformanceDashboard;
use FP\SEO\Utils\Logger;

/**
 * Performance service provider.
 */
class PerformanceServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ConditionalServiceTrait;
	use HookHelperTrait;
	use FactoryHelperTrait;

	/**
	 * Register performance services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Performance Optimizer - always load
		$container->singleton( PerformanceOptimizer::class );

		// Performance Monitor - always load
		$container->singleton( PerformanceMonitor::class );

		// Rate Limiter - depends on AdvancedCache
		$container->singleton( RateLimiter::class, function( Container $container ) {
			return new RateLimiter( $container->get( AdvancedCache::class ) );
		} );

		// Database Optimizer - depends on PerformanceMonitor
		$container->singleton( DatabaseOptimizer::class, function( Container $container ) {
			return new DatabaseOptimizer( $container->get( PerformanceMonitor::class ) );
		} );

		// Asset Optimizer - depends on PerformanceMonitor and requires WP functions
		$container->singleton( AssetOptimizer::class, $this->create_asset_optimizer_factory() );

		// Health Checker - depends on PerformanceMonitor, DatabaseOptimizer, AssetOptimizer (optional)
		$container->singleton( HealthChecker::class, $this->create_health_checker_factory() );

		// Performance Dashboard - depends on HealthChecker, PerformanceMonitor, DatabaseOptimizer, AssetOptimizer (optional)
		$container->singleton( PerformanceDashboard::class, $this->create_performance_dashboard_factory() );
	}

	/**
	 * Boot performance services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register Performance Optimizer hooks
		$this->boot_service(
			$container,
			PerformanceOptimizer::class,
			'warning',
			'Failed to register PerformanceOptimizer'
		);

		// Initialize Asset Optimizer on init hook
		$this->defer_to_init( $container, function( Container $container ) {
			if ( ! ServiceConfig::is_wp_available() ) {
				return;
			}

			try {
				$asset_optimizer = $container->get( AssetOptimizer::class );
				if ( method_exists( $asset_optimizer, 'init' ) ) {
					$asset_optimizer->init();
				}
			} catch ( \Throwable $e ) {
				Logger::debug(
					'AssetOptimizer initialization skipped',
					array( 'reason' => $e->getMessage() )
				);
			}
		}, 1 );

		// Performance Dashboard will be registered by AdminPagesServiceProvider after Menu
	}

	/**
	 * Create factory for AssetOptimizer.
	 *
	 * @return callable Factory function.
	 */
	private function create_asset_optimizer_factory(): callable {
		return function( Container $container ) {
			if ( ! ServiceConfig::is_wp_available() ) {
				throw new \RuntimeException( 'WordPress functions not available for AssetOptimizer' );
			}

			if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
				throw new \RuntimeException( 'FP_SEO_PERFORMANCE_FILE constant not defined' );
			}

			try {
				return new AssetOptimizer(
					FP_SEO_PERFORMANCE_FILE,
					$container->get( PerformanceMonitor::class )
				);
			} catch ( \Exception $e ) {
				throw new \RuntimeException(
					'Failed to create AssetOptimizer: ' . $e->getMessage(),
					0,
					$e
				);
			}
		};
	}

	/**
	 * Create factory for HealthChecker with optional AssetOptimizer.
	 *
	 * @return callable Factory function.
	 */
	private function create_health_checker_factory(): callable {
		return function( Container $container ) {
			$asset_optimizer = null;
			try {
				$asset_optimizer = $container->get( AssetOptimizer::class );
			} catch ( \Throwable $e ) {
				Logger::debug(
					'AssetOptimizer not available for HealthChecker',
					array( 'error' => $e->getMessage() )
				);
			}

			return new HealthChecker(
				$container->get( PerformanceMonitor::class ),
				$container->get( DatabaseOptimizer::class ),
				$asset_optimizer
			);
		};
	}

	/**
	 * Create factory for PerformanceDashboard with optional AssetOptimizer.
	 *
	 * @return callable Factory function.
	 */
	private function create_performance_dashboard_factory(): callable {
		return function( Container $container ) {
			$asset_optimizer = null;
			try {
				$asset_optimizer = $container->get( AssetOptimizer::class );
			} catch ( \Throwable $e ) {
				Logger::debug(
					'AssetOptimizer not available for PerformanceDashboard',
					array( 'error' => $e->getMessage() )
				);
			}

			return new PerformanceDashboard(
				$container->get( HealthChecker::class ),
				$container->get( PerformanceMonitor::class ),
				$container->get( DatabaseOptimizer::class ),
				$asset_optimizer
			);
		};
	}
}
