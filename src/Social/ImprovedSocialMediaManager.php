<?php
/**
 * Improved Social Media Manager with Enhanced UI/UX
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social;

use FP\SEO\Social\Renderers\SocialFieldsRenderer;
use FP\SEO\Utils\Cache;
use FP\SEO\Utils\MetadataResolver;
use FP\SEO\Utils\PerformanceConfig;
use FP\SEO\Utils\WPBakeryContentExtractor;
use function get_permalink;
use function get_post;
use function get_post_field;
use function get_queried_object_id;
use function get_the_excerpt;
use function strip_shortcodes;
use function do_shortcode;
use function get_the_post_thumbnail_url;
use function get_the_title;
use function is_singular;
use function trim;
use function wp_get_attachment_url;
use function wp_strip_all_tags;
use function wp_trim_words;

/**
 * Enhanced Social Media Manager with improved UI/UX.
 */
class ImprovedSocialMediaManager {

	/**
	 * @var SocialFieldsRenderer|null
	 */
	private $renderer;

	/**
	 * @var \FP\SEO\Social\Renderers\SocialPageRenderer|null
	 */
	private $page_renderer;

	/**
	 * @var \FP\SEO\Social\Handlers\SocialAjaxHandler|null
	 */
	private $ajax_handler;

	/**
	 * @var \FP\SEO\Social\Output\SocialMetaTagsOutput|null
	 */
	private $meta_tags_output;

	/**
	 * @var \FP\SEO\Social\Styles\SocialStylesManager|null
	 */
	private $styles_manager;

	/**
	 * @var \FP\SEO\Social\Scripts\SocialScriptsManager|null
	 */
	private $scripts_manager;

	/**
	 * Supported social platforms.
	 */
	public const PLATFORMS = array(
		'facebook' => array(
			'name' => 'Facebook',
			'icon' => '📘',
			'color' => '#1877f2',
			'title_limit' => 60,
			'description_limit' => 160
		),
		'twitter' => array(
			'name' => 'Twitter',
			'icon' => '🐦',
			'color' => '#1da1f2',
			'title_limit' => 70,
			'description_limit' => 200
		),
		'linkedin' => array(
			'name' => 'LinkedIn',
			'icon' => '💼',
			'color' => '#0077b5',
			'title_limit' => 60,
			'description_limit' => 160
		),
		'pinterest' => array(
			'name' => 'Pinterest',
			'icon' => '📌',
			'color' => '#bd081c',
			'title_limit' => 60,
			'description_limit' => 160
		)
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Initialize renderer
		if ( class_exists( 'FP\SEO\Social\Renderers\SocialFieldsRenderer' ) ) {
			try {
				$this->renderer = new \FP\SEO\Social\Renderers\SocialFieldsRenderer( $this );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialFieldsRenderer', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		}

		// Initialize AJAX handler
		if ( class_exists( 'FP\SEO\Social\Handlers\SocialAjaxHandler' ) ) {
			try {
				$this->ajax_handler = new \FP\SEO\Social\Handlers\SocialAjaxHandler( $this );
				$this->ajax_handler->register();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialAjaxHandler', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				// Fallback to original methods
				add_action( 'wp_ajax_fp_seo_preview_social', array( $this, 'ajax_preview_social' ) );
				add_action( 'wp_ajax_fp_seo_optimize_social', array( $this, 'ajax_optimize_social' ) );
			}
		} else {
			// Fallback to original methods
			add_action( 'wp_ajax_fp_seo_preview_social', array( $this, 'ajax_preview_social' ) );
			add_action( 'wp_ajax_fp_seo_optimize_social', array( $this, 'ajax_optimize_social' ) );
		}

		// Initialize meta tags output
		if ( class_exists( 'FP\SEO\Social\Output\SocialMetaTagsOutput' ) ) {
			try {
				$this->meta_tags_output = new \FP\SEO\Social\Output\SocialMetaTagsOutput();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialMetaTagsOutput', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		}

		// Initialize styles manager
		if ( class_exists( 'FP\SEO\Social\Styles\SocialStylesManager' ) ) {
			try {
				$this->styles_manager = new \FP\SEO\Social\Styles\SocialStylesManager( $this );
				$this->styles_manager->register_hooks();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialStylesManager', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		}

		// Initialize scripts manager
		if ( class_exists( 'FP\SEO\Social\Scripts\SocialScriptsManager' ) ) {
			try {
				$this->scripts_manager = new \FP\SEO\Social\Scripts\SocialScriptsManager( $this );
				$this->scripts_manager->register_hooks();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialScriptsManager', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		}

		// Initialize page renderer
		if ( class_exists( 'FP\SEO\Social\Renderers\SocialPageRenderer' ) ) {
			try {
				$this->page_renderer = new \FP\SEO\Social\Renderers\SocialPageRenderer( $this );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to initialize SocialPageRenderer', array(
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
			}
		}

		add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
		add_action( 'admin_menu', array( $this, 'add_social_menu' ) );
		// ajax_get_attachment_url removed - no longer handling attachments
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_social_metabox' ) );
		
		// CRITICAL: Register hooks ONLY for supported post types to prevent ANY interference
		// This is more efficient than registering generic hooks and exiting early
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		foreach ( $supported_types as $post_type ) {
			if ( ! has_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ) ) ) {
				add_action( 'save_post_' . $post_type, array( $this, 'save_social_meta' ), 10, 1 );
			}
		}
		
		// Use priority 5 to ensure wp.media is loaded early, before other plugins
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 5 );
	}

	/**
	 * Enqueue assets for social media manager.
	 */
	public function enqueue_assets(): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = in_array( $screen->base, array( 'upload', 'media' ), true ) || $screen->id === 'upload';
		if ( $is_media_page ) {
			return;
		}

		$is_fp_seo_page = strpos( $screen->id, 'fp-seo' ) !== false;
		$is_post_editor = in_array( $screen->id, array( 'post', 'page' ), true );

		if ( $is_fp_seo_page || $is_post_editor ) {
			// Ensure wp.media is available for image uploads (including featured image)
			// This must be called early to support WordPress core featured image button
			wp_enqueue_media();
			
			// Also ensure set-post-thumbnail script is loaded (required for featured image button)
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'set-post-thumbnail' );
			}
			
			wp_enqueue_style( 'fp-seo-ui-system' );
			wp_enqueue_style( 'fp-seo-notifications' );
			wp_enqueue_script( 'fp-seo-ui-system' );
		}
	}

	/**
	 * Add Social Media menu to admin.
	 */
	public function add_social_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Social Media', 'fp-seo-performance' ),
			__( 'Social Media', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-social-media',
			array( $this, 'render_social_page' )
		);
	}

	/**
	 * Add social media metabox to post editor.
	 */
	public function add_social_metabox(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fp_seo_social_media_improved',
				__( 'Social Media Preview', 'fp-seo-performance' ),
				array( $this, 'render_improved_social_metabox' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render improved social media metabox with enhanced UI.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_improved_social_metabox( $post ): void {
		try {
			// Validate post object
			if ( ! $post || ! isset( $post->ID ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Invalid post object in render_improved_social_metabox', array(
						'post' => is_object( $post ) ? get_class( $post ) : gettype( $post ),
					) );
				}
				return;
			}

			$social_meta = $this->get_social_meta( $post->ID );
			$preview_data = $this->get_preview_data( $post );
			
			wp_nonce_field( 'fp_seo_social_meta', 'fp_seo_social_nonce' );

			// Use modular renderer if available
			if ( $this->renderer ) {
				$this->renderer->render( $post, $preview_data, $social_meta );
				return;
			}

			// Fallback message if renderer is not available
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Impossibile caricare la sezione Social Media. I campi SEO verranno comunque salvati.', 'fp-seo-performance' );
			echo '</p></div>';
		} catch ( \Throwable $e ) {
			// Log error but don't break the page
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Error initializing social metabox', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'post_id' => isset( $post->ID ) ? $post->ID : 0,
				) );
			}
			// Show fallback message
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Impossibile caricare la sezione Social Media. I campi SEO verranno comunque salvati.', 'fp-seo-performance' );
			echo '</p></div>';
		}
		// JavaScript is now handled by SocialScriptsManager
		// The inline code has been extracted to separate classes for better modularity
	}

	/**
	 * Get social meta data for post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	/**
	 * Get social meta data for post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_social_meta( int $post_id ): array {
		try {
			// Clear cache before retrieving
			clean_post_cache( $post_id );
			wp_cache_delete( $post_id, 'post_meta' );
			wp_cache_delete( $post_id, 'posts' );
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'post_meta' );
			}
			if ( function_exists( 'update_post_meta_cache' ) ) {
				update_post_meta_cache( array( $post_id ) );
			}
			
			$cache_key = 'fp_seo_social_meta_' . $post_id;
			
			return Cache::remember( $cache_key, function() use ( $post_id ) {
				try {
					$meta = get_post_meta( $post_id, '_fp_seo_social_meta', true );
					
					// Fallback: query diretta al database se get_post_meta restituisce vuoto
					if ( empty( $meta ) ) {
						global $wpdb;
						if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
							$db_value = $wpdb->get_var( $wpdb->prepare( 
								"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", 
								$post_id, 
								'_fp_seo_social_meta' 
							) );
							if ( $db_value !== null ) {
								$unserialized = maybe_unserialize( $db_value );
								$meta = is_array( $unserialized ) ? $unserialized : array();
							}
						}
					}
					
					return is_array( $meta ) ? $meta : array();
				} catch ( \Throwable $e ) {
					// Return empty array on error
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						\FP\SEO\Utils\Logger::debug( 'FP SEO: Error getting social meta', array(
							'error' => $e->getMessage(),
							'post_id' => $post_id,
						) );
					}
					return array();
				}
			}, HOUR_IN_SECONDS );
		} catch ( \Throwable $e ) {
			// Return empty array on error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Fatal error getting social meta', array(
					'error' => $e->getMessage(),
					'post_id' => $post_id,
				) );
			}
			return array();
		}
	}

	/**
	 * Get preview data for post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed>
	 */
	/**
	 * Get preview data for a post.
	 *
	 * @param WP_Post|object $post Post object.
	 * @return array Preview data.
	 */
	public function get_preview_data( $post ): array {
		try {
			$social_meta = $this->get_social_meta( $post->ID );
			
			// Use social meta if available, otherwise use SEO title/description from SERP Optimization
			// This ensures social preview automatically uses SERP Optimization values as fallback
			$title = '';
			try {
				$title = ! empty( $social_meta['facebook_title'] ) 
					? wp_specialchars_decode( $social_meta['facebook_title'], ENT_QUOTES )
					: MetadataResolver::resolve_seo_title( $post );
			} catch ( \Throwable $e ) {
				$title = get_the_title( $post->ID );
			}
			
			// For description: use social meta, then SEO description from SERP Optimization
			$description = '';
			try {
				$description = ! empty( $social_meta['facebook_description'] )
					? wp_specialchars_decode( $social_meta['facebook_description'], ENT_QUOTES )
					: MetadataResolver::resolve_meta_description( $post );
			} catch ( \Throwable $e ) {
				$description = get_the_excerpt( $post->ID ) ?: '';
			}
			
			// For image: use social meta if available, then featured image as standard, then default
			$image = '';
			try {
				// CRITICAL: Ensure we have a valid post ID
				$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
				if ( ! $post_id ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						\FP\SEO\Utils\Logger::debug( 'FP SEO: Invalid post ID in get_preview_data', array(
							'post' => is_object( $post ) ? get_class( $post ) : gettype( $post ),
						) );
					}
					$image = '';
				} else {
					// Get featured image using multiple methods for robustness
					$featured_image = '';
					
					// Featured image fallback removed - no longer using featured images
					$featured_image = ''; // No longer used
					
					// Priority: social meta > default (featured image removed)
					if ( ! empty( $social_meta['facebook_image'] ) ) {
						$image = esc_url_raw( $social_meta['facebook_image'] );
					} else {
						// Final fallback to default social image (no featured image fallback)
						$image = get_option( 'fp_seo_social_default_image', '' );
					}
				}
			} catch ( \Throwable $e ) {
				// Log error but don't break the page
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					\FP\SEO\Utils\Logger::error( 'FP SEO: Error getting featured image in get_preview_data', array(
						'error' => $e->getMessage(),
						'post_id' => isset( $post->ID ) ? $post->ID : 'N/A',
						'trace' => $e->getTraceAsString(),
					) );
				}
				$image = '';
			}
			
			$url = '';
			try {
				$url = get_permalink( $post->ID );
			} catch ( \Throwable $e ) {
				$url = '';
			}
			
			return array(
				'title' => $title ?: get_the_title( $post->ID ),
				'description' => $description ?: '',
				'url' => $url ?: '',
				'image' => $image ?: '',
			);
		} catch ( \Throwable $e ) {
			// Fallback to basic post data
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Error getting preview data', array(
					'error' => $e->getMessage(),
					'post_id' => isset( $post->ID ) ? $post->ID : 0,
				) );
			}
			// Get featured image using multiple methods for robustness in fallback
			// Featured image fallback removed
			$fallback_image = '';
			try {
				// No longer using featured images as fallback
			} catch ( \Throwable $e ) {
				$fallback_image = '';
			}
			
			return array(
				'title' => get_the_title( $post->ID ),
				'description' => get_the_excerpt( $post->ID ) ?: '',
				'url' => get_permalink( $post->ID ) ?: '',
				'image' => $fallback_image ?: '',
			);
		}
	}

	/**
	 * Save social media meta data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_social_meta( int $post_id ): void {
		// CRITICAL: Check post type FIRST, before any processing
		// This ensures we don't interfere with unsupported post types (attachments, Nectar Sliders, etc.)
		$post_type = get_post_type( $post_id );
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		
		// If not a supported post type, return immediately without any processing
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			// Log only in debug mode and only once per post type to avoid spam
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				static $logged_types = array();
				if ( ! isset( $logged_types[ $post_type ] ) ) {
					\FP\SEO\Utils\Logger::debug( 'ImprovedSocialMediaManager::save_social_meta skipped - unsupported post type', array(
						'post_id' => $post_id,
						'post_type' => $post_type,
						'supported_types' => $supported_types,
					) );
					$logged_types[ $post_type ] = true;
				}
			}
			return; // Exit immediately - no interference with WordPress core saving
		}
		
		if ( ! isset( $_POST['fp_seo_social_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_seo_social_nonce'] ) ), 'fp_seo_social_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$social_meta = array();

		foreach ( self::PLATFORMS as $platform_id => $platform_data ) {
			// Decode HTML entities before sanitizing to preserve actual characters like &
			$title_raw = isset( $_POST[ 'fp_seo_' . $platform_id . '_title' ] ) ? wp_unslash( $_POST[ 'fp_seo_' . $platform_id . '_title' ] ) : '';
			$title_decoded = html_entity_decode( (string) $title_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$social_meta[ $platform_id . '_title' ] = sanitize_text_field( $title_decoded );
			
			$description_raw = isset( $_POST[ 'fp_seo_' . $platform_id . '_description' ] ) ? wp_unslash( $_POST[ 'fp_seo_' . $platform_id . '_description' ] ) : '';
			$description_decoded = html_entity_decode( (string) $description_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$social_meta[ $platform_id . '_description' ] = sanitize_textarea_field( $description_decoded );
			
			$social_meta[ $platform_id . '_image' ] = esc_url_raw( $_POST[ 'fp_seo_' . $platform_id . '_image' ] ?? '' );
		}

		// Twitter specific
		$social_meta['twitter_card_type'] = sanitize_text_field( $_POST['fp_seo_twitter_card_type'] ?? 'summary_large_image' );

		update_post_meta( $post_id, '_fp_seo_social_meta', $social_meta );

		// Clear cache
		Cache::delete( 'fp_seo_social_meta_' . $post_id );
	}

	/**
	 * Output social media meta tags in head.
	 */
	public function output_meta_tags(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$social_meta = $this->get_social_meta( $post_id );
		$post        = get_post( $post_id );
		$post_title  = get_the_title( $post_id );
		$post_excerpt = get_the_excerpt( $post_id );
		$post_content = $post ? (string) $post->post_content : (string) get_post_field( 'post_content', $post_id );
		$permalink   = get_permalink( $post_id );

		if ( '' === trim( (string) $post_excerpt ) ) {
			// Use content without shortcodes as fallback
			$content_without_shortcodes = strip_shortcodes( $post_content );
			$post_excerpt = wp_trim_words( wp_strip_all_tags( $content_without_shortcodes ), 30, '' );
		}

		$defaults = array(
			'title'       => $post_title,
			'description' => trim( (string) $post_excerpt ),
			'permalink'   => $permalink,
			'post_id'     => $post_id,
		);

		// Use SocialMetaTagsOutput if available
		if ( $this->meta_tags_output ) {
			$this->meta_tags_output->output( $social_meta, $defaults );
			return;
		}

		// Fallback to original methods
		echo "\n<!-- FP SEO Performance Social Media Tags -->\n";
		
		// Open Graph tags
		$this->output_open_graph_tags( $social_meta, $defaults );
		
		// Twitter Card tags
		$this->output_twitter_card_tags( $social_meta, $defaults );
		
		// LinkedIn tags
		$this->output_linkedin_tags( $social_meta, $defaults );
		
		// Pinterest tags
		$this->output_pinterest_tags( $social_meta, $defaults );
		
		echo "<!-- End FP SEO Performance Social Media Tags -->\n";
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 */
	private function output_open_graph_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['facebook_title'] ) ? $meta['facebook_title'] : $defaults['title'];
		$description = ! empty( $meta['facebook_description'] ) ? $meta['facebook_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$og_tags = array(
			'og:title' => $title,
			'og:description' => $description,
			'og:type' => 'article',
			'og:url' => $permalink,
			'og:site_name' => get_bloginfo( 'name' ),
			'og:locale' => get_locale(),
		);

		// Add image
		$og_image = $this->get_social_image( $meta, 'facebook', $defaults['post_id'] );
		if ( $og_image ) {
			$og_tags['og:image'] = $og_image;
			$og_tags['og:image:width'] = 1200;
			$og_tags['og:image:height'] = 630;
			$og_tags['og:image:alt'] = $title;
		}

		foreach ( $og_tags as $property => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Twitter Card meta tags (fallback method).
	 *
	 * @deprecated Use SocialMetaTagsOutput::output_twitter_card_tags() instead.
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_twitter_card_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['twitter_title'] ) ? $meta['twitter_title'] : $defaults['title'];
		$description = ! empty( $meta['twitter_description'] ) ? $meta['twitter_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$twitter_tags = array(
			'twitter:card' => $meta['twitter_card_type'] ?? 'summary_large_image',
			'twitter:title' => $title,
			'twitter:description' => $description,
			'twitter:url' => $permalink,
		);

		// Add image
		$twitter_image = $this->get_social_image( $meta, 'twitter', $defaults['post_id'] );
		if ( $twitter_image ) {
			$twitter_tags['twitter:image'] = $twitter_image;
			$twitter_tags['twitter:image:alt'] = $title;
		}

		foreach ( $twitter_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output LinkedIn meta tags (fallback method).
	 *
	 * @deprecated Use SocialMetaTagsOutput::output_linkedin_tags() instead.
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_linkedin_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['linkedin_title'] ) ? $meta['linkedin_title'] : $defaults['title'];
		$description = ! empty( $meta['linkedin_description'] ) ? $meta['linkedin_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$linkedin_tags = array(
			'linkedin:title' => $title,
			'linkedin:description' => $description,
			'linkedin:url' => $permalink,
		);

		$linkedin_image = $this->get_social_image( $meta, 'linkedin', $defaults['post_id'] );
		if ( $linkedin_image ) {
			$linkedin_tags['linkedin:image'] = $linkedin_image;
		}

		foreach ( $linkedin_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Pinterest meta tags (fallback method).
	 *
	 * @deprecated Use SocialMetaTagsOutput::output_pinterest_tags() instead.
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_pinterest_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['pinterest_title'] ) ? $meta['pinterest_title'] : $defaults['title'];
		$description = ! empty( $meta['pinterest_description'] ) ? $meta['pinterest_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$pinterest_tags = array(
			'pinterest:title' => $title,
			'pinterest:description' => $description,
			'pinterest:url' => $permalink,
		);

		$pinterest_image = $this->get_social_image( $meta, 'pinterest', $defaults['post_id'] );
		if ( $pinterest_image ) {
			$pinterest_tags['pinterest:image'] = $pinterest_image;
		}

		foreach ( $pinterest_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Get social image for platform (fallback method).
	 *
	 * @deprecated Use SocialMetaTagsOutput::get_social_image() instead.
	 * @param array<string, mixed> $meta Social meta data.
	 * @param string $platform Platform name.
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function get_social_image( array $meta, string $platform, int $post_id ): ?string {
		// Check for platform-specific image
		$platform_image = $meta[ $platform . '_image' ] ?? null;
		if ( ! empty( $platform_image ) ) {
			return $platform_image;
		}

		// Featured image check removed - no longer using featured images

		// Check for default social image
		$default_image = get_option( 'fp_seo_social_default_image' );
		if ( $default_image ) {
			return $default_image;
		}

		return null;
	}

	/**
	 * Render Social Media management page.
	 */
	public function render_social_page(): void {
		// Use modular page renderer if available
		if ( $this->page_renderer ) {
			$this->page_renderer->render();
			return;
		}

		// Fallback message if renderer is not available
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Impossibile caricare la pagina Social Media.', 'fp-seo-performance' );
		echo '</p></div>';
	}

	/**
	 * AJAX handler for social media preview.
	 */
	public function ajax_preview_social(): void {
		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$platform = sanitize_text_field( $_POST['platform'] ?? 'facebook' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$social_meta = $this->get_social_meta( $post_id );
		$preview_data = $this->get_preview_data( get_post( $post_id ) );

		wp_send_json_success( array(
			'platform' => $platform,
			'preview' => $preview_data,
			'meta' => $social_meta,
		) );
	}

	/**
	 * AJAX handler for social media optimization (fallback method).
	 *
	 * @deprecated Use SocialAjaxHandler::handle_optimize() instead.
	 * @return void
	 */
	public function ajax_optimize_social(): void {
		// This method is kept for backward compatibility but should use SocialAjaxHandler
		if ( $this->ajax_handler ) {
			$this->ajax_handler->handle_optimize();
			return;
		}

		check_ajax_referer( 'fp_seo_social_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$platform = sanitize_text_field( $_POST['platform'] ?? 'all' );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found' );
		}

		// Use AI to optimize social media content
		$optimized = $this->optimize_social_with_ai( $post, $platform );

		wp_send_json_success( $optimized );
	}

	/**
	 * AJAX handler to get attachment URL.
	 * Used as fallback when wp.media is not available.
	 */
	public function ajax_get_attachment_url(): void {
		// Attachment URL handler removed - no longer handling images
		wp_send_json_error( array( 'message' => __( 'Image handling feature has been removed.', 'fp-seo-performance' ) ), 410 );
		return;
		check_ajax_referer( 'fp_seo_get_attachment', 'nonce' );

		$attachment_id = (int) ( $_POST['attachment_id'] ?? 0 );

		if ( ! $attachment_id ) {
			wp_send_json_error( 'Invalid attachment ID' );
		}

		// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze con dimensioni
		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			wp_send_json_error( 'Attachment not found' );
		}

		wp_send_json_success( array( 'url' => $url ) );
	}


	/**
	 * Optimize social media content with AI (fallback method).
	 *
	 * @deprecated Use SocialAjaxHandler::optimize_social_with_ai() instead.
	 * @param WP_Post $post Post object.
	 * @param string $platform Social platform.
	 * @return array<string, mixed>
	 */
	private function optimize_social_with_ai( $post, string $platform ): array {
		// This method is kept for backward compatibility but should use SocialAjaxHandler
		if ( $this->ajax_handler ) {
			// Use reflection to access private method
			$reflection = new \ReflectionClass( $this->ajax_handler );
			$method = $reflection->getMethod( 'optimize_social_with_ai' );
			$method->setAccessible( true );
			return $method->invoke( $this->ajax_handler, $post, $platform );
		}

		// Fallback implementation (should not be reached if ajax_handler is available)
		$title = get_the_title( $post->ID );
		$content = $this->extract_clean_content( $post->post_content );
		$excerpt = get_the_excerpt( $post->ID );

		$optimized = array();

		if ( $platform === 'all' ) {
			foreach ( self::PLATFORMS as $platform_id => $platform_data ) {
				$optimized[ $platform_id ] = array(
					'title' => $this->optimize_for_platform( $title, $platform_id ),
					'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform_id )
				);
			}
		} else {
			$optimized[ $platform ] = array(
				'title' => $this->optimize_for_platform( $title, $platform ),
				'description' => $this->optimize_for_platform( $excerpt ?: wp_trim_words( $content, 20 ), $platform )
			);
		}

		return $optimized;
	}

	/**
	 * Optimize content for specific platform (fallback method).
	 *
	 * @deprecated Use SocialAjaxHandler::optimize_for_platform() instead.
	 * @param string $content Content to optimize.
	 * @param string $platform Platform name.
	 * @return string
	 */
	private function optimize_for_platform( string $content, string $platform ): string {
		$platform_data = self::PLATFORMS[ $platform ] ?? null;
		if ( ! $platform_data ) {
			return $content;
		}

		$limit = $platform_data['title_limit'];
		$content = wp_trim_words( $content, $limit / 6 ); // Rough word estimation
		
		// Decode all HTML entities to ensure clean text
		$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		
		return $content;
	}

	/**
	 * Extract clean content from post, handling WPBakery shortcodes (fallback method).
	 *
	 * @deprecated Use SocialAjaxHandler::extract_clean_content() instead.
	 * @param string $post_content Raw post content.
	 * @return string Clean text content.
	 */
	private function extract_clean_content( string $post_content ): string {
		if ( empty( $post_content ) ) {
			return '';
		}

		// Check if content contains WPBakery shortcodes
		if ( strpos( $post_content, '[vc_' ) !== false || strpos( $post_content, '[vc_row' ) !== false ) {
			// Use WPBakeryContentExtractor to get clean text (static method)
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				$text = \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $post_content );
				
				if ( ! empty( $text ) ) {
					// Clean up the extracted text (already cleaned by extract_text, but normalize whitespace)
					$text = preg_replace( '/\s+/', ' ', $text ); // Normalize whitespace
					return trim( $text );
				}
			}
		}

		// Fallback: standard WordPress shortcode removal
		// First render shortcodes, then strip tags
		$rendered = do_shortcode( $post_content );
		$content = wp_strip_all_tags( $rendered );
		$content = preg_replace( '/\s+/', ' ', $content ); // Normalize whitespace
		
		return trim( $content );
	}
}

