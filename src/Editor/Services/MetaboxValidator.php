<?php
/**
 * Validation service for metabox operations.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Utils\PostTypes;
use WP_Post;

/**
 * Validates metabox operations and inputs.
 */
class MetaboxValidator {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Validate post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if valid.
	 */
	public function validate_post_id( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		return $post instanceof WP_Post;
	}

	/**
	 * Validate post type is supported.
	 *
	 * @param int|string $post_id_or_type Post ID or post type string.
	 * @return bool True if supported.
	 */
	public function validate_post_type( $post_id_or_type ): bool {
		if ( is_int( $post_id_or_type ) ) {
			$post_type = get_post_type( $post_id_or_type );
		} else {
			$post_type = $post_id_or_type;
		}

		if ( empty( $post_type ) ) {
			return false;
		}

		$supported_types = PostTypes::analyzable();
		return in_array( $post_type, $supported_types, true );
	}

	/**
	 * Validate user can edit post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if user can edit.
	 */
	public function validate_user_can_edit( int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Validate nonce.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action.
	 * @return bool True if valid.
	 */
	public function validate_nonce( string $nonce, string $action ): bool {
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Validate AJAX request.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $nonce   Nonce value.
	 * @param string $action  Nonce action.
	 * @return array{valid: bool, error?: string, post_type?: string} Validation result.
	 * @throws \InvalidArgumentException If post_id is invalid.
	 * 
	 * @example
	 * $validator = new MetaboxValidator($logger);
	 * $result = $validator->validate_ajax_request(123, $nonce, 'action');
	 * if (!$result['valid']) {
	 *     wp_send_json_error(['message' => $result['error']]);
	 * }
	 */
	public function validate_ajax_request( int $post_id, string $nonce, string $action ): array {
		// Validate post ID
		if ( ! $this->validate_post_id( $post_id ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Invalid post ID.', 'fp-seo-performance' ),
			);
		}

		// Validate post type FIRST (before any processing)
		$post_type = get_post_type( $post_id );
		if ( ! $this->validate_post_type( $post_type ) ) {
			return array(
				'valid' => false,
				'error' => __( 'This post type is not supported for SEO optimization.', 'fp-seo-performance' ),
				'post_type' => $post_type,
			);
		}

		// Validate user permissions
		if ( ! $this->validate_user_can_edit( $post_id ) ) {
			return array(
				'valid' => false,
				'error' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ),
			);
		}

		// Validate nonce
		if ( ! $this->validate_nonce( $nonce, $action ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Security check failed.', 'fp-seo-performance' ),
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Validate SEO title.
	 *
	 * @param string $title SEO title.
	 * @return array{valid: bool, error?: string, length?: int} Validation result.
	 */
	public function validate_seo_title( string $title ): array {
		$length = mb_strlen( $title );
		
		if ( $length > 60 ) {
			return array(
				'valid' => false,
				'error' => __( 'SEO title should be 60 characters or less.', 'fp-seo-performance' ),
				'length' => $length,
			);
		}

		return array( 'valid' => true, 'length' => $length );
	}

	/**
	 * Validate meta description.
	 *
	 * @param string $description Meta description.
	 * @return array{valid: bool, error?: string, length?: int} Validation result.
	 */
	public function validate_meta_description( string $description ): array {
		$length = mb_strlen( $description );
		
		if ( $length > 155 ) {
			return array(
				'valid' => false,
				'error' => __( 'Meta description should be 155 characters or less.', 'fp-seo-performance' ),
				'length' => $length,
			);
		}

		return array( 'valid' => true, 'length' => $length );
	}

	/**
	 * Validate focus keyword.
	 *
	 * @param string $keyword Focus keyword.
	 * @return array{valid: bool, error?: string} Validation result.
	 */
	public function validate_focus_keyword( string $keyword ): array {
		$length = mb_strlen( trim( $keyword ) );
		
		if ( $length > 100 ) {
			return array(
				'valid' => false,
				'error' => __( 'Focus keyword should be 100 characters or less.', 'fp-seo-performance' ),
			);
		}

		return array( 'valid' => true );
	}
}

