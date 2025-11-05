<?php
/**
 * Admin menu registration for the plugin.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;
use function add_action;
use function add_menu_page;
use function array_filter;
use function array_map;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function current_time;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_posts;
use function get_the_title;
use function get_transient;
use function human_time_diff;
use function is_array;
use function number_format_i18n;
use function round;
use function sprintf;
use function trim;
use function usort;
use function wp_count_posts;
use function wp_date;
use function wp_die;
use function time;

/**
 * Registers the primary admin menu entry for the plugin.
 */
class Menu {
	private const RECENT_RESULTS_MAX = 5;

	/**
	 * Hooks WordPress actions for the menu.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_head', array( $this, 'inject_modern_styles' ) );
	}

	/**
	 * Inject modern styles directly in admin head
	 */
	public function inject_modern_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen ) {
			return;
		}
		
		// Only on FP SEO pages
		if ( strpos( $screen->id, 'fp-seo-performance' ) === false ) {
			return;
		}
		
		?>
		<style id="fp-seo-modern-ui">
		:root {
			--fp-seo-primary: #2563eb;
			--fp-seo-primary-dark: #1d4ed8;
			--fp-seo-success: #059669;
			--fp-seo-warning: #f59e0b;
			--fp-seo-danger: #dc2626;
			--fp-seo-gray-50: #f9fafb;
			--fp-seo-gray-100: #f3f4f6;
			--fp-seo-gray-200: #e5e7eb;
			--fp-seo-gray-300: #d1d5db;
			--fp-seo-gray-600: #4b5563;
			--fp-seo-gray-700: #374151;
			--fp-seo-gray-900: #111827;
			--fp-seo-shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
			--fp-seo-shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
		}
		
		.wrap.fp-seo-performance-dashboard {
			background: var(--fp-seo-gray-50) !important;
			margin-left: -20px !important;
			margin-right: -20px !important;
			padding: 32px 40px 40px !important;
			min-height: calc(100vh - 32px) !important;
		}
		
		.fp-seo-performance-dashboard > h1 {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
			-webkit-background-clip: text !important;
			-webkit-text-fill-color: transparent !important;
			background-clip: text !important;
			font-size: 32px !important;
			font-weight: 700 !important;
			margin-bottom: 12px !important;
		}
		
		.fp-seo-performance-dashboard > .description {
			font-size: 16px !important;
			color: var(--fp-seo-gray-600) !important;
			margin-bottom: 28px !important;
		}
		
		.fp-seo-quick-stats {
			display: grid !important;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
			gap: 16px !important;
			margin: 20px 0 32px !important;
		}
		
		.fp-seo-quick-stat {
			background: linear-gradient(135deg, #fff 0%, var(--fp-seo-gray-50) 100%) !important;
			padding: 24px !important;
			border-radius: 8px !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			box-shadow: var(--fp-seo-shadow) !important;
			text-align: center !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-quick-stat:hover {
			transform: translateY(-4px) !important;
			box-shadow: var(--fp-seo-shadow-md) !important;
		}
		
		.fp-seo-quick-stat__icon {
			font-size: 32px !important;
			margin-bottom: 12px !important;
		}
		
		.fp-seo-quick-stat__value {
			display: block !important;
			font-size: 36px !important;
			font-weight: 700 !important;
			color: var(--fp-seo-gray-900) !important;
			line-height: 1 !important;
			margin-bottom: 8px !important;
		}
		
		.fp-seo-quick-stat__label {
			display: block !important;
			font-size: 12px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-600) !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
		}
		
		.fp-seo-performance-dashboard__grid {
			display: grid !important;
			grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)) !important;
			gap: 20px !important;
			margin-bottom: 32px !important;
		}
		
		.fp-seo-performance-dashboard__card {
			background: #fff !important;
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			padding: 24px !important;
			box-shadow: var(--fp-seo-shadow) !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-dashboard__card:hover {
			box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important;
			transform: translateY(-2px) !important;
		}
		
		.fp-seo-performance-dashboard__card > h2 {
			font-size: 18px !important;
			font-weight: 600 !important;
			color: var(--fp-seo-gray-900) !important;
			margin: 0 0 16px !important;
			padding-bottom: 12px !important;
			border-bottom: 2px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-performance-dashboard__metrics {
			list-style: none !important;
			margin: 0 !important;
			padding: 0 !important;
			display: grid !important;
			gap: 10px !important;
		}
		
		.fp-seo-performance-dashboard__metrics li {
			padding: 10px 12px !important;
			background: var(--fp-seo-gray-50) !important;
			border-radius: 6px !important;
			font-size: 13px !important;
			border-left: 3px solid var(--fp-seo-primary) !important;
		}
		
		.fp-seo-performance-dashboard table.widefat {
			border: 1px solid var(--fp-seo-gray-200) !important;
			border-radius: 8px !important;
			overflow: hidden !important;
			box-shadow: var(--fp-seo-shadow) !important;
			border-collapse: separate !important;
		}
		
		.fp-seo-performance-dashboard table.widefat thead {
			background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
		}
		
		.fp-seo-performance-dashboard table.widefat thead th {
			color: #fff !important;
			font-weight: 600 !important;
			text-transform: uppercase !important;
			font-size: 11px !important;
			padding: 14px 10px !important;
			border: none !important;
		}
		
		.fp-seo-performance-dashboard table.widefat tbody tr {
			transition: all 0.2s ease !important;
			border-bottom: 1px solid var(--fp-seo-gray-200) !important;
		}
		
		.fp-seo-performance-dashboard table.widefat tbody tr:hover {
			background-color: var(--fp-seo-gray-50) !important;
		}
		
		.fp-seo-performance-dashboard table.striped > tbody > tr:nth-child(odd) {
			background-color: transparent !important;
		}
		
		.fp-seo-score-display {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-width: 50px !important;
			padding: 6px 12px !important;
			border-radius: 999px !important;
			font-weight: 700 !important;
			font-size: 14px !important;
			color: #fff !important;
			box-shadow: var(--fp-seo-shadow) !important;
		}
		
		.fp-seo-score-display--high {
			background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
		}
		
		.fp-seo-score-display--medium {
			background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
		}
		
		.fp-seo-score-display--low {
			background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
		}
		
		.fp-seo-status-badge {
			display: inline-flex !important;
			align-items: center !important;
			gap: 6px !important;
			padding: 6px 12px !important;
			border-radius: 999px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-status-badge::before {
			content: '' !important;
			display: inline-block !important;
			width: 8px !important;
			height: 8px !important;
			border-radius: 50% !important;
		}
		
		.fp-seo-status-badge--healthy {
			background: #d1fae5 !important;
			color: #059669 !important;
		}
		
		.fp-seo-status-badge--healthy::before {
			background: #059669 !important;
			box-shadow: 0 0 0 3px rgba(5,150,105,0.2) !important;
		}
		
		.fp-seo-status-badge--needs-review {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		
		.fp-seo-status-badge--needs-review::before {
			background: #f59e0b !important;
		}
		
		.fp-seo-status-badge--critical {
			background: #fee2e2 !important;
			color: #dc2626 !important;
		}
		
		.fp-seo-status-badge--critical::before {
			background: #dc2626 !important;
		}
		
		.fp-seo-badge {
			display: inline-flex !important;
			padding: 4px 10px !important;
			border-radius: 999px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-badge--success {
			background: #d1fae5 !important;
			color: #065f46 !important;
		}
		
		.fp-seo-badge--warning {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		</style>
		<?php
	}

	/**
	 * Adds the top-level menu page.
	 */
	public function add_menu(): void {
		$capability = Options::get_capability();

		add_menu_page(
			__( 'SEO Performance', 'fp-seo-performance' ),
			__( 'SEO Performance', 'fp-seo-performance' ),
			$capability,
			'fp-seo-performance',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			81
		);
	}

		/**
		 * Renders the dashboard page.
		 */
	public function render_dashboard(): void {
		if ( ! current_user_can( Options::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		$options       = Options::get();
		$general       = is_array( $options['general'] ?? null ) ? $options['general'] : array();
		$analysis      = is_array( $options['analysis'] ?? null ) ? $options['analysis'] : array();
		$performance   = is_array( $options['performance'] ?? null ) ? $options['performance'] : array();
		$checks        = is_array( $analysis['checks'] ?? null ) ? $analysis['checks'] : array();
		$checks_total  = count( $checks );
		$checks_active = count( array_filter( array_map( 'boolval', $checks ) ) );

		$analyzer_enabled = (bool) ( $general['enable_analyzer'] ?? false );
		$badge_enabled    = (bool) ( $general['admin_bar_badge'] ?? false );

		$content_overview = $this->collect_content_overview();
		$bulk_stats       = $this->collect_bulk_audit_stats();

		$psi_enabled      = (bool) ( $performance['enable_psi'] ?? false );
		$psi_key          = trim( (string) ( $performance['psi_api_key'] ?? '' ) );
		$heuristics       = is_array( $performance['heuristics'] ?? null ) ? $performance['heuristics'] : array();
		$defaults         = Options::get_defaults();
		$heuristic_map    = is_array( $defaults['performance']['heuristics'] ?? null ) ? $defaults['performance']['heuristics'] : array();
		$heuristic_total  = count( $heuristic_map );
		$heuristic_active = count( array_filter( array_map( 'boolval', $heuristics ) ) );
		$signal_source    = ( $psi_enabled && '' !== $psi_key ) ? 'psi' : 'heuristics';

		?>
				<div class="wrap fp-seo-performance-dashboard">
						<h1><?php esc_html_e( 'SEO Performance Dashboard', 'fp-seo-performance' ); ?></h1>
						<p class="description"><?php esc_html_e( 'Panoramica completa dello stato SEO del tuo sito', 'fp-seo-performance' ); ?></p>

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

						<div class="fp-seo-performance-dashboard__grid">
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
																	esc_html( $this->format_last_updated( $bulk_stats['latest'] ) )
																);
																?>
														</li>
												</ul>
										<?php endif; ?>
								</div>

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
						</div>

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
														$score    = $entry['score'];
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
																		<?php if ( null !== $score ) : ?>
																				<?php
																				$score_class = 'fp-seo-score-display';
																				if ( $score >= 80 ) {
																					$score_class .= ' fp-seo-score-display--high';
																				} elseif ( $score >= 60 ) {
																					$score_class .= ' fp-seo-score-display--medium';
																				} else {
																					$score_class .= ' fp-seo-score-display--low';
																				}
																				?>
																				<span class="<?php echo esc_attr( $score_class ); ?>"><?php echo esc_html( number_format_i18n( (int) $score ) ); ?></span>
																		<?php else : ?>
																				<span class="fp-seo-score-display">—</span>
																		<?php endif; ?>
																</td>
																<td>
																		<?php
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
																		<span class="<?php echo esc_attr( $status_badge_class ); ?>"><?php echo esc_html( $this->status_label( $status ) ); ?></span>
																</td>
																<td>
																		<?php if ( $warnings > 0 ) : ?>
																				<span class="fp-seo-badge fp-seo-badge--warning"><?php echo esc_html( number_format_i18n( $warnings ) ); ?></span>
																		<?php else : ?>
																				<span class="fp-seo-badge fp-seo-badge--success">0</span>
																		<?php endif; ?>
																</td>
																<td><?php echo esc_html( $this->format_last_updated( (int) ( $entry['updated'] ?? 0 ) ) ); ?></td>
														</tr>
												<?php endforeach; ?>
										</tbody>
								</table>
						<?php endif; ?>
				</div>
				<?php
	}

		/**
		 * Collects content overview metrics.
		 *
		 * @return array{eligible:int,excluded:int}
		 */
	private function collect_content_overview(): array {
			$types = PostTypes::analyzable();

			$published_total = 0;
		foreach ( $types as $type ) {
						$counts = wp_count_posts( $type );
			if ( isset( $counts->publish ) ) {
						$published_total += (int) $counts->publish;
			}
		}

			$excluded_posts = get_posts(
				array(
					'post_type'              => $types,
					'post_status'            => 'publish',
				'fields'                 => 'ids',
				'meta_key'               => Metabox::META_EXCLUDE,
				'meta_value'             => '1',
				'posts_per_page'         => 500, // Limit to prevent memory issues
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				)
			);

		$excluded     = 0;
		$excluded_ids = array_map( 'intval', (array) $excluded_posts );
		$excluded     = count( array_unique( $excluded_ids ) );

		$eligible = $published_total - $excluded;
		if ( $eligible < 0 ) {
			$eligible = 0;
		}

		return array(
			'eligible' => $eligible,
			'excluded' => $excluded,
		);
	}

		/**
		 * Aggregates cached bulk audit statistics.
		 *
		 * @return array{
		 *     total:int,
		 *     average:int|null,
		 *     flagged:int,
		 *     latest:int|null,
		 *     status_totals:array<string,int>,
		 *     entries:array<int, array<string,mixed>>
		 * }
		 */
	private function collect_bulk_audit_stats(): array {
		$cached  = get_transient( BulkAuditPage::CACHE_KEY );
		$entries = array();

		if ( is_array( $cached ) ) {
			foreach ( $cached as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['post_id'] ) ) {
					continue;
				}

				$entries[] = array(
					'post_id'  => (int) $item['post_id'],
					'score'    => isset( $item['score'] ) && '' !== $item['score'] ? (int) $item['score'] : null,
					'status'   => isset( $item['status'] ) ? (string) $item['status'] : '',
					'warnings' => isset( $item['warnings'] ) ? (int) $item['warnings'] : 0,
					'updated'  => isset( $item['updated'] ) ? (int) $item['updated'] : 0,
				);
			}
		}

		if ( empty( $entries ) ) {
			return array(
				'total'         => 0,
				'average'       => null,
				'flagged'       => 0,
				'latest'        => null,
				'status_totals' => array(
					'green'  => 0,
					'yellow' => 0,
					'red'    => 0,
					'other'  => 0,
				),
				'entries'       => array(),
			);
		}

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				return $b['updated'] <=> $a['updated'];
			}
		);

		$total         = count( $entries );
		$score_sum     = 0;
		$score_counter = 0;
		$flagged       = 0;
		$latest        = 0;
		$status_totals = array(
			'green'  => 0,
			'yellow' => 0,
			'red'    => 0,
			'other'  => 0,
		);

		foreach ( $entries as $entry ) {
			if ( null !== $entry['score'] ) {
				$score_sum += (int) $entry['score'];
				++$score_counter;
			}

			$status = $entry['status'];
			if ( isset( $status_totals[ $status ] ) ) {
				++$status_totals[ $status ];
			} else {
				++$status_totals['other'];
			}

			if ( 'green' !== $status ) {
				++$flagged;
			}

			if ( $entry['updated'] > $latest ) {
				$latest = (int) $entry['updated'];
			}
		}

		$average = null;
		if ( $score_counter > 0 ) {
			$average = (int) round( $score_sum / $score_counter );
		}

		return array(
			'total'         => $total,
			'average'       => $average,
			'flagged'       => $flagged,
			'latest'        => $latest > 0 ? $latest : null,
			'status_totals' => $status_totals,
			'entries'       => $entries,
		);
	}

		/**
		 * Formats a timestamp into a relative/human readable string.
		 *
		 * @param int|null $timestamp Timestamp of the latest analysis, if available.
		 *
		 * @return string Human readable representation of the timestamp.
		 */
	private function format_last_updated( ?int $timestamp ): string {
		if ( empty( $timestamp ) || $timestamp <= 0 ) {
			return esc_html__( 'Not yet analyzed', 'fp-seo-performance' );
		}

				$now  = time();
				$diff = human_time_diff( $timestamp, $now );

		if ( '' !== $diff ) {
				/* translators: %s: Human readable time difference. */
				return sprintf( esc_html__( '%s ago', 'fp-seo-performance' ), $diff );
		}

			return wp_date( 'Y-m-d H:i', $timestamp );
	}

		/**
		 * Maps an internal score status to a human label.
		 *
		 * @param string $status Score state slug.
		 *
		 * @return string
		 */
	private function status_label( string $status ): string {
		switch ( $status ) {
			case 'green':
				return esc_html__( 'Healthy', 'fp-seo-performance' );
			case 'yellow':
				return esc_html__( 'Needs review', 'fp-seo-performance' );
			case 'red':
				return esc_html__( 'Critical', 'fp-seo-performance' );
			default:
				return esc_html__( 'Pending', 'fp-seo-performance' );
		}
	}
}
