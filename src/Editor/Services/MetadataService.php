<?php
/**
 * Service for managing SEO metadata operations.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function delete_post_meta;
use function get_post_meta;
use function update_post_meta;

/**
 * Service for managing SEO metadata operations.
 */
class MetadataService {
	/**
	 * Get SEO metadata for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Metadata array.
	 */
	public function get_meta( int $post_id ): array {
		$post = get_post( $post_id );
		return array(
			'seo_title'          => MetadataResolver::resolve_seo_title( $post_id ),
			'meta_description'   => MetadataResolver::resolve_meta_description( $post ),
			'canonical'          => MetadataResolver::resolve_canonical_url( $post ),
			'robots'             => MetadataResolver::resolve_robots( $post ),
			'focus_keyword'      => get_post_meta( $post_id, Metabox::META_FOCUS_KEYWORD, true ),
			'secondary_keywords' => get_post_meta( $post_id, Metabox::META_SECONDARY_KEYWORDS, true ),
			'excluded'           => $this->is_excluded( $post_id ),
		);
	}

	/**
	 * Save SEO metadata for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<string, mixed> $metadata Metadata array.
	 * @return void
	 */
	public function save_meta( int $post_id, array $metadata ): void {
		if ( isset( $metadata['seo_title'] ) ) {
			$this->save_field( $post_id, '_fp_seo_title', $metadata['seo_title'] );
		}

		if ( isset( $metadata['meta_description'] ) ) {
			$this->save_field( $post_id, '_fp_seo_meta_description', $metadata['meta_description'] );
		}

		if ( isset( $metadata['canonical'] ) ) {
			$this->save_field( $post_id, '_fp_seo_canonical', $metadata['canonical'] );
		}

		if ( isset( $metadata['robots'] ) ) {
			$this->save_field( $post_id, '_fp_seo_robots', $metadata['robots'] );
		}

		if ( isset( $metadata['focus_keyword'] ) ) {
			$this->save_field( $post_id, Metabox::META_FOCUS_KEYWORD, $metadata['focus_keyword'] );
		}

		if ( isset( $metadata['secondary_keywords'] ) ) {
			$this->save_field( $post_id, Metabox::META_SECONDARY_KEYWORDS, $metadata['secondary_keywords'] );
		}

		if ( isset( $metadata['excluded'] ) ) {
			$this->save_field( $post_id, Metabox::META_EXCLUDE, $metadata['excluded'] ? '1' : '' );
		}
	}

	/**
	 * Delete SEO metadata for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Optional meta key to delete (if empty, deletes all).
	 * @return void
	 */
	public function delete_meta( int $post_id, string $meta_key = '' ): void {
		if ( ! empty( $meta_key ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}

		// Delete all SEO metadata
		delete_post_meta( $post_id, '_fp_seo_title' );
		delete_post_meta( $post_id, '_fp_seo_meta_description' );
		delete_post_meta( $post_id, '_fp_seo_canonical' );
		delete_post_meta( $post_id, '_fp_seo_robots' );
		delete_post_meta( $post_id, Metabox::META_FOCUS_KEYWORD );
		delete_post_meta( $post_id, Metabox::META_SECONDARY_KEYWORDS );
		delete_post_meta( $post_id, Metabox::META_EXCLUDE );
	}

	/**
	 * Get all SEO metadata as array.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> All metadata.
	 */
	public function get_all_meta( int $post_id ): array {
		return $this->get_meta( $post_id );
	}

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if excluded.
	 */
	public function is_excluded( int $post_id ): bool {
		$excluded = get_post_meta( $post_id, Metabox::META_EXCLUDE, true );

		// Fallback: query database directly if get_post_meta returns empty
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
	 * Save a single metadata field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return bool True if saved successfully.
	 */
	private function save_field( int $post_id, string $meta_key, $meta_value ): bool {
		if ( '' === (string) $meta_value ) {
			delete_post_meta( $post_id, $meta_key );
			return true;
		}

		return (bool) update_post_meta( $post_id, $meta_key, $meta_value );
	}
}


