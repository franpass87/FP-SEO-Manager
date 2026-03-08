<?php
/**
 * Integration tests for media library page blocking.
 *
 * Verifies that the plugin does not load on media library pages.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Bootstrap\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * Media library blocking integration tests.
 */
class MediaLibraryBlockingTest extends TestCase {

	/**
	 * Test that kernel blocks loading on media library pages.
	 */
	public function test_kernel_blocks_media_library_pages(): void {
		// Mock REQUEST_URI for media library
		$_SERVER['REQUEST_URI'] = '/wp-admin/upload.php';

		$kernel = new Kernel( __FILE__, '0.9.0-pre.72' );
		$should_load = $kernel->should_load();

		// Should return false for pure media library pages
		$this->assertFalse( $should_load, 'Plugin should not load on media library pages' );

		// Cleanup
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test that kernel allows loading on post edit pages.
	 */
	public function test_kernel_allows_post_edit_pages(): void {
		// Mock REQUEST_URI for post edit (not pure media library)
		$_SERVER['REQUEST_URI'] = '/wp-admin/post.php?post=1&action=edit';

		$kernel = new Kernel( __FILE__, '0.9.0-pre.72' );
		$should_load = $kernel->should_load();

		// Should return true for post edit pages
		$this->assertTrue( $should_load, 'Plugin should load on post edit pages' );

		// Cleanup
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test that kernel blocks media-new.php.
	 */
	public function test_kernel_blocks_media_new(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/media-new.php';

		$kernel = new Kernel( __FILE__, '0.9.0-pre.72' );
		$should_load = $kernel->should_load();

		$this->assertFalse( $should_load, 'Plugin should not load on media-new.php' );

		// Cleanup
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test that kernel blocks query-attachments AJAX.
	 */
	public function test_kernel_blocks_query_attachments_ajax(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
		$_REQUEST['action'] = 'query-attachments';

		$kernel = new Kernel( __FILE__, '0.9.0-pre.72' );
		$should_load = $kernel->should_load();

		$this->assertFalse( $should_load, 'Plugin should not load on query-attachments AJAX' );

		// Cleanup
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_REQUEST['action'] );
	}
}














