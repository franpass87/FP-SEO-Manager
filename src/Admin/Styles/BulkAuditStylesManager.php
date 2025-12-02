<?php
/**
 * Manages styles for the Bulk Audit page.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use FP\SEO\Admin\BulkAuditPage;
use function get_current_screen;

/**
 * Manages styles for the Bulk Audit page.
 */
class BulkAuditStylesManager {
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
		
		if ( ! $screen || 'fp-seo-performance_page_' . BulkAuditPage::PAGE_SLUG !== $screen->id ) {
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
		<style id="fp-seo-bulk-modern-ui">
		<?php $this->render_wrap_styles(); ?>
		<?php $this->render_filters_styles(); ?>
		<?php $this->render_toolbar_styles(); ?>
		<?php $this->render_table_styles(); ?>
		</style>
		<?php
	}

	/**
	 * Render wrap styles.
	 *
	 * @return void
	 */
	private function render_wrap_styles(): void {
		?>
		:root {
			--fp-seo-primary: #2563eb;
			--fp-seo-gray-50: #f9fafb;
			--fp-seo-gray-200: #e5e7eb;
		}
		
		.wrap.fp-seo-performance-bulk {
			background: var(--fp-seo-gray-50) !important;
			margin-left: -20px !important;
			margin-right: -20px !important;
			padding: 32px 40px 40px !important;
		}
		
		.fp-seo-performance-bulk > h1 {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text !important;
			font-size: 32px !important;
			font-weight: 700 !important;
			margin-bottom: 16px !important;
		}
		<?php
	}

	/**
	 * Render filters styles.
	 *
	 * @return void
	 */
	private function render_filters_styles(): void {
		?>
		.fp-seo-performance-bulk__filters {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 20px !important;
			margin-bottom: 24px !important;
			display: flex !important;
			gap: 16px !important;
			align-items: flex-end !important;
		}
		
		.fp-seo-performance-bulk__filters label {
			display: flex !important;
			flex-direction: column !important;
			gap: 8px !important;
		}
		
		.fp-seo-performance-bulk__filters select {
			border: 1px solid #d1d5db !important;
			border-radius: 6px !important;
			padding: 8px 12px !important;
			min-width: 200px !important;
		}
		<?php
	}

	/**
	 * Render toolbar styles.
	 *
	 * @return void
	 */
	private function render_toolbar_styles(): void {
		?>
		.fp-seo-performance-bulk__toolbar {
			display: flex !important;
			gap: 12px !important;
			margin-bottom: 20px !important;
		}
		
		.fp-seo-performance-bulk__messages {
			margin-bottom: 20px !important;
			padding: 12px 16px !important;
			border-radius: 6px !important;
		}
		
		.fp-seo-performance-bulk__messages.is-success {
			background: #ecfdf5 !important;
			color: #065f46 !important;
			border: 1px solid #34d399 !important;
		}
		
		.fp-seo-performance-bulk__messages.is-error {
			background: #fef2f2 !important;
			color: #991b1b !important;
			border: 1px solid #fca5a5 !important;
		}
		<?php
	}

	/**
	 * Render table styles.
	 *
	 * @return void
	 */
	private function render_table_styles(): void {
		?>
		.fp-seo-performance-bulk table.widefat {
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-bulk table.widefat thead {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
		}
		
		.fp-seo-performance-bulk table.widefat thead th {
			color: #fff !important;
			font-weight: 600 !important;
			text-transform: uppercase !important;
			font-size: 11px !important;
			letter-spacing: 0.5px !important;
			border: none !important;
			padding: 14px 10px !important;
		}
		
		.fp-seo-performance-bulk table.widefat tbody tr {
			border-bottom: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-performance-bulk table.widefat tbody tr:hover {
			background-color: #f9fafb !important;
		}
		<?php
	}
}

