<?php
/**
 * WordPress validation service implementation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Validation;

/**
 * WordPress-based validation service.
 */
class WordPressValidationService implements ValidationServiceInterface {

	/**
	 * Registered custom validation rules.
	 *
	 * @var array<string, callable>
	 */
	private array $custom_rules = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register default WordPress validation functions
	}

	/**
	 * Validate a value against rules.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $rule  Validation rule name.
	 * @param array<string, mixed> $args Optional rule arguments.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate( $value, string $rule, array $args = array() ): bool {
		// Check custom rules first
		if ( isset( $this->custom_rules[ $rule ] ) ) {
			return (bool) call_user_func( $this->custom_rules[ $rule ], $value, $args );
		}

		// Built-in rules
		switch ( $rule ) {
			case 'required':
				return ! empty( $value );

			case 'email':
				return is_email( $value );

			case 'url':
				return filter_var( $value, FILTER_VALIDATE_URL ) !== false;

			case 'numeric':
				return is_numeric( $value );

			case 'integer':
				return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );

			case 'min_length':
				$min = $args['min'] ?? 0;
				return is_string( $value ) && mb_strlen( $value ) >= $min;

			case 'max_length':
				$max = $args['max'] ?? PHP_INT_MAX;
				return is_string( $value ) && mb_strlen( $value ) <= $max;

			case 'min':
				$min = $args['min'] ?? 0;
				return is_numeric( $value ) && (float) $value >= $min;

			case 'max':
				$max = $args['max'] ?? PHP_INT_MAX;
				return is_numeric( $value ) && (float) $value <= $max;

			case 'in':
				$allowed = $args['values'] ?? array();
				return in_array( $value, $allowed, true );

			case 'regex':
				$pattern = $args['pattern'] ?? '';
				return is_string( $value ) && preg_match( $pattern, $value ) === 1;

			default:
				return true; // Unknown rule, assume valid
		}
	}

	/**
	 * Validate multiple values against rules.
	 *
	 * @param array<string, mixed> $data  Data to validate (key => value pairs).
	 * @param array<string, string|array> $rules Rules (key => rule or array of rules).
	 * @return array<string, string> Errors (key => error message), empty if valid.
	 */
	public function validate_many( array $data, array $rules ): array {
		$errors = array();

		foreach ( $rules as $field => $rule_config ) {
			$value = $data[ $field ] ?? null;

			// Convert single rule to array
			$field_rules = is_array( $rule_config ) ? $rule_config : array( $rule_config );

			foreach ( $field_rules as $rule ) {
				// Parse rule with arguments (e.g., "min_length:10" or "in:value1,value2")
				$rule_parts = explode( ':', $rule, 2 );
				$rule_name  = trim( $rule_parts[0] );
				$rule_args  = array();

				// Parse arguments
				if ( isset( $rule_parts[1] ) ) {
					$args_string = trim( $rule_parts[1] );
					if ( strpos( $args_string, ',' ) !== false ) {
						$rule_args['values'] = array_map( 'trim', explode( ',', $args_string ) );
					} else {
						$rule_args['value'] = $args_string;
					}
				}

				// Special handling for min/max_length
				if ( $rule_name === 'min_length' && isset( $rule_args['value'] ) ) {
					$rule_args['min'] = (int) $rule_args['value'];
					unset( $rule_args['value'] );
				}
				if ( $rule_name === 'max_length' && isset( $rule_args['value'] ) ) {
					$rule_args['max'] = (int) $rule_args['value'];
					unset( $rule_args['value'] );
				}

				if ( ! $this->validate( $value, $rule_name, $rule_args ) ) {
					$errors[ $field ] = $this->get_error_message( $rule_name, $field, $rule_args );
					break; // Stop at first error for this field
				}
			}
		}

		return $errors;
	}

	/**
	 * Get validation error message.
	 *
	 * @param string $rule Rule name.
	 * @param string $field Field name.
	 * @param array<string, mixed> $args Rule arguments.
	 * @return string Error message.
	 */
	public function get_error_message( string $rule, string $field, array $args = array() ): string {
		$field_label = ucfirst( str_replace( '_', ' ', $field ) );

		switch ( $rule ) {
			case 'required':
				return sprintf( '%s is required.', $field_label );

			case 'email':
				return sprintf( '%s must be a valid email address.', $field_label );

			case 'url':
				return sprintf( '%s must be a valid URL.', $field_label );

			case 'numeric':
				return sprintf( '%s must be a number.', $field_label );

			case 'integer':
				return sprintf( '%s must be an integer.', $field_label );

			case 'min_length':
				$min = $args['min'] ?? 0;
				return sprintf( '%s must be at least %d characters long.', $field_label, $min );

			case 'max_length':
				$max = $args['max'] ?? PHP_INT_MAX;
				return sprintf( '%s must not exceed %d characters.', $field_label, $max );

			case 'min':
				$min = $args['min'] ?? 0;
				return sprintf( '%s must be at least %s.', $field_label, $min );

			case 'max':
				$max = $args['max'] ?? PHP_INT_MAX;
				return sprintf( '%s must not exceed %s.', $field_label, $max );

			case 'in':
				$allowed = $args['values'] ?? array();
				return sprintf( '%s must be one of: %s.', $field_label, implode( ', ', $allowed ) );

			default:
				return sprintf( '%s is invalid.', $field_label );
		}
	}

	/**
	 * Register a custom validation rule.
	 *
	 * @param string   $name Rule name.
	 * @param callable $callback Validation callback.
	 * @return void
	 */
	public function register_rule( string $name, callable $callback ): void {
		$this->custom_rules[ $name ] = $callback;
	}
}



