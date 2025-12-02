<?php
/**
 * Helper class for retrieving post meta with fallback to direct DB queries.
 *
 * @package FP\SEO\Editor\Helpers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Helpers;

use FP\SEO\Utils\Logger;
use WP_Post;
use function get_post_meta;
use function maybe_unserialize;
use function update_post_meta_cache;

/**
 * Helper for post meta operations with DB fallback.
 */
class MetaHelper {
	/**
	 * Get post meta with fallback to direct DB query.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $single Whether to return single value.
	 * @param bool   $update_cache Whether to update post meta cache.
	 * @return mixed Meta value or null if not found.
	 */
	public static function get_meta( int $post_id, string $meta_key, bool $single = true, bool $update_cache = true ) {
		if ( $update_cache && function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post_id ) );
		}

		$value = get_post_meta( $post_id, $meta_key, $single );

		// Fallback: direct DB query if get_post_meta returns empty
		if ( empty( $value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$meta_key
			) );

			if ( $db_value !== null ) {
				$value = $db_value;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'MetaHelper::get_meta - fallback DB query found value', array(
						'post_id' => $post_id,
						'meta_key' => $meta_key,
						'value_preview' => is_string( $value ) ? substr( $value, 0, 50 ) : ( is_numeric( $value ) ? $value : 'non-string/non-numeric' ),
					) );
				}
			}
		}

		// Handle serialized arrays
		if ( is_string( $value ) && ( $value[0] === 'a:' || $value[0] === 'O:' ) ) {
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				$value = $unserialized;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'MetaHelper::get_meta - final value', array(
				'post_id' => $post_id,
				'meta_key' => $meta_key,
				'final_value' => is_string( $value ) ? substr( $value, 0, 50 ) : ( is_numeric( $value ) ? $value : ( is_array( $value ) ? 'array(' . count( $value ) . ')' : 'empty' ) ),
			) );
		}

		return $value;
	}

	/**
	 * Get post meta as string.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $update_cache Whether to update post meta cache.
	 * @return string Meta value as string.
	 */
	public static function get_meta_string( int $post_id, string $meta_key, bool $update_cache = true ): string {
		$value = self::get_meta( $post_id, $meta_key, true, $update_cache );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Get post meta as array.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param bool   $update_cache Whether to update post meta cache.
	 * @return array Meta value as array.
	 */
	public static function get_meta_array( int $post_id, string $meta_key, bool $update_cache = true ): array {
		$value = self::get_meta( $post_id, $meta_key, true, $update_cache );
		return is_array( $value ) ? $value : array();
	}
}


