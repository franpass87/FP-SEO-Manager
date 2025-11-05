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
		error_log( '[FP-SEO-AI-AJAX] Starting generate_seo_suggestions for post_id: ' . $post_id );
		error_log( '[FP-SEO-AI-AJAX] Content length: ' . strlen( $content ) . ', Title: ' . $title );
		error_log( '[FP-SEO-AI-AJAX] Focus keyword: ' . $focus_keyword );
		
		$result = $this->client->generate_seo_suggestions( $post_id, $content, $title, $focus_keyword );
		
		error_log( '[FP-SEO-AI-AJAX] Result received: ' . print_r( $result, true ) );

		if ( ! $result['success'] ) {
			$error_msg = $result['error'] ?? __( 'Errore sconosciuto.', 'fp-seo-performance' );
			error_log( '[FP-SEO-AI-AJAX] Generation failed: ' . $error_msg );
			
			// Include debug info if available
			if ( isset( $result['debug'] ) ) {
				error_log( '[FP-SEO-AI-AJAX] Debug info: ' . print_r( $result['debug'], true ) );
			}
			
			wp_send_json_error(
				array(
					'message' => $error_msg,
					'debug' => $result['debug'] ?? array(),
				),
				500
			);
		}

		error_log( '[FP-SEO-AI-AJAX] Generation successful, sending response' );
		
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
		error_log( '[FP-SEO-AI-AJAX] Exception caught: ' . $e->getMessage() );
		error_log( '[FP-SEO-AI-AJAX] Stack trace: ' . $e->getTraceAsString() );
		wp_send_json_error(
			array(
				'message' => $e->getMessage(),
			),
			500
		);
	}
}
}

