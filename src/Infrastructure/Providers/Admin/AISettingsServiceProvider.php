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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
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
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\AIServiceProvider::class,
		);
	}

	/**
	 * Register AI settings services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register AiFirstAjaxHandler with HookManager, Container, and Logger dependencies
		$container->singleton( AiFirstAjaxHandler::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$logger       = $container->get( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
			return new AiFirstAjaxHandler( $hook_manager, $container, $logger );
		} );

		// Register BulkAiActions with HookManager dependency
		$container->singleton( BulkAiActions::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new BulkAiActions( $hook_manager );
		} );

		// Register AiSettings with HookManager dependency
		$container->singleton( AiSettings::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new AiSettings( $hook_manager );
		} );

		// Register AiFirstSettingsIntegration with HookManager dependency
		$container->singleton( AiFirstSettingsIntegration::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new AiFirstSettingsIntegration( $hook_manager );
		} );

		// Register AiAjaxHandler with HookManager and OpenAiClient dependencies
		$container->singleton( AiAjaxHandler::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$client       = $container->get( \FP\SEO\Integrations\OpenAiClient::class );
			return new AiAjaxHandler( $hook_manager, $client );
		} );
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
