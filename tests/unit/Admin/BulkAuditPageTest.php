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
}
