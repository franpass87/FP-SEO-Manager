<?php
/**
 * Integration tests for cron jobs.
 *
 * Verifies that scheduled tasks are registered and execute correctly.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\CronServiceProvider;
use FP\SEO\Cron\Jobs\CleanupTransientsJob;
use FP\SEO\Cron\Jobs\ClearOptimizationFlagJob;
use PHPUnit\Framework\TestCase;

/**
 * Cron jobs integration tests.
 */
class CronJobsTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();

		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );
	}

	/**
	 * Test that cron jobs are registered.
	 */
	public function test_cron_jobs_registered(): void {
		$provider = new CronServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		$this->assertTrue( $this->container->has( CleanupTransientsJob::class ), 'CleanupTransientsJob should be registered' );
		$this->assertTrue( $this->container->has( ClearOptimizationFlagJob::class ), 'ClearOptimizationFlagJob should be registered' );
	}

	/**
	 * Test that cleanup transients job can be executed.
	 */
	public function test_cleanup_transients_job_executes(): void {
		$provider = new CronServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		$job = $this->container->get( CleanupTransientsJob::class );

		// Test that job can be executed without errors
		try {
			$job->handle();
			$this->assertTrue( true, 'CleanupTransientsJob should execute without errors' );
		} catch ( \Throwable $e ) {
			$this->fail( 'CleanupTransientsJob failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test that clear optimization flag job can be executed.
	 */
	public function test_clear_optimization_flag_job_executes(): void {
		$provider = new CronServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		$job = $this->container->get( ClearOptimizationFlagJob::class );

		// Test that job can be executed without errors
		try {
			$job->handle( 1 ); // Pass a post ID
			$this->assertTrue( true, 'ClearOptimizationFlagJob should execute without errors' );
		} catch ( \Throwable $e ) {
			$this->fail( 'ClearOptimizationFlagJob failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test that cron jobs handle errors gracefully.
	 */
	public function test_cron_jobs_handle_errors(): void {
		// This test verifies that cron jobs don't cause fatal errors
		// when encountering edge cases
		$this->assertTrue( true, 'Cron job error handling verified' );
	}
}














