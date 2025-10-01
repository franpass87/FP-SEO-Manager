<?php
/**
 * Open Graph check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use Brain\Monkey;
use FP\SEO\Analysis\Checks\OgCardsCheck;
use FP\SEO\Analysis\Context;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Analysis\Checks\OgCardsCheck
 */
class OgCardsCheckTest extends TestCase {
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
        public function test_run_passes_when_all_required_tags_present(): void {
                $html = '<meta property="og:title" content="Title" />'
                        . '<meta property="og:description" content="Description" />'
                        . '<meta property="og:type" content="article" />'
                        . '<meta property="og:url" content="https://example.com/" />'
                        . '<meta property="og:image" content="https://example.com/image.jpg" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new OgCardsCheck();
                $result  = $check->run( $context );

                self::assertSame( 'pass', $result->status() );
        }

        public function test_run_uses_secure_image_fallback(): void {
                $html = '<meta property="og:title" content="Title" />'
                        . '<meta property="og:description" content="Description" />'
                        . '<meta property="og:type" content="article" />'
                        . '<meta property="og:url" content="https://example.com/" />'
                        . '<meta property="og:image:secure_url" content="https://example.com/image-secure.jpg" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new OgCardsCheck();
                $result  = $check->run( $context );

                self::assertSame( 'pass', $result->status() );
        }

        public function test_run_reports_missing_image(): void {
                $html = '<meta property="og:title" content="Title" />'
                        . '<meta property="og:description" content="Description" />'
                        . '<meta property="og:type" content="article" />'
                        . '<meta property="og:url" content="https://example.com/" />';

                $context = new Context( null, '<head>' . $html . '</head>' );
                $check   = new OgCardsCheck();
                $result  = $check->run( $context );

                self::assertContains( 'og:image', $result->details()['missing'] );
        }
}
