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
use FP\SEO\Schema\Generators\WebPageSchemaGenerator;
use FP\SEO\Schema\Generators\ContactPageSchemaGenerator;
use FP\SEO\Schema\Generators\AboutPageSchemaGenerator;
use FP\SEO\Schema\Generators\TouristTripSchemaGenerator;
use FP\SEO\Schema\Generators\EventSchemaGenerator;
use FP\SEO\Schema\Generators\TouristAttractionSchemaGenerator;
use FP\SEO\Schema\Generators\ServiceSchemaGenerator;
use FP\SEO\Schema\Generators\OfferSchemaGenerator;
use FP\SEO\Schema\Handlers\SchemaAjaxHandler;
use FP\SEO\Schema\Renderers\SchemaPageRenderer;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\CacheHelper;
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
		'TouristTrip' => 'TouristTrip',
		'TouristAttraction' => 'TouristAttraction',
		'Service' => 'Service',
		'Offer' => 'Offer',
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
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @deprecated Frontend schema rendering is now handled by Frontend/Renderers/SchemaRenderer.
	 *             Admin menu registration is now handled by AdminPagesServiceProvider.
	 *             This method is kept for backward compatibility.
	 */
	public function register(): void {
		// Frontend schema rendering moved to Frontend/Renderers/SchemaRenderer
		// Admin menu moved to AdminPagesServiceProvider
		// Only register AJAX handler if not already registered
		if ( ! $this->ajax_handler ) {
			$hook_manager = $this->hook_manager ?? $this->get_hook_manager();
			$this->ajax_handler = new SchemaAjaxHandler( $this, $hook_manager );
			$this->ajax_handler->register();
		}
	}

	/**
	 * Get hook manager from container.
	 *
	 * @return HookManagerInterface
	 */
	private function get_hook_manager(): HookManagerInterface {
		if ( $this->hook_manager ) {
			return $this->hook_manager;
		}
		
		// Fallback: get from container
		try {
			$plugin = \FP\SEO\Infrastructure\Plugin::instance();
			$container = $plugin->get_container();
			$this->hook_manager = $container->get( HookManagerInterface::class );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'HookManager not available: ' . $e->getMessage(), 0, $e );
		}
		return $this->hook_manager;
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
	 *
	 * @deprecated This method has been moved to Frontend/Renderers/SchemaRenderer.
	 *             This method is kept for backward compatibility but does nothing.
	 * @return void
	 */
	public function output_schema_markup(): void {
		// Schema rendering is now handled by Frontend/Renderers/SchemaRenderer
		// This method is kept for backward compatibility
	}

	/**
	 * Get active schemas for current page.
	 *
	 * @return array<array<string, mixed>>
	 */
	private function get_active_schemas( int $post_id = 0 ): array {
		// Get post ID before cache closure to ensure it's available
		$current_post_id = $post_id;

		if ( ! $current_post_id ) {
			if ( is_singular() ) {
				$current_post_id = (int) get_the_ID();
			}
			// Fallback: try to get from global $post
			if ( ! $current_post_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
				$current_post_id = (int) $GLOBALS['post']->ID;
			}
		}
		
		$cache_key = 'fp_seo_schemas_' . $current_post_id . '_' . get_current_blog_id();
		
		// Note: Cache is cleared in MetaboxSaver when post is saved
		// We don't clear cache here to avoid performance issues
		// If schema type was recently changed, MetaboxSaver will have cleared the cache
		
		return CacheHelper::remember( $cache_key, function() use ( $current_post_id ) {
			$schemas = array();

			// Organization schema (global)
			$org_generator = new OrganizationSchemaGenerator();
			$schemas[] = $org_generator->generate();

			// WebSite schema (global)
			$website_generator = new WebSiteSchemaGenerator();
			$schemas[] = $website_generator->generate();

			// Page-specific schemas
			if ( $current_post_id > 0 ) {
				$post_id = $current_post_id;
				$post_type = get_post_type( $post_id );

				// Get selected schema type from post meta
				$selected_schema_type = get_post_meta( $post_id, '_fp_seo_schema_type', true );
				
				// Default schema types based on post type
				if ( empty( $selected_schema_type ) ) {
					if ( $post_type === 'post' ) {
						$selected_schema_type = 'Article';
					} elseif ( $post_type === 'product' ) {
						$selected_schema_type = 'Product';
					} elseif ( $post_type === 'fp_experience' ) {
						$selected_schema_type = 'TouristTrip';
					} else {
						$selected_schema_type = 'WebPage';
					}
				}

				// Generate schema based on selected type
				switch ( $selected_schema_type ) {
					case 'Article':
					case 'BlogPosting':
					case 'NewsArticle':
						$article_generator = new ArticleSchemaGenerator();
						$schema = $article_generator->generate( $post_id );
						
						// Override @type if needed
						if ( $selected_schema_type !== 'Article' && ! empty( $schema ) ) {
							$schema['@type'] = $selected_schema_type;
						}
						// Always add schema if generated
						if ( ! empty( $schema ) && isset( $schema['@type'] ) ) {
							$schemas[] = $schema;
						}
						break;

				case 'Product':
					if ( class_exists( 'WooCommerce' ) && 'product' === $post_type ) {
						$product_generator = new ProductSchemaGenerator();
						$product_schema    = $product_generator->generate( $post_id );
						if ( ! empty( $product_schema ) ) {
							$schemas[] = $product_schema;
						}
					}
					break;

					case 'ContactPage':
						$contact_generator = new ContactPageSchemaGenerator();
						$schemas[] = $contact_generator->generate( $post_id );
						break;

					case 'AboutPage':
						$about_generator = new AboutPageSchemaGenerator();
						$schemas[] = $about_generator->generate( $post_id );
						break;

					case 'TouristTrip':
						$tourist_trip_generator = new TouristTripSchemaGenerator();
						$schemas[] = $tourist_trip_generator->generate( $post_id );
						break;

					case 'Event':
						$event_generator = new EventSchemaGenerator();
						$schemas[] = $event_generator->generate( $post_id );
						break;

					case 'TouristAttraction':
						$tourist_attraction_generator = new TouristAttractionSchemaGenerator();
						$schemas[] = $tourist_attraction_generator->generate( $post_id );
						break;

					case 'Service':
						$service_generator = new ServiceSchemaGenerator();
						$schemas[] = $service_generator->generate( $post_id );
						break;

					case 'Offer':
						$offer_generator = new OfferSchemaGenerator();
						$schemas[] = $offer_generator->generate( $post_id );
						break;

					case 'WebPage':
					default:
						$webpage_generator = new WebPageSchemaGenerator();
						$schemas[] = $webpage_generator->generate( $post_id );
						break;
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
	 * @param int $post_id Optional post ID to generate schemas for. Defaults to current post.
	 * @return array<array<string, mixed>>
	 */
	public function get_active_schemas_public( int $post_id = 0 ): array {
		return $this->get_active_schemas( $post_id );
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

