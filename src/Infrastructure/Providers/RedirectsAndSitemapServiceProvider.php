<?php
/**
 * Redirects and Sitemap service provider.
 *
 * Registers redirect manager (301/302), HTML sitemap, and related services.
 * Loaded unconditionally - these features work independently of GEO.
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
use FP\SEO\Redirects\RedirectRepository;
use FP\SEO\Redirects\RedirectHandler;
use FP\SEO\Redirects\SitemapRouter;

/**
 * Service provider for redirects and HTML sitemap.
 */
class RedirectsAndSitemapServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;

	/**
	 * Get dependencies.
	 *
	 * @return array<class-string<\FP\SEO\Infrastructure\ServiceProviderInterface>>
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			DataServiceProvider::class,
		);
	}

	/**
	 * Register services.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( RedirectRepository::class, function( Container $container ) {
			global $wpdb;
			return new RedirectRepository( $wpdb );
		} );

		$container->singleton( RedirectHandler::class, function( Container $container ) {
			return new RedirectHandler( $container->get( RedirectRepository::class ) );
		} );

		$container->singleton( SitemapRouter::class, function() {
			return new SitemapRouter();
		} );
	}

	/**
	 * Boot services.
	 *
	 * @param Container $container Container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->boot_service( $container, RedirectHandler::class, 'warning', 'Failed to register RedirectHandler' );
		$this->boot_service( $container, SitemapRouter::class, 'warning', 'Failed to register SitemapRouter' );
	}

	/**
	 * Activation: flush rewrite rules (migration runs via DataServiceProvider).
	 */
	public function activate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: flush rewrite rules.
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}
}
