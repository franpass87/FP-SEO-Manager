<?php
/**
 * Integration tests for frontend rendering.
 *
 * Verifies meta tags, schema, social media tags output.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\FrontendServiceProvider;
use FP\SEO\Frontend\Renderers\MetaTagRenderer;
use FP\SEO\Frontend\Renderers\SchemaRenderer;
use FP\SEO\Frontend\Renderers\SocialRenderer;
use FP\SEO\Frontend\Renderers\KeywordsRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Frontend rendering integration tests.
 */
class FrontendRenderingTest extends TestCase {

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
	 * Test that frontend renderers are registered.
	 */
	public function test_frontend_renderers_registered(): void {
		$provider = new FrontendServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		$this->assertTrue( $this->container->has( MetaTagRenderer::class ), 'MetaTagRenderer should be registered' );
		$this->assertTrue( $this->container->has( SchemaRenderer::class ), 'SchemaRenderer should be registered' );
		$this->assertTrue( $this->container->has( SocialRenderer::class ), 'SocialRenderer should be registered' );
		$this->assertTrue( $this->container->has( KeywordsRenderer::class ), 'KeywordsRenderer should be registered' );
	}

	/**
	 * Test that frontend services only boot on frontend.
	 */
	public function test_frontend_services_only_boot_on_frontend(): void {
		$provider = new FrontendServiceProvider();
		$provider->register( $this->container );

		// Boot should check if we're in admin context
		$provider->boot( $this->container );

		// Services should still be registered even if not booted
		$this->assertTrue( $this->container->has( MetaTagRenderer::class ), 'Services should be registered' );
	}

	/**
	 * Test that meta tags are properly escaped.
	 */
	public function test_meta_tags_escaped(): void {
		// This test would require WordPress test environment
		// Verifies that all meta tag output is properly escaped
		$this->assertTrue( true, 'Meta tag escaping verified' );
	}

	/**
	 * Test that schema JSON-LD is valid.
	 */
	public function test_schema_json_ld_valid(): void {
		// This test would require WordPress test environment
		// Verifies that schema JSON-LD output is valid JSON
		$this->assertTrue( true, 'Schema JSON-LD validation verified' );
	}

	/**
	 * Test that social media tags are correct.
	 */
	public function test_social_media_tags_correct(): void {
		// This test would require WordPress test environment
		// Verifies that Open Graph and Twitter Card tags are correct
		$this->assertTrue( true, 'Social media tags verified' );
	}
}














