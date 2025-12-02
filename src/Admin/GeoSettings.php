<?php
/**
 * GEO Settings Tab
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Renderers\GeoSettingsRenderer;
use FP\SEO\Admin\Scripts\GeoSettingsScriptsManager;
use FP\SEO\GEO\GeoSitemap;
use FP\SEO\Utils\Options;

/**
 * Renders GEO settings tab
 */
class GeoSettings {

	/**
	 * @var GeoSettingsRenderer|null
	 */
	private $renderer;

	/**
	 * @var GeoSettingsScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'fpseo_settings_tabs', array( $this, 'add_geo_tab' ) );
		add_action( 'fpseo_settings_render_tab_geo', array( $this, 'render' ) );
		add_action( 'update_option_fp_seo_performance', array( $this, 'on_settings_update' ), 10, 2 );

		// Initialize renderer and scripts manager
		$this->renderer = new GeoSettingsRenderer();
		$this->scripts_manager = new GeoSettingsScriptsManager();
	}

	/**
	 * Add GEO tab to settings
	 *
	 * @param array<string,string> $tabs Existing tabs.
	 * @return array<string,string>
	 */
	public function add_geo_tab( array $tabs ): array {
		$tabs['geo'] = __( 'GEO', 'fp-seo-performance' );
		return $tabs;
	}

	/**
	 * Render GEO settings tab
	 */
	public function render(): void {
		$options = Options::get();
		$geo     = $options['geo'] ?? array();

		if ( $this->renderer ) {
			$this->renderer->render( $geo );
		}

		if ( $this->scripts_manager ) {
			?>
			<script>
			<?php echo $this->scripts_manager->get_flush_cache_script(); ?>
			</script>
			<?php
		}
	}

	/**
	 * Handle settings update
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function on_settings_update( $old_value, $new_value ): void {
		// Flush GEO caches when settings change
		GeoSitemap::flush_cache();
		delete_transient( 'fp_seo_geo_disallowed_posts' );
		// Also flush site.json cache when settings change
		delete_transient( 'fp_seo_geo_site_json' );

		// Flush rewrite rules if post types changed
		$old_geo = is_array( $old_value ) && isset( $old_value['geo'] ) ? $old_value['geo'] : array();
		$new_geo = is_array( $new_value ) && isset( $new_value['geo'] ) ? $new_value['geo'] : array();

		$old_types = isset( $old_geo['post_types'] ) && is_array( $old_geo['post_types'] ) ? array_keys( $old_geo['post_types'] ) : array();
		$new_types = isset( $new_geo['post_types'] ) && is_array( $new_geo['post_types'] ) ? array_keys( $new_geo['post_types'] ) : array();

		if ( $old_types !== $new_types ) {
			flush_rewrite_rules();
		}
	}

}

