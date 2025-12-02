<?php
/**
 * Manages styles for the Keywords Metabox.
 *
 * @package FP\SEO\Keywords\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Styles;

use function get_current_screen;

/**
 * Manages styles for the Keywords Metabox.
 */
class KeywordsMetaboxStylesManager {
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
		
		if ( ! $screen || 'post' !== $screen->base ) {
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
		.fp-seo-keywords-metabox {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		
		.fp-seo-keywords-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin-bottom: 20px;
		}
		
		.fp-seo-keywords-tab {
			padding: 10px 16px;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-bottom: none;
			cursor: pointer;
			margin-right: 2px;
			border-radius: 4px 4px 0 0;
			font-size: 12px;
			font-weight: 600;
		}
		
		.fp-seo-keywords-tab.active {
			background: #fff;
			border-bottom: 1px solid #fff;
		}
		
		.fp-seo-keywords-tab-content {
			display: none;
		}
		
		.fp-seo-keywords-tab-content.active {
			display: block;
		}
		
		.fp-seo-form-group {
			margin-bottom: 20px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 13px;
		}
		
		.fp-seo-form-group input[type="text"] {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 13px;
		}
		
		.fp-seo-keywords-input-container {
			display: flex;
			gap: 8px;
			margin-bottom: 10px;
		}
		
		.fp-seo-keywords-input-container input {
			flex: 1;
		}
		
		.fp-seo-keywords-list {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-bottom: 10px;
		}
		
		.fp-seo-keyword-item {
			display: flex;
			align-items: center;
			background: #e3f2fd;
			border: 1px solid #bbdefb;
			border-radius: 16px;
			padding: 4px 8px;
			font-size: 12px;
		}
		
		.fp-seo-keyword-text {
			margin-right: 6px;
			color: #1976d2;
		}
		
		.fp-seo-remove-keyword {
			background: none;
			border: none;
			color: #666;
			cursor: pointer;
			font-size: 16px;
			line-height: 1;
			padding: 0;
			width: 16px;
			height: 16px;
		}
		
		.fp-seo-keyword-suggestions {
			margin-top: 20px;
		}
		
		.fp-seo-keyword-suggestions h4 {
			margin: 0 0 10px 0;
			font-size: 13px;
			color: #666;
		}
		
		.fp-seo-suggestions-list {
			max-height: 200px;
			overflow-y: auto;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		
		.fp-seo-suggestion-item {
			display: flex;
			align-items: center;
			padding: 8px 12px;
			border-bottom: 1px solid #f0f0f0;
		}
		
		.fp-seo-suggestion-item:last-child {
			border-bottom: none;
		}
		
		.fp-seo-suggestion-keyword {
			flex: 1;
			font-size: 12px;
			color: #333;
		}
		
		.fp-seo-suggestion-score {
			background: #0073aa;
			color: #fff;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
			margin-right: 8px;
		}
		
		.fp-seo-density-analysis {
			margin-bottom: 20px;
		}
		
		.fp-seo-density-analysis h5 {
			margin: 0 0 10px 0;
			font-size: 13px;
			color: #666;
		}
		
		.fp-seo-density-list {
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		
		.fp-seo-density-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 10px 12px;
			border-bottom: 1px solid #f0f0f0;
		}
		
		.fp-seo-density-item:last-child {
			border-bottom: none;
		}
		
		.fp-seo-density-keyword {
			font-weight: 600;
			font-size: 12px;
			color: #333;
		}
		
		.fp-seo-density-stats {
			display: flex;
			align-items: center;
			gap: 12px;
			font-size: 11px;
		}
		
		.fp-seo-density-count {
			color: #666;
		}
		
		.fp-seo-density-percentage {
			font-weight: 600;
			color: #0073aa;
		}
		
		.fp-seo-density-status {
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
			text-transform: uppercase;
		}
		
		.fp-seo-density-status--low {
			background: #ffebee;
			color: #c62828;
		}
		
		.fp-seo-density-status--good {
			background: #e8f5e8;
			color: #2e7d32;
		}
		
		.fp-seo-density-status--high {
			background: #fff3e0;
			color: #ef6c00;
		}
		
		.fp-seo-density-status--over-optimized {
			background: #ffebee;
			color: #c62828;
		}
		
		.fp-seo-no-analysis {
			text-align: center;
			padding: 20px;
			color: #666;
		}
		
		.fp-seo-keywords-actions {
			display: flex;
			gap: 8px;
			margin-top: 20px;
		}
		
		.fp-seo-keywords-actions .button {
			flex: 1;
			text-align: center;
		}
		</style>
		<?php
	}
}


