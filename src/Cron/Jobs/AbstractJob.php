<?php
/**
 * Abstract cron job base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Cron\Jobs;

/**
 * Abstract base class for cron jobs.
 */
abstract class AbstractJob {

	/**
	 * Get the cron hook name.
	 *
	 * @return string Hook name.
	 */
	abstract public function get_hook(): string;

	/**
	 * Get the cron schedule (hourly, daily, twicedaily, etc.).
	 *
	 * @return string Schedule name.
	 */
	abstract public function get_schedule(): string;

	/**
	 * Execute the cron job.
	 *
	 * @param array<string, mixed> $args Optional arguments.
	 * @return void
	 */
	abstract public function execute( array $args = array() ): void;

	/**
	 * Register the cron job.
	 *
	 * @return void
	 */
	public function register(): void {
		$hook = $this->get_hook();

		// Register the hook callback
		add_action( $hook, array( $this, 'handle' ) );

		// Schedule the event if not already scheduled
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $this->get_schedule(), $hook );
		}
	}

	/**
	 * Unregister the cron job.
	 *
	 * @return void
	 */
	public function unregister(): void {
		$hook = $this->get_hook();

		// Unschedule the event
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		// Remove the hook
		remove_action( $hook, array( $this, 'handle' ) );
	}

	/**
	 * Handle the cron event (WordPress hook callback).
	 *
	 * @param array<string, mixed> $args Optional arguments.
	 * @return void
	 */
	public function handle( array $args = array() ): void {
		$this->execute( $args );
	}
}



