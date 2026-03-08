<?php
/**
 * WP-CLI command for SEO analysis.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\CLI\Commands;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Data\Contracts\PostRepositoryInterface;

/**
 * WP-CLI commands for SEO analysis.
 */
class AnalysisCommand extends AbstractCommand {

	/**
	 * Analyzer instance.
	 *
	 * @var Analyzer
	 */
	private Analyzer $analyzer;

	/**
	 * Post repository.
	 *
	 * @var PostRepositoryInterface
	 */
	private PostRepositoryInterface $post_repository;

	/**
	 * Constructor.
	 *
	 * @param Analyzer                $analyzer        Analyzer instance.
	 * @param PostRepositoryInterface $post_repository Post repository.
	 */
	public function __construct( Analyzer $analyzer, PostRepositoryInterface $post_repository ) {
		$this->analyzer        = $analyzer;
		$this->post_repository = $post_repository;
	}

	/**
	 * Analyze a specific post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : Post ID to analyze
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv)
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp fp-seo analysis post 123
	 *     wp fp-seo analysis post 123 --format=json
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function post( array $args, array $assoc_args ): void {
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( $post_id <= 0 ) {
			$this->log_error( 'Invalid post ID' );
			return;
		}

		$post = $this->post_repository->get( $post_id );

		if ( ! $post ) {
			$this->log_error( "Post {$post_id} not found" );
			return;
		}

		$context = new Context( $post );
		$result  = $this->analyzer->analyze( $context );

		$format = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $result->to_array(), JSON_PRETTY_PRINT ) );
		} else {
			$items = array();
			foreach ( $result->get_checks() as $check ) {
				$items[] = array(
					'check'   => $check->get_id(),
					'status'  => $check->is_passing() ? 'PASS' : 'FAIL',
					'message' => $check->get_message(),
				);
			}

			$this->display_table( $items, array( 'Check', 'Status', 'Message' ) );
			$this->log_info( "Score: {$result->get_score()}/100" );
		}
	}

	/**
	 * Run bulk analysis on multiple posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Post type to analyze
	 * ---
	 * default: post
	 * ---
	 *
	 * [--limit=<number>]
	 * : Maximum number of posts to analyze
	 * ---
	 * default: 10
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp fp-seo analysis bulk
	 *     wp fp-seo analysis bulk --post-type=page --limit=50
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function bulk( array $args, array $assoc_args ): void {
		$post_type = $assoc_args['post-type'] ?? 'post';
		$limit     = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;

		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
			)
		);

		if ( empty( $posts ) ) {
			$this->log_warning( "No posts found for post type: {$post_type}" );
			return;
		}

		$this->log_info( "Analyzing " . count( $posts ) . " posts..." );

		$items = array();
		foreach ( $posts as $post ) {
			$context = new Context( $post );
			$result  = $this->analyzer->analyze( $context );

			$items[] = array(
				'ID'     => $post->ID,
				'Title'  => $post->post_title,
				'Score'  => $result->get_score(),
				'Status' => $result->get_score() >= 70 ? 'Good' : ( $result->get_score() >= 50 ? 'Fair' : 'Poor' ),
			);
		}

		$this->display_table( $items, array( 'ID', 'Title', 'Score', 'Status' ) );
	}
}










