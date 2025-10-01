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
}
