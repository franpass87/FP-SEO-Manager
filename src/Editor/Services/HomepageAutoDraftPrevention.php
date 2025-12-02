<?php
/**
 * Service for preventing homepage from becoming auto-draft.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Editor\Helpers\HomepageHelper;
use FP\SEO\Utils\Logger;
use WP_Post;
use function clean_post_cache;
use function get_option;
use function get_post_meta;
use function get_post_status;
use function set_transient;
use function wp_cache_delete;

/**
 * Service for preventing homepage from becoming auto-draft.
 */
class HomepageAutoDraftPrevention {
	/**
	 * Prevent homepage status from changing to auto-draft.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public static function prevent_status_transition( string $new_status, string $old_status, $post ): void {
		// Verifica se è la homepage
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 || ! $post instanceof WP_Post || $post->ID !== $page_on_front_id ) {
			return;
		}
		
		// Se lo status sta cambiando verso 'auto-draft' ma il post esiste già (non è nuovo)
		if ( $new_status === 'auto-draft' && $old_status !== 'auto-draft' && $old_status !== '' ) {
			// Usa un flag statico per evitare loop infiniti
			static $correcting = array();
			if ( isset( $correcting[ $post->ID ] ) ) {
				return;
			}
			$correcting[ $post->ID ] = true;
			
			// Correggi immediatamente usando wpdb direttamente (evita wp_update_post che può causare loop)
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $old_status ),
				array( 'ID' => $post->ID ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Clear cache
			clean_post_cache( $post->ID );
			wp_cache_delete( $post->ID, 'posts' );
			
			unset( $correcting[ $post->ID ] );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'HomepageAutoDraftPrevention - Prevented homepage status change to auto-draft', array(
					'post_id' => $post->ID,
					'old_status' => $old_status,
					'attempted_status' => 'auto-draft',
				) );
			}
		}
	}

	/**
	 * Save homepage original status at the beginning of the request.
	 *
	 * @return void
	 */
	public static function save_original_status(): void {
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		// Salva lo status originale in una variabile statica
		global $wpdb;
		$original_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$page_on_front_id
		) );
		
		if ( ! empty( $original_status ) && $original_status !== 'auto-draft' ) {
			// Salva in una transiente che dura solo per questa richiesta
			set_transient( 'fp_seo_homepage_original_status_' . $page_on_front_id, $original_status, 60 );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'HomepageAutoDraftPrevention - Saved original status', array(
					'post_id' => $page_on_front_id,
					'original_status' => $original_status,
				) );
			}
		}
	}

	/**
	 * Prevent homepage from becoming auto-draft in post data.
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Original post array.
	 * @return array Modified post data.
	 */
	public static function prevent_in_post_data( array $data, array $postarr ): array {
		// Only check if this is the homepage
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 ) {
			return $data; // Not using static homepage
		}
		
		// Check if this post is the homepage
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id === 0 || $post_id !== $page_on_front_id ) {
			return $data; // Not the homepage
		}
		
		// This is the homepage - prevent it from becoming auto-draft
		if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' ) {
			// Get current status from database to preserve it
			$current_status = get_post_status( $post_id );
			if ( $current_status && $current_status !== 'auto-draft' ) {
				$data['post_status'] = $current_status;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'HomepageAutoDraftPrevention - Prevented homepage from becoming auto-draft', array(
						'post_id' => $post_id,
						'original_status' => $current_status,
						'attempted_status' => 'auto-draft',
					) );
				}
			} else {
				// Fallback to publish if current status is also auto-draft
				$data['post_status'] = 'publish';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'HomepageAutoDraftPrevention - Forced homepage to publish (was auto-draft)', array(
						'post_id' => $post_id,
					) );
				}
			}
		}
		
		return $data;
	}

	/**
	 * Prevent homepage from becoming auto-draft when updating post.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object after update.
	 * @return void
	 */
	public static function prevent_on_update( int $post_id, WP_Post $post ): void {
		// Only check if this is the homepage
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
			return;
		}
		
		// Get current status from database (not from post object which might be cached)
		global $wpdb;
		$current_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );
		
		// If status is auto-draft, fix it immediately
		if ( $current_status === 'auto-draft' ) {
			// Get original status before update
			$original_status = get_post_meta( $post_id, '_fp_seo_original_status', true );
			if ( empty( $original_status ) || $original_status === 'auto-draft' ) {
				$original_status = 'publish'; // Default to publish for homepage
			}
			
			// Fix status immediately
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $original_status ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Clear cache
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
			
			Logger::warning( 'HomepageAutoDraftPrevention - Fixed homepage status after update', array(
				'post_id' => $post_id,
				'fixed_status' => $original_status,
			) );
		}
	}

	/**
	 * Prevent homepage from becoming auto-draft when editing post.
	 *
	 * @param int        $post_id Post ID.
	 * @param WP_Post|mixed $post    Post object.
	 * @return void
	 */
	public static function prevent_on_edit( int $post_id, $post ): void {
		// Only check if this is the homepage
		$page_on_front_id = HomepageHelper::get_homepage_id();
		if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
			return;
		}
		
		// Same logic as prevent_on_update
		self::prevent_on_update( $post_id, $post instanceof WP_Post ? $post : get_post( $post_id ) );
	}
}

