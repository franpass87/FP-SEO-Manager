<?php
/**
 * Rate limit exception.
 *
 * @package FP\SEO\Exceptions
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Exceptions;

/**
 * Exception thrown when rate limit is exceeded.
 */
class RateLimitException extends \Exception {

	/**
	 * Rate limit type.
	 */
	private string $limit_type;

	/**
	 * Rate limit value.
	 */
	private int $limit_value;

	/**
	 * Constructor.
	 *
	 * @param string $message Exception message.
	 * @param string $limit_type Type of rate limit exceeded.
	 * @param int    $limit_value Rate limit value.
	 * @param int    $code Exception code.
	 * @param \Throwable|null $previous Previous exception.
	 */
	public function __construct( string $message = '', string $limit_type = '', int $limit_value = 0, int $code = 0, ?\Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->limit_type = $limit_type;
		$this->limit_value = $limit_value;
	}

	/**
	 * Get rate limit type.
	 *
	 * @return string
	 */
	public function get_limit_type(): string {
		return $this->limit_type;
	}

	/**
	 * Get rate limit value.
	 *
	 * @return int
	 */
	public function get_limit_value(): int {
		return $this->limit_value;
	}
}
