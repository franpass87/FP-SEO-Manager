<?php
/**
 * Service for generating internal link suggestions.
 *
 * @package FP\SEO\Links\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Services;

use FP\SEO\Utils\CacheHelper;
use WP_Post;
use function get_posts;
use function get_post;
use function wp_strip_all_tags;
use function strtolower;
use function preg_split;
use function array_count_values;
use function array_filter;
use function strlen;
use function array_intersect_key;
use function array_keys;
use function explode;
use function implode;
use function array_slice;
use function preg_split as preg_split_sentences;
use function trim;
use function wp_trim_words;

/**
 * Generates internal link suggestions for posts.
 */
class LinkSuggestionService {
	/**
	 * Minimum content length for link suggestions.
	 */
	private const MIN_CONTENT_LENGTH = 100;

	/**
	 * Maximum number of suggestions per post.
	 */
	private const MAX_SUGGESTIONS = 10;

	/**
	 * Get link suggestions for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<string, mixed> $options Options for suggestions.
	 * @return array<int, array<string, mixed>> Array of link suggestions.
	 */
	public function get_suggestions( int $post_id, array $options = array() ): array {
		$cache_key = 'fp_seo_link_suggestions_' . $post_id . '_' . md5( serialize( $options ) );
		
		return CacheHelper::remember( $cache_key, function() use ( $post_id, $options ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array();
			}

			$content = $post->post_content . ' ' . $post->post_title;
			if ( strlen( $content ) < self::MIN_CONTENT_LENGTH ) {
				return array();
			}

			$keywords = $this->extract_keywords( $content );
			if ( empty( $keywords ) ) {
				return array();
			}

			$target_posts = $this->get_potential_target_posts( $post_id, $keywords, $options );
			if ( empty( $target_posts ) ) {
				return array();
			}

			$suggestions = $this->score_link_suggestions( $post_id, $target_posts, $keywords, $content );
			
			// Sort by score descending and limit
			usort( $suggestions, function( $a, $b ) {
				return $b['score'] <=> $a['score'];
			});

			return array_slice( $suggestions, 0, self::MAX_SUGGESTIONS );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Extract keywords from content.
	 *
	 * @param string $content Content to analyze.
	 * @return array<string, int> Keywords with their frequency.
	 */
	public function extract_keywords( string $content ): array {
		$content = wp_strip_all_tags( strtolower( $content ) );
		$words   = preg_split( '/\s+/', $content ) ?: array();
		
		// Filter out short words and common stop words
		$stop_words = array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
			'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
			'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'
		);
		
		$words = array_filter( $words, function( $word ) use ( $stop_words ) {
			return strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
		});
		
		return array_count_values( $words );
	}

	/**
	 * Get potential target posts for linking.
	 *
	 * @param int   $post_id Current post ID.
	 * @param array<string, int> $keywords Keywords from current post.
	 * @param array<string, mixed> $options Options.
	 * @return array<WP_Post> Array of potential target posts.
	 */
	private function get_potential_target_posts( int $post_id, array $keywords, array $options ): array {
		$args = array(
			'post_type'      => $options['post_type'] ?? array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $options['limit'] ?? 50,
			'post__not_in'   => array( $post_id ),
			'orderby'        => 'relevance',
		);

		// Add category filter if specified
		if ( ! empty( $options['category'] ) ) {
			$args['category__in'] = $options['category'];
		}

		// Optimize query arguments for plugin query
		$args = \FP\SEO\Utils\QueryOptimizer::optimize_query_args( $args );

		$posts = get_posts( $args );
		
		// Filter posts that have matching keywords
		$matching_posts = array();
		foreach ( $posts as $post ) {
			$post_keywords = $this->extract_keywords( $post->post_content . ' ' . $post->post_title );
			$matches = array_intersect_key( $keywords, $post_keywords );
			
			if ( ! empty( $matches ) ) {
				$matching_posts[] = $post;
			}
		}

		return $matching_posts;
	}

	/**
	 * Score link suggestions.
	 *
	 * @param int   $post_id Current post ID.
	 * @param array<WP_Post> $target_posts Target posts.
	 * @param array<string, int> $keywords Keywords.
	 * @param string $content Current post content.
	 * @return array<int, array<string, mixed>> Scored suggestions.
	 */
	private function score_link_suggestions( int $post_id, array $target_posts, array $keywords, string $content ): array {
		$suggestions = array();
		
		foreach ( $target_posts as $target_post ) {
			$score = $this->calculate_link_score( $post_id, $target_post, $keywords, $content );
			
			if ( $score > 0.3 ) { // Only include suggestions with decent score
				$matching_keywords = $this->get_matching_keywords( $target_post, $keywords );
				$anchor_text = $this->suggest_anchor_text( $target_post, $keywords );
				$context = $this->find_best_context( $content, $target_post, $keywords );
				
				$suggestions[] = array(
					'post_id'     => $target_post->ID,
					'title'       => $target_post->post_title,
					'url'         => get_permalink( $target_post->ID ),
					'excerpt'     => wp_trim_words( $target_post->post_excerpt ?: $target_post->post_content, 20 ),
					'score'       => $score,
					'keywords'    => $matching_keywords,
					'anchor_text' => $anchor_text,
					'context'     => $context,
				);
			}
		}

		return $suggestions;
	}

	/**
	 * Calculate link score for a target post.
	 *
	 * @param int   $post_id Current post ID.
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @param string $content Current post content.
	 * @return float Link score (0-1).
	 */
	private function calculate_link_score( int $post_id, WP_Post $target_post, array $keywords, string $content ): float {
		$scoring_service = new LinkScoringService();
		
		$similarity = $scoring_service->calculate_content_similarity( $content, $target_post->post_content );
		$authority = $scoring_service->calculate_post_authority( $target_post );
		$category_relevance = $scoring_service->calculate_category_relevance( $post_id, $target_post->ID );
		
		$matching_keywords = $this->get_matching_keywords( $target_post, $keywords );
		$keyword_score = count( $matching_keywords ) / max( count( $keywords ), 1 );
		
		// Weighted average
		$score = ( $similarity * 0.3 ) + ( $authority * 0.3 ) + ( $category_relevance * 0.2 ) + ( $keyword_score * 0.2 );
		
		return min( $score, 1.0 );
	}

	/**
	 * Get matching keywords between target post and keywords.
	 *
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @return array<string> Matching keywords.
	 */
	private function get_matching_keywords( WP_Post $target_post, array $keywords ): array {
		$target_keywords = $this->extract_keywords( $target_post->post_content . ' ' . $target_post->post_title );
		$matches = array_intersect_key( $keywords, $target_keywords );
		
		return array_keys( $matches );
	}

	/**
	 * Suggest anchor text for link.
	 *
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @return string Suggested anchor text.
	 */
	private function suggest_anchor_text( WP_Post $target_post, array $keywords ): string {
		$title = $target_post->post_title;
		
		// Check if any keywords match the title
		$title_words = explode( ' ', strtolower( $title ) );
		foreach ( array_keys( $keywords ) as $keyword ) {
			if ( in_array( $keyword, $title_words, true ) ) {
				return $title; // Use full title if keyword matches
			}
		}

		// Use first few words of title
		$words = explode( ' ', $title );
		return implode( ' ', array_slice( $words, 0, 4 ) );
	}

	/**
	 * Find best context for link placement.
	 *
	 * @param string $content Current content.
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @return string Best context sentence.
	 */
	private function find_best_context( string $content, WP_Post $target_post, array $keywords ): string {
		$sentences = preg_split_sentences( '/[.!?]+/', $content ) ?: array();
		$best_sentence = '';
		$best_score = 0;

		foreach ( $sentences as $sentence ) {
			$sentence_keywords = $this->extract_keywords( $sentence );
			$matches = array_intersect_key( $keywords, $sentence_keywords );
			$score = count( $matches );

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_sentence = trim( $sentence );
			}
		}

		return $best_sentence ?: wp_trim_words( $content, 20 );
	}
}





