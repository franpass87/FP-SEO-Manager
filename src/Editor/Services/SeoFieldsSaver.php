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

use FP\SEO\Editor\MetaboxSaver;
use FP\SEO\Utils\Logger;
use WP_Post;
use WP_REST_Request;
use function clean_post_cache;
use function delete_post_meta;
use function get_post_status;
use function get_post_type;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
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
	 * Save all SEO fields for a post (main save method, delegates to MetaboxSaver).
	 * This method provides a unified interface for saving SEO fields and delegates
	 * to MetaboxSaver which contains the complete field handling logic.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if saved successfully, false otherwise.
	 */
	public function save_all_fields( int $post_id ): bool {
		$saver = new MetaboxSaver();
		return $saver->save_all_fields( $post_id );
	}

	/**
	 * Save SEO fields from POST data (for AJAX requests).
	 *
	 * @param int $post_id Post ID.
	 * @return array Saved fields data.
	 */
	public function save_from_post( int $post_id ): array {
		// CRITICAL: Block execution completely for WordPress featured image AJAX requests
		// This prevents interference when featured image is saved via AJAX
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) ) {
			$ajax_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			$featured_image_actions = array( 'set-post-thumbnail', 'remove-post-thumbnail' );
			
			if ( in_array( $ajax_action, $featured_image_actions, true ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'SeoFieldsSaver::save_from_post BLOCKED - WordPress featured image AJAX request', array( 
						'post_id' => $post_id,
						'action' => $ajax_action 
					) );
				}
				return array();
			}
		}
		
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

		// CRITICAL: Do NOT clear cache here - can interfere with featured image saving
		// Cache will be naturally refreshed on next access via WordPress core mechanisms

		return true;
	}

	/**
	 * Clear cache for post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 * @deprecated This method is disabled to prevent interference with featured image saving
	 */
	private function clear_cache( int $post_id ): void {
		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id) saving
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache during save_post can interfere with WordPress core saving _thumbnail_id
		return; // Do nothing - WordPress will refresh cache naturally
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

	/**
	 * Save SEO fields from REST API request (Gutenberg).
	 *
	 * @param WP_Post         $post     Post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating Whether creating a new post.
	 * @return void
	 */
	public function save_from_rest( WP_Post $post, $request, bool $creating ): void {
		if ( ! $request instanceof WP_REST_Request ) {
			return;
		}

		$params = $request->get_params();

		// Look for SEO fields in parameters (can be in meta or directly)
		$seo_title = $params['fp_seo_title'] ?? $params['meta']['_fp_seo_title'] ?? null;
		$meta_desc = $params['fp_seo_meta_description'] ?? $params['meta']['_fp_seo_meta_description'] ?? null;
		$excerpt = $params['excerpt'] ?? $params['fp_seo_excerpt'] ?? null;

		// If found, save directly
		if ( $seo_title !== null || $meta_desc !== null || $excerpt !== null ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'SeoFieldsSaver::save_from_rest - Found SEO fields in request', array(
					'post_id' => $post->ID,
					'has_title' => $seo_title !== null,
					'has_description' => $meta_desc !== null,
				) );
			}

			// Use MetaboxSaver for consistency (it has all the field handling logic)
			$saver = new MetaboxSaver();

			// Simulate $_POST for saving
			$original_post = $_POST ?? array();
			if ( $seo_title !== null ) {
				$_POST['fp_seo_title'] = $seo_title;
				$_POST['fp_seo_title_sent'] = '1';
			}
			if ( $meta_desc !== null ) {
				$_POST['fp_seo_meta_description'] = $meta_desc;
				$_POST['fp_seo_meta_description_sent'] = '1';
			}
			if ( $excerpt !== null ) {
				$_POST['fp_seo_excerpt'] = $excerpt;
				$_POST['fp_seo_excerpt_sent'] = '1';
			}
			$_POST['fp_seo_performance_metabox_present'] = '1';

			$result = $saver->save_all_fields( $post->ID );

			// Restore original $_POST to avoid side effects
			$_POST = $original_post;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'SeoFieldsSaver::save_from_rest completed', array(
					'post_id' => $post->ID,
					'result' => $result ? 'success' : 'failed',
				) );
			}
		}
	}

	/**
	 * Save SEO fields from wp_insert_post hook.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 * @return void
	 */
	public function save_from_insert( int $post_id, $post, bool $update ): void {
		// Only for updates, not for new posts
		if ( ! $update ) {
			return;
		}

		// Check if we should save (post type validation is done by MetaboxSaver)
		if ( ! $this->should_save( $post_id ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'SeoFieldsSaver::save_from_insert called', array(
				'post_id' => $post_id,
				'post_type' => get_post_type( $post_id ),
				'update' => $update,
				'hook' => 'wp_insert_post',
			) );
		}

		$saver = new MetaboxSaver();
		$result = $saver->save_all_fields( $post_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'SeoFieldsSaver::save_from_insert completed', array(
				'post_id' => $post_id,
				'result' => $result ? 'success' : 'failed',
			) );
		}
	}

	/**
	 * Save SEO fields from edit_post hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_from_edit( int $post_id, $post ): void {
		// Check if we should save (post type validation is done by MetaboxSaver)
		if ( ! $this->should_save( $post_id ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'SeoFieldsSaver::save_from_edit called', array(
				'post_id' => $post_id,
				'post_type' => get_post_type( $post_id ),
				'hook' => 'edit_post',
			) );
		}

		$saver = new MetaboxSaver();
		$result = $saver->save_all_fields( $post_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'SeoFieldsSaver::save_from_edit completed', array(
				'post_id' => $post_id,
				'result' => $result ? 'success' : 'failed',
			) );
		}
	}

	/**
	 * Save SEO fields from wp_insert_post_data hook (pre-insert).
	 *
	 * @param array $data                  An array of slashed post data.
	 * @param array $postarr               An array of sanitized, but otherwise unmodified post data.
	 * @param array $unsanitized_postarr   An array of unsanitized post data (unused).
	 * @param bool  $update                Whether this is an existing post being updated (unused).
	 * @return array Modified post data.
	 */
	public function save_from_pre_insert( array $data, array $postarr, array $unsanitized_postarr = array(), bool $update = false ): array {
		$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$post_type = isset( $postarr['post_type'] ) ? $postarr['post_type'] : '';

		// Check post type - if not supported, return data unchanged
		if ( ! empty( $post_type ) ) {
			$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
			if ( ! in_array( $post_type, $supported_types, true ) ) {
				return $data;
			}
		}

		// Save excerpt if present (both for new posts and updates)
		if ( isset( $_POST['fp_seo_excerpt'] ) || isset( $postarr['fp_seo_excerpt'] ) ) {
			$excerpt = isset( $_POST['fp_seo_excerpt'] )
				? sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) )
				: sanitize_textarea_field( (string) ( $postarr['fp_seo_excerpt'] ?? '' ) );

			$excerpt = trim( $excerpt );

			// Update directly in data array to ensure it's saved
			$data['post_excerpt'] = $excerpt;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'SeoFieldsSaver::save_from_pre_insert - Excerpt saved', array(
					'post_id' => $post_id,
					'excerpt_length' => strlen( $excerpt ),
					'hook' => 'wp_insert_post_data',
				) );
			}
		}

		// Handle slug directly in $data array (avoids wp_update_post during wp_insert_post_data)
		if ( isset( $_POST['fp_seo_slug'] ) ) {
			$slug = trim( sanitize_title( wp_unslash( (string) $_POST['fp_seo_slug'] ) ) );
			if ( '' !== $slug ) {
				$data['post_name'] = $slug;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'SeoFieldsSaver::save_from_pre_insert - Slug updated in data array', array(
						'post_id' => $post_id,
						'slug' => $slug,
					) );
				}
			}
		}

		return $data;
	}

	/**
	 * Check if we should save for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if should save, false otherwise.
	 */
	private function should_save( int $post_id ): bool {
		// Skip auto-draft
		$current_status = get_post_status( $post_id );
		if ( $current_status === 'auto-draft' || $current_status === false ) {
			return false;
		}

		// Check post type
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return false;
		}

		// Check if metabox fields are present
		$has_fields = isset( $_POST['fp_seo_performance_metabox_present'] ) ||
					  isset( $_POST['fp_seo_title_sent'] ) ||
					  isset( $_POST['fp_seo_meta_description_sent'] ) ||
					  isset( $_POST['fp_seo_title'] ) ||
					  isset( $_POST['fp_seo_meta_description'] );

		if ( ! $has_fields ) {
			return false;
		}

		return true;
	}
}

