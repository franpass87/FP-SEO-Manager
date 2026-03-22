<?php
/**
 * OpenAI API Client for SEO content generation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

use OpenAI\Client as OpenAiClientClass;
// Don't import OpenAI\OpenAI to avoid triggering autoloader before we can control it
// Use fully qualified class name \OpenAI\OpenAI instead
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;

/**
 * Handles OpenAI API integration for AI-powered SEO suggestions.
 */
class OpenAiClient {

	/**
	 * OpenAI client instance.
	 *
	 * @var OpenAiClientClass|null
	 */
	private ?OpenAiClientClass $client = null;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Options instance.
	 *
	 * @var OptionsInterface
	 */
	private OptionsInterface $options;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface  $logger  Logger instance.
	 * @param OptionsInterface $options Options instance.
	 */
	public function __construct( LoggerInterface $logger, OptionsInterface $options ) {
		$this->logger  = $logger;
		$this->options = $options;
	}

	/**
	 * Initialize the OpenAI client.
	 *
	 * @return OpenAiClientClass|null
	 */
	private function get_client(): ?OpenAiClientClass {
		// Return cached client if available
		if ( null !== $this->client ) {
			return $this->client;
		}

		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return null;
		}

		$factory_class = '\\OpenAI\\OpenAI';
		
		try {
			// Use call_user_func with fully qualified class name
			// The custom autoloader registered in Kernel should handle loading safely
			$this->client = call_user_func( array( $factory_class, 'client' ), $api_key );
			return $this->client;
		} catch ( \Throwable $e ) {
			$error_message = $e->getMessage();
			
			// If it's a redeclaration error, log it
			if ( strpos( $error_message, 'Cannot redeclare' ) !== false ) {
				$this->logger->error( 'OpenAI class redeclaration error', array(
					'error' => $error_message,
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'class_exists' => class_exists( $factory_class, false ),
				) );
			} else {
				$this->logger->error( 'Failed to create OpenAI client', array(
					'error' => $error_message,
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			return null;
		}
	}

	/**
	 * Get the OpenAI API key from options.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		$api_key = $this->options->get_option( 'ai.openai_api_key', '' );
		
		// Debug logging to help diagnose API key issues
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$all_options = $this->options->get();
			$this->logger->debug( 'OpenAI API key retrieval', array(
				'api_key_length' => strlen( $api_key ),
				'api_key_empty' => empty( $api_key ),
				'ai_section_exists' => isset( $all_options['ai'] ),
				'ai_openai_api_key_exists' => isset( $all_options['ai']['openai_api_key'] ),
				'ai_openai_api_key_length' => isset( $all_options['ai']['openai_api_key'] ) ? strlen( $all_options['ai']['openai_api_key'] ) : 0,
			) );
		}
		
		return $api_key;
	}

	/**
	 * Check if OpenAI is configured and ready.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Generate SEO content suggestions for a post.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $content       Post content.
	 * @param string $title         Current post title.
	 * @param string $focus_keyword Optional focus keyword to optimize for.
	 * @return array{success: bool, data?: array{seo_title: string, meta_description: string, slug: string, focus_keyword: string}, error?: string}
	 * @throws \RuntimeException If OpenAI API call fails.
	 * @throws \InvalidArgumentException If post_id is invalid.
	 * 
	 * @example
	 * $client = new OpenAiClient();
	 * $result = $client->generate_seo_suggestions(123, 'Post content...', 'Post Title', 'keyword');
	 * if ($result['success']) {
	 *     $seo_title = $result['data']['seo_title'];
	 *     $meta_desc = $result['data']['meta_description'];
	 * }
	 */
	public function generate_seo_suggestions( int $post_id, string $content, string $title, string $focus_keyword = '' ): array {
		// Pulisci il contenuto HTML per cache key
		$clean_content = wp_strip_all_tags( $content );
		$clean_content = substr( $clean_content, 0, 2000 );

		// Sanitize focus keyword
		$focus_keyword = sanitize_text_field( $focus_keyword );

		// Enhanced cache key with post metadata
		$post_modified = get_post_modified_time( 'U', false, $post_id );
		$cache_key = 'fp_seo_ai_' . md5( $clean_content . $title . $focus_keyword . $post_modified );
		
		// Try object cache first, then transient
		$cached = wp_cache_get( $cache_key, 'fp_seo_ai' );
		if ( false === $cached ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				// Store in object cache for faster access
				wp_cache_set( $cache_key, $cached, 'fp_seo_ai', HOUR_IN_SECONDS );
			}
		}

		if ( false !== $cached ) {
			return $cached;
		}

		$client = $this->get_client();

		if ( null === $client ) {
			return array(
				'success' => false,
				'error'   => __( 'OpenAI API key non configurata. Vai in Impostazioni > FP SEO.', 'fp-seo-performance' ),
			);
		}

		try {
			// Determina la lingua del contenuto
			$locale   = get_locale();
			$language = 'italiano';
			if ( strpos( $locale, 'en' ) === 0 ) {
				$language = 'inglese';
			}

			// Raccogli informazioni contestuali dal post
			try {
				$context = $this->gather_post_context( $post_id );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'Error gathering post context', array(
					'post_id' => $post_id,
					'error' => $e->getMessage(),
				) );
				$context = array(); // Fallback to empty context
			}

			$site_context = $this->options->get_option( 'ai.site_context', '' );
			if ( is_string( $site_context ) && '' !== trim( $site_context ) ) {
				$context['site_context'] = trim( $site_context );
			}

			// Build prompt with error handling
			try {
				$prompt = $this->build_prompt( $title, $clean_content, $language, $focus_keyword, $context );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'Error building prompt', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				return array(
					'success' => false,
					'error'   => __( 'Errore nella costruzione del prompt: ', 'fp-seo-performance' ) . $e->getMessage(),
				);
			}

		// Get model with error handling
		try {
			$model = $this->get_model();
			if ( empty( $model ) ) {
				$model = 'gpt-5.4-nano'; // Default fallback (March 2026)
			}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Error getting model', array( 'error' => $e->getMessage() ) );
			$model = 'gpt-5.4-nano'; // Default fallback (March 2026)
		}

		// Build API request parameters with correct parameter names
		$api_params = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'Sei un SEO specialist. Genera SOLO contenuti SEO in formato JSON puro, senza testo aggiuntivo.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		// GPT-5.4/GPT-5 nano/mini use max_completion_tokens, other models use max_tokens
		$model_lower = strtolower( $model );
		$uses_max_completion = preg_match( '/gpt-5(?:\.4)?-(?:nano|mini)|gpt-5-nano/', $model_lower );
		if ( $uses_max_completion ) {
			$api_params['max_completion_tokens'] = 4096;
		} else {
			$api_params['max_tokens'] = 4096;
			$api_params['temperature'] = 0.7;
		}

		$this->logger->debug( 'Calling OpenAI API', array( 'model' => $api_params['model'], 'params' => array_keys( $api_params ) ) );

		try {
			$response = $client->chat()->create( $api_params );
			$this->logger->debug( 'OpenAI API response received', array( 'type' => gettype( $response ), 'choices_count' => isset( $response->choices ) ? count( $response->choices ) : 0 ) );
			
			// Debug full response structure
			if ( isset( $response->choices[0] ) ) {
				$message = $response->choices[0]->message;
				$finish_reason = $response->choices[0]->finishReason ?? 'unknown';
				
				$this->logger->debug( 'OpenAI response details', array(
					'finish_reason' => $finish_reason,
					'message_role' => $message->role ?? 'NULL',
					'has_content' => ! empty( $message->content ),
					'has_refusal' => ! empty( $message->refusal ),
				) );
				
				// Check if there's a refusal
				if ( ! empty( $message->refusal ) ) {
					$this->logger->error( 'OpenAI request refused', array( 'refusal' => $message->refusal ) );
					return array(
						'success' => false,
						'error'   => sprintf( __( 'OpenAI ha rifiutato la richiesta: %s', 'fp-seo-performance' ), $message->refusal ),
					);
				}
			} else {
				$this->logger->error( 'No choices in OpenAI response' );
				return array(
					'success' => false,
					'error'   => __( 'Risposta OpenAI non valida: nessuna scelta disponibile.', 'fp-seo-performance' ),
				);
			}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'OpenAI API call exception', array( 'message' => $e->getMessage() ) );
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Errore API OpenAI: %s', 'fp-seo-performance' ), $e->getMessage() ),
			);
		}

		$result = $response->choices[0]->message->content ?? '';

		$this->logger->debug( 'Extracted OpenAI result', array( 'length' => strlen( $result ) ) );

			if ( empty( $result ) ) {
				$this->logger->error( 'Empty result from OpenAI API', array(
					'model' => $api_params['model'],
					'finish_reason' => $response->choices[0]->finishReason ?? 'unknown',
				) );
				
				// Messaggio più dettagliato per l'utente
				$error_details = array(
					'Modello: ' . $api_params['model'],
					'Finish reason: ' . ( $response->choices[0]->finishReason ?? 'unknown' ),
					'Possibile causa: Crediti API esauriti o rate limiting',
				);
				
				return array(
					'success' => false,
					'error'   => __( 'OpenAI ha restituito una risposta vuota. Possibili cause: 1) Crediti API esauriti - verifica su platform.openai.com/usage, 2) Rate limiting - attendi 60 secondi, 3) Problema temporaneo OpenAI - riprova più tardi.', 'fp-seo-performance' ),
					'debug'   => $error_details,
				);
			}

			// Parse JSON response
			$parsed = $this->parse_ai_response( $result );

			if ( null === $parsed ) {
				return array(
					'success' => false,
					'error'   => __( 'Formato risposta non valido da OpenAI.', 'fp-seo-performance' ),
				);
			}

			$response_data = array(
				'success' => true,
				'data'    => $parsed,
			);

			// Cache in both object cache and transient
			wp_cache_set( $cache_key, $response_data, 'fp_seo_ai', HOUR_IN_SECONDS );
			set_transient( $cache_key, $response_data, WEEK_IN_SECONDS );

			return $response_data;

		} catch ( \Throwable $e ) {
			$this->logger->error( 'Fatal error in generate_seo_suggestions', array(
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'post_id' => $post_id,
			) );
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: error message */
					__( 'Errore OpenAI: %s', 'fp-seo-performance' ),
					$e->getMessage()
				),
				'debug' => array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				),
			);
		}
	}

	/**
	 * Gather contextual information about a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function gather_post_context( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$context = array(
			'post_type' => get_post_type( $post_id ),
			'excerpt'   => '',
			'categories' => array(),
			'tags'      => array(),
		);

		// Excerpt
		if ( ! empty( $post->post_excerpt ) ) {
			$context['excerpt'] = wp_strip_all_tags( $post->post_excerpt );
		}

		// Categorie
		$categories = get_the_category( $post_id );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $cat ) {
				$context['categories'][] = $cat->name;
			}
		}

		// Tag
		$tags = get_the_tags( $post_id );
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$context['tags'][] = $tag->name;
			}
		}

		return $context;
	}

	/**
	 * Build the AI prompt for SEO generation.
	 *
	 * @param string               $title         Post title.
	 * @param string               $content       Post content (cleaned).
	 * @param string               $language      Content language.
	 * @param string               $focus_keyword Optional focus keyword.
	 * @param array<string, mixed> $context       Post context information.
	 * @return string
	 */
	private function build_prompt( string $title, string $content, string $language, string $focus_keyword = '', array $context = array() ): string {
		// Sanitize inputs to prevent prompt injection
		$safe_focus_keyword = $this->sanitize_prompt_input( $focus_keyword );
		$safe_title = $this->sanitize_prompt_input( $title );
		$safe_content = $this->sanitize_prompt_input( $content );
		
		$keyword_instruction = '';
		if ( ! empty( $safe_focus_keyword ) ) {
			$keyword_instruction = sprintf(
				"\n\n⚠️ IMPORTANTE: Devi OBBLIGATORIAMENTE utilizzare la parola chiave '%s' nel titolo SEO e nella meta description. Questa è la keyword principale che il cliente vuole ottimizzare.",
				$safe_focus_keyword
			);
		}

		// Costruisci informazioni di contesto (sanitized)
		$context_info = '';

		if ( ! empty( $context['site_context'] ) ) {
			$safe_site = $this->sanitize_prompt_input( $context['site_context'] );
			$context_info .= "\nCONTESTO SITO (di cosa parla il sito, usa per allineare titoli e meta): " . $safe_site;
		}
		
		if ( ! empty( $context['post_type'] ) && 'post' !== $context['post_type'] ) {
			$post_type_obj   = get_post_type_object( $context['post_type'] );
			$post_type_label = ( $post_type_obj !== null )
				? ( $post_type_obj->labels->singular_name ?? $context['post_type'] )
				: $context['post_type'];
			$context_info .= "\nTipo di contenuto: " . $this->sanitize_prompt_input( $post_type_label );
		}

		if ( ! empty( $context['categories'] ) ) {
			$safe_categories = array_map( array( $this, 'sanitize_prompt_input' ), $context['categories'] );
			$context_info .= "\nCategorie: " . implode( ', ', $safe_categories );
		}

		if ( ! empty( $context['tags'] ) ) {
			$safe_tags = array_map( array( $this, 'sanitize_prompt_input' ), array_slice( $context['tags'], 0, 5 ) );
			$context_info .= "\nTag: " . implode( ', ', $safe_tags );
		}

		if ( ! empty( $context['excerpt'] ) ) {
			$safe_excerpt = $this->sanitize_prompt_input( substr( $context['excerpt'], 0, 200 ) );
			$context_info .= "\nRiassunto: " . $safe_excerpt;
		}

		// Limita contenuto a 1500 caratteri per ridurre token input
		$content_preview = substr( $safe_content, 0, 1500 );
		if ( strlen( $safe_content ) > 1500 ) {
			$content_preview .= '...';
		}
		
		return sprintf(
			'Contenuto in %s.
Titolo: %s
%s

Contenuto:
%s%s

Genera JSON:
{
  "seo_title": "max 60 caratteri%s",
  "meta_description": "max 155 caratteri",
  "slug": "url-slug-breve",
  "focus_keyword": "%s"
}

REGOLE:
- SEO title: max 60 caratteri, keyword all\'inizio
- Meta description: max 155 caratteri, invoglia al click
- Slug: lowercase, trattini, breve

Rispondi SOLO con JSON puro.',
			$language,
			$safe_title,
			$context_info,
			$content_preview,
			$keyword_instruction,
			! empty( $safe_focus_keyword ) ? ', usa keyword: ' . $safe_focus_keyword : '',
			! empty( $safe_focus_keyword ) ? $safe_focus_keyword : 'auto-detect'
		);
	}

	/**
	 * Sanitize input to prevent prompt injection attacks.
	 *
	 * @param string $input User input to sanitize.
	 * @return string Sanitized input.
	 */
	private function sanitize_prompt_input( string $input ): string {
		// Remove common prompt injection patterns
		$patterns = array(
			'/ignore\s+(previous|all|above)\s+instructions?/i',
			'/disregard\s+(previous|all|above)/i',
			'/forget\s+(previous|all|everything)/i',
			'/you\s+are\s+now/i',
			'/new\s+instructions?:/i',
			'/system\s*:/i',
			'/assistant\s*:/i',
			'/\[INST\]/i',
			'/\[\/INST\]/i',
		);

		$sanitized = $input;
		foreach ( $patterns as $pattern ) {
			$sanitized = preg_replace( $pattern, '', $sanitized ) ?? $sanitized;
		}

		// Limit length to prevent token exhaustion
		$sanitized = substr( $sanitized, 0, 5000 );

		return trim( $sanitized );
	}

	/**
	 * Parse AI JSON response.
	 *
	 * @param string $response AI response text.
	 * @return array{seo_title: string, meta_description: string, slug: string, focus_keyword: string}|null
	 */
	private function parse_ai_response( string $response ): ?array {
		// Rimuovi eventuali markdown code blocks
		$response = preg_replace( '/```json\s*/', '', $response ) ?? $response;
		$response = preg_replace( '/```\s*$/', '', $response ) ?? $response;
		$response = trim( $response );

		$data = json_decode( $response, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		// Valida la struttura
		$required_fields = array( 'seo_title', 'meta_description', 'slug', 'focus_keyword' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return null;
			}
		}

		// Sanitizza e valida i limiti di caratteri
		$seo_title        = sanitize_text_field( $data['seo_title'] );
		$meta_description = sanitize_textarea_field( $data['meta_description'] );
		$slug             = sanitize_title( $data['slug'] );
		$focus_keyword    = sanitize_text_field( $data['focus_keyword'] );

		// Tronca se supera i limiti (safety check) - MULTIBYTE SAFE
		if ( mb_strlen( $seo_title ) > 60 ) {
			$seo_title = mb_substr( $seo_title, 0, 60 );
			// Rimuovi l'ultima parola parziale (multibyte-safe)
			$last_space = mb_strrpos( $seo_title, ' ' );
			if ( false !== $last_space && $last_space > 40 ) {
				$seo_title = mb_substr( $seo_title, 0, $last_space );
			}
			$seo_title = rtrim( $seo_title, '.,;:!?' );
			// Aggiungi ellipsis solo se effettivamente troncato
			if ( mb_strlen( $seo_title ) < 60 ) {
				$seo_title .= '...';
			}
		}

		if ( mb_strlen( $meta_description ) > 155 ) {
			$meta_description = mb_substr( $meta_description, 0, 155 );
			// Rimuovi l'ultima parola parziale (multibyte-safe)
			$last_space = mb_strrpos( $meta_description, ' ' );
			if ( false !== $last_space && $last_space > 120 ) {
				$meta_description = mb_substr( $meta_description, 0, $last_space );
			}
			$meta_description = rtrim( $meta_description, '.,;:!?' );
			// Aggiungi ellipsis solo se effettivamente troncato
			if ( mb_strlen( $meta_description ) < 155 ) {
				$meta_description .= '...';
			}
		}

		return array(
			'seo_title'        => $seo_title,
			'meta_description' => $meta_description,
			'slug'             => $slug,
			'focus_keyword'    => $focus_keyword,
		);
	}

	/**
	 * Get the OpenAI model to use.
	 *
	 * @return string
	 */
	private function get_model(): string {
		return $this->options->get_option( 'ai.openai_model', 'gpt-5.4-nano' );
	}

	/**
	 * Generate content using OpenAI API.
	 *
	 * @param string $prompt The prompt to send.
	 * @param array<string, mixed> $options Additional options.
	 * @return string Generated content.
	 */
	public function generate_content( string $prompt, array $options = array() ): string {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( '[FP-SEO] OpenAiClient::generate_content - Entry, prompt length: ' . strlen( $prompt ) );
		}

		$client = $this->get_client();

		if ( null === $client ) {
			if ( $debug ) {
				error_log( '[FP-SEO] OpenAiClient::generate_content - Client is null, throwing exception' );
			}
			throw new \Exception( 'OpenAI client not configured' );
		}

		if ( $debug ) {
			error_log( '[FP-SEO] OpenAiClient::generate_content - Client obtained, merging options' );
		}
		$default_options = array(
			'model' => $this->get_model(),
			'temperature' => 0.7,
			'max_tokens' => 1000, // Will be converted to max_completion_tokens for GPT-5 Nano
		);

		$options = array_merge( $default_options, $options );

		// Get max_tokens value - support both parameter names
		$max_tokens_value = $options['max_tokens'] ?? $options['max_completion_tokens'] ?? 1000;

		// Build API request parameters
		$api_params = array(
			'model' => $options['model'],
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
		);

		// GPT-5.4/GPT-5 nano/mini use max_completion_tokens, other models use max_tokens
		$model = strtolower( $options['model'] );
		$uses_max_completion = preg_match( '/gpt-5(?:\.4)?-(?:nano|mini)|gpt-5-nano/', $model );
		if ( $uses_max_completion ) {
			if ( isset( $options['max_completion_tokens'] ) ) {
				$api_params['max_completion_tokens'] = $options['max_completion_tokens'];
			} else {
				$api_params['max_completion_tokens'] = $max_tokens_value;
			}
			if ( isset( $options['temperature'] ) && $options['temperature'] == 1.0 ) {
				$api_params['temperature'] = 1.0;
			}
		} else {
			$api_params['max_tokens'] = $max_tokens_value;
			if ( isset( $options['temperature'] ) ) {
				$api_params['temperature'] = $options['temperature'];
			}
		}

		if ( $debug ) {
			error_log( '[FP-SEO] OpenAiClient::generate_content - Calling API, model: ' . $api_params['model'] );
			error_log( '[FP-SEO] OpenAiClient::generate_content - API params keys: ' . json_encode( array_keys( $api_params ) ) );
		}
		try {
			$response = $client->chat()->create( $api_params );
			if ( $debug ) {
				error_log( '[FP-SEO] OpenAiClient::generate_content - API response received, type: ' . gettype( $response ) );
			}

			// Try multiple ways to extract content (depending on API version)
			$content = '';
			if ( isset( $response->choices[0]->message->content ) ) {
				$content = $response->choices[0]->message->content;
			} elseif ( isset( $response->choices[0]->message->text ) ) {
				$content = $response->choices[0]->message->text;
			} elseif ( isset( $response['choices'][0]['message']['content'] ) ) {
				$content = $response['choices'][0]['message']['content'];
			} elseif ( is_array( $response ) && isset( $response[0]['message']['content'] ) ) {
				$content = $response[0]['message']['content'];
			}
			
			if ( $debug && empty( $content ) ) {
				error_log( '[FP-SEO] OpenAiClient::generate_content - WARNING: Empty content in response' );
				error_log( '[FP-SEO] OpenAiClient::generate_content - Model used: ' . $api_params['model'] );
				
				if ( isset( $response->usage ) ) {
					error_log( '[FP-SEO] OpenAiClient::generate_content - Usage: ' . print_r( $response->usage, true ) );
				}
				if ( isset( $response->choices[0]->finishReason ) ) {
					error_log( '[FP-SEO] OpenAiClient::generate_content - Finish reason: ' . $response->choices[0]->finishReason );
				}
				if ( isset( $response->choices[0]->message->refusal ) ) {
					error_log( '[FP-SEO] OpenAiClient::generate_content - Refusal: ' . $response->choices[0]->message->refusal );
				}
				if ( isset( $response->choices[0]->message ) ) {
					$message_vars = get_object_vars( $response->choices[0]->message );
					error_log( '[FP-SEO] OpenAiClient::generate_content - Message properties: ' . implode( ', ', array_keys( $message_vars ) ) );
					error_log( '[FP-SEO] OpenAiClient::generate_content - Message structure: ' . print_r( $response->choices[0]->message, true ) );
				}
				if ( isset( $response->choices[0] ) ) {
					error_log( '[FP-SEO] OpenAiClient::generate_content - Choice 0 structure: ' . print_r( $response->choices[0], true ) );
				}
				$response_dump = print_r( $response, true );
				error_log( '[FP-SEO] OpenAiClient::generate_content - Full response (first 3000 chars): ' . substr( $response_dump, 0, 3000 ) );
			}

			if ( $debug ) {
				error_log( '[FP-SEO] OpenAiClient::generate_content - Returning content, length: ' . strlen( $content ) );
			}
			return $content;
		} catch ( \Throwable $e ) {
			if ( $debug ) {
				error_log( '[FP-SEO] OpenAiClient::generate_content - Error: ' . $e->getMessage() );
				error_log( '[FP-SEO] OpenAiClient::generate_content - Stack trace: ' . $e->getTraceAsString() );
			}
			throw $e;
		}
	}
}

