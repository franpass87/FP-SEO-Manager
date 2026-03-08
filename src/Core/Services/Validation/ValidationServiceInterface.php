<?php
/**
 * Validation service interface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Validation;

/**
 * Interface for validation service.
 */
interface ValidationServiceInterface {

	/**
	 * Validate a value against rules.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $rule  Validation rule name.
	 * @param array<string, mixed> $args Optional rule arguments.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( $value, string $rule, array $args = array() ): bool;

	/**
	 * Validate multiple values against rules.
	 *
	 * @param array<string, mixed> $data  Data to validate (key => value pairs).
	 * @param array<string, string|array> $rules Rules (key => rule or array of rules).
	 * @return array<string, string> Errors (key => error message), empty if valid.
	 */
	public function validate_many( array $data, array $rules ): array;

	/**
	 * Get validation error message.
	 *
	 * @param string $rule Rule name.
	 * @param string $field Field name.
	 * @param array<string, mixed> $args Rule arguments.
	 * @return string Error message.
	 */
	public function get_error_message( string $rule, string $field, array $args = array() ): string;

	/**
	 * Register a custom validation rule.
	 *
	 * @param string   $name Rule name.
	 * @param callable $callback Validation callback.
	 * @return void
	 */
	public function register_rule( string $name, callable $callback ): void;
}



