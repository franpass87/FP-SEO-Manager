<?php
/**
 * WordPress sanitization service implementation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Sanitization;

use function esc_html;
use function esc_url;
use function esc_url_raw;
use function sanitize_email;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function wp_kses_post;
use function wp_strip_all_tags;

/**
 * WordPress-based sanitization service.
 */
class WordPressSanitizationService implements SanitizationServiceInterface {

	/**
	 * Registered custom sanitizers.
	 *
	 * @var array<string, callable>
	 */
	private array $custom_sanitizers = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register default WordPress sanitization functions
	}

	/**
	 * Sanitize a value.
	 *
	 * @param mixed  $value  Value to sanitize.
	 * @param string $type   Sanitization type (text, email, url, etc.).
	 * @param array<string, mixed> $args Optional sanitization arguments.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value, string $type = 'text', array $args = array() ) {
		// Check custom sanitizers first
		if ( isset( $this->custom_sanitizers[ $type ] ) ) {
			return call_user_func( $this->custom_sanitizers[ $type ], $value, $args );
		}

		// Built-in sanitizers
		switch ( $type ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'email':
				return sanitize_email( (string) $value );

			case 'url':
				return esc_url_raw( (string) $value );

			case 'html':
				return wp_kses_post( (string) $value );

			case 'strip_tags':
				return wp_strip_all_tags( (string) $value );

			case 'esc_html':
				return esc_html( (string) $value );

			case 'integer':
				return (int) $value;

			case 'float':
				return (float) $value;

			case 'boolean':
				return (bool) $value;

			case 'array':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$item_type = $args['item_type'] ?? 'text';
				return array_map(
					function ( $item ) use ( $item_type, $args ) {
						return $this->sanitize( $item, $item_type, $args );
					},
					$value
				);

			case 'key':
				return sanitize_key( (string) $value );

			case 'slug':
				return sanitize_title( (string) $value );

		default:
			return is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}

	/**
	 * Sanitize multiple values.
	 *
	 * @param array<string, mixed> $data Data to sanitize (key => value pairs).
	 * @param array<string, string> $types Sanitization types (key => type).
	 * @return array<string, mixed> Sanitized data.
	 */
	public function sanitize_many( array $data, array $types = array() ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$type = $types[ $key ] ?? 'text';
			$sanitized[ $key ] = $this->sanitize( $value, $type );
		}

		return $sanitized;
	}

	/**
	 * Register a custom sanitization function.
	 *
	 * @param string   $name     Sanitization type name.
	 * @param callable $callback Sanitization callback.
	 * @return void
	 */
	public function register_sanitizer( string $name, callable $callback ): void {
		$this->custom_sanitizers[ $name ] = $callback;
	}
}

