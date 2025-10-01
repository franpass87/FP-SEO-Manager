<?php
/**
 * Plugin Name: FP SEO Performance
 * Plugin URI: https://example.com/plugins/fp-seo-performance
 * Description: SEO and performance assistant for WordPress editors.
 * Version: 0.1.0
 * Author: FP
 * Author URI: https://example.com
 * Text Domain: fp-seo-performance
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.0
 *
 * @package FP\SEO
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
	define( 'FP_SEO_PERFORMANCE_FILE', __FILE__ );
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

require_once __DIR__ . '/src/Infrastructure/Plugin.php';

FP\SEO\Infrastructure\Plugin::instance()->init();
