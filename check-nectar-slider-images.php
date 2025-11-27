<?php
/**
 * Check for images in Nectar Slider and post meta
 * Usage: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/check-nectar-slider-images.php?post_id=399
 */

// Load WordPress
$wp_load_path = 'C:\\Users\\franc\\Local Sites\\fp-development\\app\\public\\wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	die( "Cannot find wp-load.php" );
}
require_once $wp_load_path;

// Get post ID from query string
$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
if ( ! $post_id ) {
	die( 'Please provide a post_id parameter, e.g., ?post_id=399' );
}

$post = get_post( $post_id );
if ( ! $post ) {
	die( "Post ID {$post_id} not found." );
}

echo "<h1>Nectar Slider and Post Meta Images Check for Post ID: {$post_id}</h1>";
echo "<h2>Post: {$post->post_title}</h2>";

// Check for Nectar Slider shortcode in content
global $wpdb;
$db_content = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
	$post_id
) );

$has_nectar_slider = strpos( $db_content, '[nectar_slider' ) !== false;
echo "<h3>Has [nectar_slider] Shortcode: " . ( $has_nectar_slider ? '✅ Yes' : '❌ No' ) . "</h3>";

if ( $has_nectar_slider ) {
	// Extract nectar_slider shortcode attributes
	if ( preg_match( '/\[nectar_slider\s+([^\]]+)\]/i', $db_content, $nectar_match ) ) {
		echo "<h3>Nectar Slider Attributes:</h3>";
		echo "<pre>" . esc_html( $nectar_match[1] ) . "</pre>";
		
		// Check for location attribute (which might reference a slider)
		if ( preg_match( '/location\s*=\s*["\']([^"\']+)["\']/', $nectar_match[1], $location_match ) ) {
			$location = $location_match[1];
			echo "<h4>Location: {$location}</h4>";
			
			// Try to find Nectar Slider posts with this location
			$slider_posts = get_posts( array(
				'post_type' => 'nectar_slider',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'slider_location',
						'value' => $location,
						'compare' => 'LIKE',
					),
				),
			) );
			
			if ( ! empty( $slider_posts ) ) {
				echo "<h4>Nectar Slider Posts Found: " . count( $slider_posts ) . "</h4>";
				foreach ( $slider_posts as $slider_post ) {
					echo "<h5>Slider: {$slider_post->post_title} (ID: {$slider_post->ID})</h5>";
					
					// Get all post meta for this slider
					$slider_meta = get_post_meta( $slider_post->ID );
					echo "<h6>Post Meta Keys:</h6>";
					echo "<ul>";
					foreach ( array_keys( $slider_meta ) as $meta_key ) {
						if ( strpos( $meta_key, 'image' ) !== false || strpos( $meta_key, 'slide' ) !== false || strpos( $meta_key, 'bg' ) !== false ) {
							$meta_value = $slider_meta[ $meta_key ];
							if ( is_array( $meta_value ) && count( $meta_value ) === 1 ) {
								$meta_value = $meta_value[0];
							}
							echo "<li><strong>{$meta_key}</strong>: " . esc_html( is_array( $meta_value ) ? print_r( $meta_value, true ) : substr( $meta_value, 0, 200 ) ) . "</li>";
						}
					}
					echo "</ul>";
				}
			} else {
				echo "<p>❌ No Nectar Slider posts found with location: {$location}</p>";
			}
		}
	}
}

// Check all post meta for image-related keys
echo "<h3>Post Meta (Image-related keys):</h3>";
$all_meta = get_post_meta( $post_id );
$image_meta = array();
foreach ( $all_meta as $key => $value ) {
	if ( strpos( $key, 'image' ) !== false || strpos( $key, 'slide' ) !== false || strpos( $key, 'bg' ) !== false || strpos( $key, 'thumbnail' ) !== false ) {
		$image_meta[ $key ] = $value;
	}
}

if ( ! empty( $image_meta ) ) {
	echo "<ul>";
	foreach ( $image_meta as $key => $value ) {
		if ( is_array( $value ) && count( $value ) === 1 ) {
			$value = $value[0];
		}
		$display_value = is_array( $value ) ? print_r( $value, true ) : $value;
		
		// Check if value is an attachment ID
		$attachment_id = is_numeric( $value ) ? absint( $value ) : 0;
		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment && $attachment->post_type === 'attachment' ) {
				$attachment_url = wp_get_attachment_url( $attachment_id );
				$display_value .= " → <strong>Attachment ID {$attachment_id}</strong> → <a href='{$attachment_url}' target='_blank'>{$attachment_url}</a>";
			}
		}
		
		echo "<li><strong>{$key}</strong>: " . esc_html( substr( $display_value, 0, 500 ) ) . "</li>";
	}
	echo "</ul>";
} else {
	echo "<p>❌ No image-related post meta found</p>";
}

// Check for featured image
$featured_id = get_post_thumbnail_id( $post_id );
if ( $featured_id ) {
	$featured_url = wp_get_attachment_url( $featured_id );
	echo "<h3>Featured Image: ✅ Yes</h3>";
	echo "<p>ID: {$featured_id}</p>";
	echo "<p>URL: {$featured_url}</p>";
} else {
	echo "<h3>Featured Image: ❌ No</h3>";
}

