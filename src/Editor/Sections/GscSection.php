<?php
/**
 * Google Search Console metrics section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\GscMetricsRenderer;
use FP\SEO\Utils\Options;
use WP_Post;

/**
 * Google Search Console metrics section wrapper.
 */
class GscSection extends AbstractSection {
	/**
	 * GSC metrics renderer.
	 *
	 * @var GscMetricsRenderer
	 */
	private GscMetricsRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param GscMetricsRenderer $renderer GSC metrics renderer instance.
	 * @param int                $priority Section priority.
	 */
	public function __construct( GscMetricsRenderer $renderer, int $priority = 15 ) {
		parent::__construct( 'gsc', $priority );
		$this->renderer = $renderer;
	}

	/**
	 * Check if section is enabled for the given post.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if enabled.
	 */
	public function is_enabled( WP_Post $post ): bool {
		$raw_options = get_option( Options::OPTION_KEY, array() );
		$options     = is_array( $raw_options ) ? $raw_options : array();
		$gsc         = $options['gsc'] ?? array();
		return ! empty( $gsc['enabled'] );
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


