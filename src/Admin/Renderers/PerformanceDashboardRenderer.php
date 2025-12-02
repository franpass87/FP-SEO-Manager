<?php
/**
 * Renderer for Performance Dashboard
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use function esc_attr;
use function esc_html;
use function esc_html_e;
use function __;
use function ucfirst;

/**
 * Renders the Performance Dashboard HTML
 */
class PerformanceDashboardRenderer {

	/**
	 * Render the dashboard page
	 *
	 * @param array<string, mixed> $health_data Health check data.
	 * @param array<string, mixed> $performance_data Performance metrics.
	 * @param array<string, mixed> $db_stats Database statistics.
	 * @param array<string, mixed> $asset_stats Asset optimization statistics.
	 * @return void
	 */
	public function render(
		array $health_data,
		array $performance_data,
		array $db_stats,
		array $asset_stats
	): void {
		$this->render_header();
		$this->render_intro_banner();
		$this->render_dashboard_grid( $health_data, $performance_data, $db_stats, $asset_stats );
		$this->render_loading_overlay();
		$this->render_footer();
	}

	/**
	 * Render dashboard header
	 *
	 * @return void
	 */
	private function render_header(): void {
		?>
		<div class="wrap fp-seo-dashboard">
			<h1><?php esc_html_e( 'Performance Dashboard', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Monitora le performance del plugin e ottimizza database, cache e risorse', 'fp-seo-performance' ); ?></p>
		<?php
	}

	/**
	 * Render intro banner
	 *
	 * @return void
	 */
	private function render_intro_banner(): void {
		?>
		<!-- Banner introduttivo -->
		<div class="fp-seo-intro-banner">
			<div class="fp-seo-intro-icon">⚡</div>
			<div class="fp-seo-intro-content">
				<h2><?php esc_html_e( 'Performance Dashboard: Monitora la Salute del Plugin', 'fp-seo-performance' ); ?></h2>
				<p><?php esc_html_e( 'Questo dashboard ti mostra in tempo reale lo stato di salute del plugin e le performance del sistema. Puoi:', 'fp-seo-performance' ); ?></p>
				<ul class="fp-seo-intro-list">
					<li>✓ <?php esc_html_e( 'Verificare lo stato di salute generale con un punteggio da 0 a 100', 'fp-seo-performance' ); ?></li>
					<li>✓ <?php esc_html_e( 'Monitorare execution time, query DB e memoria', 'fp-seo-performance' ); ?></li>
					<li>✓ <?php esc_html_e( 'Ottimizzare il database con un click', 'fp-seo-performance' ); ?></li>
					<li>✓ <?php esc_html_e( 'Gestire cache e asset per velocizzare il sito', 'fp-seo-performance' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dashboard grid with all cards
	 *
	 * @param array<string, mixed> $health_data Health check data.
	 * @param array<string, mixed> $performance_data Performance metrics.
	 * @param array<string, mixed> $db_stats Database statistics.
	 * @param array<string, mixed> $asset_stats Asset optimization statistics.
	 * @return void
	 */
	private function render_dashboard_grid(
		array $health_data,
		array $performance_data,
		array $db_stats,
		array $asset_stats
	): void {
		?>
		<div class="fp-seo-dashboard-grid">
			<?php $this->render_health_overview( $health_data ); ?>
			<?php $this->render_performance_metrics( $performance_data ); ?>
			<?php $this->render_database_health( $db_stats ); ?>
			<?php $this->render_asset_optimization( $asset_stats ); ?>
			<?php $this->render_cache_status( $performance_data ); ?>
			<?php $this->render_recommendations( $health_data ); ?>
		</div>
		<?php
	}

	/**
	 * Render health overview card
	 *
	 * @param array<string, mixed> $health_data Health check data.
	 * @return void
	 */
	private function render_health_overview( array $health_data ): void {
		?>
		<!-- Health Overview -->
		<div class="fp-seo-card fp-seo-health-overview">
			<h2>
				<?php esc_html_e( 'Health Overview', 'fp-seo-performance' ); ?>
				<span class="fp-seo-tooltip-trigger" title="<?php echo esc_attr( __( 'Punteggio di salute complessivo del plugin calcolato su: cache, database, API, memoria. 100 = perfetto, 80-99 = buono, 60-79 = da migliorare, <60 = critico', 'fp-seo-performance' ) ); ?>">ℹ️</span>
			</h2>
			<div class="health-score">
				<div class="score-circle score-<?php echo esc_attr( $health_data['status'] ); ?>">
					<span class="score-number"><?php echo esc_html( (string) $health_data['overall_score'] ); ?></span>
					<span class="score-label"><?php esc_html_e( 'Score', 'fp-seo-performance' ); ?></span>
				</div>
				<div class="health-status">
					<span class="status-badge status-<?php echo esc_attr( $health_data['status'] ); ?>">
						<?php echo esc_html( ucfirst( $health_data['status'] ) ); ?>
					</span>
					<p class="health-description">
						<?php $this->render_health_description( $health_data['status'] ); ?>
					</p>
				</div>
			</div>
			<button type="button" class="button button-primary" id="run-health-check">
				<?php esc_html_e( 'Run Health Check', 'fp-seo-performance' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render performance metrics card
	 *
	 * @param array<string, mixed> $performance_data Performance metrics.
	 * @return void
	 */
	private function render_performance_metrics( array $performance_data ): void {
		?>
		<!-- Performance Metrics -->
		<div class="fp-seo-card fp-seo-performance-metrics">
			<h2>
				<?php esc_html_e( 'Performance Metrics', 'fp-seo-performance' ); ?>
				<span class="fp-seo-tooltip-trigger" title="<?php echo esc_attr( __( 'Metriche in tempo reale delle performance del plugin. Valori bassi = buono.', 'fp-seo-performance' ) ); ?>">ℹ️</span>
			</h2>
			<div class="metrics-grid">
				<?php $this->render_metric_item( '⏱️', __( 'Execution Time', 'fp-seo-performance' ), esc_attr( __( 'Tempo medio di esecuzione delle operazioni del plugin. Ottimale: <0.5s, Buono: <1s, Da migliorare: >1s', 'fp-seo-performance' ) ), round( $performance_data['execution_time']['total'], 3 ) . 's', $performance_data['execution_time']['total'] < 1 ); ?>
				<?php $this->render_metric_item( '🗄️', __( 'Database Queries', 'fp-seo-performance' ), esc_attr( __( 'Numero di query al database. Meno query = sito più veloce. Ottimale: <50, Buono: <100, Troppo: >150', 'fp-seo-performance' ) ), (string) $performance_data['database']['total_queries'], $performance_data['database']['total_queries'] < 100 ); ?>
				<?php $this->render_metric_item( '🔌', __( 'API Calls', 'fp-seo-performance' ), esc_attr( __( 'Chiamate a servizi esterni (OpenAI, Google API, ecc). Troppe chiamate rallentano il sito. Usa la cache!', 'fp-seo-performance' ) ), (string) $performance_data['api_calls']['total_calls'], $performance_data['api_calls']['total_calls'] < 10 ); ?>
				<?php $this->render_metric_item( '💾', __( 'Memory Usage', 'fp-seo-performance' ), esc_attr( __( 'Memoria RAM usata dal plugin. Ottimale: <50MB, Buono: <100MB, Alto: >150MB. Se troppo alta, disattiva funzionalità non necessarie.', 'fp-seo-performance' ) ), $performance_data['memory']['peak_mb'] . 'MB', $performance_data['memory']['peak_mb'] < 100 ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single metric item
	 *
	 * @param string $icon Icon emoji.
	 * @param string $label Metric label.
	 * @param string $tooltip Tooltip text.
	 * @param string $value Metric value.
	 * @param bool   $is_good Whether the value is good.
	 * @return void
	 */
	private function render_metric_item( string $icon, string $label, string $tooltip, string $value, bool $is_good ): void {
		?>
		<div class="metric-item">
			<span class="metric-icon"><?php echo esc_html( $icon ); ?></span>
			<span class="metric-label">
				<?php echo esc_html( $label ); ?>
				<span class="fp-seo-tooltip-trigger" title="<?php echo esc_attr( $tooltip ); ?>">ℹ️</span>
			</span>
			<span class="metric-value <?php echo $is_good ? 'metric-good' : 'metric-warn'; ?>">
				<?php echo esc_html( $value ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render database health card
	 *
	 * @param array<string, mixed> $db_stats Database statistics.
	 * @return void
	 */
	private function render_database_health( array $db_stats ): void {
		?>
		<!-- Database Health -->
		<div class="fp-seo-card fp-seo-database-health">
			<h2><?php esc_html_e( 'Database Health', 'fp-seo-performance' ); ?></h2>
			<div class="db-stats">
				<div class="db-stat">
					<span class="stat-label"><?php esc_html_e( 'Query Cache', 'fp-seo-performance' ); ?></span>
					<span class="stat-value <?php echo $db_stats['query_cache']['query_cache_size'] > 0 ? 'status-good' : 'status-bad'; ?>">
						<?php echo $db_stats['query_cache']['query_cache_size'] > 0 ? esc_html__( 'Enabled', 'fp-seo-performance' ) : esc_html__( 'Disabled', 'fp-seo-performance' ); ?>
					</span>
				</div>
				<div class="db-stat">
					<span class="stat-label"><?php esc_html_e( 'Slow Query Log', 'fp-seo-performance' ); ?></span>
					<span class="stat-value <?php echo $db_stats['slow_query_log_enabled'] ? 'status-good' : 'status-bad'; ?>">
						<?php echo $db_stats['slow_query_log_enabled'] ? esc_html__( 'Enabled', 'fp-seo-performance' ) : esc_html__( 'Disabled', 'fp-seo-performance' ); ?>
					</span>
				</div>
			</div>
			<button type="button" class="button" id="optimize-database">
				<?php esc_html_e( 'Optimize Database', 'fp-seo-performance' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render asset optimization card
	 *
	 * @param array<string, mixed> $asset_stats Asset optimization statistics.
	 * @return void
	 */
	private function render_asset_optimization( array $asset_stats ): void {
		?>
		<!-- Asset Optimization -->
		<div class="fp-seo-card fp-seo-asset-optimization">
			<h2><?php esc_html_e( 'Asset Optimization', 'fp-seo-performance' ); ?></h2>
			<div class="asset-stats">
				<div class="asset-stat">
					<span class="stat-label"><?php esc_html_e( 'Compression Ratio', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) $asset_stats['compression_ratio'] ); ?>%</span>
				</div>
				<div class="asset-stat">
					<span class="stat-label"><?php esc_html_e( 'Space Saved', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) $asset_stats['space_saved_mb'] ); ?>MB</span>
				</div>
				<div class="asset-stat">
					<span class="stat-label"><?php esc_html_e( 'Minified Files', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) ( $asset_stats['minified_css'] + $asset_stats['minified_js'] ) ); ?></span>
				</div>
			</div>
			<button type="button" class="button" id="optimize-assets">
				<?php esc_html_e( 'Optimize Assets', 'fp-seo-performance' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render cache status card
	 *
	 * @param array<string, mixed> $performance_data Performance metrics.
	 * @return void
	 */
	private function render_cache_status( array $performance_data ): void {
		?>
		<!-- Cache Status -->
		<div class="fp-seo-card fp-seo-cache-status">
			<h2><?php esc_html_e( 'Cache Status', 'fp-seo-performance' ); ?></h2>
			<div class="cache-stats">
				<div class="cache-stat">
					<span class="stat-label"><?php esc_html_e( 'Hit Rate', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) $performance_data['cache']['hit_rate'] ); ?>%</span>
				</div>
				<div class="cache-stat">
					<span class="stat-label"><?php esc_html_e( 'Cache Hits', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) $performance_data['cache']['hits'] ); ?></span>
				</div>
				<div class="cache-stat">
					<span class="stat-label"><?php esc_html_e( 'Cache Misses', 'fp-seo-performance' ); ?></span>
					<span class="stat-value"><?php echo esc_html( (string) $performance_data['cache']['misses'] ); ?></span>
				</div>
			</div>
			<button type="button" class="button" id="clear-cache">
				<?php esc_html_e( 'Clear Cache', 'fp-seo-performance' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render recommendations card
	 *
	 * @param array<string, mixed> $health_data Health check data.
	 * @return void
	 */
	private function render_recommendations( array $health_data ): void {
		?>
		<!-- Recommendations -->
		<div class="fp-seo-card fp-seo-recommendations">
			<h2><?php esc_html_e( 'Recommendations', 'fp-seo-performance' ); ?></h2>
			<?php if ( ! empty( $health_data['recommendations'] ) ) : ?>
				<ul class="recommendations-list">
					<?php foreach ( $health_data['recommendations'] as $recommendation ) : ?>
						<li class="recommendation-item">
							<span class="recommendation-icon">⚠️</span>
							<span class="recommendation-text"><?php echo esc_html( $recommendation ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="no-recommendations"><?php esc_html_e( 'No recommendations at this time. Your plugin is running optimally!', 'fp-seo-performance' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render loading overlay
	 *
	 * @return void
	 */
	private function render_loading_overlay(): void {
		?>
		<!-- Loading Overlay -->
		<div id="fp-seo-loading-overlay" class="fp-seo-loading-overlay" style="display: none;">
			<div class="fp-seo-loading-spinner"></div>
			<p><?php esc_html_e( 'Processing...', 'fp-seo-performance' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render dashboard footer
	 *
	 * @return void
	 */
	private function render_footer(): void {
		?>
		</div>
		<?php
	}

	/**
	 * Render health description
	 *
	 * @param string $status Health status.
	 * @return void
	 */
	private function render_health_description( string $status ): void {
		$descriptions = array(
			'excellent' => __( 'Your plugin is running at peak performance!', 'fp-seo-performance' ),
			'good'      => __( 'Your plugin is running well with minor optimizations possible.', 'fp-seo-performance' ),
			'warning'   => __( 'Your plugin needs some attention to improve performance.', 'fp-seo-performance' ),
			'critical'  => __( 'Your plugin requires immediate attention for optimal performance.', 'fp-seo-performance' ),
		);

		echo esc_html( $descriptions[ $status ] ?? $descriptions['good'] );
	}
}

