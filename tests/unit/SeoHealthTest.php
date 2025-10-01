<?php
/**
 * Site Health integration tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit;

use Brain\Monkey;
use FP\SEO\SiteHealth\SeoHealth;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\SiteHealth\SeoHealth
 */
class SeoHealthTest extends TestCase
{
        /**
         * Bootstraps Brain Monkey.
         */
        protected function setUp(): void
        {
                parent::setUp();
                Monkey\setUp();

                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
                when( 'esc_html' )->returnArg( 1 );
                when( 'esc_url' )->returnArg( 1 );
                when( 'admin_url' )->returnArg( 1 );
                when( 'sanitize_text_field' )->returnArg( 1 );
                when( 'sanitize_key' )->returnArg( 1 );
                when( 'add_settings_error' )->justReturn( null );
                when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
                when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
                when( 'wp_remote_retrieve_body' )->alias(
                        static function ( $response ) {
                                return is_array( $response ) ? ( $response['body'] ?? '' ) : '';
                        }
                );
                when( 'wp_parse_url' )->alias(
                        static function ( $url ) {
                                return parse_url( (string) $url );
                        }
                );
        }

        /**
         * Tears down Brain Monkey.
         */
        protected function tearDown(): void
        {
                Monkey\tearDown();
                parent::tearDown();
        }

        /**
         * Ensures the PSI endpoint receives an unencoded homepage URL.
         */
        public function test_run_performance_test_uses_raw_home_url(): void
        {
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'enable_psi'  => true,
                                                        'psi_api_key' => 'api-key',
                                                ),
                                        );
                                }

                                return $default;
                        }
                );

                when( 'home_url' )->justReturn( 'https://example.com/' );
                when( 'is_wp_error' )->justReturn( false );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( $args, $url ) use ( &$captured_args ) {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->alias(
                        static function () {
                                return array();
                        }
                );

                when( 'wp_remote_retrieve_body' )->alias(
                        static function () {
                                return json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.85,
                                                                ),
                                                        ),
                                                ),
                                        )
                                );
                        }
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/', $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        /**
         * Homepage SEO requests should limit redirect chains to avoid long waits.
         */
        public function test_run_seo_test_limits_redirects(): void
        {
                when( 'home_url' )->justReturn( 'https://example.com/' );
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'blog_public' === $option ) {
                                        return '1';
                                }

                                return $default;
                        }
                );
                when( 'is_wp_error' )->justReturn( false );

                expect( 'wp_remote_get' )
                        ->once()
                        ->with(
                                'https://example.com/',
                                array(
                                        'timeout'     => 10,
                                        'headers'     => array(),
                                        'redirection' => 3,
                                )
                        )
                        ->andReturn(
                                array(
                                        'body' => '<html><head><title>Welcome</title><meta name="description" content="Site" /><link rel="canonical" href="https://example.com/" /></head><body></body></html>',
                                )
                        );

                $health = new SeoHealth();
                $result = $health->run_seo_test();

                self::assertSame( 'good', $result['status'] );
        }

        /**
         * Encoded home URLs should decode safe characters before building the PSI endpoint.
         */
        public function test_run_performance_test_decodes_percent_encoded_home_url(): void
        {
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'enable_psi'  => true,
                                                        'psi_api_key' => 'api-key',
                                                ),
                                        );
                                }

                                return $default;
                        }
                );

                when( 'home_url' )->justReturn( 'https://example.com/about%20us/' );
                when( 'is_wp_error' )->justReturn( false );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( $args, $url ) use ( &$captured_args ) {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->alias(
                        static function () {
                                return array();
                        }
                );

                when( 'wp_remote_retrieve_body' )->alias(
                        static function () {
                                return json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                );
                        }
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/about us/', $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        public function test_run_performance_test_preserves_plus_sign_query_encodings(): void
        {
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'enable_psi'  => true,
                                                        'psi_api_key' => 'api-key',
                                                ),
                                        );
                                }

                                return $default;
                        }
                );

                when( 'home_url' )->justReturn( 'https://example.com/?q=cat%2Bdog' );
                when( 'is_wp_error' )->justReturn( false );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( $args, $url ) use ( &$captured_args ) {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->alias(
                        static function () {
                                return array();
                        }
                );

                when( 'wp_remote_retrieve_body' )->alias(
                        static function () {
                                return json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.9,
                                                                ),
                                                        ),
                                                ),
                                        )
                                );
                        }
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertArrayHasKey( 'url', $captured_args );
                self::assertSame( 'https://example.com/?q=cat%2Bdog', $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        /**
         * HTML entity encoded home URLs should be decoded before invoking PSI.
         */
        public function test_run_performance_test_decodes_html_entities_in_home_url(): void
        {
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'enable_psi'  => true,
                                                        'psi_api_key' => 'api-key',
                                                ),
                                        );
                                }

                                return $default;
                        }
                );

                when( 'home_url' )->justReturn( 'https://example.com/?ref=summer&amp;utm=promo' );
                when( 'is_wp_error' )->justReturn( false );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( $args, $url ) use ( &$captured_args ) {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->alias(
                        static function () {
                                return array();
                        }
                );

                when( 'wp_remote_retrieve_body' )->alias(
                        static function () {
                                return json_encode(
                                        array(
                                                'lighthouseResult' => array(
                                                        'categories' => array(
                                                                'performance' => array(
                                                                        'score' => 0.93,
                                                                ),
                                                        ),
                                                ),
                                        )
                                );
                        }
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertSame( 'https://example.com/?ref=summer&utm=promo', $captured_args['url'] );
                self::assertSame( 'good', $result['status'] );
        }

        /**
         * PSI API errors should be reported with the returned message.
         */
        public function test_run_performance_test_surfaces_api_error_message(): void
        {
                when( 'get_option' )->alias(
                        static function ( $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'performance' => array(
                                                        'enable_psi'  => true,
                                                        'psi_api_key' => 'api-key',
                                                ),
                                        );
                                }

                                return $default;
                        }
                );

                when( 'home_url' )->justReturn( 'https://example.com/' );
                when( 'is_wp_error' )->justReturn( false );

                $captured_args = null;
                when( 'add_query_arg' )->alias(
                        static function ( $args, $url ) use ( &$captured_args ) {
                                $captured_args = $args;

                                return $url . '?' . http_build_query( $args );
                        }
                );

                when( 'wp_remote_get' )->alias(
                        static function () {
                                return array(
                                        'body' => json_encode(
                                                array(
                                                        'error' => array(
                                                                'code'    => 400,
                                                                'message' => 'API key has been revoked.',
                                                                'errors'  => array(
                                                                        array(
                                                                                'message' => 'Please generate a new API key.',
                                                                        ),
                                                                ),
                                                        ),
                                                )
                                        ),
                                );
                        }
                );

                $health = new SeoHealth();
                $result = $health->run_performance_test();

                self::assertIsArray( $captured_args );
                self::assertSame( 'https://example.com/', $captured_args['url'] );
                self::assertSame( 'recommended', $result['status'] );
                self::assertSame( 'PageSpeed Insights API returned an error', $result['label'] );
                self::assertStringContainsString( 'API key has been revoked.', $result['description'] );
        }
}
