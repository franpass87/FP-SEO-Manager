<?php
/**
 * Renders the Schema sections (HowTo) of the SEO metabox.
 * NOTE: FAQ Schema is automatically generated from Q&A pairs (handled by AIRenderer).
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Utils\Logger;
use WP_Post;
use function esc_html_e;

/**
 * Renders Schema sections (HowTo).
 * NOTE: FAQ Schema is generated automatically from Q&A pairs, no separate section needed.
 */
class SchemaRenderer {
	/**
	 * Render the Schema sections.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		// NOTE: Q&A Pairs section has been moved to AIRenderer to avoid duplication
		// FAQ Schema is automatically generated from Q&A pairs, so no separate section needed
		$this->render_howto_section( $post );
	}

	/**
	 * Render HowTo Schema section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	private function render_howto_section( WP_Post $post ): void {
		?>
		<!-- Section 6: HOWTO SCHEMA (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #3b82f6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📖</span>
					<?php esc_html_e( 'HowTo Schema - Guide', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +15%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #eff6ff; border-radius: 6px; border-left: 3px solid #3b82f6;">
					<strong style="color: #2563eb;">⚡ Alto impatto (+15%)</strong> - Guide con HowTo Schema mostrano step nei risultati Google con rich snippets visuali. Ottimale per tutorial e guide.
				</p>
				<?php
				// Integra il rendering HowTo
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$schema_metaboxes = $container->get( \FP\SEO\Editor\SchemaMetaboxes::class );
								if ( $schema_metaboxes && method_exists( $schema_metaboxes, 'render_howto_metabox' ) ) {
									$schema_metaboxes->render_howto_metabox( $post );
								}
							}
						}
					}
			} catch ( \Throwable $e ) {
				Logger::debug( 'HowTo Schema not available', array( 'error' => $e->getMessage() ) );
			}
				?>
			</div>
		</div>
		<?php
	}
}







