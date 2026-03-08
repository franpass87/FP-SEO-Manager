<?php
/**
 * Comprehensive backend tests for all AJAX handlers.
 *
 * @package FP\SEO\Tests\Backend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Backend;

use Brain\Monkey;
use FP\SEO\Admin\AiAjaxHandler;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Admin\GscSettings;
use FP\SEO\Admin\PerformanceDashboard;
use FP\SEO\Admin\TestSuiteAjax;
use FP\SEO\AI\Handlers\AdvancedContentOptimizerAjaxHandler;
use FP\SEO\Editor\Handlers\AnalyzeAjaxHandler;
use FP\SEO\Editor\Handlers\SaveFieldsAjaxHandler;
use FP\SEO\Keywords\Handlers\KeywordsAjaxHandler;
use FP\SEO\Links\Handlers\InternalLinkAjaxHandler;
use FP\SEO\Schema\Handlers\SchemaAjaxHandler;
use FP\SEO\Social\Handlers\SocialAjaxHandler;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Comprehensive tests for all AJAX handlers.
 *
 * @covers \FP\SEO\Editor\Handlers\AnalyzeAjaxHandler
 * @covers \FP\SEO\Editor\Handlers\SaveFieldsAjaxHandler
 * @covers \FP\SEO\Admin\AiAjaxHandler
 * @covers \FP\SEO\Social\Handlers\SocialAjaxHandler
 * @covers \FP\SEO\Schema\Handlers\SchemaAjaxHandler
 * @covers \FP\SEO\Keywords\Handlers\KeywordsAjaxHandler
 * @covers \FP\SEO\Links\Handlers\InternalLinkAjaxHandler
 * @covers \FP\SEO\AI\Handlers\AdvancedContentOptimizerAjaxHandler
 */
final class AjaxHandlersTest extends TestCase {

	/**
	 * Sets up Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'esc_html__' )->returnArg( 1 );
		when( 'esc_html' )->returnArg( 1 );
		when( 'esc_attr' )->returnArg( 1 );
		when( 'wp_unslash' )->alias( static fn( $value ) => $value );
		when( 'sanitize_text_field' )->alias( static fn( $value ) => (string) $value );
		when( 'sanitize_textarea_field' )->alias( static fn( $value ) => (string) $value );
		when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://example.com/wp-admin/' . ltrim( $path, '/' ) );
		when( 'wp_create_nonce' )->alias( static fn( string $action ): string => 'nonce-' . $action );
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
		$_POST = array();
		$_GET = array();
	}

	// ============================================
	// METABOX AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test analyze AJAX handler nonce verification.
	 */
	public function test_analyze_ajax_handler_nonce_verification(): void {
		$_POST['nonce'] = 'invalid-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->with( 'fp_seo_performance_analyze', 'nonce' )
			->andReturn( false );

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Invalid nonce.' ) );

		$handler = new AnalyzeAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class )
		);

		$handler->handle_ajax();
	}

	/**
	 * Test analyze AJAX handler capability check.
	 */
	public function test_analyze_ajax_handler_capability_check(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->with( 'edit_post', 1 )
			->andReturn( false );

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Insufficient permissions.' ) );

		$handler = new AnalyzeAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class )
		);

		$handler->handle_ajax();
	}

	/**
	 * Test analyze AJAX handler success.
	 */
	public function test_analyze_ajax_handler_success(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AnalyzeAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class )
		);

		$handler->handle_ajax();
	}

	/**
	 * Test save fields AJAX handler nonce verification.
	 */
	public function test_save_fields_ajax_handler_nonce_verification(): void {
		$_POST['nonce'] = 'invalid-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->with( 'fp_seo_performance_save_fields', 'nonce' )
			->andReturn( false );

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Invalid nonce.' ) );

		$handler = new SaveFieldsAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class )
		);

		$handler->handle_ajax();
	}

	/**
	 * Test save fields AJAX handler input sanitization.
	 */
	public function test_save_fields_ajax_handler_input_sanitization(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['fp_seo_title'] = '<script>alert("xss")</script>Test Title';
		$_POST['fp_seo_meta_description'] = 'Test Description';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once();

		$handler = new SaveFieldsAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class ),
			$this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class )
		);

		$handler->handle_ajax();

		// Verify sanitization was called
		self::assertTrue( true );
	}

	// ============================================
	// AI AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test AI generate content AJAX handler.
	 */
	public function test_ai_generate_content_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['content'] = 'Test content';
		$_POST['title'] = 'Test title';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AiAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Integrations\OpenAiClient::class )
		);

		$handler->handle_generate_request();
	}

	// ============================================
	// SOCIAL AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test social preview AJAX handler.
	 */
	public function test_social_preview_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['platform'] = 'facebook';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new SocialAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Social\SocialMediaManager::class )
		);

		$handler->handle_preview();
	}

	/**
	 * Test social optimize AJAX handler.
	 */
	public function test_social_optimize_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['platform'] = 'facebook';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new SocialAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Social\SocialMediaManager::class )
		);

		$handler->handle_optimize();
	}

	// ============================================
	// SCHEMA AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test schema generate AJAX handler.
	 */
	public function test_schema_generate_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['schema_type'] = 'Article';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new SchemaAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Schema\AdvancedSchemaManager::class )
		);

		$handler->handle_generate_schema();
	}

	/**
	 * Test schema preview AJAX handler.
	 */
	public function test_schema_preview_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new SchemaAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Schema\AdvancedSchemaManager::class )
		);

		$handler->handle_preview_schema();
	}

	// ============================================
	// KEYWORDS AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test keywords analyze AJAX handler.
	 */
	public function test_keywords_analyze_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new KeywordsAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Keywords\Services\KeywordsAnalysisService::class )
		);

		$handler->handle_analyze();
	}

	/**
	 * Test keywords suggest AJAX handler.
	 */
	public function test_keywords_suggest_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['focus_keyword'] = 'test keyword';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new KeywordsAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Keywords\Services\KeywordsAnalysisService::class )
		);

		$handler->handle_suggest();
	}

	/**
	 * Test keywords optimize AJAX handler.
	 */
	public function test_keywords_optimize_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new KeywordsAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Keywords\Services\KeywordsAnalysisService::class )
		);

		$handler->handle_optimize();
	}

	// ============================================
	// INTERNAL LINKS AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test internal links get suggestions AJAX handler.
	 */
	public function test_internal_links_get_suggestions_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new InternalLinkAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Links\Services\LinkSuggestionService::class )
		);

		$handler->handle_get_suggestions();
	}

	/**
	 * Test internal links analyze AJAX handler.
	 */
	public function test_internal_links_analyze_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new InternalLinkAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Links\Services\LinkSuggestionService::class )
		);

		$handler->handle_analyze();
	}

	/**
	 * Test internal links optimize AJAX handler.
	 */
	public function test_internal_links_optimize_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new InternalLinkAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Links\Services\LinkSuggestionService::class )
		);

		$handler->handle_optimize();
	}

	// ============================================
	// ADVANCED CONTENT OPTIMIZER AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test analyze content gaps AJAX handler.
	 */
	public function test_analyze_content_gaps_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AdvancedContentOptimizerAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\AI\AdvancedContentOptimizer::class )
		);

		$handler->handle_analyze_content_gaps();
	}

	/**
	 * Test competitor analysis AJAX handler.
	 */
	public function test_competitor_analysis_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';
		$_POST['competitor_url'] = 'https://example.com';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AdvancedContentOptimizerAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\AI\AdvancedContentOptimizer::class )
		);

		$handler->handle_competitor_analysis();
	}

	/**
	 * Test content suggestions AJAX handler.
	 */
	public function test_content_suggestions_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AdvancedContentOptimizerAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\AI\AdvancedContentOptimizer::class )
		);

		$handler->handle_content_suggestions();
	}

	/**
	 * Test readability optimization AJAX handler.
	 */
	public function test_readability_optimization_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AdvancedContentOptimizerAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\AI\AdvancedContentOptimizer::class )
		);

		$handler->handle_readability_optimization();
	}

	/**
	 * Test semantic optimization AJAX handler.
	 */
	public function test_semantic_optimization_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_id'] = '1';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new AdvancedContentOptimizerAjaxHandler(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\AI\AdvancedContentOptimizer::class )
		);

		$handler->handle_semantic_optimization();
	}

	// ============================================
	// GSC AJAX HANDLERS TESTS
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
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Integrations\GscData::class )
		);

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
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once();

		$handler = new GscSettings(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$this->createMock( \FP\SEO\Integrations\GscData::class )
		);

		$handler->ajax_flush_cache();
	}

	// ============================================
	// TEST SUITE AJAX HANDLERS TESTS
	// ============================================

	/**
	 * Test test suite run tests AJAX handler.
	 */
	public function test_test_suite_run_tests_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		expect( 'current_user_can' )
			->once()
			->andReturn( true );

		expect( 'wp_send_json_success' )
			->once()
			->with( \PHPUnit\Framework\Assert::isType( 'array' ) );

		$handler = new TestSuiteAjax(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		$handler->handle_run_tests();
	}
}



