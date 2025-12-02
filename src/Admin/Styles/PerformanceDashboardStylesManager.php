<?php
/**
 * Manages styles for the Performance Dashboard page.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the Performance Dashboard page.
 */
class PerformanceDashboardStylesManager {
	private const PAGE_SLUG = 'fp-seo-performance-dashboard';

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
		<?php $this->render_dashboard_styles(); ?>
		<?php $this->render_card_styles(); ?>
		<?php $this->render_score_styles(); ?>
		<?php $this->render_metrics_styles(); ?>
		<?php $this->render_recommendations_styles(); ?>
		<?php $this->render_loading_styles(); ?>
		<?php $this->render_common_styles(); ?>
		</style>
		<?php
	}

	/**
	 * Render dashboard grid styles.
	 *
	 * @return void
	 */
	private function render_dashboard_styles(): void {
		?>
		.fp-seo-dashboard-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 20px;
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
		.fp-seo-card {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			padding: 20px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}

		.fp-seo-card h2 {
			margin-top: 0;
			margin-bottom: 15px;
			color: #23282d;
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
		.health-score {
			display: flex;
			align-items: center;
			gap: 20px;
			margin-bottom: 20px;
		}

		.score-circle {
			width: 80px;
			height: 80px;
			border-radius: 50%;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			color: white;
			font-weight: bold;
		}

		.score-circle.score-excellent { background: #46b450; }
		.score-circle.score-good { background: #00a0d2; }
		.score-circle.score-warning { background: #ffb900; }
		.score-circle.score-critical { background: #dc3232; }

		.score-number {
			font-size: 24px;
			line-height: 1;
		}

		.score-label {
			font-size: 12px;
			opacity: 0.9;
		}

		.status-badge {
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: bold;
			text-transform: uppercase;
		}

		.status-badge.status-excellent { background: #46b450; color: white; }
		.status-badge.status-good { background: #00a0d2; color: white; }
		.status-badge.status-warning { background: #ffb900; color: white; }
		.status-badge.status-critical { background: #dc3232; color: white; }
		<?php
	}

	/**
	 * Render metrics styles.
	 *
	 * @return void
	 */
	private function render_metrics_styles(): void {
		?>
		.metrics-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 15px;
		}

		.metric-item {
			display: flex;
			flex-direction: column;
			gap: 5px;
		}

		.metric-label {
			font-size: 12px;
			color: #666;
		}

		.metric-value {
			font-size: 18px;
			font-weight: bold;
			color: #23282d;
		}

		.db-stats, .asset-stats, .cache-stats {
			display: flex;
			flex-direction: column;
			gap: 10px;
			margin-bottom: 15px;
		}

		.db-stat, .asset-stat, .cache-stat {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.stat-label {
			font-size: 14px;
			color: #666;
		}

		.stat-value {
			font-weight: bold;
		}

		.stat-value.status-good { color: #46b450; }
		.stat-value.status-bad { color: #dc3232; }
		<?php
	}

	/**
	 * Render recommendations styles.
	 *
	 * @return void
	 */
	private function render_recommendations_styles(): void {
		?>
		.recommendations-list {
			list-style: none;
			padding: 0;
			margin: 0;
		}

		.recommendation-item {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px 0;
			border-bottom: 1px solid #eee;
		}

		.recommendation-item:last-child {
			border-bottom: none;
		}

		.recommendation-icon {
			font-size: 16px;
		}

		.recommendation-text {
			font-size: 14px;
			color: #666;
		}

		.no-recommendations {
			color: #46b450;
			font-style: italic;
		}
		<?php
	}

	/**
	 * Render loading overlay styles.
	 *
	 * @return void
	 */
	private function render_loading_styles(): void {
		?>
		.fp-seo-loading-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0,0,0,0.5);
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			z-index: 9999;
		}

		.fp-seo-loading-spinner {
			width: 40px;
			height: 40px;
			border: 4px solid #f3f3f3;
			border-top: 4px solid #0073aa;
			border-radius: 50%;
			animation: spin 1s linear infinite;
			margin-bottom: 20px;
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		<?php
	}

	/**
	 * Render common styles.
	 *
	 * @return void
	 */
	private function render_common_styles(): void {
		?>
		.fp-seo-dashboard {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-dashboard > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 6px 0;
			font-size: 14px;
			opacity: 0.9;
		}

		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 4px;
			cursor: help;
			opacity: 0.7;
			font-size: 12px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		.metric-icon {
			font-size: 20px;
			margin-right: 8px;
		}

		.metric-value.metric-good {
			color: #059669;
		}

		.metric-value.metric-warn {
			color: #f59e0b;
		}
		<?php
	}
}

