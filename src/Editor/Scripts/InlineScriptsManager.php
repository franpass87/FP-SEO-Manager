<?php
/**
 * Manages inline JavaScript injection for the metabox.
 *
 * @package FP\SEO\Editor\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Scripts;

use FP\SEO\Editor\Metabox;
use function esc_attr_e;
use function esc_js;
use function get_current_screen;
use function get_option;
use function get_post;
use function in_array;
use function wp_json_encode;
use WP_Post;

/**
 * Manages inline JavaScript for the metabox.
 */
class InlineScriptsManager {
	/**
	 * @var Metabox
	 */
	private $metabox;

	/**
	 * Constructor.
	 *
	 * @param Metabox $metabox Metabox instance.
	 */
	public function __construct( Metabox $metabox ) {
		$this->metabox = $metabox;
	}

	/**
	 * Inject modern styles and scripts in admin head.
	 *
	 * @return void
	 */
	public function inject(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->metabox->get_supported_post_types(), true ) ) {
			return;
		}

		$this->render_homepage_fix_script();
		$this->render_icon_cleanup_script();
		$this->render_help_banner_script();
		$this->render_help_toggle_script();
		$this->render_tooltip_script();
		$this->render_animations_style();
		$this->render_character_counters_script();
	}

	/**
	 * Render homepage title fix script.
	 *
	 * @return void
	 */
	private function render_homepage_fix_script(): void {
		$page_on_front_id = (int) get_option( 'page_on_front' );
		if ( $page_on_front_id <= 0 ) {
			return;
		}

		$requested_post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( $requested_post_id !== $page_on_front_id ) {
			return;
		}

		$homepage = get_post( $page_on_front_id );
		if ( ! $homepage instanceof WP_Post || $homepage->post_title === 'Bozza automatica' ) {
			return;
		}

		?>
		<script>
		(function() {
			const postIdInput = document.querySelector('#post_ID');
			const titleInput = document.querySelector('#title');
			const expectedPostId = <?php echo esc_js( $page_on_front_id ); ?>;
			const expectedTitle = <?php echo wp_json_encode( $homepage->post_title ); ?>;
			
			if (postIdInput && titleInput) {
				const currentPostId = parseInt(postIdInput.value, 10);
				const currentTitle = titleInput.value;
				
				// If post ID is correct but title is wrong, fix it
				if (currentPostId === expectedPostId && currentTitle === 'Bozza automatica') {
					titleInput.value = expectedTitle;
					
					// Also update the editor if it exists (Gutenberg)
					if (window.wp && window.wp.data && window.wp.data.dispatch) {
						try {
							window.wp.data.dispatch('core/editor').editPost({ title: expectedTitle });
						} catch(e) {
							// Gutenberg might not be loaded yet
						}
					}
					
					console.log('FP SEO: Fixed homepage title from "Bozza automatica" to "' + expectedTitle + '"');
				}
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render icon cleanup script.
	 *
	 * @return void
	 */
	private function render_icon_cleanup_script(): void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const icons = document.querySelectorAll('.fp-seo-performance-indicator__icon');
			icons.forEach(function(icon) {
				icon.textContent = '';
			});
		});
		</script>
		<?php
	}

	/**
	 * Render help banner script.
	 *
	 * @return void
	 */
	private function render_help_banner_script(): void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
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
		});
		</script>
		<?php
	}

	/**
	 * Render help toggle script.
	 *
	 * @return void
	 */
	private function render_help_toggle_script(): void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
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
		});
		</script>
		<?php
	}

	/**
	 * Render tooltip script.
	 *
	 * @return void
	 */
	private function render_tooltip_script(): void {
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const tooltipTriggers = document.querySelectorAll('.fp-seo-tooltip-trigger');
			tooltipTriggers.forEach(function(trigger) {
				const tooltipText = trigger.getAttribute('data-tooltip');
				if (tooltipText) {
					trigger.setAttribute('title', tooltipText);
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render animations style.
	 *
	 * @return void
	 */
	private function render_animations_style(): void {
		?>
		<style>
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
		</style>
		<?php
	}

	/**
	 * Render character counters script.
	 *
	 * @return void
	 */
	private function render_character_counters_script(): void {
		?>
		<script>
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
			
			// Excerpt counter and Gutenberg sync
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
				excerptField.addEventListener('input', function() {
					updateExcerptCounter();
					
					// Sync with Gutenberg if available
					if (wp && wp.data && wp.data.dispatch('core/editor')) {
						wp.data.dispatch('core/editor').editPost({
							excerpt: excerptField.value
						});
					}
				});
				
				// Sync from Gutenberg to our field
				if (wp && wp.data && wp.data.select('core/editor')) {
					wp.data.subscribe(function() {
						const gutenbergExcerpt = wp.data.select('core/editor').getEditedPostAttribute('excerpt');
						if (gutenbergExcerpt !== null && gutenbergExcerpt !== excerptField.value) {
							excerptField.value = gutenbergExcerpt || '';
							updateExcerptCounter();
						}
					});
				}
			}
		});
		</script>
		<?php
	}
}


