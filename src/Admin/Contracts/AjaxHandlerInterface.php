<?php
/**
 * AJAX handler interface - contract for AJAX handlers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Contracts;

/**
 * Interface for AJAX handlers.
 *
 * All AJAX handlers should implement this interface for consistency.
 */
interface AjaxHandlerInterface {

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Handle the AJAX request.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed> Response data.
	 */
	public function handle( array $data ): array;

	/**
	 * Get the AJAX action name.
	 *
	 * @return string Action name.
	 */
	public function get_action(): string;

	/**
	 * Get the nonce action name.
	 *
	 * @return string Nonce action name.
	 */
	public function get_nonce_action(): string;

	/**
	 * Get the capability required to use this handler.
	 *
	 * @return string Capability name.
	 */
	public function get_capability(): string;
}



