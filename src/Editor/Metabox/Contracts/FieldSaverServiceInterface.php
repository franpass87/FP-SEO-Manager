<?php
/**
 * Field saver service interface.
 *
 * @package FP\SEO\Editor\Metabox\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Metabox\Contracts;

/**
 * Interface for field saver service.
 */
interface FieldSaverServiceInterface {
	/**
	 * Save all SEO fields for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if saved successfully, false otherwise.
	 */
	public function save_all_fields( int $post_id ): bool;

	/**
	 * Save SEO fields from POST data (for AJAX requests).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, bool> Saved fields data.
	 */
	public function save_from_post( int $post_id ): array;
}















