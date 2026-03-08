<?php
/**
 * Schema GEO Extensions Renderer - Adds ClaimReview, CreativeWork.citation, FAQPage
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Frontend\Contracts\RendererInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\GEO\Extractor;
use function is_singular;
use function wp_json_encode;

/**
 * Extends JSON-LD with GEO-specific schemas
 */
class SchemaGeoRenderer extends AbstractRenderer implements RendererInterface {

	/**
	 * Extractor instance
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Constructor
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		parent::__construct( $hook_manager );
		$this->extractor = new Extractor();
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register(): void {
		$this->add_action( 'wp_head', array( $this, 'output_schema' ), 20 );
	}

	/**
	 * Check if the renderer should render for the current context.
	 *
	 * @param mixed $context Rendering context.
	 * @return bool True if should render, false otherwise.
	 */
	public function should_render( $context = null ): bool {
		return is_singular();
	}

	/**
	 * Render the output.
	 *
	 * @param mixed $context Rendering context (post, term, etc.).
	 * @return string Rendered output.
	 */
	public function render( $context = null ): string {
		ob_start();
		$this->output_schema();
		return ob_get_clean() ?: '';
	}

	/**
	 * Output GEO JSON-LD schemas
	 *
	 * @return void
	 */
	public function output_schema(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$schemas = array();

		// Add CreativeWork with citations
		$creative_work = $this->build_creative_work( $post );
		if ( $creative_work ) {
			$schemas[] = $creative_work;
		}

		// Add ClaimReview for posts with claims
		$claim_reviews = $this->build_claim_reviews( $post );
		$schemas       = array_merge( $schemas, $claim_reviews );

		// Add FAQPage if FAQ present
		$faq_page = $this->build_faq_page( $post );
		if ( $faq_page ) {
			$schemas[] = $faq_page;
		}

		if ( empty( $schemas ) ) {
			return;
		}

		// Output as JSON-LD
		foreach ( $schemas as $schema ) {
			$encoded = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $encoded ) {
				continue;
			}
			echo '<script type="application/ld+json">';
			echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</script>' . "\n";
		}
	}

	/**
	 * Build CreativeWork schema with citations
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>|null
	 */
	private function build_creative_work( \WP_Post $post ): ?array {
		$extracted = $this->extractor->extract( $post );

		if ( empty( $extracted['citations'] ) ) {
			return null;
		}

		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'CreativeWork',
			'@id'      => get_permalink( $post->ID ),
			'name'     => $post->post_title,
			'url'      => get_permalink( $post->ID ),
			'datePublished' => mysql2date( 'c', $post->post_date_gmt, false ),
			'dateModified'  => mysql2date( 'c', $post->post_modified_gmt, false ),
		);

		// Add publisher
		if ( ! empty( $geo['publisher_name'] ) ) {
			$schema['publisher'] = array(
				'@type' => 'Organization',
				'name'  => $geo['publisher_name'],
				'url'   => $geo['publisher_url'] ?? home_url( '/' ),
			);
		}

		// Add author
		$author = get_userdata( $post->post_author );
		if ( $author ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => $author->display_name,
			);
		}

		// Add citations
		$citations = array();
		foreach ( $extracted['citations'] as $citation ) {
			$citations[] = array(
				'@type' => 'CreativeWork',
				'url'   => $citation['url'],
				'name'  => $citation['title'] ?? '',
			);
		}

		$schema['citation'] = $citations;

		return $schema;
	}

	/**
	 * Build ClaimReview schemas for post claims
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<array<string,mixed>>
	 */
	private function build_claim_reviews( \WP_Post $post ): array {
		$claims = get_post_meta( $post->ID, '_fp_seo_geo_claims', true );

		if ( empty( $claims ) || ! is_array( $claims ) ) {
			return array();
		}

		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		$schemas = array();

		foreach ( $claims as $index => $claim ) {
			if ( empty( $claim['statement'] ) ) {
				continue;
			}

			$confidence = $claim['confidence'] ?? 0.7;

			// Map confidence 0-1 to rating 1-5
			$rating = round( 1 + ( $confidence * 4 ) );

			$schema = array(
				'@context'      => 'https://schema.org',
				'@type'         => 'ClaimReview',
				'url'           => get_permalink( $post->ID ) . '#claim-' . $index,
				'claimReviewed' => $claim['statement'],
				'itemReviewed'  => array(
					'@type' => 'CreativeWork',
					'url'   => get_permalink( $post->ID ),
					'name'  => $post->post_title,
				),
			);

			// Add review rating
			$schema['reviewRating'] = array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => 5,
				'worstRating' => 1,
			);

			// Add publisher as author of review
			if ( ! empty( $geo['publisher_name'] ) ) {
				$schema['author'] = array(
					'@type' => 'Organization',
					'name'  => $geo['publisher_name'],
				);
			}

			$schemas[] = $schema;
		}

		return $schemas;
	}

	/**
	 * Build FAQPage schema
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>|null
	 */
	private function build_faq_page( \WP_Post $post ): ?array {
		$extracted = $this->extractor->extract( $post );

		if ( empty( $extracted['faq'] ) ) {
			return null;
		}

		$main_entity = array();

		foreach ( $extracted['faq'] as $faq ) {
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $faq['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $faq['a'],
				),
			);
		}

		return array(
			'@context'    => 'https://schema.org',
			'@type'       => 'FAQPage',
			'mainEntity'  => $main_entity,
		);
	}
}








