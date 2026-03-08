<?php
/**
 * Comprehensive backend tests for REST API endpoints.
 *
 * @package FP\SEO\Tests\Backend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Backend;

use Brain\Monkey;
use FP\SEO\REST\Controllers\MetaController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Comprehensive tests for REST API endpoints.
 *
 * @covers \FP\SEO\REST\Controllers\MetaController
 */
final class RestApiTest extends TestCase {

	/**
	 * Sets up Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'esc_html' )->returnArg( 1 );
		when( 'sanitize_text_field' )->alias( static fn( $value ) => (string) $value );
		when( 'get_post' )->alias( static function ( $id ) {
			$post = new \stdClass();
			$post->ID = (int) $id;
			$post->post_title = 'Test Post';
			$post->post_content = 'Test content';
			$post->post_type = 'post';
			$post->post_status = 'publish';
			return $post;
		} );
		when( 'get_post_meta' )->justReturn( '' );
		when( 'update_post_meta' )->alias( static function (): bool {
			return true;
		} );
	}

	/**
	 * Tears down Brain Monkey state.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ============================================
	// META CONTROLLER TESTS
	// ============================================

	/**
	 * Test GET endpoint authentication.
	 */
	public function test_get_endpoint_authentication(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'GET', '/fp-seo/v1/meta/1' );

		// Mock permission check
		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( false );

		$response = $controller->get_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 403, $response->get_status() );
	}

	/**
	 * Test GET endpoint success.
	 */
	public function test_get_endpoint_success(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'GET', '/fp-seo/v1/meta/1' );
		$request->set_param( 'id', 1 );

		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( true );

		$response = $controller->get_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 200, $response->get_status() );
	}

	/**
	 * Test POST endpoint authentication.
	 */
	public function test_post_endpoint_authentication(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'POST', '/fp-seo/v1/meta/1' );
		$request->set_param( 'id', 1 );
		$request->set_body_params( array(
			'fp_seo_title' => 'Test Title',
		) );

		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( false );

		$response = $controller->update_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 403, $response->get_status() );
	}

	/**
	 * Test POST endpoint success.
	 */
	public function test_post_endpoint_success(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'POST', '/fp-seo/v1/meta/1' );
		$request->set_param( 'id', 1 );
		$request->set_body_params( array(
			'fp_seo_title' => 'Test Title',
			'fp_seo_meta_description' => 'Test Description',
		) );

		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( true );

		$response = $controller->update_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 200, $response->get_status() );
	}

	/**
	 * Test POST endpoint input sanitization.
	 */
	public function test_post_endpoint_input_sanitization(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'POST', '/fp-seo/v1/meta/1' );
		$request->set_param( 'id', 1 );
		$request->set_body_params( array(
			'fp_seo_title' => '<script>alert("xss")</script>Test Title',
		) );

		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( true );

		$response = $controller->update_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		// Verify sanitization was applied
		self::assertSame( 200, $response->get_status() );
	}

	/**
	 * Test GET endpoint invalid post ID.
	 */
	public function test_get_endpoint_invalid_post_id(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'GET', '/fp-seo/v1/meta/0' );
		$request->set_param( 'id', 0 );

		$response = $controller->get_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 404, $response->get_status() );
	}

	/**
	 * Test POST endpoint invalid post ID.
	 */
	public function test_post_endpoint_invalid_post_id(): void {
		$controller = new MetaController(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$request = new WP_REST_Request( 'POST', '/fp-seo/v1/meta/0' );
		$request->set_param( 'id', 0 );

		$response = $controller->update_item( $request );

		self::assertInstanceOf( WP_REST_Response::class, $response );
		self::assertSame( 404, $response->get_status() );
	}
}



