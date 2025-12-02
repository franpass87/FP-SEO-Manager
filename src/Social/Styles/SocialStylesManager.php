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
		.fp-seo-tab {
			position: relative;
			overflow: hidden;
		}

		.fp-seo-tab::before {
			content: '';
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			height: 3px;
			background: var(--platform-color);
			transform: scaleX(0);
			transition: var(--fp-seo-transition);
		}

		.fp-seo-tab-active::before {
			transform: scaleX(1);
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


