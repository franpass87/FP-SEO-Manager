<?php
/**
 * Advanced Schema Markup Manager
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema;

use FP\SEO\Schema\Generators\ArticleSchemaGenerator;
use FP\SEO\Schema\Generators\BreadcrumbSchemaGenerator;
use FP\SEO\Schema\Generators\FaqSchemaGenerator;
use FP\SEO\Schema\Generators\HowToSchemaGenerator;
use FP\SEO\Schema\Generators\OrganizationSchemaGenerator;
use FP\SEO\Schema\Generators\ProductSchemaGenerator;
use FP\SEO\Schema\Generators\WebSiteSchemaGenerator;
use FP\SEO\Schema\Handlers\SchemaAjaxHandler;
use FP\SEO\Schema\Renderers\SchemaPageRenderer;
use FP\SEO\Utils\Cache;
use FP\SEO\Utils\PerformanceConfig;
use function get_current_blog_id;
use function get_post_type;
use function get_the_ID;
use function is_singular;
use function wp_json_encode;

/**
 * Handles advanced Schema.org markup generation and management.
 */
class AdvancedSchemaManager {

	/**
	 * Schema types supported.
	 */
	private const SCHEMA_TYPES = array(
		'Article' => 'Article',
		'BlogPosting' => 'BlogPosting',
		'NewsArticle' => 'NewsArticle',
		'WebPage' => 'WebPage',
		'Product' => 'Product',
		'Organization' => 'Organization',
		'WebSite' => 'WebSite',
		'BreadcrumbList' => 'BreadcrumbList',
		'FAQPage' => 'FAQPage',
		'HowTo' => 'HowTo',
		'Review' => 'Review',
		'Event' => 'Event',
		'Person' => 'Person',
		'LocalBusiness' => 'LocalBusiness',
	);

	/**
	 * @var SchemaPageRenderer|null
	 */
	private $page_renderer;

	/**
	 * @var SchemaAjaxHandler|null
	 */
	private $ajax_handler;

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'output_schema_markup' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_schema_menu' ) );

		// Initialize and register AJAX handler
		$this->ajax_handler = new SchemaAjaxHandler( $this );
		$this->ajax_handler->register();
	}

	/**
	 * Add Schema menu to admin.
	 */
	public function add_schema_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Schema Markup', 'fp-seo-performance' ),
			__( 'Schema Markup', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-schema',
			array( $this, 'render_schema_page' )
		);
	}

	/**
	 * Render schema admin page (delegates to SchemaPageRenderer).
	 *
	 * @return void
	 */
	public function render_schema_page(): void {
		if ( ! $this->page_renderer ) {
			$this->page_renderer = new SchemaPageRenderer( $this );
		}
		$this->page_renderer->render();
	}

	/**
	 * Output schema markup in head.
	 */
	public function output_schema_markup(): void {
		if ( is_admin() ) {
			return;
		}

		$schemas = $this->get_active_schemas();
		
		if ( empty( $schemas ) ) {
			return;
		}

		echo "\n<!-- FP SEO Performance Schema Markup -->\n";
		foreach ( $schemas as $schema ) {
			echo '<script type="application/ld+json">' . "\n";
			echo wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			echo "\n" . '</script>' . "\n";
		}
		echo "<!-- End FP SEO Performance Schema Markup -->\n";
	}

	/**
	 * Get active schemas for current page.
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_active_schemas(): array {
		$cache_key = 'fp_seo_schemas_' . get_the_ID() . '_' . get_current_blog_id();
		
		return Cache::remember( $cache_key, function() {
			$schemas = array();

			// Organization schema (global)
			$org_generator = new OrganizationSchemaGenerator();
			$schemas[] = $org_generator->generate();

			// WebSite schema (global)
			$website_generator = new WebSiteSchemaGenerator();
			$schemas[] = $website_generator->generate();

			// Page-specific schemas
			if ( is_singular() ) {
				$post_id = get_the_ID();
				$post_type = get_post_type( $post_id );

				// Article/BlogPosting schema
				if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
					$article_generator = new ArticleSchemaGenerator();
					$schemas[] = $article_generator->generate( $post_id );
				}

				// Product schema for WooCommerce
				if ( class_exists( 'WooCommerce' ) && 'product' === $post_type ) {
					$product_generator = new ProductSchemaGenerator();
					$schemas[] = $product_generator->generate( $post_id );
				}

				// FAQ schema if present
				$faq_generator = new FaqSchemaGenerator();
				$faq_schema = $faq_generator->generate( $post_id );
				if ( ! empty( $faq_schema ) ) {
					$schemas[] = $faq_schema;
				}

				// HowTo schema if present
				$howto_generator = new HowToSchemaGenerator();
				$howto_schema = $howto_generator->generate( $post_id );
				if ( ! empty( $howto_schema ) ) {
					$schemas[] = $howto_schema;
				}
			}

			// Breadcrumb schema
			$breadcrumb_generator = new BreadcrumbSchemaGenerator();
			$breadcrumb_schema = $breadcrumb_generator->generate();
			if ( ! empty( $breadcrumb_schema ) ) {
				$schemas[] = $breadcrumb_schema;
			}

			return array_filter( $schemas );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Get active schemas (public accessor).
	 *
	 * @return array<array<string, mixed>>
	 */
	public function get_active_schemas_public(): array {
		return $this->get_active_schemas();
	}

	/**
	 * Get available schema types.
	 *
	 * @return array<string, string>
	 */
	public static function get_schema_types(): array {
		return self::SCHEMA_TYPES;
	}

	// Schema generation methods have been moved to dedicated generator classes
	// See: FP\SEO\Schema\Generators\*

	// Old render_schema_page() method has been moved to SchemaPageRenderer
	// Old AJAX methods have been moved to SchemaAjaxHandler

	/**
	 * AJAX handler for schema generation (deprecated - use SchemaAjaxHandler).
	 *
	 * @deprecated Use SchemaAjaxHandler::handle_generate_schema() instead.
	 * @return void
	 */
	public function ajax_generate_schema(): void {
		// This method has been moved to SchemaAjaxHandler
		// Keeping as stub for backward compatibility
		if ( $this->ajax_handler ) {
			$this->ajax_handler->handle_generate_schema();
		}
	}

	/**
	 * AJAX handler for schema preview (deprecated - use SchemaAjaxHandler).
	 *
	 * @deprecated Use SchemaAjaxHandler::handle_preview_schema() instead.
	 * @return void
	 */
	public function ajax_preview_schema(): void {
		// This method has been moved to SchemaAjaxHandler
		// Keeping as stub for backward compatibility
		if ( $this->ajax_handler ) {
			$this->ajax_handler->handle_preview_schema();
		}
	}
}

/**
 * Get custom logo URL.
 *
 * @return string Logo URL or empty string if not set.
 */
function get_custom_logo_url(): string {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	
	if ( ! $custom_logo_id ) {
		return '';
	}
	
	// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
	$logo_url = wp_get_attachment_url( $custom_logo_id );
	
	return $logo_url ? (string) $logo_url : '';
}
