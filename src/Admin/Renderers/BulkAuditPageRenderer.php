<?php
/**
 * Renders the Bulk Audit admin page.
 *
 * @package FP\SEO\Admin\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Renderers;

use FP\SEO\Utils\Options;
use WP_Post;
use function admin_url;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_edit_post_link;
use function get_post_type_object;
use function get_the_title;
use function number_format_i18n;
use function selected;
use function wp_die;
use function wp_nonce_field;

/**
 * Renders the Bulk Audit admin page.
 */
class BulkAuditPageRenderer {
	/**
	 * Render the bulk audit page.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @param array<WP_Post> $posts Posts to display.
	 * @param array<int, array<string, mixed>> $results Analysis results.
	 * @param array<string> $types Allowed post types.
	 * @param array<string> $statuses Allowed statuses.
	 * @param callable $format_timestamp Callback to format timestamp.
	 * @param bool $analyzer_enabled Whether analyzer is enabled.
	 * @param string $page_slug Page slug.
	 * @param string $nonce_action Nonce action.
	 * @param string $export_action Export action.
	 * @return void
	 */
	public function render(
		array $filters,
		array $posts,
		array $results,
		array $types,
		array $statuses,
		callable $format_timestamp,
		bool $analyzer_enabled,
		string $page_slug,
		string $nonce_action,
		string $export_action
	): void {
		if ( ! current_user_can( Options::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		?>
		<div class="wrap fp-seo-performance-bulk">
			<h1><?php esc_html_e( 'Bulk Auditor', 'fp-seo-performance' ); ?></h1>

			<?php if ( ! $analyzer_enabled ) : ?>
				<div class="notice notice-warning">
					<p><?php echo esc_html__( 'The analyzer is currently disabled. Enable it in the settings to run bulk audits.', 'fp-seo-performance' ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->render_filters( $filters, $types, $statuses, $page_slug ); ?>

			<?php $this->render_table( $filters, $posts, $results, $format_timestamp, $nonce_action, $export_action ); ?>
		</div>
		<?php
	}

	/**
	 * Render filters form.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @param array<string> $types Allowed post types.
	 * @param array<string> $statuses Allowed statuses.
	 * @param string $page_slug Page slug.
	 * @return void
	 */
	private function render_filters( array $filters, array $types, array $statuses, string $page_slug ): void {
		?>
		<form method="get" class="fp-seo-performance-bulk__filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>" />
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
		<?php
	}

	/**
	 * Render posts table.
	 *
	 * @param array<string, mixed> $filters Current filters.
	 * @param array<WP_Post> $posts Posts to display.
	 * @param array<int, array<string, mixed>> $results Analysis results.
	 * @param callable $format_timestamp Callback to format timestamp.
	 * @param string $nonce_action Nonce action.
	 * @param string $export_action Export action.
	 * @return void
	 */
	private function render_table(
		array $filters,
		array $posts,
		array $results,
		callable $format_timestamp,
		string $nonce_action,
		string $export_action
	): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-fp-seo-bulk-form>
			<?php wp_nonce_field( $nonce_action, '_fp_seo_bulk_nonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $export_action ); ?>" />
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
							<?php $this->render_table_row( $post, $results, $format_timestamp ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</form>
		<?php
	}

	/**
	 * Render a single table row.
	 *
	 * @param WP_Post $post Post object.
	 * @param array<int, array<string, mixed>> $results Analysis results.
	 * @param callable $format_timestamp Callback to format timestamp.
	 * @return void
	 */
	private function render_table_row( WP_Post $post, array $results, callable $format_timestamp ): void {
		$post_id     = (int) $post->ID;
		$row         = $results[ $post_id ] ?? array();
		$score       = isset( $row['score'] ) ? (int) $row['score'] : null;
		$warnings    = isset( $row['warnings'] ) ? (int) $row['warnings'] : null;
		$status      = isset( $row['status'] ) ? (string) $row['status'] : '';
		$updated     = isset( $row['updated'] ) ? (int) $row['updated'] : 0;
		$last_run    = $updated > 0 ? $format_timestamp( $updated ) : '—';
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
		<?php
	}
}

