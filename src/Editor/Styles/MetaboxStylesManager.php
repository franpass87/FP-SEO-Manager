<?php
/**
 * Manages inline CSS styles for the metabox.
 *
 * @package FP\SEO\Editor\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Styles;

use FP\SEO\Editor\Metabox;
use function get_current_screen;
use function in_array;

/**
 * Manages inline CSS styles for the metabox.
 */
class MetaboxStylesManager {
	/**
	 * @var Metabox
	 */
	private $metabox;

	/**
	 * Constructor.
	 *
	 * @param Metabox $metabox Metabox instance.
	 */
	public function __construct( Metabox $metabox ) {
		$this->metabox = $metabox;
	}

	/**
	 * Inject inline styles in admin head.
	 *
	 * @return void
	 */
	public function inject(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->metabox->get_supported_post_types(), true ) ) {
			return;
		}

		$this->render_styles();
	}

	/**
	 * Render all inline styles.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style id="fp-seo-metabox-modern-ui">
		<?php
		$this->render_screen_reader_styles();
		$this->render_slug_hiding_styles();
		$this->render_metabox_styles();
		$this->render_score_styles();
		$this->render_indicator_styles();
		$this->render_tooltip_styles();
		$this->render_summary_styles();
		$this->render_help_banner_styles();
		$this->render_help_toggle_styles();
		$this->render_check_help_styles();
		$this->render_recommendations_styles();
		$this->render_section_styles();
		$this->render_analysis_styles();
		$this->render_responsive_styles();
		?>
		</style>
		<?php
	}

	/**
	 * Render screen reader only styles.
	 *
	 * @return void
	 */
	private function render_screen_reader_styles(): void {
		?>
		/* Screen Reader Only Text for Accessibility */
		.screen-reader-text {
			border: 0;
			clip: rect(1px, 1px, 1px, 1px);
			clip-path: inset(50%);
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute;
			width: 1px;
			word-wrap: normal !important;
		}
		<?php
	}

	/**
	 * Render styles to hide native WordPress slug UI.
	 *
	 * @return void
	 */
	private function render_slug_hiding_styles(): void {
		?>
		/* Hide native WordPress slug UI to avoid duplication with FP SEO slug field */
		#slugdiv,
		#slugdiv .inside,
		#edit-slug-box,
		#editable-post-name,
		#editable-post-name-full,
		#post-name,
		#permalink,
		.edit-slug,
		.edit-post-post-link,
		.components-panel__body[data-editor-panel-id="post-link"],
		.components-panel__body[data-panel-id="post-link"],
		.editor-post-url,
		.editor-post-url .components-panel__body,
		.editor-post-permalink,
		.editor-document-permalink-panel {
			display: none !important;
		}
		<?php
	}

	/**
	 * Render metabox container styles.
	 *
	 * @return void
	 */
	private function render_metabox_styles(): void {
		?>
		#fp-seo-performance-metabox.postbox,
		#fp-seo-geo-metabox.postbox {
			border: 1px solid #e5e7eb !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
			border-radius: 8px !important;
		}
		
		#fp-seo-performance-metabox .postbox-header {
			background: linear-gradient(135deg, var(--fp-seo-primary) 0%, var(--fp-seo-primary-dark) 100%) !important;
			border-bottom: none !important;
		}
		
		#fp-seo-performance-metabox .postbox-header h2 {
			color: #fff !important;
			font-weight: 600 !important;
		}
		
		#fp-seo-performance-metabox .postbox-header .handle-actions button {
			filter: brightness(0) invert(1) !important;
		}
		<?php
	}

	/**
	 * Render score display styles.
	 *
	 * @return void
	 */
	private function render_score_styles(): void {
		?>
		.fp-seo-performance-metabox__score {
			display: flex !important;
			align-items: center !important;
			justify-content: space-between !important;
			gap: 16px !important;
			border-radius: 8px !important;
			padding: 24px !important;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
			border: none !important;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;
			position: relative !important;
			overflow: hidden !important;
			margin-bottom: 16px !important;
		}
		
		.fp-seo-performance-metabox__score[data-status="green"] {
			background: linear-gradient(135deg, var(--fp-seo-success) 0%, var(--fp-seo-success-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status="yellow"] {
			background: linear-gradient(135deg, var(--fp-seo-warning) 0%, var(--fp-seo-warning-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status="red"] {
			background: linear-gradient(135deg, var(--fp-seo-danger) 0%, var(--fp-seo-danger-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score-label {
			font-size: 14px !important;
			font-weight: 600 !important;
			color: rgba(255,255,255,0.9) !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
		}
		
		.fp-seo-performance-metabox__score-value {
			font-size: 48px !important;
			font-weight: 700 !important;
			color: #fff !important;
			line-height: 1 !important;
			text-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
		}
		<?php
	}

	/**
	 * Render indicator styles.
	 *
	 * @return void
	 */
	private function render_indicator_styles(): void {
		?>
		.fp-seo-performance-metabox__indicator-list {
			display: grid !important;
			grid-template-columns: repeat(2, 1fr) !important;
			gap: 8px !important;
			margin: 0 !important;
			padding: 0 !important;
			list-style: none !important;
		}
		
		.fp-seo-performance-indicator {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			padding: 10px 12px !important;
			border-radius: 8px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-indicator::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 3px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-indicator:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 4px 0 rgba(0,0,0,0.08) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-indicator--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-indicator--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-indicator--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-indicator__label {
			font-size: 12px !important;
			font-weight: 500 !important;
			color: #374151 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-indicator__icon {
			width: 8px !important;
			height: 8px !important;
			border-radius: 50% !important;
			flex-shrink: 0 !important;
			margin-left: 4px !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-indicator--fail .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-danger) !important;
			box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--warn .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-warning) !important;
			box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--pass .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-success) !important;
			box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2) !important;
		}
		
		/* Loading state */
		.fp-seo-performance-indicator--pending .fp-seo-performance-indicator__icon {
			background: #d1d5db !important;
			animation: fp-seo-pulse 1.5s ease-in-out infinite !important;
		}
		
		@keyframes fp-seo-pulse {
			0%, 100% {
				opacity: 0.4 !important;
				transform: scale(1) !important;
			}
			50% {
				opacity: 1 !important;
				transform: scale(1.2) !important;
			}
		}
		<?php
	}

	/**
	 * Render tooltip styles.
	 *
	 * @return void
	 */
	private function render_tooltip_styles(): void {
		?>
		/* Tooltip */
		.fp-seo-performance-indicator {
			position: relative !important;
		}
		
		.fp-seo-performance-indicator__tooltip {
			position: absolute !important;
			bottom: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) translateY(-8px) !important;
			padding: 8px 12px !important;
			background: #1f2937 !important;
			color: #fff !important;
			font-size: 12px !important;
			line-height: 1.4 !important;
			border-radius: 8px !important;
			white-space: nowrap !important;
			max-width: 250px !important;
			white-space: normal !important;
			pointer-events: none !important;
			opacity: 0 !important;
			visibility: hidden !important;
			transition: all 0.2s ease !important;
			z-index: 1000 !important;
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3) !important;
		}
		
		.fp-seo-performance-indicator__tooltip::after {
			content: '' !important;
			position: absolute !important;
			top: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) !important;
			border: 5px solid transparent !important;
			border-top-color: #1f2937 !important;
		}
		
		.fp-seo-performance-indicator:hover .fp-seo-performance-indicator__tooltip {
			opacity: 1 !important;
			visibility: visible !important;
			transform: translateX(-50%) translateY(-4px) !important;
		}
		<?php
	}

	/**
	 * Render summary badge styles.
	 *
	 * @return void
	 */
	private function render_summary_styles(): void {
		?>
		/* Summary badges */
		.fp-seo-performance-summary {
			display: flex !important;
			gap: 8px !important;
			margin-bottom: 12px !important;
			padding: 12px !important;
			background: #f9fafb !important;
			border-radius: 8px !important;
			border: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-performance-summary__badge {
			display: inline-flex !important;
			align-items: center !important;
			gap: 6px !important;
			padding: 6px 10px !important;
			border-radius: 8px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-performance-summary__badge--fail {
			background: #fef2f2 !important;
			color: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-summary__badge--warn {
			background: #fffbeb !important;
			color: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-summary__badge--pass {
			background: #f0fdf4 !important;
			color: var(--fp-seo-success) !important;
		}
		<?php
	}

	/**
	 * Render help banner styles.
	 *
	 * @return void
	 */
	private function render_help_banner_styles(): void {
		?>
		/* Help Banner */
		.fp-seo-metabox-help-banner {
			background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
			border-left: 4px solid #3b82f6;
			padding: 16px 20px;
			margin-bottom: 20px;
			border-radius: 8px;
			display: flex;
			gap: 16px;
			align-items: flex-start;
			position: relative;
			animation: slideDown 0.4s ease;
		}

		@keyframes slideDown {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.fp-seo-metabox-help-banner__icon {
			font-size: 24px;
			line-height: 1;
			flex-shrink: 0;
		}

		.fp-seo-metabox-help-banner__content {
			flex: 1;
		}

		.fp-seo-metabox-help-banner__title {
			margin: 0 0 8px;
			font-size: 14px;
			font-weight: 600;
			color: #1e40af;
		}

		.fp-seo-metabox-help-banner__text {
			margin: 0 0 12px;
			font-size: 13px;
			color: #1e3a8a;
			line-height: 1.5;
		}

		.fp-seo-metabox-help-banner__legend {
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
		}

		.fp-seo-legend-item {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
			color: #1e3a8a;
			font-weight: 500;
		}

		.fp-seo-legend-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			flex-shrink: 0;
		}

		.fp-seo-legend-item--pass .fp-seo-legend-dot {
			background: var(--fp-seo-success);
		}

		.fp-seo-legend-item--warn .fp-seo-legend-dot {
			background: var(--fp-seo-warning);
		}

		.fp-seo-legend-item--fail .fp-seo-legend-dot {
			background: var(--fp-seo-danger);
		}

		.fp-seo-metabox-help-banner__close {
			position: absolute;
			top: 8px;
			right: 8px;
			background: rgba(255, 255, 255, 0.7);
			border: none;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			font-size: 18px;
			line-height: 1;
			color: #1e40af;
			transition: all 0.2s;
		}

		.fp-seo-metabox-help-banner__close:hover {
			background: rgba(255, 255, 255, 1);
			transform: scale(1.1);
		}

		.fp-seo-metabox-help-banner.hidden {
			display: none;
		}
		<?php
	}

	/**
	 * Render help toggle styles.
	 *
	 * @return void
	 */
	private function render_help_toggle_styles(): void {
		?>
		/* Tooltip */
		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			opacity: 0.7;
			font-size: 14px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		/* Help Toggle Button */
		.fp-seo-help-toggle {
			background: transparent;
			border: 1px solid #e5e7eb;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			color: #6b7280;
			transition: all 0.2s;
			flex-shrink: 0;
			padding: 0;
		}

		.fp-seo-help-toggle:hover {
			background: #f3f4f6;
			border-color: #3b82f6;
			color: #3b82f6;
		}

		.fp-seo-help-toggle .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}
		<?php
	}

	/**
	 * Render check help content styles.
	 *
	 * @return void
	 */
	private function render_check_help_styles(): void {
		?>
		/* Check Help Content */
		.fp-seo-check-help {
			background: #f0f9ff;
			border: 1px solid #bfdbfe;
			border-radius: 6px;
			padding: 16px;
			margin-top: 12px;
			animation: expandDown 0.3s ease;
		}

		@keyframes expandDown {
			from {
				opacity: 0;
				max-height: 0;
				padding-top: 0;
				padding-bottom: 0;
			}
			to {
				opacity: 1;
				max-height: 500px;
				padding-top: 16px;
				padding-bottom: 16px;
			}
		}

		.fp-seo-check-help__section {
			margin-bottom: 16px;
		}

		.fp-seo-check-help__section:last-child {
			margin-bottom: 0;
		}

		.fp-seo-check-help__title {
			margin: 0 0 8px;
			font-size: 13px;
			font-weight: 600;
			color: #1e40af;
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.fp-seo-check-help__title .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}

		.fp-seo-check-help__text {
			margin: 0;
			font-size: 12px;
			color: #1e3a8a;
			line-height: 1.6;
		}

		.fp-seo-check-help__example {
			background: #fff;
			border: 1px solid #bfdbfe;
			border-radius: 4px;
			padding: 12px;
			margin-top: 12px;
		}

		.fp-seo-check-help__example strong {
			display: block;
			margin-bottom: 6px;
			font-size: 12px;
			color: #1e40af;
		}

		.fp-seo-check-help__example code {
			display: block;
			background: #f8fafc;
			padding: 8px;
			border-radius: 4px;
			font-size: 11px;
			color: #1e3a8a;
			font-family: 'Courier New', monospace;
			word-wrap: break-word;
		}
		<?php
	}

	/**
	 * Render recommendations styles.
	 *
	 * @return void
	 */
	private function render_recommendations_styles(): void {
		?>
		.fp-seo-performance-metabox__recommendations {
			margin-top: 16px !important;
		}
		
		.fp-seo-performance-recommendations-header {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin-bottom: 10px !important;
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #374151 !important;
		}
		
		.fp-seo-performance-recommendations-header__badge {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-width: 20px !important;
			height: 20px !important;
			padding: 0 6px !important;
			background: var(--fp-seo-primary) !important;
			color: #fff !important;
			font-size: 11px !important;
			font-weight: 600 !important;
			border-radius: 12px !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li {
			font-size: 12px !important;
			line-height: 1.5 !important;
			padding: 8px 12px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 4px !important;
			border-left: 3px solid #2563eb !important;
			color: #374151 !important;
			margin-bottom: 6px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:last-child {
			margin-bottom: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list--empty {
			padding: 12px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 13px !important;
			font-weight: 500 !important;
		}
		<?php
	}

	/**
	 * Render section styles.
	 *
	 * @return void
	 */
	private function render_section_styles(): void {
		?>
		.fp-seo-performance-metabox__section-heading {
			margin: 16px 0 12px !important;
			font-size: 15px !important;
			font-weight: 600 !important;
			color: #111827 !important;
		}
		
		/* Unified Section Styles */
		.fp-seo-performance-metabox__section {
			margin-bottom: 24px !important;
			padding: 20px !important;
			background: #ffffff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-metabox__section:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			display: flex !important;
			align-items: center !important;
			gap: 10px !important;
			margin: 0 0 16px 0 !important;
			padding: 0 0 12px 0 !important;
			font-size: 16px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			border-bottom: 2px solid #e5e7eb !important;
		}
		
		.fp-seo-section-icon {
			font-size: 20px !important;
			line-height: 1 !important;
		}
		
		.fp-seo-performance-metabox__section-content {
			/* Reset any inherited styles */
		}
		<?php
	}

	/**
	 * Render analysis styles.
	 *
	 * @return void
	 */
	private function render_analysis_styles(): void {
		?>
		/* Unified Analysis Styles */
		.fp-seo-performance-metabox__unified-analysis {
			margin-bottom: 20px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			display: flex !important;
			flex-direction: column !important;
			gap: 8px !important;
		}
		
		.fp-seo-performance-analysis-item {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 12px 16px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-analysis-item::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 4px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-analysis-item:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 8px 0 rgba(0,0,0,0.1) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-analysis-item--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-analysis-item--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-analysis-item--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-analysis-item__header {
			display: flex !important;
			align-items: center !important;
			gap: 12px !important;
			margin-bottom: 4px !important;
		}
		
		.fp-seo-performance-analysis-item__icon {
			font-size: 16px !important;
			line-height: 1 !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item__title {
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-analysis-item__status {
			font-size: 11px !important;
			font-weight: 500 !important;
			padding: 2px 8px !important;
			border-radius: 12px !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item--pass .fp-seo-performance-analysis-item__status {
			background: #d1fae5 !important;
			color: #065f46 !important;
		}
		
		.fp-seo-performance-analysis-item--warn .fp-seo-performance-analysis-item__status {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		
		.fp-seo-performance-analysis-item--fail .fp-seo-performance-analysis-item__status {
			background: #fee2e2 !important;
			color: #991b1b !important;
		}
		
		.fp-seo-performance-analysis-item__description {
			font-size: 12px !important;
			color: #6b7280 !important;
			line-height: 1.5 !important;
			margin-left: 28px !important;
			margin-top: 4px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list--empty {
			padding: 20px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 14px !important;
			font-weight: 500 !important;
		}
		<?php
	}

	/**
	 * Render responsive styles.
	 *
	 * @return void
	 */
	private function render_responsive_styles(): void {
		?>
		/* Responsive: 1 colonna su schermi piccoli */
		@media (max-width: 782px) {
			.fp-seo-performance-metabox__indicator-list {
				grid-template-columns: 1fr !important;
			}
		}
		<?php
	}
}


