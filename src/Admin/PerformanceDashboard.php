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

use FP\SEO\Admin\Renderers\PerformanceDashboardRenderer;
use FP\SEO\Admin\Scripts\PerformanceDashboardScriptsManager;
use FP\SEO\Admin\Styles\PerformanceDashboardStylesManager;
use FP\SEO\Utils\AssetOptimizer;
use FP\SEO\Utils\DatabaseOptimizer;
use FP\SEO\Utils\HealthChecker;
use FP\SEO\Utils\PerformanceMonitor;

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
	 * @var PerformanceDashboardStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var PerformanceDashboardScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * @var PerformanceDashboardRenderer|null
	 */
	private $renderer;

	/**
	 * Constructor.
	 */
	public function __construct( HealthChecker $health_checker, PerformanceMonitor $monitor, DatabaseOptimizer $db_optimizer, ?AssetOptimizer $asset_optimizer = null ) {
		$this->health_checker = $health_checker;
		$this->monitor = $monitor;
		$this->db_optimizer = $db_optimizer;
		$this->asset_optimizer = $asset_optimizer;
		$this->renderer = new PerformanceDashboardRenderer();
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

		// Initialize and register styles and scripts managers
		$this->styles_manager = new PerformanceDashboardStylesManager();
		$this->styles_manager->register_hooks();
		$this->scripts_manager = new PerformanceDashboardScriptsManager();
		$this->scripts_manager->register_hooks();
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

		if ( $this->renderer ) {
			$this->renderer->render( $health_data, $performance_data, $db_stats, $asset_stats );
		}
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
			$this->asset_optimizer->optimize_all();
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
