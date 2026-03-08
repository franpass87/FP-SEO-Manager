<?php
/**
 * Header section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\HeaderRenderer;
use WP_Post;

/**
 * Header section wrapper.
 */
class HeaderSection extends AbstractSection {
	/**
	 * Header renderer.
	 *
	 * @var HeaderRenderer
	 */
	private HeaderRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param HeaderRenderer $renderer Header renderer instance.
	 * @param int            $priority Section priority.
	 */
	public function __construct( HeaderRenderer $renderer, int $priority = 1 ) {
		parent::__construct( 'header', $priority );
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
		$excluded     = $context['excluded'] ?? false;
		$score_value  = $context['score']['score'] ?? 0;
		$score_status = $context['score']['status'] ?? 'pending';

		$this->renderer->render( $excluded, $score_value, $score_status );
	}
}


