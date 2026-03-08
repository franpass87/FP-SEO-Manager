<?php
/**
 * Logger interface.
 *
 * @package FP\SEO\Infrastructure\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Contracts;

/**
 * Interface for logging operations.
 */
interface LoggerInterface {
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
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function emergency( string $message, array $context = array() ): void;

	/**
	 * Logs a message with alert level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function alert( string $message, array $context = array() ): void;

	/**
	 * Logs a message with critical level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void;

	/**
	 * Logs a message with error level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Logs a message with warning level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Logs a message with notice level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function notice( string $message, array $context = array() ): void;

	/**
	 * Logs a message with info level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Logs a message with debug level.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void;

	/**
	 * Main logging method.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void;
}















