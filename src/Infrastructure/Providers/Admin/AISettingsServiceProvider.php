<?php
/**
 * AI Settings service provider.
 *
 * Registers AI settings and AI-First admin features.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Admin;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Admin\AiSettings;
use FP\SEO\Admin\AiFirstAjaxHandler;
use FP\SEO\Admin\BulkAiActions;
use FP\SEO\Admin\AiFirstSettingsIntegration;
use FP\SEO\Admin\AiAjaxHandler;

/**
 * AI Settings service provider.
 */
class AISettingsServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;
	use HookHelperTrait;
	use ServiceRegistrationTrait;

	/**
	 * Register AI settings services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register all AI settings services as singletons
		$this->register_singletons( $container, array(
			AiSettings::class,
			AiFirstAjaxHandler::class,
			BulkAiActions::class,
			AiFirstSettingsIntegration::class,
			AiAjaxHandler::class,
		) );
	}

	/**
	 * Boot AI settings services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// Boot AI Settings and AI-First features
		$this->boot_services_simple( $container, array(
			AiSettings::class,
			AiFirstAjaxHandler::class,
			BulkAiActions::class,
			AiFirstSettingsIntegration::class,
		), 'warning', 'Failed to register' );

		// Register AI AJAX Handler on admin_init (error level for critical service)
		$this->defer_to_admin_init( $container, function( Container $container ) {
			$this->boot_service(
				$container,
				AiAjaxHandler::class,
				'error',
				'Failed to register AiAjaxHandler'
			);
		} );
	}
}
