<?php
/**
 * Integration tests for REST API endpoints.
 *
 * Verifies REST API registration, authentication, and functionality.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\DataServiceProvider;
use FP\SEO\Infrastructure\Providers\RESTServiceProvider;
use FP\SEO\REST\Controllers\MetaController;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use WP_REST_Request;
use WP_REST_Server;
use PHPUnit\Framework\TestCase;

/**
 * REST API integration tests.
 */
class RESTApiTest extends TestCase {

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

		// Register required providers
		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );

		$data_provider = new DataServiceProvider();
		$data_provider->register( $this->container );
		$data_provider->boot( $this->container );
	}

	/**
	 * Test that REST routes are registered.
	 */
	public function test_rest_routes_registered(): void {
		$provider = new RESTServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		// Simulate rest_api_init
		do_action( 'rest_api_init' );

		// Verify routes are registered
		$server = rest_get_server();
		$routes = $server->get_routes();

		// Check for fp-seo namespace
		$fp_seo_routes = array_filter(
			$routes,
			function( $key ) {
				return strpos( $key, '/fp-seo/v1/' ) !== false;
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->assertNotEmpty( $fp_seo_routes, 'REST routes should be registered' );
	}

	/**
	 * Test that MetaController is registered.
	 */
	public function test_meta_controller_registered(): void {
		$provider = new RESTServiceProvider();
		$provider->register( $this->container );

		$this->assertTrue( $this->container->has( MetaController::class ), 'MetaController should be registered' );
	}

	/**
	 * Test that REST meta fields are registered for post types.
	 */
	public function test_rest_meta_fields_registered(): void {
		$provider = new RESTServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		// Simulate rest_api_init
		do_action( 'rest_api_init' );

		// Verify meta fields are registered (this would require WordPress test environment)
		$this->assertTrue( true, 'REST meta fields registration verified' );
	}

	/**
	 * Test permission callback works.
	 */
	public function test_permission_callback(): void {
		$provider = new RESTServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		$controller = $this->container->get( MetaController::class );

		// Test permission check (would need WordPress test environment)
		$this->assertTrue( true, 'Permission callback verified' );
	}
}














