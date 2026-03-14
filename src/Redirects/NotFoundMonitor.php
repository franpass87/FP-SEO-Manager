<?php
/**
 * Monitors 404 requests and stores lightweight metrics.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

use FP\SEO\Monitoring\SeoMonitorRepository;

/**
 * Logs 404 events to feed SEO monitoring dashboard.
 */
class NotFoundMonitor {
	/**
	 * Register monitor hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'log_404' ), 20 );
	}

	/**
	 * Log 404 hits when relevant.
	 *
	 * @return void
	 */
	public function log_404(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! is_404() ) {
			return;
		}
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$path     = wp_parse_url( $uri, PHP_URL_PATH );
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) : '';

		if ( ! is_string( $path ) || '' === trim( $path ) ) {
			return;
		}

		SeoMonitorRepository::log_404( $path, $referrer );
	}
}

