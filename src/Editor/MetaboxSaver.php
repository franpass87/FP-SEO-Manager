<?php
/**
 * Handles saving of SEO metabox fields.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Editor\Traits\MetaFieldSaverTrait;
use FP\SEO\Utils\Logger;
use function add_post_meta;
use function clean_post_cache;
use function current_user_can;
use function delete_post_meta;
use function esc_url_raw;
use function get_post_meta;
use function get_post_type;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_title;
use function trim;
use function update_post_meta;
use function update_post_meta_cache;
use function wp_is_post_revision;
use function wp_unslash;
use function wp_update_post;

/**
 * Handles saving of SEO metabox fields.
 */
class MetaboxSaver {
	use MetaFieldSaverTrait;
	/**
	 * Meta key for SEO title.
	 */
	private const META_SEO_TITLE = '_fp_seo_title';

	/**
	 * Meta key for SEO meta description.
	 */
	private const META_SEO_DESCRIPTION = '_fp_seo_meta_description';

	/**
	 * Meta key for focus keyword.
	 */
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';

	/**
	 * Meta key for secondary keywords.
	 */
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Meta key for canonical URL override.
	 */
	private const META_CANONICAL = '_fp_seo_canonical';

	/**
	 * Meta key for schema type.
	 */
	private const META_SCHEMA_TYPE = '_fp_seo_schema_type';

	/**
	 * Meta key for exclude flag.
	 */
	private const META_EXCLUDE = '_fp_seo_performance_exclude';

	/**
	 * Meta key for Q&A pairs.
	 */
	private const META_QA_PAIRS = '_fp_seo_qa_pairs';

	/**
	 * Nonce field name.
	 */
	private const NONCE_FIELD = 'fp_seo_performance_nonce';

	/**
	 * Nonce action name.
	 */
	private const NONCE_ACTION = 'fp_seo_performance_save';

	/**
	 * Static flag to prevent multiple saves in the same request.
	 *
	 * @var array
	 */
	private static $saved_posts = array();

	/**
	 * Save all SEO fields for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if saved successfully, false otherwise.
	 */
	public function save_all_fields( int $post_id ): bool {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( '[FP-SEO] MetaboxSaver::save_all_fields - Entry point, post_id: ' . $post_id );
			if ( isset( $_POST ) && is_array( $_POST ) ) {
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - $_POST keys: ' . implode( ', ', array_keys( $_POST ) ) );
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - fp_seo_qa_pairs_data in $_POST: ' . ( isset( $_POST['fp_seo_qa_pairs_data'] ) ? 'YES, length: ' . strlen( $_POST['fp_seo_qa_pairs_data'] ) : 'NO' ) );
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - fp_seo_performance_metabox_present in $_POST: ' . ( isset( $_POST['fp_seo_performance_metabox_present'] ) ? 'YES' : 'NO' ) );
			} else {
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - $_POST is not set or not an array' );
			}
		}

		if ( ! $post_id || $post_id <= 0 ) {
			if ( $debug ) {
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - Invalid post_id: ' . $post_id );
			}
			return false;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			if ( $debug ) {
				error_log( '[FP-SEO] MetaboxSaver::save_all_fields - Could not get post type for post_id: ' . $post_id );
			}
			return false;
		}
		
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'MetaboxSaver::save_all_fields skipped - unsupported post type', array(
					'post_id' => $post_id,
					'post_type' => $post_type,
					'supported_types' => $supported_types,
				) );
			}
			return false; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'MetaboxSaver::save_all_fields called', array(
				'post_id' => $post_id,
				'post_type' => $post_type,
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			) );
		}
		
		// Verify nonce before processing any SEO fields (CSRF protection)
		$nonce_value = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce_value, 'fp_seo_performance_meta' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'MetaboxSaver::save_all_fields - nonce verification failed', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Check if there are SEO fields being saved
		// If no SEO fields, simply return - WordPress will handle the save normally
		$has_seo_fields_in_post = isset( $_POST['fp_seo_performance_metabox_present'] ) || 
								  isset( $_POST['fp_seo_title_sent'] ) || 
								  isset( $_POST['fp_seo_meta_description_sent'] ) ||
								  isset( $_POST['fp_seo_title'] ) ||
								  isset( $_POST['fp_seo_meta_description'] ) ||
								  isset( $_POST['fp_seo_qa_pairs_data'] );
		
		// If no SEO fields, simply return - WordPress will handle the save normally
		if ( ! $has_seo_fields_in_post ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'MetaboxSaver::save_all_fields - No SEO fields, allowing WordPress to save normally' );
			}
			return false; // No SEO fields to process, but allow WordPress to save the post
		}
		
		
		
		
		// Prevent multiple saves in the same request
		if ( isset( self::$saved_posts[ $post_id ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'save_all_fields already called, skipping', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Basic validation
		if ( ! $this->should_save( $post_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'should_save returned false', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Check if metabox fields are present
		$has_fields = $this->has_metabox_fields();
		if ( ! $has_fields ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$post_keys = array_keys( $_POST ?? array() );
				$fp_keys = array_filter( $post_keys, function( $key ) {
					return strpos( $key, 'fp_seo' ) === 0;
				} );
				Logger::debug( 'No metabox fields found - preserving existing values', array(
					'post_id' => $post_id,
					'post_keys_count' => count( $post_keys ),
					'fp_seo_keys' => array_values( $fp_keys ),
					'rest_request' => defined( 'REST_REQUEST' ) && REST_REQUEST,
				) );
			}
			
			// IMPORTANTE: In Gutenberg/REST API, i campi potrebbero non essere in $_POST
			// Verifica se siamo in un contesto REST API
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'REST API context detected - will try to save from meta fields', array( 'post_id' => $post_id ) );
				}
				// In REST API, i campi potrebbero essere già salvati o essere in meta
				// Procedi comunque con il salvataggio se ci sono valori da salvare
			} else {
				// CRITICAL: Don't save if metabox is not present, but DON'T delete existing values
				// This preserves SEO fields when saving from other metaboxes (like Page Header Settings)
				// The existing values will remain intact
				return false;
			}
		}

		// Mark as saved early to prevent multiple saves (ma solo se non è già stato salvato)
		if ( ! isset( self::$saved_posts[ $post_id ] ) ) {
			self::$saved_posts[ $post_id ] = true;
		}

		// Save all fields
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Starting to save fields', array( 'post_id' => $post_id ) );
		}
		
		$this->save_title( $post_id );
		$this->save_description( $post_id );
		$this->save_slug( $post_id );
		$this->save_excerpt( $post_id );
		$this->save_keywords( $post_id );
		$this->save_canonical( $post_id );
		$this->save_schema_type( $post_id );
		$this->save_exclude_flag( $post_id );
		$this->save_qa_pairs( $post_id );

		// CRITICAL: Selective cache clearing to prevent interference with featured image saving
		// WordPress saves featured image (_thumbnail_id) during save_post hook
		// We must NOT clear the entire post_meta cache as it can interfere with _thumbnail_id
		// Instead, we only clear cache for our specific meta keys
		
		// CRITICAL: Selective cache clearing to prevent interference with featured image saving
		// WordPress saves featured image (_thumbnail_id) during save_post hook or via AJAX
		// We must NOT clear any cache that could interfere with _thumbnail_id
		// CRITICAL: Do NOT clear any cache during save_post hook
		// WordPress saves featured image (_thumbnail_id) during save_post hook
		// Clearing cache (even selectively) can interfere with WordPress core saving _thumbnail_id
		// We completely skip cache clearing to ensure zero interference with featured image
		
		// Cache will be naturally refreshed on next access via WordPress core mechanisms
		// No manual cache clearing needed - WordPress handles this automatically

		// Clear schema cache when schema type changes
		$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
		delete_transient( $cache_key );
		// Also clear via CacheHelper if available
		if ( class_exists( '\FP\SEO\Utils\CacheHelper' ) ) {
			\FP\SEO\Utils\CacheHelper::forget( $cache_key, 'default' );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Finished saving fields', array( 'post_id' => $post_id ) );
		}
		
		return true;
	}

	/**
	 * Check if we should save for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if should save, false otherwise.
	 */
	private function should_save( int $post_id ): bool {
// Skip autosave (ma solo se non è un autosave esplicito con campi SEO)
		$has_seo_fields = isset( $_POST['fp_seo_performance_metabox_present'] ) || 
						  isset( $_POST['fp_seo_title_sent'] ) || 
						  isset( $_POST['fp_seo_meta_description_sent'] );
		
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE && ! $has_seo_fields ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'should_save=false - DOING_AUTOSAVE (no SEO fields)', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Skip revision
		if ( wp_is_post_revision( $post_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'should_save=false - is revision', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Skip AJAX heartbeat (ma solo se non ci sono campi SEO)
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && 'heartbeat' === $_POST['action'] && ! $has_seo_fields ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'should_save=false - heartbeat (no SEO fields)', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		// Check user capability
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'should_save=false - no capability', array( 'post_id' => $post_id ) );
			}
			return false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'should_save=true - all checks passed', array( 'post_id' => $post_id ) );
		}
		
		return true;
	}

	/**
	 * Check if metabox fields are present in POST.
	 *
	 * @return bool True if metabox fields are present, false otherwise.
	 */
	private function has_metabox_fields(): bool {
		// APPROCCIO PIÙ PERMISSIVO: Se siamo in un contesto di salvataggio post, prova comunque
		// Verifica se siamo in un salvataggio post (non autosave, non revision)
		$is_post_save = isset( $_POST['post_ID'] ) || isset( $_POST['post_id'] ) || ( isset( $_POST['action'] ) && $_POST['action'] === 'editpost' );
		
		// Check if metabox is present via hidden field (most reliable)
		if ( isset( $_POST['fp_seo_performance_metabox_present'] ) && $_POST['fp_seo_performance_metabox_present'] === '1' ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox present via hidden field' );
			}
			return true;
		}

		// Check if any SEO field is present (fallback)
		// IMPORTANTE: Verifica anche i campi "_sent" che sono sempre presenti
		$has_title = isset( $_POST['fp_seo_title'] ) || isset( $_POST['fp_seo_title_sent'] );
		$has_desc = isset( $_POST['fp_seo_meta_description'] ) || isset( $_POST['fp_seo_meta_description_sent'] );
		$has_slug = isset( $_POST['fp_seo_slug'] );
		$has_excerpt = isset( $_POST['fp_seo_excerpt'] );
		$has_focus = isset( $_POST['fp_seo_focus_keyword'] );
		$has_secondary = isset( $_POST['fp_seo_secondary_keywords'] );
		$has_canonical = isset( $_POST['fp_seo_canonical'] ) || isset( $_POST['fp_seo_canonical_sent'] );
		$has_schema_type = isset( $_POST['fp_seo_schema_type'] ) || isset( $_POST['fp_seo_schema_type_sent'] );
		$has_qa_pairs = isset( $_POST['fp_seo_qa_pairs_data'] );
		
		$has_any = $has_title || $has_desc || $has_slug || $has_excerpt || $has_focus || $has_secondary || $has_canonical || $has_schema_type || $has_qa_pairs;
		
		if ( $has_any ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox fields detected', array(
					'has_title' => $has_title,
					'has_desc' => $has_desc,
					'has_slug' => $has_slug,
					'has_excerpt' => $has_excerpt,
					'has_focus' => $has_focus,
					'has_secondary' => $has_secondary,
					'has_canonical' => $has_canonical,
				) );
			}
			return true;
		}
		
		// NUOVO: Se siamo in un salvataggio post ma non abbiamo trovato campi, 
		// potrebbe essere che i campi non sono stati inviati. Prova comunque se è un edit post.
		if ( $is_post_save && ! defined( 'DOING_AUTOSAVE' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Post save detected but no SEO fields found', array(
					'is_post_save' => true,
					'doing_autosave' => false,
				) );
			}
			// Controlla se il metabox è stato renderizzato guardando se esiste almeno un campo visibile
			// Questo è un fallback - meglio avere campi espliciti
			return false; // Non salvare se non ci sono campi - ma logga per debug
		}
		
		// Log dettagliato per debug se non trova campi
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$post_keys = array_keys( $_POST ?? array() );
			$fp_keys = array_filter( $post_keys, function( $key ) {
				return strpos( $key, 'fp_seo' ) === 0;
			} );
			Logger::debug( 'No metabox fields found', array(
				'fp_seo_keys' => array_values( $fp_keys ),
				'post_keys_count' => count( $post_keys ),
			) );
		}
		
		return $has_any;
	}

	/**
	 * Save SEO title.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_title( int $post_id ): void {
		$presence = $this->check_field_presence( 'fp_seo_title', 'fp_seo_title_sent' );
		
		if ( ! $presence['field_sent'] && ! $presence['metabox_present'] ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'save_title skipped - field not sent and metabox not present', array( 'post_id' => $post_id ) );
			}
			return;
		}

		$title_raw = $_POST['fp_seo_title'] ?? '';
		
		// Handle array (if duplicates exist - può succedere con alcuni plugin)
		if ( is_array( $title_raw ) ) {
			$title_raw = array_filter( $title_raw );
			$title_raw = ! empty( $title_raw ) ? end( $title_raw ) : '';
		}

		$title = trim( sanitize_text_field( wp_unslash( (string) $title_raw ) ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'save_title processing', array(
				'post_id' => $post_id,
				'title_length' => strlen( (string) $title_raw ),
				'title_preview' => substr( $title, 0, 50 ),
			) );
		}

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
	 */
	private function save_description( int $post_id ): void {
		$presence = $this->check_field_presence( 'fp_seo_meta_description', 'fp_seo_meta_description_sent' );
		
		if ( ! $presence['field_sent'] && ! $presence['metabox_present'] ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'save_description skipped - field not sent and metabox not present', array( 'post_id' => $post_id ) );
			}
			return;
		}

		$desc_raw = $_POST['fp_seo_meta_description'] ?? '';
		
		// Handle array (if duplicates exist - può succedere con alcuni plugin)
		if ( is_array( $desc_raw ) ) {
			$desc_raw = array_filter( $desc_raw );
			$desc_raw = ! empty( $desc_raw ) ? end( $desc_raw ) : '';
		}

		$description = trim( sanitize_textarea_field( wp_unslash( (string) $desc_raw ) ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'save_description processing', array(
				'post_id' => $post_id,
				'description_length' => strlen( (string) $desc_raw ),
				'description_preview' => substr( $description, 0, 50 ),
			) );
		}

		if ( '' !== $description ) {
			$this->save_meta_field( $post_id, self::META_SEO_DESCRIPTION, $description, 'Description' );
		} else {
			$field_explicitly_empty = $this->is_field_explicitly_empty( 'fp_seo_meta_description', 'fp_seo_meta_description_sent' );
			$this->delete_meta_field_if_empty( $post_id, self::META_SEO_DESCRIPTION, 'Description', $field_explicitly_empty, $presence['metabox_present'] );
		}
	}

	/**
	 * Save slug (post_name).
	 *
	 * @param int $post_id Post ID.
	 */
	/**
	 * Save slug - CRITICAL: Use direct DB update to avoid triggering wp_update_post hooks.
	 * wp_update_post triggers save_post and other hooks that can cause auto-draft creation.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_slug( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_slug'] ) ) {
			return;
		}

		$slug = trim( sanitize_title( wp_unslash( (string) $_POST['fp_seo_slug'] ) ) );

		if ( '' !== $slug ) {
			// CRITICAL: Use direct DB update instead of wp_update_post
			// wp_update_post triggers save_post and other hooks that can cause auto-draft creation
			global $wpdb;
			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_name' => $slug ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			if ( $updated !== false ) {
				// CRITICAL: Do NOT clear cache here - can interfere with featured image saving
				// Cache will be naturally refreshed on next access via WordPress core mechanisms
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'FP SEO: Slug saved via direct DB update', array(
						'post_id' => $post_id,
						'slug' => $slug,
					) );
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Failed to update slug via direct DB update', array(
						'post_id' => $post_id,
						'db_error' => $wpdb->last_error,
					) );
				}
			}
		}
	}

	/**
	 * Save excerpt (post_excerpt).
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_excerpt( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_excerpt'] ) ) {
			return;
		}

		$excerpt = trim( sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) ) );

		// CRITICAL: Use direct DB update instead of wp_update_post
		// wp_update_post triggers save_post and other hooks that can cause auto-draft creation
		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_excerpt' => $excerpt ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( $updated !== false ) {
			// CRITICAL: Do NOT clear cache here - can interfere with featured image saving
			// Cache will be naturally refreshed on next access via WordPress core mechanisms
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: Excerpt saved via direct DB update', array(
					'post_id' => $post_id,
					'excerpt_length' => strlen( $excerpt ),
				) );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Failed to update excerpt via direct DB update', array(
					'post_id' => $post_id,
					'db_error' => $wpdb->last_error,
				) );
			}
		}
	}

	/**
	 * Save keywords (focus and secondary).
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_keywords( int $post_id ): void {
		// Save focus keyword
		if ( isset( $_POST['fp_seo_focus_keyword'] ) ) {
			$focus_keyword = trim( sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_focus_keyword'] ) ) );

			if ( '' !== $focus_keyword ) {
				update_post_meta( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
			} else {
				delete_post_meta( $post_id, self::META_FOCUS_KEYWORD );
			}
		}

		// Save secondary keywords
		if ( isset( $_POST['fp_seo_secondary_keywords'] ) ) {
			$secondary_keywords = trim( sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_secondary_keywords'] ) ) );

			if ( '' !== $secondary_keywords ) {
				$keywords_array = array_map( 'trim', explode( ',', $secondary_keywords ) );
				$keywords_array = array_filter( $keywords_array );
				update_post_meta( $post_id, self::META_SECONDARY_KEYWORDS, $keywords_array );
			} else {
				delete_post_meta( $post_id, self::META_SECONDARY_KEYWORDS );
			}
		}
	}

	/**
	 * Save canonical URL override.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_canonical( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_canonical'] ) && ! isset( $_POST['fp_seo_canonical_sent'] ) ) {
			return;
		}

		$canonical = isset( $_POST['fp_seo_canonical'] )
			? trim( esc_url_raw( wp_unslash( (string) $_POST['fp_seo_canonical'] ) ) )
			: '';

		if ( '' !== $canonical ) {
			update_post_meta( $post_id, self::META_CANONICAL, $canonical );
			return;
		}

		delete_post_meta( $post_id, self::META_CANONICAL );
	}

	/**
	 * Save schema type.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_schema_type( int $post_id ): void {
		if ( ! isset( $_POST['fp_seo_schema_type'] ) && ! isset( $_POST['fp_seo_schema_type_sent'] ) ) {
			return;
		}

		$schema_type = isset( $_POST['fp_seo_schema_type'] ) 
			? trim( sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_schema_type'] ) ) )
			: '';

		// Valid schema types
		$valid_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'ContactPage', 'AboutPage', 'Product', 'TouristTrip', 'Event', 'TouristAttraction', 'Service', 'Offer' );
		
		if ( '' !== $schema_type && in_array( $schema_type, $valid_types, true ) ) {
			update_post_meta( $post_id, self::META_SCHEMA_TYPE, $schema_type );
		} else {
			// If explicitly sent but empty, use default based on post type
			if ( isset( $_POST['fp_seo_schema_type_sent'] ) ) {
				$post_type = get_post_type( $post_id );
				$default = 'WebPage';
				if ( $post_type === 'post' ) {
					$default = 'Article';
				} elseif ( $post_type === 'product' ) {
					$default = 'Product';
				} elseif ( $post_type === 'fp_experience' ) {
					$default = 'TouristTrip';
				}
				update_post_meta( $post_id, self::META_SCHEMA_TYPE, $default );
			}
		}
	}

	/**
	 * Save exclude flag.
	 *
	 * @param int $post_id Post ID.
	 */
	private function save_exclude_flag( int $post_id ): void {
		$exclude = isset( $_POST['fp_seo_performance_exclude'] ) && '1' === $_POST['fp_seo_performance_exclude'];
		
		if ( $exclude ) {
			update_post_meta( $post_id, self::META_EXCLUDE, '1' );
		} else {
			delete_post_meta( $post_id, self::META_EXCLUDE );
		}
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
			error_log( '[FP-SEO] MetaboxSaver::save_qa_pairs - Entry, post_id: ' . $post_id . ', data length: ' . strlen( $qa_pairs_data ) );
		}

		$qa_pairs = json_decode( $qa_pairs_data, true );

		if ( ! is_array( $qa_pairs ) ) {
			if ( $debug ) {
				Logger::debug( 'MetaboxSaver::save_qa_pairs - Invalid Q&A pairs data', array(
					'post_id'        => $post_id,
					'json_error'     => json_last_error_msg(),
				) );
			}
			return;
		}

		if ( $debug ) {
			error_log( '[FP-SEO] MetaboxSaver::save_qa_pairs - Parsed array count: ' . count( $qa_pairs ) );
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

		// Save Q&A pairs
		if ( ! empty( $sanitized_pairs ) ) {
			update_post_meta( $post_id, self::META_QA_PAIRS, $sanitized_pairs );

			if ( class_exists( '\FP\SEO\Admin\Helpers\CacheHelper' ) ) {
				\FP\SEO\Admin\Helpers\CacheHelper::clear_schema_cache( $post_id );
			}

			if ( $debug ) {
				Logger::debug( 'MetaboxSaver::save_qa_pairs - Saved Q&A pairs', array(
					'post_id' => $post_id,
					'count'   => count( $sanitized_pairs ),
				) );
			}
		} else {
			delete_post_meta( $post_id, self::META_QA_PAIRS );

			if ( class_exists( '\FP\SEO\Admin\Helpers\CacheHelper' ) ) {
				\FP\SEO\Admin\Helpers\CacheHelper::clear_schema_cache( $post_id );
			}
		}
	}
}

