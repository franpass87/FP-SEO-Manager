<?php
/**
 * General settings tab renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use FP\SEO\Utils\Options;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function selected;

/**
 * Renders the General settings tab.
 */
class GeneralTabRenderer extends SettingsTabRenderer {

	/**
	 * Renders general settings tab.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	public function render( array $options ): void {
		$general = $options['general'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Analyzer', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[general][enable_analyzer]" value="1" <?php checked( $general['enable_analyzer'] ); ?> />
						<?php esc_html_e( 'Enable on-page analyzer', 'fp-seo-performance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Toggle to enable or disable all analyzer features globally.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Content language', 'fp-seo-performance' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $this->get_option_key() ); ?>[general][language]">
						<?php foreach ( $this->get_language_choices() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $general['language'], $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used as a hint for language-specific analysis tweaks.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin bar badge', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[general][admin_bar_badge]" value="1" <?php checked( $general['admin_bar_badge'] ); ?> />
						<?php esc_html_e( 'Display analyzer score badge in the admin bar.', 'fp-seo-performance' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>

		<?php
	}

	/**
	 * Provides the supported language choices.
	 *
	 * @return array<string, string> Map of locale codes to labels.
	 */
	private function get_language_choices(): array {
		return Options::get_language_choices();
	}
}