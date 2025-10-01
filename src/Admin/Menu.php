<?php
/**
 * Admin menu registration for the plugin.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Utils\Options;


/**
 * Registers the primary admin menu entry for the plugin.
 */
class Menu {

	/**
	 * Hooks WordPress actions for the menu.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Adds the top-level menu page.
	 */
	public function add_menu(): void {
		$capability = Options::get_capability();

		add_menu_page(
			__( 'SEO Performance', 'fp-seo-performance' ),
			__( 'SEO Performance', 'fp-seo-performance' ),
			$capability,
			'fp-seo-performance',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			81
		);
	}

	/**
	 * Renders a placeholder dashboard page.
	 */
	public function render_dashboard(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'FP SEO Performance', 'fp-seo-performance' ) . '</h1>';
		echo '<p>' . esc_html__( 'Dashboard coming soon.', 'fp-seo-performance' ) . '</p></div>';
	}
}
