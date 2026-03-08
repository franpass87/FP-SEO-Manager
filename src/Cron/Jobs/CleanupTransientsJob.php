<?php
/**
 * Cleanup transients cron job.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Cron\Jobs;

use wpdb;

/**
 * Cron job to clean up expired transients.
 */
class CleanupTransientsJob extends AbstractJob {

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
	 * Get the cron hook name.
	 *
	 * @return string Hook name.
	 */
	public function get_hook(): string {
		return 'fp_seo_cleanup_transients';
	}

	/**
	 * Get the cron schedule.
	 *
	 * @return string Schedule name.
	 */
	public function get_schedule(): string {
		return 'daily';
	}

	/**
	 * Execute the cron job.
	 *
	 * @param array<string, mixed> $args Optional arguments.
	 * @return void
	 */
	public function execute( array $args = array() ): void {
		// Delete expired transients
		$time = time();
		$expired = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT option_name FROM {$this->wpdb->options}
				WHERE option_name LIKE %s
				AND option_value < %d",
				$this->wpdb->esc_like( '_transient_timeout_' ) . 'fp_seo_%',
				$time
			)
		);

		if ( empty( $expired ) ) {
			return;
		}

		$options_table = $this->wpdb->options;
		$placeholders  = implode( ',', array_fill( 0, count( $expired ), '%s' ) );

		// Delete expired transient timeouts
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$options_table} WHERE option_name IN ($placeholders)",
				...$expired
			)
		);

		// Delete corresponding transient values
		$transient_names = array_map(
			function ( $name ) {
				return str_replace( '_transient_timeout_', '_transient_', $name );
			},
			$expired
		);

		if ( ! empty( $transient_names ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $transient_names ), '%s' ) );
			$this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM {$options_table} WHERE option_name IN ($placeholders)",
					...$transient_names
				)
			);
		}
	}
}
