<?php
/**
 * Performance settings tab renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use function checked;
use function esc_attr;
use function esc_attr_e;
use function esc_html_e;
use function selected;

/**
 * Renders the Performance settings tab.
 */
class PerformanceTabRenderer extends SettingsTabRenderer {

	/**
	 * Renders performance settings tab.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	public function render( array $options ): void {
		$performance = $options['performance'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
		<th scope="row"><?php esc_html_e( 'PageSpeed Insights', 'fp-seo-performance' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][enable_psi]" value="1" <?php checked( $performance['enable_psi'] ); ?> />
				<?php esc_html_e( 'Enable PSI-based performance hints.', 'fp-seo-performance' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Requires a Google PageSpeed Insights API key to fetch Core Web Vitals signals.', 'fp-seo-performance' ); ?></p>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][psi_api_key]" value="<?php echo esc_attr( $performance['psi_api_key'] ); ?>" placeholder="<?php esc_attr_e( 'Enter PSI API key', 'fp-seo-performance' ); ?>" />
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="psi_cache_ttl">
				<?php esc_html_e( 'PSI cache duration', 'fp-seo-performance' ); ?>
			</label>
		</th>
		<td>
			<select name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][psi_cache_ttl]" id="psi_cache_ttl">
				<option value="3600" <?php selected( $performance['psi_cache_ttl'], 3600 ); ?>><?php esc_html_e( '1 hour', 'fp-seo-performance' ); ?></option>
				<option value="21600" <?php selected( $performance['psi_cache_ttl'], 21600 ); ?>><?php esc_html_e( '6 hours', 'fp-seo-performance' ); ?></option>
				<option value="43200" <?php selected( $performance['psi_cache_ttl'], 43200 ); ?>><?php esc_html_e( '12 hours', 'fp-seo-performance' ); ?></option>
				<option value="86400" <?php selected( $performance['psi_cache_ttl'], 86400 ); ?>><?php esc_html_e( '1 day', 'fp-seo-performance' ); ?></option>
				<option value="604800" <?php selected( $performance['psi_cache_ttl'], 604800 ); ?>><?php esc_html_e( '1 week', 'fp-seo-performance' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'How long to cache PageSpeed Insights results before fetching fresh data.', 'fp-seo-performance' ); ?></p>
		</td>
	</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Local heuristics', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][heuristics][image_alt_coverage]" value="1" <?php checked( $performance['heuristics']['image_alt_coverage'] ); ?> />
						<?php esc_html_e( 'Monitor image alternative text coverage.', 'fp-seo-performance' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][heuristics][inline_css]" value="1" <?php checked( $performance['heuristics']['inline_css'] ); ?> />
						<?php esc_html_e( 'Flag large inline CSS blocks.', 'fp-seo-performance' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][heuristics][image_count]" value="1" <?php checked( $performance['heuristics']['image_count'] ); ?> />
						<?php esc_html_e( 'Warn when pages embed many images.', 'fp-seo-performance' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[performance][heuristics][heading_depth]" value="1" <?php checked( $performance['heuristics']['heading_depth'] ); ?> />
						<?php esc_html_e( 'Highlight deeply nested heading structures.', 'fp-seo-performance' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}
}