<?php
/**
 * Exception handler service - centralized exception handling.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\ExceptionHandler;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use Throwable;

/**
 * Exception handler service.
 */
class ExceptionHandlerService {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface|null
	 */
	private ?LoggerInterface $logger = null;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface|null $logger Optional logger instance.
	 */
	public function __construct( ?LoggerInterface $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Handle an exception.
	 *
	 * Logs the exception and optionally returns a user-friendly message.
	 *
	 * @param Throwable $exception Exception to handle.
	 * @param bool      $log       Whether to log the exception.
	 * @return string User-friendly error message.
	 */
	public function handle( Throwable $exception, bool $log = true ): string {
		if ( $log && $this->logger ) {
			$this->logger->error(
				'Exception caught: ' . $exception->getMessage(),
				array(
					'exception' => get_class( $exception ),
					'file'      => $exception->getFile(),
					'line'      => $exception->getLine(),
					'trace'     => $exception->getTraceAsString(),
				)
			);
		}

		// Return user-friendly message
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return sprintf(
				'Error: %s in %s on line %d',
				$exception->getMessage(),
				basename( $exception->getFile() ),
				$exception->getLine()
			);
		}

		return 'An error occurred. Please try again or contact support if the problem persists.';
	}

	/**
	 * Handle an exception silently (no user message).
	 *
	 * Only logs the exception.
	 *
	 * @param Throwable $exception Exception to handle.
	 * @return void
	 */
	public function handle_silently( Throwable $exception ): void {
		if ( $this->logger ) {
			$this->logger->error(
				'Exception caught (silent): ' . $exception->getMessage(),
				array(
					'exception' => get_class( $exception ),
					'file'      => $exception->getFile(),
					'line'      => $exception->getLine(),
					'trace'     => $exception->getTraceAsString(),
				)
			);
		}
	}

	/**
	 * Wrap a callable in exception handling.
	 *
	 * @param callable $callback Callback to execute.
	 * @param mixed    $default  Default return value if exception occurs.
	 * @return mixed Callback result or default value.
	 */
	public function wrap( callable $callback, $default = null ) {
		try {
			return $callback();
		} catch ( Throwable $e ) {
			$this->handle( $e );
			return $default;
		}
	}

	/**
	 * Set the logger instance.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 * @return void
	 */
	public function set_logger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}
}



