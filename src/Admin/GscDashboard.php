<?php
/**
 * GSC Dashboard Widget
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Renderers\GscDashboardRenderer;
use FP\SEO\Integrations\GscData;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Adds GSC widgets to SEO Performance dashboard
 */
class GscDashboard {

	/**
	 * GSC Data instance
	 *
	 * @var GscData
	 */
	private GscData $gsc_data;

	/**
	 * @var GscDashboardRenderer|null
	 */
	private $renderer;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->gsc_data = new GscData();
		$this->renderer = new GscDashboardRenderer();
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( 'fpseo_dashboard_after_quick_stats', array( $this, 'render_gsc_widget' ) );
		} else {
			add_action( 'fpseo_dashboard_after_quick_stats', array( $this, 'render_gsc_widget' ) );
		}
	}

	/**
	 * Render GSC metrics widget
	 */
	public function render_gsc_widget(): void {
		$options = get_option( 'fp_seo_performance', array() );
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		// Get last 28 days metrics
		$metrics = $this->gsc_data->get_site_metrics( 28 );
		$top_pages = $this->gsc_data->get_top_pages( 28, 5 );

		if ( $this->renderer ) {
			$this->renderer->render( $metrics, $top_pages );
		}
	}
}

