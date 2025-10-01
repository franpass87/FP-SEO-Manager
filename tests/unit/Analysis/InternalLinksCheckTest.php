<?php
/**
 * Internal links check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use Brain\Monkey;
use FP\SEO\Analysis\Checks\InternalLinksCheck;
use FP\SEO\Analysis\Context;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Analysis\Checks\InternalLinksCheck
 */
class InternalLinksCheckTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();

                when( 'home_url' )->justReturn( 'https://example.com/' );
                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
                when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        public function test_run_ignores_external_links(): void {
                $html    = str_repeat( 'Word ', 200 ) . '<a href="https://example.com/internal">Internal</a>'
                        . '<a href="https://other.com/page">External</a>';
                $context = new Context( null, $html, 'Title' );

                $check  = new InternalLinksCheck();
                $result = $check->run( $context );

                $details = $result->details();

                self::assertSame( 1, $details['links'] );
                self::assertSame( 'pass', $result->status() );
        }

        public function test_run_skips_placeholder_links(): void {
                $html    = str_repeat( 'Word ', 200 ) . '<a href="#">Placeholder</a>';
                $context = new Context( null, $html, 'Title' );

                $check  = new InternalLinksCheck();
                $result = $check->run( $context );

                $details = $result->details();

                self::assertSame( 0, $details['links'] );
                self::assertNotSame( 'pass', $result->status() );
        }
}
