<?php
/**
 * Service for analyzing keywords in content.
 *
 * @package FP\SEO\Keywords\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Services;

use WP_Post;
use function get_post;
use function strtolower;
use function str_word_count;
use function substr_count;
use function wp_strip_all_tags;

/**
 * Analyzes keywords in post content.
 */
class KeywordsAnalysisService {
	/**
	 * Analyze keywords in post content.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<string, mixed> $keywords_data Keywords data.
	 * @return array<string, mixed> Analysis results with 'density' and 'positions' keys.
	 */
	public function analyze( int $post_id, array $keywords_data ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'density' => array(), 'positions' => array() );
		}

		$content = strtolower( $post->post_content . ' ' . $post->post_title . ' ' . $post->post_excerpt );
		$content = wp_strip_all_tags( $content );
		$word_count = str_word_count( $content );

		$all_keywords = array_merge(
			array( $keywords_data['primary_keyword'] ?? '' ),
			$keywords_data['secondary_keywords'] ?? array(),
			$keywords_data['long_tail_keywords'] ?? array(),
			$keywords_data['semantic_keywords'] ?? array()
		);

		$all_keywords = array_filter( $all_keywords );

		$density = array();
		$positions = array();

		foreach ( $all_keywords as $keyword ) {
			if ( empty( $keyword ) ) {
				continue;
			}

			$keyword_lower = strtolower( $keyword );
			$keyword_count = substr_count( $content, $keyword_lower );
			$density_value = $word_count > 0 ? ( $keyword_count / $word_count ) * 100 : 0;

			$density[ $keyword ] = array(
				'count' => $keyword_count,
				'density' => round( $density_value, 2 ),
				'status' => $this->get_density_status( $density_value )
			);

			// Find keyword positions
			$positions[ $keyword ] = $this->find_keyword_positions( $content, $keyword_lower );
		}

		return array(
			'density' => $density,
			'positions' => $positions
		);
	}

	/**
	 * Get density status for keyword.
	 *
	 * @param float $density Density percentage.
	 * @return string Status: 'low', 'good', 'high', or 'over-optimized'.
	 */
	public function get_density_status( float $density ): string {
		if ( $density < 0.5 ) {
			return 'low';
		} elseif ( $density <= 2.5 ) {
			return 'good';
		} elseif ( $density <= 3.5 ) {
			return 'high';
		} else {
			return 'over-optimized';
		}
	}

	/**
	 * Find keyword positions in content.
	 *
	 * @param string $content Content to search.
	 * @param string $keyword Keyword to find.
	 * @return array<int, array{position: int, context: string, in_title: bool, in_excerpt: bool}>
	 */
	public function find_keyword_positions( string $content, string $keyword ): array {
		$positions = array();
		$offset = 0;

		while ( ( $pos = strpos( $content, $keyword, $offset ) ) !== false ) {
			$context_start = max( 0, $pos - 50 );
			$context_end = min( strlen( $content ), $pos + strlen( $keyword ) + 50 );
			$context = substr( $content, $context_start, $context_end - $context_start );

			$positions[] = array(
				'position' => $pos,
				'context' => '...' . $context . '...',
				'in_title' => $pos < 100, // Rough estimate
				'in_excerpt' => $pos < 200 // Rough estimate
			);

			$offset = $pos + 1;
		}

		return $positions;
	}
}








