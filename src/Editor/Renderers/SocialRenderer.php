<?php
/**
 * Renders the Social Media section of the SEO metabox.
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
 * Renders Social Media section.
 */
class SocialRenderer {
	/**
	 * Render the Social Media section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		?>
		<!-- Section 3: SOCIAL MEDIA (Medium Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #8b5cf6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📱</span>
					<?php esc_html_e( 'Social Media Preview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(139, 92, 246, 0.2);">
					<span style="font-size: 14px;">📊</span>
					<?php esc_html_e( 'Impatto: +12%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #f5f3ff; border-radius: 6px; border-left: 3px solid #8b5cf6;">
					<strong style="color: #7c3aed;">📱 Medio impatto (+12%)</strong> - Ottimizza title, description e immagini per Facebook, Twitter, LinkedIn e Pinterest. Aumenta condivisioni e traffico social.
				</p>
				<?php
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$social_metabox = $container->get( \FP\SEO\Social\ImprovedSocialMediaManager::class );
								if ( $social_metabox && method_exists( $social_metabox, 'render_improved_social_metabox' ) ) {
									$social_metabox->render_improved_social_metabox( $post );
								}
							}
						}
					}
			} catch ( \Throwable $e ) {
				Logger::debug( 'Social metabox not available', array( 'error' => $e->getMessage() ) );
			}
				?>
			</div>
		</div>
		<?php
	}
}








