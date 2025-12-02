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

use FP\SEO\Admin\Helpers\CacheHelper;
use FP\SEO\Admin\Traits\AjaxValidationTrait;
use FP\SEO\AI\HowToGenerator;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\AI\EmbeddingsGenerator;
use FP\SEO\GEO\EntityGraph;
use FP\SEO\GEO\MultiModalOptimizer;

/**
 * Handles AJAX for AI-first features
 */
class AiFirstAjaxHandler {
	use AjaxValidationTrait;

	/**
	 * Register AJAX hooks
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_generate_qa', array( $this, 'handle_generate_qa' ) );
		add_action( 'wp_ajax_fp_seo_generate_faq', array( $this, 'handle_generate_faq' ) );
		add_action( 'wp_ajax_fp_seo_generate_howto', array( $this, 'handle_generate_howto' ) );
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
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];
		$post    = $validation['post'];

		try {
			
			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::debug( 'AiFirstAjaxHandler::handle_generate_qa - Starting', array(
					'post_id' => $post_id,
					'post_title' => $post->post_title,
					'content_length' => strlen( $post->post_content ),
					'has_content' => ! empty( $post->post_content ),
				) );
			}
			
			$extractor = new QAPairExtractor();
			$qa_pairs  = $extractor->extract_qa_pairs( $post_id, true ); // Force regeneration
			
			// Verify Q&A pairs were saved
			$saved_qa_pairs = get_post_meta( $post_id, '_fp_seo_qa_pairs', true );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::debug( 'AiFirstAjaxHandler::handle_generate_qa - Completed', array(
					'post_id' => $post_id,
					'extracted_count' => count( $qa_pairs ),
					'saved_count' => is_array( $saved_qa_pairs ) ? count( $saved_qa_pairs ) : 0,
					'saved_is_array' => is_array( $saved_qa_pairs ),
				) );
			}

			wp_send_json_success( array(
				'message'  => sprintf( 'Generated %d Q&A pairs', count( $qa_pairs ) ),
				'qa_pairs' => $qa_pairs,
				'total'    => count( $qa_pairs ),
			) );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, $post_id, 'AiFirstAjaxHandler::handle_generate_qa' );
		}
	}

	/**
	 * Handle FAQ Schema generation request
	 */
	public function handle_generate_faq(): void {
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];
		$post    = $validation['post'];

		try {

			// Generate Q&A pairs using AI
			$extractor = new QAPairExtractor();
			$qa_pairs  = $extractor->extract_qa_pairs( $post_id, true ); // Force regeneration

			if ( empty( $qa_pairs ) ) {
				wp_send_json_error( array( 'message' => 'Nessuna FAQ generata. Assicurati che il contenuto sia sufficiente e che la chiave API OpenAI sia configurata.' ), 400 );
			}

			// Convert Q&A pairs to FAQ Schema format (simplified: only question and answer)
			$faq_questions = array();
			foreach ( $qa_pairs as $qa_pair ) {
				if ( isset( $qa_pair['question'] ) && isset( $qa_pair['answer'] ) ) {
					$faq_questions[] = array(
						'question' => sanitize_text_field( $qa_pair['question'] ),
						'answer'   => wp_kses_post( $qa_pair['answer'] ),
					);
				}
			}

			// Limit to 5-8 FAQs for better quality
			if ( count( $faq_questions ) > 8 ) {
				$faq_questions = array_slice( $faq_questions, 0, 8 );
			}

			// Save FAQ Schema data
			if ( ! empty( $faq_questions ) ) {
				update_post_meta( $post_id, '_fp_seo_faq_questions', $faq_questions );
				CacheHelper::clear_schema_cache( $post_id );
			}

			wp_send_json_success( array(
				'message'       => sprintf( 'Generate %d FAQ questions', count( $faq_questions ) ),
				'faq_questions' => $faq_questions,
				'total'         => count( $faq_questions ),
			) );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, $post_id, 'AiFirstAjaxHandler::handle_generate_faq' );
		}
	}

	/**
	 * Handle HowTo Schema generation request
	 */
	public function handle_generate_howto(): void {
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];
		$post    = $validation['post'];

		try {
			$generator = new HowToGenerator();
			$result    = $generator->generate_steps( $post_id, $post );

			// Clear cache
			CacheHelper::clear_schema_cache( $post_id );

			wp_send_json_success( array(
				'message'   => sprintf( 'Generate %d step della guida', count( $result['steps'] ) ),
				'steps'     => $result['steps'],
				'total'     => count( $result['steps'] ),
				'all_steps' => $result['all_steps'], // Return all steps (existing + new)
			) );

		} catch ( \Exception $e ) {
			$this->handle_ajax_exception( $e, $post_id, 'AiFirstAjaxHandler::handle_generate_howto' );
		}
	}

	/**
	 * Handle variants generation request
	 */
	public function handle_generate_variants(): void {
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];

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
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];

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
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];

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
		// Image optimization removed - no longer managing images
		wp_send_json_error( array( 'message' => __( 'Image optimization feature has been removed.', 'fp-seo-performance' ) ), 410 );
	}

	/**
	 * Handle clear cache request
	 */
	public function handle_clear_cache(): void {
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];

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


