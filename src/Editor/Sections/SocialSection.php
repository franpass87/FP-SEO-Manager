<?php
/**
 * Social media section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\SocialRenderer;
use WP_Post;

/**
 * Social media section wrapper.
 */
class SocialSection extends AbstractSection {
	/**
	 * Social renderer.
	 *
	 * @var SocialRenderer
	 */
	private SocialRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param SocialRenderer $renderer Social renderer instance.
	 * @param int            $priority Section priority.
	 */
	public function __construct( SocialRenderer $renderer, int $priority = 30 ) {
		parent::__construct( 'social', $priority );
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


