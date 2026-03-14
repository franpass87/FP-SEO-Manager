<?php
/**
 * XML Sitemap Router - Serves dedicated FP XML sitemap endpoints.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

/**
 * Registers and serves XML sitemap endpoints.
 */
class XmlSitemapRouter {
	/**
	 * Query var for sitemap index.
	 */
	public const QUERY_INDEX = 'fp_seo_xml_sitemap';

	/**
	 * Query var for post type.
	 */
	public const QUERY_POST_TYPE = 'fp_seo_xml_sitemap_post_type';

	/**
	 * Query var for page.
	 */
	public const QUERY_PAGE = 'fp_seo_xml_sitemap_page';

	/**
	 * Register rewrite and serve hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 2 );
		add_action( 'save_post', array( $this, 'flush_cache' ) );
	}

	/**
	 * Add rewrite rules.
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^fp-sitemap\.xml$',
			'index.php?' . self::QUERY_INDEX . '=1',
			'top'
		);

		add_rewrite_rule(
			'^fp-sitemap-([^/]+)-([0-9]+)\.xml$',
			'index.php?' . self::QUERY_INDEX . '=1&' . self::QUERY_POST_TYPE . '=$matches[1]&' . self::QUERY_PAGE . '=$matches[2]',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array<int,string> $vars Vars.
	 * @return array<int,string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_INDEX;
		$vars[] = self::QUERY_POST_TYPE;
		$vars[] = self::QUERY_PAGE;
		return $vars;
	}

	/**
	 * Serve XML sitemap response.
	 */
	public function serve(): void {
		if ( ! RedirectsOptions::xml_sitemap_enabled() ) {
			return;
		}

		if ( ! get_query_var( self::QUERY_INDEX, false ) ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$post_type = sanitize_key( (string) get_query_var( self::QUERY_POST_TYPE, '' ) );
		$page      = absint( get_query_var( self::QUERY_PAGE, 1 ) );

		$service = new XmlSitemap();
		$xml     = '';
		if ( '' === $post_type ) {
			$xml = $service->build_index();
		} else {
			$xml = $service->build_post_type_sitemap( $post_type, max( 1, $page ) );
		}

		if ( ob_get_level() ) {
			ob_clean();
		}

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Flush XML sitemap cache.
	 */
	public function flush_cache(): void {
		XmlSitemap::flush_cache();
	}
}

