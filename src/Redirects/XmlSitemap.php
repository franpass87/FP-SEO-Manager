<?php
/**
 * XML Sitemap generator for search engines.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

use function esc_url;
use function get_permalink;
use function get_post_type_object;
use function get_posts;
use function get_transient;
use function gmdate;
use function home_url;
use function is_array;
use function set_transient;
use function wp_date;

/**
 * Builds XML sitemap index and sitemap files by post type.
 */
class XmlSitemap {

	/**
	 * Build sitemap index XML.
	 *
	 * @return string
	 */
	public function build_index(): string {
		$opts      = RedirectsOptions::get_xml_sitemap();
		$cache_key = 'fp_seo_xml_sitemap_index';
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$items = array();
		foreach ( $opts['post_types'] as $post_type ) {
			$total = $this->count_posts( $post_type );
			if ( $total < 1 ) {
				continue;
			}

			$pages = (int) ceil( $total / max( 1, (int) $opts['max_urls_per_file'] ) );
			for ( $page = 1; $page <= $pages; ++$page ) {
				$items[] = array(
					'loc'     => home_url( '/fp-sitemap-' . $post_type . '-' . $page . '.xml' ),
					'lastmod' => $this->lastmod_for_post_type( $post_type ),
				);
			}
		}

		$xml = $this->render_index_xml( $items );
		set_transient( $cache_key, $xml, (int) $opts['cache_ttl'] );
		return $xml;
	}

	/**
	 * Build a post-type sitemap XML page.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      Page number (1-indexed).
	 * @return string
	 */
	public function build_post_type_sitemap( string $post_type, int $page = 1 ): string {
		$opts = RedirectsOptions::get_xml_sitemap();
		if ( ! in_array( $post_type, $opts['post_types'], true ) ) {
			return $this->render_urlset_xml( array() );
		}

		$page      = max( 1, $page );
		$cache_key = 'fp_seo_xml_sitemap_' . $post_type . '_' . $page;
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$per_page = max( 100, (int) $opts['max_urls_per_file'] );
		$offset   = ( $page - 1 ) * $per_page;
		$posts    = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'offset'                 => $offset,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$items[] = array(
				'loc'     => get_permalink( $post ),
				'lastmod' => gmdate( 'c', strtotime( (string) $post->post_modified_gmt ) ),
			);
		}

		$xml = $this->render_urlset_xml( $items );
		set_transient( $cache_key, $xml, (int) $opts['cache_ttl'] );
		return $xml;
	}

	/**
	 * Flush all XML sitemap caches.
	 */
	public static function flush_cache(): void {
		delete_transient( 'fp_seo_xml_sitemap_index' );
		$opts = RedirectsOptions::get_xml_sitemap();
		foreach ( $opts['post_types'] as $post_type ) {
			$total = (int) wp_count_posts( $post_type )->publish;
			$pages = (int) ceil( $total / max( 1, (int) $opts['max_urls_per_file'] ) );
			for ( $page = 1; $page <= max( 1, $pages ); ++$page ) {
				delete_transient( 'fp_seo_xml_sitemap_' . $post_type . '_' . $page );
			}
		}
	}

	/**
	 * Render sitemapindex XML.
	 *
	 * @param array<int,array{loc:string,lastmod:string}> $items Items.
	 * @return string
	 */
	private function render_index_xml( array $items ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $items as $item ) {
			$xml .= "\t" . '<sitemap>' . "\n";
			$xml .= "\t\t" . '<loc>' . esc_url( $item['loc'] ) . '</loc>' . "\n";
			$xml .= "\t\t" . '<lastmod>' . $item['lastmod'] . '</lastmod>' . "\n";
			$xml .= "\t" . '</sitemap>' . "\n";
		}
		$xml .= '</sitemapindex>';
		return $xml;
	}

	/**
	 * Render urlset XML.
	 *
	 * @param array<int,array{loc:string,lastmod:string}> $items Items.
	 * @return string
	 */
	private function render_urlset_xml( array $items ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $items as $item ) {
			$xml .= "\t" . '<url>' . "\n";
			$xml .= "\t\t" . '<loc>' . esc_url( $item['loc'] ) . '</loc>' . "\n";
			$xml .= "\t\t" . '<lastmod>' . $item['lastmod'] . '</lastmod>' . "\n";
			$xml .= "\t" . '</url>' . "\n";
		}
		$xml .= '</urlset>';
		return $xml;
	}

	/**
	 * Count publish posts for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function count_posts( string $post_type ): int {
		$count = wp_count_posts( $post_type );
		return isset( $count->publish ) ? (int) $count->publish : 0;
	}

	/**
	 * Resolve last modified timestamp for post type.
	 *
	 * @param string $post_type Post type.
	 * @return string ISO datetime.
	 */
	private function lastmod_for_post_type( string $post_type ): string {
		$latest = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! empty( $latest ) && isset( $latest[0]->post_modified_gmt ) ) {
			return gmdate( 'c', strtotime( (string) $latest[0]->post_modified_gmt ) );
		}

		return gmdate( 'c', strtotime( wp_date( 'c' ) ) );
	}
}

