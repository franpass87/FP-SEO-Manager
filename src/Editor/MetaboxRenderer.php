<?php
/**
 * Renders the SEO metabox HTML.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Editor\Helpers\MetaHelper;
use FP\SEO\Editor\Helpers\PostValidationHelper;
use FP\SEO\Editor\Renderers\AnalysisSectionRenderer;
use FP\SEO\Editor\Renderers\GscMetricsRenderer;
use FP\SEO\Editor\Renderers\HeaderRenderer;
use FP\SEO\Editor\Renderers\SerpFieldsRenderer;
use FP\SEO\Editor\Renderers\SerpPreviewRenderer;
use FP\SEO\Editor\Sections\SectionRegistry;
use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Options;
use WP_Post;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function esc_textarea;
use function get_post_meta;
use function is_array;
use function number_format_i18n;
use function sprintf;
use function wp_create_nonce;
use function wp_specialchars_decode;

/**
 * Handles rendering of the SEO metabox sections.
 */
class MetaboxRenderer {
	/**
	 * @var CheckHelpText
	 */
	private $check_help_text;

	/**
	 * @var SerpFieldsRenderer
	 */
	private $serp_fields_renderer;

	/**
	 * @var HeaderRenderer
	 */
	private $header_renderer;

	/**
	 * @var SerpPreviewRenderer
	 */
	private $serp_preview_renderer;

	/**
	 * @var AnalysisSectionRenderer
	 */
	private $analysis_section_renderer;

	/**
	 * @var GscMetricsRenderer
	 */
	private $gsc_metrics_renderer;


	/**
	 * Section registry instance.
	 * 
	 * Always available when MetaboxRenderer is created via DI container.
	 * May be null only if created manually (deprecated pattern).
	 *
	 * @var SectionRegistry|null
	 */
	private ?SectionRegistry $section_registry = null;

	/**
	 * @var string
	 */
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';

	/**
	 * @var string
	 */
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Constructor.
	 *
	 * @param SectionRegistry|null $section_registry Optional section registry for modular rendering.
	 */
	public function __construct( ?SectionRegistry $section_registry = null ) {
		$this->section_registry = $section_registry;
		// Inizializza check_help_text PRIMA di AnalysisSectionRenderer che ne ha bisogno
		// Le classi sono nello stesso namespace, quindi l'autoloader PSR-4 dovrebbe caricarla automaticamente
		try {
			// Prova a istanziare direttamente - l'autoloader dovrebbe caricarla
			$this->check_help_text = new CheckHelpText();
			
			// Verifica che l'istanza sia valida e abbia i metodi necessari
			if ( ! is_object( $this->check_help_text ) || ! method_exists( $this->check_help_text, 'get_importance' ) ) {
				throw new \RuntimeException( 'CheckHelpText instance invalid or missing methods' );
			}
			
			// Log successo in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: CheckHelpText initialized successfully', array(
					'class' => get_class( $this->check_help_text ),
				) );
			}
		} catch ( \Error $e ) {
			// Cattura errori fatali (class not found, etc.)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Fatal error initializing CheckHelpText (Error)', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'class_exists' => class_exists( 'FP\\SEO\\Editor\\CheckHelpText' ),
				) );
			}
			$this->check_help_text = $this->create_fallback_help_text();
		} catch ( \Exception $e ) {
			// Log errore ma continua - crea un oggetto fallback
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Failed to initialize CheckHelpText (Exception)', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			$this->check_help_text = $this->create_fallback_help_text();
		} catch ( \Throwable $e ) {
			// Catch-all per qualsiasi altro tipo di errore
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Unexpected error initializing CheckHelpText (Throwable)', array(
					'error' => $e->getMessage(),
					'type' => get_class( $e ),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			$this->check_help_text = $this->create_fallback_help_text();
		}
		
		// Assicurati che check_help_text non sia mai null
		if ( ! $this->check_help_text ) {
			$this->check_help_text = $this->create_fallback_help_text();
		}
		
		// Initialize renderers lazily - they will be created when needed
		// This avoids dependency injection issues in constructor
	}
	
	/**
	 * Initialize renderers lazily when needed.
	 */
	private function initialize_renderers(): void {
		if ( ! $this->header_renderer ) {
			$this->header_renderer = new HeaderRenderer();
		}
		if ( ! $this->serp_fields_renderer ) {
			$this->serp_fields_renderer = new SerpFieldsRenderer();
		}
		if ( ! $this->serp_preview_renderer ) {
			$this->serp_preview_renderer = new SerpPreviewRenderer();
		}
		if ( ! $this->analysis_section_renderer && $this->check_help_text ) {
			$this->analysis_section_renderer = new AnalysisSectionRenderer( $this->check_help_text );
		}
		if ( ! $this->gsc_metrics_renderer ) {
			$this->gsc_metrics_renderer = new GscMetricsRenderer();
		}
	}
	
	/**
	 * Creates a fallback help text object.
	 *
	 * @return object Fallback help text object.
	 */
	private function create_fallback_help_text(): object {
		return new class {
			public function get_importance( string $check_id ): string {
				return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
			}
			public function get_howto( string $check_id ): string {
				return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
			}
			public function get_example( string $check_id ): string {
				return '';
			}
		};
	}

	/**
	 * Render the complete metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @param array   $analysis Analysis data.
	 * @param bool    $excluded Whether post is excluded.
	 */
	public function render( WP_Post $post, array $analysis, bool $excluded ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: MetaboxRenderer::render() called - post_id=' . $post->ID . ', analysis_keys=' . implode( ',', array_keys( $analysis ) ) . ', excluded=' . ( $excluded ? 'yes' : 'no' ) );
		}
		
		// Initialize renderers lazily when needed
		$this->initialize_renderers();
		
		// Validate post using helper
		$post = PostValidationHelper::validate_post( $post );
		if ( null === $post ) {
			echo '<div class="notice notice-error"><p><strong>Errore: Oggetto post non valido.</strong></p></div>';
			return;
		}
		
		// DIAGNOSTIC: Show diagnostic info directly in metabox for homepage
		// TEMPORARILY DISABLED to test if it's causing the critical error
		/*
		// Wrapped in try-catch to prevent breaking the metabox if diagnostics fail
		try {
			$page_on_front_id = (int) get_option( 'page_on_front' );
			$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $page_on_front_id > 0 && $requested_post_id === $page_on_front_id ) {
				// CRITICAL: Avoid calling get_post() during initial metabox rendering to prevent auto-draft creation
				// Use post_status from the post object we already have
				$correct_status = ( $post->ID === $page_on_front_id ) ? $post->post_status : 'unknown';
				$is_wrong_post = $post->ID !== $page_on_front_id || $post->post_status === 'auto-draft';
				
				global $wp_query;
				$global_post = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
				$global_post_id = $global_post instanceof WP_Post ? $global_post->ID : 0;
				$global_post_status = $global_post instanceof WP_Post ? $global_post->post_status : 'none';
				
				$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
				$is_autosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
				$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				$screen_id = $current_screen ? $current_screen->id : 'unknown';
				
				$notice_class = $is_wrong_post ? 'notice-error' : 'notice-info';
				$notice_color = $is_wrong_post ? '#dc2626' : '#3b82f6';
				$icon = $is_wrong_post ? '🔍' : 'ℹ️';
				
				echo '<div class="notice ' . esc_attr( $notice_class ) . '" style="border-left-color: ' . esc_attr( $notice_color ) . '; padding: 12px; margin: 0 0 20px 0; background: #fff; border: 1px solid ' . esc_attr( $notice_color ) . '; border-radius: 4px;">';
				echo '<h3 style="margin: 0 0 8px 0; color: ' . esc_attr( $notice_color ) . ';">' . esc_html( $icon ) . ' FP SEO: Diagnostica Homepage</h3>';
				if ( $is_wrong_post ) {
					echo '<p style="margin: 0 0 8px 0; color: #dc2626;"><strong>⚠️ PROBLEMA RILEVATO:</strong> WordPress ha passato un post sbagliato!</p>';
				} else {
					echo '<p style="margin: 0 0 8px 0;"><strong>✓ Post corretto:</strong> WordPress ha passato la homepage corretta.</p>';
				}
				echo '<ul style="margin: 8px 0; padding-left: 20px; font-size: 13px;">';
				echo '<li><strong>URL richiesto:</strong> <code>post=' . esc_html( $requested_post_id ) . '&action=edit</code></li>';
				echo '<li><strong>Post ricevuto (parametro render):</strong> ID ' . esc_html( $post->ID ) . ' - Status: <code>' . esc_html( $post->post_status ) . '</code> - Type: <code>' . esc_html( $post->post_type ) . '</code></li>';
				echo '<li><strong>Post globale ($GLOBALS[\'post\']):</strong> ID ' . esc_html( $global_post_id ) . ' - Status: <code>' . esc_html( $global_post_status ) . '</code></li>';
				echo '<li><strong>Homepage corretta (dal DB):</strong> ID ' . esc_html( $page_on_front_id ) . ' - Status: <code>' . esc_html( $correct_status ) . '</code></li>';
				echo '<li><strong>Corrispondenza:</strong> ' . ( ( $post->ID === $page_on_front_id && $post->post_status !== 'auto-draft' ) ? '<span style="color: #10b981;">✓ Corretto</span>' : '<span style="color: #dc2626;">✗ ERRORE</span>' ) . '</li>';
				echo '<li><strong>Contesto:</strong> Screen: <code>' . esc_html( $screen_id ) . '</code> | AJAX: ' . ( $is_ajax ? 'Sì' : 'No' ) . ' | Autosave: ' . ( $is_autosave ? 'Sì' : 'No' ) . '</li>';
				echo '</ul>';
				if ( $is_wrong_post ) {
					echo '<p style="margin: 8px 0 0 0; font-size: 12px; color: #6b7280; padding: 8px; background: #fef2f2; border-radius: 4px;">';
					echo '<strong>Causa possibile:</strong> Qualche plugin/tema sta creando un nuovo auto-draft (ID ' . esc_html( $post->ID ) . ') invece di caricare la homepage esistente (ID ' . esc_html( $page_on_front_id ) . ').<br>';
					echo '<strong>Quando succede:</strong> Quando apri l\'editor della homepage, WordPress dovrebbe passarti il post ID ' . esc_html( $page_on_front_id ) . ', ma invece ti sta passando il post ID ' . esc_html( $post->ID ) . ' con status auto-draft.<br>';
					if ( $global_post_id !== $post->ID ) {
						echo '<strong>⚠️ DISCREPANZA:</strong> Il post globale (ID ' . esc_html( $global_post_id ) . ') è diverso dal post passato al metabox (ID ' . esc_html( $post->ID ) . '). Questo indica che qualcosa sta modificando il post durante il rendering.';
					}
					echo '</p>';
				}
				echo '</div>';
				
				// Check for auto-draft diagnosis info
				$auto_draft_diagnosis = get_transient( 'fp_seo_auto_draft_diagnosis_' . $page_on_front_id );
				if ( $auto_draft_diagnosis && is_array( $auto_draft_diagnosis ) ) {
					echo '<div class="notice notice-warning" style="border-left-color: #f59e0b; padding: 12px; margin: 0 0 20px 0; background: #fff; border: 1px solid #f59e0b; border-radius: 4px;">';
					echo '<h3 style="margin: 0 0 8px 0; color: #f59e0b;">🔍 Auto-draft Creato Recentemente</h3>';
					echo '<p style="margin: 0 0 8px 0;"><strong>Un auto-draft è stato creato mentre stavi editando la homepage.</strong></p>';
					echo '<ul style="margin: 8px 0; padding-left: 20px; font-size: 13px;">';
					echo '<li><strong>Auto-draft ID:</strong> ' . esc_html( $auto_draft_diagnosis['auto_draft_id'] ?? 'unknown' ) . '</li>';
					echo '<li><strong>Timestamp:</strong> ' . esc_html( date( 'Y-m-d H:i:s', $auto_draft_diagnosis['timestamp'] ?? 0 ) ) . '</li>';
					if ( ! empty( $auto_draft_diagnosis['caller_trace'] ) ) {
						echo '<li><strong>Chiamata da:</strong><br><code style="font-size: 11px; background: #f3f4f6; padding: 4px; border-radius: 2px;">';
						echo esc_html( implode( '<br>', array_slice( $auto_draft_diagnosis['caller_trace'], 0, 5 ) ) );
						echo '</code></li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				
				if ( $is_wrong_post ) {
					Logger::warning( 'MetaboxRenderer::render - WordPress passed wrong post when opening homepage editor', array(
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
					) );
				}
			}
		} catch ( \Throwable $e ) {
			// Silently fail diagnostics to prevent breaking the metabox
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::error( 'FP SEO: Error in homepage diagnostics in MetaboxRenderer', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				) );
			}
		}
		*/
		
		// Wrap entire render method in try-catch to prevent fatal errors
		try {
			// Verify required classes are loaded
			$required_classes = array(
				'FP\\SEO\\Utils\\Logger',
				'FP\\SEO\\Utils\\Options',
			);

			if ( ! PostValidationHelper::validate_required_classes( $required_classes ) ) {
				echo '<div class="notice notice-error"><p><strong>Errore: Classi richieste non caricate.</strong></p></div>';
				return;
			}

			// Log inizio rendering in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'FP SEO: MetaboxRenderer::render() called', array(
				'post_id' => isset( $post->ID ) ? $post->ID : 0,
				'post_type' => isset( $post->post_type ) ? $post->post_type : 'unknown',
				'excluded' => $excluded,
				'analysis_count' => is_array( $analysis ) ? count( $analysis ) : 0,
				'analysis_type' => gettype( $analysis ),
				'check_help_text_class' => isset( $this->check_help_text ) ? get_class( $this->check_help_text ) : 'not set',
				) );
			}
			
			// Post validation already done at the beginning of the method
			
			// DISABLED: Cache clearing was interfering with WordPress's post object
		// WordPress manages its own cache, we should not clear it during rendering
		// This was causing WordPress to load the wrong post (auto-draft instead of homepage)
		
			// Log per debug - verifica diretta dal database (solo in debug mode)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			global $wpdb;
			$db_title = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post->ID, '_fp_seo_title' ) );
			$db_desc = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post->ID, '_fp_seo_meta_description' ) );
			$db_keyword = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post->ID, '_fp_seo_focus_keyword' ) );
			
			$seo_title_debug = get_post_meta( $post->ID, '_fp_seo_title', true );
			$meta_desc_debug = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
			$focus_keyword_debug = get_post_meta( $post->ID, '_fp_seo_focus_keyword', true );
			
			Logger::debug( 'MetaboxRenderer::render - metadata comparison', array(
				'post_id' => $post->ID,
				'db_direct' => array(
					'title' => $db_title ? substr( $db_title, 0, 50 ) : 'empty',
					'description' => $db_desc ? substr( $db_desc, 0, 50 ) : 'empty',
					'keyword' => $db_keyword ?: 'empty',
				),
				'get_post_meta' => array(
					'title' => $seo_title_debug ? substr( $seo_title_debug, 0, 50 ) : 'empty',
					'description' => $meta_desc_debug ? substr( $meta_desc_debug, 0, 50 ) : 'empty',
					'keyword' => $focus_keyword_debug ?: 'empty',
				),
			) );
			}
			
			// Add hidden field to ensure metabox is always recognized in POST
			// This helps WordPress identify that our metabox fields should be processed
			echo '<input type="hidden" name="fp_seo_performance_metabox_present" value="1" />';
			
		
			// Ensure check_help_text is initialized (doppio controllo di sicurezza)
			if ( ! isset( $this->check_help_text ) || ! is_object( $this->check_help_text ) ) {
			// Try to initialize it
			try {
				if ( class_exists( 'FP\SEO\Editor\CheckHelpText' ) ) {
					$this->check_help_text = new \FP\SEO\Editor\CheckHelpText();
				} else {
					// Create dummy object
					$this->check_help_text = new class {
						public function get_importance( string $check_id ): string {
							return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
						}
						public function get_howto( string $check_id ): string {
							return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
						}
						public function get_example( string $check_id ): string {
							return '';
						}
					};
				}
			} catch ( \Throwable $e ) {
				// Log errore se possibile
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\LoggerHelper::error( 'FP SEO: Failed to initialize CheckHelpText in render', array(
						'error' => $e->getMessage(),
					) );
				}
				// Create dummy object on error
				$this->check_help_text = new class {
					public function get_importance( string $check_id ): string {
						return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
					}
					public function get_howto( string $check_id ): string {
						return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
					}
					public function get_example( string $check_id ): string {
						return '';
					}
				};
			}
		}
		
		// Verifica finale che check_help_text abbia i metodi necessari
		if ( ! method_exists( $this->check_help_text, 'get_importance' ) ) {
			$this->check_help_text = new class {
				public function get_importance( string $check_id ): string {
					return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
				}
				public function get_howto( string $check_id ): string {
					return __( 'Informazioni non disponibili.', 'fp-seo-performance' );
				}
				public function get_example( string $check_id ): string {
					return '';
				}
			};
			}
			
			// Ensure $analysis is an array before accessing its keys
			if ( ! is_array( $analysis ) ) {
				Logger::error( 'FP SEO: Invalid analysis parameter in MetaboxRenderer::render()', array(
					'analysis_type' => gettype( $analysis ),
					'post_id' => isset( $post->ID ) ? $post->ID : 0,
				) );
				$analysis = array(); // Default to empty array
			}
			
		$score_value  = isset( $analysis['score']['score'] ) ? (int) $analysis['score']['score'] : 0;
		$score_status = isset( $analysis['score']['status'] ) ? (string) $analysis['score']['status'] : 'pending';
		$checks       = $analysis['checks'] ?? array();
		
		if ( empty( $checks ) && isset( $analysis['checks'] ) && is_array( $analysis['checks'] ) ) {
			$checks = $analysis['checks'];
		}
			
			// Ensure checks is always an array
			if ( ! is_array( $checks ) ) {
				$checks = array();
			}
			
			// Debug: log checks received in renderer
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'MetaboxRenderer::render - checks received', array(
					'post_id' => ( $post instanceof WP_Post ) ? $post->ID : 0,
					'post_type' => gettype( $post ),
					'checks_count' => count( $checks ),
					'analysis_keys' => array_keys( $analysis ),
					'has_checks_key' => isset( $analysis['checks'] ),
					'checks_type' => gettype( $analysis['checks'] ?? 'not_set' ),
					'first_check' => ! empty( $checks ) ? reset( $checks ) : null,
					'all_checks' => $checks, // Log all checks for debugging
				) );
			}

			// Avvolgi tutto il rendering in un try/catch per catturare errori specifici
			// CRITICAL: Log that we're about to render
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'MetaboxRenderer::render - About to output HTML', array(
					'post_id' => ( $post instanceof WP_Post ) ? $post->ID : 0,
				) );
			}
			?>
		<?php
		// Render critical styles FIRST, before any HTML output
		?>
		<style id="fp-seo-metabox-modern-ui">
		/* Help Banner */
		.fp-seo-metabox-help-banner {
			background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
			border-left: 4px solid #3b82f6 !important;
			padding: 16px 20px !important;
			margin-bottom: 20px !important;
			border-radius: 8px !important;
			display: flex !important;
			gap: 16px !important;
			align-items: flex-start !important;
			position: relative !important;
		}
		
		.fp-seo-metabox-help-banner__icon {
			font-size: 24px !important;
			line-height: 1 !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-metabox-help-banner__content {
			flex: 1 !important;
		}
		
		.fp-seo-metabox-help-banner__title {
			margin: 0 0 8px !important;
			font-size: 14px !important;
			font-weight: 600 !important;
			color: #1e40af !important;
		}
		
		.fp-seo-metabox-help-banner__text {
			margin: 0 0 12px !important;
			font-size: 13px !important;
			color: #1e3a8a !important;
			line-height: 1.5 !important;
		}
		
		.fp-seo-metabox-help-banner__close {
			position: absolute !important;
			top: 8px !important;
			right: 8px !important;
			background: rgba(255, 255, 255, 0.7) !important;
			border: none !important;
			border-radius: 4px !important;
			width: 24px !important;
			height: 24px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
			cursor: pointer !important;
			font-size: 18px !important;
			line-height: 1 !important;
			color: #1e40af !important;
			transition: all 0.2s !important;
		}
		
		.fp-seo-metabox-help-banner.hidden {
			display: none !important;
		}
		
		/* Section Styles */
		.fp-seo-performance-metabox__section {
			margin-bottom: 20px !important;
			padding: 16px !important;
			background: #fff !important;
			border-radius: 8px !important;
			border: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			margin: 0 0 16px !important;
			font-size: 16px !important;
			font-weight: 600 !important;
			color: #111827 !important;
		}
		
		.fp-seo-performance-metabox__section-content {
			margin-top: 16px !important;
		}
		
		/* SERP Preview Styles */
		.fp-seo-serp-preview-card {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 16px !important;
			margin-bottom: 12px !important;
		}
		
		.fp-seo-serp-preview-url {
			color: #3b82f6 !important;
			font-size: 14px !important;
			margin-bottom: 4px !important;
			line-height: 1.4 !important;
		}
		
		.fp-seo-serp-preview-title {
			color: #1a0dab !important;
			font-size: 20px !important;
			font-weight: 400 !important;
			margin-bottom: 4px !important;
			line-height: 1.3 !important;
			cursor: pointer !important;
		}
		
		.fp-seo-serp-preview-title:hover {
			text-decoration: underline !important;
		}
		
		.fp-seo-serp-preview-description {
			color: #545454 !important;
			font-size: 14px !important;
			line-height: 1.5 !important;
			margin-bottom: 4px !important;
		}
		
		/* Legacy SERP Preview Styles (for compatibility) */
		.fp-seo-serp-preview__container {
			background: #f9fafb !important;
			border-radius: 8px !important;
			padding: 20px !important;
			border: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-serp-preview__device-toggle {
			display: flex !important;
			gap: 8px !important;
			margin-bottom: 16px !important;
		}
		
		.fp-seo-serp-device {
			padding: 8px 16px !important;
			border: 1px solid #d1d5db !important;
			background: #fff !important;
			border-radius: 6px !important;
			cursor: pointer !important;
			font-size: 13px !important;
			transition: all 0.2s !important;
		}
		
		.fp-seo-serp-device.active {
			background: #6366f1 !important;
			color: #fff !important;
			border-color: #6366f1 !important;
		}
		
		.fp-seo-serp-preview__snippet {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 16px !important;
		}
		
		.fp-seo-serp-preview__url {
			color: #3b82f6 !important;
			font-size: 14px !important;
			margin-bottom: 4px !important;
		}
		
		.fp-seo-serp-preview__title {
			color: #1a0dab !important;
			font-size: 20px !important;
			font-weight: 400 !important;
			margin-bottom: 4px !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-serp-preview__description {
			color: #545454 !important;
			font-size: 14px !important;
			line-height: 1.5 !important;
			margin-bottom: 4px !important;
		}
		
		.fp-seo-serp-preview__date {
			color: #808080 !important;
			font-size: 12px !important;
		}
		
		/* Analysis Section Styles */
		.fp-seo-analysis-section {
			background: #fff !important;
			border-radius: 8px !important;
			overflow: visible !important;
		}
		
		.fp-seo-analysis-section .fp-seo-performance-metabox__section-content {
			padding: 16px 0 !important;
		}
		
		.fp-seo-analysis-section .fp-seo-performance-metabox__section-content > p {
			padding: 16px 20px !important;
			background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
			border: 2px solid #10b981 !important;
			border-radius: 8px !important;
			font-size: 14px !important;
			font-weight: 600 !important;
			color: #065f46 !important;
			text-align: center !important;
			margin: 0 !important;
			box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1) !important;
		}
		
		.fp-seo-performance-metabox__unified-analysis {
			margin-bottom: 20px !important;
		}
		
		.fp-seo-checks-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			display: flex !important;
			flex-direction: column !important;
			gap: 12px !important;
		}
		
		.fp-seo-check {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 12px 16px !important;
			transition: all 0.3s !important;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
		}
		
		.fp-seo-check:hover {
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-check__header {
			display: flex !important;
			align-items: center !important;
			gap: 12px !important;
		}
		
		.fp-seo-check__icon {
			font-size: 18px !important;
			line-height: 1 !important;
			flex-shrink: 0 !important;
			width: 24px !important;
			height: 24px !important;
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}
		
		.fp-seo-check__message {
			font-size: 14px !important;
			font-weight: 500 !important;
			color: #111827 !important;
			flex: 1 !important;
			line-height: 1.5 !important;
		}
		
		.fp-seo-check--pass {
			border-left: 4px solid #10b981 !important;
			background: linear-gradient(90deg, #f0fdf4 0%, #fff 10%) !important;
		}
		
		.fp-seo-check--warn {
			border-left: 4px solid #f59e0b !important;
			background: linear-gradient(90deg, #fef3c7 0%, #fff 10%) !important;
		}
		
		.fp-seo-check--fail {
			border-left: 4px solid #ef4444 !important;
			background: linear-gradient(90deg, #fee2e2 0%, #fff 10%) !important;
		}
		
		.fp-seo-check__help {
			margin-top: 12px !important;
			padding-top: 12px !important;
			border-top: 1px solid #e5e7eb !important;
			font-size: 13px !important;
			color: #4b5563 !important;
			line-height: 1.6 !important;
		}
		
		.fp-seo-check__help p {
			margin: 8px 0 !important;
		}
		
		.fp-seo-check__help strong {
			color: #111827 !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-check__help code {
			background: #f3f4f6 !important;
			padding: 3px 8px !important;
			border-radius: 4px !important;
			font-size: 12px !important;
			font-family: 'Courier New', monospace !important;
			color: #1f2937 !important;
		}
		
		.fp-seo-checks-details {
			margin-top: 12px !important;
		}
		
		.fp-seo-checks-summary {
			cursor: pointer !important;
			padding: 12px 16px !important;
			background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
			border: 1px solid #10b981 !important;
			border-radius: 6px !important;
			font-size: 14px !important;
			font-weight: 600 !important;
			color: #065f46 !important;
			transition: all 0.2s !important;
		}
		
		.fp-seo-checks-summary:hover {
			background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%) !important;
			transform: translateY(-1px) !important;
			box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2) !important;
		}
		
		.fp-seo-checks-summary::-webkit-details-marker {
			display: none !important;
		}
		
		.fp-seo-checks-summary::marker {
			display: none !important;
		}
		
		/* Legacy Analysis Styles (for compatibility) */
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
			transition: all 0.3s !important;
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
		
		.fp-seo-performance-analysis-item--pass::before {
			background: #10b981 !important;
		}
		
		.fp-seo-performance-analysis-item--warn::before {
			background: #f59e0b !important;
		}
		
		.fp-seo-performance-analysis-item--fail::before {
			background: #ef4444 !important;
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
		</style>
		<div class="fp-seo-performance-metabox" data-fp-seo-metabox>
			<?php
			// Use SectionRegistry if available, otherwise use fallback rendering
			if ( $this->section_registry ) {
				$this->render_with_registry( $post, $analysis, $excluded, $score_value, $score_status, $checks );
			} else {
				// Fallback: render basic metabox without sections
				$this->render_fallback( $post, $analysis, $excluded, $score_value, $score_status, $checks );
			}
			?>
		</div>
		<?php
		} catch ( \Throwable $e ) {
			// Catch any fatal errors in the render method to prevent breaking the metabox
			Logger::error( 'FP SEO: Fatal error in MetaboxRenderer::render()', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'post_id' => isset( $post->ID ) ? $post->ID : 0,
			) );
			
			// Show error message to user
			echo '<div class="notice notice-error" style="display: block !important; padding: 15px; margin: 10px 0;">';
			echo '<p><strong>' . esc_html__( 'Errore critico nel rendering del metabox SEO', 'fp-seo-performance' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Impossibile caricare il metabox completo. Controlla i log per dettagli.', 'fp-seo-performance' ) . '</p>';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo '<p><small><strong>Errore:</strong> ' . esc_html( $e->getMessage() ) . '</small></p>';
				echo '<p><small><strong>File:</strong> ' . esc_html( $e->getFile() ) . ':' . esc_html( $e->getLine() ) . '</small></p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Render using SectionRegistry (modular approach).
	 * 
	 * SectionRegistry is always available when MetaboxRenderer is created via DI container.
	 *
	 * @param WP_Post              $post Post object.
	 * @param array<string, mixed> $analysis Analysis data.
	 * @param bool                 $excluded Whether post is excluded.
	 * @param int                  $score_value Score value.
	 * @param string               $score_status Score status.
	 * @param array<string, mixed> $checks Checks array.
	 * @return void
	 * @throws \RuntimeException If SectionRegistry is not available.
	 */
	private function render_with_registry( WP_Post $post, array $analysis, bool $excluded, int $score_value, string $score_status, array $checks ): void {
		// SectionRegistry is always available when created via DI container
		if ( ! $this->section_registry ) {
			throw new \RuntimeException( 'SectionRegistry is not available. Ensure MetaboxRenderer is created via DI container.' );
		}

		// Prepare context for sections
		// CRITICAL: Ensure checks are always in both places for compatibility
		$context = array(
			'analysis'      => array_merge( $analysis, array( 'checks' => $checks ) ), // Ensure checks are in analysis too
			'excluded'      => $excluded,
			'score'         => array(
				'score'  => $score_value,
				'status' => $score_status,
			),
			'checks'        => $checks, // Also pass directly for backward compatibility
		);
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO DEBUG: render_with_registry - checks_count=' . count( $checks ) . ', context_has_analysis_checks=' . ( isset( $context['analysis']['checks'] ) ? 'yes(' . count( $context['analysis']['checks'] ) . ')' : 'no' ) . ', context_has_checks=' . ( isset( $context['checks'] ) ? 'yes(' . count( $context['checks'] ) . ')' : 'no' ) );
		}

		// Get enabled sections sorted by priority
		$sections = $this->section_registry->get_by_priority( $post );

		// Render each section
		foreach ( $sections as $section ) {
			try {
				$section->render( $post, $context );
			} catch ( \Throwable $e ) {
				Logger::error(
					'FP SEO: Error rendering section',
					array(
						'section_id' => $section->get_id(),
						'error'      => $e->getMessage(),
						'trace'      => $e->getTraceAsString(),
					)
				);
			}
		}
	}

	/**
	 * Fallback rendering when SectionRegistry is not available.
	 * 
	 * Renders basic SEO fields without using the modular section system.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $analysis Analysis data.
	 * @param bool    $excluded Whether post is excluded from analysis.
	 * @param int     $score_value SEO score value.
	 * @param string  $score_status SEO score status.
	 * @param array   $checks Analysis checks.
	 * @return void
	 */
	private function render_fallback( WP_Post $post, array $analysis, bool $excluded, int $score_value, string $score_status, array $checks ): void {
		// Initialize renderers if not already done
		if ( ! $this->serp_fields_renderer ) {
			$this->serp_fields_renderer = new SerpFieldsRenderer();
		}
		if ( ! $this->header_renderer ) {
			$this->header_renderer = new HeaderRenderer();
		}

		// Render basic header with score
		if ( $this->header_renderer ) {
			$this->header_renderer->render( $post, array(
				'score' => array(
					'score' => $score_value,
					'status' => $score_status,
				),
				'excluded' => $excluded,
			) );
		}

		// Render SERP fields (title, description, keyword)
		if ( $this->serp_fields_renderer ) {
			$this->serp_fields_renderer->render( $post, array(
				'analysis' => $analysis,
			) );
		}

		// Show a notice that full features require SectionRegistry
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			?>
			<div class="notice notice-warning inline">
				<p><strong>Debug:</strong> MetaboxRenderer is using fallback mode. SectionRegistry is not available. Some features may be limited.</p>
			</div>
			<?php
		}
	}
}
