<?php
/**
 * Frontend service provider.
 *
 * Registers frontend rendering services (Meta Tags, Schema, Social Media, etc.).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Frontend\Renderers\MetaTagRenderer;
use FP\SEO\Frontend\Renderers\SchemaRenderer;
use FP\SEO\Frontend\Renderers\SocialRenderer;
use FP\SEO\Frontend\Renderers\KeywordsRenderer;
use FP\SEO\Frontend\Shortcodes\GeoShortcodes;
use FP\SEO\Social\ImprovedSocialMediaManager;
use FP\SEO\Links\InternalLinkManager;
use FP\SEO\Keywords\MultipleKeywordsManager;
use FP\SEO\Schema\AdvancedSchemaManager;

/**
 * Frontend service provider.
 */
class FrontendServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ServiceRegistrationTrait;
	use ConditionalServiceTrait;

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
	 * Register frontend services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register MetaTagRenderer with HookManager dependency
		$container->singleton( MetaTagRenderer::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new MetaTagRenderer( $hook_manager );
		} );

		// Register GeoShortcodes with HookManager dependency
		$container->singleton( GeoShortcodes::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new GeoShortcodes( $hook_manager );
		} );

		// Register SchemaRenderer with dependencies
		$container->singleton( SchemaRenderer::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$schema_manager = $container->get( AdvancedSchemaManager::class );
			return new SchemaRenderer( $hook_manager, $schema_manager );
		} );

		// Register SocialRenderer with dependencies
		$container->singleton( SocialRenderer::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$social_manager = $container->get( ImprovedSocialMediaManager::class );
			return new SocialRenderer( $hook_manager, $social_manager );
		} );

		// Register KeywordsRenderer with dependencies
		$container->singleton( KeywordsRenderer::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$keywords_manager = $container->get( MultipleKeywordsManager::class );
			return new KeywordsRenderer( $hook_manager, $keywords_manager );
		} );

		// Register other frontend services as singletons with HookManager dependency
		$container->singleton( MultipleKeywordsManager::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new MultipleKeywordsManager( $hook_manager );
		} );
		
		$container->singleton( ImprovedSocialMediaManager::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$logger       = $container->get( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
			$cache        = $container->get( \FP\SEO\Infrastructure\Contracts\CacheInterface::class );
			return new ImprovedSocialMediaManager( $hook_manager, $logger, $cache );
		} );
		
		$container->singleton( InternalLinkManager::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new InternalLinkManager( $hook_manager );
		} );
		
		$container->singleton( AdvancedSchemaManager::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new AdvancedSchemaManager( $hook_manager );
		} );
	}

	/**
	 * Boot frontend services.
	 *
	 * Only boots if NOT in admin context (frontend only).
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Only boot on frontend (not in admin)
		if ( $this->is_admin_context() ) {
			return;
		}

		// Boot renderers first (they register hooks)
		$this->boot_service( $container, MetaTagRenderer::class, 'warning', 'Failed to register MetaTagRenderer' );
		$this->boot_service( $container, SchemaRenderer::class, 'warning', 'Failed to register SchemaRenderer' );
		$this->boot_service( $container, SocialRenderer::class, 'warning', 'Failed to register SocialRenderer' );
		$this->boot_service( $container, KeywordsRenderer::class, 'warning', 'Failed to register KeywordsRenderer' );
		$this->boot_service( $container, GeoShortcodes::class, 'warning', 'Failed to register GeoShortcodes' );

		// Boot other frontend services
		$this->boot_services_simple(
			$container,
			array(
				ImprovedSocialMediaManager::class,
				InternalLinkManager::class,
				MultipleKeywordsManager::class,
				AdvancedSchemaManager::class,
			),
			'warning',
			'Failed to register'
		);
	}
}
