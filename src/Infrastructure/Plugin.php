<?php
/**
 * Plugin bootstrapper.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\PerformanceServiceProvider;
use FP\SEO\Infrastructure\Providers\AnalysisServiceProvider;
use FP\SEO\Infrastructure\Providers\EditorServiceProvider;
use FP\SEO\Infrastructure\Providers\AIServiceProvider;
use FP\SEO\Infrastructure\Providers\GEOServiceProvider;
use FP\SEO\Infrastructure\Providers\IntegrationServiceProvider;
use FP\SEO\Infrastructure\Providers\FrontendServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminAssetsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminPagesServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminUIServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AISettingsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\TestSuiteServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\SchemaMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MainMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\QAMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\FreshnessMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\AuthorProfileMetaboxServiceProvider;

/**
 * Central plugin bootstrapper wiring services together via service providers.
 */
class Plugin {

	/**
	 * Shared singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Service provider registry.
	 *
	 * @var ServiceProviderRegistry
	 */
	private ServiceProviderRegistry $registry;

	/**
	 * Sets up the container and registry.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->registry  = new ServiceProviderRegistry( $this->container );
	}

	/**
	 * Retrieves the singleton plugin instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the service container.
	 *
	 * @return Container
	 */
	public function get_container(): Container {
		return $this->container;
	}

	/**
	 * Get the service provider registry.
	 *
	 * @return ServiceProviderRegistry
	 */
	public function get_registry(): ServiceProviderRegistry {
		return $this->registry;
	}

	/**
	 * Registers core hooks required for bootstrapping.
	 *
	 * @return void
	 */
	public function init(): void {
		register_activation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Load plugin translations at init.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'fp-seo-performance',
			false,
			dirname( plugin_basename( FP_SEO_PERFORMANCE_FILE ) ) . '/languages'
		);
	}

	/**
	 * Boots plugin services after all plugins load.
	 *
	 * Registers all service providers in the correct order and boots them.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Register service providers in order (dependencies first)
		// 1. Core services (foundational: Cache, Logger, Health)
		$this->registry->register( new CoreServiceProvider() );

		// 2. Performance services (optimizations)
		$this->registry->register( new PerformanceServiceProvider() );

		// 3. Analysis services (SEO analysis system)
		$this->registry->register( new AnalysisServiceProvider() );

		// 4. Schema Metaboxes (must be first, before main metabox)
		$this->registry->register( new SchemaMetaboxServiceProvider() );

		// 5. Main SEO Metabox (core editor functionality)
		$this->registry->register( new MainMetaboxServiceProvider() );

		// 6. QA Metabox (Q&A pairs management)
		$this->registry->register( new QAMetaboxServiceProvider() );

		// 7. Freshness Metabox (Temporal signals)
		$this->registry->register( new FreshnessMetaboxServiceProvider() );

		// 8. Author Profile Fields (Authority signals - user profile fields)
		$this->registry->register( new AuthorProfileMetaboxServiceProvider() );

		// 9. Editor Service Provider (kept for backward compatibility, now empty)
		$this->registry->register( new EditorServiceProvider() );

		// 10. Admin Assets (must be first for admin_enqueue_scripts)
		$this->registry->register( new AdminAssetsServiceProvider() );

		// 11. Admin Pages (Menu, Settings, Bulk Audit, Performance Dashboard)
		$this->registry->register( new AdminPagesServiceProvider() );

		// 12. Admin UI components (Notices, Admin Bar)
		$this->registry->register( new AdminUIServiceProvider() );

		// 13. AI services (Core AI)
		$this->registry->register( new AIServiceProvider() );

		// 14. AI Settings (Admin AI features)
		$this->registry->register( new AISettingsServiceProvider() );

		// 15. GEO services (conditional - only if enabled)
		$this->registry->register( new GEOServiceProvider() );

		// 16. Integration services (GSC, Indexing - conditional)
		$this->registry->register( new IntegrationServiceProvider() );

		// 17. Frontend services (renderers)
		$this->registry->register( new FrontendServiceProvider() );

		// 18. Test Suite (only for admins with manage_options)
		$this->registry->register( new TestSuiteServiceProvider() );

		// Boot all providers
		$this->registry->boot();
	}

	/**
	 * Runs activation routines.
	 *
	 * Delegates to service providers for activation tasks.
	 *
	 * IMPORTANTE: Questo metodo NON tocca le opzioni del plugin.
	 * Le opzioni esistenti vengono preservate durante l'attivazione/aggiornamento.
	 * Le opzioni vengono cancellate SOLO quando il plugin viene disinstallato (non disattivato).
	 *
	 * @return void
	 */
	public function activate(): void {
		// Create database tables via service providers
		$this->registry->activate();
	}

	/**
	 * Runs deactivation routines.
	 *
	 * Delegates to service providers for deactivation tasks.
	 *
	 * IMPORTANTE: Questo metodo NON cancella le opzioni del plugin.
	 * Le opzioni vengono preservate durante la disattivazione.
	 * Le opzioni vengono cancellate SOLO quando il plugin viene disinstallato (non disattivato).
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Run deactivation routines via service providers
		$this->registry->deactivate();
	}
}