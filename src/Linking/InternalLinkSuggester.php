<?php
/**
 * Internal Link Suggester
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Linking;

/**
 * Suggests internal links based on content analysis
 */
class InternalLinkSuggester {

	/**
	 * Get link suggestions for a post
	 *
	 * @param int $post_id Current post ID.
	 * @return array<array{post_id:int,title:string,url:string,relevance:float,anchor:string}>
	 */
	public function get_suggestions( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		// Extract keywords from current post
		$keywords = $this->extract_keywords( $post );

		if ( empty( $keywords ) ) {
			return array();
		}

		// Find related posts
		$related_posts = $this->find_related_posts( $post_id, $keywords );

		// Score and sort by relevance
		$suggestions = array();
		foreach ( $related_posts as $related_post ) {
			$relevance = $this->calculate_relevance( $keywords, $related_post );
			$anchor    = $this->suggest_anchor_text( $keywords, $related_post );

			$suggestions[] = array(
				'post_id'   => $related_post->ID,
				'title'     => $related_post->post_title,
				'url'       => get_permalink( $related_post->ID ),
				'relevance' => $relevance,
				'anchor'    => $anchor,
			);
		}

		// Sort by relevance DESC
		usort( $suggestions, function ( $a, $b ) {
			return $b['relevance'] <=> $a['relevance'];
		} );

		return array_slice( $suggestions, 0, 10 );
	}

	/**
	 * Extract keywords from post
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string>
	 */
	private function extract_keywords( \WP_Post $post ): array {
		$keywords = array();

		// From tags
		$tags = get_the_tags( $post->ID );
		if ( $tags && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				$keywords[] = strtolower( $tag->name );
			}
		}

		// From title (significant words)
		$title_words = $this->extract_significant_words( $post->post_title );
		$keywords    = array_merge( $keywords, $title_words );

		// From content (top keywords)
		$content_keywords = $this->extract_content_keywords( $post->post_content );
		$keywords         = array_merge( $keywords, $content_keywords );

		return array_unique( array_filter( $keywords ) );
	}

	/**
	 * Extract significant words from text
	 *
	 * @param string $text Text to analyze.
	 * @return array<string>
	 */
	private function extract_significant_words( string $text ): array {
		// Remove HTML
		$text = wp_strip_all_tags( $text );

		// Lowercase
		$text = strtolower( $text );

		// Remove punctuation
		$text = preg_replace( '/[^\w\s]/u', ' ', $text );

		// Split words
		$words = preg_split( '/\s+/', $text );

		// Stop words (italiano + inglese)
		$stop_words = array(
			'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una',
			'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
			'e', 'o', 'ma', 'se', 'che', 'chi', 'cui', 'come', 'quando',
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be',
		);

		// Filter
		$significant = array();
		foreach ( $words as $word ) {
			$word = trim( $word );
			// Min 4 characters, not stop word
			if ( strlen( $word ) >= 4 && ! in_array( $word, $stop_words, true ) ) {
				$significant[] = $word;
			}
		}

		return $significant;
	}

	/**
	 * Extract top keywords from content
	 *
	 * @param string $content Post content.
	 * @return array<string>
	 */
	private function extract_content_keywords( string $content ): array {
		$words       = $this->extract_significant_words( $content );
		$word_counts = array_count_values( $words );

		// Sort by frequency
		arsort( $word_counts );

		// Top 10 most frequent
		return array_keys( array_slice( $word_counts, 0, 10 ) );
	}

	/**
	 * Find related posts based on keywords
	 *
	 * @param int              $exclude_id Post ID to exclude.
	 * @param array<string>    $keywords   Keywords to match.
	 * @return array<\WP_Post>
	 */
	private function find_related_posts( int $exclude_id, array $keywords ): array {
		// Build search query
		$search_terms = implode( ' ', array_slice( $keywords, 0, 5 ) );

		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				's'                      => $search_terms,
				'post__not_in'           => array( $exclude_id ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => true,
			)
		);

		return $query->posts;
	}

	/**
	 * Calculate relevance score
	 *
	 * @param array<string> $keywords     Source keywords.
	 * @param \WP_Post      $related_post Related post.
	 * @return float
	 */
	private function calculate_relevance( array $keywords, \WP_Post $related_post ): float {
		$score = 0.0;

		// Title match
		$title_lower = strtolower( $related_post->post_title );
		foreach ( $keywords as $keyword ) {
			if ( strpos( $title_lower, $keyword ) !== false ) {
				$score += 2.0; // Title match = high relevance
			}
		}

		// Tag overlap
		$related_tags = get_the_tags( $related_post->ID );
		if ( $related_tags && ! is_wp_error( $related_tags ) ) {
			foreach ( $related_tags as $tag ) {
				$tag_lower = strtolower( $tag->name );
				if ( in_array( $tag_lower, $keywords, true ) ) {
					$score += 1.5;
				}
			}
		}

		// Content match (sample)
		$content_lower = strtolower( wp_strip_all_tags( $related_post->post_content ) );
		$matches       = 0;
		foreach ( array_slice( $keywords, 0, 5 ) as $keyword ) {
			if ( strpos( $content_lower, $keyword ) !== false ) {
				$matches++;
			}
		}
		$score += $matches * 0.5;

		return round( $score, 2 );
	}

	/**
	 * Suggest anchor text
	 *
	 * @param array<string> $keywords     Source keywords.
	 * @param \WP_Post      $related_post Related post.
	 * @return string
	 */
	private function suggest_anchor_text( array $keywords, \WP_Post $related_post ): string {
		// Try to find keyword match in title
		$title_lower = strtolower( $related_post->post_title );

		foreach ( $keywords as $keyword ) {
			if ( strpos( $title_lower, $keyword ) !== false ) {
				// Use title as anchor if contains keyword
				return $related_post->post_title;
			}
		}

		// Fallback to post title
		return $related_post->post_title;
	}
}

