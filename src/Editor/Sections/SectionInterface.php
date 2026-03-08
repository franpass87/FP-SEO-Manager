<?php
/**
 * Section interface for modular metabox sections.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use WP_Post;

/**
 * Interface for metabox sections.
 */
interface SectionInterface {
	/**
	 * Render the section.
	 *
	 * @param WP_Post              $post Post object.
	 * @param array<string, mixed> $context Context data (analysis, excluded, etc.).
	 * @return void
	 */
	public function render( WP_Post $post, array $context = [] ): void;

	/**
	 * Check if section is enabled for the given post.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if enabled.
	 */
	public function is_enabled( WP_Post $post ): bool;

	/**
	 * Get section priority (lower = rendered first).
	 *
	 * @return int Priority value.
	 */
	public function get_priority(): int;

	/**
	 * Get section unique identifier.
	 *
	 * @return string Section ID.
	 */
	public function get_id(): string;
}


