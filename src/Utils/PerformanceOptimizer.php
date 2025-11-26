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
		
		// Optimize meta queries (get_post_meta caching)
		add_action( 'init', array( $this, 'optimize_meta_queries' ) );
		
		// Optimize object cache
		add_action( 'init', array( $this, 'optimize_object_cache' ) );
		
		// Add performance monitoring
		add_action( 'wp_footer', array( $this, 'add_performance_monitoring' ) );
		add_action( 'admin_footer', array( $this, 'add_performance_monitoring' ) );
		
		// Optimize memory usage
		add_action( 'wp_loaded', array( $this, 'optimize_memory_usage' ) );
	}

	/**
	 * Optimize database queries.
	 */
	public function optimize_database_queries(): void {
		// DISABLED: These filters interfere with WordPress's internal queries,
		// including queries used to load posts in the editor.
		// They were causing WordPress to load auto-draft posts instead of the correct post.
		// 
		// If query optimization is needed in the future, it should be done
		// in a way that does NOT modify WordPress core queries.
		return;
		
		// OLD CODE - DISABLED
		// // DISABLED in frontend: Can interfere with page rendering
		// // Only optimize database queries in admin
		// if ( ! is_admin() ) {
		// 	return;
		// }
		// 
		// // Add database query optimizations (admin only)
		// add_filter( 'posts_where', array( $this, 'optimize_posts_where' ), 10, 2 );
		// add_filter( 'posts_orderby', array( $this, 'optimize_posts_orderby' ), 10, 2 );
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
		// CRITICAL: Never modify queries on post edit pages - this can cause WordPress
		// to load the wrong post or interfere with the editor
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( strpos( $request_uri, 'post.php' ) !== false || strpos( $request_uri, 'post-new.php' ) !== false ) {
			return $where; // Don't modify queries on edit pages
		}
		
		// This method is only called in admin (filter registered only in admin)
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
		// CRITICAL: Never modify queries on post edit pages - this can cause WordPress
		// to load the wrong post or interfere with the editor
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( strpos( $request_uri, 'post.php' ) !== false || strpos( $request_uri, 'post-new.php' ) !== false ) {
			return $orderby; // Don't modify queries on edit pages
		}
		
		// This method is only called in admin (filter registered only in admin)
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
		// Use cached options to avoid extra queries
		$options = Options::get();
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

	/**
	 * Optimize meta queries by enabling meta cache.
	 */
	public function optimize_meta_queries(): void {
		// DISABLED in frontend: Can interfere with page rendering
		// Only optimize meta queries in admin
		if ( ! is_admin() ) {
			return;
		}
		
		// Enable meta cache for better performance
		if ( ! wp_using_ext_object_cache() ) {
			// For non-persistent cache, ensure meta is cached during request
			add_filter( 'update_post_metadata_cache', '__return_true' );
		}
		
		// Preload SEO meta for posts being displayed (admin only)
		add_action( 'the_post', array( $this, 'preload_seo_meta' ) );
	}

	/**
	 * Preload SEO meta fields for the current post to reduce queries.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function preload_seo_meta( $post ): void {
		if ( ! $post || ! isset( $post->ID ) ) {
			return;
		}

		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post->ID );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return; // Exit immediately - no interference with WordPress core
		}

		// Only preload SEO-related meta keys
		$seo_keys = array(
			'_fp_seo_title',
			'_fp_seo_description',
			'_fp_seo_keywords',
			'_fp_seo_canonical',
			'_fp_seo_og_title',
			'_fp_seo_og_description',
			'_fp_seo_twitter_title',
			'_fp_seo_twitter_description',
		);

		// Preload all SEO meta at once to reduce queries
		update_postmeta_cache( array( $post->ID ) );
	}

	/**
	 * Optimize object cache usage.
	 */
	public function optimize_object_cache(): void {
		// Increase cache expiration for frequently accessed data
		add_filter( 'wp_cache_themes_persistently', '__return_true' );
		
		// Optimize cache groups
		if ( function_exists( 'wp_cache_add_global_groups' ) ) {
			wp_cache_add_global_groups( array( self::CACHE_GROUP ) );
		}
	}

	/**
	 * Optimize memory usage by cleaning up unused data.
	 */
	public function optimize_memory_usage(): void {
		// Only optimize on frontend to avoid affecting admin
		if ( is_admin() ) {
			return;
		}

		// Clear unused transients periodically
		if ( ! wp_next_scheduled( 'fp_seo_cleanup_transients' ) ) {
			wp_schedule_event( time(), 'daily', 'fp_seo_cleanup_transients' );
		}

		add_action( 'fp_seo_cleanup_transients', array( $this, 'cleanup_expired_transients' ) );
	}

	/**
	 * Clean up expired transients to free memory.
	 */
	public function cleanup_expired_transients(): void {
		global $wpdb;

		// Clean up expired transients (WordPress doesn't always do this automatically)
		$time = time();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$time
			)
		);

		// Also clean up our plugin-specific transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_fp_seo_' ) . '%',
				$time
			)
		);
	}
}
