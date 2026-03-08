<?php
/**
 * Internal links section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\InternalLinksRenderer;
use WP_Post;

/**
 * Internal links section wrapper.
 */
class InternalLinksSection extends AbstractSection {
	/**
	 * Internal links renderer.
	 *
	 * @var InternalLinksRenderer
	 */
	private InternalLinksRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param InternalLinksRenderer $renderer Internal links renderer instance.
	 * @param int                   $priority Section priority.
	 */
	public function __construct( InternalLinksRenderer $renderer, int $priority = 35 ) {
		parent::__construct( 'internal_links', $priority );
		$this->renderer = $renderer;
	}

	/**
	 * Render the section.
	 *
	 * @param WP_Post              $post Post object.
	 * @param array<string, mixed> $context Context data.
	 * @return void
	 */
	public function render( WP_Post $post, array $context = [] ): void {
		$this->renderer->render( $post );
	}
}


