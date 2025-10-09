<?php
/**
 * Base plugin exception.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Exceptions;

use RuntimeException;

/**
 * Base exception class for all plugin exceptions.
 */
class PluginException extends RuntimeException {

	/**
	 * Creates an exception for missing required data.
	 *
	 * @param string $data_type Type of missing data.
	 *
	 * @return self
	 */
	public static function missing_required_data( string $data_type ): self {
		return new self( sprintf( 'Required data is missing: %s', $data_type ) );
	}

	/**
	 * Creates an exception for invalid configuration.
	 *
	 * @param string $config_key Configuration key that is invalid.
	 * @param string $reason     Reason for invalidity.
	 *
	 * @return self
	 */
	public static function invalid_configuration( string $config_key, string $reason ): self {
		return new self( sprintf( 'Invalid configuration for "%s": %s', $config_key, $reason ) );
	}
}
