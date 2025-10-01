<?php
/**
 * Post type helper tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Utils;

use Brain\Monkey;
use FP\SEO\Utils\PostTypes;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Utils\PostTypes
 */
class PostTypesTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        public function test_analyzable_filters_non_editor_post_types(): void {
                when( 'get_post_types' )->justReturn( array( 'post', 'page', 'attachment', 'landing' ) );
                when( 'post_type_supports' )->alias(
                        static function ( string $type, string $feature ): bool {
                                if ( 'editor' !== $feature ) {
                                        return false;
                                }

                                return in_array( $type, array( 'post', 'page', 'landing' ), true );
                        }
                );

                self::assertSame(
                        array( 'post', 'page', 'landing' ),
                        PostTypes::analyzable()
                );
        }

        public function test_analyzable_falls_back_to_defaults_when_empty(): void {
                when( 'get_post_types' )->justReturn( array() );

                self::assertSame(
                        array( 'post', 'page' ),
                        PostTypes::analyzable()
                );
        }
}
