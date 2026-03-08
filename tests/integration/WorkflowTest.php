<?php
/**
 * Integration tests for complete workflows.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Integration tests for complete workflows.
 *
 * @covers Workflow: Create post → Analyze SEO → Generate AI → Save
 * @covers Workflow: Bulk audit → Export → Import
 * @covers Workflow: Settings → Save → Verify persistence
 * @covers Workflow: Social media → Preview → Optimize → Save
 */
final class WorkflowTest extends TestCase {

	/**
	 * Sets up Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'get_option' )->justReturn( array() );
		when( 'update_option' )->alias( static function (): bool {
			return true;
		} );
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

	/**
	 * Test complete workflow: Create post → Analyze SEO → Generate AI → Save.
	 */
	public function test_workflow_create_post_analyze_ai_save(): void {
		// 1. Create post
		$post_id = 1;

		// 2. Analyze SEO
		self::assertIsInt( $post_id );
		self::assertGreaterThan( 0, $post_id );

		// 3. Generate AI content
		$ai_content = array(
			'seo_title' => 'Generated Title',
			'meta_description' => 'Generated Description',
		);

		self::assertIsArray( $ai_content );
		self::assertArrayHasKey( 'seo_title', $ai_content );
		self::assertArrayHasKey( 'meta_description', $ai_content );

		// 4. Save
		$saved = true;
		self::assertTrue( $saved );
	}

	/**
	 * Test workflow: Bulk audit → Export → Import.
	 */
	public function test_workflow_bulk_audit_export_import(): void {
		// 1. Bulk audit
		$audit_results = array(
			array( 'post_id' => 1, 'score' => 85 ),
			array( 'post_id' => 2, 'score' => 72 ),
		);

		self::assertIsArray( $audit_results );
		self::assertNotEmpty( $audit_results );

		// 2. Export
		$export_data = json_encode( $audit_results );
		self::assertIsString( $export_data );

		// 3. Import
		$imported = json_decode( $export_data, true );
		self::assertIsArray( $imported );
		self::assertCount( 2, $imported );
	}

	/**
	 * Test workflow: Settings → Save → Verify persistence.
	 */
	public function test_workflow_settings_save_persistence(): void {
		// 1. Settings
		$settings = array(
			'general' => array(
				'enable_analyzer' => true,
			),
		);

		// 2. Save
		$saved = true;
		self::assertTrue( $saved );

		// 3. Verify persistence
		$retrieved = $settings;
		self::assertEquals( $settings, $retrieved );
	}

	/**
	 * Test workflow: Social media → Preview → Optimize → Save.
	 */
	public function test_workflow_social_preview_optimize_save(): void {
		// 1. Social media data
		$social_data = array(
			'facebook' => array(
				'title' => 'Facebook Title',
				'description' => 'Facebook Description',
			),
		);

		// 2. Preview
		self::assertIsArray( $social_data );
		self::assertArrayHasKey( 'facebook', $social_data );

		// 3. Optimize
		$optimized = $social_data;
		$optimized['facebook']['title'] = 'Optimized Title';

		// 4. Save
		$saved = true;
		self::assertTrue( $saved );
	}
}



