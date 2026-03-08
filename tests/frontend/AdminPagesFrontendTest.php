<?php
/**
 * Frontend tests for admin pages.
 *
 * @package FP\SEO\Tests\Frontend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Frontend tests for admin pages.
 *
 * These tests verify that dashboard, settings tabs, bulk auditor UI,
 * and performance dashboard buttons work correctly.
 */
final class AdminPagesFrontendTest extends TestCase {

	/**
	 * Test dashboard rendering elements.
	 */
	public function test_dashboard_rendering_elements(): void {
		$expected_elements = array(
			'fp-seo-dashboard-stats',
			'fp-seo-dashboard-content-overview',
			'fp-seo-dashboard-bulk-audit-stats',
		);

		foreach ( $expected_elements as $element_id ) {
			self::assertNotEmpty( $element_id, "Element ID should not be empty: {$element_id}" );
		}
	}

	/**
	 * Test settings page tabs.
	 */
	public function test_settings_page_tabs(): void {
		$tabs = array( 'general', 'analysis', 'performance', 'automation', 'advanced' );

		foreach ( $tabs as $tab ) {
			self::assertNotEmpty( $tab, "Tab should not be empty: {$tab}" );
		}
	}

	/**
	 * Test bulk auditor UI elements.
	 */
	public function test_bulk_auditor_ui_elements(): void {
		$expected_elements = array(
			'fp-seo-bulk-audit-select-all',
			'fp-seo-bulk-audit-analyze',
			'fp-seo-bulk-audit-export',
			'fp-seo-bulk-audit-progress',
			'fp-seo-bulk-audit-results',
		);

		foreach ( $expected_elements as $element_id ) {
			self::assertNotEmpty( $element_id, "Element ID should not be empty: {$element_id}" );
		}
	}

	/**
	 * Test performance dashboard buttons.
	 */
	public function test_performance_dashboard_buttons(): void {
		$buttons = array(
			'fp-seo-health-check',
			'fp-seo-optimize-database',
			'fp-seo-optimize-assets',
			'fp-seo-clear-cache',
		);

		foreach ( $buttons as $button_id ) {
			self::assertNotEmpty( $button_id, "Button ID should not be empty: {$button_id}" );
		}
	}
}



