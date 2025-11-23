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
	public const META_EXCLUDE         = '_fp_seo_performance_exclude';
	public const META_FOCUS_KEYWORD   = '_fp_seo_focus_keyword';
	public const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * @var MetaboxRenderer
	 */
	private $renderer;

	/**
	 * Costruttore - registra gli hook immediatamente
	 */
	public function __construct() {
		// REGISTRA GLI HOOK IMMEDIATAMENTE nel costruttore per garantire che vengano sempre registrati
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::__construct() called' );
		}
		$this->register_hooks();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::__construct() completed - hooks should be registered' );
		}
	}

	/**
	 * Registra gli hook di salvataggio - chiamato dal costruttore e da register()
	 */
	private function register_hooks(): void {
		// MULTIPLE HOOKS con priorità diverse per garantire il salvataggio
		// save_post receives: (int $post_id, WP_Post $post, bool $update)
		add_action( 'save_post', array( $this, 'save_meta' ), 1, 3 ); // Priorità 1 - molto presto
		add_action( 'save_post', array( $this, 'save_meta' ), 5, 3 ); // Priorità 5 - normale
		add_action( 'save_post', array( $this, 'save_meta' ), 99, 3 ); // Priorità 99 - molto tardi
		
		// Hook aggiuntivo per edit_post (chiamato anche in Gutenberg)
		add_action( 'edit_post', array( $this, 'save_meta_edit_post' ), 1, 2 );
		add_action( 'edit_post', array( $this, 'save_meta_edit_post' ), 99, 2 );
		
		// Hook wp_insert_post per catturare anche questo
		add_action( 'wp_insert_post', array( $this, 'save_meta_insert_post' ), 10, 3 );
		
		// Hook wp_insert_post_data per salvare prima che il post venga inserito
		// Priorità 1 per intervenire PRIMA di qualsiasi altro plugin che potrebbe modificare lo status
		add_filter( 'wp_insert_post_data', array( $this, 'save_meta_pre_insert' ), 1, 4 );
		
		// Hook transition_post_status per intercettare cambiamenti di status (specialmente per homepage)
		add_action( 'transition_post_status', array( $this, 'prevent_homepage_auto_draft' ), 1, 3 );
		
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
		// Inizializza renderer con gestione errori robusta
		try {
			// Verifica che la classe esista prima di istanziarla
			if ( ! class_exists( 'FP\\SEO\\Editor\\MetaboxRenderer' ) ) {
				throw new \RuntimeException( 'MetaboxRenderer class not found' );
			}
			
			$this->renderer = new MetaboxRenderer();
			
			// Verifica che il renderer sia stato creato correttamente
			if ( ! $this->renderer instanceof MetaboxRenderer ) {
				throw new \RuntimeException( 'MetaboxRenderer instance invalid' );
			}
		} catch ( \Exception $e ) {
			// Log errore ma continua - useremo fallback rendering
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Failed to initialize MetaboxRenderer', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			// Crea un renderer fallback invece di non registrare gli hook
			$this->renderer = null;
		} catch ( \Throwable $e ) {
			// Log errore fatale ma continua - useremo fallback rendering
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Fatal error initializing MetaboxRenderer', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			// Crea un renderer fallback invece di non registrare gli hook
			$this->renderer = null;
		}
		
		// Registra sempre gli hook, anche se il renderer non è disponibile
		// Priorità 5 per essere registrato tra i primi metabox (prima di altri plugin)
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
		
		// Gli hook di salvataggio sono già registrati nel costruttore, ma li registriamo di nuovo per sicurezza
		$this->register_hooks();
		
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
			
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 0 );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_SAVE_FIELDS, array( $this, 'handle_save_fields_ajax' ) );
			add_action( 'wp_ajax_' . self::AJAX_SAVE_IMAGES, array( $this, 'handle_save_images_ajax' ) );
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

		global $post;
		if ( ! $post ) {
			return;
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
		
		// Use a local variable to avoid modifying the parameter
		$current_post = $post;
		
		// Special handling for homepage: always try to get the real post if it's set as page_on_front
		$page_on_front_id = (int) get_option( 'page_on_front' );
		$is_homepage_edit = $page_on_front_id > 0 && (
			( isset( $_GET['post'] ) && absint( $_GET['post'] ) === $page_on_front_id ) ||
			( isset( $_POST['post_ID'] ) && absint( $_POST['post_ID'] ) === $page_on_front_id ) ||
			( ! empty( $current_post->ID ) && $current_post->ID === $page_on_front_id )
		);
		
		// If post has auto-draft status but we're editing an existing post, try to get the real post
		// This happens when WordPress creates a new post object instead of loading the existing one
		// This is especially common with the homepage
		if ( ( empty( $current_post->ID ) || $current_post->ID <= 0 ) || 
		     ( isset( $current_post->post_status ) && $current_post->post_status === 'auto-draft' ) ||
		     ( $is_homepage_edit && ( empty( $current_post->ID ) || $current_post->post_status === 'auto-draft' ) ) ) {
			
			// First, try to get post ID from request (most reliable for existing posts)
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0 );
			
			// For homepage, also check page_on_front option
			if ( $post_id <= 0 && $is_homepage_edit && $page_on_front_id > 0 ) {
				$post_id = $page_on_front_id;
			}
			
			if ( $post_id > 0 ) {
				$retrieved_post = get_post( $post_id );
				if ( $retrieved_post instanceof WP_Post && $retrieved_post->post_status !== 'auto-draft' ) {
					$current_post = $retrieved_post;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::debug( 'FP SEO: Retrieved existing post from request (was auto-draft)', array(
							'post_id' => $current_post->ID,
							'post_type' => $current_post->post_type,
							'post_status' => $current_post->post_status,
							'original_status' => $post->post_status ?? 'unknown',
							'is_homepage' => $is_homepage_edit,
						) );
					}
				} elseif ( $retrieved_post instanceof WP_Post ) {
					// Post exists but is auto-draft - this is a new post, keep it
					$current_post = $retrieved_post;
				}
			}
			
			// If still no valid post, try global
			if ( ( empty( $current_post->ID ) || $current_post->ID <= 0 ) || 
			     ( isset( $current_post->post_status ) && $current_post->post_status === 'auto-draft' && $post_id <= 0 ) ) {
				// Get global post without overwriting the parameter
				$global_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
				if ( isset( $global_post ) && $global_post instanceof WP_Post && 
				     ! empty( $global_post->ID ) && $global_post->ID > 0 &&
				     $global_post->post_status !== 'auto-draft' ) {
					$current_post = $global_post;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::debug( 'FP SEO: Using global post object in render', array(
							'post_id' => $current_post->ID,
							'post_type' => $current_post->post_type,
							'post_status' => $current_post->post_status,
							'is_homepage' => $is_homepage_edit,
						) );
					}
				}
			}
		}
		
		// Use current_post for the rest of the method - don't modify the original $post parameter
		// to avoid interfering with WordPress's post object handling
		
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

		// Se il renderer non è disponibile, mostra un messaggio di fallback
		if ( ! $this->renderer ) {
			echo '<div class="notice notice-warning">';
			echo '<p><strong>' . esc_html__( 'FP SEO Manager', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Il metabox non può essere visualizzato correttamente. I campi SEO verranno comunque salvati.', 'fp-seo-performance' ) . '</p>';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<p><small>' . esc_html__( 'Controlla i log per dettagli.', 'fp-seo-performance' ) . '</small></p>';
			}
			echo '</div>';
			return;
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
			$this->renderer->render( $current_post, $analysis, $excluded );
		} catch ( \Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error rendering metabox', array(
					'post_id' => $post->ID,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			// Fallback: show basic error message con campi essenziali
			echo '<div class="notice notice-error">';
			echo '<p><strong>' . esc_html__( 'Errore nel rendering del metabox SEO', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'I campi SEO verranno comunque salvati. Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
			echo '</div>';
			
			// Mostra almeno i campi essenziali per il salvataggio
			$this->render_fallback_fields( $current_post );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Fatal error rendering metabox', array(
					'post_id' => $current_post->ID,
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			// Fallback: show basic error message con campi essenziali
			echo '<div class="notice notice-error">';
			echo '<p><strong>' . esc_html__( 'Errore critico nel rendering del metabox SEO', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'I campi SEO verranno comunque salvati. Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
			echo '</div>';
			
			// Mostra almeno i campi essenziali per il salvataggio
			$this->render_fallback_fields( $post );
		}
	}
	
	/**
	 * Render fallback fields quando il renderer principale fallisce.
	 *
	 * @param WP_Post $post Current post instance.
	 */
	private function render_fallback_fields( WP_Post $post ): void {
		// Clear cache before retrieving
		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'post_meta' );
		wp_cache_delete( $post->ID, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}

		$seo_title = get_post_meta( $post->ID, '_fp_seo_title', true );
		$meta_desc = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
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
		
		echo '<div style="padding: 15px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; margin-top: 15px;">';
		echo '<h4>' . esc_html__( 'Campi SEO Essenziali', 'fp-seo-performance' ) . '</h4>';
		
		echo '<p>';
		echo '<label for="fp-seo-title-fallback" style="display: block; margin-bottom: 5px; font-weight: 600;">';
		echo esc_html__( 'SEO Title:', 'fp-seo-performance' );
		echo '</label>';
		echo '<input type="text" id="fp-seo-title-fallback" name="fp_seo_title" value="' . esc_attr( $seo_title ) . '" style="width: 100%; padding: 8px;" />';
		echo '<input type="hidden" name="fp_seo_title_sent" value="1" />';
		echo '</p>';
		
		echo '<p>';
		echo '<label for="fp-seo-meta-desc-fallback" style="display: block; margin-bottom: 5px; font-weight: 600;">';
		echo esc_html__( 'Meta Description:', 'fp-seo-performance' );
		echo '</label>';
		echo '<textarea id="fp-seo-meta-desc-fallback" name="fp_seo_meta_description" rows="3" style="width: 100%; padding: 8px;">' . esc_textarea( $meta_desc ) . '</textarea>';
		echo '<input type="hidden" name="fp_seo_meta_description_sent" value="1" />';
		echo '</p>';
		
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
		// Prevent multiple calls - usa un array statico per tracciare
		static $saved = array();
		$hook_key = current_filter() . '_' . $post_id;
		if ( isset( $saved[ $hook_key ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'Metabox::save_meta already processed', array(
					'hook' => current_filter(),
					'post_id' => $post_id,
				) );
			}
			return;
		}
		$saved[ $hook_key ] = true;
		
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
					$value = $_POST[ $field ];
					if ( is_string( $value ) && strlen( $value ) > 100 ) {
						$value = substr( $value, 0, 100 ) . '...';
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
		
		$saver = new \FP\SEO\Editor\MetaboxSaver();
		$result = $saver->save_all_fields( $post_id );
		
		// Protezione per homepage: verifica e correggi lo status se necessario
		// Questo viene eseguito DOPO il salvataggio per correggere eventuali modifiche fatte da altri plugin
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id > 0 && $post_id === $page_on_front_id ) {
			$current_post = get_post( $post_id );
			if ( $current_post instanceof WP_Post && $current_post->post_status === 'auto-draft' ) {
				// Recupera lo status originale dal database (potrebbe essere stato modificato)
				global $wpdb;
				$original_status = $wpdb->get_var( $wpdb->prepare(
					"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
					$post_id
				) );
				
				// Se lo status è ancora auto-draft ma il post esiste da tempo, correggilo
				if ( $original_status === 'auto-draft' && $current_post->post_date !== '0000-00-00 00:00:00' ) {
					// Il post ha una data, quindi non dovrebbe essere auto-draft
					// Imposta a 'publish' o 'draft' in base al contesto
					$new_status = 'publish'; // Default per homepage
					wp_update_post( array(
						'ID' => $post_id,
						'post_status' => $new_status,
					) );
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::warning( 'Metabox::save_meta - Corrected homepage status from auto-draft', array(
							'post_id' => $post_id,
							'new_status' => $new_status,
						) );
					}
				}
			}
		}
		
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::save_meta_edit_post called', array(
				'post_id' => $post_id,
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
			
			// Protezione per homepage: verifica e correggi lo status se necessario
			$page_on_front_id = (int) get_option( 'page_on_front' );
			if ( $page_on_front_id > 0 && $post->ID === $page_on_front_id && ! $creating ) {
				$current_post = get_post( $post->ID );
				if ( $current_post instanceof WP_Post && $current_post->post_status === 'auto-draft' ) {
					// Recupera lo status originale dal database
					global $wpdb;
					$original_status = $wpdb->get_var( $wpdb->prepare(
						"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
						$post->ID
					) );
					
					if ( $original_status === 'auto-draft' && $current_post->post_date !== '0000-00-00 00:00:00' ) {
						// Il post ha una data, quindi non dovrebbe essere auto-draft
						wp_update_post( array(
							'ID' => $post->ID,
							'post_status' => 'publish',
						) );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							Logger::warning( 'Metabox::save_meta_rest - Corrected homepage status from auto-draft', array(
								'post_id' => $post->ID,
							) );
						}
					}
				}
			}
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Metabox::save_meta_insert_post called', array(
				'post_id' => $post_id,
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
		$post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		
		// IMPORTANTE: Non modificare lo status del post - questo potrebbe causare che WordPress tratti
		// un post esistente come nuovo (auto-draft)
		// Assicuriamoci che se è un update, lo status rimanga quello originale
		// Questo è particolarmente importante per la homepage (page_on_front)
		
		// Verifica se questa è la homepage PRIMA di qualsiasi altro controllo
		$page_on_front_id = (int) get_option( 'page_on_front' );
		$is_homepage = $page_on_front_id > 0 && $post_id === $page_on_front_id;
		
		// Se è un update di un post esistente (inclusa la homepage)
		if ( $post_id > 0 && $update ) {
			// Recupera lo status originale direttamente dal database per maggiore affidabilità
			global $wpdb;
			$original_status = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			) );
			
			// Se non trovato nel DB, prova con get_post come fallback
			if ( empty( $original_status ) ) {
				$original_post = get_post( $post_id );
				$original_status = $original_post instanceof WP_Post ? $original_post->post_status : '';
			}
			
			if ( ! empty( $original_status ) && $original_status !== 'auto-draft' ) {
				// Se lo status nel data è 'auto-draft' ma il post originale ha uno status diverso,
				// ripristina lo status originale IMMEDIATAMENTE
				// Questo è CRITICO per la homepage che potrebbe essere trattata in modo speciale
				if ( isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' && 
				     $original_status !== 'auto-draft' ) {
					$data['post_status'] = $original_status;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::debug( 'Metabox::save_meta_pre_insert - Restored original post status', array(
							'post_id' => $post_id,
							'original_status' => $original_status,
							'was_status' => 'auto-draft',
							'is_homepage' => $is_homepage,
						) );
					}
				}
				
				// Protezione aggiuntiva per la homepage: assicurati che lo status non venga mai cambiato
				// anche se non è esattamente 'auto-draft' ma è diverso dall'originale
				if ( $is_homepage && isset( $data['post_status'] ) && 
				     $data['post_status'] !== $original_status ) {
					// Solo se il nuovo status è 'auto-draft' o 'draft', ripristina l'originale
					if ( in_array( $data['post_status'], array( 'auto-draft', 'draft' ), true ) &&
					     ! in_array( $original_status, array( 'auto-draft', 'draft' ), true ) ) {
						$data['post_status'] = $original_status;
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							Logger::warning( 'Metabox::save_meta_pre_insert - Prevented homepage status change', array(
								'post_id' => $post_id,
								'original_status' => $original_status,
								'attempted_status' => $data['post_status'],
							) );
						}
					}
				}
			}
		}
		
		// Protezione aggiuntiva: se non è un update ma abbiamo un post_id valido e siamo sulla homepage,
		// potrebbe essere un errore - verifica e correggi
		if ( $post_id > 0 && ! $update && $is_homepage ) {
			// Recupera lo status originale direttamente dal database
			global $wpdb;
			$original_status_homepage = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_status FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			) );
			
			if ( ! empty( $original_status_homepage ) &&
			     isset( $data['post_status'] ) && $data['post_status'] === 'auto-draft' &&
			     $original_status_homepage !== 'auto-draft' ) {
				// Forza l'update e ripristina lo status
				$data['post_status'] = $original_status_homepage;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::warning( 'Metabox::save_meta_pre_insert - Homepage treated as new but exists, restored status', array(
						'post_id' => $post_id,
						'original_status' => $original_status_homepage,
						'was_status' => 'auto-draft',
					) );
				}
			}
		}
		
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
		// Clear cache before retrieving
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		
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
		
		// Clear cache before retrieving (for AJAX calls)
		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'post_meta' );
		wp_cache_delete( $post->ID, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}

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
		$post = get_post( $post_id );
		if ( $post ) {
			$content = $post->post_content;
			$updated_content = $this->update_images_in_content( $content, $sanitized_images );

			if ( $updated_content !== $content ) {
				// Update post content
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => $updated_content,
				) );
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

}
