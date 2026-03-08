<?php
/**
 * Service for calculating link scores and relevance.
 *
 * @package FP\SEO\Links\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Services;

use WP_Post;
use function wp_strip_all_tags;
use function strtolower;
use function similar_text;
use function get_post_meta;
use function get_the_category;
use function get_post_type;
use function wp_get_post_categories;
use function array_intersect;
use function count;
use function max;

/**
 * Calculates scores and relevance for internal links.
 */
class LinkScoringService {
	/**
	 * Calculate content similarity between two texts.
	 *
	 * @param string $text1 First text.
	 * @param string $text2 Second text.
	 * @return float Similarity score (0-1).
	 */
	public function calculate_content_similarity( string $text1, string $text2 ): float {
		$text1 = wp_strip_all_tags( strtolower( $text1 ) );
		$text2 = wp_strip_all_tags( strtolower( $text2 ) );
		
		$similarity = 0.0;
		similar_text( $text1, $text2, $similarity );
		
		return $similarity / 100.0;
	}

	/**
	 * Calculate post authority score.
	 *
	 * @param WP_Post $post Post object.
	 * @return float Authority score (0-1).
	 */
	public function calculate_post_authority( WP_Post $post ): float {
		$score = 0.0;
		
		// Age factor (older posts = more authority)
		$age_days = ( time() - strtotime( $post->post_date ) ) / DAY_IN_SECONDS;
		if ( $age_days > 365 ) {
			$score += 0.3;
		} elseif ( $age_days > 180 ) {
			$score += 0.2;
		} elseif ( $age_days > 90 ) {
			$score += 0.1;
		}
		
		// Comment count (more comments = more engagement)
		$comment_count = (int) $post->comment_count;
		if ( $comment_count > 50 ) {
			$score += 0.3;
		} elseif ( $comment_count > 20 ) {
			$score += 0.2;
		} elseif ( $comment_count > 5 ) {
			$score += 0.1;
		}
		
		// View count (if available)
		$views = (int) get_post_meta( $post->ID, 'post_views_count', true );
		if ( $views > 1000 ) {
			$score += 0.2;
		} elseif ( $views > 500 ) {
			$score += 0.1;
		}
		
		// Featured post
		if ( get_post_meta( $post->ID, '_featured', true ) ) {
			$score += 0.2;
		}
		
		return min( $score, 1.0 );
	}

	/**
	 * Calculate category relevance between two posts.
	 *
	 * @param int $post1_id First post ID.
	 * @param int $post2_id Second post ID.
	 * @return float Relevance score (0-1).
	 */
	public function calculate_category_relevance( int $post1_id, int $post2_id ): float {
		$categories1 = wp_get_post_categories( $post1_id );
		$categories2 = wp_get_post_categories( $post2_id );
		
		if ( empty( $categories1 ) || empty( $categories2 ) ) {
			return 0.0;
		}
		
		$common = array_intersect( $categories1, $categories2 );
		$total = count( array_unique( array_merge( $categories1, $categories2 ) ) );
		
		if ( $total === 0 ) {
			return 0.0;
		}
		
		return count( $common ) / $total;
	}
}








