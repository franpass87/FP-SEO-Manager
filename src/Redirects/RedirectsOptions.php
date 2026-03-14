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
	private const XML_SITEMAP_KEY = 'xml_sitemap';
	private const META_RENDERING_KEY = 'meta_rendering';
	private const ROBOTS_KEY = 'robots';
	private const BREADCRUMB_KEY = 'breadcrumb';

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

	/** @var array{enabled: bool, max_urls_per_file: int, cache_ttl: int, post_types: array<int,string>} */
	private const XML_SITEMAP_DEFAULTS = array(
		'enabled'           => true,
		'max_urls_per_file' => 1000,
		'cache_ttl'         => 3600,
		'post_types'        => array( 'post', 'page' ),
	);

	/** @var array{hreflang_enabled: bool, include_x_default: bool} */
	private const META_RENDERING_DEFAULTS = array(
		'hreflang_enabled'  => true,
		'include_x_default' => true,
	);

	/** @var array{enabled: bool,extra_rules: string} */
	private const ROBOTS_DEFAULTS = array(
		'enabled'     => true,
		'extra_rules' => '',
	);

	/** @var array{enabled: bool,show_home: bool} */
	private const BREADCRUMB_DEFAULTS = array(
		'enabled'   => true,
		'show_home' => true,
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
	 * Get XML sitemap options.
	 *
	 * @return array{enabled: bool, max_urls_per_file: int, cache_ttl: int, post_types: array<int,string>}
	 */
	public static function get_xml_sitemap(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$x    = $opts[ self::XML_SITEMAP_KEY ] ?? array();
		$x    = wp_parse_args( is_array( $x ) ? $x : array(), self::XML_SITEMAP_DEFAULTS );
		$x['post_types'] = self::sanitize_post_types( $x['post_types'] ?? array() );
		return $x;
	}

	/**
	 * Get meta rendering options.
	 *
	 * @return array{hreflang_enabled: bool, include_x_default: bool}
	 */
	public static function get_meta_rendering(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$m    = $opts[ self::META_RENDERING_KEY ] ?? array();
		return wp_parse_args( is_array( $m ) ? $m : array(), self::META_RENDERING_DEFAULTS );
	}

	/**
	 * Get robots manager options.
	 *
	 * @return array{enabled: bool,extra_rules: string}
	 */
	public static function get_robots(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$r    = $opts[ self::ROBOTS_KEY ] ?? array();
		return wp_parse_args( is_array( $r ) ? $r : array(), self::ROBOTS_DEFAULTS );
	}

	/**
	 * Get breadcrumb options.
	 *
	 * @return array{enabled: bool,show_home: bool}
	 */
	public static function get_breadcrumb(): array {
		$opts = get_option( self::OPTION_KEY, array() );
		$b    = $opts[ self::BREADCRUMB_KEY ] ?? array();
		return wp_parse_args( is_array( $b ) ? $b : array(), self::BREADCRUMB_DEFAULTS );
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
	 * Check if XML sitemap is enabled.
	 */
	public static function xml_sitemap_enabled(): bool {
		return (bool) self::get_xml_sitemap()['enabled'];
	}

	/**
	 * Save meta rendering options.
	 *
	 * @param array{hreflang_enabled?: bool, include_x_default?: bool} $data New values.
	 * @return bool
	 */
	public static function save_meta_rendering( array $data ): bool {
		$opts    = get_option( self::OPTION_KEY, array() );
		$opts    = is_array( $opts ) ? $opts : array();
		$current = self::get_meta_rendering();
		if ( isset( $data['hreflang_enabled'] ) ) {
			$current['hreflang_enabled'] = (bool) $data['hreflang_enabled'];
		}
		if ( isset( $data['include_x_default'] ) ) {
			$current['include_x_default'] = (bool) $data['include_x_default'];
		}
		$opts[ self::META_RENDERING_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
	}

	/**
	 * Save robots manager options.
	 *
	 * @param array{enabled?: bool, extra_rules?: string} $data New values.
	 * @return bool
	 */
	public static function save_robots( array $data ): bool {
		$opts    = get_option( self::OPTION_KEY, array() );
		$opts    = is_array( $opts ) ? $opts : array();
		$current = self::get_robots();
		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['extra_rules'] ) ) {
			$current['extra_rules'] = sanitize_textarea_field( (string) $data['extra_rules'] );
		}
		$opts[ self::ROBOTS_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
	}

	/**
	 * Save breadcrumb options.
	 *
	 * @param array{enabled?: bool, show_home?: bool} $data New values.
	 * @return bool
	 */
	public static function save_breadcrumb( array $data ): bool {
		$opts    = get_option( self::OPTION_KEY, array() );
		$opts    = is_array( $opts ) ? $opts : array();
		$current = self::get_breadcrumb();
		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['show_home'] ) ) {
			$current['show_home'] = (bool) $data['show_home'];
		}
		$opts[ self::BREADCRUMB_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
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

	/**
	 * Save XML sitemap options.
	 *
	 * @param array{enabled?: bool, max_urls_per_file?: int, cache_ttl?: int, post_types?: array<int,string>} $data New values.
	 * @return bool
	 */
	public static function save_xml_sitemap( array $data ): bool {
		$opts    = get_option( self::OPTION_KEY, array() );
		$opts    = is_array( $opts ) ? $opts : array();
		$current = self::get_xml_sitemap();

		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['max_urls_per_file'] ) ) {
			$m = (int) $data['max_urls_per_file'];
			$current['max_urls_per_file'] = $m >= 100 && $m <= 5000 ? $m : 1000;
		}
		if ( isset( $data['cache_ttl'] ) ) {
			$c = (int) $data['cache_ttl'];
			$current['cache_ttl'] = $c >= 60 && $c <= 86400 ? $c : 3600;
		}
		if ( isset( $data['post_types'] ) ) {
			$current['post_types'] = self::sanitize_post_types( $data['post_types'] );
		}

		$opts[ self::XML_SITEMAP_KEY ] = $current;
		return update_option( self::OPTION_KEY, $opts );
	}

	/**
	 * Sanitize list of sitemap post types.
	 *
	 * @param mixed $post_types Raw value.
	 * @return array<int, string>
	 */
	private static function sanitize_post_types( $post_types ): array {
		$list = array();
		if ( ! is_array( $post_types ) ) {
			return self::XML_SITEMAP_DEFAULTS['post_types'];
		}

		$public = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $type ) {
			$type = is_string( $type ) ? sanitize_key( $type ) : '';
			if ( '' !== $type && in_array( $type, $public, true ) ) {
				$list[] = $type;
			}
		}

		$list = array_values( array_unique( $list ) );
		return ! empty( $list ) ? $list : self::XML_SITEMAP_DEFAULTS['post_types'];
	}
}
