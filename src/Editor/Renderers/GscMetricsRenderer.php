<?php
/**
 * Renders Google Search Console metrics section.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Options;
use WP_Post;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function number_format_i18n;

/**
 * Renders GSC metrics.
 */
class GscMetricsRenderer {
	/**
	 * Render GSC metrics section.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		// Verifica che Options sia disponibile
		if ( ! class_exists( 'FP\\SEO\\Utils\\Options', false ) ) {
			return;
		}

		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		// Verifica che GscData sia disponibile
		if ( ! class_exists( 'FP\\SEO\\Integrations\\GscData', false ) ) {
			return;
		}

		try {
			$gsc_data = new GscData();
			$metrics = $gsc_data->get_post_metrics( $post->ID, 28 );

			if ( empty( $metrics ) || ! is_array( $metrics ) ) {
				return;
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error loading GSC data', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			return;
		}

		$clicks = $metrics['clicks'] ?? 0;
		$impressions = $metrics['impressions'] ?? 0;
		$ctr = $metrics['ctr'] ?? 0.0;
		$position = $metrics['position'] ?? 0.0;
		$queries = $metrics['queries'] ?? array();

		?>
		<div class="fp-seo-gsc-post-metrics" style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
			<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #111827;">
				📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
			</h4>
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Clicks', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #059669;">
						<?php echo esc_html( number_format_i18n( $clicks ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #2563eb;">
						<?php echo esc_html( number_format_i18n( $impressions ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( number_format_i18n( $ctr, 2 ) ); ?>%
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Position', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( number_format_i18n( $position, 1 ) ); ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $queries ) && is_array( $queries ) ) : ?>
				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-weight: 600; color: #374151;">
						🔍 <?php esc_html_e( 'Top Queries', 'fp-seo-performance' ); ?> (<?php echo esc_html( count( $queries ) ); ?>)
					</summary>
					<ul style="margin: 8px 0 0; padding: 0; list-style: none;">
						<?php foreach ( array_slice( $queries, 0, 5 ) as $query_data ) : ?>
							<?php
							if ( ! is_array( $query_data ) ) {
								continue;
							}
							$query_text   = $query_data['query'] ?? '';
							$query_clicks = $query_data['clicks'] ?? 0;
							$query_pos    = $query_data['position'] ?? 0.0;
							if ( empty( $query_text ) ) {
								continue;
							}
							?>
							<li style="padding: 6px 8px; background: #fff; border-radius: 4px; margin-bottom: 4px; font-size: 12px;">
								<strong><?php echo esc_html( $query_text ); ?></strong>
								<span style="color: #6b7280; margin-left: 10px;">
									<?php echo esc_html( number_format_i18n( $query_clicks ) ); ?> clicks, 
									pos <?php echo esc_html( number_format_i18n( $query_pos, 1 ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}
}

