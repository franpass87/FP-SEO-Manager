<?php
/**
 * Health check system for monitoring plugin status.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Health check system for monitoring plugin health and performance.
 */
class HealthChecker {

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
	public function __construct( PerformanceMonitor $monitor, DatabaseOptimizer $db_optimizer, ?AssetOptimizer $asset_optimizer = null ) {
		$this->monitor = $monitor;
		$this->db_optimizer = $db_optimizer;
		$this->asset_optimizer = $asset_optimizer;
	}

	/**
	 * Run comprehensive health check.
	 *
	 * @return array<string, mixed>
	 */
	public function run_health_check(): array {
		$checks = [
			'performance' => $this->check_performance(),
			'database' => $this->check_database(),
			'assets' => $this->check_assets(),
			'memory' => $this->check_memory(),
			'cache' => $this->check_cache(),
			'api_connectivity' => $this->check_api_connectivity(),
			'file_permissions' => $this->check_file_permissions(),
			'plugin_conflicts' => $this->check_plugin_conflicts(),
		];

		$overall_score = $this->calculate_overall_score( $checks );
		$status = $this->get_health_status( $overall_score );

		return [
			'overall_score' => $overall_score,
			'status' => $status,
			'checks' => $checks,
			'timestamp' => time(),
			'recommendations' => $this->get_recommendations( $checks ),
		];
	}

	/**
	 * Check performance metrics.
	 *
	 * @return array<string, mixed>
	 */
	private function check_performance(): array {
		$summary = $this->monitor->get_summary();
		$score = $summary['performance_score'];

		$issues = [];
		if ( $score < 80 ) {
			$issues[] = 'Performance score is below optimal threshold';
		}

		if ( $summary['execution_time']['total'] > 5.0 ) {
			$issues[] = 'Total execution time exceeds 5 seconds';
		}

		if ( $summary['database']['total_queries'] > 50 ) {
			$issues[] = 'High number of database queries detected';
		}

		return [
			'score' => $score,
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'metrics' => $summary,
		];
	}

	/**
	 * Check database health.
	 *
	 * @return array<string, mixed>
	 */
	private function check_database(): array {
		$stats = $this->db_optimizer->get_performance_stats();
		$analysis = $this->db_optimizer->analyze_tables();

		$issues = [];
		$score = 100;

		// Check for fragmented tables
		foreach ( $analysis as $table => $data ) {
			if ( $data['fragmentation'] > 10 ) {
				$issues[] = "Table {$table} has high fragmentation ({$data['fragmentation']}%)";
				$score -= 20;
			}
		}

		// Check for slow queries
		if ( ! $stats['slow_query_log_enabled'] ) {
			$issues[] = 'Slow query log is not enabled';
			$score -= 10;
		}

		// Check query cache
		if ( empty( $stats['query_cache']['query_cache_size'] ) || $stats['query_cache']['query_cache_size'] === '0' ) {
			$issues[] = 'Query cache is not enabled';
			$score -= 15;
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'stats' => $stats,
			'analysis' => $analysis,
		];
	}

	/**
	 * Check asset optimization.
	 *
	 * @return array<string, mixed>
	 */
	private function check_assets(): array {
		if ( ! $this->asset_optimizer ) {
			return [
				'status' => 'warning',
				'message' => 'Asset optimizer not available',
				'score' => 50,
				'issues' => ['Asset optimizer not initialized']
			];
		}
		
		$stats = $this->asset_optimizer->get_optimization_stats();
		
		$issues = [];
		$score = 100;

		// Check compression ratio
		if ( $stats['compression_ratio'] < 20 ) {
			$issues[] = 'Asset compression ratio is below 20%';
			$score -= 20;
		}

		// Check for unoptimized assets
		$unoptimized_css = $stats['css_files'] - $stats['minified_css'];
		$unoptimized_js = $stats['js_files'] - $stats['minified_js'];
		$unoptimized_images = $stats['image_files'] - $stats['optimized_images'];

		if ( $unoptimized_css > 0 ) {
			$issues[] = "{$unoptimized_css} CSS files are not minified";
			$score -= 10;
		}

		if ( $unoptimized_js > 0 ) {
			$issues[] = "{$unoptimized_js} JS files are not minified";
			$score -= 10;
		}

		if ( $unoptimized_images > 0 ) {
			$issues[] = "{$unoptimized_images} images are not optimized";
			$score -= 15;
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'stats' => $stats,
		];
	}

	/**
	 * Check memory usage.
	 *
	 * @return array<string, mixed>
	 */
	private function check_memory(): array {
		$memory_peak = memory_get_peak_usage( true );
		$memory_current = memory_get_usage( true );
		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$memory_usage_percent = ( $memory_peak / $memory_limit ) * 100;

		$issues = [];
		$score = 100;

		if ( $memory_usage_percent > 80 ) {
			$issues[] = 'Memory usage is above 80% of limit';
			$score -= 30;
		} elseif ( $memory_usage_percent > 60 ) {
			$issues[] = 'Memory usage is above 60% of limit';
			$score -= 15;
		}

		if ( $memory_peak > 128 * 1024 * 1024 ) { // 128MB
			$issues[] = 'Peak memory usage exceeds 128MB';
			$score -= 20;
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'peak_mb' => round( $memory_peak / 1024 / 1024, 2 ),
			'current_mb' => round( $memory_current / 1024 / 1024, 2 ),
			'usage_percent' => round( $memory_usage_percent, 2 ),
			'limit_mb' => round( $memory_limit / 1024 / 1024, 2 ),
		];
	}

	/**
	 * Check cache health.
	 *
	 * @return array<string, mixed>
	 */
	private function check_cache(): array {
		$cache_stats = $this->monitor->get_cache_metrics();
		
		$issues = [];
		$score = 100;

		// Check hit rate
		if ( $cache_stats['hit_rate'] < 70 ) {
			$issues[] = 'Cache hit rate is below 70%';
			$score -= 25;
		} elseif ( $cache_stats['hit_rate'] < 85 ) {
			$issues[] = 'Cache hit rate is below 85%';
			$score -= 10;
		}

		// Check for cache errors
		if ( $cache_stats['hits'] + $cache_stats['misses'] > 0 ) {
			$error_rate = ( $cache_stats['errors'] ?? 0 ) / ( $cache_stats['hits'] + $cache_stats['misses'] ) * 100;
			if ( $error_rate > 5 ) {
				$issues[] = 'Cache error rate is above 5%';
				$score -= 20;
			}
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'stats' => $cache_stats,
		];
	}

	/**
	 * Check API connectivity.
	 *
	 * @return array<string, mixed>
	 */
	private function check_api_connectivity(): array {
		$apis = [
			'openai' => 'https://api.openai.com/v1/models',
			'google_search_console' => 'https://www.googleapis.com/discovery/v1/apis/searchconsole/v1/rest',
		];

		$issues = [];
		$score = 100;
		$results = [];

		foreach ( $apis as $name => $url ) {
			$start_time = microtime( true );
			$response = wp_remote_get( $url, [
				'timeout' => 10,
				'sslverify' => true,
			] );
			$response_time = microtime( true ) - $start_time;

			$is_success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
			
			$results[ $name ] = [
				'success' => $is_success,
				'response_time' => $response_time,
				'error' => is_wp_error( $response ) ? $response->get_error_message() : null,
			];

			if ( ! $is_success ) {
				$issues[] = "API {$name} is not accessible";
				$score -= 30;
			} elseif ( $response_time > 5.0 ) {
				$issues[] = "API {$name} response time is slow ({$response_time}s)";
				$score -= 10;
			}
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'results' => $results,
		];
	}

	/**
	 * Check file permissions.
	 *
	 * @return array<string, mixed>
	 */
	private function check_file_permissions(): array {
		$directories = [
			WP_CONTENT_DIR . '/uploads/fp-seo/',
			plugin_dir_path( FP_SEO_PERFORMANCE_FILE ) . 'assets/minified/',
		];

		$issues = [];
		$score = 100;

		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				continue;
			}

			$perms = fileperms( $dir );
			$octal = substr( sprintf( '%o', $perms ), -4 );

			if ( $octal < '0755' ) {
				$issues[] = "Directory {$dir} has insufficient permissions ({$octal})";
				$score -= 20;
			}
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
		];
	}

	/**
	 * Check for plugin conflicts.
	 *
	 * @return array<string, mixed>
	 */
	private function check_plugin_conflicts(): array {
		$conflicting_plugins = [
			'yoast-seo/yoast.php',
			'rankmath/rank-math.php',
			'seo-by-rank-math/rank-math.php',
			'all-in-one-seo-pack/all_in_one_seo_pack.php',
			'wordpress-seo/wp-seo.php',
		];

		$active_plugins = get_option( 'active_plugins', [] );
		$conflicts = array_intersect( $conflicting_plugins, $active_plugins );

		$issues = [];
		$score = 100;

		if ( ! empty( $conflicts ) ) {
			foreach ( $conflicts as $plugin ) {
				$issues[] = "Potential conflict with plugin: {$plugin}";
				$score -= 15;
			}
		}

		return [
			'score' => max( 0, $score ),
			'status' => $score >= 80 ? 'good' : ( $score >= 60 ? 'warning' : 'critical' ),
			'issues' => $issues,
			'conflicting_plugins' => $conflicts,
		];
	}

	/**
	 * Calculate overall health score.
	 *
	 * @param array<string, mixed> $checks Health check results.
	 * @return int
	 */
	private function calculate_overall_score( array $checks ): int {
		$total_score = 0;
		$count = 0;

		foreach ( $checks as $check ) {
			if ( isset( $check['score'] ) ) {
				$total_score += $check['score'];
				$count++;
			}
		}

		return $count > 0 ? round( $total_score / $count ) : 0;
	}

	/**
	 * Get health status based on score.
	 *
	 * @param int $score Overall health score.
	 * @return string
	 */
	private function get_health_status( int $score ): string {
		if ( $score >= 90 ) {
			return 'excellent';
		} elseif ( $score >= 80 ) {
			return 'good';
		} elseif ( $score >= 60 ) {
			return 'warning';
		} else {
			return 'critical';
		}
	}

	/**
	 * Get recommendations based on health checks.
	 *
	 * @param array<string, mixed> $checks Health check results.
	 * @return array<string>
	 */
	private function get_recommendations( array $checks ): array {
		$recommendations = [];

		foreach ( $checks as $check_name => $check ) {
			if ( isset( $check['status'] ) && $check['status'] !== 'good' ) {
				switch ( $check_name ) {
					case 'performance':
						$recommendations[] = 'Optimize database queries and enable caching';
						break;
					case 'database':
						$recommendations[] = 'Run database optimization and enable query cache';
						break;
					case 'assets':
						$recommendations[] = 'Minify CSS/JS files and optimize images';
						break;
					case 'memory':
						$recommendations[] = 'Increase memory limit or optimize memory usage';
						break;
					case 'cache':
						$recommendations[] = 'Check cache configuration and increase hit rate';
						break;
					case 'api_connectivity':
						$recommendations[] = 'Check API credentials and network connectivity';
						break;
					case 'file_permissions':
						$recommendations[] = 'Fix file permissions for plugin directories';
						break;
					case 'plugin_conflicts':
						$recommendations[] = 'Review conflicting plugins and disable if necessary';
						break;
				}
			}
		}

		return array_unique( $recommendations );
	}

	/**
	 * Get health check summary for admin notice.
	 *
	 * @return array<string, mixed>
	 */
	public function get_health_summary(): array {
		$health = $this->run_health_check();
		
		return [
			'status' => $health['status'],
			'score' => $health['overall_score'],
			'critical_issues' => $this->count_critical_issues( $health['checks'] ),
			'warnings' => $this->count_warnings( $health['checks'] ),
			'recommendations' => $health['recommendations'],
		];
	}

	/**
	 * Count critical issues.
	 *
	 * @param array<string, mixed> $checks Health check results.
	 * @return int
	 */
	private function count_critical_issues( array $checks ): int {
		$count = 0;
		foreach ( $checks as $check ) {
			if ( isset( $check['status'] ) && $check['status'] === 'critical' ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Count warnings.
	 *
	 * @param array<string, mixed> $checks Health check results.
	 * @return int
	 */
	private function count_warnings( array $checks ): int {
		$count = 0;
		foreach ( $checks as $check ) {
			if ( isset( $check['status'] ) && $check['status'] === 'warning' ) {
				$count++;
			}
		}
		return $count;
	}
}
