<?php
/**
 * Helper class to protect WordPress native meta fields and operations
 * from plugin interference.
 *
 * @package FP\SEO\Editor\Helpers
 * @author Francesco Passeri
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Helpers;

/**
 * WordPress Native Protection Helper
 * 
 * This class provides methods to detect when WordPress is handling
 * native operations and prevent plugin interference.
 */
class WordPressNativeProtection {
	
	/**
	 * List of WordPress native meta keys that should be protected
	 * from plugin cache clearing and interference.
	 */
	private const NATIVE_META_KEYS = array(
		// Featured image - CRITICAL: Must be protected to prevent interference
		'_thumbnail_id',
		
		// Post editing locks
		'_edit_lock',
		'_edit_last',
		
		// Page template
		'_wp_page_template',
		
		// Attachment metadata
		'_wp_attachment_metadata',
		'_wp_attachment_backup_sizes',
		'_wp_attached_file',
		
		// Old slug/date for redirects
		'_wp_old_slug',
		'_wp_old_date',
		
		// Post format
		'_wp_post_format',
		
		// Custom fields (if being saved by WordPress core)
		'_wp_page_template',
		
		// Revision metadata
		'_wp_revision_uid',
		
		// Menu order
		'menu_order',
	);
	
	/**
	 * List of WordPress native AJAX actions that should be protected
	 */
	private const NATIVE_AJAX_ACTIONS = array(
		// Featured image
		'set-post-thumbnail',
		'remove-post-thumbnail',
		'get-post-thumbnail-html',
		
		// Post locks
		'heartbeat',
		'wp-remove-post-lock',
		'wp-refresh-post-lock',
		
		// Autosave
		'autosave',
		'wp-autosave',
		
		// Media
		'get-attachment',
		'send-attachment-to-editor',
		'set-attachment-thumbnail',
		
		// Meta boxes
		'meta-box-order',
		'closed-postboxes',
	);
	
	/**
	 * Checks if a WordPress native meta field is currently being saved.
	 * This is determined by checking if the meta key is present in $_POST
	 * and if it's a known native WordPress meta key.
	 *
	 * @param string $meta_key The meta key to check.
	 * @return bool True if a native meta field is being saved, false otherwise.
	 */
	public static function is_native_meta_field_being_saved( string $meta_key ): bool {
		// Check if the current meta_key is one of the native ones
		if ( in_array( $meta_key, self::NATIVE_META_KEYS, true ) ) {
			// Additionally, check if it's present in $_POST, indicating an active save operation
			if ( isset( $_POST[ $meta_key ] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\LoggerHelper::debug( "FP SEO: Native meta field '{$meta_key}' detected in POST, indicating active save.", array( 'meta_key' => $meta_key ) );
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if any WordPress native meta field is currently being saved.
	 * This is a broader check than is_native_meta_field_being_saved.
	 *
	 * @return bool True if any native meta field is being saved, false otherwise.
	 */
	public static function any_native_meta_field_being_saved(): bool {
		foreach ( self::NATIVE_META_KEYS as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\LoggerHelper::debug( "FP SEO: Native meta field '{$key}' detected in POST, indicating active save.", array( 'meta_key' => $key ) );
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if WordPress is handling a native operation
	 * 
	 * @return bool True if WordPress is handling a native operation
	 */
	public static function is_wordpress_native_operation(): bool {
		// Check for native AJAX actions
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) ) {
			$ajax_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			if ( in_array( $ajax_action, self::NATIVE_AJAX_ACTIONS, true ) ) {
				return true;
			}
		}
		
		// Check for native meta keys in POST
		foreach ( self::NATIVE_META_KEYS as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Check if a specific meta key is a WordPress native meta key
	 * 
	 * @param string $meta_key Meta key to check
	 * @return bool True if it's a native meta key
	 */
	public static function is_native_meta_key( string $meta_key ): bool {
		return in_array( $meta_key, self::NATIVE_META_KEYS, true );
	}
	
	/**
	 * Check if WordPress is handling a native meta field save
	 * 
	 * @param string|null $meta_key Optional meta key to check specifically
	 * @return bool True if WordPress is handling a native meta field save
	 */
	public static function is_native_meta_save( ?string $meta_key = null ): bool {
		// If specific meta key provided, check if it's native
		if ( $meta_key !== null ) {
			if ( self::is_native_meta_key( $meta_key ) ) {
				// Check if it's present in POST
				if ( isset( $_POST[ $meta_key ] ) ) {
					return true;
				}
			}
		}
		
		// Check all native meta keys
		foreach ( self::NATIVE_META_KEYS as $native_key ) {
			if ( isset( $_POST[ $native_key ] ) ) {
				// For all native keys, if present in POST, it's a native operation
				if ( true ) {
					// For other native keys, if present in POST, it's a native operation
					return true;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Get list of native meta keys currently being saved
	 * 
	 * @return array List of native meta keys present in POST
	 */
	public static function get_native_meta_keys_being_saved(): array {
		$keys = array();
		
		foreach ( self::NATIVE_META_KEYS as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				$keys[] = $meta_key;
			}
		}
		
		return $keys;
	}
	
	/**
	 * Check if cache clearing should be skipped
	 * 
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key being saved
	 * @return bool True if cache clearing should be skipped
	 */
	public static function should_skip_cache_clearing( int $post_id, string $meta_key ): bool {
		// Always skip if WordPress is handling a native operation
		if ( self::is_wordpress_native_operation() ) {
			return true;
		}
		
		// Skip if this specific meta key is native and being saved
		if ( self::is_native_meta_save( $meta_key ) ) {
			return true;
		}
		
		// Skip if any native meta key is being saved
		$native_keys = self::get_native_meta_keys_being_saved();
		if ( ! empty( $native_keys ) ) {
			return true;
		}
		
		return false;
	}
}

