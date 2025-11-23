<?php
/**
 * AJAX handler for AI content generation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\Logger;

/**
 * Handles AJAX requests for AI-powered SEO generation.
 */
class AiAjaxHandler {

	/**
	 * OpenAI client instance.
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new OpenAiClient();
	}

	/**
	 * Register AJAX hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_generate_ai_content', array( $this, 'handle_generate_request' ) );
		
		// Register error handler to catch fatal errors
		if ( ! has_action( 'shutdown', array( $this, 'handle_fatal_error' ) ) ) {
			add_action( 'shutdown', array( $this, 'handle_fatal_error' ) );
		}
	}
	
	/**
	 * Handle fatal errors that might occur during AJAX requests.
	 */
	public function handle_fatal_error(): void {
		if ( ! wp_doing_ajax() ) {
			return;
		}
		
		$error = error_get_last();
		if ( null !== $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			// Only handle if it's our AJAX action
			if ( isset( $_POST['action'] ) && 'fp_seo_generate_ai_content' === $_POST['action'] ) {
				Logger::error( 'Fatal error in AJAX request', array(
					'message' => $error['message'],
					'file' => $error['file'],
					'line' => $error['line'],
				) );
				
				// Try to send error response if headers not sent
				if ( ! headers_sent() ) {
					wp_send_json_error(
						array(
							'message' => __( 'Errore fatale: ', 'fp-seo-performance' ) . $error['message'],
						),
						500
					);
				}
			}
		}
	}

	/**
	 * Handle AI content generation request.
	 */
	public function handle_generate_request(): void {
		// Ensure no output before JSON response
		if ( ob_get_level() > 0 ) {
			ob_clean();
		}
		
		// Set proper headers
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
		}
		
		try {
			// Verify nonce.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fp_seo_ai_generate' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Verifica di sicurezza fallita. Ricarica la pagina e riprova.', 'fp-seo-performance' ),
					),
					403
				);
				return;
			}

			// Check permissions.
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Permessi insufficienti.', 'fp-seo-performance' ),
					),
					403
				);
				return;
			}

			// Check if OpenAI API key is configured.
			$api_key = Options::get_option( 'ai.openai_api_key', '' );
			
			// Debug logging to help diagnose API key issues
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$all_options = Options::get();
				Logger::debug( 'AI AJAX Handler - API key check', array(
					'api_key_length' => strlen( $api_key ),
					'api_key_empty' => empty( $api_key ),
					'ai_section_exists' => isset( $all_options['ai'] ),
					'ai_openai_api_key_exists' => isset( $all_options['ai']['openai_api_key'] ),
					'ai_openai_api_key_length' => isset( $all_options['ai']['openai_api_key'] ) ? strlen( $all_options['ai']['openai_api_key'] ) : 0,
					'option_key' => Options::OPTION_KEY,
				) );
			}
			
			if ( empty( $api_key ) ) {
				// Try direct database check as fallback
				$direct_check = get_option( Options::OPTION_KEY, array() );
				$direct_api_key = $direct_check['ai']['openai_api_key'] ?? '';
				
				if ( ! empty( $direct_api_key ) ) {
					// API key exists in DB but not being read correctly - clear cache
					\FP\SEO\Utils\Cache::delete( 'options_data' );
					wp_cache_delete( Options::OPTION_KEY, 'options' );
					wp_cache_delete( 'alloptions', 'options' );
					
					// Retry after cache clear
					$api_key = Options::get_option( 'ai.openai_api_key', '' );
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::debug( 'API key found in direct check, cleared cache and retried', array(
							'retry_api_key_length' => strlen( $api_key ),
						) );
					}
				}
				
				if ( empty( $api_key ) ) {
					wp_send_json_error(
						array(
							'message' => __( 'Chiave API OpenAI non configurata. Vai in Impostazioni > FP SEO Performance > AI per configurare la chiave API.', 'fp-seo-performance' ),
						),
						400
					);
					return;
				}
			}

			// Check if AI is enabled.
			if ( ! Options::get_option( 'ai.enable_auto_generation', true ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'La generazione AI è disabilitata nelle impostazioni.', 'fp-seo-performance' ),
					),
					400
				);
				return;
			}
		} catch ( \Exception $e ) {
			Logger::error( 'AI handler validation error', array(
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			) );
			wp_send_json_error(
				array(
					'message' => __( 'Errore di validazione: ', 'fp-seo-performance' ) . $e->getMessage(),
				),
				500
			);
			return;
		}

		// Validate input.
		$post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$content       = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$focus_keyword = isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '';

		if ( 0 === $post_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'ID post non valido.', 'fp-seo-performance' ),
				),
				400
			);
		}

		if ( empty( $content ) && empty( $title ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contenuto o titolo richiesto per la generazione AI.', 'fp-seo-performance' ),
				),
				400
			);
		}

		// Check if user can edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Non hai i permessi per modificare questo post.', 'fp-seo-performance' ),
				),
				403
			);
		}

		// Generate AI content.
		try {
			Logger::debug( 'Starting AI SEO generation', array(
				'post_id' => $post_id,
				'content_length' => strlen( $content ),
				'title' => $title,
				'focus_keyword' => $focus_keyword,
			) );
			
			// Verify client is initialized
			if ( ! $this->client->is_configured() ) {
				// Re-check API key for logging
				$api_key_check = Options::get_option( 'ai.openai_api_key', '' );
				Logger::error( 'OpenAI client not configured', array(
					'api_key_present' => ! empty( $api_key_check ),
					'api_key_length' => strlen( $api_key_check ),
				) );
				wp_send_json_error(
					array(
						'message' => __( 'Client OpenAI non configurato correttamente.', 'fp-seo-performance' ),
					),
					500
				);
				return;
			}
			
			// Verify client instance is valid
			if ( ! $this->client instanceof OpenAiClient ) {
				Logger::error( 'Invalid OpenAI client instance', array(
					'client_type' => get_class( $this->client ),
				) );
				wp_send_json_error(
					array(
						'message' => __( 'Errore di inizializzazione del client OpenAI.', 'fp-seo-performance' ),
					),
					500
				);
				return;
			}
			
			// Call generate_seo_suggestions with error handling
			try {
				$result = $this->client->generate_seo_suggestions( $post_id, $content, $title, $focus_keyword );
			} catch ( \Error $e ) {
				// Catch fatal errors (PHP 7+)
				Logger::error( 'Fatal error in generate_seo_suggestions', array(
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
				wp_send_json_error(
					array(
						'message' => __( 'Errore fatale durante la generazione: ', 'fp-seo-performance' ) . $e->getMessage(),
					),
					500
				);
				return;
			} catch ( \Exception $e ) {
				// Catch regular exceptions
				Logger::error( 'Exception in generate_seo_suggestions', array(
					'message' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
				wp_send_json_error(
					array(
						'message' => __( 'Errore durante la generazione: ', 'fp-seo-performance' ) . $e->getMessage(),
					),
					500
				);
				return;
			}
			
			// Verify result structure
			if ( ! is_array( $result ) ) {
				Logger::error( 'Invalid result structure from generate_seo_suggestions', array(
					'result_type' => gettype( $result ),
					'result' => $result,
				) );
				wp_send_json_error(
					array(
						'message' => __( 'Risposta non valida dal client OpenAI.', 'fp-seo-performance' ),
					),
					500
				);
				return;
			}
			
			Logger::debug( 'AI generation result received', array( 
				'success' => $result['success'] ?? false,
				'has_error' => isset( $result['error'] ),
				'has_data' => isset( $result['data'] ),
			) );

			if ( ! isset( $result['success'] ) || ! $result['success'] ) {
				$error_msg = $result['error'] ?? __( 'Errore sconosciuto durante la generazione AI.', 'fp-seo-performance' );
				Logger::error( 'AI generation failed', array(
					'error' => $error_msg,
					'debug' => $result['debug'] ?? array(),
					'result' => $result,
				) );
				
				wp_send_json_error(
					array(
						'message' => $error_msg,
						'debug' => $result['debug'] ?? array(),
					),
					500
				);
				return;
			}

			Logger::debug( 'AI generation successful' );
			
			// Return generated data.
			wp_send_json_success(
				array(
					'seo_title'        => $result['data']['seo_title'] ?? '',
					'meta_description' => $result['data']['meta_description'] ?? '',
					'slug'             => $result['data']['slug'] ?? '',
					'focus_keyword'    => $result['data']['focus_keyword'] ?? '',
					'message'          => __( 'Contenuto SEO generato con successo!', 'fp-seo-performance' ),
				)
			);
		} catch ( \Throwable $e ) {
			Logger::error( 'AI generation exception', array(
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			wp_send_json_error(
				array(
					'message' => __( 'Errore durante la generazione AI: ', 'fp-seo-performance' ) . $e->getMessage(),
				),
				500
			);
		}
	}
}

