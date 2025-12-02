<?php
/**
 * Renders SERP optimization fields (Title, Meta Description, Slug, Excerpt, Keywords).
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Editor\Helpers\MetaHelper;
use FP\SEO\Editor\Metabox;
use WP_Post;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function esc_textarea;
use function html_entity_decode;
use function implode;
use function is_array;
use function wp_create_nonce;

/**
 * Renders SERP optimization fields.
 */
class SerpFieldsRenderer extends FieldRenderer {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// No dependencies needed
	}

	/**
	 * Render SEO Title field.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_seo_title( WP_Post $post ): void {
		$value = MetaHelper::get_meta_string( $post->ID, '_fp_seo_title' );

		$this->render_text_field(
			'fp-seo-title',
			'fp_seo_title',
			$value,
			'SEO Title',
			'📝',
			'+15%',
			'#10b981',
			70,
			'es: Guida Completa alla SEO WordPress 2025 | Nome Sito',
			'fp-seo-title-counter',
			'0/60',
			'🎯 Alto impatto (+15%) - Appare come titolo principale in Google. Lunghezza ottimale: 50-60 caratteri con keyword all\'inizio.',
			'#059669',
			$post,
			'seo_title',
			'#10b981'
		);
	}

	/**
	 * Render Meta Description field.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_description( WP_Post $post ): void {
		$value = MetaHelper::get_meta_string( $post->ID, '_fp_seo_meta_description' );

		$this->render_textarea_field(
			'fp-seo-meta-description',
			'fp_seo_meta_description',
			$value,
			'Meta Description',
			'📄',
			'+10%',
			'#10b981',
			200,
			3,
			'es: Scopri come ottimizzare WordPress per la SEO con la nostra guida completa 2025. Aumenta il traffico del 300% seguendo 5 step comprovati.',
			'fp-seo-meta-description-counter',
			'0/160',
			'🎯 Medio-Alto impatto (+10%) - Descrizione sotto il titolo in Google. Include keyword + CTA. Ottimale: 150-160 caratteri.',
			'#059669',
			$post,
			'meta_description',
			'#10b981'
		);
	}

	/**
	 * Render Slug field.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_slug( WP_Post $post ): void {
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
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_excerpt( WP_Post $post ): void {
		$value = html_entity_decode( $post->post_excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$this->render_textarea_field(
			'fp-seo-excerpt',
			'fp_seo_excerpt',
			$value,
			'Riassunto (Excerpt)',
			'📋',
			'+9%',
			'#3b82f6',
			300,
			3,
			'es: Breve riassunto del contenuto. Usato come fallback per meta description se non compilata. 100-150 caratteri ottimali.',
			'fp-seo-excerpt-counter',
			'0/150',
			'🎯 Medio impatto (+9%) - Riassunto breve del contenuto. Usato come fallback se Meta Description è vuota. Appare anche in archivi/elenchi. Ottimale: 100-150 caratteri.',
			'#3b82f6',
			$post,
			'',
			'#3b82f6'
		);
	}

	/**
	 * Render Keywords section (Focus and Secondary).
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_keywords( WP_Post $post ): void {
		$focus_keyword = MetaHelper::get_meta_string( $post->ID, MetaboxRenderer::META_FOCUS_KEYWORD );
		$secondary_keywords = MetaHelper::get_meta_array( $post->ID, MetaboxRenderer::META_SECONDARY_KEYWORDS );

		$secondary_keywords_string = '';
		if ( is_array( $secondary_keywords ) ) {
			$secondary_keywords_string = implode( ', ', $secondary_keywords );
		} elseif ( is_string( $secondary_keywords ) ) {
			$secondary_keywords_string = $secondary_keywords;
		}

		?>
		<!-- Keywords Section -->
		<div style="display: grid; gap: 16px;">
			<!-- Focus Keyword -->
			<div>
				<label for="fp-seo-focus-keyword" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
					<span style="display: flex; align-items: center; gap: 8px;">
						<span style="font-size: 16px;">🎯</span>
						<?php esc_html_e( 'Focus Keyword', 'fp-seo-performance' ); ?>
						<span style="display: inline-flex; padding: 2px 8px; background: #ef4444; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+20%</span>
					</span>
				</label>
				<div style="display: flex; gap: 8px; align-items: stretch;">
					<input 
						type="text" 
						id="fp-seo-focus-keyword" 
						name="fp_seo_focus_keyword"
						value="<?php echo esc_attr( $focus_keyword ); ?>"
						placeholder="<?php esc_attr_e( 'es: SEO WordPress', 'fp-seo-performance' ); ?>"
						maxlength="100"
						style="flex: 1; padding: 10px 14px; font-size: 14px; border: 2px solid #ef4444; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
						data-fp-seo-focus-keyword
					/>
					<input type="hidden" name="fp_seo_focus_keyword_sent" value="1" />
					<button 
						type="button" 
						class="fp-seo-ai-generate-field-btn" 
						data-field="focus_keyword"
						data-target-id="fp-seo-focus-keyword"
						data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
						title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
					>
						<span>🤖</span>
						<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
					</button>
				</div>
				<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
					<strong style="color: #ef4444;">🎯 Altissimo impatto (+20%)</strong> - Parola chiave principale su cui ottimizzare il contenuto. Deve apparire nel titolo, meta description, H1 e nel contenuto.
				</p>
			</div>

			<!-- Secondary Keywords -->
			<div>
				<label for="fp-seo-secondary-keywords" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
					<span style="display: flex; align-items: center; gap: 8px;">
						<span style="font-size: 16px;">🔑</span>
						<?php esc_html_e( 'Secondary Keywords', 'fp-seo-performance' ); ?>
						<span style="display: inline-flex; padding: 2px 8px; background: #f59e0b; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+8%</span>
					</span>
				</label>
				<div style="display: flex; gap: 8px; align-items: stretch;">
					<input 
						type="text" 
						id="fp-seo-secondary-keywords" 
						name="fp_seo_secondary_keywords"
						value="<?php echo esc_attr( $secondary_keywords_string ); ?>"
						placeholder="<?php esc_attr_e( 'es: ottimizzazione SEO, WordPress SEO plugin, migliorare ranking', 'fp-seo-performance' ); ?>"
						maxlength="500"
						style="flex: 1; padding: 10px 14px; font-size: 14px; border: 2px solid #f59e0b; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
						data-fp-seo-secondary-keywords
					/>
					<input type="hidden" name="fp_seo_secondary_keywords_sent" value="1" />
					<button 
						type="button" 
						class="fp-seo-ai-generate-field-btn" 
						data-field="secondary_keywords"
						data-target-id="fp-seo-secondary-keywords"
						data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
						title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
					>
						<span>🤖</span>
						<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
					</button>
				</div>
				<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
					<strong style="color: #f59e0b;">📊 Medio impatto (+8%)</strong> - Parole chiave secondarie correlate. Separate da virgola. Aiutano a coprire più ricerche correlate e migliorano la rilevanza semantica.
				</p>
			</div>
		</div>
		<?php
	}
}

