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
		$this->check_help_text = null;
		
		try {
			// Verifica che la classe esista
			if ( ! class_exists( 'FP\SEO\Editor\CheckHelpText' ) ) {
				throw new \RuntimeException( 'CheckHelpText class not found. Verifica che il file src/Editor/CheckHelpText.php esista.' );
			}
			
			// Prova a istanziare
			$this->check_help_text = new \FP\SEO\Editor\CheckHelpText();
			
			// Verifica che l'istanza sia valida
			if ( ! is_object( $this->check_help_text ) || ! method_exists( $this->check_help_text, 'get_importance' ) ) {
				throw new \RuntimeException( 'CheckHelpText instance invalid or missing methods' );
			}
		} catch ( \Throwable $e ) {
			// Log error ma continua - crea un oggetto fallback
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( '\FP\SEO\Utils\Logger' ) ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize CheckHelpText', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			}
			
			// Create a dummy object to prevent null reference errors
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
		
		// Assicurati che check_help_text non sia mai null
		if ( ! $this->check_help_text ) {
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

	/**
	 * Render the complete metabox.
	 *
	 * @param WP_Post $post Current post.
	 * @param array   $analysis Analysis data.
	 * @param bool    $excluded Whether post is excluded.
	 */
	public function render( WP_Post $post, array $analysis, bool $excluded ): void {
		// IMPORTANTE: Pulisci la cache PRIMA di iniziare il rendering
		// Questo assicura che tutti i valori vengano letti correttamente
		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'post_meta' );
		wp_cache_delete( $post->ID, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
		if ( function_exists( 'update_post_meta_cache' ) ) {
			update_post_meta_cache( array( $post->ID ) );
		}
		
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
		?>
		<div class="fp-seo-performance-metabox" data-fp-seo-metabox>
			<?php $this->render_header( $excluded, $score_value, $score_status ); ?>
			<?php $this->render_serp_optimization_section( $post ); ?>
			<?php $this->render_analysis_section( $checks ); ?>
			<?php $this->render_gsc_metrics( $post ); ?>
			<?php $this->render_ai_section( $post ); ?>
			<?php $this->render_social_section( $post ); ?>
			<?php $this->render_internal_links_section( $post ); ?>
			<?php $this->render_schema_sections( $post ); ?>
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
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #10b981;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🎯</span>
					<?php esc_html_e( 'SERP Optimization', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impact: +40%', 'fp-seo-performance' ); ?>
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
	 * Render SEO Title field.
	 *
	 * @param WP_Post $post Current post.
	 */
	private function render_seo_title_field( WP_Post $post ): void {
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
					style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);"
					onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
					onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span style="font-size: 14px;">🤖</span>
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
					style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2); height: fit-content;"
					onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
					onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span style="font-size: 14px;">🤖</span>
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
					style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);"
					onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
					onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
					title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
				>
					<span style="font-size: 14px;">🤖</span>
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
			><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
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
		// Clear cache before retrieving keywords
		clean_post_cache( $post->ID );
		wp_cache_delete( $post->ID, 'post_meta' );
		wp_cache_delete( $post->ID, 'posts' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'post_meta' );
		}
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
					<?php esc_html_e( 'Impact: +18%', 'fp-seo-performance' ); ?>
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
					<?php esc_html_e( 'Impact: +12%', 'fp-seo-performance' ); ?>
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
					<?php esc_html_e( 'Impact: +7%', 'fp-seo-performance' ); ?>
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
					<?php esc_html_e( 'Impact: +20%', 'fp-seo-performance' ); ?>
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
					<?php esc_html_e( 'Impact: +15%', 'fp-seo-performance' ); ?>
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
}

