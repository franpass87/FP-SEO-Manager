<?php
/**
 * Renders the Schema admin page.
 *
 * @package FP\SEO\Schema\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Renderers;

use FP\SEO\Schema\AdvancedSchemaManager;
use FP\SEO\Schema\Styles\SchemaPageStylesManager;
use function count;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function wp_add_inline_style;
use function wp_enqueue_style;
use function wp_create_nonce;
use function wp_json_encode;

/**
 * Renders the Schema admin page.
 */
class SchemaPageRenderer {
	/**
	 * @var AdvancedSchemaManager
	 */
	private $manager;

	/**
	 * @var SchemaPageStylesManager
	 */
	private $styles_manager;

	/**
	 * Constructor.
	 *
	 * @param AdvancedSchemaManager $manager Schema manager instance.
	 */
	public function __construct( AdvancedSchemaManager $manager ) {
		$this->manager = $manager;
		$this->styles_manager = new SchemaPageStylesManager();
	}

	/**
	 * Render the schema admin page.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap fp-seo-schema-wrap">
			<h1><?php esc_html_e( 'Schema Markup Manager', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Genera e gestisci lo Schema Markup (JSON-LD) per aiutare i motori di ricerca a capire meglio i tuoi contenuti', 'fp-seo-performance' ); ?></p>

			<?php $this->render_intro_banner(); ?>
			
			<div class="fp-seo-schema-dashboard">
				<?php $this->render_info_box(); ?>
				<?php $this->render_stats_cards(); ?>
				<?php $this->render_schema_generator(); ?>
				<?php $this->render_preview_section(); ?>
			</div>
		</div>

		<?php $this->render_styles(); ?>
		<?php $this->render_scripts(); ?>
		<?php
	}

	/**
	 * Render intro banner.
	 *
	 * @return void
	 */
	private function render_intro_banner(): void {
		?>
		<div class="fp-seo-intro-banner">
			<div class="fp-seo-intro-icon">🏗️</div>
			<div class="fp-seo-intro-content">
				<h2><?php esc_html_e( 'Cos\'è lo Schema Markup?', 'fp-seo-performance' ); ?></h2>
				<p><?php esc_html_e( 'Lo Schema Markup è un codice che aiuta i motori di ricerca a comprendere meglio il tuo contenuto e mostrare risultati più ricchi (rich snippets) nelle SERP:', 'fp-seo-performance' ); ?></p>
				<ul class="fp-seo-intro-list">
					<li>⭐ <strong>Rich Snippets:</strong> Recensioni con stelle, prezzi, disponibilità</li>
					<li>📰 <strong>Articoli:</strong> Immagine, data, autore nelle ricerche</li>
					<li>❓ <strong>FAQ:</strong> Domande e risposte espandibili</li>
					<li>🏢 <strong>Business:</strong> Indirizzo, orari, contatti</li>
					<li>🎯 <strong>Breadcrumb:</strong> Percorso di navigazione nei risultati</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render info box.
	 *
	 * @return void
	 */
	private function render_info_box(): void {
		?>
		<div class="fp-seo-info-box">
			<div class="fp-seo-info-icon">ℹ️</div>
			<div class="fp-seo-info-content">
				<h3><?php esc_html_e( 'Schema Automatici Attivi', 'fp-seo-performance' ); ?></h3>
				<p><?php esc_html_e( 'Il plugin genera automaticamente questi schema per il tuo sito:', 'fp-seo-performance' ); ?></p>
				<ul>
					<li>✓ <strong>Organization:</strong> Informazioni sulla tua azienda/sito</li>
					<li>✓ <strong>WebSite:</strong> Dati del sito + ricerca interna</li>
					<li>✓ <strong>Article:</strong> Per post e pagine</li>
					<li>✓ <strong>BreadcrumbList:</strong> Navigazione gerarchica</li>
					<li>✓ <strong>Product:</strong> Se usi WooCommerce</li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render stats cards.
	 *
	 * @return void
	 */
	private function render_stats_cards(): void {
		$active_schemas = $this->manager->get_active_schemas_public();
		$schema_types = AdvancedSchemaManager::get_schema_types();
		?>
		<div class="fp-seo-schema-stats">
			<div class="fp-seo-stat-card">
				<div class="fp-seo-stat-icon">✅</div>
				<div class="fp-seo-stat-content">
					<span class="fp-seo-stat-number"><?php echo esc_html( (string) count( $active_schemas ) ); ?></span>
					<h3><?php esc_html_e( 'Schema Attivi', 'fp-seo-performance' ); ?></h3>
					<p class="fp-seo-stat-desc"><?php esc_html_e( 'Schema attualmente generati su questa pagina', 'fp-seo-performance' ); ?></p>
				</div>
			</div>
			<div class="fp-seo-stat-card">
				<div class="fp-seo-stat-icon">📋</div>
				<div class="fp-seo-stat-content">
					<span class="fp-seo-stat-number"><?php echo esc_html( (string) count( $schema_types ) ); ?></span>
					<h3><?php esc_html_e( 'Tipi Disponibili', 'fp-seo-performance' ); ?></h3>
					<p class="fp-seo-stat-desc"><?php esc_html_e( 'Tipologie di schema supportate dal plugin', 'fp-seo-performance' ); ?></p>
				</div>
			</div>
			<div class="fp-seo-stat-card fp-seo-stat-card-highlight">
				<div class="fp-seo-stat-icon">🔧</div>
				<div class="fp-seo-stat-content">
					<h3><?php esc_html_e( 'Test Schema', 'fp-seo-performance' ); ?></h3>
					<p class="fp-seo-stat-desc"><?php esc_html_e( 'Verifica il tuo Schema', 'fp-seo-performance' ); ?></p>
					<a href="https://search.google.com/test/rich-results" target="_blank" class="button button-secondary">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'Google Rich Results Test', 'fp-seo-performance' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render schema generator form.
	 *
	 * @return void
	 */
	private function render_schema_generator(): void {
		$schema_types = AdvancedSchemaManager::get_schema_types();
		?>
		<div class="fp-seo-schema-generator">
			<div class="fp-seo-generator-header">
				<h2><?php esc_html_e( 'Schema Generator', 'fp-seo-performance' ); ?></h2>
				<p class="fp-seo-generator-desc"><?php esc_html_e( 'Genera Schema Markup personalizzati in formato JSON-LD', 'fp-seo-performance' ); ?></p>
			</div>
			<form id="fp-seo-schema-form">
				<div class="fp-seo-inline-notice" data-fp-seo-schema-notice hidden role="status" aria-live="polite"></div>
				<div class="fp-seo-form-group">
					<label for="schema-type">
						<?php esc_html_e( 'Tipo di Schema', 'fp-seo-performance' ); ?>
						<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Seleziona il tipo di schema che vuoi generare. Ogni tipo ha proprietà specifiche richieste da Google.', 'fp-seo-performance' ); ?>">ℹ️</span>
					</label>
					<select id="schema-type" name="schema_type">
						<?php foreach ( $schema_types as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="fp-seo-field-help"><?php esc_html_e( 'I più usati: Article (blog), Product (e-commerce), FAQPage (FAQ), LocalBusiness (azienda locale)', 'fp-seo-performance' ); ?></p>
				</div>
				
				<div class="fp-seo-form-group">
					<label for="schema-data">
						<?php esc_html_e( 'Dati Schema (JSON)', 'fp-seo-performance' ); ?>
						<span class="fp-seo-tooltip" title="<?php esc_attr_e( 'Inserisci i dati dello schema in formato JSON. Ogni tipo di schema ha proprietà specifiche richieste.', 'fp-seo-performance' ); ?>">ℹ️</span>
					</label>
					<textarea id="schema-data" name="schema_data" rows="15" placeholder='<?php echo esc_attr( '{\n  "name": "Nome Articolo",\n  "description": "Descrizione dell\'articolo",\n  "author": {\n    "@type": "Person",\n    "name": "Nome Autore"\n  },\n  "datePublished": "2025-11-03"\n}' ); ?>'></textarea>
					<p class="fp-seo-field-help"><?php esc_html_e( 'Inserisci i dati in formato JSON valido. Non includere @context e @type (vengono aggiunti automaticamente).', 'fp-seo-performance' ); ?></p>
				</div>

				<?php $this->render_examples_section(); ?>
				
				<div class="fp-seo-form-actions">
					<button type="button" id="fp-seo-generate-schema" class="button button-primary button-hero">
						<span class="dashicons dashicons-admin-tools"></span>
						<?php esc_html_e( 'Genera Schema', 'fp-seo-performance' ); ?>
					</button>
					<button type="button" id="fp-seo-preview-schema" class="button button-secondary">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'Anteprima', 'fp-seo-performance' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render examples section.
	 *
	 * @return void
	 */
	private function render_examples_section(): void {
		?>
		<div class="fp-seo-examples-section">
			<h3><?php esc_html_e( '📋 Esempi Schema Comuni', 'fp-seo-performance' ); ?></h3>
			
			<details class="fp-seo-example-accordion">
				<summary><strong>Article</strong> - Per articoli di blog</summary>
				<pre class="fp-seo-code-example">{
  "headline": "Titolo dell'articolo",
  "description": "Descrizione breve",
  "image": "https://tuosito.com/immagine.jpg",
  "datePublished": "2025-11-03",
  "dateModified": "2025-11-03",
  "author": {
    "@type": "Person",
    "name": "Nome Autore"
  }
}</pre>
			</details>

			<details class="fp-seo-example-accordion">
				<summary><strong>FAQPage</strong> - Per pagine con FAQ</summary>
				<pre class="fp-seo-code-example">{
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Qual è la domanda?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Questa è la risposta alla domanda."
      }
    }
  ]
}</pre>
			</details>

			<details class="fp-seo-example-accordion">
				<summary><strong>Product</strong> - Per prodotti e-commerce</summary>
				<pre class="fp-seo-code-example">{
  "name": "Nome Prodotto",
  "description": "Descrizione del prodotto",
  "image": "https://tuosito.com/prodotto.jpg",
  "offers": {
    "@type": "Offer",
    "price": "99.99",
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock"
  }
}</pre>
			</details>

			<details class="fp-seo-example-accordion">
				<summary><strong>LocalBusiness</strong> - Per attività locali</summary>
				<pre class="fp-seo-code-example">{
  "name": "Nome Attività",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Via Roma 123",
    "addressLocality": "Milano",
    "postalCode": "20100",
    "addressCountry": "IT"
  },
  "telephone": "+39-02-1234567",
  "openingHours": "Mo-Fr 09:00-18:00"
}</pre>
			</details>
		</div>
		<?php
	}

	/**
	 * Render preview section.
	 *
	 * @return void
	 */
	private function render_preview_section(): void {
		?>
		<div id="fp-seo-schema-preview" class="fp-seo-schema-preview" style="display: none;">
			<h3><?php esc_html_e( 'Schema Preview', 'fp-seo-performance' ); ?></h3>
			<pre id="fp-seo-schema-output"></pre>
		</div>
		<?php
	}

	/**
	 * Render styles.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style>
		<?php echo $this->styles_manager->get_styles(); ?>
		</style>
		<?php
	}

	// Style rendering methods removed - now handled by SchemaPageStylesManager

	/**
	 * Render scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		// Scripts will be extracted to SchemaScriptsManager
		// For now, keeping inline for compatibility
		$schema_types = AdvancedSchemaManager::get_schema_types();
		?>
		<script>
		jQuery(document).ready(function($) {
			const schemaNonce = '<?php echo esc_js( wp_create_nonce( 'fp_seo_schema_nonce' ) ); ?>';
			const messages = <?php echo wp_json_encode(
				array(
					'schemaGenerated'     => __( 'Schema generato correttamente. Consulta l\'anteprima qui sotto.', 'fp-seo-performance' ),
					'schemaDataRequired'  => __( 'Inserisci i dati dello schema in formato JSON.', 'fp-seo-performance' ),
					'schemaTypeRequired'  => __( 'Seleziona il tipo di schema da generare.', 'fp-seo-performance' ),
					'schemaInvalidJson'   => __( 'Il JSON inserito non è valido. Controlla la sintassi e riprova.', 'fp-seo-performance' ),
					'schemaErrorGeneric'  => __( 'Impossibile generare lo schema. Riprova tra qualche istante.', 'fp-seo-performance' ),
					'previewVisible'      => __( 'Anteprima visualizzata.', 'fp-seo-performance' ),
					'previewHidden'       => __( 'Anteprima nascosta.', 'fp-seo-performance' ),
					'permissionError'     => __( 'Permessi insufficienti per generare lo schema.', 'fp-seo-performance' ),
				)
			); ?>;

			const $form = $('#fp-seo-schema-form');
			const $notice = $form.find('[data-fp-seo-schema-notice]');
			const $typeField = $('#schema-type');
			const $dataField = $('#schema-data');
			const $generateButton = $('#fp-seo-generate-schema');
			const $previewButton = $('#fp-seo-preview-schema');
			const $previewContainer = $('#fp-seo-schema-preview');
			const $output = $('#fp-seo-schema-output');

			function speak(message, politeness) {
				if (!message) {
					return;
				}

				if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
					window.wp.a11y.speak(message, politeness || 'polite');
				}
			}

			function showNotice(message, type) {
				if (!$notice.length) {
					return;
				}

				const level = type === 'error' ? 'is-error' : (type === 'warning' ? 'is-warning' : 'is-success');
				$notice.removeClass('is-error is-success is-warning')
					.addClass(level)
					.text(message)
					.attr('hidden', false);

				speak(message, type === 'error' ? 'assertive' : 'polite');
			}

			function clearNotice() {
				if ($notice.length) {
					$notice.removeClass('is-error is-success is-warning')
						.text('')
						.attr('hidden', true);
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

			function setLoading(isLoading) {
				if (isLoading) {
					$generateButton.prop('disabled', true).addClass('is-loading');
				} else {
					$generateButton.prop('disabled', false).removeClass('is-loading');
				}
			}

			function parseSchemaError(response) {
				if (!response) {
					return messages.schemaErrorGeneric;
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

				return messages.schemaErrorGeneric;
			}

			$generateButton.on('click', function() {
				clearNotice();
				setFieldError($typeField, false);
				setFieldError($dataField, false);

				const schemaType = $typeField.val();
				const rawData = ($dataField.val() || '').trim();

				if (!schemaType) {
					setFieldError($typeField, true);
					showNotice(messages.schemaTypeRequired, 'error');
					$typeField.focus();
					return;
				}

				if (!rawData.length) {
					setFieldError($dataField, true);
					showNotice(messages.schemaDataRequired, 'error');
					$dataField.focus();
					return;
				}

				try {
					JSON.parse(rawData);
					setFieldError($dataField, false);
				} catch (error) {
					setFieldError($dataField, true);
					showNotice(messages.schemaInvalidJson, 'error');
					$dataField.focus();
					return;
				}

				setLoading(true);

				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'fp_seo_generate_schema',
						schema_type: schemaType,
						schema_data: rawData,
						nonce: schemaNonce
					}
				}).done(function(response) {
					if (response && response.success) {
						const formatted = JSON.stringify(response.data || {}, null, 2);
						$output.text(formatted);
						$previewContainer.show();
						showNotice(messages.schemaGenerated, 'success');
					} else {
						showNotice(parseSchemaError(response), 'error');
					}
				}).fail(function(_, textStatus) {
					showNotice(messages.schemaErrorGeneric + ' (' + textStatus + ')', 'error');
				}).always(function() {
					setLoading(false);
				});
			});

			$previewButton.on('click', function() {
				const isVisible = $previewContainer.toggle().is(':visible');
				speak(isVisible ? messages.previewVisible : messages.previewHidden, 'polite');
			});
		});
		</script>
		<?php
	}
}

