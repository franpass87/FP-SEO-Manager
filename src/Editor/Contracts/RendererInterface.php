<?php
/**
 * Renderer interface.
 *
 * @package FP\SEO\Editor\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Contracts;

use WP_Post;

/**
 * Interface for Metabox renderers.
 */
interface RendererInterface {
	/**
	 * Render the metabox content.
	 *
	 * @param WP_Post $post Current post.
	 * @param array<string, mixed> $analysis Analysis data.
	 * @param bool $excluded Whether post is excluded from analysis.
	 * @return void
	 */
	public function render( WP_Post $post, array $analysis, bool $excluded ): void;
}


