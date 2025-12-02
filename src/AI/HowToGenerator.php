<?php
/**
 * HowTo Schema Generator using AI
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI;

use FP\SEO\Integrations\OpenAiClient;
use function do_shortcode;
use function esc_html;
use function get_current_blog_id;
use function get_post_meta;
use function json_decode;
use function mb_substr;
use function preg_replace;
use function sanitize_text_field;
use function trim;
use function update_post_meta;
use function wp_kses_post;
use function wp_strip_all_tags;
use WP_Post;

/**
 * Generates HowTo Schema steps using AI
 */
class HowToGenerator {

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->openai_client = new OpenAiClient();
	}

	/**
	 * Generate HowTo steps for a post using AI
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @return array<string, mixed> Generated steps data.
	 * @throws \Exception If generation fails.
	 */
	public function generate_steps( int $post_id, WP_Post $post ): array {
		if ( ! $this->openai_client->is_configured() ) {
			throw new \Exception( 'OpenAI API key non configurata. Vai in Impostazioni > FP SEO.' );
		}

		$content = $this->prepare_content( $post->post_content );

		if ( empty( $content ) ) {
			throw new \Exception( 'Il contenuto del post è vuoto. Aggiungi contenuto prima di generare gli step.' );
		}

		$prompt = $this->build_prompt( $post->post_title, $content );
		$response = $this->openai_client->generate_content( $prompt, array(
			'model'                => 'gpt-4o-mini',
			'temperature'          => 0.3,
			'max_completion_tokens' => 2000,
		) );

		$steps = $this->parse_response( $response );

		if ( empty( $steps ) ) {
			throw new \Exception( 'Nessuno step generato. Assicurati che il contenuto contenga istruzioni o procedure.' );
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
		$howto_data['steps'] = array_merge( $howto_data['steps'] ?? array(), $steps );

		// Save HowTo data
		update_post_meta( $post_id, '_fp_seo_howto', $howto_data );

		return array(
			'steps'     => $steps,
			'all_steps' => $howto_data['steps'],
		);
	}

	/**
	 * Prepare content for AI processing
	 *
	 * @param string $content Raw post content.
	 * @return string Prepared content.
	 */
	private function prepare_content( string $content ): string {
		// Handle WPBakery shortcodes
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

		return $content;
	}

	/**
	 * Build prompt for HowTo generation
	 *
	 * @param string $title   Post title.
	 * @param string $content Post content.
	 * @return string Generated prompt.
	 */
	private function build_prompt( string $title, string $content ): string {
		return sprintf(
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
	}

	/**
	 * Parse AI response and convert to HowTo format
	 *
	 * @param string $response Raw AI response.
	 * @return array<int, array<string, string>> Parsed steps.
	 */
	private function parse_response( string $response ): array {
		// Clean response
		$response = preg_replace( '/```json\s*/', '', $response );
		$response = preg_replace( '/```\s*$/', '', $response );
		$response = trim( $response );

		$data = json_decode( $response, true );

		if ( ! is_array( $data ) || ! isset( $data['steps'] ) || ! is_array( $data['steps'] ) ) {
			return array();
		}

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

		return $howto_steps;
	}
}

