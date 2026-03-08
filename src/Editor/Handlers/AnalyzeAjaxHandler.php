<?php
/**
 * AJAX handler for SEO analysis requests.
 *
 * @package FP\SEO\Editor\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Handlers;

use FP\SEO\Editor\Services\AnalysisRunner;
use FP\SEO\Editor\Services\AnalysisDataService;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use WP_Post;

/**
 * Handles AJAX requests for SEO analysis.
 */
class AnalyzeAjaxHandler extends AbstractAjaxHandler {
	/**
	 * Analysis runner service.
	 *
	 * @var AnalysisRunner
	 */
	private AnalysisRunner $analysis_runner;

	/**
	 * Analysis data service for formatting.
	 *
	 * @var AnalysisDataService
	 */
	private AnalysisDataService $analysis_data_service;

	/**
	 * Supported post types.
	 *
	 * @var array<string>
	 */
	private array $supported_post_types;

	/**
	 * AJAX action name.
	 *
	 * @var string
	 */
	public const AJAX_ACTION = 'fp_seo_performance_analyze';

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 * @param AnalysisRunner       $analysis_runner Analysis runner service.
	 * @param AnalysisDataService  $analysis_data_service Analysis data service for formatting.
	 * @param array<string>        $supported_post_types Supported post types.
	 */
	public function __construct(
		HookManagerInterface $hook_manager,
		AnalysisRunner $analysis_runner,
		AnalysisDataService $analysis_data_service,
		array $supported_post_types
	) {
		parent::__construct( $hook_manager );
		$this->analysis_runner       = $analysis_runner;
		$this->analysis_data_service = $analysis_data_service;
		$this->supported_post_types  = $supported_post_types;
	}

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Handle analyze AJAX request.
	 *
	 * @return void
	 */
	public function handle_ajax(): void {
		$this->verify_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$post_id = $this->get_post_id_from_request( 'post_id' );

		if ( $post_id <= 0 ) {
			$this->send_error( __( 'Invalid post ID.', 'fp-seo-performance' ), 400 );
			return;
		}

		if ( ! $this->check_capability( 'edit_post', $post_id ) ) {
			$this->send_error( __( 'You are not allowed to edit this post.', 'fp-seo-performance' ), 403 );
			return;
		}

		$post = $this->get_post_from_request( $post_id );

		if ( ! $post ) {
			$this->send_error( __( 'Post not found.', 'fp-seo-performance' ), 404 );
			return;
		}

		// Validate post type
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			$this->send_error(
				__( 'Post not found or post type unavailable.', 'fp-seo-performance' ),
				404
			);
			return;
		}
		if ( ! $this->validate_post_type( $post_type, $this->supported_post_types ) ) {
			$this->send_error(
				__( 'This post type is not supported for SEO analysis.', 'fp-seo-performance' ),
				400,
				array( 'post_type' => $post_type )
			);
			return;
		}

		// Run analysis
		try {
			$this->log_debug( 'Running analysis via AJAX', array( 'post_id' => $post_id ) );

			$analysis_result = $this->analysis_runner->run( $post );

			// AnalysisRunner already returns formatted checks, but we use AnalysisDataService
			// to ensure consistency and allow for additional processing if needed
			$checks = $analysis_result['checks'] ?? array();
			$formatted_checks = $this->analysis_data_service->format_checks_for_frontend( $checks );

			// Compile payload using AnalysisDataService
			$score = $analysis_result['score'] ?? array( 'score' => 0, 'status' => 'pending' );
			$payload = $this->analysis_data_service->compile_payload( $score, $formatted_checks );

			$this->log_debug( 'Analysis completed', array(
				'post_id'       => $post_id,
				'checks_count'  => count( $formatted_checks ),
			) );

			$this->send_success( $payload );
		} catch ( \Throwable $e ) {
			$this->log_error( 'Analysis failed', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );

			$this->send_error(
				__( 'Analysis failed. Please try again.', 'fp-seo-performance' ),
				500,
				array( 'error' => $e->getMessage() )
			);
		}
	}
}
