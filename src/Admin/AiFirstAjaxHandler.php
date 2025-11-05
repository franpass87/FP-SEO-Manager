<?php
/**
 * AJAX Handler for AI-First Features
 *
 * Handles AJAX requests for Q&A generation, variants, entities, etc.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\AI\EmbeddingsGenerator;
use FP\SEO\GEO\EntityGraph;
use FP\SEO\GEO\MultiModalOptimizer;

/**
 * Handles AJAX for AI-first features
 */
class AiFirstAjaxHandler {

	/**
	 * Register AJAX hooks
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_generate_qa', array( $this, 'handle_generate_qa' ) );
		add_action( 'wp_ajax_fp_seo_generate_variants', array( $this, 'handle_generate_variants' ) );
		add_action( 'wp_ajax_fp_seo_generate_entities', array( $this, 'handle_generate_entities' ) );
		add_action( 'wp_ajax_fp_seo_generate_embeddings', array( $this, 'handle_generate_embeddings' ) );
		add_action( 'wp_ajax_fp_seo_optimize_images', array( $this, 'handle_optimize_images' ) );
		add_action( 'wp_ajax_fp_seo_clear_ai_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'wp_ajax_fp_seo_batch_generate_qa', array( $this, 'handle_batch_generate_qa' ) );
	}

	/**
	 * Handle Q&A generation request
	 */
	public function handle_generate_qa(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$extractor = new QAPairExtractor();
			$qa_pairs  = $extractor->extract_qa_pairs( $post_id, true ); // Force regeneration

			wp_send_json_success( array(
				'message'  => sprintf( 'Generated %d Q&A pairs', count( $qa_pairs ) ),
				'qa_pairs' => $qa_pairs,
				'total'    => count( $qa_pairs ),
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle variants generation request
	 */
	public function handle_generate_variants(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$generator = new ConversationalVariants();
			$variants  = $generator->generate_variants( $post_id, true );

			wp_send_json_success( array(
				'message'  => sprintf( 'Generated %d variants', count( $variants ) ),
				'variants' => $variants,
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle entities generation request
	 */
	public function handle_generate_entities(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$graph = new EntityGraph();
			$data  = $graph->build_entity_graph( $post_id );

			wp_send_json_success( array(
				'message'       => sprintf( 'Generated %d entities and %d relationships', 
					count( $data['entities'] ?? array() ), 
					count( $data['relationships'] ?? array() )
				),
				'entities'      => $data['entities'] ?? array(),
				'relationships' => $data['relationships'] ?? array(),
				'statistics'    => $data['statistics'] ?? array(),
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle embeddings generation request
	 */
	public function handle_generate_embeddings(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$generator = new EmbeddingsGenerator();
			$data      = $generator->generate_embeddings( $post_id, true );

			if ( isset( $data['error'] ) ) {
				wp_send_json_error( array( 'message' => $data['message'] ?? 'Failed to generate embeddings' ), 500 );
			}

			wp_send_json_success( array(
				'message'      => sprintf( 'Generated embeddings for %d chunks', $data['total_chunks'] ?? 0 ),
				'total_chunks' => $data['total_chunks'] ?? 0,
				'model'        => $data['model'] ?? '',
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle image optimization request
	 */
	public function handle_optimize_images(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$optimizer = new MultiModalOptimizer();
			$data      = $optimizer->optimize_images( $post_id );

			wp_send_json_success( array(
				'message'            => sprintf( 'Optimized %d images', $data['total_images'] ?? 0 ),
				'total_images'       => $data['total_images'] ?? 0,
				'optimization_score' => $data['optimization_score'] ?? 0,
			) );

		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle clear cache request
	 */
	public function handle_clear_cache(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		// Clear all AI-first caches
		delete_post_meta( $post_id, '_fp_seo_qa_pairs' );
		delete_post_meta( $post_id, '_fp_seo_conversational_variants' );
		delete_post_meta( $post_id, '_fp_seo_embeddings' );
		delete_post_meta( $post_id, '_fp_seo_image_optimization' );
		delete_post_meta( $post_id, '_fp_seo_entities' );
		delete_post_meta( $post_id, '_fp_seo_relationships' );

		wp_send_json_success( array( 'message' => 'AI cache cleared successfully' ) );
	}

	/**
	 * Handle batch Q&A generation
	 */
	public function handle_batch_generate_qa(): void {
		check_ajax_referer( 'fp_seo_ai_first_bulk', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => 'No posts selected' ), 400 );
		}

		$extractor = new QAPairExtractor();
		$results   = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ( $post_ids as $post_id ) {
			try {
				$qa_pairs = $extractor->extract_qa_pairs( $post_id, true );
				$results['success'][] = array(
					'post_id' => $post_id,
					'count'   => count( $qa_pairs ),
				);
			} catch ( \Exception $e ) {
				$results['failed'][] = array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				);
			}

			// Rate limiting
			usleep( 500000 ); // 0.5 seconds
		}

		wp_send_json_success( array(
			'message' => sprintf( 'Processed %d posts: %d success, %d failed', 
				count( $post_ids ),
				count( $results['success'] ),
				count( $results['failed'] )
			),
			'results' => $results,
		) );
	}
}


