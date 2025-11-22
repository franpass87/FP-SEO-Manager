<?php
/**
 * Advanced AI Content Optimizer
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\Cache;
use FP\SEO\Utils\PerformanceConfig;

/**
 * Advanced AI-powered content optimization and analysis.
 */
class AdvancedContentOptimizer {

	/**
	 * OpenAI client instance.
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->openai_client = new OpenAiClient();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_optimizer_menu' ) );
		add_action( 'wp_ajax_fp_seo_analyze_content_gaps', array( $this, 'ajax_analyze_content_gaps' ) );
		add_action( 'wp_ajax_fp_seo_competitor_analysis', array( $this, 'ajax_competitor_analysis' ) );
		add_action( 'wp_ajax_fp_seo_content_suggestions', array( $this, 'ajax_content_suggestions' ) );
		add_action( 'wp_ajax_fp_seo_readability_optimization', array( $this, 'ajax_readability_optimization' ) );
		add_action( 'wp_ajax_fp_seo_semantic_optimization', array( $this, 'ajax_semantic_optimization' ) );
	}

	/**
	 * Add Content Optimizer menu to admin.
	 */
	public function add_optimizer_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'AI Content Optimizer', 'fp-seo-performance' ),
			__( 'AI Content Optimizer', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-content-optimizer',
			array( $this, 'render_optimizer_page' )
		);
	}

	/**
	 * Analyze content gaps for a topic.
	 *
	 * @param string $topic Main topic.
	 * @param string $target_keyword Target keyword.
	 * @param array<string> $competitor_urls Competitor URLs to analyze.
	 * @return array<string, mixed>
	 */
	public function analyze_content_gaps( string $topic, string $target_keyword, array $competitor_urls = array() ): array {
		$cache_key = 'fp_seo_content_gaps_' . md5( $topic . $target_keyword . implode( ',', $competitor_urls ) );
		
		return Cache::remember( $cache_key, function() use ( $topic, $target_keyword, $competitor_urls ) {
			$prompt = $this->build_content_gap_prompt( $topic, $target_keyword, $competitor_urls );
			
			$response = $this->openai_client->generate_content( $prompt, array(
				'max_completion_tokens' => 1000,
				'temperature' => 0.7,
			) );

			return $this->parse_content_gap_response( $response );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Analyze competitor content.
	 *
	 * @param string $target_url Target URL to analyze.
	 * @param string $target_keyword Target keyword.
	 * @return array<string, mixed>
	 */
	public function analyze_competitor_content( string $target_url, string $target_keyword ): array {
		$cache_key = 'fp_seo_competitor_' . md5( $target_url . $target_keyword );
		
		return Cache::remember( $cache_key, function() use ( $target_url, $target_keyword ) {
			// Get content from URL
			$content = $this->fetch_url_content( $target_url );
			if ( empty( $content ) ) {
				return array( 'error' => 'Unable to fetch content from URL' );
			}

			$prompt = $this->build_competitor_analysis_prompt( $content, $target_keyword );
			
		$response = $this->openai_client->generate_content( $prompt, array(
			'max_completion_tokens' => 800,
			'temperature' => 0.5,
		) );

			return $this->parse_competitor_response( $response );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Generate content suggestions.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Current content.
	 * @param string $target_keyword Target keyword.
	 * @return array<string, mixed>
	 */
	public function generate_content_suggestions( int $post_id, string $content, string $target_keyword ): array {
		$cache_key = 'fp_seo_content_suggestions_' . $post_id . '_' . md5( $content . $target_keyword );
		
		return Cache::remember( $cache_key, function() use ( $post_id, $content, $target_keyword ) {
			$context = $this->gather_content_context( $post_id );
			$prompt = $this->build_content_suggestions_prompt( $content, $target_keyword, $context );
			
		$response = $this->openai_client->generate_content( $prompt, array(
			'max_completion_tokens' => 1200,
			'temperature' => 0.8,
		) );

			return $this->parse_content_suggestions_response( $response );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Optimize content readability.
	 *
	 * @param string $content Content to optimize.
	 * @param string $target_audience Target audience.
	 * @return array<string, mixed>
	 */
	public function optimize_readability( string $content, string $target_audience = 'general' ): array {
		$cache_key = 'fp_seo_readability_' . md5( $content . $target_audience );
		
		return Cache::remember( $cache_key, function() use ( $content, $target_audience ) {
			$prompt = $this->build_readability_prompt( $content, $target_audience );
			
		$response = $this->openai_client->generate_content( $prompt, array(
			'max_completion_tokens' => 1000,
			'temperature' => 0.6,
		) );

			return $this->parse_readability_response( $response );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Optimize content for semantic SEO.
	 *
	 * @param string $content Content to optimize.
	 * @param string $target_keyword Target keyword.
	 * @param array<string> $semantic_keywords Related keywords.
	 * @return array<string, mixed>
	 */
	public function optimize_semantic_seo( string $content, string $target_keyword, array $semantic_keywords = array() ): array {
		$cache_key = 'fp_seo_semantic_' . md5( $content . $target_keyword . implode( ',', $semantic_keywords ) );
		
		return Cache::remember( $cache_key, function() use ( $content, $target_keyword, $semantic_keywords ) {
			$prompt = $this->build_semantic_prompt( $content, $target_keyword, $semantic_keywords );
			
			$response = $this->openai_client->generate_content( $prompt, array(
				'max_completion_tokens' => 1000,
				'temperature' => 0.7,
			) );

			return $this->parse_semantic_response( $response );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Build content gap analysis prompt.
	 *
	 * @param string $topic Main topic.
	 * @param string $target_keyword Target keyword.
	 * @param array<string> $competitor_urls Competitor URLs.
	 * @return string
	 */
	private function build_content_gap_prompt( string $topic, string $target_keyword, array $competitor_urls ): string {
		$competitor_info = '';
		if ( ! empty( $competitor_urls ) ) {
			$competitor_info = "\n\nCompetitor URLs to analyze:\n" . implode( "\n", $competitor_urls );
		}

		return sprintf(
			'Analizza le lacune di contenuto per il topic "%s" con keyword target "%s".%s

Fornisci un\'analisi dettagliata che includa:

1. **Sottotemi mancanti** - Argomenti correlati che potrebbero essere trattati
2. **Domande frequenti** - FAQ che gli utenti potrebbero avere
3. **Angoli unici** - Approcci originali per trattare l\'argomento
4. **Contenuti long-tail** - Keyword a coda lunga da considerare
5. **Formati di contenuto** - Tipi di contenuto che potrebbero funzionare meglio
6. **Suggerimenti pratici** - Azioni concrete per migliorare il contenuto

Rispondi in formato JSON con questa struttura:
{
  "missing_subtopics": ["sottotema1", "sottotema2"],
  "faq_suggestions": ["domanda1", "domanda2"],
  "unique_angles": ["angolo1", "angolo2"],
  "long_tail_keywords": ["keyword1", "keyword2"],
  "content_formats": ["formato1", "formato2"],
  "practical_tips": ["suggerimento1", "suggerimento2"],
  "content_score": 75,
  "improvement_priority": "high|medium|low"
}',
			$topic,
			$target_keyword,
			$competitor_info
		);
	}

	/**
	 * Build competitor analysis prompt.
	 *
	 * @param string $content Competitor content.
	 * @param string $target_keyword Target keyword.
	 * @return string
	 */
	private function build_competitor_analysis_prompt( string $content, string $target_keyword ): string {
		return sprintf(
			'Analizza questo contenuto del competitor per la keyword "%s":

%s

Fornisci un\'analisi dettagliata che includa:

1. **Punti di forza** - Cosa fa bene questo contenuto
2. **Punti deboli** - Aree di miglioramento
3. **Opportunità** - Cosa puoi fare meglio
4. **Struttura** - Come è organizzato il contenuto
5. **Keyword density** - Come usa le keyword
6. **Suggerimenti** - Consigli per superare questo competitor

Rispondi in formato JSON con questa struttura:
{
  "strengths": ["punto1", "punto2"],
  "weaknesses": ["debolezza1", "debolezza2"],
  "opportunities": ["opportunità1", "opportunità2"],
  "structure_analysis": "analisi struttura",
  "keyword_usage": "analisi keyword",
  "suggestions": ["suggerimento1", "suggerimento2"],
  "overall_score": 80,
  "competitiveness": "high|medium|low"
}',
			$target_keyword,
			wp_strip_all_tags( $content )
		);
	}

	/**
	 * Build content suggestions prompt.
	 *
	 * @param string $content Current content.
	 * @param string $target_keyword Target keyword.
	 * @param array<string, mixed> $context Content context.
	 * @return string
	 */
	private function build_content_suggestions_prompt( string $content, string $target_keyword, array $context ): string {
		$context_info = '';
		if ( ! empty( $context ) ) {
			$context_info = "\n\nContesto del contenuto:\n";
			foreach ( $context as $key => $value ) {
				$context_info .= "- {$key}: {$value}\n";
			}
		}

		return sprintf(
			'Analizza questo contenuto e fornisci suggerimenti per migliorarlo per la keyword "%s":

%s%s

Fornisci suggerimenti dettagliati che includano:

1. **Miglioramenti strutturali** - Come riorganizzare il contenuto
2. **Ottimizzazioni keyword** - Come usare meglio le keyword
3. **Aggiunte di contenuto** - Cosa aggiungere per migliorare
4. **Suggerimenti di formattazione** - Come formattare meglio
5. **Call-to-action** - Suggerimenti per CTA
6. **Meta ottimizzazioni** - Suggerimenti per title e description

Rispondi in formato JSON con questa struttura:
{
  "structural_improvements": ["miglioramento1", "miglioramento2"],
  "keyword_optimizations": ["ottimizzazione1", "ottimizzazione2"],
  "content_additions": ["aggiunta1", "aggiunta2"],
  "formatting_suggestions": ["formattazione1", "formattazione2"],
  "cta_suggestions": ["cta1", "cta2"],
  "meta_suggestions": {
    "title": "suggerimento title",
    "description": "suggerimento description"
  },
  "overall_score": 75,
  "priority_actions": ["azione1", "azione2"]
}',
			$target_keyword,
			wp_strip_all_tags( $content ),
			$context_info
		);
	}

	/**
	 * Build readability optimization prompt.
	 *
	 * @param string $content Content to optimize.
	 * @param string $target_audience Target audience.
	 * @return string
	 */
	private function build_readability_prompt( string $content, string $target_audience ): string {
		return sprintf(
			'Ottimizza la leggibilità di questo contenuto per il pubblico "%s":

%s

Fornisci suggerimenti per migliorare:

1. **Struttura delle frasi** - Frasi troppo lunghe o complesse
2. **Vocabolario** - Parole troppo tecniche o difficili
3. **Paragrafi** - Paragrafi troppo lunghi
4. **Transizioni** - Collegamenti tra i paragrafi
5. **Formattazione** - Uso di elenchi, sottotitoli, etc.
6. **Tono** - Adattamento al pubblico target

Rispondi in formato JSON con questa struttura:
{
  "sentence_issues": ["problema1", "problema2"],
  "vocabulary_suggestions": ["suggerimento1", "suggerimento2"],
  "paragraph_improvements": ["miglioramento1", "miglioramento2"],
  "transition_suggestions": ["transizione1", "transizione2"],
  "formatting_tips": ["formattazione1", "formattazione2"],
  "tone_adjustments": ["aggiustamento1", "aggiustamento2"],
  "readability_score": 75,
  "target_audience": "%s"
}',
			$target_audience,
			wp_strip_all_tags( $content ),
			$target_audience
		);
	}

	/**
	 * Build semantic SEO optimization prompt.
	 *
	 * @param string $content Content to optimize.
	 * @param string $target_keyword Target keyword.
	 * @param array<string> $semantic_keywords Related keywords.
	 * @return string
	 */
	private function build_semantic_prompt( string $content, string $target_keyword, array $semantic_keywords ): string {
		$semantic_list = ! empty( $semantic_keywords ) ? implode( ', ', $semantic_keywords ) : 'nessuna';

		return sprintf(
			'Ottimizza questo contenuto per SEO semantico con keyword target "%s" e keyword semantiche: %s

%s

Fornisci suggerimenti per:

1. **Keyword semantiche** - Come integrare meglio le keyword correlate
2. **Topic clusters** - Come creare cluster di argomenti
3. **Entity optimization** - Come ottimizzare per entità
4. **Context enrichment** - Come arricchire il contesto
5. **LSI keywords** - Keyword semanticamente correlate
6. **Content depth** - Come approfondire gli argomenti

Rispondi in formato JSON con questa struttura:
{
  "semantic_integrations": ["integrazione1", "integrazione2"],
  "topic_cluster_suggestions": ["cluster1", "cluster2"],
  "entity_optimizations": ["entità1", "entità2"],
  "context_enrichments": ["arricchimento1", "arricchimento2"],
  "lsi_keywords": ["keyword1", "keyword2"],
  "depth_improvements": ["approfondimento1", "approfondimento2"],
  "semantic_score": 75,
  "optimization_priority": "high|medium|low"
}',
			$target_keyword,
			$semantic_list,
			wp_strip_all_tags( $content )
		);
	}

	/**
	 * Parse content gap analysis response.
	 *
	 * @param string $response AI response.
	 * @return array<string, mixed>
	 */
	private function parse_content_gap_response( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}

	/**
	 * Parse competitor analysis response.
	 *
	 * @param string $response AI response.
	 * @return array<string, mixed>
	 */
	private function parse_competitor_response( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}

	/**
	 * Parse content suggestions response.
	 *
	 * @param string $response AI response.
	 * @return array<string, mixed>
	 */
	private function parse_content_suggestions_response( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}

	/**
	 * Parse readability optimization response.
	 *
	 * @param string $response AI response.
	 * @return array<string, mixed>
	 */
	private function parse_readability_response( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}

	/**
	 * Parse semantic SEO response.
	 *
	 * @param string $response AI response.
	 * @return array<string, mixed>
	 */
	private function parse_semantic_response( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}

	/**
	 * Fetch content from URL.
	 *
	 * @param string $url URL to fetch.
	 * @return string|null
	 */
	private function fetch_url_content( string $url ): ?string {
		$response = wp_remote_get( $url, array(
			'timeout' => 30,
			'user-agent' => 'FP SEO Performance Bot/1.0',
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return null;
		}

		// Extract text content from HTML
		$dom = new \DOMDocument();
		@$dom->loadHTML( $body );
		$xpath = new \DOMXPath( $dom );
		
		// Remove script and style elements
		$scripts = $xpath->query( '//script | //style' );
		foreach ( $scripts as $script ) {
			$script->parentNode->removeChild( $script );
		}

		// Get text content
		$text = $dom->textContent;
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Gather content context.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function gather_content_context( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$context = array(
			'post_type' => get_post_type( $post_id ),
			'categories' => array(),
			'tags' => array(),
			'word_count' => str_word_count( strip_tags( $post->post_content ) ),
		);

		// Categories
		$categories = get_the_category( $post_id );
		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$context['categories'][] = $category->name;
			}
		}

		// Tags
		$tags = get_the_tags( $post_id );
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$context['tags'][] = $tag->name;
			}
		}

		return $context;
	}

	/**
	 * Render Content Optimizer page.
	 */
	public function render_optimizer_page(): void {
		?>
		<div class="wrap fp-seo-optimizer-wrap">
			<h1><?php esc_html_e( 'AI Content Optimizer', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Ottimizza i tuoi contenuti con l\'intelligenza artificiale per migliorare ranking e conversioni', 'fp-seo-performance' ); ?></p>

			<!-- Sezione introduttiva con guida -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">🚀</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Come funziona l\'AI Content Optimizer?', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'Questo strumento utilizza l\'intelligenza artificiale per analizzare e ottimizzare i tuoi contenuti in 5 modi diversi. Seleziona una delle funzionalità qui sotto per iniziare:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li><strong>Content Gap Analysis:</strong> Scopri quali argomenti mancano rispetto ai competitor</li>
						<li><strong>Competitor Analysis:</strong> Analizza i contenuti della concorrenza per superarli</li>
						<li><strong>Content Suggestions:</strong> Ricevi suggerimenti specifici per migliorare i tuoi post</li>
						<li><strong>Readability Optimization:</strong> Rendi i tuoi contenuti più leggibili e coinvolgenti</li>
						<li><strong>Semantic SEO:</strong> Ottimizza per SEO semantico e keyword correlate</li>
					</ul>
				</div>
			</div>
			
			<div class="fp-seo-optimizer-dashboard">
				<div class="fp-seo-optimizer-tabs">
					<button class="fp-seo-tab-button active" data-tab="content-gaps" title="<?php esc_attr_e( 'Analizza le lacune nei tuoi contenuti', 'fp-seo-performance' ); ?>">
						<span class="fp-seo-tab-icon">🔍</span>
						<?php esc_html_e( 'Content Gap Analysis', 'fp-seo-performance' ); ?>
					</button>
					<button class="fp-seo-tab-button" data-tab="competitor-analysis" title="<?php esc_attr_e( 'Studia i contenuti dei competitor', 'fp-seo-performance' ); ?>">
						<span class="fp-seo-tab-icon">🎯</span>
						<?php esc_html_e( 'Competitor Analysis', 'fp-seo-performance' ); ?>
					</button>
					<button class="fp-seo-tab-button" data-tab="content-suggestions" title="<?php esc_attr_e( 'Ricevi suggerimenti personalizzati', 'fp-seo-performance' ); ?>">
						<span class="fp-seo-tab-icon">💡</span>
						<?php esc_html_e( 'Content Suggestions', 'fp-seo-performance' ); ?>
					</button>
					<button class="fp-seo-tab-button" data-tab="readability" title="<?php esc_attr_e( 'Migliora la leggibilità del testo', 'fp-seo-performance' ); ?>">
						<span class="fp-seo-tab-icon">📖</span>
						<?php esc_html_e( 'Readability Optimization', 'fp-seo-performance' ); ?>
					</button>
					<button class="fp-seo-tab-button" data-tab="semantic-seo" title="<?php esc_attr_e( 'Ottimizza per SEO semantico', 'fp-seo-performance' ); ?>">
						<span class="fp-seo-tab-icon">🧠</span>
						<?php esc_html_e( 'Semantic SEO', 'fp-seo-performance' ); ?>
					</button>
				</div>

				<div class="fp-seo-tab-content active" id="content-gaps">
					<div class="fp-seo-tab-help">
						<h2>🔍 <?php esc_html_e( 'Content Gap Analysis', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-help-text">
							<?php esc_html_e( 'Scopri quali argomenti e sottotemi mancano nei tuoi contenuti rispetto alla concorrenza. L\'AI analizzerà il topic e suggerirà:', 'fp-seo-performance' ); ?>
						</p>
						<ul class="fp-seo-help-list">
							<li>✓ Sottotemi mancanti da trattare</li>
							<li>✓ Domande frequenti (FAQ) degli utenti</li>
							<li>✓ Angoli unici per differenziarti</li>
							<li>✓ Keyword long-tail da targetizzare</li>
						</ul>
					</div>

					<form id="fp-seo-content-gaps-form">
						<div class="fp-seo-inline-notice" data-fp-seo-notice hidden role="status" aria-live="polite"></div>
						<div class="fp-seo-form-group">
							<label for="gap-topic">
								<?php esc_html_e( 'Argomento Principale', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'L\'argomento generale del contenuto che vuoi analizzare. Es: WordPress SEO, Marketing Digitale, ecc.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="gap-topic" name="topic" placeholder="es. WordPress SEO, Marketing Digitale" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Inserisci l\'argomento principale su cui vuoi creare contenuti', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="gap-keyword">
								<?php esc_html_e( 'Keyword Target', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'La keyword principale che vuoi rankare su Google. Sii specifico!', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="gap-keyword" name="keyword" placeholder="es. wordpress seo plugin 2025" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'La parola chiave specifica che vuoi posizionare sui motori di ricerca', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="gap-competitors">
								<?php esc_html_e( 'URL Competitor (uno per riga)', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Inserisci gli URL dei tuoi principali competitor che rankano per questa keyword. L\'AI analizzerà i loro contenuti per trovare gap.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<textarea id="gap-competitors" name="competitors" rows="4" placeholder="https://www.competitor1.com/article&#10;https://www.competitor2.com/guide&#10;https://www.competitor3.com/tutorial"></textarea>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Opzionale: Inserisci fino a 5 URL dei competitor per un\'analisi più approfondita', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-example-box">
							<strong>📋 Esempio pratico:</strong><br>
							<strong>Argomento:</strong> SEO per WordPress<br>
							<strong>Keyword:</strong> come ottimizzare wordpress per seo<br>
							<strong>Competitor:</strong> https://yoast.com/wordpress-seo/
						</div>

						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Analizza Lacune di Contenuto', 'fp-seo-performance' ); ?>
						</button>
					</form>
					<div id="fp-seo-gaps-results" class="fp-seo-results"></div>
				</div>

				<div class="fp-seo-tab-content" id="competitor-analysis">
					<div class="fp-seo-tab-help">
						<h2>🎯 <?php esc_html_e( 'Competitor Analysis', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-help-text">
							<?php esc_html_e( 'Analizza in profondità un articolo della concorrenza per scoprire i suoi punti di forza e debolezza. L\'AI ti dirà:', 'fp-seo-performance' ); ?>
						</p>
						<ul class="fp-seo-help-list">
							<li>✓ Cosa fa bene il competitor (punti di forza)</li>
							<li>✓ Dove puoi fare meglio (opportunità)</li>
							<li>✓ Come struttura il contenuto</li>
							<li>✓ Come usa le keyword strategicamente</li>
						</ul>
					</div>

					<form id="fp-seo-competitor-form">
						<div class="fp-seo-inline-notice" data-fp-seo-notice hidden role="status" aria-live="polite"></div>
						<div class="fp-seo-form-group">
							<label for="competitor-url">
								<?php esc_html_e( 'URL Competitor', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'L\'URL completo dell\'articolo del competitor che vuoi analizzare. Deve essere un URL pubblico.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="url" id="competitor-url" name="url" placeholder="https://www.competitor.com/miglior-articolo" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Inserisci l\'URL completo dell\'articolo competitor che rankano meglio di te', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="competitor-keyword">
								<?php esc_html_e( 'Keyword Target', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'La keyword per cui questo competitor rankano. Serve per capire come ottimizzano il contenuto.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="competitor-keyword" name="keyword" placeholder="es. migliore plugin seo wordpress" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'La keyword principale per cui il competitor si posiziona', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-example-box">
							<strong>📋 Esempio pratico:</strong><br>
							<strong>URL:</strong> https://yoast.com/wordpress-seo/<br>
							<strong>Keyword:</strong> wordpress seo plugin
						</div>

						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-chart-line"></span>
							<?php esc_html_e( 'Analizza Competitor', 'fp-seo-performance' ); ?>
						</button>
					</form>
					<div id="fp-seo-competitor-results" class="fp-seo-results"></div>
				</div>

				<div class="fp-seo-tab-content" id="content-suggestions">
					<div class="fp-seo-tab-help">
						<h2>💡 <?php esc_html_e( 'Content Suggestions', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-help-text">
							<?php esc_html_e( 'Ricevi suggerimenti personalizzati per migliorare un tuo articolo esistente. L\'AI analizzerà il contenuto e ti suggerirà:', 'fp-seo-performance' ); ?>
						</p>
						<ul class="fp-seo-help-list">
							<li>✓ Miglioramenti strutturali (headings, paragrafi)</li>
							<li>✓ Ottimizzazioni per le keyword</li>
							<li>✓ Contenuti da aggiungere per completezza</li>
							<li>✓ Suggerimenti per title e meta description</li>
						</ul>
					</div>

					<form id="fp-seo-suggestions-form">
						<div class="fp-seo-inline-notice" data-fp-seo-notice hidden role="status" aria-live="polite"></div>
						<div class="fp-seo-form-group">
							<label for="suggestions-post">
								<?php esc_html_e( 'Seleziona Articolo', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Scegli un articolo o pagina esistente del tuo sito da analizzare e migliorare.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<select id="suggestions-post" name="post_id" required>
								<option value=""><?php esc_html_e( '-- Seleziona un articolo --', 'fp-seo-performance' ); ?></option>
								<?php
								$posts = get_posts( array( 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC' ) );
								foreach ( $posts as $post ) {
									$word_count = str_word_count( strip_tags( $post->post_content ) );
									echo '<option value="' . esc_attr( $post->ID ) . '">' . esc_html( $post->post_title ) . ' (' . $word_count . ' parole)</option>';
								}
								?>
							</select>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Seleziona uno dei tuoi ultimi 50 articoli pubblicati', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="suggestions-keyword">
								<?php esc_html_e( 'Keyword Target', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'La keyword principale per cui vuoi ottimizzare questo articolo.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="suggestions-keyword" name="keyword" placeholder="es. ottimizzazione seo wordpress" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'La parola chiave su cui vuoi focalizzare l\'ottimizzazione', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-example-box">
							<strong>💡 Suggerimento:</strong> Scegli un articolo con basso traffico o che vuoi migliorare per rankare meglio.
						</div>

						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-lightbulb"></span>
							<?php esc_html_e( 'Genera Suggerimenti', 'fp-seo-performance' ); ?>
						</button>
					</form>
					<div id="fp-seo-suggestions-results" class="fp-seo-results"></div>
				</div>

				<div class="fp-seo-tab-content" id="readability">
					<div class="fp-seo-tab-help">
						<h2>📖 <?php esc_html_e( 'Readability Optimization', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-help-text">
							<?php esc_html_e( 'Migliora la leggibilità dei tuoi contenuti per renderli più comprensibili e coinvolgenti. L\'AI analizzerà:', 'fp-seo-performance' ); ?>
						</p>
						<ul class="fp-seo-help-list">
							<li>✓ Frasi troppo lunghe o complesse</li>
							<li>✓ Vocabolario da semplificare</li>
							<li>✓ Struttura dei paragrafi</li>
							<li>✓ Uso di transizioni e connettivi</li>
						</ul>
					</div>

					<form id="fp-seo-readability-form">
						<div class="fp-seo-inline-notice" data-fp-seo-notice hidden role="status" aria-live="polite"></div>
						<div class="fp-seo-form-group">
							<label for="readability-content">
								<?php esc_html_e( 'Contenuto da Ottimizzare', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Incolla qui il testo che vuoi rendere più leggibile. Può essere un paragrafo, una sezione o un intero articolo.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<textarea id="readability-content" name="content" rows="12" placeholder="Incolla qui il tuo testo...&#10;&#10;Esempio: 'Questo è un paragrafo molto lungo che contiene diverse informazioni complesse che potrebbero essere difficili da comprendere per il lettore medio...'" required></textarea>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Incolla il testo che vuoi analizzare e migliorare (min. 100 parole consigliato)', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="readability-audience">
								<?php esc_html_e( 'Pubblico Target', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Seleziona il tipo di pubblico a cui è destinato il contenuto. L\'AI adatterà i suggerimenti di conseguenza.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<select id="readability-audience" name="audience">
								<option value="general"><?php esc_html_e( '🌍 Pubblico Generale (consigliato)', 'fp-seo-performance' ); ?></option>
								<option value="beginner"><?php esc_html_e( '🆕 Principianti (linguaggio semplice)', 'fp-seo-performance' ); ?></option>
								<option value="technical"><?php esc_html_e( '🔧 Pubblico Tecnico (termini specialistici ok)', 'fp-seo-performance' ); ?></option>
								<option value="expert"><?php esc_html_e( '👨‍🎓 Esperti (linguaggio avanzato)', 'fp-seo-performance' ); ?></option>
							</select>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Scegli il livello di expertise del tuo pubblico', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-example-box">
							<strong>💡 Suggerimento:</strong> Per il web, è meglio scrivere per un pubblico generale (livello 8ª elementare). Frasi brevi, paragrafi corti, linguaggio chiaro.
						</div>

						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-book-alt"></span>
							<?php esc_html_e( 'Ottimizza Leggibilità', 'fp-seo-performance' ); ?>
						</button>
					</form>
					<div id="fp-seo-readability-results" class="fp-seo-results"></div>
				</div>

				<div class="fp-seo-tab-content" id="semantic-seo">
					<div class="fp-seo-tab-help">
						<h2>🧠 <?php esc_html_e( 'Semantic SEO Optimization', 'fp-seo-performance' ); ?></h2>
						<p class="fp-seo-help-text">
							<?php esc_html_e( 'Ottimizza il tuo contenuto per il SEO semantico, aiutando Google a capire meglio il contesto. L\'AI ti suggerirà:', 'fp-seo-performance' ); ?>
						</p>
						<ul class="fp-seo-help-list">
							<li>✓ Keyword semanticamente correlate (LSI keywords)</li>
							<li>✓ Topic cluster da creare</li>
							<li>✓ Entità da menzionare</li>
							<li>✓ Come approfondire gli argomenti</li>
						</ul>
					</div>

					<form id="fp-seo-semantic-form">
						<div class="fp-seo-inline-notice" data-fp-seo-notice hidden role="status" aria-live="polite"></div>
						<div class="fp-seo-form-group">
							<label for="semantic-content">
								<?php esc_html_e( 'Contenuto da Ottimizzare', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Il contenuto esistente che vuoi arricchire con keyword semantiche e concetti correlati.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<textarea id="semantic-content" name="content" rows="12" placeholder="Incolla qui il tuo contenuto...&#10;&#10;L'AI analizzerà il testo e suggerirà keyword correlate, entità e concetti da aggiungere per migliorare il SEO semantico." required></textarea>
							<p class="fp-seo-field-help"><?php esc_html_e( 'Incolla l\'articolo o la sezione che vuoi ottimizzare semanticamente', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="semantic-keyword">
								<?php esc_html_e( 'Keyword Principale', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'La keyword principale del contenuto. L\'AI cercherà keyword correlate semanticamente.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="semantic-keyword" name="keyword" placeholder="es. wordpress seo 2025" required>
							<p class="fp-seo-field-help"><?php esc_html_e( 'La keyword target principale del tuo contenuto', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-form-group">
							<label for="semantic-keywords">
								<?php esc_html_e( 'Keyword Correlate (separate da virgola)', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Opzionale: Keyword secondarie correlate. L\'AI le userà per suggerimenti più precisi.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</label>
							<input type="text" id="semantic-keywords" name="semantic_keywords" placeholder="plugin seo, ottimizzazione wordpress, ranking google, meta tag">
							<p class="fp-seo-field-help"><?php esc_html_e( 'Opzionale: Aggiungi keyword correlate che conosci già (max 5)', 'fp-seo-performance' ); ?></p>
						</div>

						<div class="fp-seo-example-box">
							<strong>📋 Esempio pratico:</strong><br>
							<strong>Keyword principale:</strong> wordpress seo<br>
							<strong>Keyword correlate:</strong> ottimizzazione motori ricerca, plugin seo wordpress, posizionamento google
						</div>

						<button type="submit" class="button button-primary button-hero">
							<span class="dashicons dashicons-networking"></span>
							<?php esc_html_e( 'Ottimizza SEO Semantico', 'fp-seo-performance' ); ?>
						</button>
					</form>
					<div id="fp-seo-semantic-results" class="fp-seo-results"></div>
				</div>
			</div>
		</div>

		<style>
		/* Container principale */
		.fp-seo-optimizer-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-optimizer-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		/* Banner introduttivo */
		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 8px 0;
			font-size: 14px;
			opacity: 0.9;
		}
		
		/* Tabs */
		.fp-seo-optimizer-dashboard {
			max-width: 1200px;
		}
		
		.fp-seo-optimizer-tabs {
			display: flex;
			gap: 8px;
			border-bottom: 2px solid #e5e7eb;
			margin-bottom: 0;
			flex-wrap: wrap;
		}
		
		.fp-seo-tab-button {
			padding: 14px 24px;
			background: #f9fafb;
			border: 2px solid #e5e7eb;
			border-bottom: none;
			cursor: pointer;
			font-size: 14px;
			font-weight: 600;
			color: #6b7280;
			border-radius: 8px 8px 0 0;
			transition: all 0.3s ease;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.fp-seo-tab-icon {
			font-size: 18px;
		}
		
		.fp-seo-tab-button:hover {
			background: #f3f4f6;
			color: #374151;
			transform: translateY(-2px);
		}
		
		.fp-seo-tab-button.active {
			background: #fff;
			border-color: #2563eb;
			border-bottom-color: #fff;
			color: #2563eb;
			margin-bottom: -2px;
		}
		
		/* Tab content */
		.fp-seo-tab-content {
			display: none;
			background: #fff;
			padding: 32px;
			border: 2px solid #e5e7eb;
			border-top: none;
			border-radius: 0 0 12px 12px;
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
		}
		
		.fp-seo-tab-content.active {
			display: block;
		}

		/* Tab help section */
		.fp-seo-tab-help {
			background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
			border-left: 4px solid #0ea5e9;
			padding: 24px;
			margin-bottom: 32px;
			border-radius: 8px;
		}

		.fp-seo-tab-help h2 {
			margin: 0 0 12px;
			font-size: 22px;
			color: #0c4a6e;
		}

		.fp-seo-help-text {
			margin: 0 0 16px;
			color: #075985;
			font-size: 15px;
			line-height: 1.6;
		}

		.fp-seo-help-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-help-list li {
			padding: 6px 0;
			color: #0c4a6e;
			font-size: 14px;
		}
		
		/* Form groups */
		.fp-seo-form-group {
			margin-bottom: 28px;
		}
		
		.fp-seo-form-group label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			font-size: 15px;
			color: #374151;
		}

		.fp-seo-tooltip {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			font-size: 14px;
			opacity: 0.7;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip:hover {
			opacity: 1;
		}

		.fp-seo-field-help {
			margin: 8px 0 0;
			font-size: 13px;
			color: #6b7280;
			font-style: italic;
		}
		
		.fp-seo-form-group input,
		.fp-seo-form-group select,
		.fp-seo-form-group textarea {
			width: 100%;
			padding: 12px 16px;
			border: 2px solid #e5e7eb;
			border-radius: 8px;
			font-size: 14px;
			transition: all 0.3s ease;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}

		.fp-seo-form-group input:focus,
		.fp-seo-form-group select:focus,
		.fp-seo-form-group textarea:focus {
			outline: none;
			border-color: #2563eb;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
		}

		.fp-seo-form-group .fp-seo-field-error {
			border-color: #dc2626 !important;
			box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1) !important;
		}

		.fp-seo-form-group textarea {
			resize: vertical;
			min-height: 120px;
		}

		/* Example box */
		.fp-seo-example-box {
			background: #fef3c7;
			border-left: 4px solid #f59e0b;
			padding: 16px 20px;
			border-radius: 8px;
			margin: 24px 0;
			font-size: 14px;
			color: #78350f;
			line-height: 1.8;
		}

		/* Buttons */
		.button-hero {
			font-size: 16px !important;
			padding: 14px 32px !important;
			height: auto !important;
			display: inline-flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin-top: 8px;
		}

		.button-hero.is-loading,
		.button.is-loading {
			opacity: 0.6;
			pointer-events: none;
		}

		.button-hero.is-loading .dashicons:before,
		.button.is-loading .dashicons:before {
			content: "\f463";
			animation: fp-seo-spin 1s linear infinite;
		}

		@keyframes fp-seo-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}

		.button-hero .dashicons {
			font-size: 20px;
			width: 20px;
			height: 20px;
		}
		
		/* Inline notices */
		.fp-seo-inline-notice {
			display: none;
			margin-bottom: 20px;
			padding: 14px 18px;
			border-radius: 8px;
			font-size: 14px;
			font-weight: 600;
		}

		.fp-seo-inline-notice.is-success {
			display: block;
			background: #ecfdf5;
			color: #065f46;
			border: 1px solid #34d399;
		}

		.fp-seo-inline-notice.is-error {
			display: block;
			background: #fef2f2;
			color: #991b1b;
			border: 1px solid #fca5a5;
		}

		.fp-seo-inline-notice.is-warning {
			display: block;
			background: #fffbeb;
			color: #92400e;
			border: 1px solid #fcd34d;
		}

		/* Results */
		.fp-seo-results {
			margin-top: 32px;
			padding: 24px;
			background: #f9fafb;
			border-radius: 12px;
			border: 2px solid #e5e7eb;
			display: none;
		}
		
		.fp-seo-results.show {
			display: block;
			animation: slideIn 0.3s ease;
		}

		@keyframes slideIn {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.fp-seo-result-item {
			background: #fff;
			padding: 20px;
			margin-bottom: 16px;
			border-radius: 8px;
			border-left: 4px solid #2563eb;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		}
		
		.fp-seo-result-title {
			font-weight: 700;
			font-size: 16px;
			margin-bottom: 12px;
			color: #2563eb;
		}
		
		.fp-seo-result-content {
			color: #4b5563;
			line-height: 1.7;
		}

		.fp-seo-result-content p {
			margin: 12px 0;
		}

		.fp-seo-result-content strong {
			color: #1f2937;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			const optimizerNonce = '<?php echo wp_create_nonce( 'fp_seo_optimizer_nonce' ); ?>';
			const messages = <?php echo wp_json_encode(
				array(
					'analysisComplete'           => __( 'Analisi completata! Controlla i risultati qui sotto.', 'fp-seo-performance' ),
					'competitorComplete'         => __( 'Analisi del competitor completata. Controlla il riepilogo qui sotto.', 'fp-seo-performance' ),
					'suggestionsComplete'        => __( 'Suggerimenti generati con successo. Scorri per i dettagli.', 'fp-seo-performance' ),
					'readabilityComplete'        => __( 'Ottimizzazione della leggibilità completata.', 'fp-seo-performance' ),
					'semanticComplete'           => __( 'Analisi SEO semantica completata.', 'fp-seo-performance' ),
					'topicRequired'              => __( 'Inserisci un argomento principale.', 'fp-seo-performance' ),
					'keywordRequired'            => __( 'Inserisci una keyword target.', 'fp-seo-performance' ),
					'competitorUrlRequired'      => __( 'Inserisci l\'URL del competitor da analizzare.', 'fp-seo-performance' ),
					'competitorKeywordRequired'  => __( 'Inserisci la keyword per l\'analisi del competitor.', 'fp-seo-performance' ),
					'postRequired'               => __( 'Seleziona un contenuto da analizzare.', 'fp-seo-performance' ),
					'contentRequired'            => __( 'Inserisci del contenuto da analizzare.', 'fp-seo-performance' ),
					'contentTooShort'            => __( 'Il contenuto deve contenere almeno 50 caratteri.', 'fp-seo-performance' ),
					'semanticKeywordRequired'    => __( 'Inserisci la keyword principale per l\'ottimizzazione semantica.', 'fp-seo-performance' ),
					'invalidUrl'                 => __( 'L\'URL "%s" non è valido. Usa un indirizzo completo che inizi con http o https.', 'fp-seo-performance' ),
					'requestFailed'              => __( 'Richiesta fallita, riprova tra qualche istante.', 'fp-seo-performance' ),
					'unexpectedError'            => __( 'Si è verificato un errore inatteso. Riprova più tardi.', 'fp-seo-performance' ),
					'validationGeneric'          => __( 'Correggi i campi evidenziati e riprova.', 'fp-seo-performance' ),
					'noneLabel'                  => __( 'Nessun dato disponibile', 'fp-seo-performance' ),
					'contentGapTitle'            => __( 'Risultati Content Gap Analysis', 'fp-seo-performance' ),
					'competitorTitle'            => __( 'Risultati analisi competitor', 'fp-seo-performance' ),
					'suggestionsTitle'           => __( 'Suggerimenti contenuto', 'fp-seo-performance' ),
					'readabilityTitle'           => __( 'Ottimizzazione leggibilità', 'fp-seo-performance' ),
					'semanticTitle'              => __( 'Ottimizzazione SEO semantica', 'fp-seo-performance' ),
					'missingSubtopicsLabel'      => __( 'Sottotemi mancanti', 'fp-seo-performance' ),
					'faqSuggestionsLabel'        => __( 'Domande frequenti', 'fp-seo-performance' ),
					'uniqueAnglesLabel'          => __( 'Angoli unici', 'fp-seo-performance' ),
					'longTailLabel'              => __( 'Keyword long-tail', 'fp-seo-performance' ),
					'contentScoreLabel'          => __( 'Punteggio contenuto', 'fp-seo-performance' ),
					'strengthsLabel'             => __( 'Punti di forza', 'fp-seo-performance' ),
					'weaknessesLabel'            => __( 'Punti deboli', 'fp-seo-performance' ),
					'opportunitiesLabel'         => __( 'Opportunità', 'fp-seo-performance' ),
					'structureLabel'             => __( 'Struttura', 'fp-seo-performance' ),
					'keywordUsageLabel'          => __( 'Uso delle keyword', 'fp-seo-performance' ),
					'overallScoreLabel'          => __( 'Punteggio complessivo', 'fp-seo-performance' ),
					'structuralLabel'            => __( 'Miglioramenti strutturali', 'fp-seo-performance' ),
					'keywordOptimizationsLabel'  => __( 'Ottimizzazioni keyword', 'fp-seo-performance' ),
					'contentAdditionsLabel'      => __( 'Aggiunte di contenuto', 'fp-seo-performance' ),
					'metaTitleLabel'             => __( 'Meta title suggerito', 'fp-seo-performance' ),
					'metaDescriptionLabel'       => __( 'Meta description suggerita', 'fp-seo-performance' ),
					'priorityActionsLabel'       => __( 'Azioni prioritarie', 'fp-seo-performance' ),
					'sentenceIssuesLabel'        => __( 'Problemi nelle frasi', 'fp-seo-performance' ),
					'vocabularyLabel'            => __( 'Suggerimenti di vocabolario', 'fp-seo-performance' ),
					'paragraphLabel'             => __( 'Miglioramenti paragrafi', 'fp-seo-performance' ),
					'transitionLabel'            => __( 'Transizioni', 'fp-seo-performance' ),
					'formattingLabel'            => __( 'Suggerimenti di formattazione', 'fp-seo-performance' ),
					'toneLabel'                  => __( 'Regolazioni tono', 'fp-seo-performance' ),
					'readabilityScoreLabel'      => __( 'Punteggio leggibilità', 'fp-seo-performance' ),
					'semanticIntegrationsLabel'  => __( 'Integrazioni semantiche', 'fp-seo-performance' ),
					'topicClustersLabel'         => __( 'Topic cluster', 'fp-seo-performance' ),
					'entityLabel'                => __( 'Ottimizzazioni entità', 'fp-seo-performance' ),
					'contextLabel'               => __( 'Arricchimento contesto', 'fp-seo-performance' ),
					'lsiLabel'                   => __( 'Keyword correlate (LSI)', 'fp-seo-performance' ),
					'depthLabel'                 => __( 'Approfondimenti consigliati', 'fp-seo-performance' ),
					'semanticScoreLabel'         => __( 'Punteggio semantico', 'fp-seo-performance' ),
				)
			); ?>;
			const $gapsResults = $('#fp-seo-gaps-results');
			const $competitorResults = $('#fp-seo-competitor-results');
			const $suggestionsResults = $('#fp-seo-suggestions-results');
			const $readabilityResults = $('#fp-seo-readability-results');
			const $semanticResults = $('#fp-seo-semantic-results');

			function speak(message, politeness) {
				if (!message) {
					return;
				}

				if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
					window.wp.a11y.speak(message, politeness || 'polite');
				}
			}

			function showNotice($form, message, type) {
				const $notice = $form.find('[data-fp-seo-notice]');
				if (!$notice.length) {
					return;
				}

				const level = type === 'error' ? 'is-error' : (type === 'warning' ? 'is-warning' : 'is-success');
				$notice
					.removeClass('is-error is-success is-warning')
					.addClass(level)
					.text(message)
					.attr('hidden', false);

				speak(message, type === 'error' ? 'assertive' : 'polite');
			}

			function clearNotice($form) {
				const $notice = $form.find('[data-fp-seo-notice]');
				if ($notice.length) {
					$notice.removeClass('is-error is-success is-warning').text('').attr('hidden', true);
				}
			}

			function setLoading($button, isLoading) {
				if (!$button || !$button.length) {
					return;
				}

				if (isLoading) {
					$button.prop('disabled', true).addClass('is-loading');
				} else {
					$button.prop('disabled', false).removeClass('is-loading');
				}
			}

			function setFieldError($field, hasError) {
				if (!$field || !$field.length) {
					return;
				}

				if (hasError) {
					$field.addClass('fp-seo-field-error').attr('aria-invalid', 'true');
				} else {
					$field.removeClass('fp-seo-field-error').removeAttr('aria-invalid');
				}
			}

			function normalize(value) {
				return $.trim(value || '');
			}

			function resetResults($target) {
				if ($target && $target.length) {
					$target.removeClass('show').empty();
				}
			}

			function isValidUrl(value) {
				if (!value) {
					return false;
				}

				try {
					const url = new URL(value);
					return url.protocol === 'http:' || url.protocol === 'https:';
				} catch (error) {
					return false;
				}
			}

			function formatList(value) {
				if (Array.isArray(value) && value.length) {
					return value.join(', ');
				}

				if (typeof value === 'string' && value.trim().length) {
					return value.trim();
				}

				return messages.noneLabel;
			}

			function formatScore(value) {
				if (typeof value === 'number') {
					return value;
				}

				if (typeof value === 'string' && value.trim().length) {
					return value.trim();
				}

				return 'N/A';
			}

			function parseError(response, fallback) {
				if (!response) {
					return fallback;
				}

				if (typeof response === 'string' && response.length) {
					return response;
				}

				if (response.data) {
					if (typeof response.data === 'string') {
						return response.data;
					}

					if (response.data.message) {
						return response.data.message;
					}
				}

				if (response.message) {
					return response.message;
				}

				return fallback;
			}

			function displayContentGapsResults(data) {
				const html = [
					'<div class="fp-seo-result-item">',
					'<div class="fp-seo-result-title">' + messages.contentGapTitle + '</div>',
					'<div class="fp-seo-result-content">',
					'<p><strong>' + messages.missingSubtopicsLabel + ':</strong> ' + formatList(data.missing_subtopics) + '</p>',
					'<p><strong>' + messages.faqSuggestionsLabel + ':</strong> ' + formatList(data.faq_suggestions) + '</p>',
					'<p><strong>' + messages.uniqueAnglesLabel + ':</strong> ' + formatList(data.unique_angles) + '</p>',
					'<p><strong>' + messages.longTailLabel + ':</strong> ' + formatList(data.long_tail_keywords) + '</p>',
					'<p><strong>' + messages.contentScoreLabel + ':</strong> ' + formatScore(data.content_score) + '</p>',
					'</div></div>'
				].join('');

				$gapsResults.html(html).addClass('show');
			}

			function displayCompetitorResults(data) {
				const html = [
					'<div class="fp-seo-result-item">',
					'<div class="fp-seo-result-title">' + messages.competitorTitle + '</div>',
					'<div class="fp-seo-result-content">',
					'<p><strong>' + messages.strengthsLabel + ':</strong> ' + formatList(data.strengths) + '</p>',
					'<p><strong>' + messages.weaknessesLabel + ':</strong> ' + formatList(data.weaknesses) + '</p>',
					'<p><strong>' + messages.opportunitiesLabel + ':</strong> ' + formatList(data.opportunities) + '</p>',
					'<p><strong>' + messages.structureLabel + ':</strong> ' + formatList(data.structure_analysis) + '</p>',
					'<p><strong>' + messages.keywordUsageLabel + ':</strong> ' + formatList(data.keyword_usage) + '</p>',
					'<p><strong>' + messages.overallScoreLabel + ':</strong> ' + formatScore(data.overall_score) + '</p>',
					'</div></div>'
				].join('');

				$competitorResults.html(html).addClass('show');
			}

			function displaySuggestionsResults(data) {
				const metaSuggestions = data.meta_suggestions || {};
				const html = [
					'<div class="fp-seo-result-item">',
					'<div class="fp-seo-result-title">' + messages.suggestionsTitle + '</div>',
					'<div class="fp-seo-result-content">',
					'<p><strong>' + messages.structuralLabel + ':</strong> ' + formatList(data.structural_improvements) + '</p>',
					'<p><strong>' + messages.keywordOptimizationsLabel + ':</strong> ' + formatList(data.keyword_optimizations) + '</p>',
					'<p><strong>' + messages.contentAdditionsLabel + ':</strong> ' + formatList(data.content_additions) + '</p>',
					'<p><strong>' + messages.metaTitleLabel + ':</strong> ' + (metaSuggestions.title ? metaSuggestions.title : messages.noneLabel) + '</p>',
					'<p><strong>' + messages.metaDescriptionLabel + ':</strong> ' + (metaSuggestions.description ? metaSuggestions.description : messages.noneLabel) + '</p>',
					'<p><strong>' + messages.priorityActionsLabel + ':</strong> ' + formatList(data.priority_actions) + '</p>',
					'<p><strong>' + messages.overallScoreLabel + ':</strong> ' + formatScore(data.overall_score) + '</p>',
					'</div></div>'
				].join('');

				$suggestionsResults.html(html).addClass('show');
			}

			function displayReadabilityResults(data) {
				const html = [
					'<div class="fp-seo-result-item">',
					'<div class="fp-seo-result-title">' + messages.readabilityTitle + '</div>',
					'<div class="fp-seo-result-content">',
					'<p><strong>' + messages.sentenceIssuesLabel + ':</strong> ' + formatList(data.sentence_issues) + '</p>',
					'<p><strong>' + messages.vocabularyLabel + ':</strong> ' + formatList(data.vocabulary_suggestions) + '</p>',
					'<p><strong>' + messages.paragraphLabel + ':</strong> ' + formatList(data.paragraph_improvements) + '</p>',
					'<p><strong>' + messages.transitionLabel + ':</strong> ' + formatList(data.transition_suggestions) + '</p>',
					'<p><strong>' + messages.formattingLabel + ':</strong> ' + formatList(data.formatting_tips) + '</p>',
					'<p><strong>' + messages.toneLabel + ':</strong> ' + formatList(data.tone_adjustments) + '</p>',
					'<p><strong>' + messages.readabilityScoreLabel + ':</strong> ' + formatScore(data.readability_score) + '</p>',
					'</div></div>'
				].join('');

				$readabilityResults.html(html).addClass('show');
			}

			function displaySemanticResults(data) {
				const html = [
					'<div class="fp-seo-result-item">',
					'<div class="fp-seo-result-title">' + messages.semanticTitle + '</div>',
					'<div class="fp-seo-result-content">',
					'<p><strong>' + messages.semanticIntegrationsLabel + ':</strong> ' + formatList(data.semantic_integrations) + '</p>',
					'<p><strong>' + messages.topicClustersLabel + ':</strong> ' + formatList(data.topic_cluster_suggestions) + '</p>',
					'<p><strong>' + messages.entityLabel + ':</strong> ' + formatList(data.entity_optimizations) + '</p>',
					'<p><strong>' + messages.contextLabel + ':</strong> ' + formatList(data.context_enrichments) + '</p>',
					'<p><strong>' + messages.lsiLabel + ':</strong> ' + formatList(data.lsi_keywords) + '</p>',
					'<p><strong>' + messages.depthLabel + ':</strong> ' + formatList(data.depth_improvements) + '</p>',
					'<p><strong>' + messages.semanticScoreLabel + ':</strong> ' + formatScore(data.semantic_score) + '</p>',
					'</div></div>'
				].join('');

				$semanticResults.html(html).addClass('show');
			}

			$('.fp-seo-tab-button').on('click', function() {
				const tab = $(this).data('tab');
				$('.fp-seo-tab-button').removeClass('active');
				$('.fp-seo-tab-content').removeClass('active');
				$(this).addClass('active');
				$('#' + tab).addClass('active');
			});

			$('#fp-seo-content-gaps-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $topic = $form.find('[name="topic"]');
				const $keyword = $form.find('[name="keyword"]');
				const $competitors = $form.find('[name="competitors"]');

				const topic = normalize($topic.val());
				const keyword = normalize($keyword.val());
				const competitorsRaw = normalize($competitors.val());
				let hasError = false;

				clearNotice($form);
				resetResults($gapsResults);

				if (!topic.length) {
					setFieldError($topic, true);
					hasError = true;
				} else {
					setFieldError($topic, false);
				}

				if (!keyword.length) {
					setFieldError($keyword, true);
					hasError = true;
				} else {
					setFieldError($keyword, false);
				}

				let competitorsValue = '';
				if (competitorsRaw.length) {
					const competitorList = competitorsRaw.split(/\n+/).map(normalize).filter(Boolean);
					const invalidUrl = competitorList.find(function(url) {
						return !isValidUrl(url);
					});

					if (invalidUrl) {
						setFieldError($competitors, true);
						showNotice($form, messages.invalidUrl.replace('%s', invalidUrl), 'error');
						$competitors.focus();
						return;
					}

					setFieldError($competitors, false);
					competitorsValue = competitorList.join("\n");
				} else {
					setFieldError($competitors, false);
				}

				if (hasError) {
					showNotice($form, messages.validationGeneric, 'error');
					(!topic.length ? $topic : $keyword).focus();
					return;
				}

				setLoading($submit, true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_analyze_content_gaps',
						topic: topic,
						keyword: keyword,
						competitors: competitorsValue,
						nonce: optimizerNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						displayContentGapsResults(response.data || {});
						showNotice($form, messages.analysisComplete, 'success');
					} else {
						showNotice($form, parseError(response, messages.unexpectedError), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice($form, messages.requestFailed + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading($submit, false);
				});
			});

			$('#fp-seo-competitor-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $url = $form.find('[name="url"]');
				const $keyword = $form.find('[name="keyword"]');

				const url = normalize($url.val());
				const keyword = normalize($keyword.val());
				let hasError = false;

				clearNotice($form);
				resetResults($competitorResults);

				if (!url.length) {
					setFieldError($url, true);
					showNotice($form, messages.competitorUrlRequired, 'error');
					$url.focus();
					return;
				}

				if (!isValidUrl(url)) {
					setFieldError($url, true);
					showNotice($form, messages.invalidUrl.replace('%s', url), 'error');
					$url.focus();
					return;
				}

				setFieldError($url, false);

				if (!keyword.length) {
					setFieldError($keyword, true);
					hasError = true;
				} else {
					setFieldError($keyword, false);
				}

				if (hasError) {
					showNotice($form, messages.competitorKeywordRequired, 'error');
					$keyword.focus();
					return;
				}

				setLoading($submit, true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_competitor_analysis',
						url: url,
						keyword: keyword,
						nonce: optimizerNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						displayCompetitorResults(response.data || {});
						showNotice($form, messages.competitorComplete, 'success');
					} else {
						showNotice($form, parseError(response, messages.unexpectedError), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice($form, messages.requestFailed + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading($submit, false);
				});
			});

			$('#fp-seo-suggestions-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $post = $form.find('[name="post_id"]');
				const $keyword = $form.find('[name="focus_keyword"]');

				const postId = normalize($post.val());
				const keyword = normalize($keyword.val());
				let hasError = false;

				clearNotice($form);
				resetResults($suggestionsResults);

				if (!postId.length) {
					setFieldError($post, true);
					hasError = true;
				} else {
					setFieldError($post, false);
				}

				if (!keyword.length) {
					setFieldError($keyword, true);
					hasError = true;
				} else {
					setFieldError($keyword, false);
				}

				if (hasError) {
					showNotice($form, messages.validationGeneric, 'error');
					if (!postId.length) {
						$post.focus();
					} else {
						$keyword.focus();
					}
					return;
				}

				setLoading($submit, true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_content_suggestions',
						post_id: postId,
						keyword: keyword,
						focus_keyword: keyword,
						nonce: optimizerNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						displaySuggestionsResults(response.data || {});
						showNotice($form, messages.suggestionsComplete, 'success');
					} else {
						showNotice($form, parseError(response, messages.unexpectedError), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice($form, messages.requestFailed + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading($submit, false);
				});
			});

			$('#fp-seo-readability-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $content = $form.find('[name="content"]');
				const $audience = $form.find('[name="audience"]');

				const content = normalize($content.val());
				const audience = normalize($audience.val()) || 'general';

				clearNotice($form);
				resetResults($readabilityResults);

				if (!content.length) {
					setFieldError($content, true);
					showNotice($form, messages.contentRequired, 'error');
					$content.focus();
					return;
				}

				if (content.length < 50) {
					setFieldError($content, true);
					showNotice($form, messages.contentTooShort, 'error');
					$content.focus();
					return;
				}

				setFieldError($content, false);
				setLoading($submit, true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_readability_optimization',
						content: content,
						audience: audience,
						nonce: optimizerNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						displayReadabilityResults(response.data || {});
						showNotice($form, messages.readabilityComplete, 'success');
					} else {
						showNotice($form, parseError(response, messages.unexpectedError), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice($form, messages.requestFailed + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading($submit, false);
				});
			});

			$('#fp-seo-semantic-form').on('submit', function(e) {
				e.preventDefault();

				const $form = $(this);
				const $submit = $form.find('button[type="submit"]');
				const $content = $form.find('[name="content"]');
				const $keyword = $form.find('[name="keyword"]');
				const $semanticKeywords = $form.find('[name="semantic_keywords"]');

				const content = normalize($content.val());
				const keyword = normalize($keyword.val());
				const semanticKeywords = normalize($semanticKeywords.val());

				clearNotice($form);
				resetResults($semanticResults);

				if (!content.length) {
					setFieldError($content, true);
					showNotice($form, messages.contentRequired, 'error');
					$content.focus();
					return;
				}

				setFieldError($content, false);

				if (!keyword.length) {
					setFieldError($keyword, true);
					showNotice($form, messages.semanticKeywordRequired, 'error');
					$keyword.focus();
					return;
				}

				setFieldError($keyword, false);
				setLoading($submit, true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_semantic_optimization',
						content: content,
						keyword: keyword,
						semantic_keywords: semanticKeywords,
						nonce: optimizerNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						displaySemanticResults(response.data || {});
						showNotice($form, messages.semanticComplete, 'success');
					} else {
						showNotice($form, parseError(response, messages.unexpectedError), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice($form, messages.requestFailed + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading($submit, false);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for content gap analysis.
	 */
	public function ajax_analyze_content_gaps(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
		}

		$topic = sanitize_text_field( $_POST['topic'] ?? '' );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? '' );
		$competitors = array_filter(
			array_map(
				static function( $url ) {
					$url = esc_url_raw( trim( (string) $url ) );
					return ( $url && wp_http_validate_url( $url ) ) ? $url : '';
				},
				explode( "\n", $_POST['competitors'] ?? '' )
			)
		);

		if ( empty( $topic ) || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Argomento e keyword sono obbligatori.', 'fp-seo-performance' ) ),
				400
			);
		}

		try {
			$results = $this->analyze_content_gaps( $topic, $keyword, $competitors );
			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for competitor analysis.
	 */
	public function ajax_competitor_analysis(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
		}

		$url     = esc_url_raw( $_POST['url'] ?? '' );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? '' );

		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			wp_send_json_error(
				array( 'message' => __( 'URL non valido. Inserisci un indirizzo completo con http o https.', 'fp-seo-performance' ) ),
				400
			);
		}

		if ( empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'La keyword per l\'analisi del competitor è obbligatoria.', 'fp-seo-performance' ) ),
				400
			);
		}

		try {
			$results = $this->analyze_competitor_content( $url, $keyword );
			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for content suggestions.
	 */
	public function ajax_content_suggestions(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? ( $_POST['focus_keyword'] ?? '' ) );

		if ( ! $post_id || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Seleziona un contenuto e indica la keyword da analizzare.', 'fp-seo-performance' ) ),
				400
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error(
				array( 'message' => __( 'Contenuto non trovato.', 'fp-seo-performance' ) ),
				404
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per analizzare questo contenuto.', 'fp-seo-performance' ) ),
				403
			);
		}

		try {
			$results = $this->generate_content_suggestions( $post_id, $post->post_content, $keyword );
			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for readability optimization.
	 */
	public function ajax_readability_optimization(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
		}

		$content = wp_unslash( $_POST['content'] ?? '' );
		$audience = sanitize_text_field( $_POST['audience'] ?? 'general' );

		if ( empty( $content ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Il contenuto da analizzare è obbligatorio.', 'fp-seo-performance' ) ),
				400
			);
		}

		try {
			$results = $this->optimize_readability( $content, $audience );
			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for semantic SEO optimization.
	 */
	public function ajax_semantic_optimization(): void {
		check_ajax_referer( 'fp_seo_optimizer_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permessi insufficienti per eseguire questa operazione.', 'fp-seo-performance' ) ),
				403
			);
		}

		$content = wp_unslash( $_POST['content'] ?? '' );
		$keyword = sanitize_text_field( $_POST['keyword'] ?? '' );
		$semantic_keywords = array_filter( array_map( 'trim', explode( ',', $_POST['semantic_keywords'] ?? '' ) ) );

		if ( empty( $content ) || empty( $keyword ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Contenuto e keyword sono obbligatori per l\'ottimizzazione semantica.', 'fp-seo-performance' ) ),
				400
			);
		}

		try {
			$results = $this->optimize_semantic_seo( $content, $keyword, $semantic_keywords );
			wp_send_json_success( $results );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
