<?php
/**
 * Test Suite AJAX Handler
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

/**
 * Gestisce le richieste AJAX per la test suite.
 */
class TestSuiteAjax {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_fp_seo_run_tests', array( $this, 'handle_run_tests' ) );
	}

	/**
	 * Handle test execution request.
	 */
	public function handle_run_tests(): void {
		// Verify nonce
		check_ajax_referer( 'fp_seo_run_tests', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permessi insufficienti.', 'fp-seo-performance' ) ) );
			return;
		}

		// Set DOING_AJAX early
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		// Capture output
		ob_start();
		
		// Include test file
		$test_file = dirname( dirname( dirname( __FILE__ ) ) ) . '/test-plugin.php';
		
		if ( ! file_exists( $test_file ) ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => __( 'File test-plugin.php non trovato.', 'fp-seo-performance' ) ) );
			return;
		}

		try {
			// Execute tests (WordPress è già caricato)
			// Il file test-plugin.php ora controlla DOING_AJAX
			include $test_file;
			
			$output = ob_get_clean();

			// Return HTML output
			wp_send_json_success( array(
				'html' => $output,
			) );
		} catch ( \Exception $e ) {
			ob_end_clean();
			wp_send_json_error( array(
				'message' => __( 'Errore durante esecuzione test: ', 'fp-seo-performance' ) . $e->getMessage(),
			) );
		}
	}
}

