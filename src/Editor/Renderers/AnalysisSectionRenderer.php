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
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( 'FP SEO DEBUG: AnalysisSectionRenderer::render() called - checks_count=' . count( $checks ) );
		}

		if ( ! is_array( $checks ) ) {
			$checks = array();
		}
		
		// Group checks by status
		$grouped = array(
			Result::STATUS_FAIL => array(),
			Result::STATUS_WARN => array(),
			Result::STATUS_PASS => array(),
		);

		foreach ( $checks as $check ) {
			if ( ! is_array( $check ) ) {
				continue;
			}
			$status              = $check['status'] ?? Result::STATUS_PASS;
			$grouped[ $status ][] = $check;
		}

		$has_issues   = ! empty( $grouped[Result::STATUS_FAIL] ) || ! empty( $grouped[Result::STATUS_WARN] );
		$total_checks = count( $checks );
		$total_pass   = count( $grouped[Result::STATUS_PASS] );
		$total_warn   = count( $grouped[Result::STATUS_WARN] );
		$total_fail   = count( $grouped[Result::STATUS_FAIL] );

		?>
		<!-- Section: ANALYSIS -->
		<div class="fp-seo-performance-metabox__section fp-seo-analysis-section" style="border-left: 4px solid #ef4444; margin-bottom: 16px;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb;">
				<span style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600;">
					<span class="fp-seo-section-icon">📊</span>
					<?php esc_html_e( 'Analisi SEO', 'fp-seo-performance' ); ?>
				</span>
				<?php if ( $has_issues ) : ?>
					<span class="fp-seo-badge fp-seo-badge-warning" style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
						<?php echo esc_html( (string) ( $total_fail + $total_warn ) ); ?> <?php esc_html_e( 'da migliorare', 'fp-seo-performance' ); ?>
					</span>
				<?php elseif ( $total_checks > 0 ) : ?>
					<span class="fp-seo-badge" style="background: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
						<?php echo esc_html( (string) $total_pass ); ?> <?php esc_html_e( 'ottimali', 'fp-seo-performance' ); ?>
					</span>
				<?php endif; ?>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<?php
				$checks_empty = ( 0 === $total_checks );
				if ( $checks_empty ) : ?>
					<?php
					// Check if analyzer is enabled to show appropriate message
					$options = get_option( 'fp_seo_perf_options', array() );
					$analyzer_enabled = ! empty( $options['general']['enable_analyzer'] ?? false );
					?>
					<?php if ( ! $analyzer_enabled ) : ?>
						<div style="padding: 16px 20px; background: #fef3c7; border: 2px solid #f59e0b; border-radius: 8px; margin: 0;">
							<p style="margin: 0 0 8px; color: #92400e; font-weight: 600; font-size: 14px;">
								⚠️ <?php esc_html_e( 'Analisi non disponibile', 'fp-seo-performance' ); ?>
							</p>
							<p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.5;">
								<?php esc_html_e( 'L\'analyzer è disabilitato. Vai in', 'fp-seo-performance' ); ?> 
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-seo-performance-settings&tab=general' ) ); ?>" style="color: #92400e; text-decoration: underline;">
									<?php esc_html_e( 'Impostazioni > General', 'fp-seo-performance' ); ?>
								</a> 
								<?php esc_html_e( 'per abilitarlo.', 'fp-seo-performance' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div style="padding: 16px 20px; background: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; margin: 0; text-align: center;">
							<p style="margin: 0; color: #059669; font-weight: 600; font-size: 14px;">
								✅ <?php esc_html_e( 'Analisi in corso...', 'fp-seo-performance' ); ?>
							</p>
							<p style="margin: 8px 0 0; color: #047857; font-size: 13px; line-height: 1.5;">
								<?php esc_html_e( 'L\'analisi SEO verrà eseguita al prossimo salvataggio del post.', 'fp-seo-performance' ); ?>
							</p>
						</div>
					<?php endif; ?>
				<?php elseif ( $total_checks > 0 && $total_pass === $total_checks && $total_warn === 0 && $total_fail === 0 ) : ?>
					<p style="color: #10b981; font-weight: 600; padding: 16px 20px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #10b981; border-radius: 8px; margin: 0; text-align: center; font-size: 14px;">
						<?php 
						printf(
							esc_html__( '✅ Tutti gli %d indicatori sono ottimali! 🎉', 'fp-seo-performance' ),
							$total_checks
						);
						?>
					</p>
				<?php else : ?>
					<ul class="fp-seo-checks-list" style="list-style: none; padding: 0; margin: 0;">
						<?php
						// Render critical first
						foreach ( $grouped[Result::STATUS_FAIL] as $check ) {
							$this->render_check_item( $check, Result::STATUS_FAIL );
						}
						// Then warnings
						foreach ( $grouped[Result::STATUS_WARN] as $check ) {
							$this->render_check_item( $check, Result::STATUS_WARN );
						}
						// Finally passes (collapsed by default, more compact)
						if ( ! empty( $grouped[Result::STATUS_PASS] ) ) :
							?>
							<details class="fp-seo-checks-details" style="margin-top: 8px;">
								<summary class="fp-seo-checks-summary" style="cursor: pointer; padding: 8px 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; font-size: 12px; font-weight: 600; color: #065f46; list-style: none;">
									<?php
									printf(
										esc_html__( '✅ %d indicatori ottimali', 'fp-seo-performance' ),
										count( $grouped[Result::STATUS_PASS] )
									);
									?>
								</summary>
								<ul class="fp-seo-checks-list" style="list-style: none; padding: 0; margin: 8px 0 0 0;">
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
		// Usa l'hint diretto dal check se disponibile (più compatto e diretto)
		$hint = $check['hint'] ?? $check['fix_hint'] ?? '';
		// Fallback: usa howto solo se non c'è hint
		$howto = empty( $hint ) ? $this->check_help_text->get_howto( $check_id ) : '';

		$status_class = 'fp-seo-check--' . $status;
		$icon = $status === Result::STATUS_PASS ? '✅' : ( $status === Result::STATUS_WARN ? '⚠️' : '❌' );
		
		// Mostra help solo per FAIL e WARN, non per PASS
		$show_help = ( $status !== Result::STATUS_PASS ) && ( ! empty( $hint ) || ! empty( $howto ) );

		?>
		<li class="fp-seo-check <?php echo esc_attr( $status_class ); ?>" style="margin-bottom: 6px; padding: 8px 10px; border-left: 3px solid <?php echo $status === Result::STATUS_FAIL ? '#ef4444' : ( $status === Result::STATUS_WARN ? '#f59e0b' : '#10b981' ); ?>; background: <?php echo $status === Result::STATUS_FAIL ? '#fef2f2' : ( $status === Result::STATUS_WARN ? '#fffbeb' : '#f0fdf4' ); ?>; border-radius: 3px;">
			<div class="fp-seo-check__header" style="display: flex; align-items: flex-start; gap: 6px; font-weight: 600; font-size: 12px; color: <?php echo $status === Result::STATUS_FAIL ? '#991b1b' : ( $status === Result::STATUS_WARN ? '#92400e' : '#065f46' ); ?>; line-height: 1.4;">
				<span class="fp-seo-check__icon" style="font-size: 14px; flex-shrink: 0; margin-top: 1px;"><?php echo esc_html( $icon ); ?></span>
				<div style="flex: 1; min-width: 0;">
					<span class="fp-seo-check__message" style="display: block; margin-bottom: <?php echo $show_help ? '4px' : '0'; ?>;"><?php echo esc_html( $message ); ?></span>
					<?php if ( $show_help ) : ?>
						<div class="fp-seo-check__help" style="font-size: 11px; line-height: 1.4; color: #4b5563; font-weight: 400; margin-top: 2px;">
							<?php if ( ! empty( $hint ) ) : ?>
								<?php echo esc_html( $hint ); ?>
							<?php elseif ( ! empty( $howto ) ) : ?>
								<?php echo esc_html( $howto ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</li>
		<?php
	}
}
















