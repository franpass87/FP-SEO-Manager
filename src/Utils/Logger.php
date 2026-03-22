<?php
/**
 * Logging utilities for debugging and monitoring.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Provides structured logging capabilities.
 */
class Logger {

	/**
	 * Log levels following PSR-3 conventions.
	 */
	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * Logs a message with emergency level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function emergency( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::emergency() via dependency injection' );
		self::log( self::EMERGENCY, $message, $context );
	}

	/**
	 * Logs a message with alert level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function alert( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::alert() via dependency injection' );
		self::log( self::ALERT, $message, $context );
	}

	/**
	 * Logs a message with critical level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function critical( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::critical() via dependency injection' );
		self::log( self::CRITICAL, $message, $context );
	}

	/**
	 * Logs a message with error level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::error() via dependency injection' );
		self::log( self::ERROR, $message, $context );
	}

	/**
	 * Logs a message with warning level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::warning() via dependency injection' );
		self::log( self::WARNING, $message, $context );
	}

	/**
	 * Logs a message with notice level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function notice( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::notice() via dependency injection' );
		self::log( self::NOTICE, $message, $context );
	}

	/**
	 * Logs a message with info level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::info() via dependency injection' );
		self::log( self::INFO, $message, $context );
	}

	/**
	 * Logs a message with debug level.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::debug() via dependency injection' );
		self::log( self::DEBUG, $message, $context );
	}

	/**
	 * Main logging method.
	 *
	 * @deprecated 0.9.0 Use injected LoggerInterface instead. This static method will be removed in a future version.
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public static function log( string $level, string $message, array $context = array() ): void {
		self::trigger_deprecation_notice( __METHOD__, 'LoggerInterface::log() via dependency injection' );
		// Only log if WP_DEBUG is enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$formatted = self::format_message( $level, $message, $context );

		// Use WordPress error_log if available.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging.
			error_log( $formatted );
		}

		/**
		 * Fires after a log entry is created.
		 *
		 * @param string               $level   Log level.
		 * @param string               $message Original message.
		 * @param array<string, mixed> $context Context data.
		 * @param string               $formatted Formatted log entry.
		 */
		do_action( 'fp_seo_log', $level, $message, $context, $formatted );
	}

	/**
	 * Formats a log message with level, timestamp, and context.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 *
	 * @return string Formatted log entry.
	 */
	private static function format_message( string $level, string $message, array $context ): string {
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$level_str = strtoupper( $level );

		// Interpolate context placeholders in message.
		$message = self::interpolate( $message, $context );

		$formatted = sprintf( '[%s] [FP-SEO] [%s] %s', $timestamp, $level_str, $message );

		// Add context data if present.
		if ( ! empty( $context ) ) {
			$context_str = wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false !== $context_str ) {
				$formatted .= ' ' . $context_str;
			}
		}

		return $formatted;
	}

	/**
	 * Interpolates context values into message placeholders.
	 *
	 * @param string               $message Message with {placeholders}.
	 * @param array<string, mixed> $context Context values.
	 *
	 * @return string Interpolated message.
	 */
	private static function interpolate( string $message, array $context ): string {
		$replacements = array();

		foreach ( $context as $key => $value ) {
			if ( is_null( $value ) || is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
				$replacements[ '{' . $key . '}' ] = (string) $value;
			} elseif ( is_array( $value ) || is_object( $value ) ) {
				$json = wp_json_encode( $value );
				if ( false !== $json ) {
					$replacements[ '{' . $key . '}' ] = $json;
				}
			}
		}

		return strtr( $message, $replacements );
	}

	/**
	 * Emits deprecated notices only when explicitly enabled.
	 *
	 * This keeps production/staging logs clean while maintaining opt-in
	 * diagnostics for migration work.
	 */
	private static function trigger_deprecation_notice( string $method, string $replacement ): void {
		$emit_notice = defined( 'FP_SEO_STRICT_DEPRECATIONS' ) && FP_SEO_STRICT_DEPRECATIONS;
		if ( ! $emit_notice ) {
			return;
		}

		_deprecated_function( $method, '0.9.0', $replacement );
	}
}
