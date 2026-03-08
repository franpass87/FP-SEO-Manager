<?php
/**
 * Service for validating save operations.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use function get_post_status;

/**
 * Service for validating save operations.
 */
class SaveValidationService {

	/**
	 * Check if this is a real save operation (user clicked Save/Publish button).
	 *
	 * @return bool True if real save, false otherwise.
	 */
	public function is_real_save(): bool {
		return ( isset( $_POST['save'] ) && $_POST['save'] !== '' ) ||
			   ( isset( $_POST['publish'] ) && $_POST['publish'] !== '' ) ||
			   ( isset( $_POST['action'] ) && $_POST['action'] === 'editpost' &&
				 ( isset( $_POST['save'] ) || isset( $_POST['publish'] ) ) );
	}

	/**
	 * Check if there are actual SEO fields being submitted.
	 *
	 * @return bool True if has SEO fields, false otherwise.
	 */
	public function has_seo_fields(): bool {
		return isset( $_POST['fp_seo_performance_metabox_present'] ) ||
			   isset( $_POST['fp_seo_title_sent'] ) ||
			   isset( $_POST['fp_seo_meta_description_sent'] ) ||
			   isset( $_POST['fp_seo_title'] ) ||
			   isset( $_POST['fp_seo_meta_description'] );
	}

	/**
	 * Check if post status is auto-draft or invalid.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if should skip, false otherwise.
	 */
	public function should_skip_auto_draft( int $post_id ): bool {
		$current_post_status = get_post_status( $post_id );
		return $current_post_status === 'auto-draft' || $current_post_status === false;
	}

	/**
	 * Check if this is an autosave operation.
	 *
	 * @return bool True if autosave, false otherwise.
	 */
	public function is_autosave(): bool {
		return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
	}

	/**
	 * Validate if save operation should proceed.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if should proceed, false otherwise.
	 */
	public function should_proceed( int $post_id ): bool {
		// Skip auto-draft immediately
		if ( $this->should_skip_auto_draft( $post_id ) ) {
			return false;
		}

		// Only process if it's a REAL save operation AND has SEO fields
		return $this->is_real_save() && $this->has_seo_fields();
	}
}








