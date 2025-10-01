<?php
/**
 * Performance signals tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Perf;

use Brain\Monkey;
use FP\SEO\Perf\Signals;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Ensures the performance signals provider behaves as expected.
 *
 * @covers \FP\SEO\Perf\Signals
 */
class SignalsTest extends TestCase {
	/**
	 * Prepare Brain Monkey environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'esc_html__' )->returnArg( 1 );
		when( 'esc_html' )->returnArg( 1 );
		when( 'esc_url' )->returnArg( 1 );
		when( 'sanitize_text_field' )->returnArg( 1 );
		when( 'sanitize_key' )->returnArg( 1 );
		when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		when( 'add_settings_error' )->alias(
			static function (): void {
			}
		);
		when( 'home_url' )->alias(
			static function ( string $path = '/', string $scheme = 'https' ): string {
				unset( $scheme );

				return 'https://example.com' . $path;
			}
		);
		when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				return $url . '?' . http_build_query( $args );
			}
		);
		when( 'wp_json_encode' )->alias( 'json_encode' );
		when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
			}
		);
		when( 'is_wp_error' )->alias(
			static function (): bool {
				return false;
			}
		);
	}

	/**
	 * Tear down Brain Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Cached PSI responses should be reused without remote requests.
	 */
	public function test_collect_returns_cached_psi_response(): void {
		when( 'get_option' )->alias(
			static function ( string $option, $default_value = false ) {
				if ( Options::OPTION_KEY === $option ) {
					return array(
						'performance' => array(
							'enable_psi'  => true,
							'psi_api_key' => 'abc123',
						),
					);
				}

				return $default_value;
			}
		);

		expect( 'get_transient' )->once()->andReturn(
			array(
				'source'        => 'psi',
				'url'           => 'https://example.com/',
				'endpoint'      => 'mock',
				'metrics'       => array(),
				'opportunities' => array(),
			)
		);
		expect( 'wp_remote_get' )->never();

		$signals = new Signals();
		$result  = $signals->collect( 'https://example.com/' );

		self::assertSame( 'psi', $result['source'] );
		self::assertTrue( $result['cached'] );
	}

	/**
	 * PSI data should be parsed into metrics and opportunities when available.
	 */
	public function test_collect_fetches_and_parses_psi_payload(): void {
		$payload = array(
			'loadingExperience' => array(
				'metrics' => array(
					'LARGEST_CONTENTFUL_PAINT_MS'   => array(
						'category'   => 'FAST',
						'percentile' => 2100,
					),
					'CUMULATIVE_LAYOUT_SHIFT_SCORE' => array(
						'category'   => 'AVERAGE',
						'percentile' => 15,
					),
				),
			),
			'lighthouseResult'  => array(
				'audits' => array(
					'unused-css' => array(
						'details'     => array( 'type' => 'opportunity' ),
						'title'       => 'Reduce unused CSS',
						'description' => 'Remove unused rules.',
						'score'       => 0.5,
					),
				),
			),
		);

		$payload_json = wp_json_encode( $payload );

		when( 'get_option' )->alias(
			static function ( string $option, $default_value = false ) {
				if ( Options::OPTION_KEY === $option ) {
					return array(
						'performance' => array(
							'enable_psi'  => true,
							'psi_api_key' => 'abc123',
						),
					);
				}

				return $default_value;
			}
		);

		expect( 'get_transient' )->once()->andReturn( false );
		expect( 'wp_remote_get' )->once()->andReturn(
			array(
				'body' => $payload_json,
			)
		);
		expect( 'set_transient' )->once()->andReturn( true );

		$signals = new Signals();
		$result  = $signals->collect( 'https://example.com/' );

		self::assertSame( 'psi', $result['source'] );
		self::assertArrayHasKey( 'lcp', $result['metrics'] );
		self::assertSame( 'fast', $result['metrics']['lcp']['category'] );
		self::assertCount( 1, $result['opportunities'] );
		self::assertFalse( $result['cached'] );
	}

	/**
	 * When PSI is disabled the service should fallback to heuristics.
	 */
	public function test_collect_uses_heuristics_when_psi_disabled(): void {
		when( 'get_option' )->alias(
			static function ( string $option, $default_value = false ) {
				if ( Options::OPTION_KEY === $option ) {
					return array();
				}

				return $default_value;
			}
		);

		expect( 'get_transient' )->never();
		expect( 'wp_remote_get' )->never();

		$signals = new Signals();
		$result  = $signals->collect(
			'',
			array(
				'images'           => array(
					'total'       => 10,
					'missing_alt' => 5,
				),
				'inline_css_bytes' => 50000,
				'headings'         => array(
					'depth' => 5,
				),
			)
		);

		self::assertSame( 'local', $result['source'] );
		self::assertNotEmpty( $result['opportunities'] );
		self::assertArrayHasKey( 'image_alt_coverage', $result['metrics'] );
	}
}
