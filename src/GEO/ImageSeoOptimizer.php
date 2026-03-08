<?php
/**
 * Image SEO Optimizer
 *
 * Optimizes images for SEO by renaming files based on SEO title and updating metadata.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use FP\SEO\Editor\ImageExtractor;
use FP\SEO\Editor\Services\ImageManagementService;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Utils\MetadataResolver;
use WP_Post;

/**
 * Optimizes images for SEO by renaming files and updating metadata
 */
class ImageSeoOptimizer {

	/**
	 * Meta key to track optimized images (stored on posts, not attachments)
	 */
	private const OPTIMIZED_META_KEY = '_fp_seo_images_optimized';

	/**
	 * Logger instance
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Image extractor instance
	 *
	 * @var ImageExtractor
	 */
	private ImageExtractor $image_extractor;

	/**
	 * Image management service instance
	 *
	 * @var ImageManagementService
	 */
	private ImageManagementService $image_manager;

	/**
	 * Constructor
	 *
	 * @param LoggerInterface        $logger         Logger instance.
	 * @param ImageExtractor         $image_extractor Image extractor instance.
	 * @param ImageManagementService $image_manager  Image management service instance.
	 */
	public function __construct(
		LoggerInterface $logger,
		ImageExtractor $image_extractor,
		ImageManagementService $image_manager
	) {
		$this->logger         = $logger;
		$this->image_extractor = $image_extractor;
		$this->image_manager  = $image_manager;
	}

	/**
	 * Optimize all images in a post for SEO
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Optimization results.
	 */
	public function optimize_images_seo( int $post_id ): array {
$post = get_post( $post_id );

		if ( ! $post ) {
			$this->logger->warning( 'Post not found for image SEO optimization', array( 'post_id' => $post_id ) );
			return array(
				'success' => false,
				'message' => 'Post not found',
				'optimized_count' => 0,
			);
		}

		// Check if post type is supported
		$supported_types = \FP\SEO\Utils\PostTypes::analyzable();
		if ( ! in_array( $post->post_type, $supported_types, true ) ) {
			$this->logger->debug( 'Post type not supported for image SEO optimization', array(
				'post_id' => $post_id,
				'post_type' => $post->post_type,
			) );
			return array(
				'success' => false,
				'message' => 'Post type not supported',
				'optimized_count' => 0,
			);
		}

		// Get SEO title
		$seo_title = $this->get_seo_title( $post_id, $post );

		if ( empty( $seo_title ) ) {
			$this->logger->warning( 'No SEO title found for image optimization', array( 'post_id' => $post_id ) );
			return array(
				'success' => false,
				'message' => 'No SEO title available',
				'optimized_count' => 0,
			);
		}

		// Extract images from post
		// Allow non-AJAX context for image SEO optimization during save_post
		$images = $this->image_extractor->extract( $post, true, true );

		if ( empty( $images ) ) {
			$this->logger->debug( 'No images found in post', array( 'post_id' => $post_id ) );
			return array(
				'success' => true,
				'message' => 'No images to optimize',
				'optimized_count' => 0,
			);
		}

		$optimized_count = 0;
		$errors = array();
		$old_urls = array();
		$new_urls = array();

		// Process each image
		foreach ( $images as $index => $image ) {
			$attachment_id = $image['attachment_id'] ?? null;

			// Skip images without attachment ID (external images)
			if ( ! $attachment_id ) {
				$this->logger->debug( 'Skipping external image', array(
					'post_id' => $post_id,
					'image_url' => $image['src'] ?? '',
				) );
				continue;
			}

			try {
				$result = $this->optimize_single_image( $attachment_id, $seo_title, $index + 1, $post );

				// Only count as optimized if not skipped
				if ( $result['success'] && empty( $result['skipped'] ) ) {
					$optimized_count++;
					
					// If image was duplicated, update the post to use the new attachment_id
					if ( ! empty( $result['was_duplicated'] ) && $result['was_duplicated'] ) {
						$new_attachment_id = $result['attachment_id'] ?? $attachment_id;
						
						// Update featured image if this was the featured image
						if ( get_post_thumbnail_id( $post_id ) === $attachment_id ) {
							set_post_thumbnail( $post_id, $new_attachment_id );
							$this->logger->info( 'Updated featured image to duplicated attachment', array(
								'post_id' => $post_id,
								'old_attachment_id' => $attachment_id,
								'new_attachment_id' => $new_attachment_id,
							) );
						}
					}
					
					if ( ! empty( $result['old_url'] ) && ! empty( $result['new_url'] ) ) {
						$old_urls[] = $result['old_url'];
						$new_urls[] = $result['new_url'];
					}
				} else {
					$errors[] = array(
						'attachment_id' => $attachment_id,
						'error' => $result['error'] ?? 'Unknown error',
					);
				}
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Error optimizing image', array(
				'post_id'       => $post_id,
				'attachment_id' => $attachment_id,
				'error'         => $e->getMessage(),
				'trace'         => $e->getTraceAsString(),
			) );
			$errors[] = array(
				'attachment_id' => $attachment_id,
				'error'         => $e->getMessage(),
			);
		}
		}

		// Update content references if any images were renamed
		if ( ! empty( $old_urls ) && ! empty( $new_urls ) && count( $old_urls ) === count( $new_urls ) ) {
			$this->update_content_references( $post_id, $old_urls, $new_urls );
		}

		$this->logger->info( 'Image SEO optimization completed', array(
			'post_id' => $post_id,
			'optimized_count' => $optimized_count,
			'total_images' => count( $images ),
			'errors' => count( $errors ),
		) );

		return array(
			'success' => true,
			'optimized_count' => $optimized_count,
			'total_images' => count( $images ),
			'errors' => $errors,
		);
	}

	/**
	 * Get SEO title for post (for image naming)
	 *
	 * For image naming, we prioritize:
	 * 1. Custom SEO title (_fp_seo_title meta)
	 * 2. Post title (NOT content - content is not suitable for image names)
	 *
	 * @param int      $post_id Post ID.
	 * @param WP_Post  $post    Post object.
	 * @return string SEO title or fallback to post title.
	 */
	private function get_seo_title( int $post_id, WP_Post $post ): string {
		// First, check for custom SEO title in post meta
		$seo_title = get_post_meta( $post_id, '_fp_seo_title', true );
		
		// If custom SEO title exists and is not empty, use it
		if ( is_string( $seo_title ) && '' !== trim( $seo_title ) ) {
			return trim( wp_specialchars_decode( $seo_title, ENT_QUOTES ) );
		}

		// Fallback to post title (NOT content - content is not suitable for image filenames)
		return trim( $post->post_title );
	}

	/**
	 * Check if an image has already been optimized for a specific post
	 *
	 * The optimization state is stored on the POST, not the attachment.
	 * This is because attachments may be duplicated during optimization.
	 *
	 * @param int $attachment_id Original attachment ID (as found in content).
	 * @param int $post_id       Post ID.
	 * @return bool True if already optimized for this post.
	 */
	private function is_image_already_optimized( int $attachment_id, int $post_id ): bool {
		$optimized_images = get_post_meta( $post_id, self::OPTIMIZED_META_KEY, true );
		
		if ( empty( $optimized_images ) || ! is_array( $optimized_images ) ) {
			return false;
		}
		
		// Check if this attachment was already optimized for this post
		return isset( $optimized_images[ $attachment_id ] );
	}

	/**
	 * Mark an image as optimized for a specific post
	 *
	 * Stores the optimization data on the POST, not the attachment.
	 *
	 * @param int    $original_attachment_id Original attachment ID (before duplication).
	 * @param int    $final_attachment_id    Final attachment ID (may be duplicate).
	 * @param int    $post_id                Post ID.
	 * @param string $seo_title              SEO title used for optimization.
	 * @return void
	 */
	private function mark_image_as_optimized( int $original_attachment_id, int $final_attachment_id, int $post_id, string $seo_title ): void {
		$optimized_images = get_post_meta( $post_id, self::OPTIMIZED_META_KEY, true );
		
		if ( ! is_array( $optimized_images ) ) {
			$optimized_images = array();
		}
		
		$optimized_images[ $original_attachment_id ] = array(
			'final_attachment_id' => $final_attachment_id,
			'seo_title'           => $seo_title,
			'optimized_at'        => current_time( 'mysql' ),
		);
		
		update_post_meta( $post_id, self::OPTIMIZED_META_KEY, $optimized_images );
	}

	/**
	 * Optimize a single image
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param string  $seo_title     SEO title.
	 * @param int     $index         Image index (1-based).
	 * @param WP_Post $post          Post object.
	 * @return array<string, mixed> Result with success status and URLs.
	 */
	private function optimize_single_image( int $attachment_id, string $seo_title, int $index, WP_Post $post ): array {
		// Check if image is already optimized for this post
		if ( $this->is_image_already_optimized( $attachment_id, $post->ID ) ) {
			$this->logger->debug( 'Image already optimized, skipping', array(
				'attachment_id' => $attachment_id,
				'post_id' => $post->ID,
			) );
			
			return array(
				'success' => true,
				'skipped' => true,
				'message' => 'Image already optimized for this post',
			);
		}

		// Get attachment
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return array(
				'success' => false,
				'error' => 'Invalid attachment',
			);
		}

		// Get original attachment URL (the one currently in the post content)
		// This is needed for content replacement when image is duplicated
		$original_attachment_url = wp_get_attachment_url( $attachment_id );

		// Check if image is used in other posts
		$is_used_elsewhere = $this->is_attachment_used_in_other_posts( $attachment_id, $post->ID );
		$final_attachment_id = $attachment_id;
		$was_duplicated = false;

		// If used elsewhere, create a duplicate
		if ( $is_used_elsewhere ) {
			$duplicate_id = $this->duplicate_attachment( $attachment_id );
			
			if ( $duplicate_id === false ) {
				return array(
					'success' => false,
					'error' => 'Failed to duplicate attachment for multi-post usage',
				);
			}
			
			$final_attachment_id = $duplicate_id;
			$was_duplicated = true;
			
			$this->logger->info( 'Attachment duplicated for multi-post usage', array(
				'original_attachment_id' => $attachment_id,
				'new_attachment_id' => $final_attachment_id,
				'post_id' => $post->ID,
			) );
		}

		// For content replacement:
		// - If duplicated: use original attachment URL (currently in content) as old_url
		// - If not duplicated: use current attachment URL as old_url (they're the same)
		$old_url = $original_attachment_url;

		if ( ! $old_url ) {
			return array(
				'success' => false,
				'error' => 'Could not get attachment URL',
			);
		}

		// Get file path
		$file_path = get_attached_file( $final_attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'error' => 'File not found',
			);
		}

		// Generate new filename
		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		$new_filename = $this->generate_seo_filename( $seo_title, $index, $extension );

		// Check if filename needs to change
		$current_filename = basename( $file_path );
		if ( $current_filename === $new_filename ) {
			// Filename already optimized, just update metadata
			$this->update_attachment_metadata( $final_attachment_id, $seo_title, $index, $post );
			
			// Mark as optimized for this post (use original attachment_id as key)
			$this->mark_image_as_optimized( $attachment_id, $final_attachment_id, $post->ID, $seo_title );
			
		// Get final URL (even if not renamed, it might be a duplicate)
		$final_url = wp_get_attachment_url( $final_attachment_id );
		if ( ! $final_url ) {
			return array( 'success' => false, 'error' => 'Could not retrieve final attachment URL' );
		}

		return array(
			'success'        => true,
			'old_url'        => $old_url,
			'new_url'        => $final_url,
			'attachment_id'  => $final_attachment_id,
			'was_duplicated' => $was_duplicated,
			'message'        => 'Filename already optimized, metadata updated',
		);
		}

		// Rename file
		$rename_result = $this->rename_attachment_file( $final_attachment_id, $new_filename );

		if ( ! $rename_result['success'] ) {
			return array(
				'success' => false,
				'error' => $rename_result['error'] ?? 'Failed to rename file',
			);
		}

		// Update metadata
		$this->update_attachment_metadata( $final_attachment_id, $seo_title, $index, $post );

		// Mark as optimized for this post (use original attachment_id as key)
		$this->mark_image_as_optimized( $attachment_id, $final_attachment_id, $post->ID, $seo_title );

		// Get new URL
		$new_url = wp_get_attachment_url( $final_attachment_id );
		if ( ! $new_url ) {
			return array( 'success' => false, 'error' => 'Could not retrieve new attachment URL after rename' );
		}

		$this->logger->info( 'Image optimized for SEO', array(
			'attachment_id'          => $final_attachment_id,
			'original_attachment_id' => $attachment_id,
			'was_duplicated'         => $was_duplicated,
			'old_filename'           => $current_filename,
			'new_filename'           => $new_filename,
			'old_url'                => $old_url,
			'new_url'                => $new_url,
		) );

		return array(
			'success' => true,
			'old_url' => $old_url,
			'new_url' => $new_url,
			'old_filename' => $current_filename,
			'new_filename' => $new_filename,
			'attachment_id' => $final_attachment_id,
			'original_attachment_id' => $attachment_id,
			'was_duplicated' => $was_duplicated,
		);
	}

	/**
	 * Generate SEO-friendly filename
	 *
	 * @param string $seo_title SEO title.
	 * @param int    $index     Image index (1-based).
	 * @param string $extension File extension.
	 * @return string SEO-friendly filename.
	 */
	private function generate_seo_filename( string $seo_title, int $index, string $extension ): string {
		// Sanitize title: lowercase, remove special chars, replace spaces with hyphens
		$sanitized = sanitize_title( $seo_title );

		// Remove any remaining special characters
		$sanitized = preg_replace( '/[^a-z0-9-]/', '', $sanitized ) ?? '';

		// Limit length to avoid filesystem issues
		$max_length = 200;
		if ( strlen( $sanitized ) > $max_length ) {
			$sanitized = substr( $sanitized, 0, $max_length );
		}

		// Add index with zero padding (01, 02, 03...)
		$index_padded = str_pad( (string) $index, 2, '0', STR_PAD_LEFT );

		// Build filename: sanitized-title-01.ext
		$filename = $sanitized . '-' . $index_padded . '.' . $extension;

		return $filename;
	}

	/**
	 * Rename attachment file
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $new_filename  New filename.
	 * @return array<string, mixed> Result with success status.
	 */
	private function rename_attachment_file( int $attachment_id, string $new_filename ): array {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'error' => 'File not found',
			);
		}

		$upload_dir = wp_upload_dir();
		$file_dir = dirname( $file_path );

		// Ensure we're in the uploads directory
		if ( strpos( $file_dir, $upload_dir['basedir'] ) !== 0 ) {
			return array(
				'success' => false,
				'error' => 'File outside uploads directory',
			);
		}

		// Generate unique filename to avoid conflicts
		$unique_filename    = wp_unique_filename( $file_dir, $new_filename );
		$new_file_full_path = $file_dir . '/' . $unique_filename;

		// Rename file
		if ( ! rename( $file_path, $new_file_full_path ) ) {
			return array(
				'success' => false,
				'error' => 'Failed to rename file',
			);
		}

		// Update attachment metadata
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $new_file_full_path );
		update_attached_file( $attachment_id, $relative_path );

		// Regenerate attachment metadata (thumbnails, etc.)
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $new_file_full_path );
		if ( ! empty( $attachment_data ) ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}

		return array(
			'success' => true,
			'new_path' => $new_file_full_path,
		);
	}

	/**
	 * Update attachment metadata (title, alt, caption, description)
	 *
	 * @param int     $attachment_id Attachment ID.
	 * @param string  $seo_title     SEO title.
	 * @param int     $index         Image index (1-based).
	 * @param WP_Post $post          Post object.
	 * @return bool Success status.
	 */
	private function update_attachment_metadata( int $attachment_id, string $seo_title, int $index, WP_Post $post ): bool {
		// Update post title (attachment title)
		$image_title = $seo_title . ' - Immagine ' . $index;
		wp_update_post( array(
			'ID' => $attachment_id,
			'post_title' => $image_title,
		) );

		// Update alt text
		$alt_text = $this->generate_alt_text( $seo_title, $index, $post );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		// Update caption (post_excerpt)
		$caption = $this->generate_caption( $seo_title, $index, $post );
		wp_update_post( array(
			'ID' => $attachment_id,
			'post_excerpt' => $caption,
		) );

		// Update description (post_content)
		$description = $this->generate_description( $seo_title, $index, $post );
		wp_update_post( array(
			'ID' => $attachment_id,
			'post_content' => $description,
		) );

			$this->logger->debug( 'Attachment metadata updated', array(
			'attachment_id' => $attachment_id,
			'title' => $image_title,
			'alt' => $alt_text,
		) );

		return true;
	}

	/**
	 * Generate alt text for image
	 *
	 * @param string  $seo_title SEO title.
	 * @param int     $index     Image index.
	 * @param WP_Post $post      Post object.
	 * @return string Alt text.
	 */
	private function generate_alt_text( string $seo_title, int $index, WP_Post $post ): string {
		// Base alt text from SEO title
		$alt = $seo_title;

		// Add context if available
		$context = $this->get_image_context( $index, $post );
		if ( ! empty( $context ) ) {
			$alt .= ' - ' . $context;
		}

		// Limit length (recommended max 125 characters)
		if ( strlen( $alt ) > 125 ) {
			$alt = substr( $alt, 0, 122 ) . '...';
		}

		return $alt;
	}

	/**
	 * Generate caption for image
	 *
	 * @param string  $seo_title SEO title.
	 * @param int     $index     Image index.
	 * @param WP_Post $post      Post object.
	 * @return string Caption.
	 */
	private function generate_caption( string $seo_title, int $index, WP_Post $post ): string {
		$context = $this->get_image_context( $index, $post );

		if ( ! empty( $context ) ) {
			return $context;
		}

		// Fallback to simple caption
		return sprintf( 'Immagine %d: %s', $index, $seo_title );
	}

	/**
	 * Generate description for image
	 *
	 * @param string  $seo_title SEO title.
	 * @param int     $index     Image index.
	 * @param WP_Post $post      Post object.
	 * @return string Description.
	 */
	private function generate_description( string $seo_title, int $index, WP_Post $post ): string {
		$context = $this->get_image_context( $index, $post );

		$description = sprintf(
			'Immagine %d dell\'articolo "%s". %s',
			$index,
			$seo_title,
			! empty( $context ) ? $context : 'Immagine correlata al contenuto dell\'articolo.'
		);

		return $description;
	}

	/**
	 * Get image context from surrounding content
	 *
	 * @param int     $index Image index.
	 * @param WP_Post $post  Post object.
	 * @return string Context description.
	 */
	private function get_image_context( int $index, WP_Post $post ): string {
		// Try to extract context from post content
		$content = $post->post_content;

		if ( empty( $content ) ) {
			return '';
		}

		// Extract images from content to find position
		// Allow non-AJAX context for image SEO optimization during save_post
		$images = $this->image_extractor->extract( $post, true, true );

		if ( isset( $images[ $index - 1 ] ) ) {
			$image = $images[ $index - 1 ];
			$image_url = $image['src'] ?? '';

			if ( ! empty( $image_url ) ) {
				// Find image position in content
				$pos = strpos( $content, $image_url );

				if ( false !== $pos ) {
					// Extract surrounding text
					$before = substr( $content, max( 0, $pos - 200 ), 200 );
					$after = substr( $content, $pos, 300 );

					// Find nearest heading or paragraph
					$context = '';

					// Check for heading before image
					if ( preg_match( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $before, $matches ) ) {
						$context = wp_strip_all_tags( end( $matches ) );
					} elseif ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $before, $matches ) ) {
						$context = wp_trim_words( wp_strip_all_tags( end( $matches ) ), 10 );
					}

					return trim( $context );
				}
			}
		}

		return '';
	}

	/**
	 * Check if attachment is used in other posts
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $current_post_id Current post ID to exclude from check.
	 * @return bool True if used in other posts.
	 */
	private function is_attachment_used_in_other_posts( int $attachment_id, int $current_post_id ): bool {
		global $wpdb;

		// Check if used as featured image in other posts
		$posts_with_featured = get_posts( array(
			'post_type' => 'any',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'post__not_in' => array( $current_post_id ),
			'meta_query' => array(
				array(
					'key' => '_thumbnail_id',
					'value' => $attachment_id,
					'compare' => '='
				)
			),
			'fields' => 'ids',
		) );

		if ( ! empty( $posts_with_featured ) ) {
			return true;
		}

		// Check if used in content of other posts using CSS class wp-image-{id}
		// This is more reliable than URL matching because:
		// 1. The class contains the attachment ID directly
		// 2. It's not affected by image size suffixes in URLs
		// 3. WordPress always adds this class to images inserted via media library
		$css_class = 'wp-image-' . $attachment_id;
		$used_in_content = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE ID != %d 
			AND post_content LIKE %s 
			AND post_status != 'trash'",
			$current_post_id,
			'%' . $wpdb->esc_like( $css_class ) . '%'
		) );

		return (int) $used_in_content > 0;
	}

	/**
	 * Duplicate attachment file and create new attachment post
	 *
	 * @param int $attachment_id Original attachment ID.
	 * @return int|false New attachment ID on success, false on failure.
	 */
	private function duplicate_attachment( int $attachment_id ): int|false {
		$original_attachment = get_post( $attachment_id );
		
		if ( ! $original_attachment || 'attachment' !== $original_attachment->post_type ) {
			return false;
		}

		// Get original file path
		$original_file = get_attached_file( $attachment_id );
		if ( ! $original_file || ! file_exists( $original_file ) ) {
			return false;
		}

		// Get upload directory
		$upload_dir = wp_upload_dir();
		$file_dir = dirname( $original_file );
		
		// Generate new filename (temporary, will be renamed later)
		// Use wp_unique_filename to ensure uniqueness
		$temp_filename = 'copy-' . basename( $original_file );
		$unique_filename = wp_unique_filename( $file_dir, $temp_filename );
		$new_file_path = $file_dir . '/' . $unique_filename;

		// Copy file
		if ( ! copy( $original_file, $new_file_path ) ) {
			$this->logger->error( 'Failed to copy attachment file', array(
				'attachment_id' => $attachment_id,
				'original_file' => $original_file,
				'new_file' => $new_file_path,
			) );
			return false;
		}

	// Prepare attachment data
	$file_type = wp_check_filetype( $unique_filename );
		if ( empty( $file_type['type'] ) ) {
			$this->logger->error( 'Unrecognized file type for duplicate', array( 'filename' => $unique_filename ) );
			return false;
		}
		
		// Get relative path for attachment
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $new_file_path );
		
		// Create new attachment post
		$new_attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title' => $original_attachment->post_title,
			'post_content' => $original_attachment->post_content,
			'post_excerpt' => $original_attachment->post_excerpt,
			'post_status' => 'inherit',
			'guid' => $upload_dir['baseurl'] . '/' . $relative_path,
		);

		// Insert attachment
		$new_attachment_id = wp_insert_attachment( $new_attachment, $relative_path, 0, true );
		
		if ( is_wp_error( $new_attachment_id ) ) {
			// Cleanup: delete copied file on failure
			@unlink( $new_file_path );
			$this->logger->error( 'Failed to create attachment post', array(
				'attachment_id' => $attachment_id,
				'error' => $new_attachment_id->get_error_message(),
			) );
			return false;
		}

		// Copy alt text from original
		$original_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $original_alt ) ) {
			update_post_meta( $new_attachment_id, '_wp_attachment_image_alt', $original_alt );
		}

		// Generate attachment metadata (thumbnails, etc.)
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $new_attachment_id, $new_file_path );
		if ( ! empty( $attachment_data ) ) {
			wp_update_attachment_metadata( $new_attachment_id, $attachment_data );
		}

		$this->logger->info( 'Attachment duplicated', array(
			'original_attachment_id' => $attachment_id,
			'new_attachment_id' => $new_attachment_id,
		) );

		return $new_attachment_id;
	}

	/**
	 * Update content references (old URLs to new URLs)
	 *
	 * @param int   $post_id  Post ID.
	 * @param array $old_urls Array of old URLs.
	 * @param array $new_urls Array of new URLs.
	 * @return bool Success status.
	 */
	private function update_content_references( int $post_id, array $old_urls, array $new_urls ): bool {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		$content = $post->post_content;
		$updated = false;

		// Replace each old URL with new URL
		for ( $i = 0; $i < count( $old_urls ); $i++ ) {
			if ( isset( $old_urls[ $i ] ) && isset( $new_urls[ $i ] ) ) {
				$old_url = $old_urls[ $i ];
				$new_url = $new_urls[ $i ];

			// Extract basenames without extension
			// WordPress adds -WIDTHxHEIGHT before extension for thumbnails
			$old_path      = parse_url( $old_url, PHP_URL_PATH ) ?: '';
			$new_path      = parse_url( $new_url, PHP_URL_PATH ) ?: '';
			$old_path_info = pathinfo( $old_path );
			$new_path_info = pathinfo( $new_path );

				$old_basename = $old_path_info['filename'] ?? '';
				$old_extension = $old_path_info['extension'] ?? '';
				$new_basename = $new_path_info['filename'] ?? '';
				$new_extension = $new_path_info['extension'] ?? '';

				// Remove dimension suffixes from old basename if present
				// WordPress image sizes: -150x150, -300x200, etc.
				$old_basename_clean = preg_replace( '/-\d+x\d+$/', '', $old_basename ) ?? $old_basename;

				// Direct URL replacement
				if ( strpos( $content, $old_url ) !== false ) {
					$content = str_replace( $old_url, $new_url, $content );
					$updated = true;
				}

				// Replace all variants (original and sized versions)
				// Match: old_basename_clean followed by any number of -WIDTHxHEIGHT suffixes and extension
				if ( ! empty( $old_basename_clean ) && ! empty( $old_extension ) ) {
					// Pattern: matches the clean basename + any dimension suffixes (one or more) + extension
					// Examples: image.jpg, image-300x200.jpg, image-300x200-1920x0.jpg
					$pattern = '/' . preg_quote( $old_basename_clean, '/' ) . '((?:-\d+x\d+)*)\.' . preg_quote( $old_extension, '/' ) . '/';
					$replacement = $new_basename . '$1.' . $new_extension;
					$new_content = preg_replace( $pattern, $replacement, $content );
					if ( $new_content !== null && $new_content !== $content ) {
						$content = $new_content;
						$updated = true;
						
						$this->logger->debug( 'URL replacement with dimensions pattern', array(
							'pattern' => $pattern,
							'replacement' => $replacement,
							'old_basename_clean' => $old_basename_clean,
							'new_basename' => $new_basename,
						) );
					}
				}

				// Also replace in various URL formats (with query strings, etc.)
				$old_url_clean = strtok( $old_url, '?' );
				$new_url_clean = strtok( $new_url, '?' );

				if ( $old_url_clean !== $new_url_clean && strpos( $content, $old_url_clean ) !== false ) {
					$content = str_replace( $old_url_clean, $new_url_clean, $content );
					$updated = true;
				}
			}
		}

		// Update post content if changed
		if ( $updated ) {
			wp_update_post( array(
				'ID' => $post_id,
				'post_content' => $content,
			) );

			$this->logger->info( 'Content references updated', array(
				'post_id' => $post_id,
				'replaced_count' => count( $old_urls ),
			) );
		}

		return $updated;
	}
}

