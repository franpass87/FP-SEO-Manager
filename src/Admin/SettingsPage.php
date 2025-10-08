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

use FP\SEO\Admin\Settings\AdvancedTabRenderer;
use FP\SEO\Admin\Settings\AnalysisTabRenderer;
use FP\SEO\Admin\Settings\GeneralTabRenderer;
use FP\SEO\Admin\Settings\PerformanceTabRenderer;
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
					<?php $this->render_tab_content( $current_tab, $options ); ?>
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
	 * Renders tab content using dedicated renderer classes.
	 *
	 * @param string               $tab     Current tab slug.
	 * @param array<string, mixed> $options Current plugin options.
	 */
	private function render_tab_content( string $tab, array $options ): void {
		$renderer = match ( $tab ) {
			'analysis'    => new AnalysisTabRenderer(),
			'performance' => new PerformanceTabRenderer(),
			'advanced'    => new AdvancedTabRenderer(),
			default       => new GeneralTabRenderer(),
		};

		$renderer->render( $options );
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