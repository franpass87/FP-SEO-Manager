<?php
/**
 * Manages styles for the Internal Link Manager metabox.
 *
 * @package FP\SEO\Links\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Styles;

use function get_current_screen;

/**
 * Manages styles for the Internal Link Manager metabox.
 */
class InternalLinkStylesManager {
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
		<?php $this->render_metabox_styles(); ?>
		<?php $this->render_stats_styles(); ?>
		<?php $this->render_suggestions_styles(); ?>
		<?php $this->render_actions_styles(); ?>
		</style>
		<?php
	}

	/**
	 * Render metabox styles.
	 *
	 * @return void
	 */
	private function render_metabox_styles(): void {
		?>
		.fp-seo-internal-links-metabox {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		<?php
	}

	/**
	 * Render stats styles.
	 *
	 * @return void
	 */
	private function render_stats_styles(): void {
		?>
		.fp-seo-links-stats {
			display: flex;
			gap: 15px;
			margin-bottom: 15px;
		}
		
		.fp-seo-link-stat {
			text-align: center;
			padding: 10px;
			background: #f8f9fa;
			border-radius: 4px;
			flex: 1;
		}
		
		.fp-seo-link-stat-number {
			display: block;
			font-size: 18px;
			font-weight: 600;
			color: #0073aa;
		}
		
		.fp-seo-link-stat-label {
			display: block;
			font-size: 11px;
			color: #666;
			text-transform: uppercase;
		}
		<?php
	}

	/**
	 * Render suggestions styles.
	 *
	 * @return void
	 */
	private function render_suggestions_styles(): void {
		?>
		.fp-seo-suggestions-list {
			max-height: 300px;
			overflow-y: auto;
		}
		
		.fp-seo-suggestion-item {
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 10px;
			margin-bottom: 8px;
			background: #fff;
		}
		
		.fp-seo-suggestion-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 5px;
		}
		
		.fp-seo-suggestion-title {
			font-weight: 600;
			font-size: 12px;
			color: #1d2129;
		}
		
		.fp-seo-suggestion-score {
			background: #0073aa;
			color: #fff;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
		}
		
		.fp-seo-suggestion-excerpt {
			font-size: 11px;
			color: #666;
			margin-bottom: 8px;
			line-height: 1.4;
		}
		
		.fp-seo-suggestion-keywords {
			margin-bottom: 8px;
		}
		
		.fp-seo-keyword-tag {
			display: inline-block;
			background: #e3f2fd;
			color: #1976d2;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			margin-right: 4px;
			margin-bottom: 2px;
		}
		
		.fp-seo-suggestion-actions {
			display: flex;
			gap: 5px;
		}
		
		.fp-seo-suggestion-actions .button {
			font-size: 11px;
			padding: 4px 8px;
			height: auto;
		}
		
		.fp-seo-no-suggestions {
			text-align: center;
			padding: 20px;
			color: #666;
		}
		<?php
	}

	/**
	 * Render actions styles.
	 *
	 * @return void
	 */
	private function render_actions_styles(): void {
		?>
		.fp-seo-links-actions {
			margin-top: 15px;
			display: flex;
			gap: 8px;
		}
		
		.fp-seo-links-actions .button {
			flex: 1;
			text-align: center;
		}
		<?php
	}
}


