<?php
/**
 * Conversational Variants Generator for AI Engines
 *
 * Generates multiple conversational variants of content optimized for different
 * AI audience contexts (formal, casual, expert, beginner, etc.).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI;

use FP\SEO\Integrations\OpenAiClient;
use WP_Post;

/**
 * Generates conversational content variants for AI consumption
 */
class ConversationalVariants {

	/**
	 * Meta key for cached variants
	 */
	private const META_VARIANTS = '_fp_seo_conversational_variants';

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * Available variant types
	 *
	 * @var array<string, string>
	 */
	private const VARIANT_TYPES = array(
		'formal'          => 'Formal and professional tone',
		'conversational'  => 'Friendly and approachable tone',
		'expert'          => 'Technical and expert-level',
		'beginner'        => 'Simple and beginner-friendly',
		'summary_30s'     => '30-second summary',
		'summary_2min'    => '2-minute detailed summary',
		'eli5'            => 'Explain Like I\'m 5 (very simple)',
		'technical'       => 'Highly technical and detailed',
		'action_oriented' => 'Focused on actionable steps',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->openai_client = new OpenAiClient();
	}

	/**
	 * Generate all conversational variants for a post
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force regeneration.
	 * @return array<string, string> Variants.
	 */
	public function generate_variants( int $post_id, bool $force = false ): array {
		// Check cache
		if ( ! $force ) {
			$cached = get_post_meta( $post_id, self::META_VARIANTS, true );

			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$variants = array();

		// Generate each variant type
		foreach ( self::VARIANT_TYPES as $type => $description ) {
			$variant = $this->generate_single_variant( $post, $type, $description );

			if ( ! empty( $variant ) ) {
				$variants[ $type ] = $variant;
			}
		}

		// Cache variants
		if ( ! empty( $variants ) ) {
			update_post_meta( $post_id, self::META_VARIANTS, $variants );
		}

		return $variants;
	}

	/**
	 * Generate a single variant
	 *
	 * @param WP_Post $post        Post object.
	 * @param string  $type        Variant type.
	 * @param string  $description Type description.
	 * @return string Variant text.
	 */
	private function generate_single_variant( WP_Post $post, string $type, string $description ): string {
		// For summaries, use extraction-based approach
		if ( strpos( $type, 'summary' ) !== false ) {
			return $this->generate_extractive_summary( $post, $type );
		}

		// For other variants, use AI if available
		if ( $this->openai_client->is_configured() ) {
			return $this->generate_ai_variant( $post, $type, $description );
		}

		// Fallback: use rule-based transformation
		return $this->generate_rule_based_variant( $post, $type );
	}

	/**
	 * Generate variant using AI
	 *
	 * @param WP_Post $post        Post object.
	 * @param string  $type        Variant type.
	 * @param string  $description Type description.
	 * @return string Variant text.
	 */
	private function generate_ai_variant( WP_Post $post, string $type, string $description ): string {
		$content = $this->prepare_content( $post );

		if ( empty( $content ) ) {
			return '';
		}

		$prompt = $this->build_variant_prompt( $content, $post->post_title, $type, $description );

		try {
			$variant = $this->openai_client->generate_content( $prompt, array(
			'model'       => 'gpt-5-nano',
			'temperature' => 0.5,
			'max_completion_tokens'  => $this->get_max_tokens_for_type( $type ),
		) );

			return trim( $variant );

		} catch ( \Exception $e ) {
			error_log( 'FP SEO Variant Generation Error: ' . $e->getMessage() );
			return $this->generate_rule_based_variant( $post, $type );
		}
	}

	/**
	 * Build AI prompt for variant generation
	 *
	 * @param string $content     Content to transform.
	 * @param string $title       Post title.
	 * @param string $type        Variant type.
	 * @param string $description Type description.
	 * @return string Prompt.
	 */
	private function build_variant_prompt( string $content, string $title, string $type, string $description ): string {
		$instructions = $this->get_variant_instructions( $type );

		return sprintf(
			'Trasforma questo contenuto con il seguente stile: %s

Titolo: %s

Contenuto originale:
%s

ISTRUZIONI SPECIFICHE:
%s

REQUISITI:
- Mantieni i fatti e le informazioni chiave
- Adatta solo il tono e lo stile di presentazione
- Non inventare informazioni non presenti nel testo originale
- Lunghezza: %s

Rispondi SOLO con il contenuto trasformato, senza introduzioni o spiegazioni.',
			esc_html( $description ),
			esc_html( $title ),
			esc_html( $content ),
			esc_html( $instructions ),
			esc_html( $this->get_length_requirement( $type ) )
		);
	}

	/**
	 * Get specific instructions for variant type
	 *
	 * @param string $type Variant type.
	 * @return string Instructions.
	 */
	private function get_variant_instructions( string $type ): string {
		$instructions = array(
			'formal'          => 'Usa un linguaggio formale e professionale. Evita contrazioni, slang e espressioni colloquiali. Usa termini tecnici appropriati.',
			'conversational'  => 'Usa un tono amichevole e accessibile. Va bene usare "tu" e domande retoriche. Immagina di spiegare ad un amico.',
			'expert'          => 'Usa terminologia tecnica avanzata. Assumi che il lettore abbia conoscenze approfondite del settore. Approfondisci aspetti tecnici.',
			'beginner'        => 'Spiega concetti base chiaramente. Evita jargon tecnico o spiegalo quando necessario. Usa esempi semplici e concreti.',
			'summary_30s'     => 'Riassumi i punti chiave in 2-3 frasi leggibili in 30 secondi. Solo le informazioni essenziali.',
			'summary_2min'    => 'Crea un riassunto completo leggibile in 2 minuti. Include punti principali, dati chiave e conclusioni.',
			'eli5'            => 'Spiega come se il lettore avesse 5 anni. Usa analogie semplici, esempi della vita quotidiana. Linguaggio elementare.',
			'technical'       => 'Massimo dettaglio tecnico. Include specifiche, metriche, processi. Linguaggio per professionisti del settore.',
			'action_oriented' => 'Focalizza su passi concreti e azioni da compiere. Usa liste numerate. "Come fare" piuttosto che "cos\'è".',
		);

		return $instructions[ $type ] ?? 'Trasforma il contenuto mantenendo le informazioni chiave.';
	}

	/**
	 * Get length requirement for variant type
	 *
	 * @param string $type Variant type.
	 * @return string Length description.
	 */
	private function get_length_requirement( string $type ): string {
		$lengths = array(
			'summary_30s'     => '50-80 parole',
			'summary_2min'    => '200-300 parole',
			'eli5'            => '100-150 parole',
			'formal'          => 'Simile all\'originale',
			'conversational'  => 'Simile all\'originale',
			'expert'          => 'Può essere più lungo dell\'originale',
			'beginner'        => 'Può essere più lungo per spiegazioni chiare',
			'technical'       => 'Può essere più lungo per dettagli tecnici',
			'action_oriented' => 'Conciso ma completo',
		);

		return $lengths[ $type ] ?? 'Appropriata per il contesto';
	}

	/**
	 * Get max tokens for variant type
	 *
	 * @param string $type Variant type.
	 * @return int Max tokens.
	 */
	private function get_max_tokens_for_type( string $type ): int {
		$tokens = array(
			'summary_30s'     => 150,
			'summary_2min'    => 500,
			'eli5'            => 300,
			'formal'          => 1500,
			'conversational'  => 1500,
			'expert'          => 2000,
			'beginner'        => 1500,
			'technical'       => 2000,
			'action_oriented' => 1000,
		);

		return $tokens[ $type ] ?? 1500;
	}

	/**
	 * Prepare content for variant generation
	 *
	 * @param WP_Post $post Post object.
	 * @return string Prepared content.
	 */
	private function prepare_content( WP_Post $post ): string {
		$content = $post->post_content;

		// Remove shortcodes
		$content = strip_shortcodes( $content );

		// Remove HTML
		$content = wp_strip_all_tags( $content );

		// Limit length for AI processing
		$content = substr( $content, 0, 4000 );

		return trim( $content );
	}

	/**
	 * Generate extractive summary (no AI required)
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $type Summary type (30s or 2min).
	 * @return string Summary.
	 */
	private function generate_extractive_summary( WP_Post $post, string $type ): string {
		$content = wp_strip_all_tags( $post->post_content );

		// Split into sentences
		$sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_map( 'trim', $sentences );
		$sentences = array_filter( $sentences );

		if ( empty( $sentences ) ) {
			return '';
		}

		// Score sentences by importance
		$scored_sentences = array();

		foreach ( $sentences as $index => $sentence ) {
			$score = $this->score_sentence_importance( $sentence, $index, $post );
			$scored_sentences[] = array(
				'sentence' => $sentence,
				'score'    => $score,
				'index'    => $index,
			);
		}

		// Sort by score
		usort( $scored_sentences, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		// Select top sentences
		$sentence_count = 'summary_30s' === $type ? 3 : 8;
		$top_sentences  = array_slice( $scored_sentences, 0, $sentence_count );

		// Re-sort by original order
		usort( $top_sentences, function ( $a, $b ) {
			return $a['index'] <=> $b['index'];
		} );

		// Combine sentences
		$summary = implode( '. ', array_column( $top_sentences, 'sentence' ) ) . '.';

		return $summary;
	}

	/**
	 * Score sentence importance for extractive summarization
	 *
	 * @param string  $sentence Sentence text.
	 * @param int     $index    Sentence index.
	 * @param WP_Post $post     Post object.
	 * @return float Importance score.
	 */
	private function score_sentence_importance( string $sentence, int $index, WP_Post $post ): float {
		$score = 0.0;

		// Position-based scoring (first and last sentences often important)
		if ( 0 === $index ) {
			$score += 1.5; // First sentence bonus
		}

		// Length-based scoring (too short or too long = less important)
		$word_count = str_word_count( $sentence );
		if ( $word_count > 10 && $word_count < 30 ) {
			$score += 1.0;
		}

		// Contains numbers/data
		if ( preg_match( '/\d+/', $sentence ) ) {
			$score += 0.5;
		}

		// Contains key terms from title
		$title_words = array_map( 'strtolower', str_word_count( $post->post_title, 1 ) );
		$sentence_lower = strtolower( $sentence );

		foreach ( $title_words as $word ) {
			if ( strlen( $word ) > 4 && strpos( $sentence_lower, $word ) !== false ) {
				$score += 0.3;
			}
		}

		// Contains important keywords
		$important_keywords = array( 'importante', 'fondamentale', 'essenziale', 'chiave', 'principale', 'risultato', 'conclusione' );

		foreach ( $important_keywords as $keyword ) {
			if ( stripos( $sentence, $keyword ) !== false ) {
				$score += 0.4;
			}
		}

		return $score;
	}

	/**
	 * Generate variant using rule-based approach (fallback)
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $type Variant type.
	 * @return string Variant text.
	 */
	private function generate_rule_based_variant( WP_Post $post, string $type ): string {
		$content = wp_strip_all_tags( $post->post_content );

		// For summaries, use extractive summarization
		if ( strpos( $type, 'summary' ) !== false ) {
			return $this->generate_extractive_summary( $post, $type );
		}

		// For other types, use simple transformations
		switch ( $type ) {
			case 'beginner':
				// Add explanations for technical terms
				return $this->simplify_content( $content );

			case 'action_oriented':
				// Extract action items
				return $this->extract_action_items( $content );

			default:
				// Return original content with minor adjustments
				return substr( $content, 0, 1000 ) . ( strlen( $content ) > 1000 ? '...' : '' );
		}
	}

	/**
	 * Simplify content for beginners
	 *
	 * @param string $content Content to simplify.
	 * @return string Simplified content.
	 */
	private function simplify_content( string $content ): string {
		// Extract first 500 words (usually introductory content)
		$words     = explode( ' ', $content );
		$simplified = implode( ' ', array_slice( $words, 0, 500 ) );

		return $simplified . ( count( $words ) > 500 ? '...' : '' );
	}

	/**
	 * Extract action items from content
	 *
	 * @param string $content Content to extract from.
	 * @return string Action-oriented content.
	 */
	private function extract_action_items( string $content ): string {
		// Find sentences with action verbs
		$sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );

		$action_verbs = array( 'devi', 'puoi', 'dovresti', 'clicca', 'vai', 'apri', 'configura', 'installa', 'attiva', 'crea', 'aggiungi' );
		$action_items = array();

		foreach ( $sentences as $sentence ) {
			$sentence_lower = strtolower( trim( $sentence ) );

			foreach ( $action_verbs as $verb ) {
				if ( strpos( $sentence_lower, $verb ) !== false ) {
					$action_items[] = trim( $sentence );
					break;
				}
			}

			if ( count( $action_items ) >= 10 ) {
				break;
			}
		}

		if ( empty( $action_items ) ) {
			// Fallback to first 300 words
			$words = explode( ' ', $content );
			return implode( ' ', array_slice( $words, 0, 300 ) );
		}

		return "Passaggi chiave:\n\n" . implode( "\n\n", $action_items );
	}

	/**
	 * Get specific variant
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Variant type.
	 * @return string|null Variant text or null.
	 */
	public function get_variant( int $post_id, string $type ): ?string {
		$variants = $this->get_all_variants( $post_id );

		return $variants[ $type ] ?? null;
	}

	/**
	 * Get all cached variants
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string> Variants.
	 */
	public function get_all_variants( int $post_id ): array {
		$variants = get_post_meta( $post_id, self::META_VARIANTS, true );

		return is_array( $variants ) ? $variants : array();
	}

	/**
	 * Clear cached variants
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success.
	 */
	public function clear_variants( int $post_id ): bool {
		return delete_post_meta( $post_id, self::META_VARIANTS );
	}

	/**
	 * Get available variant types
	 *
	 * @return array<string, string> Variant types.
	 */
	public static function get_variant_types(): array {
		return self::VARIANT_TYPES;
	}
}


