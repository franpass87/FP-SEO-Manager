<?php
/**
 * Analysis-specific exceptions.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Exceptions;

use Throwable;

/**
 * Exception thrown during SEO analysis operations.
 */
class AnalysisException extends PluginException {

	/**
	 * Creates an exception for check failure.
	 *
	 * @param string         $check_id Check identifier that failed.
	 * @param Throwable|null $previous Previous exception for chaining.
	 *
	 * @return self
	 */
	public static function check_failed( string $check_id, ?Throwable $previous = null ): self {
		return new self(
			sprintf( 'Analysis check "%s" failed to execute', $check_id ),
			0,
			$previous
		);
	}

	/**
	 * Creates an exception for invalid context.
	 *
	 * @param string $reason Reason for invalid context.
	 *
	 * @return self
	 */
	public static function invalid_context( string $reason ): self {
		return new self( sprintf( 'Invalid analysis context: %s', $reason ) );
	}

	/**
	 * Creates an exception for missing check class.
	 *
	 * @param string $check_class Check class name.
	 *
	 * @return self
	 */
	public static function check_class_not_found( string $check_class ): self {
		return new self( sprintf( 'Check class not found: %s', $check_class ) );
	}
}
