<?php
/**
 * Options interface.
 *
 * @package FP\SEO\Infrastructure\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Contracts;

/**
 * Interface for options management.
 */
interface OptionsInterface {
	/**
	 * Get all options with defaults merged.
	 *
	 * @return array<string, mixed> Options array.
	 */
	public function get(): array;

	/**
	 * Get a specific option value by key path.
	 *
	 * Supports dot notation for nested keys (e.g., 'general.language').
	 *
	 * @param string $key     Option key path.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Option value or default.
	 */
	public function get_option( string $key, $default = null );

	/**
	 * Update options.
	 *
	 * @param array<string, mixed> $value New option values (will be merged with existing).
	 * @return void
	 */
	public function update( array $value ): void;

	/**
	 * Sanitize option values.
	 *
	 * @param array<string, mixed>|null $input Raw option values.
	 * @return array<string, mixed> Sanitized options.
	 */
	public function sanitize( ?array $input ): array;

	/**
	 * Get default options structure.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public function get_defaults(): array;

	/**
	 * Merge values with defaults.
	 *
	 * @param array<string, mixed> $value Option values.
	 * @return array<string, mixed> Merged options with defaults.
	 */
	public function merge_defaults( array $value ): array;

	/**
	 * Get capability required to manage options.
	 *
	 * @return string Capability name.
	 */
	public function get_capability(): string;
}















