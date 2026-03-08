<?php
/**
 * Manages inline CSS styles for social media metabox.
 *
 * @package FP\SEO\Social\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Styles;

use FP\SEO\Social\ImprovedSocialMediaManager;
use function is_admin;
use function get_current_screen;
use function in_array;

/**
 * Manages inline CSS styles for social media.
 */
class SocialStylesManager {
	/**
	 * @var ImprovedSocialMediaManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ImprovedSocialMediaManager $manager Social media manager instance.
	 */
	public function __construct( ImprovedSocialMediaManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_head', array( $this, 'render_all_styles' ) );
		add_action( 'admin_footer', array( $this, 'render_all_styles' ) );
	}
	
	/**
	 * Render styles inline (for use in metabox).
	 *
	 * @return void
	 */
	public function render_inline(): void {
		$this->render_all_styles();
	}

	/**
	 * Render all styles.
	 *
	 * @return void
	 */
	public function render_all_styles(): void {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $supported_types, true ) ) {
			return;
		}
		?>
		<style id="fp-seo-social-styles">
		<?php $this->render_social_icon_styles(); ?>
		<?php $this->render_tab_styles(); ?>
		<?php $this->render_preview_styles(); ?>
		<?php $this->render_form_styles(); ?>
		<?php $this->render_button_styles(); ?>
		<?php $this->render_loading_styles(); ?>
		<?php $this->render_platform_specific_styles(); ?>
		<?php $this->render_responsive_styles(); ?>
		</style>
		<?php
	}

	/**
	 * Render social icon styles.
	 *
	 * @return void
	 */
	private function render_social_icon_styles(): void {
		?>
		/* Enhanced Social Media Styles */
		.fp-seo-ui {
			width: 100%;
		}

		.fp-seo-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
			overflow: hidden;
		}

		.fp-seo-card-header {
			padding: 16px 20px;
			border-bottom: 1px solid #e5e7eb;
			background: #f9fafb;
		}

		.fp-seo-heading-3 {
			margin: 0;
			font-size: 16px;
			font-weight: 600;
			color: #111827;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.fp-seo-text-sm {
			font-size: 13px;
		}

		.fp-seo-text-muted {
			color: #6b7280;
		}

		.fp-seo-card-body {
			padding: 20px;
		}

		.fp-seo-social-icon {
			margin-right: var(--fp-seo-space-2);
		}

		.fp-seo-tab-icon {
			margin-right: var(--fp-seo-space-1);
		}
		<?php
	}

	/**
	 * Render tab styles.
	 *
	 * @return void
	 */
	private function render_tab_styles(): void {
		?>
		.fp-seo-tabs {
			display: flex;
			gap: 8px;
			margin-bottom: 20px;
			border-bottom: 2px solid #e5e7eb;
		}

		.fp-seo-tab {
			position: relative;
			overflow: hidden;
			padding: 12px 16px;
			background: transparent;
			border: none;
			border-bottom: 3px solid transparent;
			cursor: pointer;
			font-size: 14px;
			font-weight: 500;
			color: #6b7280;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.fp-seo-tab:hover {
			color: #111827;
			background: #f9fafb;
		}

		.fp-seo-tab-active {
			color: var(--platform-color, #3b82f6);
			border-bottom-color: var(--platform-color, #3b82f6);
			font-weight: 600;
		}

		.fp-seo-tab-label {
			display: inline-block;
		}

		.fp-seo-tab-content {
			display: none;
		}

		.fp-seo-tab-content-active {
			display: block;
			animation: fadeIn 0.3s ease;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		<?php
	}

	/**
	 * Render preview styles.
	 *
	 * @return void
	 */
	private function render_preview_styles(): void {
		?>
		.fp-seo-social-preview-container {
			margin-bottom: var(--fp-seo-space-6);
		}

		.fp-seo-social-preview-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: var(--fp-seo-space-3);
		}

		.fp-seo-social-preview-card {
			border: 1px solid var(--fp-seo-gray-200);
			border-radius: var(--fp-seo-radius-lg);
			overflow: hidden;
			background: var(--fp-seo-white);
			box-shadow: var(--fp-seo-shadow-sm);
			transition: var(--fp-seo-transition);
		}

		.fp-seo-social-preview-card:hover {
			box-shadow: var(--fp-seo-shadow-md);
		}

		.fp-seo-social-preview-image {
			position: relative;
			width: 100%;
			height: 200px;
			overflow: hidden;
		}

		.fp-seo-social-preview-image img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.fp-seo-social-preview-image-overlay {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.5);
			display: flex;
			align-items: center;
			justify-content: center;
			opacity: 0;
			transition: var(--fp-seo-transition);
		}

		.fp-seo-social-preview-image:hover .fp-seo-social-preview-image-overlay {
			opacity: 1;
		}

		.fp-seo-social-preview-content {
			padding: var(--fp-seo-space-4);
		}

		.fp-seo-social-preview-title {
			font-weight: 600;
			font-size: var(--fp-seo-font-size-base);
			color: var(--fp-seo-gray-900);
			margin-bottom: var(--fp-seo-space-2);
			line-height: 1.3;
		}

		.fp-seo-social-preview-description {
			font-size: var(--fp-seo-font-size-sm);
			color: var(--fp-seo-gray-600);
			margin-bottom: var(--fp-seo-space-2);
			line-height: 1.4;
		}

		.fp-seo-social-preview-url {
			font-size: var(--fp-seo-font-size-xs);
			color: var(--fp-seo-gray-500);
			text-transform: uppercase;
			letter-spacing: 0.5px;
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
		.fp-seo-form-fields {
			margin-top: 20px;
		}

		.fp-seo-form-group {
			margin-bottom: 16px;
		}

		.fp-seo-label {
			display: block;
			margin-bottom: 6px;
			font-size: 13px;
			font-weight: 500;
			color: #374151;
		}

		.fp-seo-char-count {
			float: right;
			font-size: 12px;
			color: #6b7280;
			font-weight: normal;
		}

		.fp-seo-input,
		.fp-seo-textarea {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			font-size: 14px;
			transition: border-color 0.2s ease;
		}

		.fp-seo-input:focus,
		.fp-seo-textarea:focus {
			outline: none;
			border-color: #3b82f6;
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
		}

		.fp-seo-textarea {
			resize: vertical;
			min-height: 80px;
		}

		.fp-seo-image-input-group {
			display: flex;
			gap: 8px;
		}

		.fp-seo-image-input-group .fp-seo-input {
			flex: 1;
		}

		.fp-seo-btn {
			padding: 8px 16px;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			background: #fff;
			color: #374151;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.fp-seo-btn:hover {
			background: #f9fafb;
			border-color: #9ca3af;
		}

		.fp-seo-btn-primary {
			background: #3b82f6;
			color: #fff;
			border-color: #3b82f6;
		}

		.fp-seo-btn-primary:hover {
			background: #2563eb;
			border-color: #2563eb;
		}

		.fp-seo-btn-secondary {
			background: #fff;
			color: #374151;
			border-color: #d1d5db;
		}

		.fp-seo-btn-sm {
			padding: 6px 12px;
			font-size: 13px;
		}

		.fp-seo-media-button {
			white-space: nowrap;
		}

		.fp-seo-form-control-group {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-form-control-group .fp-seo-form-control {
			flex: 1;
		}

		.fp-seo-character-count {
			text-align: right;
			font-size: var(--fp-seo-font-size-xs);
			color: var(--fp-seo-gray-500);
			margin-top: var(--fp-seo-space-1);
		}

		.fp-seo-character-count .fp-seo-text-danger {
			color: var(--fp-seo-danger);
		}

		.fp-seo-character-count .fp-seo-text-warning {
			color: var(--fp-seo-warning);
		}
		<?php
	}

	/**
	 * Render button styles.
	 *
	 * @return void
	 */
	private function render_button_styles(): void {
		?>
		.fp-seo-social-stats {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-social-actions {
			display: flex;
			gap: var(--fp-seo-space-2);
		}

		.fp-seo-btn-icon {
			margin-right: var(--fp-seo-space-1);
		}

		.fp-seo-refresh-icon {
			display: inline-block;
			transition: transform 0.3s ease;
		}
		<?php
	}

	/**
	 * Render loading styles.
	 *
	 * @return void
	 */
	private function render_loading_styles(): void {
		?>
		.fp-seo-loading-icon {
			animation: fp-seo-spin 1s linear infinite;
		}
		
		/* Loading spinner for buttons */
		.fp-seo-loading-spinner {
			display: inline-block;
			width: 14px;
			height: 14px;
			border: 2px solid rgba(255, 255, 255, 0.3);
			border-top-color: #fff;
			border-radius: 50%;
			animation: fp-seo-spin 0.8s linear infinite;
			vertical-align: middle;
			margin-right: 6px;
		}
		
		.fp-seo-btn.fp-seo-loading {
			position: relative;
			pointer-events: none;
			opacity: 0.7;
		}
		
		@keyframes fp-seo-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		
		/* Add loading class only when button is clicked */
		.fp-seo-btn.refreshing .fp-seo-refresh-icon {
			animation: fp-seo-spin 1s linear infinite;
		}
		<?php
	}

	/**
	 * Render platform-specific styles.
	 *
	 * @return void
	 */
	private function render_platform_specific_styles(): void {
		?>
		/* Platform-specific styles */
		.fp-seo-social-preview-facebook {
			max-width: 500px;
		}

		.fp-seo-social-preview-twitter {
			max-width: 400px;
		}

		.fp-seo-social-preview-linkedin {
			max-width: 500px;
		}

		.fp-seo-social-preview-pinterest {
			max-width: 300px;
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
		/* Responsive */
		@media (max-width: 768px) {
			.fp-seo-social-preview-header {
				flex-direction: column;
				align-items: flex-start;
				gap: var(--fp-seo-space-2);
			}

			.fp-seo-social-actions {
				flex-direction: column;
				width: 100%;
			}

			.fp-seo-social-actions .fp-seo-btn {
				width: 100%;
			}
		}
		<?php
	}
}
















