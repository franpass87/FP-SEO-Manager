<?php
/**
 * Automation Tab Renderer for Settings Page
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

use FP\SEO\Utils\Options;

/**
 * Renders the Automation settings tab.
 */
class AutomationTabRenderer {

	/**
	 * Render the automation tab content.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 */
	public function render( array $options ): void {
		$automation = $options['automation'] ?? array();
		?>
		<div class="fp-seo-settings-section">
			<!-- Header Section -->
			<div class="fp-seo-section-header">
				<h2>🤖 <?php esc_html_e( 'Auto-Ottimizzazione SEO con AI', 'fp-seo-performance' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Attiva la generazione automatica con AI dei campi SEO mancanti (keyword, titoli, meta description) quando pubblichi o aggiorni un contenuto.', 'fp-seo-performance' ); ?>
				</p>
			</div>

			<!-- Introduzione con benefici -->
			<div class="fp-seo-intro-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 12px; margin-bottom: 32px;">
				<h3 style="color: white; margin-top: 0;">⚡ Perché usare l'Auto-Ottimizzazione?</h3>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 16px;">
					<div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
						<div style="font-size: 32px; margin-bottom: 8px;">⏱️</div>
						<strong>Risparmio Tempo</strong>
						<p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">
							Non devi più pensare a title e description - l'AI lo fa per te automaticamente
						</p>
					</div>
					<div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
						<div style="font-size: 32px; margin-bottom: 8px;">🎯</div>
						<strong>SEO Ottimale</strong>
						<p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">
							Ogni contenuto ha sempre keyword, title e description ottimizzati per Google
						</p>
					</div>
					<div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px;">
						<div style="font-size: 32px; margin-bottom: 8px;">🚀</div>
						<strong>Più Traffico</strong>
						<p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">
							Meta description accattivanti aumentano il CTR del 20-30% in media
						</p>
					</div>
				</div>
			</div>

			<table class="form-table" role="presentation">
				<!-- Enable Auto-Optimization -->
				<tr>
					<th scope="row">
						<label for="auto_seo_optimization">
							<?php esc_html_e( 'Abilita Auto-Ottimizzazione', 'fp-seo-performance' ); ?>
						</label>
					</th>
					<td>
						<label class="fp-seo-toggle-switch">
							<input 
								type="checkbox" 
								id="auto_seo_optimization" 
								name="fp_seo_performance[automation][auto_seo_optimization]" 
								value="1" 
								<?php checked( ! empty( $automation['auto_seo_optimization'] ) ); ?>
							>
							<span class="fp-seo-toggle-slider"></span>
						</label>
						<p class="description">
							<?php esc_html_e( 'Quando attivo, il plugin genera automaticamente i campi SEO mancanti ogni volta che pubblichi o aggiorni un post/pagina.', 'fp-seo-performance' ); ?>
						</p>

						<?php
						// Check if AI is configured
						$ai_configured = ! empty( $options['ai']['openai_api_key'] ?? '' );
						if ( ! $ai_configured ) :
							?>
							<div class="notice notice-warning inline" style="margin-top: 12px;">
								<p>
									<strong>⚠️ <?php esc_html_e( 'API Key OpenAI non configurata', 'fp-seo-performance' ); ?></strong><br>
									<?php
									printf(
										/* translators: %s: link to AI settings tab */
										esc_html__( 'Per usare l\'auto-ottimizzazione, devi prima configurare la tua API Key OpenAI nella %s.', 'fp-seo-performance' ),
										'<a href="?page=fp-seo-performance&tab=ai">' . esc_html__( 'tab AI', 'fp-seo-performance' ) . '</a>'
									);
									?>
								</p>
							</div>
							<?php
						endif;
						?>
					</td>
				</tr>

				<!-- Which fields to auto-optimize -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Campi da Generare', 'fp-seo-performance' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Seleziona quali campi generare automaticamente', 'fp-seo-performance' ); ?></span>
							</legend>

							<?php
							$auto_fields = $automation['auto_optimize_fields'] ?? array( 'focus_keyword', 'meta_description' );
							$fields_options = array(
								'focus_keyword'    => array(
									'label' => __( 'Focus Keyword', 'fp-seo-performance' ),
									'desc'  => __( 'Genera automaticamente la parola chiave principale analizzando il contenuto', 'fp-seo-performance' ),
								),
								'meta_description' => array(
									'label' => __( 'Meta Description', 'fp-seo-performance' ),
									'desc'  => __( 'Genera una descrizione accattivante per le SERP (max 155 caratteri)', 'fp-seo-performance' ),
								),
							);

							foreach ( $fields_options as $field_key => $field_data ) :
								$checked = in_array( $field_key, $auto_fields, true );
								?>
								<label style="display: block; margin-bottom: 16px; padding: 12px; background: #f9fafb; border-radius: 8px; border-left: 4px solid <?php echo $checked ? '#3b82f6' : '#d1d5db'; ?>;">
									<input 
										type="checkbox" 
										name="fp_seo_performance[automation][auto_optimize_fields][]" 
										value="<?php echo esc_attr( $field_key ); ?>" 
										<?php checked( $checked ); ?>
									>
									<strong><?php echo esc_html( $field_data['label'] ); ?></strong>
									<p class="description" style="margin: 4px 0 0 24px;">
										<?php echo esc_html( $field_data['desc'] ); ?>
									</p>
								</label>
								<?php
							endforeach;
							?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Seleziona quali campi SEO vuoi generare automaticamente quando sono vuoti. Ti consigliamo di selezionare tutti e tre per la massima ottimizzazione.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>

				<!-- Post types to auto-optimize -->
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Tipi di Contenuto', 'fp-seo-performance' ); ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Seleziona i tipi di contenuto da ottimizzare', 'fp-seo-performance' ); ?></span>
							</legend>

							<?php
							$auto_post_types = $automation['auto_optimize_post_types'] ?? array( 'post', 'page' );
							$post_types = get_post_types( array( 'public' => true ), 'objects' );

							foreach ( $post_types as $post_type ) :
								if ( in_array( $post_type->name, array( 'attachment' ), true ) ) {
									continue;
								}

								$checked = in_array( $post_type->name, $auto_post_types, true );
								?>
								<label style="display: inline-block; margin-right: 24px; margin-bottom: 8px;">
									<input 
										type="checkbox" 
										name="fp_seo_performance[automation][auto_optimize_post_types][]" 
										value="<?php echo esc_attr( $post_type->name ); ?>" 
										<?php checked( $checked ); ?>
									>
									<?php echo esc_html( $post_type->labels->name ); ?>
								</label>
								<?php
							endforeach;
							?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Seleziona su quali tipi di contenuto applicare l\'auto-ottimizzazione. Di default sono attivi Post e Pagine.', 'fp-seo-performance' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<!-- How it works section -->
			<div class="fp-seo-info-box" style="background: #e0f2fe; border-left: 4px solid #0ea5e9; padding: 24px; margin-top: 32px; border-radius: 8px;">
				<h3 style="margin-top: 0; color: #075985;">
					<span class="dashicons dashicons-info" style="font-size: 24px; width: 24px; height: 24px;"></span>
					<?php esc_html_e( 'Come Funziona l\'Auto-Ottimizzazione', 'fp-seo-performance' ); ?>
				</h3>
				<ol style="color: #0c4a6e; line-height: 1.8;">
					<li><strong><?php esc_html_e( 'Pubblichi o aggiorni un post/pagina', 'fp-seo-performance' ); ?></strong></li>
					<li><?php esc_html_e( 'Il plugin controlla se i campi SEO selezionati sono vuoti', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Se sono vuoti, invia il contenuto all\'AI (OpenAI GPT-4)', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'L\'AI analizza il contenuto e genera keyword, title e description ottimizzati', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'I campi generati vengono salvati automaticamente', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Ricevi una notifica con i campi che sono stati generati', 'fp-seo-performance' ); ?></li>
				</ol>
				<p style="color: #0c4a6e; margin-bottom: 0;">
					<strong>✅ <?php esc_html_e( 'Nota:', 'fp-seo-performance' ); ?></strong>
					<?php esc_html_e( 'L\'auto-ottimizzazione funziona SOLO sui campi vuoti. Se hai già compilato manualmente un campo, non verrà sovrascritto.', 'fp-seo-performance' ); ?>
				</p>
			</div>

			<!-- Best Practices -->
			<div class="fp-seo-tips-box" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 24px; margin-top: 24px; border-radius: 8px;">
				<h3 style="margin-top: 0; color: #92400e;">
					💡 <?php esc_html_e( 'Consigli per il Massimo Risultato', 'fp-seo-performance' ); ?>
				</h3>
				<ul style="color: #78350f; line-height: 1.8;">
					<li><?php esc_html_e( 'Scrivi contenuti di qualità con almeno 300-500 parole - più contesto hai, migliore sarà l\'AI', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Usa un titolo chiaro e descrittivo - l\'AI lo userà come base per generare il titolo SEO', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Rivedi sempre i campi generati dall\'AI - puoi modificarli manualmente se vuoi', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Assegna categorie e tag pertinenti - l\'AI li usa per capire il contesto', 'fp-seo-performance' ); ?></li>
					<li><?php esc_html_e( 'Per contenuti molto specifici, imposta manualmente la focus keyword prima di pubblicare', 'fp-seo-performance' ); ?></li>
				</ul>
			</div>
		</div>

		<style>
		.fp-seo-toggle-switch {
			position: relative;
			display: inline-block;
			width: 60px;
			height: 34px;
			vertical-align: middle;
		}

		.fp-seo-toggle-switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		.fp-seo-toggle-slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: .4s;
			border-radius: 34px;
		}

		.fp-seo-toggle-slider:before {
			position: absolute;
			content: "";
			height: 26px;
			width: 26px;
			left: 4px;
			bottom: 4px;
			background-color: white;
			transition: .4s;
			border-radius: 50%;
		}

		.fp-seo-toggle-switch input:checked + .fp-seo-toggle-slider {
			background-color: #3b82f6;
		}

		.fp-seo-toggle-switch input:focus + .fp-seo-toggle-slider {
			box-shadow: 0 0 1px #3b82f6;
		}

		.fp-seo-toggle-switch input:checked + .fp-seo-toggle-slider:before {
			transform: translateX(26px);
		}
		</style>
		<?php
	}
}

