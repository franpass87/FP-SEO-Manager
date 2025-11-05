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
use FP\SEO\Admin\Settings\AutomationTabRenderer;
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
		add_action( 'admin_head', array( $this, 'inject_modern_styles' ) );
	}

	/**
	 * Inject modern styles in admin head
	 */
	public function inject_modern_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'fp-seo-performance_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}
		
		?>
		<style id="fp-seo-settings-modern-ui">
		:root {
			--fp-seo-primary: #2563eb;
			--fp-seo-primary-dark: #1d4ed8;
			--fp-seo-gray-50: #f9fafb;
			--fp-seo-gray-200: #e5e7eb;
			--fp-seo-gray-300: #d1d5db;
			--fp-seo-gray-600: #4b5563;
			--fp-seo-gray-700: #374151;
			--fp-seo-gray-900: #111827;
		}
		
		.wrap.fp-seo-performance-settings {
			background: var(--fp-seo-gray-50) !important;
			margin-left: -20px !important;
			margin-right: -20px !important;
			padding: 32px 40px 40px !important;
			min-height: calc(100vh - 32px) !important;
		}
		
		.fp-seo-performance-settings > h1 {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text !important;
			font-size: 32px !important;
			font-weight: 700 !important;
			margin-bottom: 24px !important;
		}
		
		.fp-seo-performance-settings .nav-tab-wrapper {
			border-bottom: 2px solid var(--fp-seo-gray-200) !important;
			margin-bottom: 24px !important;
		}
		
		.fp-seo-performance-settings .nav-tab {
			background: transparent !important;
			border: none !important;
			border-bottom: 3px solid transparent !important;
			color: #4b5563 !important;
			font-weight: 500 !important;
			padding: 12px 20px !important;
			margin-bottom: -2px !important;
			transition: all 0.2s ease !important;
		}
		
		.fp-seo-performance-settings .nav-tab:hover {
			background: #f9fafb !important;
			color: #111827 !important;
			border-bottom-color: #d1d5db !important;
		}
		
		.fp-seo-performance-settings .nav-tab-active,
		.fp-seo-performance-settings .nav-tab-active:hover {
			background: transparent !important;
			border-bottom-color: #2563eb !important;
			color: #2563eb !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-performance-settings form {
			background: #fff !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			padding: 24px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
		}
		
		.fp-seo-performance-settings input[type="text"],
		.fp-seo-performance-settings input[type="number"],
		.fp-seo-performance-settings input[type="email"],
		.fp-seo-performance-settings input[type="url"],
		.fp-seo-performance-settings textarea,
		.fp-seo-performance-settings select {
			border: 1px solid var(--fp-seo-gray-300) !important;
			border-radius: 6px !important;
			padding: 8px 12px !important;
			transition: all 0.2s ease !important;
		}
		
		.fp-seo-performance-settings input:focus,
		.fp-seo-performance-settings textarea:focus,
		.fp-seo-performance-settings select:focus {
			outline: none !important;
			border-color: #2563eb !important;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
		}
		
		.fp-seo-performance-settings .button-primary {
			background: #2563eb !important;
			border-color: #2563eb !important;
			color: #fff !important;
			font-weight: 600 !important;
			padding: 10px 24px !important;
			height: auto !important;
			border-radius: 6px !important;
			box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important;
			transition: all 0.2s ease !important;
		}
		
		.fp-seo-performance-settings .button-primary:hover {
			background: #1d4ed8 !important;
			border-color: #1d4ed8 !important;
			transform: translateY(-1px) !important;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;
		}
		
		.fp-seo-settings-section {
			margin-bottom: 32px !important;
			padding-bottom: 24px !important;
			border-bottom: 1px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-settings-section__title {
			font-size: 18px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-900) !important;
			margin: 0 0 8px !important;
		}
		
		.fp-seo-settings-section__description {
			font-size: 13px !important;
			color: var(--fp-seo-gray-600) !important;
			margin: 0 0 20px !important;
		}
		</style>
		<?php
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
			'automation'  => __( 'Automation', 'fp-seo-performance' ),
			'advanced'    => __( 'Advanced', 'fp-seo-performance' ),
		);

		/**
		 * Filter settings tabs
		 *
		 * @param array<string,string> $tabs Tabs array.
		 */
		$tabs = apply_filters( 'fpseo_settings_tabs', $tabs );
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
				<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo esc_attr( $current_tab === $tab ? 'nav-tab-active' : '' ); ?>">
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
		// Check if this is a custom tab
		if ( has_action( 'fpseo_settings_render_tab_' . $tab ) ) {
			do_action( 'fpseo_settings_render_tab_' . $tab, $options );
			return;
		}

		// Default tabs
		$renderer = match ( $tab ) {
			'analysis'    => new AnalysisTabRenderer(),
			'performance' => new PerformanceTabRenderer(),
			'automation'  => new AutomationTabRenderer(),
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
		$tabs = array( 'general', 'analysis', 'performance', 'automation', 'advanced' );

		// Allow custom tabs via filter
		$tabs = apply_filters( 'fpseo_settings_tabs', array_combine( $tabs, $tabs ) );
		$tabs = array_keys( $tabs );

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