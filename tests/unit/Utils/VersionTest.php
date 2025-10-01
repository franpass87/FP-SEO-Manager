<?php
/**
 * Plugin version utility tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Utils;

use FP\SEO\Utils\Version;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FP\SEO\Utils\Version
 */
class VersionTest extends TestCase {
        public function test_resolve_reads_version_from_header_when_wordpress_helpers_missing(): void {
                $version = Version::resolve(
                        dirname( __DIR__, 3 ) . '/fp-seo-performance.php',
                        '0.0.0'
                );

                self::assertSame( '0.1.2', $version );
        }
}
