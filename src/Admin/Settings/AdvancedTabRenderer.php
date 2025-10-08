<?php
/**
 * Advanced settings tab renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use function admin_url;
use function checked;
use function esc_attr;
use function esc_attr_e;
use function esc_html_e;
use function esc_textarea;
use function esc_url;
use function submit_button;
use function wp_json_encode;
use function wp_nonce_field;

/**
 * Renders the Advanced settings tab with import/export.
 */
class AdvancedTabRenderer extends SettingsTabRenderer {

	/**
	 * Renders advanced settings tab.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	public function render( array $options ): void {
		$advanced = $options['advanced'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Required capability', 'fp-seo-performance' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $this->get_option_key() ); ?>[advanced][capability]" value="<?php echo esc_attr( $advanced['capability'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Users must have this capability to manage plugin settings.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Telemetry', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[advanced][telemetry_enabled]" value="1" <?php checked( $advanced['telemetry_enabled'] ); ?> />
						<?php esc_html_e( 'Share anonymous usage analytics to help improve the plugin.', 'fp-seo-performance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Telemetry remains disabled by default unless explicitly enabled.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Export settings', 'fp-seo-performance' ); ?></h3>
		<p><?php esc_html_e( 'Copy the JSON below to back up your configuration.', 'fp-seo-performance' ); ?></p>
		<textarea readonly rows="8" class="large-text code"><?php echo esc_textarea( wp_json_encode( $options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></textarea>

		<h3><?php esc_html_e( 'Import settings', 'fp-seo-performance' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'fp_seo_perf_import' ); ?>
			<input type="hidden" name="action" value="fp_seo_perf_import" />
			<input type="hidden" name="tab" value="advanced" />
			<textarea name="fp_seo_perf_import_blob" rows="8" class="large-text code" placeholder="<?php esc_attr_e( 'Paste settings JSON here', 'fp-seo-performance' ); ?>"></textarea>
			<?php submit_button( __( 'Import Settings', 'fp-seo-performance' ), 'secondary' ); ?>
		</form>
		<?php
	}
}