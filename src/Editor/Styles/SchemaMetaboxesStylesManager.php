<?php
/**
 * Manages styles for the Schema Metaboxes (FAQ and HowTo).
 *
 * @package FP\SEO\Editor\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Styles;

use function get_current_screen;

/**
 * Manages styles for the Schema Metaboxes.
 */
class SchemaMetaboxesStylesManager {
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
		
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}
		
		if ( ! $screen->post_type || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
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
		<?php $this->render_item_styles(); ?>
		<?php $this->render_form_styles(); ?>
		<?php $this->render_tips_styles(); ?>
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
		.fp-seo-schema-metabox {
			padding: 0;
		}

		.fp-seo-schema-intro {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 16px;
			margin: -12px -12px 20px;
			border-radius: 4px 4px 0 0;
		}

		.fp-seo-schema-intro .description {
			color: white;
			margin: 0;
			line-height: 1.6;
		}

		.fp-seo-howto-header {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e5e7eb;
		}
		<?php
	}

	/**
	 * Render item styles.
	 *
	 * @return void
	 */
	private function render_item_styles(): void {
		?>
		.fp-seo-faq-item,
		.fp-seo-howto-step {
			background: #f9fafb;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			margin-bottom: 16px;
			transition: all 0.3s ease;
		}

		.fp-seo-faq-item:hover,
		.fp-seo-howto-step:hover {
			border-color: #3b82f6;
			box-shadow: 0 4px 6px rgba(59, 130, 246, 0.1);
		}

		.fp-seo-faq-item-header,
		.fp-seo-howto-step-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 12px 16px;
			background: white;
			border-bottom: 1px solid #e5e7eb;
			border-radius: 6px 6px 0 0;
		}

		.fp-seo-faq-number,
		.fp-seo-howto-number {
			display: flex;
			align-items: center;
			gap: 6px;
			font-weight: 600;
			color: #374151;
		}

		.fp-seo-faq-number .dashicons,
		.fp-seo-howto-number .dashicons {
			color: #3b82f6;
		}

		.fp-seo-howto-actions {
			display: flex;
			gap: 4px;
		}

		.fp-seo-faq-item-content,
		.fp-seo-howto-step-content {
			padding: 16px;
		}
		<?php
	}

	/**
	 * Render form styles.
	 *
	 * @return void
	 */
	private function render_form_styles(): void {
		?>
		.fp-seo-form-group {
			margin-bottom: 16px;
		}

		.fp-seo-form-group:last-child {
			margin-bottom: 0;
		}

		.fp-seo-form-group label {
			display: block;
			margin-bottom: 6px;
			color: #374151;
		}

		.fp-seo-form-group .required {
			color: #dc2626;
		}

		.fp-seo-form-group input[type="text"],
		.fp-seo-form-group input[type="url"],
		.fp-seo-form-group textarea {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			font-size: 14px;
			transition: all 0.2s ease;
		}

		.fp-seo-form-group input:focus,
		.fp-seo-form-group textarea:focus {
			border-color: #3b82f6;
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			outline: none;
		}

		.fp-seo-form-group .description {
			margin-top: 6px;
			font-size: 12px;
			color: #6b7280;
		}

		.fp-seo-char-count {
			font-weight: 600;
			color: #3b82f6;
		}

		.fp-seo-add-faq,
		.fp-seo-add-step {
			margin-top: 12px;
		}
		<?php
	}

	/**
	 * Render tips styles.
	 *
	 * @return void
	 */
	private function render_tips_styles(): void {
		?>
		.fp-seo-schema-tips {
			background: #fef3c7;
			border-left: 4px solid #f59e0b;
			padding: 16px;
			margin-top: 20px;
			border-radius: 4px;
		}

		.fp-seo-schema-tips h4 {
			margin: 0 0 12px;
			color: #92400e;
			font-size: 14px;
		}

		.fp-seo-schema-tips ul {
			margin: 0;
			padding-left: 20px;
		}

		.fp-seo-schema-tips li {
			margin-bottom: 6px;
			color: #78350f;
			font-size: 13px;
		}
		<?php
	}
}


