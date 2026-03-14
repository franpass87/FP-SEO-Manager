<?php
/**
 * SERP optimization section wrapper.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\Renderers\SerpFieldsRenderer;
use WP_Post;

/**
 * SERP optimization section wrapper.
 */
class SerpSection extends AbstractSection {
	/**
	 * SERP fields renderer.
	 *
	 * @var SerpFieldsRenderer
	 */
	private SerpFieldsRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param SerpFieldsRenderer $renderer SERP fields renderer instance.
	 * @param int                $priority Section priority.
	 */
	public function __construct( SerpFieldsRenderer $renderer, int $priority = 10 ) {
		parent::__construct( 'serp', $priority );
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
		?>
		<div class="fp-seo-performance-metabox__section fp-seo-serp-optimization-section" style="border-left: 4px solid #10b981;" data-section="serp-optimization">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🎯</span>
					<?php esc_html_e( 'SERP Optimization', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +40%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<div style="display: grid; gap: 16px; margin-bottom: 20px;">
					<?php $this->renderer->render_seo_title( $post ); ?>
					<?php $this->renderer->render_meta_description( $post ); ?>
					<?php $this->renderer->render_canonical_url( $post ); ?>
					<?php $this->renderer->render_slug( $post ); ?>
					<?php $this->renderer->render_excerpt( $post ); ?>
					<div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%); margin: 8px 0;"></div>
					<?php $this->renderer->render_schema_type( $post ); ?>
					<div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%); margin: 8px 0;"></div>
					<?php $this->renderer->render_keywords( $post ); ?>
				</div>
			</div>
		</div>
		<?php
	}
}


