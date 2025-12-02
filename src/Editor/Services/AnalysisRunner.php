<?php
/**
 * Runs SEO analysis for posts.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Editor\Metabox;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function get_post_meta;
use function is_array;
use function maybe_unserialize;

/**
 * Runs SEO analysis for posts.
 */
class AnalysisRunner {
	/**
	 * Run analysis for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Analysis result with 'score' and 'checks' keys.
	 */
	public function run( WP_Post $post ): array {
		// Check if required classes exist
		if ( ! class_exists( '\FP\SEO\Analysis\Context' ) ) {
			throw new \RuntimeException( 'Context class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Analysis\Analyzer' ) ) {
			throw new \RuntimeException( 'Analyzer class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Scoring\ScoreEngine' ) ) {
			throw new \RuntimeException( 'ScoreEngine class not found' );
		}

		// Get SEO metadata using MetadataResolver
		$meta_description = MetadataResolver::resolve_meta_description( $post );
		$canonical = MetadataResolver::resolve_canonical_url( $post );
		$robots = MetadataResolver::resolve_robots( $post );
		$focus_keyword = $this->get_focus_keyword( $post->ID );
		$secondary_keywords = $this->get_secondary_keywords( $post->ID );

		// Get SEO title, fallback to post title
		$seo_title = MetadataResolver::resolve_seo_title( $post->ID );
		if ( ! $seo_title ) {
			$seo_title = $post->post_title;
		}

		// Build context
		$context = new Context(
			(int) $post->ID,
			(string) $post->post_content,
			(string) $seo_title,
			(string) $meta_description,
			$canonical,
			$robots,
			is_string( $focus_keyword ) ? $focus_keyword : '',
			$secondary_keywords
		);

		$analyzer = new Analyzer();
		$analysis = $analyzer->analyze( $context );
		$score_engine = new ScoreEngine();

		// Analyzer::analyze() returns an array with 'checks' and 'summary' keys
		// ScoreEngine::calculate() expects an array of checks indexed by check ID
		$checks_array = $analysis['checks'] ?? array();

		// Debug: log checks structure
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'AnalysisRunner::run - checks processed', array(
				'post_id' => $post->ID,
				'checks_count' => count( $checks_array ),
				'first_check_keys' => ! empty( $checks_array ) ? array_keys( reset( $checks_array ) ) : array(),
			) );
		}

		$score = $score_engine->calculate( $checks_array );

		$formatted_checks = $this->format_checks_for_frontend( $checks_array );

		// Debug: log formatted checks
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'AnalysisRunner::run - formatted checks', array(
				'post_id' => $post->ID,
				'formatted_checks_count' => count( $formatted_checks ),
			) );
		}

		return array(
			'score' => $score,
			'checks' => $formatted_checks,
		);
	}

	/**
	 * Get focus keyword for post.
	 *
	 * @param int $post_id Post ID.
	 * @return string Focus keyword.
	 */
	private function get_focus_keyword( int $post_id ): string {
		$focus_keyword = get_post_meta( $post_id, Metabox::META_FOCUS_KEYWORD, true );

		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $focus_keyword ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, Metabox::META_FOCUS_KEYWORD ) );
			if ( $db_value !== null ) {
				$focus_keyword = $db_value;
			}
		}

		return is_string( $focus_keyword ) ? $focus_keyword : '';
	}

	/**
	 * Get secondary keywords for post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Secondary keywords.
	 */
	private function get_secondary_keywords( int $post_id ): array {
		$secondary_keywords = get_post_meta( $post_id, Metabox::META_SECONDARY_KEYWORDS, true );

		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $secondary_keywords ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, Metabox::META_SECONDARY_KEYWORDS ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$secondary_keywords = is_array( $unserialized ) ? $unserialized : array();
			}
		}

		return is_array( $secondary_keywords ) ? $secondary_keywords : array();
	}

	/**
	 * Format checks for frontend display.
	 *
	 * @param array $checks_array Raw checks array.
	 * @return array Formatted checks.
	 */
	private function format_checks_for_frontend( array $checks_array ): array {
		$formatted = array();

		foreach ( $checks_array as $check_id => $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}

			$result = $check['result'] ?? null;
			if ( ! $result instanceof \FP\SEO\Analysis\Result ) {
				continue;
			}

			$formatted[] = array(
				'id'      => $check_id,
				'label'   => $result->get_label(),
				'status'  => $result->get_status(),
				'message' => $result->get_message(),
				'hint'    => $result->get_hint(),
			);
		}

		return $formatted;
	}
}


