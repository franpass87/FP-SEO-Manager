<?php
/**
 * Trait for common meta field saving logic.
 *
 * @package FP\SEO\Editor\Traits
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Traits;

use FP\SEO\Utils\Logger;
use function add_post_meta;
use function clean_post_cache;
use function delete_post_meta;
use function update_post_meta;
use function update_post_meta_cache;
use function wp_cache_delete;

/**
 * Trait for common meta field saving logic.
 */
trait MetaFieldSaverTrait {
	/**
	 * Save a meta field with verification and cache clearing.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $value Value to save.
	 * @param string $field_name Field name for logging.
	 * @return bool True if saved successfully, false otherwise.
	 */
	protected function save_meta_field( int $post_id, string $meta_key, string $value, string $field_name = '' ): bool {
		if ( '' !== $value ) {
			// Use update_post_meta which handles both insert and update
			$result = update_post_meta( $post_id, $meta_key, $value );
			
			// If update failed, try delete + add
			if ( false === $result ) {
				error_log( "FP SEO: update_post_meta failed for {$meta_key}, trying delete + add - post_id: {$post_id}" );
				delete_post_meta( $post_id, $meta_key );
				$result = add_post_meta( $post_id, $meta_key, $value, true );
			}
			
			// Clear cache immediately
			$this->clear_meta_cache( $post_id, $meta_key );
			
			// Verify the save (with cache cleared)
			$saved_value = get_post_meta( $post_id, $meta_key, true );
			if ( $saved_value === $value ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $field_name ) {
					Logger::debug( "{$field_name} saved successfully", array(
						'post_id' => $post_id,
						'value_preview' => substr( $value, 0, 50 ),
					) );
				}
				return true;
			} else {
				Logger::warning( "{$field_name} save mismatch - retrying", array(
					'post_id' => $post_id,
					'expected' => substr( $value, 0, 50 ),
					'got' => substr( $saved_value ?: '', 0, 50 ),
				) );
				// Retry save
				delete_post_meta( $post_id, $meta_key );
				add_post_meta( $post_id, $meta_key, $value, true );
				$this->clear_meta_cache( $post_id, $meta_key );
				return false;
			}
		}
		
		return false;
	}

	/**
	 * Delete a meta field if explicitly empty.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $field_name Field name for logging.
	 * @param bool   $field_explicitly_empty Whether field was explicitly sent as empty.
	 * @param bool   $metabox_present Whether metabox is present.
	 * @return bool True if deleted, false otherwise.
	 */
	protected function delete_meta_field_if_empty(
		int $post_id,
		string $meta_key,
		string $field_name,
		bool $field_explicitly_empty,
		bool $metabox_present
	): bool {
		// Only delete if BOTH: field is explicitly empty AND metabox is present
		if ( $field_explicitly_empty && $metabox_present ) {
			delete_post_meta( $post_id, $meta_key );
			$this->clear_meta_cache( $post_id, $meta_key );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( "{$field_name} deleted (explicitly empty and metabox present)", array( 'post_id' => $post_id ) );
			}
			return true;
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( "{$field_name} preserved (not explicitly deleted)", array( 
					'post_id' => $post_id,
					'field_explicitly_empty' => $field_explicitly_empty,
					'metabox_present' => $metabox_present,
				) );
			}
			return false;
		}
	}

	/**
	 * Clear meta cache for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	protected function clear_meta_cache( int $post_id, string $meta_key ): void {
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_delete( $post_id . '_' . $meta_key, 'post_meta' );
		clean_post_cache( $post_id );
		
		// Force refresh meta cache if function exists
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post_id ) );
		}
	}

	/**
	 * Check if field was sent or metabox is present.
	 *
	 * @param string $field_key Field key in POST.
	 * @param string $sent_key Sent flag key in POST.
	 * @return array{field_sent: bool, metabox_present: bool}
	 */
	protected function check_field_presence( string $field_key, string $sent_key ): array {
		$field_sent = isset( $_POST[ $sent_key ] ) || isset( $_POST[ $field_key ] );
		$metabox_present = isset( $_POST['fp_seo_performance_metabox_present'] ) && 
						   $_POST['fp_seo_performance_metabox_present'] === '1';
		
		return array(
			'field_sent' => $field_sent,
			'metabox_present' => $metabox_present,
		);
	}

	/**
	 * Check if field is explicitly empty.
	 *
	 * @param string $field_key Field key in POST.
	 * @param string $sent_key Sent flag key in POST.
	 * @return bool True if field is explicitly empty.
	 */
	protected function is_field_explicitly_empty( string $field_key, string $sent_key ): bool {
		return isset( $_POST[ $sent_key ] ) && 
			   isset( $_POST[ $field_key ] ) && 
			   '' === trim( (string) $_POST[ $field_key ] );
	}
}

