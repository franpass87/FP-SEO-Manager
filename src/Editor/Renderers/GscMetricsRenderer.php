<?php
/**
 * Renderer for Google Search Console metrics in metabox.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\Options;
use WP_Post;
use function count;
use function esc_html;
use function esc_html_e;
use function number_format_i18n;

/**
 * Renders Google Search Console metrics in the SEO metabox.
 */
class GscMetricsRenderer {

	/**
	 * Render GSC metrics section.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		$gsc_data = new GscData();
		$metrics  = $gsc_data->get_post_metrics( $post->ID, 28 );

		if ( ! $metrics ) {
			return;
		}

		$this->render_metrics( $metrics );
	}

	/**
	 * Render GSC metrics section with provided GscData instance.
	 *
	 * @param WP_Post $post Post object.
	 * @param GscData|null $gsc_data Optional GscData instance. If null, creates new instance.
	 * @return void
	 */
	public function render_for_post( WP_Post $post, ?GscData $gsc_data = null ): void {
		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		if ( ! $gsc_data ) {
			$gsc_data = new GscData();
		}

		$metrics = $gsc_data->get_post_metrics( $post->ID, 28 );

		if ( ! $metrics ) {
			return;
		}

		$this->render_metrics( $metrics );
	}

	/**
	 * Render metrics HTML.
	 *
	 * @param array<string, mixed> $metrics Metrics data.
	 * @return void
	 */
	private function render_metrics( array $metrics ): void {

		?>
		<div class="fp-seo-gsc-post-metrics" style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
			<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #111827;">
				📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
			</h4>
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
				<?php $this->render_metric( __( 'Clicks', 'fp-seo-performance' ), number_format_i18n( $metrics['clicks'] ), '#059669' ); ?>
				<?php $this->render_metric( __( 'Impressions', 'fp-seo-performance' ), number_format_i18n( $metrics['impressions'] ), '#2563eb' ); ?>
				<?php $this->render_metric( __( 'CTR', 'fp-seo-performance' ), $metrics['ctr'] . '%', '#111827' ); ?>
				<?php $this->render_metric( __( 'Position', 'fp-seo-performance' ), $metrics['position'], '#111827' ); ?>
			</div>

			<?php if ( ! empty( $metrics['queries'] ) ) : ?>
				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-weight: 600; color: #374151;">
						🔍 <?php esc_html_e( 'Top Queries', 'fp-seo-performance' ); ?> (<?php echo count( $metrics['queries'] ); ?>)
					</summary>
					<ul style="margin: 8px 0 0; padding: 0; list-style: none;">
						<?php foreach ( array_slice( $metrics['queries'], 0, 5 ) as $query_data ) : ?>
							<li style="padding: 6px 8px; background: #fff; border-radius: 4px; margin-bottom: 4px; font-size: 12px;">
								<strong><?php echo esc_html( $query_data['query'] ); ?></strong>
								<span style="color: #6b7280; margin-left: 10px;">
									<?php echo esc_html( $query_data['clicks'] ); ?> clicks, 
									pos <?php echo esc_html( $query_data['position'] ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a single metric.
	 *
	 * @param string $label Metric label.
	 * @param string $value Metric value.
	 * @param string $color Text color.
	 * @return void
	 */
	private function render_metric( string $label, string $value, string $color ): void {
		?>
		<div style="text-align: center;">
			<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
				<?php echo esc_html( $label ); ?>
			</div>
			<div style="font-size: 20px; font-weight: 700; color: <?php echo esc_attr( $color ); ?>;">
				<?php echo esc_html( $value ); ?>
			</div>
		</div>
		<?php
	}
}
