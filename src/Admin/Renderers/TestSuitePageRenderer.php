<?php
/**
 * Renderer for Test Suite Page
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use function esc_html_e;

/**
 * Renders the Test Suite Page HTML
 */
class TestSuitePageRenderer {

	/**
	 * Render the test suite page
	 *
	 * @return void
	 */
	public function render(): void {
		$this->render_header();
		$this->render_test_card();
		$this->render_results_container();
		$this->render_footer();
	}

	/**
	 * Render page header
	 *
	 * @return void
	 */
	private function render_header(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FP SEO Manager - Test Suite', 'fp-seo-performance' ); ?></h1>
		<?php
	}

	/**
	 * Render test execution card
	 *
	 * @return void
	 */
	private function render_test_card(): void {
		?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2><?php esc_html_e( 'Esegui Test Automatici', 'fp-seo-performance' ); ?></h2>
			<p><?php esc_html_e( 'Questa pagina esegue una serie di test automatici per verificare che tutte le funzionalità del plugin siano operative.', 'fp-seo-performance' ); ?></p>
			
			<p>
				<button type="button" id="fp-seo-run-tests" class="button button-primary button-large">
					<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Esegui Test', 'fp-seo-performance' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render test results container
	 *
	 * @return void
	 */
	private function render_results_container(): void {
		?>
		<div id="fp-seo-test-results" style="margin-top: 30px; display: none;">
			<h2><?php esc_html_e( 'Risultati Test', 'fp-seo-performance' ); ?></h2>
			<div id="fp-seo-test-output" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; overflow: auto; max-height: 600px;">
				<div class="fp-seo-test-loading">
					<span class="spinner is-active" style="float: none; margin-right: 10px;"></span>
					<?php esc_html_e( 'Esecuzione test in corso...', 'fp-seo-performance' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render page footer
	 *
	 * @return void
	 */
	private function render_footer(): void {
		?>
		</div>
		<?php
	}
}

