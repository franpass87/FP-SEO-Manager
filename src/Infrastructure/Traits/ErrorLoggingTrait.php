<?php
/**
 * Error logging trait.
 *
 * Provides helper methods for error logging in service providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Traits;

use FP\SEO\Infrastructure\Helpers\ErrorLoggingHelper;

/**
 * Trait for service providers to log errors easily.
 */
trait ErrorLoggingTrait {

	/**
	 * Log an error for this provider.
	 *
	 * @param string     $action The action that failed (e.g., 'register', 'boot', 'activate').
	 * @param \Throwable $exception The exception that occurred.
	 * @return void
	 */
	protected function log_error( string $action, \Throwable $exception ): void {
		ErrorLoggingHelper::log_provider_error( $this, $action, $exception );
	}

	/**
	 * Get the class name of this provider with fallback for edge cases.
	 *
	 * @return string The provider class name or fallback identifier.
	 */
	protected function get_provider_class_name(): string {
		return ErrorLoggingHelper::get_provider_class_name( $this );
	}
}

