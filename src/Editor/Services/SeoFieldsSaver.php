<?php
/**
 * Service for saving SEO fields from AJAX requests.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Utils\Logger;
use function clean_post_cache;
use function delete_post_meta;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function trim;
use function update_post_meta;
use function update_post_meta_cache;
use function wp_cache_delete;
use function wp_cache_flush_group;
use function wp_unslash;

/**
 * Service for saving SEO fields from AJAX requests.
 */
class SeoFieldsSaver {
	/**
	 * Meta keys for SEO fields.
	 */
	private const META_TITLE = '_fp_seo_title';
	private const META_DESCRIPTION = '_fp_seo_meta_description';
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';
	private const META_EXCERPT = '_fp_seo_excerpt';
	private const META_CANONICAL = '_fp_seo_canonical';
	private const META_ROBOTS = '_fp_seo_robots';

	/**
	 * Save SEO fields from POST data.
	 *
	 * @param int $post_id Post ID.
	 * @return array Saved fields data.
	 */
	public function save_from_post( int $post_id ): array {
		$saved = array();

		// Get and sanitize values - support both old and new field names
		$seo_title = $this->get_field_value( 'fp_seo_title', 'seo_title', 'text' );
		$meta_description = $this->get_field_value( 'fp_seo_meta_description', 'meta_description', 'textarea' );
		$focus_keyword = $this->get_field_value( 'fp_seo_focus_keyword', 'focus_keyword', 'text' );
		$secondary_keywords = $this->get_field_value( 'fp_seo_secondary_keywords', 'secondary_keywords', 'text' );
		$excerpt = $this->get_field_value( 'fp_seo_excerpt', 'excerpt', 'textarea' );
		$canonical = $this->get_field_value( 'fp_seo_canonical', 'canonical', 'text' );
		$robots = $this->get_field_value( 'fp_seo_robots', 'robots', 'text' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'SeoFieldsSaver - Saving fields', array(
				'post_id' => $post_id,
				'has_title' => ! empty( $seo_title ),
				'has_description' => ! empty( $meta_description ),
				'has_focus_keyword' => ! empty( $focus_keyword ),
				'has_excerpt' => ! empty( $excerpt ),
			) );
		}

		// Save meta fields
		$saved['title'] = $this->save_field( $post_id, self::META_TITLE, $seo_title );
		$saved['description'] = $this->save_field( $post_id, self::META_DESCRIPTION, $meta_description );
		$saved['focus_keyword'] = $this->save_field( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
		$saved['secondary_keywords'] = $this->save_field( $post_id, self::META_SECONDARY_KEYWORDS, $secondary_keywords );
		$saved['canonical'] = $this->save_field( $post_id, self::META_CANONICAL, $canonical );
		$saved['robots'] = $this->save_field( $post_id, self::META_ROBOTS, $robots );

		// Save excerpt using direct DB update to avoid triggering wp_update_post hooks
		$saved['excerpt'] = $this->save_excerpt( $post_id, $excerpt );

		// Clear cache
		$this->clear_cache( $post_id );

		return $saved;
	}

	/**
	 * Save excerpt using direct DB update.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $excerpt  Excerpt value.
	 * @return bool Whether excerpt was saved.
	 */
	private function save_excerpt( int $post_id, string $excerpt ): bool {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array( 'post_excerpt' => $excerpt ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Clear cache after direct DB update
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'posts' );

		return true;
	}

	/**
	 * Clear cache for post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function clear_cache( int $post_id ): void {
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_delete( $post_id, 'posts' );

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}

		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post_id ) );
		}
	}

	/**
	 * Get field value from POST data.
	 *
	 * @param string $primary_key   Primary field key.
	 * @param string $fallback_key  Fallback field key.
	 * @param string $type          Field type ('text' or 'textarea').
	 * @return string Field value.
	 */
	private function get_field_value( string $primary_key, string $fallback_key, string $type = 'text' ): string {
		$value = '';

		if ( isset( $_POST[ $primary_key ] ) ) {
			$value = $type === 'textarea' 
				? sanitize_textarea_field( wp_unslash( (string) $_POST[ $primary_key ] ) )
				: sanitize_text_field( wp_unslash( (string) $_POST[ $primary_key ] ) );
			$value = trim( $value );
		} elseif ( isset( $_POST[ $fallback_key ] ) ) {
			$value = $type === 'textarea'
				? sanitize_textarea_field( wp_unslash( (string) $_POST[ $fallback_key ] ) )
				: sanitize_text_field( wp_unslash( (string) $_POST[ $fallback_key ] ) );
			$value = trim( $value );
		}

		return $value;
	}

	/**
	 * Save a single field.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $value    Field value.
	 * @return bool Whether the field was saved.
	 */
	private function save_field( int $post_id, string $meta_key, string $value ): bool {
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
			return true;
		} else {
			delete_post_meta( $post_id, $meta_key );
			return false;
		}
	}
}

