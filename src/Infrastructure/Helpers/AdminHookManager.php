<?php
/**
 * Admin hook manager.
 *
 * Manages WordPress hook registration for admin service providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Helpers;

/**
 * Manages WordPress hook registration for admin service providers.
 */
class AdminHookManager {

	/**
	 * Track hook registrations per class to prevent duplicate registrations.
	 *
	 * @var array<string, bool>
	 */
	private static array $registered_hooks = array();

	/**
	 * Track booted state for each provider instance.
	 *
	 * @var array<string, bool>
	 */
	private static array $booted_states = array();

	/**
	 * Register boot callback on appropriate admin hooks.
	 *
	 * @param string   $class_name The provider class name.
	 * @param callable $boot_callback The callback to execute when booting.
	 * @return void
	 */
	public static function register_boot_hooks( string $class_name, callable $boot_callback ): void {
		// Validate class name is not empty
		if ( empty( $class_name ) ) {
			return;
		}

		// Validate boot callback is callable
		if ( ! is_callable( $boot_callback ) ) {
			return;
		}

		// Check if hooks are already registered for this class
		if ( self::are_hooks_registered( $class_name ) ) {
			return;
		}

		// Validate WordPress functions are available
		if ( ! function_exists( 'did_action' ) || ! function_exists( 'add_action' ) ) {
			return;
		}

		// Check if we're already past the admin hooks
		$admin_init_fired = did_action( 'admin_init' );
		$admin_menu_fired = did_action( 'admin_menu' );

		// If both main hooks fired, no point in registering them
		// Mark as registered to prevent future attempts
		if ( $admin_init_fired && $admin_menu_fired ) {
			self::$registered_hooks[ $class_name ] = true;
			return;
		}

		// Mark hooks as registered BEFORE registering to prevent race conditions
		self::$registered_hooks[ $class_name ] = true;

		// Hook 1: admin_init (standard WordPress admin initialization)
		if ( ! $admin_init_fired ) {
			add_action( 'admin_init', $boot_callback, 1 );
		}

		// Hook 2: admin_menu (early in admin page load, before add_meta_boxes)
		if ( ! $admin_menu_fired ) {
			add_action( 'admin_menu', $boot_callback, 1 );
		}

		// Hook 3: load-post.php (specific to post edit pages, very early)
		if ( ! did_action( 'load-post.php' ) ) {
			add_action( 'load-post.php', $boot_callback, 1 );
		}

		// Hook 4: load-post-new.php (for new post pages)
		if ( ! did_action( 'load-post-new.php' ) ) {
			add_action( 'load-post-new.php', $boot_callback, 1 );
		}
	}

	/**
	 * Check if provider is already booted.
	 *
	 * @param string $class_name The provider class name.
	 * @return bool True if already booted.
	 */
	public static function is_booted( string $class_name ): bool {
		if ( empty( $class_name ) ) {
			return false;
		}
		return isset( self::$booted_states[ $class_name ] );
	}

	/**
	 * Mark provider as booted.
	 *
	 * @param string $class_name The provider class name.
	 * @return void
	 */
	public static function mark_booted( string $class_name ): void {
		if ( empty( $class_name ) ) {
			return;
		}
		self::$booted_states[ $class_name ] = true;
	}

	/**
	 * Check if hooks are already registered for a class.
	 *
	 * @param string $class_name The provider class name.
	 * @return bool True if hooks are registered.
	 */
	public static function are_hooks_registered( string $class_name ): bool {
		if ( empty( $class_name ) ) {
			return false;
		}
		return isset( self::$registered_hooks[ $class_name ] );
	}
}

