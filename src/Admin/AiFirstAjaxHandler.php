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
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( array( 'message' => 'Post not found' ), 404 );
			}
			
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'AiFirstAjaxHandler::handle_generate_qa - Error', array(
					'post_id' => $post_id,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle FAQ Schema generation request
	 */
	public function handle_generate_faq(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( array( 'message' => 'Post not found' ), 404 );
			}

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
				
				// Clear cache
				clean_post_cache( $post_id );
				wp_cache_delete( $post_id, 'post_meta' );
				$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
				wp_cache_delete( $cache_key );
			}

			wp_send_json_success( array(
				'message'       => sprintf( 'Generate %d FAQ questions', count( $faq_questions ) ),
				'faq_questions' => $faq_questions,
				'total'         => count( $faq_questions ),
			) );

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'AiFirstAjaxHandler::handle_generate_faq - Error', array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				) );
			}
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * Handle HowTo Schema generation request
	 */
	public function handle_generate_howto(): void {
		check_ajax_referer( 'fp_seo_ai_first', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
		}

		try {
			$post = get_post( $post_id );
			if ( ! $post ) {
				wp_send_json_error( array( 'message' => 'Post not found' ), 404 );
			}

			// Generate HowTo steps using AI
			$openai_client = new \FP\SEO\Integrations\OpenAiClient();
			
			if ( ! $openai_client->is_configured() ) {
				wp_send_json_error( array( 'message' => 'OpenAI API key non configurata. Vai in Impostazioni > FP SEO.' ), 400 );
			}

			// Prepare content
			$content = $post->post_content;
			if ( strpos( $content, '[vc_' ) !== false ) {
				if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
					$wpbakery_text = \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $content );
					if ( ! empty( $wpbakery_text ) ) {
						$content = $wpbakery_text;
					} else {
						$content = do_shortcode( $content );
					}
				} else {
					$content = do_shortcode( $content );
				}
			}
			$content = wp_strip_all_tags( $content );
			$content = trim( $content );

			if ( empty( $content ) ) {
				wp_send_json_error( array( 'message' => 'Il contenuto del post è vuoto. Aggiungi contenuto prima di generare gli step.' ), 400 );
			}

			// Build prompt for HowTo steps generation
			$title = $post->post_title;
			$prompt = sprintf(
				'Analizza il seguente contenuto e genera una guida step-by-step in formato HowTo Schema.

Titolo: %s

Contenuto:
%s

Istruzioni:
1. Estrai 4-8 step logici e sequenziali dal contenuto
2. Ogni step deve avere:
   - Un nome chiaro e conciso (max 60 caratteri) che inizia con un verbo d\'azione (es: "Installa...", "Apri...", "Clicca...", "Inserisci...")
   - Una descrizione dettagliata (50-200 parole) che spiega come completare lo step
3. Gli step devono essere in ordine logico e sequenziale
4. Ogni step deve essere autonomo e comprensibile
5. Usa un linguaggio chiaro e diretto

Rispondi SOLO con JSON valido in questo formato:
{
  "steps": [
    {
      "name": "Nome dello step (verbo d\'azione)",
      "text": "Descrizione dettagliata e completa dello step (50-200 parole)"
    }
  ]
}

Rispondi SOLO con il JSON, senza testo aggiuntivo.',
				esc_html( $title ),
				esc_html( mb_substr( $content, 0, 4000 ) ) // Limit content to avoid token limits
			);

			// Generate with AI
			$response = $openai_client->generate_content( $prompt, array(
				'model'                => 'gpt-4o-mini',
				'temperature'          => 0.3,
				'max_completion_tokens' => 2000,
			) );

			// Parse response
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			$data = json_decode( $response, true );

			if ( ! is_array( $data ) || ! isset( $data['steps'] ) || ! is_array( $data['steps'] ) ) {
				wp_send_json_error( array( 'message' => 'Errore nel parsing della risposta AI. Riprova.' ), 500 );
			}

			// Convert to HowTo format
			$howto_steps = array();
			foreach ( $data['steps'] as $step ) {
				if ( ! isset( $step['name'] ) || ! isset( $step['text'] ) ) {
					continue;
				}

				$name = sanitize_text_field( $step['name'] );
				$text = wp_kses_post( $step['text'] );

				if ( empty( $name ) || empty( $text ) ) {
					continue;
				}

				$howto_steps[] = array(
					'name' => $name,
					'text' => $text,
					'url'  => '', // Image URL is optional, leave empty
				);
			}

			if ( empty( $howto_steps ) ) {
				wp_send_json_error( array( 'message' => 'Nessuno step generato. Assicurati che il contenuto contenga istruzioni o procedure.' ), 400 );
			}

			// Get existing HowTo data
			$howto_data = get_post_meta( $post_id, '_fp_seo_howto', true );
			if ( ! is_array( $howto_data ) ) {
				$howto_data = array(
					'name'        => '',
					'description' => '',
					'total_time'  => '',
					'steps'       => array(),
				);
			}

			// Merge with existing steps (append new steps)
			$howto_data['steps'] = array_merge( $howto_data['steps'] ?? array(), $howto_steps );

			// Save HowTo data
			update_post_meta( $post_id, '_fp_seo_howto', $howto_data );

			// Clear cache
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'post_meta' );
			$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
			wp_cache_delete( $cache_key );

			wp_send_json_success( array(
				'message'     => sprintf( 'Generate %d step della guida', count( $howto_steps ) ),
				'steps'       => $howto_steps,
				'total'       => count( $howto_steps ),
				'all_steps'   => $howto_data['steps'], // Return all steps (existing + new)
			) );

		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'AiFirstAjaxHandler::handle_generate_howto - Error', array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				) );
			}
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


