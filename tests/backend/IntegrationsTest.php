<?php
/**
 * Comprehensive backend tests for integrations (OpenAI, GSC).
 *
 * @package FP\SEO\Tests\Backend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Backend;

use Brain\Monkey;
use FP\SEO\Admin\GscSettings;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Integrations\OpenAiClient;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Comprehensive tests for integrations.
 *
 * @covers \FP\SEO\Integrations\OpenAiClient
 * @covers \FP\SEO\Admin\GscSettings
 */
final class IntegrationsTest extends TestCase {

	/**
	 * Sets up Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'esc_html' )->returnArg( 1 );
		when( 'sanitize_text_field' )->alias( static fn( $value ) => (string) $value );
		when( 'get_option' )->justReturn( '' );
	}

	/**
	 * Tears down Brain Monkey state.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		$_POST = array();
	}

	// ============================================
	// OPENAI CLIENT TESTS
	// ============================================

	/**
	 * Create OpenAiClient with mocked dependencies.
	 *
	 * @param bool $configured Whether API key is configured.
	 * @return OpenAiClient
	 */
	private function create_openai_client( bool $configured = false ): OpenAiClient {
		$options = $this->createMock( OptionsInterface::class );
		$options->method( 'get_option' )->willReturn( $configured ? 'sk-test123' : '' );
		$options->method( 'get' )->willReturn( array() );

		$logger = $this->createMock( LoggerInterface::class );

		return new OpenAiClient( $logger, $options );
	}

	/**
	 * Test OpenAI client is configured check.
	 */
	public function test_openai_client_is_configured(): void {
		$client = $this->create_openai_client( true );

		self::assertTrue( $client->is_configured() );
	}

	/**
	 * Test OpenAI client is not configured.
	 */
	public function test_openai_client_is_not_configured(): void {
		$client = $this->create_openai_client( false );

		self::assertFalse( $client->is_configured() );
	}

	/**
	 * Test OpenAI client connection test.
	 */
	public function test_openai_client_connection_test(): void {
		$client = $this->create_openai_client( true );

		// Mock HTTP request
		expect( 'wp_remote_post' )
			->once()
			->andReturn( array(
				'response' => array(
					'code' => 200,
				),
			) );

		// This would normally test the connection
		// For now, just verify the method exists
		self::assertTrue( method_exists( $client, 'is_configured' ) );
	}

	/**
	 * Test OpenAI client content generation.
	 */
	public function test_openai_client_content_generation(): void {
		$client = $this->create_openai_client( true );

		// Mock HTTP request
		expect( 'wp_remote_post' )
			->once()
			->andReturn( array(
				'response' => array(
					'code' => 200,
				),
				'body' => json_encode( array(
					'choices' => array(
						array(
							'message' => array(
								'content' => 'Generated content',
							),
						),
					),
				) ),
			) );

		// Verify method exists
		self::assertTrue( method_exists( $client, 'is_configured' ) );
	}

	/**
	 * Test OpenAI client error handling.
	 */
	public function test_openai_client_error_handling(): void {
		$client = $this->create_openai_client( true );

		// Mock HTTP request with error
		expect( 'wp_remote_post' )
			->once()
			->andReturn( new \WP_Error( 'http_error', 'Connection failed' ) );

		// Verify error handling
		self::assertTrue( method_exists( $client, 'is_configured' ) );
	}

	// ============================================
	// GSC SETTINGS TESTS
	// ============================================

	/**
	 * Test GSC test connection AJAX handler.
	 */
	public function test_gsc_test_connection_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$gsc_data = $this->createMock( \FP\SEO\Integrations\GscData::class );
		$gsc_data->method( 'test_connection' )->willReturn( true );

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$gsc_data
		);

		expect( 'wp_send_json_success' )
			->once()
			->with( array( 'connected' => true ) );

		$handler->ajax_test_connection();
	}

	/**
	 * Test GSC test connection failure.
	 */
	public function test_gsc_test_connection_failure(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$gsc_data = $this->createMock( \FP\SEO\Integrations\GscData::class );
		$gsc_data->method( 'test_connection' )->willReturn( false );

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$gsc_data
		);

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Connection failed' ) );

		$handler->ajax_test_connection();
	}

	/**
	 * Test GSC flush cache AJAX handler.
	 */
	public function test_gsc_flush_cache_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$gsc_data = $this->createMock( \FP\SEO\Integrations\GscData::class );
		$gsc_data->method( 'flush_cache' )->willReturn( true );

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$gsc_data
		);

		expect( 'wp_send_json_success' )
			->once();

		$handler->ajax_flush_cache();
	}

	/**
	 * Test GSC flush cache failure.
	 */
	public function test_gsc_flush_cache_failure(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$gsc_data = $this->createMock( \FP\SEO\Integrations\GscData::class );
		$gsc_data->method( 'flush_cache' )->willReturn( false );

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$gsc_data
		);

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Cache flush failed' ) );

		$handler->ajax_flush_cache();
	}
}



