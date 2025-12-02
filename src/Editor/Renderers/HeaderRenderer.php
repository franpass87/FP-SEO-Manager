<?php
/**
 * Renders the metabox header section (banner, controls, score).
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use function checked;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;
use function sprintf;

/**
 * Renders the metabox header.
 */
class HeaderRenderer {
	/**
	 * Render header section (banner, controls, score).
	 *
	 * @param bool   $excluded Whether post is excluded.
	 * @param int    $score_value Score value.
	 * @param string $score_status Score status.
	 * @return void
	 */
	public function render( bool $excluded, int $score_value, string $score_status ): void {
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
}

