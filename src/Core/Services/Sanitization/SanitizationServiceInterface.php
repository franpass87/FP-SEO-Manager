<?php
/**
 * Sanitization service interface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Sanitization;

/**
 * Interface for sanitization service.
 */
interface SanitizationServiceInterface {

	/**
	 * Sanitize a value.
	 *
	 * @param mixed  $value  Value to sanitize.
	 * @param string $type   Sanitization type (text, email, url, etc.).
	 * @param array<string, mixed> $args Optional sanitization arguments.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( $value, string $type = 'text', array $args = array() );

	/**
	 * Sanitize multiple values.
	 *
	 * @param array<string, mixed> $data Data to sanitize (key => value pairs).
	 * @param array<string, string> $types Sanitization types (key => type).
	 * @return array<string, mixed> Sanitized data.
	 */
	public function sanitize_many( array $data, array $types = array() ): array;

	/**
	 * Register a custom sanitization function.
	 *
	 * @param string   $name     Sanitization type name.
	 * @param callable $callback Sanitization callback.
	 * @return void
	 */
	public function register_sanitizer( string $name, callable $callback ): void;
}



