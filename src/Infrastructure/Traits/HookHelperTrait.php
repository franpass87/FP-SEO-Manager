<?php
/**
 * Hook helper trait.
 *
 * Provides helper methods for WordPress hooks management in service providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Traits;

use FP\SEO\Infrastructure\Container;

/**
 * Trait for service providers to manage WordPress hooks easily.
 */
trait HookHelperTrait {

	/**
	 * Defer service boot to admin_init hook.
	 *
	 * @param Container $container The container instance.
	 * @param callable  $callback The callback to execute on admin_init.
	 * @param int       $priority The hook priority.
	 * @return void
	 */
	protected function defer_to_admin_init(
		Container $container,
		callable $callback,
		int $priority = 20
	): void {
		add_action( 'admin_init', function() use ( $container, $callback ) {
			if ( ! is_admin() ) {
				return;
			}
			$callback( $container );
		}, $priority );
	}

	/**
	 * Defer service boot to init hook.
	 *
	 * @param Container $container The container instance.
	 * @param callable  $callback The callback to execute on init.
	 * @param int       $priority The hook priority.
	 * @return void
	 */
	protected function defer_to_init(
		Container $container,
		callable $callback,
		int $priority = 10
	): void {
		add_action( 'init', function() use ( $container, $callback ) {
			$callback( $container );
		}, $priority );
	}

	/**
	 * Boot service on admin_init with capability check.
	 *
	 * @param Container $container The container instance.
	 * @param callable  $callback The callback to execute.
	 * @param string    $capability The required capability (default: 'manage_options').
	 * @param int       $priority The hook priority.
	 * @return void
	 */
	protected function boot_on_admin_init_with_capability(
		Container $container,
		callable $callback,
		string $capability = 'manage_options',
		int $priority = 20
	): void {
		add_action( 'admin_init', function() use ( $container, $callback, $capability ) {
			if ( ! is_admin() || ! current_user_can( $capability ) ) {
				return;
			}
			$callback( $container );
		}, $priority );
	}
}




