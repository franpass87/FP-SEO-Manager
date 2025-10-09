<?php
/**
 * Tests for Logger utility.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Utils;

use Brain\Monkey\Actions;
use FP\SEO\Utils\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Logger class.
 *
 * @covers \FP\SEO\Utils\Logger
 */
class LoggerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test logging does not occur when WP_DEBUG is disabled.
	 */
	public function test_no_logging_when_debug_disabled(): void {
		if ( defined( 'WP_DEBUG' ) ) {
			$this->markTestSkipped( 'Cannot test with WP_DEBUG already defined' );
		}

		Actions\expectAdded( 'fp_seo_log' )->never();

		Logger::info( 'Test message' );
	}

	/**
	 * Test info level logging.
	 */
	public function test_info_level_logging(): void {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		Actions\expectDone( 'fp_seo_log' )
			->once()
			->with(
				Logger::INFO,
				'Test info message',
				array(),
				\Mockery::type( 'string' )
			);

		Logger::info( 'Test info message' );
	}

	/**
	 * Test error level logging.
	 */
	public function test_error_level_logging(): void {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		Actions\expectDone( 'fp_seo_log' )
			->once()
			->with(
				Logger::ERROR,
				'Test error message',
				array( 'code' => 500 ),
				\Mockery::type( 'string' )
			);

		Logger::error( 'Test error message', array( 'code' => 500 ) );
	}

	/**
	 * Test warning level logging.
	 */
	public function test_warning_level_logging(): void {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		Actions\expectDone( 'fp_seo_log' )
			->once()
			->whenHappen(
				static function ( $level, $message, $context, $formatted ) {
					return $level === Logger::WARNING && $message === 'Test warning';
				}
			);

		Logger::warning( 'Test warning' );
	}

	/**
	 * Test context interpolation in log messages.
	 */
	public function test_context_interpolation(): void {
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		Actions\expectDone( 'fp_seo_log' )
			->once()
			->whenHappen(
				static function ( $level, $message, $context, $formatted ) {
					// Check that formatted message contains interpolated values.
					return strpos( $formatted, 'User john logged in' ) !== false;
				}
			);

		Logger::info( 'User {username} logged in', array( 'username' => 'john' ) );
	}
}
