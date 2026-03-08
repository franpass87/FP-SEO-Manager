<?php
/**
 * Integration interface - contract for external integrations.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations\Contracts;

/**
 * Interface for external integrations.
 *
 * All integrations should implement this interface for consistency.
 */
interface IntegrationInterface {

	/**
	 * Check if the integration is available/configured.
	 *
	 * @return bool True if integration is available.
	 */
	public function is_available(): bool;

	/**
	 * Register the integration.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Get the integration name.
	 *
	 * @return string Integration name.
	 */
	public function get_name(): string;

	/**
	 * Get the integration version.
	 *
	 * @return string Integration version.
	 */
	public function get_version(): string;
}










