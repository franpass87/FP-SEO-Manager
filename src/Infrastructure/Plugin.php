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
use FP\SEO\Infrastructure\Providers\DataServiceProvider;
use FP\SEO\Infrastructure\Providers\PerformanceServiceProvider;
use FP\SEO\Infrastructure\Providers\AnalysisServiceProvider;
use FP\SEO\Infrastructure\Providers\AIServiceProvider;
use FP\SEO\Infrastructure\Providers\GEOServiceProvider;
use FP\SEO\Infrastructure\Providers\RedirectsAndSitemapServiceProvider;
use FP\SEO\Infrastructure\Providers\IntegrationServiceProvider;
use FP\SEO\Infrastructure\Providers\FrontendServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminAssetsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminPagesServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AdminUIServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\AISettingsServiceProvider;
use FP\SEO\Infrastructure\Providers\Admin\TestSuiteServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MetaboxServicesProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\SchemaMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MainMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\QAMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\FreshnessMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\AuthorProfileMetaboxServiceProvider;
use FP\SEO\Infrastructure\Providers\RESTServiceProvider;
use FP\SEO\Infrastructure\Providers\CLIServiceProvider;
use FP\SEO\Infrastructure\Providers\CronServiceProvider;

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
	 * Registers all service providers with automatic dependency resolution and boots them.
	 * Dependencies are resolved automatically based on get_dependencies() declarations.
	 *
	 * @return void
	 */
	public function boot(): void {
		// Register all service providers with automatic dependency resolution
		// The registry will resolve dependencies and register providers in the correct order
		$this->registry->register_with_dependencies( array(
			// Core services (foundational: Cache, Logger, Health)
			new CoreServiceProvider(),

			// Data services (repositories, migrations)
			new DataServiceProvider(),

			// Performance services (optimizations)
			new PerformanceServiceProvider(),

			// Analysis services (SEO analysis system - depends on CoreServiceProvider)
			new AnalysisServiceProvider(),

			// Metabox Services (FieldSaver, Analysis)
			new MetaboxServicesProvider(),

			// Schema Metaboxes (must be first, before main metabox)
			new SchemaMetaboxServiceProvider(),

			// Main SEO Metabox (core editor functionality - depends on MetaboxServicesProvider)
			new MainMetaboxServiceProvider(),

			// QA Metabox (Q&A pairs management)
			new QAMetaboxServiceProvider(),

			// Freshness Metabox (Temporal signals)
			new FreshnessMetaboxServiceProvider(),

			// Author Profile Fields (Authority signals - user profile fields)
			new AuthorProfileMetaboxServiceProvider(),

			// Admin Assets (must be first for admin_enqueue_scripts)
			new AdminAssetsServiceProvider(),

			// Admin Pages (Menu, Settings, Bulk Audit, Performance Dashboard)
			new AdminPagesServiceProvider(),

			// Admin UI components (Notices, Admin Bar)
			new AdminUIServiceProvider(),

			// AI services (Core AI)
			new AIServiceProvider(),

			// AI Settings (Admin AI features)
			new AISettingsServiceProvider(),

			// GEO services (conditional - only if enabled)
			new GEOServiceProvider(),

			// Redirects and HTML Sitemap (always loaded)
			new RedirectsAndSitemapServiceProvider(),

			// Integration services (GSC, Indexing - conditional)
			new IntegrationServiceProvider(),

			// Frontend services (renderers)
			new FrontendServiceProvider(),

			// Test Suite (only for admins with manage_options)
			new TestSuiteServiceProvider(),

			// REST API services
			new RESTServiceProvider(),

			// WP-CLI services (conditional - only if WP-CLI is available)
			new CLIServiceProvider(),

			// Cron services (scheduled tasks)
			new CronServiceProvider(),
		) );

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