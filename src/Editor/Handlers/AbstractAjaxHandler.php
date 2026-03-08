<?php
/**
 * Abstract AJAX handler base class for Editor handlers.
 *
 * @package FP\SEO\Editor\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Handlers;

use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\Logger;
use function absint;
use function check_ajax_referer;
use function current_user_can;
use function get_post;
use function get_post_type;
use function in_array;
use function sanitize_text_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Abstract base class for Editor AJAX handlers.
 *
 * Provides common functionality for validation, nonce checking, and capability checking.
 */
abstract class AbstractAjaxHandler {
	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	protected HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Verify nonce for AJAX request.
	 *
	 * @param string $action Nonce action.
	 * @param string $nonce_field Nonce field name (default: 'nonce').
	 * @return bool True if valid, false otherwise.
	 */
	protected function verify_nonce( string $action, string $nonce_field = 'nonce' ): bool {
		if ( ! isset( $_POST[ $nonce_field ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Verify AJAX referer.
	 *
	 * @param string $action Action name.
	 * @param string $nonce_field Nonce field name (default: 'nonce').
	 * @return void
	 */
	protected function verify_ajax_referer( string $action, string $nonce_field = 'nonce' ): void {
		check_ajax_referer( $action, $nonce_field );
	}

	/**
	 * Check user capability.
	 *
	 * @param string $capability Capability to check.
	 * @param int    $post_id Optional post ID for edit_post capability.
	 * @return bool True if user has capability.
	 */
	protected function check_capability( string $capability, int $post_id = 0 ): bool {
		if ( $post_id > 0 && 'edit_post' === $capability ) {
			return current_user_can( 'edit_post', $post_id );
		}

		return current_user_can( $capability );
	}

	/**
	 * Get post ID from request.
	 *
	 * @param string $field Field name ('post_id' or 'postId').
	 * @return int Post ID or 0 if invalid.
	 */
	protected function get_post_id_from_request( string $field = 'post_id' ): int {
		if ( 'post_id' === $field ) {
			// Support both post_id and postId
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : ( isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0 );
		} else {
			$post_id = isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : 0;
		}

		return $post_id;
	}

	/**
	 * Get post object from request.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|null Post object or null if not found.
	 */
	protected function get_post_from_request( int $post_id ): ?\WP_Post {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Validate post type.
	 *
	 * @param string   $post_type Post type to check.
	 * @param string[] $supported_types Array of supported post types.
	 * @return bool True if supported.
	 */
	protected function validate_post_type( string $post_type, array $supported_types ): bool {
		return in_array( $post_type, $supported_types, true );
	}

	/**
	 * Send JSON error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP status code.
	 * @param array<string, mixed> $data Additional error data.
	 * @return void
	 */
	protected function send_error( string $message, int $status_code = 400, array $data = [] ): void {
		$response = array_merge( array( 'message' => $message ), $data );
		wp_send_json_error( $response, $status_code );
		return;
	}

	/**
	 * Send JSON success response.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return void
	 */
	protected function send_success( array $data = [] ): void {
		wp_send_json_success( $data );
		return;
	}

	/**
	 * Log error.
	 *
	 * @param string              $message Error message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	protected function log_error( string $message, array $context = [] ): void {
		Logger::error( $message, $context );
	}

	/**
	 * Log debug message.
	 *
	 * @param string              $message Debug message.
	 * @param array<string, mixed> $context Additional context.
	 * @return void
	 */
	protected function log_debug( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		Logger::debug( $message, $context );
	}
}


