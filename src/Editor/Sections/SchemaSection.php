<?php
/**
 * Schema section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\SchemaRenderer;
use WP_Post;

/**
 * Schema section wrapper.
 */
class SchemaSection extends AbstractSection {
	/**
	 * Schema renderer.
	 *
	 * @var SchemaRenderer
	 */
	private SchemaRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param SchemaRenderer $renderer Schema renderer instance.
	 * @param int            $priority Section priority.
	 */
	public function __construct( SchemaRenderer $renderer, int $priority = 40 ) {
		parent::__construct( 'schema', $priority );
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


