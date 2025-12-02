<?php
/**
 * Helper class for post validation in metabox rendering.
 *
 * @package FP\SEO\Editor\Helpers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Helpers;

use FP\SEO\Utils\Logger;
use WP_Post;
use function absint;
use function class_exists;
use function get_post;

/**
 * Helper class for post validation in metabox rendering.
 */
class PostValidationHelper {
	/**
	 * Validate post object and ensure it's valid.
	 *
	 * @param WP_Post|mixed $post Post object to validate.
	 * @return WP_Post|null Valid post object or null if invalid.
	 */
	public static function validate_post( $post ): ?WP_Post {
		// CRITICAL: Validate $post before proceeding
		if ( ! $post instanceof WP_Post ) {
			Logger::error( 'FP SEO: Invalid post object passed to validation', array(
				'post_type' => gettype( $post ),
				'post_value' => is_object( $post ) ? get_class( $post ) : (string) $post,
			) );
			return null;
		}

		// Ensure we have a valid post ID
		if ( empty( $post->ID ) || $post->ID <= 0 ) {
			// Try to get post ID from request
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0 );
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::error( 'FP SEO: Could not retrieve post in validation', array(
							'post_id' => $post_id,
						) );
					}
					return null;
				}
			} else {
				// New post - that's OK, continue with empty fields
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'FP SEO: Validating metabox for new post', array(
						'post_type' => $post->post_type ?? 'unknown',
					) );
				}
			}
		}

		return $post;
	}

	/**
	 * Validate required classes are loaded.
	 *
	 * @param array<string> $required_classes List of required class names.
	 * @return bool True if all classes are loaded, false otherwise.
	 */
	public static function validate_required_classes( array $required_classes ): bool {
		foreach ( $required_classes as $class_name ) {
			if ( ! class_exists( $class_name, false ) ) {
				Logger::error( 'FP SEO: Required class not loaded', array(
					'class' => $class_name,
				) );
				return false;
			}
		}
		return true;
	}
}


