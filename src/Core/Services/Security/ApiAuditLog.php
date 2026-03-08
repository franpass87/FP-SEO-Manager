<?php
/**
 * API access audit logging service.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Security;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Logs API access for audit purposes.
 */
class ApiAuditLog {

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Audit log table name.
	 */
	private const TABLE_NAME = 'fp_seo_api_audit';

	/**
	 * Maximum log entries to keep.
	 */
	private const MAX_ENTRIES = 1000;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Log API access.
	 *
	 * @param string $api_name API name (e.g., 'openai', 'gsc').
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param int    $response_code HTTP response code.
	 * @param bool   $success Whether request was successful.
	 * @param array<string, mixed> $metadata Additional metadata.
	 * @return void
	 */
	public function log_access( string $api_name, string $endpoint, string $method, int $response_code, bool $success, array $metadata = array() ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Ensure table exists
		$this->ensure_table_exists();

		$user_id = get_current_user_id();
		$ip = $this->get_client_ip();
		$timestamp = current_time( 'mysql' );

		$data = array(
			'api_name' => sanitize_text_field( $api_name ),
			'endpoint' => sanitize_text_field( $endpoint ),
			'method' => sanitize_text_field( $method ),
			'response_code' => $response_code,
			'success' => $success ? 1 : 0,
			'user_id' => $user_id,
			'ip_address' => sanitize_text_field( $ip ),
			'metadata' => wp_json_encode( $metadata ),
			'created_at' => $timestamp,
		);

		$wpdb->insert( $table_name, $data );

		// Cleanup old entries
		$this->cleanup_old_entries();

		// Also log to WordPress logger for immediate visibility
		if ( $success ) {
			$this->logger->info( 'API access logged', array(
				'api_name'      => $api_name,
				'endpoint'      => $endpoint,
				'response_code' => $response_code,
				'success'       => $success,
			) );
		} else {
			$this->logger->warning( 'API access logged', array(
				'api_name'      => $api_name,
				'endpoint'      => $endpoint,
				'response_code' => $response_code,
				'success'       => $success,
			) );
		}
	}

	/**
	 * Get audit log entries.
	 *
	 * @param array<string, mixed> $filters Filters (api_name, user_id, success, date_from, date_to).
	 * @param int    $limit Limit results.
	 * @param int    $offset Offset.
	 * @return array<int, array<string, mixed>> Audit log entries.
	 */
	public function get_entries( array $filters = array(), int $limit = 100, int $offset = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$where = array( '1=1' );
		$where_values = array();

		if ( ! empty( $filters['api_name'] ) ) {
			$where[] = 'api_name = %s';
			$where_values[] = $filters['api_name'];
		}

		if ( isset( $filters['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$where_values[] = (int) $filters['user_id'];
		}

		if ( isset( $filters['success'] ) ) {
			$where[] = 'success = %d';
			$where_values[] = $filters['success'] ? 1 : 0;
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$where_values[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$where_values[] = $filters['date_to'];
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, array( $limit, $offset ) );
		$prepared     = $wpdb->prepare( $query, $query_values );
		return $wpdb->get_results( $prepared, ARRAY_A ) ?: array();
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP.
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Ensure audit log table exists.
	 *
	 * @return void
	 */
	private function ensure_table_exists(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			api_name varchar(50) NOT NULL,
			endpoint varchar(255) NOT NULL,
			method varchar(10) NOT NULL,
			response_code int(11) NOT NULL,
			success tinyint(1) NOT NULL DEFAULT 0,
			user_id bigint(20) UNSIGNED NOT NULL,
			ip_address varchar(45) NOT NULL,
			metadata text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY api_name (api_name),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY success (success)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Cleanup old entries.
	 *
	 * @return void
	 */
	private function cleanup_old_entries(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Get current count
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $count > self::MAX_ENTRIES ) {
			// Delete oldest entries
			$to_delete = $count - self::MAX_ENTRIES;
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table_name} ORDER BY created_at ASC LIMIT %d",
				$to_delete
			) );
		}
	}
}




