<?php
/**
 * Centralized hook management.
 *
 * Provides validation, tracking, and utilities for WordPress hook registration.
 *
 * @package FP\SEO\Infrastructure\Bootstrap
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Bootstrap;

use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\LoggerHelper;

/**
 * Centralized hook manager for WordPress hooks.
 *
 * Tracks hook registrations, prevents duplicates, and provides utilities.
 */
class HookManager implements HookManagerInterface {

	/**
	 * Registered hooks tracking.
	 *
	 * Structure: [hook_name][priority][] = {callback, priority, args}
	 * Multiple callbacks can share the same hook+priority.
	 *
	 * @var array<string, array<int, list<array{callback: callable, priority: int, args: int}>>>
	 */
	private array $registered_hooks = array();

	/**
	 * Hook priorities constants.
	 */
	public const HOOK_PRIORITY_EARLY   = 5;
	public const HOOK_PRIORITY_DEFAULT = 10;
	public const HOOK_PRIORITY_LATE    = 20;
	/**
	 * Enable verbose per-hook debug logs only when explicitly requested.
	 */
	private const VERBOSE_DEBUG_FLAG = 'FP_SEO_VERBOSE_HOOK_DEBUG';

	/**
	 * Add a WordPress action hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 */
	public function add_action( string $hook, callable $callback, int $priority = self::HOOK_PRIORITY_DEFAULT, int $args = 1 ): void {
		// Validate hook name
		if ( empty( $hook ) ) {
			LoggerHelper::warning( 'HookManager: Attempted to register action with empty hook name' );
			return;
		}

		// Check for duplicate registration
		if ( $this->is_registered( $hook, $callback, $priority ) ) {
			if ( $this->is_verbose_debug_enabled() ) {
				LoggerHelper::debug( 'HookManager: Hook already registered, skipping', array(
					'hook'     => $hook,
					'priority' => $priority,
				) );
			}
			return;
		}

		// Register the hook
		add_action( $hook, $callback, $priority, $args );

		// Track the registration
		$this->track_registration( $hook, $callback, $priority, $args );

		if ( $this->is_verbose_debug_enabled() ) {
			LoggerHelper::debug( 'HookManager: Action registered', array(
				'hook'     => $hook,
				'priority' => $priority,
				'args'     => $args,
			) );
		}
	}

	/**
	 * Add a WordPress filter hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 */
	public function add_filter( string $hook, callable $callback, int $priority = self::HOOK_PRIORITY_DEFAULT, int $args = 1 ): void {
		// Validate hook name
		if ( empty( $hook ) ) {
			LoggerHelper::warning( 'HookManager: Attempted to register filter with empty hook name' );
			return;
		}

		// Check for duplicate registration
		if ( $this->is_registered( $hook, $callback, $priority ) ) {
			if ( $this->is_verbose_debug_enabled() ) {
				LoggerHelper::debug( 'HookManager: Hook already registered, skipping', array(
					'hook'     => $hook,
					'priority' => $priority,
				) );
			}
			return;
		}

		// Register the hook
		add_filter( $hook, $callback, $priority, $args );

		// Track the registration
		$this->track_registration( $hook, $callback, $priority, $args );

		if ( $this->is_verbose_debug_enabled() ) {
			LoggerHelper::debug( 'HookManager: Filter registered', array(
				'hook'     => $hook,
				'priority' => $priority,
				'args'     => $args,
			) );
		}
	}

	/**
	 * Remove all hooks for a given hook name.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	public function remove_all( string $hook ): void {
		if ( empty( $hook ) ) {
			return;
		}

		remove_all_actions( $hook );
		remove_all_filters( $hook );

		// Clear tracking
		if ( isset( $this->registered_hooks[ $hook ] ) ) {
			unset( $this->registered_hooks[ $hook ] );
		}

		if ( $this->is_verbose_debug_enabled() ) {
			LoggerHelper::debug( 'HookManager: Removed all hooks', array( 'hook' => $hook ) );
		}
	}

	/**
	 * Get all registered hooks for a given hook name.
	 *
	 * @param string $hook Hook name.
	 * @return array<int, list<array{callback: callable, priority: int, args: int}>>
	 */
	public function get_registered( string $hook ): array {
		return $this->registered_hooks[ $hook ] ?? array();
	}

	/**
	 * Get all registered hooks.
	 *
	 * @return array<string, array<int, list<array{callback: callable, priority: int, args: int}>>>
	 */
	public function get_all_registered(): array {
		return $this->registered_hooks;
	}

	/**
	 * Check if a hook+callback+priority combination is already tracked.
	 *
	 * Supports multiple different callbacks at the same priority.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @return bool
	 */
	private function is_registered( string $hook, callable $callback, int $priority ): bool {
		if ( ! isset( $this->registered_hooks[ $hook ][ $priority ] ) ) {
			return false;
		}

		foreach ( $this->registered_hooks[ $hook ][ $priority ] as $entry ) {
			if ( $this->callbacks_match( $entry['callback'], $callback ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Track a hook registration.
	 *
	 * Appends to the list of callbacks at the given priority so that
	 * multiple different callbacks at the same priority are all tracked.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	private function track_registration( string $hook, callable $callback, int $priority, int $args ): void {
		if ( ! isset( $this->registered_hooks[ $hook ] ) ) {
			$this->registered_hooks[ $hook ] = array();
		}

		if ( ! isset( $this->registered_hooks[ $hook ][ $priority ] ) ) {
			$this->registered_hooks[ $hook ][ $priority ] = array();
		}

		$this->registered_hooks[ $hook ][ $priority ][] = array(
			'callback' => $callback,
			'priority' => $priority,
			'args'     => $args,
		);
	}

	/**
	 * Check if two callbacks match.
	 *
	 * Closures are never considered duplicates because they are unique instances.
	 *
	 * @param callable $callback1 First callback.
	 * @param callable $callback2 Second callback.
	 * @return bool
	 */
	private function callbacks_match( callable $callback1, callable $callback2 ): bool {
		// Closures are always unique — never block them
		if ( $callback1 instanceof \Closure || $callback2 instanceof \Closure ) {
			return false;
		}

		// [object, method] or [class, method] arrays
		if ( is_array( $callback1 ) && is_array( $callback2 ) ) {
			return $callback1[0] === $callback2[0] && $callback1[1] === $callback2[1];
		}

		// Plain function names
		if ( is_string( $callback1 ) && is_string( $callback2 ) ) {
			return $callback1 === $callback2;
		}

		return false;
	}

	/**
	 * Check if verbose hook-level debug logging is enabled.
	 *
	 * @return bool
	 */
	private function is_verbose_debug_enabled(): bool {
		return defined( self::VERBOSE_DEBUG_FLAG ) && constant( self::VERBOSE_DEBUG_FLAG ) === true;
	}
}















