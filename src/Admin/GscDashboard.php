<?php
/**
 * GSC Dashboard Widget
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Integrations\GscData;

/**
 * Adds GSC widgets to SEO Performance dashboard
 */
class GscDashboard {

	/**
	 * GSC Data instance
	 *
	 * @var GscData
	 */
	private GscData $gsc_data;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->gsc_data = new GscData();
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'fpseo_dashboard_after_quick_stats', array( $this, 'render_gsc_widget' ) );
	}

	/**
	 * Render GSC metrics widget
	 */
	public function render_gsc_widget(): void {
		$options = get_option( 'fp_seo_performance', array() );
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		// Get last 28 days metrics
		$metrics = $this->gsc_data->get_site_metrics( 28 );

		if ( ! $metrics ) {
			?>
			<div class="fp-seo-alert fp-seo-alert--info" style="margin: 20px 0;">
				ℹ️ <?php esc_html_e( 'Google Search Console data not available. Check your connection in Settings → GSC.', 'fp-seo-performance' ); ?>
			</div>
			<?php
			return;
		}

		?>
		<h2 style="margin-top: 32px; font-size: 20px; font-weight: 600; color: #111827;">
			📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
		</h2>

		<div class="fp-seo-gsc-metrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin: 20px 0 32px;">
			<!-- Clicks -->
			<div class="fp-seo-stat fp-seo-stat--success">
				<span class="fp-seo-stat__icon">🖱️</span>
				<div class="fp-seo-stat__content">
					<span class="fp-seo-stat__label"><?php esc_html_e( 'Clicks', 'fp-seo-performance' ); ?></span>
					<span class="fp-seo-stat__value"><?php echo esc_html( number_format_i18n( $metrics['clicks'] ) ); ?></span>
				</div>
			</div>

			<!-- Impressions -->
			<div class="fp-seo-stat">
				<span class="fp-seo-stat__icon">👁️</span>
				<div class="fp-seo-stat__content">
					<span class="fp-seo-stat__label"><?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?></span>
					<span class="fp-seo-stat__value"><?php echo esc_html( number_format_i18n( $metrics['impressions'] ) ); ?></span>
				</div>
			</div>

			<!-- CTR -->
			<div class="fp-seo-stat">
				<span class="fp-seo-stat__icon">📈</span>
				<div class="fp-seo-stat__content">
					<span class="fp-seo-stat__label"><?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?></span>
					<span class="fp-seo-stat__value"><?php echo esc_html( $metrics['ctr'] ); ?>%</span>
				</div>
			</div>

			<!-- Avg Position -->
			<div class="fp-seo-stat">
				<span class="fp-seo-stat__icon">🎯</span>
				<div class="fp-seo-stat__content">
					<span class="fp-seo-stat__label"><?php esc_html_e( 'Avg Position', 'fp-seo-performance' ); ?></span>
					<span class="fp-seo-stat__value"><?php echo esc_html( $metrics['position'] ); ?></span>
				</div>
			</div>
		</div>

		<?php
		// Top Pages
		$top_pages = $this->gsc_data->get_top_pages( 28, 5 );

		if ( ! empty( $top_pages ) ) :
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
		<?php endif; ?>
		<?php
	}
}

