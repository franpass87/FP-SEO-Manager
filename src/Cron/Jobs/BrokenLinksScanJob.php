<?php
/**
 * Cron job that scans internal broken links.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Cron\Jobs;

use FP\SEO\Monitoring\SeoMonitorRepository;

/**
 * Scans post content to detect unresolved internal links.
 */
class BrokenLinksScanJob extends AbstractJob {
	/**
	 * {@inheritDoc}
	 */
	public function get_hook(): string {
		return 'fp_seo_scan_broken_links';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_schedule(): string {
		return 'daily';
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $args = array() ): void {
		$posts = get_posts(
			array(
				'post_type'              => get_post_types( array( 'public' => true ), 'names' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 40,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$broken = array();
		foreach ( $posts as $post ) {
			$content = (string) ( $post->post_content ?? '' );
			if ( '' === $content ) {
				continue;
			}

			preg_match_all( '/href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches );
			$urls = isset( $matches[1] ) && is_array( $matches[1] ) ? $matches[1] : array();

			foreach ( $urls as $url ) {
				$url = trim( (string) $url );
				if ( '' === $url || str_starts_with( $url, '#' ) || str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) ) {
					continue;
				}

				$is_internal_absolute = str_starts_with( $url, home_url() );
				$is_relative          = str_starts_with( $url, '/' );
				if ( ! $is_internal_absolute && ! $is_relative ) {
					continue;
				}

				$normalized = $is_relative ? home_url( $url ) : $url;
				if ( url_to_postid( $normalized ) > 0 ) {
					continue;
				}

				$path = wp_parse_url( $normalized, PHP_URL_PATH );
				if ( ! is_string( $path ) || '' === trim( $path ) || '/' === $path ) {
					continue;
				}

				$broken[] = array(
					'source_post_id'    => (int) $post->ID,
					'source_post_title' => (string) $post->post_title,
					'broken_url'        => $normalized,
					'reason'            => 'not_mapped_to_post',
				);
			}
		}

		$broken = array_values(
			array_reduce(
				$broken,
				static function( array $carry, array $item ): array {
					$key = (int) $item['source_post_id'] . '|' . (string) $item['broken_url'];
					$carry[ $key ] = $item;
					return $carry;
				},
				array()
			)
		);

		SeoMonitorRepository::set_broken_links( $broken );
	}
}

