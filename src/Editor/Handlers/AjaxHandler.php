<?php
/**
 * Handles all AJAX requests for the SEO metabox.
 *
 * @package FP\SEO\Editor\Handlers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Handlers;

use FP\SEO\Editor\Metabox;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Handles AJAX requests for the metabox.
 *
 * This class now acts as an orchestrator, delegating to specialized handlers.
 */
class AjaxHandler {
	/**
	 * @var Metabox
	 */
	private $metabox;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Analyze handler.
	 *
	 * @var AnalyzeAjaxHandler|null
	 */
	private ?AnalyzeAjaxHandler $analyze_handler = null;

	/**
	 * Save fields handler.
	 *
	 * @var SaveFieldsAjaxHandler|null
	 */
	private ?SaveFieldsAjaxHandler $save_fields_handler = null;


	/**
	 * Constructor.
	 *
	 * @param Metabox              $metabox Metabox instance (for backward compatibility).
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( Metabox $metabox, HookManagerInterface $hook_manager ) {
		$this->metabox       = $metabox;
		$this->hook_manager  = $hook_manager;
	}

	/**
	 * Set analyze handler.
	 *
	 * @param AnalyzeAjaxHandler $handler Analyze handler.
	 * @return void
	 */
	public function set_analyze_handler( AnalyzeAjaxHandler $handler ): void {
		$this->analyze_handler = $handler;
	}

	/**
	 * Set save fields handler.
	 *
	 * @param SaveFieldsAjaxHandler $handler Save fields handler.
	 * @return void
	 */
	public function set_save_fields_handler( SaveFieldsAjaxHandler $handler ): void {
		$this->save_fields_handler = $handler;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		// Register specialized handlers if available
		if ( $this->analyze_handler ) {
			$this->analyze_handler->register();
		} else {
			// Fallback to old method for backward compatibility
			$this->hook_manager->add_action( 'wp_ajax_fp_seo_performance_analyze', array( $this, 'handle_analyze' ) );
		}

		if ( $this->save_fields_handler ) {
			$this->save_fields_handler->register();
		} else {
			// Fallback to old method for backward compatibility
			$this->hook_manager->add_action( 'wp_ajax_fp_seo_performance_save_fields', array( $this, 'handle_save_fields' ) );
		}

		// Register additional AJAX actions that may not have specialized handlers yet
		// (kept for backward compatibility)
	}

	/**
	 * Handle analyze AJAX request (backward compatibility wrapper).
	 *
	 * @return void
	 */
	public function handle_analyze(): void {
		// Delegate to metabox for backward compatibility
		if ( method_exists( $this->metabox, 'handle_ajax' ) ) {
			$this->metabox->handle_ajax();
		}
	}

	/**
	 * Handle save fields AJAX request (backward compatibility wrapper).
	 *
	 * @return void
	 */
	public function handle_save_fields(): void {
		// Delegate to metabox for backward compatibility
		if ( method_exists( $this->metabox, 'handle_save_fields_ajax' ) ) {
			$this->metabox->handle_save_fields_ajax();
		}
	}
}