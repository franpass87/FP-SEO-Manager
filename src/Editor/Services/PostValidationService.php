<?php
/**
 * Service for validating posts and post types.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\PostTypes;
use function get_post_meta;
use function get_post_type;
use function in_array;

/**
 * Service for validating posts and post types.
 */
class PostValidationService {

	/**
	 * Get supported post types for the metabox.
	 *
	 * @return array Array of supported post type slugs.
	 */
	public function get_supported_post_types(): array {
		return PostTypes::analyzable();
	}

	/**
	 * Check if a post type is supported.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool True if supported, false otherwise.
	 */
	public function is_post_type_supported( string $post_type ): bool {
		return in_array( $post_type, $this->get_supported_post_types(), true );
	}

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_post_excluded( int $post_id ): bool {
		// DISABLED: Cache clearing interferes with WordPress's post object during page load
		$excluded = get_post_meta( $post_id, Metabox::META_EXCLUDE, true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( '' === $excluded ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				Metabox::META_EXCLUDE
			) );
			if ( $db_value !== null ) {
				$excluded = $db_value;
			}
		}
		
		return '1' === $excluded;
	}

	/**
	 * Validate post type for a given post ID.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if post type is supported, false otherwise.
	 */
	public function validate_post_type( int $post_id ): bool {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return false;
		}
		return $this->is_post_type_supported( $post_type );
	}
}








