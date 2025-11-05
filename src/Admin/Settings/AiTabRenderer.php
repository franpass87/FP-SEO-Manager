<?php
/**
 * AI settings tab renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use FP\SEO\Integrations\OpenAiClient;
use function checked;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function selected;

/**
 * Renders the AI settings tab.
 */
class AiTabRenderer extends SettingsTabRenderer {

	/**
	 * Renders AI settings tab.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	public function render( array $options ): void {
		$ai_settings = $options['ai'] ?? array();
		
		// Check if API key is configured
		$client       = new OpenAiClient();
		$is_configured = $client->is_configured();
		?>
		<div class="fp-seo-settings-section">
			<h2 class="fp-seo-settings-section__title">
				<?php esc_html_e( 'Configurazione OpenAI', 'fp-seo-performance' ); ?>
			</h2>
			<p class="fp-seo-settings-section__description">
				<?php esc_html_e( 'Configura l\'integrazione con OpenAI per generare automaticamente contenuti SEO ottimizzati con l\'intelligenza artificiale.', 'fp-seo-performance' ); ?>
			</p>
		</div>

		<table class="form-table" role="presentation">
			<tbody>
			<tr>
				<th scope="row">
					<label for="openai_api_key"><?php esc_html_e( 'API Key OpenAI', 'fp-seo-performance' ); ?></label>
				</th>
				<td>
					<input 
						type="password" 
						id="openai_api_key" 
						name="<?php echo esc_attr( $this->get_option_key() ); ?>[ai][openai_api_key]" 
						value="<?php echo esc_attr( $ai_settings['openai_api_key'] ?? '' ); ?>" 
						class="regular-text"
						autocomplete="off"
					/>
					<?php if ( $is_configured ) : ?>
						<p class="description" style="color: #46b450;">
							✓ <?php esc_html_e( 'API Key configurata correttamente', 'fp-seo-performance' ); ?>
						</p>
					<?php else : ?>
						<p class="description">
							<?php 
							printf(
								/* translators: %s: OpenAI dashboard URL */
								esc_html__( 'Ottieni la tua API key dal %s', 'fp-seo-performance' ),
								'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">dashboard OpenAI</a>'
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="openai_model"><?php esc_html_e( 'Modello AI', 'fp-seo-performance' ); ?></label>
				</th>
				<td>
					<select 
						id="openai_model" 
						name="<?php echo esc_attr( $this->get_option_key() ); ?>[ai][openai_model]"
					>
						<?php foreach ( $this->get_model_choices() as $model => $label ) : ?>
							<option 
								value="<?php echo esc_attr( $model ); ?>" 
								<?php selected( $ai_settings['openai_model'] ?? 'gpt-4o-mini', $model ); ?>
							>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Scegli il modello AI da utilizzare. GPT-5 Nano è consigliato per il miglior rapporto velocità/qualità/costo.', 'fp-seo-performance' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Funzionalità AI', 'fp-seo-performance' ); ?>
				</th>
				<td>
					<fieldset>
						<label>
							<input 
								type="checkbox" 
								name="<?php echo esc_attr( $this->get_option_key() ); ?>[ai][enable_auto_generation]" 
								value="1" 
								<?php checked( $ai_settings['enable_auto_generation'] ?? true ); ?> 
							/>
							<?php esc_html_e( 'Abilita generazione automatica SEO', 'fp-seo-performance' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Mostra il pulsante "Genera con AI" nel metabox dell\'editor per creare automaticamente titolo SEO, meta description e slug.', 'fp-seo-performance' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Preferenze generazione', 'fp-seo-performance' ); ?>
				</th>
				<td>
					<label>
						<input 
							type="checkbox" 
							name="<?php echo esc_attr( $this->get_option_key() ); ?>[ai][focus_on_keywords]" 
							value="1" 
							<?php checked( $ai_settings['focus_on_keywords'] ?? true ); ?> 
						/>
						<?php esc_html_e( 'Priorità alle keyword nel contenuto', 'fp-seo-performance' ); ?>
					</label>
					<br/>
					<label style="margin-top: 8px; display: inline-block;">
						<input 
							type="checkbox" 
							name="<?php echo esc_attr( $this->get_option_key() ); ?>[ai][optimize_for_ctr]" 
							value="1" 
							<?php checked( $ai_settings['optimize_for_ctr'] ?? true ); ?> 
						/>
						<?php esc_html_e( 'Ottimizza per Click-Through Rate (CTR)', 'fp-seo-performance' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'L\'AI genererà contenuti più orientati al click e al coinvolgimento dell\'utente.', 'fp-seo-performance' ); ?>
					</p>
				</td>
			</tr>
			</tbody>
		</table>

		<div class="fp-seo-settings-section" style="margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 24px;">
			<h2 class="fp-seo-settings-section__title">
				<?php esc_html_e( 'Informazioni', 'fp-seo-performance' ); ?>
			</h2>
			<div style="background: #f9fafb; border-left: 4px solid #2563eb; padding: 16px; border-radius: 4px;">
				<p style="margin: 0 0 8px 0; font-weight: 600;">
					<?php esc_html_e( 'Come funziona?', 'fp-seo-performance' ); ?>
				</p>
				<p style="margin: 0; color: #4b5563; line-height: 1.6;">
					<?php esc_html_e( 'Quando modifichi un post o una pagina, troverai un pulsante "Genera con AI" nel metabox FP SEO. Con un click, l\'intelligenza artificiale analizzerà il tuo contenuto e genererà automaticamente:', 'fp-seo-performance' ); ?>
				</p>
				<ul style="margin: 12px 0 0 0; padding-left: 20px; color: #4b5563;">
					<li><?php esc_html_e( 'Titolo SEO ottimizzato (max 60 caratteri)', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Meta description accattivante (max 155 caratteri)', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Slug URL ottimizzato', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Focus keyword principale', 'fp-seo-performance' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Get available AI model choices.
	 *
	 * @return array<string, string>
	 */
	private function get_model_choices(): array {
		return array(
			// GPT-5 Models (Latest & Most Advanced)
			'gpt-5-nano'   => __( 'GPT-5 Nano ⚡ (Consigliato - Veloce ed Economico)', 'fp-seo-performance' ),
			'gpt-5-mini'   => __( 'GPT-5 Mini (Ottimizzato)', 'fp-seo-performance' ),
			'gpt-5'        => __( 'GPT-5 (Qualità Massima)', 'fp-seo-performance' ),
			'gpt-5-pro'    => __( 'GPT-5 Pro (Enterprise)', 'fp-seo-performance' ),
			// GPT-4 Models (Legacy)
			'gpt-4o-mini'  => __( 'GPT-4o Mini (Legacy)', 'fp-seo-performance' ),
			'gpt-4o'       => __( 'GPT-4o (Legacy)', 'fp-seo-performance' ),
			'gpt-4-turbo'  => __( 'GPT-4 Turbo (Legacy)', 'fp-seo-performance' ),
			'gpt-3.5-turbo' => __( 'GPT-3.5 Turbo (Obsoleto)', 'fp-seo-performance' ),
		);
	}
}

