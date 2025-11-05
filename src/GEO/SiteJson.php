<?php
/**
 * Site JSON Generator - Creates /geo/site.json
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Generates site-level GEO JSON
 */
class SiteJson {

	/**
	 * Generate site JSON data
	 *
	 * @return array<string,mixed>
	 */
	public function generate(): array {
		// Check cache
		$cached = get_transient( 'fp_seo_geo_site_json' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->build_data();

		// Cache for 1 hour
		set_transient( 'fp_seo_geo_site_json', $data, 3600 );

		return $data;
	}

	/**
	 * Build site data structure
	 *
	 * @return array<string,mixed>
	 */
	private function build_data(): array {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();

		$data = array(
			'site' => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
				'lang' => $this->get_site_language(),
			),
			'policy' => array(
				'usage'   => $geo['ai_usage'] ?? 'allow-with-attribution',
				'license' => $geo['license_url'] ?? '',
			),
		);

		// Publisher info
		if ( ! empty( $geo['publisher_name'] ) ) {
			$data['site']['publisher'] = array(
				'@type' => 'Organization',
				'name'  => $geo['publisher_name'],
				'url'   => $geo['publisher_url'] ?? home_url( '/' ),
			);

			if ( ! empty( $geo['publisher_logo'] ) ) {
				$data['site']['publisher']['logo'] = $geo['publisher_logo'];
			}
		}

		// Content index
		$data['content_index'] = $this->build_content_index();

		// Topics (sitewide tags)
		$data['topics'] = $this->get_site_topics();

		/**
		 * Filter site JSON payload
		 *
		 * @param array<string,mixed> $data Site data.
		 */
		return apply_filters( 'fpseo_geo_site_json', $data );
	}

	/**
	 * Build content index
	 *
	 * @return array<array{id:int,url:string,type:string,lang:string,lastmod:string,hash_id:string}>
	 */
	private function build_content_index(): array {
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
			return array();
		}

		// Query all exposed posts (limit to prevent memory issues on very large sites)
		$query = new \WP_Query(
			array(
				'post_type'              => $enabled_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 1000, // Reasonable limit for site.json index
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$index = array();
		foreach ( $query->posts as $post ) {
			$hash_id = $this->generate_hash_id( $post );
			$lang    = $this->get_post_language( $post->ID );

			$index[] = array(
				'id'      => $post->ID,
				'url'     => get_permalink( $post->ID ),
				'type'    => $post->post_type,
				'lang'    => $lang,
				'lastmod' => mysql2date( 'c', $post->post_modified_gmt, false ),
				'hash_id' => $hash_id,
			);
		}

		return $index;
	}

	/**
	 * Get site-wide topics from tags
	 *
	 * @return array<string>
	 */
	private function get_site_topics(): array {
		$tags = get_tags(
			array(
				'orderby' => 'count',
				'order'   => 'DESC',
				'number'  => 50,
			)
		);

		$topics = array();
		foreach ( $tags as $tag ) {
			$topics[] = $tag->name;
		}

		return $topics;
	}

	/**
	 * Generate stable hash ID for a post
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function generate_hash_id( \WP_Post $post ): string {
		$input = home_url( '/' ) . $post->ID . $post->post_modified_gmt;
		return 'sha1:' . sha1( $input );
	}

	/**
	 * Get site language
	 *
	 * @return string
	 */
	private function get_site_language(): string {
		$locale = get_locale();
		// Convert wp_locale to ISO (e.g., it_IT → it)
		return substr( $locale, 0, 2 );
	}

	/**
	 * Get post language (Polylang/WPML support)
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_post_language( int $post_id ): string {
		// Polylang
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			if ( $lang ) {
				return $lang;
			}
		}

		// WPML
		if ( function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( $post_id );
			if ( isset( $lang_info['locale'] ) ) {
				return substr( $lang_info['locale'], 0, 2 );
			}
		}

		// Fallback to site language
		return $this->get_site_language();
	}
}

