<?php
/**
 * Menu dashboard tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Admin;

use Brain\Monkey;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Admin\Menu;
use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Admin\Menu
 */
class MenuTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();

                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
                when( 'esc_attr' )->returnArg( 1 );
                when( 'esc_html' )->returnArg( 1 );
                when( 'esc_url' )->returnArg( 1 );
                when( 'number_format_i18n' )->alias( static function ( $number ): string {
                        return (string) $number;
                } );
                when( 'get_option' )->justReturn( Options::get_defaults() );
                when( 'post_type_supports' )->alias(
                        static function ( string $type, string $feature ): bool {
                                if ( 'editor' !== $feature ) {
                                        return false;
                                }

                                return in_array( $type, array( 'post', 'page' ), true );
                        }
                );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }

        public function test_render_dashboard_requires_capability(): void {
                when( 'current_user_can' )->justReturn( false );
                expect( 'wp_die' )->once()->andReturnUsing( static function ( $message ): void {
                        throw new \RuntimeException( (string) $message );
                } );

                $menu = new Menu();

                $this->expectException( \RuntimeException::class );
                $this->expectExceptionMessage( 'Sorry, you are not allowed to access this page.' );

                $menu->render_dashboard();
        }

        public function test_render_dashboard_outputs_stats(): void {
                $now = 2_000;

                when( 'current_user_can' )->justReturn( true );
                when( 'esc_html_e' )->alias( static function ( $text ): void {
                        echo (string) $text;
                } );
                when( 'get_option' )->justReturn(
                        Options::merge_defaults(
                                array(
                                        'general'     => array(
                                                'enable_analyzer' => true,
                                                'admin_bar_badge' => true,
                                        ),
                                        'analysis'    => array(
                                                'checks' => array(
                                                        'title_length'       => true,
                                                        'meta_description'   => true,
                                                        'h1_presence'        => true,
                                                        'headings_structure' => true,
                                                        'image_alt'          => true,
                                                        'canonical'          => false,
                                                        'robots'             => false,
                                                        'og_cards'           => true,
                                                        'twitter_cards'      => true,
                                                        'schema_presets'     => true,
                                                        'internal_links'     => false,
                                                ),
                                        ),
                                        'performance' => array(
                                                'enable_psi'  => true,
                                                'psi_api_key' => 'abc123',
                                                'heuristics'  => array(
                                                        'image_alt_coverage' => true,
                                                        'inline_css'         => false,
                                                        'image_count'        => true,
                                                        'heading_depth'      => true,
                                                ),
                                        ),
                                )
                        )
                );

                when( 'get_post_types' )->justReturn( array( 'post', 'page', 'attachment' ) );
                when( 'wp_count_posts' )->alias( static function ( $type ) {
                        $counts = new \stdClass();
                        if ( 'post' === $type ) {
                                $counts->publish = 12;
                        } elseif ( 'page' === $type ) {
                                $counts->publish = 8;
                        } elseif ( 'attachment' === $type ) {
                                $counts->publish = 15;
                        } else {
                                $counts->publish = 0;
                        }

                        return $counts;
                } );

                expect( 'get_posts' )
                        ->once()
                        ->with(
                                array(
                                        'post_type'              => array( 'post', 'page' ),
                                        'post_status'            => 'publish',
                                        'fields'                 => 'ids',
                                        'meta_key'               => Metabox::META_EXCLUDE,
                                        'meta_value'             => '1',
                                        'posts_per_page'         => -1,
                                        'nopaging'               => true,
                                        'no_found_rows'          => true,
                                        'update_post_meta_cache' => false,
                                        'update_post_term_cache' => false,
                                        'suppress_filters'       => true,
                                )
                        )
                        ->andReturn( array( 21 ) );

                expect( 'get_transient' )
                        ->once()
                        ->with( BulkAuditPage::CACHE_KEY )
                        ->andReturn(
                        array(
                                10 => array(
                                        'post_id'  => 10,
                                        'score'    => 82,
                                        'status'   => 'green',
                                        'warnings' => 0,
                                        'updated'  => 1_500,
                                ),
                                20 => array(
                                        'post_id'  => 20,
                                        'score'    => 55,
                                        'status'   => 'red',
                                        'warnings' => 3,
                                        'updated'  => 1_800,
                                ),
                        )
                );

                when( 'get_the_title' )->alias( static function ( int $post_id ): string {
                        return 'Post ' . $post_id;
                } );
                when( 'get_edit_post_link' )->alias( static function ( int $post_id ): string {
                        return 'https://example.com/edit/' . $post_id;
                } );
                when( 'current_time' )->justReturn( $now );
                when( 'human_time_diff' )->alias( static function ( int $from, int $to ): string {
                        return '16 mins';
                } );
                when( 'wp_date' )->alias( static function (): string {
                        return '1970-01-01 00:25';
                } );

                $menu = new Menu();

                ob_start();
                $menu->render_dashboard();
                $output = ob_get_clean();

                self::assertIsString( $output );
                self::assertStringContainsString( 'SEO Performance Dashboard', $output );
                self::assertStringContainsString( 'Eligible content items: 19', $output );
                self::assertStringContainsString( 'Excluded from analysis: 1', $output );
                self::assertStringContainsString( 'Average score: 69', $output );
                self::assertStringContainsString( 'Flagged items: 1 of 2', $output );
                self::assertStringContainsString( 'Signal source: PageSpeed Insights', $output );
                self::assertStringContainsString( 'Post 20', $output );
                self::assertStringContainsString( 'https://example.com/edit/20', $output );
                self::assertStringContainsString( '16 mins ago', $output );
        }
}
