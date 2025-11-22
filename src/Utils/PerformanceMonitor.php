<?php
/**
 * Performance monitoring and metrics collection.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Utils\Logger;

/**
 * Performance monitoring system with detailed metrics.
 */
class PerformanceMonitor {

	/**
	 * Performance metrics.
	 *
	 * @var array<string, mixed>
	 */
	private array $metrics = [];

	/**
	 * Start times for timing.
	 *
	 * @var array<string, float>
	 */
	private array $start_times = [];

	/**
	 * Memory usage tracking.
	 *
	 * @var array<string, int>
	 */
	private array $memory_usage = [];

	/**
	 * Database query tracking.
	 *
	 * @var array<string, mixed>
	 */
	private array $db_queries = [];

	/**
	 * Cache hit/miss tracking.
	 *
	 * @var array<string, int>
	 */
	private array $cache_stats = [
		'hits' => 0,
		'misses' => 0,
		'sets' => 0,
		'deletes' => 0,
	];

	/**
	 * API call tracking.
	 *
	 * @var array<string, mixed>
	 */
	private array $api_calls = [];

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Start timing an operation.
	 *
	 * @param string $operation Operation name.
	 * @return void
	 */
	public function start_timer( string $operation ): void {
		$this->start_times[ $operation ] = microtime( true );
	}

	/**
	 * End timing an operation.
	 *
	 * @param string $operation Operation name.
	 * @return float Execution time in seconds.
	 */
	public function end_timer( string $operation ): float {
		if ( ! isset( $this->start_times[ $operation ] ) ) {
			return 0.0;
		}

		$execution_time = microtime( true ) - $this->start_times[ $operation ];
		unset( $this->start_times[ $operation ] );

		// Store the metric
		$this->metrics[ $operation ] = [
			'execution_time' => $execution_time,
			'timestamp' => time(),
			'memory_peak' => memory_get_peak_usage( true ),
			'memory_usage' => memory_get_usage( true ),
		];

		return $execution_time;
	}

	/**
	 * Record memory usage.
	 *
	 * @param string $operation Operation name.
	 * @return void
	 */
	public function record_memory( string $operation ): void {
		$this->memory_usage[ $operation ] = [
			'peak' => memory_get_peak_usage( true ),
			'current' => memory_get_usage( true ),
			'timestamp' => time(),
		];
	}

	/**
	 * Record database query.
	 *
	 * @param string $query SQL query.
	 * @param float  $execution_time Query execution time.
	 * @param int    $rows_affected Number of rows affected.
	 * @return void
	 */
	public function record_db_query( string $query, float $execution_time, int $rows_affected = 0 ): void {
		$this->db_queries[] = [
			'query' => $query,
			'execution_time' => $execution_time,
			'rows_affected' => $rows_affected,
			'timestamp' => time(),
		];
	}

	/**
	 * Record cache operation.
	 *
	 * @param string $operation Cache operation (hit, miss, set, delete).
	 * @return void
	 */
	public function record_cache_operation( string $operation ): void {
		if ( isset( $this->cache_stats[ $operation ] ) ) {
			$this->cache_stats[ $operation ]++;
		}
	}

	/**
	 * Record API call.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param float  $execution_time Execution time.
	 * @param int    $response_code HTTP response code.
	 * @param int    $response_size Response size in bytes.
	 * @return void
	 */
	public function record_api_call( string $endpoint, string $method, float $execution_time, int $response_code, int $response_size = 0 ): void {
		$this->api_calls[] = [
			'endpoint' => $endpoint,
			'method' => $method,
			'execution_time' => $execution_time,
			'response_code' => $response_code,
			'response_size' => $response_size,
			'timestamp' => time(),
		];
	}

	/**
	 * Get performance summary.
	 *
	 * @return array<string, mixed>
	 */
	public function get_summary(): array {
		$total_execution_time = array_sum( array_column( $this->metrics, 'execution_time' ) );
		$total_db_queries = count( $this->db_queries );
		$total_api_calls = count( $this->api_calls );
		$total_db_time = array_sum( array_column( $this->db_queries, 'execution_time' ) );
		$total_api_time = array_sum( array_column( $this->api_calls, 'execution_time' ) );

		$memory_peak = memory_get_peak_usage( true );
		$memory_current = memory_get_usage( true );

		return [
			'execution_time' => [
				'total' => $total_execution_time,
				'average' => count( $this->metrics ) > 0 ? $total_execution_time / count( $this->metrics ) : 0,
				'operations' => $this->metrics,
			],
			'database' => [
				'total_queries' => $total_db_queries,
				'total_time' => $total_db_time,
				'average_time' => $total_db_queries > 0 ? $total_db_time / $total_db_queries : 0,
				'slowest_query' => $this->get_slowest_query(),
			],
			'api_calls' => [
				'total_calls' => $total_api_calls,
				'total_time' => $total_api_time,
				'average_time' => $total_api_calls > 0 ? $total_api_time / $total_api_calls : 0,
				'slowest_call' => $this->get_slowest_api_call(),
			],
			'cache' => $this->cache_stats,
			'memory' => [
				'peak' => $memory_peak,
				'current' => $memory_current,
				'peak_mb' => round( $memory_peak / 1024 / 1024, 2 ),
				'current_mb' => round( $memory_current / 1024 / 1024, 2 ),
			],
			'performance_score' => $this->calculate_performance_score(),
		];
	}

	/**
	 * Get slowest database query.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_slowest_query(): ?array {
		if ( empty( $this->db_queries ) ) {
			return null;
		}

		$slowest = $this->db_queries[0];
		foreach ( $this->db_queries as $query ) {
			if ( $query['execution_time'] > $slowest['execution_time'] ) {
				$slowest = $query;
			}
		}

		return $slowest;
	}

	/**
	 * Get slowest API call.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_slowest_api_call(): ?array {
		if ( empty( $this->api_calls ) ) {
			return null;
		}

		$slowest = $this->api_calls[0];
		foreach ( $this->api_calls as $call ) {
			if ( $call['execution_time'] > $slowest['execution_time'] ) {
				$slowest = $call;
			}
		}

		return $slowest;
	}

	/**
	 * Calculate performance score (0-100).
	 *
	 * @return int
	 */
	private function calculate_performance_score(): int {
		$score = 100;

		// Deduct points for slow operations
		foreach ( $this->metrics as $operation => $data ) {
			if ( $data['execution_time'] > 1.0 ) {
				$score -= 10; // 10 points for operations over 1 second
			} elseif ( $data['execution_time'] > 0.5 ) {
				$score -= 5; // 5 points for operations over 0.5 seconds
			}
		}

		// Deduct points for slow database queries
		foreach ( $this->db_queries as $query ) {
			if ( $query['execution_time'] > 0.1 ) {
				$score -= 5; // 5 points for queries over 0.1 seconds
			}
		}

		// Deduct points for slow API calls
		foreach ( $this->api_calls as $call ) {
			if ( $call['execution_time'] > 2.0 ) {
				$score -= 10; // 10 points for API calls over 2 seconds
			} elseif ( $call['execution_time'] > 1.0 ) {
				$score -= 5; // 5 points for API calls over 1 second
			}
		}

		// Deduct points for high memory usage
		$memory_peak_mb = memory_get_peak_usage( true ) / 1024 / 1024;
		if ( $memory_peak_mb > 128 ) {
			$score -= 15; // 15 points for memory usage over 128MB
		} elseif ( $memory_peak_mb > 64 ) {
			$score -= 10; // 10 points for memory usage over 64MB
		}

		return max( 0, $score );
	}

	/**
	 * Get detailed metrics for a specific operation.
	 *
	 * @param string $operation Operation name.
	 * @return array<string, mixed>|null
	 */
	public function get_operation_metrics( string $operation ): ?array {
		return $this->metrics[ $operation ] ?? null;
	}

	/**
	 * Get database query metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_db_metrics(): array {
		return [
			'total_queries' => count( $this->db_queries ),
			'total_time' => array_sum( array_column( $this->db_queries, 'execution_time' ) ),
			'average_time' => count( $this->db_queries ) > 0 ? array_sum( array_column( $this->db_queries, 'execution_time' ) ) / count( $this->db_queries ) : 0,
			'queries' => $this->db_queries,
		];
	}

	/**
	 * Get API call metrics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_api_metrics(): array {
		return [
			'total_calls' => count( $this->api_calls ),
			'total_time' => array_sum( array_column( $this->api_calls, 'execution_time' ) ),
			'average_time' => count( $this->api_calls ) > 0 ? array_sum( array_column( $this->api_calls, 'execution_time' ) ) / count( $this->api_calls ) : 0,
			'calls' => $this->api_calls,
		];
	}

	/**
	 * Get cache metrics.
	 *
	 * @return array<string, int>
	 */
	public function get_cache_metrics(): array {
		$total = $this->cache_stats['hits'] + $this->cache_stats['misses'];
		$hit_rate = $total > 0 ? round( ( $this->cache_stats['hits'] / $total ) * 100, 2 ) : 0;

		return array_merge( $this->cache_stats, [
			'hit_rate' => $hit_rate,
		] );
	}

	/**
	 * Reset all metrics.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->metrics = [];
		$this->start_times = [];
		$this->memory_usage = [];
		$this->db_queries = [];
		$this->cache_stats = [
			'hits' => 0,
			'misses' => 0,
			'sets' => 0,
			'deletes' => 0,
		];
		$this->api_calls = [];
	}

	/**
	 * Export metrics to JSON.
	 *
	 * @return string
	 */
	public function export_json(): string {
		return wp_json_encode( $this->get_summary(), JSON_PRETTY_PRINT );
	}

	/**
	 * Log performance metrics.
	 *
	 * @return void
	 */
	public function log_metrics(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$summary = $this->get_summary();
		Logger::debug( 'Performance Metrics', array( 'metrics' => $this->export_json() ) );
	}
}
