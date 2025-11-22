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

// Carica Container prima di Plugin per evitare errori di autoload
require_once __DIR__ . '/src/Infrastructure/Container.php';
require_once __DIR__ . '/src/Infrastructure/Plugin.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			'fp-seo-performance',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	},
	0
);

FP\SEO\Infrastructure\Plugin::instance()->init();

add_action(
	'init',
	static function () {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		$capability = \FP\SEO\Utils\Options::get_capability();

		if ( empty( $capability ) || 'manage_options' === $capability ) {
			return;
		}

		$administrator = get_role( 'administrator' );

		if ( $administrator && ! $administrator->has_cap( $capability ) ) {
			$administrator->add_cap( $capability );
		}
	},
	5
);
