<?php
/**
 * Analysis service interface.
 *
 * @package FP\SEO\Editor\Metabox\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Metabox\Contracts;

use WP_Post;

/**
 * Interface for analysis service.
 */
interface AnalysisServiceInterface {
	/**
	 * Run analysis for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Analysis result with 'score' and 'checks' keys.
	 */
	public function run( WP_Post $post ): array;
}















