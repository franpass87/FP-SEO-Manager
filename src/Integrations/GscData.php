<?php
/**
 * GSC Data Manager - Fetch and cache GSC metrics
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

/**
 * Manages GSC data fetching and caching
 */
class GscData {

	/**
	 * GSC Client instance
	 *
	 * @var GscClient
	 */
	private GscClient $client;

	/**
	 * Cache TTL (1 hour)
	 *
	 * @var int
	 */
	private const CACHE_TTL = 3600;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->client = new GscClient();
	}

	/**
	 * Get site-wide metrics for last N days
	 *
	 * @param int $days Number of days (default: 28).
	 * @return array{clicks:int,impressions:int,ctr:float,position:float,period:string}|null
	 */
	public function get_site_metrics( int $days = 28 ): ?array {
		// Check cache
		$cache_key = 'fp_seo_gsc_site_metrics_' . $days;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) ); // GSC has 2-3 days delay
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$data = $this->client->get_search_analytics( $start_date, $end_date, 10000 );

		if ( ! $data || empty( $data['rows'] ) ) {
			return null;
		}

		// Aggregate totals
		$totals = array(
			'clicks'      => 0,
			'impressions' => 0,
			'ctr'         => 0.0,
			'position'    => 0.0,
			'period'      => $days . ' days',
		);

		$position_sum = 0;

		foreach ( $data['rows'] as $row ) {
			$totals['clicks']      += $row->getClicks();
			$totals['impressions'] += $row->getImpressions();
			$position_sum          += $row->getPosition() * $row->getImpressions();
		}

		if ( $totals['impressions'] > 0 ) {
			$totals['ctr']      = round( ( $totals['clicks'] / $totals['impressions'] ) * 100, 2 );
			$totals['position'] = round( $position_sum / $totals['impressions'], 1 );
		}

		// Cache for 1 hour
		set_transient( $cache_key, $totals, self::CACHE_TTL );

		return $totals;
	}

	/**
	 * Get metrics for specific post
	 *
	 * @param int $post_id Post ID.
	 * @param int $days    Number of days.
	 * @return array{clicks:int,impressions:int,ctr:float,position:float,queries:array}|null
	 */
	public function get_post_metrics( int $post_id, int $days = 28 ): ?array {
		// Check cache
		$cache_key = 'fp_seo_gsc_post_' . $post_id . '_' . $days;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return null;
		}

		$end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Get URL analytics
		$analytics = $this->client->get_url_analytics( $url, $start_date, $end_date );

		if ( ! $analytics ) {
			return null;
		}

		// Get top queries
		$queries = $this->client->get_top_queries( $url, $start_date, $end_date, 10 );

		$data = array_merge( $analytics, array( 'queries' => $queries ?? array() ) );

		// Cache for 1 hour
		set_transient( $cache_key, $data, self::CACHE_TTL );

		return $data;
	}

	/**
	 * Get top performing pages
	 *
	 * @param int $days  Number of days.
	 * @param int $limit Number of pages.
	 * @return array<array{url:string,clicks:int,impressions:int,ctr:float,position:float}>|null
	 */
	public function get_top_pages( int $days = 28, int $limit = 10 ): ?array {
		// Check cache
		$cache_key = 'fp_seo_gsc_top_pages_' . $days . '_' . $limit;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$end_date   = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		$data = $this->client->get_search_analytics( $start_date, $end_date, $limit );

		if ( ! $data || empty( $data['rows'] ) ) {
			return null;
		}

		$pages = array();
		$aggregated = array();

		// Aggregate by page URL
		foreach ( $data['rows'] as $row ) {
			$keys = $row->getKeys();
			$page_url = $keys[0] ?? '';

			if ( empty( $page_url ) ) {
				continue;
			}

			if ( ! isset( $aggregated[ $page_url ] ) ) {
				$aggregated[ $page_url ] = array(
					'url'         => $page_url,
					'clicks'      => 0,
					'impressions' => 0,
					'positions'   => array(),
				);
			}

			$aggregated[ $page_url ]['clicks']      += $row->getClicks();
			$aggregated[ $page_url ]['impressions'] += $row->getImpressions();
			$aggregated[ $page_url ]['positions'][] = array(
				'pos'   => $row->getPosition(),
				'weight' => $row->getImpressions(),
			);
		}

		// Calculate CTR and avg position
		foreach ( $aggregated as $url => $page_data ) {
			$ctr = 0;
			if ( $page_data['impressions'] > 0 ) {
				$ctr = round( ( $page_data['clicks'] / $page_data['impressions'] ) * 100, 2 );
			}

			$position_sum = 0;
			$weight_sum   = 0;
			foreach ( $page_data['positions'] as $pos_data ) {
				$position_sum += $pos_data['pos'] * $pos_data['weight'];
				$weight_sum   += $pos_data['weight'];
			}
			$avg_position = $weight_sum > 0 ? round( $position_sum / $weight_sum, 1 ) : 0;

			$pages[] = array(
				'url'         => $url,
				'clicks'      => $page_data['clicks'],
				'impressions' => $page_data['impressions'],
				'ctr'         => $ctr,
				'position'    => $avg_position,
			);
		}

		// Sort by clicks DESC
		usort( $pages, function ( $a, $b ) {
			return $b['clicks'] <=> $a['clicks'];
		} );

		$pages = array_slice( $pages, 0, $limit );

		// Cache for 1 hour
		set_transient( $cache_key, $pages, self::CACHE_TTL );

		return $pages;
	}

	/**
	 * Flush all GSC caches
	 */
	public static function flush_cache(): void {
		global $wpdb;

		// Delete all transients starting with fp_seo_gsc_ (use prepared statement for security)
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_fp_seo_gsc_%', '_transient_timeout_fp_seo_gsc_%' ) );
	}
}

