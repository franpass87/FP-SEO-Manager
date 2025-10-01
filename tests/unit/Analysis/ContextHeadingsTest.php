<?php
/**
 * Context heading helpers tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use FP\SEO\Analysis\Context;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FP\SEO\Analysis\Context::headings
 */
class ContextHeadingsTest extends TestCase {
        public function test_headings_preserve_document_order(): void {
                $html    = '<h2>Second</h2><h1>First</h1><h3>Third</h3>';
                $context = new Context( null, $html );

                $headings = $context->headings();

                self::assertSame( array( 2, 1, 3 ), array_column( $headings, 'level' ) );
                self::assertSame( array( 'Second', 'First', 'Third' ), array_column( $headings, 'text' ) );
        }
}
