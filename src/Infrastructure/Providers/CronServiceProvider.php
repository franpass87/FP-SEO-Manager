<?php
/**
 * Cron service provider.
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
use FP\SEO\Cron\Jobs\CleanupTransientsJob;
use FP\SEO\Cron\Jobs\ClearOptimizationFlagJob;
use FP\SEO\Utils\LoggerHelper;
use wpdb;

/**
 * Cron service provider.
 *
 * Registers scheduled tasks.
 */
class CronServiceProvider extends AbstractServiceProvider {

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
	 * Register cron services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register cron jobs
		$container->singleton( CleanupTransientsJob::class, function( Container $container ) {
			global $wpdb;
			return new CleanupTransientsJob( $wpdb );
		} );

		$container->singleton( ClearOptimizationFlagJob::class );
	}

	/**
	 * Boot cron services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register cleanup transients job (boot_service calls $job->register() automatically)
		$this->boot_service(
			$container,
			CleanupTransientsJob::class,
			'warning',
			'Failed to register CleanupTransientsJob'
		);

		// Register clear optimization flag job (hook only, not scheduled)
		// boot_service() only calls register() — we need to register the WordPress hook manually.
		try {
			$job  = $container->get( ClearOptimizationFlagJob::class );
			$hook = $job->get_hook();
			add_action( $hook, array( $job, 'handle' ), 10, 1 );
		} catch ( \Throwable $e ) {
			LoggerHelper::warning( 'Failed to register ClearOptimizationFlagJob hook', array( 'error' => $e->getMessage() ) );
		}
	}
}



