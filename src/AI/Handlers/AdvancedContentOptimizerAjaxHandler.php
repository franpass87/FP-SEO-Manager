<?php
/**
 * AJAX handler for Advanced Content Optimizer.
 *
 * @package FP\SEO\AI\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI\Handlers;

use FP\SEO\AI\AdvancedContentOptimizer;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function check_ajax_referer;
use function current_user_can;
use function esc_url_raw;
use function sanitize_text_field;
use function wp_http_validate_url;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

/**
 * Handles AJAX requests for Advanced Content Optimizer.
 */
class AdvancedContentOptimizerAjaxHandler {
	/**
	 * @var AdvancedContentOptimizer
	 */
	private AdvancedContentOptimizer $optimizer;

	/**
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param AdvancedContentOptimizer $optimizer Optimizer instance.
	 * @param HookManagerInterface     $hook_manager Hook manager instance.
	 */
	public function __construct( AdvancedContentOptimizer $optimizer, HookManagerInterface $hook_manager ) {
		$this->optimizer    = $optimizer;
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register AJAX handlers.
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_analyze_content_gaps', array( $this, 'handle_analyze_content_gaps' ) );
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_competitor_analysis', array( $this, 'handle_competitor_analysis' ) );
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_content_suggestions', array( $this, 'handle_content_suggestions' ) );
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_readability_optimization', array( $this, 'handle_readability_optimization' ) );
		$this->hook_manager->add_action( 'wp_ajax_fp_seo_semantic_optimization', array( $this, 'handle_semantic_optimization' ) );
	}

	/**
	 * Handle content gaps analysis AJAX request.
	 */
	public function handle_analyze_content_gaps(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$topic       = sanitize_text_field( $_POST['topic'] ?? '' );
		$keyword     = sanitize_text_field( $_POST['keyword'] ?? '' );
		$competitors = array_filter(
			array_map(
				static function( $url ) {
					$url = esc_url_raw( trim( (string) $url ) );
					return ( $url && wp_http_validate_url( $url ) ) ? $url : '';
				},
				explode( "\n", $_POST['competitors'] ?? '' )
			)
		);

		if ( empty( $topic ) || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Argomento e keyword sono obbligatori.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		try {
			$results = $this->optimizer->analyze_content_gaps( $topic, $keyword, $competitors );
			wp_send_json_success( $results );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Handle competitor analysis AJAX request.
	 */
	public function handle_competitor_analysis(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$url     = esc_url_raw( $_POST['url'] ?? '' );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? '' );

		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			wp_send_json_error(
				array( 'message' => __( 'URL non valido. Inserisci un indirizzo completo con http o https.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		if ( empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La keyword per l\'analisi del competitor è obbligatoria.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		try {
			$results = $this->optimizer->analyze_competitor_content( $url, $keyword );
			wp_send_json_success( $results );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Handle content suggestions AJAX request.
	 */
	public function handle_content_suggestions(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? ( $_POST['focus_keyword'] ?? '' ) );

		if ( ! $post_id || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Seleziona un contenuto e indica la keyword da analizzare.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				array( 'message' => __( 'Contenuto non trovato.', 'fp-seo-performance' ) ),
				404
			);
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per analizzare questo contenuto.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		try {
			$results = $this->optimizer->generate_content_suggestions( $post_id, $post->post_content, $keyword );
			wp_send_json_success( $results );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Handle readability optimization AJAX request.
	 */
	public function handle_readability_optimization(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$content  = wp_unslash( $_POST['content'] ?? '' );
		$audience = sanitize_text_field( $_POST['audience'] ?? 'general' );

		if ( empty( $content ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Il contenuto da analizzare è obbligatorio.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		try {
			$results = $this->optimizer->optimize_readability( $content, $audience );
			wp_send_json_success( $results );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Handle semantic SEO optimization AJAX request.
	 */
	public function handle_semantic_optimization(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
			return;
		}

		$content           = wp_unslash( $_POST['content'] ?? '' );
		$keyword           = sanitize_text_field( $_POST['keyword'] ?? '' );
		$semantic_keywords = array_filter( array_map( 'trim', explode( ',', $_POST['semantic_keywords'] ?? '' ) ) );

		if ( empty( $content ) || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Contenuto e keyword sono obbligatori per l\'ottimizzazione semantica.', 'fp-seo-performance' ) ),
				400
			);
			return;
		}

		try {
			$results = $this->optimizer->optimize_semantic_seo( $content, $keyword, $semantic_keywords );
			wp_send_json_success( $results );
			return;
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}
	}
}








