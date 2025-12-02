<?php
/**
 * Manages styles for the Admin Menu pages.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the Admin Menu pages.
 */
class MenuStylesManager {
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
		
		if ( ! $screen ) {
			return;
		}
		
		// Only on FP SEO pages
		if ( strpos( $screen->id, 'fp-seo-performance' ) === false ) {
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
		<style id="fp-seo-modern-ui">
		<?php $this->render_css_variables(); ?>
		<?php $this->render_dashboard_styles(); ?>
		<?php $this->render_quick_stats_styles(); ?>
		<?php $this->render_grid_styles(); ?>
		<?php $this->render_card_styles(); ?>
		<?php $this->render_table_styles(); ?>
		<?php $this->render_score_styles(); ?>
		<?php $this->render_badge_styles(); ?>
		</style>
		<?php
	}

	/**
	 * Render CSS variables.
	 *
	 * @return void
	 */
	private function render_css_variables(): void {
		?>
		:root {
			--fp-seo-primary: #2563eb;
			--fp-seo-primary-dark: #1d4ed8;
			--fp-seo-success: #059669;
			--fp-seo-warning: #f59e0b;
			--fp-seo-danger: #dc2626;
			--fp-seo-gray-50: #f9fafb;
			--fp-seo-gray-100: #f3f4f6;
			--fp-seo-gray-200: #e5e7eb;
			--fp-seo-gray-300: #d1d5db;
			--fp-seo-gray-600: #4b5563;
			--fp-seo-gray-700: #374151;
			--fp-seo-gray-900: #111827;
			--fp-seo-shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
			--fp-seo-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
		}
		<?php
	}

	/**
	 * Render dashboard styles.
	 *
	 * @return void
	 */
	private function render_dashboard_styles(): void {
		?>
		.wrap.fp-seo-performance-dashboard {
			background: var(--fp-seo-gray-50) !important;
			margin-left: -20px !important;
			margin-right: -20px !important;
			padding: 32px 40px 40px !important;
			min-height: calc(100vh - 32px) !important;
		}
		
		.fp-seo-performance-dashboard > h1 {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text !important;
			font-size: 32px !important;
			font-weight: 700 !important;
			margin-bottom: 12px !important;
		}
		
		.fp-seo-performance-dashboard > .description {
			font-size: 16px !important;
			color: var(--fp-seo-gray-600) !important;
			margin-bottom: 28px !important;
		}
		<?php
	}

	/**
	 * Render quick stats styles.
	 *
	 * @return void
	 */
	private function render_quick_stats_styles(): void {
		?>
		.fp-seo-quick-stats {
			display: grid !important;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
			gap: 16px !important;
			margin: 20px 0 32px !important;
		}
		
		.fp-seo-quick-stat {
			background: linear-gradient(135deg, #fff 0%, var(--fp-seo-gray-50) 100%) !important;
			padding: 24px !important;
			border-radius: 8px !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			box-shadow: var(--fp-seo-shadow) !important;
			text-align: center !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-quick-stat:hover {
			transform: translateY(-4px) !important;
			box-shadow: var(--fp-seo-shadow-md) !important;
		}
		
		.fp-seo-quick-stat__icon {
			font-size: 32px !important;
			margin-bottom: 12px !important;
		}
		
		.fp-seo-quick-stat__value {
			display: block !important;
			font-size: 36px !important;
			font-weight: 700 !important;
			color: var(--fp-seo-gray-900) !important;
			line-height: 1 !important;
			margin-bottom: 8px !important;
		}
		
		.fp-seo-quick-stat__label {
			display: block !important;
			font-size: 12px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-600) !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
		}
		<?php
	}

	/**
	 * Render grid styles.
	 *
	 * @return void
	 */
	private function render_grid_styles(): void {
		?>
		.fp-seo-performance-dashboard__grid {
			display: grid !important;
			grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)) !important;
			gap: 20px !important;
			margin-bottom: 32px !important;
		}
		<?php
	}

	/**
	 * Render card styles.
	 *
	 * @return void
	 */
	private function render_card_styles(): void {
		?>
		.fp-seo-performance-dashboard__card {
			background: #fff !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			padding: 24px !important;
			box-shadow: var(--fp-seo-shadow) !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-dashboard__card:hover {
			box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important;
			transform: translateY(-2px) !important;
		}
		
		.fp-seo-performance-dashboard__card > h2 {
			font-size: 18px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-900) !important;
			margin: 0 0 16px !important;
			padding-bottom: 12px !important;
			border-bottom: 2px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-performance-dashboard__metrics {
			list-style: none !important;
			margin: 0 !important;
			padding: 0 !important;
			display: grid !important;
			gap: 10px !important;
		}
		
		.fp-seo-performance-dashboard__metrics li {
			padding: 10px 12px !important;
			background: var(--fp-seo-gray-50) !important;
			border-radius: 6px !important;
			font-size: 13px !important;
			border-left: 3px solid var(--fp-seo-primary) !important;
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
		.fp-seo-performance-dashboard table.widefat {
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			overflow: hidden !important;
			box-shadow: var(--fp-seo-shadow) !important;
			border-collapse: separate !important;
		}
		
		.fp-seo-performance-dashboard table.widefat thead {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
		}
		
		.fp-seo-performance-dashboard table.widefat thead th {
			color: #fff !important;
			font-weight: 600 !important;
			text-transform: uppercase !important;
			font-size: 11px !important;
			padding: 14px 10px !important;
			border: none !important;
		}
		
		.fp-seo-performance-dashboard table.widefat tbody tr {
			transition: all 0.2s ease !important;
			border-bottom: 1px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-performance-dashboard table.widefat tbody tr:hover {
			background-color: var(--fp-seo-gray-50) !important;
		}
		
		.fp-seo-performance-dashboard table.striped > tbody > tr:nth-child(odd) {
			background-color: transparent !important;
		}
		<?php
	}

	/**
	 * Render score styles.
	 *
	 * @return void
	 */
	private function render_score_styles(): void {
		?>
		.fp-seo-score-display {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-width: 50px !important;
			padding: 6px 12px !important;
			border-radius: 999px !important;
			font-weight: 700 !important;
			font-size: 14px !important;
			color: #fff !important;
			box-shadow: var(--fp-seo-shadow) !important;
		}
		
		.fp-seo-score-display--high {
			background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
		}
		
		.fp-seo-score-display--medium {
			background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
		}
		
		.fp-seo-score-display--low {
			background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
		}
		<?php
	}

	/**
	 * Render badge styles.
	 *
	 * @return void
	 */
	private function render_badge_styles(): void {
		?>
		.fp-seo-status-badge {
			display: inline-flex !important;
			align-items: center !important;
			gap: 6px !important;
			padding: 6px 12px !important;
			border-radius: 999px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-status-badge::before {
			content: '' !important;
			display: inline-block !important;
			width: 8px !important;
			height: 8px !important;
			border-radius: 50% !important;
		}
		
		.fp-seo-status-badge--healthy {
			background: #d1fae5 !important;
			color: #059669 !important;
		}
		
		.fp-seo-status-badge--healthy::before {
			background: #059669 !important;
			box-shadow: 0 0 0 3px rgba(5,150,105,0.2) !important;
		}
		
		.fp-seo-status-badge--needs-review {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		
		.fp-seo-status-badge--needs-review::before {
			background: #f59e0b !important;
		}
		
		.fp-seo-status-badge--critical {
			background: #fee2e2 !important;
			color: #dc2626 !important;
		}
		
		.fp-seo-status-badge--critical::before {
			background: #dc2626 !important;
		}
		
		.fp-seo-badge {
			display: inline-flex !important;
			padding: 4px 10px !important;
			border-radius: 999px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-badge--success {
			background: #d1fae5 !important;
			color: #065f46 !important;
		}
		
		.fp-seo-badge--warning {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		<?php
	}
}


