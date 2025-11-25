<?php
/**
 * Integration service provider.
 *
 * Registers external integrations (GSC, Indexing API, etc.) with conditional loading.
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
use FP\SEO\Admin\GscSettings;
use FP\SEO\Admin\GscDashboard;
use FP\SEO\Integrations\GscClient;
use FP\SEO\Integrations\GscData;
use FP\SEO\Integrations\IndexingApi;
use FP\SEO\Utils\Logger;

/**
 * Integration service provider.
 */
class IntegrationServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ConditionalServiceTrait;

	/**
	 * Register integration services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// GSC Settings - always register (users need it to configure credentials)
		if ( $this->is_admin_context() ) {
			$container->singleton( GscSettings::class );
		}

		// GSC Client and Data - only if configured
		if ( $this->is_gsc_configured() ) {
			$container->singleton( GscClient::class );
			$container->singleton( GscData::class );

			// GSC Dashboard - only if configured
			if ( $this->is_admin_context() ) {
				$container->singleton( GscDashboard::class );
			}
		}

		// Indexing API
		$container->singleton( IndexingApi::class );
	}

	/**
	 * Boot integration services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register GSC Settings (always, so users can configure)
		if ( $this->is_admin_context() ) {
			$this->boot_service(
				$container,
				GscSettings::class,
				'warning',
				'Failed to register GscSettings'
			);
		}

		// Register GSC Dashboard only if configured
		if ( $this->is_admin_context() && $this->is_gsc_configured() ) {
			$this->boot_service(
				$container,
				GscDashboard::class,
				'warning',
				'Failed to register GscDashboard'
			);
		}
	}
}
