<?php
/**
 * Asset registration utilities.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Handles registration of plugin assets.
 */
class Assets {

	/**
	 * Hooks asset registration into admin requests.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Registers admin styles and scripts.
	 */
	public function enqueue_admin(): void {
			wp_register_style(
				'fp-seo-performance-admin',
				plugins_url( 'assets/admin/admin.css', FP_SEO_PERFORMANCE_FILE ),
				array(),
				'0.1.0'
			);

			wp_register_script(
				'fp-seo-performance-admin',
				plugins_url( 'assets/admin/admin.js', FP_SEO_PERFORMANCE_FILE ),
				array( 'jquery' ),
				'0.1.0',
				true
			);

		wp_register_script(
			'fp-seo-performance-editor',
			plugins_url( 'assets/admin/editor-metabox.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			'0.1.0',
			true
		);

		wp_register_script(
			'fp-seo-performance-bulk',
			plugins_url( 'assets/admin/bulk-auditor.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			'0.1.0',
			true
		);
	}
}
