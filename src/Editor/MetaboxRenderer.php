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

use FP\SEO\Editor\ImageExtractor;
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
	 * @var string
	 */
	private const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';

	/**
	 * @var string
	 */
	private const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Inizializza check_help_text con gestione errori robusta
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
		// Verifica che tutte le dipendenze siano caricate prima di iniziare
		$required_classes = array(
			'FP\\SEO\\Utils\\Logger',
			'FP\\SEO\\Utils\\Options',
		);

		foreach ( $required_classes as $class_name ) {
			if ( ! class_exists( $class_name, false ) ) {
				Logger::error( 'FP SEO: Required class not loaded in MetaboxRenderer::render()', array(
					'class' => $class_name,
				) );
				echo '<div class="notice notice-error"><p><strong>Errore: Classe ' . esc_html( $class_name ) . ' non caricata.</strong></p></div>';
				return;
			}
		}

		// Log inizio rendering in debug mode
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'FP SEO: MetaboxRenderer::render() called', array(
				'post_id' => isset( $post->ID ) ? $post->ID : 0,
				'post_type' => isset( $post->post_type ) ? $post->post_type : 'unknown',
				'excluded' => $excluded,
				'analysis_count' => count( $analysis ),
				'check_help_text_class' => get_class( $this->check_help_text ),
			) );
		}
		
		// Ensure we have a valid post ID
		if ( empty( $post->ID ) || $post->ID <= 0 ) {
			// Try to get post ID from request
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : ( isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0 );
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						Logger::error( 'FP SEO: Could not retrieve post in renderer', array(
							'post_id' => $post_id,
						) );
					}
					return;
				}
			} else {
				// New post - that's OK, continue with empty fields
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'FP SEO: Rendering metabox for new post', array(
						'post_type' => $post->post_type ?? 'unknown',
					) );
				}
			}
		}
		
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
		
		// Validate inputs
		if ( ! $post instanceof WP_Post ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( '\FP\SEO\Utils\Logger' ) ) {
				Logger::error( 'FP SEO: Invalid post object in render', array( 'post' => gettype( $post ) ) );
			}
			return;
		}
		
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
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( '\FP\SEO\Utils\Logger' ) ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize CheckHelpText in render', array(
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
		
		$score_value  = isset( $analysis['score']['score'] ) ? (int) $analysis['score']['score'] : 0;
		$score_status = isset( $analysis['score']['status'] ) ? (string) $analysis['score']['status'] : 'pending';
		$checks       = $analysis['checks'] ?? array();
		
		// Debug: log checks received in renderer
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'MetaboxRenderer::render - checks received', array(
				'post_id' => $post->ID,
				'checks_count' => count( $checks ),
				'first_check' => ! empty( $checks ) ? reset( $checks ) : null,
			) );
		}

		// Avvolgi tutto il rendering in un try/catch per catturare errori specifici
		?>
		<div class="fp-seo-performance-metabox" data-fp-seo-metabox>
			<?php
			try {
				$this->render_header( $excluded, $score_value, $score_status );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering header', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_serp_optimization_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering SERP optimization', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_serp_preview_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering SERP preview', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_analysis_section( $checks );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering analysis', array( 'error' => $e->getMessage() ) );
				echo '<div class="notice notice-error"><p>Errore nel rendering dell\'analisi: ' . esc_html( $e->getMessage() ) . '</p></div>';
			}
			
			try {
				$this->render_images_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering images section', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'post_id' => $post->ID ?? 0,
				) );
				// Mostra un messaggio di errore invece di nascondere completamente la sezione
				echo '<div class="notice notice-warning" style="margin: 10px 0; padding: 12px;">';
				echo '<p><strong>⚠️ Errore nel caricamento della sezione immagini:</strong></p>';
				echo '<p>' . esc_html( $e->getMessage() ) . '</p>';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					echo '<p><small>File: ' . esc_html( $e->getFile() ) . ':' . esc_html( $e->getLine() ) . '</small></p>';
				}
				echo '</div>';
			}
			
			try {
				$this->render_gsc_metrics( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering GSC metrics', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_ai_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering AI section', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_social_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering social section', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_internal_links_section( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering internal links', array( 'error' => $e->getMessage() ) );
			}
			
			try {
				$this->render_schema_sections( $post );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error rendering schema sections', array( 'error' => $e->getMessage() ) );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render header section (banner, controls, score).
	 *
	 * @param bool   $excluded Whether post is excluded.
	 * @param int    $score_value Score value.
	 * @param string $score_status Score status.
	 */
	private function render_header( bool $excluded, int $score_value, string $score_status ): void {
		?>
		<!-- Banner informativo -->
		<div class="fp-seo-metabox-help-banner">
			<div class="fp-seo-metabox-help-banner__icon">ℹ️</div>
			<div class="fp-seo-metabox-help-banner__content">
				<h4 class="fp-seo-metabox-help-banner__title">
					<?php esc_html_e( 'Come funziona l\'analisi SEO?', 'fp-seo-performance' ); ?>
				</h4>
				<p class="fp-seo-metabox-help-banner__text">
					<?php esc_html_e( 'Questo tool analizza in tempo reale il tuo contenuto e ti assegna un punteggio SEO da 0 a 100. Ogni modifica che fai (titolo, contenuto, ecc.) viene automaticamente analizzata dopo 500ms.', 'fp-seo-performance' ); ?>
				</p>
				<div class="fp-seo-metabox-help-banner__legend">
					<span class="fp-seo-legend-item fp-seo-legend-item--pass">
						<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Ottimo (tutto ok)', 'fp-seo-performance' ); ?>
					</span>
					<span class="fp-seo-legend-item fp-seo-legend-item--warn">
						<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Attenzione (da migliorare)', 'fp-seo-performance' ); ?>
					</span>
					<span class="fp-seo-legend-item fp-seo-legend-item--fail">
						<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Critico (richiede azione)', 'fp-seo-performance' ); ?>
					</span>
				</div>
			</div>
			<button type="button" class="fp-seo-metabox-help-banner__close" title="<?php esc_attr_e( 'Chiudi', 'fp-seo-performance' ); ?>">×</button>
		</div>

		<div class="fp-seo-performance-metabox__controls">
			<label for="fp-seo-performance-exclude">
				<input type="checkbox" name="fp_seo_performance_exclude" id="fp-seo-performance-exclude" value="1" <?php checked( $excluded ); ?> data-fp-seo-exclude />
				<?php esc_html_e( 'Exclude this content from analysis', 'fp-seo-performance' ); ?>
				<span class="fp-seo-tooltip-trigger" data-tooltip="<?php esc_attr_e( 'Attiva questa opzione per escludere completamente questo contenuto dall\'analisi SEO. Utile per pagine di servizio, ringraziamenti, ecc.', 'fp-seo-performance' ); ?>">ℹ️</span>
			</label>
		</div>
		<div class="fp-seo-performance-metabox__message" role="status" aria-live="polite" data-fp-seo-message></div>
		<div class="fp-seo-performance-metabox__score" role="status" aria-live="polite" aria-atomic="true" data-fp-seo-score data-status="<?php echo esc_attr( $score_status ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Punteggio SEO corrente: %d su 100', 'fp-seo-performance' ), $score_value ) ); ?>">
			<strong class="fp-seo-performance-metabox__score-label"><?php esc_html_e( 'SEO Score', 'fp-seo-performance' ); ?></strong>
			<span class="fp-seo-performance-metabox__score-value" data-fp-seo-score-value><?php echo esc_html( (string) $score_value ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render SERP Optimization section (Title, Description, Slug, Excerpt, Keywords).
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_serp_optimization_section( WP_Post $post ): void {
		?>
		<!-- Section 1: SERP OPTIMIZATION (Very High Impact) -->
		<div class="fp-seo-performance-metabox__section fp-seo-serp-optimization-section" style="border-left: 4px solid #10b981;" data-section="serp-optimization">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🎯</span>
					<?php esc_html_e( 'SERP Optimization', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +40%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #f0fdf4; border-radius: 6px; border-left: 3px solid #10b981;">
					<strong style="color: #059669;">💡 Questi campi appaiono direttamente su Google e influenzano la SERP</strong><br>
					Ottimizzali per massimizzare visibilità e click-through rate. Totale impatto sezione: <strong>+40% score</strong> (Title +15%, Description +10%, Excerpt +9%, Slug +6%).
				</p>
				
				<!-- CAMPI PRINCIPALI SEMPRE VISIBILI -->
				<div style="display: grid; gap: 16px; margin-bottom: 20px;">
					<?php $this->render_seo_title_field( $post ); ?>
					<?php $this->render_meta_description_field( $post ); ?>
					<?php $this->render_slug_field( $post ); ?>
					<?php $this->render_excerpt_field( $post ); ?>
					
					<!-- Separator -->
					<div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%); margin: 8px 0;"></div>
					
					<?php $this->render_keywords_section( $post ); ?>
				</div>
				
				<!-- Advanced Keywords Manager (optional integration) -->
				<?php
				try {
					$keywords_manager = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Keywords\MultipleKeywordsManager::class );
					if ( $keywords_manager && method_exists( $keywords_manager, 'render_keywords_metabox' ) ) {
						// Il manager potrebbe aggiungere tab o funzionalità avanzate
						// $keywords_manager->render_keywords_metabox( $post );
					}
				} catch ( \Exception $e ) {
					// Manager non disponibile - campi base già mostrati sopra
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render SERP Preview section.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_serp_preview_section( WP_Post $post ): void {
		?>
		<!-- Section: SERP PREVIEW -->
		<div class="fp-seo-serp-preview fp-seo-performance-metabox__section" style="border-left: 4px solid #6366f1;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🔍</span>
					<?php esc_html_e( 'SERP Preview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);">
					<?php esc_html_e( 'Anteprima Live', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #eef2ff; border-radius: 6px; border-left: 3px solid #6366f1;">
					<strong style="color: #4f46e5;">🔍 Anteprima Live</strong> - Visualizza come apparirà il tuo contenuto nei risultati di ricerca Google. Aggiornamento in tempo reale.
				</p>
				<div class="fp-seo-serp-preview__container">
					<div class="fp-seo-serp-preview__device-toggle">
						<button type="button" class="fp-seo-serp-device active" data-device="desktop">💻 <?php esc_html_e( 'Desktop', 'fp-seo-performance' ); ?></button>
						<button type="button" class="fp-seo-serp-device" data-device="mobile">📱 <?php esc_html_e( 'Mobile', 'fp-seo-performance' ); ?></button>
					</div>
					
					<div class="fp-seo-serp-preview__snippet" data-device="desktop">
						<div class="fp-seo-serp-preview__url"></div>
						<div class="fp-seo-serp-preview__title"></div>
						<div class="fp-seo-serp-preview__description"></div>
						<div class="fp-seo-serp-preview__date"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render SEO Title field.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_seo_title_field( WP_Post $post ): void {
		// DISABLED: Cache clearing interferes with WordPress's post object
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}
		
		$seo_title_value = get_post_meta( $post->ID, '_fp_seo_title', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $seo_title_value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_title' ) );
			if ( $db_value !== null ) {
				$seo_title_value = $db_value;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'render_seo_title_field - fallback DB query found value', array(
						'post_id' => $post->ID,
						'value_preview' => substr( $seo_title_value, 0, 50 ),
					) );
				}
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'render_seo_title_field - final value', array(
				'post_id' => $post->ID,
				'final_value' => $seo_title_value ? substr( $seo_title_value, 0, 50 ) : 'empty',
			) );
		}
		?>
		<!-- SEO Title -->
		<div style="position: relative;">
			<label for="fp-seo-title" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">📝</span>
					<?php esc_html_e( 'SEO Title', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #10b981; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+15%</span>
				</span>
				<span id="fp-seo-title-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/60</span>
			</label>
			<div style="display: flex; gap: 8px; align-items: stretch;">
				<?php
				?>
				<input 
					type="text" 
					id="fp-seo-title" 
					name="fp_seo_title"
					value="<?php echo esc_attr( wp_specialchars_decode( $seo_title_value, ENT_QUOTES ) ); ?>"
					placeholder="<?php esc_attr_e( 'es: Guida Completa alla SEO WordPress 2025 | Nome Sito', 'fp-seo-performance' ); ?>"
					maxlength="70"
					aria-label="<?php esc_attr_e( 'SEO Title - Titolo ottimizzato per SERP', 'fp-seo-performance' ); ?>"
					style="flex: 1; padding: 10px 14px; font-size: 14px; border: 2px solid #10b981; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
					data-fp-seo-title
				/>
				<!-- Hidden field to ensure fp_seo_title is always in POST -->
				<input type="hidden" name="fp_seo_title_sent" value="1" />
				<button 
					type="button" 
					class="fp-seo-ai-generate-field-btn" 
					data-field="seo_title"
					data-target-id="fp-seo-title"
					data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span>🤖</span>
					<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
				</button>
			</div>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #059669;">🎯 Alto impatto (+15%)</strong> - Appare come titolo principale in Google. Lunghezza ottimale: 50-60 caratteri con keyword all'inizio.
			</p>
		</div>
		<?php
	}

	/**
	 * Render Meta Description field.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_meta_description_field( WP_Post $post ): void {
		// DISABLED: Cache clearing interferes with WordPress's post object
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}
		
		$meta_desc_value = get_post_meta( $post->ID, '_fp_seo_meta_description', true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $meta_desc_value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, '_fp_seo_meta_description' ) );
			if ( $db_value !== null ) {
				$meta_desc_value = $db_value;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'render_meta_description_field - fallback DB query found value', array(
						'post_id' => $post->ID,
						'value_preview' => substr( $meta_desc_value, 0, 50 ),
					) );
				}
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'render_meta_description_field - final value', array(
				'post_id' => $post->ID,
				'final_value' => $meta_desc_value ? substr( $meta_desc_value, 0, 50 ) : 'empty',
			) );
		}
		?>
		<!-- Meta Description -->
		<div style="position: relative;">
			<label for="fp-seo-meta-description" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">📄</span>
					<?php esc_html_e( 'Meta Description', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #10b981; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+10%</span>
				</span>
				<span id="fp-seo-meta-description-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/160</span>
			</label>
			<div style="display: flex; gap: 8px; align-items: flex-start;">
				<textarea 
					id="fp-seo-meta-description" 
					name="fp_seo_meta_description"
					placeholder="<?php esc_attr_e( 'es: Scopri come ottimizzare WordPress per la SEO con la nostra guida completa 2025. Aumenta il traffico del 300% seguendo 5 step comprovati.', 'fp-seo-performance' ); ?>"
					maxlength="200"
					rows="3"
					aria-label="<?php esc_attr_e( 'Meta Description - Descrizione per SERP', 'fp-seo-performance' ); ?>"
					style="flex: 1; padding: 10px 14px; font-size: 13px; border: 2px solid #10b981; border-radius: 8px; background: #fff; resize: vertical; line-height: 1.5; transition: all 0.2s ease;"
					data-fp-seo-meta-description
					autocomplete="off"
				><?php echo esc_textarea( wp_specialchars_decode( $meta_desc_value, ENT_QUOTES ) ); ?></textarea>
				<!-- Hidden field to ensure fp_seo_meta_description is always in POST -->
				<input type="hidden" name="fp_seo_meta_description_sent" value="1" />
				<button 
					type="button" 
					class="fp-seo-ai-generate-field-btn" 
					data-field="meta_description"
					data-target-id="fp-seo-meta-description"
					data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span>🤖</span>
					<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
				</button>
			</div>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #059669;">🎯 Medio-Alto impatto (+10%)</strong> - Descrizione sotto il titolo in Google. Include keyword + CTA. Ottimale: 150-160 caratteri.
			</p>
		</div>
		<?php
	}

	/**
	 * Render Slug field.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_slug_field( WP_Post $post ): void {
		?>
		<!-- Slug (URL Permalink) -->
		<div style="position: relative;">
			<label for="fp-seo-slug" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">🔗</span>
					<?php esc_html_e( 'Slug (URL Permalink)', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #6b7280; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+6%</span>
				</span>
				<span id="fp-seo-slug-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0 parole</span>
			</label>
			<div style="display: flex; gap: 8px; align-items: stretch;">
				<input 
					type="text" 
					id="fp-seo-slug" 
					name="fp_seo_slug"
					value="<?php echo esc_attr( $post->post_name ); ?>"
					placeholder="<?php esc_attr_e( 'es: guida-seo-wordpress-2025 (lowercase, separate-con-trattini)', 'fp-seo-performance' ); ?>"
					maxlength="100"
					aria-label="<?php esc_attr_e( 'Slug URL - Permalink SEO-friendly', 'fp-seo-performance' ); ?>"
					style="flex: 1; padding: 10px 14px; font-size: 13px; font-family: monospace; border: 2px solid #9ca3af; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
					data-fp-seo-slug
				/>
				<button 
					type="button" 
					class="fp-seo-ai-generate-field-btn" 
					data-field="slug"
					data-target-id="fp-seo-slug"
					data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span>🤖</span>
					<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
				</button>
			</div>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #6b7280;">📊 Medio-Basso impatto (+6%)</strong> - URL della pagina (dopo il dominio). Breve, con keyword, solo lowercase e trattini. Es: <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 10px;">dominio.it/<strong>questo-e-lo-slug</strong></code>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Excerpt field.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_excerpt_field( WP_Post $post ): void {
		?>
		<!-- Riassunto (Excerpt) -->
		<div style="position: relative;">
			<label for="fp-seo-excerpt" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">📋</span>
					<?php esc_html_e( 'Riassunto (Excerpt)', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+9%</span>
				</span>
				<span id="fp-seo-excerpt-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/150</span>
			</label>
			<textarea 
				id="fp-seo-excerpt" 
				name="fp_seo_excerpt"
				placeholder="<?php esc_attr_e( 'es: Breve riassunto del contenuto. Usato come fallback per meta description se non compilata. 100-150 caratteri ottimali.', 'fp-seo-performance' ); ?>"
				maxlength="300"
				rows="3"
				aria-label="<?php esc_attr_e( 'Riassunto - Excerpt usato come fallback meta description', 'fp-seo-performance' ); ?>"
				style="width: 100%; padding: 10px 14px; font-size: 13px; border: 2px solid #3b82f6; border-radius: 8px; background: #fff; resize: vertical; line-height: 1.5; transition: all 0.2s ease;"
				data-fp-seo-excerpt
			><?php echo esc_textarea( html_entity_decode( $post->post_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?></textarea>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #3b82f6;">🎯 Medio impatto (+9%)</strong> - Riassunto breve del contenuto. Usato come fallback se Meta Description è vuota. Appare anche in archivi/elenchi. Ottimale: 100-150 caratteri.
			</p>
		</div>
		<?php
	}

	/**
	 * Render Keywords section (Focus and Secondary).
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_keywords_section( WP_Post $post ): void {
		// DISABLED: Cache clearing interferes with WordPress's post object
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}
		
		$focus_keyword_value = get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true );
		$secondary_keywords_value = get_post_meta( $post->ID, self::META_SECONDARY_KEYWORDS, true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $focus_keyword_value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, self::META_FOCUS_KEYWORD ) );
			if ( $db_value !== null ) {
				$focus_keyword_value = $db_value;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'render_keywords_section - fallback DB query found focus keyword', array(
						'post_id' => $post->ID,
						'focus_keyword' => $focus_keyword_value,
					) );
				}
			}
		}
		
		if ( empty( $secondary_keywords_value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post->ID, self::META_SECONDARY_KEYWORDS ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$secondary_keywords_value = is_array( $unserialized ) ? $unserialized : $db_value;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::debug( 'render_keywords_section - fallback DB query found secondary keywords', array(
						'post_id' => $post->ID,
					) );
				}
			}
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'render_keywords_section - final values', array(
				'post_id' => $post->ID,
				'focus_keyword' => $focus_keyword_value ?: 'empty',
				'secondary_keywords' => is_array( $secondary_keywords_value ) ? implode( ', ', $secondary_keywords_value ) : ( $secondary_keywords_value ?: 'empty' ),
			) );
		}
		?>
		<!-- Focus Keyword -->
		<div style="position: relative;">
			<label for="fp-seo-focus-keyword" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">🔑</span>
					<?php esc_html_e( 'Focus Keyword (Principale)', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+8%</span>
				</span>
			</label>
			<input 
				type="text" 
				id="fp-seo-focus-keyword" 
				name="fp_seo_focus_keyword"
				value="<?php echo esc_attr( $focus_keyword_value ); ?>"
				placeholder="<?php esc_attr_e( 'es: seo wordpress, ottimizzazione motori ricerca', 'fp-seo-performance' ); ?>"
				aria-label="<?php esc_attr_e( 'Focus Keyword - Parola chiave principale per ottimizzazione SEO', 'fp-seo-performance' ); ?>"
				aria-describedby="fp-seo-focus-keyword-hint"
				style="width: 100%; padding: 10px 14px; font-size: 14px; border: 2px solid #3b82f6; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
				data-fp-seo-focus-keyword
			/>
			<span id="fp-seo-focus-keyword-hint" class="screen-reader-text">
				<?php esc_html_e( 'Inserisci la parola chiave principale che vuoi ottimizzare per questo contenuto. Verrà analizzata nei title, meta description e contenuto.', 'fp-seo-performance' ); ?>
			</span>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #3b82f6;">🎯 Medio impatto (+8%)</strong> - Keyword principale che guida l'analisi SEO. Usala nel title, description e contenuto.
			</p>
		</div>
		
		<!-- Secondary Keywords -->
		<div style="position: relative;">
			<label for="fp-seo-secondary-keywords" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span style="font-size: 16px;">🔐</span>
					<?php esc_html_e( 'Secondary Keywords', 'fp-seo-performance' ); ?>
					<span style="display: inline-flex; padding: 2px 8px; background: #6b7280; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+5%</span>
				</span>
			</label>
			<input 
				type="text" 
				id="fp-seo-secondary-keywords" 
				name="fp_seo_secondary_keywords"
				value="<?php echo esc_attr( is_array( $secondary_keywords_value ) ? implode( ', ', $secondary_keywords_value ) : ( $secondary_keywords_value ?: '' ) ); ?>"
				placeholder="<?php esc_attr_e( 'es: plugin seo, guida ottimizzazione, wordpress performance (separate con virgola)', 'fp-seo-performance' ); ?>"
				aria-label="<?php esc_attr_e( 'Keyword Secondarie - Separate con virgola', 'fp-seo-performance' ); ?>"
				aria-describedby="fp-seo-secondary-keywords-hint"
				style="width: 100%; padding: 10px 14px; font-size: 13px; border: 2px solid #9ca3af; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
				data-fp-seo-secondary-keywords
			/>
			<span id="fp-seo-secondary-keywords-hint" class="screen-reader-text">
				<?php esc_html_e( 'Inserisci keyword secondarie separate da virgola. Aiutano l\'analisi a valutare la copertura semantica del contenuto.', 'fp-seo-performance' ); ?>
			</span>
			<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
				<strong style="color: #6b7280;">📊 Basso-Medio impatto (+5%)</strong> - Keyword correlate per copertura semantica. Separate con virgola.
			</p>
		</div>
		<?php
	}

	/**
	 * Render Analysis section.
	 *
	 * @param array $checks Analysis checks.
	 */
	private function render_analysis_section( array $checks ): void {
		// Count by status
		$status_counts = array(
			'fail' => 0,
			'warn' => 0,
			'pass' => 0,
		);
		foreach ( $checks as $check ) {
			$status = $check['status'] ?? 'pending';
			if ( isset( $status_counts[ $status ] ) ) {
				$status_counts[ $status ]++;
			}
		}
		?>
		<div class="fp-seo-performance-metabox__section">
			<h4 class="fp-seo-performance-metabox__section-heading">
				<span class="fp-seo-section-icon">📈</span>
				<?php esc_html_e( 'Analisi SEO', 'fp-seo-performance' ); ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<div class="fp-seo-performance-metabox__unified-analysis">
					<?php if ( ! empty( $checks ) ) : ?>
						<div class="fp-seo-performance-summary">
							<?php if ( $status_counts['fail'] > 0 ) : ?>
								<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--fail">
									❌ <?php echo esc_html( $status_counts['fail'] ); ?> <?php esc_html_e( 'Critico', 'fp-seo-performance' ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $status_counts['warn'] > 0 ) : ?>
								<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--warn">
									⚠️ <?php echo esc_html( $status_counts['warn'] ); ?> <?php esc_html_e( 'Attenzione', 'fp-seo-performance' ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $status_counts['pass'] > 0 ) : ?>
								<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--pass">
									✅ <?php echo esc_html( $status_counts['pass'] ); ?> <?php esc_html_e( 'Ottimo', 'fp-seo-performance' ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					
					<?php if ( empty( $checks ) ) : ?>
						<div class="fp-seo-performance-metabox__analysis-list--empty">
							✅ <?php esc_html_e( 'Ottimo! Tutti gli indicatori sono ottimali.', 'fp-seo-performance' ); ?>
						</div>
					<?php else : ?>
						<ul class="fp-seo-performance-metabox__analysis-list" data-fp-seo-analysis>
							<?php 
							$delay = 0;
							foreach ( $checks as $check ) : 
								$delay += 0.05; // 50ms delay tra ogni elemento
								$status = $check['status'] ?? 'pending';
								$icon = '';
								$status_text = '';
								
								switch ( $status ) {
									case 'fail':
										$icon = '🔴';
										$status_text = __( 'Critico', 'fp-seo-performance' );
										break;
									case 'warn':
										$icon = '🟡';
										$status_text = __( 'Attenzione', 'fp-seo-performance' );
										break;
									case 'pass':
										$icon = '🟢';
										$status_text = __( 'Ottimo', 'fp-seo-performance' );
										break;
									default:
										$icon = '⚪';
										$status_text = __( 'In attesa', 'fp-seo-performance' );
								}
							?>
								<li class="fp-seo-performance-analysis-item fp-seo-performance-analysis-item--<?php echo esc_attr( $status ); ?>" style="animation-delay: <?php echo esc_attr( $delay . 's' ); ?>" data-check-id="<?php echo esc_attr( $check['id'] ?? '' ); ?>">
									<div class="fp-seo-performance-analysis-item__header">
										<span class="fp-seo-performance-analysis-item__icon"><?php echo $icon; ?></span>
										<span class="fp-seo-performance-analysis-item__title"><?php echo esc_html( $check['label'] ?? '' ); ?></span>
										<span class="fp-seo-performance-analysis-item__status"><?php echo esc_html( $status_text ); ?></span>
										<?php if ( $status !== 'pass' ) : ?>
											<button type="button" class="fp-seo-help-toggle" title="<?php esc_attr_e( 'Mostra aiuto', 'fp-seo-performance' ); ?>" data-help-toggle>
												<span class="dashicons dashicons-editor-help"></span>
											</button>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $check['hint'] ) ) : ?>
										<div class="fp-seo-performance-analysis-item__description">
											<?php echo esc_html( $check['hint'] ); ?>
										</div>
									<?php endif; ?>
									
									<?php if ( $status !== 'pass' ) : ?>
										<div class="fp-seo-check-help" data-help-content style="display: none;">
											<div class="fp-seo-check-help__section">
												<h5 class="fp-seo-check-help__title">
													<span class="dashicons dashicons-lightbulb"></span>
													<?php esc_html_e( 'Perché è importante?', 'fp-seo-performance' ); ?>
												</h5>
												<p class="fp-seo-check-help__text">
													<?php echo esc_html( $this->check_help_text->get_importance( $check['id'] ?? '' ) ); ?>
												</p>
											</div>
											<div class="fp-seo-check-help__section">
												<h5 class="fp-seo-check-help__title">
													<span class="dashicons dashicons-admin-tools"></span>
													<?php esc_html_e( 'Come migliorare', 'fp-seo-performance' ); ?>
												</h5>
												<p class="fp-seo-check-help__text">
													<?php echo esc_html( $this->check_help_text->get_howto( $check['id'] ?? '' ) ); ?>
												</p>
											</div>
											<?php 
											$example = $this->check_help_text->get_example( $check['id'] ?? '' );
											if ( $example ) : 
											?>
												<div class="fp-seo-check-help__example">
													<strong><?php esc_html_e( '✅ Esempio:', 'fp-seo-performance' ); ?></strong>
													<code><?php echo esc_html( $example ); ?></code>
												</div>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render GSC metrics section.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_gsc_metrics( WP_Post $post ): void {
		// Verifica che Options sia disponibile
		if ( ! class_exists( 'FP\\SEO\\Utils\\Options', false ) ) {
			return;
		}

		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		// Verifica che GscData sia disponibile prima di istanziarlo
		if ( ! class_exists( 'FP\\SEO\\Integrations\\GscData', false ) ) {
			// Prova a caricare il file manualmente
			$gsc_data_file = __DIR__ . '/../Integrations/GscData.php';
			if ( file_exists( $gsc_data_file ) ) {
				require_once $gsc_data_file;
			} else {
				return;
			}
		}

		// Verifica anche che GscClient sia disponibile (dipendenza di GscData)
		if ( ! class_exists( 'FP\\SEO\\Integrations\\GscClient', false ) ) {
			$gsc_client_file = __DIR__ . '/../Integrations/GscClient.php';
			if ( file_exists( $gsc_client_file ) ) {
				require_once $gsc_client_file;
			} else {
				// Se GscClient non esiste, non possiamo usare GscData
				return;
			}
		}

		try {
		$gsc_data = new GscData();
		$metrics  = $gsc_data->get_post_metrics( $post->ID, 28 );
		} catch ( \Throwable $e ) {
			// Se GscData fallisce, logga ma continua senza mostrare la sezione
			Logger::error( 'FP SEO: Error loading GSC data', array(
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			) );
			return;
		}

		if ( ! $metrics || ! is_array( $metrics ) ) {
			return;
		}

		// Estrai valori con fallback sicuro per evitare undefined index
		$clicks      = $metrics['clicks'] ?? 0;
		$impressions = $metrics['impressions'] ?? 0;
		$ctr         = $metrics['ctr'] ?? 0.0;
		$position    = $metrics['position'] ?? 0.0;
		$queries     = $metrics['queries'] ?? array();

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
						<?php echo esc_html( number_format_i18n( $clicks ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #2563eb;">
						<?php echo esc_html( number_format_i18n( $impressions ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( number_format_i18n( $ctr, 2 ) ); ?>%
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Position', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( number_format_i18n( $position, 1 ) ); ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $queries ) && is_array( $queries ) ) : ?>
				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-weight: 600; color: #374151;">
						🔍 <?php esc_html_e( 'Top Queries', 'fp-seo-performance' ); ?> (<?php echo esc_html( count( $queries ) ); ?>)
					</summary>
					<ul style="margin: 8px 0 0; padding: 0; list-style: none;">
						<?php foreach ( array_slice( $queries, 0, 5 ) as $query_data ) : ?>
							<?php
							if ( ! is_array( $query_data ) ) {
								continue;
							}
							$query_text   = $query_data['query'] ?? '';
							$query_clicks = $query_data['clicks'] ?? 0;
							$query_pos    = $query_data['position'] ?? 0.0;
							if ( empty( $query_text ) ) {
								continue;
							}
							?>
							<li style="padding: 6px 8px; background: #fff; border-radius: 4px; margin-bottom: 4px; font-size: 12px;">
								<strong><?php echo esc_html( $query_text ); ?></strong>
								<span style="color: #6b7280; margin-left: 10px;">
									<?php echo esc_html( number_format_i18n( $query_clicks ) ); ?> clicks, 
									pos <?php echo esc_html( number_format_i18n( $query_pos, 1 ) ); ?>
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
	 * Render AI section (Q&A Pairs, GEO, Freshness).
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_ai_section( WP_Post $post ): void {
		?>
		<!-- Section 2: AI OPTIMIZATION (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #f59e0b;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🤖</span>
					<?php esc_html_e( 'Q&A Pairs per AI', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);">
					<span style="font-size: 14px;">🚀</span>
					<?php esc_html_e( 'Impatto: +18%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b;">
					<strong style="color: #d97706;">🤖 Alto impatto (+18%)</strong> - Le Q&A aiutano ChatGPT, Gemini e Perplexity a citare i tuoi contenuti. Essenziale per AI Overview di Google.
				</p>
				<?php
				// Integra il contenuto Q&A Pairs
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$qa_metabox = $container->get( \FP\SEO\Admin\QAMetaBox::class );
								if ( $qa_metabox ) {
									$qa_metabox->render( $post );
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'QAMetaBox not available', array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					Logger::debug( 'QAMetaBox error', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>

		<!-- GEO Claims - Integrated Section (solo se GEO abilitato) -->
		<?php
		$geo_options = Options::get();
		if ( ! empty( $geo_options['geo']['enabled'] ) ) :
		?>
		<div class="fp-seo-performance-metabox__section">
			<h4 class="fp-seo-performance-metabox__section-heading">
				<span class="fp-seo-section-icon">🗺️</span>
				<?php esc_html_e( 'GEO Claims', 'fp-seo-performance' ); ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php
				try {
					$geo_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\GeoMetaBox::class );
					if ( $geo_metabox ) {
						$geo_metabox->render( $post );
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'GeoMetaBox not available', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Freshness & Temporal Signals - Integrated Section -->
		<div class="fp-seo-performance-metabox__section">
			<h4 class="fp-seo-performance-metabox__section-heading">
				<span class="fp-seo-section-icon">📅</span>
				<?php esc_html_e( 'Freshness & Temporal Signals', 'fp-seo-performance' ); ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php
				try {
					$freshness_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\FreshnessMetaBox::class );
					if ( $freshness_metabox ) {
						$freshness_metabox->render( $post );
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'FreshnessMetaBox not available', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Social Media section.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_social_section( WP_Post $post ): void {
		?>
		<!-- Section 3: SOCIAL MEDIA (Medium Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #8b5cf6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📱</span>
					<?php esc_html_e( 'Social Media Preview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(139, 92, 246, 0.2);">
					<span style="font-size: 14px;">📊</span>
					<?php esc_html_e( 'Impatto: +12%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #f5f3ff; border-radius: 6px; border-left: 3px solid #8b5cf6;">
					<strong style="color: #7c3aed;">📱 Medio impatto (+12%)</strong> - Ottimizza title, description e immagini per Facebook, Twitter, LinkedIn e Pinterest. Aumenta condivisioni e traffico social.
				</p>
				<?php
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$social_metabox = $container->get( \FP\SEO\Social\ImprovedSocialMediaManager::class );
								if ( $social_metabox && method_exists( $social_metabox, 'render_improved_social_metabox' ) ) {
									$social_metabox->render_improved_social_metabox( $post );
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'Social metabox not available', array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					Logger::debug( 'Social metabox error', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Internal Links section.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_internal_links_section( WP_Post $post ): void {
		?>
		<!-- Section 4: INTERNAL LINKS (Medium-Low Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #06b6d4;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🔗</span>
					<?php esc_html_e( 'Internal Link Suggestions', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);">
					<span style="font-size: 14px;">🔗</span>
					<?php esc_html_e( 'Impatto: +7%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #ecfeff; border-radius: 6px; border-left: 3px solid #06b6d4;">
					<strong style="color: #0891b2;">🔗 Medio-Basso impatto (+7%)</strong> - Link interni distribuiscono il PageRank e migliorano la navigazione. Collega contenuti correlati per SEO on-site.
				</p>
				<?php
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$links_manager = $container->get( \FP\SEO\Links\InternalLinkManager::class );
								if ( $links_manager && method_exists( $links_manager, 'render_links_metabox' ) ) {
									$links_manager->render_links_metabox( $post );
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'Internal Links not available', array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					Logger::debug( 'Internal Links error', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Schema sections (FAQ and HowTo).
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_schema_sections( WP_Post $post ): void {
		?>
		<!-- Section 5: FAQ SCHEMA (Very High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #f59e0b;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">❓</span>
					<?php esc_html_e( 'FAQ Schema - AI Overview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +20%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b;">
					<strong style="color: #d97706;">⚡ Molto Alto impatto (+20%)</strong> - Le FAQ aumentano visibilità Google AI Overview del 50%. Essenziali per ChatGPT, Gemini e Perplexity.
				</p>
				<?php
				// Integra il rendering FAQ
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$schema_metaboxes = $container->get( \FP\SEO\Editor\SchemaMetaboxes::class );
								if ( $schema_metaboxes && method_exists( $schema_metaboxes, 'render_faq_metabox' ) ) {
									$schema_metaboxes->render_faq_metabox( $post );
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'FAQ Schema not available', array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					Logger::debug( 'FAQ Schema error', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>

		<!-- Section 6: HOWTO SCHEMA (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #3b82f6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📖</span>
					<?php esc_html_e( 'HowTo Schema - Guide', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +15%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #eff6ff; border-radius: 6px; border-left: 3px solid #3b82f6;">
					<strong style="color: #2563eb;">⚡ Alto impatto (+15%)</strong> - Guide con HowTo Schema mostrano step nei risultati Google con rich snippets visuali. Ottimale per tutorial e guide.
				</p>
				<?php
				// Integra il rendering HowTo
				try {
					if ( class_exists( '\FP\SEO\Infrastructure\Plugin' ) ) {
						$plugin = \FP\SEO\Infrastructure\Plugin::instance();
						if ( $plugin && method_exists( $plugin, 'get_container' ) ) {
							$container = $plugin->get_container();
							if ( $container ) {
								$schema_metaboxes = $container->get( \FP\SEO\Editor\SchemaMetaboxes::class );
								if ( $schema_metaboxes && method_exists( $schema_metaboxes, 'render_howto_metabox' ) ) {
									$schema_metaboxes->render_howto_metabox( $post );
								}
							}
						}
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'HowTo Schema not available', array( 'error' => $e->getMessage() ) );
				} catch ( \Throwable $e ) {
					Logger::debug( 'HowTo Schema error', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Images Management section.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_images_section( WP_Post $post ): void {
		// CRITICAL: Do NOT extract images during initial rendering
		// Images will be loaded lazily via AJAX to avoid any interference
		Logger::info( 'FP SEO: render_images_section called (lazy-loaded)', array(
			'post_id' => $post->ID,
			'has_content' => ! empty( $post->post_content ),
		) );
		
		// Get AJAX URL and nonce for lazy loading
		$ajax_url = admin_url( 'admin-ajax.php' );
		// Use the nonce action string directly (cannot access private constant)
		$nonce = wp_create_nonce( 'fp_seo_performance_meta' );
		$post_id = $post->ID;
		
		?>
		<!-- Section: Images Optimization -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #8b5cf6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🖼️</span>
					<?php esc_html_e( 'Images Optimization', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(139, 92, 246, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impatto: +15%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #faf5ff; border-radius: 6px; border-left: 3px solid #8b5cf6;">
					<strong style="color: #7c3aed;">💡 Ottimizza le immagini per migliorare l'accessibilità e il SEO</strong><br>
					Aggiungi alt text descrittivi, titoli e descrizioni per tutte le immagini presenti nel contenuto. Questo migliora l'accessibilità e aiuta Google a comprendere meglio le tue immagini.
				</p>
				
				<!-- Lazy-loaded images container -->
				<div id="fp-seo-images-container-<?php echo esc_attr( $post_id ); ?>" 
					 class="fp-seo-images-container"
					 data-post-id="<?php echo esc_attr( $post_id ); ?>"
					 data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
					 data-nonce="<?php echo esc_attr( $nonce ); ?>"
					 style="min-height: 100px;">
					<div style="padding: 24px; text-align: center; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;">
						<div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #e5e7eb; border-top-color: #8b5cf6; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 12px;"></div>
						<p style="margin: 0; color: #6b7280; font-size: 14px;">
							<?php esc_html_e( 'Caricamento immagini...', 'fp-seo-performance' ); ?>
						</p>
						<p style="margin: 8px 0 0; color: #9ca3af; font-size: 12px;">
							<?php esc_html_e( 'Le immagini vengono estratte in modo sicuro senza interferenze.', 'fp-seo-performance' ); ?>
						</p>
					</div>
				</div>
				
				<!-- Images list will be populated here via AJAX -->
				<div class="fp-seo-images-list" id="fp-seo-images-list-<?php echo esc_attr( $post_id ); ?>" style="display: none; grid; gap: 16px;">
					<!-- Content will be loaded via AJAX -->
				</div>
				
				<!-- Template for rendering images (used by render_images_section_content) -->
				<script type="text/template" id="fp-seo-images-template">
					<!-- This template is used by render_images_section_content when called via AJAX -->
				</script>
			</div>
		</div>
		
		<style>
		.fp-seo-image-item:hover {
			border-color: #8b5cf6 !important;
			box-shadow: 0 2px 8px rgba(139, 92, 246, 0.1);
		}
		.fp-seo-image-item input:focus,
		.fp-seo-image-item textarea:focus {
			outline: none;
			border-color: #8b5cf6 !important;
			box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
		}
		@keyframes spin {
			to { transform: rotate(360deg); }
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Lazy-load images via AJAX (completely non-interfering)
			(function() {
				const containers = document.querySelectorAll('.fp-seo-images-container');
				containers.forEach(function(container) {
					const postId = container.dataset.postId;
					const ajaxUrl = container.dataset.ajaxUrl;
					const nonce = container.dataset.nonce;
					const listContainer = document.getElementById('fp-seo-images-list-' + postId);
					
					if (!postId || !ajaxUrl || !nonce) {
						return;
					}
					
					// Load images via AJAX
					jQuery.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'fp_seo_extract_images',
							post_id: postId,
							fp_seo_performance_nonce: nonce,
							force_refresh: false
						},
						success: function(response) {
							if (response.success && response.data && response.data.images && response.data.images.length > 0) {
								// Hide loading indicator
								container.style.display = 'none';
								
								// Show images list and reload section content
								if (listContainer) {
									jQuery.ajax({
										url: ajaxUrl,
										type: 'POST',
										data: {
											action: 'fp_seo_reload_images_section',
											post_id: postId,
											fp_seo_performance_nonce: nonce
										},
										success: function(reloadResponse) {
											if (reloadResponse.success && reloadResponse.data && reloadResponse.data.html) {
												listContainer.innerHTML = reloadResponse.data.html;
												listContainer.style.display = 'grid';
											} else {
												container.innerHTML = '<div style="padding: 24px; text-align: center; color: #6b7280;">Nessuna immagine trovata.</div>';
											}
										},
										error: function() {
											container.innerHTML = '<div style="padding: 24px; text-align: center; color: #ef4444;">Errore nel caricamento delle immagini.</div>';
										}
									});
								}
							} else {
								container.innerHTML = '<div style="padding: 24px; text-align: center; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;"><p style="margin: 0; color: #6b7280; font-size: 14px;">Nessuna immagine trovata nel contenuto.</p><p style="margin: 8px 0 0; color: #9ca3af; font-size: 12px;">Aggiungi immagini al contenuto per ottimizzarle qui.</p></div>';
							}
						},
						error: function() {
							container.innerHTML = '<div style="padding: 24px; text-align: center; color: #ef4444;">Errore nel caricamento delle immagini.</div>';
						}
					});
				});
			})();
			
			// Initialize character counters on page load
			$('[id*="-alt"]').each(function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				if (fieldId && fieldId.includes('-alt') && !fieldId.includes('-count')) {
					const index = fieldId.replace('fp-seo-image-', '').replace('-alt', '');
					const length = $field.val().length;
					$('#fp-seo-image-' + index + '-alt-count').text(length + '/125');
				}
			});
			
			$('[id*="-description"]').each(function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				if (fieldId && fieldId.includes('-description') && !fieldId.includes('-count')) {
					const index = fieldId.replace('fp-seo-image-', '').replace('-description', '');
					const length = $field.val().length;
					$('#fp-seo-image-' + index + '-description-count').text(length + '/500');
				}
			});
			
			// Character counters on input
			$('[id*="-alt"], [id*="-description"]').on('input', function() {
				const $field = $(this);
				const fieldId = $field.attr('id');
				const value = $field.val();
				const length = value.length;
				
				if (fieldId.includes('-alt') && !fieldId.includes('-count')) {
					// Alt text counter
					const index = fieldId.replace('fp-seo-image-', '').replace('-alt', '');
					$('#fp-seo-image-' + index + '-alt-count').text(length + '/125');
				} else if (fieldId.includes('-description') && !fieldId.includes('-count')) {
					// Description counter
					const index = fieldId.replace('fp-seo-image-', '').replace('-description', '');
					$('#fp-seo-image-' + index + '-description-count').text(length + '/500');
				}
			});
			
			// Save images data
			$('#fp-seo-save-images').on('click', function() {
				const $btn = $(this);
				const $status = $('#fp-seo-images-save-status');
				const postId = <?php echo (int) $post->ID; ?>;
				
				// Collect all image data
				const imagesData = {};
				$('.fp-seo-image-item').each(function() {
					const $item = $(this);
					const index = $item.data('image-index');
					const src = $item.data('image-src');
					
					if (src) {
						imagesData[src] = {
							alt: $item.find('[name*="[alt]"]').val() || '',
							title: $item.find('[name*="[title]"]').val() || '',
							description: $item.find('[name*="[description]"]').val() || ''
						};
					}
				});
				
				$btn.prop('disabled', true).text('<?php esc_html_e( 'Salvataggio...', 'fp-seo-performance' ); ?>');
				$status.hide();
				
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_save_images_data',
						post_id: postId,
						images_data: imagesData,
						fp_seo_performance_nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_performance_meta' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$status.show().fadeOut(3000);
							$btn.prop('disabled', false).text('<?php esc_html_e( '💾 Salva Modifiche Immagini', 'fp-seo-performance' ); ?>');
						} else {
							alert('<?php esc_html_e( 'Errore durante il salvataggio. Riprova.', 'fp-seo-performance' ); ?>');
							$btn.prop('disabled', false).text('<?php esc_html_e( '💾 Salva Modifiche Immagini', 'fp-seo-performance' ); ?>');
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Errore durante il salvataggio. Riprova.', 'fp-seo-performance' ); ?>');
						$btn.prop('disabled', false).text('<?php esc_html_e( '💾 Salva Modifiche Immagini', 'fp-seo-performance' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Legacy method to render image items (used by render_images_section_content).
	 * 
	 * This is kept separate to avoid interference with lazy loading.
	 *
	 * @param WP_Post $post Post object.
	 * @param array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}> $images Images array.
	 * @return void
	 */
	private function render_image_items_legacy( WP_Post $post, array $images ): void {
		if ( empty( $images ) ) {
			return;
		}
		
		// Get featured image ID
		$featured_thumbnail_id = get_post_thumbnail_id( $post->ID );
		
		foreach ( $images as $index => $image ) :
			if ( empty( $image['src'] ) ) {
				continue;
			}
			
			$is_featured = false;
			if ( $featured_thumbnail_id && ! empty( $image['attachment_id'] ) ) {
				$is_featured = ( (int) $image['attachment_id'] === (int) $featured_thumbnail_id );
			}
			
			$preview_url = $image['src'];
			if ( strpos( $preview_url, 'http' ) !== 0 ) {
				if ( strpos( $preview_url, '/' ) === 0 ) {
					$preview_url = site_url( $preview_url );
				} else {
					$preview_url = content_url( $preview_url );
				}
			}
			?>
			<div class="fp-seo-image-item <?php echo $is_featured ? 'fp-seo-featured-image' : ''; ?>" 
				 data-image-index="<?php echo esc_attr( $index ); ?>"
				 data-image-src="<?php echo esc_attr( $image['src'] ); ?>"
				 data-is-featured="<?php echo $is_featured ? '1' : '0'; ?>"
				 style="background: #fff; border: <?php echo $is_featured ? '2px solid #10b981' : '1px solid #e5e7eb'; ?>; border-radius: 8px; padding: 16px; transition: all 0.2s ease; <?php echo $is_featured ? 'box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15);' : ''; ?>">
				
				<div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; align-items: start;">
					<!-- Image Preview -->
					<div style="position: relative;">
						<img src="<?php echo esc_url( $preview_url ); ?>" 
							 alt="<?php echo esc_attr( $image['alt'] ?? '' ); ?>"
							 style="width: 100%; height: auto; border-radius: 6px; border: 1px solid #e5e7eb; object-fit: cover; max-height: 120px; min-height: 80px; background: #f3f4f6;"
							 loading="lazy">
						<?php if ( $is_featured ) : ?>
							<div style="position: absolute; top: 4px; left: 4px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);">
								⭐ <?php esc_html_e( 'In Evidenza', 'fp-seo-performance' ); ?>
							</div>
						<?php endif; ?>
						<div style="position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.7); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;">
							#<?php echo esc_html( $index + 1 ); ?>
						</div>
					</div>
					
					<!-- Image Fields -->
					<div style="display: grid; gap: 12px; flex: 1;">
						<!-- Image URL (read-only) -->
						<div>
							<label style="display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px;">
								<?php esc_html_e( 'URL Immagine', 'fp-seo-performance' ); ?>
							</label>
							<input type="text" 
								   value="<?php echo esc_attr( $image['src'] ); ?>" 
								   readonly
								   style="width: 100%; padding: 6px 10px; font-size: 11px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; font-family: monospace;">
						</div>
						
						<!-- Alt Text -->
						<div>
							<label for="fp-seo-image-<?php echo esc_attr( $index ); ?>-alt" 
								   style="display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px;">
								<?php esc_html_e( 'Alt Text', 'fp-seo-performance' ); ?>
								<span style="color: #ef4444;">*</span>
								<span class="fp-seo-tooltip-trigger" style="margin-left: 4px; cursor: help;" title="<?php esc_attr_e( 'Testo alternativo per accessibilità e SEO. Descrivi l\'immagine in modo chiaro e conciso.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" 
								   id="fp-seo-image-<?php echo esc_attr( $index ); ?>-alt" 
								   name="fp_seo_images[<?php echo esc_attr( $index ); ?>][alt]" 
								   value="<?php echo esc_attr( $image['alt'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'es: Foto del B&B Dimora Verde a Mentana', 'fp-seo-performance' ); ?>"
								   maxlength="125"
								   data-image-field="alt"
								   data-image-src="<?php echo esc_attr( $image['src'] ); ?>"
								   style="width: 100%; padding: 8px 12px; font-size: 13px; border: 2px solid #8b5cf6; border-radius: 6px; transition: all 0.2s ease;">
							<div style="display: flex; justify-content: space-between; margin-top: 4px;">
								<span style="font-size: 10px; color: #9ca3af;">
									<?php esc_html_e( 'Raccomandato: 5-15 parole descrittive', 'fp-seo-performance' ); ?>
								</span>
								<span id="fp-seo-image-<?php echo esc_attr( $index ); ?>-alt-count" 
									  style="font-size: 10px; font-weight: 600; color: #6b7280;">
									<?php echo esc_html( mb_strlen( $image['alt'] ?? '' ) ); ?>/125
								</span>
							</div>
						</div>
						
						<!-- Title -->
						<div>
							<label for="fp-seo-image-<?php echo esc_attr( $index ); ?>-title" 
								   style="display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px;">
								<?php esc_html_e( 'Title', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" style="margin-left: 4px; cursor: help;" title="<?php esc_attr_e( 'Titolo dell\'immagine (attributo title). Appare al passaggio del mouse.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" 
								   id="fp-seo-image-<?php echo esc_attr( $index ); ?>-title" 
								   name="fp_seo_images[<?php echo esc_attr( $index ); ?>][title]" 
								   value="<?php echo esc_attr( $image['title'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'es: Dimora Verde B&B - Vista esterna', 'fp-seo-performance' ); ?>"
								   maxlength="200"
								   data-image-field="title"
								   data-image-src="<?php echo esc_attr( $image['src'] ); ?>"
								   style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #d1d5db; border-radius: 6px; transition: all 0.2s ease;">
						</div>
						
						<!-- Description -->
						<div>
							<label for="fp-seo-image-<?php echo esc_attr( $index ); ?>-description" 
								   style="display: block; font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 4px;">
								<?php esc_html_e( 'Description', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" style="margin-left: 4px; cursor: help;" title="<?php esc_attr_e( 'Descrizione estesa dell\'immagine. Utile per contesto aggiuntivo.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<textarea id="fp-seo-image-<?php echo esc_attr( $index ); ?>-description" 
									  name="fp_seo_images[<?php echo esc_attr( $index ); ?>][description]" 
									  rows="2"
									  maxlength="500"
									  data-image-field="description"
									  data-image-src="<?php echo esc_attr( $image['src'] ); ?>"
									  placeholder="<?php esc_attr_e( 'es: Vista panoramica del B&B Dimora Verde situato a Mentana, vicino Roma. Struttura immersa nel verde con giardino e piscina.', 'fp-seo-performance' ); ?>"
									  style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical; transition: all 0.2s ease; font-family: inherit;"><?php echo esc_textarea( $image['description'] ?? '' ); ?></textarea>
							<div style="text-align: right; margin-top: 4px;">
								<span id="fp-seo-image-<?php echo esc_attr( $index ); ?>-description-count" 
									  style="font-size: 10px; font-weight: 600; color: #6b7280;">
									<?php echo esc_html( mb_strlen( $image['description'] ?? '' ) ); ?>/500
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		
		<!-- Save Button -->
		<div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
			<button type="button" 
					id="fp-seo-save-images" 
					class="button button-primary">
				<?php esc_html_e( '💾 Salva Modifiche Immagini', 'fp-seo-performance' ); ?>
			</button>
			<span id="fp-seo-images-save-status" style="margin-left: 12px; font-size: 12px; color: #10b981; display: none;">
				✅ <?php esc_html_e( 'Salvato!', 'fp-seo-performance' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * Extract all images from post content.
	 *
	 * @param WP_Post $post Current post.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_images_from_content( WP_Post $post ): array {
		// Verifica che DOMDocument sia disponibile
		if ( ! class_exists( 'DOMDocument' ) ) {
			Logger::error( 'FP SEO: DOMDocument class not available for image extraction' );
			return array();
		}

		// DISABLED: Cache clearing and post refresh interferes with WordPress's post object
		// WordPress manages its own cache, we should not clear it during rendering
		// This was causing WordPress to load the wrong post (auto-draft instead of homepage)
		
		// CRITICAL: Always retrieve content directly from database to ensure we have the latest version
		// WordPress's global $post or passed $post object might be stale, especially after AJAX calls or when editing
		// This is essential for finding images that were just added or modified
		$content = '';
		if ( ! empty( $post->ID ) ) {
			global $wpdb;
			$db_content = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
				$post->ID
			) );
			if ( ! empty( $db_content ) ) {
				$content = $db_content;
				// Update the post object so it's consistent for any subsequent operations
				$post->post_content = $db_content;
				Logger::info( 'FP SEO: extract_images_from_content - Retrieved content from database', array(
					'post_id' => $post->ID,
					'content_length' => strlen( $content ),
					'has_wpbakery' => strpos( $content, '[vc_' ) !== false,
					'has_img_tags' => strpos( $content, '<img' ) !== false,
					'img_count' => substr_count( $content, '<img' ),
				) );
			} else {
				// Fallback to post object if database query fails or returns empty
				$content = $post->post_content ?? '';
				Logger::warning( 'FP SEO: extract_images_from_content - Database content is empty, using post object', array(
					'post_id' => $post->ID,
					'post_type' => $post->post_type ?? 'unknown',
					'post_status' => $post->post_status ?? 'unknown',
					'post_object_content_length' => strlen( $content ),
				) );
			}
		} else {
			// Fallback if no post ID
			$content = $post->post_content ?? '';
		}
		$images = array();
		$seen_srcs = array(); // Avoid duplicates
		
		// Log sempre (non solo in debug) per tracciare il problema
		Logger::info( 'FP SEO: extract_images_from_content called', array(
				'post_id' => $post->ID,
				'content_length' => strlen( $content ),
			'content_preview' => substr( $content, 0, 500 ),
				'has_wpbakery' => strpos( $content, '[vc_' ) !== false,
				'has_img_tags' => strpos( $content, '<img' ) !== false,
			'has_img_shortcode' => strpos( $content, '[img' ) !== false || strpos( $content, '[image' ) !== false,
			'content_empty' => empty( $content ),
			) );
		
		// First, add featured image if available (most important for SEO)
		try {
		$featured_image = $this->get_featured_image_data( $post->ID );
		if ( ! empty( $featured_image ) && ! empty( $featured_image['src'] ) ) {
			$images[] = $featured_image;
			$seen_srcs[ $featured_image['src'] ] = true;
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'extract_images_from_content - Featured image added', array(
					'post_id' => $post->ID,
					'featured_image_src' => $featured_image['src'],
					'featured_image_id' => $featured_image['attachment_id'] ?? null,
				) );
			}
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error getting featured image data', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
			) );
			// Continua senza featured image
		}
		
		if ( empty( $content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'extract_images_from_content - Content is empty', array( 'post_id' => $post->ID ) );
			}
			return $images;
		}
		
		// Check for WPBakery shortcodes
		$has_wpbakery = strpos( $content, '[vc_' ) !== false 
			|| strpos( $content, '[vc_row' ) !== false
			|| strpos( $content, '[vc_column' ) !== false
			|| strpos( $content, 'vc_single_image' ) !== false
			|| strpos( $content, 'vc_gallery' ) !== false;
		
		// First, extract images from WPBakery shortcodes (from raw content)
		$wpbakery_images = array();
		try {
		$wpbakery_images = $this->extract_wpbakery_images( $content, $post->ID );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'extract_images_from_content - WPBakery images found', array(
				'post_id' => $post->ID,
				'count' => count( $wpbakery_images ),
				'has_wpbakery' => $has_wpbakery,
			) );
		}
		foreach ( $wpbakery_images as $image ) {
			if ( ! empty( $image['src'] ) && ! isset( $seen_srcs[ $image['src'] ] ) ) {
				$seen_srcs[ $image['src'] ] = true;
				$images[] = $image;
			}
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error extracting WPBakery images', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
			) );
			// Continua senza le immagini WPBakery
		}
		
		// Process content with do_shortcode to get rendered HTML (for images that might be rendered)
		// For WPBakery, we need to ensure shortcodes are fully processed
		$processed_content = do_shortcode( $content );
		
		Logger::info( 'FP SEO: extract_images_from_content - After do_shortcode', array(
			'post_id' => $post->ID,
			'original_length' => strlen( $content ),
			'processed_length' => strlen( $processed_content ),
			'has_img_in_processed' => strpos( $processed_content, '<img' ) !== false,
		) );
		
		// If WPBakery is active, try to process shortcodes more thoroughly
		if ( $has_wpbakery ) {
			// Try using the_content filter which processes all shortcodes including WPBakery
			$processed_content = apply_filters( 'the_content', $content );
			
			Logger::info( 'FP SEO: extract_images_from_content - After the_content filter', array(
				'post_id' => $post->ID,
				'processed_length' => strlen( $processed_content ),
				'has_img_in_processed' => strpos( $processed_content, '<img' ) !== false,
			) );
			
			// DISABLED: vc_do_shortcode() causes "Element must be mapped in system" error
			// WPBakery shortcodes are already processed by do_shortcode() and apply_filters('the_content')
			// We extract images directly from shortcode attributes in extract_wpbakery_images()
			// No need to call vc_do_shortcode() which requires full WPBakery initialization
		}
		
		// Then, extract images from HTML img tags (from both raw and processed content)
		// CRITICAL: Combine both raw and processed content to catch all images
		// Some images might only appear in processed content (WPBakery), others only in raw (direct HTML)
		$content_to_parse = $processed_content;
		if ( $processed_content !== $content ) {
			// Only combine if they're different to avoid duplicates
			$content_to_parse = $processed_content . "\n" . $content;
		}
		
		// CRITICAL: If we still have no content to parse, log a warning
		if ( empty( $content_to_parse ) ) {
			Logger::warning( 'FP SEO: extract_images_from_content - Content to parse is empty after processing', array(
				'post_id' => $post->ID,
				'original_content_length' => strlen( $content ),
				'processed_content_length' => strlen( $processed_content ),
			) );
		}
		
		Logger::info( 'FP SEO: extract_images_from_content - Content to parse prepared', array(
			'post_id' => $post->ID,
			'content_to_parse_length' => strlen( $content_to_parse ),
			'has_img_in_content_to_parse' => strpos( $content_to_parse, '<img' ) !== false,
			'img_count_in_content' => substr_count( $content_to_parse, '<img' ),
			'has_wpbakery_shortcodes' => strpos( $content_to_parse, '[vc_' ) !== false,
			'content_preview' => substr( $content_to_parse, 0, 200 ), // First 200 chars for debugging
		) );
		
		// Usa try/catch per gestire errori di parsing HTML
		try {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content_to_parse );
		libxml_clear_errors();
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error parsing HTML for image extraction', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
			) );
			// Ritorna le immagini già trovate (featured, wpbakery, ecc.)
			return $images;
		}
		
		$img_tags = $dom->getElementsByTagName( 'img' );
		$total_img_tags = $img_tags->length;
		
		Logger::info( 'FP SEO: extract_images_from_content - Found img tags in DOM', array(
			'post_id' => $post->ID,
			'total_img_tags' => $total_img_tags,
			'images_before_parsing' => count( $images ),
		) );
		
		foreach ( $img_tags as $index => $img ) {
			try {
			$src = $img->getAttribute( 'src' );
			
				// Skip if empty
				if ( empty( $src ) ) {
				continue;
			}
				
				// Normalizza URL PRIMA del controllo duplicati
				$original_src = $src;
				$normalized_src = $src;
				if ( strpos( $src, 'http' ) !== 0 ) {
					// URL relativo - converti in assoluto
					if ( strpos( $src, '/' ) === 0 ) {
						// URL assoluto relativo al dominio
						$normalized_src = site_url( $src );
					} else {
						// URL relativo al contenuto
						$normalized_src = content_url( $src );
					}
				}
				
				// Controlla duplicati con entrambi gli URL (originale e normalizzato)
				if ( isset( $seen_srcs[ $src ] ) || isset( $seen_srcs[ $normalized_src ] ) ) {
					continue;
				}
				
				// Usa l'URL normalizzato come principale
				$src = $normalized_src;
			
			$seen_srcs[ $src ] = true;
				$seen_srcs[ $original_src ] = true; // Anche l'originale per evitare duplicati
			
			// Get attachment ID from URL if it's a WordPress attachment
			$attachment_id = $this->get_attachment_id_from_url( $src );
				// Se non trovato con URL normalizzato, prova con l'originale
				if ( ! $attachment_id && $original_src !== $src ) {
					$attachment_id = $this->get_attachment_id_from_url( $original_src );
				}
			
			// Get existing alt, title
			$alt = $img->getAttribute( 'alt' ) ?: '';
			$title = $img->getAttribute( 'title' ) ?: '';
			
			// Get description from attachment if available
			$description = '';
			if ( $attachment_id ) {
				$attachment = get_post( $attachment_id );
				if ( $attachment ) {
					// Use attachment description or content
					$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
					
					// If alt is empty, try to get from attachment
					if ( empty( $alt ) ) {
						$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
					}
				}
			}
			
			// Get saved custom data from post meta
			$saved_images = get_post_meta( $post->ID, '_fp_seo_images_data', true );
			if ( is_array( $saved_images ) && isset( $saved_images[ $src ] ) ) {
				$saved = $saved_images[ $src ];
				$alt = $saved['alt'] ?? $alt;
				$title = $saved['title'] ?? $title;
				$description = $saved['description'] ?? $description;
			}
			
			$images[] = array(
				'src'           => $src,
				'alt'           => $alt,
				'title'         => $title,
				'description'   => $description,
				'attachment_id' => $attachment_id,
			);
			} catch ( \Throwable $e ) {
				// Log errore ma continua con le altre immagini
				Logger::error( 'FP SEO: Error processing image in extract_images_from_content', array(
					'error' => $e->getMessage(),
					'post_id' => $post->ID,
					'image_index' => $index,
				) );
				continue;
			}
		}
		
		Logger::info( 'FP SEO: extract_images_from_content - After parsing HTML img tags', array(
			'post_id' => $post->ID,
			'total_img_tags' => $total_img_tags,
			'images_after_parsing' => count( $images ),
			'images_added_from_html' => count( $images ) - ( count( $wpbakery_images ) + ( ! empty( $featured_image ) ? 1 : 0 ) ),
		) );
		
		// CRITICAL: Extract images from background-image CSS and data attributes in processed content
		// WPBakery often uses background-image CSS instead of <img> tags
		if ( ! empty( $processed_content ) && $processed_content !== $content ) {
			try {
				$dom_processed = new \DOMDocument();
				libxml_use_internal_errors( true );
				$dom_processed->loadHTML( '<?xml encoding="UTF-8">' . $processed_content );
				libxml_clear_errors();
				
				// Extract from style="background-image: url(...)"
				$xpath = new \DOMXPath( $dom_processed );
				$elements_with_bg = $xpath->query( '//*[@style]' );
				foreach ( $elements_with_bg as $element ) {
					$style = $element->getAttribute( 'style' );
					if ( preg_match_all( '/background-image\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i', $style, $bg_matches, PREG_SET_ORDER ) ) {
						foreach ( $bg_matches as $bg_match ) {
							$bg_url = $bg_match[1];
							if ( ! empty( $bg_url ) && ! isset( $seen_srcs[ $bg_url ] ) ) {
								// Normalize URL
								if ( strpos( $bg_url, 'http' ) !== 0 ) {
									if ( strpos( $bg_url, '/' ) === 0 ) {
										$bg_url = site_url( $bg_url );
									} else {
										$bg_url = content_url( $bg_url );
									}
								}
								
								$seen_srcs[ $bg_url ] = true;
								$attachment_id = $this->get_attachment_id_from_url( $bg_url );
								
								$alt = '';
								$title = '';
								$description = '';
								
								if ( $attachment_id ) {
									$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
									$attachment = get_post( $attachment_id );
									$title = $attachment ? $attachment->post_title : '';
									$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
								}
								
								$images[] = array(
									'src'           => $bg_url,
									'alt'           => $alt,
									'title'         => $title,
									'description'   => $description,
									'attachment_id' => $attachment_id,
								);
							}
						}
					}
				}
				
				// Extract from data-bg-image, data-image, data-background-image attributes
				$data_image_elements = $xpath->query( '//*[@data-bg-image or @data-image or @data-background-image]' );
				foreach ( $data_image_elements as $element ) {
					$data_attrs = array( 'data-bg-image', 'data-image', 'data-background-image' );
					foreach ( $data_attrs as $attr ) {
						$data_url = $element->getAttribute( $attr );
						if ( ! empty( $data_url ) && ! isset( $seen_srcs[ $data_url ] ) ) {
							// Normalize URL
							if ( strpos( $data_url, 'http' ) !== 0 ) {
								if ( strpos( $data_url, '/' ) === 0 ) {
									$data_url = site_url( $data_url );
								} else {
									$data_url = content_url( $data_url );
								}
							}
							
							$seen_srcs[ $data_url ] = true;
							$attachment_id = $this->get_attachment_id_from_url( $data_url );
							
							$alt = '';
							$title = '';
							$description = '';
							
							if ( $attachment_id ) {
								$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
								$attachment = get_post( $attachment_id );
								$title = $attachment ? $attachment->post_title : '';
								$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
							}
							
							$images[] = array(
								'src'           => $data_url,
								'alt'           => $alt,
								'title'         => $title,
								'description'   => $description,
								'attachment_id' => $attachment_id,
							);
						}
					}
				}
				
				Logger::info( 'FP SEO: extract_images_from_content - Extracted from background-image and data attributes', array(
					'post_id' => $post->ID,
					'images_after_bg_extraction' => count( $images ),
				) );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error extracting background images', array(
					'error' => $e->getMessage(),
					'post_id' => $post->ID,
				) );
			}
		}
		
		// CRITICAL: Also extract from background-image CSS using regex (fallback if DOMDocument fails)
		// This catches images in inline styles that might not be parsed correctly
		if ( strpos( $content_to_parse, 'background-image' ) !== false ) {
			preg_match_all( '/background-image\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i', $content_to_parse, $bg_regex_matches, PREG_SET_ORDER );
			foreach ( $bg_regex_matches as $bg_match ) {
				$bg_url = $bg_match[1] ?? '';
				if ( ! empty( $bg_url ) && ! isset( $seen_srcs[ $bg_url ] ) ) {
					// Normalize URL
					if ( strpos( $bg_url, 'http' ) !== 0 ) {
						if ( strpos( $bg_url, '/' ) === 0 ) {
							$bg_url = site_url( $bg_url );
						} else {
							$bg_url = content_url( $bg_url );
						}
					}
					
					$seen_srcs[ $bg_url ] = true;
					$attachment_id = $this->get_attachment_id_from_url( $bg_url );
					
					$alt = '';
					$title = '';
					$description = '';
					
					if ( $attachment_id ) {
						$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
						$attachment = get_post( $attachment_id );
						$title = $attachment ? $attachment->post_title : '';
						$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
					}
					
					$images[] = array(
						'src'           => $bg_url,
						'alt'           => $alt,
						'title'         => $title,
						'description'   => $description,
						'attachment_id' => $attachment_id,
					);
				}
			}
			
			Logger::info( 'FP SEO: extract_images_from_content - Extracted background-image from raw content via regex', array(
				'post_id' => $post->ID,
				'images_after_bg_regex' => count( $images ),
			) );
		}
		
		// Se non abbiamo trovato immagini con DOMDocument ma ci sono tag <img> nel contenuto,
		// prova a estrarle con regex come fallback
		if ( $total_img_tags === 0 && strpos( $content_to_parse, '<img' ) !== false ) {
			Logger::info( 'FP SEO: extract_images_from_content - DOMDocument found no images but content has <img> tags, trying regex extraction', array(
				'post_id' => $post->ID,
				'img_count_in_content' => substr_count( $content_to_parse, '<img' ),
			) );
			
			// Estrai immagini con regex come fallback
			preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content_to_parse, $regex_matches, PREG_SET_ORDER );
			
			if ( ! empty( $regex_matches ) ) {
				Logger::info( 'FP SEO: extract_images_from_content - Regex found images', array(
					'post_id' => $post->ID,
					'regex_matches_count' => count( $regex_matches ),
				) );
				
				foreach ( $regex_matches as $match ) {
					if ( empty( $match[1] ) ) {
						continue;
					}
					
					$src = $match[1];
					
					// Normalizza URL
					$original_src = $src;
					if ( strpos( $src, 'http' ) !== 0 ) {
						if ( strpos( $src, '/' ) === 0 ) {
							$src = site_url( $src );
						} else {
							$src = content_url( $src );
						}
					}
					
					// Controlla duplicati
					if ( isset( $seen_srcs[ $src ] ) || isset( $seen_srcs[ $original_src ] ) ) {
						continue;
					}
					
					$seen_srcs[ $src ] = true;
					$seen_srcs[ $original_src ] = true;
					
					// Get attachment ID
					$attachment_id = $this->get_attachment_id_from_url( $src );
					if ( ! $attachment_id && $original_src !== $src ) {
						$attachment_id = $this->get_attachment_id_from_url( $original_src );
					}
					
					// Estrai alt e title dal tag completo
					$full_tag = $match[0];
					$alt = '';
					$title = '';
					if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $full_tag, $alt_match ) ) {
						$alt = $alt_match[1];
					}
					if ( preg_match( '/title=["\']([^"\']*)["\']/i', $full_tag, $title_match ) ) {
						$title = $title_match[1];
					}
					
					// Get description from attachment
					$description = '';
					if ( $attachment_id ) {
						$attachment = get_post( $attachment_id );
						if ( $attachment ) {
							$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
							if ( empty( $alt ) ) {
								$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
							}
						}
					}
					
					// Get saved custom data
					$saved_images = get_post_meta( $post->ID, '_fp_seo_images_data', true );
					if ( is_array( $saved_images ) && isset( $saved_images[ $src ] ) ) {
						$saved = $saved_images[ $src ];
						$alt = $saved['alt'] ?? $alt;
						$title = $saved['title'] ?? $title;
						$description = $saved['description'] ?? $description;
					}
					
					$images[] = array(
						'src'           => $src,
						'alt'           => $alt,
						'title'         => $title,
						'description'   => $description,
						'attachment_id' => $attachment_id,
					);
				}
				
				Logger::info( 'FP SEO: extract_images_from_content - After regex extraction', array(
					'post_id' => $post->ID,
					'total_images_after_regex' => count( $images ),
					'images_added_by_regex' => count( $images ) - ( count( $wpbakery_images ) + ( ! empty( $featured_image ) ? 1 : 0 ) + $total_img_tags ),
				) );
			}
		}
		
		// CRITICAL: Extract images from post meta (WPBakery, Nectar Slider, and other plugins store images in post meta)
		// This is essential for pages that use page builders or sliders that don't store images in post_content
		try {
			// CRITICAL: Skip processing for auto-draft posts to prevent interference with homepage editing
			// Auto-drafts should not trigger Nectar Slider image extraction
			if ( $post->post_status === 'auto-draft' ) {
				Logger::info( 'FP SEO: extract_images_from_content - Skipping Nectar Slider extraction for auto-draft', array(
					'post_id' => $post->ID,
					'post_status' => $post->post_status,
				) );
			} else {
				// CRITICAL: Only check for Nectar Slider images if content contains slider references
				// OR if this is a published homepage (not auto-draft)
				$is_homepage_check = ( (int) $post->ID === (int) get_option( 'page_on_front' ) );
				$is_published_homepage = $is_homepage_check && $post->post_status === 'publish';
				$has_slider_references = strpos( $content, 'nectar' ) !== false || strpos( $content, 'slider' ) !== false;
				
				if ( $is_published_homepage || $has_slider_references ) {
				// Try to find all published Nectar Slider posts
				$all_sliders = get_posts( array(
					'post_type' => 'nectar_slider',
					'posts_per_page' => 100, // Get more sliders
					'post_status' => 'publish',
					'orderby' => 'date',
					'order' => 'DESC',
					'suppress_filters' => false,
				) );
				
				Logger::info( 'FP SEO: extract_images_from_content - Checking all sliders (fallback)', array(
					'post_id' => $post->ID,
					'is_homepage' => $is_homepage_check,
					'all_sliders_count' => count( $all_sliders ),
					'slider_ids' => array_map( function( $s ) { return $s->ID; }, $all_sliders ),
				) );
				
				foreach ( $all_sliders as $slider_post ) {
					$slider_image = get_post_meta( $slider_post->ID, '_nectar_slider_image', true );
					if ( ! empty( $slider_image ) ) {
						// Handle array values
						if ( is_array( $slider_image ) ) {
							if ( count( $slider_image ) === 1 ) {
								$slider_image = $slider_image[0];
							} else {
								foreach ( $slider_image as $img_value ) {
									if ( ! empty( $img_value ) ) {
										$this->process_meta_image_value( $img_value, '_nectar_slider_image_fallback_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
									}
								}
								continue;
							}
						}
						
						// Handle serialized arrays/objects
						if ( is_string( $slider_image ) && ( $slider_image[0] === 'a:' || $slider_image[0] === 'O:' ) ) {
							$unserialized = @unserialize( $slider_image );
							if ( is_array( $unserialized ) ) {
								$image_ids_or_urls = $this->extract_image_from_array( $unserialized );
								foreach ( $image_ids_or_urls as $img_value ) {
									$this->process_meta_image_value( $img_value, '_nectar_slider_image_fallback_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
								}
								continue;
							}
						}
						
						if ( ! empty( $slider_image ) ) {
							$this->process_meta_image_value( $slider_image, '_nectar_slider_image_fallback_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
						}
					}
				}
				} // End if ( $is_published_homepage || $has_slider_references )
			} // End else (not auto-draft)
			
			$all_meta = get_post_meta( $post->ID );
			$image_meta_keys = array();

			// Specific meta keys known to contain images (Nectar Slider, Salient theme, etc.)
			$known_image_meta_keys = array(
				'_nectar_slider_image',           // Nectar Slider image URL
				'_nectar_slider_preview_image',  // Nectar Slider preview
				'_nectar_header_bg',             // Salient header background
				'_thumbnail_id',                 // Featured image (alternative)
				'_wp_attachment_id',            // Generic attachment ID
			);

			// Collect all post meta keys that might contain images
			foreach ( $all_meta as $key => $value ) {
				// Check if it's a known image meta key
				if ( in_array( $key, $known_image_meta_keys, true ) ) {
					$image_meta_keys[ $key ] = $value;
					continue;
				}

				// Check if key suggests it contains image data
				if ( strpos( $key, 'image' ) !== false
					|| strpos( $key, 'bg' ) !== false
					|| strpos( $key, 'background' ) !== false
					|| strpos( $key, 'thumbnail' ) !== false
					|| strpos( $key, 'slide' ) !== false
					|| strpos( $key, 'header' ) !== false
					|| strpos( $key, 'preview' ) !== false ) {
					$image_meta_keys[ $key ] = $value;
				}
			}

			// CRITICAL: Also check for Nectar Slider shortcodes and extract images from associated slider posts
			// Pattern: [nectar_slider location="location_name"] or [nectar_slider location=""]
			// IMPORTANT: Only process if there's an actual shortcode OR if this is a published homepage (not auto-draft)
			$is_homepage = ( (int) $post->ID === (int) get_option( 'page_on_front' ) );
			$is_published_homepage = $is_homepage && $post->post_status === 'publish';
			$has_nectar_slider_shortcode = preg_match_all( '/\[nectar_slider[^\]]*\]/i', $content, $slider_shortcode_matches, PREG_SET_ORDER );
			
			// Only process if there's a shortcode OR if it's a published homepage (skip auto-drafts)
			if ( $has_nectar_slider_shortcode || $is_published_homepage ) {
				Logger::info( 'FP SEO: extract_images_from_content - Found Nectar Slider shortcode or published homepage', array(
					'post_id' => $post->ID,
					'has_shortcode' => $has_nectar_slider_shortcode,
					'is_homepage' => $is_homepage,
					'is_published_homepage' => $is_published_homepage,
					'post_status' => $post->post_status,
					'shortcode_count' => $has_nectar_slider_shortcode ? count( $slider_shortcode_matches ) : 0,
					'shortcodes' => $has_nectar_slider_shortcode ? $slider_shortcode_matches : array(),
				) );
				
				// If no shortcode matches but this is a published homepage, process as if there's an empty shortcode
				// BUT only if it's published, not auto-draft
				if ( ! $has_nectar_slider_shortcode && $is_published_homepage ) {
					$slider_shortcode_matches = array( array( 0 => '[nectar_slider]' ) );
				}
				
				foreach ( $slider_shortcode_matches as $shortcode_match ) {
					$shortcode_content = $shortcode_match[0];
					
					// Extract location if present
					$location = '';
					if ( preg_match( '/location\s*=\s*["\']([^"\']+)["\']/i', $shortcode_content, $location_match ) ) {
						$location = $location_match[1];
					}
					
					Logger::info( 'FP SEO: extract_images_from_content - Processing Nectar Slider shortcode', array(
						'post_id' => $post->ID,
						'shortcode_content' => $shortcode_content,
						'location' => $location ?: 'empty',
					) );
					
					$slider_posts = array();
					
					if ( ! empty( $location ) ) {
						// Find slider posts with this specific location
						$slider_posts = get_posts( array(
							'post_type' => 'nectar_slider',
							'posts_per_page' => -1,
							'tax_query' => array(
								array(
									'taxonomy' => 'slider-locations',
									'field' => 'slug',
									'terms' => $location,
								),
							),
						) );
						
						Logger::info( 'FP SEO: extract_images_from_content - Found sliders with location', array(
							'post_id' => $post->ID,
							'location' => $location,
							'slider_count' => count( $slider_posts ),
						) );
					} else {
						// Location is empty - check if this is the homepage first
						// Use multiple checks to ensure we identify the homepage correctly
						$page_on_front = (int) get_option( 'page_on_front' );
						$is_homepage = ( (int) $post->ID === $page_on_front );
						
						Logger::info( 'FP SEO: extract_images_from_content - Location is empty, checking homepage status', array(
							'post_id' => $post->ID,
							'page_on_front' => $page_on_front,
							'is_homepage' => $is_homepage ? 'yes' : 'no',
							'show_on_front' => get_option( 'show_on_front' ),
						) );
						
						// Additional check: if page_on_front is 0, it means posts page, but we still check for home location
						// Also check if show_on_front is 'page' to ensure we're using a static page
						if ( ! $is_homepage && get_option( 'show_on_front' ) === 'page' ) {
							// Double-check: maybe the post ID matches
							$is_homepage = ( (int) $post->ID === $page_on_front );
						}
						
						// Location is empty - try to find sliders with common location names first
						// Common location names: 'home', 'homepage', 'main', 'default', etc.
						$common_locations = array( 'home', 'homepage', 'main', 'default', 'header', 'hero' );
						$slider_posts = array();
						
						// CRITICAL: If this is the homepage, prioritize 'home' location
						// Also always check 'home' location when location is empty, as it's the most common case
						$home_sliders = get_posts( array(
							'post_type' => 'nectar_slider',
							'posts_per_page' => 20,
							'post_status' => 'publish',
							'tax_query' => array(
								array(
									'taxonomy' => 'slider-locations',
									'field' => 'slug',
									'terms' => 'home',
								),
							),
						) );
						if ( ! empty( $home_sliders ) ) {
							$slider_posts = array_merge( $slider_posts, $home_sliders );
							Logger::info( 'FP SEO: extract_images_from_content - Found home sliders (location empty)', array(
								'post_id' => $post->ID,
								'is_homepage' => $is_homepage ? 'yes' : 'no',
								'page_on_front' => $page_on_front,
								'home_sliders_count' => count( $home_sliders ),
								'home_slider_ids' => array_map( function( $s ) { return $s->ID; }, $home_sliders ),
							) );
						}
						
						// First, try to find sliders with common location names
						foreach ( $common_locations as $common_location ) {
							$location_sliders = get_posts( array(
								'post_type' => 'nectar_slider',
								'posts_per_page' => 10,
								'post_status' => 'publish',
								'tax_query' => array(
									array(
										'taxonomy' => 'slider-locations',
										'field' => 'slug',
										'terms' => $common_location,
									),
								),
							) );
							if ( ! empty( $location_sliders ) ) {
								$slider_posts = array_merge( $slider_posts, $location_sliders );
								Logger::info( 'FP SEO: extract_images_from_content - Found sliders with common location', array(
									'post_id' => $post->ID,
									'location' => $common_location,
									'slider_count' => count( $location_sliders ),
								) );
							}
						}
						
						// If no sliders found with common locations, get all published Nectar Slider posts
						// Only get a limited number to avoid performance issues
						if ( empty( $slider_posts ) ) {
							$slider_posts = get_posts( array(
								'post_type' => 'nectar_slider',
								'posts_per_page' => 50, // Increased from 20 to 50 to catch more sliders
								'post_status' => 'publish',
								'orderby' => 'date',
								'order' => 'DESC',
							) );
							
							Logger::info( 'FP SEO: extract_images_from_content - No sliders found with common locations, getting all published sliders', array(
								'post_id' => $post->ID,
								'all_sliders_count' => count( $slider_posts ),
							) );
						}
						
						// CRITICAL: If still no sliders found, try without taxonomy filter
						// Some sliders might not have location taxonomy set
						if ( empty( $slider_posts ) ) {
							$slider_posts = get_posts( array(
								'post_type' => 'nectar_slider',
								'posts_per_page' => 50,
								'post_status' => 'publish',
								'orderby' => 'date',
								'order' => 'DESC',
								'suppress_filters' => false,
							) );
							
							Logger::info( 'FP SEO: extract_images_from_content - No sliders found with taxonomy, getting all sliders without filter', array(
								'post_id' => $post->ID,
								'all_sliders_count_no_tax' => count( $slider_posts ),
							) );
						}
					}

					Logger::info( 'FP SEO: extract_images_from_content - Processing slider posts', array(
						'post_id' => $post->ID,
						'total_slider_posts' => count( $slider_posts ),
						'slider_post_ids' => array_map( function( $s ) { return $s->ID; }, $slider_posts ),
					) );

					foreach ( $slider_posts as $slider_post ) {
						// Get slider image from meta
						$slider_image = get_post_meta( $slider_post->ID, '_nectar_slider_image', true );
						
						Logger::info( 'FP SEO: extract_images_from_content - Checking slider post', array(
							'post_id' => $post->ID,
							'slider_post_id' => $slider_post->ID,
							'slider_image' => $slider_image ?: 'empty',
							'slider_image_type' => gettype( $slider_image ),
							'location' => $location ?: 'empty',
						) );
						
						// Handle array values (get_post_meta might return array with single element or multiple elements)
						if ( is_array( $slider_image ) ) {
							if ( count( $slider_image ) === 1 ) {
								$slider_image = $slider_image[0];
								Logger::info( 'FP SEO: extract_images_from_content - Unwrapped single-element array', array(
									'post_id' => $post->ID,
									'slider_post_id' => $slider_post->ID,
									'unwrapped_value' => $slider_image,
									'unwrapped_type' => gettype( $slider_image ),
								) );
							} else {
								// Multiple elements - process each one
								Logger::info( 'FP SEO: extract_images_from_content - Found array with multiple elements', array(
									'post_id' => $post->ID,
									'slider_post_id' => $slider_post->ID,
									'array_count' => count( $slider_image ),
								) );
								foreach ( $slider_image as $img_value ) {
									if ( ! empty( $img_value ) ) {
										$images_count_before = count( $images );
										$this->process_meta_image_value( $img_value, '_nectar_slider_image_from_slider_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
										$images_count_after = count( $images );
										Logger::info( 'FP SEO: extract_images_from_content - Processed image from array element', array(
											'post_id' => $post->ID,
											'slider_post_id' => $slider_post->ID,
											'img_value' => is_string( $img_value ) ? substr( $img_value, 0, 100 ) : $img_value,
											'images_count_before' => $images_count_before,
											'images_count_after' => $images_count_after,
											'image_added' => $images_count_after > $images_count_before,
										) );
									}
								}
								continue; // Skip to next slider post since we processed all array elements
							}
						}
						
						// Handle serialized arrays/objects
						if ( is_string( $slider_image ) && ( $slider_image[0] === 'a:' || $slider_image[0] === 'O:' ) ) {
							$unserialized = @unserialize( $slider_image );
							if ( is_array( $unserialized ) ) {
								// Extract image IDs/URLs from serialized arrays
								$image_ids_or_urls = $this->extract_image_from_array( $unserialized );
								foreach ( $image_ids_or_urls as $img_value ) {
									$images_count_before = count( $images );
									$this->process_meta_image_value( $img_value, '_nectar_slider_image_from_slider_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
									$images_count_after = count( $images );
									Logger::info( 'FP SEO: extract_images_from_content - Processed image from serialized array', array(
										'post_id' => $post->ID,
										'slider_post_id' => $slider_post->ID,
										'img_value' => $img_value,
										'images_count_before' => $images_count_before,
										'images_count_after' => $images_count_after,
										'image_added' => $images_count_after > $images_count_before,
									) );
								}
								continue; // Skip to next slider post
							}
						}
						
						if ( ! empty( $slider_image ) ) {
							// CRITICAL: Process the slider image directly instead of adding to image_meta_keys
							// This ensures it's processed immediately and not lost in the array processing logic
							$images_count_before = count( $images );
							$this->process_meta_image_value( $slider_image, '_nectar_slider_image_from_slider_' . $slider_post->ID, $post->ID, $images, $seen_srcs );
							$images_count_after = count( $images );
							
							Logger::info( 'FP SEO: extract_images_from_content - Processed Nectar Slider image directly', array(
								'post_id' => $post->ID,
								'slider_post_id' => $slider_post->ID,
								'slider_image' => $slider_image,
								'location' => $location ?: 'empty',
								'images_count_before' => $images_count_before,
								'images_count_after' => $images_count_after,
								'image_added' => $images_count_after > $images_count_before,
								'slider_image_type' => gettype( $slider_image ),
								'is_numeric' => is_numeric( $slider_image ),
								'is_url' => filter_var( $slider_image, FILTER_VALIDATE_URL ) !== false,
								'starts_with_http' => is_string( $slider_image ) && strpos( $slider_image, 'http' ) === 0,
								'starts_with_slash' => is_string( $slider_image ) && strpos( $slider_image, '/' ) === 0,
							) );
						} else {
							Logger::info( 'FP SEO: extract_images_from_content - Slider image is empty', array(
								'post_id' => $post->ID,
								'slider_post_id' => $slider_post->ID,
								'location' => $location ?: 'empty',
							) );
						}
					}
				}
			}
			
			if ( ! empty( $image_meta_keys ) ) {
				Logger::info( 'FP SEO: extract_images_from_content - Found image-related post meta', array(
					'post_id' => $post->ID,
					'meta_keys_count' => count( $image_meta_keys ),
					'meta_keys' => array_keys( $image_meta_keys ),
				) );
				
				foreach ( $image_meta_keys as $meta_key => $meta_value ) {
					// Handle array values (get_post_meta returns array with single element for single values)
					if ( is_array( $meta_value ) && count( $meta_value ) === 1 ) {
						$meta_value = $meta_value[0];
					}
					
					// Skip empty values
					if ( empty( $meta_value ) ) {
						continue;
					}
					
					// Handle serialized arrays/objects
					if ( is_string( $meta_value ) && ( $meta_value[0] === 'a:' || $meta_value[0] === 'O:' ) ) {
						$unserialized = @unserialize( $meta_value );
						if ( is_array( $unserialized ) ) {
							// Recursively extract image IDs/URLs from serialized arrays
							$meta_value = $this->extract_image_from_array( $unserialized );
							if ( empty( $meta_value ) ) {
								continue;
							}
						}
					}
					
					// Handle arrays directly (not serialized)
					if ( is_array( $meta_value ) ) {
						$image_ids_or_urls = $this->extract_image_from_array( $meta_value );
						foreach ( $image_ids_or_urls as $img_value ) {
							$this->process_meta_image_value( $img_value, $meta_key, $post->ID, $images, $seen_srcs );
						}
						continue;
					}
					
					// Process single value
					$this->process_meta_image_value( $meta_value, $meta_key, $post->ID, $images, $seen_srcs );
				}
				
				Logger::info( 'FP SEO: extract_images_from_content - After extracting from post meta', array(
					'post_id' => $post->ID,
					'total_images_after_meta' => count( $images ),
					'images_added_from_meta' => count( $images ) - ( count( $wpbakery_images ) + ( ! empty( $featured_image ) ? 1 : 0 ) + $total_img_tags ),
				) );
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error extracting images from post meta', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
				'trace' => $e->getTraceAsString(),
			) );
			// Continue without post meta images
		}
		
		// Log sempre (non solo in debug) per tracciare il problema
		Logger::info( 'FP SEO: extract_images_from_content - Final images count', array(
				'post_id' => $post->ID,
				'total_images' => count( $images ),
				'wpbakery_images' => count( $wpbakery_images ),
				'html_images' => count( $images ) - count( $wpbakery_images ),
				'has_wpbakery' => $has_wpbakery,
				'processed_content_length' => strlen( $processed_content ?? '' ),
			'images_srcs' => array_map( function( $img ) {
				return array(
					'src' => substr( $img['src'] ?? '', 0, 150 ),
					'has_attachment_id' => ! empty( $img['attachment_id'] ?? null ),
					'attachment_id' => $img['attachment_id'] ?? null,
				);
			}, array_slice( $images, 0, 20 ) ), // Prime 20 immagini per debug
		) );
		
		return $images;
	}
	
	/**
	 * Extract image IDs or URLs from an array (recursively).
	 *
	 * @param array $array Array to search.
	 * @return array Array of image IDs or URLs found.
	 */
	private function extract_image_from_array( array $array ): array {
		$results = array();
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				// Recursively search nested arrays
				$results = array_merge( $results, $this->extract_image_from_array( $value ) );
			} elseif ( is_numeric( $value ) && $value > 0 ) {
				// Check if it's an attachment ID
				$attachment = get_post( absint( $value ) );
				if ( $attachment && $attachment->post_type === 'attachment' ) {
					$mime_type = get_post_mime_type( $attachment->ID );
					if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
						$results[] = absint( $value );
					}
				}
			} elseif ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				// Check if it's an image URL
				// Accept URLs with image extensions OR URLs that might be image attachments (WordPress media URLs)
				if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?|$)/i', $value ) 
					|| strpos( $value, '/wp-content/uploads/' ) !== false
					|| strpos( $value, '/wp-includes/images/' ) !== false ) {
					$results[] = $value;
				}
			}
		}
		return $results;
	}
	
	/**
	 * Process a single meta image value (ID or URL) and add to images array.
	 *
	 * @param mixed  $value Image ID or URL.
	 * @param string $meta_key Meta key name.
	 * @param int    $post_id Post ID.
	 * @param array  $images Images array (passed by reference).
	 * @param array  $seen_srcs Seen sources array (passed by reference).
	 */
	private function process_meta_image_value( $value, string $meta_key, int $post_id, array &$images, array &$seen_srcs ): void {
		Logger::info( 'FP SEO: process_meta_image_value called', array(
			'post_id' => $post_id,
			'meta_key' => $meta_key,
			'value_type' => gettype( $value ),
			'value_preview' => is_string( $value ) ? substr( $value, 0, 100 ) : ( is_numeric( $value ) ? $value : 'non-string/non-numeric' ),
			'images_count_before' => count( $images ),
		) );
		
		// Skip empty values (including empty strings, null, false, 0, '0', etc.)
		if ( empty( $value ) ) {
			Logger::info( 'FP SEO: process_meta_image_value - Value is empty, skipping', array(
				'post_id' => $post_id,
				'meta_key' => $meta_key,
			) );
			return;
		}
		
		// Also skip if value is a string that's only whitespace
		if ( is_string( $value ) && trim( $value ) === '' ) {
			Logger::info( 'FP SEO: process_meta_image_value - Value is whitespace only, skipping', array(
				'post_id' => $post_id,
				'meta_key' => $meta_key,
			) );
			return;
		}
		
		// Check if value is an attachment ID (numeric)
		$attachment_id = is_numeric( $value ) ? absint( $value ) : 0;
		if ( $attachment_id > 0 ) {
			// Verify it's actually an attachment
			$attachment = get_post( $attachment_id );
			if ( $attachment && $attachment->post_type === 'attachment' ) {
				// Check if image type
				$mime_type = get_post_mime_type( $attachment_id );
				if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
					$image_url = wp_get_attachment_url( $attachment_id );
					if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
						$seen_srcs[ $image_url ] = true;
						
						// Get alt, title, description from attachment
						$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
						$title = $attachment->post_title ?: '';
						$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
						
						// Check for saved custom data
						$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
						if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
							$saved = $saved_images[ $image_url ];
							$alt = $saved['alt'] ?? $alt;
							$title = $saved['title'] ?? $title;
							$description = $saved['description'] ?? $description;
						}
						
						$images[] = array(
							'src'           => $image_url,
							'alt'           => $alt,
							'title'         => $title,
							'description'   => $description,
							'attachment_id' => $attachment_id,
						);
						
						Logger::info( 'FP SEO: extract_images_from_content - Added image from post meta', array(
							'post_id' => $post_id,
							'meta_key' => $meta_key,
							'attachment_id' => $attachment_id,
							'image_url' => $image_url,
						) );
					}
				}
			}
		} elseif ( is_string( $value ) ) {
			// Value is a string - could be a URL, file path, or numeric string
			// First check if it's numeric (could be an attachment ID as string)
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				// It's a numeric string, treat it as attachment ID
				$attachment_id = absint( $value );
				$attachment = get_post( $attachment_id );
				if ( $attachment && $attachment->post_type === 'attachment' ) {
					$mime_type = get_post_mime_type( $attachment_id );
					if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
						$image_url = wp_get_attachment_url( $attachment_id );
						if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
							$seen_srcs[ $image_url ] = true;
							
							$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
							$title = $attachment->post_title ?: '';
							$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
							
							// Check for saved custom data
							$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
							if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
								$saved = $saved_images[ $image_url ];
								$alt = $saved['alt'] ?? $alt;
								$title = $saved['title'] ?? $title;
								$description = $saved['description'] ?? $description;
							}
							
							$images[] = array(
								'src'           => $image_url,
								'alt'           => $alt,
								'title'         => $title,
								'description'   => $description,
								'attachment_id' => $attachment_id,
							);
							
							Logger::info( 'FP SEO: extract_images_from_content - Added image from numeric string (attachment ID)', array(
								'post_id' => $post_id,
								'meta_key' => $meta_key,
								'attachment_id' => $attachment_id,
								'image_url' => $image_url,
								'original_value' => $value,
							) );
							return; // Exit early since we processed it
						} else {
							Logger::info( 'FP SEO: process_meta_image_value - Numeric string is attachment but not image or URL not found', array(
								'post_id' => $post_id,
								'meta_key' => $meta_key,
								'attachment_id' => $attachment_id,
								'mime_type' => $mime_type ?? 'unknown',
								'image_url' => $image_url ?? 'not found',
							) );
						}
					} else {
						Logger::info( 'FP SEO: process_meta_image_value - Numeric string is not a valid attachment', array(
							'post_id' => $post_id,
							'meta_key' => $meta_key,
							'attachment_id' => $attachment_id,
							'attachment_exists' => $attachment ? 'yes' : 'no',
							'attachment_type' => $attachment ? $attachment->post_type : 'N/A',
						) );
					}
				} else {
					Logger::info( 'FP SEO: process_meta_image_value - Numeric string is not positive', array(
						'post_id' => $post_id,
						'meta_key' => $meta_key,
						'value' => $value,
						'int_value' => (int) $value,
					) );
				}
			}
			
			// Check if it looks like a URL or file path
			$is_url_like = (
				filter_var( $value, FILTER_VALIDATE_URL ) !== false
				|| strpos( $value, 'http' ) === 0
				|| strpos( $value, '/' ) === 0
				|| strpos( $value, 'wp-content' ) !== false
				|| strpos( $value, 'uploads' ) !== false
				|| preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)/i', $value )
			);
			
			if ( $is_url_like ) {
				// Value is a URL (or looks like one), try to find attachment ID from URL
				// Normalize URL first if needed
				$normalized_url = $value;
				if ( strpos( $normalized_url, 'http' ) !== 0 ) {
					if ( strpos( $normalized_url, '/' ) === 0 ) {
						$normalized_url = site_url( $normalized_url );
					} else {
						$normalized_url = content_url( $normalized_url );
					}
				}
			
			$attachment_id = $this->get_attachment_id_from_url( $normalized_url );
			if ( $attachment_id <= 0 ) {
				// Try with original URL too
				$attachment_id = $this->get_attachment_id_from_url( $value );
			}
			
			if ( $attachment_id > 0 ) {
				$image_url = wp_get_attachment_url( $attachment_id );
				if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
					$seen_srcs[ $image_url ] = true;
					
					$attachment = get_post( $attachment_id );
					$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
					$title = $attachment ? $attachment->post_title : '';
					$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
					
					// Check for saved custom data
					$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
					if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
						$saved = $saved_images[ $image_url ];
						$alt = $saved['alt'] ?? $alt;
						$title = $saved['title'] ?? $title;
						$description = $saved['description'] ?? $description;
					}
					
					$images[] = array(
						'src'           => $image_url,
						'alt'           => $alt,
						'title'         => $title,
						'description'   => $description,
						'attachment_id' => $attachment_id,
					);
					
					Logger::info( 'FP SEO: extract_images_from_content - Added image from post meta URL', array(
						'post_id' => $post_id,
						'meta_key' => $meta_key,
						'attachment_id' => $attachment_id,
						'image_url' => $image_url,
						'original_value' => $value,
					) );
				}
			} elseif ( ! isset( $seen_srcs[ $normalized_url ] ) && ! isset( $seen_srcs[ $value ] ) ) {
				// External URL or URL without attachment, add it anyway if it looks like an image
				// Check if it's likely an image URL
				// CRITICAL: For Nectar Slider and similar plugins, be more permissive
				// If the meta key suggests it's an image (contains 'image', 'slider', 'bg', etc.), trust it
				$meta_key_suggests_image = (
					strpos( $meta_key, 'image' ) !== false
					|| strpos( $meta_key, 'slider' ) !== false
					|| strpos( $meta_key, 'bg' ) !== false
					|| strpos( $meta_key, 'background' ) !== false
					|| strpos( $meta_key, 'thumbnail' ) !== false
				);
				
				$is_likely_image = (
					preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?|$)/i', $value )
					|| preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?|$)/i', $normalized_url )
					|| strpos( $value, '/wp-content/uploads/' ) !== false
					|| strpos( $normalized_url, '/wp-content/uploads/' ) !== false
					|| ( $meta_key_suggests_image && filter_var( $normalized_url, FILTER_VALIDATE_URL ) !== false )
				);
				
				if ( $is_likely_image ) {
					$seen_srcs[ $normalized_url ] = true;
					$seen_srcs[ $value ] = true;
					$images[] = array(
						'src'           => $normalized_url,
						'alt'           => '',
						'title'         => '',
						'description'   => '',
						'attachment_id' => null,
					);
					
					Logger::info( 'FP SEO: extract_images_from_content - Added external image from post meta', array(
						'post_id' => $post_id,
						'meta_key' => $meta_key,
						'image_url' => $normalized_url,
						'original_value' => $value,
						'is_likely_image' => true,
						'meta_key_suggests_image' => $meta_key_suggests_image,
					) );
				} else {
					Logger::info( 'FP SEO: process_meta_image_value - URL does not look like an image, skipping', array(
						'post_id' => $post_id,
						'meta_key' => $meta_key,
						'value' => substr( $value, 0, 200 ),
						'normalized_url' => substr( $normalized_url, 0, 200 ),
						'has_image_ext' => preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?|$)/i', $value ) || preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?|$)/i', $normalized_url ),
						'has_wp_content_uploads' => strpos( $value, '/wp-content/uploads/' ) !== false || strpos( $normalized_url, '/wp-content/uploads/' ) !== false,
						'meta_key_suggests_image' => $meta_key_suggests_image,
						'is_valid_url' => filter_var( $normalized_url, FILTER_VALIDATE_URL ) !== false,
					) );
				}
			} else {
				Logger::info( 'FP SEO: process_meta_image_value - URL already seen, skipping', array(
					'post_id' => $post_id,
					'meta_key' => $meta_key,
					'value' => substr( $value, 0, 200 ),
					'normalized_url' => substr( $normalized_url, 0, 200 ),
					'already_seen_normalized' => isset( $seen_srcs[ $normalized_url ] ),
					'already_seen_original' => isset( $seen_srcs[ $value ] ),
				) );
			}
			} else {
				// String value that doesn't look like a URL - log it for debugging
				Logger::info( 'FP SEO: process_meta_image_value - String value does not look like URL or attachment ID', array(
					'post_id' => $post_id,
					'meta_key' => $meta_key,
					'value' => substr( $value, 0, 200 ),
					'value_length' => strlen( $value ),
					'is_numeric_check' => is_numeric( $value ),
					'is_url_check' => filter_var( $value, FILTER_VALIDATE_URL ) !== false,
					'starts_with_http' => strpos( $value, 'http' ) === 0,
					'starts_with_slash' => strpos( $value, '/' ) === 0,
					'has_wp_content' => strpos( $value, 'wp-content' ) !== false,
					'has_uploads' => strpos( $value, 'uploads' ) !== false,
					'has_image_ext' => preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)/i', $value ),
				) );
			}
		} else {
			// Value is not a string and not numeric - log it
			Logger::info( 'FP SEO: process_meta_image_value - Value is not string or numeric', array(
				'post_id' => $post_id,
				'meta_key' => $meta_key,
				'value_type' => gettype( $value ),
				'value' => is_array( $value ) ? 'array(' . count( $value ) . ')' : ( is_object( $value ) ? get_class( $value ) : (string) $value ),
			) );
		}
		
		// Log final state
		$images_count_after = count( $images );
		Logger::info( 'FP SEO: process_meta_image_value completed', array(
			'post_id' => $post_id,
			'meta_key' => $meta_key,
			'images_count_before' => $images_count_before,
			'images_count_after' => $images_count_after,
			'image_added' => $images_count_after > $images_count_before,
		) );
	}

	/**
	 * Render only the content of the images section (without header/wrapper).
	 * Used for AJAX reloads.
	 *
	 * @param WP_Post $post Current post.
	 * @param array   $images Images array (optional, will extract if not provided).
	 */
	/**
	 * Render only the content of the images section (without header/wrapper).
	 * 
	 * This method uses the new isolated ImageExtractor for safe, non-interfering extraction.
	 *
	 * @param WP_Post $post Current post.
	 * @param array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}> $images Optional pre-extracted images. If empty, will extract using ImageExtractor.
	 * @return void
	 */
	public function render_images_section_content( WP_Post $post, array $images = array() ): void {
		// Use new isolated ImageExtractor (ONLY way to extract images - completely safe)
		if ( empty( $images ) ) {
			try {
				$extractor = new ImageExtractor();
				$force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'] === 'true';
				$images = $extractor->extract( $post, $force_refresh );
				
				Logger::info( 'FP SEO: render_images_section_content - Images extracted via ImageExtractor', array(
					'post_id' => $post->ID,
					'image_count' => count( $images ),
					'force_refresh' => $force_refresh,
				) );
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error extracting images using ImageExtractor', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'post_id' => $post->ID,
				) );
				$images = array();
			}
		}
		
		if ( empty( $images ) ) {
			?>
			<div style="padding: 24px; text-align: center; background: #f9fafb; border-radius: 8px; border: 2px dashed #e5e7eb;">
				<p style="margin: 0; color: #6b7280; font-size: 14px;">
					<?php esc_html_e( 'Nessuna immagine trovata nel contenuto.', 'fp-seo-performance' ); ?>
				</p>
				<p style="margin: 8px 0 0; color: #9ca3af; font-size: 12px;">
					<?php esc_html_e( 'Aggiungi immagini al contenuto per ottimizzarle qui.', 'fp-seo-performance' ); ?>
				</p>
			</div>
			<?php
			return;
		}
		
		// Use helper method to render images (avoids code duplication)
		$this->render_image_items_legacy( $post, $images );
		
		Logger::info( 'FP SEO: render_images_section_content - Rendering complete', array(
			'post_id' => $post->ID,
			'total_images' => count( $images ),
		) );
	}

	/**
	 * Extract images from WPBakery shortcodes.
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_wpbakery_images( string $content, int $post_id ): array {
		$images = array();
		
		if ( empty( $content ) ) {
			Logger::info( 'FP SEO: extract_wpbakery_images - Content is empty', array(
				'post_id' => $post_id,
			) );
			return $images;
		}
		
		// Check for WPBakery shortcodes - expanded check
		$has_wpbakery = strpos( $content, '[vc_' ) !== false 
			|| strpos( $content, '[vc_row' ) !== false
			|| strpos( $content, '[vc_column' ) !== false
			|| strpos( $content, 'vc_single_image' ) !== false
			|| strpos( $content, 'vc_gallery' ) !== false;
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'extract_wpbakery_images called', array(
				'post_id' => $post_id,
				'content_length' => strlen( $content ),
				'has_wpbakery' => $has_wpbakery,
				'content_preview' => substr( $content, 0, 200 ),
			) );
		}
		
		if ( ! $has_wpbakery ) {
			return $images;
		}
		
		// Track seen sources to avoid duplicates
		$seen_srcs = array();
		
		// First, try to process shortcodes to get rendered HTML and extract from there
		$rendered = do_shortcode( $content );
		
		// If WPBakery is active, try to use its own shortcode processor
		if ( class_exists( 'Vc_Manager' ) && function_exists( 'WPBakery_Visual_Composer' ) ) {
			// Try to get the rendered content more thoroughly
			$rendered = apply_filters( 'the_content', $content );
		}
		
		if ( $rendered !== $content && ! empty( $rendered ) ) {
			// Shortcodes were processed, extract images from rendered HTML
			$dom_rendered = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom_rendered->loadHTML( '<?xml encoding="UTF-8">' . $rendered );
			libxml_clear_errors();
			
			// Extract from <img> tags
			$rendered_img_tags = $dom_rendered->getElementsByTagName( 'img' );
			foreach ( $rendered_img_tags as $img ) {
				$src = $img->getAttribute( 'src' );
				if ( ! empty( $src ) && ! isset( $seen_srcs[ $src ] ) ) {
					$seen_srcs[ $src ] = true;
					
					// Normalize URL (handle relative URLs)
					if ( strpos( $src, 'http' ) !== 0 ) {
						$src = site_url( $src );
					}
					
					$attachment_id = $this->get_attachment_id_from_url( $src );
					$alt = $img->getAttribute( 'alt' ) ?: '';
					$title = $img->getAttribute( 'title' ) ?: '';
					
					if ( $attachment_id ) {
						$attachment = get_post( $attachment_id );
						$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
						if ( empty( $alt ) ) {
							$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
						}
						if ( empty( $title ) && $attachment ) {
							$title = $attachment->post_title ?: '';
						}
					} else {
						$description = '';
					}
					
					// Check for saved custom data
					$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
					if ( is_array( $saved_images ) && isset( $saved_images[ $src ] ) ) {
						$saved = $saved_images[ $src ];
						$alt = $saved['alt'] ?? $alt;
						$title = $saved['title'] ?? $title;
						$description = $saved['description'] ?? $description;
					}
					
					$images[] = array(
						'src'           => $src,
						'alt'           => $alt,
						'title'         => $title,
						'description'   => $description,
						'attachment_id' => $attachment_id,
					);
				}
			}
			
			// CRITICAL: Also extract images from CSS background-image properties
			// WPBakery often uses background-image instead of <img> tags
			// Pattern: background-image: url('...') or background-image: url("...")
			if ( preg_match_all( '/background-image\s*:\s*url\s*\(["\']?([^"\'()]+)["\']?\)/i', $rendered, $bg_matches, PREG_SET_ORDER ) ) {
				foreach ( $bg_matches as $bg_match ) {
					$bg_url = trim( $bg_match[1] );
					if ( ! empty( $bg_url ) && ! isset( $seen_srcs[ $bg_url ] ) ) {
						// Normalize URL
						if ( strpos( $bg_url, 'http' ) !== 0 ) {
							if ( strpos( $bg_url, '/' ) === 0 ) {
								$bg_url = site_url( $bg_url );
							} else {
								$bg_url = content_url( $bg_url );
							}
						}
						
						$seen_srcs[ $bg_url ] = true;
						$attachment_id = $this->get_attachment_id_from_url( $bg_url );
						
						$alt = '';
						$title = '';
						$description = '';
						
						if ( $attachment_id ) {
							$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
							$attachment = get_post( $attachment_id );
							$title = $attachment ? $attachment->post_title : '';
							$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
						}
						
						// Check for saved custom data
						$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
						if ( is_array( $saved_images ) && isset( $saved_images[ $bg_url ] ) ) {
							$saved = $saved_images[ $bg_url ];
							$alt = $saved['alt'] ?? $alt;
							$title = $saved['title'] ?? $title;
							$description = $saved['description'] ?? $description;
						}
						
						$images[] = array(
							'src'           => $bg_url,
							'alt'           => $alt,
							'title'         => $title,
							'description'   => $description,
							'attachment_id' => $attachment_id,
						);
					}
				}
			}
			
			// Also extract from data attributes (WPBakery might use data-bg-image, data-image, etc.)
			$xpath = new \DOMXPath( $dom_rendered );
			$data_image_elements = $xpath->query( '//*[@data-bg-image or @data-image or @data-background-image]' );
			foreach ( $data_image_elements as $element ) {
				$data_attrs = array( 'data-bg-image', 'data-image', 'data-background-image' );
				foreach ( $data_attrs as $attr ) {
					$data_url = $element->getAttribute( $attr );
					if ( ! empty( $data_url ) && ! isset( $seen_srcs[ $data_url ] ) ) {
						// Normalize URL
						if ( strpos( $data_url, 'http' ) !== 0 ) {
							if ( strpos( $data_url, '/' ) === 0 ) {
								$data_url = site_url( $data_url );
							} else {
								$data_url = content_url( $data_url );
							}
						}
						
						$seen_srcs[ $data_url ] = true;
						$attachment_id = $this->get_attachment_id_from_url( $data_url );
						
						$alt = '';
						$title = '';
						$description = '';
						
						if ( $attachment_id ) {
							$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
							$attachment = get_post( $attachment_id );
							$title = $attachment ? $attachment->post_title : '';
							$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
						}
						
						$images[] = array(
							'src'           => $data_url,
							'alt'           => $alt,
							'title'         => $title,
							'description'   => $description,
							'attachment_id' => $attachment_id,
						);
					}
				}
			}
		}
		
		// CRITICAL: Extract images from ALL WPBakery shortcodes, not just vc_single_image
		// WPBakery can use images in many ways:
		// - vc_single_image: image="123"
		// - vc_row/vc_column: bg_image="123" or background_image="123"
		// - vc_gallery: images="123,456,789"
		// - Other shortcodes: image="123", bg_image="123", etc.
		
		// Extract all image-related attributes from ALL WPBakery shortcodes
		// Look for: image="123", bg_image="123", background_image="123", images="123,456"
		// Pattern matches any WPBakery shortcode with image attributes
		$image_attr_patterns = array(
			'image',           // Standard image attribute
			'bg_image',        // Background image
			'background_image', // Alternative background image
			'images',          // Gallery images (comma-separated)
		);
		
		$matches = array();
		foreach ( $image_attr_patterns as $attr ) {
			// CRITICAL: Pattern must handle multi-line shortcodes
			// WPBakery shortcodes can span multiple lines, so we need to match across newlines
			// Pattern: [vc_* ... attr="value" ...] where attr can be image, bg_image, etc.
			// Use DOTALL flag (s) to make . match newlines, and use non-greedy matching
			$pattern = '/\[vc_\w+.*?' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\'].*?\]/is';
			if ( preg_match_all( $pattern, $content, $attr_matches, PREG_SET_ORDER ) ) {
				foreach ( $attr_matches as $match ) {
					// Extract just the shortcode name and the attribute value
					$shortcode_name = '';
					if ( preg_match( '/\[(vc_\w+)/i', $match[0], $name_match ) ) {
						$shortcode_name = $name_match[1];
					}
					$matches[] = array( 
						0 => $match[0], // Full shortcode
						1 => $match[1], // Attribute value
						'attr' => $attr,
						'shortcode_name' => $shortcode_name,
					);
				}
			}
		}
		
		// Also extract vc_single_image specifically (for backward compatibility and edge cases)
		// CRITICAL: Use DOTALL flag (s) to match across newlines
		$vc_single_image_patterns = array(
			'/\[vc_single_image.*?image\s*=\s*["\']([^"\']+)["\'].*?\]/is',
		);
		
		foreach ( $vc_single_image_patterns as $pattern ) {
			if ( preg_match_all( $pattern, $content, $pattern_matches, PREG_SET_ORDER ) ) {
				foreach ( $pattern_matches as $match ) {
					// Avoid duplicates
					$is_duplicate = false;
					foreach ( $matches as $existing ) {
						if ( ( $existing[0] ?? '' ) === ( $match[0] ?? '' ) && ( $existing[1] ?? '' ) === ( $match[1] ?? '' ) ) {
							$is_duplicate = true;
							break;
						}
					}
					if ( ! $is_duplicate ) {
						$matches[] = array( 
							0 => $match[0] ?? '', 
							1 => $match[1] ?? '', 
							'attr' => 'image',
							'shortcode_name' => 'vc_single_image',
						);
					}
				}
			}
		}
		
		if ( ! empty( $matches ) ) {
			Logger::info( 'FP SEO: extract_wpbakery_images - Found WPBakery shortcodes with images', array(
					'post_id' => $post_id,
					'count' => count( $matches ),
				'shortcodes_preview' => array_slice( array_map( function( $m ) {
					return array(
						'shortcode' => substr( $m[0] ?? '', 0, 100 ),
						'attr_value' => substr( $m[1] ?? '', 0, 50 ),
						'attr_name' => $m['attr'] ?? 'unknown',
					);
				}, $matches ), 0, 5 ),
			) );
		}
		
		foreach ( $matches as $match ) {
			$shortcode_content = $match[0];
			$image_param = $match[1] ?? '';
			$attr_name = $match['attr'] ?? 'image';
			
			// If image_param is empty, try to extract from shortcode attributes
			if ( empty( $image_param ) ) {
				// Try to extract from shortcode attributes based on attr_name
				$attr_pattern = '/' . preg_quote( $attr_name, '/' ) . '\s*=\s*["\']([^"\']+)["\']/i';
				if ( preg_match( $attr_pattern, $shortcode_content, $attr_match ) ) {
					$image_param = $attr_match[1];
				}
			}
			
			if ( empty( $image_param ) ) {
				continue;
			}
			
			// Handle different formats:
			// - Single ID: "123"
			// - ID with size: "123|full" or "123|medium"
			// - Comma-separated IDs: "123,456,789"
			// - URL: "https://..."
			
			// Check if it's comma-separated (for galleries)
			if ( strpos( $image_param, ',' ) !== false ) {
				$image_ids = array_map( 'intval', array_filter( explode( ',', $image_param ), 'is_numeric' ) );
				foreach ( $image_ids as $attachment_id ) {
			if ( $attachment_id > 0 ) {
						$this->add_wpbakery_image_to_list( $attachment_id, $post_id, $images, $seen_srcs );
					}
				}
				continue;
			}
			
			// Extract attachment ID (format can be "123" or "123|full")
			// Remove everything except digits
			$attachment_id = (int) preg_replace( '/[^\d]/', '', $image_param );
			
			if ( $attachment_id > 0 ) {
				$this->add_wpbakery_image_to_list( $attachment_id, $post_id, $images, $seen_srcs );
			} elseif ( filter_var( $image_param, FILTER_VALIDATE_URL ) ) {
				// It's a URL, try to find attachment ID from URL
				$attachment_id = $this->get_attachment_id_from_url( $image_param );
				if ( $attachment_id > 0 ) {
					$this->add_wpbakery_image_to_list( $attachment_id, $post_id, $images, $seen_srcs );
				} elseif ( ! isset( $seen_srcs[ $image_param ] ) ) {
					// External URL, add it anyway
					$seen_srcs[ $image_param ] = true;
					$images[] = array(
						'src'           => $image_param,
						'alt'           => '',
						'title'         => '',
						'description'   => '',
						'attachment_id' => null,
					);
				}
			}
		}
		
		// Extract vc_gallery shortcodes
		// Pattern: [vc_gallery images="123,456,789" ...]
		// CRITICAL: Use DOTALL flag (s) to match across newlines, and use non-greedy matching
		if ( preg_match_all( '/\[vc_gallery.*?images\s*=\s*["\']([^"\']+)["\'].*?\]/is', $content, $gallery_matches, PREG_SET_ORDER ) ) {
			foreach ( $gallery_matches as $match ) {
				$images_param = $match[1];
				
				// Split by comma and extract IDs
				$image_ids = array_map( 'intval', array_filter( explode( ',', $images_param ), 'is_numeric' ) );
				
				foreach ( $image_ids as $attachment_id ) {
					if ( $attachment_id > 0 ) {
						// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
						$image_url = wp_get_attachment_url( $attachment_id );
						if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
							$seen_srcs[ $image_url ] = true;
							// Get alt text from attachment
							$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
							
							// Get title from attachment
							$attachment = get_post( $attachment_id );
							$title = $attachment ? $attachment->post_title : '';
							$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
							
							// Check for saved custom data
							$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
							if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
								$saved = $saved_images[ $image_url ];
								$alt = $saved['alt'] ?? $alt;
								$title = $saved['title'] ?? $title;
								$description = $saved['description'] ?? $description;
							}
							
							$images[] = array(
								'src'           => $image_url,
								'alt'           => $alt,
								'title'         => $title,
								'description'   => $description,
								'attachment_id' => $attachment_id,
							);
						}
					}
				}
			}
		}
		
		// Extract images from other WPBakery shortcodes with image attributes
		// Pattern: [vc_row image_url="123" ...] or [vc_column image_1_url="123" image_2_url="456" ...]
		// CRITICAL: Use DOTALL flag (s) to match across newlines
		$other_image_attrs = array( 'image_url', 'image_1_url', 'image_2_url', 'image_3_url' );
		foreach ( $other_image_attrs as $attr ) {
			$pattern = '/\[vc_.*?' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\'].*?\]/is';
			if ( preg_match_all( $pattern, $content, $other_matches, PREG_SET_ORDER ) ) {
				foreach ( $other_matches as $match ) {
					// Avoid duplicates
					$is_duplicate = false;
					foreach ( $matches as $existing ) {
						if ( ( $existing[0] ?? '' ) === ( $match[0] ?? '' ) && ( $existing[1] ?? '' ) === ( $match[1] ?? '' ) ) {
							$is_duplicate = true;
							break;
						}
					}
					if ( ! $is_duplicate ) {
						$matches[] = array( 
							0 => $match[0] ?? '', 
							1 => $match[1] ?? '', 
							'attr' => $attr,
							'shortcode_name' => 'vc_other',
						);
					}
				}
			}
		}
		
		// Legacy extraction for backward compatibility (handles single-line shortcodes)
		$legacy_attrs = array( 'image_url', 'image_1_url', 'image_2_url', 'image_3_url', 'images' );
		foreach ( $legacy_attrs as $attr ) {
			if ( preg_match_all( '/\[vc_[^\]]*' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\'][^\]]*\]/i', $content, $legacy_matches, PREG_SET_ORDER ) ) {
				foreach ( $legacy_matches as $match ) {
					$image_param = $match[1];
					
					// Check if it's an attachment ID (numeric) or URL
					if ( is_numeric( $image_param ) ) {
						$attachment_id = (int) $image_param;
						if ( $attachment_id > 0 ) {
							// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
							$image_url = wp_get_attachment_url( $attachment_id );
							if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
								$seen_srcs[ $image_url ] = true;
								$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
								$attachment = get_post( $attachment_id );
								$title = $attachment ? $attachment->post_title : '';
								$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
								
								$images[] = array(
									'src'           => $image_url,
									'alt'           => $alt,
									'title'         => $title,
									'description'   => $description,
									'attachment_id' => $attachment_id,
								);
							}
						}
					} elseif ( filter_var( $image_param, FILTER_VALIDATE_URL ) ) {
						// It's a URL, use it directly
						if ( ! isset( $seen_srcs[ $image_param ] ) ) {
							$seen_srcs[ $image_param ] = true;
							$attachment_id = $this->get_attachment_id_from_url( $image_param );
							$alt = '';
							$title = '';
							$description = '';
							
							if ( $attachment_id ) {
								$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
								$attachment = get_post( $attachment_id );
								$title = $attachment ? $attachment->post_title : '';
								$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
							}
							
							$images[] = array(
								'src'           => $image_param,
								'alt'           => $alt,
								'title'         => $title,
								'description'   => $description,
								'attachment_id' => $attachment_id,
							);
						}
					} else {
						// Might be comma-separated IDs
						$image_ids = array_map( 'intval', array_filter( explode( ',', $image_param ), 'is_numeric' ) );
						foreach ( $image_ids as $attachment_id ) {
							if ( $attachment_id > 0 ) {
								// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
								$image_url = wp_get_attachment_url( $attachment_id );
								if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
									$seen_srcs[ $image_url ] = true;
									$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
									$attachment = get_post( $attachment_id );
									$title = $attachment ? $attachment->post_title : '';
									$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
									
									$images[] = array(
										'src'           => $image_url,
										'alt'           => $alt,
										'title'         => $title,
										'description'   => $description,
										'attachment_id' => $attachment_id,
									);
								}
							}
						}
					}
				}
			}
		}
		
		// Extract external images from vc_single_image with custom_src
		// Pattern: [vc_single_image source="external_link" custom_src="https://..." ...]
		if ( preg_match_all( '/\[vc_single_image[^\]]*source\s*=\s*["\']external_link["\'][^\]]*custom_src\s*=\s*["\']([^"\']+)["\'][^\]]*\]/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$image_url = esc_url_raw( $match[1] );
				if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
					$seen_srcs[ $image_url ] = true;
					$images[] = array(
						'src'           => $image_url,
						'alt'           => '',
						'title'         => '',
						'description'   => '',
						'attachment_id' => null,
					);
				}
			}
		}
		
		return $images;
	}

	/**
	 * Helper method to add a WPBakery image to the images list.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param int   $post_id Post ID.
	 * @param array $images Images array (passed by reference).
	 * @param array $seen_srcs Seen sources array (passed by reference).
	 */
	private function add_wpbakery_image_to_list( int $attachment_id, int $post_id, array &$images, array &$seen_srcs ): void {
		// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( $image_url && ! isset( $seen_srcs[ $image_url ] ) ) {
			$seen_srcs[ $image_url ] = true;
			// Get alt text from attachment
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
			
			// Get title from attachment
			$attachment = get_post( $attachment_id );
			$title = $attachment ? $attachment->post_title : '';
			$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
			
			// Check for saved custom data
			$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
			if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
				$saved = $saved_images[ $image_url ];
				$alt = $saved['alt'] ?? $alt;
				$title = $saved['title'] ?? $title;
				$description = $saved['description'] ?? $description;
			}
			
			$images[] = array(
				'src'           => $image_url,
				'alt'           => $alt,
				'title'         => $title,
				'description'   => $description,
				'attachment_id' => $attachment_id,
			);
		}
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
	 * Get featured image data for a post.
	 *
	 * Uses modern WordPress APIs to get featured image with all metadata.
	 *
	 * @param int $post_id Post ID.
	 * @return array{src: string, alt: string, title: string, description: string, attachment_id: int|null}|null Featured image data or null if not set.
	 */
	private function get_featured_image_data( int $post_id ): ?array {
		// Get featured image attachment ID using modern WordPress API
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		
		if ( ! $thumbnail_id || $thumbnail_id <= 0 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'get_featured_image_data - No thumbnail ID', array( 'post_id' => $post_id ) );
			}
			return null;
		}
		
		// Get image URL - usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
		$image_url = wp_get_attachment_url( $thumbnail_id );
		if ( ! $image_url ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'get_featured_image_data - No image URL', array( 
					'post_id' => $post_id,
					'thumbnail_id' => $thumbnail_id 
				) );
			}
			return null;
		}
		
		// Get attachment post for metadata
		$attachment = get_post( $thumbnail_id );
		
		// Get alt text from attachment meta
		$alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: '';
		
		// Get title from attachment post title
		$title = $attachment ? $attachment->post_title : '';
		
		// Get description from attachment content or excerpt
		$description = '';
		if ( $attachment ) {
			$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
		}
		
		// Check for saved custom data from post meta
		$saved_images = get_post_meta( $post_id, '_fp_seo_images_data', true );
		if ( is_array( $saved_images ) && isset( $saved_images[ $image_url ] ) ) {
			$saved = $saved_images[ $image_url ];
			$alt = $saved['alt'] ?? $alt;
			$title = $saved['title'] ?? $title;
			$description = $saved['description'] ?? $description;
		}
		
		return array(
			'src'           => $image_url,
			'alt'           => $alt,
			'title'         => $title,
			'description'   => $description,
			'attachment_id' => $thumbnail_id,
		);
	}
}

