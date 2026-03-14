<?php
/**
 * Sitemap Router - Serves HTML sitemap at /sitemap/.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

use FP\SEO\GEO\HtmlSitemap;

/**
 * Registers and serves the HTML sitemap endpoint.
 */
class SitemapRouter {

	/**
	 * Query var for sitemap route.
	 */
	public const QUERY_VAR = 'fp_seo_html_sitemap';

	/**
	 * Register rewrite rule and template handler.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'serve_sitemap' ), 2 );
		add_action( 'save_post', array( $this, 'flush_sitemap_cache' ) );
	}

	/**
	 * Flush HTML sitemap cache when content changes.
	 */
	public function flush_sitemap_cache(): void {
		HtmlSitemap::flush_cache();
	}

	/**
	 * Add rewrite rule for /sitemap/.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^sitemap/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Add query var.
	 *
	 * @param array<string> $vars Query vars.
	 * @return array<string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Serve HTML sitemap when route matches.
	 */
	public function serve_sitemap(): void {
		if ( ! get_query_var( self::QUERY_VAR, false ) ) {
			return;
		}

		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$sitemap = new HtmlSitemap();
		$html    = $sitemap->generate();

		// Prevent any output before HTML
		if ( ob_get_level() ) {
			ob_clean();
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: index, follow' );

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
