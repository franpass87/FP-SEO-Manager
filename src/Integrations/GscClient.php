<?php
/**
 * Google Search Console Client - Service Account Authentication
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Integrations;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

/**
 * GSC API client with service account auth
 */
class GscClient {

	/**
	 * Google Client instance
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * SearchConsole service
	 *
	 * @var SearchConsole|null
	 */
	private ?SearchConsole $service = null;

	/**
	 * Initialize client with service account
	 *
	 * @return bool True if authenticated successfully.
	 */
	public function authenticate(): bool {
		$options = get_option( 'fp_seo_performance', array() );
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['service_account_json'] ) || empty( $gsc['site_url'] ) ) {
			return false;
		}

		try {
			$this->client = new Client();
			$this->client->setApplicationName( 'FP SEO Performance' );
			$this->client->setScopes( array( SearchConsole::WEBMASTERS_READONLY ) );

			// Decode JSON key
			$credentials = json_decode( $gsc['service_account_json'], true );
			if ( ! $credentials ) {
				return false;
			}

			$this->client->setAuthConfig( $credentials );
			$this->service = new SearchConsole( $this->client );

			return true;
		} catch ( \Exception $e ) {
			error_log( 'FP SEO GSC Auth Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get search analytics data
	 *
	 * @param string $start_date Start date (YYYY-MM-DD).
	 * @param string $end_date   End date (YYYY-MM-DD).
	 * @param int    $row_limit  Max rows to return.
	 * @return array<string,mixed>|null
	 */
	public function get_search_analytics( string $start_date, string $end_date, int $row_limit = 1000 ): ?array {
		if ( ! $this->authenticate() ) {
			return null;
		}

		$options  = get_option( 'fp_seo_performance', array() );
		$site_url = $options['gsc']['site_url'] ?? '';

		if ( empty( $site_url ) ) {
			return null;
		}

		try {
			$request = new SearchAnalyticsQueryRequest();
			$request->setStartDate( $start_date );
			$request->setEndDate( $end_date );
			$request->setDimensions( array( 'page', 'query' ) );
			$request->setRowLimit( $row_limit );

			$response = $this->service->searchanalytics->query( $site_url, $request );

			return array(
				'rows'          => $response->getRows(),
				'responseAggregationType' => $response->getResponseAggregationType(),
			);
		} catch ( \Exception $e ) {
			error_log( 'FP SEO GSC Query Error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get data for specific URL
	 *
	 * @param string $url        Page URL.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array{clicks:int,impressions:int,ctr:float,position:float}|null
	 */
	public function get_url_analytics( string $url, string $start_date, string $end_date ): ?array {
		// Cache key based on URL and date range
		$cache_key = 'fp_seo_gsc_url_' . md5( $url . $start_date . $end_date );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! $this->authenticate() ) {
			return null;
		}

		$options  = get_option( 'fp_seo_performance', array() );
		$site_url = $options['gsc']['site_url'] ?? '';

		if ( empty( $site_url ) ) {
			return null;
		}

		try {
			$request = new SearchAnalyticsQueryRequest();
			$request->setStartDate( $start_date );
			$request->setEndDate( $end_date );
			$request->setDimensions( array( 'page' ) );

			// Filter by specific URL
			$dimension_filter = new \Google\Service\SearchConsole\ApiDimensionFilter();
			$dimension_filter->setDimension( 'page' );
			$dimension_filter->setOperator( 'equals' );
			$dimension_filter->setExpression( $url );

			$filter_group = new \Google\Service\SearchConsole\ApiDimensionFilterGroup();
			$filter_group->setFilters( array( $dimension_filter ) );

			$request->setDimensionFilterGroups( array( $filter_group ) );

			$response = $this->service->searchanalytics->query( $site_url, $request );
			$rows     = $response->getRows();

			if ( empty( $rows ) ) {
				return null;
			}

			// Aggregate data
			$totals = array(
				'clicks'      => 0,
				'impressions' => 0,
				'ctr'         => 0.0,
				'position'    => 0.0,
			);

			foreach ( $rows as $row ) {
				$totals['clicks']      += $row->getClicks();
				$totals['impressions'] += $row->getImpressions();
			}

			if ( $totals['impressions'] > 0 ) {
				$totals['ctr'] = round( ( $totals['clicks'] / $totals['impressions'] ) * 100, 2 );
			}

			// Average position weighted by impressions
			$position_sum = 0;
			foreach ( $rows as $row ) {
				$position_sum += $row->getPosition() * $row->getImpressions();
			}
			$totals['position'] = $totals['impressions'] > 0 ? round( $position_sum / $totals['impressions'], 1 ) : 0;

			// Cache for 24 hours
			set_transient( $cache_key, $totals, DAY_IN_SECONDS );

			return $totals;
		} catch ( \Exception $e ) {
			error_log( 'FP SEO GSC URL Query Error: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Test connection
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		if ( ! $this->authenticate() ) {
			return false;
		}

		$options  = get_option( 'fp_seo_performance', array() );
		$site_url = $options['gsc']['site_url'] ?? '';

		if ( empty( $site_url ) ) {
			return false;
		}

		try {
			// Try to list sites to verify access
			$sites = $this->service->sites->listSites();
			return true;
		} catch ( \Exception $e ) {
			error_log( 'FP SEO GSC Connection Test Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get top queries for a page
	 *
	 * @param string $url        Page URL.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Number of queries.
	 * @return array<array{query:string,clicks:int,impressions:int,ctr:float,position:float}>|null
	 */
	public function get_top_queries( string $url, string $start_date, string $end_date, int $limit = 10 ): ?array {
		// Cache key based on URL, date range, and limit
		$cache_key = 'fp_seo_gsc_queries_' . md5( $url . $start_date . $end_date . $limit );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! $this->authenticate() ) {
			return null;
		}

		$options  = get_option( 'fp_seo_performance', array() );
		$site_url = $options['gsc']['site_url'] ?? '';

		try {
			$request = new SearchAnalyticsQueryRequest();
			$request->setStartDate( $start_date );
			$request->setEndDate( $end_date );
			$request->setDimensions( array( 'query' ) );
			$request->setRowLimit( $limit );

			// Filter by URL
			$dimension_filter = new \Google\Service\SearchConsole\ApiDimensionFilter();
			$dimension_filter->setDimension( 'page' );
			$dimension_filter->setOperator( 'equals' );
			$dimension_filter->setExpression( $url );

			$filter_group = new \Google\Service\SearchConsole\ApiDimensionFilterGroup();
			$filter_group->setFilters( array( $dimension_filter ) );

			$request->setDimensionFilterGroups( array( $filter_group ) );

			$response = $this->service->searchanalytics->query( $site_url, $request );
			$rows     = $response->getRows();

			if ( empty( $rows ) ) {
				return array();
			}

			$queries = array();
			foreach ( $rows as $row ) {
				$queries[] = array(
					'query'       => $row->getKeys()[0],
					'clicks'      => $row->getClicks(),
					'impressions' => $row->getImpressions(),
					'ctr'         => round( $row->getCtr() * 100, 2 ),
					'position'    => round( $row->getPosition(), 1 ),
				);
			}

			// Cache for 24 hours
			set_transient( $cache_key, $queries, DAY_IN_SECONDS );

			return $queries;
		} catch ( \Exception $e ) {
			error_log( 'FP SEO GSC Top Queries Error: ' . $e->getMessage() );
			return null;
		}
	}
}

