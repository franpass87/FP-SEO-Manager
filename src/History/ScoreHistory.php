<?php
/**
 * Content Score History Tracker
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\History;

/**
 * Tracks SEO score changes over time
 */
class ScoreHistory {

	/**
	 * Table name (without prefix)
	 */
	private const TABLE_NAME = 'fp_seo_score_history';

	/**
	 * Register hooks
	 *
	 * Note: Activation hook is handled by CoreServiceProvider::activate()
	 * to avoid duplicate registration and ensure proper service provider pattern.
	 */
	public function register(): void {
		add_action( 'fpseo_after_score_calculation', array( $this, 'record_score' ), 10, 2 );
		// Activation hook removed - handled by CoreServiceProvider::activate()
	}

	/**
	 * Create database table
	 */
	public function create_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			score tinyint(3) unsigned NOT NULL,
			status varchar(20) NOT NULL,
			checks_passed tinyint(3) unsigned NOT NULL DEFAULT 0,
			checks_warned tinyint(3) unsigned NOT NULL DEFAULT 0,
			checks_failed tinyint(3) unsigned NOT NULL DEFAULT 0,
			recorded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY recorded_at (recorded_at),
			KEY post_recorded (post_id, recorded_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Record score to history
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $score   Score data.
	 */
	public function record_score( int $post_id, array $score ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Check if a similar record exists in the last 24 hours
		$recent_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, score FROM {$table_name} 
				WHERE post_id = %d 
				AND score = %d 
				AND recorded_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
				LIMIT 1",
				$post_id,
				(int) $score['score']
			)
		);

		// If similar record exists, skip insertion to avoid duplicates
		if ( $recent_record ) {
			return;
		}

		// Check if we need to update the most recent record or insert a new one
		$latest_record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, score, recorded_at FROM {$table_name} 
				WHERE post_id = %d 
				ORDER BY recorded_at DESC 
				LIMIT 1",
				$post_id
			)
		);

		$current_score = (int) $score['score'];
		$status = $score['status'] ?? 'pending';
		$checks_passed = $score['summary']['pass'] ?? 0;
		$checks_warned = $score['summary']['warn'] ?? 0;
		$checks_failed = $score['summary']['fail'] ?? 0;
		$recorded_at = current_time( 'mysql' );

		// If latest score is different, insert new record
		if ( ! $latest_record || (int) $latest_record->score !== $current_score ) {
			$wpdb->insert(
				$table_name,
				array(
					'post_id'        => $post_id,
					'score'          => $current_score,
					'status'         => $status,
					'checks_passed'  => $checks_passed,
					'checks_warned'  => $checks_warned,
					'checks_failed'  => $checks_failed,
					'recorded_at'    => $recorded_at,
				),
				array( '%d', '%d', '%s', '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Get score history for a post
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Number of records.
	 * @return array<object>
	 */
	public function get_history( int $post_id, int $limit = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE post_id = %d ORDER BY recorded_at DESC LIMIT %d",
				$post_id,
				$limit
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Get latest score for a post
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public function get_latest_score( int $post_id ): ?object {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE post_id = %d ORDER BY recorded_at DESC LIMIT 1",
				$post_id
			)
		);

		return $result ?: null;
	}

	/**
	 * Get site-wide score trend
	 *
	 * @param int $days Number of days.
	 * @return array<array{date:string,avg_score:float,count:int}>
	 */
	public function get_site_trend( int $days = 30 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Use a more efficient query with proper indexing
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(recorded_at) as date, 
					ROUND(AVG(score), 1) as avg_score, 
					COUNT(*) as count
				FROM {$table_name}
				WHERE recorded_at >= %s
				GROUP BY DATE(recorded_at)
				ORDER BY date ASC
				LIMIT 100",
				$cutoff_date
			)
		);

		$trend = array();
		foreach ( $results as $row ) {
			$trend[] = array(
				'date'      => $row->date,
				'avg_score' => (float) $row->avg_score,
				'count'     => (int) $row->count,
			);
		}

		return $trend;
	}

	/**
	 * Get score improvement stats
	 *
	 * @param int $post_id Post ID.
	 * @return array{first_score:int,latest_score:int,improvement:int,trend:string}|null
	 */
	public function get_improvement_stats( int $post_id ): ?array {
		$history = $this->get_history( $post_id, 100 );

		if ( count( $history ) < 2 ) {
			return null;
		}

		$latest = $history[0];
		$first  = end( $history );

		$improvement = (int) $latest->score - (int) $first->score;
		$trend       = $improvement > 0 ? 'up' : ( $improvement < 0 ? 'down' : 'stable' );

		return array(
			'first_score'   => (int) $first->score,
			'latest_score'  => (int) $latest->score,
			'improvement'   => $improvement,
			'trend'         => $trend,
			'total_records' => count( $history ),
		);
	}
}

