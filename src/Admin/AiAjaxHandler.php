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
	}

	/**
	 * Handle AI content generation request.
	 */
	public function handle_generate_request(): void {
		// Verify nonce.
		check_ajax_referer( 'fp_seo_ai_generate', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permessi insufficienti.', 'fp-seo-performance' ),
				),
				403
			);
		}

		// Check if AI is enabled.
		if ( ! Options::get_option( 'ai.enable_auto_generation', true ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'La generazione AI è disabilitata nelle impostazioni.', 'fp-seo-performance' ),
				),
				400
			);
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
		
		$result = $this->client->generate_seo_suggestions( $post_id, $content, $title, $focus_keyword );
		
		Logger::debug( 'AI generation result received', array( 'success' => $result['success'] ?? false ) );

		if ( ! $result['success'] ) {
			$error_msg = $result['error'] ?? __( 'Errore sconosciuto.', 'fp-seo-performance' );
			Logger::error( 'AI generation failed', array(
				'error' => $error_msg,
				'debug' => $result['debug'] ?? array(),
			) );
			
			wp_send_json_error(
				array(
					'message' => $error_msg,
					'debug' => $result['debug'] ?? array(),
				),
				500
			);
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
	} catch ( \Exception $e ) {
		Logger::error( 'AI generation exception', array(
			'message' => $e->getMessage(),
			'trace' => $e->getTraceAsString(),
		) );
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
			),
			500
		);
	}
}
}

