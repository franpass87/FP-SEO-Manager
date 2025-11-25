<?php
/**
 * Schema Metabox service provider.
 *
 * Registers schema markup metaboxes for WordPress editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Editor\SchemaMetaboxes;

/**
 * Schema Metabox service provider.
 *
 * Registers schema markup metaboxes for structured data.
 * This provider should be registered before MainMetaboxServiceProvider.
 */
class SchemaMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return SchemaMetaboxes::class;
	}

	/**
	 * Register schema metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register schema metaboxes as singleton
		$container->singleton( SchemaMetaboxes::class );
	}

	/**
	 * Get the error message prefix for booting failures.
	 *
	 * @return string Error message prefix.
	 */
	protected function get_boot_error_message(): string {
		return 'Failed to register SchemaMetaboxes';
	}
}

