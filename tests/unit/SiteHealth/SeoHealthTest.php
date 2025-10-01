<?php
/**
 * Site Health integration tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\SiteHealth;

use Brain\Monkey;
use FP\SEO\Perf\Signals;
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
                $signals = $this->createMock( Signals::class );
                $signals->expects( self::never() )->method( 'collect' );

                $health = new SeoHealth( $signals );
                $result = $health->run_performance_test();

                self::assertSame( 'recommended', $result['status'] );
                self::assertSame( 'PageSpeed Insights API key not configured', $result['label'] );
                self::assertNotEmpty( $result['actions'] );
        }

        /**
         * Confirms PSI score renders when cached signals provide data.
         */
        public function test_run_performance_test_uses_cached_signals(): void {
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

                $signals = $this->createMock( Signals::class );
                $signals->expects( self::once() )
                        ->method( 'collect' )
                        ->with( 'https://example.com/' )
                        ->willReturn(
                                array(
                                        'source'            => 'psi',
                                        'performance_score' => 95,
                                        'endpoint'          => 'https://example.com/report',
                                )
                        );

                $health = new SeoHealth( $signals );
                $result = $health->run_performance_test();

                self::assertSame( 'good', $result['status'] );
                self::assertStringContainsString( '95', $result['description'] );
                self::assertStringContainsString( 'https://example.com/report', $result['actions'][0] ?? '' );
        }

        /**
         * Signals error messages should surface actionable guidance.
         */
        public function test_run_performance_test_handles_signals_error(): void {
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

                $signals = $this->createMock( Signals::class );
                $signals->expects( self::once() )
                        ->method( 'collect' )
                        ->willReturn(
                                array(
                                        'source' => 'psi',
                                        'error'  => 'API quota exceeded',
                                )
                        );

                $health = new SeoHealth( $signals );
                $result = $health->run_performance_test();

                self::assertSame( 'recommended', $result['status'] );
                self::assertStringContainsString( 'API quota exceeded', $result['description'] );
        }

        /**
         * Missing scores should produce a recommended notice with fallback links.
         */
        public function test_run_performance_test_handles_missing_score(): void {
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

                $signals = $this->createMock( Signals::class );
                $signals->expects( self::once() )
                        ->method( 'collect' )
                        ->willReturn(
                                array(
                                        'source'   => 'psi',
                                        'endpoint' => 'https://example.com/report',
                                )
                        );

                $health = new SeoHealth( $signals );
                $result = $health->run_performance_test();

                self::assertSame( 'recommended', $result['status'] );
                self::assertStringContainsString( 'did not include a performance score', $result['description'] );
        }

        /**
         * Home URLs should be normalized before requesting PSI signals.
         */
        public function test_run_performance_test_normalizes_home_url_before_collect(): void {
                $encoded_home_url = 'https%3A%2F%2Fexample.com%2Fdownload%252Ffile?q=cat%252Bdog';

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

                $signals = $this->createMock( \FP\SEO\Perf\Signals::class );
                $signals->expects( self::once() )
                        ->method( 'collect' )
                        ->with( 'https://example.com/download%2Ffile?q=cat%2Bdog' )
                        ->willReturn(
                                array(
                                        'source'            => 'psi',
                                        'performance_score' => 90,
                                        'endpoint'          => 'https://example.com/report',
                                )
                        );

                $health = new SeoHealth( $signals );
                $result = $health->run_performance_test();

                self::assertSame( 'good', $result['status'] );
                self::assertStringContainsString( '90', $result['description'] );
        }
}
