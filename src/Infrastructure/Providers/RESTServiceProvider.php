<?php
/**
 * REST API service provider.
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
use FP\SEO\REST\Controllers\MetaController;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Core\Services\Validation\ValidationServiceInterface;
use FP\SEO\Core\Services\Sanitization\SanitizationServiceInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * REST API service provider.
 *
 * Registers REST API endpoints and controllers.
 */
class RESTServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			DataServiceProvider::class,
		);
	}

	/**
	 * Register REST services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register MetaController
		$container->singleton( MetaController::class, function( Container $container ) {
			return new MetaController(
				$container->get( PostMetaRepositoryInterface::class ),
				$container->get( ValidationServiceInterface::class ),
				$container->get( SanitizationServiceInterface::class ),
				$container->get( HookManagerInterface::class )
			);
		} );
	}

	/**
	 * Boot REST services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register REST routes on rest_api_init
		add_action(
			'rest_api_init',
			function () use ( $container ) {
				try {
					$controller = $container->get( MetaController::class );
					$controller->register_routes();
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'FP SEO: Failed to register REST routes: ' . $e->getMessage() );
					}
				}
			},
			10
		);
	}
}



