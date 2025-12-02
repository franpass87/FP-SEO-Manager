<?php
/**
 * Manages CSS styles for the Schema admin page.
 *
 * @package FP\SEO\Schema\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Styles;

/**
 * Manages CSS styles for the Schema admin page.
 */
class SchemaPageStylesManager {
	/**
	 * Get all styles for the Schema page.
	 *
	 * @return string CSS styles.
	 */
	public function get_styles(): string {
		$styles = $this->get_container_styles();
		$styles .= $this->get_banner_styles();
		$styles .= $this->get_info_box_styles();
		$styles .= $this->get_stats_styles();
		$styles .= $this->get_generator_styles();
		$styles .= $this->get_form_styles();
		$styles .= $this->get_examples_styles();
		$styles .= $this->get_preview_styles();
		$styles .= $this->get_animations();

		return $styles;
	}

	/**
	 * Get container styles.
	 *
	 * @return string CSS.
	 */
	private function get_container_styles(): string {
		return '
		.fp-seo-schema-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-schema-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		.fp-seo-schema-dashboard {
			max-width: 1200px;
		}
		';
	}

	/**
	 * Get banner styles.
	 *
	 * @return string CSS.
	 */
	private function get_banner_styles(): string {
		return '
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
			padding: 8px 0;
			font-size: 14px;
			opacity: 0.9;
		}
		';
	}

	/**
	 * Get info box styles.
	 *
	 * @return string CSS.
	 */
	private function get_info_box_styles(): string {
		return '
		.fp-seo-info-box {
			background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
			border-left: 4px solid #0ea5e9;
			padding: 24px;
			margin-bottom: 24px;
			border-radius: 12px;
			display: flex;
			gap: 20px;
		}

		.fp-seo-info-icon {
			font-size: 36px;
			line-height: 1;
		}

		.fp-seo-info-content h3 {
			margin: 0 0 12px;
			font-size: 18px;
			color: #075985;
		}

		.fp-seo-info-content p {
			margin: 0 0 12px;
			color: #0c4a6e;
			font-size: 14px;
		}

		.fp-seo-info-content ul {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-info-content ul li {
			padding: 4px 0;
			color: #0c4a6e;
			font-size: 14px;
		}
		';
	}

	/**
	 * Get stats styles.
	 *
	 * @return string CSS.
	 */
	private function get_stats_styles(): string {
		return '
		.fp-seo-schema-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
			gap: 20px;
			margin: 24px 0 32px;
		}
		
		.fp-seo-stat-card {
			background: white;
			padding: 24px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			transition: all 0.3s ease;
		}

		.fp-seo-stat-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 8px 12px rgba(0,0,0,0.1);
		}

		.fp-seo-stat-card-highlight {
			border-color: #2563eb;
			background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
		}
		
		.fp-seo-stat-icon {
			font-size: 32px;
			margin-bottom: 12px;
		}
		
		.fp-seo-stat-card h3 {
			margin: 8px 0;
			color: #374151;
			font-size: 15px;
			font-weight: 600;
		}

		.fp-seo-stat-desc {
			margin: 8px 0 12px;
			color: #6b7280;
			font-size: 13px;
		}
		
		.fp-seo-stat-number {
			font-size: 42px;
			font-weight: 700;
			color: #2563eb;
			line-height: 1;
			display: block;
		}

		.fp-seo-stat-card .button {
			margin-top: 12px;
			width: 100%;
			justify-content: center;
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}
		';
	}

	/**
	 * Get generator styles.
	 *
	 * @return string CSS.
	 */
	private function get_generator_styles(): string {
		return '
		.fp-seo-schema-generator {
			background: #fff;
			padding: 32px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			margin: 24px 0;
		}

		.fp-seo-generator-header {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e5e7eb;
		}

		.fp-seo-generator-header h2 {
			margin: 0 0 8px;
			font-size: 22px;
			color: #1f2937;
		}

		.fp-seo-generator-desc {
			margin: 0;
			color: #6b7280;
			font-size: 14px;
		}
		';
	}

	/**
	 * Get form styles.
	 *
	 * @return string CSS.
	 */
	private function get_form_styles(): string {
		return '
		.fp-seo-inline-notice {
			display: none;
			margin-bottom: 20px;
			padding: 14px 18px;
			border-radius: 8px;
			font-size: 14px;
			font-weight: 600;
		}

		.fp-seo-inline-notice.is-success {
			display: block;
			background: #ecfdf5;
			color: #065f46;
			border: 1px solid #34d399;
		}

		.fp-seo-inline-notice.is-error {
			display: block;
			background: #fef2f2;
			color: #991b1b;
			border: 1px solid #fca5a5;
		}

		.fp-seo-inline-notice.is-warning {
			display: block;
			background: #fffbeb;
			color: #92400e;
			border: 1px solid #fcd34d;
		}

		.fp-seo-form-group {
			margin-bottom: 28px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 15px;
			color: #374151;
		}

		.fp-seo-tooltip {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			font-size: 14px;
			opacity: 0.7;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip:hover {
			opacity: 1;
		}

		.fp-seo-field-help {
			margin: 8px 0 0;
			font-size: 13px;
			color: #6b7280;
			font-style: italic;
		}
		
		.fp-seo-form-group select,
		.fp-seo-form-group textarea {
			width: 100%;
			padding: 12px 16px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			font-size: 14px;
			transition: all 0.3s ease;
			font-family: "Courier New", monospace;
		}

		.fp-seo-form-group select:focus,
		.fp-seo-form-group textarea:focus {
			outline: none;
			border-color: #2563eb;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
		}

		.fp-seo-form-group .fp-seo-field-error {
			border-color: #dc2626 !important;
			box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
		}

		.fp-seo-form-group textarea {
			resize: vertical;
			min-height: 200px;
		}

		.fp-seo-form-actions {
			display: flex;
			gap: 12px;
			align-items: center;
		}

		.button-hero {
			font-size: 16px !important;
			padding: 14px 32px !important;
			height: auto !important;
			display: inline-flex !important;
			align-items: center !important;
			gap: 8px !important;
		}

		.button-hero.is-loading,
		.button.is-loading {
			opacity: 0.6;
			pointer-events: none;
		}

		.button-hero.is-loading .dashicons:before,
		.button.is-loading .dashicons:before {
			content: "\f463";
			animation: fp-seo-spin 1s linear infinite;
		}

		.button-hero .dashicons {
			font-size: 20px;
			width: 20px;
			height: 20px;
		}

		.button-secondary .dashicons {
			width: 18px;
			height: 18px;
			font-size: 18px;
		}
		';
	}

	/**
	 * Get examples styles.
	 *
	 * @return string CSS.
	 */
	private function get_examples_styles(): string {
		return '
		.fp-seo-examples-section {
			background: #f9fafb;
			padding: 20px;
			border-radius: 8px;
			margin: 24px 0;
			border: 1px solid #e5e7eb;
		}

		.fp-seo-examples-section h3 {
			margin: 0 0 16px;
			font-size: 16px;
			color: #374151;
		}

		.fp-seo-example-accordion {
			background: white;
			border: 1px solid #e5e7eb;
			border-radius: 6px;
			margin-bottom: 12px;
			overflow: hidden;
		}

		.fp-seo-example-accordion summary {
			padding: 12px 16px;
			cursor: pointer;
			font-size: 14px;
			color: #374151;
			user-select: none;
			transition: background 0.2s;
		}

		.fp-seo-example-accordion summary:hover {
			background: #f3f4f6;
		}

		.fp-seo-example-accordion[open] summary {
			border-bottom: 1px solid #e5e7eb;
			background: #f9fafb;
		}

		.fp-seo-code-example {
			margin: 0;
			padding: 16px;
			background: #1f2937;
			color: #f3f4f6;
			font-size: 12px;
			line-height: 1.6;
			overflow-x: auto;
			font-family: "Courier New", monospace;
		}
		';
	}

	/**
	 * Get preview styles.
	 *
	 * @return string CSS.
	 */
	private function get_preview_styles(): string {
		return '
		.fp-seo-schema-preview {
			background: #f9fafb;
			padding: 24px;
			border-radius: 12px;
			border: 2px solid #e5e7eb;
			margin-top: 24px;
		}

		.fp-seo-schema-preview h3 {
			margin: 0 0 16px;
			font-size: 18px;
			color: #374151;
		}
		
		.fp-seo-schema-preview pre {
			background: #1f2937;
			color: #f3f4f6;
			padding: 20px;
			border-radius: 8px;
			overflow-x: auto;
			font-size: 13px;
			line-height: 1.6;
			margin: 0;
			font-family: "Courier New", monospace;
		}
		';
	}

	/**
	 * Get animations.
	 *
	 * @return string CSS.
	 */
	private function get_animations(): string {
		return '
		@keyframes fp-seo-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		';
	}
}
