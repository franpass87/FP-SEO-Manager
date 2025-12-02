<?php
/**
 * Manages styles for the Test Suite page.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the Test Suite page.
 */
class TestSuiteStylesManager {
	private const PAGE_SLUG = 'fp-seo-test-suite';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_head', array( $this, 'inject_styles' ) );
	}

	/**
	 * Inject styles in admin head.
	 *
	 * @return void
	 */
	public function inject_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'fp-seo-performance_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}
		
		$this->render_styles();
	}

	/**
	 * Render all styles.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style>
		.fp-seo-test-loading {
			color: #2563eb;
			font-size: 14px;
		}
		#fp-seo-test-output div {
			margin: 2px 0;
		}
		</style>
		<?php
	}
}


