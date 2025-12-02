<?php
/**
 * Renderer for GSC Dashboard Widget
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_the_title;
use function number_format_i18n;
use function parse_url;
use function url_to_postid;

/**
 * Renders the GSC Dashboard Widget HTML
 */
class GscDashboardRenderer {

	/**
	 * Render GSC metrics widget
	 *
	 * @param array<string, mixed>|false $metrics GSC metrics data.
	 * @param array<int, array<string, mixed>> $top_pages Top performing pages.
	 * @return void
	 */
	public function render( $metrics, array $top_pages ): void {
		if ( ! $metrics ) {
			$this->render_no_data_message();
			return;
		}

		$this->render_header();
		$this->render_metrics_grid( $metrics );
		$this->render_top_pages_table( $top_pages );
	}

	/**
	 * Render no data message
	 *
	 * @return void
	 */
	private function render_no_data_message(): void {
		?>
		<div class="fp-seo-alert fp-seo-alert--info" style="margin: 20px 0;">
			ℹ️ <?php esc_html_e( 'Google Search Console data not available. Check your connection in Settings → GSC.', 'fp-seo-performance' ); ?>
		</div>
		<?php
	}

	/**
	 * Render widget header
	 *
	 * @return void
	 */
	private function render_header(): void {
		?>
		<h2 style="margin-top: 32px; font-size: 20px; font-weight: 600; color: #111827;">
			📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
		</h2>
		<?php
	}

	/**
	 * Render metrics grid
	 *
	 * @param array<string, mixed> $metrics GSC metrics data.
	 * @return void
	 */
	private function render_metrics_grid( array $metrics ): void {
		?>
		<div class="fp-seo-gsc-metrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0 32px;">
			<?php $this->render_metric_stat( '🖱️', __( 'Clicks', 'fp-seo-performance' ), number_format_i18n( $metrics['clicks'] ), true ); ?>
			<?php $this->render_metric_stat( '👁️', __( 'Impressions', 'fp-seo-performance' ), number_format_i18n( $metrics['impressions'] ), false ); ?>
			<?php $this->render_metric_stat( '📈', __( 'CTR', 'fp-seo-performance' ), $metrics['ctr'] . '%', false ); ?>
			<?php $this->render_metric_stat( '🎯', __( 'Avg Position', 'fp-seo-performance' ), (string) $metrics['position'], false ); ?>
		</div>
		<?php
	}

	/**
	 * Render a single metric stat
	 *
	 * @param string $icon Icon emoji.
	 * @param string $label Metric label.
	 * @param string $value Metric value.
	 * @param bool   $is_success Whether to show success styling.
	 * @return void
	 */
	private function render_metric_stat( string $icon, string $label, string $value, bool $is_success ): void {
		$class = $is_success ? 'fp-seo-stat fp-seo-stat--success' : 'fp-seo-stat';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<span class="fp-seo-stat__icon"><?php echo esc_html( $icon ); ?></span>
			<div class="fp-seo-stat__content">
				<span class="fp-seo-stat__label"><?php echo esc_html( $label ); ?></span>
				<span class="fp-seo-stat__value"><?php echo esc_html( $value ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render top pages table
	 *
	 * @param array<int, array<string, mixed>> $top_pages Top performing pages.
	 * @return void
	 */
	private function render_top_pages_table( array $top_pages ): void {
		if ( empty( $top_pages ) ) {
			return;
		}
		?>
		<h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">
			🏆 <?php esc_html_e( 'Top Performing Pages', 'fp-seo-performance' ); ?>
		</h3>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'fp-seo-performance' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'fp-seo-performance' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?></th>
					<th><?php esc_html_e( 'Position', 'fp-seo-performance' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_pages as $page ) : ?>
					<?php
					$post_id = url_to_postid( $page['url'] );
					$title   = $post_id ? get_the_title( $post_id ) : parse_url( $page['url'], PHP_URL_PATH );
					$edit_url = $post_id ? get_edit_post_link( $post_id ) : '';
					?>
					<tr>
						<td>
							<?php if ( $edit_url ) : ?>
								<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank">
									<strong><?php echo esc_html( $title ); ?></strong>
								</a>
							<?php else : ?>
								<strong><?php echo esc_html( $title ); ?></strong>
							<?php endif; ?>
							<br>
							<small style="color: #6b7280;"><?php echo esc_html( $page['url'] ); ?></small>
						</td>
						<td><strong><?php echo esc_html( number_format_i18n( $page['clicks'] ) ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( $page['impressions'] ) ); ?></td>
						<td><?php echo esc_html( $page['ctr'] ); ?>%</td>
						<td><?php echo esc_html( $page['position'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}

