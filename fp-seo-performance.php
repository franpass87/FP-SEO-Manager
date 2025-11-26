<?php
/**
 * Plugin Name: FP SEO Performance
 * Plugin URI: https://francescopasseri.com
 * Description: FP SEO Performance provides AI-powered SEO content generation with GPT-5 Nano, on-page analyzer, bulk audits, GEO optimization, and Google Search Console integration.
 * Version: 0.9.0-pre.48
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

/**
 * CRITICAL: Disable plugin ONLY on the media library grid page (upload.php without post editing).
 * This prevents interference with thumbnail display in the media library.
 * NOTE: Using raw $_SERVER because WordPress functions may not be available yet.
 */
$fp_seo_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
$fp_seo_ajax_action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

// Only block on pure media library pages, NOT when editing posts
$fp_seo_is_pure_media_library = (
	// Media library grid view (upload.php without item parameter means grid view)
	( strpos( $fp_seo_request_uri, 'upload.php' ) !== false && strpos( $fp_seo_request_uri, 'item=' ) === false ) ||
	// Media upload popup (not when inserting into post)
	strpos( $fp_seo_request_uri, 'media-new.php' ) !== false ||
	// AJAX calls for media library grid only
	( strpos( $fp_seo_request_uri, 'admin-ajax.php' ) !== false && 
	  in_array( $fp_seo_ajax_action, array( 'query-attachments' ), true )
	)
);

// CRITICAL: Block plugin on Nectar Slider pages to prevent interference with slider image saving
// Check if we're on a post.php or post-new.php page and verify post type after WordPress loads
$fp_seo_is_post_edit_page = (
	strpos( $fp_seo_request_uri, 'post.php' ) !== false ||
	strpos( $fp_seo_request_uri, 'post-new.php' ) !== false
);

// If on post edit page, we'll check post type after WordPress loads (see hook below)
$fp_seo_is_nectar_slider_page = false;
if ( $fp_seo_is_post_edit_page ) {
	// Check post type from GET parameter or will check after WordPress loads
	$fp_seo_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $fp_seo_post_id > 0 ) {
		// Try to get post type from database directly (before WordPress is fully loaded)
		// This is a minimal check - we'll do a more thorough check after WordPress loads
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$fp_seo_post_type = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_type FROM {$wpdb->posts} WHERE ID = %d LIMIT 1",
				$fp_seo_post_id
			) );
			if ( $fp_seo_post_type === 'nectar_slider' ) {
				$fp_seo_is_nectar_slider_page = true;
			}
		}
	} elseif ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'nectar_slider' ) {
		// New post page with post_type parameter
		$fp_seo_is_nectar_slider_page = true;
	}
}

// Also check edit.php list page
if ( strpos( $fp_seo_request_uri, 'edit.php' ) !== false && strpos( $fp_seo_request_uri, 'post_type=nectar_slider' ) !== false ) {
	$fp_seo_is_nectar_slider_page = true;
}

// If on pure media library page or Nectar Slider page, do NOT load the plugin
if ( $fp_seo_is_pure_media_library || $fp_seo_is_nectar_slider_page ) {
	return; // Exit early, plugin will not be loaded
}

// Clean up temporary variables
unset( $fp_seo_request_uri, $fp_seo_ajax_action, $fp_seo_is_pure_media_library, $fp_seo_is_nectar_slider_page );

if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
		define( 'FP_SEO_PERFORMANCE_FILE', __FILE__ );
}

require_once __DIR__ . '/src/Utils/Version.php';

if ( ! defined( 'FP_SEO_PERFORMANCE_VERSION' ) ) {
		define( 'FP_SEO_PERFORMANCE_VERSION', FP\SEO\Utils\Version::resolve( __FILE__, '0.9.0-pre.48' ) );
}

$autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

// Autoloader di fallback per classi critiche se l'autoload PSR-4 non funziona
spl_autoload_register(
	function ( $class ) {
		// Namespace del plugin
		if ( strpos( $class, 'FP\\SEO\\' ) !== 0 ) {
			return false;
		}

		// Rimuove il namespace base
		$relative_class = substr( $class, strlen( 'FP\\SEO\\' ) );
		
		// Converte namespace in percorso file
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Carica il file se esiste
		if ( file_exists( $file ) ) {
			require_once $file;
			return true;
		}

		return false;
	},
	true, // Prepend per avere priorità
	false // Non throw exception, ritorna false
);

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
