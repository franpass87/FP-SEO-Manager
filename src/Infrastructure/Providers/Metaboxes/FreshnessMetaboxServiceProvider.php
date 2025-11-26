<?php
/**
 * Freshness Metabox service provider.
 *
 * Registers the Freshness & Temporal Signals metabox for WordPress editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Admin\FreshnessMetaBox;

/**
 * Freshness Metabox service provider.
 *
 * Registers the Freshness & Temporal Signals metabox.
 */
class FreshnessMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return FreshnessMetaBox::class;
	}

	/**
	 * Register freshness metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register freshness metabox as singleton
		$container->singleton( FreshnessMetaBox::class );
	}
}





