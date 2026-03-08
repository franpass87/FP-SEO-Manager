<?php
/**
 * Options manager implementation.
 *
 * @package FP\SEO\Core\Services\Options
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Options;

use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Infrastructure\Contracts\CacheInterface;
use FP\SEO\Utils\Options as LegacyOptions;
use FP\SEO\Utils\Cache;

/**
 * Options manager implementation.
 *
 * Implements OptionsInterface without triggering deprecated notices from LegacyOptions.
 * Reads/writes directly from the WordPress options table using the same key as LegacyOptions.
 */
class OptionsManager implements OptionsInterface {

	/**
	 * Cache instance.
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Constructor.
	 *
	 * @param CacheInterface $cache Cache instance.
	 */
	public function __construct( CacheInterface $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Get all options with defaults merged.
	 *
	 * @return array<string, mixed> Options array.
	 */
	public function get(): array {
		return Cache::remember(
			'options_data',
			function (): array {
				wp_cache_delete( LegacyOptions::OPTION_KEY, 'options' );
				wp_cache_delete( 'alloptions', 'options' );

				$stored = get_option( LegacyOptions::OPTION_KEY, array() );

				if ( empty( $stored ) || ! is_array( $stored ) ) {
					global $wpdb;
					$db_value = $wpdb->get_var( $wpdb->prepare(
						"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
						LegacyOptions::OPTION_KEY
					) );
					if ( null !== $db_value ) {
						$unserialized = maybe_unserialize( $db_value );
						$stored       = is_array( $unserialized ) ? $unserialized : array();
					}
				}

				if ( ! is_array( $stored ) ) {
					$stored = array();
				}

				return $this->merge_defaults( $this->sanitize( $stored ) );
			},
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Get a specific option value by key path (dot notation supported).
	 *
	 * @param string $key     Option key path (e.g. 'ai.openai_api_key').
	 * @param mixed  $default Default value if not found.
	 * @return mixed Option value or default.
	 */
	public function get_option( string $key, $default = null ) {
		$options = $this->get();
		$keys    = explode( '.', $key );
		$value   = $options;

		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}

		return $value;
	}

	/**
	 * Update options (merges with existing, then sanitizes).
	 *
	 * @param array<string, mixed> $value New option values.
	 * @return void
	 */
	public function update( array $value ): void {
		Cache::delete( 'options_data' );

		$existing = get_option( LegacyOptions::OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$merged    = array_replace_recursive( $existing, $value );
		$sanitized = $this->sanitize( $merged );

		update_option( LegacyOptions::OPTION_KEY, $sanitized );

		Cache::delete( 'options_data' );
	}

	/**
	 * Sanitize option values.
	 *
	 * Delegates to LegacyOptions internal sanitization logic without triggering
	 * the deprecated notice (the notice is meant for external callers, not for
	 * the canonical OptionsInterface implementation).
	 *
	 * @param array<string, mixed>|null $input Raw option values.
	 * @return array<string, mixed> Sanitized options.
	 */
	public function sanitize( ?array $input ): array {
		// LegacyOptions::sanitize contains the full sanitization logic.
		// We suppress the deprecated notice here because this class IS the
		// canonical replacement — the notice targets external static callers.
		set_error_handler( static function () {
			return true; // Silence E_USER_DEPRECATED from _deprecated_function
		}, E_USER_DEPRECATED );

		try {
			$result = LegacyOptions::sanitize( $input );
		} finally {
			restore_error_handler();
		}

		return $result;
	}

	/**
	 * Get default options structure.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public function get_defaults(): array {
		set_error_handler( static function () {
			return true;
		}, E_USER_DEPRECATED );

		try {
			$result = LegacyOptions::get_defaults();
		} finally {
			restore_error_handler();
		}

		return $result;
	}

	/**
	 * Merge values with defaults.
	 *
	 * @param array<string, mixed> $value Option values.
	 * @return array<string, mixed> Merged options with defaults.
	 */
	public function merge_defaults( array $value ): array {
		set_error_handler( static function () {
			return true;
		}, E_USER_DEPRECATED );

		try {
			$result = LegacyOptions::merge_defaults( $value );
		} finally {
			restore_error_handler();
		}

		return $result;
	}

	/**
	 * Get capability required to manage options.
	 *
	 * @return string Capability name.
	 */
	public function get_capability(): string {
		$options = $this->get();
		return $options['advanced']['capability'] ?? 'manage_options';
	}
}















