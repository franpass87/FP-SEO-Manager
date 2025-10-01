<?php
/**
 * Site Health integration tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\SiteHealth;

use Brain\Monkey;
use FP\SEO\SiteHealth\SeoHealth;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Site Health integration unit tests.
 *
 * @covers \FP\SEO\SiteHealth\SeoHealth
 */
class SeoHealthTest extends TestCase {
	/**
	 * Set up Brain Monkey hooks.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

				when( '__' )->returnArg( 1 );
				when( 'esc_html__' )->returnArg( 1 );
				when( 'esc_html' )->returnArg( 1 );
				when( 'esc_url' )->returnArg( 1 );
				when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
				when( 'sanitize_text_field' )->returnArg( 1 );
				when( 'sanitize_key' )->returnArg( 1 );
				when( 'add_settings_error' )->alias(
					static function (): void {
					}
				);
				when( 'admin_url' )->alias(
					static function ( string $path = '', string $scheme = 'admin' ): string {
								unset( $scheme );

								return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
					}
				);
                                when( 'home_url' )->alias(
                                        static function ( string $path = '', string $scheme = 'http' ): string {
                                                                unset( $scheme );

                                                                return 'https://example.com' . $path;
                                        }
                                );
                                when( 'wp_parse_url' )->alias(
                                        static function ( $url ) {
                                                return parse_url( (string) $url );
                                        }
                                );
                                when( 'add_query_arg' )->alias(
                                        static function ( array $args, string $url ): string {
                                                                return $url . '?' . http_build_query( $args );
                                        }
                                );
				when( 'get_option' )->alias(
					static function ( string $option, $default_value = false ) {
						if ( 'blog_public' === $option ) {
								return '1';
						}

						if ( Options::OPTION_KEY === $option ) {
								return array();
						}

								return $default_value;
					}
				);
				when( 'is_wp_error' )->alias(
					static function (): bool {
								return false;
					}
				);
                when( 'wp_json_encode' )->alias( 'json_encode' );

                when( 'wp_remote_retrieve_body' )->alias(
                        static function ( $response ) {
                                return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
                        }
                );
                when( 'wp_remote_retrieve_response_code' )->alias(
                        static function ( $response ): int {
                                return is_array( $response ) ? (int) ( $response['code'] ?? 200 ) : 200;
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
	 * Ensures the register method wires expected filters.
	 */
	public function test_register_adds_filter(): void {
		$calls = array();

		when( 'add_filter' )->alias(
			static function ( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ) use ( &$calls ) {
				$calls[] = array( $hook, $callback, $priority, $accepted_args );
				return true;
			}
		);

		$health = new SeoHealth();
		$health->register();

		self::assertNotEmpty( $calls );
		self::assertSame( 'site_status_tests', $calls[0][0] );
		self::assertSame( array( $health, 'add_tests' ), $calls[0][1] );
	}

	/**
	 * Confirms SEO check returns good status when metadata present.
	 */
	public function test_run_seo_test_returns_good_status_when_metadata_present(): void {
		$html = '<title>Example</title>'
			. '<meta name="description" content="Example description">'
			. '<link rel="canonical" href="https://example.com/">'
			. '<meta name="robots" content="index,follow">';

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => $html,
                                'code' => 200,
                        )
                );

		$health = new SeoHealth();
		$result = $health->run_seo_test();

		self::assertSame( 'good', $result['status'] );
		self::assertSame( 'Homepage exposes SEO metadata', $result['label'] );
        }

        /**
         * Ensures HTTP errors are surfaced with actionable messaging.
         */
        public function test_run_seo_test_reports_http_error(): void {
                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => '',
                                'code' => 503,
                        )
                );

                $health = new SeoHealth();
                $result = $health->run_seo_test();

                self::assertSame( 'critical', $result['status'] );
                self::assertStringContainsString( 'HTTP 503', $result['description'] );
        }

	/**
	 * Confirms performance test prompts for API key when PSI disabled.
	 */
        public function test_run_performance_test_prompts_for_api_key(): void {
                when( 'wp_remote_get' )->justReturn( array() );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertSame( 'recommended', $result['status'] );
                self::assertSame( 'PageSpeed Insights API key not configured', $result['label'] );
                self::assertNotEmpty( $result['actions'] );
        }

        /**
         * Nested encoded query values should stay encoded when Site Health requests PSI.
         */
        public function test_run_performance_test_preserves_nested_query_encoding(): void {
                $nested_url = 'https://example.com/?redirect=https%3A%2F%2Fdest.test%2F%3Fa%3Db%2526c%253Dd';

                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

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

                when( 'home_url' )->justReturn( $nested_url );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.95,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( $nested_url, $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        public function test_run_performance_test_preserves_plus_sign_query_encodings(): void {
                $home_url = 'https://example.com/?q=cat%2Bdog';

                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

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

                when( 'home_url' )->justReturn( $home_url );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.95,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( $home_url, $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        public function test_run_performance_test_decodes_percent_encoded_host(): void {
                $home_url     = 'https://%65xample.com/?id=42';
                $expected_url = 'https://example.com/?id=42';

                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

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

                when( 'home_url' )->justReturn( $home_url );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.94,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( $expected_url, $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        public function test_run_performance_test_decodes_fully_encoded_home_url(): void {
                $encoded_home_url = 'https%3A%2F%2Fexample.com%2Fdownload%252Ffile';

                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

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

                when( 'home_url' )->justReturn( $encoded_home_url );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.95,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/download%2Ffile', $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        public function test_run_performance_test_collapses_double_encoded_reserved_sequences(): void {
                when( 'get_option' )->alias(
                        static function ( string $option, $default_value = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

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

                when( 'home_url' )->justReturn( 'https://example.com/download%252Ffile/?q=cat%252Bdog' );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ) use ( &$captured_args ): string {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->justReturn(
                        array(
                                'body' => wp_json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.95,
                                                                ),
                                                        ),
                                                ),
                                        )
                                ),
                        )
                );

                $health = new SeoHealth();
                $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/download%2Ffile/?q=cat%2Bdog', $captured_args['url'] );
        }
}
