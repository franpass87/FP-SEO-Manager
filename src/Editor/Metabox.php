<?php
/**
 * Editor metabox integration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Editor\Services\SeoFieldsSaver;
use FP\SEO\Editor\Services\AnalysisDataService;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\PostTypes;
use FP\SEO\Integrations\GscData;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use WP_Post;
use function absint;
use function admin_url;
use function array_filter;
use function array_map;
use function check_ajax_referer;
use function current_user_can;
use function delete_post_meta;
use function get_current_screen;
use function get_post_meta;
use function in_array;
use function esc_url_raw;
use function is_array;
use function sanitize_text_field;
use function update_post_meta;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_kses_post;
use function wp_localize_script;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_strip_all_tags;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Provides the editor metabox with live analysis output.
 */
class Metabox {
	private const NONCE_ACTION = 'fp_seo_performance_meta';
	private const NONCE_FIELD  = 'fp_seo_performance_nonce';
	public const AJAX_ACTION  = 'fp_seo_performance_analyze';
	public const AJAX_SAVE_FIELDS = 'fp_seo_performance_save_fields';
	public const META_EXCLUDE         = '_fp_seo_performance_exclude';
	public const META_FOCUS_KEYWORD   = '_fp_seo_focus_keyword';
	public const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * @var MetaboxRenderer
	 */
	private $renderer;


	/**
	 * @var MetaboxDiagnostics
	 */
	private $diagnostics;

	/**
	 * @var \FP\SEO\Editor\Handlers\AjaxHandler|null
	 */
	private $ajax_handler;

	/**
	 * @var \FP\SEO\Editor\Managers\AssetsManager|null
	 */
	private $assets_manager;

	/**
	 * @var \FP\SEO\Editor\Services\AnalysisRunner|null
	 */
	private $analysis_runner;

	/**
	 * @var \FP\SEO\Editor\Services\AnalysisDataService|null
	 */
	private $analysis_data_service;

	/**
	 * @var SeoFieldsSaver|null
	 */
	private $fields_saver;

	/**
	 * @var \FP\SEO\Editor\Scripts\InlineScriptsManager|null
	 */
	private $inline_scripts_manager;

	/**
	 * @var \FP\SEO\Editor\Styles\MetaboxStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var HookManagerInterface
	 */
	private $hook_manager;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var OptionsInterface
	 */
	private $options;

	/**
	 * Lazy service loader for heavy services (AI, GSC).
	 *
	 * @var \FP\SEO\Editor\Services\LazyServiceLoader|null
	 */
	private $lazy_loader;

	/**
	 * Validator instance.
	 *
	 * @var \FP\SEO\Editor\Services\MetaboxValidator|null
	 */
	private $validator;

	/**
	 * State manager instance.
	 *
	 * @var \FP\SEO\Editor\Services\MetaboxStateManager|null
	 */
	private $state_manager;

	/**
	 * Costruttore - registra gli hook immediatamente e inizializza il renderer
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 * @param LoggerInterface      $logger       Logger instance.
	 * @param OptionsInterface     $options      Options instance.
	 */
	public function __construct( HookManagerInterface $hook_manager, LoggerInterface $logger, OptionsInterface $options ) {
		// CRITICAL DEBUG: Log constructor call
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: Metabox::__construct() CALLED' );
		}
		
		$this->hook_manager = $hook_manager;
		$this->logger       = $logger;
		$this->options      = $options;
		// REGISTRA GLI HOOK IMMEDIATAMENTE nel costruttore per garantire che vengano sempre registrati
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::__construct() called' );
			error_log( 'FP SEO DEBUG: Metabox::__construct() - logger and options set' );
		}
		
		// INIZIALIZZA IL RENDERER NEL COSTRUTTORE per garantire che sia sempre disponibile
		// anche se register() non viene chiamato o fallisce
		// Usa try/catch per permettere la creazione dell'oggetto anche se il renderer fallisce
		// Il renderer verrà reinizializzato quando necessario in render()
		try {
			$this->initialize_renderer();
		} catch ( \Throwable $e ) {
			// Log errore ma permette la creazione dell'oggetto
			// Il renderer verrà reinizializzato quando necessario
			$this->logger->error( 'FP SEO: Failed to initialize MetaboxRenderer in constructor', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			$this->renderer = null;
		}

		// Initialize AJAX handler
		if ( class_exists( 'FP\SEO\Editor\Handlers\AjaxHandler' ) ) {
			try {
				$this->ajax_handler = new \FP\SEO\Editor\Handlers\AjaxHandler( $this, $this->hook_manager );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize AjaxHandler', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->ajax_handler = null;
			}
		}

		// Initialize Assets Manager
		if ( class_exists( 'FP\SEO\Editor\Managers\AssetsManager' ) ) {
			try {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO DEBUG: Metabox constructor - initializing AssetsManager' );
				}
				$this->assets_manager = new \FP\SEO\Editor\Managers\AssetsManager( $this );
				// Register immediately in constructor to ensure hook is registered early
				if ( $this->assets_manager ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'FP SEO DEBUG: Metabox constructor - calling AssetsManager->register()' );
					}
					$this->assets_manager->register();
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'FP SEO DEBUG: Metabox constructor - AssetsManager->register() called' );
					}
				}
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->debug( 'FP SEO: AssetsManager initialized and registered in constructor', array(
						'class' => get_class( $this->assets_manager ),
					) );
				}
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO DEBUG: Metabox constructor - ERROR initializing AssetsManager: ' . $e->getMessage() );
				}
				$this->logger->error( 'FP SEO: Failed to initialize AssetsManager', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->assets_manager = null;
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO DEBUG: Metabox constructor - AssetsManager class not found' );
			}
		}

		// Initialize Analysis Runner
		if ( class_exists( 'FP\SEO\Editor\Services\AnalysisRunner' ) ) {
			try {
				$this->analysis_runner = new \FP\SEO\Editor\Services\AnalysisRunner();
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize AnalysisRunner', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->analysis_runner = null;
			}
		}

		// Initialize AnalysisDataService
		if ( class_exists( 'FP\SEO\Editor\Services\AnalysisDataService' ) ) {
			try {
				$this->analysis_data_service = new AnalysisDataService();
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize AnalysisDataService', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->analysis_data_service = null;
			}
		}

		// Initialize SEO Fields Saver
		$this->fields_saver = new SeoFieldsSaver();

		// Initialize Inline Scripts Manager
		if ( class_exists( 'FP\SEO\Editor\Scripts\InlineScriptsManager' ) ) {
			try {
				$this->inline_scripts_manager = new \FP\SEO\Editor\Scripts\InlineScriptsManager( $this );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize InlineScriptsManager', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->inline_scripts_manager = null;
			}
		}

		// Initialize Styles Manager
		if ( class_exists( 'FP\SEO\Editor\Styles\MetaboxStylesManager' ) ) {
			try {
				$this->styles_manager = new \FP\SEO\Editor\Styles\MetaboxStylesManager( $this );
				// Register hook to inject styles
				if ( $this->hook_manager ) {
					$this->hook_manager->add_action( 'admin_head', array( $this->styles_manager, 'inject' ) );
				}
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize MetaboxStylesManager', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				$this->styles_manager = null;
			}
		}
		
		if ( class_exists( 'FP\SEO\Editor\MetaboxDiagnostics' ) ) {
			try {
				$this->diagnostics = new MetaboxDiagnostics();
			} catch ( \Throwable $e ) {
				$this->logger->error( 'FP SEO: Failed to initialize MetaboxDiagnostics', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
				// Create a dummy object to prevent fatal errors
				$this->diagnostics = new class {
					public function get_homepage_diagnostics( WP_Post $post ): array { return array(); }
					public function render_diagnostics( array $diagnostics ): string { return ''; }
				};
			}
		} else {
			$this->logger->error( 'FP SEO: MetaboxDiagnostics class not found' );
			// Create a dummy object to prevent fatal errors
			$this->diagnostics = new class {
				public function get_homepage_diagnostics( WP_Post $post ): array { return array(); }
				public function render_diagnostics( array $diagnostics ): string { return ''; }
			};
		}

		// Registra hook di salvataggio
		$this->register_hooks();
		
		// IMPORTANTE: Registra anche l'hook add_meta_boxes nel costruttore
		// Questo garantisce che il metabox venga sempre registrato, anche se register() non viene chiamato
		// Priorità 5 per essere registrato tra i primi metabox (prima di altri plugin)
		$is_admin_uri = false;
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$is_admin_uri = strpos( $request_uri, '/wp-admin/' ) !== false;
		}
		if ( is_admin() || ( defined( 'WP_ADMIN' ) && WP_ADMIN ) || $is_admin_uri ) {
			$this->hook_manager->add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: add_meta_boxes hook registered in __construct()' );
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::__construct() completed - hooks and renderer should be initialized', array(
				'renderer_initialized' => $this->renderer !== null,
			) );
		}
	}

	/**
	 * Inizializza il renderer con gestione errori robusta.
	 * Chiamato dal costruttore per garantire che il renderer sia sempre disponibile.
	 *
	 * @return void
	 */
	private function initialize_renderer(): void {
		// Se il renderer è già inizializzato, non fare nulla
		if ( $this->renderer !== null ) {
			return;
		}

		// FORZA IL CARICAMENTO DIRETTO DEL FILE - nessun fallback, deve funzionare
		$renderer_file = __DIR__ . '/MetaboxRenderer.php';
		
		if ( ! file_exists( $renderer_file ) ) {
			$error_msg = sprintf(
				'FP SEO: MetaboxRenderer file not found at %s',
				$renderer_file
			);
			$this->logger->error( $error_msg );
			throw new \RuntimeException( $error_msg );
		}

		// Carica il file direttamente se la classe non esiste
		if ( ! class_exists( MetaboxRenderer::class, false ) ) {
			require_once $renderer_file;
		}

		// Verifica che la classe esista dopo il caricamento
		if ( ! class_exists( MetaboxRenderer::class, false ) ) {
			$error_msg = sprintf(
				'FP SEO: MetaboxRenderer class not found after loading file. Expected: %s',
				MetaboxRenderer::class
			);
			$this->logger->error( $error_msg, array(
				'file' => $renderer_file,
				'file_exists' => file_exists( $renderer_file ),
				'is_readable' => is_readable( $renderer_file ),
			) );
			throw new \RuntimeException( $error_msg );
		}

		// Verifica e carica tutte le dipendenze necessarie prima di istanziare MetaboxRenderer
		$check_help_text_file = __DIR__ . '/CheckHelpText.php';
		if ( ! file_exists( $check_help_text_file ) ) {
			$error_msg = sprintf(
				'FP SEO: CheckHelpText file not found at %s',
				$check_help_text_file
			);
			$this->logger->error( $error_msg );
			throw new \RuntimeException( $error_msg );
		}

		// Carica CheckHelpText se necessario
		if ( ! class_exists( 'FP\\SEO\\Editor\\CheckHelpText', false ) ) {
			require_once $check_help_text_file;
		}

		// Verifica che le classi utilizzate da MetaboxRenderer siano disponibili
		$required_classes = array(
			'FP\\SEO\\Utils\\Logger' => __DIR__ . '/../Utils/Logger.php',
			'FP\\SEO\\Utils\\Options' => __DIR__ . '/../Utils/Options.php',
			'FP\\SEO\\Integrations\\GscClient' => __DIR__ . '/../Integrations/GscClient.php',
			'FP\\SEO\\Integrations\\GscData' => __DIR__ . '/../Integrations/GscData.php',
		);

		foreach ( $required_classes as $class_name => $class_file ) {
			if ( ! class_exists( $class_name, false ) ) {
				if ( file_exists( $class_file ) ) {
					require_once $class_file;
				} else {
					$error_msg = sprintf(
						'FP SEO: Required class file not found: %s (for %s)',
						$class_file,
						$class_name
					);
					$this->logger->error( $error_msg );
					throw new \RuntimeException( $error_msg );
				}
			}

			// Verifica che la classe esista dopo il caricamento
			if ( ! class_exists( $class_name, false ) ) {
				$error_msg = sprintf(
					'FP SEO: Class %s not found after loading file %s',
					$class_name,
					$class_file
				);
				$this->logger->error( $error_msg );
				throw new \RuntimeException( $error_msg );
			}
		}

		// Istanzia il renderer usando il container DI per ottenere SectionRegistry
		try {
			// Prova a ottenere il renderer dal container DI (che include SectionRegistry)
			try {
				$container = \FP\SEO\Infrastructure\Plugin::instance()->get_container();
				$this->renderer = $container->get( MetaboxRenderer::class );
			} catch ( \Throwable $container_error ) {
				// Fallback: crea renderer senza SectionRegistry (per backward compatibility)
				$this->logger->warning( 'FP SEO: Could not get MetaboxRenderer from container, using fallback', array(
					'error' => $container_error->getMessage(),
				) );
				$this->renderer = new MetaboxRenderer( null );
			}
		} catch ( \Throwable $e ) {
			$error_msg = sprintf(
				'FP SEO: Failed to instantiate MetaboxRenderer: %s',
				$e->getMessage()
			);
			$this->logger->error( $error_msg, array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'check_help_text_file' => $check_help_text_file,
				'check_help_text_exists' => file_exists( $check_help_text_file ),
				'check_help_text_class_exists' => class_exists( 'FP\\SEO\\Editor\\CheckHelpText', false ),
			) );
			throw new \RuntimeException( $error_msg, 0, $e );
		}

		// Verifica che il renderer sia stato creato correttamente
		if ( ! $this->renderer instanceof MetaboxRenderer ) {
			$error_msg = sprintf(
				'FP SEO: MetaboxRenderer instance invalid. Expected: %s, Got: %s',
				MetaboxRenderer::class,
				get_class( $this->renderer )
			);
			$this->logger->error( $error_msg );
			throw new \RuntimeException( $error_msg );
		}

		// Verifica che il renderer abbia il metodo render()
		if ( ! method_exists( $this->renderer, 'render' ) ) {
			$error_msg = 'FP SEO: MetaboxRenderer instance missing render() method';
			$this->logger->error( $error_msg, array(
				'methods' => get_class_methods( $this->renderer ),
			) );
			throw new \RuntimeException( $error_msg );
		}

		// Log successo sempre (non solo in debug mode) per verificare che funzioni
		$this->logger->info( 'FP SEO: MetaboxRenderer initialized successfully', array(
			'version' => defined( 'FP_SEO_PERFORMANCE_VERSION' ) ? FP_SEO_PERFORMANCE_VERSION : 'unknown',
			'renderer_class' => get_class( $this->renderer ),
			'has_render_method' => method_exists( $this->renderer, 'render' ),
			'renderer_file' => __DIR__ . '/MetaboxRenderer.php',
			'file_exists' => file_exists( __DIR__ . '/MetaboxRenderer.php' ),
		) );
	}

	/**
	 * Registra gli hook di salvataggio - chiamato dal costruttore e da register()
	 */
	private function register_hooks(): void {
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		// This is more efficient than registering generic hooks and exiting early
		$supported_types = $this->get_supported_post_types();
		
		foreach ( $supported_types as $post_type ) {
			// Register post-type-specific hooks to avoid calling hooks for unsupported types
			// Using only save_post hook to avoid interference with WordPress core saving
			// Multiple hooks (edit_post, wp_insert_post) were being called even when just opening editor
			$this->hook_manager->add_action( 'save_post_' . $post_type, array( $this, 'save_meta' ), 10, 3 );
			// DISABLED: These hooks were causing auto-draft creation when opening editor
			// if ( ! has_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ) ) ) {
			// 	add_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ), 10, 2 );
			// }
			// if ( ! has_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ) ) ) {
			// 	add_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ), 10, 3 );
			// }
		}
		
		// NOTE: All homepage protection workarounds have been removed.
		// The root cause was PerformanceOptimizer interfering with WordPress core post meta retrieval.
		// The fix was to disable PerformanceOptimizer entirely.
		
		// Log registrazione solo in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox hooks registered in register_hooks()', array(
				'hooks' => array(
					'save_post' => array( 1, 5, 99 ),
					'edit_post' => array( 1, 99 ),
					'wp_insert_post' => array( 10 ),
					'wp_insert_post_data' => array( 10 ),
				),
			) );
		}
	}

	/**
	 * Hooks WordPress actions for registering and saving the metabox.
	 */
	public function register(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'FP SEO: Metabox::register() called', array(
				'assets_manager_available' => $this->assets_manager !== null,
				'assets_manager_class' => $this->assets_manager ? get_class( $this->assets_manager ) : 'null',
			) );
		}
	// CRITICAL DEBUG: Log to error log to verify method is called
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Metabox::register() CALLED' );
	}
		
		// Log chiamata al metodo register
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'FP SEO: Metabox::register() called', array(
				'admin_context' => is_admin(),
				'hook' => current_filter(),
			) );
		}
		
		// Il renderer viene già inizializzato nel costruttore per garantire che sia sempre disponibile
		// Qui verifichiamo solo che sia stato inizializzato correttamente e tentiamo di reinizializzarlo se necessario
		if ( $this->renderer === null ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->warning( 'FP SEO: Renderer is null in register(), attempting to reinitialize' );
			}
			try {
				$this->initialize_renderer();
			} catch ( \Throwable $e ) {
				// Se l'inizializzazione fallisce, logga l'errore ma non bloccare register()
				// Il renderer verrà reinizializzato quando necessario in render()
				$this->logger->error( 'FP SEO: Failed to reinitialize MetaboxRenderer in register()', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
		
		// Log stato finale del renderer
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'FP SEO: Metabox::register() completed', array(
				'renderer_initialized' => $this->renderer !== null,
				'renderer_class' => $this->renderer ? get_class( $this->renderer ) : 'null',
			) );
		}
		
		// L'hook add_meta_boxes è già registrato nel costruttore per garantire che sia sempre presente
		// Qui verifichiamo solo se è già registrato (potrebbe essere duplicato, ma WordPress lo gestisce)
		// Non lo registriamo di nuovo per evitare duplicati
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			global $wp_filter;
			$already_registered = false;
			if ( isset( $wp_filter['add_meta_boxes'] ) ) {
				foreach ( $wp_filter['add_meta_boxes']->callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook ) {
						if ( is_array( $hook['function'] ) && 
						     is_object( $hook['function'][0] ) && 
						     $hook['function'][0] === $this && 
						     $hook['function'][1] === 'add_meta_box' ) {
							$already_registered = true;
							break 2;
						}
					}
				}
			}
			
		if ( ! $already_registered ) {
				// Se per qualche motivo non è registrato, registralo qui come fallback
				$this->hook_manager->add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->debug( 'FP SEO: add_meta_boxes hook registered in register() (fallback)' );
				}
			}
		}
		
		// Gli hook di salvataggio sono già registrati nel costruttore
		// Verifica che non siano già stati registrati prima di registrarli di nuovo
		$this->register_hooks();
		
		// Questo permette al salvataggio di funzionare anche se il rendering fallisce
		try {
			// Hook per REST API (Gutenberg) - registra per tutti i post types supportati
			$post_types = PostTypes::analyzable();
			foreach ( $post_types as $post_type ) {
				$this->hook_manager->add_action( 'rest_after_insert_' . $post_type, array( $this, 'save_meta_rest' ), 10, 3 );
			}
			
			// REST meta fields registration moved to REST/Controllers/MetaController
			// This is now handled by RESTServiceProvider
			
			// Hook pre_post_update rimosso - usiamo solo save_post per evitare doppi salvataggi
			// $this->hook_manager->add_filter( 'pre_post_update', array( $this, 'save_meta_pre_update' ), 5, 2 );
			
			// AssetsManager is now registered in constructor for early hook registration
			// Only use fallback if AssetsManager was not available during initialization
			if ( ! $this->assets_manager ) {
				// Fallback to direct method if AssetsManager not available
				// Use priority 999 to ensure it runs AFTER conditional_asset_loading (priority 15)
				$this->hook_manager->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 999, 0 );
			}
			
			// Register AJAX handlers via modular handler
			if ( $this->ajax_handler ) {
				$this->ajax_handler->register();
			} else {
				// Fallback to direct registration if handler not available
				$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
				$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_SAVE_FIELDS, array( $this, 'handle_save_fields_ajax' ) );
			}
			
			// Register inline scripts manager
			if ( $this->inline_scripts_manager ) {
				$this->hook_manager->add_action( 'admin_head', array( $this->inline_scripts_manager, 'inject' ) );
			} else {
				// Fallback to original method
				$this->hook_manager->add_action( 'admin_head', array( $this, 'inject_modern_styles' ) );
			}
		} catch ( \Throwable $e ) {
			// Se anche la registrazione degli hook fallisce, logga ma non bloccare
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->error( 'FP SEO: Failed to register metabox hooks', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
	}

	/**
	 * Adds the metabox to supported post types.
	 * 
	 * ORDINE METABOX LOGICO:
	 * 1. SEO Performance (normal, high) - PRINCIPALE - deve essere tra i primi
	 * 2. Altri metabox del plugin (normal, default) - se presenti
	 * 3. Metabox secondari (side, default) - se presenti
	 */
	public function add_meta_box(): void {
		// FORCE: Enqueue assets directly when metabox is added
		// This ensures scripts are loaded even if admin_enqueue_scripts has already fired
		global $post;
		if ( ! $post ) {
			return;
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: add_meta_box() called for post_id=' . $post->ID );
	}
		
		// DISABLED: wp_enqueue_media() was causing interference with featured image metabox
		// WordPress already loads wp.media when needed for featured image functionality
		// Calling it again here with priority 5 (add_meta_boxes) was resetting _thumbnail_id to -1
		// The fix is to let WordPress handle wp.media loading natively
		/*
		$screen = get_current_screen();
		if ( $screen && 'post' === $screen->base ) {
			wp_enqueue_media();
		}
		*/
		
		// Always enqueue scripts
		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
		wp_enqueue_script( 'fp-seo-performance-serp-preview' );
		wp_enqueue_script( 'fp-seo-performance-ai-generator' );
		wp_enqueue_script( 'fp-seo-performance-metabox-ai-fields' );
		
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Scripts enqueued in add_meta_box(), checking status...' );
		error_log( 'FP SEO DEBUG: Script registered: ' . ( wp_script_is( 'fp-seo-performance-editor', 'registered' ) ? 'yes' : 'no' ) );
		error_log( 'FP SEO DEBUG: Script enqueued: ' . ( wp_script_is( 'fp-seo-performance-editor', 'enqueued' ) ? 'yes' : 'no' ) );
	}
		
		// Also call enqueue_assets to ensure localization happens
		if ( $this->assets_manager ) {
			$this->assets_manager->enqueue_assets();
		}
		
		try {
			$post_types = $this->get_supported_post_types();
			if ( ! is_array( $post_types ) || empty( $post_types ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->warning( 'FP SEO: No supported post types found', array( 'post_types' => $post_types ) );
				}
				return;
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: Registering metabox for post types', array(
					'post_types' => $post_types,
				) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->error( 'FP SEO: Error getting supported post types', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
			return;
		}
		
		foreach ( $post_types as $post_type ) {
			// Remove native WordPress excerpt metabox to avoid duplication
			// (we have our own excerpt field in SEO Performance metabox with better UX)
			remove_meta_box( 'postexcerpt', $post_type, 'normal' );
			remove_meta_box( 'postexcerpt', $post_type, 'side' );
			// Remove native slug box to prevent duplicate slug editors
			remove_meta_box( 'slugdiv', $post_type, 'normal' );
			remove_meta_box( 'slugdiv', $post_type, 'advanced' );
			remove_meta_box( 'slugdiv', $post_type, 'side' );
			
			add_meta_box(
				'fp-seo-performance-metabox',
				__( 'SEO Performance', 'fp-seo-performance' ),
				array( $this, 'render' ),
				$post_type,
				'normal', // Posizione: colonna principale (normal = prima della sidebar)
				'high'    // Priorità: alta (appare tra i primi metabox)
			);
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: Metabox registered for post type', array(
					'post_type' => $post_type,
				) );
			}
		}
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 */
	public function enqueue_assets(): void {
		// Use static flag to prevent multiple calls
		static $called = false;
	if ( $called ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: Metabox::enqueue_assets() already called, skipping' );
		}
		return;
	}
	$called = true;

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Metabox::enqueue_assets() CALLED - FORCING ENQUEUE' );
		error_log( 'FP SEO DEBUG: Current filter: ' . current_filter() );
		error_log( 'FP SEO DEBUG: is_admin: ' . ( is_admin() ? 'yes' : 'no' ) );
	}

	// TEMPORARY: Force enqueue without conditions for testing
	global $post;
	$screen = get_current_screen();
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Screen: ' . ( $screen ? $screen->base : 'null' ) . ', Post: ' . ( $post ? $post->ID : 'null' ) );
	}
		
		// DISABLED: wp_enqueue_media() was causing interference with featured image metabox
		// WordPress already loads wp.media when needed for featured image functionality
		// Calling it here was resetting _thumbnail_id to -1
		/*
		if ( $screen && 'post' === $screen->base ) {
			wp_enqueue_media();
		}
		*/
		
	// Enqueue scripts
	wp_enqueue_style( 'fp-seo-performance-admin' );
	wp_enqueue_script( 'fp-seo-performance-editor' );
	wp_enqueue_script( 'fp-seo-performance-serp-preview' );
	wp_enqueue_script( 'fp-seo-performance-ai-generator' );
	wp_enqueue_script( 'fp-seo-performance-metabox-ai-fields' );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Scripts enqueued via wp_enqueue_script' );
	}
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'FP SEO DEBUG: Metabox::enqueue_assets() - Scripts enqueued' );
	}

		// Prepara i dati per il JavaScript PRIMA che il module si carichi
		$options  = $this->options->get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			try {
			$analysis = $this->run_analysis_for_post( $post );
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Error running analysis in enqueue_assets', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Fatal error running analysis in enqueue_assets', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			}
		}

		// Get AI configuration
		$ai_enabled = $this->options->get_option( 'ai.enable_auto_generation', true );
		$api_key    = $this->options->get_option( 'ai.openai_api_key', '' );
		
		// Debug: Verify API key retrieval
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$all_options = Options::get();
			$this->logger->debug( 'FP SEO: AI configuration check in Metabox', array(
				'ai_enabled' => $ai_enabled,
				'api_key_length' => strlen( $api_key ),
				'api_key_empty' => empty( $api_key ),
				'ai_section_exists' => isset( $all_options['ai'] ),
				'ai_openai_api_key_exists' => isset( $all_options['ai']['openai_api_key'] ),
				'ai_openai_api_key_length' => isset( $all_options['ai']['openai_api_key'] ) ? strlen( $all_options['ai']['openai_api_key'] ) : 0,
				'api_key_via_get_option' => strlen( $this->options->get_option( 'ai.openai_api_key', '' ) ),
			) );
		}
		
		// Use lazy loader to check OpenAI configuration (without loading full client)
		$lazy_loader = $this->get_lazy_loader();
		$is_configured = $lazy_loader->is_openai_configured();
		
		// Use the more reliable check from lazy loader
		$api_key_present = $is_configured || ! empty( $api_key );

		// Localizza lo script per renderlo disponibile al module
		$localized_data = array(
				'postId'   => (int) $post->ID,
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
			'saveNonce' => wp_create_nonce( self::AJAX_SAVE_FIELDS ),
			'saveAction' => self::AJAX_SAVE_FIELDS,
				'enabled'  => $enabled,
				'excluded' => $excluded,
			'aiEnabled' => $ai_enabled,
			'apiKeyPresent' => $api_key_present,
			'debug'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'initial'  => $analysis,
				'labels'   => array(
					'score'      => __( 'SEO Score', 'fp-seo-performance' ),
					'indicators' => __( 'Analisi SEO', 'fp-seo-performance' ),
					'notes'      => __( 'Raccomandazioni', 'fp-seo-performance' ),
					'none'       => __( 'Tutti gli indicatori sono ottimali.', 'fp-seo-performance' ),
					'disabled'   => __( 'Analizzatore disabilitato nelle impostazioni.', 'fp-seo-performance' ),
					'excluded'   => __( 'This content is excluded from SEO analysis.', 'fp-seo-performance' ),
					'loading'    => __( 'Analyzing content…', 'fp-seo-performance' ),
					'error'      => __( 'Unable to analyze content. Please try again.', 'fp-seo-performance' ),
				),
				'legend'   => array(
					Result::STATUS_PASS => __( 'Ottimo', 'fp-seo-performance' ),
					Result::STATUS_WARN => __( 'Attenzione', 'fp-seo-performance' ),
					Result::STATUS_FAIL => __( 'Critico', 'fp-seo-performance' ),
				),
		);

		wp_localize_script(
			'fp-seo-performance-editor',
			'fpSeoPerformanceMetabox',
			$localized_data
		);

		// Also localize for the AI fields script
		wp_localize_script(
			'fp-seo-performance-metabox-ai-fields',
			'fpSeoPerformanceMetabox',
			$localized_data
		);
	}

	/**
	 * Inject modern styles in admin head
	 */
	public function inject_modern_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		
		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}
		
		?>
		<script>
		// Clean up any text content from indicator icons (cache fix)
		document.addEventListener('DOMContentLoaded', function() {
			// CRITICAL: Fix homepage title if it shows "Bozza automatica"
			<?php
			$page_on_front_id = (int) get_option( 'page_on_front' );
			if ( $page_on_front_id > 0 ) {
				$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
				if ( $requested_post_id === $page_on_front_id ) {
					$homepage = get_post( $page_on_front_id );
					if ( $homepage instanceof WP_Post && $homepage->post_title !== 'Bozza automatica' ) {
						?>
						(function() {
							const postIdInput = document.querySelector('#post_ID');
							const titleInput = document.querySelector('#title');
							const expectedPostId = <?php echo esc_js( $page_on_front_id ); ?>;
							const expectedTitle = <?php echo wp_json_encode( $homepage->post_title ); ?>;
							
							if (postIdInput && titleInput) {
								const currentPostId = parseInt(postIdInput.value, 10);
								const currentTitle = titleInput.value;
								
								// If post ID is correct but title is wrong, fix it
								if (currentPostId === expectedPostId && currentTitle === 'Bozza automatica') {
									titleInput.value = expectedTitle;
									
									// Also update the editor if it exists (Gutenberg)
									if (window.wp && window.wp.data && window.wp.data.dispatch) {
										try {
											window.wp.data.dispatch('core/editor').editPost({ title: expectedTitle });
										} catch(e) {
											// Gutenberg might not be loaded yet
										}
									}
									
									if (window.fpSeoPerformanceMetabox && window.fpSeoPerformanceMetabox.debug) console.log('FP SEO: Fixed homepage title from "Bozza automatica" to "' + expectedTitle + '"');
								}
							}
						})();
						<?php
					}
				}
			}
			?>
			
			const icons = document.querySelectorAll('.fp-seo-performance-indicator__icon');
			icons.forEach(function(icon) {
				icon.textContent = '';
			});

			// Help Banner - Close functionality
			const helpBanner = document.querySelector('.fp-seo-metabox-help-banner');
			const closeButton = document.querySelector('.fp-seo-metabox-help-banner__close');
			
			if (helpBanner && closeButton) {
				// Check if banner was previously closed
				const bannerClosed = localStorage.getItem('fp_seo_help_banner_closed');
				if (bannerClosed === 'true') {
					helpBanner.classList.add('hidden');
				}

				closeButton.addEventListener('click', function(e) {
					e.preventDefault();
					helpBanner.style.animation = 'slideUp 0.3s ease';
					setTimeout(function() {
						helpBanner.classList.add('hidden');
						// Remember user preference
						localStorage.setItem('fp_seo_help_banner_closed', 'true');
					}, 300);
				});
			}

			// Help Toggle - Expand/Collapse check help
			const helpToggles = document.querySelectorAll('[data-help-toggle]');
			helpToggles.forEach(function(toggle) {
				toggle.addEventListener('click', function(e) {
					e.preventDefault();
					const checkItem = toggle.closest('.fp-seo-performance-analysis-item');
					const helpContent = checkItem.querySelector('[data-help-content]');
					
					if (helpContent) {
						const isVisible = helpContent.style.display !== 'none';
						if (isVisible) {
							helpContent.style.animation = 'collapseUp 0.3s ease';
							setTimeout(function() {
								helpContent.style.display = 'none';
							}, 300);
							toggle.setAttribute('title', '<?php esc_attr_e( 'Mostra aiuto', 'fp-seo-performance' ); ?>');
						} else {
							helpContent.style.display = 'block';
							helpContent.style.animation = 'expandDown 0.3s ease';
							toggle.setAttribute('title', '<?php esc_attr_e( 'Nascondi aiuto', 'fp-seo-performance' ); ?>');
						}
					}
				});
			});

			// Tooltip functionality (simple title attribute for now)
			const tooltipTriggers = document.querySelectorAll('.fp-seo-tooltip-trigger');
			tooltipTriggers.forEach(function(trigger) {
				const tooltipText = trigger.getAttribute('data-tooltip');
				if (tooltipText) {
					trigger.setAttribute('title', tooltipText);
				}
			});
		});

		// Add collapseUp animation
		const style = document.createElement('style');
		style.textContent = `
			@keyframes collapseUp {
				from {
					opacity: 1;
					max-height: 500px;
				}
				to {
					opacity: 0;
					max-height: 0;
					padding-top: 0;
					padding-bottom: 0;
				}
			}
			@keyframes slideUp {
				from {
					opacity: 1;
					transform: translateY(0);
				}
				to {
					opacity: 0;
					transform: translateY(-10px);
				}
			}
		`;
		document.head.appendChild(style);

		// Character counters for SEO Title and Meta Description
		document.addEventListener('DOMContentLoaded', function() {
			// SEO Title counter
			const seoTitleField = document.getElementById('fp-seo-title');
			const seoTitleCounter = document.getElementById('fp-seo-title-counter');
			
			if (seoTitleField && seoTitleCounter) {
				function updateTitleCounter() {
					const length = seoTitleField.value.length;
					seoTitleCounter.textContent = length + '/60';
					
					// Color coding: green (50-60), orange (60-70), red (>70)
					if (length >= 50 && length <= 60) {
						seoTitleCounter.style.color = '#10b981'; // Green
					} else if (length > 60 && length <= 70) {
						seoTitleCounter.style.color = '#f59e0b'; // Orange
					} else if (length > 70) {
						seoTitleCounter.style.color = '#ef4444'; // Red
					} else {
						seoTitleCounter.style.color = '#6b7280'; // Gray
					}
				}
				
				// Initialize counter
				updateTitleCounter();
				
				// Update on input
				seoTitleField.addEventListener('input', updateTitleCounter);
			}
			
			// Meta Description counter
			const metaDescField = document.getElementById('fp-seo-meta-description');
			const metaDescCounter = document.getElementById('fp-seo-meta-description-counter');
			
			if (metaDescField && metaDescCounter) {
				function updateDescCounter() {
					const length = metaDescField.value.length;
					metaDescCounter.textContent = length + '/160';
					
					// Color coding: green (150-160), orange (160-180), red (>180)
					if (length >= 150 && length <= 160) {
						metaDescCounter.style.color = '#10b981'; // Green
					} else if (length > 160 && length <= 180) {
						metaDescCounter.style.color = '#f59e0b'; // Orange
					} else if (length > 180) {
						metaDescCounter.style.color = '#ef4444'; // Red
					} else {
						metaDescCounter.style.color = '#6b7280'; // Gray
					}
				}
				
			// Initialize counter
			updateDescCounter();
			
			// Update on input
			metaDescField.addEventListener('input', updateDescCounter);
		}
		
		// Slug counter (word count)
		const slugField = document.getElementById('fp-seo-slug');
		const slugCounter = document.getElementById('fp-seo-slug-counter');
		
		if (slugField && slugCounter) {
			function updateSlugCounter() {
				const text = slugField.value.trim();
				const words = text ? text.split('-').filter(w => w.length > 0).length : 0;
				slugCounter.textContent = words + ' parole';
				
				// Color coding: green (3-5 words), orange (6-8), red (>8)
				if (words >= 3 && words <= 5) {
					slugCounter.style.color = '#10b981'; // Green
				} else if (words > 5 && words <= 8) {
					slugCounter.style.color = '#f59e0b'; // Orange
				} else if (words > 8) {
					slugCounter.style.color = '#ef4444'; // Red
				} else {
					slugCounter.style.color = '#6b7280'; // Gray
				}
			}
			
			// Initialize counter
			updateSlugCounter();
			
			// Update on input
			slugField.addEventListener('input', updateSlugCounter);
		}
		
		// Excerpt counter and Gutenberg sync
		const excerptField = document.getElementById('fp-seo-excerpt');
		const excerptCounter = document.getElementById('fp-seo-excerpt-counter');
		
		if (excerptField && excerptCounter) {
			function updateExcerptCounter() {
				const length = excerptField.value.length;
				excerptCounter.textContent = length + '/150';
				
				// Color coding: green (100-150), orange (150-200), red (>200)
				if (length >= 100 && length <= 150) {
					excerptCounter.style.color = '#10b981'; // Green
				} else if (length > 150 && length <= 200) {
					excerptCounter.style.color = '#f59e0b'; // Orange
				} else if (length > 200) {
					excerptCounter.style.color = '#ef4444'; // Red
				} else {
					excerptCounter.style.color = '#6b7280'; // Gray
				}
			}
			
			// Initialize counter
			updateExcerptCounter();
			
			// Update on input
			excerptField.addEventListener('input', function() {
				updateExcerptCounter();
				
				// Sync with Gutenberg if available
				if (wp && wp.data && wp.data.dispatch('core/editor')) {
					wp.data.dispatch('core/editor').editPost({
						excerpt: excerptField.value
					});
				}
			});
			
			// Sync from Gutenberg to our field
			if (wp && wp.data && wp.data.select('core/editor')) {
				wp.data.subscribe(function() {
					const gutenbergExcerpt = wp.data.select('core/editor').getEditedPostAttribute('excerpt');
					if (gutenbergExcerpt !== null && gutenbergExcerpt !== excerptField.value) {
						excerptField.value = gutenbergExcerpt || '';
						updateExcerptCounter();
					}
				});
			}
		}

		// Image optimization features removed - no longer managing images
		// Removed: reloadImagesSection, refreshStandardPreview, and all featured image listeners
		});
	</script>
		<?php
		// CSS styles are now handled by MetaboxStylesManager
		// Only render styles if styles manager is not available (fallback)
		if ( ! $this->styles_manager ) {
			?>
		<style id="fp-seo-metabox-modern-ui">
		/* CSS Variables now unified in fp-seo-ui-system.css - No redefinition needed */
		
		/* Screen Reader Only Text for Accessibility */
		.screen-reader-text {
			border: 0;
			clip: rect(1px, 1px, 1px, 1px);
			clip-path: inset(50%);
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute;
			width: 1px;
			word-wrap: normal !important;
		}
		
		/* Hide native WordPress slug UI to avoid duplication with FP SEO slug field */
		/* IMPORTANT: Explicitly exclude #postimagediv to ensure featured image metabox is always visible */
		#slugdiv,
		#slugdiv .inside,
		#edit-slug-box,
		#editable-post-name,
		#editable-post-name-full,
		#post-name,
		#permalink,
		.edit-slug,
		.edit-post-post-link,
		.components-panel__body[data-editor-panel-id="post-link"],
		.components-panel__body[data-panel-id="post-link"],
		.editor-post-url,
		.editor-post-url .components-panel__body,
		.editor-post-permalink,
		.editor-document-permalink-panel {
			display: none !important;
		}
		
		#fp-seo-performance-metabox.postbox,
		#fp-seo-geo-metabox.postbox {
			border: 1px solid #e5e7eb !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
			border-radius: 8px !important;
		}
		
		#fp-seo-performance-metabox .postbox-header {
			background: linear-gradient(135deg, var(--fp-seo-primary) 0%, var(--fp-seo-primary-dark) 100%) !important;
			border-bottom: none !important;
		}
		
		#fp-seo-performance-metabox .postbox-header h2 {
			color: #fff !important;
			font-weight: 600 !important;
		}
		
		#fp-seo-performance-metabox .postbox-header .handle-actions button {
			filter: brightness(0) invert(1) !important;
		}
		
		.fp-seo-performance-metabox__score {
			display: flex !important;
			align-items: center !important;
			justify-content: space-between !important;
			gap: 16px !important;
			border-radius: 8px !important;
			padding: 24px !important;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
			border: none !important;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;
			position: relative !important;
			overflow: hidden !important;
			margin-bottom: 16px !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"green\"] {
			background: linear-gradient(135deg, var(--fp-seo-success) 0%, var(--fp-seo-success-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"yellow\"] {
			background: linear-gradient(135deg, var(--fp-seo-warning) 0%, var(--fp-seo-warning-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"red\"] {
			background: linear-gradient(135deg, var(--fp-seo-danger) 0%, var(--fp-seo-danger-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score-label {
			font-size: 14px !important;
			font-weight: 600 !important;
			color: rgba(255,255,255,0.9) !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
		}
		
		.fp-seo-performance-metabox__score-value {
			font-size: 48px !important;
			font-weight: 700 !important;
			color: #fff !important;
			line-height: 1 !important;
			text-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
		}
		
		.fp-seo-performance-metabox__indicator-list {
			display: grid !important;
			grid-template-columns: repeat(2, 1fr) !important;
			gap: 8px !important;
			margin: 0 !important;
			padding: 0 !important;
			list-style: none !important;
		}
		
		.fp-seo-performance-indicator {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			padding: 10px 12px !important;
			border-radius: 8px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-indicator::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 3px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-indicator:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 4px 0 rgba(0,0,0,0.08) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-indicator--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-indicator--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-indicator--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-indicator__label {
			font-size: 12px !important;
			font-weight: 500 !important;
			color: #374151 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-indicator__icon {
			width: 8px !important;
			height: 8px !important;
			border-radius: 50% !important;
			flex-shrink: 0 !important;
			margin-left: 4px !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-indicator--fail .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-danger) !important;
			box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--warn .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-warning) !important;
			box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--pass .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-success) !important;
			box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2) !important;
		}
		
		/* Loading state */
		.fp-seo-performance-indicator--pending .fp-seo-performance-indicator__icon {
			background: #d1d5db !important;
			animation: fp-seo-pulse 1.5s ease-in-out infinite !important;
		}
		
		@keyframes fp-seo-pulse {
			0%, 100% {
				opacity: 0.4 !important;
				transform: scale(1) !important;
			}
			50% {
				opacity: 1 !important;
				transform: scale(1.2) !important;
			}
		}
		
		/* Tooltip */
		.fp-seo-performance-indicator {
			position: relative !important;
		}
		
		.fp-seo-performance-indicator__tooltip {
			position: absolute !important;
			bottom: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) translateY(-8px) !important;
			padding: 8px 12px !important;
			background: #1f2937 !important;
			color: #fff !important;
			font-size: 12px !important;
			line-height: 1.4 !important;
			border-radius: 8px !important;
			white-space: nowrap !important;
			max-width: 250px !important;
			white-space: normal !important;
			pointer-events: none !important;
			opacity: 0 !important;
			visibility: hidden !important;
			transition: all 0.2s ease !important;
			z-index: 1000 !important;
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3) !important;
		}
		
		.fp-seo-performance-indicator__tooltip::after {
			content: '' !important;
			position: absolute !important;
			top: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) !important;
			border: 5px solid transparent !important;
			border-top-color: #1f2937 !important;
		}
		
		.fp-seo-performance-indicator:hover .fp-seo-performance-indicator__tooltip {
			opacity: 1 !important;
			visibility: visible !important;
			transform: translateX(-50%) translateY(-4px) !important;
		}
		
		/* Summary badges */
		.fp-seo-performance-summary {
			display: flex !important;
			gap: 8px !important;
			margin-bottom: 12px !important;
			padding: 12px !important;
			background: #f9fafb !important;
			border-radius: 8px !important;
			border: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-performance-summary__badge {
			display: inline-flex !important;
			align-items: center !important;
			gap: 6px !important;
			padding: 6px 10px !important;
			border-radius: 8px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-performance-summary__badge--fail {
			background: #fef2f2 !important;
			color: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-summary__badge--warn {
			background: #fffbeb !important;
			color: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-summary__badge--pass {
			background: #f0fdf4 !important;
			color: var(--fp-seo-success) !important;
		}
		
		/* Responsive: 1 colonna su schermi piccoli */
		@media (max-width: 782px) {
			.fp-seo-performance-metabox__indicator-list {
				grid-template-columns: 1fr !important;
			}
		}

		/* Help Banner */
		.fp-seo-metabox-help-banner {
			background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
			border-left: 4px solid #3b82f6;
			padding: 16px 20px;
			margin-bottom: 20px;
			border-radius: 8px;
			display: flex;
			gap: 16px;
			align-items: flex-start;
			position: relative;
			animation: slideDown 0.4s ease;
		}

		@keyframes slideDown {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.fp-seo-metabox-help-banner__icon {
			font-size: 24px;
			line-height: 1;
			flex-shrink: 0;
		}

		.fp-seo-metabox-help-banner__content {
			flex: 1;
		}

		.fp-seo-metabox-help-banner__title {
			margin: 0 0 8px;
			font-size: 14px;
			font-weight: 600;
			color: #1e40af;
		}

		.fp-seo-metabox-help-banner__text {
			margin: 0 0 12px;
			font-size: 13px;
			color: #1e3a8a;
			line-height: 1.5;
		}

		.fp-seo-metabox-help-banner__legend {
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
		}

		.fp-seo-legend-item {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
			color: #1e3a8a;
			font-weight: 500;
		}

		.fp-seo-legend-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			flex-shrink: 0;
		}

		.fp-seo-legend-item--pass .fp-seo-legend-dot {
			background: var(--fp-seo-success);
		}

		.fp-seo-legend-item--warn .fp-seo-legend-dot {
			background: var(--fp-seo-warning);
		}

		.fp-seo-legend-item--fail .fp-seo-legend-dot {
			background: var(--fp-seo-danger);
		}

		.fp-seo-metabox-help-banner__close {
			position: absolute;
			top: 8px;
			right: 8px;
			background: rgba(255, 255, 255, 0.7);
			border: none;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			font-size: 18px;
			line-height: 1;
			color: #1e40af;
			transition: all 0.2s;
		}

		.fp-seo-metabox-help-banner__close:hover {
			background: rgba(255, 255, 255, 1);
			transform: scale(1.1);
		}

		.fp-seo-metabox-help-banner.hidden {
			display: none;
		}

		/* Tooltip */
		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			opacity: 0.7;
			font-size: 14px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		/* Help Toggle Button */
		.fp-seo-help-toggle {
			background: transparent;
			border: 1px solid #e5e7eb;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			color: #6b7280;
			transition: all 0.2s;
			flex-shrink: 0;
			padding: 0;
		}

		.fp-seo-help-toggle:hover {
			background: #f3f4f6;
			border-color: #3b82f6;
			color: #3b82f6;
		}

		.fp-seo-help-toggle .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}

		/* Check Help Content */
		.fp-seo-check-help {
			background: #f0f9ff;
			border: 1px solid #bfdbfe;
			border-radius: 6px;
			padding: 16px;
			margin-top: 12px;
			animation: expandDown 0.3s ease;
		}

		@keyframes expandDown {
			from {
				opacity: 0;
				max-height: 0;
				padding-top: 0;
				padding-bottom: 0;
			}
			to {
				opacity: 1;
				max-height: 500px;
				padding-top: 16px;
				padding-bottom: 16px;
			}
		}

		.fp-seo-check-help__section {
			margin-bottom: 16px;
		}

		.fp-seo-check-help__section:last-child {
			margin-bottom: 0;
		}

		.fp-seo-check-help__title {
			margin: 0 0 8px;
			font-size: 13px;
			font-weight: 600;
			color: #1e40af;
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.fp-seo-check-help__title .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}

		.fp-seo-check-help__text {
			margin: 0;
			font-size: 12px;
			color: #1e3a8a;
			line-height: 1.6;
		}

		.fp-seo-check-help__example {
			background: #fff;
			border: 1px solid #bfdbfe;
			border-radius: 4px;
			padding: 12px;
			margin-top: 12px;
		}

		.fp-seo-check-help__example strong {
			display: block;
			margin-bottom: 6px;
			font-size: 12px;
			color: #1e40af;
		}

		.fp-seo-check-help__example code {
			display: block;
			background: #f8fafc;
			padding: 8px;
			border-radius: 4px;
			font-size: 11px;
			color: #1e3a8a;
			font-family: 'Courier New', monospace;
			word-wrap: break-word;
		}
		
		.fp-seo-performance-metabox__recommendations {
			margin-top: 16px !important;
		}
		
		.fp-seo-performance-recommendations-header {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin-bottom: 10px !important;
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #374151 !important;
		}
		
		.fp-seo-performance-recommendations-header__badge {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-width: 20px !important;
			height: 20px !important;
			padding: 0 6px !important;
			background: var(--fp-seo-primary) !important;
			color: #fff !important;
			font-size: 11px !important;
			font-weight: 600 !important;
			border-radius: 12px !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li {
			font-size: 12px !important;
			line-height: 1.5 !important;
			padding: 8px 12px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 4px !important;
			border-left: 3px solid #2563eb !important;
			color: #374151 !important;
			margin-bottom: 6px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:last-child {
			margin-bottom: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list--empty {
			padding: 12px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 13px !important;
			font-weight: 500 !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			margin: 16px 0 12px !important;
			font-size: 15px !important;
			font-weight: 600 !important;
			color: #111827 !important;
		}
		
		/* Unified Analysis Styles */
		.fp-seo-performance-metabox__unified-analysis {
			margin-bottom: 20px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			display: flex !important;
			flex-direction: column !important;
			gap: 8px !important;
		}
		
		.fp-seo-performance-analysis-item {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 12px 16px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-analysis-item::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 4px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-analysis-item:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 8px 0 rgba(0,0,0,0.1) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-analysis-item--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-analysis-item--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-analysis-item--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-analysis-item__header {
			display: flex !important;
			align-items: center !important;
			gap: 12px !important;
			margin-bottom: 4px !important;
		}
		
		.fp-seo-performance-analysis-item__icon {
			font-size: 16px !important;
			line-height: 1 !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item__title {
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-analysis-item__status {
			font-size: 11px !important;
			font-weight: 500 !important;
			padding: 2px 8px !important;
			border-radius: 12px !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item--pass .fp-seo-performance-analysis-item__status {
			background: #d1fae5 !important;
			color: #065f46 !important;
		}
		
		.fp-seo-performance-analysis-item--warn .fp-seo-performance-analysis-item__status {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		
		.fp-seo-performance-analysis-item--fail .fp-seo-performance-analysis-item__status {
			background: #fee2e2 !important;
			color: #991b1b !important;
		}
		
		.fp-seo-performance-analysis-item__description {
			font-size: 12px !important;
			color: #6b7280 !important;
			line-height: 1.5 !important;
			margin-left: 28px !important;
			margin-top: 4px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list--empty {
			padding: 20px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 14px !important;
			font-weight: 500 !important;
		}
		
		/* Unified Section Styles */
		.fp-seo-performance-metabox__section {
			margin-bottom: 24px !important;
			padding: 20px !important;
			background: #ffffff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-metabox__section:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			display: flex !important;
			align-items: center !important;
			gap: 10px !important;
			margin: 0 0 16px 0 !important;
			padding: 0 0 12px 0 !important;
			font-size: 16px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			border-bottom: 2px solid #e5e7eb !important;
		}
		
		.fp-seo-section-icon {
			font-size: 20px !important;
			line-height: 1 !important;
		}
		
		.fp-seo-performance-metabox__section-content {
			/* Reset any inherited styles */
		}
		
		/* Keywords Section Uniform Style */
		.fp-seo-performance-metabox__keywords {
			margin-bottom: 24px !important;
			padding: 20px !important;
			background: #ffffff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important;
		}
		
		.fp-seo-performance-metabox__keywords:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px 0 rgba(0,0,0,0.08) !important;
		}
		</style>
		<?php
		}
		?>
		<script>
		(function() {
			// CRITICAL: Fix homepage title if it shows "Bozza automatica"
			// This runs immediately and also on DOMContentLoaded for block editor
			var pageOnFront = <?php echo (int) get_option( 'page_on_front' ); ?>;
			var currentPostId = <?php echo isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; ?>;
			var correctTitle = '<?php echo esc_js( get_the_title( (int) get_option( 'page_on_front' ) ) ); ?>';
			
			if ( pageOnFront === 0 || currentPostId !== pageOnFront || ! correctTitle || correctTitle === 'Bozza automatica' || correctTitle === 'Auto Draft' ) {
				return;
			}
			
			function fixHomepageTitle() {
				// Fix title in classic editor
				var titleInput = document.getElementById('title');
				if ( titleInput ) {
					var currentValue = titleInput.value || titleInput.textContent || '';
					if ( currentValue === 'Bozza automatica' || currentValue === 'Auto Draft' || currentValue.trim() === '' ) {
						titleInput.value = correctTitle;
						if ( titleInput.dispatchEvent ) {
							titleInput.dispatchEvent(new Event('input', { bubbles: true }));
							titleInput.dispatchEvent(new Event('change', { bubbles: true }));
						}
					}
				}
				
				// Fix title in block editor (Gutenberg)
				var blockEditorTitle = document.querySelector('.editor-post-title__input, .wp-block-post-title input, .wp-block-post-title textarea, [data-type="core/post-title"] input, [data-type="core/post-title"] textarea');
				if ( blockEditorTitle ) {
					var currentValue = blockEditorTitle.value || blockEditorTitle.textContent || '';
					if ( currentValue === 'Bozza automatica' || currentValue === 'Auto Draft' || currentValue.trim() === '' ) {
						blockEditorTitle.value = correctTitle;
						if ( blockEditorTitle.dispatchEvent ) {
							blockEditorTitle.dispatchEvent(new Event('input', { bubbles: true }));
							blockEditorTitle.dispatchEvent(new Event('change', { bubbles: true }));
						}
						// Also update textContent for textarea
						if ( blockEditorTitle.textContent !== undefined ) {
							blockEditorTitle.textContent = correctTitle;
						}
						// Update innerHTML if it's a contenteditable
						if ( blockEditorTitle.contentEditable === 'true' ) {
							blockEditorTitle.textContent = correctTitle;
							blockEditorTitle.innerHTML = correctTitle;
						}
					}
				}
				
				// Fix page title in browser tab
				var pageTitle = document.querySelector('title');
				if ( pageTitle && pageTitle.textContent.indexOf('Bozza automatica') !== -1 ) {
					pageTitle.textContent = pageTitle.textContent.replace('Bozza automatica', correctTitle);
				}
			}
			
			// Run immediately
			fixHomepageTitle();
			
			// Run on DOMContentLoaded for block editor
			if ( document.readyState === 'loading' ) {
				document.addEventListener('DOMContentLoaded', fixHomepageTitle);
			} else {
				// Already loaded, run immediately
				fixHomepageTitle();
			}
			
			// Run after delays to catch late-loading elements
			setTimeout(fixHomepageTitle, 100);
			setTimeout(fixHomepageTitle, 500);
			setTimeout(fixHomepageTitle, 1000);
			setTimeout(fixHomepageTitle, 2000);
			setTimeout(fixHomepageTitle, 3000);
			
			// Also run when WordPress editor loads (for block editor)
			if ( typeof wp !== 'undefined' && wp.data && wp.data.subscribe ) {
				wp.data.subscribe(function() {
					setTimeout(fixHomepageTitle, 100);
				});
			}
			
			// Use MutationObserver to watch for title changes
			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if ( mutation.type === 'childList' || mutation.type === 'characterData' ) {
						setTimeout(fixHomepageTitle, 50);
					}
				});
			});
			
			// Observe title input changes
			var titleInput = document.getElementById('title');
			if ( titleInput ) {
				observer.observe(titleInput, { childList: true, characterData: true, subtree: true });
			}
			
			// Observe block editor title changes
			var blockEditorTitle = document.querySelector('.editor-post-title__input, .wp-block-post-title input, .wp-block-post-title textarea');
			if ( blockEditorTitle ) {
				observer.observe(blockEditorTitle, { childList: true, characterData: true, subtree: true });
			}
		})();
		</script>
		<?php
	}

	/**
	 * Renders the metabox content.
	 *
	 * @param WP_Post $post Current post instance.
	 */
	public function render( WP_Post $post ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: Metabox::render() CALLED for post_id=' . $post->ID );
		}
		
		// FORCE: Print scripts and localization in admin_footer
		// WordPress may strip script tags from metabox content, so we use admin_footer hook
		// Use global flag to ensure it only runs once per page
		global $fp_seo_scripts_printed;
		if ( ! isset( $fp_seo_scripts_printed ) || ! $fp_seo_scripts_printed ) {
			$fp_seo_scripts_printed = true;
			$post_id = $post->ID;
			$metabox_instance = $this;
			
			add_action( 'admin_footer', function() use ( $post_id, $metabox_instance ) {
				// Always print on post editor pages
				$screen = get_current_screen();
				if ( ! $screen || 'post' !== $screen->base ) {
					return;
				}
				
				global $post;
				if ( ! $post ) {
					return;
				}
				
				// Print localization data
				if ( $metabox_instance->assets_manager && method_exists( $metabox_instance->assets_manager, 'print_localization_data' ) ) {
					$metabox_instance->assets_manager->print_localization_data( $post );
				}
				
				// Print script tag
				if ( defined( 'FP_SEO_PERFORMANCE_FILE' ) ) {
					$script_url = plugins_url( 'assets/admin/js/editor-metabox-legacy.js', FP_SEO_PERFORMANCE_FILE );
				} else {
					$script_url = plugins_url( 'FP-SEO-Manager/assets/admin/js/editor-metabox-legacy.js' );
				}
				$version = defined( 'FP_SEO_PERFORMANCE_VERSION' ) ? FP_SEO_PERFORMANCE_VERSION : '1.0.0';
				echo '<script src="' . esc_url( $script_url ) . '?ver=' . esc_attr( $version ) . '"></script>' . "\n";
			}, 1 );
		}
		
		// CRITICAL: Wrap entire render method in try-catch to prevent fatal errors
		try {
			// Ensure styles manager is initialized and render styles inline FIRST
			if ( ! $this->styles_manager && class_exists( 'FP\SEO\Editor\Styles\MetaboxStylesManager' ) ) {
				try {
					$this->styles_manager = new \FP\SEO\Editor\Styles\MetaboxStylesManager( $this );
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $this->logger ) {
						$this->logger->error( 'FP SEO: Failed to initialize MetaboxStylesManager in render()', array(
							'error' => $e->getMessage(),
						) );
					}
				}
			}
			
			// Inject styles inline - try styles manager first, then fallback
			$styles_rendered = false;
			if ( $this->styles_manager && method_exists( $this->styles_manager, 'render_inline' ) ) {
				try {
					$this->styles_manager->render_inline();
					$styles_rendered = true;
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $this->logger ) {
						$this->logger->error( 'FP SEO: Error rendering styles inline', array(
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString(),
						) );
					}
				}
			}
			
			// Fallback: render styles directly if styles manager failed
			if ( ! $styles_rendered ) {
				// Use the fallback CSS from inject_modern_styles
				// This ensures styles are always loaded
				?>
				<style id="fp-seo-metabox-modern-ui">
				<?php
				// Include minimal critical styles for help banner and sections
				?>
				/* Help Banner */
				.fp-seo-metabox-help-banner {
					background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
					border-left: 4px solid #3b82f6;
					padding: 16px 20px;
					margin-bottom: 20px;
					border-radius: 8px;
					display: flex;
					gap: 16px;
					align-items: flex-start;
					position: relative;
				}
				
				.fp-seo-metabox-help-banner__icon {
					font-size: 24px;
					line-height: 1;
					flex-shrink: 0;
				}
				
				.fp-seo-metabox-help-banner__content {
					flex: 1;
				}
				
				.fp-seo-metabox-help-banner__title {
					margin: 0 0 8px;
					font-size: 14px;
					font-weight: 600;
					color: #1e40af;
				}
				
				.fp-seo-metabox-help-banner__text {
					margin: 0 0 12px;
					font-size: 13px;
					color: #1e3a8a;
					line-height: 1.5;
				}
				
				.fp-seo-metabox-help-banner__close {
					position: absolute;
					top: 8px;
					right: 8px;
					background: rgba(255, 255, 255, 0.7);
					border: none;
					border-radius: 4px;
					width: 24px;
					height: 24px;
					display: flex;
					align-items: center;
					justify-content: center;
					cursor: pointer;
					font-size: 18px;
					line-height: 1;
					color: #1e40af;
					transition: all 0.2s;
				}
				
				.fp-seo-metabox-help-banner.hidden {
					display: none;
				}
				
				/* Section Styles */
				.fp-seo-performance-metabox__section {
					margin-bottom: 20px;
					padding: 16px;
					background: #fff;
					border-radius: 8px;
					border: 1px solid #e5e7eb;
				}
				<?php
				?>
				</style>
				<?php
			}
			// Validazione input
			if ( ! $post instanceof WP_Post ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Invalid post object in render', array(
						'post' => gettype( $post ),
						'post_id' => isset( $post->ID ) ? $post->ID : 'unknown',
					) );
				}
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Errore: oggetto post non valido.', 'fp-seo-performance' ) . '</p></div>';
				return;
			}
		
		// CRITICAL FIX: Check if WordPress passed wrong post (e.g., nectar_slider instead of homepage)
		// This happens when Nectar Slider or other plugins modify the global $post
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$post_was_corrected = false;
		
		// Show diagnostics when editing homepage (delegated to MetaboxDiagnostics)
		// TEMPORARILY DISABLED to test if metabox renders without diagnostics
		// Diagnostics are disabled to prevent any potential errors from breaking the metabox
		/*
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id > 0 && $requested_post_id === $page_on_front_id ) {
			if ( isset( $this->diagnostics ) && method_exists( $this->diagnostics, 'get_homepage_diagnostics' ) ) {
				try {
					$diagnostics_data = $this->diagnostics->get_homepage_diagnostics( $post );
					if ( ! empty( $diagnostics_data ) && method_exists( $this->diagnostics, 'render_diagnostics' ) ) {
						$diagnostics_html = $this->diagnostics->render_diagnostics( $diagnostics_data );
						if ( ! empty( $diagnostics_html ) ) {
							$this->hook_manager->add_action( 'admin_notices', function() use ( $diagnostics_html ) {
								echo $diagnostics_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} );
						}
					}
				} catch ( \Throwable $e ) {
					// Silently fail diagnostics to prevent breaking the metabox
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$this->logger->error( 'FP SEO: Error rendering diagnostics', array(
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString(),
						) );
					}
				}
			}
		}
		*/
		
		// SIMPLIFIED: Just use the post WordPress gives us - no special handling
		// All the previous "homepage protection" code was causing more problems than it solved
		$current_post = $post;
		
		// Output sempre il nonce e il campo nascosto, anche se il rendering fallisce
		try {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			
			// Add hidden field to ensure metabox is always recognized in POST
			// This helps WordPress identify that our metabox fields should be processed
			echo '<input type="hidden" name="fp_seo_performance_metabox_present" value="1" />';
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->error( 'FP SEO: Error outputting nonce', array(
					'error' => $e->getMessage(),
				) );
			}
		}

		// Se il renderer non è disponibile, FORZA la reinizializzazione - nessun fallback
		if ( ! $this->renderer ) {
			$this->logger->error( 'FP SEO: MetaboxRenderer is null in render(), forcing reinitialization', array(
				'post_id' => isset( $current_post->ID ) ? $current_post->ID : 0,
				'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
			) );
			
			// Forza la reinizializzazione - DEVE funzionare
			try {
				$this->initialize_renderer();
			} catch ( \Throwable $e ) {
				// Se l'inizializzazione fallisce, logga e mostra errore
				$this->logger->error( 'FP SEO: Failed to reinitialize MetaboxRenderer in render()', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Errore critico: impossibile inizializzare il metabox SEO.', 'fp-seo-performance' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					echo '<p><small><strong>Errore:</strong> ' . esc_html( $e->getMessage() ) . '</small></p>';
				}
				echo '</div>';
				return;
			}
			
			// Se ancora null dopo la reinizializzazione, è un errore critico
			if ( ! $this->renderer ) {
				$error_msg = 'FP SEO: MetaboxRenderer is still null after reinitialization in render()';
				$this->logger->error( $error_msg );
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Errore critico: impossibile inizializzare il metabox SEO.', 'fp-seo-performance' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p></div>';
				return;
			}
		}

		// I dati per JS sono già stati preparati in enqueue_assets()
		$options  = $this->options->get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $current_post->ID );
		$analysis = array();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: Analyzer status - enabled=' . ( $enabled ? 'yes' : 'no' ) . ', excluded=' . ( $excluded ? 'yes' : 'no' ) . ', post_id=' . $current_post->ID );
		}

		// Debug: log analyzer status
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'FP SEO: Analyzer status check', array(
				'post_id' => $current_post->ID,
				'analyzer_enabled' => $enabled,
				'post_excluded' => $excluded,
				'options_general' => $options['general'] ?? array(),
			) );
		}

		if ( $enabled && ! $excluded ) {
			try {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO DEBUG: Running analysis for post_id=' . $current_post->ID );
				}
				$analysis = $this->run_analysis_for_post( $current_post );
				
				// Log analysis results (only when WP_DEBUG)
				$checks_count = isset( $analysis['checks'] ) && is_array( $analysis['checks'] ) ? count( $analysis['checks'] ) : 0;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO DEBUG: Analysis completed - checks_count=' . $checks_count . ', analysis_keys=' . implode( ',', array_keys( $analysis ) ) );
				}
				if ( $checks_count > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$first_check = reset( $analysis['checks'] );
					error_log( 'FP SEO DEBUG: First check keys=' . ( is_array( $first_check ) ? implode( ',', array_keys( $first_check ) ) : gettype( $first_check ) ) );
				}
				
				// Debug: log analysis results
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->debug( 'FP SEO: Analysis completed', array(
						'post_id' => $current_post->ID,
						'analysis_keys' => array_keys( $analysis ),
						'checks_count' => $checks_count,
						'score' => $analysis['score'] ?? null,
						'has_checks' => ! empty( $analysis['checks'] ),
					) );
				}
				
				// Ensure analysis has required structure even if checks are empty
				if ( ! isset( $analysis['checks'] ) ) {
					$analysis['checks'] = array();
				}
				if ( ! isset( $analysis['score'] ) ) {
					$analysis['score'] = array(
						'score' => 0,
						'status' => 'pending',
					);
				}
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					error_log( 'FP SEO DEBUG: Exception in analysis - error=' . $e->getMessage() . ', file=' . $e->getFile() . ', line=' . $e->getLine() );
				}
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Error running analysis', array(
						'post_id' => $current_post->ID,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					) );
				}
				$analysis = array(
					'checks' => array(),
					'score' => array(
						'score' => 0,
						'status' => 'error',
					),
				);
			} catch ( \Throwable $e ) {
				// ALWAYS log fatal error
				error_log( 'FP SEO DEBUG: Throwable in analysis - error=' . $e->getMessage() . ', file=' . $e->getFile() . ', line=' . $e->getLine() );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Fatal error running analysis', array(
						'post_id' => $current_post->ID,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					) );
				}
				$analysis = array(
					'checks' => array(),
					'score' => array(
						'score' => 0,
						'status' => 'error',
					),
				);
			}
		} else {
			$reason = ! $enabled ? 'analyzer_disabled' : ( $excluded ? 'post_excluded' : 'unknown' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( 'FP SEO DEBUG: Analysis NOT run - reason=' . $reason . ', enabled=' . ( $enabled ? 'yes' : 'no' ) . ', excluded=' . ( $excluded ? 'yes' : 'no' ) );
			}
			
			// Debug: log why analysis was not run
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: Analysis not run', array(
					'post_id' => $current_post->ID,
					'reason' => $reason,
					'analyzer_enabled' => $enabled,
					'post_excluded' => $excluded,
				) );
			}
		}

		// Ensure $analysis is always an array before passing to renderer
		if ( ! is_array( $analysis ) ) {
			$this->logger->error( 'FP SEO: Invalid analysis result in Metabox::render()', array(
				'analysis_type' => gettype( $analysis ),
				'post_id' => isset( $current_post->ID ) ? $current_post->ID : 0,
			) );
			$analysis = array(); // Force to empty array
		}
		
		// ALWAYS ensure analysis has score and checks keys, even if empty
		if ( ! isset( $analysis['score'] ) ) {
			$analysis['score'] = array(
				'score' => 0,
				'status' => 'pending',
			);
		}
		if ( ! isset( $analysis['checks'] ) ) {
			$analysis['checks'] = array();
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( 'FP SEO DEBUG: Metabox::render() - Passing analysis to renderer - checks_count=' . count( $analysis['checks'] ) );
		}

		// Use renderer to output HTML con gestione errori robusta
		// Pass current_post instead of modifying the original $post parameter
		try {
			// Verifica che il renderer sia ancora disponibile prima di chiamarlo
			if ( ! $this->renderer ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->error( 'FP SEO: Renderer is null before render() call', array(
						'post_id' => isset( $current_post->ID ) ? $current_post->ID : ( isset( $post->ID ) ? $post->ID : 0 ),
						'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
					) );
				}
				throw new \RuntimeException( 'Renderer became null before render() call' );
			}
			
			// Log inizio rendering in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: Starting metabox rendering', array(
					'post_id' => isset( $current_post->ID ) ? $current_post->ID : 0,
					'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
					'excluded' => $excluded,
					'analysis_count' => is_array( $analysis ) ? count( $analysis ) : 0,
					'analysis_type' => gettype( $analysis ),
					'renderer_class' => isset( $this->renderer ) ? get_class( $this->renderer ) : 'null',
				) );
			}
			
			// CRITICAL: Output test message before calling renderer
			echo '<!-- FP SEO Metabox::render() calling renderer -->';
			
			// Chiama il renderer
			$this->renderer->render( $current_post, $analysis, $excluded );
			
			// Log successo in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'FP SEO: Metabox rendering completed successfully' );
			}
		} catch ( \Throwable $e ) {
			// Errore critico - logga e mostra messaggio chiaro
			$this->logger->error( 'FP SEO: Critical error rendering metabox', array(
				'post_id' => isset( $current_post->ID ) ? $current_post->ID : ( isset( $post->ID ) ? $post->ID : 0 ),
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'renderer_null' => is_null( $this->renderer ),
				'renderer_class' => $this->renderer ? get_class( $this->renderer ) : 'null',
				'error_type' => get_class( $e ),
			) );
			
			// Mostra errore chiaro con dettagli utili
			echo '<div class="notice notice-error" style="display: block !important; padding: 15px; margin: 10px 0;">';
			echo '<p><strong>' . esc_html__( 'Errore critico nel rendering del metabox SEO', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Impossibile caricare il metabox completo. Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
			
			// Mostra sempre il messaggio di errore (non solo in debug)
			echo '<p><small><strong>Errore:</strong> ' . esc_html( $e->getMessage() ) . '</small></p>';
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<p><small><strong>File:</strong> ' . esc_html( $e->getFile() ) . ':' . esc_html( $e->getLine() ) . '</small></p>';
				echo '<p><small><strong>Tipo errore:</strong> ' . esc_html( get_class( $e ) ) . '</small></p>';
				if ( ! is_null( $this->renderer ) ) {
					echo '<p><small><strong>Renderer class:</strong> ' . esc_html( get_class( $this->renderer ) ) . '</small></p>';
				} else {
					echo '<p><small><strong>Renderer:</strong> null</small></p>';
				}
			}
			echo '</div>';
		}
		} catch ( \Throwable $e ) {
			// Catch any errors that weren't caught by inner try-catch blocks
			// Log error but don't break the page - show user-friendly message instead
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->error( 'FP SEO: Uncaught error in Metabox::render()', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'error_type' => get_class( $e ),
				) );
			}
			// Show user-friendly error message instead of breaking the page
			echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Errore nel caricamento del metabox SEO', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Il metabox non può essere visualizzato. Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<p><small><strong>Errore:</strong> ' . esc_html( $e->getMessage() ) . '</small></p>';
				echo '<p><small><strong>File:</strong> ' . esc_html( $e->getFile() ) . ':' . esc_html( $e->getLine() ) . '</small></p>';
			}
			echo '</div>';
		}
	}
	
	/**
	 * DEPRECATED: Render fallback fields - NON PIÙ UTILIZZATO
	 * Il renderer DEVE funzionare sempre, nessun fallback.
	 *
	 * @param WP_Post $post Current post instance.
	 * @deprecated Questo metodo non dovrebbe mai essere chiamato. Se viene chiamato, c'è un problema critico.
	 */
	private function render_fallback_fields( WP_Post $post ): void {
		// NON RENDERE NULLA - se questo metodo viene chiamato, è un errore critico
		$this->logger->error( 'FP SEO: render_fallback_fields() called - this should never happen!', array(
			'post_id' => $post->ID ?? 0,
			'backtrace' => wp_debug_backtrace_summary(),
		) );
		
		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html__( 'ERRORE CRITICO: Il metabox non può essere renderizzato.', 'fp-seo-performance' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Contatta il supporto tecnico con i dettagli dell\'errore.', 'fp-seo-performance' ) . '</p>';
		echo '</div>';
		// DISABLED: Cache clearing interferes with WordPress's post object during page load

		$seo_title = ! empty( $post->ID ) && $post->ID > 0 ? get_post_meta( $post->ID, '_fp_seo_title', true ) : '';
		$meta_desc = ! empty( $post->ID ) && $post->ID > 0 ? get_post_meta( $post->ID, '_fp_seo_meta_description', true ) : '';
		$focus_keyword = ! empty( $post->ID ) && $post->ID > 0 ? get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true ) : '';
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( ! empty( $post->ID ) && $post->ID > 0 ) {
			if ( empty( $seo_title ) ) {
				global $wpdb;
				$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_title' ) );
				if ( $db_value !== null ) {
					$seo_title = $db_value;
				}
			}
			
			if ( empty( $meta_desc ) ) {
				global $wpdb;
				$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_meta_description' ) );
				if ( $db_value !== null ) {
					$meta_desc = $db_value;
				}
			}
			
			if ( empty( $focus_keyword ) ) {
				global $wpdb;
				$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, self::META_FOCUS_KEYWORD ) );
				if ( $db_value !== null ) {
					$focus_keyword = $db_value;
				}
			}
		}
		
		// Render fallback con struttura simile al renderer principale
		echo '<div class="fp-seo-performance-metabox" data-fp-seo-metabox style="padding: 0;">';
		echo '<div class="notice notice-warning" style="margin: 0 0 15px 0; padding: 12px;">';
		echo '<p><strong>' . esc_html__( 'FP SEO Manager - Modalità Fallback', 'fp-seo-performance' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Il metabox completo non è disponibile, ma i campi SEO essenziali sono disponibili qui sotto.', 'fp-seo-performance' ) . '</p>';
		echo '<p><small>' . esc_html__( 'Suggerimento: dopo aver eseguito l\'analisi SEO, ricarica la pagina per vedere il metabox completo.', 'fp-seo-performance' ) . '</small></p>';
		echo '</div>';
		
		// SEO Title
		echo '<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;">';
		echo '<label for="fp-seo-title-fallback" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #0c4a6e;">';
		echo '📝 ' . esc_html__( 'SEO Title', 'fp-seo-performance' ) . ' <span style="font-size: 10px; padding: 2px 6px; background: #10b981; color: #fff; border-radius: 4px;">+15%</span>';
		echo '</label>';
		echo '<input type="text" id="fp-seo-title" name="fp_seo_title" value="' . esc_attr( $seo_title ) . '" placeholder="' . esc_attr__( 'Titolo ottimizzato per Google (50-60 caratteri)', 'fp-seo-performance' ) . '" maxlength="70" style="width: 100%; padding: 10px; border: 2px solid #10b981; border-radius: 6px; font-size: 14px;" />';
		echo '<input type="hidden" name="fp_seo_title_sent" value="1" />';
		echo '<p style="margin: 8px 0 0; font-size: 11px; color: #64748b;">' . esc_html__( 'Appare come titolo principale nei risultati di ricerca Google.', 'fp-seo-performance' ) . '</p>';
		echo '</div>';
		
		// Meta Description
		echo '<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;">';
		echo '<label for="fp-seo-meta-desc-fallback" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #0c4a6e;">';
		echo '📄 ' . esc_html__( 'Meta Description', 'fp-seo-performance' ) . ' <span style="font-size: 10px; padding: 2px 6px; background: #10b981; color: #fff; border-radius: 4px;">+10%</span>';
		echo '</label>';
		echo '<textarea id="fp-seo-meta-description" name="fp_seo_meta_description" rows="3" placeholder="' . esc_attr__( 'Descrizione ottimizzata per Google (150-160 caratteri)', 'fp-seo-performance' ) . '" maxlength="160" style="width: 100%; padding: 10px; border: 2px solid #10b981; border-radius: 6px; font-size: 14px; resize: vertical;">' . esc_textarea( $meta_desc ) . '</textarea>';
		echo '<input type="hidden" name="fp_seo_meta_description_sent" value="1" />';
		echo '<p style="margin: 8px 0 0; font-size: 11px; color: #64748b;">' . esc_html__( 'Appare come descrizione sotto il titolo nei risultati di ricerca.', 'fp-seo-performance' ) . '</p>';
		echo '</div>';
		
		// Focus Keyword
		echo '<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;">';
		echo '<label for="fp-seo-focus-keyword-fallback" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #0c4a6e;">';
		echo '🎯 ' . esc_html__( 'Focus Keyword', 'fp-seo-performance' );
		echo '</label>';
		echo '<input type="text" id="fp-seo-focus-keyword" name="fp_seo_focus_keyword" value="' . esc_attr( $focus_keyword ) . '" placeholder="' . esc_attr__( 'Parola chiave principale per questo contenuto', 'fp-seo-performance' ) . '" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;" />';
		echo '<p style="margin: 8px 0 0; font-size: 11px; color: #64748b;">' . esc_html__( 'La parola chiave principale che vuoi far posizionare per questo contenuto.', 'fp-seo-performance' ) . '</p>';
		echo '</div>';
		
		echo '</div>';
	}

	/**
	 * Save SEO meta data for a post.
	 * Called by save_post hook (receives: int $post_id, WP_Post $post, bool $update)
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object (ignored).
	 * @param bool     $update  Whether this is an update (ignored).
	 */
	public function save_meta( int $post_id, $post = null, $update = null ): void {
		// CRITICAL: Check post status FIRST - skip auto-draft immediately
		// WordPress creates auto-draft when opening editor - we must NEVER process these
		$current_post_status = get_post_status( $post_id );
		if ( $current_post_status === 'auto-draft' || $current_post_status === false ) {
			// NEVER process auto-draft - this is WordPress creating temporary draft when opening editor
			return;
		}
		
		// Check if this is a real save operation (user clicked Save/Publish button)
		$is_real_save = ( isset( $_POST['save'] ) && $_POST['save'] !== '' ) || 
						( isset( $_POST['publish'] ) && $_POST['publish'] !== '' ) ||
						( isset( $_POST['action'] ) && $_POST['action'] === 'editpost' && 
						  ( isset( $_POST['save'] ) || isset( $_POST['publish'] ) ) );
		
		// Also check if there are actual SEO fields being submitted
		$has_seo_fields = isset( $_POST['fp_seo_performance_metabox_present'] ) || 
						  isset( $_POST['fp_seo_title_sent'] ) || 
						  isset( $_POST['fp_seo_meta_description_sent'] ) ||
						  isset( $_POST['fp_seo_title'] ) ||
						  isset( $_POST['fp_seo_meta_description'] );
		
		// Only process if it's a REAL save operation AND has SEO fields
		// This prevents ANY processing when WordPress is just opening editor
		if ( ! $is_real_save || ! $has_seo_fields ) {
			return; // Not a real save - exit immediately without any processing
		}
		
		// CRITICAL: Check post type FIRST, before any static tracking
		// This ensures we don't interfere with unsupported post types at all
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		// DEBUG: Always log when save_meta is called (even for unsupported types)
		// This helps diagnose why posts aren't saving
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta called', array(
				'post_id' => $post_id,
				'post_type' => $post_type,
				'supported' => in_array( $post_type, $supported_types, true ) ? 'yes' : 'no',
				'supported_types' => $supported_types,
				'update' => $update ? 'yes' : 'no',
				'hook' => current_filter(),
				'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
				'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
			) );
		}
		
		// If not a supported post type, return immediately without any processing
		// This prevents ANY interference with Nectar Sliders, attachments, or other custom post types
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			// Log only once per post type per request to avoid spam
			static $logged_types = array();
			if ( ! isset( $logged_types[ $post_type ] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$this->logger->debug( 'Metabox::save_meta skipped - unsupported post type (exiting immediately)', array(
						'post_id' => $post_id,
						'post_type' => $post_type,
						'supported_types' => $supported_types,
						'hook' => current_filter(),
					) );
				}
				$logged_types[ $post_type ] = true;
			}
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		// Prevent multiple calls - usa un array statico per tracciare per post_id
		// Questo previene esecuzioni multiple anche se lo stesso hook viene chiamato più volte
		// IMPORTANT: This check happens AFTER post type validation
		static $saved = array();
		$post_key = (string) $post_id;
		
		if ( isset( $saved[ $post_key ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'Metabox::save_meta already processed for post_id', array(
					'hook' => current_filter(),
					'post_id' => $post_id,
					'post_type' => $post_type,
				) );
			}
			return;
		}
		
		// Marca questo post come processato per tutta la request
		$saved[ $post_key ] = true;
		
		// Log solo in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$seo_fields_present = array();
			$seo_fields = array(
				'fp_seo_performance_metabox_present',
				'fp_seo_title',
				'fp_seo_title_sent',
				'fp_seo_meta_description',
				'fp_seo_meta_description_sent',
				'fp_seo_focus_keyword',
				'fp_seo_secondary_keywords',
			);
			
			foreach ( $seo_fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					$value = wp_unslash( $_POST[ $field ] );
					// Sanitize for logging to prevent XSS in log output
					if ( is_string( $value ) ) {
						$value = sanitize_text_field( $value );
						if ( strlen( $value ) > 100 ) {
							$value = substr( $value, 0, 100 ) . '...';
						}
					}
					$seo_fields_present[ $field ] = $value;
				}
			}
			
			$this->logger->debug( 'Metabox::save_meta called', array(
				'post_id' => $post_id,
				'update' => $update ? 'yes' : 'no',
				'hook' => current_filter(),
				'post_keys_count' => isset( $_POST ) ? count( $_POST ) : 0,
				'seo_fields' => $seo_fields_present,
			) );
		}
		
		// DISABLED - All homepage status protection code has been removed
		// The plugin should NOT touch post_status at all - this was causing the Auto Draft issue
		
		// Use SeoFieldsSaver (delegates to MetaboxSaver internally)
		$result = $this->fields_saver->save_all_fields( $post_id );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta completed', array(
				'post_id' => $post_id,
				'result' => $result ? 'success' : 'failed',
			) );
		}
		
		$saved[ $post_id ] = true;
	}

	/**
	 * Save SEO meta data for a post (edit_post hook).
	 * Called by edit_post hook (receives: int $post_id, WP_Post $post)
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object (ignored).
	 */
	public function save_meta_edit_post( int $post_id, $post = null ): void {
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types at all
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			// Log only in debug mode and only once per post type to avoid spam
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_types = array();
				if ( ! isset( $logged_types[ $post_type ] ) ) {
					$this->logger->debug( 'Metabox::save_meta_edit_post skipped - unsupported post type', array(
						'post_id' => $post_id,
						'post_type' => $post_type,
						'supported_types' => $supported_types,
						'hook' => 'edit_post',
					) );
					$logged_types[ $post_type ] = true;
				}
			}
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta_edit_post called', array(
				'post_id' => $post_id,
				'post_type' => $post_type,
				'hook' => 'edit_post',
			) );
		}
		
		// Use SeoFieldsSaver for edit_post saves
		$this->fields_saver->save_from_edit( $post_id, $post );
	}
	
	/**
	 * Register SEO meta fields for REST API (Gutenberg support).
	 */
	/**
	 * Register REST meta fields for Gutenberg support.
	 *
	 * @deprecated This method has been moved to REST/Controllers/MetaController.
	 *             REST meta fields are now registered via RESTServiceProvider.
	 *             This method is kept for backward compatibility but does nothing.
	 * @return void
	 */
	public function register_rest_meta_fields(): void {
		// REST meta fields registration is now handled by REST/Controllers/MetaController
		// via RESTServiceProvider. This method is kept for backward compatibility.
	}
	
	/**
	 * Save SEO meta data via REST API (Gutenberg).
	 * Called by rest_after_insert_{post_type} hook
	 *
	 * @param WP_Post         $post     Post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating Whether creating a new post.
	 */
	public function save_meta_rest( WP_Post $post, $request, bool $creating ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta_rest called', array(
				'post_id' => $post->ID,
				'creating' => $creating ? 'yes' : 'no',
				'hook' => 'REST API',
			) );
		}
		
		// In Gutenberg, i dati vengono passati via REST API
		// Verifica se ci sono dati SEO nella richiesta
		if ( ! $request instanceof \WP_REST_Request ) {
			return;
		}
		
		$params = $request->get_params();
		
		// Cerca campi SEO nei parametri (possono essere in meta o direttamente)
		$seo_title = $params['fp_seo_title'] ?? $params['meta']['_fp_seo_title'] ?? null;
		$meta_desc = $params['fp_seo_meta_description'] ?? $params['meta']['_fp_seo_meta_description'] ?? null;
		$excerpt = $params['excerpt'] ?? $params['fp_seo_excerpt'] ?? null;
		
		// Se trovati, salva direttamente
		if ( $seo_title !== null || $meta_desc !== null || $excerpt !== null ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'REST API - Found SEO fields in request', array(
					'post_id' => $post->ID,
					'has_title' => $seo_title !== null,
					'has_description' => $meta_desc !== null,
				) );
			}
			
			// Use SeoFieldsSaver for REST API saves
			$this->fields_saver->save_from_rest( $post, $request, $creating );
			
			// DISABLED - Homepage protection was causing the Auto Draft issue
			// The plugin should not touch post status at all
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'REST API - No SEO fields found in request params', array(
					'post_id' => $post->ID,
				) );
			}
			// Prova a salvare comunque (potrebbero essere già stati salvati via register_rest_field)
			// Non chiamare save_meta qui per evitare doppio salvataggio
		}
	}
	
	/**
	 * Save SEO meta data before post update (pre_post_update filter).
	 * Questo hook viene chiamato PRIMA di save_post e può intercettare i dati.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Array of post data.
	 * @return array Unchanged data.
	 */
	/**
	 * Save SEO meta data for a post (wp_insert_post hook).
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 */
	public function save_meta_insert_post( int $post_id, $post, bool $update ): void {
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types at all
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			// Log only in debug mode and only once per post type to avoid spam
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_types = array();
				if ( ! isset( $logged_types[ $post_type ] ) ) {
					$this->logger->debug( 'Metabox::save_meta_insert_post skipped - unsupported post type', array(
						'post_id' => $post_id,
						'post_type' => $post_type,
						'supported_types' => $supported_types,
						'update' => $update ? 'yes' : 'no',
						'hook' => 'wp_insert_post',
					) );
					$logged_types[ $post_type ] = true;
				}
			}
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta_insert_post called', array(
				'post_id' => $post_id,
				'post_type' => $post_type,
				'update' => $update ? 'yes' : 'no',
				'hook' => 'wp_insert_post',
			) );
		}
		
		// Solo per update, non per nuovi post
		if ( ! $update ) {
			return;
		}
		
		// Use SeoFieldsSaver for insert_post saves
		$this->fields_saver->save_from_insert( $post_id, $post, $update );
	}

	/**
	 * Save SEO meta data before post is inserted (wp_insert_post_data hook).
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @param array $unsanitized_postarr An array of unsanitized post data.
	 * @param bool  $update  Whether this is an existing post being updated.
	 * @return array Modified post data.
	 */
	public function save_meta_pre_insert( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		// NOTE: This method is DISABLED (hook is commented out in register_hooks())
		// CRITICAL: We should NEVER modify post_status - let WordPress handle it completely
		// Any modification to post_status can interfere with WordPress core saving process
		// This method is kept for reference only but should NOT be called
		
		$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$post_type = isset( $postarr['post_type'] ) ? $postarr['post_type'] : '';
		
		// CRITICAL: Check post type FIRST - if not supported, return data unchanged immediately
		if ( ! empty( $post_type ) ) {
			$supported_types = $this->get_supported_post_types();
			if ( ! in_array( $post_type, $supported_types, true ) ) {
				// Return data unchanged - no interference with unsupported post types
				return $data;
			}
		}
		
		// CRITICAL: DO NOT modify post_status - this was causing auto-draft issues
		// WordPress handles post_status correctly on its own - we should not interfere
		
		// Use SeoFieldsSaver for pre_insert saves (handles excerpt and slug)
		return $this->fields_saver->save_from_pre_insert( $data, $postarr, $unsanitized_postarr, $update );
	}
	
	public function save_meta_pre_update( int $post_id, array $data ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'Metabox::save_meta_pre_update called', array(
				'post_id' => $post_id,
				'hook' => 'pre_post_update',
			) );
		}
		
		// Use SeoFieldsSaver for pre_update saves
		if ( isset( $_POST['fp_seo_performance_metabox_present'] ) ||
			 isset( $_POST['fp_seo_title_sent'] ) ||
			 isset( $_POST['fp_seo_meta_description_sent'] ) ||
			 isset( $_POST['fp_seo_excerpt'] ) ||
			 isset( $_POST['fp_seo_excerpt_sent'] ) ) {
			$this->fields_saver->save_from_post( $post_id );
		}

		// Return data unchanged
		return $data;
	}

	/**
	 * Handles AJAX requests for analyzing SEO.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		// Support both postId (from JS) and post_id (standard)
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : ( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0 );

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		$post = get_post( $post_id );
		
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
		}
		
		// Run analysis - this already returns the complete payload
		$payload = $this->run_analysis_for_post( $post );
		
		wp_send_json_success( $payload );
	}


	/**
	 * Get supported post types for the metabox.
	 *
	 * @return array
	 */
	public function get_supported_post_types(): array {
		return PostTypes::analyzable();
	}

	/**
	 * Get analysis runner instance.
	 *
	 * @return \FP\SEO\Editor\Services\AnalysisRunner|null
	 */
	public function get_analysis_runner() {
		return $this->analysis_runner;
	}

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_post_excluded( int $post_id ): bool {
		// DISABLED: Cache clearing interferes with WordPress's post object during page load
		$excluded = get_post_meta( $post_id, self::META_EXCLUDE, true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( '' === $excluded ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, self::META_EXCLUDE ) );
			if ( $db_value !== null ) {
				$excluded = $db_value;
			}
		}
		
		return '1' === $excluded;
	}

	/**
	 * Run analysis for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	/**
	 * Run SEO analysis for a post.
	 *
	 * Uses AnalysisRunner (always available via DI container).
	 *
	 * @param WP_Post $post Post object.
	 * @return array Analysis payload.
	 * @throws \RuntimeException If AnalysisRunner is not available.
	 */
	public function run_analysis_for_post( WP_Post $post ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: run_analysis_for_post() called for post_id=' . $post->ID );
		}
		
		// Use AnalysisRunner (always available via DI container)
		if ( ! $this->analysis_runner ) {
			throw new \RuntimeException( 'AnalysisRunner is not available. Ensure it is registered in the DI container.' );
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: Using AnalysisRunner' );
		}
		$result = $this->analysis_runner->run( $post );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: AnalysisRunner returned - checks_count=' . ( isset( $result['checks'] ) && is_array( $result['checks'] ) ? count( $result['checks'] ) : 0 ) );
		}
		return $result;
	}

	/**
	 * Compile analysis payload for frontend.
	 *
	 * @param Context $context Analysis context.
	 * @return array
	 */
	private function compile_analysis_payload( Context $context ): array {
		$analyzer = new Analyzer();
		$analysis = $analyzer->analyze( $context );
		$score_engine = new ScoreEngine();
		
		// Analyzer::analyze() returns an array with 'checks' and 'summary' keys
		// ScoreEngine::calculate() expects an array of checks indexed by check ID
		$checks_array = $analysis['checks'] ?? array();
		$score = $score_engine->calculate( $checks_array );

		// Use AnalysisDataService (always available via DI container)
		$formatted_checks = $this->analysis_data_service->format_checks_for_frontend( $checks_array );
		return $this->analysis_data_service->compile_payload( $score, $formatted_checks );
	}


	/**
	 * Handle AJAX request to save SEO Title and Meta Description fields.
	 * This is a separate endpoint to ensure fields are saved reliably.
	 */
	public function handle_save_fields_ajax(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : ( isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0 );

		// Use validator for all validation
		$validator = $this->get_validator();
		$validation = $validator->validate_ajax_request( $post_id, $nonce, self::AJAX_SAVE_FIELDS );
		
		if ( ! $validation['valid'] ) {
			$status_code = isset( $validation['post_type'] ) ? 400 : 403;
			wp_send_json_error( array( 'message' => $validation['error'] ?? __( 'Validation failed.', 'fp-seo-performance' ) ), $status_code );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->debug( 'handle_save_fields_ajax called', array(
				'post_id' => $post_id,
				'ajax_post_keys' => array_keys( $_POST ),
			) );
		}

		// Use dedicated service for saving fields
		try {
			$saved = $this->fields_saver->save_from_post( $post_id );
			$result = ! empty( $saved );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$this->logger->debug( 'AJAX save successful', array( 'post_id' => $post_id, 'saved_fields' => array_keys( $saved ) ) );
			}
		} catch ( \Exception $e ) {
			$this->logger->error( 'AJAX save error', array(
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			) );
			$result = false;
		} catch ( \Error $e ) {
			$this->logger->error( 'AJAX save fatal error', array(
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			) );
			$result = false;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->logger->info( 'FP SEO: Fields saved via AJAX', array(
				'post_id' => $post_id,
				'result' => $result,
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Fields saved successfully.', 'fp-seo-performance' ),
			'saved' => $result,
		) );
	}


	/**
	 * Handle AJAX request to save images data - REMOVED
	 */
	public function handle_save_images_ajax(): void {
		wp_send_json_error( array( 'message' => __( 'Image optimization feature has been removed.', 'fp-seo-performance' ) ), 410 );
	}

	/**
	 * Handle AJAX request to reload images section - REMOVED
	 */
	public function handle_reload_images_section_ajax(): void {
		wp_send_json_error( array( 'message' => __( 'Image optimization feature has been removed.', 'fp-seo-performance' ) ), 410 );
	}

	/**
	 * Update images in post content - REMOVED
	 *
	 * @param string $content Post content.
	 * @param array<string, array{alt: string, title: string, description: string}> $images_data Images data.
	 * @return string Updated content.
	 */
	private function update_images_in_content( string $content, array $images_data ): string {
		// Image content updating removed - method kept for backward compatibility
		return $content;
	}

	/**
	 * Get attachment ID from image URL - REMOVED
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null if not found.
	 */
	private function get_attachment_id_from_url( string $url ): ?int {
		// Image attachment handling removed - method kept for backward compatibility
		return null;
	}

	/**
	 * Handle AJAX request for lazy-loaded image extraction - REMOVED
	 *
	 * @return void
	 */
	public function handle_extract_images_ajax(): void {
		wp_send_json_error( array( 'message' => __( 'Image optimization feature has been removed.', 'fp-seo-performance' ) ), 410 );
	}

	/**
	 * Get lazy service loader instance.
	 *
	 * @return \FP\SEO\Editor\Services\LazyServiceLoader Lazy loader instance.
	 */
	private function get_lazy_loader(): \FP\SEO\Editor\Services\LazyServiceLoader {
		if ( $this->lazy_loader === null ) {
			$this->lazy_loader = new \FP\SEO\Editor\Services\LazyServiceLoader( $this->logger );
		}
		return $this->lazy_loader;
	}

	/**
	 * Get validator instance.
	 *
	 * @return \FP\SEO\Editor\Services\MetaboxValidator Validator instance.
	 */
	private function get_validator(): \FP\SEO\Editor\Services\MetaboxValidator {
		if ( $this->validator === null ) {
			$this->validator = new \FP\SEO\Editor\Services\MetaboxValidator( $this->logger );
		}
		return $this->validator;
	}

	/**
	 * Get state manager instance.
	 *
	 * @return \FP\SEO\Editor\Services\MetaboxStateManager State manager instance.
	 */
	private function get_state_manager(): \FP\SEO\Editor\Services\MetaboxStateManager {
		if ( $this->state_manager === null ) {
			$this->state_manager = new \FP\SEO\Editor\Services\MetaboxStateManager( $this->logger );
		}
		return $this->state_manager;
	}

}
