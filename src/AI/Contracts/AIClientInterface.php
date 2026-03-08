<?php
/**
 * AI client interface.
 *
 * @package FP\SEO\AI\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI\Contracts;

/**
 * Interface for AI client implementations.
 */
interface AIClientInterface {
	/**
	 * Check if AI is configured and ready.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured(): bool;

	/**
	 * Generate SEO content suggestions for a post.
	 *
	 * @param int    $post_id       Post ID.
	 * @param string $content       Post content.
	 * @param string $title         Current post title.
	 * @param string $focus_keyword Optional focus keyword to optimize for.
	 * @return array<string, mixed> Result with 'success' key and optional 'data' or 'error' keys.
	 */
	public function generate_seo_suggestions( int $post_id, string $content, string $title, string $focus_keyword = '' ): array;
}















