<?php
/**
 * Bulk auditor admin page implementation.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;
use WP_Post;
use WP_Query;
use function __;
use function absint;
use function admin_url;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function check_admin_referer;
use function check_ajax_referer;
use function count;
use function current_time;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function fputcsv;
use function get_edit_post_link;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_type_object;
use function get_the_title;
use function get_transient;
use function gmdate;
use function html_entity_decode;
use function header;
use function in_array;
use function is_array;
use function is_string;
use function number_format_i18n;
use function sanitize_key;
use function selected;
use function set_transient;
use function uasort;
use function wp_create_nonce;
use function wp_date;
use function wp_die;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_nonce_field;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_strip_all_tags;
use function wp_unslash;

/**
 * Provides the bulk audit screen with batch analysis tools.
 */
class BulkAuditPage {
	private const PAGE_SLUG     = 'fp-seo-performance-bulk';
	private const PAGE_PARENT   = 'fp-seo-performance';
	private const AJAX_ACTION   = 'fp_seo_performance_bulk_analyze';
	private const EXPORT_ACTION = 'fp_seo_performance_bulk_export';
	private const NONCE_ACTION  = 'fp_seo_performance_bulk';
	public const CACHE_KEY      = 'fp_seo_performance_bulk_results';
	private const CACHE_TTL     = 86400;
	private const CACHE_LIMIT   = 500;

		/**
		 * Hooks WordPress actions for the page.
		 */
	public function register(): void {
			add_action( 'admin_menu', array( $this, 'add_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax_analyze' ) );
			add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );
	}

		/**
		 * Adds the submenu entry.
		 */
	public function add_page(): void {
			$capability = Options::get_capability();

			add_submenu_page(
				self::PAGE_PARENT,
				__( 'Bulk Auditor', 'fp-seo-performance' ),
				__( 'Bulk Auditor', 'fp-seo-performance' ),
				$capability,
				self::PAGE_SLUG,
				array( $this, 'render' )
			);
	}

		/**
		 * Enqueue assets when viewing the bulk auditor screen.
		 *
		 * @param string $hook Current admin page hook name.
		 */
	public function enqueue_assets( string $hook ): void {
		if ( 'fp-seo-performance_page_' . self::PAGE_SLUG !== $hook ) {
				return;
		}

			wp_enqueue_style( 'fp-seo-performance-admin' );
			wp_enqueue_script( 'fp-seo-performance-admin' );
			wp_enqueue_script( 'fp-seo-performance-bulk' );

			wp_localize_script(
				'fp-seo-performance-bulk',
				'fpSeoPerformanceBulk',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
					'action'    => self::AJAX_ACTION,
					'chunkSize' => 10,
					'messages'  => array(
						/* translators: 1: Number of items processed, 2: Total items selected. */
						'processing'   => __( 'Analyzing %1$d of %2$d items…', 'fp-seo-performance' ),
						/* translators: %1$d: Number of items analyzed. */
						'complete'     => __( 'Analysis complete for %1$d items.', 'fp-seo-performance' ),
						'noneSelected' => __( 'Select at least one item to analyze.', 'fp-seo-performance' ),
						'error'        => __( 'Analysis request failed. Please try again.', 'fp-seo-performance' ),
					),
				)
			);
	}

		/**
		 * Displays the bulk auditor table and controls.
		 */
	public function render(): void {
		if ( ! current_user_can( Options::get_capability() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

			$filters  = $this->get_filters();
			$posts    = $this->query_posts( $filters['post_type'], $filters['status'] );
			$results  = $this->get_cached_results();
			$types    = $this->get_allowed_post_types();
			$statuses = $this->get_allowed_statuses();

		?>
				<div class="wrap fp-seo-performance-bulk">
						<h1><?php esc_html_e( 'Bulk Auditor', 'fp-seo-performance' ); ?></h1>

					<?php if ( ! $this->is_analyzer_enabled() ) : ?>
								<div class="notice notice-warning">
										<p><?php echo esc_html__( 'The analyzer is currently disabled. Enable it in the settings to run bulk audits.', 'fp-seo-performance' ); ?></p>
								</div>
						<?php endif; ?>

						<form method="get" class="fp-seo-performance-bulk__filters">
								<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
								<label for="fp-seo-performance-filter-type">
										<span class="screen-reader-text"><?php esc_html_e( 'Filter by type', 'fp-seo-performance' ); ?></span>
										<select name="post_type" id="fp-seo-performance-filter-type">
												<option value="all" <?php selected( 'all', $filters['post_type'] ); ?>><?php esc_html_e( 'All types', 'fp-seo-performance' ); ?></option>
											<?php foreach ( $types as $type ) : ?>
														<?php $type_object = get_post_type_object( $type ); ?>
														<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['post_type'], $type ); ?>>
																<?php echo esc_html( $type_object && isset( $type_object->labels->singular_name ) ? $type_object->labels->singular_name : $type ); ?>
														</option>
												<?php endforeach; ?>
										</select>
								</label>
								<label for="fp-seo-performance-filter-status">
										<span class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'fp-seo-performance' ); ?></span>
										<select name="status" id="fp-seo-performance-filter-status">
												<option value="any" <?php selected( 'any', $filters['status'] ); ?>><?php esc_html_e( 'All statuses', 'fp-seo-performance' ); ?></option>
											<?php foreach ( $statuses as $status ) : ?>
														<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>>
																<?php echo esc_html( ucfirst( $status ) ); ?>
														</option>
												<?php endforeach; ?>
										</select>
								</label>
								<button type="submit" class="button"><?php esc_html_e( 'Apply filters', 'fp-seo-performance' ); ?></button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-fp-seo-bulk-form>
							<?php wp_nonce_field( self::NONCE_ACTION, '_fp_seo_bulk_nonce' ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
								<input type="hidden" name="post_type" value="<?php echo esc_attr( $filters['post_type'] ); ?>" />
								<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />

								<div class="fp-seo-performance-bulk__toolbar">
										<button type="button" class="button button-primary" data-fp-seo-bulk-analyze>
											<?php esc_html_e( 'Analyze selected', 'fp-seo-performance' ); ?>
										</button>
										<button type="submit" class="button">
											<?php esc_html_e( 'Export CSV', 'fp-seo-performance' ); ?>
										</button>
								</div>

								<div class="fp-seo-performance-bulk__messages" role="status" aria-live="polite" hidden data-fp-seo-bulk-status></div>

								<table class="wp-list-table widefat fixed striped">
										<thead>
												<tr>
														<td class="manage-column column-cb check-column">
																<input type="checkbox" data-fp-seo-bulk-select-all />
														</td>
														<th scope="col"><?php esc_html_e( 'Title', 'fp-seo-performance' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Type', 'fp-seo-performance' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Status', 'fp-seo-performance' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Score', 'fp-seo-performance' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Warnings', 'fp-seo-performance' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Last analyzed', 'fp-seo-performance' ); ?></th>
												</tr>
										</thead>
										<tbody>
											<?php if ( empty( $posts ) ) : ?>
														<tr>
																<td colspan="7" class="fp-seo-performance-bulk__empty">
																		<?php esc_html_e( 'No content found for the selected filters.', 'fp-seo-performance' ); ?>
																</td>
														</tr>
												<?php else : ?>
													<?php foreach ( $posts as $post ) : ?>
														<?php
														$post_id     = (int) $post->ID;
														$row         = $results[ $post_id ] ?? array();
														$score       = isset( $row['score'] ) ? (int) $row['score'] : null;
														$warnings    = isset( $row['warnings'] ) ? (int) $row['warnings'] : null;
														$status      = isset( $row['status'] ) ? (string) $row['status'] : '';
														$updated     = isset( $row['updated'] ) ? (int) $row['updated'] : 0;
														$last_run    = $updated > 0 ? $this->format_timestamp( $updated ) : '—';
														$type_object = get_post_type_object( $post->post_type );
														?>
<tr data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-fp-seo-bulk-row tabindex="-1" aria-selected="false">
<th scope="row" class="check-column">
<input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (string) $post_id ); ?>" />
</th>
<td class="column-title">
<strong>
<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
														<?php echo esc_html( get_the_title( $post ) ); ?>
</a>
</strong>
</td>
<td>
														<?php echo esc_html( $type_object && isset( $type_object->labels->singular_name ) ? $type_object->labels->singular_name : $post->post_type ); ?>
</td>
<td>
														<?php echo esc_html( ucfirst( $post->post_status ) ); ?>
</td>
<td>
<span data-fp-seo-bulk-score><?php echo null === $score ? '—' : esc_html( (string) $score ); ?></span>
</td>
<td>
<span data-fp-seo-bulk-warnings><?php echo null === $warnings ? '—' : esc_html( number_format_i18n( $warnings ) ); ?></span>
</td>
<td>
<span data-fp-seo-bulk-updated><?php echo esc_html( $last_run ); ?></span>
</td>
</tr>
<?php endforeach; ?>
												<?php endif; ?>
										</tbody>
								</table>
						</form>
				</div>
				<?php
	}

		/**
		 * Handles AJAX batch analysis requests.
		 */
	public function handle_ajax_analyze(): void {
			check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Options::get_capability() ) ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'fp-seo-performance' ) ), 403 );
		}

		if ( ! $this->is_analyzer_enabled() ) {
				wp_send_json_error( array( 'message' => __( 'Analyzer is disabled in settings.', 'fp-seo-performance' ) ), 400 );
		}

			$ids = isset( $_POST['post_ids'] ) ? (array) wp_unslash( $_POST['post_ids'] ) : array();
			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
				wp_send_json_success( array( 'results' => array() ) );
		}

			$results = array();

		foreach ( $ids as $post_id ) {
				$result = $this->analyze_post_id( $post_id );

			if ( null === $result ) {
					continue;
			}

				$results[] = $result;
				$this->persist_result( $result );
		}

			wp_send_json_success( array( 'results' => $results ) );
	}

		/**
		 * Outputs a CSV export of the current table selection.
		 */
	public function handle_export(): void {
			check_admin_referer( self::NONCE_ACTION, '_fp_seo_bulk_nonce' );

		if ( ! current_user_can( Options::get_capability() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to export these results.', 'fp-seo-performance' ) );
		}

			$filters = $this->get_filters_from_request();
			$results = $this->get_cached_results();

				$selected = isset( $_POST['post_ids'] ) ? (array) wp_unslash( $_POST['post_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			$selected     = array_values( array_filter( array_map( 'absint', $selected ) ) );

		if ( empty( $selected ) ) {
				$posts    = $this->query_posts( $filters['post_type'], $filters['status'] );
				$selected = array_map(
					static function ( WP_Post $post ): int {
								return (int) $post->ID;
					},
					$posts
				);
		}

			$filename = 'fp-seo-bulk-' . gmdate( 'Y-m-d-H-i' ) . '.csv';

			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );

				$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

				fputcsv(
					$output,
					array(
						__( 'Post ID', 'fp-seo-performance' ),
						__( 'Title', 'fp-seo-performance' ),
						__( 'Type', 'fp-seo-performance' ),
						__( 'Status', 'fp-seo-performance' ),
						__( 'Score', 'fp-seo-performance' ),
						__( 'Warnings', 'fp-seo-performance' ),
						__( 'Last analyzed', 'fp-seo-performance' ),
					)
				);

		foreach ( $selected as $post_id ) {
				$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

				$row     = $results[ $post_id ] ?? array();
				$score   = isset( $row['score'] ) ? (int) $row['score'] : '';
				$warning = isset( $row['warnings'] ) ? (int) $row['warnings'] : '';
				$updated = isset( $row['updated'] ) ? (int) $row['updated'] : 0;

				fputcsv(
					$output,
					array(
						$post_id,
						html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
						$post->post_type,
						$post->post_status,
						'' === $score ? '' : $score,
						'' === $warning ? '' : $warning,
						$updated > 0 ? $this->format_timestamp( $updated ) : '',
					)
				);
		}

								fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
	}

		/**
		 * Retrieve allowed post type filters.
		 *
		 * @return string[]
		 */
	private function get_allowed_post_types(): array {
					return PostTypes::analyzable();
	}

		/**
		 * Retrieve allowed post statuses.
		 *
		 * @return string[]
		 */
	private function get_allowed_statuses(): array {
			return array( 'publish', 'draft', 'pending', 'future', 'private' );
	}

		/**
		 * Collects filter values from the current request.
		 *
		 * @return array{post_type:string,status:string}
		 */
	private function get_filters(): array {
			return $this->get_filters_from_request();
	}

		/**
		 * Extracts filter values from the current request variables.
		 *
		 * @return array{post_type:string,status:string}
		 */
	private function get_filters_from_request(): array {
				$type   = isset( $_REQUEST['post_type'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['post_type'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Filtering view only.
				$status = isset( $_REQUEST['status'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['status'] ) ) : 'any'; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Filtering view only.

		if ( 'all' !== $type && ! in_array( $type, $this->get_allowed_post_types(), true ) ) {
				$type = 'all';
		}

		if ( 'any' !== $status && ! in_array( $status, $this->get_allowed_statuses(), true ) ) {
				$status = 'any';
		}

			return array(
				'post_type' => $type,
				'status'    => $status,
			);
	}

		/**
		 * Query posts for the table.
		 *
		 * @param string $post_type Post type filter.
		 * @param string $status    Post status filter.
		 *
		 * @return array<int, WP_Post>
		 */
	private function query_posts( string $post_type, string $status ): array {
			$types = $this->get_allowed_post_types();

				$args = array(
					'post_type'                  => 'all' === $post_type ? $types : $post_type,
					'post_status'                => 'any' === $status ? $this->get_allowed_statuses() : $status,
					'posts_per_page'             => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Limit scoped to admin reporting view.
						'orderby'                => 'date',
						'order'                  => 'DESC',
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
				);

				$query = new WP_Query( $args );

				return $query->posts;
	}

		/**
		 * Retrieves cached results from the transient store.
		 *
		 * @return array<int, array<string, mixed>>
		 */
	private function get_cached_results(): array {
			$cached = get_transient( self::CACHE_KEY );

		if ( ! is_array( $cached ) ) {
				return array();
		}

			return array_filter(
				$cached,
				static function ( $item ): bool {
							return is_array( $item ) && isset( $item['post_id'] );
				}
			);
	}

		/**
		 * Persist a result payload in the transient cache.
		 *
		 * @param array<string, mixed> $result Result payload.
		 */
	private function persist_result( array $result ): void {
			$cached = $this->get_cached_results();

		if ( isset( $result['post_id'] ) ) {
				$cached[ (int) $result['post_id'] ] = $result;
		}

		if ( count( $cached ) > self::CACHE_LIMIT ) {
				uasort(
					$cached,
					static function ( array $a, array $b ): int {
								$a_updated = isset( $a['updated'] ) ? (int) $a['updated'] : 0;
								$b_updated = isset( $b['updated'] ) ? (int) $b['updated'] : 0;

								return $b_updated <=> $a_updated;
					}
				);

				$cached = array_slice( $cached, 0, self::CACHE_LIMIT, true );
		}

			set_transient( self::CACHE_KEY, $cached, $this->get_cache_duration() );
	}

		/**
		 * Calculate the cache duration.
		 */
	private function get_cache_duration(): int {
		if ( defined( 'DAY_IN_SECONDS' ) ) {
				return (int) DAY_IN_SECONDS;
		}

			return self::CACHE_TTL;
	}

		/**
		 * Analyze a post ID and build the result payload.
		 *
		 * @param int $post_id Post identifier.
		 *
		 * @return array<string, mixed>|null
		 */
	private function analyze_post_id( int $post_id ): ?array {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return null;
		}

		if ( ! $this->is_analyzer_enabled() ) {
				return null;
		}

			$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
				return null;
		}

			$context  = $this->build_context( $post );
			$analysis = ( new Analyzer() )->analyze( $context );
			$score    = ( new ScoreEngine() )->calculate( $analysis['checks'] ?? array() );
			$summary  = $analysis['summary'] ?? array();

			$warnings    = $this->count_warnings( $summary );
				$updated = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Requires site-local timestamp for cache freshness tracking.

			return array(
				'post_id'   => $post_id,
				'score'     => isset( $score['score'] ) ? (int) $score['score'] : 0,
				'status'    => isset( $score['status'] ) ? (string) $score['status'] : '',
				'warnings'  => $warnings,
				'updated'   => $updated,
				'updated_h' => $this->format_timestamp( $updated ),
			);
	}

		/**
		 * Build analyzer context from a post.
		 *
		 * @param WP_Post $post Post object.
		 */
	private function build_context( WP_Post $post ): Context {
		return new Context(
			(int) $post->ID,
			(string) $post->post_content,
			(string) $post->post_title,
			$this->resolve_meta_description( $post ),
			$this->resolve_canonical_url( $post ),
			$this->resolve_robots( $post )
		);
	}

		/**
		 * Count warning + failure statuses from summary data.
		 *
		 * @param array<string, int> $summary Summary payload from analyzer.
		 */
	private function count_warnings( array $summary ): int {
			$warn = isset( $summary[ Result::STATUS_WARN ] ) ? (int) $summary[ Result::STATUS_WARN ] : 0;
			$fail = isset( $summary[ Result::STATUS_FAIL ] ) ? (int) $summary[ Result::STATUS_FAIL ] : 0;

			return $warn + $fail;
	}

		/**
		 * Format a timestamp using site preferences.
		 *
		 * @param int $timestamp Timestamp to format.
		 */
	private function format_timestamp( int $timestamp ): string {
		$date_format = (string) get_option( 'date_format', 'Y-m-d' );
		$time_format = (string) get_option( 'time_format', 'H:i' );

		return wp_date( $date_format . ' ' . $time_format, $timestamp );
	}

		/**
		 * Resolve a meta description for a given post.
		 *
		 * @param WP_Post $post Post object.
		 */
	private function resolve_meta_description( WP_Post $post ): string {
		$meta = get_post_meta( (int) $post->ID, '_fp_seo_meta_description', true );

		if ( is_string( $meta ) && '' !== $meta ) {
				return $meta;
		}

		return wp_strip_all_tags( (string) $post->post_excerpt );
	}

		/**
		 * Resolve canonical URL metadata.
		 *
		 * @param WP_Post $post Post object.
		 */
	private function resolve_canonical_url( WP_Post $post ): ?string {
		$canonical = get_post_meta( (int) $post->ID, '_fp_seo_meta_canonical', true );

		if ( is_string( $canonical ) && '' !== $canonical ) {
				return $canonical;
		}

		return null;
	}

		/**
		 * Resolve robots directives metadata.
		 *
		 * @param WP_Post $post Post object.
		 */
	private function resolve_robots( WP_Post $post ): ?string {
		$robots = get_post_meta( (int) $post->ID, '_fp_seo_meta_robots', true );

		if ( is_string( $robots ) && '' !== $robots ) {
				return $robots;
		}

		return null;
	}

		/**
		 * Determine whether the analyzer is enabled in settings.
		 */
	private function is_analyzer_enabled(): bool {
			$options = Options::get();

			return ! empty( $options['general']['enable_analyzer'] );
	}
}
