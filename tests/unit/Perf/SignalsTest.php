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
use ReflectionClass;
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
                when( 'wp_parse_url' )->alias(
                        static function ( $url ) {
                                return parse_url( (string) $url );
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
         * PSI requests should send the raw URL without double-encoding.
         */
        public function test_collect_from_psi_uses_raw_url_parameter(): void {
                $raw_url = 'https://example.com/landing page/?ref=ä';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $raw_url );

                self::assertIsArray( $captured_args );
                self::assertSame( $raw_url, $captured_args['url'] ?? null );
                self::assertSame( 'psi', $result['source'] );
        }

        /**
         * Cache keys should normalize host casing but respect path casing.
         */
        public function test_cache_key_respects_path_casing(): void {
                $signals    = new Signals();
                $reflection = new ReflectionClass( Signals::class );
                $method     = $reflection->getMethod( 'build_cache_key' );
                $method->setAccessible( true );

                $upper_host_key = $method->invoke( $signals, 'HTTPS://Example.com/Page' );
                $lower_host_key = $method->invoke( $signals, 'https://example.com/Page' );
                $path_variant   = $method->invoke( $signals, 'https://example.com/page' );

                self::assertSame( $lower_host_key, $upper_host_key );
                self::assertNotSame( $lower_host_key, $path_variant );
        }

        /**
         * Percent-encoded URLs should decode safe characters while preserving reserved encodings.
         */
        public function test_collect_from_psi_decodes_safe_percent_encoded_urls(): void {
                $encoded_url = 'https://example.com/landing%20page/?ref=%C3%A4';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $encoded_url );

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/landing page/?ref=ä', $captured_args['url'] );
                self::assertSame( 'https://example.com/landing page/?ref=ä', $result['url'] );
                self::assertSame( 'psi', $result['source'] );
        }

        public function test_collect_from_psi_preserves_plus_sign_query_encodings(): void {
                $encoded_url = 'https://example.com/?q=cat%2Bdog';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $encoded_url );

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/?q=cat%2Bdog', $captured_args['url'] );
                self::assertSame( 'https://example.com/?q=cat%2Bdog', $result['url'] );
        }

        public function test_collect_from_psi_collapses_double_encoded_reserved_sequences(): void {
                $encoded_url = 'https://example.com/download%252Ffile/?q=cat%252Bdog';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $encoded_url );

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/download%2Ffile/?q=cat%2Bdog', $captured_args['url'] );
                self::assertSame( 'https://example.com/download%2Ffile/?q=cat%2Bdog', $result['url'] );
        }

        /**
         * Nested encoded query values should remain encoded so PSI requests hit the right URL.
         */
        public function test_collect_from_psi_preserves_nested_query_encoding(): void {
                $nested_url = 'https://example.com/?redirect=https%3A%2F%2Fdest.test%2F%3Fa%3Db%2526c%253Dd';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.92,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $nested_url );

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( $nested_url, $captured_args['url'] );
                self::assertSame( $nested_url, $result['url'] );
        }

        /**
         * HTML entity encoded URLs should be normalized before being sent to PSI.
         */
        public function test_collect_from_psi_decodes_html_entities_in_urls(): void {
                $encoded_url = 'https://example.com/landing/?ref=summer&amp;utm_campaign=promo';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.92,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $signals->collect( $encoded_url );

                self::assertIsArray( $captured_args );
                self::assertSame(
                        'https://example.com/landing/?ref=summer&utm_campaign=promo',
                        $captured_args['url'] ?? null
                );
        }

        /**
         * Fully encoded URLs should decode before PSI requests are made.
         */
        public function test_collect_from_psi_decodes_fully_encoded_urls(): void {
                $encoded_url = 'https%3A%2F%2Fexample.com%2Fdownload%252Ffile';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $encoded_url );

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/download%2Ffile', $captured_args['url'] );
                self::assertSame( 'https://example.com/download%2Ffile', $result['url'] );
        }

        /**
         * Percent-encoded hosts should be decoded before invoking PSI.
         */
        public function test_collect_from_psi_decodes_percent_encoded_hosts(): void {
                $encoded_host_url = 'https://%65xample.com/reports/?id=123';

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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'loadingExperience' => array(
                                                        'metrics' => array(),
                                                ),
                                                'lighthouseResult'  => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.91,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );
                expect( 'set_transient' )->once()->andReturn( true );

                $signals = new Signals();
                $result  = $signals->collect( $encoded_host_url );

                $expected_url = 'https://example.com/reports/?id=123';

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( $expected_url, $captured_args['url'] );
                self::assertSame( $expected_url, $result['url'] );
        }

        /**
         * PSI API errors should be surfaced instead of caching empty responses.
         */
        public function test_collect_from_psi_returns_error_message_from_payload(): void {
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

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                $error_payload = array(
                        'error' => array(
                                'code'    => 400,
                                'message' => 'Invalid API key provided.',
                                'errors'  => array(
                                        array(
                                                'message' => 'The API key is invalid.',
                                        ),
                                ),
                        ),
                );

                expect( 'get_transient' )->once()->andReturn( false );
                expect( 'wp_remote_get' )->once()->andReturn(
                        array(
                                'body' => wp_json_encode( $error_payload ),
                        )
                );
                expect( 'set_transient' )->never();

                $signals = new Signals();
                $result  = $signals->collect( 'https://example.com/' );

                self::assertIsArray( $captured_args );
                self::assertSame( 'https://example.com/', $captured_args['url'] ?? null );
                self::assertSame( 'psi', $result['source'] );
                self::assertSame( 'Invalid API key provided.', $result['error'] );
                self::assertSame( array(), $result['metrics'] );
                self::assertSame( array(), $result['opportunities'] );
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

        /**
         * Ensures heuristics toggles disable specific metrics and opportunities.
         */
        public function test_collect_heuristics_respects_disabled_toggles(): void {
                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( Options::OPTION_KEY === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'heuristics' => array(
                                                                'image_alt_coverage' => false,
                                                                'inline_css'         => false,
                                                                'image_count'        => false,
                                                                'heading_depth'      => false,
                                                        ),
                                                ),
                                        );
                                }

                                return $default_value;
                        }
                );

                $signals = new Signals();
                $result  = $signals->collect(
                        '',
                        array(
                                'images'           => array(
                                        'total'       => 20,
                                        'missing_alt' => 10,
                                ),
                                'inline_css_bytes' => 100000,
                                'headings'         => array(
                                        'depth' => 6,
                                ),
                        )
                );

                self::assertSame( 'local', $result['source'] );
                self::assertSame( array(), $result['metrics'] );
                self::assertSame( array(), $result['opportunities'] );
        }
}
