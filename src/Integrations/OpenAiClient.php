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
use OpenAI\OpenAI as OpenAiFactory;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\Logger;

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
	 * Initialize the OpenAI client.
	 *
	 * @return OpenAiClientClass|null
	 */
	private function get_client(): ?OpenAiClientClass {
		if ( null !== $this->client ) {
			return $this->client;
		}

		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return null;
		}

		// Verify OpenAI factory class exists
		if ( ! class_exists( OpenAiFactory::class ) ) {
			Logger::error( 'OpenAI library not loaded', array(
				'openai_factory_class' => class_exists( OpenAiFactory::class ),
				'client_class' => class_exists( OpenAiClientClass::class ),
				'autoload_working' => spl_autoload_functions() !== false,
			) );
			return null;
		}

		try {
			// Use the OpenAI factory class to create client
			$this->client = OpenAiFactory::client( $api_key );
			
			return $this->client;
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to initialize OpenAI client', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'api_key_length' => strlen( $api_key ),
			) );
			return null;
		}
	}

	/**
	 * Get the OpenAI API key from options.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		return Options::get_option( 'ai.openai_api_key', '' );
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
				Logger::error( 'Error gathering post context', array(
					'post_id' => $post_id,
					'error' => $e->getMessage(),
				) );
				$context = array(); // Fallback to empty context
			}

			// Build prompt with error handling
			try {
				$prompt = $this->build_prompt( $title, $clean_content, $language, $focus_keyword, $context );
			} catch ( \Throwable $e ) {
				Logger::error( 'Error building prompt', array(
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
				$model = 'gpt-5-nano'; // Default fallback
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'Error getting model', array( 'error' => $e->getMessage() ) );
			$model = 'gpt-5-nano'; // Default fallback
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
			'max_completion_tokens'  => 4096, // Aumentato a 4096 per GPT-5 Nano (massimo sicuro)
		);

		// GPT-5 Nano only supports temperature=1 (default), so omit it for that model
		$model_lower = strtolower( $model );
		if ( strpos( $model_lower, 'gpt-5-nano' ) === false ) {
			$api_params['temperature'] = 0.7;
		}

		Logger::debug( 'Calling OpenAI API', array( 'model' => $api_params['model'], 'params' => array_keys( $api_params ) ) );

		try {
			$response = $client->chat()->create( $api_params );
			Logger::debug( 'OpenAI API response received', array( 'type' => gettype( $response ), 'choices_count' => isset( $response->choices ) ? count( $response->choices ) : 0 ) );
			
			// Debug full response structure
			if ( isset( $response->choices[0] ) ) {
				$message = $response->choices[0]->message;
				$finish_reason = $response->choices[0]->finishReason ?? 'unknown';
				
				Logger::debug( 'OpenAI response details', array(
					'finish_reason' => $finish_reason,
					'message_role' => $message->role ?? 'NULL',
					'has_content' => ! empty( $message->content ),
					'has_refusal' => ! empty( $message->refusal ),
				) );
				
				// Check if there's a refusal
				if ( ! empty( $message->refusal ) ) {
					Logger::error( 'OpenAI request refused', array( 'refusal' => $message->refusal ) );
					return array(
						'success' => false,
						'error'   => sprintf( __( 'OpenAI ha rifiutato la richiesta: %s', 'fp-seo-performance' ), $message->refusal ),
					);
				}
			} else {
				Logger::error( 'No choices in OpenAI response' );
				return array(
					'success' => false,
					'error'   => __( 'Risposta OpenAI non valida: nessuna scelta disponibile.', 'fp-seo-performance' ),
				);
			}
		} catch ( \Exception $e ) {
			Logger::error( 'OpenAI API call exception', array( 'message' => $e->getMessage() ) );
			return array(
				'success' => false,
				'error'   => sprintf( __( 'Errore API OpenAI: %s', 'fp-seo-performance' ), $e->getMessage() ),
			);
		}

		$result = $response->choices[0]->message->content ?? '';

		Logger::debug( 'Extracted OpenAI result', array( 'length' => strlen( $result ) ) );

			if ( empty( $result ) ) {
				Logger::error( 'Empty result from OpenAI API', array(
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
			Logger::error( 'Fatal error in generate_seo_suggestions', array(
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
		
		if ( ! empty( $context['post_type'] ) && 'post' !== $context['post_type'] ) {
			$post_type_label = get_post_type_object( $context['post_type'] )->labels->singular_name ?? $context['post_type'];
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
			$sanitized = preg_replace( $pattern, '', $sanitized );
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
		$response = preg_replace( '/```json\s*/', '', $response );
		$response = preg_replace( '/```\s*$/', '', $response );
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
		return Options::get_option( 'ai.openai_model', 'gpt-5-nano' );
	}

	/**
	 * Generate content using OpenAI API.
	 *
	 * @param string $prompt The prompt to send.
	 * @param array<string, mixed> $options Additional options.
	 * @return string Generated content.
	 */
	public function generate_content( string $prompt, array $options = array() ): string {
		$client = $this->get_client();

		if ( null === $client ) {
			throw new \Exception( 'OpenAI client not configured' );
		}

		$default_options = array(
			'model' => $this->get_model(),
			'temperature' => 0.7,
			'max_completion_tokens' => 1000,
		);

		$options = array_merge( $default_options, $options );

		// Support both old and new parameter names for backward compatibility
		$max_tokens_param = isset( $options['max_completion_tokens'] ) 
			? 'max_completion_tokens' 
			: ( isset( $options['max_tokens'] ) ? 'max_tokens' : 'max_completion_tokens' );
		
		$max_tokens_value = $options[$max_tokens_param] ?? 1000;

		// Build API request parameters
		$api_params = array(
			'model' => $options['model'],
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'max_completion_tokens' => $max_tokens_value,
		);

		// GPT-5 Nano only supports temperature=1 (default), so omit it for that model
		$model = strtolower( $options['model'] );
		if ( strpos( $model, 'gpt-5-nano' ) === false ) {
			$api_params['temperature'] = $options['temperature'];
		}

		try {
			$response = $client->chat()->create( $api_params );

			return $response->choices[0]->message->content ?? '';
		} catch ( \Exception $e ) {
			throw new \Exception( 'OpenAI API error: ' . $e->getMessage() );
		}
	}
}

