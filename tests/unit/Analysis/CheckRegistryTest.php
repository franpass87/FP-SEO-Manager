<?php
/**
 * Tests for CheckRegistry.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Analysis;

use Brain\Monkey\Functions;
use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\CheckRegistry;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;

/**
 * Test case for CheckRegistry.
 */
class CheckRegistryTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

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
	 * Test filter_enabled_checks returns all checks when no configuration.
	 */
	public function test_filter_enabled_checks_returns_all_when_no_config(): void {
		$check1 = $this->createMockCheck( 'check_one' );
		$check2 = $this->createMockCheck( 'check_two' );
		$checks = array( $check1, $check2 );

		$context = new Context( null, 'content', 'title', '', null, null );

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'apply_filters' )->returnArg( 1 );

		$result = CheckRegistry::filter_enabled_checks( $checks, $context );

		$this->assertCount( 2, $result );
	}

	/**
	 * Test filter_enabled_checks filters based on configuration.
	 */
	public function test_filter_enabled_checks_filters_by_config(): void {
		$check1 = $this->createMockCheck( 'check_one' );
		$check2 = $this->createMockCheck( 'check_two' );
		$checks = array( $check1, $check2 );

		$context = new Context( null, 'content', 'title', '', null, null );

		Functions\when( 'get_option' )->justReturn(
			array(
				'analysis' => array(
					'checks' => array(
						'check_one' => true,
						'check_two' => false,
					),
				),
			)
		);
		Functions\when( 'apply_filters' )->returnArg( 1 );

		$result = CheckRegistry::filter_enabled_checks( $checks, $context );

		$this->assertCount( 1, $result );
		$this->assertSame( $check1, $result[0] );
	}

	/**
	 * Test filter_enabled_checks applies WordPress filter.
	 */
	public function test_filter_enabled_checks_applies_filter(): void {
		$check1 = $this->createMockCheck( 'check_one' );
		$check2 = $this->createMockCheck( 'check_two' );
		$checks = array( $check1, $check2 );

		$context = new Context( null, 'content', 'title', '', null, null );

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'apply_filters' )
			->justReturn( array( 'check_one' ) );

		$result = CheckRegistry::filter_enabled_checks( $checks, $context );

		$this->assertCount( 1, $result );
	}

	/**
	 * Test filter_enabled_checks handles empty checks array.
	 */
	public function test_filter_enabled_checks_handles_empty_array(): void {
		$checks  = array();
		$context = new Context( null, 'content', 'title', '', null, null );

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'apply_filters' )->returnArg( 1 );

		$result = CheckRegistry::filter_enabled_checks( $checks, $context );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Creates a mock check with given ID.
	 *
	 * @param string $id Check identifier.
	 *
	 * @return CheckInterface
	 */
	private function createMockCheck( string $id ): CheckInterface {
		$check = \Mockery::mock( CheckInterface::class );
		$check->shouldReceive( 'id' )->andReturn( $id );
		$check->shouldReceive( 'label' )->andReturn( 'Label for ' . $id );
		$check->shouldReceive( 'description' )->andReturn( 'Description for ' . $id );

		return $check;
	}
}