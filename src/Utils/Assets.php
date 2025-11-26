<?php
/**
 * Asset registration utilities.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare( strict_types=1 );

namespace FP\SEO\Utils;

use function add_action;
use function plugins_url;
use function wp_add_inline_script;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_register_script;
use function wp_register_style;
use function wp_script_is;
use function wp_style_is;

/**
 * Handles registration of plugin assets.
 */
class Assets {

	/**
	 * Hooks asset registration into admin requests.
	 */
	public function register(): void {
		// Only register assets in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		// Register type="module" filter FIRST (before any script registration)
		add_filter( 'script_loader_tag', array( $this, 'add_type_module' ), 10, 3 );
		
		// Also add hooks as backup
		add_action( 'admin_init', array( $this, 'register_admin_assets' ), 10, 0 );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'ensure_admin_handles' ), 5, 0 );
		
		// Add conditional loading for specific pages
		add_action( 'admin_enqueue_scripts', array( $this, 'conditional_asset_loading' ), 15, 0 );
		
		// Add early wp.media handler (runs in head) - use high priority to run early
		// Only register once to avoid duplicates
		add_action( 'admin_head', array( $this, 'ensure_wp_media_early' ), 5, 0 );
	}

	/**
	 * Registers admin asset handles early in the request.
	 */
	public function register_admin_assets(): void {
		$this->register_handles();
	}

	/**
	 * Ensures wp.media is loaded early for featured image button support.
	 * This must run before other plugins to prevent conflicts.
	 */
	public function ensure_wp_media(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only enqueue on post editor pages where featured image is used
		$is_post_editor = in_array( $screen->base, array( 'post', 'page' ), true );
		
		if ( $is_post_editor ) {
			// Ensure wp.media is available for featured image button (modern WordPress API)
			// Must be called early, before other scripts
			wp_enqueue_media();
			
			// Also ensure set-post-thumbnail script is loaded (WordPress core script for featured image)
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'set-post-thumbnail' );
			}
			
			// Force load image-edit script if needed
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'image-edit' );
			}
			
			// Ensure all dependencies are loaded
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'underscore' );
			wp_enqueue_script( 'backbone' );
		}
	}

	/**
	 * Syncs featured image to social media fields when featured image is set.
	 * WordPress handles the featured image UI completely, we just sync it to social fields.
	 */
	public function ensure_wp_media_early(): void {
		// Prevent duplicate execution
		static $executed = false;
		if ( $executed ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only on post editor pages
		$is_post_editor = in_array( $screen->base, array( 'post', 'page' ), true );
		
		if ( $is_post_editor ) {
			$executed = true;
			?>
			<script type="text/javascript">
			(function() {
				// Run as soon as jQuery is available
				if (typeof jQuery !== 'undefined') {
					jQuery(function($) {
						// NON interferire con wp.media - WordPress gestisce tutto automaticamente
						// Il nostro codice si limita solo a sincronizzare l'immagine in evidenza ai campi social
						// NON intercettare Modal.prototype.open o altri metodi wp.media - lascia che WordPress gestisca tutto
						
						// Function to sync featured image to social media fields
						var syncFeaturedImageToSocial = function() {
							var thumbnailId = $('input[name="_thumbnail_id"]').val();
							
							// Check if featured image is set (not empty, not "0", not "-1")
							if (thumbnailId && parseInt(thumbnailId, 10) > 0) {
								// Get featured image URL
								var $featuredImg = $('#postimagediv .inside img');
								var featuredUrl = '';
								
								if ($featuredImg.length > 0) {
									featuredUrl = $featuredImg.attr('src');
								} else {
									// Try to get from attachment
									if (typeof wp !== 'undefined' && wp.media && wp.media.attachment) {
										var attachment = wp.media.attachment(thumbnailId);
										attachment.fetch().done(function() {
											var url = attachment.get('url');
											if (url) {
												setSocialImageFields(url);
											}
										});
										return; // Exit early, will be set in callback
									}
								}
								
								if (featuredUrl) {
									setSocialImageFields(featuredUrl);
								}
							}
						};
						
						// Function to set social image fields
						var setSocialImageFields = function(imageUrl) {
							// Set Facebook image if field exists and is empty
							var $facebookImage = $('#fp-seo-facebook-image');
							if ($facebookImage.length > 0 && !$facebookImage.val()) {
								$facebookImage.val(imageUrl).trigger('input');
							}
							
							// Set Twitter image if field exists and is empty
							var $twitterImage = $('#fp-seo-twitter-image');
							if ($twitterImage.length > 0 && !$twitterImage.val()) {
								$twitterImage.val(imageUrl).trigger('input');
							}
							
							// Set LinkedIn image if field exists and is empty
							var $linkedinImage = $('#fp-seo-linkedin-image');
							if ($linkedinImage.length > 0 && !$linkedinImage.val()) {
								$linkedinImage.val(imageUrl).trigger('input');
							}
							
							// Set Pinterest image if field exists and is empty
							var $pinterestImage = $('#fp-seo-pinterest-image');
							if ($pinterestImage.length > 0 && !$pinterestImage.val()) {
								$pinterestImage.val(imageUrl).trigger('input');
							}
						};
						
						// Listen for changes to featured image input
						$('input[name="_thumbnail_id"]').on('change', function() {
							setTimeout(syncFeaturedImageToSocial, 500); // Wait for WordPress to update metabox
						});
						
						// Listen for WordPress's featured image set event (standard WordPress event)
						$(document).on('wp-set-post-thumbnail', function(event, thumbnailId) {
							if (thumbnailId && parseInt(thumbnailId, 10) > 0) {
								setTimeout(syncFeaturedImageToSocial, 500);
							}
						});
						
						// Listen for AJAX completion to sync after WordPress updates metabox
						// This is a safe way to detect when featured image is updated via AJAX
						$(document).ajaxComplete(function(event, xhr, settings) {
							if (settings && settings.data && typeof settings.data === 'string') {
								// Check if this is a featured image update
								if (settings.data.indexOf('action=set-post-thumbnail') !== -1 || 
								    settings.data.indexOf('action=get-post-thumbnail-html') !== -1) {
									// Featured image was updated, sync to social fields after metabox updates
									setTimeout(function() {
										syncFeaturedImageToSocial();
									}, 1000);
								}
							}
						});
						
						// Initial sync on page load
						setTimeout(syncFeaturedImageToSocial, 1000);
					});
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Backup sync in footer (runs after all scripts are loaded).
	 * Ensures sync works even if loaded late.
	 * NOTE: This is kept as a minimal backup, but ensure_wp_media_early should handle everything.
	 */
	public function ensure_wp_media_footer(): void {
		// Prevent duplicate execution
		static $executed = false;
		if ( $executed ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only on post editor pages
		$is_post_editor = in_array( $screen->base, array( 'post', 'page' ), true );
		
		if ( $is_post_editor ) {
			$executed = true;
			?>
			<script type="text/javascript">
			(function() {
				// Backup sync - just sync featured image to social fields
				if (typeof jQuery !== 'undefined') {
					jQuery(function($) {
						// Function to sync featured image to social media fields
						var syncFeaturedImageToSocial = function() {
							var thumbnailId = $('input[name="_thumbnail_id"]').val();
							
							// Check if featured image is set (not empty, not "0", not "-1")
							if (thumbnailId && parseInt(thumbnailId, 10) > 0) {
								// Get featured image URL from metabox
								var $featuredImg = $('#postimagediv .inside img');
								var featuredUrl = '';
								
								if ($featuredImg.length > 0) {
									featuredUrl = $featuredImg.attr('src');
								}
								
								if (featuredUrl) {
									// Set social image fields if they are empty
									var $facebookImage = $('#fp-seo-facebook-image');
									if ($facebookImage.length > 0 && !$facebookImage.val()) {
										$facebookImage.val(featuredUrl).trigger('input');
									}
									
									var $twitterImage = $('#fp-seo-twitter-image');
									if ($twitterImage.length > 0 && !$twitterImage.val()) {
										$twitterImage.val(featuredUrl).trigger('input');
									}
									
									var $linkedinImage = $('#fp-seo-linkedin-image');
									if ($linkedinImage.length > 0 && !$linkedinImage.val()) {
										$linkedinImage.val(featuredUrl).trigger('input');
									}
									
									var $pinterestImage = $('#fp-seo-pinterest-image');
									if ($pinterestImage.length > 0 && !$pinterestImage.val()) {
										$pinterestImage.val(featuredUrl).trigger('input');
									}
								}
							}
						};
						
						// Listen for AJAX completion (WordPress uses AJAX to set featured image)
						$(document).ajaxComplete(function(event, xhr, settings) {
							if (settings && settings.data && typeof settings.data === 'string') {
								// Check if this is a featured image update
								if (settings.data.indexOf('action=set-post-thumbnail') !== -1) {
									// Featured image was updated, sync to social fields
									setTimeout(syncFeaturedImageToSocial, 500);
								}
							}
						});
						
						// Initial sync on page load
						setTimeout(syncFeaturedImageToSocial, 1500);
					});
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Ensures admin handles exist before other callbacks enqueue them.
	 */
	public function ensure_admin_handles(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only enqueue on post editor pages where featured image is used
		$is_post_editor = in_array( $screen->base, array( 'post', 'page' ), true );
		
		if ( $is_post_editor ) {
			// Ensure wp.media is loaded early for featured image button
			$this->ensure_wp_media();
		}
		
		if ( $this->handles_registered() ) {
			return;
		}

		$this->register_handles();
	}

	/**
	 * Conditionally loads assets based on current admin page.
	 */
	public function conditional_asset_loading(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Only load on FP SEO pages or post editor
		$fp_seo_pages = array(
			'toplevel_page_fp-seo-performance',
			'fp-seo-performance_page_fp-seo-performance-bulk',
			'seo-performance_page_fp-seo-performance-bulk',
			'fp-seo-performance_page_fp-seo-bulk-audit',
			'toplevel_page_fp-seo-test-suite',
			'fp-seo-performance_page_fp-seo-social-media',
			'fp-seo-performance_page_fp-seo-internal-links',
			'fp-seo-performance_page_fp-seo-multiple-keywords',
		);

		$is_fp_seo_page = in_array( $screen->id, $fp_seo_pages, true );
		$is_post_editor = in_array( $screen->base, array( 'post', 'page' ), true );

		// Enqueue UI system assets ONLY on FP SEO pages and post editor
		if ( $is_fp_seo_page || $is_post_editor ) {
			wp_enqueue_style( 'fp-seo-ui-system' );
			wp_enqueue_style( 'fp-seo-notifications' );
			wp_enqueue_style( 'fp-seo-ai-enhancements' );
			wp_enqueue_script( 'fp-seo-ui-system' );
		}

		// Dequeue heavy assets if not on FP SEO pages or post editor
		if ( ! $is_fp_seo_page && ! $is_post_editor ) {
			wp_dequeue_style( 'fp-seo-ui-system' );
			wp_dequeue_style( 'fp-seo-notifications' );
			wp_dequeue_style( 'fp-seo-ai-enhancements' );
			wp_dequeue_script( 'fp-seo-ui-system' );
			wp_dequeue_script( 'fp-seo-performance-bulk' );
			wp_dequeue_script( 'fp-seo-performance-ai-generator' );
			wp_dequeue_script( 'fp-seo-performance-serp-preview' );
		}
	}

	/**
	 * Registers asset handles used across admin screens.
	 */
	private function register_handles(): void {
		$version = $this->asset_version();

		// UI System CSS (load first)
		wp_register_style(
			'fp-seo-ui-system',
			plugins_url( 'assets/admin/css/fp-seo-ui-system.css', FP_SEO_PERFORMANCE_FILE ),
			array(),
			$version
		);

		// Notifications CSS
		wp_register_style(
			'fp-seo-notifications',
			plugins_url( 'assets/admin/css/fp-seo-notifications.css', FP_SEO_PERFORMANCE_FILE ),
			array( 'fp-seo-ui-system' ),
			$version
		);

	// AI Enhancements CSS (ultra-light, < 3KB)
	wp_register_style(
		'fp-seo-ai-enhancements',
		plugins_url( 'assets/admin/css/components/ai-enhancements.css', FP_SEO_PERFORMANCE_FILE ),
		array(),
		$version
	);

	wp_register_style(
		'fp-seo-performance-admin',
		plugins_url( 'assets/admin/css/admin.css', FP_SEO_PERFORMANCE_FILE ),
			array( 'fp-seo-ui-system' ),
			$version
		);

		// UI System JavaScript (load first)
		wp_register_script(
			'fp-seo-ui-system',
			plugins_url( 'assets/admin/js/fp-seo-ui-system.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_register_script(
			'fp-seo-performance-admin',
			plugins_url( 'assets/admin/js/admin.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery', 'fp-seo-ui-system' ),
			$version,
			true
		);

		// Editor metabox - Legacy version (senza ES6 modules per compatibility)
		wp_register_script(
			'fp-seo-performance-editor',
			plugins_url( 'assets/admin/js/editor-metabox-legacy.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			$version,
			true
		);
		
		// Editor metabox - ES6 version (backup, se serve)
		wp_register_script(
			'fp-seo-performance-editor-modern',
			plugins_url( 'assets/admin/js/editor-metabox.js', FP_SEO_PERFORMANCE_FILE ),
			array(),
			$version,
			true
		);

		wp_register_script(
			'fp-seo-performance-bulk',
			plugins_url( 'assets/admin/js/bulk-auditor.js', FP_SEO_PERFORMANCE_FILE ),
			array(),
			$version,
			true
		);

		wp_register_script(
			'fp-seo-performance-serp-preview',
			plugins_url( 'assets/admin/js/serp-preview.js', FP_SEO_PERFORMANCE_FILE ),
			array(),
			$version,
			true
		);

		wp_register_script(
			'fp-seo-performance-ai-generator',
			plugins_url( 'assets/admin/js/ai-generator.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			$version,
			true
		);

		wp_register_script(
			'fp-seo-performance-metabox-ai-fields',
			plugins_url( 'assets/admin/js/metabox-ai-fields.js', FP_SEO_PERFORMANCE_FILE ),
			array( 'jquery' ),
			$version,
			true
		);
	}

	/**
	 * Determines whether all admin handles are registered.
	 */
	private function handles_registered(): bool {
		return wp_style_is( 'fp-seo-ui-system', 'registered' )
			&& wp_style_is( 'fp-seo-notifications', 'registered' )
			&& wp_style_is( 'fp-seo-performance-admin', 'registered' )
			&& wp_script_is( 'fp-seo-ui-system', 'registered' )
			&& wp_script_is( 'fp-seo-performance-admin', 'registered' )
			&& wp_script_is( 'fp-seo-performance-editor', 'registered' )
			&& wp_script_is( 'fp-seo-performance-bulk', 'registered' )
			&& wp_script_is( 'fp-seo-performance-ai-generator', 'registered' );
	}

	/**
	 * Adds type="module" attribute to ES6 module scripts.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified script tag.
	 */
	public function add_type_module( string $tag, string $handle, string $src ): string {
		$module_handles = array(
			'fp-seo-performance-editor-modern', // Solo la versione modern usa modules
			'fp-seo-performance-bulk',
		);

		if ( in_array( $handle, $module_handles, true ) ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}

		return $tag;
	}

	/**
	 * Resolve the version string used for asset registration.
	 */
	private function asset_version(): string {
		if ( defined( 'FP_SEO_PERFORMANCE_VERSION' ) && '' !== FP_SEO_PERFORMANCE_VERSION ) {
			// Use file modification time for cache busting only when needed
			$file_path = dirname( FP_SEO_PERFORMANCE_FILE ) . '/assets/admin/css/admin.css';
			$file_time = file_exists( $file_path ) ? filemtime( $file_path ) : time();
			
			// Only add timestamp in development mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return FP_SEO_PERFORMANCE_VERSION . '-' . $file_time;
			}
			
			return FP_SEO_PERFORMANCE_VERSION;
		}

		return '0.1.0';
	}
}
