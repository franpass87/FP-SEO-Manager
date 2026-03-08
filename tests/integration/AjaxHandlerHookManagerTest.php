<?php
/**
 * Integration tests for AJAX handler hook registration via HookManager.
 *
 * Verifies that AJAX handlers properly use HookManager for hook registrations.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AISettingsServiceProvider;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Admin\Ajax\AbstractAjaxHandler;
use FP\SEO\Admin\AiAjaxHandler;
use FP\SEO\Admin\AiFirstAjaxHandler;
use PHPUnit\Framework\TestCase;

/**
 * AJAX Handler HookManager integration tests.
 */
class AjaxHandlerHookManagerTest extends TestCase {

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
		
		// Register core services
		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		
		// Register AI settings services
		$ai_provider = new AISettingsServiceProvider();
		$ai_provider->register( $this->container );
	}

	/**
	 * Test that AbstractAjaxHandler requires HookManager.
	 */
	public function test_abstract_ajax_handler_requires_hook_manager(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		
		// Create a concrete implementation for testing
		$handler = new class( $hook_manager ) extends AbstractAjaxHandler {
			public function get_action(): string {
				return 'test_action';
			}
			
			protected function handle( array $data ): array {
				return array( 'success' => true );
			}
		};
		
		$this->assertInstanceOf( AbstractAjaxHandler::class, $handler );
	}

	/**
	 * Test that AiAjaxHandler requires HookManager.
	 */
	public function test_ai_ajax_handler_requires_hook_manager(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		$handler = new AiAjaxHandler( $hook_manager );
		
		$this->assertInstanceOf( AiAjaxHandler::class, $handler );
	}

	/**
	 * Test that AiFirstAjaxHandler requires HookManager.
	 */
	public function test_ai_first_ajax_handler_requires_hook_manager(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		$handler = new AiFirstAjaxHandler( $hook_manager );
		
		$this->assertInstanceOf( AiFirstAjaxHandler::class, $handler );
	}

	/**
	 * Test that AJAX handlers use HookManager for registration.
	 */
	public function test_ajax_handlers_use_hook_manager(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		
		$handler = new class( $hook_manager ) extends AbstractAjaxHandler {
			public function get_action(): string {
				return 'test_ajax_action';
			}
			
			protected function handle( array $data ): array {
				return array( 'success' => true );
			}
		};
		
		// Register should use HookManager
		$handler->register();
		
		// Verify hook is registered
		$this->assertTrue( has_action( 'wp_ajax_test_ajax_action' ) !== false );
	}

	/**
	 * Test that AiAjaxHandler registers hooks via HookManager.
	 */
	public function test_ai_ajax_handler_registers_hooks(): void {
		$hook_manager = $this->container->get( HookManagerInterface::class );
		$handler = new AiAjaxHandler( $hook_manager );
		
		$handler->register();
		
		// Verify hooks are registered
		$this->assertTrue( has_action( 'wp_ajax_fp_seo_generate_ai_content' ) !== false );
		$this->assertTrue( has_action( 'shutdown' ) !== false );
	}
}














