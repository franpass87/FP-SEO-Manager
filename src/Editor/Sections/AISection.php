<?php
/**
 * AI optimization section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\AIRenderer;
use WP_Post;

/**
 * AI optimization section wrapper.
 */
class AISection extends AbstractSection {
	/**
	 * AI renderer.
	 *
	 * @var AIRenderer
	 */
	private AIRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param AIRenderer $renderer AI renderer instance.
	 * @param int        $priority Section priority.
	 */
	public function __construct( AIRenderer $renderer, int $priority = 25 ) {
		parent::__construct( 'ai', $priority );
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


