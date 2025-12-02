<?php
/**
 * Manages scripts for the Test Suite page.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use function esc_html_e;
use function esc_js;
use function get_current_screen;
use function wp_create_nonce;

/**
 * Manages scripts for the Test Suite page.
 */
class TestSuiteScriptsManager {
	private const PAGE_SLUG = 'fp-seo-test-suite';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'inject_scripts' ) );
	}

	/**
	 * Inject scripts in admin footer.
	 *
	 * @return void
	 */
	public function inject_scripts(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'fp-seo-performance_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}
		
		$this->render_scripts();
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		?>
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
		<?php
	}
}


