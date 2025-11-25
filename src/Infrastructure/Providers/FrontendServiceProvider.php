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
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Front\MetaTagRenderer;
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

	/**
	 * Register frontend services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register all frontend services as singletons
		$this->register_singletons( $container, array(
			MetaTagRenderer::class,
			ImprovedSocialMediaManager::class,
			InternalLinkManager::class,
			MultipleKeywordsManager::class,
			AdvancedSchemaManager::class,
		) );
	}

	/**
	 * Boot frontend services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Boot all frontend services
		$this->boot_services_simple(
			$container,
			array(
				ImprovedSocialMediaManager::class,
				InternalLinkManager::class,
				MultipleKeywordsManager::class,
				MetaTagRenderer::class,
				AdvancedSchemaManager::class,
			),
			'warning',
			'Failed to register'
		);
	}
}
