<?php
/**
 * Analysis settings tab renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use FP\SEO\Utils\Options;
use function __;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function selected;

/**
 * Renders the Analysis settings tab.
 */
class AnalysisTabRenderer extends SettingsTabRenderer {

	/**
	 * Renders analysis settings tab.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	public function render( array $options ): void {
		$analysis = $options['analysis'];
		$scoring  = $options['scoring'];
		?>
		<h3><?php esc_html_e( 'Checks', 'fp-seo-performance' ); ?></h3>
		<p><?php esc_html_e( 'Enable or disable individual analyzer checks.', 'fp-seo-performance' ); ?></p>
		<div class="fp-seo-performance-grid">
		<?php
		foreach ( Options::get_check_keys() as $key ) :
			$label = $this->get_check_label( $key );
			?>
			<label class="fp-seo-performance-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][checks][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $analysis['checks'][ $key ] ); ?> />
				<span><?php echo esc_html( $label ); ?></span>
			</label>
		<?php endforeach; ?>
		</div>

		<h3><?php esc_html_e( 'Metadata thresholds', 'fp-seo-performance' ); ?></h3>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Title length', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
					<?php esc_html_e( 'Min', 'fp-seo-performance' ); ?>
						<input type="number" min="10" max="80" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][title_length_min]" value="<?php echo esc_attr( (string) $analysis['title_length_min'] ); ?>" />
					</label>
					<label>
					<?php esc_html_e( 'Max', 'fp-seo-performance' ); ?>
						<input type="number" min="30" max="80" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][title_length_max]" value="<?php echo esc_attr( (string) $analysis['title_length_max'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'Recommended between 50 and 60 characters.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Meta description length', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
					<?php esc_html_e( 'Min', 'fp-seo-performance' ); ?>
						<input type="number" min="50" max="200" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][meta_length_min]" value="<?php echo esc_attr( (string) $analysis['meta_length_min'] ); ?>" />
					</label>
					<label>
					<?php esc_html_e( 'Max', 'fp-seo-performance' ); ?>
						<input type="number" min="90" max="220" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][meta_length_max]" value="<?php echo esc_attr( (string) $analysis['meta_length_max'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'Recommended between 120 and 160 characters.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Canonical policy', 'fp-seo-performance' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][canonical_policy]">
						<option value="auto" <?php selected( $analysis['canonical_policy'], 'auto' ); ?>><?php esc_html_e( 'Automatic (recommended)', 'fp-seo-performance' ); ?></option>
						<option value="none" <?php selected( $analysis['canonical_policy'], 'none' ); ?>><?php esc_html_e( 'Do not enforce', 'fp-seo-performance' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Social tags', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][enable_og]" value="1" <?php checked( $analysis['enable_og'] ); ?> />
					<?php esc_html_e( 'Enable Open Graph tags.', 'fp-seo-performance' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->get_option_key() ); ?>[analysis][enable_twitter]" value="1" <?php checked( $analysis['enable_twitter'] ); ?> />
					<?php esc_html_e( 'Enable Twitter card tags.', 'fp-seo-performance' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Scoring weights', 'fp-seo-performance' ); ?></h3>
		<p><?php esc_html_e( 'Fine-tune how much each check influences the final score. Higher numbers increase impact.', 'fp-seo-performance' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
		<?php foreach ( Options::get_check_keys() as $key ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $this->get_check_label( $key ) ); ?></th>
				<td>
					<input type="number" step="0.1" min="0" max="5" name="<?php echo esc_attr( $this->get_option_key() ); ?>[scoring][weights][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $scoring['weights'][ $key ] ?? 1.0 ) ); ?>" />
				</td>
			</tr>
		<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Resolves a human readable label for a check key.
	 *
	 * @param string $key Check identifier.
	 *
	 * @return string Translated label.
	 */
	private function get_check_label( string $key ): string {
		return match ( $key ) {
			'title_length'          => __( 'SEO Title length', 'fp-seo-performance' ),
			'meta_description'      => __( 'Meta description length', 'fp-seo-performance' ),
			'h1_presence'           => __( 'H1 presence', 'fp-seo-performance' ),
			'headings_structure'    => __( 'Heading structure', 'fp-seo-performance' ),
			'image_alt'             => __( 'Image alternative text', 'fp-seo-performance' ),
			'canonical'             => __( 'Canonical tag', 'fp-seo-performance' ),
			'robots'                => __( 'Robots indexability', 'fp-seo-performance' ),
			'og_cards'              => __( 'Open Graph cards', 'fp-seo-performance' ),
			'twitter_cards'         => __( 'Twitter cards', 'fp-seo-performance' ),
			'schema_presets'        => __( 'Schema.org presets', 'fp-seo-performance' ),
			'internal_links'        => __( 'Internal links', 'fp-seo-performance' ),
			// AI Overview optimization checks
			'faq_schema'            => __( '🤖 FAQ Schema (AI Overview)', 'fp-seo-performance' ),
			'howto_schema'          => __( '🤖 HowTo Schema (AI Overview)', 'fp-seo-performance' ),
			'ai_optimized_content'  => __( '🤖 Contenuti ottimizzati per AI', 'fp-seo-performance' ),
			default                 => ucfirst( str_replace( '_', ' ', $key ) ),
		};
	}
}