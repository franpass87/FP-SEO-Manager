<?php
/**
 * Unified analysis service.
 *
 * @package FP\SEO\Editor\Metabox\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Metabox\Services;

use FP\SEO\Editor\Metabox\Contracts\AnalysisServiceInterface;
use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function is_array;
use function maybe_unserialize;

/**
 * Unified analysis service.
 *
 * Consolidates AnalysisRunner and Metabox analysis logic.
 */
class AnalysisService implements AnalysisServiceInterface {

	/**
	 * Post repository.
	 *
	 * @var PostRepositoryInterface
	 */
	private PostRepositoryInterface $post_repository;

	/**
	 * Post meta repository.
	 *
	 * @var PostMetaRepositoryInterface
	 */
	private PostMetaRepositoryInterface $post_meta_repository;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Meta keys.
	 */
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Constructor.
	 *
	 * @param PostRepositoryInterface     $post_repository     Post repository.
	 * @param PostMetaRepositoryInterface $post_meta_repository Post meta repository.
	 * @param LoggerInterface             $logger              Logger instance.
	 */
	public function __construct(
		PostRepositoryInterface $post_repository,
		PostMetaRepositoryInterface $post_meta_repository,
		LoggerInterface $logger
	) {
		$this->post_repository      = $post_repository;
		$this->post_meta_repository = $post_meta_repository;
		$this->logger               = $logger;
	}

	/**
	 * Run analysis for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Analysis result with 'score' and 'checks' keys.
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

		$this->logger->debug( 'AnalysisService::run - checks processed', array(
			'post_id'        => $post->ID,
			'checks_count'   => count( $checks_array ),
			'first_check_keys' => ! empty( $checks_array ) ? array_keys( reset( $checks_array ) ) : array(),
		) );

		$score = $score_engine->calculate( $checks_array );

		$formatted_checks = $this->format_checks_for_frontend( $checks_array );

		$this->logger->debug( 'AnalysisService::run - formatted checks', array(
			'post_id'              => $post->ID,
			'formatted_checks_count' => count( $formatted_checks ),
		) );

		return array(
			'score'  => $score,
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
		$focus_keyword = $this->post_meta_repository->get( $post_id, self::META_FOCUS_KEYWORD, true );

		// Fallback: query directly from database if repository returns empty
		if ( empty( $focus_keyword ) ) {
			$db_value = $this->post_meta_repository->get_from_db( $post_id, self::META_FOCUS_KEYWORD );
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
		$secondary_keywords = $this->post_meta_repository->get( $post_id, self::META_SECONDARY_KEYWORDS, true );

		// Fallback: query directly from database if repository returns empty
		if ( empty( $secondary_keywords ) ) {
			$db_value = $this->post_meta_repository->get_from_db( $post_id, self::META_SECONDARY_KEYWORDS );
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
	 * @param array<string, mixed> $checks_array Raw checks array.
	 * @return array<int, array<string, mixed>> Formatted checks.
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















