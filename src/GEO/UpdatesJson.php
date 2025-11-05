<?php
/**
 * Updates JSON Generator - Creates /geo/updates.json
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Generates updates feed JSON
 */
class UpdatesJson {

	/**
	 * Generate updates JSON data
	 *
	 * @return array<string,mixed>
	 */
	public function generate(): array {
		// Check cache
		$cached = get_transient( 'fp_seo_geo_updates_json' );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$data = $this->build_data();

		// Cache for 5 minutes
		set_transient( 'fp_seo_geo_updates_json', $data, 300 );

		return $data;
	}

	/**
	 * Build updates data structure
	 *
	 * @return array<string,mixed>
	 */
	private function build_data(): array {
		$updates = $this->get_recent_updates();

		$data = array(
			'updates'     => $updates,
			'total'       => count( $updates ),
			'generated'   => current_time( 'c' ),
			'nextUpdate'  => gmdate( 'c', time() + 300 ), // Next update in 5 min
		);

		/**
		 * Filter updates JSON payload
		 *
		 * @param array<string,mixed> $data Updates data.
		 */
		return apply_filters( 'fpseo_geo_updates_json', $data );
	}

	/**
	 * Get recent updates (last 100 modified posts)
	 *
	 * @return array<array{id:int,url:string,lastmod:string,change:string}>
	 */
	private function get_recent_updates(): array {
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

		// Query last 100 modified posts
		$query = new \WP_Query(
			array(
				'post_type'              => $enabled_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$updates = array();
		foreach ( $query->posts as $post ) {
			$change = $this->determine_change_type( $post );

			$updates[] = array(
				'id'      => $post->ID,
				'url'     => get_permalink( $post->ID ),
				'lastmod' => mysql2date( 'c', $post->post_modified_gmt, false ),
				'change'  => $change,
			);
		}

		return $updates;
	}

	/**
	 * Determine change type (created/updated)
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function determine_change_type( \WP_Post $post ): string {
		$created  = strtotime( $post->post_date_gmt );
		$modified = strtotime( $post->post_modified_gmt );

		// If modified within 5 minutes of creation, consider it "created"
		if ( abs( $modified - $created ) < 300 ) {
			return 'created';
		}

		return 'updated';
	}
}

