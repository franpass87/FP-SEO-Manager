<?php
/**
 * Manages styles for the Settings page.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use FP\SEO\Admin\SettingsPage;
use function get_current_screen;

/**
 * Manages styles for the Settings page.
 */
class SettingsStylesManager {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_head', array( $this, 'inject_styles' ) );
	}

	/**
	 * Inject modern styles in admin head.
	 *
	 * @return void
	 */
	public function inject_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'fp-seo-performance_page_' . SettingsPage::PAGE_SLUG !== $screen->id ) {
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
		<style id="fp-seo-settings-modern-ui">
		<?php $this->render_css_variables(); ?>
		<?php $this->render_wrap_styles(); ?>
		<?php $this->render_title_styles(); ?>
		<?php $this->render_tab_styles(); ?>
		<?php $this->render_form_styles(); ?>
		<?php $this->render_input_styles(); ?>
		<?php $this->render_button_styles(); ?>
		<?php $this->render_section_styles(); ?>
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
			--fp-seo-gray-50: #f9fafb;
			--fp-seo-gray-200: #e5e7eb;
			--fp-seo-gray-300: #d1d5db;
			--fp-seo-gray-600: #4b5563;
			--fp-seo-gray-700: #374151;
			--fp-seo-gray-900: #111827;
		}
		<?php
	}

	/**
	 * Render wrap styles.
	 *
	 * @return void
	 */
	private function render_wrap_styles(): void {
		?>
		.wrap.fp-seo-performance-settings {
			background: var(--fp-seo-gray-50) !important;
			margin-left: -20px !important;
			margin-right: -20px !important;
			padding: 32px 40px 40px !important;
			min-height: calc(100vh - 32px) !important;
		}
		<?php
	}

	/**
	 * Render title styles.
	 *
	 * @return void
	 */
	private function render_title_styles(): void {
		?>
		.fp-seo-performance-settings > h1 {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text !important;
			font-size: 32px !important;
			font-weight: 700 !important;
			margin-bottom: 24px !important;
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
		.fp-seo-performance-settings .nav-tab-wrapper {
			border-bottom: 2px solid var(--fp-seo-gray-200) !important;
			margin-bottom: 24px !important;
		}
		
		.fp-seo-performance-settings .nav-tab {
			background: transparent !important;
			border: none !important;
			border-bottom: 3px solid transparent !important;
			color: #4b5563 !important;
			font-weight: 500 !important;
			padding: 12px 20px !important;
			margin-bottom: -2px !important;
			transition: all 0.2s ease !important;
		}
		
		.fp-seo-performance-settings .nav-tab:hover {
			background: #f9fafb !important;
			color: #111827 !important;
			border-bottom-color: #d1d5db !important;
		}
		
		.fp-seo-performance-settings .nav-tab-active,
		.fp-seo-performance-settings .nav-tab-active:hover {
			background: transparent !important;
			border-bottom-color: #2563eb !important;
			color: #2563eb !important;
			font-weight: 600 !important;
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
		.fp-seo-performance-settings form {
			background: #fff !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			padding: 24px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
		}
		<?php
	}

	/**
	 * Render input styles.
	 *
	 * @return void
	 */
	private function render_input_styles(): void {
		?>
		.fp-seo-performance-settings input[type="text"],
		.fp-seo-performance-settings input[type="number"],
		.fp-seo-performance-settings input[type="email"],
		.fp-seo-performance-settings input[type="url"],
		.fp-seo-performance-settings textarea,
		.fp-seo-performance-settings select {
			border: 1px solid var(--fp-seo-gray-300) !important;
			border-radius: 6px !important;
			padding: 8px 12px !important;
			transition: all 0.2s ease !important;
		}

		.fp-seo-performance-settings input:focus,
		.fp-seo-performance-settings textarea:focus,
		.fp-seo-performance-settings select:focus {
			outline: none !important;
			border-color: #2563eb !important;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
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
		.fp-seo-performance-settings .button-primary {
			background: #2563eb !important;
			border-color: #2563eb !important;
			color: #fff !important;
			font-weight: 600 !important;
			padding: 10px 24px !important;
			height: auto !important;
			border-radius: 6px !important;
			box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important;
			transition: all 0.2s ease !important;
		}
		
		.fp-seo-performance-settings .button-primary:hover {
			background: #1d4ed8 !important;
			border-color: #1d4ed8 !important;
			transform: translateY(-1px) !important;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;
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
		.fp-seo-settings-section {
			margin-bottom: 32px !important;
			padding-bottom: 24px !important;
			border-bottom: 1px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-settings-section__title {
			font-size: 18px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-900) !important;
			margin: 0 0 8px !important;
		}
		
		.fp-seo-settings-section__description {
			font-size: 13px !important;
			color: var(--fp-seo-gray-600) !important;
			margin: 0 0 20px !important;
		}
		<?php
	}
}


