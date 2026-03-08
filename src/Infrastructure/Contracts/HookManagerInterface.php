<?php
/**
 * Hook manager interface.
 *
 * @package FP\SEO\Infrastructure\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Contracts;

/**
 * Interface for hook management.
 */
interface HookManagerInterface {
	/**
	 * Add a WordPress action hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void;

	/**
	 * Add a WordPress filter hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority (default 10).
	 * @param int      $args     Number of arguments (default 1).
	 * @return void
	 */
	public function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void;

	/**
	 * Remove all hooks for a given hook name.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	public function remove_all( string $hook ): void;

	/**
	 * Get all registered hooks for a given hook name.
	 *
	 * @param string $hook Hook name.
	 * @return array<int, array{callback: callable, priority: int, args: int}>
	 */
	public function get_registered( string $hook ): array;
}















