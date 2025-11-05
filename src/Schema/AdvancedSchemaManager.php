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

use FP\SEO\Utils\Cache;
use FP\SEO\Utils\PerformanceConfig;

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
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'output_schema_markup' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_schema_menu' ) );
		add_action( 'wp_ajax_fp_seo_generate_schema', array( $this, 'ajax_generate_schema' ) );
		add_action( 'wp_ajax_fp_seo_preview_schema', array( $this, 'ajax_preview_schema' ) );
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
			$schemas[] = $this->get_organization_schema();

			// WebSite schema (global)
			$schemas[] = $this->get_website_schema();

			// Page-specific schemas
			if ( is_singular() ) {
				$post_id = get_the_ID();
				$post_type = get_post_type( $post_id );

				// Article/BlogPosting schema
				if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
					$schemas[] = $this->get_article_schema( $post_id );
				}

				// Product schema for WooCommerce
				if ( class_exists( 'WooCommerce' ) && 'product' === $post_type ) {
					$schemas[] = $this->get_product_schema( $post_id );
				}

				// FAQ schema if present
				$faq_schema = $this->get_faq_schema( $post_id );
				if ( ! empty( $faq_schema ) ) {
					$schemas[] = $faq_schema;
				}

				// HowTo schema if present
				$howto_schema = $this->get_howto_schema( $post_id );
				if ( ! empty( $howto_schema ) ) {
					$schemas[] = $howto_schema;
				}
			}

			// Breadcrumb schema
			$breadcrumb_schema = $this->get_breadcrumb_schema();
			if ( ! empty( $breadcrumb_schema ) ) {
				$schemas[] = $breadcrumb_schema;
			}

			return array_filter( $schemas );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Get Organization schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_organization_schema(): array {
		$options = get_option( 'fp_seo_performance', array() );
		$org_data = $options['schema']['organization'] ?? array();

		return array(
			'@context' => 'https://schema.org',
			'@type' => 'Organization',
			'name' => $org_data['name'] ?? get_bloginfo( 'name' ),
			'url' => home_url(),
			'logo' => array(
				'@type' => 'ImageObject',
				'url' => $org_data['logo'] ?? get_custom_logo_url(),
			),
			'description' => $org_data['description'] ?? get_bloginfo( 'description' ),
			'address' => $this->get_address_schema( $org_data ),
			'contactPoint' => $this->get_contact_point_schema( $org_data ),
			'sameAs' => $this->get_social_links( $org_data ),
		);
	}

	/**
	 * Get WebSite schema.
	 *
	 * @return array<string, mixed>
	 */
	private function get_website_schema(): array {
		$search_action = array(
			'@type' => 'SearchAction',
			'target' => array(
				'@type' => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);

		return array(
			'@context' => 'https://schema.org',
			'@type' => 'WebSite',
			'name' => get_bloginfo( 'name' ),
			'url' => home_url(),
			'description' => get_bloginfo( 'description' ),
			'potentialAction' => $search_action,
		);
	}

	/**
	 * Get Article schema for posts.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_article_schema( int $post_id ): array {
		$post = get_post( $post_id );
		$author = get_userdata( $post->post_author );
		$categories = get_the_category( $post_id );
		$tags = get_the_tags( $post_id );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Article',
			'headline' => get_the_title( $post_id ),
			'url' => get_permalink( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified' => get_the_modified_date( 'c', $post_id ),
			'author' => array(
				'@type' => 'Person',
				'name' => $author->display_name,
				'url' => get_author_posts_url( $author->ID ),
			),
			'publisher' => array(
				'@type' => 'Organization',
				'name' => get_bloginfo( 'name' ),
				'logo' => array(
					'@type' => 'ImageObject',
					'url' => get_custom_logo_url(),
				),
			),
		);

		// Add featured image
		$featured_image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $featured_image ) {
			$schema['image'] = array(
				'@type' => 'ImageObject',
				'url' => $featured_image,
				'width' => 1200,
				'height' => 630,
			);
		}

		// Add excerpt
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add categories
		if ( ! empty( $categories ) ) {
			$schema['articleSection'] = array();
			foreach ( $categories as $category ) {
				$schema['articleSection'][] = $category->name;
			}
		}

		// Add keywords
		if ( ! empty( $tags ) ) {
			$schema['keywords'] = array();
			foreach ( $tags as $tag ) {
				$schema['keywords'][] = $tag->name;
			}
		}

		// Add word count
		$word_count = str_word_count( strip_tags( $post->post_content ) );
		if ( $word_count > 0 ) {
			$schema['wordCount'] = $word_count;
		}

		return $schema;
	}

	/**
	 * Get Product schema for WooCommerce products.
	 *
	 * @param int $post_id Product ID.
	 * @return array<string, mixed>
	 */
	private function get_product_schema( int $post_id ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return array();
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Product',
			'name' => $product->get_name(),
			'description' => $product->get_description(),
			'url' => get_permalink( $post_id ),
			'sku' => $product->get_sku(),
		);

		// Add images
		$image_ids = $product->get_gallery_image_ids();
		if ( ! empty( $image_ids ) ) {
			$schema['image'] = array();
			foreach ( $image_ids as $image_id ) {
				$image_url = wp_get_attachment_image_url( $image_id, 'full' );
				if ( $image_url ) {
					$schema['image'][] = $image_url;
				}
			}
		}

		// Add price
		if ( $product->get_price() ) {
			$schema['offers'] = array(
				'@type' => 'Offer',
				'price' => $product->get_price(),
				'priceCurrency' => get_woocommerce_currency(),
				'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
			);
		}

		// Add reviews
		$reviews = get_comments( array(
			'post_id' => $post_id,
			'status' => 'approve',
			'type' => 'review',
		) );

		if ( ! empty( $reviews ) ) {
			$schema['aggregateRating'] = array(
				'@type' => 'AggregateRating',
				'ratingValue' => $product->get_average_rating(),
				'reviewCount' => count( $reviews ),
			);

			$schema['review'] = array();
			foreach ( array_slice( $reviews, 0, 5 ) as $review ) {
				$schema['review'][] = array(
					'@type' => 'Review',
					'author' => array(
						'@type' => 'Person',
						'name' => $review->comment_author,
					),
					'datePublished' => $review->comment_date,
					'description' => $review->comment_content,
					'reviewRating' => array(
						'@type' => 'Rating',
						'ratingValue' => get_comment_meta( $review->comment_ID, 'rating', true ),
					),
				);
			}
		}

		return $schema;
	}

	/**
	 * Get FAQ schema from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function get_faq_schema( int $post_id ): ?array {
		$faq_questions = get_post_meta( $post_id, '_fp_seo_faq_questions', true );
		
		if ( empty( $faq_questions ) || ! is_array( $faq_questions ) ) {
			return null;
		}

		$main_entity = array();
		foreach ( $faq_questions as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$main_entity[] = array(
				'@type' => 'Question',
				'name' => sanitize_text_field( $faq['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text' => wp_kses_post( $faq['answer'] ),
				),
			);
		}

		if ( empty( $main_entity ) ) {
			return null;
		}

		return array(
			'@context' => 'https://schema.org',
			'@type' => 'FAQPage',
			'mainEntity' => $main_entity,
		);
	}

	/**
	 * Get HowTo schema from post content.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function get_howto_schema( int $post_id ): ?array {
		$howto_data = get_post_meta( $post_id, '_fp_seo_howto', true );
		
		if ( empty( $howto_data ) || ! is_array( $howto_data ) ) {
			return null;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'HowTo',
			'name' => sanitize_text_field( $howto_data['name'] ?? get_the_title( $post_id ) ),
			'description' => wp_kses_post( $howto_data['description'] ?? get_the_excerpt( $post_id ) ),
		);

		// Add steps
		if ( ! empty( $howto_data['steps'] ) && is_array( $howto_data['steps'] ) ) {
			$schema['step'] = array();
			foreach ( $howto_data['steps'] as $index => $step ) {
				$schema['step'][] = array(
					'@type' => 'HowToStep',
					'position' => $index + 1,
					'name' => sanitize_text_field( $step['name'] ?? '' ),
					'text' => wp_kses_post( $step['text'] ?? '' ),
					'url' => ! empty( $step['url'] ) ? esc_url( $step['url'] ) : null,
				);
			}
		}

		// Add total time
		if ( ! empty( $howto_data['total_time'] ) ) {
			$schema['totalTime'] = sanitize_text_field( $howto_data['total_time'] );
		}

		// Add tools
		if ( ! empty( $howto_data['tools'] ) && is_array( $howto_data['tools'] ) ) {
			$schema['tool'] = array();
			foreach ( $howto_data['tools'] as $tool ) {
				$schema['tool'][] = array(
					'@type' => 'HowToTool',
					'name' => sanitize_text_field( $tool ),
				);
			}
		}

		return $schema;
	}

	/**
	 * Get Breadcrumb schema.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_breadcrumb_schema(): ?array {
		$breadcrumbs = $this->get_breadcrumb_items();
		
		if ( empty( $breadcrumbs ) ) {
			return null;
		}

		$list_items = array();
		foreach ( $breadcrumbs as $index => $breadcrumb ) {
			$list_items[] = array(
				'@type' => 'ListItem',
				'position' => $index + 1,
				'name' => $breadcrumb['name'],
				'item' => $breadcrumb['url'],
			);
		}

		return array(
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $list_items,
		);
	}

	/**
	 * Get breadcrumb items for current page.
	 *
	 * @return array<array{name: string, url: string}>
	 */
	private function get_breadcrumb_items(): array {
		$breadcrumbs = array();

		// Home
		$breadcrumbs[] = array(
			'name' => __( 'Home', 'fp-seo-performance' ),
			'url' => home_url(),
		);

		if ( is_singular() ) {
			$post = get_post();
			$post_type = get_post_type();

			// Add post type archive if exists
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && $post_type_obj->has_archive ) {
				$breadcrumbs[] = array(
					'name' => $post_type_obj->labels->name,
					'url' => get_post_type_archive_link( $post_type ),
				);
			}

			// Add categories for posts
			if ( 'post' === $post_type ) {
				$categories = get_the_category();
				if ( ! empty( $categories ) ) {
					$category = $categories[0];
					$breadcrumbs[] = array(
						'name' => $category->name,
						'url' => get_category_link( $category->term_id ),
					);
				}
			}

			// Add parent pages
			if ( $post->post_parent ) {
				$parent_pages = get_post_ancestors( $post->ID );
				$parent_pages = array_reverse( $parent_pages );
				
				foreach ( $parent_pages as $parent_id ) {
					$breadcrumbs[] = array(
						'name' => get_the_title( $parent_id ),
						'url' => get_permalink( $parent_id ),
					);
				}
			}

			// Current page
			$breadcrumbs[] = array(
				'name' => get_the_title(),
				'url' => get_permalink(),
			);
		} elseif ( is_category() ) {
			$category = get_queried_object();
			$breadcrumbs[] = array(
				'name' => $category->name,
				'url' => get_category_link( $category->term_id ),
			);
		} elseif ( is_tag() ) {
			$tag = get_queried_object();
			$breadcrumbs[] = array(
				'name' => $tag->name,
				'url' => get_tag_link( $tag->term_id ),
			);
		}

		return $breadcrumbs;
	}

	/**
	 * Get address schema.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string, mixed>|null
	 */
	private function get_address_schema( array $org_data ): ?array {
		if ( empty( $org_data['address'] ) ) {
			return null;
		}

		$address = $org_data['address'];
		
		return array(
			'@type' => 'PostalAddress',
			'streetAddress' => $address['street'] ?? '',
			'addressLocality' => $address['city'] ?? '',
			'addressRegion' => $address['state'] ?? '',
			'postalCode' => $address['zip'] ?? '',
			'addressCountry' => $address['country'] ?? '',
		);
	}

	/**
	 * Get contact point schema.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string, mixed>|null
	 */
	private function get_contact_point_schema( array $org_data ): ?array {
		if ( empty( $org_data['contact'] ) ) {
			return null;
		}

		$contact = $org_data['contact'];
		
		return array(
			'@type' => 'ContactPoint',
			'telephone' => $contact['phone'] ?? '',
			'email' => $contact['email'] ?? '',
			'contactType' => 'customer service',
		);
	}

	/**
	 * Get social links.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string>|null
	 */
	private function get_social_links( array $org_data ): ?array {
		if ( empty( $org_data['social'] ) || ! is_array( $org_data['social'] ) ) {
			return null;
		}

		return array_filter( $org_data['social'] );
	}

	/**
	 * Render Schema management page.
	 */
	public function render_schema_page(): void {
		?>
		<div class="wrap fp-seo-schema-wrap">
			<h1><?php esc_html_e( 'Schema Markup Manager', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Genera e gestisci lo Schema Markup (JSON-LD) per aiutare i motori di ricerca a capire meglio i tuoi contenuti', 'fp-seo-performance' ); ?></p>

			<!-- Banner introduttivo con guida -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">🏗️</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Cos\'è lo Schema Markup?', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'Lo Schema Markup è un codice che aiuta i motori di ricerca a comprendere meglio il tuo contenuto e mostrare risultati più ricchi (rich snippets) nelle SERP:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li>⭐ <strong>Rich Snippets:</strong> Recensioni con stelle, prezzi, disponibilità</li>
						<li>📰 <strong>Articoli:</strong> Immagine, data, autore nelle ricerche</li>
						<li>❓ <strong>FAQ:</strong> Domande e risposte espandibili</li>
						<li>🏢 <strong>Business:</strong> Indirizzo, orari, contatti</li>
						<li>🎯 <strong>Breadcrumb:</strong> Percorso di navigazione nei risultati</li>
					</ul>
				</div>
			</div>
			
			<div class="fp-seo-schema-dashboard">
				<!-- Info Box -->
				<div class="fp-seo-info-box">
					<div class="fp-seo-info-icon">ℹ️</div>
					<div class="fp-seo-info-content">
						<h3><?php esc_html_e( 'Schema Automatici Attivi', 'fp-seo-performance' ); ?></h3>
						<p><?php esc_html_e( 'Il plugin genera automaticamente questi schema per il tuo sito:', 'fp-seo-performance' ); ?></p>
						<ul>
							<li>✓ <strong>Organization:</strong> Informazioni sulla tua azienda/sito</li>
							<li>✓ <strong>WebSite:</strong> Dati del sito + ricerca interna</li>
							<li>✓ <strong>Article:</strong> Per post e pagine</li>
							<li>✓ <strong>BreadcrumbList:</strong> Navigazione gerarchica</li>
							<li>✓ <strong>Product:</strong> Se usi WooCommerce</li>
						</ul>
					</div>
				</div>

				<div class="fp-seo-schema-stats">
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">✅</div>
						<div class="fp-seo-stat-content">
							<span class="fp-seo-stat-number"><?php echo count( $this->get_active_schemas() ); ?></span>
							<h3><?php esc_html_e( 'Schema Attivi', 'fp-seo-performance' ); ?></h3>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Schema attualmente generati su questa pagina', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📋</div>
						<div class="fp-seo-stat-content">
							<span class="fp-seo-stat-number"><?php echo count( self::SCHEMA_TYPES ); ?></span>
							<h3><?php esc_html_e( 'Tipi Disponibili', 'fp-seo-performance' ); ?></h3>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Tipologie di schema supportate dal plugin', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card fp-seo-stat-card-highlight">
						<div class="fp-seo-stat-icon">🔧</div>
						<div class="fp-seo-stat-content">
							<h3><?php esc_html_e( 'Test Schema', 'fp-seo-performance' ); ?></h3>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Verifica il tuo Schema', 'fp-seo-performance' ); ?></p>
							<a href="https://search.google.com/test/rich-results" target="_blank" class="button button-secondary">
								<span class="dashicons dashicons-external"></span>
								<?php esc_html_e( 'Google Rich Results Test', 'fp-seo-performance' ); ?>
							</a>
						</div>
					</div>
				</div>

				<div class="fp-seo-schema-generator">
					<div class="fp-seo-generator-header">
						<h2><?php esc_html_e( 'Schema Generator', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-generator-desc"><?php esc_html_e( 'Genera Schema Markup personalizzati in formato JSON-LD', 'fp-seo-performance' ); ?></p>
					</div>
					<form id="fp-seo-schema-form">
						<div class="fp-seo-form-group">
							<label for="schema-type">
								<?php esc_html_e( 'Tipo di Schema', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Seleziona il tipo di schema che vuoi generare. Ogni tipo ha proprietà specifiche richieste da Google.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<select id="schema-type" name="schema_type">
								<?php foreach ( self::SCHEMA_TYPES as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="fp-seo-field-help"><?php esc_html_e( 'I più usati: Article (blog), Product (e-commerce), FAQPage (FAQ), LocalBusiness (azienda locale)', 'fp-seo-performance' ); ?></p>
						</div>
						
						<div class="fp-seo-form-group">
							<label for="schema-data">
								<?php esc_html_e( 'Dati Schema (JSON)', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Inserisci i dati dello schema in formato JSON. Ogni tipo di schema ha proprietà specifiche richieste.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<textarea id="schema-data" name="schema_data" rows="15" placeholder='<?php echo esc_attr( '{\n  "name": "Nome Articolo",\n  "description": "Descrizione dell\'articolo",\n  "author": {\n    "@type": "Person",\n    "name": "Nome Autore"\n  },\n  "datePublished": "2025-11-03"\n}' ); ?>'></textarea>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Inserisci i dati in formato JSON valido. Non includere @context e @type (vengono aggiunti automaticamente).', 'fp-seo-performance' ); ?></p>
						</div>

						<!-- Examples accordions -->
						<div class="fp-seo-examples-section">
							<h3><?php esc_html_e( '📋 Esempi Schema Comuni', 'fp-seo-performance' ); ?></h3>
							
							<details class="fp-seo-example-accordion">
								<summary><strong>Article</strong> - Per articoli di blog</summary>
								<pre class="fp-seo-code-example">{
  "headline": "Titolo dell'articolo",
  "description": "Descrizione breve",
  "image": "https://tuosito.com/immagine.jpg",
  "datePublished": "2025-11-03",
  "dateModified": "2025-11-03",
  "author": {
    "@type": "Person",
    "name": "Nome Autore"
  }
}</pre>
							</details>

							<details class="fp-seo-example-accordion">
								<summary><strong>FAQPage</strong> - Per pagine con FAQ</summary>
								<pre class="fp-seo-code-example">{
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Qual è la domanda?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Questa è la risposta alla domanda."
      }
    }
  ]
}</pre>
							</details>

							<details class="fp-seo-example-accordion">
								<summary><strong>Product</strong> - Per prodotti e-commerce</summary>
								<pre class="fp-seo-code-example">{
  "name": "Nome Prodotto",
  "description": "Descrizione del prodotto",
  "image": "https://tuosito.com/prodotto.jpg",
  "offers": {
    "@type": "Offer",
    "price": "99.99",
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock"
  }
}</pre>
							</details>

							<details class="fp-seo-example-accordion">
								<summary><strong>LocalBusiness</strong> - Per attività locali</summary>
								<pre class="fp-seo-code-example">{
  "name": "Nome Attività",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Via Roma 123",
    "addressLocality": "Milano",
    "postalCode": "20100",
    "addressCountry": "IT"
  },
  "telephone": "+39-02-1234567",
  "openingHours": "Mo-Fr 09:00-18:00"
}</pre>
							</details>
						</div>
						
						<div class="fp-seo-form-actions">
							<button type="button" id="fp-seo-generate-schema" class="button button-primary button-hero">
								<span class="dashicons dashicons-admin-tools"></span>
								<?php esc_html_e( 'Genera Schema', 'fp-seo-performance' ); ?>
							</button>
							<button type="button" id="fp-seo-preview-schema" class="button button-secondary">
								<span class="dashicons dashicons-visibility"></span>
								<?php esc_html_e( 'Anteprima', 'fp-seo-performance' ); ?>
							</button>
						</div>
					</form>
				</div>

				<div id="fp-seo-schema-preview" class="fp-seo-schema-preview" style="display: none;">
					<h3><?php esc_html_e( 'Schema Preview', 'fp-seo-performance' ); ?></h3>
					<pre id="fp-seo-schema-output"></pre>
				</div>
			</div>
		</div>

		<style>
		/* Container principale */
		.fp-seo-schema-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-schema-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		/* Banner introduttivo (riuso stili da Content Optimizer) */
		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 8px 0;
			font-size: 14px;
			opacity: 0.9;
		}

		/* Info Box */
		.fp-seo-info-box {
			background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
			border-left: 4px solid #0ea5e9;
			padding: 24px;
			margin-bottom: 24px;
			border-radius: 12px;
			display: flex;
			gap: 20px;
		}

		.fp-seo-info-icon {
			font-size: 36px;
			line-height: 1;
		}

		.fp-seo-info-content h3 {
			margin: 0 0 12px;
			font-size: 18px;
			color: #075985;
		}

		.fp-seo-info-content p {
			margin: 0 0 12px;
			color: #0c4a6e;
			font-size: 14px;
		}

		.fp-seo-info-content ul {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-info-content ul li {
			padding: 4px 0;
			color: #0c4a6e;
			font-size: 14px;
		}
		
		/* Dashboard */
		.fp-seo-schema-dashboard {
			max-width: 1200px;
		}
		
		/* Stats Cards */
		.fp-seo-schema-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
			gap: 20px;
			margin: 24px 0 32px;
		}
		
		.fp-seo-stat-card {
			background: white;
			padding: 24px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			transition: all 0.3s ease;
		}

		.fp-seo-stat-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 8px 12px rgba(0,0,0,0.1);
		}

		.fp-seo-stat-card-highlight {
			border-color: #2563eb;
			background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
		}
		
		.fp-seo-stat-icon {
			font-size: 32px;
			margin-bottom: 12px;
		}

		.fp-seo-stat-content {}
		
		.fp-seo-stat-card h3 {
			margin: 8px 0;
			color: #374151;
			font-size: 15px;
			font-weight: 600;
		}

		.fp-seo-stat-desc {
			margin: 8px 0 12px;
			color: #6b7280;
			font-size: 13px;
		}
		
		.fp-seo-stat-number {
			font-size: 42px;
			font-weight: 700;
			color: #2563eb;
			line-height: 1;
			display: block;
		}

		.fp-seo-stat-card .button {
			margin-top: 12px;
			width: 100%;
			justify-content: center;
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}
		
		/* Schema Generator */
		.fp-seo-schema-generator {
			background: #fff;
			padding: 32px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			margin: 24px 0;
		}

		.fp-seo-generator-header {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e5e7eb;
		}

		.fp-seo-generator-header h2 {
			margin: 0 0 8px;
			font-size: 22px;
			color: #1f2937;
		}

		.fp-seo-generator-desc {
			margin: 0;
			color: #6b7280;
			font-size: 14px;
		}
		
		/* Form */
		.fp-seo-form-group {
			margin-bottom: 28px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 15px;
			color: #374151;
		}

		.fp-seo-tooltip {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			font-size: 14px;
			opacity: 0.7;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip:hover {
			opacity: 1;
		}

		.fp-seo-field-help {
			margin: 8px 0 0;
			font-size: 13px;
			color: #6b7280;
			font-style: italic;
		}
		
		.fp-seo-form-group select,
		.fp-seo-form-group textarea {
			width: 100%;
			padding: 12px 16px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			font-size: 14px;
			transition: all 0.3s ease;
			font-family: 'Courier New', monospace;
		}

		.fp-seo-form-group select:focus,
		.fp-seo-form-group textarea:focus {
			outline: none;
			border-color: #2563eb;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
		}

		.fp-seo-form-group textarea {
			resize: vertical;
			min-height: 200px;
		}

		/* Examples Section */
		.fp-seo-examples-section {
			background: #f9fafb;
			padding: 20px;
			border-radius: 8px;
			margin: 24px 0;
			border: 1px solid #e5e7eb;
		}

		.fp-seo-examples-section h3 {
			margin: 0 0 16px;
			font-size: 16px;
			color: #374151;
		}

		.fp-seo-example-accordion {
			background: white;
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			margin-bottom: 12px;
			overflow: hidden;
		}

		.fp-seo-example-accordion summary {
			padding: 12px 16px;
			cursor: pointer;
			font-size: 14px;
			color: #374151;
			user-select: none;
			transition: background 0.2s;
		}

		.fp-seo-example-accordion summary:hover {
			background: #f3f4f6;
		}

		.fp-seo-example-accordion[open] summary {
			border-bottom: 1px solid #e5e7eb;
			background: #f9fafb;
		}

		.fp-seo-code-example {
			margin: 0;
			padding: 16px;
			background: #1f2937;
			color: #f3f4f6;
			font-size: 12px;
			line-height: 1.6;
			overflow-x: auto;
			font-family: 'Courier New', monospace;
		}
		
		/* Form Actions */
		.fp-seo-form-actions {
			display: flex;
			gap: 12px;
			align-items: center;
		}

		.button-hero {
			font-size: 16px !important;
			padding: 14px 32px !important;
			height: auto !important;
			display: inline-flex !important;
			align-items: center !important;
			gap: 8px !important;
		}

		.button-hero .dashicons {
			font-size: 20px;
			width: 20px;
			height: 20px;
		}

		.button-secondary .dashicons {
			width: 18px;
			height: 18px;
			font-size: 18px;
		}
		
		/* Schema Preview */
		.fp-seo-schema-preview {
			background: #f9fafb;
			padding: 24px;
			border-radius: 12px;
			border: 2px solid #e5e7eb;
			margin-top: 24px;
		}

		.fp-seo-schema-preview h3 {
			margin: 0 0 16px;
			font-size: 18px;
			color: #374151;
		}
		
		.fp-seo-schema-preview pre {
			background: #1f2937;
			color: #f3f4f6;
			padding: 20px;
			border-radius: 8px;
			overflow-x: auto;
			font-size: 13px;
			line-height: 1.6;
			margin: 0;
			font-family: 'Courier New', monospace;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('#fp-seo-generate-schema').on('click', function() {
				var schemaType = $('#schema-type').val();
				var schemaData = $('#schema-data').val();
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_generate_schema',
						schema_type: schemaType,
						schema_data: schemaData,
						nonce: '<?php echo wp_create_nonce( 'fp_seo_schema_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$('#fp-seo-schema-output').text(JSON.stringify(response.data, null, 2));
							$('#fp-seo-schema-preview').show();
						} else {
							alert('Error: ' + response.data);
						}
					}
				});
			});
			
			$('#fp-seo-preview-schema').on('click', function() {
				$('#fp-seo-schema-preview').toggle();
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for schema generation.
	 */
	public function ajax_generate_schema(): void {
		check_ajax_referer( 'fp_seo_schema_nonce', 'nonce' );

		$schema_type = sanitize_text_field( $_POST['schema_type'] ?? '' );
		$schema_data = wp_unslash( $_POST['schema_data'] ?? '' );

		if ( empty( $schema_type ) || ! array_key_exists( $schema_type, self::SCHEMA_TYPES ) ) {
			wp_send_json_error( 'Invalid schema type' );
		}

		$data = json_decode( $schema_data, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( 'Invalid JSON data' );
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => $schema_type,
		);

		$schema = array_merge( $schema, $data );

		wp_send_json_success( $schema );
	}

	/**
	 * AJAX handler for schema preview.
	 */
	public function ajax_preview_schema(): void {
		check_ajax_referer( 'fp_seo_schema_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$schemas = $this->get_active_schemas();
		wp_send_json_success( $schemas );
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
	
	$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
	
	return $logo_url ? (string) $logo_url : '';
}