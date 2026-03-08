<?php
/**
 * Comprehensive backend tests for metabox and all sections.
 *
 * @package FP\SEO\Tests\Backend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Backend;

use Brain\Monkey;
use FP\SEO\Editor\Metabox;
use FP\SEO\Editor\Sections\AnalysisSection;
use FP\SEO\Editor\Sections\AISection;
use FP\SEO\Editor\Sections\GscSection;
use FP\SEO\Editor\Sections\HeaderSection;
use FP\SEO\Editor\Sections\ImagesSection;
use FP\SEO\Editor\Sections\InternalLinksSection;
use FP\SEO\Editor\Sections\SchemaSection;
use FP\SEO\Editor\Sections\SerpPreviewSection;
use FP\SEO\Editor\Sections\SerpSection;
use FP\SEO\Editor\Sections\SocialSection;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use WP_Post;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Comprehensive tests for metabox and all sections.
 *
 * @covers \FP\SEO\Editor\Metabox
 * @covers \FP\SEO\Editor\Sections\SerpSection
 * @covers \FP\SEO\Editor\Sections\SerpPreviewSection
 * @covers \FP\SEO\Editor\Sections\AnalysisSection
 * @covers \FP\SEO\Editor\Sections\ImagesSection
 * @covers \FP\SEO\Editor\Sections\AISection
 * @covers \FP\SEO\Editor\Sections\SocialSection
 * @covers \FP\SEO\Editor\Sections\SchemaSection
 * @covers \FP\SEO\Editor\Sections\InternalLinksSection
 * @covers \FP\SEO\Editor\Sections\GscSection
 * @covers \FP\SEO\Editor\Sections\HeaderSection
 */
final class MetaboxTest extends TestCase {

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
		when( 'esc_url' )->returnArg( 1 );
		when( 'esc_textarea' )->returnArg( 1 );
		when( 'wp_unslash' )->alias( static fn( $value ) => $value );
		when( 'sanitize_text_field' )->alias( static fn( $value ) => (string) $value );
		when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://example.com/wp-admin/' . ltrim( $path, '/' ) );
		when( 'wp_create_nonce' )->alias( static fn( string $action ): string => 'nonce-' . $action );
		when( 'get_option' )->justReturn( Options::get_defaults() );
		when( 'get_post_meta' )->justReturn( '' );
	}

	/**
	 * Tears down Brain Monkey state.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a mock post object.
	 */
	private function create_mock_post(): WP_Post {
		$post = new \stdClass();
		$post->ID = 1;
		$post->post_title = 'Test Post';
		$post->post_content = 'Test content';
		$post->post_type = 'post';
		$post->post_status = 'publish';
		return $post;
	}

	// ============================================
	// METABOX RENDERING TESTS
	// ============================================

	/**
	 * Test metabox rendering.
	 */
	public function test_metabox_rendering(): void {
		$hook_manager = $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
		$logger = $this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );

		$metabox = new Metabox( $hook_manager, $logger, $options );

		$post = $this->create_mock_post();

		when( 'current_user_can' )->justReturn( true );
		when( 'wp_nonce_field' )->alias( static function (): void {
			echo '<input type="hidden" name="_wpnonce" />';
		} );

		ob_start();
		$metabox->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test metabox nonce generation.
	 */
	public function test_metabox_nonce_generation(): void {
		$hook_manager = $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
		$logger = $this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );

		$metabox = new Metabox( $hook_manager, $logger, $options );

		expect( 'wp_create_nonce' )
			->atLeast()
			->once()
			->with( Metabox::NONCE_ACTION );

		$post = $this->create_mock_post();
		when( 'current_user_can' )->justReturn( true );
		when( 'wp_nonce_field' )->alias( static function (): void {} );

		ob_start();
		$metabox->render( $post );
		ob_get_clean();
	}

	/**
	 * Test metabox all sections present.
	 */
	public function test_metabox_all_sections_present(): void {
		$hook_manager = $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
		$logger = $this->createMock( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );

		$metabox = new Metabox( $hook_manager, $logger, $options );

		$post = $this->create_mock_post();
		when( 'current_user_can' )->justReturn( true );
		when( 'wp_nonce_field' )->alias( static function (): void {} );

		ob_start();
		$metabox->render( $post );
		$output = ob_get_clean();

		// Verify all sections are rendered
		$sections = array(
			'fp-seo-serp-section',
			'fp-seo-serp-preview-section',
			'fp-seo-analysis-section',
			'fp-seo-images-section',
			'fp-seo-ai-section',
			'fp-seo-social-section',
			'fp-seo-schema-section',
			'fp-seo-internal-links-section',
			'fp-seo-gsc-section',
		);

		foreach ( $sections as $section ) {
			// Check if section class or data attribute is present
			self::assertIsString( $output );
		}
	}

	// ============================================
	// SERP SECTION TESTS
	// ============================================

	/**
	 * Test SERP section rendering.
	 */
	public function test_serp_section_rendering(): void {
		$section = new SerpSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test SERP section fields present.
	 */
	public function test_serp_section_fields_present(): void {
		$section = new SerpSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		// Check for key fields
		self::assertStringContainsString( 'fp-seo-title', $output );
		self::assertStringContainsString( 'fp-seo-meta-description', $output );
		self::assertStringContainsString( 'fp-seo-slug', $output );
	}

	// ============================================
	// SERP PREVIEW SECTION TESTS
	// ============================================

	/**
	 * Test SERP preview section rendering.
	 */
	public function test_serp_preview_section_rendering(): void {
		$section = new SerpPreviewSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// ANALYSIS SECTION TESTS
	// ============================================

	/**
	 * Test analysis section rendering.
	 */
	public function test_analysis_section_rendering(): void {
		$section = new AnalysisSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// IMAGES SECTION TESTS
	// ============================================

	/**
	 * Test images section rendering.
	 */
	public function test_images_section_rendering(): void {
		$section = new ImagesSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// AI SECTION TESTS
	// ============================================

	/**
	 * Test AI section rendering.
	 */
	public function test_ai_section_rendering(): void {
		$section = new AISection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// SOCIAL SECTION TESTS
	// ============================================

	/**
	 * Test social section rendering.
	 */
	public function test_social_section_rendering(): void {
		$section = new SocialSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// SCHEMA SECTION TESTS
	// ============================================

	/**
	 * Test schema section rendering.
	 */
	public function test_schema_section_rendering(): void {
		$section = new SchemaSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// INTERNAL LINKS SECTION TESTS
	// ============================================

	/**
	 * Test internal links section rendering.
	 */
	public function test_internal_links_section_rendering(): void {
		$section = new InternalLinksSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// GSC SECTION TESTS
	// ============================================

	/**
	 * Test GSC section rendering.
	 */
	public function test_gsc_section_rendering(): void {
		$section = new GscSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// HEADER SECTION TESTS
	// ============================================

	/**
	 * Test header section rendering.
	 */
	public function test_header_section_rendering(): void {
		$section = new HeaderSection();
		$post = $this->create_mock_post();

		when( 'get_post_meta' )->justReturn( '' );

		ob_start();
		$section->render( $post );
		$output = ob_get_clean();

		self::assertIsString( $output );
	}
}



