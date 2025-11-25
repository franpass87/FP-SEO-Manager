<?php
/**
 * Abstract admin service provider.
 *
 * Base class for admin-only service providers that automatically
 * handles admin context checks in register() and boot() methods.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Admin;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Infrastructure\Helpers\AdminHookManager;
use FP\SEO\Infrastructure\Helpers\ErrorLoggingHelper;

/**
 * Abstract base class for admin-only service providers.
 *
 * Automatically checks admin context before registering or booting services.
 * Subclasses only need to implement register_admin() and boot_admin() methods.
 */
abstract class AbstractAdminServiceProvider extends AbstractServiceProvider {

	use ConditionalServiceTrait;

	/**
	 * Register admin services in the container.
	 *
	 * Always registers services in container (for lazy loading).
	 * Admin context check happens during boot, not registration.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	final public function register( Container $container ): void {
		// Always register in container - boot will check admin context
		// This ensures services are available even if is_admin() is not reliable during plugins_loaded
		$this->register_admin( $container );
	}

	/**
	 * Boot admin services.
	 *
	 * Automatically checks admin context before delegating to boot_admin().
	 * Uses multiple hooks to ensure boot happens at the right time:
	 * - Immediately if already in admin context during plugins_loaded
	 * - On admin_init if not in admin context yet
	 * - Also hooks into admin_menu early to catch edge cases
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	final public function boot( Container $container ): void {
		// Get class name with fallback for edge cases
		// ErrorLoggingHelper::get_provider_class_name() always returns a non-empty string
		$class_name = ErrorLoggingHelper::get_provider_class_name( $this );
		
		// Check if already booted
		if ( AdminHookManager::is_booted( $class_name ) ) {
			return;
		}

		// Check if hooks are already registered for this class
		$hooks_already_registered = AdminHookManager::are_hooks_registered( $class_name );
		
		// Try to boot immediately if we're already in admin context
		// No need to register hooks since we can boot now
		if ( $this->is_admin_context() ) {
			try {
				$this->boot_admin( $container );
				AdminHookManager::mark_booted( $class_name );
			} catch ( \Throwable $e ) {
				// Log error but don't set booted state, allowing retry
				ErrorLoggingHelper::log_provider_error( $this, 'boot (immediate)', $e );
				// Re-throw to allow error propagation
				throw $e;
			}
			return;
		}

		// If hooks are already registered, nothing more to do (we're not in admin context)
		if ( $hooks_already_registered ) {
			return;
		}

		// Store reference to this instance for closures
		$provider = $this;

		// Define boot callback once
		$boot_callback = function() use ( $container, $provider, $class_name ) {
			// Check if not already booted and in admin context
			if ( ! AdminHookManager::is_booted( $class_name ) && $provider->is_admin_context() ) {
				try {
					$provider->boot_admin( $container );
					AdminHookManager::mark_booted( $class_name );
				} catch ( \Throwable $e ) {
					// Log error but don't set booted state, allowing retry on next hook
					// This prevents one failed boot from blocking future attempts
					ErrorLoggingHelper::log_provider_error( $provider, 'boot', $e );
					// Re-throw to allow WordPress error handling
					throw $e;
				}
			}
		};

		// Register boot hooks using the hook manager
		AdminHookManager::register_boot_hooks( $class_name, $boot_callback );
	}

	/**
	 * Register admin services in the container.
	 *
	 * Override this method to register admin services.
	 * This method is only called when in admin context.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	abstract protected function register_admin( Container $container ): void;

	/**
	 * Boot admin services.
	 *
	 * Override this method to boot admin services.
	 * This method is only called when in admin context.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// Default: no boot actions needed.
	}
}

