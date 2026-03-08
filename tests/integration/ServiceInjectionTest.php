<?php
/**
 * Integration tests for service injection.
 *
 * Verifies that services are properly injected via dependency injection container.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\CacheInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Core\Services\Logger\WordPressLogger;
use FP\SEO\Core\Services\Cache\WordPressCache;
use FP\SEO\Core\Services\Options\OptionsManager;
use FP\SEO\Infrastructure\Bootstrap\HookManager;
use PHPUnit\Framework\TestCase;

/**
 * Service injection integration tests.
 */
class ServiceInjectionTest extends TestCase {

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
		$provider = new CoreServiceProvider();
		$provider->register( $this->container );
	}

	/**
	 * Test that LoggerInterface is registered and resolvable.
	 */
	public function test_logger_interface_injection(): void {
		$logger = $this->container->get( LoggerInterface::class );
		
		$this->assertInstanceOf( LoggerInterface::class, $logger );
		$this->assertInstanceOf( WordPressLogger::class, $logger );
	}

	/**
	 * Test that CacheInterface is registered and resolvable.
	 */
	public function test_cache_interface_injection(): void {
		$cache = $this->container->get( CacheInterface::class );
		
		$this->assertInstanceOf( CacheInterface::class, $cache );
		$this->assertInstanceOf( WordPressCache::class, $cache );
	}

	/**
	 * Test that OptionsInterface is registered and resolvable.
	 */
	public function test_options_interface_injection(): void {
		$options = $this->container->get( OptionsInterface::class );
		
		$this->assertInstanceOf( OptionsInterface::class, $options );
		$this->assertInstanceOf( OptionsManager::class, $options );
	}

	/**
	 * Test that HookManagerInterface is registered and resolvable.
	 */
	public function test_hook_manager_interface_injection(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		
		$this->assertInstanceOf( HookManagerInterface::class, $hook_manager );
		$this->assertInstanceOf( HookManager::class, $hook_manager );
	}

	/**
	 * Test that OptionsManager receives CacheInterface dependency.
	 */
	public function test_options_manager_dependency_injection(): void {
		$options = $this->container->get( OptionsInterface::class );
		
		// Verify OptionsManager is created with Cache dependency
		$this->assertInstanceOf( OptionsManager::class, $options );
		
		// Test that OptionsManager can use its dependencies
		$defaults = $options->get_defaults();
		$this->assertIsArray( $defaults );
	}

	/**
	 * Test that services are singletons.
	 */
	public function test_services_are_singletons(): void {
		$logger1 = $this->container->get( LoggerInterface::class );
		$logger2 = $this->container->get( LoggerInterface::class );
		
		$this->assertSame( $logger1, $logger2, 'Logger should be a singleton' );
		
		$cache1 = $this->container->get( CacheInterface::class );
		$cache2 = $this->container->get( CacheInterface::class );
		
		$this->assertSame( $cache1, $cache2, 'Cache should be a singleton' );
		
		$options1 = $this->container->get( OptionsInterface::class );
		$options2 = $this->container->get( OptionsInterface::class );
		
		$this->assertSame( $options1, $options2, 'Options should be a singleton' );
	}

	/**
	 * Test that concrete classes are also registered.
	 */
	public function test_concrete_classes_registered(): void {
		$logger = $this->container->get( WordPressLogger::class );
		$this->assertInstanceOf( WordPressLogger::class, $logger );
		
		$cache = $this->container->get( WordPressCache::class );
		$this->assertInstanceOf( WordPressCache::class, $cache );
		
		$options = $this->container->get( OptionsManager::class );
		$this->assertInstanceOf( OptionsManager::class, $options );
	}
}














