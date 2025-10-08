<?php
/**
 * Tests for MetadataResolver utility.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Utils;

use Brain\Monkey\Functions;
use FP\SEO\Utils\MetadataResolver;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Test case for MetadataResolver.
 */
class MetadataResolverTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test resolve_meta_description with custom meta.
	 */
	public function test_resolve_meta_description_with_custom_meta(): void {
		$post          = new WP_Post( (object) array( 'ID' => 1, 'post_excerpt' => 'Excerpt text' ) );
		$custom_meta   = 'Custom meta description';

		Functions\when( 'get_post_meta' )->justReturn( $custom_meta );
		Functions\when( 'wp_strip_all_tags' )->returnArg();

		$result = MetadataResolver::resolve_meta_description( $post );

		$this->assertSame( $custom_meta, $result );
	}

	/**
	 * Test resolve_meta_description falls back to excerpt.
	 */
	public function test_resolve_meta_description_fallback_to_excerpt(): void {
		$excerpt = 'Post excerpt text';
		$post    = new WP_Post( (object) array( 'ID' => 1, 'post_excerpt' => $excerpt ) );

		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_strip_all_tags' )->justReturn( $excerpt );

		$result = MetadataResolver::resolve_meta_description( $post );

		$this->assertSame( $excerpt, $result );
	}

	/**
	 * Test resolve_meta_description with post ID.
	 */
	public function test_resolve_meta_description_with_post_id(): void {
		$custom_meta = 'Meta from ID';

		Functions\when( 'get_post_meta' )->justReturn( $custom_meta );
		Functions\when( 'wp_strip_all_tags' )->returnArg();

		$result = MetadataResolver::resolve_meta_description( 123 );

		$this->assertSame( $custom_meta, $result );
	}

	/**
	 * Test resolve_canonical_url returns canonical when set.
	 */
	public function test_resolve_canonical_url_returns_value(): void {
		$canonical = 'https://example.com/canonical';
		$post      = new WP_Post( (object) array( 'ID' => 1 ) );

		Functions\when( 'get_post_meta' )->justReturn( $canonical );

		$result = MetadataResolver::resolve_canonical_url( $post );

		$this->assertSame( $canonical, $result );
	}

	/**
	 * Test resolve_canonical_url returns null when not set.
	 */
	public function test_resolve_canonical_url_returns_null(): void {
		$post = new WP_Post( (object) array( 'ID' => 1 ) );

		Functions\when( 'get_post_meta' )->justReturn( '' );

		$result = MetadataResolver::resolve_canonical_url( $post );

		$this->assertNull( $result );
	}

	/**
	 * Test resolve_robots returns robots directive when set.
	 */
	public function test_resolve_robots_returns_value(): void {
		$robots = 'noindex, nofollow';
		$post   = new WP_Post( (object) array( 'ID' => 1 ) );

		Functions\when( 'get_post_meta' )->justReturn( $robots );

		$result = MetadataResolver::resolve_robots( $post );

		$this->assertSame( $robots, $result );
	}

	/**
	 * Test resolve_robots returns null when not set.
	 */
	public function test_resolve_robots_returns_null(): void {
		$post = new WP_Post( (object) array( 'ID' => 1 ) );

		Functions\when( 'get_post_meta' )->justReturn( '' );

		$result = MetadataResolver::resolve_robots( $post );

		$this->assertNull( $result );
	}

	/**
	 * Test resolve_robots with post ID.
	 */
	public function test_resolve_robots_with_post_id(): void {
		$robots = 'noindex';

		Functions\when( 'get_post_meta' )->justReturn( $robots );

		$result = MetadataResolver::resolve_robots( 456 );

		$this->assertSame( $robots, $result );
	}
}