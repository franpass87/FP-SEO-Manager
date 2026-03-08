<?php
/**
 * Renderer interface - contract for frontend renderers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Contracts;

/**
 * Interface for frontend renderers.
 *
 * All frontend renderers should implement this interface.
 */
interface RendererInterface {

	/**
	 * Register hooks for the renderer.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Render the output.
	 *
	 * @param mixed $context Rendering context (post, term, etc.).
	 * @return string Rendered output.
	 */
	public function render( $context = null ): string;

	/**
	 * Check if the renderer should render for the current context.
	 *
	 * @param mixed $context Rendering context.
	 * @return bool True if should render, false otherwise.
	 */
	public function should_render( $context = null ): bool;
}



