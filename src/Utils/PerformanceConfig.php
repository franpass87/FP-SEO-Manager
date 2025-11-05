<?php
/**
 * Performance configuration settings.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Handles performance configuration and settings.
 */
class PerformanceConfig {

	/**
	 * Default performance settings.
	 */
	private const DEFAULT_SETTINGS = array(
		'cache' => array(
			'enabled' => true,
			'ttl' => 3600, // 1 hour
			'use_object_cache' => true,
			'use_transients' => true,
		),
		'assets' => array(
			'minify_css' => true,
			'minify_js' => true,
			'combine_files' => false,
			'defer_non_critical' => true,
		),
		'database' => array(
			'optimize_queries' => true,
			'use_indexes' => true,
			'limit_results' => 100,
		),
		'analysis' => array(
			'enable_advanced_checks' => true,
			'parallel_processing' => false,
			'cache_results' => true,
		),
		'ai' => array(
			'cache_responses' => true,
			'cache_ttl' => WEEK_IN_SECONDS,
			'use_object_cache' => true,
		),
	);

	/**
	 * Get performance settings.
	 *
	 * @return array<string, mixed> Performance settings.
	 */
	public static function get_settings(): array {
		$options = get_option( 'fp_seo_performance', array() );
		$performance_settings = $options['performance'] ?? array();

		return array_merge( self::DEFAULT_SETTINGS, $performance_settings );
	}

	/**
	 * Get cache TTL for a specific operation.
	 *
	 * @param string $operation Operation type.
	 * @return int TTL in seconds.
	 */
	public static function get_cache_ttl( string $operation ): int {
		$settings = self::get_settings();

		switch ( $operation ) {
			case 'ai_response':
				return $settings['ai']['cache_ttl'] ?? WEEK_IN_SECONDS;
			case 'analysis_result':
				return $settings['cache']['ttl'] ?? HOUR_IN_SECONDS;
			case 'database_query':
				return $settings['cache']['ttl'] ?? HOUR_IN_SECONDS;
			default:
				return $settings['cache']['ttl'] ?? HOUR_IN_SECONDS;
		}
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @param string $feature Feature name.
	 * @return bool True if enabled.
	 */
	public static function is_feature_enabled( string $feature ): bool {
		$settings = self::get_settings();

		switch ( $feature ) {
			case 'cache':
				return $settings['cache']['enabled'] ?? true;
			case 'object_cache':
				return $settings['cache']['use_object_cache'] ?? true;
			case 'transients':
				return $settings['cache']['use_transients'] ?? true;
			case 'advanced_checks':
				return $settings['analysis']['enable_advanced_checks'] ?? true;
			case 'ai_cache':
				return $settings['ai']['cache_responses'] ?? true;
			default:
				return false;
		}
	}

	/**
	 * Get database query limit.
	 *
	 * @return int Query limit.
	 */
	public static function get_query_limit(): int {
		$settings = self::get_settings();
		return $settings['database']['limit_results'] ?? 100;
	}

	/**
	 * Check if parallel processing is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_parallel_processing_enabled(): bool {
		$settings = self::get_settings();
		return $settings['analysis']['parallel_processing'] ?? false;
	}

	/**
	 * Get asset optimization settings.
	 *
	 * @return array<string, mixed> Asset settings.
	 */
	public static function get_asset_settings(): array {
		$settings = self::get_settings();
		return $settings['assets'] ?? array();
	}

	/**
	 * Update performance settings.
	 *
	 * @param array<string, mixed> $new_settings New settings.
	 * @return bool True on success.
	 */
	public static function update_settings( array $new_settings ): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$options['performance'] = array_merge( $options['performance'] ?? array(), $new_settings );
		
		return update_option( 'fp_seo_performance', $options );
	}

	/**
	 * Reset to default settings.
	 *
	 * @return bool True on success.
	 */
	public static function reset_to_defaults(): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$options['performance'] = self::DEFAULT_SETTINGS;
		
		return update_option( 'fp_seo_performance', $options );
	}
}
