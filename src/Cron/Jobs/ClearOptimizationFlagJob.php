<?php
/**
 * Clear optimization flag cron job.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Cron\Jobs;

/**
 * Cron job to clear optimization flags after a delay.
 *
 * This is used to clear the "optimizing" flag after auto-optimization completes.
 */
class ClearOptimizationFlagJob extends AbstractJob {

	/**
	 * Get the cron hook name.
	 *
	 * @return string Hook name.
	 */
	public function get_hook(): string {
		return 'fp_seo_clear_optimization_flag';
	}

	/**
	 * Get the cron schedule.
	 *
	 * This job is scheduled as a single event, not recurring.
	 *
	 * @return string Schedule name.
	 */
	public function get_schedule(): string {
		return 'hourly'; // Default, but usually scheduled as single event
	}

	/**
	 * Execute the cron job.
	 *
	 * @param array<string, mixed> $args Optional arguments (should contain post_id).
	 * @return void
	 */
	public function execute( array $args = array() ): void {
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( $post_id <= 0 ) {
			return;
		}

		// Clear the optimization flag
		delete_post_meta( $post_id, '_fp_seo_optimizing' );
	}
}











