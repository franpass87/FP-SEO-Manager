<?php
/**
 * Renders the AI Optimization section of the SEO metabox.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Options;
use WP_Post;
use function esc_html_e;

/**
 * Renders AI-related sections (Q&A Pairs, GEO Claims, Freshness).
 */
class AIRenderer {
	/**
	 * Render the AI Optimization section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		$this->render_qa_section( $post );
		$this->render_geo_section( $post );
		$this->render_freshness_section( $post );
	}

	/**
	 * Render Q&A Pairs section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	private function render_qa_section( WP_Post $post ): void {
		?>
		<!-- Section: Q&A Pairs (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #f59e0b;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🤖</span>
					<?php esc_html_e( 'Q&A Pairs', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +20%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b;">
					<strong style="color: #d97706;">⚡ Molto Alto impatto (+20%)</strong> - Le Q&A aiutano ChatGPT, Gemini e Perplexity a citare i tuoi contenuti. Generano automaticamente il FAQ Schema per Google AI Overview.
				</p>
				<?php
				// Integra il contenuto Q&A Pairs
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$qa_metabox = $container->get( \FP\SEO\Admin\QAMetabox::class );
								if ( $qa_metabox ) {
									$qa_metabox->render( $post );
								}
							}
						}
					}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'QAMetabox not available', array( 'error' => $e->getMessage() ) );
			}
		}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render GEO Claims section (only if GEO is enabled).
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	private function render_geo_section( WP_Post $post ): void {
		$raw_geo_options = get_option( Options::OPTION_KEY, array() );
		$geo_options     = is_array( $raw_geo_options ) ? $raw_geo_options : array();
		if ( empty( $geo_options['geo']['enabled'] ) ) {
			return;
		}
		?>
		<!-- GEO Claims - Integrated Section (solo se GEO abilitato) -->
		<div class="fp-seo-performance-metabox__section">
			<h4 class="fp-seo-performance-metabox__section-heading">
				<span class="fp-seo-section-icon">🗺️</span>
				<?php esc_html_e( 'GEO Claims', 'fp-seo-performance' ); ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php
			try {
				if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
					$plugin = \FP\SEO\Infrastructure\Plugin::instance();
					if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
						$container = $plugin->get_container();
						if ( $container ) {
							$geo_metabox = $container->get( \FP\SEO\Admin\GeoMetabox::class );
							if ( $geo_metabox ) {
								$geo_metabox->render( $post );
							}
						}
					}
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'GeoMetabox not available', array( 'error' => $e->getMessage() ) );
				}
			}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Freshness & Temporal Signals section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	private function render_freshness_section( WP_Post $post ): void {
		?>
		<!-- Freshness & Temporal Signals - Integrated Section -->
		<div class="fp-seo-performance-metabox__section">
			<h4 class="fp-seo-performance-metabox__section-heading">
				<span class="fp-seo-section-icon">📅</span>
				<?php esc_html_e( 'Freshness & Temporal Signals', 'fp-seo-performance' ); ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php
				try {
					$freshness_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\FreshnessMetabox::class );
					if ( $freshness_metabox ) {
						$freshness_metabox->render( $post );
					}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FreshnessMetabox not available', array( 'error' => $e->getMessage() ) );
			}
		}
				?>
			</div>
		</div>
		<?php
	}
}







