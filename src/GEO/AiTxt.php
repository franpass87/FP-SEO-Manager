<?php
/**
 * AI.txt Generator - Creates /.well-known/ai.txt for AI crawler guidance
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Generates ai.txt content
 */
class AiTxt {

	/**
	 * Generate ai.txt content
	 *
	 * @return string
	 */
	public function generate(): string {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		$lines = array();

		// User-Agent
		$lines[] = 'User-Agent: *';
		$lines[] = 'Allow: /';
		$lines[] = '';

		// Policy
		$lines[] = '# Policy';
		$usage = $geo['ai_usage'] ?? 'allow-with-attribution';
		$lines[] = 'Usage: ' . $usage;

		// License
		if ( ! empty( $geo['license_url'] ) ) {
			$lines[] = 'License: ' . esc_url_raw( $geo['license_url'] );
		}

		// Crawl delay
		$lines[] = 'Crawl-Delay: 1';
		$lines[] = '';

		// Sitemaps
		$lines[] = '# Sitemaps';
		$lines[] = 'Sitemaps:';
		$lines[] = '  - ' . home_url( '/geo-sitemap.xml' );
		$lines[] = '';

		// Contact
		$admin_email = get_option( 'admin_email' );
		if ( $admin_email ) {
			$lines[] = 'Contact: mailto:' . $admin_email;
			$lines[] = '';
		}

		// Disallow-Content for posts with "No AI reuse"
		$disallowed = $this->get_disallowed_posts();
		if ( ! empty( $disallowed ) ) {
			$lines[] = '# Content-specific restrictions';
			foreach ( $disallowed as $url ) {
				$lines[] = 'Disallow-Content: ' . $url;
			}
			$lines[] = '';
		}

		/**
		 * Filter ai.txt lines before output
		 *
		 * @param array<string> $lines Generated lines.
		 */
		$lines = apply_filters( 'fpseo_geo_ai_txt_lines', $lines );

		return implode( "\n", $lines );
	}

	/**
	 * Get posts with "No AI reuse" flag
	 *
	 * @return array<string> URLs of disallowed posts.
	 */
	private function get_disallowed_posts(): array {
		// Check transient first
		$cached = get_transient( 'fp_seo_geo_disallowed_posts' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		// Get enabled post types
		$enabled_types = array();
		if ( ! empty( $geo['post_types'] ) && is_array( $geo['post_types'] ) ) {
			foreach ( $geo['post_types'] as $type => $settings ) {
				if ( ! empty( $settings['expose'] ) ) {
					$enabled_types[] = $type;
				}
			}
		}

		if ( empty( $enabled_types ) ) {
			set_transient( 'fp_seo_geo_disallowed_posts', array(), 900 );
			return array();
		}

		// Query posts with meta _fp_seo_geo_no_ai_reuse = 1
		$query = new \WP_Query(
			array(
				'post_type'              => $enabled_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 100, // Limit for ai.txt to prevent memory issues
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_fp_seo_geo_no_ai_reuse',
						'value' => '1',
					),
				),
			)
		);

		$urls = array();
		foreach ( $query->posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				$urls[] = $permalink;
			}
		}

		// Cache for 15 minutes
		set_transient( 'fp_seo_geo_disallowed_posts', $urls, 900 );

		return $urls;
	}
}

