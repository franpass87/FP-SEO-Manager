<?php
/**
 * Renders the analysis section with SEO checks.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Analysis\Result;
use FP\SEO\Editor\CheckHelpText;
use function esc_attr;
use function esc_attr_e;
use function esc_html;
use function esc_html_e;

/**
 * Renders the analysis section.
 */
class AnalysisSectionRenderer {
	/**
	 * @var CheckHelpText
	 */
	private $check_help_text;

	/**
	 * Constructor.
	 *
	 * @param CheckHelpText $check_help_text Check help text instance.
	 */
	public function __construct( CheckHelpText $check_help_text ) {
		$this->check_help_text = $check_help_text;
	}

	/**
	 * Render analysis section.
	 *
	 * @param array $checks Array of check results.
	 * @return void
	 */
	public function render( array $checks ): void {
		// Group checks by status
		$grouped = array(
			Result::STATUS_FAIL => array(),
			Result::STATUS_WARN => array(),
			Result::STATUS_PASS => array(),
		);

		foreach ( $checks as $check ) {
			$status = $check['status'] ?? Result::STATUS_PASS;
			$grouped[ $status ][] = $check;
		}

		// Render critical issues first
		$has_issues = ! empty( $grouped[Result::STATUS_FAIL] ) || ! empty( $grouped[Result::STATUS_WARN] );

		?>
		<!-- Section: ANALYSIS -->
		<div class="fp-seo-performance-metabox__section fp-seo-analysis-section" style="border-left: 4px solid #ef4444;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📊</span>
					<?php esc_html_e( 'Analisi SEO', 'fp-seo-performance' ); ?>
				</span>
				<?php if ( $has_issues ) : ?>
					<span class="fp-seo-badge fp-seo-badge-warning">
						<?php echo esc_html( (string) ( count( $grouped[Result::STATUS_FAIL] ) + count( $grouped[Result::STATUS_WARN] ) ) ); ?> <?php esc_html_e( 'da migliorare', 'fp-seo-performance' ); ?>
					</span>
				<?php endif; ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php if ( empty( $checks ) ) : ?>
					<p style="color: #10b981; font-weight: 600;">
						<?php esc_html_e( 'Tutti gli indicatori sono ottimali! 🎉', 'fp-seo-performance' ); ?>
					</p>
				<?php else : ?>
					<ul class="fp-seo-checks-list">
						<?php
						// Render critical first
						foreach ( $grouped[Result::STATUS_FAIL] as $check ) {
							$this->render_check_item( $check, Result::STATUS_FAIL );
						}
						// Then warnings
						foreach ( $grouped[Result::STATUS_WARN] as $check ) {
							$this->render_check_item( $check, Result::STATUS_WARN );
						}
						// Finally passes (collapsed by default)
						if ( ! empty( $grouped[Result::STATUS_PASS] ) ) :
							?>
							<details class="fp-seo-checks-details">
								<summary class="fp-seo-checks-summary">
									<?php
									printf(
										esc_html__( '✅ %d indicatori ottimali', 'fp-seo-performance' ),
										count( $grouped[Result::STATUS_PASS] )
									);
									?>
								</summary>
								<ul class="fp-seo-checks-list">
									<?php
									foreach ( $grouped[Result::STATUS_PASS] as $check ) {
										$this->render_check_item( $check, Result::STATUS_PASS );
									}
									?>
								</ul>
							</details>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single check item.
	 *
	 * @param array  $check Check data.
	 * @param string $status Check status.
	 * @return void
	 */
	private function render_check_item( array $check, string $status ): void {
		$check_id = $check['id'] ?? '';
		$message = $check['message'] ?? '';
		$importance = $this->check_help_text->get_importance( $check_id );
		$howto = $this->check_help_text->get_howto( $check_id );
		$example = $this->check_help_text->get_example( $check_id );

		$status_class = 'fp-seo-check--' . $status;
		$icon = $status === Result::STATUS_PASS ? '✅' : ( $status === Result::STATUS_WARN ? '⚠️' : '❌' );

		?>
		<li class="fp-seo-check <?php echo esc_attr( $status_class ); ?>">
			<div class="fp-seo-check__header">
				<span class="fp-seo-check__icon"><?php echo esc_html( $icon ); ?></span>
				<span class="fp-seo-check__message"><?php echo esc_html( $message ); ?></span>
			</div>
			<?php if ( ! empty( $importance ) || ! empty( $howto ) || ! empty( $example ) ) : ?>
				<div class="fp-seo-check__help">
					<?php if ( ! empty( $importance ) ) : ?>
						<p><strong><?php esc_html_e( 'Importanza:', 'fp-seo-performance' ); ?></strong> <?php echo esc_html( $importance ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $howto ) ) : ?>
						<p><strong><?php esc_html_e( 'Come risolvere:', 'fp-seo-performance' ); ?></strong> <?php echo esc_html( $howto ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $example ) ) : ?>
						<p><strong><?php esc_html_e( 'Esempio:', 'fp-seo-performance' ); ?></strong> <code><?php echo esc_html( $example ); ?></code></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</li>
		<?php
	}
}


