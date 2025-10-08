<?php
/**
 * Tests for GeneralTabRenderer.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Admin\Settings;

use Brain\Monkey\Functions;
use FP\SEO\Admin\Settings\GeneralTabRenderer;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;

/**
 * Test case for GeneralTabRenderer.
 */
class GeneralTabRendererTest extends TestCase {

	use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test render outputs expected HTML elements.
	 */
	public function test_render_outputs_html(): void {
		$options = Options::get_defaults();
		
		Functions\expect( 'esc_html_e' )->atLeast()->once();
		Functions\expect( 'esc_attr' )->atLeast()->once();
		Functions\expect( 'checked' )->atLeast()->once();
		Functions\expect( 'selected' )->atLeast()->once();
		Functions\expect( 'esc_html' )->atLeast()->once();
		Functions\expect( '__' )->andReturnFirstArg();

		$renderer = new GeneralTabRenderer();

		ob_start();
		$renderer->render( $options );
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'form-table', $output );
		$this->assertStringContainsString( 'enable_analyzer', $output );
		$this->assertStringContainsString( 'language', $output );
		$this->assertStringContainsString( 'admin_bar_badge', $output );
	}

	/**
	 * Test render includes all expected form fields.
	 */
	public function test_render_includes_required_fields(): void {
		$options = Options::get_defaults();

		Functions\expect( 'esc_html_e' )->atLeast()->once();
		Functions\expect( 'esc_attr' )->atLeast()->once();
		Functions\expect( 'checked' )->atLeast()->once();
		Functions\expect( 'selected' )->atLeast()->once();
		Functions\expect( 'esc_html' )->atLeast()->once();
		Functions\expect( '__' )->andReturnFirstArg();

		$renderer = new GeneralTabRenderer();

		ob_start();
		$renderer->render( $options );
		$output = ob_get_clean();

		// Check for key form elements.
		$this->assertStringContainsString( 'checkbox', $output );
		$this->assertStringContainsString( 'select', $output );
		$this->assertStringContainsString( 'fp_seo_perf_options', $output );
	}
}