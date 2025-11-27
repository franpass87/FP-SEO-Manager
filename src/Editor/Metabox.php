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
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;
use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\Logger;
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
	private const AJAX_ACTION  = 'fp_seo_performance_analyze';
	private const AJAX_SAVE_FIELDS = 'fp_seo_performance_save_fields';
	private const AJAX_SAVE_IMAGES = 'fp_seo_save_images_data';
	private const AJAX_RELOAD_IMAGES_SECTION = 'fp_seo_reload_images_section';
	private const AJAX_EXTRACT_IMAGES = 'fp_seo_extract_images';
	public const META_EXCLUDE         = '_fp_seo_performance_exclude';
	public const META_FOCUS_KEYWORD   = '_fp_seo_focus_keyword';
	public const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * @var MetaboxRenderer
	 */
	private $renderer;

	/**
	 * Costruttore - registra gli hook immediatamente e inizializza il renderer
	 */
	public function __construct() {
		// REGISTRA GLI HOOK IMMEDIATAMENTE nel costruttore per garantire che vengano sempre registrati
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::__construct() called' );
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
			Logger::error( 'FP SEO: Failed to initialize MetaboxRenderer in constructor', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			$this->renderer = null;
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
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: add_meta_boxes hook registered in __construct()' );
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::__construct() completed - hooks and renderer should be initialized', array(
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
			Logger::error( $error_msg );
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
			Logger::error( $error_msg, array(
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
			Logger::error( $error_msg );
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
					Logger::error( $error_msg );
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
				Logger::error( $error_msg );
				throw new \RuntimeException( $error_msg );
			}
		}

		// Istanzia il renderer - DEVE funzionare
		try {
			$this->renderer = new MetaboxRenderer();
		} catch ( \Throwable $e ) {
			$error_msg = sprintf(
				'FP SEO: Failed to instantiate MetaboxRenderer: %s',
				$e->getMessage()
			);
			Logger::error( $error_msg, array(
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
			Logger::error( $error_msg );
			throw new \RuntimeException( $error_msg );
		}

		// Verifica che il renderer abbia il metodo render()
		if ( ! method_exists( $this->renderer, 'render' ) ) {
			$error_msg = 'FP SEO: MetaboxRenderer instance missing render() method';
			Logger::error( $error_msg, array(
				'methods' => get_class_methods( $this->renderer ),
			) );
			throw new \RuntimeException( $error_msg );
		}

		// Log successo sempre (non solo in debug mode) per verificare che funzioni
		Logger::info( 'FP SEO: MetaboxRenderer initialized successfully', array(
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
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_meta' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_meta' ), 10, 3 );
			}
			// DISABLED: These hooks were causing auto-draft creation when opening editor
			// if ( ! has_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ) ) ) {
			// 	add_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ), 10, 2 );
			// }
			// if ( ! has_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ) ) ) {
			// 	add_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ), 10, 3 );
			// }
		}
		
		// DISABLED: Generic hooks removed to prevent ANY interference with unsupported post types
		// Only post-type-specific hooks are registered above, which ensures zero interference
		// with custom post types like Nectar Sliders, attachments, etc.
		//
		// If you need to support a new post type, add it to PostTypes::analyzable() and
		// the hooks will be automatically registered via the loop above.
		
		// DISABLED: Removed all homepage protection workarounds
		// We need to find the root cause instead of patching symptoms
		// The problem is that WordPress creates a new auto-draft when opening homepage editor
		// This might be caused by something in the rendering or by another plugin/template
		// 
		// if ( ! has_action( 'transition_post_status', array( $this, 'prevent_homepage_auto_draft' ) ) ) {
		// 	add_action( 'transition_post_status', array( $this, 'prevent_homepage_auto_draft' ), 1, 3 );
		// }
		
		// DISABLED - This was also causing issues with homepage
		// if ( ! has_action( 'shutdown', array( $this, 'fix_homepage_status_on_shutdown' ) ) ) {
		// 	add_action( 'shutdown', array( $this, 'fix_homepage_status_on_shutdown' ), 999 );
		// }
		
		// DISABLED - Homepage status tracking was causing issues
		// if ( ! has_action( 'init', array( $this, 'save_homepage_original_status' ) ) ) {
		// 	add_action( 'init', array( $this, 'save_homepage_original_status' ), 1 );
		// }
		
		// DISABLED - Was causing redirects when creating new sliders/CPTs
		// See prevent_homepage_auto_draft_creation() for details
		// if ( ! has_action( 'admin_init', array( $this, 'prevent_homepage_auto_draft_creation' ) ) ) {
		// 	add_action( 'admin_init', array( $this, 'prevent_homepage_auto_draft_creation' ), 1 );
		// }
		
		// DIAGNOSTIC: Hook to detect when auto-draft is created for homepage
		// This helps identify what's creating the auto-draft
		if ( ! has_action( 'wp_insert_post', array( $this, 'diagnose_auto_draft_creation' ) ) ) {
			add_action( 'wp_insert_post', array( $this, 'diagnose_auto_draft_creation' ), 999, 3 );
		}
		
		// CRITICAL FIX: Prevent auto-draft creation when editing homepage
		// Intercept wp_insert_post_data to prevent auto-draft creation
		if ( ! has_filter( 'wp_insert_post_data', array( $this, 'prevent_homepage_auto_draft_on_edit' ) ) ) {
			add_filter( 'wp_insert_post_data', array( $this, 'prevent_homepage_auto_draft_on_edit' ), 10, 2 );
		}
		
		// CRITICAL FIX: Prevent homepage from becoming auto-draft when saving
		// This hook runs when WordPress updates a post
		if ( ! has_action( 'wp_update_post', array( $this, 'prevent_homepage_auto_draft_on_update' ) ) ) {
			add_action( 'wp_update_post', array( $this, 'prevent_homepage_auto_draft_on_update' ), 1, 2 );
		}
		
		// CRITICAL FIX: Also hook into edit_post to catch status changes
		if ( ! has_action( 'edit_post', array( $this, 'prevent_homepage_auto_draft_on_edit_post' ) ) ) {
			add_action( 'edit_post', array( $this, 'prevent_homepage_auto_draft_on_edit_post' ), 1, 2 );
		}
		
		// CRITICAL FIX: Force WordPress to load correct homepage when opening editor
		// This intercepts the post loading before WordPress displays it in the editor
		if ( ! has_action( 'admin_init', array( $this, 'force_correct_homepage_in_editor' ) ) ) {
			add_action( 'admin_init', array( $this, 'force_correct_homepage_in_editor' ), 1 );
		}
		
		// Also hook into current_screen to fix post after WordPress loads it
		if ( ! has_action( 'current_screen', array( $this, 'force_correct_homepage_on_screen' ) ) ) {
			add_action( 'current_screen', array( $this, 'force_correct_homepage_on_screen' ), 999 );
		}
		
		// CRITICAL: Filter get_post to always return homepage when editing homepage
		// This intercepts ALL calls to get_post() and ensures we get the correct post
		if ( ! has_filter( 'get_post', array( $this, 'filter_get_post_for_homepage' ) ) ) {
			add_filter( 'get_post', array( $this, 'filter_get_post_for_homepage' ), 10, 2 );
		}
		
		// Log registrazione solo in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox hooks registered in register_hooks()', array(
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
		// Log chiamata al metodo register
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'FP SEO: Metabox::register() called', array(
				'admin_context' => is_admin(),
				'hook' => current_filter(),
			) );
		}
		
		// Il renderer viene già inizializzato nel costruttore per garantire che sia sempre disponibile
		// Qui verifichiamo solo che sia stato inizializzato correttamente e tentiamo di reinizializzarlo se necessario
		if ( $this->renderer === null ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'FP SEO: Renderer is null in register(), attempting to reinitialize' );
			}
			try {
				$this->initialize_renderer();
			} catch ( \Throwable $e ) {
				// Se l'inizializzazione fallisce, logga l'errore ma non bloccare register()
				// Il renderer verrà reinizializzato quando necessario in render()
				Logger::error( 'FP SEO: Failed to reinitialize MetaboxRenderer in register()', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
		
		// Log stato finale del renderer
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'FP SEO: Metabox::register() completed', array(
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
			if ( ! has_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) ) ) {
				add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'FP SEO: add_meta_boxes hook registered in register() (fallback)' );
				}
			}
		}
	}
	
	// Gli hook di salvataggio sono già registrati nel costruttore
	// Verifica che non siano già stati registrati prima di registrarli di nuovo
	if ( ! has_action( 'save_post', array( $this, 'save_meta' ) ) ) {
		$this->register_hooks();
	}
		
		// Questo permette al salvataggio di funzionare anche se il rendering fallisce
		try {
			
			// Hook per REST API (Gutenberg) - registra per tutti i post types supportati
			$post_types = PostTypes::analyzable();
			foreach ( $post_types as $post_type ) {
				add_action( 'rest_after_insert_' . $post_type, array( $this, 'save_meta_rest' ), 10, 3 );
			}
			
			// Registra meta fields per REST API (Gutenberg)
			add_action( 'rest_api_init', array( $this, 'register_rest_meta_fields' ) );
			
			// Hook pre_post_update rimosso - usiamo solo save_post per evitare doppi salvataggi
			// add_filter( 'pre_post_update', array( $this, 'save_meta_pre_update' ), 5, 2 );
			
			// Use priority 5 to ensure wp.media is loaded early, before other plugins
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 5, 0 );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_SAVE_FIELDS, array( $this, 'handle_save_fields_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_SAVE_IMAGES, array( $this, 'handle_save_images_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_RELOAD_IMAGES_SECTION, array( $this, 'handle_reload_images_section_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_EXTRACT_IMAGES, array( $this, 'handle_extract_images_ajax' ) );
			add_action( 'admin_head', array( $this, 'inject_modern_styles' ) );
		} catch ( \Throwable $e ) {
			// Se anche la registrazione degli hook fallisce, logga ma non bloccare
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Failed to register metabox hooks', array(
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
		try {
			$post_types = $this->get_supported_post_types();
			if ( ! is_array( $post_types ) || empty( $post_types ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'FP SEO: No supported post types found', array( 'post_types' => $post_types ) );
				}
				return;
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: Registering metabox for post types', array(
					'post_types' => $post_types,
				) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error getting supported post types', array(
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
				Logger::debug( 'FP SEO: Metabox registered for post type', array(
					'post_type' => $post_type,
				) );
			}
		}
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 */
	public function enqueue_assets(): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = in_array( $screen->base, array( 'upload', 'media' ), true ) || $screen->id === 'upload';
		if ( $is_media_page ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		// Ensure wp.media is available for image uploads (including featured image)
		// This must be called early to support WordPress core featured image button
		wp_enqueue_media();
		
		// Also ensure set-post-thumbnail script is loaded (required for featured image button)
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'set-post-thumbnail' );
		}
		
		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
		wp_enqueue_script( 'fp-seo-performance-serp-preview' );
		wp_enqueue_script( 'fp-seo-performance-ai-generator' );
		wp_enqueue_script( 'fp-seo-performance-metabox-ai-fields' );

		// Prepara i dati per il JavaScript PRIMA che il module si carichi
		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			try {
			$analysis = $this->run_analysis_for_post( $post );
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Error running analysis in enqueue_assets', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Fatal error running analysis in enqueue_assets', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			}
		}

		// Get AI configuration
		$ai_enabled = Options::get_option( 'ai.enable_auto_generation', true );
		$api_key    = Options::get_option( 'ai.openai_api_key', '' );

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
			'apiKeyPresent' => ! empty( $api_key ),
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

		// Reload images section when featured image is set/removed
		jQuery(document).ready(function() {
			// Listen for WordPress featured image events
			jQuery(document).on('wp-set-post-thumbnail', function(event, thumbnailId) {
				console.log('FP SEO: wp-set-post-thumbnail event triggered', { thumbnailId: thumbnailId });
				// Reload images section after a short delay to ensure thumbnail is saved
				setTimeout(function() {
					reloadImagesSection();
				}, 500);
			});

			// Also listen for thumbnail removal
			jQuery(document).on('wp-remove-post-thumbnail', function() {
				console.log('FP SEO: wp-remove-post-thumbnail event triggered');
				setTimeout(function() {
					reloadImagesSection();
				}, 500);
			});

			// Function to reload images section via AJAX
			function reloadImagesSection() {
				console.log('FP SEO: reloadImagesSection() called');
				const postId = jQuery('#post_ID').val();
				if (!postId) {
					console.warn('FP SEO: No post ID found, skipping reload');
					return;
				}

				const imagesSection = jQuery('.fp-seo-performance-metabox__section').filter(function() {
					const title = jQuery(this).find('h4').text();
					return title.includes('Images Optimization') || title.includes('Images');
				});

				if (imagesSection.length === 0) {
					console.warn('FP SEO: Images section not found, skipping reload');
					return;
				}

				console.log('FP SEO: Reloading images section for post', postId);

				// Show loading indicator
				imagesSection.find('.fp-seo-performance-metabox__section-content').html(
					'<div style="padding: 24px; text-align: center;"><span style="color: #8b5cf6;">⏳ Caricamento immagini...</span></div>'
				);

				// AJAX request to reload images section
				// Use the nonce generated inline (not from a hidden field)
				const reloadNonce = '<?php echo esc_js( wp_create_nonce( 'fp_seo_reload_images_nonce' ) ); ?>';
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: '<?php echo esc_js( self::AJAX_RELOAD_IMAGES_SECTION ); ?>',
						post_id: postId,
						nonce: reloadNonce
					},
					success: function(response) {
						console.log('FP SEO: AJAX success', response);
						if (response.success && response.data && response.data.html) {
							imagesSection.find('.fp-seo-performance-metabox__section-content').html(response.data.html);
							console.log('FP SEO: Images section reloaded successfully');
						} else {
							console.error('FP SEO: Error reloading images section', response);
						}
					},
					error: function(xhr, status, error) {
						console.error('FP SEO: AJAX error reloading images section', { status, error, responseText: xhr.responseText });
					}
				});
			}
		});
	});
	</script>
		<?php
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

	/**
	 * Renders the metabox content.
	 *
	 * @param WP_Post $post Current post instance.
	 */
	public function render( WP_Post $post ): void {
		// Validazione input
		if ( ! $post instanceof WP_Post ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Invalid post object in render', array(
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
		
		// CRITICAL: Clean up any auto-drafts when editing homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id > 0 && $requested_post_id === $page_on_front_id ) {
			// Delete any auto-draft pages that might have been created
			global $wpdb;
			$auto_drafts = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'auto-draft' AND post_author = %d ORDER BY ID DESC LIMIT 10",
				get_current_user_id()
			) );
			
			foreach ( $auto_drafts as $auto_draft_id ) {
				if ( (int) $auto_draft_id !== $page_on_front_id ) {
					wp_delete_post( (int) $auto_draft_id, true ); // Force delete
					Logger::debug( 'Metabox::render - Deleted auto-draft page', array(
						'auto_draft_id' => $auto_draft_id,
						'homepage_id' => $page_on_front_id,
					) );
				}
			}
		}
		
		if ( $requested_post_id > 0 && $post->ID !== $requested_post_id ) {
			// WordPress passed wrong post - get the correct one from URL
			$correct_post = get_post( $requested_post_id );
			if ( $correct_post instanceof WP_Post ) {
				Logger::warning( 'Metabox::render - WordPress passed wrong post, correcting', array(
					'requested_post_id' => $requested_post_id,
					'wrong_post_id' => $post->ID,
					'wrong_post_type' => $post->post_type,
					'correct_post_id' => $correct_post->ID,
					'correct_post_type' => $correct_post->post_type,
				) );
				$post = $correct_post;
				$post_was_corrected = true;
				// Also fix global post to prevent other issues
				global $wp_query;
				$GLOBALS['post'] = $correct_post;
				if ( isset( $wp_query ) ) {
					$wp_query->post = $correct_post;
				}
			}
		}
		
		// COMPREHENSIVE DIAGNOSTIC: Always show extensive diagnostic info when editing homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id > 0 && $requested_post_id === $page_on_front_id ) {
			// We're trying to edit the homepage - show diagnostic info
			$correct_post = get_post( $page_on_front_id );
			$correct_status = $correct_post instanceof WP_Post ? $correct_post->post_status : 'unknown';
			$is_wrong_post = $post->ID !== $page_on_front_id || $post->post_status === 'auto-draft';
			
			// If we corrected the post, show success message
			if ( $post_was_corrected && $post->ID === $page_on_front_id ) {
				$is_wrong_post = false; // Post is now correct
			}
			
			// Get global post for comparison
			global $wp_query, $pagenow;
			$global_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
			$global_post_id = $global_post instanceof WP_Post ? $global_post->ID : 0;
			$global_post_status = $global_post instanceof WP_Post ? $global_post->post_status : 'none';
			
			// Check if this is AJAX
			$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
			$is_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
			
			// Get current screen info
			$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$screen_id = $current_screen ? $current_screen->id : 'unknown';
			$screen_base = $current_screen ? $current_screen->base : 'unknown';
			
			// COMPREHENSIVE DIAGNOSTIC: Gather extensive information
			global $wpdb, $wp_filter;
			
			// Get all auto-drafts in database
			$auto_drafts_in_db = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title, post_author, post_date, post_date_gmt, post_modified, post_modified_gmt 
				FROM {$wpdb->posts} 
				WHERE post_type = 'page' AND post_status = 'auto-draft' 
				AND post_author = %d 
				ORDER BY ID DESC 
				LIMIT 20",
				get_current_user_id()
			), ARRAY_A );
			
			// Get homepage from DB directly
			$homepage_from_db = $wpdb->get_row( $wpdb->prepare(
				"SELECT ID, post_title, post_status, post_type, post_content, post_modified 
				FROM {$wpdb->posts} 
				WHERE ID = %d",
				$page_on_front_id
			), ARRAY_A );
			
			// Check what hooks are active on wp_insert_post
			$wp_insert_post_hooks = array();
			if ( isset( $wp_filter['wp_insert_post'] ) ) {
				foreach ( $wp_filter['wp_insert_post']->callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook ) {
						$callback = is_array( $hook['function'] ) 
							? ( is_object( $hook['function'][0] ) ? get_class( $hook['function'][0] ) . '::' . $hook['function'][1] : 'array' )
							: ( is_string( $hook['function'] ) ? $hook['function'] : 'closure' );
						$wp_insert_post_hooks[] = array(
							'priority' => $priority,
							'callback' => $callback,
						);
					}
				}
			}
			
			// Check what hooks are active on save_post
			$save_post_hooks = array();
			if ( isset( $wp_filter['save_post'] ) ) {
				foreach ( $wp_filter['save_post']->callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook ) {
						$callback = is_array( $hook['function'] ) 
							? ( is_object( $hook['function'][0] ) ? get_class( $hook['function'][0] ) . '::' . $hook['function'][1] : 'array' )
							: ( is_string( $hook['function'] ) ? $hook['function'] : 'closure' );
						$save_post_hooks[] = array(
							'priority' => $priority,
							'callback' => $callback,
						);
					}
				}
			}
			
			// Check what hooks are active on get_post
			$get_post_filters = array();
			if ( isset( $wp_filter['get_post'] ) ) {
				foreach ( $wp_filter['get_post']->callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook ) {
						$callback = is_array( $hook['function'] ) 
							? ( is_object( $hook['function'][0] ) ? get_class( $hook['function'][0] ) . '::' . $hook['function'][1] : 'array' )
							: ( is_string( $hook['function'] ) ? $hook['function'] : 'closure' );
						$get_post_filters[] = array(
							'priority' => $priority,
							'callback' => $callback,
						);
					}
				}
			}
			
			// Get post object from multiple sources for comparison
			$post_from_get_post = get_post( $page_on_front_id );
			$post_from_get_post_edit = get_post( $page_on_front_id, OBJECT, 'edit' );
			
			// Check if there's a post lock
			$post_lock = wp_check_post_lock( $page_on_front_id );
			$post_lock_user = $post_lock ? get_userdata( $post_lock ) : null;
			
			// Check autosave
			$autosave = wp_get_post_autosave( $page_on_front_id );
			
			// Always show comprehensive diagnostic notice when editing homepage
			add_action( 'admin_notices', function() use ( 
				$requested_post_id, $post, $page_on_front_id, $correct_status, $is_wrong_post, 
				$global_post_id, $global_post_status, $is_ajax, $is_autosave, $screen_id, $screen_base,
				$auto_drafts_in_db, $homepage_from_db, $wp_insert_post_hooks, $save_post_hooks, $get_post_filters,
				$post_from_get_post, $post_from_get_post_edit, $post_lock, $post_lock_user, $autosave
			) {
				$notice_class = 'notice-warning';
				$notice_color = '#f59e0b';
				$icon = '🔍';
				?>
				<div class="notice <?php echo esc_attr( $notice_class ); ?>" style="border-left-color: <?php echo esc_attr( $notice_color ); ?>; padding: 15px; margin: 20px 0; max-width: 95%;">
					<h3 style="margin: 0 0 12px 0; color: <?php echo esc_attr( $notice_color ); ?>; font-size: 16px;"><?php echo esc_html( $icon ); ?> FP SEO: Diagnostica Completa Homepage</h3>
					
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
						<div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
							<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #374151;">📋 Informazioni Base</h4>
							<ul style="margin: 0; padding-left: 20px; font-size: 12px; line-height: 1.8;">
								<li><strong>URL richiesto:</strong> <code>post=<?php echo esc_html( $requested_post_id ); ?>&action=edit</code></li>
								<li><strong>Post ricevuto (render):</strong> ID <?php echo esc_html( $post->ID ); ?> - Status: <code><?php echo esc_html( $post->post_status ); ?></code> - Type: <code><?php echo esc_html( $post->post_type ); ?></code></li>
								<li><strong>Post globale:</strong> ID <?php echo esc_html( $global_post_id ); ?> - Status: <code><?php echo esc_html( $global_post_status ); ?></code></li>
								<li><strong>Homepage (DB diretto):</strong> ID <?php echo esc_html( $homepage_from_db['ID'] ?? 'N/A' ); ?> - Status: <code><?php echo esc_html( $homepage_from_db['post_status'] ?? 'N/A' ); ?></code></li>
								<li><strong>get_post(ID):</strong> ID <?php echo esc_html( $post_from_get_post->ID ?? 'N/A' ); ?> - Status: <code><?php echo esc_html( $post_from_get_post->post_status ?? 'N/A' ); ?></code></li>
								<li><strong>get_post(ID, 'edit'):</strong> ID <?php echo esc_html( $post_from_get_post_edit->ID ?? 'N/A' ); ?> - Status: <code><?php echo esc_html( $post_from_get_post_edit->post_status ?? 'N/A' ); ?></code></li>
								<li><strong>Post Lock:</strong> <?php echo $post_lock ? 'Sì (User ID: ' . esc_html( $post_lock ) . ' - ' . esc_html( $post_lock_user->user_login ?? 'unknown' ) . ')' : 'No'; ?></li>
								<li><strong>Autosave:</strong> <?php echo $autosave ? 'Sì (ID: ' . esc_html( $autosave->ID ) . ')' : 'No'; ?></li>
							</ul>
						</div>
						
						<div style="background: #fef3c7; padding: 12px; border-radius: 4px;">
							<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #92400e;">🗄️ Auto-Draft nel Database</h4>
							<?php if ( empty( $auto_drafts_in_db ) ) { ?>
								<p style="margin: 0; font-size: 12px; color: #10b981;">✓ Nessun auto-draft trovato</p>
							<?php } else { ?>
								<p style="margin: 0 0 8px 0; font-size: 12px; color: #dc2626;"><strong>⚠️ Trovati <?php echo count( $auto_drafts_in_db ); ?> auto-draft:</strong></p>
								<ul style="margin: 0; padding-left: 20px; font-size: 11px; line-height: 1.6; max-height: 200px; overflow-y: auto;">
									<?php foreach ( array_slice( $auto_drafts_in_db, 0, 10 ) as $ad ) { ?>
										<li>ID <?php echo esc_html( $ad['ID'] ); ?> - Creato: <?php echo esc_html( $ad['post_date'] ); ?></li>
									<?php } ?>
								</ul>
							<?php } ?>
						</div>
					</div>
					
					<div style="margin: 15px 0; background: #eff6ff; padding: 12px; border-radius: 4px;">
						<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #1e40af;">🔗 Hook Attivi</h4>
						<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; font-size: 11px;">
							<div>
								<strong>wp_insert_post (<?php echo count( $wp_insert_post_hooks ); ?>):</strong>
								<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
									<?php foreach ( array_slice( $wp_insert_post_hooks, 0, 10 ) as $hook ) { ?>
										<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
									<?php } ?>
								</ul>
							</div>
							<div>
								<strong>save_post (<?php echo count( $save_post_hooks ); ?>):</strong>
								<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
									<?php foreach ( array_slice( $save_post_hooks, 0, 10 ) as $hook ) { ?>
										<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
									<?php } ?>
								</ul>
							</div>
							<div>
								<strong>get_post filter (<?php echo count( $get_post_filters ); ?>):</strong>
								<ul style="margin: 4px 0 0 0; padding-left: 18px; line-height: 1.5; max-height: 150px; overflow-y: auto;">
									<?php foreach ( array_slice( $get_post_filters, 0, 10 ) as $hook ) { ?>
										<li>P<?php echo esc_html( $hook['priority'] ); ?>: <?php echo esc_html( $hook['callback'] ); ?></li>
									<?php } ?>
								</ul>
							</div>
						</div>
					</div>
					
					<div style="margin: 15px 0; background: #f3f4f6; padding: 12px; border-radius: 4px; font-size: 12px;">
						<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #374151;">📊 Contesto</h4>
						<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
							<li><strong>Screen:</strong> <code><?php echo esc_html( $screen_id ); ?></code> (<?php echo esc_html( $screen_base ); ?>)</li>
							<li><strong>AJAX:</strong> <?php echo $is_ajax ? 'Sì' : 'No'; ?> | <strong>Autosave:</strong> <?php echo $is_autosave ? 'Sì' : 'No'; ?></li>
							<li><strong>REQUEST_URI:</strong> <code style="font-size: 10px;"><?php echo esc_html( $_SERVER['REQUEST_URI'] ?? 'N/A' ); ?></code></li>
							<li><strong>POST data:</strong> <?php echo ! empty( $_POST ) ? count( $_POST ) . ' campi' : 'Nessuno'; ?></li>
						</ul>
					</div>
					
					<?php if ( ! empty( $homepage_from_db ) ) { ?>
						<div style="margin: 15px 0; background: #f0fdf4; padding: 12px; border-radius: 4px; font-size: 11px;">
							<h4 style="margin: 0 0 8px 0; font-size: 14px; color: #166534;">✅ Homepage dal DB</h4>
							<ul style="margin: 0; padding-left: 20px; line-height: 1.8;">
								<li><strong>ID:</strong> <?php echo esc_html( $homepage_from_db['ID'] ); ?></li>
								<li><strong>Status:</strong> <code><?php echo esc_html( $homepage_from_db['post_status'] ); ?></code></li>
								<li><strong>Type:</strong> <code><?php echo esc_html( $homepage_from_db['post_type'] ); ?></code></li>
								<li><strong>Content length:</strong> <?php echo strlen( $homepage_from_db['post_content'] ?? '' ); ?> caratteri</li>
								<li><strong>Modified:</strong> <?php echo esc_html( $homepage_from_db['post_modified'] ?? 'N/A' ); ?></li>
							</ul>
						</div>
					<?php } ?>
				</div>
				<?php
			} );
			
			// Log for debugging
			if ( $is_wrong_post ) {
				Logger::warning( 'Metabox::render - WordPress passed wrong post when opening homepage editor', array(
					'requested_post_id' => $requested_post_id,
					'received_post_id' => $post->ID,
					'received_post_status' => $post->post_status,
					'global_post_id' => $global_post_id,
					'global_post_status' => $global_post_status,
					'correct_homepage_id' => $page_on_front_id,
					'correct_homepage_status' => $correct_status,
					'is_ajax' => $is_ajax,
					'is_autosave' => $is_autosave,
					'screen_id' => $screen_id,
					'screen_base' => $screen_base,
				) );
			}
		}
		
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
				Logger::error( 'FP SEO: Error outputting nonce', array(
					'error' => $e->getMessage(),
				) );
			}
		}

		// Se il renderer non è disponibile, FORZA la reinizializzazione - nessun fallback
		if ( ! $this->renderer ) {
			Logger::error( 'FP SEO: MetaboxRenderer is null in render(), forcing reinitialization', array(
				'post_id' => isset( $current_post->ID ) ? $current_post->ID : 0,
				'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
			) );
			
			// Forza la reinizializzazione - DEVE funzionare
			try {
				$this->initialize_renderer();
			} catch ( \Throwable $e ) {
				// Se l'inizializzazione fallisce, logga e mostra errore
				Logger::error( 'FP SEO: Failed to reinitialize MetaboxRenderer in render()', array(
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
				Logger::error( $error_msg );
				echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Errore critico: impossibile inizializzare il metabox SEO.', 'fp-seo-performance' ) . '</strong></p>';
				echo '<p>' . esc_html__( 'Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p></div>';
				return;
			}
		}

		// I dati per JS sono già stati preparati in enqueue_assets()
		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $current_post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			try {
				$analysis = $this->run_analysis_for_post( $current_post );
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Error running analysis', array(
						'post_id' => $current_post->ID,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					) );
				}
				$analysis = array();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Fatal error running analysis', array(
						'post_id' => $current_post->ID,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					) );
				}
				$analysis = array();
			}
		}

		// Use renderer to output HTML con gestione errori robusta
		// Pass current_post instead of modifying the original $post parameter
		try {
			// Verifica che il renderer sia ancora disponibile prima di chiamarlo
			if ( ! $this->renderer ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Renderer is null before render() call', array(
						'post_id' => isset( $current_post->ID ) ? $current_post->ID : ( isset( $post->ID ) ? $post->ID : 0 ),
						'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
					) );
				}
				throw new \RuntimeException( 'Renderer became null before render() call' );
			}
			
			// Log inizio rendering in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: Starting metabox rendering', array(
					'post_id' => isset( $current_post->ID ) ? $current_post->ID : 0,
					'post_type' => isset( $current_post->post_type ) ? $current_post->post_type : 'unknown',
					'excluded' => $excluded,
					'analysis_count' => count( $analysis ),
					'renderer_class' => get_class( $this->renderer ),
				) );
			}
			
			// Chiama il renderer
			$this->renderer->render( $current_post, $analysis, $excluded );
			
			// Log successo in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: Metabox rendering completed successfully' );
			}
		} catch ( \Throwable $e ) {
			// Errore critico - logga e mostra messaggio chiaro
			Logger::error( 'FP SEO: Critical error rendering metabox', array(
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
		Logger::error( 'FP SEO: render_fallback_fields() called - this should never happen!', array(
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
		// CRITICAL: If this is the homepage, ensure it never becomes auto-draft
		$page_on_front_id = (int) get_option( 'page_on_front' );
		$is_homepage = $page_on_front_id > 0 && $post_id === $page_on_front_id;
		
		if ( $is_homepage ) {
			// Get current status from database (not cached)
			global $wpdb;
			$current_status_db = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			) );
			
			// If status is auto-draft, fix it immediately
			if ( $current_status_db === 'auto-draft' ) {
				$original_status = get_post_meta( $post_id, '_fp_seo_original_status', true );
				if ( empty( $original_status ) || $original_status === 'auto-draft' ) {
					$original_status = 'publish'; // Default to publish for homepage
				}
				
				// Fix status immediately
				$wpdb->update(
					$wpdb->posts,
					array( 'post_status' => $original_status ),
					array( 'ID' => $post_id ),
					array( '%s' ),
					array( '%d' )
				);
				
				// Clear cache
				clean_post_cache( $post_id );
				wp_cache_delete( $post_id, 'posts' );
				
				Logger::warning( 'Metabox::save_meta - Fixed homepage status to prevent auto-draft', array(
					'post_id' => $post_id,
					'fixed_status' => $original_status,
				) );
			} else {
				// Save current status as original for future reference
				update_post_meta( $post_id, '_fp_seo_original_status', $current_status_db );
			}
		}
		
		// DIAGNOSTIC: Check if save_meta is being called unexpectedly (e.g., when opening editor)
		$current_status = get_post_status( $post_id );
		$is_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
		$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$has_post_data = ! empty( $_POST );
		$is_real_save_check = ( isset( $_POST['save'] ) && $_POST['save'] !== '' ) || 
							  ( isset( $_POST['publish'] ) && $_POST['publish'] !== '' );
		
		// Show diagnostic notice if save_meta is called unexpectedly for homepage
		if ( $is_homepage && ! $is_real_save_check && ! $is_autosave ) {
			add_action( 'admin_notices', function() use ( $post_id, $current_status, $is_ajax, $has_post_data ) {
				?>
				<div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 12px;">
					<h3 style="margin: 0 0 8px 0; color: #f59e0b;">⚠️ FP SEO: save_meta chiamato inaspettatamente</h3>
					<p style="margin: 0 0 8px 0;"><strong>Problema:</strong> Il metodo save_meta è stato chiamato quando hai aperto l'editor della homepage, non durante un salvataggio.</p>
					<ul style="margin: 8px 0; padding-left: 20px;">
						<li><strong>Post ID:</strong> <?php echo esc_html( $post_id ); ?></li>
						<li><strong>Status:</strong> <code><?php echo esc_html( $current_status ); ?></code></li>
						<li><strong>È AJAX:</strong> <?php echo $is_ajax ? 'Sì' : 'No'; ?></li>
						<li><strong>Ha dati POST:</strong> <?php echo $has_post_data ? 'Sì (' . count( $_POST ) . ' campi)' : 'No'; ?></li>
						<li><strong>URI richiesta:</strong> <code><?php echo esc_html( $_SERVER['REQUEST_URI'] ?? 'unknown' ); ?></code></li>
					</ul>
					<p style="margin: 8px 0 0 0; font-size: 12px; color: #6b7280;">
						<strong>Causa possibile:</strong> Qualche hook sta triggerando save_post anche quando apri l'editor, causando la creazione di auto-draft.
					</p>
				</div>
				<?php
			} );
			
			Logger::warning( 'Metabox::save_meta called unexpectedly for homepage', array(
				'post_id' => $post_id,
				'post_status' => $current_status,
				'is_ajax' => $is_ajax,
				'has_post_data' => $has_post_data,
				'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
			) );
		}
		
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
			Logger::debug( 'Metabox::save_meta called', array(
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
					Logger::debug( 'Metabox::save_meta skipped - unsupported post type (exiting immediately)', array(
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
				Logger::debug( 'Metabox::save_meta already processed for post_id', array(
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
			
			Logger::debug( 'Metabox::save_meta called', array(
				'post_id' => $post_id,
				'update' => $update ? 'yes' : 'no',
				'hook' => current_filter(),
				'post_keys_count' => isset( $_POST ) ? count( $_POST ) : 0,
				'seo_fields' => $seo_fields_present,
			) );
		}
		
		// DISABLED - All homepage status protection code has been removed
		// The plugin should NOT touch post_status at all - this was causing the Auto Draft issue
		
		$saver = new \FP\SEO\Editor\MetaboxSaver();
		$result = $saver->save_all_fields( $post_id );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::save_meta completed', array(
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
					Logger::debug( 'Metabox::save_meta_edit_post skipped - unsupported post type', array(
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
			Logger::debug( 'Metabox::save_meta_edit_post called', array(
				'post_id' => $post_id,
				'post_type' => $post_type,
				'hook' => 'edit_post',
			) );
		}
		
		// Use the same save_meta method but prevent double processing
		$this->save_meta( $post_id, $post, true );
	}
	
	/**
	 * Register SEO meta fields for REST API (Gutenberg support).
	 */
	public function register_rest_meta_fields(): void {
		$post_types = $this->get_supported_post_types();
		
		foreach ( $post_types as $post_type ) {
			// Registra _fp_seo_title
			register_rest_field(
				$post_type,
				'fp_seo_title',
				array(
					'get_callback' => function( $post ) {
						return get_post_meta( $post['id'], '_fp_seo_title', true );
					},
					'update_callback' => function( $value, $post ) {
						if ( $value !== null ) {
							update_post_meta( $post->ID, '_fp_seo_title', sanitize_text_field( $value ) );
						} else {
							delete_post_meta( $post->ID, '_fp_seo_title' );
						}
						return true;
					},
					'schema' => array(
						'description' => __( 'SEO Title', 'fp-seo-performance' ),
						'type' => 'string',
						'context' => array( 'edit' ),
					),
				)
			);
			
			// Registra _fp_seo_meta_description
			register_rest_field(
				$post_type,
				'fp_seo_meta_description',
				array(
					'get_callback' => function( $post ) {
						return get_post_meta( $post['id'], '_fp_seo_meta_description', true );
					},
					'update_callback' => function( $value, $post ) {
						if ( $value !== null ) {
							update_post_meta( $post->ID, '_fp_seo_meta_description', sanitize_textarea_field( $value ) );
						} else {
							delete_post_meta( $post->ID, '_fp_seo_meta_description' );
						}
						return true;
					},
					'schema' => array(
						'description' => __( 'SEO Meta Description', 'fp-seo-performance' ),
						'type' => 'string',
						'context' => array( 'edit' ),
					),
				)
			);
		}
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
			Logger::debug( 'Metabox::save_meta_rest called', array(
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
				Logger::debug( 'REST API - Found SEO fields in request', array(
					'post_id' => $post->ID,
					'has_title' => $seo_title !== null,
					'has_description' => $meta_desc !== null,
				) );
			}
			
			$saver = new \FP\SEO\Editor\MetaboxSaver();
			
			// Simula $_POST per il salvataggio
			if ( $seo_title !== null ) {
				$_POST['fp_seo_title'] = $seo_title;
				$_POST['fp_seo_title_sent'] = '1';
			}
			if ( $meta_desc !== null ) {
				$_POST['fp_seo_meta_description'] = $meta_desc;
				$_POST['fp_seo_meta_description_sent'] = '1';
			}
			if ( $excerpt !== null ) {
				$_POST['fp_seo_excerpt'] = $excerpt;
				$_POST['fp_seo_excerpt_sent'] = '1';
			}
			$_POST['fp_seo_performance_metabox_present'] = '1';
			
			$result = $saver->save_all_fields( $post->ID );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'REST API save completed', array(
					'post_id' => $post->ID,
					'result' => $result ? 'success' : 'failed',
				) );
			}
			
			// Pulisci $_POST per evitare effetti collaterali
			unset( $_POST['fp_seo_title'], $_POST['fp_seo_title_sent'] );
			unset( $_POST['fp_seo_meta_description'], $_POST['fp_seo_meta_description_sent'] );
			unset( $_POST['fp_seo_excerpt'], $_POST['fp_seo_excerpt_sent'] );
			unset( $_POST['fp_seo_performance_metabox_present'] );
			
			// DISABLED - Homepage protection was causing the Auto Draft issue
			// The plugin should not touch post status at all
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'REST API - No SEO fields found in request params', array(
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
					Logger::debug( 'Metabox::save_meta_insert_post skipped - unsupported post type', array(
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
			Logger::debug( 'Metabox::save_meta_insert_post called', array(
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
		
		// Chiama save_meta ma senza il controllo di duplicati (usa hook diverso)
		$saver = new \FP\SEO\Editor\MetaboxSaver();
		$result = $saver->save_all_fields( $post_id );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::save_meta_insert_post completed', array(
				'post_id' => $post_id,
				'result' => $result ? 'success' : 'failed',
			) );
		}
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
		
		// Salva excerpt se presente (sia per nuovi post che per update)
		if ( isset( $_POST['fp_seo_excerpt'] ) || isset( $postarr['fp_seo_excerpt'] ) ) {
			$excerpt = isset( $_POST['fp_seo_excerpt'] ) 
				? sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) )
				: sanitize_textarea_field( (string) ( $postarr['fp_seo_excerpt'] ?? '' ) );
			
			$excerpt = trim( $excerpt );
			
			// Aggiorna direttamente nel data array per assicurarsi che venga salvato
			$data['post_excerpt'] = $excerpt;
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox::save_meta_pre_insert - Excerpt saved', array(
					'post_id' => $post_id,
					'excerpt_length' => strlen( $excerpt ),
					'hook' => 'wp_insert_post_data',
				) );
			}
		}
		
		// Gestisci slug direttamente nell'array $data (evita wp_update_post durante wp_insert_post_data)
		if ( isset( $_POST['fp_seo_slug'] ) ) {
			$slug = trim( sanitize_title( wp_unslash( (string) $_POST['fp_seo_slug'] ) ) );
			if ( '' !== $slug ) {
				$data['post_name'] = $slug;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'Metabox::save_meta_pre_insert - Slug updated in data array', array(
						'post_id' => $post_id,
						'slug' => $slug,
					) );
				}
			}
		}
		
		if ( $post_id > 0 && $update ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox::save_meta_pre_insert called', array(
					'post_id' => $post_id,
					'update' => $update,
					'post_status' => $data['post_status'] ?? 'not set',
					'hook' => 'wp_insert_post_data',
				) );
			}
			
			// IMPORTANTE: NON chiamare save_all_fields qui perché potrebbe chiamare wp_update_post
			// (tramite save_slug e save_excerpt) che può causare problemi durante wp_insert_post_data,
			// specialmente per la homepage. I meta fields verranno salvati tramite save_post hook invece.
			// Slug ed excerpt sono già gestiti direttamente nell'array $data sopra.
		}
		return $data;
	}
	
	/**
	 * Prevents homepage from being set to auto-draft status.
	 * Hook: transition_post_status
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function prevent_homepage_auto_draft( string $new_status, string $old_status, $post ): void {
		// Verifica se è la homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 || ! $post instanceof WP_Post || $post->ID !== $page_on_front_id ) {
			return;
		}
		
		// Se lo status sta cambiando verso 'auto-draft' ma il post esiste già (non è nuovo)
		if ( $new_status === 'auto-draft' && $old_status !== 'auto-draft' && $old_status !== '' ) {
			// Usa un flag statico per evitare loop infiniti
			static $correcting = array();
			if ( isset( $correcting[ $post->ID ] ) ) {
				return;
			}
			$correcting[ $post->ID ] = true;
			
			// Correggi immediatamente usando wp_update_post
			// Rimuovi temporaneamente l'hook per evitare loop
			remove_action( 'transition_post_status', array( $this, 'prevent_homepage_auto_draft' ), 1 );
			
			wp_update_post( array(
				'ID' => $post->ID,
				'post_status' => $old_status,
			) );
			
			// Ripristina l'hook
			add_action( 'transition_post_status', array( $this, 'prevent_homepage_auto_draft' ), 1, 3 );
			
			unset( $correcting[ $post->ID ] );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'Metabox::prevent_homepage_auto_draft - Prevented homepage status change to auto-draft', array(
					'post_id' => $post->ID,
					'old_status' => $old_status,
					'attempted_status' => 'auto-draft',
				) );
			}
		}
	}
	
	/**
	 * Prevents creation of new auto-draft pages when editing homepage.
	 * Hook: admin_init (priority 1)
	 * 
	 * NOTE: This function is DISABLED to prevent interference with other post types.
	 * The auto-draft detection was causing issues when creating new sliders and other CPTs.
	 */
	public function prevent_homepage_auto_draft_creation(): void {
		// DISABLED - This was causing redirects when creating new sliders/CPTs
		// The original code was too aggressive and redirected any auto-draft to homepage
		return;
	}
	
	/**
	 * Save homepage original status at the beginning of the request.
	 * Hook: init (priority 1)
	 */
	public function save_homepage_original_status(): void {
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		// Salva lo status originale in una variabile statica
		global $wpdb;
		$original_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$page_on_front_id
		) );
		
		if ( ! empty( $original_status ) && $original_status !== 'auto-draft' ) {
			// Salva in una transiente che dura solo per questa richiesta
			set_transient( 'fp_seo_homepage_original_status_' . $page_on_front_id, $original_status, 60 );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox::save_homepage_original_status - Saved original status', array(
					'post_id' => $page_on_front_id,
					'original_status' => $original_status,
				) );
			}
		}
	}
	
	/**
	 * Prevent homepage from becoming auto-draft when opening editor.
	 * Hook: wp_insert_post_data
	 * 
	 * @param array $data Post data.
	 * @param array $postarr Original post array.
	 * @return array Modified post data.
	 */
	public function prevent_homepage_auto_draft_data( array $data, array $postarr ): array {
		// Only check if this is the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return $data; // Not using static homepage
		}
		
		// Check if this post is the homepage
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $post_id === 0 ) {
			// New post - check if it's being created as homepage
			// This shouldn't happen, but just in case
			return $data;
		}
		
		if ( $post_id !== $page_on_front_id ) {
			return $data; // Not the homepage
		}
		
		// This is the homepage - prevent it from becoming auto-draft
		if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' ) {
			// Get current status from database to preserve it
			$current_status = get_post_status( $post_id );
			if ( $current_status && $current_status !== 'auto-draft' ) {
				$data['post_status'] = $current_status;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'Metabox::prevent_homepage_auto_draft_data - Prevented homepage from becoming auto-draft', array(
						'post_id' => $post_id,
						'original_status' => $current_status,
						'attempted_status' => 'auto-draft',
					) );
				}
			} else {
				// Fallback to publish if current status is also auto-draft
				$data['post_status'] = 'publish';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'Metabox::prevent_homepage_auto_draft_data - Forced homepage to publish (was auto-draft)', array(
						'post_id' => $post_id,
					) );
				}
			}
		}
		
		return $data;
	}

	/**
	 * Prevent homepage from becoming auto-draft when updating post.
	 * Hook: wp_update_post (priority 1)
	 * 
	 * This intercepts post updates and ensures homepage never becomes auto-draft.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object after update.
	 */
	public function prevent_homepage_auto_draft_on_update( int $post_id, WP_Post $post ): void {
		// Only check if this is the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
			return;
		}
		
		// Get current status from database (not from post object which might be cached)
		global $wpdb;
		$current_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );
		
		// If status is auto-draft, fix it immediately
		if ( $current_status === 'auto-draft' ) {
			// Get original status before update
			$original_status = get_post_meta( $post_id, '_fp_seo_original_status', true );
			if ( empty( $original_status ) || $original_status === 'auto-draft' ) {
				$original_status = 'publish'; // Default to publish for homepage
			}
			
			// Fix status immediately
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $original_status ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Clear cache
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
			
			Logger::warning( 'Metabox::prevent_homepage_auto_draft_on_update - Fixed homepage status after update', array(
				'post_id' => $post_id,
				'fixed_status' => $original_status,
			) );
		}
	}

	/**
	 * Prevent homepage from becoming auto-draft when editing post.
	 * Hook: edit_post (priority 1)
	 * 
	 * This intercepts post edits and ensures homepage never becomes auto-draft.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 */
	public function prevent_homepage_auto_draft_on_edit_post( int $post_id, $post ): void {
		// Only check if this is the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 || $post_id !== $page_on_front_id ) {
			return;
		}
		
		// Save original status before any modifications
		$current_status = get_post_status( $post_id );
		if ( $current_status && $current_status !== 'auto-draft' ) {
			update_post_meta( $post_id, '_fp_seo_original_status', $current_status );
		}
		
		// Get current status from database
		global $wpdb;
		$db_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );
		
		// If status is auto-draft, fix it immediately
		if ( $db_status === 'auto-draft' ) {
			$original_status = get_post_meta( $post_id, '_fp_seo_original_status', true );
			if ( empty( $original_status ) || $original_status === 'auto-draft' ) {
				$original_status = 'publish'; // Default to publish for homepage
			}
			
			// Fix status immediately
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $original_status ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Clear cache
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'posts' );
			
			Logger::warning( 'Metabox::prevent_homepage_auto_draft_on_edit_post - Fixed homepage status during edit', array(
				'post_id' => $post_id,
				'fixed_status' => $original_status,
			) );
		}
	}

	/**
	 * Prevent homepage from becoming auto-draft via transition_post_status hook.
	 * Hook: transition_post_status
	 * This is a backup protection in case wp_insert_post_data doesn't catch it.
	 * 
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param WP_Post $post Post object.
	 */
	public function prevent_homepage_auto_draft_transition( string $new_status, string $old_status, $post ): void {
		// Only check if this is the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return; // Not using static homepage
		}
		
		// Check if this post is the homepage
		if ( ! $post instanceof WP_Post || $post->ID !== $page_on_front_id ) {
			return; // Not the homepage
		}
		
		// If status is changing to auto-draft and it was published before, prevent it
		if ( $new_status === 'auto-draft' && $old_status !== 'auto-draft' && $old_status !== '' ) {
			// Use static flag to prevent infinite loops
			static $correcting = array();
			if ( isset( $correcting[ $post->ID ] ) ) {
				return;
			}
			$correcting[ $post->ID ] = true;
			
			// Immediately correct the status back to what it was
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $old_status ),
				array( 'ID' => $post->ID ),
				array( '%s' ),
				array( '%d' )
			);
			
			// Clear cache
			clean_post_cache( $post->ID );
			wp_cache_delete( $post->ID, 'posts' );
			
			unset( $correcting[ $post->ID ] );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'Metabox::prevent_homepage_auto_draft_transition - Prevented homepage from becoming auto-draft', array(
					'post_id' => $post->ID,
					'old_status' => $old_status,
					'attempted_status' => $new_status,
				) );
			}
		}
	}

	/**
	 * Diagnose when auto-draft is created - helps identify what's creating it.
	 * Hook: wp_insert_post (priority 999)
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function diagnose_auto_draft_creation( int $post_id, $post, bool $update ): void {
		// Only check if this is related to homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		// Get post status
		$post_status = get_post_status( $post_id );
		if ( $post_status !== 'auto-draft' ) {
			return;
		}
		
		// Check if we're trying to edit the homepage
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$is_editing_homepage = $requested_post_id === $page_on_front_id;
		
		// Check if this auto-draft is a page (not a post or other type)
		$post_type = get_post_type( $post_id );
		if ( $post_type !== 'page' ) {
			return;
		}
		
		// If we're editing homepage and an auto-draft page is created, delete it immediately
		if ( $is_editing_homepage ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
			$caller_info = array();
			foreach ( $backtrace as $index => $trace ) {
				if ( $index > 5 ) { // Skip first few frames (this function, WordPress core)
					break;
				}
				if ( isset( $trace['file'] ) && isset( $trace['line'] ) ) {
					$file = str_replace( ABSPATH, '', $trace['file'] );
					$caller_info[] = $file . ':' . $trace['line'] . ( isset( $trace['function'] ) ? ' -> ' . $trace['function'] : '' );
				}
			}
			
			Logger::warning( 'Metabox::diagnose_auto_draft_creation - Auto-draft created while editing homepage, deleting it', array(
				'auto_draft_id' => $post_id,
				'homepage_id' => $page_on_front_id,
				'requested_post_id' => $requested_post_id,
				'is_update' => $update,
				'post_type' => $post_type,
				'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
				'is_ajax' => defined( 'DOING_AJAX' ) && DOING_AJAX,
				'is_autosave' => defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE,
				'has_post_data' => ! empty( $_POST ),
				'caller_trace' => $caller_info,
			) );
			
			// CRITICAL: Delete the auto-draft immediately if it's not the homepage
			// This prevents WordPress from showing it in the editor
			if ( $post_id !== $page_on_front_id ) {
				wp_delete_post( $post_id, true ); // Force delete, bypass trash
				Logger::info( 'Metabox::diagnose_auto_draft_creation - Auto-draft deleted', array(
					'deleted_post_id' => $post_id,
				) );
			}
			
			// Store diagnostic info in transient so it can be displayed in metabox
			set_transient( 'fp_seo_auto_draft_diagnosis_' . $page_on_front_id, array(
				'auto_draft_id' => $post_id,
				'timestamp' => time(),
				'caller_trace' => $caller_info,
				'is_ajax' => defined( 'DOING_AJAX' ) && DOING_AJAX,
				'is_autosave' => defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE,
				'deleted' => $post_id !== $page_on_front_id,
			), 300 ); // 5 minutes
		}
	}

	/**
	 * Force correct homepage in editor - intercepts post loading before WordPress displays it.
	 * Hook: admin_init (priority 1)
	 * 
	 * This ensures that when editing the homepage, WordPress always loads the correct post,
	 * not an auto-draft or wrong post.
	 */
	public function force_correct_homepage_in_editor(): void {
		// Only run on post editor pages
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}
		
		// Check if we're editing the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $requested_post_id !== $page_on_front_id ) {
			return;
		}
		
		// Force WordPress to load the correct homepage
		global $post, $post_type, $post_type_object;
		
		// Get the correct homepage
		$correct_post = get_post( $page_on_front_id, OBJECT, 'edit' );
		if ( ! $correct_post instanceof WP_Post ) {
			return;
		}
		
		// If the global post is wrong or is an auto-draft, fix it
		if ( ! $post || $post->ID !== $page_on_front_id || $post->post_status === 'auto-draft' ) {
			$post = $correct_post;
			$post_type = $correct_post->post_type;
			$post_type_object = get_post_type_object( $post_type );
			
			// Also fix GLOBALS
			$GLOBALS['post'] = $correct_post;
			$GLOBALS['post_type'] = $post_type;
			$GLOBALS['post_type_object'] = $post_type_object;
			
			Logger::info( 'Metabox::force_correct_homepage_in_editor - Forced correct homepage in editor', array(
				'requested_post_id' => $requested_post_id,
				'homepage_id' => $page_on_front_id,
				'corrected_post_id' => $correct_post->ID,
				'corrected_post_status' => $correct_post->post_status,
			) );
		}
		
		// Also delete any auto-drafts that might have been created
		global $wpdb;
		$auto_drafts = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'auto-draft' AND post_author = %d AND ID != %d ORDER BY ID DESC LIMIT 10",
			get_current_user_id(),
			$page_on_front_id
		) );
		
		foreach ( $auto_drafts as $auto_draft_id ) {
			wp_delete_post( (int) $auto_draft_id, true );
			Logger::debug( 'Metabox::force_correct_homepage_in_editor - Deleted auto-draft', array(
				'auto_draft_id' => $auto_draft_id,
			) );
		}
	}

	/**
	 * Force correct homepage on screen load - runs after WordPress loads the post.
	 * Hook: current_screen (priority 999)
	 * 
	 * This ensures the post is correct even after WordPress has loaded it.
	 *
	 * @param WP_Screen $screen Current screen object.
	 */
	public function force_correct_homepage_on_screen( $screen ): void {
		// Only run on post editor pages
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}
		
		// Check if we're editing the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $requested_post_id !== $page_on_front_id ) {
			return;
		}
		
		// Force WordPress to load the correct homepage
		global $post, $post_type, $post_type_object;
		
		// Get the correct homepage
		$correct_post = get_post( $page_on_front_id, OBJECT, 'edit' );
		if ( ! $correct_post instanceof WP_Post ) {
			return;
		}
		
		// If the global post is wrong or is an auto-draft, fix it
		if ( ! $post || $post->ID !== $page_on_front_id || $post->post_status === 'auto-draft' ) {
			$post = $correct_post;
			$post_type = $correct_post->post_type;
			$post_type_object = get_post_type_object( $post_type );
			
			// Also fix GLOBALS
			$GLOBALS['post'] = $correct_post;
			$GLOBALS['post_type'] = $post_type;
			$GLOBALS['post_type_object'] = $post_type_object;
			
			Logger::info( 'Metabox::force_correct_homepage_on_screen - Forced correct homepage on screen load', array(
				'requested_post_id' => $requested_post_id,
				'homepage_id' => $page_on_front_id,
				'corrected_post_id' => $correct_post->ID,
				'corrected_post_status' => $correct_post->post_status,
			) );
		}
	}

	/**
	 * Filter get_post to always return homepage when editing homepage.
	 * Hook: get_post (priority 10)
	 * 
	 * This intercepts ALL calls to get_post() and ensures we get the correct homepage,
	 * not an auto-draft or wrong post.
	 *
	 * @param WP_Post|null $post    The post object or null if not found.
	 * @param int|WP_Post   $post_id Post ID or post object.
	 * @return WP_Post|null Modified post object.
	 */
	public function filter_get_post_for_homepage( $post, $post_id ): ?WP_Post {
		// Only run in admin when editing
		if ( ! is_admin() || ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
			return $post;
		}
		
		// Check if we're editing the homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return $post;
		}
		
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $requested_post_id !== $page_on_front_id ) {
			return $post;
		}
		
		// Get the post ID from the parameter
		$check_post_id = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id;
		
		// If WordPress is trying to load an auto-draft or wrong post, return homepage instead
		if ( $post instanceof WP_Post ) {
			// If the loaded post is an auto-draft page, return homepage instead
			if ( $post->post_status === 'auto-draft' && $post->post_type === 'page' && $check_post_id !== $page_on_front_id ) {
				$correct_post = get_post( $page_on_front_id, OBJECT, 'edit' );
				if ( $correct_post instanceof WP_Post ) {
					Logger::info( 'Metabox::filter_get_post_for_homepage - Replaced auto-draft with homepage', array(
						'auto_draft_id' => $post->ID,
						'homepage_id' => $page_on_front_id,
					) );
					return $correct_post;
				}
			}
			
			// If WordPress is trying to load a different post when we requested homepage, return homepage
			if ( $check_post_id === $page_on_front_id && $post->ID !== $page_on_front_id ) {
				$correct_post = get_post( $page_on_front_id, OBJECT, 'edit' );
				if ( $correct_post instanceof WP_Post ) {
					Logger::info( 'Metabox::filter_get_post_for_homepage - Replaced wrong post with homepage', array(
						'wrong_post_id' => $post->ID,
						'homepage_id' => $page_on_front_id,
					) );
					return $correct_post;
				}
			}
		}
		
		return $post;
	}

	/**
	 * Prevent auto-draft creation when editing homepage.
	 * Hook: wp_insert_post_data (priority 10)
	 * 
	 * This intercepts the data before WordPress creates an auto-draft
	 * and prevents it if we're editing the homepage.
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @return array Modified post data.
	 */
	public function prevent_homepage_auto_draft_on_edit( array $data, array $postarr ): array {
		// Only check if we're in admin and editing a page
		if ( ! is_admin() || ! isset( $postarr['post_type'] ) || $postarr['post_type'] !== 'page' ) {
			return $data;
		}
		
		// Check if this is related to homepage
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return $data;
		}
		
		// Check if we're trying to edit the homepage
		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $requested_post_id !== $page_on_front_id ) {
			return $data;
		}
		
		// If this is an auto-draft being created while editing homepage, prevent it
		if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' ) {
			// Check if this is a new post (no ID) or if it's the homepage being modified
			$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			
			// If it's a new auto-draft (no ID) and we're editing homepage, prevent it
			if ( $post_id === 0 ) {
				Logger::warning( 'Metabox::prevent_homepage_auto_draft_on_edit - Preventing new auto-draft creation while editing homepage', array(
					'requested_post_id' => $requested_post_id,
					'homepage_id' => $page_on_front_id,
					'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
				) );
				// Return false to prevent insertion, or modify status
				// Actually, we can't return false, but we can change the status
				// However, if we change status, WordPress might still create it
				// Better approach: delete it immediately after creation via wp_insert_post hook
				return $data; // Let it be created, we'll delete it immediately
			}
			
			// If it's the homepage being changed to auto-draft, prevent it
			if ( $post_id === $page_on_front_id ) {
				$current_status = get_post_status( $page_on_front_id );
				if ( $current_status && $current_status !== 'auto-draft' ) {
					Logger::warning( 'Metabox::prevent_homepage_auto_draft_on_edit - Preventing homepage status change to auto-draft', array(
						'post_id' => $post_id,
						'current_status' => $current_status,
						'attempted_status' => 'auto-draft',
					) );
					$data['post_status'] = $current_status; // Keep original status
				}
			}
		}
		
		return $data;
	}

	/**
	 * Fix homepage status on shutdown (ultima risorsa).
	 * Hook: shutdown
	 */
	public function fix_homepage_status_on_shutdown(): void {
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id === 0 ) {
			return;
		}
		
		global $wpdb;
		$current_status = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
			$page_on_front_id
		) );
		
		// Se lo status è 'auto-draft', prova a ripristinare lo status originale salvato all'inizio
		if ( $current_status === 'auto-draft' ) {
			$original_status = get_transient( 'fp_seo_homepage_original_status_' . $page_on_front_id );
			
			// Se abbiamo lo status originale salvato, usalo. Altrimenti forza 'publish'
			$new_status = ! empty( $original_status ) && $original_status !== 'auto-draft' ? $original_status : 'publish';
			
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => $new_status ),
				array( 'ID' => $page_on_front_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $page_on_front_id );
			wp_cache_delete( $page_on_front_id, 'posts' );
			
			// Elimina la transiente
			delete_transient( 'fp_seo_homepage_original_status_' . $page_on_front_id );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::warning( 'Metabox::fix_homepage_status_on_shutdown - Corrected homepage status on shutdown', array(
					'post_id' => $page_on_front_id,
					'old_status' => $current_status,
					'new_status' => $new_status,
					'restored_from_original' => ! empty( $original_status ),
				) );
			}
		}
	}

	public function save_meta_pre_update( int $post_id, array $data ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::save_meta_pre_update called', array(
				'post_id' => $post_id,
				'hook' => 'pre_post_update',
			) );
		}
		
		// Salva i campi SEO se presenti
		// Questo hook viene chiamato prima di save_post, quindi possiamo salvare qui
		if ( isset( $_POST['fp_seo_performance_metabox_present'] ) || 
			 isset( $_POST['fp_seo_title_sent'] ) || 
			 isset( $_POST['fp_seo_meta_description_sent'] ) ||
			 isset( $_POST['fp_seo_excerpt'] ) ||
			 isset( $_POST['fp_seo_excerpt_sent'] ) ) {
			$saver = new \FP\SEO\Editor\MetaboxSaver();
			$saver->save_all_fields( $post_id );
		}
		
		// Ritorna i dati invariati
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
	private function get_supported_post_types(): array {
				return PostTypes::analyzable();
	}

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_post_excluded( int $post_id ): bool {
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
	private function run_analysis_for_post( WP_Post $post ): array {
		// Check if required classes exist
		if ( ! class_exists( '\FP\SEO\Analysis\Context' ) ) {
			throw new \RuntimeException( 'Context class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Analysis\Analyzer' ) ) {
			throw new \RuntimeException( 'Analyzer class not found' );
		}
		if ( ! class_exists( '\FP\SEO\Scoring\ScoreEngine' ) ) {
			throw new \RuntimeException( 'ScoreEngine class not found' );
		}
		
		// DISABLED: Cache clearing interferes with WordPress's post object during page load
		// Get SEO metadata using MetadataResolver (same pattern as BulkAuditPage)
		$meta_description = MetadataResolver::resolve_meta_description( $post );
		$canonical = MetadataResolver::resolve_canonical_url( $post );
		$robots = MetadataResolver::resolve_robots( $post );
		$focus_keyword = get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true );
		$secondary_keywords = get_post_meta( $post->ID, self::META_SECONDARY_KEYWORDS, true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $focus_keyword ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, self::META_FOCUS_KEYWORD ) );
			if ( $db_value !== null ) {
				$focus_keyword = $db_value;
			}
		}
		
		if ( empty( $secondary_keywords ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, self::META_SECONDARY_KEYWORDS ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$secondary_keywords = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( ! is_array( $secondary_keywords ) ) {
			$secondary_keywords = array();
		}
		
		// Get SEO title, fallback to post title
		$seo_title = MetadataResolver::resolve_seo_title( $post->ID );
		if ( ! $seo_title ) {
			$seo_title = $post->post_title;
		}
		
		// Build context with proper parameters (same pattern as BulkAuditPage)
		$context = new Context(
			(int) $post->ID,
			(string) $post->post_content,
			(string) $seo_title,
			(string) $meta_description,
			$canonical,
			$robots,
			is_string( $focus_keyword ) ? $focus_keyword : '',
			$secondary_keywords
		);

		$analyzer = new Analyzer();
		$analysis = $analyzer->analyze( $context );
		$score_engine = new ScoreEngine();
		
		// Analyzer::analyze() returns an array with 'checks' and 'summary' keys
		// ScoreEngine::calculate() expects an array of checks indexed by check ID
		$checks_array = $analysis['checks'] ?? array();
		
		// Debug: log checks structure
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'run_analysis_for_post - checks processed', array(
				'post_id' => $post->ID,
				'checks_count' => count( $checks_array ),
				'first_check_keys' => ! empty( $checks_array ) ? array_keys( reset( $checks_array ) ) : array(),
			) );
		}
		
		$score = $score_engine->calculate( $checks_array );
		
		$formatted_checks = $this->format_checks_for_frontend( $checks_array );
		
		// Debug: log formatted checks
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'run_analysis_for_post - formatted checks', array(
				'post_id' => $post->ID,
				'formatted_checks_count' => count( $formatted_checks ),
			) );
		}
		
		return array(
			'score' => $score,
			'checks' => $formatted_checks,
		);
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

		return array(
			'score' => $score,
			'checks' => $this->format_checks_for_frontend( $checks_array ),
		);
	}

	/**
	 * Format checks for frontend display.
	 *
	 * @param array $checks Raw checks from analyzer.
	 * @return array
	 */
	private function format_checks_for_frontend( array $checks ): array {
		$formatted = array();
		foreach ( $checks as $check_id => $check ) {
			// Check can be an array (from Analyzer) or an object (from Result)
			if ( is_array( $check ) ) {
				// Analyzer returns: id, label, description, status, details, fix_hint, weight
				$formatted[] = array(
					'id' => $check['id'] ?? $check_id,
					'label' => $check['label'] ?? '',
					'status' => $check['status'] ?? 'pending',
					'hint' => $check['fix_hint'] ?? $check['description'] ?? '',
				);
			} else {
				// Handle object with methods
				$formatted[] = array(
					'id' => method_exists( $check, 'get_id' ) ? $check->get_id() : (string) $check_id,
					'label' => method_exists( $check, 'get_label' ) ? $check->get_label() : '',
					'status' => method_exists( $check, 'get_status' ) ? $check->get_status() : 'pending',
					'hint' => method_exists( $check, 'get_hint' ) ? $check->get_hint() : '',
				);
			}
		}
		return $formatted;
	}

	/**
	 * Render GSC metrics section.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function render_gsc_metrics( \WP_Post $post ): void {
		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		$gsc_data = new GscData();
		$metrics  = $gsc_data->get_post_metrics( $post->ID, 28 );

		if ( ! $metrics ) {
			return;
		}

		?>
		<div class="fp-seo-gsc-post-metrics" style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
			<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #111827;">
				📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
			</h4>
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Clicks', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #059669;">
						<?php echo esc_html( number_format_i18n( $metrics['clicks'] ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #2563eb;">
						<?php echo esc_html( number_format_i18n( $metrics['impressions'] ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( $metrics['ctr'] ); ?>%
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Position', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( $metrics['position'] ); ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $metrics['queries'] ) ) : ?>
				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-weight: 600; color: #374151;">
						🔍 <?php esc_html_e( 'Top Queries', 'fp-seo-performance' ); ?> (<?php echo count( $metrics['queries'] ); ?>)
					</summary>
					<ul style="margin: 8px 0 0; padding: 0; list-style: none;">
						<?php foreach ( array_slice( $metrics['queries'], 0, 5 ) as $query_data ) : ?>
							<li style="padding: 6px 8px; background: #fff; border-radius: 4px; margin-bottom: 4px; font-size: 12px;">
								<strong><?php echo esc_html( $query_data['query'] ); ?></strong>
								<span style="color: #6b7280; margin-left: 10px;">
									<?php echo esc_html( $query_data['clicks'] ); ?> clicks, 
									pos <?php echo esc_html( $query_data['position'] ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle AJAX request to save SEO Title and Meta Description fields.
	 * This is a separate endpoint to ensure fields are saved reliably.
	 */
	public function handle_save_fields_ajax(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::AJAX_SAVE_FIELDS ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fp-seo-performance' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : ( isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0 );

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'fp-seo-performance' ) ), 400 );
		}

		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		// If not a supported post type, return error immediately
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			wp_send_json_error( array( 
				'message' => __( 'This post type is not supported for SEO optimization.', 'fp-seo-performance' ),
				'post_type' => $post_type,
			), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'handle_save_fields_ajax called', array(
				'post_id' => $post_id,
				'ajax_post_keys' => array_keys( $_POST ),
			) );
		}

		// Get and sanitize values - supporta sia i nomi vecchi che quelli nuovi
		$seo_title = '';
		if ( isset( $_POST['fp_seo_title'] ) ) {
			$seo_title = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_title'] ) );
			$seo_title = trim( $seo_title );
		} elseif ( isset( $_POST['seo_title'] ) ) {
			$seo_title = sanitize_text_field( wp_unslash( (string) $_POST['seo_title'] ) );
			$seo_title = trim( $seo_title );
		}

		$meta_description = '';
		if ( isset( $_POST['fp_seo_meta_description'] ) ) {
			$meta_description = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_meta_description'] ) );
			$meta_description = trim( $meta_description );
		} elseif ( isset( $_POST['meta_description'] ) ) {
			$meta_description = sanitize_textarea_field( wp_unslash( (string) $_POST['meta_description'] ) );
			$meta_description = trim( $meta_description );
		}

		$focus_keyword = '';
		if ( isset( $_POST['fp_seo_focus_keyword'] ) ) {
			$focus_keyword = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_focus_keyword'] ) );
			$focus_keyword = trim( $focus_keyword );
		}

		$secondary_keywords = '';
		if ( isset( $_POST['fp_seo_secondary_keywords'] ) ) {
			$secondary_keywords = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_secondary_keywords'] ) );
			$secondary_keywords = trim( $secondary_keywords );
		}

		$excerpt = '';
		if ( isset( $_POST['fp_seo_excerpt'] ) ) {
			$excerpt = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) );
			$excerpt = trim( $excerpt );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'AJAX save values', array(
				'post_id' => $post_id,
				'has_title' => ! empty( $seo_title ),
				'has_description' => ! empty( $meta_description ),
				'has_focus_keyword' => ! empty( $focus_keyword ),
				'has_excerpt' => ! empty( $excerpt ),
			) );
		}

		// Salva direttamente i campi senza usare MetaboxSaver per evitare conflitti
		// Questo è più sicuro in contesto AJAX
		try {
			// Salva Title
			if ( '' !== $seo_title ) {
				update_post_meta( $post_id, '_fp_seo_title', $seo_title );
			} else {
				delete_post_meta( $post_id, '_fp_seo_title' );
			}

			// Salva Meta Description
			if ( '' !== $meta_description ) {
				update_post_meta( $post_id, '_fp_seo_meta_description', $meta_description );
			} else {
				delete_post_meta( $post_id, '_fp_seo_meta_description' );
			}

			// Salva Focus Keyword
			if ( '' !== $focus_keyword ) {
				update_post_meta( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
			} else {
				delete_post_meta( $post_id, self::META_FOCUS_KEYWORD );
			}

			// Salva Secondary Keywords
			if ( '' !== $secondary_keywords ) {
				update_post_meta( $post_id, self::META_SECONDARY_KEYWORDS, $secondary_keywords );
			} else {
				delete_post_meta( $post_id, self::META_SECONDARY_KEYWORDS );
			}

			// Salva Excerpt
			if ( '' !== $excerpt ) {
				$excerpt_result = wp_update_post(
					array(
						'ID'           => $post_id,
						'post_excerpt' => $excerpt,
					),
					true
				);
				
				if ( is_wp_error( $excerpt_result ) ) {
					// Fallback: direct database update
					global $wpdb;
					$wpdb->update(
						$wpdb->posts,
						array( 'post_excerpt' => $excerpt ),
						array( 'ID' => $post_id ),
						array( '%s' ),
						array( '%d' )
					);
					clean_post_cache( $post_id );
					wp_cache_delete( $post_id, 'posts' );
				} else {
					clean_post_cache( $post_id );
					wp_cache_delete( $post_id, 'posts' );
				}
			} else {
				// Se excerpt è vuoto, rimuovilo
				$excerpt_result = wp_update_post(
					array(
						'ID'           => $post_id,
						'post_excerpt' => '',
					),
					true
				);
				if ( ! is_wp_error( $excerpt_result ) ) {
					clean_post_cache( $post_id );
					wp_cache_delete( $post_id, 'posts' );
				}
			}

			$result = true;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'AJAX direct save successful', array( 'post_id' => $post_id ) );
			}
		} catch ( \Exception $e ) {
			Logger::error( 'AJAX save error', array(
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			) );
			$result = false;
		} catch ( \Error $e ) {
			Logger::error( 'AJAX save fatal error', array(
				'post_id' => $post_id,
				'error' => $e->getMessage(),
			) );
			$result = false;
		}

		// Force cache clear to ensure updated values are read on reload
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_delete( $post_id, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post_id ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::info( 'FP SEO: Fields saved via AJAX', array(
				'post_id' => $post_id,
				'title' => $seo_title,
				'description' => substr( $meta_description, 0, 50 ) . ( strlen( $meta_description ) > 50 ? '...' : '' ),
				'focus_keyword' => $focus_keyword,
				'result' => $result,
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Fields saved successfully.', 'fp-seo-performance' ),
			'saved' => $result,
		) );
	}

	/**
	 * Get check importance explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	private function get_check_importance( string $check_id ): string {
		$importance_map = array(
			'title_length'       => __( 'Il titolo è la prima cosa che gli utenti vedono nelle SERP di Google. Un titolo ben ottimizzato (50-60 caratteri) viene mostrato completamente nei risultati e attira più clic.', 'fp-seo-performance' ),
			'meta_description'   => __( 'La meta description appare sotto il titolo nelle ricerche Google. Una buona description (150-160 caratteri) aumenta il CTR (tasso di clic) del 30-50%.', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'La focus keyword nel titolo aiuta Google a capire l\'argomento principale. I titoli con keyword target rankano in media 15 posizioni più in alto.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Una densità keyword ottimale (1-2%) aiuta il posizionamento senza penalizzazioni per keyword stuffing. Troppo poche keyword = difficile rankare; troppe = penalizzazione Google.', 'fp-seo-performance' ),
			'content_length'     => __( 'Contenuti più lunghi (>1000 parole) tendono a rankare meglio perché forniscono informazioni più complete. Articoli lunghi ottengono il 77% dei backlink.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Una struttura H1-H6 corretta aiuta Google a capire la gerarchia del contenuto. Migliora anche l\'accessibilità per screen reader.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Gli attributi ALT sulle immagini migliorano l\'accessibilità e aiutano il ranking in Google Immagini. Il 27% del traffico organico viene da immagini.', 'fp-seo-performance' ),
			'internal_links'     => __( 'I link interni distribuiscono autorità SEO tra le pagine e aiutano Google a scoprire nuovi contenuti. Siti con buona link structure rankano il 40% meglio.', 'fp-seo-performance' ),
			'external_links'     => __( 'Link a fonti autorevoli aumentano la credibilità del contenuto. Google considera i link esterni un segnale di qualità e profondità dell\'articolo.', 'fp-seo-performance' ),
			'readability'        => __( 'Un contenuto leggibile (punteggio Flesch >60) mantiene gli utenti più tempo sulla pagina, riducendo il bounce rate. Google favorisce contenuti comprensibili.', 'fp-seo-performance' ),
		);

		return $importance_map[ $check_id ] ?? __( 'Questo check SEO è importante per il posizionamento organico del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check how-to-fix explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	private function get_check_howto( string $check_id ): string {
		$howto_map = array(
			'title_length'       => __( 'Modifica il titolo per mantenerlo tra 50-60 caratteri. Includi la keyword principale all\'inizio. Se troppo lungo, Google lo tronca con "..." perdendo impatto.', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scrivi una description di 150-160 caratteri che riassume il contenuto e include la focus keyword. Usa un tono coinvolgente e aggiungi una call-to-action (CTA).', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Inserisci la focus keyword nel campo apposito sopra, poi assicurati che appaia nel titolo (preferibilmente all\'inizio), nei primi 100 caratteri del contenuto e in almeno un H2.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Aggiungi o rimuovi keyword per raggiungere 1-2% di densità. Usa sinonimi e keyword correlate (LSI keywords) invece di ripetere sempre la stessa keyword.', 'fp-seo-performance' ),
			'content_length'     => __( 'Espandi il contenuto aggiungendo sezioni utili: esempi pratici, FAQ, statistiche, case study. Punta a minimo 1000 parole per argomenti informativi, 500+ per pagine commerciali.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Usa un solo H1 (titolo principale), poi H2 per sezioni principali, H3 per sottosezioni. Non saltare livelli (es: da H2 a H4). Includi keyword nei heading quando possibile.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Aggiungi un attributo ALT descrittivo a ogni immagine. Descrivi cosa mostra l\'immagine includendo keyword dove appropriato. Es: "screenshot plugin SEO WordPress" invece di "immagine1".', 'fp-seo-performance' ),
			'internal_links'     => __( 'Aggiungi 2-5 link interni a pagine/post correlati. Usa anchor text descrittivo (no "clicca qui"). Link a contenuti pillar e articoli correlati per creare topic clusters.', 'fp-seo-performance' ),
			'external_links'     => __( 'Aggiungi 1-3 link a fonti autorevoli (.gov, .edu, siti riconosciuti nel settore). Apri in nuova tab e usa rel="noopener noreferrer" per sicurezza.', 'fp-seo-performance' ),
			'readability'        => __( 'Semplifica le frasi (max 20 parole). Usa paragrafi corti (3-4 righe). Aggiungi elenchi puntati. Evita gergo tecnico o spiegalo. Usa sottotitoli per spezzare il testo.', 'fp-seo-performance' ),
		);

		return $howto_map[ $check_id ] ?? __( 'Segui le best practices SEO per ottimizzare questo aspetto del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check example
	 *
	 * @param string $check_id Check identifier.
	 * @return string|null
	 */
	private function get_check_example( string $check_id ): ?string {
		$example_map = array(
			'title_length'       => __( 'Guida SEO WordPress: 10 Trucchi per Rankare nel 2025', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scopri 10 tecniche SEO WordPress avanzate per migliorare il ranking nel 2025. Guida pratica con esempi reali e risultati garantiti. Leggi ora!', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Se keyword = "wordpress seo", includi nel titolo: "WordPress SEO: Guida Completa 2025"', 'fp-seo-performance' ),
			'headings_structure' => __( 'H1: Titolo principale | H2: Cos\'è la SEO | H3: Tecniche on-page | H3: Tecniche off-page', 'fp-seo-performance' ),
			'images_alt'         => __( 'ALT="screenshot dashboard plugin SEO WordPress con analytics traffico organico"', 'fp-seo-performance' ),
		);

		return $example_map[ $check_id ] ?? null;
	}

	/**
	 * Handle AJAX request to save images data.
	 */
	public function handle_save_images_ajax(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fp_seo_images_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fp-seo-performance' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'fp-seo-performance' ) ), 400 );
		}

		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		// If not a supported post type, return error immediately
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			wp_send_json_error( array( 
				'message' => __( 'This post type is not supported for image optimization.', 'fp-seo-performance' ),
				'post_type' => $post_type,
			), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		// Get images data
		$images_data = isset( $_POST['images'] ) && is_array( $_POST['images'] ) ? $_POST['images'] : array();

		if ( empty( $images_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No images data provided.', 'fp-seo-performance' ) ), 400 );
		}

		// Sanitize images data
		$sanitized_images = array();
		foreach ( $images_data as $src => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$sanitized_images[ esc_url_raw( $src ) ] = array(
				'alt'         => sanitize_text_field( $data['alt'] ?? '' ),
				'title'       => sanitize_text_field( $data['title'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			);
		}

		// Save images data to post meta
		update_post_meta( $post_id, '_fp_seo_images_data', $sanitized_images );

		// Update post content with new alt and title attributes
		// CRITICAL: Double-check post type before modifying post content
		// This prevents interference with slider meta saving
		$post = get_post( $post_id );
		if ( $post ) {
			// Double-check post type before any modification
			$current_post_type = get_post_type( $post_id );
			if ( ! in_array( $current_post_type, $supported_types, true ) ) {
				wp_send_json_error( array( 
					'message' => __( 'Post type changed during processing. Operation cancelled.', 'fp-seo-performance' ),
					'post_type' => $current_post_type,
				), 400 );
			}
			
			$content = $post->post_content;
			$updated_content = $this->update_images_in_content( $content, $sanitized_images );

			if ( $updated_content !== $content ) {
				// Update post content
				// CRITICAL: Remove our save_post hooks temporarily to prevent recursion
				// Use post-type-specific hooks only
				$supported_types = $this->get_supported_post_types();
				foreach ( $supported_types as $post_type ) {
					remove_action( 'save_post_' . $post_type, array( $this, 'save_meta' ), 10 );
					remove_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ), 10 );
					remove_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ), 10 );
				}
				
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				) );
				
				// Re-add our hooks (post-type-specific only)
				foreach ( $supported_types as $post_type ) {
					if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_meta' ) ) ) {
						add_action( 'save_post_' . $post_type, array( $this, 'save_meta' ), 10, 3 );
					}
					if ( ! has_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ) ) ) {
						add_action( 'edit_post_' . $post_type, array( $this, 'save_meta_edit_post' ), 10, 2 );
					}
					if ( ! has_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ) ) ) {
						add_action( 'wp_insert_post_' . $post_type, array( $this, 'save_meta_insert_post' ), 10, 3 );
					}
				}
			}

			// Also update attachment alt text if image is a WordPress attachment
			foreach ( $sanitized_images as $src => $data ) {
				$attachment_id = $this->get_attachment_id_from_url( $src );
				if ( $attachment_id && ! empty( $data['alt'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $data['alt'] );
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Images data saved', array(
				'post_id'      => $post_id,
				'images_count' => count( $sanitized_images ),
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Images data saved successfully.', 'fp-seo-performance' ),
			'count'   => count( $sanitized_images ),
		) );
	}

	/**
	 * Handle AJAX request to reload images section.
	 */
	public function handle_reload_images_section_ajax(): void {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'fp_seo_reload_images_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'fp-seo-performance' ) ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'fp-seo-performance' ) ), 400 );
		}

		// Check post type
		$post_type = get_post_type( $post_id );
		$supported_types = $this->get_supported_post_types();
		
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			wp_send_json_error( array( 
				'message' => __( 'This post type is not supported.', 'fp-seo-performance' ),
				'post_type' => $post_type,
			), 400 );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		// Get post object - CRITICAL: Always refresh from database to get latest content
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
		}
		
		// CRITICAL: Refresh post content from database to ensure we have the latest version
		// WordPress's get_post() might return cached/stale content, especially after AJAX calls
		global $wpdb;
		$db_content = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
			$post_id
		) );
		if ( ! empty( $db_content ) ) {
			$post->post_content = $db_content;
			Logger::info( 'FP SEO: handle_reload_images_section_ajax - Refreshed post content from database', array(
				'post_id' => $post_id,
				'content_length' => strlen( $db_content ),
				'has_wpbakery' => strpos( $db_content, '[vc_' ) !== false,
				'has_img_tags' => strpos( $db_content, '<img' ) !== false,
			) );
		}

		// Render only the images section content
		if ( $this->renderer === null ) {
			$this->initialize_renderer();
		}

		if ( $this->renderer === null ) {
			wp_send_json_error( array( 'message' => __( 'Renderer not available.', 'fp-seo-performance' ) ), 500 );
		}

		// Extract images using new isolated ImageExtractor
		try {
			$extractor = new ImageExtractor();
			$force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'] === 'true';
			$images = $extractor->extract( $post, $force_refresh );
			
			Logger::info( 'FP SEO: handle_reload_images_section_ajax - Images extracted via ImageExtractor', array(
				'post_id' => $post_id,
				'images_count' => count( $images ),
				'force_refresh' => $force_refresh,
			) );
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error extracting images in reload handler', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'post_id' => $post_id,
			) );
			$images = array();
		}

		// Render images section content HTML
		ob_start();
		$this->renderer->render_images_section_content( $post, $images );
		$html = ob_get_clean();

		wp_send_json_success( array( 
			'html' => $html,
			'images_count' => count( $images ),
		) );
	}

	/**
	 * Update images in post content with new alt and title attributes.
	 *
	 * @param string $content Post content.
	 * @param array<string, array{alt: string, title: string, description: string}> $images_data Images data.
	 * @return string Updated content.
	 */
	private function update_images_in_content( string $content, array $images_data ): string {
		if ( empty( $content ) || empty( $images_data ) ) {
			return $content;
		}

		// Use DOMDocument to parse and update HTML
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$img_tags = $dom->getElementsByTagName( 'img' );
		$updated = false;

		foreach ( $img_tags as $img ) {
			$src = $img->getAttribute( 'src' );
			
			// Remove query strings for matching
			$src_clean = strtok( $src, '?' );
			
			// Find matching image data
			$image_data = null;
			foreach ( $images_data as $data_src => $data ) {
				$data_src_clean = strtok( $data_src, '?' );
				if ( $src_clean === $data_src_clean || $src === $data_src ) {
					$image_data = $data;
					break;
				}
			}

			if ( $image_data ) {
				// Update alt attribute
				if ( ! empty( $image_data['alt'] ) ) {
					$img->setAttribute( 'alt', $image_data['alt'] );
					$updated = true;
				} elseif ( $img->hasAttribute( 'alt' ) && empty( $image_data['alt'] ) ) {
					// Remove alt if it was cleared
					$img->removeAttribute( 'alt' );
					$updated = true;
				}

				// Update title attribute
				if ( ! empty( $image_data['title'] ) ) {
					$img->setAttribute( 'title', $image_data['title'] );
					$updated = true;
				} elseif ( $img->hasAttribute( 'title' ) && empty( $image_data['title'] ) ) {
					// Remove title if it was cleared
					$img->removeAttribute( 'title' );
					$updated = true;
				}
			}
		}

		if ( ! $updated ) {
			return $content;
		}

		// Get updated HTML
		$updated_content = $dom->saveHTML();
		
		// Remove XML declaration and DOCTYPE if present
		$updated_content = preg_replace( '/^<\?xml[^>]*\?>/i', '', $updated_content );
		$updated_content = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $updated_content );
		$updated_content = trim( $updated_content );

		return $updated_content;
	}

	/**
	 * Get attachment ID from image URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null if not found.
	 */
	private function get_attachment_id_from_url( string $url ): ?int {
		// Remove query strings
		$url = strtok( $url, '?' );
		
		// Try to get attachment ID from URL
		global $wpdb;
		
		// Try full URL match
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			basename( $url )
		) );
		
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}
		
		// Try GUID match
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
			$url
		) );
		
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}
		
		// Try to extract from URL path
		$upload_dir = wp_upload_dir();
		if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
			$relative_path = str_replace( $upload_dir['baseurl'] . '/', '', $url );
			$attachment_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$relative_path
			) );
			
			if ( $attachment_id ) {
				return (int) $attachment_id;
			}
		}
		
		return null;
	}

	/**
	 * Handle AJAX request for lazy-loaded image extraction.
	 * 
	 * This is the ONLY way images should be extracted - completely isolated and non-interfering.
	 *
	 * @return void
	 */
	public function handle_extract_images_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-seo-performance' ) ), 403 );
			return;
		}
		
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'fp-seo-performance' ) ), 400 );
			return;
		}
		
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'fp-seo-performance' ) ), 404 );
			return;
		}
		
		// Use the new isolated ImageExtractor
		$extractor = new ImageExtractor();
		$force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'] === 'true';
		
		try {
			$images = $extractor->extract( $post, $force_refresh );
			
			wp_send_json_success( array(
				'images' => $images,
				'count' => count( $images ),
				'post_id' => $post_id,
			) );
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error in handle_extract_images_ajax', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'post_id' => $post_id,
			) );
			
			wp_send_json_error( array(
				'message' => __( 'Error extracting images.', 'fp-seo-performance' ),
				'error' => $e->getMessage(),
			), 500 );
		}
	}

}
