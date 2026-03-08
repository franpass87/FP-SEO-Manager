<?php
/**
 * Metabox interface.
 *
 * @package FP\SEO\Editor\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Contracts;

use WP_Post;

/**
 * Interface for Metabox functionality.
 */
interface MetaboxInterface {
	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void;

	/**
	 * Render metabox content.
	 *
	 * @param WP_Post $post Current post instance.
	 * @return void
	 */
	public function render( WP_Post $post ): void;

	/**
	 * Save metabox data.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post|null $post Post object.
	 * @param bool|null    $update Whether this is an update.
	 * @return void
	 */
	public function save_meta( int $post_id, $post = null, $update = null ): void;

	/**
	 * Run SEO analysis for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Analysis results.
	 */
	public function run_analysis_for_post( WP_Post $post ): array;

	/**
	 * Get supported post types.
	 *
	 * @return array<string> Array of post type slugs.
	 */
	public function get_supported_post_types(): array;

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if excluded.
	 */
	public function is_post_excluded( int $post_id ): bool;
}


