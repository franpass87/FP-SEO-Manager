<?php
/**
 * Advanced input validation service with schema validation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Validation;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Utils\RateLimiter;

/**
 * Advanced input validator with schema validation and rate limiting.
 */
class InputValidator {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Rate limiter instance.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 * @param RateLimiter     $rate_limiter Rate limiter instance.
	 */
	public function __construct( LoggerInterface $logger, RateLimiter $rate_limiter ) {
		$this->logger = $logger;
		$this->rate_limiter = $rate_limiter;
	}

	/**
	 * Validate input against schema.
	 *
	 * @param array<string, mixed> $input Input data.
	 * @param array<string, array<string, mixed>> $schema Validation schema.
	 * @return array{valid: bool, errors?: array<string, string>} Validation result.
	 */
	public function validate_schema( array $input, array $schema ): array {
		$errors = array();

		foreach ( $schema as $field => $rules ) {
			$value = $input[ $field ] ?? null;

			// Check required fields
			if ( ! empty( $rules['required'] ) && ( $value === null || $value === '' ) ) {
				$errors[ $field ] = sprintf( __( 'Field %s is required.', 'fp-seo-performance' ), $field );
				continue;
			}

			// Skip validation if field is empty and not required
			if ( ( $value === null || $value === '' ) && empty( $rules['required'] ) ) {
				continue;
			}

			// Type validation
			if ( isset( $rules['type'] ) ) {
				$type_error = $this->validate_type( $value, $rules['type'], $field );
				if ( $type_error ) {
					$errors[ $field ] = $type_error;
					continue;
				}
			}

			// String length validation
			if ( isset( $rules['min_length'] ) && is_string( $value ) ) {
				if ( mb_strlen( $value ) < $rules['min_length'] ) {
					$errors[ $field ] = sprintf( __( 'Field %s must be at least %d characters.', 'fp-seo-performance' ), $field, $rules['min_length'] );
				}
			}

			if ( isset( $rules['max_length'] ) && is_string( $value ) ) {
				if ( mb_strlen( $value ) > $rules['max_length'] ) {
					$errors[ $field ] = sprintf( __( 'Field %s must be at most %d characters.', 'fp-seo-performance' ), $field, $rules['max_length'] );
				}
			}

			// Numeric range validation
			if ( isset( $rules['min'] ) && is_numeric( $value ) ) {
				if ( (float) $value < $rules['min'] ) {
					$errors[ $field ] = sprintf( __( 'Field %s must be at least %s.', 'fp-seo-performance' ), $field, $rules['min'] );
				}
			}

			if ( isset( $rules['max'] ) && is_numeric( $value ) ) {
				if ( (float) $value > $rules['max'] ) {
					$errors[ $field ] = sprintf( __( 'Field %s must be at most %s.', 'fp-seo-performance' ), $field, $rules['max'] );
				}
			}

			// Pattern validation (regex)
			if ( isset( $rules['pattern'] ) && is_string( $value ) ) {
				if ( preg_match( $rules['pattern'], $value ) !== 1 ) {
					$errors[ $field ] = sprintf( __( 'Field %s does not match required pattern.', 'fp-seo-performance' ), $field );
				}
			}

			// Enum validation (allowed values)
			if ( isset( $rules['enum'] ) && is_array( $rules['enum'] ) ) {
				if ( ! in_array( $value, $rules['enum'], true ) ) {
					$errors[ $field ] = sprintf( __( 'Field %s must be one of: %s.', 'fp-seo-performance' ), $field, implode( ', ', $rules['enum'] ) );
				}
			}

			// Custom validation callback
			if ( isset( $rules['validate'] ) && is_callable( $rules['validate'] ) ) {
				$custom_error = call_user_func( $rules['validate'], $value, $input );
				if ( is_string( $custom_error ) && ! empty( $custom_error ) ) {
					$errors[ $field ] = $custom_error;
				}
			}
		}

		return array(
			'valid' => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Validate type.
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $type  Expected type.
	 * @param string $field Field name.
	 * @return string|null Error message or null if valid.
	 */
	private function validate_type( $value, string $type, string $field ): ?string {
		switch ( $type ) {
			case 'string':
				if ( ! is_string( $value ) ) {
					return sprintf( __( 'Field %s must be a string.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'integer':
				if ( ! is_int( $value ) && ! ctype_digit( (string) $value ) ) {
					return sprintf( __( 'Field %s must be an integer.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'float':
			case 'number':
				if ( ! is_numeric( $value ) ) {
					return sprintf( __( 'Field %s must be a number.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'boolean':
				if ( ! is_bool( $value ) && $value !== '0' && $value !== '1' && $value !== 0 && $value !== 1 ) {
					return sprintf( __( 'Field %s must be a boolean.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'array':
				if ( ! is_array( $value ) ) {
					return sprintf( __( 'Field %s must be an array.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'email':
				if ( ! is_string( $value ) || ! is_email( $value ) ) {
					return sprintf( __( 'Field %s must be a valid email address.', 'fp-seo-performance' ), $field );
				}
				break;
			case 'url':
				if ( ! is_string( $value ) || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return sprintf( __( 'Field %s must be a valid URL.', 'fp-seo-performance' ), $field );
				}
				break;
		}

		return null;
	}

	/**
	 * Validate AJAX request with rate limiting.
	 *
	 * @param string $action AJAX action.
	 * @param int    $max_requests Maximum requests per time window.
	 * @param int    $time_window Time window in seconds.
	 * @return array{valid: bool, error?: string, remaining?: int} Validation result.
	 */
	public function validate_ajax_rate_limit( string $action, int $max_requests = 60, int $time_window = 60 ): array {
		$user_id = get_current_user_id();
		$ip = $this->get_client_ip();
		$key = "ajax_rate_limit_{$action}_{$user_id}_{$ip}";

		$allowed = $this->rate_limiter->is_allowed( $key, $max_requests, $time_window );

		if ( ! $allowed ) {
			$this->logger->warning( 'AJAX rate limit exceeded', array(
				'action' => $action,
				'user_id' => $user_id,
				'ip' => $ip,
			) );

			return array(
				'valid' => false,
				'error' => __( 'Too many requests. Please try again later.', 'fp-seo-performance' ),
			);
		}

		$remaining = $this->rate_limiter->get_remaining( $key, $max_requests, $time_window );

		return array(
			'valid' => true,
			'remaining' => $remaining,
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Proxy
			'REMOTE_ADDR',           // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Sanitize input according to schema.
	 *
	 * @param array<string, mixed> $input Input data.
	 * @param array<string, array<string, mixed>> $schema Validation schema.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_input( array $input, array $schema ): array {
		$sanitized = array();

		foreach ( $schema as $field => $rules ) {
			$value = $input[ $field ] ?? null;

			if ( $value === null ) {
				continue;
			}

			// Type-based sanitization
			if ( isset( $rules['type'] ) ) {
				switch ( $rules['type'] ) {
					case 'string':
						$value = sanitize_text_field( (string) $value );
						break;
					case 'textarea':
						$value = sanitize_textarea_field( (string) $value );
						break;
					case 'integer':
						$value = absint( $value );
						break;
					case 'float':
					case 'number':
						$value = filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
						break;
					case 'boolean':
						$value = (bool) $value;
						break;
					case 'email':
						$value = sanitize_email( (string) $value );
						break;
					case 'url':
						$value = esc_url_raw( (string) $value );
						break;
					case 'array':
						if ( ! is_array( $value ) ) {
							$value = array();
						}
						break;
				}
			}

			// Custom sanitization callback
			if ( isset( $rules['sanitize'] ) && is_callable( $rules['sanitize'] ) ) {
				$value = call_user_func( $rules['sanitize'], $value );
			}

			$sanitized[ $field ] = $value;
		}

		return $sanitized;
	}
}




