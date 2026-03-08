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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Container;

/**
 * Handles AJAX for AI-first features
 */
class AiFirstAjaxHandler {
	use AjaxValidationTrait;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Service container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 * @param Container            $container    Service container instance.
	 * @param LoggerInterface      $logger       Logger instance.
	 */
	public function __construct( HookManagerInterface $hook_manager, Container $container, LoggerInterface $logger ) {
		$this->hook_manager = $hook_manager;
		$this->container    = $container;
		$this->logger       = $logger;
	}

	/**
	 * Register AJAX hooks
	 */
	public function register(): void {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		if ( $debug ) {
			error_log( '[FP-SEO] AiFirstAjaxHandler::register - Entry point' );
		}
		$actions = array(
			'fp_seo_generate_qa'         => 'handle_generate_qa',
			'fp_seo_generate_faq'         => 'handle_generate_faq',
			'fp_seo_generate_howto'       => 'handle_generate_howto',
			'fp_seo_generate_variants'    => 'handle_generate_variants',
			'fp_seo_generate_entities'    => 'handle_generate_entities',
			'fp_seo_generate_embeddings'  => 'handle_generate_embeddings',
			'fp_seo_optimize_images'      => 'handle_optimize_images',
			'fp_seo_clear_ai_cache'       => 'handle_clear_cache',
			'fp_seo_batch_generate_qa'    => 'handle_batch_generate_qa',
		);

		foreach ( $actions as $action => $method ) {
			$hook = 'wp_ajax_' . $action;
			if ( $debug ) {
				error_log( '[FP-SEO] AiFirstAjaxHandler::register - Registering hook: ' . $hook . ' -> ' . $method );
			}

			// Store handler instance reference for closure
			$handler = $this;

			// Register with a wrapper closure that ensures the handler is accessible
			$this->hook_manager->add_action( $hook, function() use ( $handler, $method, $hook, $debug ) {
				if ( $debug ) {
					error_log( '[FP-SEO] AiFirstAjaxHandler - Wrapper called for hook: ' . $hook . ', method: ' . $method );
				}
				try {
					if ( ! method_exists( $handler, $method ) ) {
						if ( $debug ) {
							error_log( '[FP-SEO] AiFirstAjaxHandler - Method does not exist: ' . $method );
						}
						wp_send_json_error( array( 'message' => 'Handler method not found' ), 500 );
						return;
					}
					if ( $debug ) {
						error_log( '[FP-SEO] AiFirstAjaxHandler - Calling method: ' . $method );
					}
					call_user_func( array( $handler, $method ) );
				} catch ( \Throwable $e ) {
					if ( $debug ) {
						error_log( '[FP-SEO] AiFirstAjaxHandler - Exception in wrapper: ' . $e->getMessage() );
						error_log( '[FP-SEO] AiFirstAjaxHandler - Stack trace: ' . $e->getTraceAsString() );
					}
				wp_send_json_error( array( 'message' => 'Internal server error: ' . $e->getMessage() ), 500 );
				return;
			}
		} );
		}
		if ( $debug ) {
			error_log( '[FP-SEO] AiFirstAjaxHandler::register - All hooks registered' );
		}
	}

	/**
	 * Handle Q&A generation request
	 */
	public function handle_generate_qa(): void {
		@set_time_limit( 60 );
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( '[FP-SEO] AiFirstAjaxHandler::handle_generate_qa - Entry point START' );
			$this->logger->debug( 'AiFirstAjaxHandler::handle_generate_qa - Entry point', array(
				'post_id' => isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0,
				'action'  => isset( $_POST['action'] ) ? $_POST['action'] : 'none',
				'nonce'   => isset( $_POST['nonce'] ) ? substr( $_POST['nonce'], 0, 10 ) . '...' : 'none',
			) );
		}

		try {
			$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
			if ( null === $validation ) {
				return; // Error already sent
			}

			$post_id = $validation['post_id'];
			$post    = $validation['post'];

			if ( $debug ) {
				$this->logger->debug( 'AiFirstAjaxHandler::handle_generate_qa - Starting', array(
					'post_id'        => $post_id,
					'post_title'     => $post->post_title,
					'content_length' => strlen( $post->post_content ),
					'has_content'    => ! empty( $post->post_content ),
				) );
			}

		$extractor = $this->container->get( QAPairExtractor::class );
		if ( ! $extractor instanceof QAPairExtractor ) {
			wp_send_json_error( array( 'message' => 'QAPairExtractor service not available.' ), 500 );
			return;
		}

		if ( $debug ) {
			$this->logger->debug( 'AiFirstAjaxHandler::handle_generate_qa - Extractor obtained', array(
				'extractor_class' => get_class( $extractor ),
			) );
		}
		
	// Check if OpenAI is configured
		$openai_client = $this->container->get( \FP\SEO\Integrations\OpenAiClient::class );
		if ( ! $openai_client || ! $openai_client->is_configured() ) {
				wp_send_json_error( array(
					'message' => __( 'OpenAI API key non configurata. Vai in Impostazioni > FP SEO > AI per configurarla.', 'fp-seo-performance' ),
				), 400 );
				return;
			}

			$qa_pairs = $extractor->extract_qa_pairs( $post_id, true );

			if ( $debug ) {
				$saved_qa_pairs = get_post_meta( $post_id, '_fp_seo_qa_pairs', true );
				$this->logger->debug( 'AiFirstAjaxHandler::handle_generate_qa - Completed', array(
					'post_id'         => $post_id,
					'extracted_count' => count( $qa_pairs ),
					'saved_count'     => is_array( $saved_qa_pairs ) ? count( $saved_qa_pairs ) : 0,
				) );
			}

			wp_send_json_success( array(
				'message'  => sprintf( 'Generated %d Q&A pairs', count( $qa_pairs ) ),
				'qa_pairs' => $qa_pairs,
				'total'    => count( $qa_pairs ),
			) );

		} catch ( \Throwable $e ) {
			if ( $debug ) {
				error_log( '[FP-SEO] AiFirstAjaxHandler::handle_generate_qa - Error: ' . $e->getMessage() );
			}
			$this->handle_ajax_exception_with_logger( $e, $post_id ?? null, 'AiFirstAjaxHandler::handle_generate_qa' );
		}
	}

	/**
	 * Handle AJAX exception with injected logger (replaces trait method).
	 *
	 * @param \Throwable $e       Throwable to handle.
	 * @param int|null   $post_id Optional post ID for logging.
	 * @param string     $context Context for logging.
	 * @return void
	 */
	private function handle_ajax_exception_with_logger( \Throwable $e, ?int $post_id = null, string $context = '' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->error( $context . ' - Error', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		return;
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
			$extractor = $this->container->get( QAPairExtractor::class );
			if ( ! $extractor instanceof QAPairExtractor ) {
				wp_send_json_error( array( 'message' => 'QAPairExtractor service not available.' ), 500 );
				return;
			}
			$qa_pairs  = $extractor->extract_qa_pairs( $post_id, true ); // Force regeneration

			if ( empty( $qa_pairs ) ) {
				wp_send_json_error( array( 'message' => 'Nessuna FAQ generata. Assicurati che il contenuto sia sufficiente e che la chiave API OpenAI sia configurata.' ), 400 );
				return;
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

			// Q&A pairs are already saved in _fp_seo_qa_pairs (unified system)
			// FaqSchemaGenerator now reads directly from _fp_seo_qa_pairs
			// No need to save separately in _fp_seo_faq_questions
			if ( ! empty( $faq_questions ) ) {
				CacheHelper::clear_schema_cache( $post_id );
			}

			wp_send_json_success( array(
				'message'       => sprintf( 'Generated %d Q&A pairs', count( $faq_questions ) ),
				'faq_questions' => $faq_questions,
				'total'         => count( $faq_questions ),
			) );

	} catch ( \Throwable $e ) {
		$this->handle_ajax_exception_with_logger( $e, $post_id, 'AiFirstAjaxHandler::handle_generate_faq' );
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
			'message'   => sprintf( 'Generate %d step della guida', count( $result['steps'] ?? array() ) ),
			'steps'     => $result['steps'] ?? array(),
			'total'     => count( $result['steps'] ?? array() ),
			'all_steps' => $result['all_steps'] ?? array(),
		) );

	} catch ( \Throwable $e ) {
		$this->handle_ajax_exception_with_logger( $e, $post_id, 'AiFirstAjaxHandler::handle_generate_howto' );
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
			$generator = $this->container->get( ConversationalVariants::class );
			if ( ! $generator instanceof ConversationalVariants ) {
				wp_send_json_error( array( 'message' => 'ConversationalVariants service not available.' ), 500 );
				return;
			}
			$variants  = $generator->generate_variants( $post_id, true );

			wp_send_json_success( array(
			'message'  => sprintf( 'Generated %d variants', count( $variants ) ),
			'variants' => $variants,
		) );
		return;

	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		return;
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
			$graph = $this->container->get( EntityGraph::class );
			if ( ! $graph instanceof EntityGraph ) {
				wp_send_json_error( array( 'message' => 'EntityGraph service not available.' ), 500 );
				return;
			}
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
		return;

	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		return;
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
			$generator = $this->container->get( EmbeddingsGenerator::class );
			if ( ! $generator instanceof EmbeddingsGenerator ) {
				wp_send_json_error( array( 'message' => 'EmbeddingsGenerator service not available.' ), 500 );
				return;
			}
			$data      = $generator->generate_embeddings( $post_id, true );

		if ( isset( $data['error'] ) ) {
			wp_send_json_error( array( 'message' => $data['message'] ?? 'Failed to generate embeddings' ), 500 );
			return;
		}

		wp_send_json_success( array(
				'message'      => sprintf( 'Generated embeddings for %d chunks', $data['total_chunks'] ?? 0 ),
			'total_chunks' => $data['total_chunks'] ?? 0,
			'model'        => $data['model'] ?? '',
		) );
		return;

	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
		return;
	}
}

	/**
	 * Handle image optimization request
	 */
	public function handle_optimize_images(): void {
		$validation = $this->validate_ajax_post_request( 'fp_seo_ai_first' );
		if ( null === $validation ) {
			return; // Error already sent
		}

		$post_id = $validation['post_id'];

		try {
			// Get ImageSeoOptimizer from container
			$image_seo_optimizer = $this->container->get( 'image_seo_optimizer' );
			if ( null === $image_seo_optimizer ) {
				wp_send_json_error( array( 'message' => __( 'Image optimizer not available.', 'fp-seo-performance' ) ), 500 );
				return;
			}

			// Optimize images for SEO
			$result = $image_seo_optimizer->optimize_images_seo( $post_id );

			if ( ( $result['optimized_count'] ?? 0 ) > 0 ) {
				wp_send_json_success( array(
					'message' => sprintf(
						__( 'Successfully optimized %d image(s) for SEO.', 'fp-seo-performance' ),
						$result['optimized_count']
					),
					'optimized_count' => $result['optimized_count'],
					'errors' => $result['errors'] ?? array(),
				) );
			} else {
				wp_send_json_success( array(
					'message' => __( 'No images needed optimization or no images found.', 'fp-seo-performance' ),
					'optimized_count' => 0,
					'errors' => $result['errors'] ?? array(),
				) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->error( 'Image SEO optimization failed', array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				) );
			}
			wp_send_json_error( array(
				'message' => __( 'Failed to optimize images: ', 'fp-seo-performance' ) . $e->getMessage(),
			) );
			return;
		}
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
			return;
		}

		$post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => 'No posts selected' ), 400 );
			return;
		}

		$extractor = $this->container->get( QAPairExtractor::class );
		if ( ! $extractor instanceof QAPairExtractor ) {
			wp_send_json_error( array( 'message' => 'QAPairExtractor service not available.' ), 500 );
			return;
		}

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
			} catch ( \Throwable $e ) {
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


