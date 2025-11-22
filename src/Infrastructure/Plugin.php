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

use FP\SEO\Admin\AdminBarBadge;
use FP\SEO\Admin\Menu;
use FP\SEO\Admin\Notices;
use FP\SEO\Admin\SettingsPage;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Editor\Metabox;
use FP\SEO\Editor\SchemaMetaboxes;
use FP\SEO\Admin\AuthorProfileFields;
use FP\SEO\Admin\QAMetaBox;
use FP\SEO\Admin\FreshnessMetaBox;
use FP\SEO\Admin\AiFirstAjaxHandler;
use FP\SEO\Admin\BulkAiActions;
use FP\SEO\Admin\AiFirstSettingsIntegration;
use FP\SEO\SiteHealth\SeoHealth;
use FP\SEO\Utils\Assets;
use FP\SEO\Utils\PerformanceOptimizer;
use FP\SEO\Utils\PerformanceConfig;
use FP\SEO\Utils\AdvancedCache;
use FP\SEO\Utils\RateLimiter;
use FP\SEO\Utils\PerformanceMonitor;
use FP\SEO\Utils\DatabaseOptimizer;
use FP\SEO\Utils\AssetOptimizer;
use FP\SEO\Utils\HealthChecker;
use FP\SEO\Utils\Logger;
use FP\SEO\Admin\PerformanceDashboard;
use FP\SEO\Schema\AdvancedSchemaManager;
use FP\SEO\AI\AdvancedContentOptimizer;
use FP\SEO\Social\ImprovedSocialMediaManager;
use FP\SEO\Links\InternalLinkManager;
use FP\SEO\Keywords\MultipleKeywordsManager;
use FP\SEO\Front\MetaTagRenderer;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\AI\EmbeddingsGenerator;
use FP\SEO\GEO\FreshnessSignals;
use FP\SEO\GEO\CitationFormatter;
use FP\SEO\GEO\AuthoritySignals;
use FP\SEO\GEO\SemanticChunker;
use FP\SEO\GEO\EntityGraph;
use FP\SEO\GEO\MultiModalOptimizer;
use FP\SEO\GEO\TrainingDatasetFormatter;
use FP\SEO\Integrations\AutoGenerationHook;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Automation\AutoSeoOptimizer;

/**
 * Central plugin bootstrapper wiring services together.
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
	 * Sets up the container.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Retrieves the singleton plugin instance.
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
	 * Registers core hooks required for bootstrapping.
	 */
	public function init(): void {
		register_activation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'deactivate' ) );
		
		// Inizializza AssetOptimizer quando WordPress è pronto
		add_action( 'init', array( $this, 'init_asset_optimizer' ), 1 );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Load plugin translations at init.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'fp-seo-performance', false, dirname( plugin_basename( FP_SEO_PERFORMANCE_FILE ) ) . '/languages' );
	}

	/**
	 * Inizializza AssetOptimizer quando WordPress è pronto.
	 */
	public function init_asset_optimizer(): void {
		if ( function_exists( 'plugin_dir_path' ) && function_exists( 'wp_mkdir_p' ) ) {
			try {
				$asset_optimizer = $this->container->get( AssetOptimizer::class );
				$asset_optimizer->init();
			} catch ( \RuntimeException $e ) {
				// WordPress functions not available or other critical errors, skip silently
				Logger::debug( 'AssetOptimizer skipped', array( 'reason' => $e->getMessage() ) );
			} catch ( \Exception $e ) {
				// Other errors, log but don't break the plugin
				Logger::warning( 'Failed to initialize AssetOptimizer', array( 'error' => $e->getMessage() ) );
			} catch ( \Error $e ) {
				// Fatal errors, log but don't break the plugin
				Logger::error( 'Fatal error initializing AssetOptimizer', array( 'error' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * Boots plugin services after all plugins load.
	 * Implements lazy loading to reduce memory footprint on shared hosting.
	 */
	public function boot(): void {
		// Core services - always load (minimal footprint)
		$this->container->singleton( SeoHealth::class );
		$this->container->get( SeoHealth::class )->register();

		// Performance optimizer - always load
		$this->container->singleton( PerformanceOptimizer::class );
		$this->container->get( PerformanceOptimizer::class )->register();

		// Advanced Cache - always load
		$this->container->singleton( AdvancedCache::class );

		// Performance Monitor - always load
		$this->container->singleton( PerformanceMonitor::class );

		// Rate Limiter - always load
		$this->container->singleton( RateLimiter::class, function() {
			return new RateLimiter( $this->container->get( AdvancedCache::class ) );
		} );

		// Database Optimizer - always load
		$this->container->singleton( DatabaseOptimizer::class, function() {
			return new DatabaseOptimizer( $this->container->get( PerformanceMonitor::class ) );
		} );

		// Asset Optimizer - registra ma non istanzia immediatamente
		$this->container->singleton( AssetOptimizer::class, function() {
			// Verifica che le funzioni WordPress siano disponibili prima di istanziare
			if ( ! function_exists( 'plugin_dir_path' ) || ! function_exists( 'wp_mkdir_p' ) ) {
				throw new \RuntimeException( 'WordPress functions not available for AssetOptimizer' );
			}
			
			try {
				return new AssetOptimizer( FP_SEO_PERFORMANCE_FILE, $this->container->get( PerformanceMonitor::class ) );
			} catch ( \Exception $e ) {
				throw new \RuntimeException( 'Failed to create AssetOptimizer: ' . $e->getMessage(), 0, $e );
			}
		} );
		
		// AssetOptimizer viene inizializzato tramite hook 'init' per evitare errori di caricamento prematuro

		// Health Checker - always load
		$this->container->singleton( HealthChecker::class, function() {
			// Passa null per AssetOptimizer per evitare errori di inizializzazione
			$asset_optimizer = null;
			try {
				$asset_optimizer = $this->container->get( AssetOptimizer::class );
			} catch ( \Exception $e ) {
				// AssetOptimizer non disponibile, continua senza
				Logger::debug( 'AssetOptimizer not available for HealthChecker', array( 'error' => $e->getMessage() ) );
			}
			
			return new HealthChecker(
				$this->container->get( PerformanceMonitor::class ),
				$this->container->get( DatabaseOptimizer::class ),
				$asset_optimizer
			);
		} );

		// Performance Dashboard - registra ma non inizializza immediatamente
		$this->container->singleton( PerformanceDashboard::class, function() {
			// Passa null per AssetOptimizer per evitare errori di inizializzazione
			$asset_optimizer = null;
			try {
				$asset_optimizer = $this->container->get( AssetOptimizer::class );
			} catch ( \Exception $e ) {
				// AssetOptimizer non disponibile, continua senza
				Logger::debug( 'AssetOptimizer not available for PerformanceDashboard', array( 'error' => $e->getMessage() ) );
			}
			
			return new PerformanceDashboard(
				$this->container->get( HealthChecker::class ),
				$this->container->get( PerformanceMonitor::class ),
				$this->container->get( DatabaseOptimizer::class ),
				$asset_optimizer
			);
		} );
		
		// PerformanceDashboard sarà inizializzato in boot_admin_services() dopo il Menu

		// Advanced Schema Manager - registra singleton ma inizializza dopo il Menu
		$this->container->singleton( AdvancedSchemaManager::class );

		// Advanced Content Optimizer - registra singleton ma inizializza dopo il Menu
		$this->container->singleton( AdvancedContentOptimizer::class );

		// Improved Social Media Manager - registra singleton ma inizializza dopo il Menu
		$this->container->singleton( ImprovedSocialMediaManager::class );

		// Internal Link Manager - registra singleton ma inizializza dopo il Menu
		$this->container->singleton( InternalLinkManager::class );

		// Multiple Keywords Manager - registra singleton ma inizializza dopo il Menu
		$this->container->singleton( MultipleKeywordsManager::class );

		// Frontend meta tag renderer
		$this->container->singleton( MetaTagRenderer::class );

		try {
			$this->container->get( ImprovedSocialMediaManager::class )->register();
		} catch ( \Exception $e ) {
			Logger::warning( 'Failed to register ImprovedSocialMediaManager', array( 'error' => $e->getMessage() ) );
		}

		try {
			// InternalLinkManager - register but output_link_analysis is disabled in frontend
			$this->container->get( InternalLinkManager::class )->register();
		} catch ( \Exception $e ) {
			Logger::warning( 'Failed to register InternalLinkManager', array( 'error' => $e->getMessage() ) );
		}

		try {
			// MultipleKeywordsManager - only adds meta tags, should be safe
			$this->container->get( MultipleKeywordsManager::class )->register();
		} catch ( \Exception $e ) {
			Logger::warning( 'Failed to register MultipleKeywordsManager', array( 'error' => $e->getMessage() ) );
		}

		try {
			// MetaTagRenderer - essential SEO meta tags, keep active
			$this->container->get( MetaTagRenderer::class )->register();
		} catch ( \Exception $e ) {
			Logger::warning( 'Failed to register MetaTagRenderer', array( 'error' => $e->getMessage() ) );
		}

		// OpenAI Client - Always register (needed for AI features)
		$this->container->singleton( OpenAiClient::class );

		// NEW AI-FIRST SERVICES - Always load (lightweight singletons)
		$this->container->singleton( QAPairExtractor::class );
		$this->container->singleton( ConversationalVariants::class );
		$this->container->singleton( EmbeddingsGenerator::class );
		$this->container->singleton( FreshnessSignals::class );
		$this->container->singleton( CitationFormatter::class );
		$this->container->singleton( AuthoritySignals::class );
		$this->container->singleton( SemanticChunker::class );
		$this->container->singleton( EntityGraph::class );
		$this->container->singleton( MultiModalOptimizer::class );
		$this->container->singleton( TrainingDatasetFormatter::class );

		// Auto-Generation Hook (conditionally registers based on settings)
		$this->container->singleton( AutoGenerationHook::class );
		$this->container->get( AutoGenerationHook::class )->register();

		// Auto SEO Optimizer (automatically generates missing SEO fields with AI)
		$this->container->singleton( AutoSeoOptimizer::class, function() {
			return new AutoSeoOptimizer( $this->container->get( OpenAiClient::class ) );
		} );
		$this->container->get( AutoSeoOptimizer::class )->register();

		// Menu and core admin pages - must be registered early for admin_menu hook
		if ( is_admin() ) {
			// Assets - must be registered BEFORE admin_enqueue_scripts
			$this->container->singleton( Assets::class );
			$this->container->get( Assets::class )->register();
			
			$this->container->singleton( Menu::class );
			$this->container->get( Menu::class )->register();
			
			$this->container->singleton( SettingsPage::class );
			$this->container->get( SettingsPage::class )->register();
			
			$this->container->singleton( BulkAuditPage::class );
			$this->container->get( BulkAuditPage::class )->register();
			
			// Tutti i submenu devono essere registrati QUI, non in admin_init
			// Inizializza PerformanceDashboard dopo il Menu
			try {
				$this->container->get( PerformanceDashboard::class )->register();
			} catch ( \Exception $e ) {
				Logger::warning( 'Failed to register PerformanceDashboard', array( 'error' => $e->getMessage() ) );
			}

			// Inizializza Advanced Schema Manager dopo il Menu
			// Schema markup viene generato nel frontend per SEO, ma è sicuro
			try {
				$this->container->get( AdvancedSchemaManager::class )->register();
			} catch ( \Exception $e ) {
				Logger::warning( 'Failed to register AdvancedSchemaManager', array( 'error' => $e->getMessage() ) );
			}

			// Inizializza Advanced Content Optimizer dopo il Menu
			try {
				$this->container->get( AdvancedContentOptimizer::class )->register();
			} catch ( \Exception $e ) {
				Logger::warning( 'Failed to register AdvancedContentOptimizer', array( 'error' => $e->getMessage() ) );
			}

			// AI Settings tab - sempre disponibile per permettere configurazione API key
			$this->container->singleton( \FP\SEO\Admin\AiSettings::class );
			$this->container->get( \FP\SEO\Admin\AiSettings::class )->register();
			
			// Editor metaboxes - must be registered early for add_meta_boxes hook
			$this->container->singleton( Metabox::class );
			$this->container->get( Metabox::class )->register();

			// Schema metaboxes (FAQ and HowTo)
			$this->container->singleton( SchemaMetaboxes::class );
			$this->container->get( SchemaMetaboxes::class )->register();

			// NEW AI-FIRST ADMIN UI
			$this->container->singleton( QAMetaBox::class );
			$this->container->get( QAMetaBox::class )->register();

			$this->container->singleton( FreshnessMetaBox::class );
			$this->container->get( FreshnessMetaBox::class )->register();

			$this->container->singleton( AuthorProfileFields::class );
			$this->container->get( AuthorProfileFields::class )->register();

			$this->container->singleton( AiFirstAjaxHandler::class );
			$this->container->get( AiFirstAjaxHandler::class )->register();

			$this->container->singleton( BulkAiActions::class );
			$this->container->get( BulkAiActions::class )->register();

			$this->container->singleton( AiFirstSettingsIntegration::class );
			$this->container->get( AiFirstSettingsIntegration::class )->register();
		}

		// Admin services - lazy load on admin_init
		add_action( 'admin_init', array( $this, 'boot_admin_services' ) );

		// GEO services - conditional loading
		$this->boot_geo_services();
	}

	/**
	 * Boot admin services only when needed (on admin_init).
	 */
	public function boot_admin_services(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Core admin services (tutto il resto è già registrato in boot())
		$this->container->singleton( Notices::class );
		$this->container->get( Notices::class )->register();

		$this->container->singleton( AdminBarBadge::class );
		$this->container->get( AdminBarBadge::class )->register();

		// Servizi AI AJAX - solo se OpenAI API key è configurata
		$this->boot_ai_services();

		// Test Suite (only for admins)
		if ( current_user_can( 'manage_options' ) ) {
			$this->container->singleton( \FP\SEO\Admin\TestSuitePage::class );
			$this->container->get( \FP\SEO\Admin\TestSuitePage::class )->register();

			$this->container->singleton( \FP\SEO\Admin\TestSuiteAjax::class );
			$this->container->get( \FP\SEO\Admin\TestSuiteAjax::class )->register();
		}
	}


	/**
	 * Boot AI services only if API key is configured.
	 */
	private function boot_ai_services(): void {
		$options    = \FP\SEO\Utils\Options::get();
		$openai_key = $options['ai']['openai_api_key'] ?? '';

		// Only load AI AJAX handlers if API key is configured
		// (AiSettings è già registrato in boot_admin_services per permettere la configurazione)
		if ( empty( $openai_key ) ) {
			return;
		}

		$this->container->singleton( \FP\SEO\Admin\AiAjaxHandler::class );
		$this->container->get( \FP\SEO\Admin\AiAjaxHandler::class )->register();
	}

	/**
	 * Boot GEO-specific services with conditional loading.
	 */
	private function boot_geo_services(): void {
		$options     = \FP\SEO\Utils\Options::get();
		$geo_enabled = $options['geo']['enabled'] ?? false;

		// Only load GEO services if enabled
		if ( ! $geo_enabled ) {
			return;
		}

		// GeoMetaBox - registrato qui solo se GEO abilitato
		if ( is_admin() ) {
			$this->container->singleton( \FP\SEO\Admin\GeoMetaBox::class );
			$this->container->get( \FP\SEO\Admin\GeoMetaBox::class )->register();
		}

		// Frontend GEO services
		$this->container->singleton( \FP\SEO\GEO\Router::class );
		$this->container->get( \FP\SEO\GEO\Router::class )->register();

		$this->container->singleton( \FP\SEO\Front\SchemaGeo::class );
		$this->container->get( \FP\SEO\Front\SchemaGeo::class )->register();

		$this->container->singleton( \FP\SEO\Shortcodes\GeoShortcodes::class );
		$this->container->get( \FP\SEO\Shortcodes\GeoShortcodes::class )->register();

		// Admin GEO services - defer to admin_init
		add_action( 'admin_init', array( $this, 'boot_geo_admin_services' ), 20 );

		// Auto Indexing (frontend + backend)
		$this->container->singleton( \FP\SEO\Integrations\AutoIndexing::class );
		$this->container->get( \FP\SEO\Integrations\AutoIndexing::class )->register();

		// Flush rewrite rules on activation/deactivation
		register_activation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'flush_rewrites_on_activation' ) );
		register_deactivation_hook( FP_SEO_PERFORMANCE_FILE, 'flush_rewrite_rules' );
	}

	/**
	 * Boot GEO admin services with conditional GSC loading.
	 */
	public function boot_geo_admin_services(): void {
		if ( ! is_admin() ) {
			return;
		}

		$this->container->singleton( \FP\SEO\Admin\GeoSettings::class );
		$this->container->get( \FP\SEO\Admin\GeoSettings::class )->register();

		// GeoMetaBox già registrato in boot_geo_services() solo se GEO abilitato

		// Score History
		$this->container->singleton( \FP\SEO\History\ScoreHistory::class );
		$this->container->get( \FP\SEO\History\ScoreHistory::class )->register();

		// Internal Linking AJAX
		$this->container->singleton( \FP\SEO\Linking\LinkingAjax::class );
		$this->container->get( \FP\SEO\Linking\LinkingAjax::class )->register();

		// GSC Integration - only if credentials are configured
		$this->boot_gsc_services();
	}

	/**
	 * Boot Google Search Console services only if credentials configured.
	 */
	private function boot_gsc_services(): void {
		$options           = \FP\SEO\Utils\Options::get();
		$gsc_credentials   = $options['gsc']['service_account_json'] ?? '';
		$gsc_site_url      = $options['gsc']['site_url'] ?? '';

		// ALWAYS register GSC Settings tab (users need it to configure credentials!)
		$this->container->singleton( \FP\SEO\Admin\GscSettings::class );
		$this->container->get( \FP\SEO\Admin\GscSettings::class )->register();

		// Only load GSC Dashboard if credentials are configured
		if ( ! empty( $gsc_credentials ) && ! empty( $gsc_site_url ) ) {
			$this->container->singleton( \FP\SEO\Admin\GscDashboard::class );
			$this->container->get( \FP\SEO\Admin\GscDashboard::class )->register();
		}
	}

	/**
	 * Flush rewrites on activation
	 */
	public function flush_rewrites_on_activation(): void {
		// Register routes first
		$router = new \FP\SEO\GEO\Router();
		$router->add_rewrite_rules();

		// Then flush
		flush_rewrite_rules();
	}

	/**
	 * Runs activation routines.
	 */
	public function activate(): void {
		// Create database tables
		if ( class_exists( '\FP\SEO\History\ScoreHistory' ) ) {
			$score_history = new \FP\SEO\History\ScoreHistory();
			$score_history->create_table();
		}
	}

	/**
	 * Runs deactivation routines.
	 */
	public function deactivate(): void {
		// Placeholder for deactivation routines.
	}
}
