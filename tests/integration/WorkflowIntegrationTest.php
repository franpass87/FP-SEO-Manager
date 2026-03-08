<?php
/**
 * Complete workflow integration tests.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for complete workflows.
 */
class WorkflowIntegrationTest extends TestCase {

	/**
	 * Test complete SEO analysis workflow.
	 */
	public function test_seo_analysis_workflow(): void {
		// This would require a test post, but we can test the structure
		$this->assertTrue( class_exists( \FP\SEO\Analysis\Analyzer::class ) );
		$this->assertTrue( class_exists( \FP\SEO\Analysis\Context::class ) );
		$this->assertTrue( class_exists( \FP\SEO\Scoring\ScoreEngine::class ) );
	}

	/**
	 * Test complete cache workflow.
	 */
	public function test_cache_workflow(): void {
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		
		$key = 'test_workflow_' . time();
		$value = array( 'test' => 'data' );
		
		// Set
		$result = $cache->set( $key, $value, 60, 'test' );
		$this->assertTrue( $result );
		
		// Get
		$retrieved = $cache->get( $key, null, 'test' );
		$this->assertEquals( $value, $retrieved );
		
		// Remember
		$remembered = $cache->remember( $key . '_2', function() use ( $value ) {
			return $value;
		}, 60, 'test' );
		$this->assertEquals( $value, $remembered );
		
		// Delete
		$deleted = $cache->delete( $key, 'test' );
		$this->assertTrue( $deleted );
		
		// Verify deleted
		$after_delete = $cache->get( $key, null, 'test' );
		$this->assertNull( $after_delete );
	}

	/**
	 * Test complete validation workflow.
	 */
	public function test_validation_workflow(): void {
		$logger = new \FP\SEO\Core\Services\Logger\WordPressLogger();
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		$rate_limiter = new \FP\SEO\Utils\RateLimiter( $cache );
		$validator = new \FP\SEO\Core\Services\Validation\InputValidator( $logger, $rate_limiter );
		
		$schema = array(
			'title' => array(
				'type' => 'string',
				'required' => true,
				'max_length' => 60,
			),
			'description' => array(
				'type' => 'string',
				'required' => false,
				'max_length' => 155,
			),
		);
		
		$valid_input = array(
			'title' => 'Test Title',
			'description' => 'Test Description',
		);
		
		$result = $validator->validate_schema( $valid_input, $schema );
		$this->assertTrue( $result['valid'] );
		
		$invalid_input = array(
			'title' => str_repeat( 'a', 100 ), // Too long
		);
		
		$result = $validator->validate_schema( $invalid_input, $schema );
		$this->assertFalse( $result['valid'] );
		$this->assertArrayHasKey( 'errors', $result );
	}

	/**
	 * Test complete lazy loading workflow.
	 */
	public function test_lazy_loading_workflow(): void {
		$logger = new \FP\SEO\Core\Services\Logger\WordPressLogger();
		$lazy_loader = new \FP\SEO\Editor\Services\LazyServiceLoader( $logger );
		
		// Check configuration without loading
		$is_configured = $lazy_loader->is_openai_configured();
		$this->assertIsBool( $is_configured );
		
		// Load service only when needed
		$client = $lazy_loader->get_openai_client();
		// Client may be null if not configured, which is fine
		$this->assertTrue( $client === null || $client instanceof \FP\SEO\Integrations\OpenAiClient );
	}
}




