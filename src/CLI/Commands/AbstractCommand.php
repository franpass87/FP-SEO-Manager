<?php
/**
 * Abstract WP-CLI command base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\CLI\Commands;

use WP_CLI;
use WP_CLI_Command;

/**
 * Abstract base class for WP-CLI commands.
 */
abstract class AbstractCommand extends WP_CLI_Command {

	/**
	 * Log an info message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log_info( string $message ): void {
		WP_CLI::log( $message );
	}

	/**
	 * Log a success message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log_success( string $message ): void {
		WP_CLI::success( $message );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log_warning( string $message ): void {
		WP_CLI::warning( $message );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log_error( string $message ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Display a table.
	 *
	 * @param array<array<string, mixed>> $items Table rows.
	 * @param array<string>                $headers Table headers.
	 * @return void
	 */
	protected function display_table( array $items, array $headers ): void {
		WP_CLI\Utils\format_items( 'table', $items, $headers );
	}
}



