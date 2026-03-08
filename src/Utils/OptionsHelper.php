<?php
/**
 * Options helper for backward compatibility and easy access to OptionsInterface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Infrastructure\Plugin;

/**
 * Helper class to access OptionsInterface from container.
 * 
 * This provides a bridge between static Options:: methods and dependency injection.
 * Use this when you cannot inject OptionsInterface directly.
 */
class OptionsHelper {

	/**
	 * Get OptionsInterface instance from container.
	 *
	 * @return OptionsInterface|null Options instance or null if not available.
	 */
	public static function get_options(): ?OptionsInterface {
		try {
			$container = Plugin::instance()->get_container();
			return $container->get( OptionsInterface::class );
		} catch ( \Throwable $e ) {
			// Fallback to static Options if container not available
			return null;
		}
	}

	/**
	 * Get all options.
	 *
	 * @return array<string, mixed> All options.
	 */
	public static function get(): array {
		$options = self::get_options();
		if ( $options ) {
			return $options->get();
		}
		// Fallback: read option directly to avoid @deprecated notice from Options::get()
		$raw = get_option( Options::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Get a specific option value.
	 *
	 * @param string $key     Option key (supports dot notation, e.g., 'ai.openai_api_key').
	 * @param mixed  $default Default value if option not found.
	 * @return mixed Option value or default.
	 */
	public static function get_option( string $key, $default = null ) {
		$options = self::get_options();
		if ( $options ) {
			return $options->get_option( $key, $default );
		}
		// Fallback: read option directly to avoid @deprecated notice from Options::get_option()
		$all = get_option( Options::OPTION_KEY, array() );
		if ( ! is_array( $all ) ) {
			return $default;
		}
		// Support dot notation (e.g. 'ai.openai_api_key')
		$keys  = explode( '.', $key );
		$value = $all;
		foreach ( $keys as $k ) {
			if ( ! is_array( $value ) || ! array_key_exists( $k, $value ) ) {
				return $default;
			}
			$value = $value[ $k ];
		}
		return $value;
	}

	/**
	 * Get capability required for plugin access.
	 *
	 * @return string Capability name.
	 */
	public static function get_capability(): string {
		$options = self::get_options();
		if ( $options ) {
			return $options->get_capability();
		}
		// Fallback: read directly to avoid @deprecated notice from Options::get_capability()
		$all = get_option( Options::OPTION_KEY, array() );
		return ( is_array( $all ) && ! empty( $all['advanced']['capability'] ) )
			? (string) $all['advanced']['capability']
			: 'manage_options';
	}

	/**
	 * Get default options structure.
	 *
	 * @return array<string, mixed> Default options.
	 */
	public static function get_defaults(): array {
		$options = self::get_options();
		if ( $options ) {
			return $options->get_defaults();
		}
		// Fallback: return empty array to avoid @deprecated notice from Options::get_defaults()
		return array();
	}
}


