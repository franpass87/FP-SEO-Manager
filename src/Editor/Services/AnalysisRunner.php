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
use FP\SEO\Editor\Services\CheckFormatterService;
use FP\SEO\Editor\Services\MetadataCollectionService;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\Logger;
use WP_Post;

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
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( 'FP SEO DEBUG: AnalysisRunner::run() called for post_id=' . $post->ID );
		}

		if ( ! class_exists( '\FP\SEO\Analysis\Context' ) ) {
			if ( $debug ) {
				error_log( 'FP SEO DEBUG: Context class not found' );
			}
			throw new \RuntimeException( 'Context class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Analysis\Analyzer' ) ) {
			if ( $debug ) {
				error_log( 'FP SEO DEBUG: Analyzer class not found' );
			}
			throw new \RuntimeException( 'Analyzer class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Scoring\ScoreEngine' ) ) {
			if ( $debug ) {
				error_log( 'FP SEO DEBUG: ScoreEngine class not found' );
			}
			throw new \RuntimeException( 'ScoreEngine class not found' );
		}

		$metadata_collector = new MetadataCollectionService();
		$metadata           = $metadata_collector->collect_metadata( $post );

		if ( $debug ) {
			error_log( 'FP SEO DEBUG: Metadata collected - seo_title=' . ( ! empty( $metadata['seo_title'] ) ? 'yes' : 'no' ) . ', meta_description=' . ( ! empty( $metadata['meta_description'] ) ? 'yes' : 'no' ) );
		}

		$context = new Context(
			(int) $post->ID,
			(string) $post->post_content,
			$metadata['seo_title'],
			$metadata['meta_description'],
			$metadata['canonical'],
			$metadata['robots'],
			$metadata['focus_keyword'],
			$metadata['secondary_keywords']
		);

		if ( $debug ) {
			error_log( 'FP SEO DEBUG: Context created - post_id=' . ( $context->post_id() ?? 'null' ) . ', content_length=' . strlen( $context->html() ) . ', title=' . substr( $context->title(), 0, 50 ) );
		}

		$analyzer         = new Analyzer();
		$analysis         = $analyzer->analyze( $context );
		$checks_array     = $analysis['checks'] ?? array();
		$score_engine     = new ScoreEngine();

		if ( $debug ) {
			Logger::debug( 'AnalysisRunner::run - checks processed', array(
				'post_id'          => $post->ID,
				'checks_count'     => count( $checks_array ),
				'first_check_keys' => ! empty( $checks_array ) ? array_keys( reset( $checks_array ) ) : array(),
			) );
		}

		$score            = $score_engine->calculate( $checks_array );
		$check_formatter  = new CheckFormatterService();
		$formatted_checks = $check_formatter->format_checks_for_frontend( $checks_array );

		if ( $debug ) {
			Logger::debug( 'AnalysisRunner::run - formatted checks', array(
				'post_id'               => $post->ID,
				'formatted_checks_count' => count( $formatted_checks ),
			) );
		}

		return array(
			'score'  => $score,
			'checks' => $formatted_checks,
		);
	}

}
















