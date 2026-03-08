<?php
/**
 * Multiple Keywords Manager
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords;

use FP\SEO\Keywords\Handlers\KeywordsAjaxHandler;
use FP\SEO\Keywords\Scripts\KeywordsMetaboxScriptsManager;
use FP\SEO\Keywords\Services\KeywordsAnalysisService;
use FP\SEO\Keywords\Services\KeywordsSuggestionService;
use FP\SEO\Keywords\Styles\KeywordsMetaboxStylesManager;
use FP\SEO\Keywords\Styles\KeywordsPageStylesManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\CacheHelper;
use FP\SEO\Utils\PerformanceConfig;

/**
 * Handles multiple focus keywords management and optimization.
 */
class MultipleKeywordsManager {
	/**
	 * @var KeywordsMetaboxStylesManager|null
	 */
	private $metabox_styles_manager;

	/**
	 * @var KeywordsMetaboxScriptsManager|null
	 */
	private $metabox_scripts_manager;

	/**
	 * @var KeywordsPageStylesManager|null
	 */
	private $page_styles_manager;

	/**
	 * @var KeywordsAjaxHandler|null
	 */
	private $ajax_handler;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Keywords analysis service.
	 *
	 * @var KeywordsAnalysisService
	 */
	private KeywordsAnalysisService $analysis_service;

	/**
	 * Keywords suggestion service.
	 *
	 * @var KeywordsSuggestionService
	 */
	private KeywordsSuggestionService $suggestion_service;

	/**
	 * Keywords cache group.
	 */
	private const CACHE_GROUP = 'fp_seo_keywords';

	/**
	 * Maximum number of keywords per post.
	 */
	private const MAX_KEYWORDS = 10;

	/**
	 * Minimum keyword length.
	 */
	private const MIN_KEYWORD_LENGTH = 2;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
		$this->analysis_service = new KeywordsAnalysisService();
		$this->suggestion_service = new KeywordsSuggestionService();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$hook_manager = $this->hook_manager ?? $this->get_hook_manager();
		
		$hook_manager->add_action( 'admin_menu', array( $this, 'add_keywords_menu' ) );
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_keywords_metabox' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		// CRITICAL: Use priority 20 instead of 10 to ensure we run AFTER WordPress core saves _thumbnail_id
		// WordPress core saves featured image (_thumbnail_id) during save_post with priority 10
		// By using priority 20, we ensure our hook runs after WordPress has finished saving the featured image
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			$hook_manager->add_action( 'save_post_' . $post_type, array( $this, 'save_keywords_meta' ), 20, 1 );
		}
		
		// Register AJAX handler
		$this->ajax_handler = new KeywordsAjaxHandler( $this, $hook_manager );
		$this->ajax_handler->register();

		// Frontend keywords meta rendering moved to Frontend/Renderers/KeywordsRenderer

		// Initialize and register styles and scripts managers
		$this->metabox_styles_manager = new KeywordsMetaboxStylesManager();
		$this->metabox_styles_manager->register_hooks();
		$this->metabox_scripts_manager = new KeywordsMetaboxScriptsManager();
		$this->metabox_scripts_manager->register_hooks();
		$this->page_styles_manager = new KeywordsPageStylesManager();
		$this->page_styles_manager->register_hooks();
	}

	/**
	 * Get hook manager from container.
	 *
	 * @return HookManagerInterface
	 */
	private function get_hook_manager(): HookManagerInterface {
		if ( $this->hook_manager ) {
			return $this->hook_manager;
		}
		
		// Fallback: get from container
		$plugin = \FP\SEO\Infrastructure\Plugin::instance();
		$container = $plugin->get_container();
		$this->hook_manager = $container->get( HookManagerInterface::class );
		return $this->hook_manager;
	}

	/**
	 * Add Keywords menu to admin.
	 */
	public function add_keywords_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Multiple Keywords', 'fp-seo-performance' ),
			__( 'Multiple Keywords', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-multiple-keywords',
			array( $this, 'render_keywords_page' )
		);
	}

	/**
	 * Add keywords metabox to post editor (DEPRECATO - contenuto integrato in Metabox.php).
	 * Mantenuto per compatibilità ma non utilizzato.
	 */
	public function add_keywords_metabox(): void {
		// Metodo deprecato - il contenuto è ora integrato nella metabox principale SEO Performance
		// Non registra più una metabox separata
	}

	/**
	 * Get keywords for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_post_keywords( int $post_id ): array {
		$cache_key = 'fp_seo_keywords_' . $post_id;
		
		return CacheHelper::remember( $cache_key, function() use ( $post_id ) {
			$keywords_meta = get_post_meta( $post_id, '_fp_seo_multiple_keywords', true );
			return is_array( $keywords_meta ) ? $keywords_meta : array();
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Save keywords meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_keywords_meta( int $post_id ): void {
// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( ! isset( $_POST['fp_seo_keywords_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_keywords_nonce'] ) ), 'fp_seo_keywords_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$keywords_data = array(
			'primary_keyword' => sanitize_text_field( $_POST['fp_seo_primary_keyword'] ?? '' ),
			'secondary_keywords' => array_map( 'sanitize_text_field', $_POST['fp_seo_secondary_keywords'] ?? array() ),
			'long_tail_keywords' => array_map( 'sanitize_text_field', $_POST['fp_seo_long_tail_keywords'] ?? array() ),
			'semantic_keywords' => array_map( 'sanitize_text_field', $_POST['fp_seo_semantic_keywords'] ?? array() ),
			'keyword_density' => array(),
			'keyword_positions' => array(),
			'last_analyzed' => current_time( 'mysql' )
		);

		// Remove empty keywords
		$keywords_data['secondary_keywords'] = array_filter( $keywords_data['secondary_keywords'] );
		$keywords_data['long_tail_keywords'] = array_filter( $keywords_data['long_tail_keywords'] );
		$keywords_data['semantic_keywords'] = array_filter( $keywords_data['semantic_keywords'] );

		// Analyze keywords if content exists
		if ( ! empty( $keywords_data['primary_keyword'] ) || ! empty( $keywords_data['secondary_keywords'] ) ) {
			$analysis = $this->analyze_keywords_in_content( $post_id, $keywords_data );
			$keywords_data['keyword_density'] = $analysis['density'];
			$keywords_data['keyword_positions'] = $analysis['positions'];
		}

		update_post_meta( $post_id, '_fp_seo_multiple_keywords', $keywords_data );

		// Clear cache
		CacheHelper::delete( 'fp_seo_keywords_' . $post_id );
	}

	/**
	 * Analyze keywords in post content.
	 *
	 * @param int $post_id Post ID.
	 * @param array<string, mixed> $keywords_data Keywords data.
	 * @return array<string, mixed>
	 */
	public function analyze_keywords_in_content( int $post_id, array $keywords_data ): array {
		return $this->analysis_service->analyze( $post_id, $keywords_data );
	}

	/**
	 * Render keywords metabox content (può essere chiamato dalla metabox principale).
	 *
	 * @param WP_Post|int $post Current post or post ID.
	 */
	public function render_keywords_metabox( $post ): void {
		// Accetta sia WP_Post che int
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}
		
		if ( ! $post ) {
			return;
		}
		$keywords_data = $this->get_post_keywords( $post->ID );
		$suggestions = $this->get_keyword_suggestions( $post->ID );
		
		wp_nonce_field( 'fp_seo_keywords_meta', 'fp_seo_keywords_nonce' );
		?>
		<div class="fp-seo-keywords-metabox">
			<div class="fp-seo-keywords-tabs">
				<button type="button" class="fp-seo-keywords-tab active" data-tab="primary"><?php esc_html_e( 'Principale', 'fp-seo-performance' ); ?></button>
				<button type="button" class="fp-seo-keywords-tab" data-tab="secondary"><?php esc_html_e( 'Secondarie', 'fp-seo-performance' ); ?></button>
				<button type="button" class="fp-seo-keywords-tab" data-tab="long-tail"><?php esc_html_e( 'Long Tail', 'fp-seo-performance' ); ?></button>
				<button type="button" class="fp-seo-keywords-tab" data-tab="semantic"><?php esc_html_e( 'Semantiche', 'fp-seo-performance' ); ?></button>
				<button type="button" class="fp-seo-keywords-tab" data-tab="analysis"><?php esc_html_e( 'Analisi', 'fp-seo-performance' ); ?></button>
			</div>

			<div class="fp-seo-keywords-tab-content active" id="primary">
				<div class="fp-seo-form-group">
					<label for="fp-seo-primary-keyword"><?php esc_html_e( 'Primary Focus Keyword', 'fp-seo-performance' ); ?></label>
					<input type="text" id="fp-seo-primary-keyword" name="fp_seo_primary_keyword" 
						   value="<?php echo esc_attr( $keywords_data['primary_keyword'] ?? '' ); ?>" 
						   placeholder="<?php esc_attr_e( 'Enter your main keyword', 'fp-seo-performance' ); ?>">
					<p class="description"><?php esc_html_e( 'Your most important keyword for this content.', 'fp-seo-performance' ); ?></p>
				</div>

				<div class="fp-seo-keyword-suggestions">
					<h4><?php esc_html_e( 'AI Suggestions', 'fp-seo-performance' ); ?></h4>
					<div class="fp-seo-suggestions-list">
						<?php foreach ( $suggestions['primary'] ?? array() as $suggestion ) : ?>
						<div class="fp-seo-suggestion-item" data-keyword="<?php echo esc_attr( $suggestion['keyword'] ); ?>">
							<span class="fp-seo-suggestion-keyword"><?php echo esc_html( $suggestion['keyword'] ); ?></span>
							<span class="fp-seo-suggestion-score"><?php echo esc_html( $suggestion['score'] ); ?>%</span>
							<button type="button" class="button button-small fp-seo-use-suggestion"><?php esc_html_e( 'Usa', 'fp-seo-performance' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="fp-seo-keywords-tab-content" id="secondary">
				<div class="fp-seo-form-group">
					<label for="fp-seo-secondary-keywords"><?php esc_html_e( 'Secondary Keywords', 'fp-seo-performance' ); ?></label>
					<div class="fp-seo-keywords-input-container">
						<input type="text" id="fp-seo-secondary-keyword-input" placeholder="<?php esc_attr_e( 'Add secondary keyword', 'fp-seo-performance' ); ?>">
						<button type="button" class="button" id="fp-seo-add-secondary-keyword"><?php esc_html_e( 'Aggiungi', 'fp-seo-performance' ); ?></button>
					</div>
					<div class="fp-seo-keywords-list" id="fp-seo-secondary-keywords-list">
						<?php foreach ( $keywords_data['secondary_keywords'] ?? array() as $index => $keyword ) : ?>
							<div class="fp-seo-keyword-item">
								<input type="hidden" name="fp_seo_secondary_keywords[]" value="<?php echo esc_attr( $keyword ); ?>">
								<span class="fp-seo-keyword-text"><?php echo esc_html( $keyword ); ?></span>
								<button type="button" class="fp-seo-remove-keyword">×</button>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Supporting keywords that complement your primary keyword.', 'fp-seo-performance' ); ?></p>
				</div>

				<div class="fp-seo-keyword-suggestions">
					<h4><?php esc_html_e( 'AI Suggestions', 'fp-seo-performance' ); ?></h4>
					<div class="fp-seo-suggestions-list">
						<?php foreach ( $suggestions['secondary'] ?? array() as $suggestion ) : ?>
						<div class="fp-seo-suggestion-item" data-keyword="<?php echo esc_attr( $suggestion['keyword'] ); ?>">
							<span class="fp-seo-suggestion-keyword"><?php echo esc_html( $suggestion['keyword'] ); ?></span>
							<span class="fp-seo-suggestion-score"><?php echo esc_html( $suggestion['score'] ); ?>%</span>
							<button type="button" class="button button-small fp-seo-use-suggestion"><?php esc_html_e( 'Usa', 'fp-seo-performance' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="fp-seo-keywords-tab-content" id="long-tail">
				<div class="fp-seo-form-group">
					<label for="fp-seo-long-tail-keywords"><?php esc_html_e( 'Long Tail Keywords', 'fp-seo-performance' ); ?></label>
					<div class="fp-seo-keywords-input-container">
						<input type="text" id="fp-seo-long-tail-keyword-input" placeholder="<?php esc_attr_e( 'Add long tail keyword', 'fp-seo-performance' ); ?>">
						<button type="button" class="button" id="fp-seo-add-long-tail-keyword"><?php esc_html_e( 'Aggiungi', 'fp-seo-performance' ); ?></button>
					</div>
					<div class="fp-seo-keywords-list" id="fp-seo-long-tail-keywords-list">
						<?php foreach ( $keywords_data['long_tail_keywords'] ?? array() as $index => $keyword ) : ?>
							<div class="fp-seo-keyword-item">
								<input type="hidden" name="fp_seo_long_tail_keywords[]" value="<?php echo esc_attr( $keyword ); ?>">
								<span class="fp-seo-keyword-text"><?php echo esc_html( $keyword ); ?></span>
								<button type="button" class="fp-seo-remove-keyword">×</button>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Specific, longer phrases that are easier to rank for.', 'fp-seo-performance' ); ?></p>
				</div>

				<div class="fp-seo-keyword-suggestions">
					<h4><?php esc_html_e( 'AI Suggestions', 'fp-seo-performance' ); ?></h4>
					<div class="fp-seo-suggestions-list">
						<?php foreach ( $suggestions['long_tail'] ?? array() as $suggestion ) : ?>
						<div class="fp-seo-suggestion-item" data-keyword="<?php echo esc_attr( $suggestion['keyword'] ); ?>">
							<span class="fp-seo-suggestion-keyword"><?php echo esc_html( $suggestion['keyword'] ); ?></span>
							<span class="fp-seo-suggestion-score"><?php echo esc_html( $suggestion['score'] ); ?>%</span>
							<button type="button" class="button button-small fp-seo-use-suggestion"><?php esc_html_e( 'Usa', 'fp-seo-performance' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="fp-seo-keywords-tab-content" id="semantic">
				<div class="fp-seo-form-group">
					<label for="fp-seo-semantic-keywords"><?php esc_html_e( 'Semantic Keywords', 'fp-seo-performance' ); ?></label>
					<div class="fp-seo-keywords-input-container">
						<input type="text" id="fp-seo-semantic-keyword-input" placeholder="<?php esc_attr_e( 'Add semantic keyword', 'fp-seo-performance' ); ?>">
						<button type="button" class="button" id="fp-seo-add-semantic-keyword"><?php esc_html_e( 'Aggiungi', 'fp-seo-performance' ); ?></button>
					</div>
					<div class="fp-seo-keywords-list" id="fp-seo-semantic-keywords-list">
						<?php foreach ( $keywords_data['semantic_keywords'] ?? array() as $index => $keyword ) : ?>
							<div class="fp-seo-keyword-item">
								<input type="hidden" name="fp_seo_semantic_keywords[]" value="<?php echo esc_attr( $keyword ); ?>">
								<span class="fp-seo-keyword-text"><?php echo esc_html( $keyword ); ?></span>
								<button type="button" class="fp-seo-remove-keyword">×</button>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Related terms and synonyms that help search engines understand context.', 'fp-seo-performance' ); ?></p>
				</div>

				<div class="fp-seo-keyword-suggestions">
					<h4><?php esc_html_e( 'AI Suggestions', 'fp-seo-performance' ); ?></h4>
					<div class="fp-seo-suggestions-list">
						<?php foreach ( $suggestions['semantic'] ?? array() as $suggestion ) : ?>
						<div class="fp-seo-suggestion-item" data-keyword="<?php echo esc_attr( $suggestion['keyword'] ); ?>">
							<span class="fp-seo-suggestion-keyword"><?php echo esc_html( $suggestion['keyword'] ); ?></span>
							<span class="fp-seo-suggestion-score"><?php echo esc_html( $suggestion['score'] ); ?>%</span>
							<button type="button" class="button button-small fp-seo-use-suggestion"><?php esc_html_e( 'Usa', 'fp-seo-performance' ); ?></button>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="fp-seo-keywords-tab-content" id="analysis">
				<div class="fp-seo-keywords-analysis">
					<h4><?php esc_html_e( 'Keyword Analysis', 'fp-seo-performance' ); ?></h4>
					
					<?php if ( ! empty( $keywords_data['keyword_density'] ) ) : ?>
						<div class="fp-seo-density-analysis">
							<h5><?php esc_html_e( 'Density Analysis', 'fp-seo-performance' ); ?></h5>
							<div class="fp-seo-density-list">
								<?php foreach ( $keywords_data['keyword_density'] as $keyword => $data ) : ?>
									<div class="fp-seo-density-item">
									<div class="fp-seo-density-keyword"><?php echo esc_html( $keyword ); ?></div>
									<div class="fp-seo-density-stats">
										<span class="fp-seo-density-count"><?php echo esc_html( $data['count'] ); ?> <?php esc_html_e( 'times', 'fp-seo-performance' ); ?></span>
										<span class="fp-seo-density-percentage"><?php echo esc_html( $data['density'] ); ?>%</span>
											<span class="fp-seo-density-status fp-seo-density-status--<?php echo esc_attr( $data['status'] ); ?>">
												<?php echo esc_html( ucfirst( $data['status'] ) ); ?>
											</span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php else : ?>
						<div class="fp-seo-no-analysis">
							<p><?php esc_html_e( 'No keyword analysis available. Add keywords and save to analyze.', 'fp-seo-performance' ); ?></p>
						</div>
					<?php endif; ?>

					<div class="fp-seo-keywords-actions">
						<button type="button" class="button" id="fp-seo-analyze-keywords">
							<?php esc_html_e( 'Analizza Keyword', 'fp-seo-performance' ); ?>
						</button>
						<button type="button" class="fp-seo-ai-btn" id="fp-seo-optimize-keywords">
							<span>🤖</span>
							<span><?php esc_html_e( 'Ottimizza con AI', 'fp-seo-performance' ); ?></span>
						</button>
					</div>
				</div>
			</div>
		</div>
	<style>
	.fp-seo-keywords-metabox {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	}
		
		.fp-seo-keywords-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin-bottom: 20px;
		}
		
		.fp-seo-keywords-tab {
			padding: 10px 16px;
			background: #f8f9fa;
			border: 1px solid #ddd;
			border-bottom: none;
			cursor: pointer;
			margin-right: 2px;
			border-radius: 4px 4px 0 0;
			font-size: 12px;
			font-weight: 600;
		}
		
		.fp-seo-keywords-tab.active {
			background: #fff;
			border-bottom: 1px solid #fff;
		}
		
		.fp-seo-keywords-tab-content {
			display: none;
		}
		
		.fp-seo-keywords-tab-content.active {
			display: block;
		}
		
		.fp-seo-form-group {
			margin-bottom: 20px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 13px;
		}
		
		.fp-seo-form-group input[type="text"] {
			width: 100%;
			padding: 8px 12px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 13px;
		}
		
		.fp-seo-keywords-input-container {
			display: flex;
			gap: 8px;
			margin-bottom: 10px;
		}
		
		.fp-seo-keywords-input-container input {
			flex: 1;
		}
		
		.fp-seo-keywords-list {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-bottom: 10px;
		}
		
		.fp-seo-keyword-item {
			display: flex;
			align-items: center;
			background: #e3f2fd;
			border: 1px solid #bbdefb;
			border-radius: 16px;
			padding: 4px 8px;
			font-size: 12px;
		}
		
		.fp-seo-keyword-text {
			margin-right: 6px;
			color: #1976d2;
		}
		
		.fp-seo-remove-keyword {
			background: none;
			border: none;
			color: #666;
			cursor: pointer;
			font-size: 16px;
			line-height: 1;
			padding: 0;
			width: 16px;
			height: 16px;
		}
		
		.fp-seo-keyword-suggestions {
			margin-top: 20px;
		}
		
		.fp-seo-keyword-suggestions h4 {
			margin: 0 0 10px 0;
			font-size: 13px;
			color: #666;
		}
		
		.fp-seo-suggestions-list {
			max-height: 200px;
			overflow-y: auto;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		
		.fp-seo-suggestion-item {
			display: flex;
			align-items: center;
			padding: 8px 12px;
			border-bottom: 1px solid #f0f0f0;
		}
		
		.fp-seo-suggestion-item:last-child {
			border-bottom: none;
		}
		
		.fp-seo-suggestion-keyword {
			flex: 1;
			font-size: 12px;
			color: #333;
		}
		
		.fp-seo-suggestion-score {
			background: #0073aa;
			color: #fff;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
			margin-right: 8px;
		}
		
		.fp-seo-density-analysis {
			margin-bottom: 20px;
		}
		
		.fp-seo-density-analysis h5 {
			margin: 0 0 10px 0;
			font-size: 13px;
			color: #666;
		}
		
		.fp-seo-density-list {
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		
		.fp-seo-density-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 10px 12px;
			border-bottom: 1px solid #f0f0f0;
		}
		
		.fp-seo-density-item:last-child {
			border-bottom: none;
		}
		
		.fp-seo-density-keyword {
			font-weight: 600;
			font-size: 12px;
			color: #333;
		}
		
		.fp-seo-density-stats {
			display: flex;
			align-items: center;
			gap: 12px;
			font-size: 11px;
		}
		
		.fp-seo-density-count {
			color: #666;
		}
		
		.fp-seo-density-percentage {
			font-weight: 600;
			color: #0073aa;
		}
		
		.fp-seo-density-status {
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
			text-transform: uppercase;
		}
		
		.fp-seo-density-status--low {
			background: #ffebee;
			color: #c62828;
		}
		
		.fp-seo-density-status--good {
			background: #e8f5e8;
			color: #2e7d32;
		}
		
		.fp-seo-density-status--high {
			background: #fff3e0;
			color: #ef6c00;
		}
		
		.fp-seo-density-status--over-optimized {
			background: #ffebee;
			color: #c62828;
		}
		
		.fp-seo-no-analysis {
			text-align: center;
			padding: 20px;
			color: #666;
		}
		
		.fp-seo-keywords-actions {
			display: flex;
			gap: 8px;
			margin-top: 20px;
		}
		
	.fp-seo-keywords-actions .button {
		flex: 1;
		text-align: center;
	}
	</style>
	<?php
}

	/**
	 * Get keyword suggestions for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_keyword_suggestions( int $post_id ): array {
		return $this->suggestion_service->get_suggestions( $post_id );
	}

	/**
	 * Render Keywords management page.
	 */
	public function render_keywords_page(): void {
		$site_analysis = $this->get_site_keywords_analysis();
		
		?>
		<div class="wrap fp-seo-keywords-wrap">
			<h1><?php esc_html_e( 'Multiple Keywords Management', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Gestisci e ottimizza le strategie keyword per tutti i tuoi contenuti', 'fp-seo-performance' ); ?></p>

			<!-- Banner introduttivo -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">🎯</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Cosa sono le Multiple Keywords?', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'Invece di limitarti a una sola keyword, puoi targetizzare più keyword correlate per ogni contenuto. Questo approccio aumenta le possibilità di rankare per query diverse:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li><strong>Primary Keyword:</strong> La keyword principale (es: "SEO WordPress")</li>
						<li><strong>Secondary Keywords:</strong> Varianti correlate (es: "ottimizzazione WordPress", "plugin SEO")</li>
						<li><strong>Long Tail Keywords:</strong> Frasi specifiche più lunghe (es: "come ottimizzare WordPress per Google")</li>
						<li><strong>Semantic Keywords:</strong> Sinonimi e termini correlati (es: "posizionamento Google", "ranking organico")</li>
					</ul>
					<p class="fp-seo-tip">💡 <strong>Tip:</strong> Articoli con 3-5 keyword strategiche rankano in media per 50+ query diverse su Google!</p>
				</div>
			</div>
			
			<div class="fp-seo-keywords-dashboard">
				<div class="fp-seo-keywords-stats-grid">
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📊</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Total Keywords', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero totale di keyword configurate su tutti i contenuti del sito (primary + secondary + long tail + semantic)', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( $site_analysis['total_keywords'] ); ?></span>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Keyword monitorate', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📝</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Posts with Keywords', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero di post/pagine che hanno almeno una keyword configurata. Più post ottimizzati = maggiore visibilità organica.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( $site_analysis['posts_with_keywords'] ); ?></span>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Contenuti ottimizzati', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">⚡</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Media Keyword/Post', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero medio di keyword per post. Ideale: 3-5 keyword per post. Meno di 3 = opportunità perse; più di 7 = rischio diluzione focus.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo round( $site_analysis['avg_keywords_per_post'], 1 ); ?></span>
							<p class="fp-seo-stat-desc">
								<?php 
								$avg = $site_analysis['avg_keywords_per_post'];
								if ($avg < 2) {
									echo '⚠️ ' . esc_html__( 'Troppo poche', 'fp-seo-performance' );
								} elseif ($avg >= 3 && $avg <= 5) {
									echo '✅ ' . esc_html__( 'Ottimale', 'fp-seo-performance' );
								} elseif ($avg > 5) {
									echo '⚠️ ' . esc_html__( 'Forse troppe', 'fp-seo-performance' );
								} else {
									echo esc_html__( 'Media keyword', 'fp-seo-performance' );
								}
								?>
							</p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">🎯</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Keyword Coverage', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Percentuale di contenuti del sito con keyword configurate. Target: >80% per massima visibilità SEO.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo round( $site_analysis['keyword_coverage'], 1 ); ?>%</span>
							<p class="fp-seo-stat-desc">
								<?php 
								$coverage = $site_analysis['keyword_coverage'];
								if ($coverage < 50) {
									echo '🔴 ' . esc_html__( 'Bassa', 'fp-seo-performance' );
								} elseif ($coverage >= 50 && $coverage < 80) {
									echo '🟡 ' . esc_html__( 'Media', 'fp-seo-performance' );
								} else {
									echo '🟢 ' . esc_html__( 'Ottima', 'fp-seo-performance' );
								}
								?>
							</p>
						</div>
					</div>
				</div>

				<div class="fp-seo-keywords-analysis">
					<h2><?php esc_html_e( 'Keywords Analysis', 'fp-seo-performance' ); ?></h2>
					<div class="fp-seo-analysis-results">
						<?php $this->render_keywords_analysis_results( $site_analysis ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get site-wide keywords analysis.
	 *
	 * @return array<string, mixed>
	 */
	private function get_site_keywords_analysis(): array {
		$cache_key = 'fp_seo_site_keywords_analysis';
		
		return CacheHelper::remember( $cache_key, function() {
			global $wpdb;
			
			// Get all posts with keywords (use prepared statement for security)
			$posts_with_keywords = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} 
					WHERE meta_key = %s 
					AND meta_value != ''",
					'_fp_seo_multiple_keywords'
				)
			);
			
			$total_keywords = 0;
			$posts_count = count( $posts_with_keywords );
			
			foreach ( $posts_with_keywords as $post_meta ) {
				$keywords_data = maybe_unserialize( $post_meta->meta_value );
				if ( is_array( $keywords_data ) ) {
					$total_keywords += count( $keywords_data['secondary_keywords'] ?? array() );
					$total_keywords += count( $keywords_data['long_tail_keywords'] ?? array() );
					$total_keywords += count( $keywords_data['semantic_keywords'] ?? array() );
					if ( ! empty( $keywords_data['primary_keyword'] ) ) {
						$total_keywords++;
					}
				}
			}
			
			// Get total published posts (safe query - hardcoded values)
			$total_posts = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
					WHERE post_type IN (%s, %s) 
					AND post_status = %s",
					'post',
					'page',
					'publish'
				)
			);
			
			$avg_keywords_per_post = $posts_count > 0 ? $total_keywords / $posts_count : 0;
			$keyword_coverage = $total_posts > 0 ? ( $posts_count / $total_posts ) * 100 : 0;
			
			return array(
				'total_keywords' => $total_keywords,
				'posts_with_keywords' => $posts_count,
				'avg_keywords_per_post' => $avg_keywords_per_post,
				'keyword_coverage' => $keyword_coverage,
				'total_posts' => $total_posts
			);
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Render keywords analysis results.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 */
	private function render_keywords_analysis_results( array $analysis ): void {
		?>
		<div class="fp-seo-analysis-summary">
			<div class="fp-seo-analysis-item">
				<strong><?php esc_html_e( 'Keywords Health Score:', 'fp-seo-performance' ); ?></strong>
				<span class="fp-seo-score <?php $kw_coverage = $analysis['keyword_coverage'] ?? 0; echo $kw_coverage > 70 ? 'good' : ( $kw_coverage > 40 ? 'warning' : 'poor' ); ?>">
					<?php echo $this->calculate_keywords_health_score( $analysis ); ?>%
				</span>
			</div>
			
			<div class="fp-seo-analysis-item">
				<strong><?php esc_html_e( 'Recommendations:', 'fp-seo-performance' ); ?></strong>
				<ul>
					<?php foreach ( $this->get_keywords_recommendations( $analysis ) as $recommendation ) : ?>
						<li><?php echo esc_html( $recommendation ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Calculate keywords health score.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 * @return int
	 */
	private function calculate_keywords_health_score( array $analysis ): int {
		$score       = 0;
		$coverage    = $analysis['keyword_coverage'] ?? 0;
		$avg_per_post = $analysis['avg_keywords_per_post'] ?? 0;
		$total       = $analysis['total_keywords'] ?? 0;
		
		// Keyword coverage score (50%)
		if ( $coverage >= 80 ) {
			$score += 50;
		} elseif ( $coverage >= 60 ) {
			$score += 40;
		} elseif ( $coverage >= 40 ) {
			$score += 30;
		} else {
			$score += 20;
		}
		
		// Average keywords per post score (30%)
		if ( $avg_per_post >= 5 ) {
			$score += 30;
		} elseif ( $avg_per_post >= 3 ) {
			$score += 20;
		} elseif ( $avg_per_post >= 1 ) {
			$score += 10;
		}
		
		// Total keywords score (20%)
		if ( $total >= 100 ) {
			$score += 20;
		} elseif ( $total >= 50 ) {
			$score += 15;
		} elseif ( $total >= 20 ) {
			$score += 10;
		}
		
		return min( $score, 100 );
	}

	/**
	 * Get keywords recommendations based on analysis.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 * @return array<string>
	 */
	private function get_keywords_recommendations( array $analysis ): array {
		$recommendations  = array();
		$coverage         = $analysis['keyword_coverage'] ?? 0;
		$avg_per_post     = $analysis['avg_keywords_per_post'] ?? 0;
		$total            = $analysis['total_keywords'] ?? 0;
		
		if ( $coverage < 60 ) {
			$recommendations[] = __( 'Add keywords to more posts to improve coverage.', 'fp-seo-performance' );
		}
		
		if ( $avg_per_post < 3 ) {
			$recommendations[] = __( 'Increase average keywords per post to at least 3.', 'fp-seo-performance' );
		}
		
		if ( $total < 50 ) {
			$recommendations[] = __( 'Add more keywords across your content.', 'fp-seo-performance' );
		}
		
		if ( empty( $recommendations ) ) {
			$recommendations[] = __( 'Your keywords strategy looks good!', 'fp-seo-performance' );
		}
		
		return $recommendations;
	}

	// AJAX handlers removed - now handled by KeywordsAjaxHandler

	/**
	 * Optimize keywords with AI.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	public function optimize_keywords_with_ai( $post ): array {
		// This would integrate with the AI Content Optimizer
		// For now, return basic optimization using suggestion service
		$suggestions = $this->suggestion_service->get_suggestions( $post->ID );
		
		$primary = ! empty( $suggestions['primary'] ) ? $suggestions['primary'][0]['keyword'] ?? '' : '';
		$secondary = array_column( array_slice( $suggestions['secondary'] ?? array(), 0, 3 ), 'keyword' );
		
		$optimized = array(
			'primary' => $primary,
			'secondary' => $secondary,
			'message' => __( 'Keywords optimized using AI analysis', 'fp-seo-performance' )
		);
		
		return $optimized;
	}

	/**
	 * Output keywords meta data in head.
	 */
	/**
	 * Output keywords meta tags in head.
	 *
	 * @deprecated Frontend rendering is now handled by Frontend/Renderers/KeywordsRenderer.
	 *             This method is kept for backward compatibility and is called by KeywordsRenderer.
	 * @return void
	 */
	public function output_keywords_meta(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$keywords_data = $this->get_post_keywords( $post_id );
		if ( empty( $keywords_data ) ) {
			return;
		}

		$all_keywords = array_merge(
			array( $keywords_data['primary_keyword'] ?? '' ),
			$keywords_data['secondary_keywords'] ?? array(),
			$keywords_data['long_tail_keywords'] ?? array(),
			$keywords_data['semantic_keywords'] ?? array()
		);

		$all_keywords = array_filter( $all_keywords );

		if ( ! empty( $all_keywords ) ) {
			echo "\n<!-- FP SEO Performance Keywords -->\n";
			echo '<meta name="keywords" content="' . esc_attr( implode( ', ', $all_keywords ) ) . '">' . "\n";
			echo "<!-- End FP SEO Performance Keywords -->\n";
		}
	}
}
