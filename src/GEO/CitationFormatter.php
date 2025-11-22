<?php
/**
 * Citation Format Optimizer for AI Engines
 *
 * Formats content to be easily citable by AI engines (Gemini, Claude, OpenAI, Perplexity).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use FP\SEO\Utils\MetadataResolver;
use WP_Post;

/**
 * Optimizes content formatting for AI citations
 */
class CitationFormatter {

	/**
	 * Format content for optimal AI citation
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Citation-optimized data.
	 */
	public function format_for_citation( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		return array(
			'title'             => $this->get_citation_title( $post ),
			'url'               => $this->get_canonical_url( $post ),
			'published_date'    => $this->format_date( $post->post_date_gmt ),
			'updated_date'      => $this->format_date( $post->post_modified_gmt ),
			'author'            => $this->get_author_data( $post ),
			'excerpts'          => $this->extract_citable_excerpts( $post ),
			'key_facts'         => $this->extract_key_facts( $post ),
			'expertise_signals' => $this->calculate_expertise_signals( $post ),
			'citation_context'  => $this->get_citation_context( $post ),
			'related_content'   => $this->get_related_content( $post ),
		);
	}

	/**
	 * Get optimized citation title
	 *
	 * @param WP_Post $post Post object.
	 * @return string Citation title.
	 */
	private function get_citation_title( WP_Post $post ): string {
		// Use MetadataResolver to get SEO title (falls back to content if not set)
		$seo_title = \FP\SEO\Utils\MetadataResolver::resolve_seo_title( $post );

		return sanitize_text_field( $seo_title );
	}

	/**
	 * Get canonical URL
	 *
	 * @param WP_Post $post Post object.
	 * @return string Canonical URL.
	 */
	private function get_canonical_url( WP_Post $post ): string {
		$canonical = get_post_meta( $post->ID, '_fp_seo_canonical', true );

		if ( ! empty( $canonical ) ) {
			return esc_url_raw( $canonical );
		}

		return get_permalink( $post );
	}

	/**
	 * Format date to ISO 8601
	 *
	 * @param string $date MySQL date.
	 * @return string ISO 8601 date.
	 */
	private function format_date( string $date ): string {
		if ( empty( $date ) || '0000-00-00 00:00:00' === $date ) {
			return gmdate( 'c' );
		}

		$timestamp = strtotime( $date . ' UTC' );
		return gmdate( 'c', $timestamp );
	}

	/**
	 * Get author data with credentials
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Author data.
	 */
	private function get_author_data( WP_Post $post ): array {
		$author_id = $post->post_author;
		$author    = get_userdata( $author_id );

		if ( ! $author ) {
			return array();
		}

		return array(
			'name'         => $author->display_name,
			'url'          => get_author_posts_url( $author_id ),
			'bio'          => get_user_meta( $author_id, 'description', true ),
			'credentials'  => $this->get_author_credentials( $author_id ),
			'expertise'    => $this->get_author_expertise( $author_id ),
			'social_proof' => $this->get_author_social_proof( $author_id ),
		);
	}

	/**
	 * Get author credentials
	 *
	 * @param int $author_id Author ID.
	 * @return array<string, mixed> Credentials.
	 */
	private function get_author_credentials( int $author_id ): array {
		return array(
			'title'          => get_user_meta( $author_id, 'fp_author_title', true ) ?: '',
			'certifications' => $this->get_author_certifications( $author_id ),
			'experience_years' => (int) get_user_meta( $author_id, 'fp_author_experience_years', true ),
			'publications'   => (int) count_user_posts( $author_id, 'post', true ),
		);
	}

	/**
	 * Get author certifications
	 *
	 * @param int $author_id Author ID.
	 * @return array<int, string> Certifications.
	 */
	private function get_author_certifications( int $author_id ): array {
		$certs = get_user_meta( $author_id, 'fp_author_certifications', true );

		if ( ! is_array( $certs ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $certs );
	}

	/**
	 * Get author expertise areas
	 *
	 * @param int $author_id Author ID.
	 * @return array<int, string> Expertise areas.
	 */
	private function get_author_expertise( int $author_id ): array {
		$expertise = get_user_meta( $author_id, 'fp_author_expertise', true );

		if ( ! is_array( $expertise ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $expertise );
	}

	/**
	 * Get author social proof
	 *
	 * @param int $author_id Author ID.
	 * @return array<string, mixed> Social proof data.
	 */
	private function get_author_social_proof( int $author_id ): array {
		return array(
			'followers'    => (int) get_user_meta( $author_id, 'fp_author_followers', true ),
			'endorsements' => (int) get_user_meta( $author_id, 'fp_author_endorsements', true ),
		);
	}

	/**
	 * Extract citable excerpts from content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Citable excerpts.
	 */
	private function extract_citable_excerpts( WP_Post $post ): array {
		$content = $post->post_content;

		// Remove shortcodes and HTML
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );

		// Split into paragraphs
		$paragraphs = array_filter( array_map( 'trim', explode( "\n\n", $content ) ) );

		$excerpts = array();

		// Extract first paragraph (often most important)
		if ( ! empty( $paragraphs[0] ) ) {
			$excerpts[] = $this->format_excerpt( $paragraphs[0], 'introduction', $post );
		}

		// Extract paragraphs with key indicators
		foreach ( $paragraphs as $index => $paragraph ) {
			if ( count( $excerpts ) >= 5 ) {
				break; // Limit to 5 excerpts
			}

			// Skip very short paragraphs
			if ( strlen( $paragraph ) < 100 ) {
				continue;
			}

			// Look for key phrases that indicate important content
			$indicators = array(
				'è importante',
				'fondamentale',
				'essenziale',
				'chiave',
				'ricorda',
				'attenzione',
				'nota',
				'migliore pratica',
				'consiglio',
			);

			$is_important = false;
			foreach ( $indicators as $indicator ) {
				if ( stripos( $paragraph, $indicator ) !== false ) {
					$is_important = true;
					break;
				}
			}

			if ( $is_important ) {
				$excerpts[] = $this->format_excerpt( $paragraph, 'key_point', $post );
			}
		}

		return $excerpts;
	}

	/**
	 * Format a single excerpt
	 *
	 * @param string  $text Text content.
	 * @param string  $type Excerpt type.
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Formatted excerpt.
	 */
	private function format_excerpt( string $text, string $type, WP_Post $post ): array {
		// Truncate if too long (optimal for AI: 150-200 chars)
		$text = $this->truncate_excerpt( $text, 200 );

		return array(
			'text'          => $text,
			'type'          => $type,
			'confidence'    => $this->calculate_excerpt_confidence( $text ),
			'fact_checked'  => $this->is_fact_checked( $post ),
			'sources'       => $this->extract_sources_from_text( $text, $post ),
			'keywords'      => $this->extract_keywords_from_text( $text ),
		);
	}

	/**
	 * Truncate excerpt intelligently
	 *
	 * @param string $text      Text to truncate.
	 * @param int    $max_chars Maximum characters.
	 * @return string Truncated text.
	 */
	private function truncate_excerpt( string $text, int $max_chars ): string {
		if ( mb_strlen( $text ) <= $max_chars ) {
			return $text;
		}

		// Truncate at sentence boundary
		$truncated = mb_substr( $text, 0, $max_chars );

		// Find last period
		$last_period = mb_strrpos( $truncated, '.' );

		if ( $last_period !== false && $last_period > $max_chars * 0.7 ) {
			return mb_substr( $truncated, 0, $last_period + 1 );
		}

		// Truncate at word boundary
		$last_space = mb_strrpos( $truncated, ' ' );

		if ( $last_space !== false ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}

		return $truncated . '...';
	}

	/**
	 * Calculate excerpt confidence score
	 *
	 * @param string $text Excerpt text.
	 * @return float Confidence score (0-1).
	 */
	private function calculate_excerpt_confidence( string $text ): float {
		$score = 0.5; // Base score

		// Longer = more informative
		$length = mb_strlen( $text );
		if ( $length > 100 ) {
			$score += 0.2;
		}

		// Contains numbers/data = more credible
		if ( preg_match( '/\d+/', $text ) ) {
			$score += 0.1;
		}

		// Contains specific terms = more authoritative
		if ( preg_match( '/(secondo|studi|ricerca|dati|statistiche)/i', $text ) ) {
			$score += 0.2;
		}

		return min( 1.0, $score );
	}

	/**
	 * Check if post is fact-checked
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if fact-checked.
	 */
	private function is_fact_checked( WP_Post $post ): bool {
		return (bool) get_post_meta( $post->ID, '_fp_seo_fact_checked', true );
	}

	/**
	 * Extract sources from text
	 *
	 * @param string  $text Text content.
	 * @param WP_Post $post Post object.
	 * @return array<int, string> Source URLs.
	 */
	private function extract_sources_from_text( string $text, WP_Post $post ): array {
		// Get post-level sources
		$sources = get_post_meta( $post->ID, '_fp_seo_sources', true );

		if ( ! is_array( $sources ) ) {
			return array();
		}

		return array_map( 'esc_url_raw', array_slice( $sources, 0, 3 ) );
	}

	/**
	 * Extract keywords from text
	 *
	 * @param string $text Text content.
	 * @return array<int, string> Keywords.
	 */
	private function extract_keywords_from_text( string $text ): array {
		// Simple keyword extraction (can be enhanced with NLP)
		$words = str_word_count( strtolower( $text ), 1, 'àèéìòù' );

		// Remove common words
		$stopwords = array( 'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'un', 'una', 'uno', 'che', 'e', 'è', 'sono', 'del', 'della', 'dei', 'delle' );
		$words = array_diff( $words, $stopwords );

		// Count frequencies
		$word_counts = array_count_values( $words );

		// Sort by frequency
		arsort( $word_counts );

		// Return top 5
		return array_slice( array_keys( $word_counts ), 0, 5 );
	}

	/**
	 * Extract key facts from content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Key facts.
	 */
	private function extract_key_facts( WP_Post $post ): array {
		$facts = get_post_meta( $post->ID, '_fp_seo_key_facts', true );

		if ( ! is_array( $facts ) ) {
			return array();
		}

		return array_map( function ( $fact ) {
			return array(
				'claim'         => sanitize_text_field( $fact['claim'] ?? '' ),
				'evidence'      => esc_url_raw( $fact['evidence'] ?? '' ),
				'date_verified' => $this->format_date( $fact['date_verified'] ?? gmdate( 'Y-m-d H:i:s' ) ),
				'confidence'    => min( 1.0, max( 0.0, (float) ( $fact['confidence'] ?? 0.8 ) ) ),
			);
		}, $facts );
	}

	/**
	 * Calculate expertise signals
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, float> Expertise signals (0-1 scores).
	 */
	private function calculate_expertise_signals( WP_Post $post ): array {
		return array(
			'author_authority'  => $this->calculate_author_authority( $post ),
			'content_freshness' => $this->calculate_content_freshness( $post ),
			'fact_density'      => $this->calculate_fact_density( $post ),
			'citation_quality'  => $this->calculate_citation_quality( $post ),
			'depth_score'       => $this->calculate_content_depth( $post ),
		);
	}

	/**
	 * Calculate author authority score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Authority score (0-1).
	 */
	private function calculate_author_authority( WP_Post $post ): float {
		$author_id = $post->post_author;

		$score = 0.5; // Base score

		// Publications count
		$publications = count_user_posts( $author_id, 'post', true );
		if ( $publications > 50 ) {
			$score += 0.2;
		} elseif ( $publications > 20 ) {
			$score += 0.1;
		}

		// Has certifications
		$certs = $this->get_author_certifications( $author_id );
		if ( ! empty( $certs ) ) {
			$score += 0.2;
		}

		// Experience years
		$experience = (int) get_user_meta( $author_id, 'fp_author_experience_years', true );
		if ( $experience > 10 ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Calculate content freshness score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Freshness score (0-1).
	 */
	private function calculate_content_freshness( WP_Post $post ): float {
		$days_since_update = ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS;

		if ( $days_since_update < 30 ) {
			return 1.0;
		}

		if ( $days_since_update < 90 ) {
			return 0.9;
		}

		if ( $days_since_update < 180 ) {
			return 0.7;
		}

		if ( $days_since_update < 365 ) {
			return 0.5;
		}

		return 0.3;
	}

	/**
	 * Calculate fact density (facts per 1000 words)
	 *
	 * @param WP_Post $post Post object.
	 * @return float Fact density score (0-1).
	 */
	private function calculate_fact_density( WP_Post $post ): float {
		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );

		if ( $word_count === 0 || $word_count < 1 ) {
			return 0.0;
		}

		// Count numbers (indicator of facts/data)
		preg_match_all( '/\d+/', $content, $matches );
		$number_count = count( $matches[0] ?? array() );

		$density = ( $number_count / max( 1, $word_count ) ) * 1000;

		// Normalize to 0-1 (assume 10 numbers per 1000 words is good)
		return min( 1.0, $density / 10 );
	}

	/**
	 * Calculate citation quality score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Citation quality score (0-1).
	 */
	private function calculate_citation_quality( WP_Post $post ): float {
		$sources = get_post_meta( $post->ID, '_fp_seo_sources', true );

		if ( ! is_array( $sources ) || empty( $sources ) ) {
			return 0.3; // Low score for no sources
		}

		$count = count( $sources );

		if ( $count >= 10 ) {
			return 1.0;
		}

		if ( $count >= 5 ) {
			return 0.8;
		}

		if ( $count >= 3 ) {
			return 0.6;
		}

		return 0.4;
	}

	/**
	 * Calculate content depth score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Depth score (0-1).
	 */
	private function calculate_content_depth( WP_Post $post ): float {
		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );

		// Score based on word count
		if ( $word_count >= 2000 ) {
			return 1.0;
		}

		if ( $word_count >= 1500 ) {
			return 0.9;
		}

		if ( $word_count >= 1000 ) {
			return 0.7;
		}

		if ( $word_count >= 500 ) {
			return 0.5;
		}

		return 0.3;
	}

	/**
	 * Get citation context
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Context data.
	 */
	private function get_citation_context( WP_Post $post ): array {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		return array(
			'categories' => $categories,
			'tags'       => array_slice( $tags, 0, 10 ),
			'post_type'  => $post->post_type,
		);
	}

	/**
	 * Get related content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, string>> Related posts.
	 */
	private function get_related_content( WP_Post $post ): array {
		$categories = wp_get_post_categories( $post->ID );

		if ( empty( $categories ) ) {
			return array();
		}

		$related = get_posts( array(
			'category__in'        => $categories,
			'post__not_in'        => array( $post->ID ),
			'posts_per_page'      => 5,
			'ignore_sticky_posts' => true,
		) );

		return array_map( function ( $related_post ) {
			return array(
				'title' => $related_post->post_title,
				'url'   => get_permalink( $related_post ),
			);
		}, $related );
	}

}

