<?php
/**
 * Content JSON Generator - Creates /geo/content/{post_id}.json
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Generates per-content GEO JSON
 */
class ContentJson {

	/**
	 * Extractor instance
	 *
	 * @var Extractor
	 */
	private Extractor $extractor;

	/**
	 * Freshness signals instance
	 *
	 * @var FreshnessSignals
	 */
	private FreshnessSignals $freshness_signals;

	/**
	 * Citation formatter instance
	 *
	 * @var CitationFormatter
	 */
	private CitationFormatter $citation_formatter;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->extractor          = new Extractor();
		$this->freshness_signals  = new FreshnessSignals();
		$this->citation_formatter = new CitationFormatter();
	}

	/**
	 * Generate content JSON for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null Null if post should not be exposed.
	 */
	public function generate( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		// Check if post type is enabled
		if ( ! $this->is_post_type_enabled( $post->post_type ) ) {
			return null;
		}

		// Check if specific post is exposed
		if ( ! $this->is_post_exposed( $post_id ) ) {
			return null;
		}

		return $this->build_data( $post );
	}

	/**
	 * Build content data structure
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string,mixed>
	 */
	private function build_data( \WP_Post $post ): array {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		// Extract data from content
		$extracted = $this->extractor->extract( $post );

		// Build base data
		$data = array(
			'id'       => $post->ID,
			'url'      => get_permalink( $post->ID ),
			'lang'     => $this->get_post_language( $post->ID ),
			'type'     => $post->post_type,
			'title'    => $post->post_title,
			'summary'  => $this->get_clean_summary( $post ),
			'lastmod'  => mysql2date( 'c', $post->post_modified_gmt, false ),
			'hash_id'  => $this->generate_hash_id( $post ),
			'keywords' => $extracted['keywords'],
			'entities' => $extracted['entities'],
		);

		// Claims (from metabox + shortcodes)
		$data['claims'] = $this->get_claims( $post );

		// Citations
		$data['citations'] = $extracted['citations'];

		// Add citations from claims' evidence
		foreach ( $data['claims'] as $claim ) {
			if ( ! empty( $claim['evidence'] ) ) {
				foreach ( $claim['evidence'] as $evidence ) {
					$data['citations'][] = array(
						'url'       => $evidence['url'],
						'title'     => $evidence['title'] ?? '',
						'publisher' => $evidence['publisher'] ?? '',
						'author'    => $evidence['author'] ?? '',
						'accessed'  => $evidence['accessed'] ?? '',
					);
				}
			}
		}

		// FAQ
		$data['faq'] = $extracted['faq'];

		// Policy
		$data['license'] = $geo['license_url'] ?? '';
		$data['usage']   = $this->get_post_usage_policy( $post->ID, $geo );

		// Publisher
		if ( ! empty( $geo['publisher_name'] ) ) {
			$data['publisher'] = array(
				'@type' => 'Organization',
				'name'  => $geo['publisher_name'],
				'url'   => $geo['publisher_url'] ?? home_url( '/' ),
			);

			if ( ! empty( $geo['publisher_logo'] ) ) {
				$data['publisher']['logo'] = $geo['publisher_logo'];
			}
		}

		// Authors
		$data['authors'] = $this->get_authors( $post );

		// NEW AI-FIRST FIELDS

		// Freshness signals
		$data['freshness'] = $this->freshness_signals->get_freshness_data( $post->ID );

		// Citation optimization
		$data['citation_data'] = $this->citation_formatter->format_for_citation( $post->ID );

		// Related endpoints for AI discovery
		$data['related_endpoints'] = $this->get_related_endpoints( $post->ID );

		/**
		 * Filter content JSON payload
		 *
		 * @param array<string,mixed> $data Content data.
		 * @param \WP_Post            $post Post object.
		 */
		return apply_filters( 'fpseo_geo_content_payload', $data, $post );
	}

	/**
	 * Get related AI-first endpoints for this content
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string> Related endpoints.
	 */
	private function get_related_endpoints( int $post_id ): array {
		$base_url = home_url( '/geo/content/' . $post_id );

		return array(
			'qa_pairs'    => $base_url . '/qa.json',
			'chunks'      => $base_url . '/chunks.json',
			'entities'    => $base_url . '/entities.json',
			'authority'   => $base_url . '/authority.json',
			'variants'    => $base_url . '/variants.json',
			'images'      => $base_url . '/images.json',
			'embeddings'  => $base_url . '/embeddings.json',
		);
	}

	/**
	 * Get claims from metabox and shortcodes
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<array{statement:string,confidence:float,evidence:array<array{url:string,title:string,publisher:string,author:string,accessed:string}>}>
	 */
	private function get_claims( \WP_Post $post ): array {
		$claims = array();

		// Get from metabox
		$meta_claims = get_post_meta( $post->ID, '_fp_seo_geo_claims', true );
		if ( is_array( $meta_claims ) ) {
			foreach ( $meta_claims as $claim ) {
				if ( empty( $claim['statement'] ) ) {
					continue;
				}

				$claims[] = $this->format_claim( $claim );
			}
		}

		// Extract from [fp_claim] shortcodes
		$pattern = '/\[fp_claim([^\]]+)\]([^\[]*)\[\/fp_claim\]/';
		preg_match_all( $pattern, $post->post_content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $index => $attrs_str ) {
				$attrs = shortcode_parse_atts( $attrs_str );
				if ( ! empty( $attrs['statement'] ) ) {
					$claim = array(
						'statement'  => $attrs['statement'],
						'confidence' => isset( $attrs['confidence'] ) ? (float) $attrs['confidence'] : $this->get_default_confidence(),
					);

					// Add evidence if present
					if ( ! empty( $attrs['evidence_url'] ) ) {
						$claim['evidence'] = array(
							array(
								'url'       => $attrs['evidence_url'],
								'title'     => $attrs['evidence_title'] ?? '',
								'publisher' => $attrs['publisher'] ?? '',
								'author'    => $attrs['author'] ?? '',
								'accessed'  => $attrs['accessed'] ?? '',
							),
						);
					}

					$claims[] = $claim;
				}
			}
		}

		return $claims;
	}

	/**
	 * Format claim data
	 *
	 * @param array<string,mixed> $raw_claim Raw claim data.
	 * @return array{statement:string,confidence:float,evidence:array<array{url:string,title:string,publisher:string,author:string,accessed:string}>}
	 */
	private function format_claim( array $raw_claim ): array {
		$claim = array(
			'statement'  => $raw_claim['statement'] ?? '',
			'confidence' => isset( $raw_claim['confidence'] ) ? (float) $raw_claim['confidence'] : $this->get_default_confidence(),
			'evidence'   => array(),
		);

		if ( ! empty( $raw_claim['evidence'] ) && is_array( $raw_claim['evidence'] ) ) {
			foreach ( $raw_claim['evidence'] as $evidence ) {
				$claim['evidence'][] = array(
					'url'       => $evidence['url'] ?? '',
					'title'     => $evidence['title'] ?? '',
					'publisher' => $evidence['publisher'] ?? '',
					'author'    => $evidence['author'] ?? '',
					'accessed'  => $evidence['accessed'] ?? '',
				);
			}
		}

		return $claim;
	}

	/**
	 * Get clean summary from content
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_clean_summary( \WP_Post $post ): string {
		$excerpt = $post->post_excerpt;

		if ( empty( $excerpt ) ) {
			$excerpt = wp_strip_all_tags( $post->post_content );
			$excerpt = wp_trim_words( $excerpt, 55, '...' );
		}

		return $excerpt;
	}

	/**
	 * Get authors (post author + co-authors if available)
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<array{name:string,url?:string}>
	 */
	private function get_authors( \WP_Post $post ): array {
		$authors = array();

		// Main author
		$author_id = $post->post_author;
		$author    = get_userdata( $author_id );

		if ( $author ) {
			$authors[] = array(
				'name' => $author->display_name,
				'url'  => get_author_posts_url( $author_id ),
			);
		}

		// Co-authors Plus support
		if ( function_exists( 'get_coauthors' ) ) {
			$coauthors = get_coauthors( $post->ID );
			foreach ( $coauthors as $coauthor ) {
				// Skip main author (already added)
				if ( isset( $coauthor->ID ) && (int) $coauthor->ID === (int) $author_id ) {
					continue;
				}

				$authors[] = array(
					'name' => $coauthor->display_name,
					'url'  => isset( $coauthor->ID ) ? get_author_posts_url( $coauthor->ID ) : '',
				);
			}
		}

		return $authors;
	}

	/**
	 * Generate stable hash ID
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function generate_hash_id( \WP_Post $post ): string {
		$input = home_url( '/' ) . $post->ID . $post->post_modified_gmt;
		return 'sha1:' . sha1( $input );
	}

	/**
	 * Get post language
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_language( int $post_id ): string {
		// Polylang
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			if ( $lang ) {
				return $lang;
			}
		}

		// WPML
		if ( function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( $post_id );
			if ( isset( $lang_info['locale'] ) ) {
				return substr( $lang_info['locale'], 0, 2 );
			}
		}

		// Fallback
		$locale = get_locale();
		return substr( $locale, 0, 2 );
	}

	/**
	 * Get usage policy for post
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $geo     GEO settings.
	 * @return string
	 */
	private function get_post_usage_policy( int $post_id, array $geo ): string {
		// Check if post has "No AI reuse"
		$no_ai_reuse = get_post_meta( $post_id, '_fp_seo_geo_no_ai_reuse', true );
		if ( '1' === $no_ai_reuse ) {
			return 'deny';
		}

		return $geo['ai_usage'] ?? 'allow-with-attribution';
	}

	/**
	 * Check if post type is enabled
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_post_type_enabled( string $post_type ): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		if ( empty( $geo['post_types'] ) || ! is_array( $geo['post_types'] ) ) {
			return false;
		}

		return ! empty( $geo['post_types'][ $post_type ]['expose'] );
	}

	/**
	 * Check if specific post is exposed
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_post_exposed( int $post_id ): bool {
		$expose = get_post_meta( $post_id, '_fp_seo_geo_expose', true );

		// Default to exposed if meta not set
		return '' === $expose || '1' === $expose;
	}

	/**
	 * Get default confidence from settings
	 *
	 * @return float
	 */
	private function get_default_confidence(): float {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		return isset( $geo['default_confidence'] ) ? (float) $geo['default_confidence'] : 0.7;
	}
}

