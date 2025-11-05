<?php
/**
 * Internal Linking AJAX Handler
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Linking;

use function add_action;
use function check_ajax_referer;
use function current_user_can;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function absint;

/**
 * Handles AJAX requests for internal linking suggestions
 */
class LinkingAjax {

	private const ACTION = 'fp_seo_get_link_suggestions';
	private const NONCE_ACTION = 'fp_seo_linking_nonce';

	/**
	 * @var InternalLinkSuggester
	 */
	private InternalLinkSuggester $suggester;

	public function __construct() {
		$this->suggester = new InternalLinkSuggester();
	}

	/**
	 * Register AJAX hooks
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_get_suggestions' ) );
	}

	/**
	 * Handle AJAX request for link suggestions
	 */
	public function handle_get_suggestions(): void {
		// Verify nonce
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'fp-seo-performance' ) ) );
		}

		// Get post ID
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'fp-seo-performance' ) ) );
		}

		// Get suggestions
		$suggestions = $this->suggester->get_suggestions( $post_id );

		wp_send_json_success( array(
			'suggestions' => $suggestions,
			'count'       => count( $suggestions ),
		) );
	}

	/**
	 * Get nonce action
	 */
	public static function get_nonce_action(): string {
		return self::NONCE_ACTION;
	}

	/**
	 * Get AJAX action
	 */
	public static function get_action(): string {
		return self::ACTION;
	}
}

