<?php
/**
 * Abstract renderer base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Frontend\Contracts\RendererInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Abstract base class for frontend renderers.
 *
 * Provides common functionality for all renderers.
 */
abstract class AbstractRenderer implements RendererInterface {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	protected HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks for the renderer.
	 *
	 * Default implementation does nothing.
	 * Subclasses should override to register their hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Default: no hooks to register.
		// Subclasses should override.
	}

	/**
	 * Check if the renderer should render for the current context.
	 *
	 * Default implementation returns true.
	 * Subclasses should override for context-specific logic.
	 *
	 * @param mixed $context Rendering context.
	 * @return bool True if should render, false otherwise.
	 */
	public function should_render( $context = null ): bool {
		return true;
	}

	/**
	 * Register a WordPress action hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	protected function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->hook_manager->add_action( $hook, $callback, $priority, $args );
	}

	/**
	 * Register a WordPress filter hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	protected function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->hook_manager->add_filter( $hook, $callback, $priority, $args );
	}
}



