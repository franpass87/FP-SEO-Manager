<?php
/**
 * SERP preview section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\SerpPreviewRenderer;
use WP_Post;

/**
 * SERP preview section wrapper.
 */
class SerpPreviewSection extends AbstractSection {
	/**
	 * SERP preview renderer.
	 *
	 * @var SerpPreviewRenderer
	 */
	private SerpPreviewRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param SerpPreviewRenderer $renderer SERP preview renderer instance.
	 * @param int                 $priority Section priority.
	 */
	public function __construct( SerpPreviewRenderer $renderer, int $priority = 5 ) {
		parent::__construct( 'serp_preview', $priority );
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


