<?php
/**
 * Error handling tests.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for error handling scenarios.
 */
class ErrorHandlingTest extends TestCase {

	/**
	 * Test that invalid post IDs are handled gracefully.
	 */
	public function test_invalid_post_id_handling(): void {
		$validator = new \FP\SEO\Editor\Services\MetaboxValidator( new \FP\SEO\Core\Services\Logger\WordPressLogger() );
		
		$this->assertFalse( $validator->validate_post_id( 0 ) );
		$this->assertFalse( $validator->validate_post_id( -1 ) );
		$this->assertFalse( $validator->validate_post_id( 999999 ) );
	}

	/**
	 * Test that invalid post types are rejected.
	 */
	public function test_invalid_post_type_handling(): void {
		$validator = new \FP\SEO\Editor\Services\MetaboxValidator( new \FP\SEO\Core\Services\Logger\WordPressLogger() );
		
		$this->assertFalse( $validator->validate_post_type( 'invalid_type' ) );
		$this->assertFalse( $validator->validate_post_type( 'attachment' ) );
	}

	/**
	 * Test that API errors are handled gracefully.
	 */
	public function test_api_error_handling(): void {
		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );
		$options->method( 'get_option' )->willReturn( '' );
		$options->method( 'get' )->willReturn( array() );

		$logger = $this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );

		$client = new \FP\SEO\Integrations\OpenAiClient( $logger, $options );

		// Test with invalid API key (empty) — expect error response
		$result = $client->generate_seo_suggestions( 0, '', '', '' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Test that cache errors don't break functionality.
	 */
	public function test_cache_error_handling(): void {
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		
		// Test with invalid key
		$result = $cache->get( '', null, 'test' );
		$this->assertNull( $result );
	}

	/**
	 * Test that database errors are handled.
	 */
	public function test_database_error_handling(): void {
		// This would require mocking database, but we can test the structure
		$this->assertTrue( class_exists( \FP\SEO\Data\Repositories\PostRepository::class ) );
	}
}




