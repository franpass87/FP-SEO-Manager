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

use FP\SEO\Utils\Options;

/**
 * Pagina admin per eseguire la test suite del plugin.
 */
class TestSuitePage {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_test_page' ) );
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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FP SEO Manager - Test Suite', 'fp-seo-performance' ); ?></h1>
			
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

			<div id="fp-seo-test-results" style="margin-top: 30px; display: none;">
				<h2><?php esc_html_e( 'Risultati Test', 'fp-seo-performance' ); ?></h2>
				<div id="fp-seo-test-output" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; overflow: auto; max-height: 600px;">
					<div class="fp-seo-test-loading">
						<span class="spinner is-active" style="float: none; margin-right: 10px;"></span>
						<?php esc_html_e( 'Esecuzione test in corso...', 'fp-seo-performance' ); ?>
					</div>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#fp-seo-run-tests').on('click', function() {
				var $button = $(this);
				var $results = $('#fp-seo-test-results');
				var $output = $('#fp-seo-test-output');
				
				// Disable button
				$button.prop('disabled', true).text('<?php esc_html_e( 'Test in corso...', 'fp-seo-performance' ); ?>');
				
				// Show results area
				$results.slideDown();
				$output.html('<div class="fp-seo-test-loading"><span class="spinner is-active" style="float: none; margin-right: 10px;"></span> <?php esc_html_e( 'Esecuzione test in corso...', 'fp-seo-performance' ); ?></div>');
				
				// Execute tests via AJAX
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_run_tests',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_run_tests' ) ); ?>'
					},
					success: function(response) {
						if (response.success && response.data.html) {
							$output.html(response.data.html);
						} else {
							$output.html('<div style="color: #dc2626;">❌ Errore durante l\'esecuzione dei test.</div>');
						}
						$button.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span> <?php esc_html_e( 'Esegui Test', 'fp-seo-performance' ); ?>');
					},
					error: function(xhr, status, error) {
						$output.html('<div style="color: #dc2626;">❌ Errore AJAX: ' + error + '</div>');
						$button.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span> <?php esc_html_e( 'Esegui Test', 'fp-seo-performance' ); ?>');
					}
				});
			});
		});
		</script>

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

