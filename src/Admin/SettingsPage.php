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
use FP\SEO\Admin\Styles\SettingsStylesManager;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\OptionsHelper;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Renders and processes the plugin settings interface.
 */
class SettingsPage {

	public const PAGE_SLUG = 'fp-seo-performance-settings';

	/**
	 * @var SettingsStylesManager|null
	 */
	private $styles_manager;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Hooks WordPress events for settings management.
	 */
	public function register(): void {
		$hooks = array(
			'admin_menu'                => array( $this, 'add_settings_page' ),
			'admin_init'                => array( $this, 'register_settings' ),
			'admin_post_fp_seo_perf_import' => array( $this, 'handle_import' ),
		);

		foreach ( $hooks as $hook => $callback ) {
			$this->hook_manager->add_action( $hook, $callback );
		}

		// Initialize and register styles manager
		$this->styles_manager = new SettingsStylesManager();
		$this->styles_manager->register_hooks();
	}

	/**
	 * Adds the SEO Performance settings submenu.
	 */
	public function add_settings_page(): void {
		$capability = OptionsHelper::get_capability();

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
	 * 
	 * IMPORTANTE: Il parametro 'default' viene usato SOLO se l'opzione non esiste nel database.
	 * WordPress NON sovrascrive opzioni esistenti quando viene chiamato register_setting().
	 * Le opzioni esistenti vengono preservate anche durante aggiornamenti/disattivazioni del plugin.
	 */
	public function register_settings(): void {
		// Verifica che le opzioni esistenti non vengano sovrascritte
		// WordPress usa 'default' SOLO se l'opzione non esiste
		$existing_options = get_option( Options::OPTION_KEY, false );
		
		register_setting(
			Options::OPTION_GROUP,
			Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			// 'default' viene usato SOLO se l'opzione non esiste (prima installazione)
			// Le opzioni esistenti vengono sempre preservate
			'default'           => OptionsHelper::get_defaults(),
			)
		);
		
		// Se l'opzione esiste già, assicurati che non venga sovrascritta
		// WordPress gestisce questo automaticamente, ma verifichiamo per sicurezza
		if ( $existing_options !== false && is_array( $existing_options ) ) {
			// Le opzioni esistenti sono già nel database e non verranno sovrascritte
			// Questo è il comportamento standard di WordPress
		}
	}

	/**
	 * Sanitizes options while preserving existing values.
	 * This prevents resetting unmodified sections when only partial options are saved.
	 *
	 * @param array<string, mixed> $input Raw option values from form.
	 * @return array<string, mixed> Sanitized options merged with existing values.
	 */
	public function sanitize_options( array $input ): array {
		// Clear cache before retrieving existing options to ensure fresh data
		require_once __DIR__ . '/../Utils/Cache.php';
		\FP\SEO\Utils\Cache::delete( 'options_data' );
		
		// Get existing options first
		$existing = get_option( Options::OPTION_KEY, array() );
		
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		
		// Merge new values with existing options recursively
		// This preserves unmodified sections when only partial options are passed
		$merged = array_replace_recursive( $existing, $input );
		
		// Sanitize the merged options
		$options_instance = OptionsHelper::get_options();
		if ( $options_instance ) {
			$sanitized = $options_instance->sanitize( $merged );
		} else {
			set_error_handler( static function () { return true; }, E_USER_DEPRECATED );
			try {
				$sanitized = Options::sanitize( $merged );
			} finally {
				restore_error_handler();
			}
		}
		
		// Log per debug (usando error_log invece di Logger per evitare dipendenze)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && WP_DEBUG_LOG ) {
			$debug_json = wp_json_encode( array(
				'input_keys'     => array_keys( $input ),
				'existing_keys'  => array_keys( $existing ),
				'merged_keys'    => array_keys( $merged ),
				'sanitized_keys' => array_keys( $sanitized ),
			) );
			error_log( 'FP SEO: sanitize_options - ' . ( false !== $debug_json ? $debug_json : '{}' ) );
		}
		
		return $sanitized;
	}

	/**
	 * Outputs the settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access these settings.', 'fp-seo-performance' ) );
		}

		try {
			$current_tab = $this->get_current_tab();
			$options     = OptionsHelper::get();

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core handles nonce in options.php redirect.
			$flag = sanitize_text_field( wp_unslash( (string) $_GET['settings-updated'] ) );

			if ( 'true' === $flag ) {
				add_settings_error(
					Options::OPTION_GROUP,
					'fp_seo_perf_settings_saved',
					__( 'Impostazioni salvate correttamente.', 'fp-seo-performance' ),
					'updated'
				);
			}
		}

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
		} catch ( \Throwable $e ) {
			// Log error and show user-friendly message
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO SettingsPage Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'FP SEO Performance Settings', 'fp-seo-performance' ); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'An error occurred while loading the settings page. Please try refreshing the page.', 'fp-seo-performance' ); ?></p>
					<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
						<p><strong><?php esc_html_e( 'Debug Info:', 'fp-seo-performance' ); ?></strong> <?php echo esc_html( $e->getMessage() ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Handles the settings import submission.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to import settings.', 'fp-seo-performance' ) );
		}

		check_admin_referer( 'fp_seo_perf_import' );

		$raw = wp_unslash( $_POST['fp_seo_perf_import_blob'] ?? '' );

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			add_settings_error( Options::OPTION_GROUP, 'fp_seo_perf_import_empty', __( 'Import data cannot be empty.', 'fp-seo-performance' ) );
			$this->redirect_back();
			return;
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			add_settings_error( Options::OPTION_GROUP, 'fp_seo_perf_import_invalid', __( 'Invalid JSON settings payload.', 'fp-seo-performance' ) );
			$this->redirect_back();
			return;
		}

		$opts = OptionsHelper::get_options();
		if ( $opts ) {
			$opts->update( $decoded );
		} else {
			set_error_handler( static function () { return true; }, E_USER_DEPRECATED );
			try {
				Options::update( $decoded );
			} finally {
				restore_error_handler();
			}
		}

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
		// Get tab from GET or POST, sanitize immediately for security
		$raw = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : ( isset( $_POST['tab'] ) ? sanitize_key( (string) wp_unslash( $_POST['tab'] ) ) : 'general' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Tab selection only, no sensitive operation.
		$tab  = $raw;
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