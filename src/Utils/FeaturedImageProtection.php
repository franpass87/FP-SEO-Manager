<?php
/**
 * Featured Image Protection Helper
 *
 * Provides utilities to prevent plugin interference with WordPress featured image saving.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function defined;
use function sanitize_text_field;
use function wp_unslash;

/**
 * Helper class to prevent interference with WordPress featured image operations
 */
class FeaturedImageProtection {

	/**
	 * Check if current request is for featured image AJAX operation
	 *
	 * WordPress uses AJAX actions 'set-post-thumbnail' and 'remove-post-thumbnail'
	 * to save/remove featured images. This method detects these requests.
	 *
	 * @return bool True if this is a featured image AJAX request.
	 */
	public static function is_featured_image_ajax(): bool {
		// Must be AJAX request
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}

		// Must have action in POST
		if ( ! isset( $_POST['action'] ) ) {
			return false;
		}

		$ajax_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
		$featured_image_actions = array( 'set-post-thumbnail', 'remove-post-thumbnail', 'get-post-thumbnail-html' );

		return in_array( $ajax_action, $featured_image_actions, true );
	}

	/**
	 * Check if we should block plugin execution to prevent featured image interference
	 *
	 * This is the main method to use in save_post hooks and other critical points.
	 * 
	 * IMPORTANT: We ONLY block AJAX requests for featured image operations.
	 * We do NOT block normal post saves, even if they include _thumbnail_id,
	 * because WordPress always includes _thumbnail_id in $_POST during normal saves
	 * even if the featured image hasn't changed.
	 *
	 * @return bool True if we should block execution (to prevent interference).
	 */
	public static function should_block_execution(): bool {
		// ONLY block if it's a featured image AJAX request
		// WordPress uses AJAX for set-post-thumbnail and remove-post-thumbnail
		// These are the ONLY operations we need to block
		return self::is_featured_image_ajax();
	}
}

