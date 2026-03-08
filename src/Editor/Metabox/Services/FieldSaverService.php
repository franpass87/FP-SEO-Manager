<?php
/**
 * Unified field saver service.
 *
 * @package FP\SEO\Editor\Metabox\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Metabox\Services;

use FP\SEO\Editor\Metabox\Contracts\FieldSaverServiceInterface;
use FP\SEO\Editor\Traits\MetaFieldSaverTrait;
use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Utils\PostTypes;
use function current_user_can;
use function get_post_type;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function trim;
use function wp_is_post_revision;
use function wp_unslash;

/**
 * Unified field saver service.
 *
 * Consolidates MetaboxSaver and SeoFieldsSaver into a single service.
 */
class FieldSaverService implements FieldSaverServiceInterface {
	use MetaFieldSaverTrait;

	/**
	 * Meta keys for SEO fields.
	 */
	private const META_SEO_TITLE = '_fp_seo_title';
	private const META_SEO_DESCRIPTION = '_fp_seo_meta_description';
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';
	private const META_EXCLUDE = '_fp_seo_performance_exclude';
	private const META_CANONICAL = '_fp_seo_canonical';
	private const META_ROBOTS = '_fp_seo_robots';

	/**
	 * Post repository.
	 *
	 * @var PostRepositoryInterface
	 */
	private PostRepositoryInterface $post_repository;

	/**
	 * Post meta repository.
	 *
	 * @var PostMetaRepositoryInterface
	 */
	private PostMetaRepositoryInterface $post_meta_repository;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Static flag to prevent multiple saves in the same request.
	 *
	 * @var array<int, bool>
	 */
	private static array $saved_posts = array();

	/**
	 * Constructor.
	 *
	 * @param PostRepositoryInterface     $post_repository     Post repository.
	 * @param PostMetaRepositoryInterface $post_meta_repository Post meta repository.
	 * @param LoggerInterface             $logger              Logger instance.
	 */
	public function __construct(
		PostRepositoryInterface $post_repository,
		PostMetaRepositoryInterface $post_meta_repository,
		LoggerInterface $logger
	) {
		$this->post_repository      = $post_repository;
		$this->post_meta_repository = $post_meta_repository;
		$this->logger               = $logger;
	}

	/**
	 * Save all SEO fields for a post (from metabox).
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if saved successfully, false otherwise.
	 */
	public function save_all_fields( int $post_id ): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[FP-SEO] FieldSaverService::save_all_fields - Entry, post_id: ' . $post_id );
			error_log( '[FP-SEO] FieldSaverService::save_all_fields - $_POST action: ' . ( $_POST['action'] ?? 'none' ) );
			error_log( '[FP-SEO] FieldSaverService::save_all_fields - fp_seo_qa_pairs_data: ' . ( isset( $_POST['fp_seo_qa_pairs_data'] ) ? 'YES, length: ' . strlen( $_POST['fp_seo_qa_pairs_data'] ) : 'NO' ) );
		}
		
		// Check post type first
		$post_type = get_post_type( $post_id );
		$supported_types = PostTypes::analyzable();

		if ( ! in_array( $post_type, $supported_types, true ) ) {
			$this->logger->debug( 'FieldSaverService::save_all_fields skipped - unsupported post type', array(
				'post_id'        => $post_id,
				'post_type'      => $post_type,
				'supported_types' => $supported_types,
			) );
			return false;
		}

		// Prevent multiple saves in the same request
		if ( isset( self::$saved_posts[ $post_id ] ) ) {
			$this->logger->debug( 'save_all_fields already called, skipping', array( 'post_id' => $post_id ) );
			return false;
		}

		// Basic validation
		if ( ! $this->should_save( $post_id ) ) {
			$this->logger->debug( 'should_save returned false', array( 'post_id' => $post_id ) );
			return false;
		}

		// Check if metabox fields are present
		$has_fields = $this->has_metabox_fields();
		if ( ! $has_fields ) {
			// In REST API context, fields might be in meta already
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$this->logger->debug( 'REST API context detected - will try to save from meta fields', array( 'post_id' => $post_id ) );
			} else {
				// Don't save if metabox is not present, but preserve existing values
				return false;
			}
		}

		// Mark as saved early to prevent multiple saves
		self::$saved_posts[ $post_id ] = true;

		// Save all fields
		$this->logger->debug( 'Starting to save fields', array( 'post_id' => $post_id ) );

		$this->save_title( $post_id );
		$this->save_description( $post_id );
		$this->save_slug( $post_id );
		$this->save_excerpt( $post_id );
		$this->save_keywords( $post_id );
		$this->save_exclude_flag( $post_id );
		$this->save_qa_pairs( $post_id );

		// Clear cache after saving all fields
		$this->clear_full_cache( $post_id );

		$this->logger->debug( 'Finished saving fields', array( 'post_id' => $post_id ) );

		return true;
	}

	/**
	 * Save SEO fields from POST data (for AJAX requests).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, bool> Saved fields data.
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

		$this->logger->debug( 'FieldSaverService - Saving fields from POST', array(
			'post_id'           => $post_id,
			'has_title'         => ! empty( $seo_title ),
			'has_description'   => ! empty( $meta_description ),
			'has_focus_keyword' => ! empty( $focus_keyword ),
			'has_excerpt'       => ! empty( $excerpt ),
		) );

		// Save meta fields
		$saved['title'] = $this->save_meta_field_value( $post_id, self::META_SEO_TITLE, $seo_title );
		$saved['description'] = $this->save_meta_field_value( $post_id, self::META_SEO_DESCRIPTION, $meta_description );
		$saved['focus_keyword'] = $this->save_meta_field_value( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
		$saved['secondary_keywords'] = $this->save_secondary_keywords( $post_id, $secondary_keywords );
		$saved['canonical'] = $this->save_meta_field_value( $post_id, self::META_CANONICAL, $canonical );
		$saved['robots'] = $this->save_meta_field_value( $post_id, self::META_ROBOTS, $robots );

		// Save excerpt using repository
		$saved['excerpt'] = $this->save_excerpt_field( $post_id, $excerpt );

		// Clear cache
		$this->clear_full_cache( $post_id );

		return $saved;
	}

	/**
	 * Check if we should save for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if should save, false otherwise.
	 */
	private function should_save( int $post_id ): bool {
		$has_seo_fields = isset( $_POST['fp_seo_performance_metabox_present'] ) ||
						  isset( $_POST['fp_seo_title_sent'] ) ||
						  isset( $_POST['fp_seo_meta_description_sent'] ) ||
						  isset( $_POST['fp_seo_qa_pairs_data'] );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && ! $has_seo_fields ) {
			$this->logger->debug( 'should_save=false - DOING_AUTOSAVE (no SEO fields)', array( 'post_id' => $post_id ) );
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			$this->logger->debug( 'should_save=false - is revision', array( 'post_id' => $post_id ) );
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'] && ! $has_seo_fields ) {
			$this->logger->debug( 'should_save=false - heartbeat (no SEO fields)', array( 'post_id' => $post_id ) );
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			$this->logger->debug( 'should_save=false - no capability', array( 'post_id' => $post_id ) );
			return false;
		}

		$this->logger->debug( 'should_save=true - all checks passed', array( 'post_id' => $post_id ) );

		return true;
	}

	/**
	 * Check if metabox fields are present in POST.
	 *
	 * @return bool True if metabox fields are present, false otherwise.
	 */
	private function has_metabox_fields(): bool {
		if ( isset( $_POST['fp_seo_performance_metabox_present'] ) && $_POST['fp_seo_performance_metabox_present'] === '1' ) {
			return true;
		}

		$has_title = isset( $_POST['fp_seo_title'] ) || isset( $_POST['fp_seo_title_sent'] );
		$has_desc = isset( $_POST['fp_seo_meta_description'] ) || isset( $_POST['fp_seo_meta_description_sent'] );
		$has_slug = isset( $_POST['fp_seo_slug'] );
		$has_excerpt = isset( $_POST['fp_seo_excerpt'] );
		$has_focus = isset( $_POST['fp_seo_focus_keyword'] );
		$has_secondary = isset( $_POST['fp_seo_secondary_keywords'] );
		$has_qa_pairs = isset( $_POST['fp_seo_qa_pairs_data'] );

		return $has_title || $has_desc || $has_slug || $has_excerpt || $has_focus || $has_secondary || $has_qa_pairs;
	}

	/**
	 * Save SEO title.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_title( int $post_id ): void {
		$presence = $this->check_field_presence( 'fp_seo_title', 'fp_seo_title_sent' );

		if ( ! $presence['field_sent'] && ! $presence['metabox_present'] ) {
			$this->logger->debug( 'save_title skipped - field not sent and metabox not present', array( 'post_id' => $post_id ) );
			return;
		}

		$title_raw = $_POST['fp_seo_title'] ?? '';

		// Handle array (if duplicates exist)
		if ( is_array( $title_raw ) ) {
			$title_raw = array_filter( $title_raw );
			$title_raw = ! empty( $title_raw ) ? end( $title_raw ) : '';
		}

		$title = trim( sanitize_text_field( wp_unslash( (string) $title_raw ) ) );

		if ( '' !== $title ) {
			$this->save_meta_field( $post_id, self::META_SEO_TITLE, $title, 'Title' );
		} else {
			$field_explicitly_empty = $this->is_field_explicitly_empty( 'fp_seo_title', 'fp_seo_title_sent' );
			$this->delete_meta_field_if_empty( $post_id, self::META_SEO_TITLE, 'Title', $field_explicitly_empty, $presence['metabox_present'] );
		}
	}

	/**
	 * Save meta description.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_description( int $post_id ): void {
		$presence = $this->check_field_presence( 'fp_seo_meta_description', 'fp_seo_meta_description_sent' );

		if ( ! $presence['field_sent'] && ! $presence['metabox_present'] ) {
			$this->logger->debug( 'save_description skipped - field not sent and metabox not present', array( 'post_id' => $post_id ) );
			return;
		}

		$desc_raw = $_POST['fp_seo_meta_description'] ?? '';

		// Handle array (if duplicates exist)
		if ( is_array( $desc_raw ) ) {
			$desc_raw = array_filter( $desc_raw );
			$desc_raw = ! empty( $desc_raw ) ? end( $desc_raw ) : '';
		}

		$description = trim( sanitize_textarea_field( wp_unslash( (string) $desc_raw ) ) );

		if ( '' !== $description ) {
			$this->save_meta_field( $post_id, self::META_SEO_DESCRIPTION, $description, 'Description' );
		} else {
			$field_explicitly_empty = $this->is_field_explicitly_empty( 'fp_seo_meta_description', 'fp_seo_meta_description_sent' );
			$this->delete_meta_field_if_empty( $post_id, self::META_SEO_DESCRIPTION, 'Description', $field_explicitly_empty, $presence['metabox_present'] );
		}
	}

	/**
	 * Save slug (post_name) using repository.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_slug( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_slug'] ) ) {
			return;
		}

		$slug = trim( sanitize_title( wp_unslash( (string) $_POST['fp_seo_slug'] ) ) );

		if ( '' !== $slug ) {
			// Use repository to update post
			$this->post_repository->update( $post_id, array( 'post_name' => $slug ) );
			$this->logger->debug( 'Slug saved via repository', array(
				'post_id' => $post_id,
				'slug'    => $slug,
			) );
		}
	}

	/**
	 * Save excerpt using repository.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_excerpt( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_excerpt'] ) ) {
			return;
		}

		$excerpt = trim( sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) ) );

		// Use repository to update post
		$this->post_repository->update( $post_id, array( 'post_excerpt' => $excerpt ) );
		$this->logger->debug( 'Excerpt saved via repository', array(
			'post_id'       => $post_id,
			'excerpt_length' => strlen( $excerpt ),
		) );
	}

	/**
	 * Save excerpt field (for AJAX requests).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $excerpt Excerpt value.
	 * @return bool Whether excerpt was saved.
	 */
	private function save_excerpt_field( int $post_id, string $excerpt ): bool {
		if ( '' === $excerpt ) {
			return false;
		}

		$this->post_repository->update( $post_id, array( 'post_excerpt' => $excerpt ) );
		return true;
	}

	/**
	 * Save keywords (focus and secondary).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_keywords( int $post_id ): void {
		// Save focus keyword
		if ( isset( $_POST['fp_seo_focus_keyword'] ) ) {
			$focus_keyword = trim( sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_focus_keyword'] ) ) );

			if ( '' !== $focus_keyword ) {
				$this->post_meta_repository->update( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
			} else {
				$this->post_meta_repository->delete( $post_id, self::META_FOCUS_KEYWORD );
			}
		}

		// Save secondary keywords
		if ( isset( $_POST['fp_seo_secondary_keywords'] ) ) {
			$secondary_keywords = trim( sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_secondary_keywords'] ) ) );

			if ( '' !== $secondary_keywords ) {
				$keywords_array = array_map( 'trim', explode( ',', $secondary_keywords ) );
				$keywords_array = array_filter( $keywords_array );
				$this->post_meta_repository->update( $post_id, self::META_SECONDARY_KEYWORDS, $keywords_array );
			} else {
				$this->post_meta_repository->delete( $post_id, self::META_SECONDARY_KEYWORDS );
			}
		}
	}

	/**
	 * Save secondary keywords (for AJAX requests).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $secondary_keywords Secondary keywords value.
	 * @return bool Whether keywords were saved.
	 */
	private function save_secondary_keywords( int $post_id, string $secondary_keywords ): bool {
		if ( '' === $secondary_keywords ) {
			$this->post_meta_repository->delete( $post_id, self::META_SECONDARY_KEYWORDS );
			return false;
		}

		$keywords_array = array_map( 'trim', explode( ',', $secondary_keywords ) );
		$keywords_array = array_filter( $keywords_array );
		$this->post_meta_repository->update( $post_id, self::META_SECONDARY_KEYWORDS, $keywords_array );
		return true;
	}

	/**
	 * Save exclude flag.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_exclude_flag( int $post_id ): void {
		$exclude = isset( $_POST['fp_seo_performance_exclude'] ) && '1' === $_POST['fp_seo_performance_exclude'];

		if ( $exclude ) {
			$this->post_meta_repository->update( $post_id, self::META_EXCLUDE, '1' );
		} else {
			$this->post_meta_repository->delete( $post_id, self::META_EXCLUDE );
		}
	}

	/**
	 * Save a meta field value (for AJAX requests).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $value Field value.
	 * @return bool Whether the field was saved.
	 */
	private function save_meta_field_value( int $post_id, string $meta_key, string $value ): bool {
		if ( '' !== $value ) {
			$this->post_meta_repository->update( $post_id, $meta_key, $value );
			return true;
		} else {
			$this->post_meta_repository->delete( $post_id, $meta_key );
			return false;
		}
	}

	/**
	 * Get field value from POST data (for AJAX requests).
	 *
	 * @param string $primary_key  Primary field key.
	 * @param string $fallback_key Fallback field key.
	 * @param string $type         Field type ('text' or 'textarea').
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
	 * Save Q&A pairs.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_qa_pairs( int $post_id ): void {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! isset( $_POST['fp_seo_qa_pairs_data'] ) ) {
			return;
		}

		$qa_pairs_data = wp_unslash( $_POST['fp_seo_qa_pairs_data'] );

		if ( $debug ) {
			error_log( '[FP-SEO] FieldSaverService::save_qa_pairs - Entry, post_id: ' . $post_id . ', data length: ' . strlen( $qa_pairs_data ) );
		}

		$qa_pairs = json_decode( $qa_pairs_data, true );

		if ( ! is_array( $qa_pairs ) ) {
			$this->logger->debug( 'FieldSaverService::save_qa_pairs - Invalid Q&A pairs data', array(
				'post_id'    => $post_id,
				'json_error' => json_last_error_msg(),
			) );
			return;
		}

		if ( $debug ) {
			error_log( '[FP-SEO] FieldSaverService::save_qa_pairs - Parsed array count: ' . count( $qa_pairs ) );
		}

		// Sanitize and validate each Q&A pair
		$sanitized_pairs = array();
		foreach ( $qa_pairs as $pair ) {
			if ( ! is_array( $pair ) ) {
				continue;
			}

			$question = isset( $pair['question'] ) ? sanitize_text_field( $pair['question'] ) : '';
			$answer = isset( $pair['answer'] ) ? sanitize_textarea_field( $pair['answer'] ) : '';

			// Only add pairs with both question and answer
			if ( ! empty( $question ) && ! empty( $answer ) ) {
				$sanitized_pairs[] = array(
					'question' => $question,
					'answer' => $answer,
					'confidence' => isset( $pair['confidence'] ) ? floatval( $pair['confidence'] ) : 1.0,
					'question_type' => isset( $pair['question_type'] ) ? sanitize_text_field( $pair['question_type'] ) : 'manual',
					'keywords' => isset( $pair['keywords'] ) && is_array( $pair['keywords'] ) 
						? array_map( 'sanitize_text_field', $pair['keywords'] ) 
						: array(),
				);
			}
		}

		// Save Q&A pairs using post meta repository
		$meta_key = '_fp_seo_qa_pairs';
		if ( ! empty( $sanitized_pairs ) ) {
			$this->post_meta_repository->update( $post_id, $meta_key, $sanitized_pairs );
			$this->logger->debug( 'FieldSaverService::save_qa_pairs - Saved Q&A pairs', array(
				'post_id' => $post_id,
				'count'   => count( $sanitized_pairs ),
			) );
		} else {
			$this->post_meta_repository->delete( $post_id, $meta_key );
		}
	}

	/**
	 * Clear full cache for post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function clear_full_cache( int $post_id ): void {
		// CRITICAL: Cache clearing completely disabled to prevent interference with featured image (_thumbnail_id) saving
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache during save_post can interfere with WordPress core saving _thumbnail_id
		// This includes wp_cache_delete( $post_id, 'post_meta' ) which would delete _thumbnail_id from cache
		return; // Do nothing - WordPress will refresh cache naturally
	}
}















