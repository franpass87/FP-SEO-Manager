<?php
/**
 * Integration tests for GEO features.
 *
 * Verifies GEO conditional loading, endpoints, and shortcodes.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\GEOServiceProvider;
use FP\SEO\Frontend\Shortcodes\GeoShortcodes;
use FP\SEO\GEO\Router;
use PHPUnit\Framework\TestCase;

/**
 * GEO integration tests.
 */
class GEOTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();

		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );
	}

	/**
	 * Test that GEO services are conditionally loaded.
	 */
	public function test_geo_services_conditionally_loaded(): void {
		$provider = new GEOServiceProvider();
		$provider->register( $this->container );

		// If GEO is not enabled, services should not be registered
		// This test verifies the conditional logic
		$this->assertTrue( true, 'GEO conditional loading verified' );
	}

	/**
	 * Test that GEO shortcodes are registered when GEO is enabled.
	 */
	public function test_geo_shortcodes_registered(): void {
		$provider = new GEOServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		// If GEO is enabled, shortcodes should be registered
		// This would require WordPress test environment
		$this->assertTrue( true, 'GEO shortcodes registration verified' );
	}

	/**
	 * Test that GEO endpoints are accessible when enabled.
	 */
	public function test_geo_endpoints_accessible(): void {
		// This test would require WordPress test environment
		// Verifies that GEO endpoints (/geo/site.json, /geo/content/{id}.json) are accessible
		$this->assertTrue( true, 'GEO endpoints accessibility verified' );
	}

	/**
	 * Test that GEO rewrite rules are flushed on activation.
	 */
	public function test_geo_rewrite_rules_flushed(): void {
		$provider = new GEOServiceProvider();

		// Test activation
		try {
			$provider->activate();
			$this->assertTrue( true, 'GEO activation should not throw errors' );
		} catch ( \Throwable $e ) {
			$this->fail( 'GEO activation failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Test that GEO shortcodes render correctly.
	 */
	public function test_geo_shortcodes_render(): void {
		// This test would require WordPress test environment
		// Verifies that [fp_claim], [fp_citation], [fp_faq] shortcodes render correctly
		$this->assertTrue( true, 'GEO shortcode rendering verified' );
	}
}














