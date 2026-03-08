<?php
/**
 * Test Suite Admin Page
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Renderers\TestSuitePageRenderer;
use FP\SEO\Admin\Scripts\TestSuiteScriptsManager;
use FP\SEO\Admin\Styles\TestSuiteStylesManager;
use FP\SEO\Utils\Options;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Pagina admin per eseguire la test suite del plugin.
 */
class TestSuitePage {
	/**
	 * @var TestSuiteStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var TestSuiteScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * @var TestSuitePageRenderer|null
	 */
	private $renderer;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'admin_menu', array( $this, 'add_test_page' ) );

		// Initialize and register styles and scripts managers
		$this->styles_manager = new TestSuiteStylesManager();
		$this->styles_manager->register_hooks();
		$this->scripts_manager = new TestSuiteScriptsManager();
		$this->scripts_manager->register_hooks();

		// Initialize renderer
		$this->renderer = new TestSuitePageRenderer();
	}

	/**
	 * Add test suite page to admin menu.
	 */
	public function add_test_page(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Test Suite', 'fp-seo-performance' ),
			__( 'Test Suite', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-test-suite',
			array( $this, 'render_test_page' )
		);
	}

	/**
	 * Render test suite page.
	 */
	public function render_test_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'fp-seo-performance' ) );
		}

		if ( $this->renderer ) {
			$this->renderer->render();
		}
	}
}

