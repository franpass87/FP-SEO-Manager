<?php
/**
 * Service for analyzing internal links.
 *
 * @package FP\SEO\Links\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Services;

use function get_post;
use function wp_strip_all_tags;
use function str_word_count;
use function count;
use function sprintf;
use function __;
use function round;

/**
 * Analyzes internal links for posts.
 */
class LinkAnalysisService {
	/**
	 * Analyze links for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<int, array<string, mixed>> $existing_links Existing links.
	 * @param array<int, array<string, mixed>> $suggestions Link suggestions.
	 * @return array<string, mixed> Analysis results.
	 */
	public function analyze_post( int $post_id, array $existing_links, array $suggestions ): array {
		return array(
			'existing_links' => count( $existing_links ),
			'suggestions' => count( $suggestions ),
			'link_density' => $this->calculate_link_density( $post_id, $existing_links ),
			'recommendations' => $this->get_recommendations( $post_id, $existing_links, $suggestions )
		);
	}

	/**
	 * Calculate link density for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<int, array<string, mixed>> $existing_links Existing links.
	 * @return float Link density percentage.
	 */
	public function calculate_link_density( int $post_id, array $existing_links ): float {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0.0;
		}

		$content = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		$link_count = count( $existing_links );

		return $word_count > 0 ? ( $link_count / $word_count ) * 100 : 0.0;
	}

	/**
	 * Get recommendations for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<int, array<string, mixed>> $existing_links Existing links.
	 * @param array<int, array<string, mixed>> $suggestions Link suggestions.
	 * @return array<string> Recommendations.
	 */
	public function get_recommendations( int $post_id, array $existing_links, array $suggestions ): array {
		$recommendations = array();
		$link_count = count( $existing_links );
		
		if ( $link_count < 3 ) {
			$recommendations[] = __( 'Add more internal links (recommended: 3-5 per post).', 'fp-seo-performance' );
		}
		
		if ( ! empty( $suggestions ) ) {
			$top_suggestion = $suggestions[0];
			$recommendations[] = sprintf( 
				__( 'Consider linking to "%s" (relevance: %d%%)', 'fp-seo-performance' ), 
				$top_suggestion['title'] ?? '', 
				round( ( $top_suggestion['score'] ?? 0 ) * 100 )
			);
		}
		
		return $recommendations;
	}
}








