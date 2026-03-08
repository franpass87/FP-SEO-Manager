<?php
/**
 * Logger helper for backward compatibility and easy access to LoggerInterface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Plugin;

/**
 * Helper class to access LoggerInterface from container.
 * 
 * This provides a bridge between static Logger:: methods and dependency injection.
 * Use this when you cannot inject LoggerInterface directly.
 */
class LoggerHelper {

	/**
	 * Get LoggerInterface instance from container.
	 *
	 * @return LoggerInterface|null Logger instance or null if not available.
	 */
	public static function get_logger(): ?LoggerInterface {
		try {
			$container = Plugin::instance()->get_container();
			return $container->get( LoggerInterface::class );
		} catch ( \Throwable $e ) {
			// Fallback to static Logger if container not available
			return null;
		}
	}

	/**
	 * Log a message with debug level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function debug( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->debug( $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO][debug] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with info level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->info( $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO][info] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with warning level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function warning( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->warning( $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO][warning] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with error level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->error( $message, $context );
		} else {
			error_log( '[FP-SEO][error] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with emergency level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function emergency( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->emergency( $message, $context );
		} else {
			error_log( '[FP-SEO][emergency] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with alert level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function alert( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->alert( $message, $context );
		} else {
			error_log( '[FP-SEO][alert] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with critical level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function critical( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->critical( $message, $context );
		} else {
			error_log( '[FP-SEO][critical] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with notice level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function notice( string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->notice( $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO][notice] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}

	/**
	 * Log a message with any level.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		$logger = self::get_logger();
		if ( $logger ) {
			$logger->log( $level, $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO][' . $level . '] ' . $message . ( $context ? ' ' . wp_json_encode( $context ) : '' ) );
		}
	}
}


