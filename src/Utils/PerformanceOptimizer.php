<?php
/**
 * Performance optimization utilities.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Handles performance optimizations for the plugin.
 */
class PerformanceOptimizer {

	/**
	 * Cache group for performance data.
	 */
	private const CACHE_GROUP = 'fp_seo_performance';

	/**
	 * Register performance optimization hooks.
	 */
	public function register(): void {
		// Optimize database queries
		add_action( 'init', array( $this, 'optimize_database_queries' ) );
		
		// Optimize admin loading
		add_action( 'admin_init', array( $this, 'optimize_admin_loading' ) );
		
		// Add performance monitoring
		add_action( 'wp_footer', array( $this, 'add_performance_monitoring' ) );
		add_action( 'admin_footer', array( $this, 'add_performance_monitoring' ) );
	}

	/**
	 * Optimize database queries.
	 */
	public function optimize_database_queries(): void {
		// Add database query optimizations
		add_filter( 'posts_where', array( $this, 'optimize_posts_where' ), 10, 2 );
		add_filter( 'posts_orderby', array( $this, 'optimize_posts_orderby' ), 10, 2 );
	}

	/**
	 * Optimize admin loading.
	 */
	public function optimize_admin_loading(): void {
		// Only load admin optimizations on admin pages
		if ( ! is_admin() ) {
			return;
		}

		// Defer non-critical admin scripts
		add_filter( 'script_loader_tag', array( $this, 'defer_non_critical_scripts' ), 10, 3 );
		
		// Optimize admin menu loading
		add_action( 'admin_menu', array( $this, 'optimize_admin_menu' ), 1 );
	}

	/**
	 * Add performance monitoring.
	 */
	public function add_performance_monitoring(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$memory_usage = memory_get_peak_usage( true );
		$memory_limit = ini_get( 'memory_limit' );
		$memory_percent = ( $memory_usage / wp_convert_hr_to_bytes( $memory_limit ) ) * 100;

		echo sprintf(
			'<!-- FP SEO Performance Monitor: Memory: %s/%s (%.1f%%) -->',
			size_format( $memory_usage ),
			$memory_limit,
			$memory_percent
		);
	}

	/**
	 * Optimize posts WHERE clause.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query object.
	 * @return string Modified WHERE clause.
	 */
	public function optimize_posts_where( string $where, $query ): string {
		// Add optimizations for common queries
		if ( $query->is_main_query() && $query->is_home() ) {
			// Optimize home page queries
			$where .= " AND post_status = 'publish'";
		}

		return $where;
	}

	/**
	 * Optimize posts ORDER BY clause.
	 *
	 * @param string   $orderby ORDER BY clause.
	 * @param WP_Query $query Query object.
	 * @return string Modified ORDER BY clause.
	 */
	public function optimize_posts_orderby( string $orderby, $query ): string {
		// Add optimizations for common ordering
		if ( $query->is_main_query() && $query->is_home() ) {
			// Use indexed columns for ordering
			$orderby = "post_date DESC";
		}

		return $orderby;
	}

	/**
	 * Defer non-critical scripts.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string Modified script tag.
	 */
	public function defer_non_critical_scripts( string $tag, string $handle, string $src ): string {
		$defer_scripts = array(
			'fp-seo-performance-bulk',
			'fp-seo-performance-serp-preview',
		);

		if ( in_array( $handle, $defer_scripts, true ) ) {
			$tag = str_replace( '<script ', '<script defer ', $tag );
		}

		return $tag;
	}

	/**
	 * Optimize admin menu loading.
	 */
	public function optimize_admin_menu(): void {
		// Remove unnecessary admin menu items if not needed
		$options = get_option( 'fp_seo_performance', array() );
		$hide_advanced_features = $options['general']['hide_advanced_features'] ?? false;

		if ( $hide_advanced_features ) {
			remove_menu_page( 'fp-seo-test-suite' );
		}
	}

	/**
	 * Get performance metrics.
	 *
	 * @return array<string, mixed> Performance metrics.
	 */
	public function get_performance_metrics(): array {
		$cache_key = 'fp_seo_performance_metrics';
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$metrics = array(
			'memory_usage' => memory_get_peak_usage( true ),
			'memory_limit' => ini_get( 'memory_limit' ),
			'execution_time' => microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) ),
			'db_queries' => get_num_queries(),
			'cache_hits' => wp_cache_get( 'fp_seo_cache_hits', self::CACHE_GROUP ) ?: 0,
			'cache_misses' => wp_cache_get( 'fp_seo_cache_misses', self::CACHE_GROUP ) ?: 0,
		);

		wp_cache_set( $cache_key, $metrics, self::CACHE_GROUP, MINUTE_IN_SECONDS );

		return $metrics;
	}

	/**
	 * Clear performance cache.
	 */
	public function clear_performance_cache(): void {
		wp_cache_delete( 'fp_seo_performance_metrics', self::CACHE_GROUP );
		wp_cache_delete( 'fp_seo_cache_hits', self::CACHE_GROUP );
		wp_cache_delete( 'fp_seo_cache_misses', self::CACHE_GROUP );
	}
}
