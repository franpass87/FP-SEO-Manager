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

use FP\SEO\GEO\GeoSitemap;
use FP\SEO\Utils\Options;

/**
 * Renders GEO settings tab
 */
class GeoSettings {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'fpseo_settings_tabs', array( $this, 'add_geo_tab' ) );
		add_action( 'fpseo_settings_render_tab_geo', array( $this, 'render' ) );
		add_action( 'update_option_fp_seo_performance', array( $this, 'on_settings_update' ), 10, 2 );
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

		?>
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">🤖 <?php esc_html_e( 'Generative Engine Optimization (GEO)', 'fp-seo-performance' ); ?></h3>
			<p class="fp-seo-settings-section__description">
				<?php esc_html_e( 'Configure how AI crawlers and LLMs can access and use your content.', 'fp-seo-performance' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<!-- Publisher Information -->
				<tr>
					<th scope="row">
						<label for="fp_seo_geo_publisher_name"><?php esc_html_e( 'Publisher Name', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="text" 
							   id="fp_seo_geo_publisher_name" 
							   name="fp_seo_performance[geo][publisher_name]"
							   value="<?php echo esc_attr( $geo['publisher_name'] ?? '' ); ?>"
							   class="regular-text" />
						<p class="description"><?php esc_html_e( 'Organization or company name', 'fp-seo-performance' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="fp_seo_geo_publisher_url"><?php esc_html_e( 'Publisher URL', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="url" 
							   id="fp_seo_geo_publisher_url" 
							   name="fp_seo_performance[geo][publisher_url]"
							   value="<?php echo esc_attr( $geo['publisher_url'] ?? '' ); ?>"
							   class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="fp_seo_geo_publisher_logo"><?php esc_html_e( 'Publisher Logo URL', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="url" 
							   id="fp_seo_geo_publisher_logo" 
							   name="fp_seo_performance[geo][publisher_logo]"
							   value="<?php echo esc_attr( $geo['publisher_logo'] ?? '' ); ?>"
							   class="regular-text" />
					</td>
				</tr>

				<!-- License -->
				<tr>
					<th scope="row">
						<label for="fp_seo_geo_license_url"><?php esc_html_e( 'License URL', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="url" 
							   id="fp_seo_geo_license_url" 
							   name="fp_seo_performance[geo][license_url]"
							   value="<?php echo esc_attr( $geo['license_url'] ?? '' ); ?>"
							   class="regular-text" />
						<p class="description"><?php esc_html_e( 'URL to your content license (e.g., Creative Commons)', 'fp-seo-performance' ); ?></p>
					</td>
				</tr>

				<!-- AI Usage Policy -->
				<tr>
					<th scope="row"><?php esc_html_e( 'AI Usage Policy', 'fp-seo-performance' ); ?></th>
					<td>
						<fieldset>
							<?php
							$current_usage = $geo['ai_usage'] ?? 'allow-with-attribution';
							$policies      = array(
								'allow'                  => __( 'Allow (no restrictions)', 'fp-seo-performance' ),
								'allow-with-attribution' => __( 'Allow with attribution', 'fp-seo-performance' ),
								'deny'                   => __( 'Deny (do not use)', 'fp-seo-performance' ),
							);
							?>
							<?php foreach ( $policies as $value => $label ) : ?>
								<label>
									<input type="radio" 
										   name="fp_seo_performance[geo][ai_usage]" 
										   value="<?php echo esc_attr( $value ); ?>"
										   <?php checked( $current_usage, $value ); ?> />
									<?php echo esc_html( $label ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>

				<!-- Default Confidence -->
				<tr>
					<th scope="row">
						<label for="fp_seo_geo_default_confidence"><?php esc_html_e( 'Default Confidence', 'fp-seo-performance' ); ?></label>
					</th>
					<td>
						<input type="number" 
							   id="fp_seo_geo_default_confidence" 
							   name="fp_seo_performance[geo][default_confidence]"
							   value="<?php echo esc_attr( $geo['default_confidence'] ?? '0.7' ); ?>"
							   min="0" 
							   max="1" 
							   step="0.1"
							   style="width: 80px;" />
						<p class="description"><?php esc_html_e( 'Default confidence score for claims (0.0 - 1.0)', 'fp-seo-performance' ); ?></p>
					</td>
				</tr>

				<!-- Pretty Print JSON -->
				<tr>
					<th scope="row"><?php esc_html_e( 'JSON Output', 'fp-seo-performance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[geo][pretty_print]" 
								   value="1"
								   <?php checked( ! empty( $geo['pretty_print'] ) ); ?> />
							<?php esc_html_e( 'Pretty-print JSON endpoints (easier to read, larger file size)', 'fp-seo-performance' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Post Types Configuration -->
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">📋 <?php esc_html_e( 'Post Types', 'fp-seo-performance' ); ?></h3>
			<p class="fp-seo-settings-section__description">
				<?php esc_html_e( 'Select which post types to expose in GEO endpoints', 'fp-seo-performance' ); ?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Post Type', 'fp-seo-performance' ); ?></th>
						<th><?php esc_html_e( 'Expose in GEO', 'fp-seo-performance' ); ?></th>
						<th><?php esc_html_e( 'Include in Sitemap', 'fp-seo-performance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$post_types = get_post_types( array( 'public' => true ), 'objects' );
					foreach ( $post_types as $type ) :
						if ( in_array( $type->name, array( 'attachment' ), true ) ) {
							continue;
						}

						$settings = $geo['post_types'][ $type->name ] ?? array();
						?>
						<tr>
							<td><strong><?php echo esc_html( $type->label ); ?></strong> <code><?php echo esc_html( $type->name ); ?></code></td>
							<td>
								<input type="checkbox" 
									   name="fp_seo_performance[geo][post_types][<?php echo esc_attr( $type->name ); ?>][expose]" 
									   value="1"
									   <?php checked( ! empty( $settings['expose'] ) ); ?> />
							</td>
							<td>
								<input type="checkbox" 
									   name="fp_seo_performance[geo][post_types][<?php echo esc_attr( $type->name ); ?>][in_sitemap]" 
									   value="1"
									   <?php checked( ! empty( $settings['in_sitemap'] ) ); ?> />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Endpoints Info -->
		<div class="fp-seo-settings-section">
			<h3 class="fp-seo-settings-section__title">🔗 <?php esc_html_e( 'GEO Endpoints', 'fp-seo-performance' ); ?></h3>
			<p class="fp-seo-settings-section__description">
				<?php esc_html_e( 'Your site provides these GEO endpoints:', 'fp-seo-performance' ); ?>
			</p>

			<ul class="fp-seo-endpoint-list" style="list-style: disc; margin-left: 20px;">
				<li><strong>ai.txt:</strong> <a href="<?php echo esc_url( home_url( '/.well-known/ai.txt' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/.well-known/ai.txt' ) ); ?></a></li>
				<li><strong>GEO Sitemap:</strong> <a href="<?php echo esc_url( home_url( '/geo-sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/geo-sitemap.xml' ) ); ?></a></li>
				<li><strong>Site Info:</strong> <a href="<?php echo esc_url( home_url( '/geo/site.json' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/geo/site.json' ) ); ?></a></li>
				<li><strong>Updates Feed:</strong> <a href="<?php echo esc_url( home_url( '/geo/updates.json' ) ); ?>" target="_blank"><?php echo esc_html( home_url( '/geo/updates.json' ) ); ?></a></li>
				<li><strong>Content JSON:</strong> <code><?php echo esc_html( home_url( '/geo/content/{post_id}.json' ) ); ?></code></li>
			</ul>

			<p>
				<button type="button" class="button" onclick="fpseoFlushGeoCache()">
					<?php esc_html_e( 'Flush GEO Cache', 'fp-seo-performance' ); ?>
				</button>
			</p>

			<script>
			function fpseoFlushGeoCache() {
				if (confirm('<?php esc_html_e( 'Flush all GEO caches?', 'fp-seo-performance' ); ?>')) {
					jQuery.post(ajaxurl, {
						action: 'fp_seo_geo_flush_cache',
						nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_geo_flush' ) ); ?>'
					}, function(response) {
						alert(response.data || 'Cache flushed!');
					});
				}
			}
			</script>
		</div>
		<?php
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

