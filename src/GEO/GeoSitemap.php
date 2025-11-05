<?php
/**
 * GEO Sitemap Generator - Creates /geo-sitemap.xml
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Generates GEO sitemap index
 */
class GeoSitemap {

	/**
	 * Generate sitemap XML
	 *
	 * @return string
	 */
	public function generate(): string {
		// Check cache
		$cached = get_transient( 'fp_seo_geo_sitemap' );
		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}

		$xml = $this->build_sitemap();

		// Cache for 15 minutes
		set_transient( 'fp_seo_geo_sitemap', $xml, 900 );

		return $xml;
	}

	/**
	 * Build sitemap XML structure
	 *
	 * @return string
	 */
	private function build_sitemap(): string {
		$urls = array();

		// Always include site.json and updates.json
		$urls[] = array(
			'loc'     => home_url( '/geo/site.json' ),
			'lastmod' => current_time( 'c' ),
		);

		$urls[] = array(
			'loc'     => home_url( '/geo/updates.json' ),
			'lastmod' => current_time( 'c' ),
		);

		// Add content URLs from enabled post types
		$content_urls = $this->get_content_urls();
		$urls         = array_merge( $urls, $content_urls );

		// Build XML
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $urls as $url ) {
			$xml .= '  <sitemap>' . "\n";
			$xml .= '    <loc>' . esc_url( $url['loc'] ) . '</loc>' . "\n";
			$xml .= '    <lastmod>' . esc_html( $url['lastmod'] ) . '</lastmod>' . "\n";
			$xml .= '  </sitemap>' . "\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Get content URLs for sitemap
	 *
	 * @return array<array{loc:string,lastmod:string}>
	 */
	private function get_content_urls(): array {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		// Get enabled post types for sitemap
		$enabled_types = array();
		if ( ! empty( $geo['post_types'] ) && is_array( $geo['post_types'] ) ) {
			foreach ( $geo['post_types'] as $type => $settings ) {
				if ( ! empty( $settings['expose'] ) && ! empty( $settings['in_sitemap'] ) ) {
					$enabled_types[] = $type;
				}
			}
		}

		if ( empty( $enabled_types ) ) {
			return array();
		}

		// Query published posts
		$query = new \WP_Query(
			array(
				'post_type'              => $enabled_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 1000, // Reasonable limit for sitemap to prevent memory issues
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
				'meta_query'             => array(
					'relation' => 'OR',
					array(
						'key'     => '_fp_seo_geo_expose',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_fp_seo_geo_expose',
						'value' => '0',
						'compare' => '!=',
					),
				),
			)
		);

		$urls = array();
		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$urls[] = array(
				'loc'     => home_url( '/geo/content/' . $post_id . '.json' ),
				'lastmod' => mysql2date( 'c', $post->post_modified_gmt, false ),
			);
		}

		return $urls;
	}

	/**
	 * Flush sitemap cache
	 */
	public static function flush_cache(): void {
		delete_transient( 'fp_seo_geo_sitemap' );
		delete_transient( 'fp_seo_geo_site_json' );
		delete_transient( 'fp_seo_geo_updates_json' );
	}
}

