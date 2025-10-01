<?php
/**
 * Editor metabox integration.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;
use WP_Post;
use function absint;
use function admin_url;
use function array_filter;
use function array_map;
use function check_ajax_referer;
use function current_user_can;
use function delete_post_meta;
use function get_current_screen;
use function get_post_meta;
use function in_array;
use function esc_url_raw;
use function is_array;
use function sanitize_text_field;
use function update_post_meta;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_kses_post;
use function wp_localize_script;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_strip_all_tags;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Provides the editor metabox with live analysis output.
 */
class Metabox {
	private const NONCE_ACTION = 'fp_seo_performance_meta';
	private const NONCE_FIELD  = 'fp_seo_performance_nonce';
	private const AJAX_ACTION  = 'fp_seo_performance_analyze';
        public const META_EXCLUDE  = '_fp_seo_performance_exclude';

	/**
	 * Hooks WordPress actions for registering and saving the metabox.
	 */
	public function register(): void {
                add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 0 );
                add_action( 'save_post', array( $this, 'save_meta' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 0 );
                add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Adds the metabox to supported post types.
	 */
	public function add_meta_box(): void {
		foreach ( $this->get_supported_post_types() as $post_type ) {
			add_meta_box(
				'fp-seo-performance-metabox',
				__( 'SEO Performance', 'fp-seo-performance' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 */
	public function enqueue_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
	}

	/**
	 * Renders the metabox content.
	 *
	 * @param WP_Post $post Current post instance.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			$analysis = $this->run_analysis_for_post( $post );
		}

		wp_localize_script(
			'fp-seo-performance-editor',
			'fpSeoPerformanceMetabox',
			array(
				'postId'   => (int) $post->ID,
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
				'enabled'  => $enabled,
				'excluded' => $excluded,
				'initial'  => $analysis,
				'labels'   => array(
					'score'      => __( 'SEO Score', 'fp-seo-performance' ),
					'indicators' => __( 'Key indicators', 'fp-seo-performance' ),
					'notes'      => __( 'Recommendations', 'fp-seo-performance' ),
					'none'       => __( 'All checks are passing.', 'fp-seo-performance' ),
					'disabled'   => __( 'Analyzer disabled in settings.', 'fp-seo-performance' ),
					'excluded'   => __( 'This content is excluded from SEO analysis.', 'fp-seo-performance' ),
					'loading'    => __( 'Analyzing content…', 'fp-seo-performance' ),
					'error'      => __( 'Unable to analyze content. Please try again.', 'fp-seo-performance' ),
				),
				'legend'   => array(
					Result::STATUS_PASS => __( 'Pass', 'fp-seo-performance' ),
					Result::STATUS_WARN => __( 'Warning', 'fp-seo-performance' ),
					Result::STATUS_FAIL => __( 'Fail', 'fp-seo-performance' ),
				),
			)
		);

		$score_value  = isset( $analysis['score']['score'] ) ? (int) $analysis['score']['score'] : 0;
		$score_status = isset( $analysis['score']['status'] ) ? (string) $analysis['score']['status'] : 'pending';
		$checks       = $analysis['checks'] ?? array();
		$recommend    = $analysis['score']['recommendations'] ?? array();
		?>
		<div class="fp-seo-performance-metabox" data-fp-seo-metabox>
			<div class="fp-seo-performance-metabox__controls">
				<label for="fp-seo-performance-exclude">
					<input type="checkbox" name="fp_seo_performance_exclude" id="fp-seo-performance-exclude" value="1" <?php checked( $excluded ); ?> data-fp-seo-exclude />
					<?php esc_html_e( 'Exclude this content from analysis', 'fp-seo-performance' ); ?>
				</label>
			</div>
			<div class="fp-seo-performance-metabox__message" role="status" aria-live="polite" data-fp-seo-message></div>
			<div class="fp-seo-performance-metabox__score" role="status" aria-live="polite" data-fp-seo-score data-status="<?php echo esc_attr( $score_status ); ?>">
				<strong class="fp-seo-performance-metabox__score-label"><?php esc_html_e( 'SEO Score', 'fp-seo-performance' ); ?></strong>
				<span class="fp-seo-performance-metabox__score-value" data-fp-seo-score-value><?php echo esc_html( (string) $score_value ); ?></span>
			</div>
			<div class="fp-seo-performance-metabox__indicators">
				<h4 class="fp-seo-performance-metabox__section-heading"><?php esc_html_e( 'Key indicators', 'fp-seo-performance' ); ?></h4>
				<ul class="fp-seo-performance-metabox__indicator-list" data-fp-seo-indicators>
					<?php foreach ( $checks as $check ) : ?>
						<li class="fp-seo-performance-indicator fp-seo-performance-indicator--<?php echo esc_attr( $check['status'] ?? 'pending' ); ?>">
							<span class="fp-seo-performance-indicator__label"><?php echo esc_html( $check['label'] ?? '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="fp-seo-performance-metabox__recommendations">
				<h4 class="fp-seo-performance-metabox__section-heading"><?php esc_html_e( 'Recommendations', 'fp-seo-performance' ); ?></h4>
				<ul class="fp-seo-performance-metabox__recommendation-list" data-fp-seo-recommendations>
					<?php foreach ( $recommend as $item ) : ?>
						<li><?php echo esc_html( (string) $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
</div>
		<?php
	}

	/**
	 * Handles persistence for metabox interactions.
	 *
	 * @param int $post_id Post identifier.
	 */
	public function save_meta( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$exclude = isset( $_POST['fp_seo_performance_exclude'] ) && '1' === sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_performance_exclude'] ) );

		if ( $exclude ) {
			update_post_meta( $post_id, self::META_EXCLUDE, '1' );
		} else {
			delete_post_meta( $post_id, self::META_EXCLUDE );
		}
	}

	/**
	 * Handle analyzer AJAX requests.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;

		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to edit this post.', 'fp-seo-performance' ) ), 403 );
		}

		$options = Options::get();

		if ( empty( $options['general']['enable_analyzer'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Analyzer disabled in settings.', 'fp-seo-performance' ) ), 400 );
		}

		if ( $post_id > 0 && $this->is_post_excluded( $post_id ) ) {
			wp_send_json_success( array( 'excluded' => true ) );
		}

		$content   = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( (string) $_POST['content'] ) ) : '';
		$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		$excerpt   = isset( $_POST['excerpt'] ) ? wp_kses_post( wp_unslash( (string) $_POST['excerpt'] ) ) : '';
		$meta      = isset( $_POST['metaDescription'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['metaDescription'] ) ) : '';
                $canonical = isset( $_POST['canonical'] ) ? esc_url_raw( wp_unslash( (string) $_POST['canonical'] ) ) : null;
		$robots    = isset( $_POST['robots'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['robots'] ) ) : null;

		if ( '' === $meta ) {
			$meta = wp_strip_all_tags( $excerpt );
		}

		if ( '' === $canonical ) {
			$canonical = null;
		}

		if ( '' === $robots ) {
			$robots = null;
		}

		$context = new Context(
			$post_id > 0 ? $post_id : null,
			$content,
			$title,
			$meta,
			$canonical,
			$robots
		);
		$result  = $this->compile_analysis_payload( $context );

		wp_send_json_success( $result );
	}

	/**
	 * Returns post types eligible for the metabox.
	 *
	 * @return string[]
	 */
	private function get_supported_post_types(): array {
                return PostTypes::analyzable();
        }
	/**
	 * Determine if a post is excluded from analysis.
	 *
	 * @param int $post_id Post identifier.
	 */
	private function is_post_excluded( int $post_id ): bool {
		$value = get_post_meta( $post_id, self::META_EXCLUDE, true );

		return '1' === $value;
	}

	/**
	 * Run the analyzer for a post object.
	 *
	 * @param WP_Post $post Current post instance.
	 *
	 * @return array<string, mixed>
	 */
	private function run_analysis_for_post( WP_Post $post ): array {
		$context = new Context(
			(int) $post->ID,
			(string) $post->post_content,
			(string) $post->post_title,
			$this->resolve_meta_description( $post ),
			$this->resolve_canonical_url( $post ),
			$this->resolve_robots( $post )
		);

		return $this->compile_analysis_payload( $context );
	}

	/**
	 * Compile analyzer output with scoring and recommendations.
	 *
	 * @param Context $context Analyzer context.
	 *
	 * @return array<string, mixed>
	 */
	private function compile_analysis_payload( Context $context ): array {
		$analyzer   = new Analyzer();
		$analysis   = $analyzer->analyze( $context );
		$score      = ( new ScoreEngine() )->calculate( $analysis['checks'] ?? array() );
		$checks     = $this->format_checks_for_frontend( $analysis['checks'] ?? array() );
		$summary    = $analysis['summary'] ?? array();
		$score_data = array(
			'score'           => $score['score'] ?? 0,
			'status'          => $score['status'] ?? 'pending',
			'recommendations' => array_filter( (array) ( $score['recommendations'] ?? array() ) ),
		);

		return array(
			'score'   => $score_data,
			'checks'  => $checks,
			'summary' => $summary,
		);
	}

	/**
	 * Normalize check output for front-end consumption.
	 *
	 * @param array<string, array<string, mixed>> $checks Analyzer checks keyed by id.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function format_checks_for_frontend( array $checks ): array {
		return array_values(
			array_map(
				static function ( array $check ): array {
					return array(
						'id'     => isset( $check['id'] ) ? (string) $check['id'] : '',
						'label'  => isset( $check['label'] ) ? (string) $check['label'] : '',
						'status' => isset( $check['status'] ) ? (string) $check['status'] : '',
						'hint'   => isset( $check['fix_hint'] ) ? (string) $check['fix_hint'] : '',
					);
				},
				$checks
			)
		);
	}

	/**
	 * Resolve a meta description for the given post.
	 *
	 * @param WP_Post $post Post instance.
	 */
	private function resolve_meta_description( WP_Post $post ): string {
		$meta = get_post_meta( (int) $post->ID, '_fp_seo_meta_description', true );

		if ( is_string( $meta ) && '' !== $meta ) {
			return $meta;
		}

		return wp_strip_all_tags( (string) $post->post_excerpt );
	}

	/**
	 * Resolve a canonical URL for the post when stored.
	 *
	 * @param WP_Post $post Post instance.
	 */
	private function resolve_canonical_url( WP_Post $post ): ?string {
		$canonical = get_post_meta( (int) $post->ID, '_fp_seo_meta_canonical', true );

		if ( is_string( $canonical ) && '' !== $canonical ) {
			return $canonical;
		}

		return null;
	}

	/**
	 * Resolve robots directives if stored.
	 *
	 * @param WP_Post $post Post instance.
	 */
	private function resolve_robots( WP_Post $post ): ?string {
		$robots = get_post_meta( (int) $post->ID, '_fp_seo_meta_robots', true );

		if ( is_string( $robots ) && '' !== $robots ) {
			return $robots;
		}

		return null;
	}
}
