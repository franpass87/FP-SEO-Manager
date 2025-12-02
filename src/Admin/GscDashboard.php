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
	 * Constructor
	 */
	public function __construct() {
		$this->gsc_data = new GscData();
		$this->renderer = new GscDashboardRenderer();
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'fpseo_dashboard_after_quick_stats', array( $this, 'render_gsc_widget' ) );
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

