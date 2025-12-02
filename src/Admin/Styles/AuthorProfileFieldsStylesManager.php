<?php
/**
 * Manages CSS styles for Author Profile Fields.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

/**
 * Manages CSS styles for Author Profile Fields.
 */
class AuthorProfileFieldsStylesManager {
	/**
	 * Get all inline styles.
	 *
	 * @return string CSS styles.
	 */
	public function get_styles(): string {
		return $this->get_container_styles() .
			   $this->get_form_styles() .
			   $this->get_tag_input_styles() .
			   $this->get_expertise_tags_styles() .
			   $this->get_preview_styles();
	}

	/**
	 * Get container styles.
	 *
	 * @return string CSS.
	 */
	private function get_container_styles(): string {
		return '
		.fp-seo-author-authority {
			background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
			padding: 20px;
			border-radius: 8px;
			border-left: 4px solid #0284c7;
			margin-top: 20px;
		}
		.fp-seo-author-authority h3 {
			margin-top: 0;
			color: #0c4a6e;
		}
		.fp-seo-author-authority .form-table th {
			width: 200px;
		}
		.fp-seo-author-authority .description {
			color: #64748b;
			font-size: 13px;
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
		.fp-seo-author-authority .form-table input[type="text"],
		.fp-seo-author-authority .form-table input[type="url"],
		.fp-seo-author-authority .form-table textarea {
			width: 100%;
			max-width: 500px;
		}
		';
	}

	/**
	 * Get tag input styles.
	 *
	 * @return string CSS.
	 */
	private function get_tag_input_styles(): string {
		return '
		.fp-seo-tag-input {
			width: 100%;
			max-width: 500px;
		}
		';
	}

	/**
	 * Get expertise tags styles.
	 *
	 * @return string CSS.
	 */
	private function get_expertise_tags_styles(): string {
		return '
		.fp-seo-expertise-tags {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 10px;
		}
		.fp-seo-expertise-tag {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			background: #0284c7;
			color: white;
			border-radius: 999px;
			font-size: 12px;
		}
		.fp-seo-expertise-tag button {
			background: transparent;
			border: none;
			color: white;
			cursor: pointer;
			padding: 0;
			font-size: 14px;
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
		.fp-seo-author-preview {
			margin-top: 20px;
			padding: 16px;
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 6px;
		}
		.fp-seo-author-preview h4 {
			margin-top: 0;
			color: #1e293b;
		}
		.fp-seo-author-preview-item {
			margin: 8px 0;
			padding: 8px;
			background: white;
			border-radius: 4px;
		}
		';
	}
}

