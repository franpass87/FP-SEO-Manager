<?php
/**
 * URL normalizer tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Utils;

use Brain\Monkey;
use FP\SEO\Utils\UrlNormalizer;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Utils\UrlNormalizer
 */
class UrlNormalizerTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();

                when( 'wp_parse_url' )->alias(
                        static function ( $url ) {
                                return parse_url( (string) $url );
                        }
                );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        public function test_preserves_reserved_path_encodings(): void {
                $url = 'https://example.com/download/%2Fsecret%2Ftoken%3Dabc';

                self::assertSame(
                        $url,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_decodes_percent_encoded_hosts(): void {
                $url      = 'https://%65xample.com/download/';
                $expected = 'https://example.com/download/';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_decodes_safe_path_and_query_characters(): void {
                $url      = 'https://user%3Aname:p%40ss@example.com/path%20with%20space/%E2%82%AC?name=J%C3%B6rg';
                $expected = 'https://user%3Aname:p%40ss@example.com/path with space/€?name=Jörg';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_preserves_plus_sign_query_encodings(): void {
                $url = 'https://example.com/?q=cat%2Bdog';

                self::assertSame(
                        $url,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_collapses_double_encoded_reserved_sequences(): void {
                $url      = 'https://example.com/download%252Ffile/?q=cat%252Bdog#section%252F1';
                $expected = 'https://example.com/download%2Ffile/?q=cat%2Bdog#section%2F1';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_decodes_double_encoded_safe_sequences(): void {
                $url      = 'https://example.com/path%2520with%2520space/?name=J%C3%B6rg%2520M%C3%BCller';
                $expected = 'https://example.com/path with space/?name=Jörg Müller';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_preserves_reserved_fragment_encodings(): void {
                $url = 'https://example.com/page#section%2F1%3Fmode%3Dfull';

                self::assertSame(
                        $url,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_does_not_add_authority_separator_for_non_hierarchical_schemes(): void {
                $url      = 'mailto:user%40example.com?subject=Hello%20World';
                $expected = 'mailto:user@example.com?subject=Hello World';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_decodes_fully_encoded_absolute_urls(): void {
                $url      = 'https%3A%2F%2Fexample.com%2Fdownload%252Ffile';
                $expected = 'https://example.com/download%2Ffile';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }

        public function test_decodes_fully_encoded_non_hierarchical_urls(): void {
                $url      = 'mailto%3Auser%40example.com%3Fsubject%3DHello%2520World';
                $expected = 'mailto:user@example.com?subject=Hello World';

                self::assertSame(
                        $expected,
                        UrlNormalizer::normalize( $url )
                );
        }
}
