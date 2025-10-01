<?php
/**
 * Asset helper tests.
 *
 * @package FP\SEO\Tests
 */

declare( strict_types=1 );

namespace FP\SEO\Tests\Unit\Utils;

use Brain\Monkey;
use FP\SEO\Utils\Assets;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Utils\Assets
 */
class AssetsTest extends TestCase {
    /**
     * Set up Brain Monkey.
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        if ( ! defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
            define( 'FP_SEO_PERFORMANCE_FILE', __FILE__ );
        }

        if ( ! defined( 'FP_SEO_PERFORMANCE_VERSION' ) ) {
            define( 'FP_SEO_PERFORMANCE_VERSION', '9.9.9' );
        }
    }

    /**
     * Tear down Brain Monkey.
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Ensures register wires required hooks.
     */
    public function test_register_adds_hooks(): void {
        $assets    = new Assets();
        $callbacks = array();

        when( 'add_action' )->alias(
            static function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$callbacks ): bool {
                $callbacks[] = array( $hook, $callback, $priority, $accepted_args );

                return true;
            }
        );

        $assets->register();

        self::assertContains(
            array( 'admin_init', array( $assets, 'register_admin_assets' ), 10, 0 ),
            $callbacks
        );

        self::assertContains(
            array( 'admin_enqueue_scripts', array( $assets, 'ensure_admin_handles' ), 5, 0 ),
            $callbacks
        );
    }

    /**
     * Ensures handles register when missing.
     */
    public function test_ensure_admin_handles_registers_handles_when_missing(): void {
        $registered_styles  = array();
        $registered_scripts = array();

        when( 'plugins_url' )->alias(
            static function ( $path ): string {
                return 'https://example.com/' . ltrim( (string) $path, '/' );
            }
        );

        when( 'wp_style_is' )->alias(
            static function ( string $handle, string $list = 'enqueued' ) use ( &$registered_styles ): bool {
                if ( 'registered' !== $list ) {
                    return false;
                }

                return isset( $registered_styles[ $handle ] );
            }
        );

        when( 'wp_script_is' )->alias(
            static function ( string $handle, string $list = 'enqueued' ) use ( &$registered_scripts ): bool {
                if ( 'registered' !== $list ) {
                    return false;
                }

                return isset( $registered_scripts[ $handle ] );
            }
        );

        when( 'wp_register_style' )->alias(
            static function (
                string $handle,
                string $src = '',
                array $deps = array(),
                $ver = false
            ) use ( &$registered_styles ): bool {
                $registered_styles[ $handle ] = array(
                    'src'  => $src,
                    'deps' => $deps,
                    'ver'  => $ver,
                );

                return true;
            }
        );

        when( 'wp_register_script' )->alias(
            static function (
                string $handle,
                string $src = '',
                array $deps = array(),
                $ver = false
            ) use ( &$registered_scripts ): bool {
                $registered_scripts[ $handle ] = array(
                    'src'  => $src,
                    'deps' => $deps,
                    'ver'  => $ver,
                );

                return true;
            }
        );

        expect( 'wp_enqueue_style' )->never();
        expect( 'wp_enqueue_script' )->never();

        $assets = new Assets();
        $assets->ensure_admin_handles();

        self::assertArrayHasKey( 'fp-seo-performance-admin', $registered_styles );
        self::assertSame( 'https://example.com/assets/admin/admin.css', $registered_styles['fp-seo-performance-admin']['src'] );
        self::assertSame( array(), $registered_styles['fp-seo-performance-admin']['deps'] );
        self::assertSame( FP_SEO_PERFORMANCE_VERSION, $registered_styles['fp-seo-performance-admin']['ver'] );

        self::assertArrayHasKey( 'fp-seo-performance-admin', $registered_scripts );
        self::assertArrayHasKey( 'fp-seo-performance-editor', $registered_scripts );
        self::assertArrayHasKey( 'fp-seo-performance-bulk', $registered_scripts );

        self::assertSame( FP_SEO_PERFORMANCE_VERSION, $registered_scripts['fp-seo-performance-admin']['ver'] );
        self::assertSame( FP_SEO_PERFORMANCE_VERSION, $registered_scripts['fp-seo-performance-editor']['ver'] );
        self::assertSame( FP_SEO_PERFORMANCE_VERSION, $registered_scripts['fp-seo-performance-bulk']['ver'] );
    }

    /**
     * Ensures existing handles do not re-register.
     */
    public function test_ensure_admin_handles_uses_existing_handles(): void {
        $style_checks = array();
        $script_checks = array();
        $style_registered = false;
        $registered_scripts = array();

        when( 'wp_style_is' )->alias(
            static function ( string $handle, string $list = 'enqueued' ) use ( &$style_checks ): bool {
                if ( 'registered' === $list ) {
                    $style_checks[] = $handle;

                    return 'fp-seo-performance-admin' === $handle;
                }

                return false;
            }
        );

        when( 'wp_script_is' )->alias(
            static function ( string $handle, string $list = 'enqueued' ) use ( &$script_checks ): bool {
                if ( 'registered' === $list ) {
                    $script_checks[] = $handle;

                    return in_array(
                        $handle,
                        array(
                            'fp-seo-performance-admin',
                            'fp-seo-performance-editor',
                            'fp-seo-performance-bulk',
                        ),
                        true
                    );
                }

                return false;
            }
        );

        when( 'wp_register_style' )->alias(
            static function () use ( &$style_registered ): bool {
                $style_registered = true;

                return true;
            }
        );

        when( 'wp_register_script' )->alias(
            static function ( string $handle ) use ( &$registered_scripts ): bool {
                $registered_scripts[] = $handle;

                return true;
            }
        );

        expect( 'wp_enqueue_style' )->never();
        expect( 'wp_enqueue_script' )->never();

        $assets = new Assets();
        $assets->ensure_admin_handles();

        self::assertFalse( $style_registered );
        self::assertSame( array(), $registered_scripts );
        self::assertSame( array( 'fp-seo-performance-admin' ), $style_checks );
        self::assertSame(
            array(
                'fp-seo-performance-admin',
                'fp-seo-performance-editor',
                'fp-seo-performance-bulk',
            ),
            $script_checks
        );
    }
}
