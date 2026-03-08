<?php
/**
 * Analysis section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\AnalysisSectionRenderer;
use WP_Post;

/**
 * Analysis section wrapper.
 */
class AnalysisSection extends AbstractSection {
	/**
	 * Analysis section renderer.
	 *
	 * @var AnalysisSectionRenderer
	 */
	private AnalysisSectionRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param AnalysisSectionRenderer $renderer Analysis section renderer instance.
	 * @param int                     $priority Section priority.
	 */
	public function __construct( AnalysisSectionRenderer $renderer, int $priority = 20 ) {
		parent::__construct( 'analysis', $priority );
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
		// CRITICAL FIX: Try multiple ways to extract checks
		$checks = array();
		
		if ( isset( $context['checks'] ) && is_array( $context['checks'] ) && ! empty( $context['checks'] ) ) {
			$checks = $context['checks'];
		} elseif ( isset( $context['analysis']['checks'] ) && is_array( $context['analysis']['checks'] ) ) {
			$checks = $context['analysis']['checks'];
		}

		if ( ! is_array( $checks ) ) {
			$checks = array();
		}
		
		$this->renderer->render( $checks );
	}
}
