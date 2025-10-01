<?php
/**
 * Admin menu registration for the plugin.
 *
 * @package FP\SEO
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

/**
 * Registers the primary admin menu entry for the plugin.
 */
class Menu {
        private const RECENT_RESULTS_MAX   = 5;

        /**
         * Hooks WordPress actions for the menu.
         */
        public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
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

                $psi_enabled   = (bool) ( $performance['enable_psi'] ?? false );
                $psi_key       = trim( (string) ( $performance['psi_api_key'] ?? '' ) );
                $heuristics    = is_array( $performance['heuristics'] ?? null ) ? $performance['heuristics'] : array();
                $defaults      = Options::get_defaults();
                $heuristic_map = is_array( $defaults['performance']['heuristics'] ?? null ) ? $defaults['performance']['heuristics'] : array();
                $heuristic_total = count( $heuristic_map );
                $heuristic_active = count( array_filter( array_map( 'boolval', $heuristics ) ) );
                $signal_source    = ( $psi_enabled && '' !== $psi_key ) ? 'psi' : 'heuristics';

                ?>
                <div class="wrap fp-seo-performance-dashboard">
                        <h1><?php esc_html_e( 'SEO Performance Dashboard', 'fp-seo-performance' ); ?></h1>
                        <p class="description"><?php esc_html_e( 'Review analyzer health, bulk audit trends, and recent results at a glance.', 'fp-seo-performance' ); ?></p>

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
                                                                esc_html__( 'Checks active: %1$s of %2$s', 'fp-seo-performance' ),
                                                                esc_html( number_format_i18n( $checks_active ) ),
                                                                esc_html( number_format_i18n( $checks_total ) )
                                                        );
                                                        ?>
                                                </li>
                                                <li>
                                                        <?php
                                                        printf(
                                                                esc_html__( 'Eligible content items: %s', 'fp-seo-performance' ),
                                                                esc_html( number_format_i18n( $content_overview['eligible'] ) )
                                                        );
                                                        ?>
                                                </li>
                                                <?php if ( $content_overview['excluded'] > 0 ) : ?>
                                                        <li>
                                                                <?php
                                                                printf(
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
                                                                                esc_html__( 'Average score: %s', 'fp-seo-performance' ),
                                                                                esc_html( number_format_i18n( $bulk_stats['average'] ) )
                                                                        );
                                                                        ?>
                                                                </li>
                                                        <?php endif; ?>
                                                        <li>
                                                                <?php
                                                                printf(
                                                                        esc_html__( 'Flagged items: %1$s of %2$s', 'fp-seo-performance' ),
                                                                        esc_html( number_format_i18n( $bulk_stats['flagged'] ) ),
                                                                        esc_html( number_format_i18n( $bulk_stats['total'] ) )
                                                                );
                                                                ?>
                                                        </li>
                                                        <li>
                                                                <?php
                                                                printf(
                                                                        esc_html__( 'Healthy items: %s', 'fp-seo-performance' ),
                                                                        esc_html( number_format_i18n( $bulk_stats['status_totals']['green'] ?? 0 ) )
                                                                );
                                                                ?>
                                                        </li>
                                                        <li>
                                                                <?php
                                                                $needs_attention = (int) ( $bulk_stats['status_totals']['yellow'] ?? 0 ) + (int) ( $bulk_stats['status_totals']['red'] ?? 0 );
                                                                printf(
                                                                        esc_html__( 'Needs attention: %s', 'fp-seo-performance' ),
                                                                        esc_html( number_format_i18n( $needs_attention ) )
                                                                );
                                                                ?>
                                                        </li>
                                                        <li>
                                                                <?php
                                                                printf(
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
                                                                                <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $title ); ?></a>
                                                                        <?php else : ?>
                                                                                <?php echo esc_html( $title ); ?>
                                                                        <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                        <?php echo null === $score ? '—' : esc_html( number_format_i18n( (int) $score ) ); ?>
                                                                </td>
                                                                <td><?php echo esc_html( $this->status_label( $status ) ); ?></td>
                                                                <td><?php echo esc_html( number_format_i18n( $warnings ) ); ?></td>
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
                        if ( is_object( $counts ) && isset( $counts->publish ) ) {
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
                                'posts_per_page'         => -1,
                                'nopaging'               => true,
                                'no_found_rows'          => true,
                                'update_post_meta_cache' => false,
                                'update_post_term_cache' => false,
                                'suppress_filters'       => true,
                        )
                );

                $excluded = 0;
                if ( is_array( $excluded_posts ) ) {
                        $excluded = count( array_unique( array_map( 'intval', $excluded_posts ) ) );
                }

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
                $cached = get_transient( BulkAuditPage::CACHE_KEY );
                $entries = array();

                if ( is_array( $cached ) ) {
                        foreach ( $cached as $item ) {
                                if ( ! is_array( $item ) || ! isset( $item['post_id'] ) ) {
                                        continue;
                                }

                                $entries[] = array(
                                        'post_id' => (int) $item['post_id'],
                                        'score'   => isset( $item['score'] ) && '' !== $item['score'] ? (int) $item['score'] : null,
                                        'status'  => isset( $item['status'] ) ? (string) $item['status'] : '',
                                        'warnings'=> isset( $item['warnings'] ) ? (int) $item['warnings'] : 0,
                                        'updated' => isset( $item['updated'] ) ? (int) $item['updated'] : 0,
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
                                return ( $b['updated'] ?? 0 ) <=> ( $a['updated'] ?? 0 );
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
                                $score_sum     += (int) $entry['score'];
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

                        if ( ( $entry['updated'] ?? 0 ) > $latest ) {
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
         */
        private function format_last_updated( ?int $timestamp ): string {
                if ( empty( $timestamp ) || $timestamp <= 0 ) {
                        return esc_html__( 'Not yet analyzed', 'fp-seo-performance' );
                }

                $now = current_time( 'timestamp' );

                if ( $now > 0 ) {
                        $diff = human_time_diff( $timestamp, $now );
                        if ( '' !== $diff ) {
                                return sprintf( esc_html__( '%s ago', 'fp-seo-performance' ), $diff );
                        }
                }

                return wp_date( 'Y-m-d H:i', $timestamp );
        }

        /**
         * Maps an internal score status to a human label.
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
