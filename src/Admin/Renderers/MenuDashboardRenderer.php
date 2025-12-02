<?php
/**
 * Renderer for Menu Dashboard
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use function array_slice;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_the_title;
use function number_format_i18n;
use function sprintf;

/**
 * Renders the Menu Dashboard HTML
 */
class MenuDashboardRenderer {

	/**
	 * Maximum number of recent results to display
	 */
	private const RECENT_RESULTS_MAX = 5;

	/**
	 * Render the dashboard page
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @param array<string, mixed> $content_overview Content overview data.
	 * @param array<string, mixed> $bulk_stats Bulk audit statistics.
	 * @param int                   $checks_active Number of active checks.
	 * @param int                   $checks_total Total number of checks.
	 * @param bool                  $analyzer_enabled Whether analyzer is enabled.
	 * @param bool                  $badge_enabled Whether admin bar badge is enabled.
	 * @param string                $signal_source Signal source ('psi' or 'heuristics').
	 * @param int                   $heuristic_active Number of active heuristics.
	 * @param int                   $heuristic_total Total number of heuristics.
	 * @param callable              $format_last_updated Callback to format last updated timestamp.
	 * @param callable              $status_label Callback to get status label.
	 * @return void
	 */
	public function render(
		array $options,
		array $content_overview,
		array $bulk_stats,
		int $checks_active,
		int $checks_total,
		bool $analyzer_enabled,
		bool $badge_enabled,
		string $signal_source,
		int $heuristic_active,
		int $heuristic_total,
		callable $format_last_updated,
		callable $status_label
	): void {
		$this->render_header();
		$this->render_quick_stats( $checks_active, $content_overview, $bulk_stats );
		$this->render_dashboard_grid(
			$analyzer_enabled,
			$checks_active,
			$checks_total,
			$content_overview,
			$badge_enabled,
			$bulk_stats,
			$signal_source,
			$heuristic_active,
			$heuristic_total,
			$format_last_updated
		);
		$this->render_recent_results( $bulk_stats, $format_last_updated, $status_label );
		$this->render_footer();
	}

	/**
	 * Render dashboard header
	 *
	 * @return void
	 */
	private function render_header(): void {
		?>
		<div class="wrap fp-seo-performance-dashboard">
			<h1><?php esc_html_e( 'SEO Performance Dashboard', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Panoramica completa dello stato SEO del tuo sito', 'fp-seo-performance' ); ?></p>
		<?php
	}

	/**
	 * Render quick stats section
	 *
	 * @param int                   $checks_active Number of active checks.
	 * @param array<string, mixed> $content_overview Content overview data.
	 * @param array<string, mixed> $bulk_stats Bulk audit statistics.
	 * @return void
	 */
	private function render_quick_stats( int $checks_active, array $content_overview, array $bulk_stats ): void {
		?>
		<!-- Quick Stats -->
		<div class="fp-seo-quick-stats">
			<div class="fp-seo-quick-stat">
				<div class="fp-seo-quick-stat__icon">📊</div>
				<span class="fp-seo-quick-stat__value"><?php echo esc_html( number_format_i18n( $checks_active ) ); ?></span>
				<span class="fp-seo-quick-stat__label"><?php esc_html_e( 'Check attivi', 'fp-seo-performance' ); ?></span>
			</div>
			<div class="fp-seo-quick-stat">
				<div class="fp-seo-quick-stat__icon">📝</div>
				<span class="fp-seo-quick-stat__value"><?php echo esc_html( number_format_i18n( $content_overview['eligible'] ) ); ?></span>
				<span class="fp-seo-quick-stat__label"><?php esc_html_e( 'Contenuti analizzabili', 'fp-seo-performance' ); ?></span>
			</div>
			<?php if ( null !== $bulk_stats['average'] ) : ?>
				<div class="fp-seo-quick-stat">
					<div class="fp-seo-quick-stat__icon">⭐</div>
					<span class="fp-seo-quick-stat__value"><?php echo esc_html( number_format_i18n( $bulk_stats['average'] ) ); ?></span>
					<span class="fp-seo-quick-stat__label"><?php esc_html_e( 'Punteggio medio', 'fp-seo-performance' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="fp-seo-quick-stat">
				<div class="fp-seo-quick-stat__icon">⚠️</div>
				<span class="fp-seo-quick-stat__value"><?php echo esc_html( number_format_i18n( $bulk_stats['flagged'] ?? 0 ) ); ?></span>
				<span class="fp-seo-quick-stat__label"><?php esc_html_e( 'Da migliorare', 'fp-seo-performance' ); ?></span>
			</div>
		</div>

		<?php
		/**
		 * Action after quick stats
		 * Used by GSC to display Search Console metrics
		 */
		do_action( 'fpseo_dashboard_after_quick_stats' );
		?>
		<?php
	}

	/**
	 * Render dashboard grid with cards
	 *
	 * @param bool                  $analyzer_enabled Whether analyzer is enabled.
	 * @param int                   $checks_active Number of active checks.
	 * @param int                   $checks_total Total number of checks.
	 * @param array<string, mixed>  $content_overview Content overview data.
	 * @param bool                  $badge_enabled Whether admin bar badge is enabled.
	 * @param array<string, mixed>  $bulk_stats Bulk audit statistics.
	 * @param string                $signal_source Signal source.
	 * @param int                   $heuristic_active Number of active heuristics.
	 * @param int                   $heuristic_total Total number of heuristics.
	 * @param callable              $format_last_updated Callback to format last updated timestamp.
	 * @return void
	 */
	private function render_dashboard_grid(
		bool $analyzer_enabled,
		int $checks_active,
		int $checks_total,
		array $content_overview,
		bool $badge_enabled,
		array $bulk_stats,
		string $signal_source,
		int $heuristic_active,
		int $heuristic_total,
		callable $format_last_updated
	): void {
		?>
		<div class="fp-seo-performance-dashboard__grid">
			<?php $this->render_analyzer_status_card( $analyzer_enabled, $checks_active, $checks_total, $content_overview, $badge_enabled ); ?>
			<?php $this->render_bulk_audit_card( $bulk_stats, $format_last_updated ); ?>
			<?php $this->render_performance_signals_card( $signal_source, $heuristic_active, $heuristic_total ); ?>
		</div>
		<?php
	}

	/**
	 * Render analyzer status card
	 *
	 * @param bool                 $analyzer_enabled Whether analyzer is enabled.
	 * @param int                  $checks_active Number of active checks.
	 * @param int                  $checks_total Total number of checks.
	 * @param array<string, mixed> $content_overview Content overview data.
	 * @param bool                 $badge_enabled Whether admin bar badge is enabled.
	 * @return void
	 */
	private function render_analyzer_status_card(
		bool $analyzer_enabled,
		int $checks_active,
		int $checks_total,
		array $content_overview,
		bool $badge_enabled
	): void {
		?>
		<div class="card fp-seo-performance-dashboard__card">
			<h2><?php esc_html_e( 'Analyzer status', 'fp-seo-performance' ); ?></h2>
			<p>
				<?php if ( $analyzer_enabled ) : ?>
					<?php esc_html_e( 'The analyzer is currently enabled.', 'fp-seo-performance' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'The analyzer is disabled. Enable it in settings to generate scores.', 'fp-seo-performance' ); ?>
				<?php endif; ?>
			</p>
			<ul class="fp-seo-performance-dashboard__metrics">
				<li>
					<?php
					printf(
						/* translators: 1: Number of active checks, 2: Total available checks. */
						esc_html__( 'Checks active: %1$s of %2$s', 'fp-seo-performance' ),
						esc_html( number_format_i18n( $checks_active ) ),
						esc_html( number_format_i18n( $checks_total ) )
					);
					?>
				</li>
				<li>
					<?php
					printf(
						/* translators: %s: Count of eligible content items. */
						esc_html__( 'Eligible content items: %s', 'fp-seo-performance' ),
						esc_html( number_format_i18n( $content_overview['eligible'] ) )
					);
					?>
				</li>
				<?php if ( $content_overview['excluded'] > 0 ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: Count of excluded content items. */
							esc_html__( 'Excluded from analysis: %s', 'fp-seo-performance' ),
							esc_html( number_format_i18n( $content_overview['excluded'] ) )
						);
						?>
					</li>
				<?php endif; ?>
				<li>
					<?php if ( $badge_enabled ) : ?>
						<?php esc_html_e( 'Admin bar badge: enabled', 'fp-seo-performance' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Admin bar badge: disabled', 'fp-seo-performance' ); ?>
					<?php endif; ?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render bulk audit summary card
	 *
	 * @param array<string, mixed> $bulk_stats Bulk audit statistics.
	 * @param callable              $format_last_updated Callback to format last updated timestamp.
	 * @return void
	 */
	private function render_bulk_audit_card( array $bulk_stats, callable $format_last_updated ): void {
		?>
		<div class="card fp-seo-performance-dashboard__card">
			<h2><?php esc_html_e( 'Bulk audit summary', 'fp-seo-performance' ); ?></h2>
			<?php if ( 0 === $bulk_stats['total'] ) : ?>
				<p><?php esc_html_e( 'Run a bulk audit to populate score history and recommendations.', 'fp-seo-performance' ); ?></p>
			<?php else : ?>
				<ul class="fp-seo-performance-dashboard__metrics">
					<?php if ( null !== $bulk_stats['average'] ) : ?>
						<li>
							<?php
							printf(
								/* translators: %s: Average score across bulk audits. */
								esc_html__( 'Average score: %s', 'fp-seo-performance' ),
								esc_html( number_format_i18n( $bulk_stats['average'] ) )
							);
							?>
						</li>
					<?php endif; ?>
					<li>
						<?php
						printf(
							/* translators: 1: Number of flagged items, 2: Total analyzed items. */
							esc_html__( 'Flagged items: %1$s of %2$s', 'fp-seo-performance' ),
							esc_html( number_format_i18n( $bulk_stats['flagged'] ) ),
							esc_html( number_format_i18n( $bulk_stats['total'] ) )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: Count of healthy items. */
							esc_html__( 'Healthy items: %s', 'fp-seo-performance' ),
							esc_html( number_format_i18n( $bulk_stats['status_totals']['green'] ?? 0 ) )
						);
						?>
					</li>
					<li>
						<?php
						$needs_attention = (int) ( $bulk_stats['status_totals']['yellow'] ?? 0 ) + (int) ( $bulk_stats['status_totals']['red'] ?? 0 );
						printf(
							/* translators: %s: Count of items that need attention. */
							esc_html__( 'Needs attention: %s', 'fp-seo-performance' ),
							esc_html( number_format_i18n( $needs_attention ) )
						);
						?>
					</li>
					<li>
						<?php
						printf(
							/* translators: %s: Datetime of the latest bulk analysis. */
							esc_html__( 'Last analyzed: %s', 'fp-seo-performance' ),
							esc_html( $format_last_updated( $bulk_stats['latest'] ) )
						);
						?>
					</li>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render performance signals card
	 *
	 * @param string $signal_source Signal source.
	 * @param int    $heuristic_active Number of active heuristics.
	 * @param int    $heuristic_total Total number of heuristics.
	 * @return void
	 */
	private function render_performance_signals_card( string $signal_source, int $heuristic_active, int $heuristic_total ): void {
		?>
		<div class="card fp-seo-performance-dashboard__card">
			<h2><?php esc_html_e( 'Performance signals', 'fp-seo-performance' ); ?></h2>
			<?php if ( 'psi' === $signal_source ) : ?>
				<p><?php esc_html_e( 'PageSpeed Insights integration is active and will refresh metrics automatically.', 'fp-seo-performance' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Local heuristics are being used to estimate performance signals.', 'fp-seo-performance' ); ?></p>
			<?php endif; ?>
			<ul class="fp-seo-performance-dashboard__metrics">
				<li>
					<?php if ( 'psi' === $signal_source ) : ?>
						<?php esc_html_e( 'Signal source: PageSpeed Insights', 'fp-seo-performance' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Signal source: Local heuristics', 'fp-seo-performance' ); ?>
					<?php endif; ?>
				</li>
				<li>
					<?php
					printf(
						/* translators: 1: Number of enabled heuristics, 2: Total heuristics. */
						esc_html__( 'Heuristics enabled: %1$s of %2$s', 'fp-seo-performance' ),
						esc_html( number_format_i18n( $heuristic_active ) ),
						esc_html( number_format_i18n( $heuristic_total ) )
					);
					?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render recent audit results table
	 *
	 * @param array<string, mixed> $bulk_stats Bulk audit statistics.
	 * @param callable             $format_last_updated Callback to format last updated timestamp.
	 * @param callable             $status_label Callback to get status label.
	 * @return void
	 */
	private function render_recent_results( array $bulk_stats, callable $format_last_updated, callable $status_label ): void {
		?>
		<h2><?php esc_html_e( 'Recent audit results', 'fp-seo-performance' ); ?></h2>
		<?php if ( empty( $bulk_stats['entries'] ) ) : ?>
			<p><?php esc_html_e( 'No recent audits recorded. Use the Bulk Auditor to analyze your content library.', 'fp-seo-performance' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Content item', 'fp-seo-performance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'fp-seo-performance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'fp-seo-performance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Warnings', 'fp-seo-performance' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last analyzed', 'fp-seo-performance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$rows = array_slice( $bulk_stats['entries'], 0, self::RECENT_RESULTS_MAX );
					foreach ( $rows as $entry ) :
						$post_id = (int) ( $entry['post_id'] ?? 0 );
						if ( $post_id <= 0 ) {
							continue;
						}

						$title    = (string) get_the_title( $post_id );
						$edit_url = get_edit_post_link( $post_id );
						$score    = $entry['score'] ?? null;
						$warnings = isset( $entry['warnings'] ) ? (int) $entry['warnings'] : 0;
						$status   = isset( $entry['status'] ) ? (string) $entry['status'] : '';
						?>
						<tr>
							<td>
								<?php if ( $edit_url ) : ?>
									<a href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $title ); ?></strong></a>
								<?php else : ?>
									<strong><?php echo esc_html( $title ); ?></strong>
								<?php endif; ?>
							</td>
							<td>
								<?php $this->render_score_cell( $score ); ?>
							</td>
							<td>
								<?php $this->render_status_cell( $status, $status_label ); ?>
							</td>
							<td>
								<?php $this->render_warnings_cell( $warnings ); ?>
							</td>
							<td><?php echo esc_html( $format_last_updated( (int) ( $entry['updated'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render score cell
	 *
	 * @param int|null $score Score value.
	 * @return void
	 */
	private function render_score_cell( ?int $score ): void {
		if ( null !== $score ) {
			$score_class = 'fp-seo-score-display';
			if ( $score >= 80 ) {
				$score_class .= ' fp-seo-score-display--high';
			} elseif ( $score >= 60 ) {
				$score_class .= ' fp-seo-score-display--medium';
			} else {
				$score_class .= ' fp-seo-score-display--low';
			}
			?>
			<span class="<?php echo esc_attr( $score_class ); ?>"><?php echo esc_html( number_format_i18n( $score ) ); ?></span>
			<?php
		} else {
			?>
			<span class="fp-seo-score-display">—</span>
			<?php
		}
	}

	/**
	 * Render status cell
	 *
	 * @param string   $status Status value.
	 * @param callable $status_label Callback to get status label.
	 * @return void
	 */
	private function render_status_cell( string $status, callable $status_label ): void {
		$status_badge_class = 'fp-seo-status-badge';
		switch ( $status ) {
			case 'green':
				$status_badge_class .= ' fp-seo-status-badge--healthy';
				break;
			case 'yellow':
				$status_badge_class .= ' fp-seo-status-badge--needs-review';
				break;
			case 'red':
				$status_badge_class .= ' fp-seo-status-badge--critical';
				break;
			default:
				$status_badge_class .= ' fp-seo-status-badge--pending';
		}
		?>
		<span class="<?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( $status_label( $status ) ); ?></span>
		<?php
	}

	/**
	 * Render warnings cell
	 *
	 * @param int $warnings Number of warnings.
	 * @return void
	 */
	private function render_warnings_cell( int $warnings ): void {
		if ( $warnings > 0 ) {
			?>
			<span class="fp-seo-badge fp-seo-badge--warning"><?php echo esc_html( number_format_i18n( $warnings ) ); ?></span>
			<?php
		} else {
			?>
			<span class="fp-seo-badge fp-seo-badge--success">0</span>
			<?php
		}
	}

	/**
	 * Render dashboard footer
	 *
	 * @return void
	 */
	private function render_footer(): void {
		?>
		</div>
		<?php
	}
}

