<?php
/**
 * Main Metabox service provider.
 *
 * Registers the main SEO metabox for WordPress editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\Logger;

/**
 * Main Metabox service provider.
 *
 * Registers the core SEO metabox used in WordPress editor.
 * This is the most critical metabox and uses 'error' log level.
 */
class MainMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return Metabox::class;
	}

	/**
	 * Register main metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register main SEO metabox as singleton
		$container->singleton( Metabox::class );
	}

	/**
	 * Get the log level for booting this metabox.
	 *
	 * Main metabox is critical, so use 'error' level.
	 *
	 * @return string Log level.
	 */
	protected function get_boot_log_level(): string {
		return 'error';
	}

	/**
	 * Get the error message prefix for booting failures.
	 *
	 * @return string Error message prefix.
	 */
	protected function get_boot_error_message(): string {
		return 'Failed to register Metabox';
	}

	/**
	 * Boot main metabox service.
	 *
	 * Overrides parent to add additional debug logging.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors from breaking WordPress
		try {
			// Use parent implementation
			parent::boot_admin( $container );

			// Additional debug logging for main metabox
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				try {
					$metabox = $container->get( Metabox::class );
					Logger::debug( 'Metabox instance created', array( 'class' => get_class( $metabox ) ) );
					Logger::debug( 'Metabox::register() called successfully' );
				} catch ( \Throwable $e ) {
					// Silent fail in debug mode
					Logger::error( 'FP SEO: Error getting Metabox instance in MainMetaboxServiceProvider', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			Logger::error( 'FP SEO: Fatal error in MainMetaboxServiceProvider::boot_admin()', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			// Don't re-throw - allow WordPress to continue
		}
	}
}

