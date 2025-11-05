<?php
/**
 * Plugin Name: FP SEO Performance
 * Plugin URI: https://francescopasseri.com
 * Description: FP SEO Performance provides AI-powered SEO content generation with GPT-5 Nano, on-page analyzer, bulk audits, GEO optimization, and Google Search Console integration.
 * Version: 0.9.0-pre.11
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-seo-performance
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
		define( 'FP_SEO_PERFORMANCE_FILE', __FILE__ );
}

require_once __DIR__ . '/src/Utils/Version.php';

if ( ! defined( 'FP_SEO_PERFORMANCE_VERSION' ) ) {
		define( 'FP_SEO_PERFORMANCE_VERSION', FP\SEO\Utils\Version::resolve( __FILE__, '0.9.0-pre.11' ) );
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

require_once __DIR__ . '/src/Infrastructure/Plugin.php';

FP\SEO\Infrastructure\Plugin::instance()->init();

// TEMPORARY: Force flush menu cache UNA SOLA VOLTA - rimuovi dopo 1-2 giorni
add_action( 'init', function() {
	// Controlla se il flush è già stato fatto
	if ( false === get_transient( 'fp_seo_menu_flushed_v3' ) ) {
		// Flush cache
		wp_cache_flush();
		// Imposta transient per 7 giorni - dopo questo periodo si auto-riabilita
		set_transient( 'fp_seo_menu_flushed_v3', true, 7 * DAY_IN_SECONDS );
		// Log per debug
		error_log( 'FP SEO Performance: Cache flushed after menu restructure' );
	}
}, 1 );
