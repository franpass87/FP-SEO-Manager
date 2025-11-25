<?php
/**
 * Abstract Metabox service provider.
 *
 * Base class for metabox service providers that provides common functionality.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Providers\Admin\AbstractAdminServiceProvider;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;

/**
 * Abstract base class for metabox service providers.
 *
 * Provides common functionality for all metabox providers.
 * All metabox providers should extend this class for consistency.
 */
abstract class AbstractMetaboxServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	abstract protected function get_metabox_class(): string;

	/**
	 * Get the log level for booting this metabox.
	 *
	 * Override in subclasses to customize error handling.
	 *
	 * @return string Log level ('debug', 'warning', 'error').
	 */
	protected function get_boot_log_level(): string {
		return 'warning';
	}

	/**
	 * Get the error message prefix for booting failures.
	 *
	 * Override in subclasses to customize error messages.
	 *
	 * @return string Error message prefix.
	 */
	protected function get_boot_error_message(): string {
		return sprintf( 'Failed to register %s', $this->get_metabox_class() );
	}

	/**
	 * Boot metabox service.
	 *
	 * Automatically uses the metabox class from get_metabox_class().
	 *
	 * @param \FP\SEO\Infrastructure\Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( \FP\SEO\Infrastructure\Container $container ): void {
		$this->boot_service(
			$container,
			$this->get_metabox_class(),
			$this->get_boot_log_level(),
			$this->get_boot_error_message()
		);
	}
}


