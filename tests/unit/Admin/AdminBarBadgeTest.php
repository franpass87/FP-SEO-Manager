<?php
/**
 * Admin bar badge tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace {
        if ( ! class_exists( 'WP_Admin_Bar' ) ) {
                class WP_Admin_Bar {
                        /** @var array<int, array<string, mixed>> */
                        public array $nodes = array();

                        public function add_node( array $args ): void {
                                $this->nodes[] = $args;
                        }
                }
        }
}

namespace FP\SEO\Tests\Unit\Admin {

use Brain\Monkey;
use FP\SEO\Admin\AdminBarBadge;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Admin\AdminBarBadge
 */
class AdminBarBadgeTest extends TestCase {
        /**
         * Prepare Brain Monkey stubs.
         */
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();

                when( 'is_admin' )->justReturn( true );
                when( 'is_admin_bar_showing' )->justReturn( true );
                when( 'current_user_can' )->justReturn( true );
                when( 'esc_html' )->returnArg( 1 );
                when( 'esc_attr' )->alias(
                        static function ( string $text ): string {
                                return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
                        }
                );
                when( 'esc_url' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
                when( 'sanitize_html_class' )->returnArg( 1 );
                when( 'admin_url' )->alias(
                        static function ( string $path = '' ): string {
                                return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
                        }
                );
                when( 'add_query_arg' )->alias(
                        static function ( array $args, string $url ): string {
                                return $url . '?' . http_build_query( $args );
                        }
                );
                when( 'home_url' )->alias(
                        static function ( string $path = '/' ): string {
                                return 'https://example.com' . $path;
                        }
                );
                when( 'get_option' )->alias(
                        static function ( string $option, $default = false ) {
                                if ( 'fp_seo_perf_options' === $option ) {
                                        return array(
                                                'general' => array(
                                                        'enable_analyzer' => true,
                                                        'language'        => 'en',
                                                        'admin_bar_badge' => true,
                                                ),
                                        );
                                }

                                return $default;
                        }
                );
                when( 'get_post' )->alias(
                        static function ( $post_id ) {
                                return (object) array(
                                        'ID'           => (int) $post_id,
                                        'post_content' => str_repeat( 'Word ', 200 ) . '<a href="/internal">Link</a>',
                                        'post_title'   => 'Sample Title',
                                        'post_excerpt' => 'Summary',
                                );
                        }
                );
                when( 'get_post_type' )->justReturn( 'post' );
                when( 'get_post_meta' )->justReturn( '' );
                when( 'get_permalink' )->alias(
                        static function (): string {
                                return 'https://example.com/post';
                        }
                );
                when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
                when( '__' )->alias(
                        static function ( string $text ) {
                                if ( 'Critical issues' === $text ) {
                                        return '"><script>alert(1)</script';
                                }

                                return $text;
                        }
                );
        }

        /**
         * Clean up Brain Monkey state.
         */
        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        /**
         * Ensures the tooltip string is properly escaped.
         */
        public function test_add_badge_escapes_tooltip_attribute(): void {
                global $pagenow;

                $pagenow      = 'post.php';
                $_GET['post'] = '123';

                $bar   = new \WP_Admin_Bar();
                $badge = new AdminBarBadge();
                $badge->add_badge( $bar );

                self::assertNotEmpty( $bar->nodes );
                $meta = $bar->nodes[0]['meta'];

                self::assertArrayHasKey( 'title', $meta );
                self::assertStringNotContainsString( '<script', $meta['title'] );
                self::assertStringContainsString( '&lt;script', $meta['title'] );
        }
}

}
