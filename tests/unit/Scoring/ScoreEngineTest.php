<?php
/**
 * Score engine unit coverage.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Scoring;

use Brain\Monkey;
use FP\SEO\Analysis\Result;
use FP\SEO\Scoring\ScoreEngine;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests covering the score engine.
 *
 * @covers \FP\SEO\Scoring\ScoreEngine
 */
class ScoreEngineTest extends TestCase {
		/**
		 * Bootstraps Brain Monkey expectations.
		 */
	protected function setUp(): void {
			parent::setUp();
			Monkey\setUp();
			when( '__' )->returnArg( 1 );
	}

		/**
		 * Tears down Brain Monkey state.
		 */
	protected function tearDown(): void {
			Monkey\tearDown();
			parent::tearDown();
	}

		/**
		 * Verifies scores translate into traffic-light statuses and recommendations.
		 */
	public function test_calculate_scales_with_weights_and_recommendations(): void {
			$engine = new ScoreEngine(
				static function (): array {
							return array(
								'title_length'     => 2.0,
								'meta_description' => 1.0,
							);
				}
			);

			$checks = array(
				'title_length'     => array(
					'status'   => Result::STATUS_PASS,
					'weight'   => 0.3,
					'label'    => 'Title length',
					'fix_hint' => 'Adjust the title.',
				),
				'meta_description' => array(
					'status'   => Result::STATUS_WARN,
					'weight'   => 0.3,
					'label'    => 'Meta description',
					'fix_hint' => 'Improve the summary.',
				),
			);

			$result = $engine->calculate( $checks );

			self::assertSame( 83, $result['score'] );
			self::assertSame( 'green', $result['status'] );
			self::assertNotEmpty( $result['recommendations'] );
			self::assertStringContainsString( 'Meta description', $result['recommendations'][0] );
	}

		/**
		 * Ensures threshold boundaries produce red, yellow, and green statuses.
		 */
	public function test_calculate_respects_status_thresholds(): void {
			$engine = new ScoreEngine(
				static function (): array {
							return array(
								'title_length'     => 1.0,
								'meta_description' => 1.0,
							);
				}
			);

			$base = array(
				'title_length'     => array(
					'status'   => Result::STATUS_PASS,
					'weight'   => 0.5,
					'label'    => 'Title length',
					'fix_hint' => 'Keep title optimized.',
				),
				'meta_description' => array(
					'status'   => Result::STATUS_PASS,
					'weight'   => 0.5,
					'label'    => 'Meta description',
					'fix_hint' => 'Write a compelling summary.',
				),
			);

			$green = $engine->calculate( $base );
			self::assertSame( 'green', $green['status'] );
			self::assertSame( 100, $green['score'] );

			$yellow_checks                               = $base;
			$yellow_checks['meta_description']['status'] = Result::STATUS_WARN;
			$yellow                                      = $engine->calculate( $yellow_checks );
			self::assertSame( 'yellow', $yellow['status'] );
			self::assertSame( 75, $yellow['score'] );

			$red_checks                               = $base;
			$red_checks['meta_description']['status'] = Result::STATUS_FAIL;
			$red                                      = $engine->calculate( $red_checks );
			self::assertSame( 'red', $red['status'] );
			self::assertSame( 50, $red['score'] );
	}

	/**
	 * Ensures default weighting and fallback labels are applied for partial payloads.
	 */
	public function test_calculate_handles_defaults_and_unknown_values(): void {
		$engine = new ScoreEngine(
			static function (): array {
				return array(
					'title_length' => 'invalid',
				);
			}
		);

		$checks = array(
			'title_length'     => array(
				'status'   => Result::STATUS_FAIL,
				'weight'   => 1.0,
				'label'    => '',
				'fix_hint' => '',
			),
			'meta_description' => array(
				'status'   => Result::STATUS_WARN,
				'weight'   => 0.4,
				'label'    => '',
				'fix_hint' => '',
			),
		);

		$result = $engine->calculate( $checks );

		self::assertSame( 'red', $result['status'] );
		self::assertSame( 14, $result['score'] );
		self::assertSame( 1.4, $result['weight_total'] );
		self::assertCount( 2, $result['recommendations'] );
		self::assertStringContainsString( 'SEO check', $result['recommendations'][0] );
		self::assertSame( 0.0, $result['breakdown']['title_length']['multiplier'] );
	}
}
