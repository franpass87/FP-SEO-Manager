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
		// CRITICAL: Only inject scripts if we're NOT in a media library context
		// This prevents interference with WordPress core media functionality
		add_action( 'admin_footer', array( $this, 'render_all_scripts' ), 999 );
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

		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = (
			in_array( $screen->base, array( 'upload', 'media' ), true ) ||
			$screen->id === 'upload' ||
			$screen->id === 'attachment'
		);
		
		if ( $is_media_page ) {
			return;
		}

		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $supported_types, true ) ) {
			return;
		}
		
		// CRITICAL: Never run during AJAX requests to avoid interfering with media uploads
		if ( wp_doing_ajax() ) {
			return;
		}
		?>
		<script>
		window.fpSeoDebug = <?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false'; ?>;
		// CRITICAL: Wait for wp.media to be fully initialized AND WordPress core to be ready
		// This prevents interference with WordPress core media functionality
		(function($) {
			'use strict';
			
			// CRITICAL: Only initialize AFTER WordPress core has fully loaded wp.media
			// Use window load event to ensure everything is ready
			$(window).on('load', function() {
				// Additional check: wait for wp.media.featuredImage to be initialized
				function waitForWpMediaReady(callback, maxAttempts) {
					maxAttempts = maxAttempts || 100; // 10 seconds max wait
					var attempts = 0;
					
					function check() {
						attempts++;
						if (typeof wp !== 'undefined' && 
							typeof wp.media !== 'undefined' && 
							typeof wp.media.controller !== 'undefined' &&
							typeof wp.media.featuredImage !== 'undefined' &&
							typeof wp.media.featuredImage.init === 'function') {
							// wp.media is fully initialized, wait a bit more to be safe
							setTimeout(callback, 200);
						} else if (attempts < maxAttempts) {
							setTimeout(check, 100);
						} else {
							// wp.media not fully available, but proceed anyway
							if (window.fpSeoDebug) console.warn('FP SEO: wp.media not fully initialized after waiting, proceeding anyway');
							setTimeout(callback, 500);
						}
					}
					
					check();
				}
				
				// Wait for wp.media to be fully ready before initializing
				waitForWpMediaReady(function() {
					// Use document ready to ensure DOM is ready
					$(document).ready(function($) {
						// CRITICAL: Wrap all code in try-catch to prevent errors from blocking wp.media
						try {
							<?php $this->render_character_counters_init(); ?>
							<?php $this->render_tab_switching(); ?>
							<?php $this->render_preview_updates(); ?>
							<?php $this->render_sync_from_serp(); ?>
							<?php $this->render_field_listeners(); ?>
							<?php $this->render_refresh_buttons(); ?>
							<?php $this->render_image_selection(); ?>
							<?php $this->render_ai_optimization(); ?>
							<?php $this->render_preview_all(); ?>
						} catch (error) {
							// Log error but don't block execution
							if (window.fpSeoDebug) console.error('FP SEO: Error initializing social scripts:', error);
						}
					}); // End document.ready
				}); // End waitForWpMediaReady callback
			}); // End window.load
		})(jQuery);
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
		// Real-time preview updates and character counter for social fields
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
			
			// Update character counter
			const maxLength = parseInt($field.attr('maxlength')) || 0;
			const currentLength = value.length;
			const $counter = $(`#fp-seo-${platform}-${fieldType}-count`);
			if ($counter.length) {
				$counter.text(`${currentLength}/${maxLength}`);
			}
			
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
			
			// Update title preview and field if empty (use SERP title)
			if (!<?php echo esc_js( $platform_id ); ?>Title || <?php echo esc_js( $platform_id ); ?>Title.trim() === '') {
				if (seoTitle) {
					// Update both preview and field value
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(seoTitle);
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val(seoTitle).trigger('input');
				} else {
					// No SEO title, use post title as fallback
					const postTitle = $('input[name="post_title"]').val() || $('#title').val() || '';
					if (postTitle) {
						$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(postTitle);
						$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').val(postTitle).trigger('input');
					}
				}
			} else {
				// Field has value, use it (already decoded)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title-preview').text(<?php echo esc_js( $platform_id ); ?>Title);
				// Trigger input to update character counter
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-title').trigger('input');
			}
			
			// Update description preview and field if empty (use SERP description)
			if (!<?php echo esc_js( $platform_id ); ?>Desc || <?php echo esc_js( $platform_id ); ?>Desc.trim() === '') {
				if (seoDescription) {
					// Update both preview and field value
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(seoDescription);
					$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val(seoDescription).trigger('input');
				} else {
					// No SEO description, try to get excerpt
					const excerpt = $('#excerpt').val() || '';
					if (excerpt) {
						$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(excerpt);
						$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').val(excerpt).trigger('input');
					}
				}
			} else {
				// Field has value, use it (already decoded)
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description-preview').text(<?php echo esc_js( $platform_id ); ?>Desc);
				// Trigger input to update character counter
				$('#fp-seo-<?php echo esc_js( $platform_id ); ?>-description').trigger('input');
			}
			
			// Update image preview - use featured image as fallback if no social image is set
			const <?php echo esc_js( $platform_id ); ?>ImagePreview = $('#fp-seo-<?php echo esc_js( $platform_id ); ?>-image-preview');
			if (<?php echo esc_js( $platform_id ); ?>Image && <?php echo esc_js( $platform_id ); ?>Image.trim() !== '') {
				// If field has value, use it
				<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', <?php echo esc_js( $platform_id ); ?>Image).attr('data-empty', 'false');
				<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'block', 'opacity': '1', 'visibility': 'visible' });
				<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').hide();
				if (<?php echo esc_js( $platform_id ); ?>ImagePreview[0]) {
					<?php echo esc_js( $platform_id ); ?>ImagePreview[0].dispatchEvent(new Event('load'));
				}
			} else {
				// No social image - try to use featured image
				const thumbnailInput = document.querySelector('#_thumbnail_id');
				const thumbnailId = thumbnailInput ? thumbnailInput.value : null;
				
				if (thumbnailId && thumbnailId !== '-1' && thumbnailId !== '0') {
					// Get featured image URL via AJAX
					$.post(ajaxurl, {
						action: 'fp_seo_get_featured_image_url',
						thumbnail_id: thumbnailId,
						nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_social_nonce' ) ); ?>'
					}, function(response) {
						if (response && response.success && response.data && response.data.url) {
							<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', response.data.url).attr('data-empty', 'false');
							<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'block', 'opacity': '1', 'visibility': 'visible' });
							<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').hide();
							if (<?php echo esc_js( $platform_id ); ?>ImagePreview[0]) {
								<?php echo esc_js( $platform_id ); ?>ImagePreview[0].dispatchEvent(new Event('load'));
							}
						} else {
							// No featured image either - hide preview and show overlay
							<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E').attr('data-empty', 'true');
							<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'none' });
							<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').show();
						}
					}).fail(function() {
						// AJAX failed - hide preview and show overlay
						<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E').attr('data-empty', 'true');
						<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'none' });
						<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').show();
					});
				} else {
					// No featured image - hide preview and show overlay
					<?php echo esc_js( $platform_id ); ?>ImagePreview.attr('src', 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E').attr('data-empty', 'true');
					<?php echo esc_js( $platform_id ); ?>ImagePreview.css({ 'display': 'none' });
					<?php echo esc_js( $platform_id ); ?>ImagePreview.next('.fp-seo-social-preview-image-overlay').show();
				}
			}
			<?php endforeach; ?>
		}

		// Listen to SERP Optimization field changes
		$('#fp-seo-title, #fp-seo-meta-description').on('input', function() {
			syncFromSerpToSocial();
		});

		// Also listen to change events (for paste, autocomplete, etc.)
		$('#fp-seo-title, #fp-seo-meta-description').on('change paste', function() {
			setTimeout(syncFromSerpToSocial, 100);
		});

		// CRITICAL: Initialize social previews with SEO values on page load
		// This ensures that when the page loads, if social fields are empty,
		// they are automatically pre-filled with SEO values
		$(document).ready(function() {
			// Wait a bit for all fields to be initialized
			setTimeout(function() {
				syncFromSerpToSocial();
			}, 500);
		});

		// REMOVED: All listeners and observers on featured image (_thumbnail_id) and #postimagediv
		// These were causing interference with WordPress core featured image functionality
		// Social previews will only update when user manually changes social image fields
		<?php
	}

	/**
	 * Render field listeners.
	 *
	 * @return void
	 */
	private function render_field_listeners(): void {
		?>
		// Listen to social field changes to update previews and character counters
		$('.fp-seo-character-counter').on('input', function() {
			const $field = $(this);
			const fieldId = $field.attr('id');
			if (fieldId && (fieldId.includes('-title') || fieldId.includes('-description'))) {
				const platform = fieldId.split('-')[2];
				const fieldType = fieldId.split('-')[3];
				let value = $field.val();
				
				// Decode HTML entities
				const $temp = $('<div>').html(value);
				value = $temp.text();
				
				// Update character counter
				const maxLength = parseInt($field.attr('maxlength')) || 0;
				const currentLength = value.length;
				const $counter = $(`#fp-seo-${platform}-${fieldType}-count`);
				if ($counter.length) {
					$counter.text(`${currentLength}/${maxLength}`);
				}
				
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
		// Image selection - lazy load wp.media only when needed
		// Handle both .fp-seo-image-select (legacy) and .fp-seo-media-button (current)
		$('.fp-seo-image-select, .fp-seo-media-button').on('click', function() {
			const $button = $(this);
			let targetField = $button.data('target');
			let previewTarget = $button.data('preview');
			let platform = $button.data('platform') || 'social';
			
			// If target/preview not provided via data attributes, construct from platform
			if (!targetField || !previewTarget) {
				if (platform) {
					targetField = `fp-seo-${platform}-image`;
					previewTarget = `fp-seo-${platform}-image-preview`;
				}
			}
			
			// Load wp.media lazy only when button is clicked (not globally)
			if (typeof wp === 'undefined' || !wp.media) {
				// Load wp.media script dynamically if not already loaded
				if (typeof ajaxurl !== 'undefined') {
					$.getScript(ajaxurl.replace('admin-ajax.php', 'wp-includes/js/media-editor.min.js'), function() {
						openMediaFrame(targetField, previewTarget, platform);
					}).fail(function() {
						FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
					});
				} else {
					FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
				}
				return;
			}
			
			openMediaFrame(targetField, previewTarget, platform);
			
			function openMediaFrame(targetField, previewTarget, platform) {
				if (typeof wp === 'undefined' || !wp.media) {
					FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
					return;
				}
				
				// CRITICAL: Create completely isolated wp.media frame with unique ID to prevent interference
				// This frame must NOT interfere with WordPress core featured image metabox or media library
				const frameId = 'fp-seo-social-' + (platform || 'social') + '-' + Date.now();
				const frame = wp.media({
					id: frameId,  // Unique ID to prevent conflicts with WordPress core
					title: 'Select Social Media Image',
					button: {
						text: 'Use Image'
					},
					multiple: false,
					library: {
						type: 'image'  // Only allow images
					},
					states: [
						new wp.media.controller.Library({
							library: wp.media.query({ type: 'image' }),
							multiple: false,
							title: 'Select Social Media Image',
							priority: 20,
							filterable: 'uploaded',
							sortable: 'date'
						})
					]
				});
				
				// Handle selection - completely isolated from WordPress core
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					
					// Validate that the selected attachment is an image
					if (!attachment.type || attachment.type !== 'image') {
						FPSeoUI.showNotification('Please select an image file.', 'error');
						frame.close();
						frame.detach();
						return;
					}
					
					$(`#${targetField}`).val(attachment.url);
					$(`#${previewTarget}`).attr('src', attachment.url);
					
					// Close and detach immediately to prevent any interference
					frame.close();
					frame.detach();
					
					FPSeoUI.showNotification('Image updated successfully!', 'success');
				});
				
				// Clean up on close to prevent memory leaks and interference
				frame.on('close', function() {
					frame.off('select');
					frame.off('close');
					frame.detach();
				});
				
				frame.open();
			}
		});

		// Handle "Cambia Immagine" button in live preview - lazy load wp.media only when needed
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
			
			// Load wp.media lazy only when button is clicked (not globally)
			if (typeof wp === 'undefined' || !wp.media) {
				// WordPress should have wp.media available, but if not, show error
				FPSeoUI.showNotification('Media library not available. Please refresh the page.', 'error');
				return;
			}
			
			// CRITICAL: Create completely isolated wp.media frame with unique ID to prevent interference
			// This frame must NOT interfere with WordPress core featured image metabox or media library
			const frameId = 'fp-seo-social-preview-' + platform + '-' + Date.now();
			const frame = wp.media({
				id: frameId,  // Unique ID to prevent conflicts with WordPress core
				title: 'Select Social Media Image',
				button: {
					text: 'Use Image'
				},
				multiple: false,
				library: {
					type: 'image'  // Only allow images
				},
				states: [
					new wp.media.controller.Library({
						library: wp.media.query({ type: 'image' }),
						multiple: false,
						title: 'Select Social Media Image',
						priority: 20,
						filterable: 'uploaded',
						sortable: 'date'
					})
				]
			});
			
			// Handle selection - completely isolated from WordPress core
			frame.on('select', function() {
				const attachment = frame.state().get('selection').first().toJSON();
				
				// Validate that the selected attachment is an image
				if (!attachment.type || attachment.type !== 'image') {
					FPSeoUI.showNotification('Please select an image file.', 'error');
					frame.close();
					frame.detach();
					return;
				}
				
				$(targetField).val(attachment.url).trigger('input');
				$(previewTarget).attr('src', attachment.url);
				
				// Close and detach immediately to prevent any interference
				frame.close();
				frame.detach();
				
				FPSeoUI.showNotification('Image updated successfully!', 'success');
			});
			
			// Clean up on close to prevent memory leaks and interference
			frame.on('close', function() {
				frame.off('select');
				frame.off('close');
				frame.detach();
			});
			
			frame.open();
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
			const postId = <?php echo (int) get_the_ID(); ?>;
			
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
									// Update preview
									$(`#fp-seo-${platform}-title-preview`).text(decodedTitle);
								}
								if (response.data[platform] && response.data[platform].description) {
									const decodedDesc = decodeHtmlEntities(response.data[platform].description);
									$(`#fp-seo-${platform}-description`).val(decodedDesc).trigger('input');
									// Update preview
									$(`#fp-seo-${platform}-description-preview`).text(decodedDesc);
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
















