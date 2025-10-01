<?php
/**
 * Twitter cards check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use Brain\Monkey;
use FP\SEO\Analysis\Checks\TwitterCardsCheck;
use FP\SEO\Analysis\Context;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Analysis\Checks\TwitterCardsCheck
 */
class TwitterCardsCheckTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();
                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }
        public function test_run_passes_with_all_tags_present(): void {
                $html = '<meta name="twitter:card" content="summary_large_image" />'
                        . '<meta name="twitter:title" content="Title" />'
                        . '<meta name="twitter:description" content="Description" />'
                        . '<meta name="twitter:image" content="https://example.com/image.jpg" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new TwitterCardsCheck();
                $result  = $check->run( $context );

                self::assertSame( 'pass', $result->status() );
        }

        public function test_run_accepts_image_src_fallback(): void {
                $html = '<meta name="twitter:card" content="summary" />'
                        . '<meta name="twitter:title" content="Title" />'
                        . '<meta name="twitter:description" content="Description" />'
                        . '<meta name="twitter:image:src" content="https://example.com/image.jpg" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new TwitterCardsCheck();
                $result  = $check->run( $context );

                self::assertSame( 'pass', $result->status() );
        }

        public function test_run_reports_missing_image(): void {
                $html = '<meta name="twitter:card" content="summary" />'
                        . '<meta name="twitter:title" content="Title" />'
                        . '<meta name="twitter:description" content="Description" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new TwitterCardsCheck();
                $result  = $check->run( $context );

                self::assertContains( 'twitter:image', $result->details()['missing'] );
        }
}
