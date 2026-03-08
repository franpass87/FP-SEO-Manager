<?php
/**
 * Metabox controller.
 *
 * Orchestrates metabox functionality using extracted services.
 *
 * @package FP\SEO\Editor\Metabox\Controller
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Metabox\Controller;

use FP\SEO\Editor\Metabox\Contracts\HomepageProtectionServiceInterface;
use FP\SEO\Editor\Metabox\Contracts\FieldSaverServiceInterface;
use FP\SEO\Editor\Metabox\Contracts\AnalysisServiceInterface;
use FP\SEO\Editor\MetaboxRenderer;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\PostTypes;
use WP_Post;
use function current_user_can;
use function get_post_meta;

/**
 * Metabox controller.
 *
 * Orchestrates all metabox functionality.
 */
class MetaboxController {

	/**
	 * Homepage protection service.
	 *
	 * @var HomepageProtectionServiceInterface
	 */
	private HomepageProtectionServiceInterface $homepage_protection;

	/**
	 * Field saver service.
	 *
	 * @var FieldSaverServiceInterface
	 */
	private FieldSaverServiceInterface $field_saver;

	/**
	 * Analysis service.
	 *
	 * @var AnalysisServiceInterface
	 */
	private AnalysisServiceInterface $analysis_service;

	/**
	 * Metabox renderer.
	 *
	 * @var MetaboxRenderer
	 */
	private MetaboxRenderer $renderer;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Meta keys.
	 */
	public const META_EXCLUDE = '_fp_seo_performance_exclude';
	public const META_FOCUS_KEYWORD = '_fp_seo_focus_keyword';
	public const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Constructor.
	 *
	 * @param HomepageProtectionServiceInterface $homepage_protection Homepage protection service.
	 * @param FieldSaverServiceInterface         $field_saver         Field saver service.
	 * @param AnalysisServiceInterface           $analysis_service    Analysis service.
	 * @param MetaboxRenderer                    $renderer            Metabox renderer.
	 * @param LoggerInterface                    $logger              Logger instance.
	 * @param HookManagerInterface               $hook_manager        Hook manager instance.
	 */
	public function __construct(
		HomepageProtectionServiceInterface $homepage_protection,
		FieldSaverServiceInterface $field_saver,
		AnalysisServiceInterface $analysis_service,
		MetaboxRenderer $renderer,
		LoggerInterface $logger,
		HookManagerInterface $hook_manager
	) {
		$this->homepage_protection = $homepage_protection;
		$this->field_saver         = $field_saver;
		$this->analysis_service    = $analysis_service;
		$this->renderer            = $renderer;
		$this->logger              = $logger;
		$this->hook_manager        = $hook_manager;
	}

	/**
	 * Register metabox hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Register homepage protection hooks
		$this->homepage_protection->register_hooks();

		// Register metabox
		$this->hook_manager->add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );

		// Register save hook
		// CRITICAL: Use priority 20 instead of 10 to ensure we run AFTER WordPress core saves _thumbnail_id
		// WordPress core saves featured image (_thumbnail_id) during save_post with priority 10
		// By using priority 20, we ensure our hook runs after WordPress has finished saving the featured image
		$this->hook_manager->add_action( 'save_post', array( $this, 'save_fields' ), 20, 2 );
	}

	/**
	 * Add metabox to supported post types.
	 *
	 * @return void
	 */
	public function add_metabox(): void {
		$post_types = PostTypes::analyzable();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fp_seo_performance',
				__( 'SEO Performance', 'fp-seo-performance' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		// Correct homepage post if needed
		$post = $this->homepage_protection->correct_homepage_post( $post );

		// Run analysis
		$analysis = array();
		try {
			$analysis = $this->analysis_service->run( $post );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'MetaboxController - Error running analysis', array(
				'post_id' => $post->ID,
				'error'   => $e->getMessage(),
			) );
			$analysis = array();
		}

		// Check if post is excluded
		$excluded = $this->is_post_excluded( (int) $post->ID );

		// Render metabox
		$this->renderer->render( $post, $analysis, $excluded );
	}

	/**
	 * Save SEO fields for a post.
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @return void
	 */
	public function save_fields( int $post_id, WP_Post $post ): void {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			error_log( '[FP-SEO] MetaboxController::save_fields - Entry, post_id: ' . $post_id );
			error_log( '[FP-SEO] MetaboxController::save_fields - $_POST action: ' . ( $_POST['action'] ?? 'none' ) );
			error_log( '[FP-SEO] MetaboxController::save_fields - fp_seo_qa_pairs_data: ' . ( isset( $_POST['fp_seo_qa_pairs_data'] ) ? 'YES, length: ' . strlen( $_POST['fp_seo_qa_pairs_data'] ) : 'NO' ) );
			error_log( '[FP-SEO] MetaboxController::save_fields - _thumbnail_id in $_POST: ' . ( isset( $_POST['_thumbnail_id'] ) ? 'YES, value: ' . $_POST['_thumbnail_id'] : 'NO' ) );
		}

		if ( ! $post_id || $post_id <= 0 ) {
			return;
		}

		$has_seo_fields_in_post = isset( $_POST['fp_seo_performance_metabox_present'] ) ||
								  isset( $_POST['fp_seo_title_sent'] ) ||
								  isset( $_POST['fp_seo_meta_description_sent'] ) ||
								  isset( $_POST['fp_seo_title'] ) ||
								  isset( $_POST['fp_seo_meta_description'] ) ||
								  isset( $_POST['fp_seo_qa_pairs_data'] );

		if ( ! $has_seo_fields_in_post ) {
			return;
		}

		$this->homepage_protection->ensure_homepage_status( $post_id );
		$this->field_saver->save_all_fields( $post_id );
	}

	/**
	 * Run analysis for a post (for AJAX requests).
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Analysis result.
	 */
	public function run_analysis( WP_Post $post ): array {
		// Correct homepage post if needed
		$post = $this->homepage_protection->correct_homepage_post( $post );

		return $this->analysis_service->run( $post );
	}

	/**
	 * Save fields from POST data (for AJAX requests).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, bool> Saved fields data.
	 */
	public function save_fields_from_post( int $post_id ): array {
		// Ensure homepage status is correct
		$this->homepage_protection->ensure_homepage_status( $post_id );

		return $this->field_saver->save_from_post( $post_id );
	}

	/**
	 * Check if post is excluded from analysis.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if excluded, false otherwise.
	 */
	public function is_post_excluded( int $post_id ): bool {
		$excluded = get_post_meta( $post_id, self::META_EXCLUDE, true );
		return '1' === $excluded;
	}

	/**
	 * Get supported post types.
	 *
	 * @return array<int, string> Supported post types.
	 */
	public function get_supported_post_types(): array {
		return PostTypes::analyzable();
	}
}















