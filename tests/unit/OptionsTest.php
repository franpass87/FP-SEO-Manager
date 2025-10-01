<?php
/**
 * Options utility unit tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit;

use Brain\Monkey;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Options utility test coverage.
 *
 * @covers \FP\SEO\Utils\Options
 */
class OptionsTest extends TestCase {

	/**
	 * Sets up Brain Monkey expectations.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		when( '__' )->returnArg( 1 );
		when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return $value;
			}
		);
	}

	/**
	 * Ensures invalid title ranges reset to defaults and add notices.
	 */
	public function test_sanitize_enforces_title_range_and_reports_error(): void {
		$captured = array();
		when( 'add_settings_error' )->alias(
			static function ( $setting, $code, $message, $type = 'error' ) use ( &$captured ): void {
				$captured[] = array(
					'setting' => $setting,
					'code'    => $code,
					'message' => $message,
					'type'    => $type,
				);
			}
		);

		$input = array(
			'analysis' => array(
				'title_length_min' => 70,
				'title_length_max' => 40,
			),
		);

		$sanitized = Options::sanitize( $input );

		self::assertSame( Options::get_defaults()['analysis']['title_length_min'], $sanitized['analysis']['title_length_min'] );
		self::assertSame( Options::get_defaults()['analysis']['title_length_max'], $sanitized['analysis']['title_length_max'] );

		self::assertCount( 1, $captured );
		self::assertSame( Options::OPTION_GROUP, $captured[0]['setting'] );
		self::assertSame( 'fp_seo_perf_title_range', $captured[0]['code'] );
		self::assertSame( 'error', $captured[0]['type'] );
	}

	/**
	 * Confirms PSI warning notice is added when key missing.
	 */
	public function test_sanitize_warns_when_psi_enabled_without_key(): void {
		$captured = array();
		when( 'add_settings_error' )->alias(
			static function ( $setting, $code, $message, $type = 'error' ) use ( &$captured ): void {
				$captured[] = array(
					'setting' => $setting,
					'code'    => $code,
					'message' => $message,
					'type'    => $type,
				);
			}
		);

		$input = array(
			'performance' => array(
				'enable_psi'  => true,
				'psi_api_key' => '',
			),
		);

		$sanitized = Options::sanitize( $input );

		self::assertTrue( $sanitized['performance']['enable_psi'] );
		self::assertSame( '', $sanitized['performance']['psi_api_key'] );

		self::assertNotEmpty( $captured );
		$notice = end( $captured );
		self::assertSame( 'fp_seo_perf_psi_key', $notice['code'] );
		self::assertSame( 'warning', $notice['type'] );
	}

	/**
	 * Ensures conflicting badge/analyzer settings create a warning.
	 */
	public function test_sanitize_warns_when_badge_enabled_without_analyzer(): void {
			$captured = array();
			when( 'add_settings_error' )->alias(
				static function ( $setting, $code, $message, $type = 'error' ) use ( &$captured ): void {
							$captured[] = array(
								'setting' => $setting,
								'code'    => $code,
								'message' => $message,
								'type'    => $type,
							);
				}
			);

		$input = array(
			'general' => array(
				'enable_analyzer' => false,
				'admin_bar_badge' => true,
			),
		);

		$sanitized = Options::sanitize( $input );

		self::assertFalse( $sanitized['general']['enable_analyzer'] );
		self::assertTrue( $sanitized['general']['admin_bar_badge'] );

			$notice = end( $captured );
			self::assertSame( 'fp_seo_perf_badge_requires_analyzer', $notice['code'] );
			self::assertSame( 'warning', $notice['type'] );
	}

		/**
		 * Ensures scoring weights are normalized and warn on invalid input.
		 */
	public function test_sanitize_normalizes_scoring_weights(): void {
			$captured = array();
			when( 'add_settings_error' )->alias(
				static function ( $setting, $code, $message, $type = 'error' ) use ( &$captured ): void {
							$captured[] = array(
								'setting' => $setting,
								'code'    => $code,
								'message' => $message,
								'type'    => $type,
							);
				}
			);

			$input = array(
				'scoring' => array(
					'weights' => array(
						'title_length'     => 'invalid',
						'meta_description' => 3.5,
						'h1_presence'      => -1,
						'internal_links'   => 9,
					),
				),
			);

			$sanitized = Options::sanitize( $input );

			self::assertSame( 1.0, $sanitized['scoring']['weights']['title_length'] );
			self::assertSame( 3.5, $sanitized['scoring']['weights']['meta_description'] );
			self::assertSame( 0.0, $sanitized['scoring']['weights']['h1_presence'] );
			self::assertSame( 5.0, $sanitized['scoring']['weights']['internal_links'] );

			$notice = end( $captured );
			self::assertSame( 'fp_seo_perf_scoring_weights', $notice['code'] );
			self::assertSame( 'warning', $notice['type'] );
	}
}
