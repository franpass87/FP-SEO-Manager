<?php
/**
 * Trait for common AJAX validation logic.
 *
 * @package FP\SEO\Admin\Traits
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Traits;

use WP_Post;

/**
 * Trait for common AJAX validation logic.
 */
trait AjaxValidationTrait {
	/**
	 * Validate AJAX request with nonce, post ID, and permissions.
	 *
	 * @param string $nonce_action Nonce action name.
	 * @param string $nonce_name Nonce field name (default: 'nonce').
	 * @return array{post_id: int, post: WP_Post}|null Returns array with post_id and post on success, null on failure.
	 */
	protected function validate_ajax_post_request( string $nonce_action, string $nonce_name = 'nonce' ): ?array {
		check_ajax_referer( $nonce_action, $nonce_name );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID or insufficient permissions' ), 403 );
			return null; // wp_send_json_error exits, but for type safety
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => 'Post not found' ), 404 );
			return null; // wp_send_json_error exits, but for type safety
		}

		return array(
			'post_id' => $post_id,
			'post'    => $post,
		);
	}

	/**
	 * Handle exception and send JSON error response.
	 *
	 * @param \Exception $e Exception to handle.
	 * @param int|null   $post_id Optional post ID for logging.
	 * @param string     $context Context for logging (e.g., method name).
	 * @return void
	 */
	protected function handle_ajax_exception( \Exception $e, ?int $post_id = null, string $context = '' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\FP\SEO\Utils\Logger::error( $context . ' - Error', array(
				'post_id' => $post_id,
				'error'   => $e->getMessage(),
				'trace'   => $e->getTraceAsString(),
			) );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
	}
}


