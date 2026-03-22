<?php
/**
 * Q&A Pairs Extractor for AI Engines
 *
 * Automatically extracts question-answer pairs from content using GPT-5.4 Nano.
 * Optimized for AI search engines (Gemini, Claude, OpenAI, Perplexity).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\CacheHelper;

/**
 * Extracts Q&A pairs from content for AI consumption
 */
class QAPairExtractor {

	/**
	 * Meta key for Q&A pairs
	 */
	private const META_QA_PAIRS = '_fp_seo_qa_pairs';

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor
	 *
	 * @param OpenAiClient   $openai_client OpenAI client instance.
	 * @param LoggerInterface $logger        Logger instance.
	 */
	public function __construct( OpenAiClient $openai_client, LoggerInterface $logger ) {
		$this->openai_client = $openai_client;
		$this->logger        = $logger;
	}

	/**
	 * Extract Q&A pairs from post content
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force regeneration (skip cache).
	 * @return array<int, array<string, mixed>> Q&A pairs.
	 */
	public function extract_qa_pairs( int $post_id, bool $force = false ): array {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( '[FP-SEO] QAPairExtractor::extract_qa_pairs - Entry, post_id: ' . $post_id . ', force: ' . ( $force ? 'true' : 'false' ) );
		}

		if ( ! $force ) {
			$cached = get_post_meta( $post_id, self::META_QA_PAIRS, true );

			if ( is_array( $cached ) && ! empty( $cached ) ) {
				if ( $debug ) {
					error_log( '[FP-SEO] QAPairExtractor::extract_qa_pairs - Returning cached Q&A pairs, count: ' . count( $cached ) );
				}
				return $cached;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$qa_pairs = $this->extract_with_ai( $post );

		if ( $debug ) {
			error_log( '[FP-SEO] QAPairExtractor::extract_qa_pairs - extract_with_ai returned, count: ' . count( $qa_pairs ) );
		}

		// Save to post meta
		if ( ! empty( $qa_pairs ) ) {
			update_post_meta( $post_id, self::META_QA_PAIRS, $qa_pairs );
			
			// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id) saving
			// WordPress handles cache management automatically - no manual clearing needed
		} else {
			// If no Q&A pairs, delete the meta to avoid stale data
			delete_post_meta( $post_id, self::META_QA_PAIRS );
			
			// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id) saving
			// WordPress handles cache management automatically - no manual clearing needed
		}

		return $qa_pairs;
	}

	/**
	 * Extract Q&A pairs using OpenAI GPT-5.4 Nano
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Extracted Q&A pairs.
	 */
	private function extract_with_ai( \WP_Post $post ): array {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! $this->openai_client->is_configured() ) {
			return array();
		}

		$content = $this->prepare_content( $post );

		if ( empty( $content ) ) {
			return array();
		}

		$prompt = $this->build_extraction_prompt( $content, $post );

		try {
			$response = $this->openai_client->generate_content( $prompt, array(
				'model'       => 'gpt-5.4-nano',
				'max_tokens'  => 4000,
				'temperature' => 0.7,
			) );

			if ( empty( $response ) ) {
				if ( $debug ) {
					error_log( '[FP-SEO] QAPairExtractor::extract_with_ai - GPT-5.4 Nano returned empty response for post_id: ' . $post->ID );
				}
				return array();
			}

			$result = $this->parse_qa_response( $response, $post );

			if ( $debug && empty( $result ) ) {
				error_log( '[FP-SEO] QAPairExtractor::extract_with_ai - No Q&A pairs parsed from response for post_id: ' . $post->ID );
			}

			return $result;

		} catch ( \Exception $e ) {
			$this->logger->error( 'Q&A Extraction Error', array( 'error' => $e->getMessage(), 'post_id' => $post->ID ) );
			return array();
		} catch ( \Error $e ) {
			$this->logger->error( 'Q&A Extraction Fatal Error', array( 'error' => $e->getMessage(), 'post_id' => $post->ID ) );
			return array();
		}
	}

	/**
	 * Prepare content for extraction
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Cleaned content.
	 */
	private function prepare_content( \WP_Post $post ): string {
		$content = $post->post_content;
		
		// Process WPBakery shortcodes first to extract text content
		if ( strpos( $content, '[vc_' ) !== false ) {
			// Use WPBakeryContentExtractor if available
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				$wpbakery_text = \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $content );
				if ( ! empty( $wpbakery_text ) ) {
					$content = $wpbakery_text;
				} else {
					// Fallback: process shortcodes
					$content = do_shortcode( $content );
				}
			} else {
				// Fallback: process shortcodes
				$content = do_shortcode( $content );
			}
		}

		// Remove remaining shortcodes
		$content = strip_shortcodes( $content );

		// Remove HTML tags
		$content = wp_strip_all_tags( $content );

		// Limit length (API token limits)
		$content = substr( $content, 0, 6000 );
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'QAPairExtractor::prepare_content', array(
				'post_id' => $post->ID,
				'original_length' => strlen( $post->post_content ),
				'processed_length' => strlen( $content ),
				'has_wpbakery' => strpos( $post->post_content, '[vc_' ) !== false,
			) );
		}

		return trim( $content );
	}

	/**
	 * Build extraction prompt for AI
	 *
	 * @param string   $content Post content.
	 * @param \WP_Post $post    Post object.
	 * @return string Prompt.
	 */
	private function build_extraction_prompt( string $content, \WP_Post $post ): string {
		$title      = $post->post_title;
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		$context = '';
		if ( ! empty( $categories ) ) {
			$context .= 'Categorie: ' . implode( ', ', $categories ) . "\n";
		}
		if ( ! empty( $tags ) ) {
			$context .= 'Tag: ' . implode( ', ', array_slice( $tags, 0, 5 ) ) . "\n";
		}

		return sprintf(
			'Sei un esperto SEO specialist. Analizza questo contenuto ed estrai domande e risposte che gli utenti potrebbero cercare su Google, Gemini, Claude o Perplexity.

Titolo: %s
%s
Contenuto:
%s

ISTRUZIONI:
1. Estrai 8-12 coppie domanda-risposta
2. Le domande devono essere quelle che un utente reale farebbe
3. Le risposte devono essere COMPLETE e AUTONOME (leggibili senza il contenuto originale)
4. Includi domande di diversi tipi:
   - Domande informative ("Cos\'è...", "Come funziona...")
   - Domande procedurali ("Come fare...", "Quali passaggi...")
   - Domande comparative ("Differenza tra...", "Quale è meglio...")
   - Domande di troubleshooting ("Perché non...", "Come risolvere...")
5. Ogni risposta deve essere 2-4 frasi (80-150 parole)
6. Identifica keywords rilevanti per ogni coppia
7. Assegna un confidence score (0.0-1.0) basato su quanto la risposta è chiara e completa nel contenuto

Rispondi SOLO con JSON valido in questo formato:
{
  "qa_pairs": [
    {
      "question": "Domanda completa?",
      "answer": "Risposta completa e autonoma che può essere capita senza leggere l\'articolo.",
      "confidence": 0.95,
      "keywords": ["keyword1", "keyword2"],
      "source_section": "Sezione del contenuto da cui deriva",
      "question_type": "informational|procedural|comparative|troubleshooting"
    }
  ]
}

Rispondi SOLO con il JSON, senza testo aggiuntivo.',
			esc_html( $title ),
			esc_html( $context ),
			esc_html( $content )
		);
	}

	/**
	 * Parse AI response to extract Q&A pairs
	 *
	 * @param string   $response AI response.
	 * @param \WP_Post $post     Post object.
	 * @return array<int, array<string, mixed>> Q&A pairs.
	 */
	private function parse_qa_response( string $response, \WP_Post $post ): array {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Remove markdown code blocks if present
		$response = preg_replace( '/```json\s*/', '', $response ) ?? $response;
		$response = preg_replace( '/```\s*$/', '', $response ) ?? $response;
		$response = trim( $response );

		// Try to extract JSON object if there's surrounding text
		if ( preg_match( '/\{[\s\S]*\}/', $response, $matches ) === 1 ) {
			$response = $matches[0];
		}

		$data       = json_decode( $response, true );
		$json_error = json_last_error();

		if ( $json_error !== JSON_ERROR_NONE ) {
			// Try to fix by removing any text before the first {
			$first_brace = strpos( $response, '{' );
			if ( $first_brace !== false && $first_brace > 0 ) {
				$response   = substr( $response, $first_brace );
				$data       = json_decode( $response, true );
				$json_error = json_last_error();
			}

			if ( $json_error !== JSON_ERROR_NONE ) {
				if ( $debug ) {
					error_log( '[FP-SEO] QAPairExtractor::parse_qa_response - JSON decode failed: ' . json_last_error_msg() );
				}
				return array();
			}
		}

		if ( ! is_array( $data ) || ! isset( $data['qa_pairs'] ) || ! is_array( $data['qa_pairs'] ) ) {
			return array();
		}

		$qa_pairs       = array();
		$filtered_count = 0;

		foreach ( $data['qa_pairs'] as $pair ) {
			if ( ! isset( $pair['question'] ) || ! isset( $pair['answer'] ) ) {
				$filtered_count++;
				continue;
			}

			$qa_pair = array(
				'question'       => sanitize_text_field( $pair['question'] ),
				'answer'         => sanitize_textarea_field( $pair['answer'] ),
				'confidence'     => $this->validate_confidence( $pair['confidence'] ?? 0.5 ),
				'keywords'       => $this->sanitize_keywords( $pair['keywords'] ?? array() ),
				'source_section' => sanitize_text_field( $pair['source_section'] ?? '' ),
				'question_type'  => $this->validate_question_type( $pair['question_type'] ?? 'informational' ),
				'post_id'        => $post->ID,
				'post_title'     => $post->post_title,
				'post_url'       => get_permalink( $post->ID ),
				'generated_at'   => gmdate( 'c' ),
			);

			// Quality filters
			if ( strlen( $qa_pair['question'] ) < 10 || strlen( $qa_pair['answer'] ) < 30 || $qa_pair['confidence'] < 0.5 ) {
				$filtered_count++;
				continue;
			}

			$qa_pairs[] = $qa_pair;
		}

		if ( $debug ) {
			error_log( '[FP-SEO] QAPairExtractor::parse_qa_response - Final: ' . count( $qa_pairs ) . ' pairs, filtered: ' . $filtered_count );
		}

		return $qa_pairs;
	}

	/**
	 * Validate confidence score
	 *
	 * @param mixed $confidence Raw confidence value.
	 * @return float Validated confidence (0.0-1.0).
	 */
	private function validate_confidence( $confidence ): float {
		$confidence = (float) $confidence;
		return max( 0.0, min( 1.0, $confidence ) );
	}

	/**
	 * Sanitize keywords array
	 *
	 * @param mixed $keywords Raw keywords.
	 * @return array<int, string> Sanitized keywords.
	 */
	private function sanitize_keywords( $keywords ): array {
		if ( ! is_array( $keywords ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'sanitize_text_field', $keywords ) ) );
	}

	/**
	 * Validate question type
	 *
	 * @param string $type Raw question type.
	 * @return string Validated type.
	 */
	private function validate_question_type( string $type ): string {
		$valid_types = array( 'informational', 'procedural', 'comparative', 'troubleshooting' );

		$type = strtolower( sanitize_text_field( $type ) );

		return in_array( $type, $valid_types, true ) ? $type : 'informational';
	}

	/**
	 * Get Q&A pairs for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>> Q&A pairs.
	 */
	public function get_qa_pairs( int $post_id ): array {
		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id)
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache can interfere with WordPress core operations including _thumbnail_id
		
		// Use direct database query to bypass WordPress cache completely
		global $wpdb;
		$meta_value = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
			$post_id,
			self::META_QA_PAIRS
		) );
		
		// Unserialize the value if it exists
		$qa_pairs = $meta_value ? maybe_unserialize( $meta_value ) : false;
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'QAPairExtractor::get_qa_pairs', array(
				'post_id' => $post_id,
				'meta_key' => self::META_QA_PAIRS,
				'is_array' => is_array( $qa_pairs ),
				'count' => is_array( $qa_pairs ) ? count( $qa_pairs ) : 0,
				'raw_value' => $qa_pairs,
				'meta_value_exists' => ! empty( $meta_value ),
			) );
		}

		if ( ! is_array( $qa_pairs ) ) {
			return array();
		}

		return $qa_pairs;
	}

	/**
	 * Get Q&A pairs filtered by type
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Question type filter.
	 * @return array<int, array<string, mixed>> Filtered Q&A pairs.
	 */
	public function get_qa_pairs_by_type( int $post_id, string $type ): array {
		$qa_pairs = $this->get_qa_pairs( $post_id );

		return array_values( array_filter( $qa_pairs, function ( $pair ) use ( $type ) {
			return isset( $pair['question_type'] ) && $pair['question_type'] === $type;
		} ) );
	}

	/**
	 * Get high-confidence Q&A pairs
	 *
	 * @param int   $post_id           Post ID.
	 * @param float $min_confidence    Minimum confidence threshold.
	 * @return array<int, array<string, mixed>> High-confidence Q&A pairs.
	 */
	public function get_high_confidence_pairs( int $post_id, float $min_confidence = 0.8 ): array {
		$qa_pairs = $this->get_qa_pairs( $post_id );

		return array_values( array_filter( $qa_pairs, function ( $pair ) use ( $min_confidence ) {
			return isset( $pair['confidence'] ) && $pair['confidence'] >= $min_confidence;
		} ) );
	}

	/**
	 * Add manual Q&A pair
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $question       Question.
	 * @param string $answer         Answer.
	 * @param array<string, mixed> $metadata Additional metadata.
	 * @return bool Success.
	 */
	public function add_manual_pair( int $post_id, string $question, string $answer, array $metadata = array() ): bool {
		$qa_pairs = $this->get_qa_pairs( $post_id );

		$new_pair = array_merge(
			array(
				'question'       => sanitize_text_field( $question ),
				'answer'         => sanitize_textarea_field( $answer ),
				'confidence'     => 1.0, // Manual = high confidence
				'keywords'       => array(),
				'source_section' => 'Manual',
				'question_type'  => 'informational',
				'post_id'        => $post_id,
				'post_title'     => get_the_title( $post_id ),
				'post_url'       => get_permalink( $post_id ),
				'generated_at'   => gmdate( 'c' ),
				'manual'         => true,
			),
			$metadata
		);

		$qa_pairs[] = $new_pair;

		return update_post_meta( $post_id, self::META_QA_PAIRS, $qa_pairs );
	}

	/**
	 * Delete Q&A pair by index
	 *
	 * @param int $post_id Post ID.
	 * @param int $index   Pair index.
	 * @return bool Success.
	 */
	public function delete_pair( int $post_id, int $index ): bool {
		$qa_pairs = $this->get_qa_pairs( $post_id );

		if ( ! isset( $qa_pairs[ $index ] ) ) {
			return false;
		}

		unset( $qa_pairs[ $index ] );
		$qa_pairs = array_values( $qa_pairs ); // Reindex

		return update_post_meta( $post_id, self::META_QA_PAIRS, $qa_pairs );
	}

	/**
	 * Clear all Q&A pairs for a post
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success.
	 */
	public function clear_pairs( int $post_id ): bool {
		return delete_post_meta( $post_id, self::META_QA_PAIRS );
	}

	/**
	 * Get FAQ Schema.org markup from Q&A pairs
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null FAQ schema or null.
	 */
	public function get_faq_schema( int $post_id ): ?array {
		$qa_pairs = $this->get_high_confidence_pairs( $post_id, 0.7 );

		if ( empty( $qa_pairs ) ) {
			return null;
		}

		$main_entity = array();

		foreach ( $qa_pairs as $pair ) {
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => $pair['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $pair['answer'],
				),
			);
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $main_entity,
		);
	}
}


