<?php
/**
 * Manages styles for the Keywords admin page.
 *
 * @package FP\SEO\Keywords\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Styles;

use function get_current_screen;

/**
 * Manages styles for the Keywords admin page.
 */
class KeywordsPageStylesManager {
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
		
		if ( ! $screen || 'fp-seo-performance_page_fp-seo-multiple-keywords' !== $screen->id ) {
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
		/* Container principale */
		.fp-seo-keywords-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-keywords-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		/* Banner introduttivo (riuso stili) */
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

		.fp-seo-tip {
			margin: 16px 0 0;
			padding: 12px 16px;
			background: rgba(255, 255, 255, 0.15);
			border-radius: 6px;
			font-size: 14px;
		}

		/* Dashboard */
		.fp-seo-keywords-dashboard {
			margin-top: 20px;
		}
		
		/* Stats Grid */
		.fp-seo-keywords-stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			gap: 20px;
			margin-bottom: 32px;
		}
		
		.fp-seo-stat-card {
			background: white;
			padding: 24px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			transition: all 0.3s ease;
			text-align: center;
		}

		.fp-seo-stat-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 8px 12px rgba(0,0,0,0.1);
		}

		.fp-seo-stat-icon {
			font-size: 32px;
			margin-bottom: 12px;
		}

		.fp-seo-stat-content {}

		.fp-seo-stat-card h3 {
			margin: 0 0 12px;
			font-size: 14px;
			color: #6b7280;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.fp-seo-stat-number {
			display: block;
			font-size: 42px;
			font-weight: 700;
			color: #2563eb;
			line-height: 1;
			margin-bottom: 8px;
		}

		.fp-seo-stat-desc {
			margin: 0;
			font-size: 13px;
			color: #6b7280;
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

		/* Analysis section */
		.fp-seo-keywords-analysis {
			background: white;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		
		.fp-seo-stat-card h3 {
			margin: 0 0 10px 0;
			font-size: 14px;
			color: #666;
			text-transform: uppercase;
		}
		
		.fp-seo-stat-number {
			font-size: 32px;
			font-weight: 600;
			color: #0073aa;
		}
		
		.fp-seo-keywords-analysis {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 20px;
		}
		</style>
		<?php
	}
}


