<?php
/**
 * Selective query optimization for plugin-specific queries.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use WP_Query;

/**
 * Handles selective query optimization to avoid interfering with WordPress core queries.
 */
class QueryOptimizer {

	/**
	 * Query identifiers that belong to the plugin.
	 *
	 * @var array<string>
	 */
	private const PLUGIN_QUERY_IDENTIFIERS = array(
		'fp_seo',
		'fp-seo',
		'bulk_audit',
		'link_suggestion',
		'geo_',
		'authority_signals',
		'citation_formatter',
		'training_dataset',
	);

	/**
	 * Query contexts that should NEVER be optimized.
	 *
	 * @var array<string>
	 */
	private const BLACKLISTED_CONTEXTS = array(
		'post.php',
		'post-new.php',
		'edit.php',
		'media-upload.php',
		'upload.php',
		'admin-ajax.php',
		'rest-api',
	);

	/**
	 * Query meta keys that identify plugin queries.
	 *
	 * @var array<string>
	 */
	private const PLUGIN_META_KEYS = array(
		'_fp_seo',
		'_fp_seo_performance',
		'_fp_geo',
	);

	/**
	 * Check if a query belongs to the plugin.
	 *
	 * @param WP_Query $query Query object.
	 * @return bool True if query belongs to plugin.
	 * 
	 * @example
	 * add_filter('posts_where', function($where, $query) {
	 *     if (QueryOptimizer::is_plugin_query($query)) {
	 *         // Optimize only plugin queries
	 *         return QueryOptimizer::optimize_where($where, $query);
	 *     }
	 *     return $where;
	 * }, 10, 2);
	 */
	public static function is_plugin_query( WP_Query $query ): bool {
		// Check query vars for plugin identifiers
		$query_vars = $query->query_vars;

		// Check meta_query for plugin meta keys
		if ( isset( $query_vars['meta_query'] ) && is_array( $query_vars['meta_query'] ) ) {
			foreach ( $query_vars['meta_query'] as $meta_query ) {
				if ( isset( $meta_query['key'] ) ) {
					$key = $meta_query['key'];
					foreach ( self::PLUGIN_META_KEYS as $plugin_key ) {
						if ( strpos( $key, $plugin_key ) === 0 ) {
							return true;
						}
					}
				}
			}
		}

		// Check query name/identifier
		if ( isset( $query_vars['name'] ) ) {
			foreach ( self::PLUGIN_QUERY_IDENTIFIERS as $identifier ) {
				if ( strpos( $query_vars['name'], $identifier ) !== false ) {
					return true;
				}
			}
		}

		// Check if query has plugin-specific post types
		$post_types = $query_vars['post_type'] ?? array();
		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}

		// Check for plugin-specific flags in query
		if ( isset( $query_vars['fp_seo_query'] ) && $query_vars['fp_seo_query'] === true ) {
			return true;
		}

		// Check query object properties
		if ( isset( $query->query['fp_seo_query'] ) && $query->query['fp_seo_query'] === true ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if current context should be excluded from optimization.
	 *
	 * @return bool True if context is blacklisted.
	 */
	public static function is_blacklisted_context(): bool {
		// Never optimize in admin edit pages
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->base, array( 'post', 'edit', 'upload' ), true ) ) {
			return true;
		}

		// Check request URI
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		foreach ( self::BLACKLISTED_CONTEXTS as $context ) {
			if ( strpos( $request_uri, $context ) !== false ) {
				return true;
			}
		}

		// Never optimize during AJAX requests (except our own)
		if ( wp_doing_ajax() ) {
			$action = $_REQUEST['action'] ?? '';
			if ( strpos( $action, 'fp_seo' ) !== 0 ) {
				return true;
			}
		}

		// Never optimize during REST API (except our own endpoints)
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$rest_route = $_SERVER['REQUEST_URI'] ?? '';
			if ( strpos( $rest_route, '/wp-json/fp-seo' ) === false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark a query as plugin query for optimization.
	 *
	 * @param array<string, mixed> $query_args Query arguments.
	 * @return array<string, mixed> Modified query arguments.
	 */
	public static function mark_as_plugin_query( array $query_args ): array {
		$query_args['fp_seo_query'] = true;
		return $query_args;
	}

	/**
	 * Optimize WHERE clause for plugin queries only.
	 *
	 * @param string   $where WHERE clause.
	 * @param WP_Query $query Query object.
	 * @return string Modified WHERE clause.
	 */
	public static function optimize_where( string $where, WP_Query $query ): string {
		// Never optimize in blacklisted contexts
		if ( self::is_blacklisted_context() ) {
			return $where;
		}

		// Only optimize plugin queries
		if ( ! self::is_plugin_query( $query ) ) {
			return $where;
		}

		// Add optimizations for plugin queries
		// Only include published posts for frontend queries
		if ( ! is_admin() && ! $query->is_singular ) {
			$where .= " AND {$GLOBALS['wpdb']->posts}.post_status = 'publish'";
		}

		// Exclude revisions and auto-drafts
		$where .= " AND {$GLOBALS['wpdb']->posts}.post_status != 'inherit'";
		$where .= " AND {$GLOBALS['wpdb']->posts}.post_status != 'auto-draft'";

		return $where;
	}

	/**
	 * Optimize ORDER BY clause for plugin queries only.
	 *
	 * @param string   $orderby ORDER BY clause.
	 * @param WP_Query $query Query object.
	 * @return string Modified ORDER BY clause.
	 */
	public static function optimize_orderby( string $orderby, WP_Query $query ): string {
		// Never optimize in blacklisted contexts
		if ( self::is_blacklisted_context() ) {
			return $orderby;
		}

		// Only optimize plugin queries
		if ( ! self::is_plugin_query( $query ) ) {
			return $orderby;
		}

		// Use indexed columns for ordering when no specific order is set
		if ( empty( $orderby ) || $orderby === 'none' ) {
			return "{$GLOBALS['wpdb']->posts}.post_date DESC";
		}

		return $orderby;
	}

	/**
	 * Optimize JOIN clause for plugin queries only.
	 *
	 * @param string   $join JOIN clause.
	 * @param WP_Query $query Query object.
	 * @return string Modified JOIN clause.
	 */
	public static function optimize_join( string $join, WP_Query $query ): string {
		// Never optimize in blacklisted contexts
		if ( self::is_blacklisted_context() ) {
			return $join;
		}

		// Only optimize plugin queries
		if ( ! self::is_plugin_query( $query ) ) {
			return $join;
		}

		// Add optimizations if needed (e.g., remove unnecessary joins)
		// Currently no specific join optimizations needed

		return $join;
	}

	/**
	 * Get optimized query arguments for plugin queries.
	 *
	 * @param array<string, mixed> $args Original query arguments.
	 * @return array<string, mixed> Optimized query arguments.
	 */
	public static function optimize_query_args( array $args ): array {
		// Mark as plugin query
		$args = self::mark_as_plugin_query( $args );

		// Add performance optimizations
		$args['no_found_rows'] = $args['no_found_rows'] ?? true; // Skip counting total rows
		$args['update_post_meta_cache'] = $args['update_post_meta_cache'] ?? true; // Cache meta
		$args['update_post_term_cache'] = $args['update_post_term_cache'] ?? false; // Skip term cache if not needed

		// Limit fields to reduce memory usage
		if ( ! isset( $args['fields'] ) ) {
			$args['fields'] = 'ids'; // Only get IDs, fetch full objects when needed
		}

		return $args;
	}
}

