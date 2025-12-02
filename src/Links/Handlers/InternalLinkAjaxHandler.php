<?php
/**
 * AJAX handler for internal links operations.
 *
 * @package FP\SEO\Links\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Handlers;

use FP\SEO\Links\InternalLinkManager;
use FP\SEO\Utils\Logger;
use function absint;
use function check_ajax_referer;
use function current_user_can;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

/**
 * AJAX handler for internal links operations.
 */
class InternalLinkAjaxHandler {
	/**
	 * @var InternalLinkManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param InternalLinkManager $manager Internal link manager instance.
	 */
	public function __construct( InternalLinkManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_get_link_suggestions', array( $this, 'handle_get_suggestions' ) );
		add_action( 'wp_ajax_fp_seo_analyze_internal_links', array( $this, 'handle_analyze' ) );
		add_action( 'wp_ajax_fp_seo_optimize_internal_links', array( $this, 'handle_optimize' ) );
	}

	/**
	 * Handle get link suggestions AJAX request.
	 *
	 * @return void
	 */
	public function handle_get_suggestions(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID or insufficient permissions.', 'fp-seo-performance' ) ), 403 );
		}

		$options = $_POST['options'] ?? array();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$suggestions = $this->manager->get_link_suggestions( $post_id, $options );

		wp_send_json_success( array(
			'suggestions' => $suggestions,
		) );
	}

	/**
	 * Handle analyze internal links AJAX request.
	 *
	 * @return void
	 */
	public function handle_analyze(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID or insufficient permissions.', 'fp-seo-performance' ) ), 403 );
		}

		try {
			$analysis = $this->manager->analyze_post_links( $post_id );

			wp_send_json_success( array(
				'analysis' => $analysis,
			) );
		} catch ( \Exception $e ) {
			Logger::error( 'Internal links analysis error', array(
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			) );

			wp_send_json_error( array(
				'message' => __( 'Error analyzing internal links.', 'fp-seo-performance' ),
				'error' => $e->getMessage(),
			), 500 );
		}
	}

	/**
	 * Handle optimize internal links AJAX request.
	 *
	 * @return void
	 */
	public function handle_optimize(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID or insufficient permissions.', 'fp-seo-performance' ) ), 403 );
		}

		$optimization = $this->manager->optimize_post_links( $post_id );
		wp_send_json_success( $optimization );
	}
}

