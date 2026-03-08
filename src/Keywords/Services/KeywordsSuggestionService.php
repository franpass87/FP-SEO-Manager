<?php
/**
 * Service for generating keyword suggestions.
 *
 * @package FP\SEO\Keywords\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Services;

use FP\SEO\Utils\CacheHelper;
use function get_post;
use function strtolower;
use function wp_strip_all_tags;
use function preg_split;
use function array_count_values;
use function array_filter;
use function in_array;
use function strlen;
use function arsort;
use function array_slice;
use function array_keys;
use function preg_split as preg_split_sentences;
use function trim;
use function implode;
use function array_unique;

/**
 * Generates keyword suggestions for posts.
 */
class KeywordsSuggestionService {
	/**
	 * Get keyword suggestions for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, array<int, array{keyword: string, score: int}>> Suggestions with 'primary', 'secondary', 'long_tail', and 'semantic' keys.
	 */
	public function get_suggestions( int $post_id ): array {
		$cache_key = 'fp_seo_keyword_suggestions_' . $post_id;
		
		return CacheHelper::remember( $cache_key, function() use ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array(
					'primary' => array(),
					'secondary' => array(),
					'long_tail' => array(),
					'semantic' => array()
				);
			}

			$content = $post->post_content . ' ' . $post->post_title . ' ' . $post->post_excerpt;
			$keywords = $this->extract_content_keywords( $content );

			return array(
				'primary' => $this->suggest_primary_keywords( $keywords ),
				'secondary' => $this->suggest_secondary_keywords( $keywords ),
				'long_tail' => $this->suggest_long_tail_keywords( $keywords, $content ),
				'semantic' => $this->suggest_semantic_keywords( $keywords )
			);
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Extract keywords from content.
	 *
	 * @param string $content Content to analyze.
	 * @return array<string, int> Keywords with their frequency.
	 */
	private function extract_content_keywords( string $content ): array {
		$content = wp_strip_all_tags( strtolower( $content ) );
		$words = preg_split( '/\s+/', $content ) ?: array();
		
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
	 * Suggest primary keywords.
	 *
	 * @param array<string, int> $keywords Keywords from content.
	 * @return array<int, array{keyword: string, score: int}>
	 */
	private function suggest_primary_keywords( array $keywords ): array {
		$suggestions = array();
		arsort( $keywords );
		
		$count = 0;
		foreach ( $keywords as $keyword => $frequency ) {
			if ( $count >= 5 ) {
				break;
			}
			
			$suggestions[] = array(
				'keyword' => $keyword,
				'score' => min( $frequency * 10, 100 )
			);
			$count++;
		}
		
		return $suggestions;
	}

	/**
	 * Suggest secondary keywords.
	 *
	 * @param array<string, int> $keywords Keywords from content.
	 * @return array<int, array{keyword: string, score: int}>
	 */
	private function suggest_secondary_keywords( array $keywords ): array {
		$suggestions = array();
		arsort( $keywords );
		
		$count = 0;
		foreach ( $keywords as $keyword => $frequency ) {
			if ( $count >= 8 ) {
				break;
			}
			if ( $frequency < 2 ) {
				continue; // Skip low frequency words
			}
			
			$suggestions[] = array(
				'keyword' => $keyword,
				'score' => min( $frequency * 8, 80 )
			);
			$count++;
		}
		
		return $suggestions;
	}

	/**
	 * Suggest long tail keywords.
	 *
	 * @param array<string, int> $keywords Keywords from content.
	 * @param string $content Full content.
	 * @return array<int, array{keyword: string, score: int}>
	 */
	private function suggest_long_tail_keywords( array $keywords, string $content ): array {
		$suggestions = array();
		$phrases = $this->extract_phrases( $content );
		
		foreach ( array_slice( $phrases, 0, 6 ) as $phrase ) {
			$suggestions[] = array(
				'keyword' => $phrase,
				'score' => 75
			);
		}
		
		return $suggestions;
	}

	/**
	 * Suggest semantic keywords.
	 *
	 * @param array<string, int> $keywords Keywords from content.
	 * @return array<int, array{keyword: string, score: int}>
	 */
	private function suggest_semantic_keywords( array $keywords ): array {
		$suggestions = array();
		$top_keywords = array_slice( array_keys( $keywords ), 0, 3, true );
		
		foreach ( $top_keywords as $keyword ) {
			// Generate semantic variations
			$variations = $this->generate_semantic_variations( $keyword );
			foreach ( $variations as $variation ) {
				$suggestions[] = array(
					'keyword' => $variation,
					'score' => 60
				);
			}
		}
		
		return array_slice( $suggestions, 0, 8 );
	}

	/**
	 * Extract phrases from content.
	 *
	 * @param string $content Content to analyze.
	 * @return array<string> Extracted phrases.
	 */
	private function extract_phrases( string $content ): array {
		$content = wp_strip_all_tags( strtolower( $content ) );
		$sentences = preg_split_sentences( '/[.!?]+/', $content ) ?: array();
		$phrases = array();
		
		foreach ( $sentences as $sentence ) {
			$words = preg_split( '/\s+/', trim( $sentence ) ) ?: array();
			if ( count( $words ) >= 3 && count( $words ) <= 6 ) {
				$phrases[] = implode( ' ', $words );
			}
		}
		
		return array_unique( $phrases );
	}

	/**
	 * Generate semantic variations of a keyword.
	 *
	 * @param string $keyword Base keyword.
	 * @return array<string> Semantic variations.
	 */
	private function generate_semantic_variations( string $keyword ): array {
		// Basic semantic variations - in real implementation, this would use AI
		$variations = array();
		
		// Add common prefixes/suffixes
		$prefixes = array( 'best', 'top', 'how to', 'guide to', 'tips for' );
		$suffixes = array( 'guide', 'tips', 'tutorial', 'review', 'comparison' );
		
		foreach ( $prefixes as $prefix ) {
			$variations[] = $prefix . ' ' . $keyword;
		}
		
		foreach ( $suffixes as $suffix ) {
			$variations[] = $keyword . ' ' . $suffix;
		}
		
		return array_slice( $variations, 0, 3 );
	}
}





