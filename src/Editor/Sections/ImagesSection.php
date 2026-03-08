<?php
/**
 * Images section wrapper (deprecated - feature removed).
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use WP_Post;

/**
 * Images section wrapper.
 *
 * Note: Image optimization feature has been removed.
 * This section is kept for backward compatibility but will not render anything.
 */
class ImagesSection extends AbstractSection {
	/**
	 * Constructor.
	 *
	 * @param int $priority Section priority.
	 */
	public function __construct( int $priority = 50 ) {
		parent::__construct( 'images', $priority );
	}

	/**
	 * Check if section is enabled.
	 *
	 * Always returns false since the feature has been removed.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool Always false.
	 */
	public function is_enabled( WP_Post $post ): bool {
		return false; // Feature removed
	}

	/**
	 * Render the section.
	 *
	 * Does nothing since the feature has been removed.
	 *
	 * @param WP_Post              $post Post object.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	public function render( WP_Post $post, array $context = [] ): void {
		// Feature removed - do nothing
		return;
	}
}


