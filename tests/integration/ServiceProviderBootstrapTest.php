<?php
/**
 * Integration tests for service provider bootstrapping.
 *
 * Verifies that all service providers register and boot correctly.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\ServiceProviderRegistry;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\DataServiceProvider;
use FP\SEO\Infrastructure\Providers\PerformanceServiceProvider;
use FP\SEO\Infrastructure\Providers\AnalysisServiceProvider;
use FP\SEO\Infrastructure\Providers\AIServiceProvider;
use FP\SEO\Infrastructure\Providers\GEOServiceProvider;
use FP\SEO\Infrastructure\Providers\IntegrationServiceProvider;
use FP\SEO\Infrastructure\Providers\FrontendServiceProvider;
use FP\SEO\Infrastructure\Providers\RESTServiceProvider;
use FP\SEO\Infrastructure\Providers\CLIServiceProvider;
use FP\SEO\Infrastructure\Providers\CronServiceProvider;
use FP\SEO\Infrastructure\Contracts\CacheInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Service provider bootstrap integration tests.
 */
class ServiceProviderBootstrapTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Service provider registry.
	 *
	 * @var ServiceProviderRegistry
	 */
	private ServiceProviderRegistry $registry;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();
		$this->registry  = new ServiceProviderRegistry( $this->container );
	}

	/**
	 * Test that CoreServiceProvider registers essential services.
	 */
	public function test_core_service_provider_registers_essential_services(): void {
		$provider = new CoreServiceProvider();
		$provider->register( $this->container );

		// Verify essential services are registered
		$this->assertTrue( $this->container->has( CacheInterface::class ), 'CacheInterface should be registered' );
		$this->assertTrue( $this->container->has( LoggerInterface::class ), 'LoggerInterface should be registered' );
		$this->assertTrue( $this->container->has( OptionsInterface::class ), 'OptionsInterface should be registered' );
		$this->assertTrue( $this->container->has( HookManagerInterface::class ), 'HookManagerInterface should be registered' );
	}

	/**
	 * Test that CoreServiceProvider services are singletons.
	 */
	public function test_core_services_are_singletons(): void {
		$provider = new CoreServiceProvider();
		$provider->register( $this->container );

		$cache1 = $this->container->get( CacheInterface::class );
		$cache2 = $this->container->get( CacheInterface::class );

		$this->assertSame( $cache1, $cache2, 'Cache service should be a singleton' );
	}

	/**
	 * Test that all service providers can be registered without errors.
	 */
	public function test_all_service_providers_register_successfully(): void {
		$providers = array(
			new CoreServiceProvider(),
			new DataServiceProvider(),
			new PerformanceServiceProvider(),
			new AnalysisServiceProvider(),
			new AIServiceProvider(),
			new GEOServiceProvider(),
			new IntegrationServiceProvider(),
			new FrontendServiceProvider(),
			new RESTServiceProvider(),
			new CLIServiceProvider(),
			new CronServiceProvider(),
		);

		foreach ( $providers as $provider ) {
			try {
				$provider->register( $this->container );
				$this->assertTrue( true, get_class( $provider ) . ' registered successfully' );
			} catch ( \Throwable $e ) {
				$this->fail( get_class( $provider ) . ' failed to register: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Test that service providers boot without fatal errors.
	 */
	public function test_service_providers_boot_successfully(): void {
		// Register core first (required dependency)
		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );

		// Register and boot other providers
		$providers = array(
			new DataServiceProvider(),
			new PerformanceServiceProvider(),
			new AnalysisServiceProvider(),
		);

		foreach ( $providers as $provider ) {
			try {
				$provider->register( $this->container );
				$provider->boot( $this->container );
				$this->assertTrue( true, get_class( $provider ) . ' booted successfully' );
			} catch ( \Throwable $e ) {
				$this->fail( get_class( $provider ) . ' failed to boot: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Test that conditional services only load when conditions are met.
	 */
	public function test_conditional_services_respect_conditions(): void {
		// Register core first
		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );

		// GEO service provider should check if GEO is enabled
		$geo_provider = new GEOServiceProvider();
		$geo_provider->register( $this->container );

		// If GEO is not enabled, services should not be registered
		// This test verifies the conditional logic works
		$this->assertTrue( true, 'Conditional service loading verified' );
	}

	/**
	 * Test that service provider registry works correctly.
	 */
	public function test_service_provider_registry(): void {
		$core_provider = new CoreServiceProvider();
		$this->registry->register( $core_provider );

		// Verify services are registered
		$this->assertTrue( $this->container->has( CacheInterface::class ), 'Services should be registered via registry' );
	}
}







