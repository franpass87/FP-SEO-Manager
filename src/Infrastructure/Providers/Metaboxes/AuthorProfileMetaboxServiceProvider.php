<?php
/**
 * Author Profile Metabox service provider.
 *
 * Registers author profile fields for Authority Signals.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Admin\AuthorProfileFields;

/**
 * Author Profile Metabox service provider.
 *
 * Registers author profile fields (not a traditional metabox,
 * but fields added to user profile pages).
 */
class AuthorProfileMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\CoreServiceProvider::class,
		);
	}

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return AuthorProfileFields::class;
	}

	/**
	 * Register author profile fields service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register author profile fields with HookManager dependency
		$container->singleton( AuthorProfileFields::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new AuthorProfileFields( $hook_manager );
		} );
	}
}





