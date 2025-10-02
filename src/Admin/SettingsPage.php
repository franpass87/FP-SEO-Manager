<?php
/**
 * Settings page controller.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Utils\Options;

/**
 * Renders and processes the plugin settings interface.
 */
class SettingsPage {

	private const PAGE_SLUG = 'fp-seo-performance-settings';

	/**
	 * Hooks WordPress events for settings management.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_fp_seo_perf_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Adds the SEO Performance settings submenu.
	 */
	public function add_settings_page(): void {
		$capability = Options::get_capability();

		add_submenu_page(
			'fp-seo-performance',
			__( 'Settings', 'fp-seo-performance' ),
			__( 'Settings', 'fp-seo-performance' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Registers the plugin setting with sanitization callbacks.
	 */
	public function register_settings(): void {
		register_setting(
			Options::OPTION_GROUP,
			Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Options::class, 'sanitize' ),
				'default'           => Options::get_defaults(),
			)
		);
	}

	/**
	 * Outputs the settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( Options::get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access these settings.', 'fp-seo-performance' ) );
		}

		$current_tab = $this->get_current_tab();
		$options     = Options::get();

		$tabs = array(
			'general'     => __( 'General', 'fp-seo-performance' ),
			'analysis'    => __( 'Analysis', 'fp-seo-performance' ),
			'performance' => __( 'Performance', 'fp-seo-performance' ),
			'advanced'    => __( 'Advanced', 'fp-seo-performance' ),
		);
		?>
		<div class="wrap fp-seo-performance-settings">
			<h1><?php echo esc_html__( 'FP SEO Performance Settings', 'fp-seo-performance' ); ?></h1>
			<?php settings_errors( Options::OPTION_GROUP ); ?>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $tab,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php" class="fp-seo-performance-form">
					<?php settings_fields( Options::OPTION_GROUP ); ?>
				<div class="fp-seo-performance-tab-content">
					<?php
					switch ( $current_tab ) {
						case 'analysis':
							$this->render_analysis_tab( $options );
							break;
						case 'performance':
							$this->render_performance_tab( $options );
							break;
						case 'advanced':
							$this->render_advanced_tab( $options );
							break;
						case 'general':
						default:
							$this->render_general_tab( $options );
							break;
					}
					?>
				</div>
					<?php submit_button( __( 'Save Changes', 'fp-seo-performance' ) ); ?>
			</form>
		</div>
			<?php
	}

	/**
	 * Handles the settings import submission.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( Options::get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to import settings.', 'fp-seo-performance' ) );
		}

		check_admin_referer( 'fp_seo_perf_import' );

		$raw = wp_unslash( $_POST['fp_seo_perf_import_blob'] ?? '' );

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			add_settings_error( Options::OPTION_GROUP, 'fp_seo_perf_import_empty', __( 'Import data cannot be empty.', 'fp-seo-performance' ) );
			$this->redirect_back();
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			add_settings_error( Options::OPTION_GROUP, 'fp_seo_perf_import_invalid', __( 'Invalid JSON settings payload.', 'fp-seo-performance' ) );
			$this->redirect_back();
		}

		Options::update( $decoded );

		add_settings_error( Options::OPTION_GROUP, 'fp_seo_perf_import_success', __( 'Settings imported successfully.', 'fp-seo-performance' ), 'updated' );

		$this->redirect_back();
	}

	/**
	 * Prints the General tab fields.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	private function render_general_tab( array $options ): void {
		$general = $options['general'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Analyzer', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[general][enable_analyzer]" value="1" <?php checked( $general['enable_analyzer'] ); ?> />
						<?php esc_html_e( 'Enable on-page analyzer', 'fp-seo-performance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Toggle to enable or disable all analyzer features globally.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Content language', 'fp-seo-performance' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[general][language]">
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
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[general][admin_bar_badge]" value="1" <?php checked( $general['admin_bar_badge'] ); ?> />
						<?php esc_html_e( 'Display analyzer score badge in the admin bar.', 'fp-seo-performance' ); ?>
					</label>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Prints the Analysis tab controls.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	private function render_analysis_tab( array $options ): void {
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
					<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][checks][<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $analysis['checks'][ $key ] ); ?> />
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
						<input type="number" min="10" max="80" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][title_length_min]" value="<?php echo esc_attr( (string) $analysis['title_length_min'] ); ?>" />
					</label>
					<label>
					<?php esc_html_e( 'Max', 'fp-seo-performance' ); ?>
						<input type="number" min="30" max="80" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][title_length_max]" value="<?php echo esc_attr( (string) $analysis['title_length_max'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'Recommended between 50 and 60 characters.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Meta description length', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
					<?php esc_html_e( 'Min', 'fp-seo-performance' ); ?>
						<input type="number" min="50" max="200" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][meta_length_min]" value="<?php echo esc_attr( (string) $analysis['meta_length_min'] ); ?>" />
					</label>
					<label>
					<?php esc_html_e( 'Max', 'fp-seo-performance' ); ?>
						<input type="number" min="90" max="220" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][meta_length_max]" value="<?php echo esc_attr( (string) $analysis['meta_length_max'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'Recommended between 120 and 160 characters.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Canonical policy', 'fp-seo-performance' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][canonical_policy]">
						<option value="auto" <?php selected( $analysis['canonical_policy'], 'auto' ); ?>><?php esc_html_e( 'Automatic (recommended)', 'fp-seo-performance' ); ?></option>
						<option value="none" <?php selected( $analysis['canonical_policy'], 'none' ); ?>><?php esc_html_e( 'Do not enforce', 'fp-seo-performance' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Social tags', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][enable_og]" value="1" <?php checked( $analysis['enable_og'] ); ?> />
					<?php esc_html_e( 'Enable Open Graph tags.', 'fp-seo-performance' ); ?>
					</label>
					<br />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[analysis][enable_twitter]" value="1" <?php checked( $analysis['enable_twitter'] ); ?> />
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
												<input type="number" step="0.1" min="0" max="5" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[scoring][weights][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) ( $scoring['weights'][ $key ] ?? 1.0 ) ); ?>" />
										</td>
								</tr>
						<?php endforeach; ?>
						</tbody>
				</table>
					<?php
	}

	/**
	 * Prints the Performance tab controls.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	private function render_performance_tab( array $options ): void {
		$performance = $options['performance'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'PageSpeed Insights', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][enable_psi]" value="1" <?php checked( $performance['enable_psi'] ); ?> />
						<?php esc_html_e( 'Enable PSI-based performance hints.', 'fp-seo-performance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Requires a Google PageSpeed Insights API key to fetch Core Web Vitals signals.', 'fp-seo-performance' ); ?></p>
					<input type="text" class="regular-text" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][psi_api_key]" value="<?php echo esc_attr( $performance['psi_api_key'] ); ?>" placeholder="<?php esc_attr_e( 'Enter PSI API key', 'fp-seo-performance' ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Local heuristics', 'fp-seo-performance' ); ?></th>
				<td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][heuristics][image_alt_coverage]" value="1" <?php checked( $performance['heuristics']['image_alt_coverage'] ); ?> />
                                                <?php esc_html_e( 'Monitor image alternative text coverage.', 'fp-seo-performance' ); ?>
                                        </label>
                                        <br />
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][heuristics][inline_css]" value="1" <?php checked( $performance['heuristics']['inline_css'] ); ?> />
                                                <?php esc_html_e( 'Flag large inline CSS blocks.', 'fp-seo-performance' ); ?>
                                        </label>
                                        <br />
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][heuristics][image_count]" value="1" <?php checked( $performance['heuristics']['image_count'] ); ?> />
                                                <?php esc_html_e( 'Warn when pages embed many images.', 'fp-seo-performance' ); ?>
                                        </label>
                                        <br />
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[performance][heuristics][heading_depth]" value="1" <?php checked( $performance['heuristics']['heading_depth'] ); ?> />
                                                <?php esc_html_e( 'Highlight deeply nested heading structures.', 'fp-seo-performance' ); ?>
                                        </label>
                                </td>
                        </tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Prints the Advanced tab controls and import/export tools.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	private function render_advanced_tab( array $options ): void {
		$advanced = $options['advanced'];
		?>
		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Required capability', 'fp-seo-performance' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[advanced][capability]" value="<?php echo esc_attr( $advanced['capability'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Users must have this capability to manage plugin settings.', 'fp-seo-performance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Telemetry', 'fp-seo-performance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Options::OPTION_KEY ); ?>[advanced][telemetry_enabled]" value="1" <?php checked( $advanced['telemetry_enabled'] ); ?> />
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

	/**
	 * Provides the supported language choices.
	 *
	 * @return array<string, string> Map of locale codes to labels.
	 */
        private function get_language_choices(): array {
                return Options::get_language_choices();
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
			'title_length'      => __( 'SEO Title length', 'fp-seo-performance' ),
			'meta_description'  => __( 'Meta description length', 'fp-seo-performance' ),
			'h1_presence'       => __( 'H1 presence', 'fp-seo-performance' ),
			'headings_structure'=> __( 'Heading structure', 'fp-seo-performance' ),
			'image_alt'         => __( 'Image alternative text', 'fp-seo-performance' ),
			'canonical'         => __( 'Canonical tag', 'fp-seo-performance' ),
			'robots'            => __( 'Robots indexability', 'fp-seo-performance' ),
			'og_cards'          => __( 'Open Graph cards', 'fp-seo-performance' ),
			'twitter_cards'     => __( 'Twitter cards', 'fp-seo-performance' ),
			'schema_presets'    => __( 'Schema.org presets', 'fp-seo-performance' ),
			'internal_links'    => __( 'Internal links', 'fp-seo-performance' ),
			default             => ucfirst( str_replace( '_', ' ', $key ) ),
		};
	}

	/**
	 * Returns the currently selected tab slug.
	 */
	private function get_current_tab(): string {
		$raw  = $_GET['tab'] ?? ( $_POST['tab'] ?? 'general' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		$tab  = sanitize_key( (string) wp_unslash( $raw ) );
		$tabs = array( 'general', 'analysis', 'performance', 'advanced' );

		if ( ! in_array( $tab, $tabs, true ) ) {
			return 'general';
		}

		return $tab;
	}

	/**
	 * Redirects the browser back to the settings page.
	 */
	private function redirect_back(): void {
		$redirect = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $this->get_current_tab(),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}