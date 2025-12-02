<?php
/**
 * Renders the SERP preview section.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_permalink;
use function wp_specialchars_decode;

/**
 * Renders the SERP preview.
 */
class SerpPreviewRenderer {
	/**
	 * Render SERP Preview section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		// Get preview data
		$seo_title = MetadataResolver::resolve_seo_title( $post );
		$meta_description = MetadataResolver::resolve_meta_description( $post );
		$url = get_permalink( $post->ID );

		?>
		<!-- Section: SERP PREVIEW -->
		<div class="fp-seo-serp-preview fp-seo-performance-metabox__section" style="border-left: 4px solid #6366f1;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🔍</span>
					<?php esc_html_e( 'SERP Preview', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<div class="fp-seo-serp-preview-card">
					<div class="fp-seo-serp-preview-url">
						<?php echo esc_url( $url ); ?>
					</div>
					<div class="fp-seo-serp-preview-title" id="fp-seo-serp-preview-title">
						<?php echo esc_html( wp_specialchars_decode( $seo_title, ENT_QUOTES ) ); ?>
					</div>
					<div class="fp-seo-serp-preview-description" id="fp-seo-serp-preview-description">
						<?php echo esc_html( wp_specialchars_decode( $meta_description, ENT_QUOTES ) ); ?>
					</div>
				</div>
				<p class="description" style="margin-top: 12px; font-size: 12px; color: #64748b;">
					<?php esc_html_e( 'Anteprima di come apparirà il tuo contenuto nei risultati di ricerca Google.', 'fp-seo-performance' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}


