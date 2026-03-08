<?php
/**
 * Plugin Name: FP SEO Performance
 * Plugin URI: https://francescopasseri.com
 * Description: FP SEO Performance provides AI-powered SEO content generation with GPT-5 Nano, on-page analyzer, bulk audits, GEO optimization, and Google Search Console integration.
 * Version: 0.9.0-pre.72
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

// Define plugin file constant
if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
	define( 'FP_SEO_PERFORMANCE_FILE', __FILE__ );
}

// Load version utility
require_once __DIR__ . '/src/Utils/Version.php';

// Define plugin version constant
if ( ! defined( 'FP_SEO_PERFORMANCE_VERSION' ) ) {
	define( 'FP_SEO_PERFORMANCE_VERSION', FP\SEO\Utils\Version::resolve( __FILE__, '0.9.0-pre.72' ) );
}

// Load Kernel for bootstrap
require_once __DIR__ . '/src/Infrastructure/Bootstrap/Kernel.php';

// Initialize kernel
$kernel = new FP\SEO\Infrastructure\Bootstrap\Kernel( __FILE__, FP_SEO_PERFORMANCE_VERSION );

// Check if plugin should load (early exit for media library pages)
if ( ! $kernel->should_load() ) {
	return; // Exit early, plugin will not be loaded
}

// Initialize kernel (handles autoloading, error handling, container setup)
$kernel->init();

// Load Plugin orchestrator (now available via autoloader)
require_once __DIR__ . '/src/Infrastructure/Plugin.php';

// Initialize plugin (text domain and capability registration moved to CoreServiceProvider)
FP\SEO\Infrastructure\Plugin::instance()->init();
