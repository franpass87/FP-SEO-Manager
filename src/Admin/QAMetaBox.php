<?php
/**
 * Q&A Pairs Management MetaBox
 *
 * Provides UI for managing Q&A pairs in post editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Scripts\QAMetaBoxScriptsManager;
use FP\SEO\Admin\Styles\QAMetaBoxStylesManager;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\Utils\PostTypes;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Integrations\OpenAiClient;

/**
 * Manages Q&A pairs metabox
 */
class QAMetabox {
	/**
	 * Q&A extractor instance
	 *
	 * @var QAPairExtractor
	 */
	private QAPairExtractor $extractor;

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * @var QAMetaBoxStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var QAMetaBoxScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor
	 *
	 * @param QAPairExtractor           $extractor     Q&A pair extractor instance.
	 * @param OpenAiClient              $openai_client OpenAI client instance.
	 * @param HookManagerInterface|null $hook_manager  Optional hook manager instance.
	 */
	public function __construct( QAPairExtractor $extractor, OpenAiClient $openai_client, ?HookManagerInterface $hook_manager = null ) {
		$this->extractor     = $extractor;
		$this->openai_client = $openai_client;
		$this->hook_manager  = $hook_manager;
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		} else {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		// Initialize and register styles and scripts managers
		$this->styles_manager = new QAMetaBoxStylesManager();
		$this->styles_manager->register_hooks();
		$this->scripts_manager = new QAMetaBoxScriptsManager();
		$this->scripts_manager->register_hooks();
	}

	/**
	 * Add metabox (DEPRECATO - contenuto integrato in Metabox.php)
	 * Mantenuto per compatibilità ma non utilizzato.
	 */
	public function add_meta_box(): void {
		// Metodo deprecato - il contenuto è ora integrato nella metabox principale SEO Performance
		// Non registra più una metabox separata
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
	}

	/**
	 * Render metabox
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'fp_seo_qa_metabox', 'fp_seo_qa_nonce' );

		$qa_pairs = $this->extractor->get_qa_pairs( $post->ID );
		$has_openai = $this->openai_client->is_configured();
		
		// Set post context for scripts manager
		if ( $this->scripts_manager ) {
			$this->scripts_manager->set_post( $post );
		}
		
		// Debug logging removed - use injected logger if needed

		?>
	<div class="fp-seo-qa-metabox">
		<!-- Hidden field to ensure metabox is recognized in POST -->
		<input type="hidden" name="fp_seo_performance_metabox_present" value="1" />
		<!-- Hidden field to store Q&A pairs for saving -->
		<?php $qa_pairs_json = wp_json_encode( $qa_pairs ); ?>
		<input type="hidden" id="fp-seo-qa-pairs-data" name="fp_seo_qa_pairs_data" value="<?php echo esc_attr( false !== $qa_pairs_json ? $qa_pairs_json : '[]' ); ?>">
			
			<p class="description">
				<?php esc_html_e( 'Le coppie domanda-risposta aiutano gli AI (ChatGPT, Gemini, Claude, Perplexity) a citare il tuo contenuto nelle risposte agli utenti.', 'fp-seo-performance' ); ?>
			</p>

			<!-- Generate Button -->
			<div style="margin: 20px 0; padding: 15px; background: #f0f9ff; border-radius: 6px; border-left: 4px solid #0284c7;">
				<?php if ( $has_openai ) : ?>
					<button type="button" 
							id="fp-seo-generate-qa-btn" 
							class="button button-primary"
							data-post-id="<?php echo esc_attr( $post->ID ); ?>">
						🤖 <?php esc_html_e( 'Genera Q&A Automaticamente con AI', 'fp-seo-performance' ); ?>
					</button>
					<p class="description" style="margin-top: 10px;">
						<?php esc_html_e( 'Usa GPT-4o Mini per estrarre automaticamente 8-12 coppie domanda-risposta dal contenuto.', 'fp-seo-performance' ); ?>
					</p>
				<?php else : ?>
					<p class="description">
						⚠️ <?php esc_html_e( 'Configura OpenAI API key in Settings → AI per abilitare la generazione automatica.', 'fp-seo-performance' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Q&A List -->
			<div id="fp-seo-qa-list">
				<?php if ( empty( $qa_pairs ) ) : ?>
					<p class="description" style="text-align: center; padding: 30px; background: #fafafa; border-radius: 6px;">
						<?php esc_html_e( 'Nessuna Q&A pair disponibile. Clicca "Genera Q&A Automaticamente" o aggiungine una manualmente.', 'fp-seo-performance' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $qa_pairs as $index => $pair ) : ?>
						<div class="fp-seo-qa-pair" data-index="<?php echo esc_attr( $index ); ?>" style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;">
							<div style="display: flex; justify-content: space-between; align-items: start;">
								<div style="flex: 1;">
									<div style="margin-bottom: 10px;">
										<strong style="color: #1e40af;">Q:</strong>
										<span><?php echo esc_html( $pair['question'] ); ?></span>
									</div>
									<div style="margin-bottom: 10px;">
										<strong style="color: #059669;">A:</strong>
										<span><?php echo esc_html( $pair['answer'] ); ?></span>
									</div>
									<div style="font-size: 12px; color: #6b7280;">
										<span title="Confidence Score">⭐ <?php echo esc_html( number_format( (float) ( $pair['confidence'] ?? 0.0 ), 2 ) ); ?></span>
										<span style="margin-left: 15px;" title="Type">🏷️ <?php echo esc_html( $pair['question_type'] ?? 'informational' ); ?></span>
										<?php if ( ! empty( $pair['keywords'] ) ) : ?>
											<span style="margin-left: 15px;" title="Keywords">🔑 <?php echo esc_html( implode( ', ', array_slice( $pair['keywords'], 0, 3 ) ) ); ?></span>
										<?php endif; ?>
									</div>
								</div>
								<button type="button" 
										class="button fp-seo-delete-qa" 
										data-index="<?php echo esc_attr( $index ); ?>"
										style="color: #dc2626;">
									×
								</button>
							</div>
						</div>
					<?php endforeach; ?>

					<p style="text-align: center; font-size: 13px; color: #64748b;">
						<?php
						printf(
							/* translators: %d: Number of Q&A pairs */
							esc_html__( 'Totale: %d Q&A pairs | Endpoint: ', 'fp-seo-performance' ),
							count( $qa_pairs )
						);
						?>
						<a href="<?php echo esc_url( home_url( '/geo/content/' . $post->ID . '/qa.json' ) ); ?>" target="_blank">
							<?php esc_html_e( 'Visualizza JSON', 'fp-seo-performance' ); ?> →
						</a>
					</p>
				<?php endif; ?>
			</div>

			<!-- Add Manual Q&A -->
			<div style="margin-top: 20px; padding: 15px; background: #fafafa; border-radius: 6px;">
				<h4 style="margin-top: 0;"><?php esc_html_e( '➕ Aggiungi Q&A Manualmente', 'fp-seo-performance' ); ?></h4>
				
				<p>
					<label>
						<strong><?php esc_html_e( 'Domanda:', 'fp-seo-performance' ); ?></strong><br>
						<input type="text" id="fp-seo-manual-question" class="widefat" placeholder="<?php esc_attr_e( 'Come ottimizzare per AI search?', 'fp-seo-performance' ); ?>">
					</label>
				</p>

				<p>
					<label>
						<strong><?php esc_html_e( 'Risposta:', 'fp-seo-performance' ); ?></strong><br>
						<textarea id="fp-seo-manual-answer" class="widefat" rows="4" placeholder="<?php esc_attr_e( 'Per ottimizzare per AI search devi...', 'fp-seo-performance' ); ?>"></textarea>
					</label>
				</p>

				<button type="button" id="fp-seo-add-manual-qa" class="button">
					<?php esc_html_e( 'Aggiungi Q&A', 'fp-seo-performance' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}

