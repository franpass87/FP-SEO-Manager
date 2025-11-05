<?php
/**
 * Database optimization utilities and query optimization.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Database optimization and query performance utilities.
 */
class DatabaseOptimizer {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Performance monitor instance.
	 */
	private PerformanceMonitor $monitor;

	/**
	 * Constructor.
	 */
	public function __construct( PerformanceMonitor $monitor ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->monitor = $monitor;
	}

	/**
	 * Optimize database tables.
	 *
	 * @return array<string, mixed>
	 */
	public function optimize_tables(): array {
		$results = [];
		$tables = $this->get_plugin_tables();

		foreach ( $tables as $table ) {
			$start_time = microtime( true );
			
			// Sanitize table name before using in query
			$safe_table = $this->sanitize_table_name( $table );
			if ( ! $safe_table ) {
				continue;
			}
			
			// Run OPTIMIZE TABLE (table name is already sanitized and escaped)
			$result = $this->wpdb->query( "OPTIMIZE TABLE `{$safe_table}`" );
			
			$execution_time = microtime( true ) - $start_time;
			
			$results[ $table ] = [
				'success' => $result !== false,
				'execution_time' => $execution_time,
				'message' => $result !== false ? 'Optimized successfully' : $this->wpdb->last_error,
			];

			$this->monitor->record_db_query( "OPTIMIZE TABLE `{$safe_table}`", $execution_time );
		}

		return $results;
	}

	/**
	 * Analyze database tables for performance issues.
	 *
	 * @return array<string, mixed>
	 */
	public function analyze_tables(): array {
		$results = [];
		$tables = $this->get_plugin_tables();

		foreach ( $tables as $table ) {
			$start_time = microtime( true );
			
			// Sanitize table name and use prepared statement
			$safe_table = $this->sanitize_table_name( $table );
			if ( ! $safe_table ) {
				continue;
			}
			
			// Get table status (use prepared statement for security)
			$status = $this->wpdb->get_row( $this->wpdb->prepare( "SHOW TABLE STATUS LIKE %s", $safe_table ), ARRAY_A );
			
			$execution_time = microtime( true ) - $start_time;

			if ( $status ) {
				$results[ $table ] = [
					'rows' => (int) $status['Rows'],
					'data_length' => (int) $status['Data_length'],
					'index_length' => (int) $status['Index_length'],
					'data_free' => (int) $status['Data_free'],
					'fragmentation' => $this->calculate_fragmentation( $status ),
					'execution_time' => $execution_time,
				];
			}

			$this->monitor->record_db_query( "SHOW TABLE STATUS LIKE '{$safe_table}'", $execution_time );
		}

		return $results;
	}

	/**
	 * Calculate table fragmentation percentage.
	 *
	 * @param array<string, mixed> $status Table status.
	 * @return float
	 */
	private function calculate_fragmentation( array $status ): float {
		$data_length = (int) $status['Data_length'];
		$data_free = (int) $status['Data_free'];
		
		if ( $data_length === 0 ) {
			return 0.0;
		}

		return round( ( $data_free / $data_length ) * 100, 2 );
	}

	/**
	 * Get plugin-specific tables.
	 *
	 * @return array<string>
	 */
	private function get_plugin_tables(): array {
		$tables = [];
		$prefix = $this->wpdb->prefix . 'fp_seo_';

		// Get all tables with our prefix (use prepared statement)
		$results = $this->wpdb->get_results( 
			$this->wpdb->prepare( "SHOW TABLES LIKE %s", $prefix . '%' ),
			ARRAY_N 
		);

		foreach ( $results as $row ) {
			$tables[] = $row[0];
		}

		return $tables;
	}

	/**
	 * Create database indexes for better performance.
	 *
	 * @return array<string, mixed>
	 */
	public function create_indexes(): array {
		$results = [];
		$indexes = $this->get_recommended_indexes();

		foreach ( $indexes as $table => $table_indexes ) {
			$results[ $table ] = [];

			foreach ( $table_indexes as $index_name => $index_definition ) {
				$start_time = microtime( true );
				
				// Sanitize table and index names
				$safe_table = $this->sanitize_table_name( $table );
				$safe_index_name = $this->sanitize_identifier( $index_name );
				$safe_index_def = $this->sanitize_index_definition( $index_definition );
				
				if ( ! $safe_table || ! $safe_index_name || ! $safe_index_def ) {
					continue;
				}
				
				// Check if index already exists
				$exists = $this->index_exists( $safe_table, $safe_index_name );
				
				if ( ! $exists ) {
					$sql = "CREATE INDEX `{$safe_index_name}` ON `{$safe_table}` ({$safe_index_def})";
					$result = $this->wpdb->query( $sql );
					
					$execution_time = microtime( true ) - $start_time;
					
					$results[ $table ][ $index_name ] = [
						'success' => $result !== false,
						'execution_time' => $execution_time,
						'message' => $result !== false ? 'Index created successfully' : $this->wpdb->last_error,
					];

					$this->monitor->record_db_query( $sql, $execution_time );
				} else {
					$results[ $table ][ $index_name ] = [
						'success' => true,
						'execution_time' => 0,
						'message' => 'Index already exists',
					];
				}
			}
		}

		return $results;
	}

	/**
	 * Check if index exists.
	 *
	 * @param string $table Table name.
	 * @param string $index_name Index name.
	 * @return bool
	 */
	private function index_exists( string $table, string $index_name ): bool {
		$result = $this->wpdb->get_var( 
			$this->wpdb->prepare( 
				"SHOW INDEX FROM `{$table}` WHERE Key_name = %s", 
				$index_name 
			) 
		);
		
		return ! is_null( $result );
	}

	/**
	 * Get recommended indexes for plugin tables.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_recommended_indexes(): array {
		$prefix = $this->wpdb->prefix;

		return [
			$prefix . 'fp_seo_scores' => [
				'idx_post_id' => 'post_id',
				'idx_recorded_at' => 'recorded_at',
				'idx_post_id_recorded_at' => 'post_id, recorded_at',
			],
			$prefix . 'fp_seo_analysis' => [
				'idx_post_id' => 'post_id',
				'idx_check_type' => 'check_type',
				'idx_post_id_check_type' => 'post_id, check_type',
			],
			$prefix . 'fp_seo_cache' => [
				'idx_cache_key' => 'cache_key',
				'idx_expires_at' => 'expires_at',
				'idx_cache_key_expires' => 'cache_key, expires_at',
			],
		];
	}

	/**
	 * Clean up old data to improve performance.
	 *
	 * @param int $days_old Days old data to keep.
	 * @return array<string, mixed>
	 */
	public function cleanup_old_data( int $days_old = 30 ): array {
		$results = [];
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days_old} days" ) );

		// Clean up old score history
		$start_time = microtime( true );
		$deleted_scores = $this->wpdb->query( 
			$this->wpdb->prepare( 
				"DELETE FROM {$this->wpdb->prefix}fp_seo_scores WHERE recorded_at < %s", 
				$cutoff_date 
			) 
		);
		$execution_time = microtime( true ) - $start_time;

		$results['score_history'] = [
			'deleted_rows' => $deleted_scores,
			'execution_time' => $execution_time,
		];

		$this->monitor->record_db_query( 
			"DELETE FROM {$this->wpdb->prefix}fp_seo_scores WHERE recorded_at < '{$cutoff_date}'", 
			$execution_time, 
			$deleted_scores 
		);

		// Clean up old analysis data
		$start_time = microtime( true );
		$deleted_analysis = $this->wpdb->query( 
			$this->wpdb->prepare( 
				"DELETE FROM {$this->wpdb->prefix}fp_seo_analysis WHERE created_at < %s", 
				$cutoff_date 
			) 
		);
		$execution_time = microtime( true ) - $start_time;

		$results['analysis_data'] = [
			'deleted_rows' => $deleted_analysis,
			'execution_time' => $execution_time,
		];

		$this->monitor->record_db_query( 
			"DELETE FROM {$this->wpdb->prefix}fp_seo_analysis WHERE created_at < '{$cutoff_date}'", 
			$execution_time, 
			$deleted_analysis 
		);

		// Clean up expired cache
		$start_time = microtime( true );
		$deleted_cache = $this->wpdb->query( 
			$this->wpdb->prepare( 
				"DELETE FROM {$this->wpdb->prefix}fp_seo_cache WHERE expires_at < %s", 
				date( 'Y-m-d H:i:s' ) 
			) 
		);
		$execution_time = microtime( true ) - $start_time;

		$results['expired_cache'] = [
			'deleted_rows' => $deleted_cache,
			'execution_time' => $execution_time,
		];

		$this->monitor->record_db_query( 
			"DELETE FROM {$this->wpdb->prefix}fp_seo_cache WHERE expires_at < NOW()", 
			$execution_time, 
			$deleted_cache 
		);

		return $results;
	}

	/**
	 * Get database performance statistics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_performance_stats(): array {
		$stats = [];

		// Get slow query log (if available)
		$slow_queries = $this->wpdb->get_results( 
			"SHOW VARIABLES LIKE 'slow_query_log'", 
			ARRAY_A 
		);

		$stats['slow_query_log_enabled'] = ! empty( $slow_queries ) && $slow_queries[0]['Value'] === 'ON';

		// Get query cache status
		$query_cache = $this->wpdb->get_results( 
			"SHOW VARIABLES LIKE 'query_cache%'", 
			ARRAY_A 
		);

		$stats['query_cache'] = [];
		foreach ( $query_cache as $variable ) {
			$stats['query_cache'][ $variable['Variable_name'] ] = $variable['Value'];
		}

		// Get table statistics
		$stats['tables'] = $this->analyze_tables();

		// Get index usage statistics
		$stats['indexes'] = $this->get_index_usage_stats();

		return $stats;
	}

	/**
	 * Get index usage statistics.
	 *
	 * @return array<string, mixed>
	 */
	private function get_index_usage_stats(): array {
		$stats = [];
		$tables = $this->get_plugin_tables();

		foreach ( $tables as $table ) {
			$safe_table = $this->sanitize_table_name( $table );
			if ( ! $safe_table ) {
				continue;
			}
			
			$indexes = $this->wpdb->get_results( 
				"SHOW INDEX FROM `{$safe_table}`", 
				ARRAY_A 
			);

			$stats[ $table ] = [];
			foreach ( $indexes as $index ) {
				$stats[ $table ][ $index['Key_name'] ] = [
					'column' => $index['Column_name'],
					'unique' => $index['Non_unique'] === '0',
					'cardinality' => (int) $index['Cardinality'],
				];
			}
		}

		return $stats;
	}

	/**
	 * Optimize specific query.
	 *
	 * @param string $query SQL query.
	 * @return array<string, mixed>
	 */
	public function optimize_query( string $query ): array {
		$start_time = microtime( true );
		
		// Get query execution plan
		$explain = $this->wpdb->get_results( "EXPLAIN {$query}", ARRAY_A );
		
		$execution_time = microtime( true ) - $start_time;

		$analysis = [
			'query' => $query,
			'execution_plan' => $explain,
			'analysis_time' => $execution_time,
			'recommendations' => $this->analyze_query_plan( $explain ),
		];

		$this->monitor->record_db_query( "EXPLAIN {$query}", $execution_time );

		return $analysis;
	}

	/**
	 * Analyze query execution plan for optimization opportunities.
	 *
	 * @param array<array<string, mixed>> $explain Query execution plan.
	 * @return array<string>
	 */
	private function analyze_query_plan( array $explain ): array {
		$recommendations = [];

		foreach ( $explain as $row ) {
			// Check for full table scans
			if ( $row['type'] === 'ALL' ) {
				$recommendations[] = 'Consider adding an index to avoid full table scan on ' . $row['table'];
			}

			// Check for temporary tables
			if ( $row['Extra'] && strpos( $row['Extra'], 'Using temporary' ) !== false ) {
				$recommendations[] = 'Query uses temporary table - consider optimizing GROUP BY or ORDER BY';
			}

			// Check for filesort
			if ( $row['Extra'] && strpos( $row['Extra'], 'Using filesort' ) !== false ) {
				$recommendations[] = 'Query uses filesort - consider adding index for ORDER BY';
			}
		}

		return $recommendations;
	}

	/**
	 * Sanitize table name to prevent SQL injection.
	 *
	 * @param string $table_name Table name to sanitize.
	 * @return string|false Sanitized table name or false if invalid.
	 */
	private function sanitize_table_name( string $table_name ) {
		// Only allow alphanumeric characters and underscores
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
			return false;
		}
		
		// Verify it starts with our prefix
		$prefix = $this->wpdb->prefix;
		if ( strpos( $table_name, $prefix ) !== 0 ) {
			return false;
		}
		
		return $table_name;
	}

	/**
	 * Sanitize database identifier (column, index names).
	 *
	 * @param string $identifier Identifier to sanitize.
	 * @return string|false Sanitized identifier or false if invalid.
	 */
	private function sanitize_identifier( string $identifier ) {
		// Only allow alphanumeric characters and underscores
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $identifier ) ) {
			return false;
		}
		
		return $identifier;
	}

	/**
	 * Sanitize index definition (column list).
	 *
	 * @param string $definition Index definition to sanitize.
	 * @return string|false Sanitized definition or false if invalid.
	 */
	private function sanitize_index_definition( string $definition ) {
		// Only allow column names separated by commas and spaces
		// Example: "post_id, recorded_at" or "post_id"
		if ( ! preg_match( '/^[a-zA-Z0-9_, ]+$/', $definition ) ) {
			return false;
		}
		
		return $definition;
	}
}
