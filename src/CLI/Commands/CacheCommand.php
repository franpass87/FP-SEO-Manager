<?php
/**
 * WP-CLI command for cache management.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\CLI\Commands;

use FP\SEO\Infrastructure\Contracts\CacheInterface;

/**
 * WP-CLI commands for cache management.
 */
class CacheCommand extends AbstractCommand {

	/**
	 * Cache service.
	 *
	 * @var CacheInterface
	 */
	private CacheInterface $cache;

	/**
	 * Constructor.
	 *
	 * @param CacheInterface $cache Cache service.
	 */
	public function __construct( CacheInterface $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Clear plugin cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fp-seo cache clear
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function clear( array $args, array $assoc_args ): void {
		$this->log_info( 'Clearing FP SEO cache...' );

		// Clear WordPress object cache
		wp_cache_flush();

		// Clear plugin transients
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_fp_seo_%',
				'_transient_timeout_fp_seo_%'
			)
		);

		$this->log_success( 'Cache cleared successfully' );
	}

	/**
	 * Show cache statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp fp-seo cache stats
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function stats( array $args, array $assoc_args ): void {
		global $wpdb;

		// Count transients
		$transient_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_fp_seo_%'
			)
		);

		$this->log_info( "FP SEO Transients: {$transient_count}" );

		// Show object cache info if available
		if ( function_exists( 'wp_cache_get_stats' ) ) {
			$stats = wp_cache_get_stats();
			if ( $stats ) {
				$this->log_info( 'Object Cache: ' . wp_json_encode( $stats ) );
			}
		}
	}
}










