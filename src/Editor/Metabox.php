<?php
/**
 * Editor metabox integration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Scoring\ScoreEngine;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\PostTypes;
use FP\SEO\Integrations\GscData;
use FP\SEO\Utils\Logger;
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
	public const META_EXCLUDE         = '_fp_seo_performance_exclude';
	public const META_FOCUS_KEYWORD   = '_fp_seo_focus_keyword';
	public const META_SECONDARY_KEYWORDS = '_fp_seo_secondary_keywords';

	/**
	 * Hooks WordPress actions for registering and saving the metabox.
	 */
	public function register(): void {
		// Priorità 5 per essere registrato tra i primi metabox (prima di altri plugin)
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 5, 0 );
		// Save meta with priority 10 (default) and 1 argument
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 10, 0 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'admin_head', array( $this, 'inject_modern_styles' ) );
	}

	/**
	 * Adds the metabox to supported post types.
	 * 
	 * ORDINE METABOX LOGICO:
	 * 1. SEO Performance (normal, high) - PRINCIPALE - deve essere tra i primi
	 * 2. Altri metabox del plugin (normal, default) - se presenti
	 * 3. Metabox secondari (side, default) - se presenti
	 */
	public function add_meta_box(): void {
		foreach ( $this->get_supported_post_types() as $post_type ) {
			// Remove native WordPress excerpt metabox to avoid duplication
			// (we have our own excerpt field in SEO Performance metabox with better UX)
			remove_meta_box( 'postexcerpt', $post_type, 'normal' );
			remove_meta_box( 'postexcerpt', $post_type, 'side' );
			// Remove native slug box to prevent duplicate slug editors
			remove_meta_box( 'slugdiv', $post_type, 'normal' );
			remove_meta_box( 'slugdiv', $post_type, 'advanced' );
			remove_meta_box( 'slugdiv', $post_type, 'side' );
			
			add_meta_box(
				'fp-seo-performance-metabox',
				__( 'SEO Performance', 'fp-seo-performance' ),
				array( $this, 'render' ),
				$post_type,
				'normal', // Posizione: colonna principale (normal = prima della sidebar)
				'high'    // Priorità: alta (appare tra i primi metabox)
			);
		}
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 */
	public function enqueue_assets(): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
		wp_enqueue_script( 'fp-seo-performance-serp-preview' );
		wp_enqueue_script( 'fp-seo-performance-ai-generator' );

		// Prepara i dati per il JavaScript PRIMA che il module si carichi
		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			$analysis = $this->run_analysis_for_post( $post );
		}

		// Localizza lo script per renderlo disponibile al module
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
					'indicators' => __( 'Analisi SEO', 'fp-seo-performance' ),
					'notes'      => __( 'Raccomandazioni', 'fp-seo-performance' ),
					'none'       => __( 'Tutti gli indicatori sono ottimali.', 'fp-seo-performance' ),
					'disabled'   => __( 'Analizzatore disabilitato nelle impostazioni.', 'fp-seo-performance' ),
					'excluded'   => __( 'This content is excluded from SEO analysis.', 'fp-seo-performance' ),
					'loading'    => __( 'Analyzing content…', 'fp-seo-performance' ),
					'error'      => __( 'Unable to analyze content. Please try again.', 'fp-seo-performance' ),
				),
				'legend'   => array(
					Result::STATUS_PASS => __( 'Ottimo', 'fp-seo-performance' ),
					Result::STATUS_WARN => __( 'Attenzione', 'fp-seo-performance' ),
					Result::STATUS_FAIL => __( 'Critico', 'fp-seo-performance' ),
				),
			)
		);
	}

	/**
	 * Inject modern styles in admin head
	 */
	public function inject_modern_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		
		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}
		
		?>
		<script>
		// Clean up any text content from indicator icons (cache fix)
		document.addEventListener('DOMContentLoaded', function() {
			const icons = document.querySelectorAll('.fp-seo-performance-indicator__icon');
			icons.forEach(function(icon) {
				icon.textContent = '';
			});

			// Help Banner - Close functionality
			const helpBanner = document.querySelector('.fp-seo-metabox-help-banner');
			const closeButton = document.querySelector('.fp-seo-metabox-help-banner__close');
			
			if (helpBanner && closeButton) {
				// Check if banner was previously closed
				const bannerClosed = localStorage.getItem('fp_seo_help_banner_closed');
				if (bannerClosed === 'true') {
					helpBanner.classList.add('hidden');
				}

				closeButton.addEventListener('click', function(e) {
					e.preventDefault();
					helpBanner.style.animation = 'slideUp 0.3s ease';
					setTimeout(function() {
						helpBanner.classList.add('hidden');
						// Remember user preference
						localStorage.setItem('fp_seo_help_banner_closed', 'true');
					}, 300);
				});
			}

			// Help Toggle - Expand/Collapse check help
			const helpToggles = document.querySelectorAll('[data-help-toggle]');
			helpToggles.forEach(function(toggle) {
				toggle.addEventListener('click', function(e) {
					e.preventDefault();
					const checkItem = toggle.closest('.fp-seo-performance-analysis-item');
					const helpContent = checkItem.querySelector('[data-help-content]');
					
					if (helpContent) {
						const isVisible = helpContent.style.display !== 'none';
						if (isVisible) {
							helpContent.style.animation = 'collapseUp 0.3s ease';
							setTimeout(function() {
								helpContent.style.display = 'none';
							}, 300);
							toggle.setAttribute('title', '<?php esc_attr_e( 'Mostra aiuto', 'fp-seo-performance' ); ?>');
						} else {
							helpContent.style.display = 'block';
							helpContent.style.animation = 'expandDown 0.3s ease';
							toggle.setAttribute('title', '<?php esc_attr_e( 'Nascondi aiuto', 'fp-seo-performance' ); ?>');
						}
					}
				});
			});

			// Tooltip functionality (simple title attribute for now)
			const tooltipTriggers = document.querySelectorAll('.fp-seo-tooltip-trigger');
			tooltipTriggers.forEach(function(trigger) {
				const tooltipText = trigger.getAttribute('data-tooltip');
				if (tooltipText) {
					trigger.setAttribute('title', tooltipText);
				}
			});
		});

		// Add collapseUp animation
		const style = document.createElement('style');
		style.textContent = `
			@keyframes collapseUp {
				from {
					opacity: 1;
					max-height: 500px;
				}
				to {
					opacity: 0;
					max-height: 0;
					padding-top: 0;
					padding-bottom: 0;
				}
			}
			@keyframes slideUp {
				from {
					opacity: 1;
					transform: translateY(0);
				}
				to {
					opacity: 0;
					transform: translateY(-10px);
				}
			}
		`;
		document.head.appendChild(style);

		// Character counters for SEO Title and Meta Description
		document.addEventListener('DOMContentLoaded', function() {
			// SEO Title counter
			const seoTitleField = document.getElementById('fp-seo-title');
			const seoTitleCounter = document.getElementById('fp-seo-title-counter');
			
			if (seoTitleField && seoTitleCounter) {
				function updateTitleCounter() {
					const length = seoTitleField.value.length;
					seoTitleCounter.textContent = length + '/60';
					
					// Color coding: green (50-60), orange (60-70), red (>70)
					if (length >= 50 && length <= 60) {
						seoTitleCounter.style.color = '#10b981'; // Green
					} else if (length > 60 && length <= 70) {
						seoTitleCounter.style.color = '#f59e0b'; // Orange
					} else if (length > 70) {
						seoTitleCounter.style.color = '#ef4444'; // Red
					} else {
						seoTitleCounter.style.color = '#6b7280'; // Gray
					}
				}
				
				// Initialize counter
				updateTitleCounter();
				
				// Update on input
				seoTitleField.addEventListener('input', updateTitleCounter);
			}
			
			// Meta Description counter
			const metaDescField = document.getElementById('fp-seo-meta-description');
			const metaDescCounter = document.getElementById('fp-seo-meta-description-counter');
			
			if (metaDescField && metaDescCounter) {
				function updateDescCounter() {
					const length = metaDescField.value.length;
					metaDescCounter.textContent = length + '/160';
					
					// Color coding: green (150-160), orange (160-180), red (>180)
					if (length >= 150 && length <= 160) {
						metaDescCounter.style.color = '#10b981'; // Green
					} else if (length > 160 && length <= 180) {
						metaDescCounter.style.color = '#f59e0b'; // Orange
					} else if (length > 180) {
						metaDescCounter.style.color = '#ef4444'; // Red
					} else {
						metaDescCounter.style.color = '#6b7280'; // Gray
					}
				}
				
			// Initialize counter
			updateDescCounter();
			
			// Update on input
			metaDescField.addEventListener('input', updateDescCounter);
		}
		
		// Slug counter (word count)
		const slugField = document.getElementById('fp-seo-slug');
		const slugCounter = document.getElementById('fp-seo-slug-counter');
		
		if (slugField && slugCounter) {
			function updateSlugCounter() {
				const text = slugField.value.trim();
				const words = text ? text.split('-').filter(w => w.length > 0).length : 0;
				slugCounter.textContent = words + ' parole';
				
				// Color coding: green (3-5 words), orange (6-8), red (>8)
				if (words >= 3 && words <= 5) {
					slugCounter.style.color = '#10b981'; // Green
				} else if (words > 5 && words <= 8) {
					slugCounter.style.color = '#f59e0b'; // Orange
				} else if (words > 8) {
					slugCounter.style.color = '#ef4444'; // Red
				} else {
					slugCounter.style.color = '#6b7280'; // Gray
				}
			}
			
			// Initialize counter
			updateSlugCounter();
			
			// Update on input
			slugField.addEventListener('input', updateSlugCounter);
		}
		
		// Excerpt counter
		const excerptField = document.getElementById('fp-seo-excerpt');
		const excerptCounter = document.getElementById('fp-seo-excerpt-counter');
		
		if (excerptField && excerptCounter) {
			function updateExcerptCounter() {
				const length = excerptField.value.length;
				excerptCounter.textContent = length + '/150';
				
				// Color coding: green (100-150), orange (150-200), red (>200)
				if (length >= 100 && length <= 150) {
					excerptCounter.style.color = '#10b981'; // Green
				} else if (length > 150 && length <= 200) {
					excerptCounter.style.color = '#f59e0b'; // Orange
				} else if (length > 200) {
					excerptCounter.style.color = '#ef4444'; // Red
				} else {
					excerptCounter.style.color = '#6b7280'; // Gray
				}
			}
			
			// Initialize counter
			updateExcerptCounter();
			
			// Update on input
			excerptField.addEventListener('input', updateExcerptCounter);
		}
	});
	</script>
		<?php
		?>
		<style id="fp-seo-metabox-modern-ui">
		/* CSS Variables now unified in fp-seo-ui-system.css - No redefinition needed */
		
		/* Screen Reader Only Text for Accessibility */
		.screen-reader-text {
			border: 0;
			clip: rect(1px, 1px, 1px, 1px);
			clip-path: inset(50%);
			height: 1px;
			margin: -1px;
			overflow: hidden;
			padding: 0;
			position: absolute;
			width: 1px;
			word-wrap: normal !important;
		}
		
		/* Hide native WordPress slug UI to avoid duplication with FP SEO slug field */
		#slugdiv,
		#slugdiv .inside,
		#edit-slug-box,
		#editable-post-name,
		#editable-post-name-full,
		#post-name,
		#permalink,
		.edit-slug,
		.edit-post-post-link,
		.components-panel__body[data-editor-panel-id="post-link"],
		.components-panel__body[data-panel-id="post-link"],
		.editor-post-url,
		.editor-post-url .components-panel__body,
		.editor-post-permalink,
		.editor-document-permalink-panel {
			display: none !important;
		}
		
		#fp-seo-performance-metabox.postbox,
		#fp-seo-geo-metabox.postbox {
			border: 1px solid #e5e7eb !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
			border-radius: 8px !important;
		}
		
		#fp-seo-performance-metabox .postbox-header {
			background: linear-gradient(135deg, var(--fp-seo-primary) 0%, var(--fp-seo-primary-dark) 100%) !important;
			border-bottom: none !important;
		}
		
		#fp-seo-performance-metabox .postbox-header h2 {
			color: #fff !important;
			font-weight: 600 !important;
		}
		
		#fp-seo-performance-metabox .postbox-header .handle-actions button {
			filter: brightness(0) invert(1) !important;
		}
		
		.fp-seo-performance-metabox__score {
			display: flex !important;
			align-items: center !important;
			justify-content: space-between !important;
			gap: 16px !important;
			border-radius: 8px !important;
			padding: 24px !important;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
			border: none !important;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;
			position: relative !important;
			overflow: hidden !important;
			margin-bottom: 16px !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"green\"] {
			background: linear-gradient(135deg, var(--fp-seo-success) 0%, var(--fp-seo-success-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"yellow\"] {
			background: linear-gradient(135deg, var(--fp-seo-warning) 0%, var(--fp-seo-warning-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score[data-status=\"red\"] {
			background: linear-gradient(135deg, var(--fp-seo-danger) 0%, var(--fp-seo-danger-dark) 100%) !important;
		}
		
		.fp-seo-performance-metabox__score-label {
			font-size: 14px !important;
			font-weight: 600 !important;
			color: rgba(255,255,255,0.9) !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
		}
		
		.fp-seo-performance-metabox__score-value {
			font-size: 48px !important;
			font-weight: 700 !important;
			color: #fff !important;
			line-height: 1 !important;
			text-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
		}
		
		.fp-seo-performance-metabox__indicator-list {
			display: grid !important;
			grid-template-columns: repeat(2, 1fr) !important;
			gap: 8px !important;
			margin: 0 !important;
			padding: 0 !important;
			list-style: none !important;
		}
		
		.fp-seo-performance-indicator {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			padding: 10px 12px !important;
			border-radius: 8px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-indicator::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 3px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-indicator:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 4px 0 rgba(0,0,0,0.08) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-indicator--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-indicator--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-indicator--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-indicator__label {
			font-size: 12px !important;
			font-weight: 500 !important;
			color: #374151 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-indicator__icon {
			width: 8px !important;
			height: 8px !important;
			border-radius: 50% !important;
			flex-shrink: 0 !important;
			margin-left: 4px !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-indicator--fail .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-danger) !important;
			box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--warn .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-warning) !important;
			box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2) !important;
		}
		
		.fp-seo-performance-indicator--pass .fp-seo-performance-indicator__icon {
			background: var(--fp-seo-success) !important;
			box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2) !important;
		}
		
		/* Loading state */
		.fp-seo-performance-indicator--pending .fp-seo-performance-indicator__icon {
			background: #d1d5db !important;
			animation: fp-seo-pulse 1.5s ease-in-out infinite !important;
		}
		
		@keyframes fp-seo-pulse {
			0%, 100% {
				opacity: 0.4 !important;
				transform: scale(1) !important;
			}
			50% {
				opacity: 1 !important;
				transform: scale(1.2) !important;
			}
		}
		
		/* Tooltip */
		.fp-seo-performance-indicator {
			position: relative !important;
		}
		
		.fp-seo-performance-indicator__tooltip {
			position: absolute !important;
			bottom: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) translateY(-8px) !important;
			padding: 8px 12px !important;
			background: #1f2937 !important;
			color: #fff !important;
			font-size: 12px !important;
			line-height: 1.4 !important;
			border-radius: 8px !important;
			white-space: nowrap !important;
			max-width: 250px !important;
			white-space: normal !important;
			pointer-events: none !important;
			opacity: 0 !important;
			visibility: hidden !important;
			transition: all 0.2s ease !important;
			z-index: 1000 !important;
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3) !important;
		}
		
		.fp-seo-performance-indicator__tooltip::after {
			content: '' !important;
			position: absolute !important;
			top: 100% !important;
			left: 50% !important;
			transform: translateX(-50%) !important;
			border: 5px solid transparent !important;
			border-top-color: #1f2937 !important;
		}
		
		.fp-seo-performance-indicator:hover .fp-seo-performance-indicator__tooltip {
			opacity: 1 !important;
			visibility: visible !important;
			transform: translateX(-50%) translateY(-4px) !important;
		}
		
		/* Summary badges */
		.fp-seo-performance-summary {
			display: flex !important;
			gap: 8px !important;
			margin-bottom: 12px !important;
			padding: 12px !important;
			background: #f9fafb !important;
			border-radius: 8px !important;
			border: 1px solid #e5e7eb !important;
		}
		
		.fp-seo-performance-summary__badge {
			display: inline-flex !important;
			align-items: center !important;
			gap: 6px !important;
			padding: 6px 10px !important;
			border-radius: 8px !important;
			font-size: 12px !important;
			font-weight: 600 !important;
		}
		
		.fp-seo-performance-summary__badge--fail {
			background: #fef2f2 !important;
			color: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-summary__badge--warn {
			background: #fffbeb !important;
			color: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-summary__badge--pass {
			background: #f0fdf4 !important;
			color: var(--fp-seo-success) !important;
		}
		
		/* Responsive: 1 colonna su schermi piccoli */
		@media (max-width: 782px) {
			.fp-seo-performance-metabox__indicator-list {
				grid-template-columns: 1fr !important;
			}
		}

		/* Help Banner */
		.fp-seo-metabox-help-banner {
			background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
			border-left: 4px solid #3b82f6;
			padding: 16px 20px;
			margin-bottom: 20px;
			border-radius: 8px;
			display: flex;
			gap: 16px;
			align-items: flex-start;
			position: relative;
			animation: slideDown 0.4s ease;
		}

		@keyframes slideDown {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.fp-seo-metabox-help-banner__icon {
			font-size: 24px;
			line-height: 1;
			flex-shrink: 0;
		}

		.fp-seo-metabox-help-banner__content {
			flex: 1;
		}

		.fp-seo-metabox-help-banner__title {
			margin: 0 0 8px;
			font-size: 14px;
			font-weight: 600;
			color: #1e40af;
		}

		.fp-seo-metabox-help-banner__text {
			margin: 0 0 12px;
			font-size: 13px;
			color: #1e3a8a;
			line-height: 1.5;
		}

		.fp-seo-metabox-help-banner__legend {
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
		}

		.fp-seo-legend-item {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 12px;
			color: #1e3a8a;
			font-weight: 500;
		}

		.fp-seo-legend-dot {
			width: 8px;
			height: 8px;
			border-radius: 50%;
			flex-shrink: 0;
		}

		.fp-seo-legend-item--pass .fp-seo-legend-dot {
			background: var(--fp-seo-success);
		}

		.fp-seo-legend-item--warn .fp-seo-legend-dot {
			background: var(--fp-seo-warning);
		}

		.fp-seo-legend-item--fail .fp-seo-legend-dot {
			background: var(--fp-seo-danger);
		}

		.fp-seo-metabox-help-banner__close {
			position: absolute;
			top: 8px;
			right: 8px;
			background: rgba(255, 255, 255, 0.7);
			border: none;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			font-size: 18px;
			line-height: 1;
			color: #1e40af;
			transition: all 0.2s;
		}

		.fp-seo-metabox-help-banner__close:hover {
			background: rgba(255, 255, 255, 1);
			transform: scale(1.1);
		}

		.fp-seo-metabox-help-banner.hidden {
			display: none;
		}

		/* Tooltip */
		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 6px;
			cursor: help;
			opacity: 0.7;
			font-size: 14px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}

		/* Help Toggle Button */
		.fp-seo-help-toggle {
			background: transparent;
			border: 1px solid #e5e7eb;
			border-radius: 4px;
			width: 24px;
			height: 24px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			color: #6b7280;
			transition: all 0.2s;
			flex-shrink: 0;
			padding: 0;
		}

		.fp-seo-help-toggle:hover {
			background: #f3f4f6;
			border-color: #3b82f6;
			color: #3b82f6;
		}

		.fp-seo-help-toggle .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}

		/* Check Help Content */
		.fp-seo-check-help {
			background: #f0f9ff;
			border: 1px solid #bfdbfe;
			border-radius: 6px;
			padding: 16px;
			margin-top: 12px;
			animation: expandDown 0.3s ease;
		}

		@keyframes expandDown {
			from {
				opacity: 0;
				max-height: 0;
				padding-top: 0;
				padding-bottom: 0;
			}
			to {
				opacity: 1;
				max-height: 500px;
				padding-top: 16px;
				padding-bottom: 16px;
			}
		}

		.fp-seo-check-help__section {
			margin-bottom: 16px;
		}

		.fp-seo-check-help__section:last-child {
			margin-bottom: 0;
		}

		.fp-seo-check-help__title {
			margin: 0 0 8px;
			font-size: 13px;
			font-weight: 600;
			color: #1e40af;
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.fp-seo-check-help__title .dashicons {
			width: 16px;
			height: 16px;
			font-size: 16px;
		}

		.fp-seo-check-help__text {
			margin: 0;
			font-size: 12px;
			color: #1e3a8a;
			line-height: 1.6;
		}

		.fp-seo-check-help__example {
			background: #fff;
			border: 1px solid #bfdbfe;
			border-radius: 4px;
			padding: 12px;
			margin-top: 12px;
		}

		.fp-seo-check-help__example strong {
			display: block;
			margin-bottom: 6px;
			font-size: 12px;
			color: #1e40af;
		}

		.fp-seo-check-help__example code {
			display: block;
			background: #f8fafc;
			padding: 8px;
			border-radius: 4px;
			font-size: 11px;
			color: #1e3a8a;
			font-family: 'Courier New', monospace;
			word-wrap: break-word;
		}
		
		.fp-seo-performance-metabox__recommendations {
			margin-top: 16px !important;
		}
		
		.fp-seo-performance-recommendations-header {
			display: flex !important;
			align-items: center !important;
			gap: 8px !important;
			margin-bottom: 10px !important;
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #374151 !important;
		}
		
		.fp-seo-performance-recommendations-header__badge {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			min-width: 20px !important;
			height: 20px !important;
			padding: 0 6px !important;
			background: var(--fp-seo-primary) !important;
			color: #fff !important;
			font-size: 11px !important;
			font-weight: 600 !important;
			border-radius: 12px !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li {
			font-size: 12px !important;
			line-height: 1.5 !important;
			padding: 8px 12px !important;
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 4px !important;
			border-left: 3px solid #2563eb !important;
			color: #374151 !important;
			margin-bottom: 6px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list li:last-child {
			margin-bottom: 0 !important;
		}
		
		.fp-seo-performance-metabox__recommendation-list--empty {
			padding: 12px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 13px !important;
			font-weight: 500 !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			margin: 16px 0 12px !important;
			font-size: 15px !important;
			font-weight: 600 !important;
			color: #111827 !important;
		}
		
		/* Unified Analysis Styles */
		.fp-seo-performance-metabox__unified-analysis {
			margin-bottom: 20px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list {
			list-style: none !important;
			padding: 0 !important;
			margin: 0 !important;
			display: flex !important;
			flex-direction: column !important;
			gap: 8px !important;
		}
		
		.fp-seo-performance-analysis-item {
			background: #fff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			padding: 12px 16px !important;
			transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
			position: relative !important;
			overflow: hidden !important;
		}
		
		.fp-seo-performance-analysis-item::before {
			content: '' !important;
			position: absolute !important;
			left: 0 !important;
			top: 0 !important;
			bottom: 0 !important;
			width: 4px !important;
			background: #e5e7eb !important;
		}
		
		.fp-seo-performance-analysis-item:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 8px 0 rgba(0,0,0,0.1) !important;
			transform: translateY(-1px) !important;
		}
		
		.fp-seo-performance-analysis-item--pass::before {
			background: var(--fp-seo-success) !important;
		}
		
		.fp-seo-performance-analysis-item--warn::before {
			background: var(--fp-seo-warning) !important;
		}
		
		.fp-seo-performance-analysis-item--fail::before {
			background: var(--fp-seo-danger) !important;
		}
		
		.fp-seo-performance-analysis-item__header {
			display: flex !important;
			align-items: center !important;
			gap: 12px !important;
			margin-bottom: 4px !important;
		}
		
		.fp-seo-performance-analysis-item__icon {
			font-size: 16px !important;
			line-height: 1 !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item__title {
			font-size: 13px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			flex: 1 !important;
			line-height: 1.3 !important;
		}
		
		.fp-seo-performance-analysis-item__status {
			font-size: 11px !important;
			font-weight: 500 !important;
			padding: 2px 8px !important;
			border-radius: 12px !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
			flex-shrink: 0 !important;
		}
		
		.fp-seo-performance-analysis-item--pass .fp-seo-performance-analysis-item__status {
			background: #d1fae5 !important;
			color: #065f46 !important;
		}
		
		.fp-seo-performance-analysis-item--warn .fp-seo-performance-analysis-item__status {
			background: #fef3c7 !important;
			color: #92400e !important;
		}
		
		.fp-seo-performance-analysis-item--fail .fp-seo-performance-analysis-item__status {
			background: #fee2e2 !important;
			color: #991b1b !important;
		}
		
		.fp-seo-performance-analysis-item__description {
			font-size: 12px !important;
			color: #6b7280 !important;
			line-height: 1.5 !important;
			margin-left: 28px !important;
			margin-top: 4px !important;
		}
		
		.fp-seo-performance-metabox__analysis-list--empty {
			padding: 20px !important;
			text-align: center !important;
			color: #059669 !important;
			background: #f0fdf4 !important;
			border: 1px solid #bbf7d0 !important;
			border-radius: 8px !important;
			font-size: 14px !important;
			font-weight: 500 !important;
		}
		
		/* Unified Section Styles */
		.fp-seo-performance-metabox__section {
			margin-bottom: 24px !important;
			padding: 20px !important;
			background: #ffffff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important;
			transition: all 0.3s ease !important;
		}
		
		.fp-seo-performance-metabox__section:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px 0 rgba(0,0,0,0.08) !important;
		}
		
		.fp-seo-performance-metabox__section-heading {
			display: flex !important;
			align-items: center !important;
			gap: 10px !important;
			margin: 0 0 16px 0 !important;
			padding: 0 0 12px 0 !important;
			font-size: 16px !important;
			font-weight: 600 !important;
			color: #111827 !important;
			border-bottom: 2px solid #e5e7eb !important;
		}
		
		.fp-seo-section-icon {
			font-size: 20px !important;
			line-height: 1 !important;
		}
		
		.fp-seo-performance-metabox__section-content {
			/* Reset any inherited styles */
		}
		
		/* Keywords Section Uniform Style */
		.fp-seo-performance-metabox__keywords {
			margin-bottom: 24px !important;
			padding: 20px !important;
			background: #ffffff !important;
			border: 1px solid #e5e7eb !important;
			border-radius: 8px !important;
			box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05) !important;
		}
		
		.fp-seo-performance-metabox__keywords:hover {
			border-color: #d1d5db !important;
			box-shadow: 0 2px 6px 0 rgba(0,0,0,0.08) !important;
		}
		</style>
		<?php
	}

	/**
	 * Renders the metabox content.
	 *
	 * @param WP_Post $post Current post instance.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		// I dati per JS sono già stati preparati in enqueue_assets()
		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			$analysis = $this->run_analysis_for_post( $post );
		}

		$score_value  = isset( $analysis['score']['score'] ) ? (int) $analysis['score']['score'] : 0;
		$score_status = isset( $analysis['score']['status'] ) ? (string) $analysis['score']['status'] : 'pending';
		$checks       = $analysis['checks'] ?? array();
		$recommend    = $analysis['score']['recommendations'] ?? array();
		?>
		<div class="fp-seo-performance-metabox" data-fp-seo-metabox>
			<!-- Banner informativo -->
			<div class="fp-seo-metabox-help-banner">
				<div class="fp-seo-metabox-help-banner__icon">ℹ️</div>
				<div class="fp-seo-metabox-help-banner__content">
					<h4 class="fp-seo-metabox-help-banner__title">
						<?php esc_html_e( 'Come funziona l\'analisi SEO?', 'fp-seo-performance' ); ?>
					</h4>
					<p class="fp-seo-metabox-help-banner__text">
						<?php esc_html_e( 'Questo tool analizza in tempo reale il tuo contenuto e ti assegna un punteggio SEO da 0 a 100. Ogni modifica che fai (titolo, contenuto, ecc.) viene automaticamente analizzata dopo 500ms.', 'fp-seo-performance' ); ?>
					</p>
					<div class="fp-seo-metabox-help-banner__legend">
						<span class="fp-seo-legend-item fp-seo-legend-item--pass">
							<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Ottimo (tutto ok)', 'fp-seo-performance' ); ?>
						</span>
						<span class="fp-seo-legend-item fp-seo-legend-item--warn">
							<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Attenzione (da migliorare)', 'fp-seo-performance' ); ?>
						</span>
						<span class="fp-seo-legend-item fp-seo-legend-item--fail">
							<span class="fp-seo-legend-dot"></span> <?php esc_html_e( 'Critico (richiede azione)', 'fp-seo-performance' ); ?>
						</span>
					</div>
				</div>
				<button type="button" class="fp-seo-metabox-help-banner__close" title="<?php esc_attr_e( 'Chiudi', 'fp-seo-performance' ); ?>">×</button>
			</div>

			<div class="fp-seo-performance-metabox__controls">
				<label for="fp-seo-performance-exclude">
					<input type="checkbox" name="fp_seo_performance_exclude" id="fp-seo-performance-exclude" value="1" <?php checked( $excluded ); ?> data-fp-seo-exclude />
					<?php esc_html_e( 'Exclude this content from analysis', 'fp-seo-performance' ); ?>
					<span class="fp-seo-tooltip-trigger" data-tooltip="<?php esc_attr_e( 'Attiva questa opzione per escludere completamente questo contenuto dall\'analisi SEO. Utile per pagine di servizio, ringraziamenti, ecc.', 'fp-seo-performance' ); ?>">ℹ️</span>
				</label>
			</div>
			<div class="fp-seo-performance-metabox__message" role="status" aria-live="polite" data-fp-seo-message></div>
			<div class="fp-seo-performance-metabox__score" role="status" aria-live="polite" aria-atomic="true" data-fp-seo-score data-status="<?php echo esc_attr( $score_status ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Punteggio SEO corrente: %d su 100', 'fp-seo-performance' ), $score_value ) ); ?>">
				<strong class="fp-seo-performance-metabox__score-label"><?php esc_html_e( 'SEO Score', 'fp-seo-performance' ); ?></strong>
				<span class="fp-seo-performance-metabox__score-value" data-fp-seo-score-value><?php echo esc_html( (string) $score_value ); ?></span>
			</div>
		<!-- 📊 SEO OPTIMIZATION FIELDS - Organized by Impact -->
		
		<!-- Section 1: SERP OPTIMIZATION (Very High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #10b981;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🎯</span>
					<?php esc_html_e( 'SERP Optimization', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impact: +40%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
		<div class="fp-seo-performance-metabox__section-content">
		<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #f0fdf4; border-radius: 6px; border-left: 3px solid #10b981;">
			<strong style="color: #059669;">💡 Questi campi appaiono direttamente su Google e influenzano la SERP</strong><br>
			Ottimizzali per massimizzare visibilità e click-through rate. Totale impatto sezione: <strong>+40% score</strong> (Title +15%, Description +10%, Excerpt +9%, Slug +6%).
		</p>
		
		<!-- CAMPI PRINCIPALI SEMPRE VISIBILI -->
		<div style="display: grid; gap: 16px; margin-bottom: 20px;">
			<!-- SEO Title -->
					<div style="position: relative;">
						<label for="fp-seo-title" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
							<span style="display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 16px;">📝</span>
								<?php esc_html_e( 'SEO Title', 'fp-seo-performance' ); ?>
								<span style="display: inline-flex; padding: 2px 8px; background: #10b981; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+15%</span>
							</span>
							<span id="fp-seo-title-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/60</span>
						</label>
						<div style="display: flex; gap: 8px; align-items: stretch;">
							<input 
								type="text" 
								id="fp-seo-title" 
								name="fp_seo_title"
								value="<?php echo esc_attr( get_post_meta( $post->ID, '_fp_seo_title', true ) ); ?>"
								placeholder="<?php esc_attr_e( 'es: Guida Completa alla SEO WordPress 2025 | Nome Sito', 'fp-seo-performance' ); ?>"
								maxlength="70"
								aria-label="<?php esc_attr_e( 'SEO Title - Titolo ottimizzato per SERP', 'fp-seo-performance' ); ?>"
								style="flex: 1; padding: 10px 14px; font-size: 14px; border: 2px solid #10b981; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
								data-fp-seo-title
							/>
							<button 
								type="button" 
								class="fp-seo-ai-generate-field-btn" 
								data-field="seo_title"
								data-target-id="fp-seo-title"
								data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
								style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);"
								onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
								onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
								title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
							>
								<span style="font-size: 14px;">🤖</span>
								<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
							</button>
						</div>
						<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
							<strong style="color: #059669;">🎯 Alto impatto (+15%)</strong> - Appare come titolo principale in Google. Lunghezza ottimale: 50-60 caratteri con keyword all'inizio.
						</p>
					</div>

					<!-- Meta Description -->
					<div style="position: relative;">
						<label for="fp-seo-meta-description" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
							<span style="display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 16px;">📄</span>
								<?php esc_html_e( 'Meta Description', 'fp-seo-performance' ); ?>
								<span style="display: inline-flex; padding: 2px 8px; background: #10b981; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+10%</span>
							</span>
							<span id="fp-seo-meta-description-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/160</span>
						</label>
						<div style="display: flex; gap: 8px; align-items: flex-start;">
							<textarea 
								id="fp-seo-meta-description" 
								name="fp_seo_meta_description"
								placeholder="<?php esc_attr_e( 'es: Scopri come ottimizzare WordPress per la SEO con la nostra guida completa 2025. Aumenta il traffico del 300% seguendo 5 step comprovati.', 'fp-seo-performance' ); ?>"
								maxlength="200"
								rows="3"
								aria-label="<?php esc_attr_e( 'Meta Description - Descrizione per SERP', 'fp-seo-performance' ); ?>"
								style="flex: 1; padding: 10px 14px; font-size: 13px; border: 2px solid #10b981; border-radius: 8px; background: #fff; resize: vertical; line-height: 1.5; transition: all 0.2s ease;"
								data-fp-seo-meta-description
							><?php echo esc_textarea( get_post_meta( $post->ID, '_fp_seo_meta_description', true ) ); ?></textarea>
							<button 
								type="button" 
								class="fp-seo-ai-generate-field-btn" 
								data-field="meta_description"
								data-target-id="fp-seo-meta-description"
								data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
								style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2); height: fit-content;"
								onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
								onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
								title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
							>
								<span style="font-size: 14px;">🤖</span>
								<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
							</button>
						</div>
					<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
						<strong style="color: #059669;">🎯 Medio-Alto impatto (+10%)</strong> - Descrizione sotto il titolo in Google. Include keyword + CTA. Ottimale: 150-160 caratteri.
					</p>
				</div>

				<!-- Slug (URL Permalink) -->
				<div style="position: relative;">
					<label for="fp-seo-slug" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
						<span style="display: flex; align-items: center; gap: 8px;">
							<span style="font-size: 16px;">🔗</span>
							<?php esc_html_e( 'Slug (URL Permalink)', 'fp-seo-performance' ); ?>
							<span style="display: inline-flex; padding: 2px 8px; background: #6b7280; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+6%</span>
						</span>
						<span id="fp-seo-slug-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0 parole</span>
					</label>
					<div style="display: flex; gap: 8px; align-items: stretch;">
						<input 
							type="text" 
							id="fp-seo-slug" 
							name="fp_seo_slug"
							value="<?php echo esc_attr( $post->post_name ); ?>"
							placeholder="<?php esc_attr_e( 'es: guida-seo-wordpress-2025 (lowercase, separate-con-trattini)', 'fp-seo-performance' ); ?>"
							maxlength="100"
							aria-label="<?php esc_attr_e( 'Slug URL - Permalink SEO-friendly', 'fp-seo-performance' ); ?>"
							style="flex: 1; padding: 10px 14px; font-size: 13px; font-family: monospace; border: 2px solid #9ca3af; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
							data-fp-seo-slug
						/>
						<button 
							type="button" 
							class="fp-seo-ai-generate-field-btn" 
							data-field="slug"
							data-target-id="fp-seo-slug"
							data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'fp_seo_ai_generate' ) ); ?>"
							style="padding: 10px 16px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);"
							onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(14, 165, 233, 0.3)';"
							onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 4px rgba(14, 165, 233, 0.2)';"
							title="<?php esc_attr_e( 'Genera con AI', 'fp-seo-performance' ); ?>"
						>
							<span style="font-size: 14px;">🤖</span>
							<span><?php esc_html_e( 'AI', 'fp-seo-performance' ); ?></span>
						</button>
					</div>
					<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
						<strong style="color: #6b7280;">📊 Medio-Basso impatto (+6%)</strong> - URL della pagina (dopo il dominio). Breve, con keyword, solo lowercase e trattini. Es: <code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 10px;">dominio.it/<strong>questo-e-lo-slug</strong></code>
					</p>
				</div>

				<!-- Riassunto (Excerpt) -->
				<div style="position: relative;">
					<label for="fp-seo-excerpt" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
						<span style="display: flex; align-items: center; gap: 8px;">
							<span style="font-size: 16px;">📋</span>
							<?php esc_html_e( 'Riassunto (Excerpt)', 'fp-seo-performance' ); ?>
							<span style="display: inline-flex; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+9%</span>
						</span>
						<span id="fp-seo-excerpt-counter" style="font-size: 12px; font-weight: 600; color: #6b7280;">0/150</span>
					</label>
					<textarea 
						id="fp-seo-excerpt" 
						name="fp_seo_excerpt"
						placeholder="<?php esc_attr_e( 'es: Breve riassunto del contenuto. Usato come fallback per meta description se non compilata. 100-150 caratteri ottimali.', 'fp-seo-performance' ); ?>"
						maxlength="300"
						rows="3"
						aria-label="<?php esc_attr_e( 'Riassunto - Excerpt usato come fallback meta description', 'fp-seo-performance' ); ?>"
						style="width: 100%; padding: 10px 14px; font-size: 13px; border: 2px solid #3b82f6; border-radius: 8px; background: #fff; resize: vertical; line-height: 1.5; transition: all 0.2s ease;"
						data-fp-seo-excerpt
					><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
					<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
						<strong style="color: #3b82f6;">🎯 Medio impatto (+9%)</strong> - Riassunto breve del contenuto. Usato come fallback se Meta Description è vuota. Appare anche in archivi/elenchi. Ottimale: 100-150 caratteri.
					</p>
				</div>

				<!-- Separator -->
				<div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%); margin: 8px 0;"></div>
				
				<!-- Focus Keyword -->
					<div style="position: relative;">
						<label for="fp-seo-focus-keyword" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
							<span style="display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 16px;">🔑</span>
								<?php esc_html_e( 'Focus Keyword (Principale)', 'fp-seo-performance' ); ?>
								<span style="display: inline-flex; padding: 2px 8px; background: #3b82f6; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+8%</span>
							</span>
						</label>
						<input 
							type="text" 
							id="fp-seo-focus-keyword" 
							name="fp_seo_focus_keyword"
							value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true ) ); ?>"
							placeholder="<?php esc_attr_e( 'es: seo wordpress, ottimizzazione motori ricerca', 'fp-seo-performance' ); ?>"
							aria-label="<?php esc_attr_e( 'Focus Keyword - Parola chiave principale per ottimizzazione SEO', 'fp-seo-performance' ); ?>"
							aria-describedby="fp-seo-focus-keyword-hint"
							style="width: 100%; padding: 10px 14px; font-size: 14px; border: 2px solid #3b82f6; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
							data-fp-seo-focus-keyword
						/>
						<span id="fp-seo-focus-keyword-hint" class="screen-reader-text">
							<?php esc_html_e( 'Inserisci la parola chiave principale che vuoi ottimizzare per questo contenuto. Verrà analizzata nei title, meta description e contenuto.', 'fp-seo-performance' ); ?>
						</span>
						<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
							<strong style="color: #3b82f6;">🎯 Medio impatto (+8%)</strong> - Keyword principale che guida l'analisi SEO. Usala nel title, description e contenuto.
						</p>
					</div>
					
					<!-- Secondary Keywords -->
					<div style="position: relative;">
						<label for="fp-seo-secondary-keywords" style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 600; color: #0c4a6e; margin-bottom: 8px;">
							<span style="display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 16px;">🔐</span>
								<?php esc_html_e( 'Secondary Keywords', 'fp-seo-performance' ); ?>
								<span style="display: inline-flex; padding: 2px 8px; background: #6b7280; color: #fff; border-radius: 999px; font-size: 10px; font-weight: 700;">+5%</span>
							</span>
						</label>
						<input 
							type="text" 
							id="fp-seo-secondary-keywords" 
							name="fp_seo_secondary_keywords"
							value="<?php 
								$secondary = get_post_meta( $post->ID, self::META_SECONDARY_KEYWORDS, true );
								echo esc_attr( is_array( $secondary ) ? implode( ', ', $secondary ) : $secondary );
							?>"
							placeholder="<?php esc_attr_e( 'es: plugin seo, guida ottimizzazione, wordpress performance (separate con virgola)', 'fp-seo-performance' ); ?>"
							aria-label="<?php esc_attr_e( 'Keyword Secondarie - Separate con virgola', 'fp-seo-performance' ); ?>"
							aria-describedby="fp-seo-secondary-keywords-hint"
							style="width: 100%; padding: 10px 14px; font-size: 13px; border: 2px solid #9ca3af; border-radius: 8px; background: #fff; transition: all 0.2s ease;"
							data-fp-seo-secondary-keywords
						/>
						<span id="fp-seo-secondary-keywords-hint" class="screen-reader-text">
							<?php esc_html_e( 'Inserisci keyword secondarie separate da virgola. Aiutano l\'analisi a valutare la copertura semantica del contenuto.', 'fp-seo-performance' ); ?>
						</span>
						<p style="margin: 8px 0 0; font-size: 11px; color: #64748b; line-height: 1.5;">
					<strong style="color: #6b7280;">📊 Basso-Medio impatto (+5%)</strong> - Keyword correlate per copertura semantica. Separate con virgola.
				</p>
			</div>
		</div>
		
		<!-- Advanced Keywords Manager (optional integration) -->
		<?php
		try {
			$keywords_manager = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Keywords\MultipleKeywordsManager::class );
			if ( $keywords_manager && method_exists( $keywords_manager, 'render_keywords_metabox' ) ) {
				// Il manager potrebbe aggiungere tab o funzionalità avanzate
				// $keywords_manager->render_keywords_metabox( $post );
			}
		} catch ( \Exception $e ) {
			// Manager non disponibile - campi base già mostrati sopra
		}
		?>
		</div>
	</div>
	
	<div class="fp-seo-performance-metabox__section">
		<h4 class="fp-seo-performance-metabox__section-heading">
			<span class="fp-seo-section-icon">📈</span>
			<?php esc_html_e( 'Analisi SEO', 'fp-seo-performance' ); ?>
		</h4>
		<div class="fp-seo-performance-metabox__section-content">
		<div class="fp-seo-performance-metabox__unified-analysis">
			<?php
			// Count by status
			$status_counts = array(
				'fail' => 0,
				'warn' => 0,
				'pass' => 0,
			);
			foreach ( $checks as $check ) {
				$status = $check['status'] ?? 'pending';
				if ( isset( $status_counts[ $status ] ) ) {
					$status_counts[ $status ]++;
				}
			}
			?>
			
			<?php if ( ! empty( $checks ) ) : ?>
				<div class="fp-seo-performance-summary">
					<?php if ( $status_counts['fail'] > 0 ) : ?>
						<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--fail">
							❌ <?php echo esc_html( $status_counts['fail'] ); ?> <?php esc_html_e( 'Critico', 'fp-seo-performance' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $status_counts['warn'] > 0 ) : ?>
						<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--warn">
							⚠️ <?php echo esc_html( $status_counts['warn'] ); ?> <?php esc_html_e( 'Attenzione', 'fp-seo-performance' ); ?>
						</span>
					<?php endif; ?>
					<?php if ( $status_counts['pass'] > 0 ) : ?>
						<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--pass">
							✅ <?php echo esc_html( $status_counts['pass'] ); ?> <?php esc_html_e( 'Ottimo', 'fp-seo-performance' ); ?>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			
			<?php if ( empty( $checks ) ) : ?>
				<div class="fp-seo-performance-metabox__analysis-list--empty">
					✅ <?php esc_html_e( 'Ottimo! Tutti gli indicatori sono ottimali.', 'fp-seo-performance' ); ?>
				</div>
			<?php else : ?>
				<ul class="fp-seo-performance-metabox__analysis-list" data-fp-seo-analysis>
					<?php 
					$delay = 0;
					foreach ( $checks as $check ) : 
						$delay += 0.05; // 50ms delay tra ogni elemento
						$status = $check['status'] ?? 'pending';
						$icon = '';
						$status_text = '';
						
						switch ( $status ) {
							case 'fail':
								$icon = '🔴';
								$status_text = __( 'Critico', 'fp-seo-performance' );
								break;
							case 'warn':
								$icon = '🟡';
								$status_text = __( 'Attenzione', 'fp-seo-performance' );
								break;
							case 'pass':
								$icon = '🟢';
								$status_text = __( 'Ottimo', 'fp-seo-performance' );
								break;
							default:
								$icon = '⚪';
								$status_text = __( 'In attesa', 'fp-seo-performance' );
						}
					?>
						<li class="fp-seo-performance-analysis-item fp-seo-performance-analysis-item--<?php echo esc_attr( $status ); ?>" style="animation-delay: <?php echo esc_attr( $delay . 's' ); ?>" data-check-id="<?php echo esc_attr( $check['id'] ?? '' ); ?>">
							<div class="fp-seo-performance-analysis-item__header">
								<span class="fp-seo-performance-analysis-item__icon"><?php echo $icon; ?></span>
								<span class="fp-seo-performance-analysis-item__title"><?php echo esc_html( $check['label'] ?? '' ); ?></span>
								<span class="fp-seo-performance-analysis-item__status"><?php echo esc_html( $status_text ); ?></span>
								<?php if ( $status !== 'pass' ) : ?>
									<button type="button" class="fp-seo-help-toggle" title="<?php esc_attr_e( 'Mostra aiuto', 'fp-seo-performance' ); ?>" data-help-toggle>
										<span class="dashicons dashicons-editor-help"></span>
									</button>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $check['hint'] ) ) : ?>
								<div class="fp-seo-performance-analysis-item__description">
									<?php echo esc_html( $check['hint'] ); ?>
								</div>
							<?php endif; ?>
							
							<?php if ( $status !== 'pass' ) : ?>
								<div class="fp-seo-check-help" data-help-content style="display: none;">
									<div class="fp-seo-check-help__section">
										<h5 class="fp-seo-check-help__title">
											<span class="dashicons dashicons-lightbulb"></span>
											<?php esc_html_e( 'Perché è importante?', 'fp-seo-performance' ); ?>
										</h5>
										<p class="fp-seo-check-help__text">
											<?php echo esc_html( $this->get_check_importance( $check['id'] ?? '' ) ); ?>
										</p>
									</div>
									<div class="fp-seo-check-help__section">
										<h5 class="fp-seo-check-help__title">
											<span class="dashicons dashicons-admin-tools"></span>
											<?php esc_html_e( 'Come migliorare', 'fp-seo-performance' ); ?>
										</h5>
										<p class="fp-seo-check-help__text">
											<?php echo esc_html( $this->get_check_howto( $check['id'] ?? '' ) ); ?>
										</p>
									</div>
									<?php 
									$example = $this->get_check_example( $check['id'] ?? '' );
									if ( $example ) : 
									?>
										<div class="fp-seo-check-help__example">
											<strong><?php esc_html_e( '✅ Esempio:', 'fp-seo-performance' ); ?></strong>
											<code><?php echo esc_html( $example ); ?></code>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
		<?php endif; ?>
	</div>
		</div>
	</div>
		
		<?php 
		// AI Generator now integrated per-field with individual buttons
		// $this->render_ai_generator( $post ); 
		$this->render_inline_ai_field_script( $post );
		?>
		
		<?php $this->render_gsc_metrics( $post ); ?>

		<!-- Section 2: AI OPTIMIZATION (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #f59e0b;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🤖</span>
					<?php esc_html_e( 'Q&A Pairs per AI', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);">
					<span style="font-size: 14px;">🚀</span>
					<?php esc_html_e( 'Impact: +18%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
			<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b;">
				<strong style="color: #d97706;">🤖 Alto impatto (+18%)</strong> - Le Q&A aiutano ChatGPT, Gemini e Perplexity a citare i tuoi contenuti. Essenziale per AI Overview di Google.
			</p>
					<?php
					// Integra il contenuto Q&A Pairs
					try {
						$qa_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\QAMetaBox::class );
						if ( $qa_metabox ) {
							$qa_metabox->render( $post );
						}
					} catch ( \Exception $e ) {
						Logger::debug( 'QAMetaBox not available', array( 'error' => $e->getMessage() ) );
					}
					?>
				</div>
			</div>

			<!-- GEO Claims - Integrated Section (solo se GEO abilitato) -->
			<?php
			// Verifica se GEO è abilitato prima di renderizzare
			$geo_options = \FP\SEO\Utils\Options::get();
			if ( ! empty( $geo_options['geo']['enabled'] ) ) :
			?>
			<div class="fp-seo-performance-metabox__section">
				<h4 class="fp-seo-performance-metabox__section-heading">
					<span class="fp-seo-section-icon">🗺️</span>
					<?php esc_html_e( 'GEO Claims', 'fp-seo-performance' ); ?>
				</h4>
				<div class="fp-seo-performance-metabox__section-content">
					<?php
					try {
						$geo_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\GeoMetaBox::class );
						if ( $geo_metabox ) {
							$geo_metabox->render( $post );
						}
					} catch ( \Exception $e ) {
						Logger::debug( 'GeoMetaBox not available', array( 'error' => $e->getMessage() ) );
					}
					?>
				</div>
			</div>
			<?php endif; ?>

			<!-- Freshness & Temporal Signals - Integrated Section -->
			<div class="fp-seo-performance-metabox__section">
				<h4 class="fp-seo-performance-metabox__section-heading">
					<span class="fp-seo-section-icon">📅</span>
					<?php esc_html_e( 'Freshness & Temporal Signals', 'fp-seo-performance' ); ?>
				</h4>
				<div class="fp-seo-performance-metabox__section-content">
					<?php
					try {
						$freshness_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Admin\FreshnessMetaBox::class );
						if ( $freshness_metabox ) {
							$freshness_metabox->render( $post );
						}
					} catch ( \Exception $e ) {
						Logger::debug( 'FreshnessMetaBox not available', array( 'error' => $e->getMessage() ) );
					}
					?>
				</div>
			</div>

		<!-- Section 3: SOCIAL MEDIA (Medium Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #8b5cf6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📱</span>
					<?php esc_html_e( 'Social Media Preview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(139, 92, 246, 0.2);">
					<span style="font-size: 14px;">📊</span>
					<?php esc_html_e( 'Impact: +12%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
			<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #f5f3ff; border-radius: 6px; border-left: 3px solid #8b5cf6;">
				<strong style="color: #7c3aed;">📱 Medio impatto (+12%)</strong> - Ottimizza title, description e immagini per Facebook, Twitter, LinkedIn e Pinterest. Aumenta condivisioni e traffico social.
			</p>
					<?php
					try {
						$social_metabox = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Social\ImprovedSocialMediaManager::class );
						if ( $social_metabox && method_exists( $social_metabox, 'render_improved_social_metabox' ) ) {
							$social_metabox->render_improved_social_metabox( $post );
						}
					} catch ( \Exception $e ) {
						Logger::debug( 'Social metabox not available', array( 'error' => $e->getMessage() ) );
					}
					?>
				</div>
			</div>

		<!-- Section 4: INTERNAL LINKS (Medium-Low Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #06b6d4;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">🔗</span>
					<?php esc_html_e( 'Internal Link Suggestions', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);">
					<span style="font-size: 14px;">🔗</span>
					<?php esc_html_e( 'Impact: +7%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
			<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #ecfeff; border-radius: 6px; border-left: 3px solid #06b6d4;">
				<strong style="color: #0891b2;">🔗 Medio-Basso impatto (+7%)</strong> - Link interni distribuiscono il PageRank e migliorano la navigazione. Collega contenuti correlati per SEO on-site.
			</p>
					<?php
					try {
						$links_manager = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Links\InternalLinkManager::class );
						if ( $links_manager && method_exists( $links_manager, 'render_links_metabox' ) ) {
							$links_manager->render_links_metabox( $post );
						}
					} catch ( \Exception $e ) {
						Logger::debug( 'Internal Links not available', array( 'error' => $e->getMessage() ) );
					}
				?>
			</div>
		</div>

		<!-- Section 5: FAQ SCHEMA (Very High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #f59e0b;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">❓</span>
					<?php esc_html_e( 'FAQ Schema - AI Overview', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impact: +20%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b;">
					<strong style="color: #d97706;">⚡ Molto Alto impatto (+20%)</strong> - Le FAQ aumentano visibilità Google AI Overview del 50%. Essenziali per ChatGPT, Gemini e Perplexity.
				</p>
				<?php
				// Integra il rendering FAQ
				try {
					$schema_metaboxes = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Editor\SchemaMetaboxes::class );
					if ( $schema_metaboxes && method_exists( $schema_metaboxes, 'render_faq_metabox' ) ) {
						$schema_metaboxes->render_faq_metabox( $post );
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'FAQ Schema not available', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
		</div>

		<!-- Section 6: HOWTO SCHEMA (High Impact) -->
		<div class="fp-seo-performance-metabox__section" style="border-left: 4px solid #3b82f6;">
			<h4 class="fp-seo-performance-metabox__section-heading" style="display: flex; justify-content: space-between; align-items: center;">
				<span style="display: flex; align-items: center; gap: 8px;">
					<span class="fp-seo-section-icon">📖</span>
					<?php esc_html_e( 'HowTo Schema - Guide', 'fp-seo-performance' ); ?>
				</span>
				<span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);">
					<span style="font-size: 14px;">⚡</span>
					<?php esc_html_e( 'Impact: +15%', 'fp-seo-performance' ); ?>
				</span>
			</h4>
			<div class="fp-seo-performance-metabox__section-content">
				<p style="margin: 0 0 16px; font-size: 12px; color: #64748b; line-height: 1.6; padding: 12px; background: #eff6ff; border-radius: 6px; border-left: 3px solid #3b82f6;">
					<strong style="color: #2563eb;">⚡ Alto impatto (+15%)</strong> - Guide con HowTo Schema mostrano step nei risultati Google con rich snippets visuali. Ottimale per tutorial e guide.
				</p>
				<?php
				// Integra il rendering HowTo
				try {
					$schema_metaboxes = \FP\SEO\Infrastructure\Plugin::instance()->get_container()->get( \FP\SEO\Editor\SchemaMetaboxes::class );
					if ( $schema_metaboxes && method_exists( $schema_metaboxes, 'render_howto_metabox' ) ) {
						$schema_metaboxes->render_howto_metabox( $post );
					}
				} catch ( \Exception $e ) {
					Logger::debug( 'HowTo Schema not available', array( 'error' => $e->getMessage() ) );
				}
				?>
			</div>
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

	// Save SEO Title
	if ( isset( $_POST['fp_seo_title'] ) ) {
		$seo_title = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_title'] ) );
		if ( '' !== trim( $seo_title ) ) {
			update_post_meta( $post_id, '_fp_seo_title', $seo_title );
		} else {
			delete_post_meta( $post_id, '_fp_seo_title' );
		}
	}

	// Save Meta Description
	if ( isset( $_POST['fp_seo_meta_description'] ) ) {
		$meta_description = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_meta_description'] ) );
		if ( '' !== trim( $meta_description ) ) {
			update_post_meta( $post_id, '_fp_seo_meta_description', $meta_description );
		} else {
			delete_post_meta( $post_id, '_fp_seo_meta_description' );
		}
	}

	// Save Slug (post_name)
	if ( isset( $_POST['fp_seo_slug'] ) ) {
		$slug = sanitize_title( wp_unslash( (string) $_POST['fp_seo_slug'] ) );
		if ( '' !== trim( $slug ) && $slug !== get_post_field( 'post_name', $post_id ) ) {
			// SECURITY: Remove save_post hook temporarily to prevent infinite loop
			remove_action( 'save_post', array( $this, 'save_meta' ), 10 );
			
			// Update post slug using wp_update_post
			$updated = wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $slug,
				)
			);
			
			// Re-add the hook with same priority and arguments
			add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		}
	}

	// Save Excerpt (post_excerpt)
	if ( isset( $_POST['fp_seo_excerpt'] ) ) {
		$excerpt = sanitize_textarea_field( wp_unslash( (string) $_POST['fp_seo_excerpt'] ) );
		$current_excerpt = get_post_field( 'post_excerpt', $post_id );
		
		if ( $excerpt !== $current_excerpt ) {
			// SECURITY: Remove save_post hook temporarily to prevent infinite loop
			remove_action( 'save_post', array( $this, 'save_meta' ), 10 );
			
			// Update post excerpt using wp_update_post
			$updated = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_excerpt' => $excerpt,
				)
			);
			
			// Re-add the hook with same priority and arguments
			add_action( 'save_post', array( $this, 'save_meta' ), 10, 1 );
		}
	}

	// Save focus keyword
	if ( isset( $_POST['fp_seo_focus_keyword'] ) ) {
		$focus_keyword = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_focus_keyword'] ) );
		if ( '' !== trim( $focus_keyword ) ) {
			update_post_meta( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
		} else {
			delete_post_meta( $post_id, self::META_FOCUS_KEYWORD );
		}
	}

	// Save secondary keywords
	if ( isset( $_POST['fp_seo_secondary_keywords'] ) ) {
		$secondary_raw = sanitize_text_field( wp_unslash( (string) $_POST['fp_seo_secondary_keywords'] ) );
		$secondary_keywords = array_filter( 
			array_map( 'trim', explode( ',', $secondary_raw ) ),
			static function( $keyword ) {
				return '' !== $keyword;
			}
		);
		
		if ( ! empty( $secondary_keywords ) ) {
			update_post_meta( $post_id, self::META_SECONDARY_KEYWORDS, $secondary_keywords );
		} else {
			delete_post_meta( $post_id, self::META_SECONDARY_KEYWORDS );
		}
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

		$content           = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( (string) $_POST['content'] ) ) : '';
		$title             = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '';
		$excerpt           = isset( $_POST['excerpt'] ) ? wp_kses_post( wp_unslash( (string) $_POST['excerpt'] ) ) : '';
		$meta              = isset( $_POST['metaDescription'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['metaDescription'] ) ) : '';
		$canonical         = isset( $_POST['canonical'] ) ? esc_url_raw( wp_unslash( (string) $_POST['canonical'] ) ) : null;
		$robots            = isset( $_POST['robots'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['robots'] ) ) : null;
		$focus_keyword     = isset( $_POST['focusKeyword'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['focusKeyword'] ) ) : '';
		$secondary_raw     = isset( $_POST['secondaryKeywords'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['secondaryKeywords'] ) ) : '';
		
		$secondary_keywords = array();
		if ( '' !== $secondary_raw ) {
			$secondary_keywords = array_filter(
				array_map( 'trim', explode( ',', $secondary_raw ) ),
				static function( $keyword ) {
					return '' !== $keyword;
				}
			);
		}

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
			$robots,
			$focus_keyword,
			array_values( $secondary_keywords )
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
		$focus_keyword = get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true );
		$secondary_keywords = get_post_meta( $post->ID, self::META_SECONDARY_KEYWORDS, true );
		
		if ( ! is_array( $secondary_keywords ) ) {
			$secondary_keywords = array();
		}
		
		$context = new Context(
			(int) $post->ID,
			(string) $post->post_content,
			(string) $post->post_title,
			MetadataResolver::resolve_meta_description( $post ),
			MetadataResolver::resolve_canonical_url( $post ),
			MetadataResolver::resolve_robots( $post ),
			is_string( $focus_keyword ) ? $focus_keyword : '',
			$secondary_keywords
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

		// Trigger score history recording if post ID available
		if ( $context->post_id() ) {
			$full_score = array_merge( $score_data, array( 'summary' => $summary ) );
			
			/**
			 * Fires after score calculation for history tracking
			 *
			 * @param int   $post_id Post ID.
			 * @param array $score   Score data with summary.
			 */
			do_action( 'fpseo_after_score_calculation', $context->post_id(), $full_score );
		}

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
	 * Render GSC metrics for post
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function render_gsc_metrics( \WP_Post $post ): void {
		$options = Options::get();
		$gsc     = $options['gsc'] ?? array();

		if ( empty( $gsc['enabled'] ) ) {
			return;
		}

		$gsc_data = new GscData();
		$metrics  = $gsc_data->get_post_metrics( $post->ID, 28 );

		if ( ! $metrics ) {
			return;
		}

		?>
		<div class="fp-seo-gsc-post-metrics" style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
			<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #111827;">
				📊 <?php esc_html_e( 'Google Search Console (Last 28 Days)', 'fp-seo-performance' ); ?>
			</h4>
			
			<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Clicks', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #059669;">
						<?php echo esc_html( number_format_i18n( $metrics['clicks'] ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Impressions', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #2563eb;">
						<?php echo esc_html( number_format_i18n( $metrics['impressions'] ) ); ?>
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'CTR', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( $metrics['ctr'] ); ?>%
					</div>
				</div>
				
				<div style="text-align: center;">
					<div style="font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Position', 'fp-seo-performance' ); ?>
					</div>
					<div style="font-size: 20px; font-weight: 700; color: #111827;">
						<?php echo esc_html( $metrics['position'] ); ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $metrics['queries'] ) ) : ?>
				<details style="margin-top: 12px;">
					<summary style="cursor: pointer; font-weight: 600; color: #374151;">
						🔍 <?php esc_html_e( 'Top Queries', 'fp-seo-performance' ); ?> (<?php echo count( $metrics['queries'] ); ?>)
					</summary>
					<ul style="margin: 8px 0 0; padding: 0; list-style: none;">
						<?php foreach ( array_slice( $metrics['queries'], 0, 5 ) as $query_data ) : ?>
							<li style="padding: 6px 8px; background: #fff; border-radius: 4px; margin-bottom: 4px; font-size: 12px;">
								<strong><?php echo esc_html( $query_data['query'] ); ?></strong>
								<span style="color: #6b7280; margin-left: 10px;">
									<?php echo esc_html( $query_data['clicks'] ); ?> clicks, 
									pos <?php echo esc_html( $query_data['position'] ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render inline script for AI field buttons
	 *
	 * @param \WP_Post $post Current post.
	 */
	private function render_inline_ai_field_script( \WP_Post $post ): void {
		$ai_enabled = Options::get_option( 'ai.enable_auto_generation', true );
		$api_key    = Options::get_option( 'ai.openai_api_key', '' );

		if ( ! $ai_enabled || empty( $api_key ) ) {
			return;
		}
		?>
		<script>
		(function($) {
			'use strict';

			$(document).ready(function() {
				// Handle click on AI field generation buttons
				$(document).on('click', '.fp-seo-ai-generate-field-btn', function(e) {
					e.preventDefault();
					
					const $btn = $(this);
					const field = $btn.data('field');
					const targetId = $btn.data('target-id');
					const postId = $btn.data('post-id');
					const nonce = $btn.data('nonce');
					
					// Validation
					if (!field || !targetId || !postId || !nonce) {
						alert('Configurazione non valida');
						return;
					}

					// Get content and title
					const content = getEditorContent();
					const title = getPostTitle();
					
					if (!content || !title) {
						alert('Contenuto o titolo mancante. Assicurati di aver scritto del contenuto prima di generare.');
						return;
					}

					// Disable button and show loading
					$btn.prop('disabled', true);
					const originalHtml = $btn.html();
					$btn.html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; margin: 0;"></span>');

					// Call AJAX
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'fp_seo_generate_ai_content',
							nonce: nonce,
							post_id: postId,
							content: content,
							title: title,
							focus_keyword: '',
						},
						success: function(response) {
							if (response.success && response.data) {
								// Fill the specific field
								const $target = $('#' + targetId);
								
								if ($target.length) {
									let value = '';
									switch(field) {
										case 'seo_title':
											value = response.data.seo_title || '';
											break;
										case 'meta_description':
											value = response.data.meta_description || '';
											break;
										case 'slug':
											value = response.data.slug || '';
											break;
									}
									
									if (value) {
										$target.val(value).trigger('input');
										
										// Highlight with animation
										$target.css({
											'background': '#f0fdf4',
											'border-color': '#10b981',
											'transition': 'all 0.3s ease'
										});
										
										setTimeout(function() {
											$target.css({
												'background': '#fff',
												'transition': 'all 0.5s ease'
											});
										}, 2000);
										
										// Show success checkmark
										const $success = $('<span class="fp-seo-ai-success" style="margin-left: 8px; color: #10b981; font-size: 18px; animation: fadeIn 0.3s ease;">✓</span>');
										$btn.after($success);
										setTimeout(function() {
											$success.fadeOut(function() { $(this).remove(); });
										}, 3000);
									}
								}
							} else {
								const errorMsg = response.data?.message || 'Errore durante la generazione';
								showFieldError($btn, errorMsg);
							}
						},
						error: function(xhr, status, error) {
							console.error('AI Field Generation Error:', error);
							
							let errorMessage = 'Errore di connessione. Riprova più tardi.';
							
							if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
								errorMessage = xhr.responseJSON.data.message;
							} else if (xhr.statusText) {
								errorMessage = 'Errore del server (' + xhr.status + '): ' + xhr.statusText;
							}
							
							showFieldError($btn, errorMessage);
						},
						complete: function() {
							// Restore button
							$btn.prop('disabled', false);
							$btn.html(originalHtml);
						}
					});
				});

				// Helper function to show error near button
				function showFieldError($btn, message) {
					const $parent = $btn.closest('div[style*="flex"]');
					if (!$parent.length) return;
					
					$parent.css('position', 'relative');
					
					const $error = $('<div class="fp-seo-ai-error" style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 8px; padding: 10px 14px; background: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; font-size: 12px; color: #dc2626; z-index: 100; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.1);"></div>');
					$error.html('<strong>⚠️ Errore:</strong> ' + message);
					
					// Remove any existing error
					$parent.find('.fp-seo-ai-error').remove();
					$parent.append($error);
					
					setTimeout(function() {
						$error.fadeOut(function() {
							$(this).remove();
						});
					}, 8000);
				}

				// Get editor content (Classic or Gutenberg)
				function getEditorContent() {
					// Try Classic Editor first
					if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
						return tinyMCE.activeEditor.getContent();
					}
					
					// Try textarea (when in Text mode)
					const $textarea = $('#content');
					if ($textarea.length) {
						return $textarea.val();
					}
					
					// Try Gutenberg
					if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
						const editor = wp.data.select('core/editor');
						if (editor && typeof editor.getEditedPostContent === 'function') {
							return editor.getEditedPostContent();
						}
					}
					
					return '';
				}

				// Get post title
				function getPostTitle() {
					// Try Classic Editor
					const $title = $('#title');
					if ($title.length) {
						return $title.val();
					}
					
					// Try Gutenberg
					if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
						const editor = wp.data.select('core/editor');
						if (editor && typeof editor.getEditedPostAttribute === 'function') {
							return editor.getEditedPostAttribute('title');
						}
					}
					
					return '';
				}

				// Add rotation animation
				if (!document.getElementById('fp-seo-ai-field-animations')) {
					const style = document.createElement('style');
					style.id = 'fp-seo-ai-field-animations';
					style.textContent = `
						@keyframes rotation {
							from { transform: rotate(0deg); }
							to { transform: rotate(360deg); }
						}
						@keyframes fadeIn {
							from { opacity: 0; transform: scale(0.5); }
							to { opacity: 1; transform: scale(1); }
						}
					`;
					document.head.appendChild(style);
				}
				
				console.log('FP SEO: AI Field Generator initialized');
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Render AI content generator section (DEPRECATED - now using per-field buttons)
	 *
	 * @param \WP_Post $post Current post.
	 */
	private function render_ai_generator( \WP_Post $post ): void {
		$ai_enabled = Options::get_option( 'ai.enable_auto_generation', true );
		$api_key    = Options::get_option( 'ai.openai_api_key', '' );

		if ( ! $ai_enabled || empty( $api_key ) ) {
			return;
		}

		// Nonce for AI generation
		$ai_nonce = wp_create_nonce( 'fp_seo_ai_generate' );

		?>
		<div class="fp-seo-ai-generator" style="margin-top: 20px; padding: 16px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 8px; border: 1px solid #0ea5e9;">
			<h4 style="margin: 0 0 12px; font-size: 14px; font-weight: 600; color: #0c4a6e; display: flex; align-items: center; gap: 8px;">
				<span style="font-size: 18px;">🤖</span>
				<?php esc_html_e( 'Generazione AI - Contenuti SEO', 'fp-seo-performance' ); ?>
			</h4>
			
			<p style="margin: 0 0 12px; font-size: 13px; color: #475569; line-height: 1.5;">
				<?php esc_html_e( 'Genera automaticamente titolo SEO, meta description e slug ottimizzati con l\'intelligenza artificiale.', 'fp-seo-performance' ); ?>
			</p>

			<!-- Focus Keyword Input -->
			<div style="margin-bottom: 12px;">
				<label for="fp-seo-ai-focus-keyword-input" style="display: block; font-size: 12px; font-weight: 600; color: #0c4a6e; margin-bottom: 6px;">
					🎯 <?php esc_html_e( 'Focus Keyword (Opzionale)', 'fp-seo-performance' ); ?>
				</label>
				<input 
					type="text" 
					id="fp-seo-ai-focus-keyword-input" 
					placeholder="<?php esc_attr_e( 'es: SEO WordPress, marketing digitale, ...', 'fp-seo-performance' ); ?>"
					style="width: 100%; padding: 8px 12px; font-size: 13px; border: 2px solid #bae6fd; border-radius: 6px; background: #fff; transition: all 0.2s ease;"
					onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)';"
					onblur="this.style.borderColor='#bae6fd'; this.style.boxShadow='none';"
				/>
				<p style="margin: 6px 0 0; font-size: 11px; color: #64748b; font-style: italic;">
					💡 <?php esc_html_e( 'Inserisci la parola chiave principale che vuoi ottimizzare. Se lasci vuoto, l\'AI la identificherà automaticamente dal contenuto.', 'fp-seo-performance' ); ?>
				</p>
			</div>

			<button 
				type="button" 
				id="fp-seo-ai-generate-btn" 
				class="button button-primary" 
				data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
				data-nonce="<?php echo esc_attr( $ai_nonce ); ?>"
				aria-label="<?php esc_attr_e( 'Genera contenuti SEO ottimizzati con intelligenza artificiale', 'fp-seo-performance' ); ?>"
				aria-describedby="fp-seo-ai-description-text"
				style="background: #0ea5e9; border-color: #0ea5e9; font-weight: 600; padding: 8px 16px; height: auto; display: flex; align-items: center; gap: 6px;"
			>
				<span class="dashicons dashicons-admin-generic" style="margin: 0;" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Genera con AI', 'fp-seo-performance' ); ?></span>
			</button>
			<span id="fp-seo-ai-description-text" class="screen-reader-text">
				<?php esc_html_e( 'Genera automaticamente titolo SEO, meta description e slug ottimizzati usando intelligenza artificiale basata sul contenuto del post.', 'fp-seo-performance' ); ?>
			</span>

			<!-- Loading indicator -->
			<div id="fp-seo-ai-loading" style="display: none; margin-top: 12px; padding: 12px; background: #fff; border-radius: 6px; border-left: 3px solid #0ea5e9;">
				<div style="display: flex; align-items: center; gap: 10px;">
					<span class="spinner is-active" style="float: none; margin: 0;"></span>
					<span style="font-size: 13px; color: #475569;">
						<?php esc_html_e( 'Generazione in corso... Attendere prego.', 'fp-seo-performance' ); ?>
					</span>
				</div>
			</div>

			<!-- Results container -->
			<div id="fp-seo-ai-results" style="display: none; margin-top: 12px;">
				<div style="background: #fff; padding: 14px; border-radius: 6px; border: 1px solid #e5e7eb;">
					<h5 style="margin: 0 0 10px; font-size: 13px; font-weight: 600; color: #059669;">
						✓ <?php esc_html_e( 'Contenuti generati con successo!', 'fp-seo-performance' ); ?>
					</h5>
					
					<div style="display: grid; gap: 10px;">
						<div>
							<label style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Titolo SEO:', 'fp-seo-performance' ); ?></span>
								<span id="fp-seo-ai-title-count" style="font-size: 11px; font-weight: 500; color: #6b7280;">0/60</span>
							</label>
							<input 
								type="text" 
								id="fp-seo-ai-title" 
								readonly 
								style="width: 100%; padding: 6px 10px; font-size: 13px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"
							/>
						</div>

						<div>
							<label style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
								<span><?php esc_html_e( 'Meta Description:', 'fp-seo-performance' ); ?></span>
								<span id="fp-seo-ai-description-count" style="font-size: 11px; font-weight: 500; color: #6b7280;">0/155</span>
							</label>
							<textarea 
								id="fp-seo-ai-description" 
								readonly 
								rows="2"
								style="width: 100%; padding: 6px 10px; font-size: 13px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px; resize: vertical;"
							></textarea>
						</div>

						<div>
							<label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
								<?php esc_html_e( 'Slug:', 'fp-seo-performance' ); ?>
							</label>
							<input 
								type="text" 
								id="fp-seo-ai-slug" 
								readonly 
								style="width: 100%; padding: 6px 10px; font-size: 13px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"
							/>
						</div>

						<div>
							<label style="display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px;">
								<?php esc_html_e( 'Focus Keyword:', 'fp-seo-performance' ); ?>
							</label>
							<input 
								type="text" 
								id="fp-seo-ai-keyword" 
								readonly 
								style="width: 100%; padding: 6px 10px; font-size: 13px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"
							/>
						</div>

						<div style="margin-top: 8px;">
							<button 
								type="button" 
								id="fp-seo-ai-apply-btn" 
								class="button button-primary"
								style="background: #059669; border-color: #059669; font-weight: 600;"
							>
								<?php esc_html_e( 'Applica questi suggerimenti', 'fp-seo-performance' ); ?>
							</button>
							<button 
								type="button" 
								id="fp-seo-ai-copy-btn" 
								class="button"
								style="margin-left: 8px;"
							>
								<?php esc_html_e( 'Copia negli appunti', 'fp-seo-performance' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Error message -->
			<div id="fp-seo-ai-error" style="display: none; margin-top: 12px; padding: 12px; background: #fef2f2; border-left: 3px solid #dc2626; border-radius: 6px;">
				<p style="margin: 0; font-size: 13px; color: #991b1b;" id="fp-seo-ai-error-message"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get check importance explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	private function get_check_importance( string $check_id ): string {
		$importance_map = array(
			'title_length'       => __( 'Il titolo è la prima cosa che gli utenti vedono nelle SERP di Google. Un titolo ben ottimizzato (50-60 caratteri) viene mostrato completamente nei risultati e attira più clic.', 'fp-seo-performance' ),
			'meta_description'   => __( 'La meta description appare sotto il titolo nelle ricerche Google. Una buona description (150-160 caratteri) aumenta il CTR (tasso di clic) del 30-50%.', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'La focus keyword nel titolo aiuta Google a capire l\'argomento principale. I titoli con keyword target rankano in media 15 posizioni più in alto.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Una densità keyword ottimale (1-2%) aiuta il posizionamento senza penalizzazioni per keyword stuffing. Troppo poche keyword = difficile rankare; troppe = penalizzazione Google.', 'fp-seo-performance' ),
			'content_length'     => __( 'Contenuti più lunghi (>1000 parole) tendono a rankare meglio perché forniscono informazioni più complete. Articoli lunghi ottengono il 77% dei backlink.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Una struttura H1-H6 corretta aiuta Google a capire la gerarchia del contenuto. Migliora anche l\'accessibilità per screen reader.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Gli attributi ALT sulle immagini migliorano l\'accessibilità e aiutano il ranking in Google Immagini. Il 27% del traffico organico viene da immagini.', 'fp-seo-performance' ),
			'internal_links'     => __( 'I link interni distribuiscono autorità SEO tra le pagine e aiutano Google a scoprire nuovi contenuti. Siti con buona link structure rankano il 40% meglio.', 'fp-seo-performance' ),
			'external_links'     => __( 'Link a fonti autorevoli aumentano la credibilità del contenuto. Google considera i link esterni un segnale di qualità e profondità dell\'articolo.', 'fp-seo-performance' ),
			'readability'        => __( 'Un contenuto leggibile (punteggio Flesch >60) mantiene gli utenti più tempo sulla pagina, riducendo il bounce rate. Google favorisce contenuti comprensibili.', 'fp-seo-performance' ),
		);

		return $importance_map[ $check_id ] ?? __( 'Questo check SEO è importante per il posizionamento organico del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check how-to-fix explanation
	 *
	 * @param string $check_id Check identifier.
	 * @return string
	 */
	private function get_check_howto( string $check_id ): string {
		$howto_map = array(
			'title_length'       => __( 'Modifica il titolo per mantenerlo tra 50-60 caratteri. Includi la keyword principale all\'inizio. Se troppo lungo, Google lo tronca con "..." perdendo impatto.', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scrivi una description di 150-160 caratteri che riassume il contenuto e include la focus keyword. Usa un tono coinvolgente e aggiungi una call-to-action (CTA).', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Inserisci la focus keyword nel campo apposito sopra, poi assicurati che appaia nel titolo (preferibilmente all\'inizio), nei primi 100 caratteri del contenuto e in almeno un H2.', 'fp-seo-performance' ),
			'keyword_density'    => __( 'Aggiungi o rimuovi keyword per raggiungere 1-2% di densità. Usa sinonimi e keyword correlate (LSI keywords) invece di ripetere sempre la stessa keyword.', 'fp-seo-performance' ),
			'content_length'     => __( 'Espandi il contenuto aggiungendo sezioni utili: esempi pratici, FAQ, statistiche, case study. Punta a minimo 1000 parole per argomenti informativi, 500+ per pagine commerciali.', 'fp-seo-performance' ),
			'headings_structure' => __( 'Usa un solo H1 (titolo principale), poi H2 per sezioni principali, H3 per sottosezioni. Non saltare livelli (es: da H2 a H4). Includi keyword nei heading quando possibile.', 'fp-seo-performance' ),
			'images_alt'         => __( 'Aggiungi un attributo ALT descrittivo a ogni immagine. Descrivi cosa mostra l\'immagine includendo keyword dove appropriato. Es: "screenshot plugin SEO WordPress" invece di "immagine1".', 'fp-seo-performance' ),
			'internal_links'     => __( 'Aggiungi 2-5 link interni a pagine/post correlati. Usa anchor text descrittivo (no "clicca qui"). Link a contenuti pillar e articoli correlati per creare topic clusters.', 'fp-seo-performance' ),
			'external_links'     => __( 'Aggiungi 1-3 link a fonti autorevoli (.gov, .edu, siti riconosciuti nel settore). Apri in nuova tab e usa rel="noopener noreferrer" per sicurezza.', 'fp-seo-performance' ),
			'readability'        => __( 'Semplifica le frasi (max 20 parole). Usa paragrafi corti (3-4 righe). Aggiungi elenchi puntati. Evita gergo tecnico o spiegalo. Usa sottotitoli per spezzare il testo.', 'fp-seo-performance' ),
		);

		return $howto_map[ $check_id ] ?? __( 'Segui le best practices SEO per ottimizzare questo aspetto del tuo contenuto.', 'fp-seo-performance' );
	}

	/**
	 * Get check example
	 *
	 * @param string $check_id Check identifier.
	 * @return string|null
	 */
	private function get_check_example( string $check_id ): ?string {
		$example_map = array(
			'title_length'       => __( 'Guida SEO WordPress: 10 Trucchi per Rankare nel 2025', 'fp-seo-performance' ),
			'meta_description'   => __( 'Scopri 10 tecniche SEO WordPress avanzate per migliorare il ranking nel 2025. Guida pratica con esempi reali e risultati garantiti. Leggi ora!', 'fp-seo-performance' ),
			'focus_keyword'      => __( 'Se keyword = "wordpress seo", includi nel titolo: "WordPress SEO: Guida Completa 2025"', 'fp-seo-performance' ),
			'headings_structure' => __( 'H1: Titolo principale | H2: Cos\'è la SEO | H3: Tecniche on-page | H3: Tecniche off-page', 'fp-seo-performance' ),
			'images_alt'         => __( 'ALT="screenshot dashboard plugin SEO WordPress con analytics traffico organico"', 'fp-seo-performance' ),
		);

		return $example_map[ $check_id ] ?? null;
	}

}
