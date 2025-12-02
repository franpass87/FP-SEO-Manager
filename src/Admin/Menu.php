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

use FP\SEO\Admin\Renderers\MenuDashboardRenderer;
use FP\SEO\Admin\Styles\MenuStylesManager;
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
	 * @var MenuStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var MenuDashboardRenderer|null
	 */
	private $renderer;

	/**
	 * Hooks WordPress actions for the menu.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );

		// Initialize and register styles manager
		$this->styles_manager = new MenuStylesManager();
		$this->styles_manager->register_hooks();

		// Initialize renderer
		$this->renderer = new MenuDashboardRenderer();
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

		if ( $this->renderer ) {
			$this->renderer->render(
				$options,
				$content_overview,
				$bulk_stats,
				$checks_active,
				$checks_total,
				$analyzer_enabled,
				$badge_enabled,
				$signal_source,
				$heuristic_active,
				$heuristic_total,
				array( $this, 'format_last_updated' ),
				array( $this, 'status_label' )
			);
		}
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
