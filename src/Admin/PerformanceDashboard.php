<?php
/**
 * Performance dashboard for monitoring plugin health.
 *
 * @package FP\SEO\Admin
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Utils\HealthChecker;
use FP\SEO\Utils\PerformanceMonitor;
use FP\SEO\Utils\DatabaseOptimizer;
use FP\SEO\Utils\AssetOptimizer;

/**
 * Performance dashboard page.
 */
class PerformanceDashboard {

	/**
	 * Health checker instance.
	 */
	private HealthChecker $health_checker;

	/**
	 * Performance monitor instance.
	 */
	private PerformanceMonitor $monitor;

	/**
	 * Database optimizer instance.
	 */
	private DatabaseOptimizer $db_optimizer;

	/**
	 * Asset optimizer instance.
	 */
	private ?AssetOptimizer $asset_optimizer;

	/**
	 * Constructor.
	 */
	public function __construct( HealthChecker $health_checker, PerformanceMonitor $monitor, DatabaseOptimizer $db_optimizer, ?AssetOptimizer $asset_optimizer = null ) {
		$this->health_checker = $health_checker;
		$this->monitor = $monitor;
		$this->db_optimizer = $db_optimizer;
		$this->asset_optimizer = $asset_optimizer;
	}

	/**
	 * Register dashboard page.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'wp_ajax_fp_seo_run_health_check', [ $this, 'ajax_run_health_check' ] );
		add_action( 'wp_ajax_fp_seo_optimize_database', [ $this, 'ajax_optimize_database' ] );
		add_action( 'wp_ajax_fp_seo_optimize_assets', [ $this, 'ajax_optimize_assets' ] );
		add_action( 'wp_ajax_fp_seo_clear_cache', [ $this, 'ajax_clear_cache' ] );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Performance Dashboard', 'fp-seo-performance' ),
			__( 'Performance', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-performance-dashboard',
			[ $this, 'render_dashboard' ]
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard(): void {
		$health_data = $this->health_checker->run_health_check();
		$performance_data = $this->monitor->get_summary();
		$db_stats = $this->db_optimizer->get_performance_stats();
		$asset_stats = $this->asset_optimizer ? $this->asset_optimizer->get_optimization_stats() : array();

		?>
		<div class="wrap fp-seo-dashboard">
			<h1><?php esc_html_e( 'Performance Dashboard', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Monitora le performance del plugin e ottimizza database, cache e risorse', 'fp-seo-performance' ); ?></p>

			<!-- Banner introduttivo -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">⚡</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Performance Dashboard: Monitora la Salute del Plugin', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'Questo dashboard ti mostra in tempo reale lo stato di salute del plugin e le performance del sistema. Puoi:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li>✓ Verificare lo stato di salute generale con un punteggio da 0 a 100</li>
						<li>✓ Monitorare execution time, query DB e memoria</li>
						<li>✓ Ottimizzare il database con un click</li>
						<li>✓ Gestire cache e asset per velocizzare il sito</li>
					</ul>
				</div>
			</div>
			
			<div class="fp-seo-dashboard-grid">
				<!-- Health Overview -->
				<div class="fp-seo-card fp-seo-health-overview">
					<h2>
						<?php esc_html_e( 'Health Overview', 'fp-seo-performance' ); ?>
						<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Punteggio di salute complessivo del plugin calcolato su: cache, database, API, memoria. 100 = perfetto, 80-99 = buono, 60-79 = da migliorare, <60 = critico', 'fp-seo-performance' ); ?>">ℹ️</span>
					</h2>
					<div class="health-score">
						<div class="score-circle score-<?php echo esc_attr( $health_data['status'] ); ?>">
							<span class="score-number"><?php echo esc_html( $health_data['overall_score'] ); ?></span>
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

				<!-- Performance Metrics -->
				<div class="fp-seo-card fp-seo-performance-metrics">
					<h2>
						<?php esc_html_e( 'Performance Metrics', 'fp-seo-performance' ); ?>
						<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Metriche in tempo reale delle performance del plugin. Valori bassi = buono.', 'fp-seo-performance' ); ?>">ℹ️</span>
					</h2>
					<div class="metrics-grid">
						<div class="metric-item">
							<span class="metric-icon">⏱️</span>
							<span class="metric-label">
								<?php esc_html_e( 'Execution Time', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Tempo medio di esecuzione delle operazioni del plugin. Ottimale: <0.5s, Buono: <1s, Da migliorare: >1s', 'fp-seo-performance' ); ?>">ℹ️</span>
							</span>
							<span class="metric-value <?php echo $performance_data['execution_time']['total'] < 1 ? 'metric-good' : 'metric-warn'; ?>">
								<?php echo esc_html( round( $performance_data['execution_time']['total'], 3 ) ); ?>s
							</span>
						</div>
						<div class="metric-item">
							<span class="metric-icon">🗄️</span>
							<span class="metric-label">
								<?php esc_html_e( 'Database Queries', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero di query al database. Meno query = sito più veloce. Ottimale: <50, Buono: <100, Troppo: >150', 'fp-seo-performance' ); ?>">ℹ️</span>
							</span>
							<span class="metric-value <?php echo $performance_data['database']['total_queries'] < 100 ? 'metric-good' : 'metric-warn'; ?>">
								<?php echo esc_html( $performance_data['database']['total_queries'] ); ?>
							</span>
						</div>
						<div class="metric-item">
							<span class="metric-icon">🔌</span>
							<span class="metric-label">
								<?php esc_html_e( 'API Calls', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Chiamate a servizi esterni (OpenAI, Google API, ecc). Troppe chiamate rallentano il sito. Usa la cache!', 'fp-seo-performance' ); ?>">ℹ️</span>
							</span>
							<span class="metric-value <?php echo $performance_data['api_calls']['total_calls'] < 10 ? 'metric-good' : 'metric-warn'; ?>">
								<?php echo esc_html( $performance_data['api_calls']['total_calls'] ); ?>
							</span>
						</div>
						<div class="metric-item">
							<span class="metric-icon">💾</span>
							<span class="metric-label">
								<?php esc_html_e( 'Memory Usage', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Memoria RAM usata dal plugin. Ottimale: <50MB, Buono: <100MB, Alto: >150MB. Se troppo alta, disattiva funzionalità non necessarie.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</span>
							<span class="metric-value <?php echo $performance_data['memory']['peak_mb'] < 100 ? 'metric-good' : 'metric-warn'; ?>">
								<?php echo esc_html( $performance_data['memory']['peak_mb'] ); ?>MB
							</span>
						</div>
					</div>
				</div>

				<!-- Database Health -->
				<div class="fp-seo-card fp-seo-database-health">
					<h2><?php esc_html_e( 'Database Health', 'fp-seo-performance' ); ?></h2>
					<div class="db-stats">
						<div class="db-stat">
							<span class="stat-label"><?php esc_html_e( 'Query Cache', 'fp-seo-performance' ); ?></span>
							<span class="stat-value <?php echo $db_stats['query_cache']['query_cache_size'] > 0 ? 'status-good' : 'status-bad'; ?>">
								<?php echo $db_stats['query_cache']['query_cache_size'] > 0 ? __( 'Enabled', 'fp-seo-performance' ) : __( 'Disabled', 'fp-seo-performance' ); ?>
							</span>
						</div>
						<div class="db-stat">
							<span class="stat-label"><?php esc_html_e( 'Slow Query Log', 'fp-seo-performance' ); ?></span>
							<span class="stat-value <?php echo $db_stats['slow_query_log_enabled'] ? 'status-good' : 'status-bad'; ?>">
								<?php echo $db_stats['slow_query_log_enabled'] ? __( 'Enabled', 'fp-seo-performance' ) : __( 'Disabled', 'fp-seo-performance' ); ?>
							</span>
						</div>
					</div>
					<button type="button" class="button" id="optimize-database">
						<?php esc_html_e( 'Optimize Database', 'fp-seo-performance' ); ?>
					</button>
				</div>

				<!-- Asset Optimization -->
				<div class="fp-seo-card fp-seo-asset-optimization">
					<h2><?php esc_html_e( 'Asset Optimization', 'fp-seo-performance' ); ?></h2>
					<div class="asset-stats">
						<div class="asset-stat">
							<span class="stat-label"><?php esc_html_e( 'Compression Ratio', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $asset_stats['compression_ratio'] ); ?>%</span>
						</div>
						<div class="asset-stat">
							<span class="stat-label"><?php esc_html_e( 'Space Saved', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $asset_stats['space_saved_mb'] ); ?>MB</span>
						</div>
						<div class="asset-stat">
							<span class="stat-label"><?php esc_html_e( 'Minified Files', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $asset_stats['minified_css'] + $asset_stats['minified_js'] ); ?></span>
						</div>
					</div>
					<button type="button" class="button" id="optimize-assets">
						<?php esc_html_e( 'Optimize Assets', 'fp-seo-performance' ); ?>
					</button>
				</div>

				<!-- Cache Status -->
				<div class="fp-seo-card fp-seo-cache-status">
					<h2><?php esc_html_e( 'Cache Status', 'fp-seo-performance' ); ?></h2>
					<div class="cache-stats">
						<div class="cache-stat">
							<span class="stat-label"><?php esc_html_e( 'Hit Rate', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $performance_data['cache']['hit_rate'] ); ?>%</span>
						</div>
						<div class="cache-stat">
							<span class="stat-label"><?php esc_html_e( 'Cache Hits', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $performance_data['cache']['hits'] ); ?></span>
						</div>
						<div class="cache-stat">
							<span class="stat-label"><?php esc_html_e( 'Cache Misses', 'fp-seo-performance' ); ?></span>
							<span class="stat-value"><?php echo esc_html( $performance_data['cache']['misses'] ); ?></span>
						</div>
					</div>
					<button type="button" class="button" id="clear-cache">
						<?php esc_html_e( 'Clear Cache', 'fp-seo-performance' ); ?>
					</button>
				</div>

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
			</div>

			<!-- Loading Overlay -->
			<div id="fp-seo-loading-overlay" class="fp-seo-loading-overlay" style="display: none;">
				<div class="fp-seo-loading-spinner"></div>
				<p><?php esc_html_e( 'Processing...', 'fp-seo-performance' ); ?></p>
			</div>
		</div>

		<style>
		.fp-seo-dashboard-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 20px;
		}

		.fp-seo-card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}

		.fp-seo-card h2 {
			margin-top: 0;
			margin-bottom: 15px;
			color: #23282d;
		}

		.health-score {
			display: flex;
			align-items: center;
			gap: 20px;
			margin-bottom: 20px;
		}

		.score-circle {
			width: 80px;
			height: 80px;
			border-radius: 50%;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			color: white;
			font-weight: bold;
		}

		.score-circle.score-excellent { background: #46b450; }
		.score-circle.score-good { background: #00a0d2; }
		.score-circle.score-warning { background: #ffb900; }
		.score-circle.score-critical { background: #dc3232; }

		.score-number {
			font-size: 24px;
			line-height: 1;
		}

		.score-label {
			font-size: 12px;
			opacity: 0.9;
		}

		.status-badge {
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: bold;
			text-transform: uppercase;
		}

		.status-badge.status-excellent { background: #46b450; color: white; }
		.status-badge.status-good { background: #00a0d2; color: white; }
		.status-badge.status-warning { background: #ffb900; color: white; }
		.status-badge.status-critical { background: #dc3232; color: white; }

		.metrics-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 15px;
		}

		.metric-item {
			display: flex;
			flex-direction: column;
			gap: 5px;
		}

		.metric-label {
			font-size: 12px;
			color: #666;
		}

		.metric-value {
			font-size: 18px;
			font-weight: bold;
			color: #23282d;
		}

		.db-stats, .asset-stats, .cache-stats {
			display: flex;
			flex-direction: column;
			gap: 10px;
			margin-bottom: 15px;
		}

		.db-stat, .asset-stat, .cache-stat {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.stat-label {
			font-size: 14px;
			color: #666;
		}

		.stat-value {
			font-weight: bold;
		}

		.stat-value.status-good { color: #46b450; }
		.stat-value.status-bad { color: #dc3232; }

		.recommendations-list {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		.recommendation-item {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px 0;
			border-bottom: 1px solid #eee;
		}

		.recommendation-item:last-child {
			border-bottom: none;
		}

		.recommendation-icon {
			font-size: 16px;
		}

		.recommendation-text {
			font-size: 14px;
			color: #666;
		}

		.no-recommendations {
			color: #46b450;
			font-style: italic;
		}

		.fp-seo-loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0,0,0,0.5);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			z-index: 9999;
		}

		.fp-seo-loading-spinner {
			width: 40px;
			height: 40px;
			border: 4px solid #f3f3f3;
			border-top: 4px solid #0073aa;
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin-bottom: 20px;
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			$('#run-health-check').on('click', function() {
				$('#fp-seo-loading-overlay').show();
				
				$.post(ajaxurl, {
					action: 'fp_seo_run_health_check',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_health_check' ) ); ?>'
				}, function(response) {
					$('#fp-seo-loading-overlay').hide();
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});

			$('#optimize-database').on('click', function() {
				$('#fp-seo-loading-overlay').show();
				
				$.post(ajaxurl, {
					action: 'fp_seo_optimize_database',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_optimize_database' ) ); ?>'
				}, function(response) {
					$('#fp-seo-loading-overlay').hide();
					if (response.success) {
						alert('Database optimized successfully!');
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});

			$('#optimize-assets').on('click', function() {
				$('#fp-seo-loading-overlay').show();
				
				$.post(ajaxurl, {
					action: 'fp_seo_optimize_assets',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_optimize_assets' ) ); ?>'
				}, function(response) {
					$('#fp-seo-loading-overlay').hide();
					if (response.success) {
						alert('Assets optimized successfully!');
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});

			$('#clear-cache').on('click', function() {
				$('#fp-seo-loading-overlay').show();
				
				$.post(ajaxurl, {
					action: 'fp_seo_clear_cache',
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_clear_cache' ) ); ?>'
				}, function(response) {
					$('#fp-seo-loading-overlay').hide();
					if (response.success) {
						alert('Cache cleared successfully!');
						location.reload();
					} else {
						alert('Error: ' + response.data);
					}
				});
			});
		});
		</script>

		<style>
		/* Common Styles */
		.fp-seo-dashboard {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-dashboard > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 6px 0;
			font-size: 14px;
			opacity: 0.9;
		}

		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 4px;
			cursor: help;
			opacity: 0.7;
			font-size: 12px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		.metric-icon {
			font-size: 20px;
			margin-right: 8px;
		}

		.metric-value.metric-good {
			color: #059669;
		}

		.metric-value.metric-warn {
			color: #f59e0b;
		}
		</style>
		<?php
	}

	/**
	 * Render health description.
	 *
	 * @param string $status Health status.
	 */
	private function render_health_description( string $status ): void {
		$descriptions = [
			'excellent' => __( 'Your plugin is running at peak performance!', 'fp-seo-performance' ),
			'good' => __( 'Your plugin is running well with minor optimizations possible.', 'fp-seo-performance' ),
			'warning' => __( 'Your plugin needs some attention to improve performance.', 'fp-seo-performance' ),
			'critical' => __( 'Your plugin requires immediate attention for optimal performance.', 'fp-seo-performance' ),
		];

		echo esc_html( $descriptions[ $status ] ?? $descriptions['good'] );
	}

	/**
	 * AJAX handler for health check.
	 */
	public function ajax_run_health_check(): void {
		check_ajax_referer( 'fp_seo_health_check', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$health_data = $this->health_checker->run_health_check();
		
		wp_send_json_success( $health_data );
	}

	/**
	 * AJAX handler for database optimization.
	 */
	public function ajax_optimize_database(): void {
		check_ajax_referer( 'fp_seo_optimize_database', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$results = $this->db_optimizer->optimize_tables();
		
		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for asset optimization.
	 */
	public function ajax_optimize_assets(): void {
		check_ajax_referer( 'fp_seo_optimize_assets', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Trigger asset optimization
		if ( $this->asset_optimizer ) {
			$this->asset_optimizer->optimize_css_assets();
			$this->asset_optimizer->optimize_js_assets();
			$this->asset_optimizer->optimize_image_assets();
		}
		
		wp_send_json_success( 'Assets optimized successfully' );
	}

	/**
	 * AJAX handler for cache clearing.
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'fp_seo_clear_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Clear all caches
		wp_cache_flush();
		
		// Clear plugin-specific cache (use prepared statement for security)
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_fp_seo_%' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_fp_seo_%' ) );
		
		wp_send_json_success( 'Cache cleared successfully' );
	}
}
