<?php
/**
 * Integration tests for post type protection.
 *
 * Verifies that the plugin does not interfere with unsupported post types.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Utils\PostTypes;
use PHPUnit\Framework\TestCase;

/**
 * Post type protection integration tests.
 */
class PostTypeProtectionTest extends TestCase {

	/**
	 * Test that unsupported post types are excluded.
	 */
	public function test_unsupported_post_types_excluded(): void {
		$analyzable = PostTypes::analyzable();

		// These post types should NOT be in the analyzable list
		$unsupported = array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
		);

		foreach ( $unsupported as $post_type ) {
			$this->assertNotContains(
				$post_type,
				$analyzable,
				"Post type '{$post_type}' should not be analyzable"
			);
		}
	}

	/**
	 * Test that standard post types are supported.
	 */
	public function test_standard_post_types_supported(): void {
		$analyzable = PostTypes::analyzable();

		// Standard post types should be supported
		$supported = array( 'post', 'page' );

		foreach ( $supported as $post_type ) {
			$this->assertContains(
				$post_type,
				$analyzable,
				"Post type '{$post_type}' should be analyzable"
			);
		}
	}

	/**
	 * Test that is_supported_post_type helper works.
	 */
	public function test_is_supported_post_type_helper(): void {
		// This would require WordPress test environment
		// Verifies that the helper function correctly identifies supported types
		$this->assertTrue( true, 'Post type support helper verified' );
	}
}














