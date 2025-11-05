<?php
/**
 * AI-First Settings Tab Renderer
 *
 * Renders settings tab for AI-first GEO features configuration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use FP\SEO\Utils\Options;

/**
 * Renders AI-first settings tab
 */
class AiFirstTabRenderer extends SettingsTabRenderer {

	/**
	 * Render tab content
	 *
	 * @param array<string, mixed> $options Current options.
	 */
	public function render( array $options ): void {
		$ai_first = is_array( $options['ai_first'] ?? null ) ? $options['ai_first'] : array();

		?>
		<div class="fp-seo-settings-section">
			<h2><?php esc_html_e( '🤖 AI-First GEO Features', 'fp-seo-performance' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configurazione avanzata per ottimizzazione AI engines (Gemini, ChatGPT, Claude, Perplexity).', 'fp-seo-performance' ); ?>
			</p>

			<table class="form-table">
				<!-- Enable Q&A Extraction -->
				<tr>
					<th scope="row">
						<label for="ai_first_enable_qa">
							<?php esc_html_e( 'Q&A Extraction', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[ai_first][enable_qa]" 
								   id="ai_first_enable_qa" 
								   value="1" 
								   <?php checked( ! empty( $ai_first['enable_qa'] ) ); ?>>
							<?php esc_html_e( 'Enable automatic Q&A pairs extraction', 'fp-seo-performance' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Estrae automaticamente coppie domanda-risposta dal contenuto usando GPT-5 Nano. Richiede OpenAI API key.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Enable Entity Graphs -->
				<tr>
					<th scope="row">
						<label for="ai_first_enable_entities">
							<?php esc_html_e( 'Entity Graphs', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[ai_first][enable_entities]" 
								   id="ai_first_enable_entities" 
								   value="1" 
								   <?php checked( ! empty( $ai_first['enable_entities'] ) ); ?>>
							<?php esc_html_e( 'Enable entity extraction and relationship graphs', 'fp-seo-performance' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Estrae entities (Person, Organization, Software, ecc.) e le loro relazioni dal contenuto.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Enable Embeddings -->
				<tr>
					<th scope="row">
						<label for="ai_first_enable_embeddings">
							<?php esc_html_e( 'Vector Embeddings', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[ai_first][enable_embeddings]" 
								   id="ai_first_enable_embeddings" 
								   value="1" 
								   <?php checked( ! empty( $ai_first['enable_embeddings'] ) ); ?>>
							<?php esc_html_e( 'Enable vector embeddings generation', 'fp-seo-performance' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Genera embeddings vettoriali per semantic similarity. Richiede OpenAI API key. Costo: ~$0.0001 per post.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Auto-generate on Publish -->
				<tr>
					<th scope="row">
						<label for="ai_first_auto_generate">
							<?php esc_html_e( 'Auto-Generate on Publish', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox" 
								   name="fp_seo_performance[ai_first][auto_generate_on_publish]" 
								   id="ai_first_auto_generate" 
								   value="1" 
								   <?php checked( ! empty( $ai_first['auto_generate_on_publish'] ) ); ?>>
							<?php esc_html_e( 'Generate Q&A and optimize images automatically when publishing posts', 'fp-seo-performance' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Genera automaticamente Q&A pairs e ottimizza immagini al publish del post.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Batch Size -->
				<tr>
					<th scope="row">
						<label for="ai_first_batch_size">
							<?php esc_html_e( 'Batch Processing Size', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="number" 
							   name="fp_seo_performance[ai_first][batch_size]" 
							   id="ai_first_batch_size" 
							   value="<?php echo esc_attr( $ai_first['batch_size'] ?? 10 ); ?>" 
							   min="1" 
							   max="50" 
							   step="1" 
							   class="small-text">
						<p class="description">
							<?php esc_html_e( 'Numero di post da processare per volta nelle azioni bulk (default: 10).', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Cache TTL -->
				<tr>
					<th scope="row">
						<label for="ai_first_cache_ttl">
							<?php esc_html_e( 'Cache Duration', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<select name="fp_seo_performance[ai_first][cache_ttl]" id="ai_first_cache_ttl">
							<option value="3600" <?php selected( $ai_first['cache_ttl'] ?? 86400, 3600 ); ?>>
								<?php esc_html_e( '1 hour', 'fp-seo-performance' ); ?>
							</option>
							<option value="86400" <?php selected( $ai_first['cache_ttl'] ?? 86400, 86400 ); ?>>
								<?php esc_html_e( '1 day', 'fp-seo-performance' ); ?>
							</option>
							<option value="604800" <?php selected( $ai_first['cache_ttl'] ?? 86400, 604800 ); ?>>
								<?php esc_html_e( '1 week', 'fp-seo-performance' ); ?>
							</option>
							<option value="2592000" <?php selected( $ai_first['cache_ttl'] ?? 86400, 2592000 ); ?>>
								<?php esc_html_e( '1 month', 'fp-seo-performance' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Durata cache per dati AI-first (Q&A, variants, entities). Default: 1 giorno.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Content License -->
				<tr>
					<th scope="row">
						<label for="ai_first_content_license">
							<?php esc_html_e( 'Content License', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<input type="text" 
							   name="fp_seo_performance[ai_first][content_license]" 
							   id="ai_first_content_license" 
							   value="<?php echo esc_attr( $ai_first['content_license'] ?? 'All Rights Reserved' ); ?>" 
							   class="regular-text" 
							   placeholder="CC BY-SA 4.0, All Rights Reserved, ecc.">
						<p class="description">
							<?php esc_html_e( 'Licenza del contenuto (visibile nel training dataset export).', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- Endpoint Status -->
			<div style="margin-top: 30px; padding: 20px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
				<h3><?php esc_html_e( '📊 Endpoint Status', 'fp-seo-performance' ); ?></h3>
				
				<?php $this->render_endpoint_status(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render endpoint status
	 */
	private function render_endpoint_status(): void {
		$base_url  = home_url();
		$endpoints = array(
			'Q&A Pairs'       => '/geo/content/1/qa.json',
			'Semantic Chunks' => '/geo/content/1/chunks.json',
			'Entity Graph'    => '/geo/content/1/entities.json',
			'Authority'       => '/geo/content/1/authority.json',
			'Variants'        => '/geo/content/1/variants.json',
			'Images'          => '/geo/content/1/images.json',
			'Embeddings'      => '/geo/content/1/embeddings.json',
			'Training Data'   => '/geo/training-data.jsonl',
		);

		echo '<p class="description">' . esc_html__( 'Endpoint disponibili (sostituisci "1" con un post ID reale):', 'fp-seo-performance' ) . '</p>';
		echo '<ul style="list-style: none; padding: 0; margin-top: 15px;">';

		foreach ( $endpoints as $label => $path ) {
			$url = $base_url . $path;
			echo '<li style="padding: 8px 12px; margin: 5px 0; background: white; border-radius: 4px; border-left: 3px solid #0284c7;">';
			echo '<strong>' . esc_html( $label ) . ':</strong> ';
			echo '<a href="' . esc_url( $url ) . '" target="_blank" style="color: #0284c7;">' . esc_html( $path ) . ' →</a>';
			echo '</li>';
		}

		echo '</ul>';

		echo '<p style="margin-top: 15px; padding: 10px; background: #fef3c7; border-radius: 4px; border-left: 3px solid #f59e0b;">';
		echo '<strong>⚠️ Importante:</strong> ';
		echo esc_html__( 'Se gli endpoint restituiscono 404, vai su Impostazioni → Permalinks → Salva modifiche.', 'fp-seo-performance' );
		echo '</p>';
	}
}

