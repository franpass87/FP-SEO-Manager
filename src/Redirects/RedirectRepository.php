<?php
/**
 * Redirect Repository - CRUD for redirects.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

use FP\SEO\Utils\UrlNormalizer;
use wpdb;

/**
 * Handles redirect storage and retrieval.
 */
class RedirectRepository {

	/**
	 * Table name (without prefix).
	 */
	private const TABLE = 'fp_seo_redirects';

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb WordPress database instance.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	private function get_table(): string {
		return $this->wpdb->prefix . self::TABLE;
	}

	/**
	 * Find redirect by source URL (exact or regex).
	 *
	 * @param string $request_uri Request URI (e.g. /old-page/ or /category/post-name/).
	 * @return array{id: int, source_url: string, target_url: string, redirect_type: string, is_regex: int}|null
	 */
	public function find_by_source( string $request_uri ): ?array {
		$table = $this->get_table();

		// Normalize: remove query string for lookup
		$path = $this->normalize_path( $request_uri );

		// First try exact match
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id, source_url, target_url, redirect_type, is_regex FROM {$table} 
				WHERE is_active = 1 AND source_url = %s LIMIT 1",
				$path
			),
			ARRAY_A
		);

		if ( $row ) {
			return $row;
		}

		// Then try regex matches (source_url = pattern, target_url = replacement with $1, $2)
		$regex_rows = $this->wpdb->get_results(
			"SELECT id, source_url, target_url, redirect_type, is_regex FROM {$table} 
			WHERE is_active = 1 AND is_regex = 1",
			ARRAY_A
		);

		foreach ( (array) $regex_rows as $row ) {
			$pattern = $row['source_url'];
			if ( @preg_match( '#' . $pattern . '#u', $path ) ) {
				$replacement = $row['target_url'];
				$target      = preg_replace( '#' . $pattern . '#u', $replacement, $path );
				if ( is_string( $target ) ) {
					$row['target_url'] = ( str_starts_with( $target, 'http' ) || str_starts_with( $target, '/' ) ) ? $target : '/' . $target;
					return $row;
				}
			}
		}

		return null;
	}

	/**
	 * Get all redirects with optional filters.
	 *
	 * @param array{search?: string, type?: string, active?: bool, per_page?: int, offset?: int} $args Query args.
	 * @return array{array{id: int, source_url: string, target_url: string, redirect_type: string, is_regex: int, is_active: int, hits: int, created_at: string, updated_at: string}}
	 */
	public function get_all( array $args = array() ): array {
		$table   = $this->get_table();
		$where   = array( '1=1' );
		$values  = array();

		if ( isset( $args['search'] ) && '' !== trim( $args['search'] ) ) {
			$where[]  = '(source_url LIKE %s OR target_url LIKE %s)';
			$term     = '%' . $this->wpdb->esc_like( trim( $args['search'] ) ) . '%';
			$values[] = $term;
			$values[] = $term;
		}

		if ( isset( $args['type'] ) && in_array( $args['type'], array( '301', '302' ), true ) ) {
			$where[]  = 'redirect_type = %s';
			$values[] = $args['type'];
		}

		if ( isset( $args['active'] ) ) {
			$where[]  = 'is_active = %d';
			$values[] = $args['active'] ? 1 : 0;
		}

		$sql_where = implode( ' AND ', $where );
		$limit    = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 100;
		$offset   = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$sql = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		$results = $this->wpdb->get_results(
			count( $values ) > 0 ? $this->wpdb->prepare( $sql, ...$values ) : $sql,
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Count redirects.
	 *
	 * @param array{search?: string, type?: string, active?: bool} $args Optional filters.
	 * @return int
	 */
	public function count( array $args = array() ): int {
		$table  = $this->get_table();
		$where  = array( '1=1' );
		$values = array();

		if ( isset( $args['search'] ) && '' !== trim( $args['search'] ) ) {
			$where[]  = '(source_url LIKE %s OR target_url LIKE %s)';
			$term     = '%' . $this->wpdb->esc_like( trim( $args['search'] ) ) . '%';
			$values[] = $term;
			$values[] = $term;
		}

		if ( isset( $args['type'] ) && in_array( $args['type'], array( '301', '302' ), true ) ) {
			$where[]  = 'redirect_type = %s';
			$values[] = $args['type'];
		}

		if ( isset( $args['active'] ) ) {
			$where[]  = 'is_active = %d';
			$values[] = $args['active'] ? 1 : 0;
		}

		$sql_where = implode( ' AND ', $where );
		$sql      = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";

		return (int) ( count( $values ) > 0 ? $this->wpdb->get_var( $this->wpdb->prepare( $sql, ...$values ) ) : $this->wpdb->get_var( $sql ) );
	}

	/**
	 * Create a redirect.
	 *
	 * @param array{source_url: string, target_url: string, redirect_type?: string, is_regex?: bool} $data Redirect data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function create( array $data ) {
		$table = $this->get_table();
		$now   = current_time( 'mysql' );

		$insert = array(
			'source_url'    => $this->sanitize_url_path( $data['source_url'] ),
			'target_url'    => esc_url_raw( $data['target_url'] ),
			'redirect_type' => isset( $data['redirect_type'] ) && $data['redirect_type'] === '302' ? '302' : '301',
			'is_regex'      => ! empty( $data['is_regex'] ) ? 1 : 0,
			'is_active'     => 1,
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$result = $this->wpdb->insert( $table, $insert );

		return $result ? (int) $this->wpdb->insert_id : false;
	}

	/**
	 * Update a redirect.
	 *
	 * @param int   $id   Redirect ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		$table = $this->get_table();
		$data['updated_at'] = current_time( 'mysql' );

		if ( isset( $data['source_url'] ) ) {
			$data['source_url'] = $this->sanitize_url_path( $data['source_url'] );
		}
		if ( isset( $data['target_url'] ) ) {
			$data['target_url'] = esc_url_raw( $data['target_url'] );
		}
		if ( isset( $data['redirect_type'] ) ) {
			$data['redirect_type'] = $data['redirect_type'] === '302' ? '302' : '301';
		}
		if ( isset( $data['is_regex'] ) ) {
			$data['is_regex'] = $data['is_regex'] ? 1 : 0;
		}
		if ( isset( $data['is_active'] ) ) {
			$data['is_active'] = $data['is_active'] ? 1 : 0;
		}

		$allowed = array( 'source_url', 'target_url', 'redirect_type', 'is_regex', 'is_active', 'updated_at' );
		$update  = array_intersect_key( $data, array_flip( $allowed ) );

		return $this->wpdb->update( $table, $update, array( 'id' => $id ) ) !== false;
	}

	/**
	 * Increment hits for a redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public function increment_hits( int $id ): bool {
		$table = $this->get_table();
		return $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$table} SET hits = hits + 1, updated_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				$id
			)
		) !== false;
	}

	/**
	 * Delete a redirect.
	 *
	 * @param int $id Redirect ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$table = $this->get_table();
		return $this->wpdb->delete( $table, array( 'id' => $id ) ) !== false;
	}

	/**
	 * Bulk create redirects.
	 *
	 * @param array<int, array{source_url: string, target_url: string, redirect_type?: string}> $rows Rows to insert.
	 * @return array{inserted: int, skipped: int, errors: array<string>}
	 */
	public function bulk_create( array $rows ): array {
		$inserted = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $row ) {
			$source = $this->sanitize_url_path( $row['source_url'] ?? '' );
			$target = esc_url_raw( $row['target_url'] ?? '' );

			if ( '' === $source || '' === $target ) {
				$errors[] = sprintf(
					/* translators: 1: source URL, 2: target URL */
					__( 'Invalid row: source="%1$s" target="%2$s"', 'fp-seo-performance' ),
					$row['source_url'] ?? '',
					$row['target_url'] ?? ''
				);
				++$skipped;
				continue;
			}

			$id = $this->create( array(
				'source_url'    => $source,
				'target_url'    => $target,
				'redirect_type' => $row['redirect_type'] ?? '301',
			) );

			if ( $id ) {
				++$inserted;
			} else {
				$errors[] = sprintf(
					/* translators: %s: source URL */
					__( 'Failed to insert: %s', 'fp-seo-performance' ),
					$source
				);
				++$skipped;
			}
		}

		return array( 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors );
	}

	/**
	 * Normalize path for lookup (same format as storage).
	 *
	 * @param string $uri Request URI or path.
	 * @return string
	 */
	private function normalize_path( string $uri ): string {
		return UrlNormalizer::normalize_path( $uri );
	}

	/**
	 * Sanitize URL path for storage and lookup.
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	private function sanitize_url_path( string $url ): string {
		return UrlNormalizer::normalize_path( $url );
	}
}
