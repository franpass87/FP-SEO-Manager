<?php
/**
 * Bulk Actions for AI-First Features
 *
 * Adds bulk actions to Bulk Auditor for batch processing AI features.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Scripts\BulkAiActionsScriptsManager;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\GEO\MultiModalOptimizer;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Manages bulk AI actions
 */
class BulkAiActions {

	/**
	 * @var BulkAiActionsScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks
	 */
	public function register(): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			$this->hook_manager->add_filter( 'fpseo_bulk_audit_actions', array( $this, 'add_bulk_actions' ) );
		} else {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_filter( 'fpseo_bulk_audit_actions', array( $this, 'add_bulk_actions' ) );
		}

		// Initialize scripts manager
		$this->scripts_manager = new BulkAiActionsScriptsManager();
	}

	/**
	 * Enqueue assets for bulk actions
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'fp-seo-performance_page_fp-seo-performance-bulk' !== $hook ) {
			return;
		}

		wp_localize_script(
			'fp-seo-performance-bulk',
			'fpSeoAiFirstBulk',
			array(
				'nonce'   => wp_create_nonce( 'fp_seo_ai_first_bulk' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);

		if ( $this->scripts_manager ) {
			wp_add_inline_script( 'fp-seo-performance-bulk', $this->scripts_manager->get_scripts() );
		}
	}

	/**
	 * Add AI-first bulk actions
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @return array<string, string> Modified actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['generate_qa']        = __( 'Generate Q&A Pairs', 'fp-seo-performance' );
		$actions['optimize_images']    = __( 'Optimize Images for AI', 'fp-seo-performance' );
		$actions['generate_variants']  = __( 'Generate Conversational Variants', 'fp-seo-performance' );
		$actions['generate_embeddings'] = __( 'Generate Embeddings', 'fp-seo-performance' );

		return $actions;
	}
}


