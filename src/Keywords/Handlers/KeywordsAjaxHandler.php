<?php
/**
 * AJAX handler for keywords operations.
 *
 * @package FP\SEO\Keywords\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Handlers;

use FP\SEO\Keywords\MultipleKeywordsManager;
use function check_ajax_referer;
use function current_time;
use function get_post;
use function sanitize_text_field;
use function update_post_meta;
use function wp_send_json_error;
use function wp_send_json_success;

/**
 * AJAX handler for keywords operations.
 */
class KeywordsAjaxHandler {
	/**
	 * @var MultipleKeywordsManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param MultipleKeywordsManager $manager Keywords manager instance.
	 */
	public function __construct( MultipleKeywordsManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_analyze_keywords', array( $this, 'handle_analyze' ) );
		add_action( 'wp_ajax_fp_seo_suggest_keywords', array( $this, 'handle_suggest' ) );
		add_action( 'wp_ajax_fp_seo_optimize_keywords', array( $this, 'handle_optimize' ) );
	}

	/**
	 * Handle analyze keywords AJAX request.
	 *
	 * @return void
	 */
	public function handle_analyze(): void {
		check_ajax_referer( 'fp_seo_keywords_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$keywords_data = $this->manager->get_post_keywords( $post_id );
		if ( empty( $keywords_data ) ) {
			wp_send_json_error( 'No keywords found for this post' );
		}

		$analysis = $this->manager->analyze_keywords_in_content( $post_id, $keywords_data );
		
		// Update keywords data with analysis
		$keywords_data['keyword_density'] = $analysis['density'];
		$keywords_data['keyword_positions'] = $analysis['positions'];
		$keywords_data['last_analyzed'] = current_time( 'mysql' );
		
		update_post_meta( $post_id, '_fp_seo_multiple_keywords', $keywords_data );
		\FP\SEO\Utils\Cache::delete( 'fp_seo_keywords_' . $post_id );

		wp_send_json_success( array(
			'message' => __( 'Keywords analyzed successfully', 'fp-seo-performance' ),
			'analysis' => $analysis
		) );
	}

	/**
	 * Handle suggest keywords AJAX request.
	 *
	 * @return void
	 */
	public function handle_suggest(): void {
		check_ajax_referer( 'fp_seo_keywords_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$type = sanitize_text_field( $_POST['type'] ?? 'all' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$suggestions = $this->manager->get_keyword_suggestions( $post_id );
		
		if ( $type !== 'all' && isset( $suggestions[ $type ] ) ) {
			$suggestions = array( $type => $suggestions[ $type ] );
		}

		wp_send_json_success( $suggestions );
	}

	/**
	 * Handle optimize keywords AJAX request.
	 *
	 * @return void
	 */
	public function handle_optimize(): void {
		check_ajax_referer( 'fp_seo_keywords_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}

		$optimized = $this->manager->optimize_keywords_with_ai( $post );
		wp_send_json_success( $optimized );
	}
}

