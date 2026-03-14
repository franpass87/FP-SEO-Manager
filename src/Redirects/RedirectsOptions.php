<?php
/**
 * Redirects and HTML Sitemap options helper.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

/**
 * Centralized options for redirect manager and HTML sitemap.
 */
final class RedirectsOptions {

	private const OPTION_KEY = 'fp_seo_performance';
	private const REDIRECTS_KEY = 'redirects';
	private const HTML_SITEMAP_KEY = 'html_sitemap';

	/** @var array{enabled: bool, priority: int} */
	private const REDIRECTS_DEFAULTS = array(
		'enabled'  => true,
		'priority' => 1,
	);

	/** @var array{enabled: bool, max_per_section: int, cache_ttl: int} */
	private const HTML_SITEMAP_DEFAULTS = array(
		'enabled'          => true,
		'max_per_section'  => 500,
		'cache_ttl'        => 3600,
	);

	/**
	 * Get redirects options.
	 *
	 * @return array{enabled: bool, priority: int}
	 */
	public static function get_redirects(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$r    = $opts[ self::REDIRECTS_KEY ] ?? array();
		return wp_parse_args( is_array( $r ) ? $r : array(), self::REDIRECTS_DEFAULTS );
	}

	/**
	 * Get HTML sitemap options.
	 *
	 * @return array{enabled: bool, max_per_section: int, cache_ttl: int}
	 */
	public static function get_html_sitemap(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$s    = $opts[ self::HTML_SITEMAP_KEY ] ?? array();
		return wp_parse_args( is_array( $s ) ? $s : array(), self::HTML_SITEMAP_DEFAULTS );
	}

	/**
	 * Check if redirects are enabled.
	 */
	public static function redirects_enabled(): bool {
		return (bool) self::get_redirects()['enabled'];
	}

	/**
	 * Get redirect hook priority (filterable).
	 *
	 * @return int
	 */
	public static function redirect_priority(): int {
		$priority = (int) self::get_redirects()['priority'];
		$priority = $priority >= 1 && $priority <= 99 ? $priority : 1;
		return (int) apply_filters( 'fp_seo_redirect_priority', $priority );
	}

	/**
	 * Check if HTML sitemap is enabled.
	 */
	public static function html_sitemap_enabled(): bool {
		return (bool) self::get_html_sitemap()['enabled'];
	}

	/**
	 * Save redirects options.
	 *
	 * @param array{enabled?: bool, priority?: int} $data New values.
	 * @return bool
	 */
	public static function save_redirects( array $data ): bool {
		$opts  = get_option( self::OPTION_KEY, array() );
		$opts  = is_array( $opts ) ? $opts : array();
		$current = self::get_redirects();

		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['priority'] ) ) {
			$p = (int) $data['priority'];
			$current['priority'] = $p >= 1 && $p <= 99 ? $p : 1;
		}

		$opts[ self::REDIRECTS_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
	}

	/**
	 * Save HTML sitemap options.
	 *
	 * @param array{enabled?: bool, max_per_section?: int, cache_ttl?: int} $data New values.
	 * @return bool
	 */
	public static function save_html_sitemap( array $data ): bool {
		$opts    = get_option( self::OPTION_KEY, array() );
		$opts    = is_array( $opts ) ? $opts : array();
		$current = self::get_html_sitemap();

		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['max_per_section'] ) ) {
			$m = (int) $data['max_per_section'];
			$current['max_per_section'] = $m >= 10 && $m <= 2000 ? $m : 500;
		}
		if ( isset( $data['cache_ttl'] ) ) {
			$c = (int) $data['cache_ttl'];
			$current['cache_ttl'] = $c >= 60 && $c <= 86400 ? $c : 3600;
		}

		$opts[ self::HTML_SITEMAP_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
	}
}
