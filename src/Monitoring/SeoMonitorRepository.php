<?php
/**
 * SEO monitoring storage helper.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Monitoring;

/**
 * Stores 404 and broken-link metrics in options.
 */
final class SeoMonitorRepository {
	private const OPTION_404_LOG = 'fp_seo_monitor_404_log';
	private const OPTION_BROKEN_LINKS = 'fp_seo_monitor_broken_links';

	/**
	 * Log a 404 path hit.
	 *
	 * @param string $path Request path.
	 * @param string $referrer Optional referrer URL.
	 * @return void
	 */
	public static function log_404( string $path, string $referrer = '' ): void {
		$path = '/' . trim( $path, '/' );
		if ( '/' === $path || '' === trim( $path ) ) {
			return;
		}

		$log = get_option( self::OPTION_404_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		$key = md5( $path );

		if ( ! isset( $log[ $key ] ) || ! is_array( $log[ $key ] ) ) {
			$log[ $key ] = array(
				'path'      => $path,
				'hits'      => 0,
				'last_seen' => 0,
				'referrer'  => '',
			);
		}

		$log[ $key ]['hits']      = (int) ( $log[ $key ]['hits'] ?? 0 ) + 1;
		$log[ $key ]['last_seen'] = time();
		if ( '' !== $referrer ) {
			$log[ $key ]['referrer'] = esc_url_raw( $referrer );
		}

		uasort(
			$log,
			static fn( array $a, array $b ): int => (int) ( $b['last_seen'] ?? 0 ) <=> (int) ( $a['last_seen'] ?? 0 )
		);
		$log = array_slice( $log, 0, 400, true );
		update_option( self::OPTION_404_LOG, $log, false );
	}

	/**
	 * Get top 404 records by hits.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array{path:string,hits:int,last_seen:int,referrer:string}>
	 */
	public static function top_404( int $limit = 20 ): array {
		$rows = get_option( self::OPTION_404_LOG, array() );
		$rows = is_array( $rows ) ? array_values( $rows ) : array();
		usort(
			$rows,
			static fn( array $a, array $b ): int => (int) ( $b['hits'] ?? 0 ) <=> (int) ( $a['hits'] ?? 0 )
		);
		return array_slice( $rows, 0, max( 1, $limit ) );
	}

	/**
	 * Count 404 hits in last 24h.
	 *
	 * @return int
	 */
	public static function count_404_last_24h(): int {
		$rows   = get_option( self::OPTION_404_LOG, array() );
		$rows   = is_array( $rows ) ? $rows : array();
		$since  = time() - DAY_IN_SECONDS;
		$total  = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (int) ( $row['last_seen'] ?? 0 ) >= $since ) {
				$total += (int) ( $row['hits'] ?? 0 );
			}
		}
		return $total;
	}

	/**
	 * Persist broken links snapshot.
	 *
	 * @param array<int,array{source_post_id:int,source_post_title:string,broken_url:string,reason:string}> $rows Findings.
	 * @return void
	 */
	public static function set_broken_links( array $rows ): void {
		$data = array(
			'updated_at' => time(),
			'items'      => array_slice( $rows, 0, 300 ),
		);
		update_option( self::OPTION_BROKEN_LINKS, $data, false );
	}

	/**
	 * Get broken links list.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array{source_post_id:int,source_post_title:string,broken_url:string,reason:string}>
	 */
	public static function get_broken_links( int $limit = 30 ): array {
		$data = get_option( self::OPTION_BROKEN_LINKS, array() );
		$data = is_array( $data ) ? $data : array();
		$rows = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
		return array_slice( $rows, 0, max( 1, $limit ) );
	}

	/**
	 * Count broken links in current snapshot.
	 *
	 * @return int
	 */
	public static function broken_links_count(): int {
		return count( self::get_broken_links( 1000 ) );
	}
}

