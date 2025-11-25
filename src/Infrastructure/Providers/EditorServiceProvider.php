<?php
/**
 * Editor service provider.
 *
 * Orchestrates editor-related service providers.
 * This provider now delegates to specialized metabox providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;

/**
 * Editor service provider.
 *
 * This provider is kept for backward compatibility and to maintain
 * the provider registration order. It now delegates to specialized
 * metabox providers registered in Plugin.php.
 *
 * @deprecated The actual metabox registration is now handled by:
 * - Metaboxes\SchemaMetaboxServiceProvider
 * - Metaboxes\MainMetaboxServiceProvider
 * - Metaboxes\QAMetaboxServiceProvider
 * - Metaboxes\FreshnessMetaboxServiceProvider
 * - Metaboxes\AuthorProfileMetaboxServiceProvider
 */
class EditorServiceProvider extends AbstractServiceProvider {

	/**
	 * Register editor services in the container.
	 *
	 * This method is kept empty as registration is now handled by
	 * specialized metabox providers.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Registration is now handled by specialized metabox providers
		// This provider is kept for backward compatibility
	}

	/**
	 * Boot editor services.
	 *
	 * This method is kept empty as booting is now handled by
	 * specialized metabox providers.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Booting is now handled by specialized metabox providers
		// This provider is kept for backward compatibility
	}
}
