<?php
/**
 * Bulk auditor admin page tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Admin;

use Brain\Monkey;
use FP\SEO\Admin\BulkAuditPage;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

if ( ! class_exists( '\\WP_Query' ) ) {
        class BulkAuditPageTest_WP_Query_Stub {
                /**
                 * Captured query arguments.
                 *
                 * @var array<string, mixed>
                 */
                public static array $last_args = array();

                /**
                 * Queried posts.
                 *
                 * @var array<int, mixed>
                 */
                public array $posts = array();

                public function __construct( array $args = array() ) {
                        self::$last_args = $args;
                }
        }

        class_alias( __NAMESPACE__ . '\\BulkAuditPageTest_WP_Query_Stub', '\\WP_Query' );
}

/**
 * Bulk auditor admin page unit tests.
 *
 * @covers \FP\SEO\Admin\BulkAuditPage
 */
class BulkAuditPageTest extends TestCase {
		/**
		 * Set up Brain Monkey.
		 */
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();

                if ( ! defined( 'DAY_IN_SECONDS' ) ) {
                        define( 'DAY_IN_SECONDS', 24 * 60 * 60 );
                }

                when( 'get_post_types' )->alias(
                        static function (): array {
                                return array( 'post', 'page' );
                        }
                );
                when( 'post_type_supports' )->alias(
                        static function (): bool {
                                return true;
                        }
                );
        }

		/**
		 * Tear down Brain Monkey.
		 */
	protected function tearDown(): void {
			Monkey\tearDown();
			parent::tearDown();
	}

		/**
		 * Ensures the register method wires expected hooks.
		 */
        public function test_register_adds_expected_hooks(): void {
                        $calls = array();

                        when( 'add_action' )->alias(
                                static function ( string $hook, $callback ) use ( &$calls ) {
							$calls[] = array( $hook, $callback );
							return true;
				}
			);

			$page = new BulkAuditPage();
			$page->register();

			self::assertContains(
				array( 'admin_menu', array( $page, 'add_page' ) ),
				$calls
			);

			self::assertContains(
				array( 'admin_enqueue_scripts', array( $page, 'enqueue_assets' ) ),
				$calls
			);

			self::assertContains(
				array( 'wp_ajax_fp_seo_performance_bulk_analyze', array( $page, 'handle_ajax_analyze' ) ),
				$calls
			);

                        self::assertContains(
                                array( 'admin_post_fp_seo_performance_bulk_export', array( $page, 'handle_export' ) ),
                                $calls
                        );
        }

        /**
         * Ensures cached results are trimmed when exceeding the limit.
         */
        public function test_persist_result_trims_cache_size(): void {
                $reflection = new \ReflectionClass( BulkAuditPage::class );
                $limit      = null;

                foreach ( $reflection->getReflectionConstants() as $constant ) {
                        if ( 'CACHE_LIMIT' === $constant->getName() ) {
                                $limit = (int) $constant->getValue();
                                break;
                        }
                }

                self::assertIsInt( $limit );

                $existing = array();

                for ( $i = 1; $i <= $limit; $i++ ) {
                        $existing[ $i ] = array(
                                'post_id' => $i,
                                'updated' => $i,
                        );
                }

                $captured = array();

                when( 'get_transient' )->alias(
                        static function () use ( $existing ) {
                                return $existing;
                        }
                );
                when( 'set_transient' )->alias(
                        static function ( $key, $value, $ttl ) use ( &$captured ): bool {
                                $captured = array( $key, $value, $ttl );
                                return true;
                        }
                );

                $page   = new BulkAuditPage();
                $method = new \ReflectionMethod( BulkAuditPage::class, 'persist_result' );
                $method->setAccessible( true );
                $method->invoke(
                        $page,
                        array(
                                'post_id' => $limit + 1,
                                'updated' => $limit + 1,
                        )
                );

                self::assertNotEmpty( $captured );
                $cache = $captured[1];

                self::assertCount( $limit, $cache );
                self::assertArrayNotHasKey( 1, $cache );
                self::assertArrayHasKey( $limit + 1, $cache );
        }

        /**
         * Query arguments should avoid expensive counts and cache hydration.
         */
        public function test_query_posts_sets_lightweight_query_flags(): void {
                \WP_Query::$last_args = array();

                $page   = new BulkAuditPage();
                $method = new \ReflectionMethod( BulkAuditPage::class, 'query_posts' );
                $method->setAccessible( true );
                $method->invoke( $page, 'all', 'any' );

                self::assertArrayHasKey( 'no_found_rows', \WP_Query::$last_args );
                self::assertTrue( \WP_Query::$last_args['no_found_rows'] );
                self::assertFalse( \WP_Query::$last_args['update_post_meta_cache'] );
                self::assertFalse( \WP_Query::$last_args['update_post_term_cache'] );
        }
}
