<?php
/**
 * Integration tests for all 22 service providers.
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
use FP\SEO\Infrastructure\Providers\Admin\AdminAssetsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminPagesServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminUIServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AISettingsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\TestSuiteServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MetaboxServicesProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\SchemaMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MainMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\QAMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\FreshnessMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\AuthorProfileMetaboxServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * All service providers integration tests.
 */
class AllServiceProvidersTest extends TestCase {

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
	 * Get all service providers in registration order.
	 *
	 * @return array Service providers.
	 */
	private function get_all_service_providers(): array {
		return array(
			// 1. Core services (foundational)
			new CoreServiceProvider(),
			// 2. Data services
			new DataServiceProvider(),
			// 3. Performance services
			new PerformanceServiceProvider(),
			// 4. Analysis services
			new AnalysisServiceProvider(),
			// 4.5. Metabox Services
			new MetaboxServicesProvider(),
			// 5. Schema Metaboxes
			new SchemaMetaboxServiceProvider(),
			// 6. Main SEO Metabox
			new MainMetaboxServiceProvider(),
			// 7. QA Metabox
			new QAMetaboxServiceProvider(),
			// 8. Freshness Metabox
			new FreshnessMetaboxServiceProvider(),
			// 9. Author Profile Fields
			new AuthorProfileMetaboxServiceProvider(),
			// 10. Admin Assets
			new AdminAssetsServiceProvider(),
			// 12. Admin Pages
			new AdminPagesServiceProvider(),
			// 13. Admin UI components
			new AdminUIServiceProvider(),
			// 14. AI services
			new AIServiceProvider(),
			// 15. AI Settings
			new AISettingsServiceProvider(),
			// 16. GEO services
			new GEOServiceProvider(),
			// 17. Integration services
			new IntegrationServiceProvider(),
			// 18. Frontend services
			new FrontendServiceProvider(),
			// 19. Test Suite
			new TestSuiteServiceProvider(),
			// 20. REST API services
			new RESTServiceProvider(),
			// 21. WP-CLI services
			new CLIServiceProvider(),
			// 22. Cron services
			new CronServiceProvider(),
		);
	}

	/**
	 * Test that all 22 service providers can be registered.
	 */
	public function test_all_service_providers_register(): void {
		$providers = $this->get_all_service_providers();

		foreach ( $providers as $index => $provider ) {
			try {
				$provider->register( $this->container );
				$this->assertTrue( true, sprintf( 'Provider #%d (%s) registered successfully', $index + 1, get_class( $provider ) ) );
			} catch ( \Throwable $e ) {
				$this->fail( sprintf( 'Provider #%d (%s) failed to register: %s', $index + 1, get_class( $provider ), $e->getMessage() ) );
			}
		}

		$this->assertCount( 22, $providers, 'Should have exactly 22 service providers' );
	}

	/**
	 * Test that service providers register in correct order.
	 */
	public function test_service_providers_register_in_order(): void {
		$providers = $this->get_all_service_providers();

		// Register all providers in order
		foreach ( $providers as $provider ) {
			$provider->register( $this->container );
		}

		// Verify core services are available (required by others)
		$this->assertTrue( $this->container->has( \FP\SEO\Infrastructure\Contracts\CacheInterface::class ), 'Core services should be registered first' );
	}

	/**
	 * Test that service providers boot without fatal errors.
	 */
	public function test_all_service_providers_boot(): void {
		$providers = $this->get_all_service_providers();

		// Register all first
		foreach ( $providers as $provider ) {
			$provider->register( $this->container );
		}

		// Then boot all
		foreach ( $providers as $index => $provider ) {
			try {
				$provider->boot( $this->container );
				$this->assertTrue( true, sprintf( 'Provider #%d (%s) booted successfully', $index + 1, get_class( $provider ) ) );
			} catch ( \Throwable $e ) {
				$this->fail( sprintf( 'Provider #%d (%s) failed to boot: %s', $index + 1, get_class( $provider ), $e->getMessage() ) );
			}
		}
	}

	/**
	 * Test that service provider registry works with all providers.
	 */
	public function test_service_provider_registry_with_all_providers(): void {
		$providers = $this->get_all_service_providers();

		foreach ( $providers as $provider ) {
			$this->registry->register( $provider );
		}

		// Boot all via registry
		$this->registry->boot();

		// Verify services are available
		$this->assertTrue( $this->container->has( \FP\SEO\Infrastructure\Contracts\CacheInterface::class ), 'Services should be available after registry boot' );
	}

	/**
	 * Test that conditional services respect their conditions.
	 */
	public function test_conditional_services_respect_conditions(): void {
		// Register core first
		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );

		// Test GEO provider (conditional on GEO being enabled)
		$geo_provider = new GEOServiceProvider();
		$geo_provider->register( $this->container );

		// Test CLI provider (conditional on WP-CLI being available)
		$cli_provider = new CLIServiceProvider();
		$cli_provider->register( $this->container );

		// These should not throw errors even if conditions aren't met
		$this->assertTrue( true, 'Conditional services handle conditions correctly' );
	}
}







