<?php
/**
 * Manages inline JavaScript for social media metabox.
 *
 * @package FP\SEO\Social\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Scripts;

use FP\SEO\Social\ImprovedSocialMediaManager;
use function esc_js;
use function get_the_ID;
use function get_the_title;
use function is_admin;
use function get_current_screen;
use function in_array;
use function wp_create_nonce;
use function __;

/**
 * Manages inline JavaScript for social media.
 */
class SocialScriptsManager {
	/**
	 * @var ImprovedSocialMediaManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ImprovedSocialMediaManager $manager Social media manager instance.
	 */
	public function __construct( ImprovedSocialMediaManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'render_all_scripts' ) );
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	public function render_all_scripts(): void {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $supported_types, true ) ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			<?php $this->render_character_counters_init(); ?>
			<?php $this->render_tab_switching(); ?>
			<?php $this->render_preview_updates(); ?>
			<?php $this->render_sync_from_serp(); ?>
			<?php $this->render_field_listeners(); ?>
			<?php $this->render_refresh_buttons(); ?>
			<?php $this->render_image_selection(); ?>
			<?php $this->render_ai_optimization(); ?>
			<?php $this->render_preview_all(); ?>
		});
		</script>
		<?php
	}

	/**
	 * Render character counters initialization.
	 *
	 * @return void
	 */
	private function render_character_counters_init(): void {
		?>
		// Initialize character counters
		$('.fp-seo-character-counter').each(function() {
			$(this).trigger('input');
		});
		<?php
	}

	/**
	 * Render tab switching logic.
	 *
	 * @return void
	 */
	private function render_tab_switching(): void {
		?>
		// Tab switching with enhanced UX
		$('.fp-seo-tab').on('click', function() {
			const $tab = $(this);
			const tabId = $tab.data('tab');
			const $container = $tab.closest('.fp-seo-card');
			
			// Update tab states
			$container.find('.fp-seo-tab').removeClass('fp-seo-tab-active');
			$tab.addClass('fp-seo-tab-active');
			
			// Update content states with animation
			$container.find('.fp-seo-tab-content').removeClass('fp-seo-tab-content-active');
			$container.find('#' + tabId).addClass('fp-seo-tab-content-active fp-seo-fade-in');
			
			// Trigger custom event
			$tab.trigger('fp-seo-tab-changed', [tabId]);
		});
		<?php
	}

	/**
	 * Render preview updates logic.
	 *
	 * @return void
	 */
	private function render_preview_updates(): void {
		?>
		// Real-time preview updates for social fields
		$('.fp-seo-character-counter').on('input', function() {
			const $field = $(this);
			const fieldId = $field.attr('id');
			const platform = fieldId.split('-')[2]; // Extract platform from ID
			const fieldType = fieldId.split('-')[3]; // Extract field type
			let value = $field.val();
			
			// Decode HTML entities properly (&#038; -> &, &#8211; -> –, &amp; -> &)
			// First decode numeric entities, then named entities
			value = value.replace(/&#(\d+);/g, function(match, dec) {
				return String.fromCharCode(dec);
			});
			value = value.replace(/&#x([0-9A-Fa-f]+);/g, function(match, hex) {
				return String.fromCharCode(parseInt(hex, 16));
			});
			// Decode named entities using a temporary div
			const $temp = $('<div>').html(value);
			value = $temp.text();
			
			// Update preview with decoded value
			$(`#fp-seo-${platform}-${fieldType}-preview`).text(value || '<?php echo esc_js( get_the_title() ); ?>');
		});
		<?php
	}

	/**
	 * Render sync from SERP logic.
	 *
	 * @return void
	 */
	private function render_sync_from_serp(): void {
		?>
		// Helper function to decode HTML entities
		function decodeHtmlEntities(str) {
			if (!str) return '';
			// Decode numeric entities (&#038; -> &, &#8211; -> –)
			str = str.replace(/&#(\d+);/g, function(match, dec) {
				return String.fromCharCode(dec);
			});
			// Decode hex entities (&#x26; -> &)
			str = str.replace(/&#x([0-9A-Fa-f]+);/g, function(match, hex) {
				return String.fromCharCode(parseInt(hex, 16));
			});
			// Decode named entities using a temporary div
			const $temp = $('<div>').html(str);
			return $temp.text();
		}

		// Auto-sync from SERP Optimization fields to Social Media fields
		// When SERP fields change, update social previews if social fields are empty
		function syncFromSerpToSocial() {
			// Get SERP Optimization values
			let seoTitle = $('#fp-seo-title').val() || '';
			let seoDescription = $('#fp-seo-meta-description').val() || '';
			
			// Decode HTML entities from SERP fields
			seoTitle = decodeHtmlEntities(seoTitle);
			seoDescription = decodeHtmlEntities(seoDescription);
			
			// Featured image handling removed - no longer using featured images as fallback
			let featuredImageUrl = ''; // Always empty - no featured image fallback
			
			// Update all platform previews if their fields are empty
			<?php foreach ( ImprovedSocialMediaManager::PLATFORMS as $platform_id => $platform_data ) : ?>
			let <?php echo esc_js( $platform_id ); ?>Title = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val();
			let <?php echo esc_js( $platform_id ); ?>Desc = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val();
			const <?php echo esc_js( $platform_id ); ?>Image = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image').val();
			
			// Decode HTML entities from social media fields
			<?php echo esc_js( $platform_id ); ?>Title = decodeHtmlEntities(<?php echo esc_js( $platform_id ); ?>Title);
			<?php echo esc_js( $platform_id ); ?>Desc = decodeHtmlEntities(<?php echo esc_js( $platform_id ); ?>Desc);
			
			// Update title preview if field is empty (use SERP title)
			if (!<?php echo esc_js( $platform_id ); ?>Title && seoTitle) {
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(seoTitle);
				// Also update the field value if empty (for auto-fill)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val(seoTitle);
			} else if (<?php echo esc_js( $platform_id ); ?>Title) {
				// If field has value, use it (already decoded)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(<?php echo esc_js( $platform_id ); ?>Title);
			}
			
			// Update description preview if field is empty (use SERP description)
			if (!<?php echo esc_js( $platform_id ); ?>Desc && seoDescription) {
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(seoDescription);
				// Also update the field value if empty (for auto-fill)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val(seoDescription);
			} else if (<?php echo esc_js( $platform_id ); ?>Desc) {
				// If field has value, use it (already decoded)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(<?php echo esc_js( $platform_id ); ?>Desc);
			}
			
			// Update image preview - featured image fallback removed
			const <?php echo esc_js( $platform_id ); ?>ImagePreview = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image-preview');
			if (<?php echo esc_js( $platform_id ); ?>Image) {
				// If field has value, use it
				<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', <?php echo esc_js( $platform_id ); ?>Image).attr('data-empty', 'false');
				<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'block', 'opacity': '1', 'visibility': 'visible' });
				<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').hide();
				if (<?php echo esc_js( $platform_id ); ?>ImagePreview[0]) {
					<?php echo esc_js( $platform_id ); ?>ImagePreview[0].dispatchEvent(new Event('load'));
				}
			} else {
				// No social image - hide preview and show overlay
				<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E').attr('data-empty', 'true');
				<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'none' });
				<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').show();
			}
			<?php endforeach; ?>
		}

		// Listen to SERP Optimization field changes
		$('#fp-seo-title, #fp-seo-meta-description').on('input', function() {
			syncFromSerpToSocial();
		});
		<?php
	}

	/**
	 * Render field listeners.
	 *
	 * @return void
	 */
	private function render_field_listeners(): void {
		?>
		// Listen to social field changes to update previews
		$('.fp-seo-character-counter').on('input', function() {
			const $field = $(this);
			const fieldId = $field.attr('id');
			if (fieldId && fieldId.includes('-title') || fieldId.includes('-description')) {
				const platform = fieldId.split('-')[2];
				const fieldType = fieldId.split('-')[3];
				let value = $field.val();
				
				// Decode HTML entities
				const $temp = $('<div>').html(value);
				value = $temp.text();
				
				// Update preview
				$(`#fp-seo-${platform}-${fieldType}-preview`).text(value || '');
			}
		});

		// Listen to social image field changes
		$('input[id*="-image"]').on('input', function() {
			const $field = $(this);
			const fieldId = $field.attr('id');
			if (fieldId && fieldId.includes('-image')) {
				const platform = fieldId.split('-')[2];
				const value = $field.val();
				if (value) {
					$(`#fp-seo-${platform}-image-preview`).attr('src', value);
				} else {
					// If cleared, sync from SERP
					syncFromSerpToSocial();
				}
			}
		});
		<?php
	}

	/**
	 * Render refresh buttons logic.
	 *
	 * @return void
	 */
	private function render_refresh_buttons(): void {
		?>
		// Refresh preview buttons
		$('[id^="fp-seo-refresh-preview-"]').on('click', function() {
			const $btn = $(this);
			const platformId = $btn.attr('id').replace('fp-seo-refresh-preview-', '');
			
			// Add refreshing class to show animation
			$btn.addClass('refreshing');
			
			// Refresh the preview by syncing from SERP
			syncFromSerpToSocial();
			
			// Remove refreshing class after a short delay
			setTimeout(function() {
				$btn.removeClass('refreshing');
			}, 500);
		});
		<?php
	}

	/**
	 * Render image selection logic.
	 *
	 * @return void
	 */
	private function render_image_selection(): void {
		?>
		// Image selection
		$('.fp-seo-image-select').on('click', function() {
			const $button = $(this);
			const targetField = $button.data('target');
			const previewTarget = $button.data('preview');
			
			if (typeof wp !== 'undefined' && wp.media) {
				const frame = wp.media({
					title: 'Select Social Media Image',
					button: {
						text: 'Use Image'
					},
					multiple: false
				});
				
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					$(`#${targetField}`).val(attachment.url);
					$(`#${previewTarget}`).attr('src', attachment.url);
					
					FPSeoUI.showNotification('Image updated successfully!', 'success');
				});
				
				frame.open();
			} else {
				FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
			}
		});

		// Handle "Cambia Immagine" button in live preview
		$('.fp-seo-social-preview-image-overlay button').on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			// Find the parent preview card to get the platform
			const $previewCard = $(this).closest('.fp-seo-social-preview-card');
			if (!$previewCard.length) {
				return;
			}
			
			// Extract platform from class (e.g., fp-seo-social-preview-facebook -> facebook)
			const platformMatch = $previewCard.attr('class').match(/fp-seo-social-preview-(\w+)/);
			if (!platformMatch) {
				return;
			}
			
			const platform = platformMatch[1];
			const targetField = `#fp-seo-${platform}-image`;
			const previewTarget = `#fp-seo-${platform}-image-preview`;
			
			if (typeof wp !== 'undefined' && wp.media) {
				const frame = wp.media({
					title: 'Select Social Media Image',
					button: {
						text: 'Use Image'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});
				
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					$(targetField).val(attachment.url).trigger('input');
					$(previewTarget).attr('src', attachment.url);
					
					FPSeoUI.showNotification('Image updated successfully!', 'success');
				});
				
				frame.open();
			} else {
				FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
			}
		});
		<?php
	}

	/**
	 * Render AI optimization logic.
	 *
	 * @return void
	 */
	private function render_ai_optimization(): void {
		?>
		// AI Optimization
		$('#fp-seo-optimize-all-social').on('click', function() {
			const $btn = $(this);
			const postId = <?php echo get_the_ID(); ?>;
			
			// Prevent multiple clicks
			if ($btn.prop('disabled')) {
				return;
			}
			
			FPSeoUI.showLoading($btn, '<?php echo esc_js( __( 'Ottimizzazione con AI...', 'fp-seo-performance' ) ); ?>');
			
			// Safety timeout to ensure button is always restored
			const safetyTimeout = setTimeout(function() {
				FPSeoUI.hideLoading($btn);
				FPSeoUI.showNotification('Request timeout. Please try again.', 'error');
			}, 30000); // 30 seconds timeout
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				timeout: 25000, // 25 seconds AJAX timeout
				data: {
					action: 'fp_seo_optimize_social',
					post_id: postId,
					platform: 'all',
					nonce: '<?php echo wp_create_nonce( 'fp_seo_social_nonce' ); ?>'
				},
				success: function(response) {
					clearTimeout(safetyTimeout);
					FPSeoUI.hideLoading($btn);
					
					if (response && response.success) {
						// Update all fields with AI suggestions (decode HTML entities)
						if (response.data && typeof response.data === 'object') {
							Object.keys(response.data).forEach(platform => {
								if (response.data[platform] && response.data[platform].title) {
									const decodedTitle = decodeHtmlEntities(response.data[platform].title);
									$(`#fp-seo-${platform}-title`).val(decodedTitle).trigger('input');
								}
								if (response.data[platform] && response.data[platform].description) {
									const decodedDesc = decodeHtmlEntities(response.data[platform].description);
									$(`#fp-seo-${platform}-description`).val(decodedDesc).trigger('input');
								}
							});
						}
						
						FPSeoUI.showNotification('Social media content optimized successfully!', 'success');
					} else {
						const errorMsg = (response && response.data) ? (typeof response.data === 'string' ? response.data : 'Unknown error') : 'Optimization failed';
						FPSeoUI.showNotification('Error: ' + errorMsg, 'error');
					}
				},
				error: function(xhr, status, error) {
					clearTimeout(safetyTimeout);
					FPSeoUI.hideLoading($btn);
					
					let errorMsg = 'An error occurred. Please try again.';
					if (status === 'timeout') {
						errorMsg = 'Request timeout. Please try again.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					
					FPSeoUI.showNotification(errorMsg, 'error');
				},
				complete: function() {
					// Always ensure button is restored, even if something goes wrong
					clearTimeout(safetyTimeout);
					setTimeout(function() {
						FPSeoUI.hideLoading($btn);
					}, 100);
				}
			});
		});
		<?php
	}

	/**
	 * Render preview all logic.
	 *
	 * @return void
	 */
	private function render_preview_all(): void {
		?>
		// Preview all platforms
		$('#fp-seo-preview-all-social').on('click', function() {
			// Open all platform previews in new tabs
			const platforms = ['facebook', 'twitter', 'linkedin', 'pinterest'];
			platforms.forEach(platform => {
				window.open(`#${platform}`, '_blank');
			});
		});
		<?php
	}
}


