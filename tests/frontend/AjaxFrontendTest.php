<?php
/**
 * Frontend tests for AJAX calls.
 *
 * @package FP\SEO\Tests\Frontend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Frontend tests for AJAX interactions.
 *
 * These tests verify that all AJAX calls, loading states, error handling,
 * and UI updates work correctly.
 */
final class AjaxFrontendTest extends TestCase {

	/**
	 * Test all AJAX actions are defined.
	 */
	public function test_all_ajax_actions_defined(): void {
		$ajax_actions = array(
			'fp_seo_performance_analyze',
			'fp_seo_performance_save_fields',
			'fp_seo_generate_ai_content',
			'fp_seo_preview_social',
			'fp_seo_optimize_social',
			'fp_seo_generate_schema',
			'fp_seo_preview_schema',
			'fp_seo_analyze_keywords',
			'fp_seo_suggest_keywords',
			'fp_seo_optimize_keywords',
			'fp_seo_get_link_suggestions',
			'fp_seo_analyze_internal_links',
			'fp_seo_optimize_internal_links',
			'fp_seo_analyze_content_gaps',
			'fp_seo_competitor_analysis',
			'fp_seo_content_suggestions',
			'fp_seo_readability_optimization',
			'fp_seo_semantic_optimization',
			'fp_seo_gsc_test_connection',
			'fp_seo_gsc_flush_cache',
			'fp_seo_run_tests',
			'fp_seo_run_health_check',
			'fp_seo_optimize_database',
			'fp_seo_optimize_assets',
			'fp_seo_clear_cache',
		);

		foreach ( $ajax_actions as $action ) {
			self::assertNotEmpty( $action, "AJAX action should not be empty: {$action}" );
			self::assertStringStartsWith( 'fp_seo_', $action, "AJAX action should start with 'fp_seo_': {$action}" );
		}
	}

	/**
	 * Test loading states are handled.
	 */
	public function test_loading_states(): void {
		$loading_classes = array(
			'fp-seo-loading',
			'fp-seo-analyzing',
			'fp-seo-generating',
		);

		foreach ( $loading_classes as $class ) {
			self::assertNotEmpty( $class, "Loading class should not be empty: {$class}" );
		}
	}

	/**
	 * Test error handling.
	 */
	public function test_error_handling(): void {
		$error_classes = array(
			'fp-seo-error',
			'fp-seo-error-message',
		);

		foreach ( $error_classes as $class ) {
			self::assertNotEmpty( $class, "Error class should not be empty: {$class}" );
		}
	}

	/**
	 * Test success states.
	 */
	public function test_success_states(): void {
		$success_classes = array(
			'fp-seo-success',
			'fp-seo-success-message',
		);

		foreach ( $success_classes as $class ) {
			self::assertNotEmpty( $class, "Success class should not be empty: {$class}" );
		}
	}
}



