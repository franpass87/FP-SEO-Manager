<?php
/**
 * Test script to debug image extraction for a specific post
 * Usage: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-image-extraction.php?post_id=399
 */

// Load WordPress
// This file is in: wp-content/plugins/FP-SEO-Manager/test-image-extraction.php
// wp-load.php is in: wp-load.php (root)
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	// Try alternative path (if plugin is in a subdirectory)
	$wp_load_path = dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) . '/wp-load.php';
}
if ( ! file_exists( $wp_load_path ) ) {
	// Try absolute path based on workspace
	$wp_load_path = 'C:\\Users\\franc\\Local Sites\\fp-development\\app\\public\\wp-load.php';
}
if ( ! file_exists( $wp_load_path ) ) {
	die( "Cannot find wp-load.php. Tried: " . htmlspecialchars( $wp_load_path ) );
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

echo "<h1>Image Extraction Test for Post ID: {$post_id}</h1>";
echo "<h2>Post: {$post->post_title}</h2>";
echo "<h2>Post Type: {$post->post_type}</h2>";
echo "<h2>Post Status: {$post->post_status}</h2>";

// Get raw content from database
global $wpdb;
$db_content = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
	$post_id
) );

echo "<h3>Raw Content (from database)</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
echo esc_html( substr( $db_content, 0, 2000 ) );
echo "</pre>";

// Check for WPBakery shortcodes
$has_wpbakery = strpos( $db_content, '[vc_' ) !== false;
echo "<h3>Has WPBakery Shortcodes: " . ( $has_wpbakery ? '✅ Yes' : '❌ No' ) . "</h3>";

// Check for img tags
$has_img_tags = strpos( $db_content, '<img' ) !== false;
echo "<h3>Has &lt;img&gt; Tags: " . ( $has_img_tags ? '✅ Yes' : '❌ No' ) . "</h3>";

// Process content
$processed_content = do_shortcode( $db_content );
if ( $has_wpbakery ) {
	$processed_content = apply_filters( 'the_content', $db_content );
}

echo "<h3>Processed Content (after do_shortcode and the_content filter)</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
echo esc_html( substr( $processed_content, 0, 2000 ) );
echo "</pre>";

// Check for img tags in processed content
$has_img_in_processed = strpos( $processed_content, '<img' ) !== false;
echo "<h3>Has &lt;img&gt; Tags in Processed Content: " . ( $has_img_in_processed ? '✅ Yes' : '❌ No' ) . "</h3>";

// Check for background-image CSS
$has_bg_image = strpos( $processed_content, 'background-image' ) !== false || strpos( $db_content, 'background-image' ) !== false;
echo "<h3>Has background-image CSS: " . ( $has_bg_image ? '✅ Yes' : '❌ No' ) . "</h3>";

// Extract background-image URLs using regex
$bg_matches = array();
if ( preg_match_all( '/background-image\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i', $processed_content . "\n" . $db_content, $bg_matches, PREG_SET_ORDER ) ) {
	echo "<h3>Background-image URLs Found (regex):</h3>";
	echo "<ul>";
	foreach ( $bg_matches as $match ) {
		echo "<li>" . esc_html( $match[1] ) . "</li>";
	}
	echo "</ul>";
} else {
	echo "<h3>Background-image URLs Found (regex): ❌ None</h3>";
}

// Extract WPBakery shortcode attributes
if ( $has_wpbakery ) {
	echo "<h3>WPBakery Shortcode Attributes:</h3>";
	$image_attr_patterns = array( 'image', 'bg_image', 'background_image', 'images', 'image_url', 'image_1_url', 'image_2_url', 'image_3_url' );
	$found_attrs = array();
	foreach ( $image_attr_patterns as $attr ) {
		$pattern = '/\[vc_\w+.*?' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\'].*?\]/is';
		if ( preg_match_all( $pattern, $db_content, $attr_matches, PREG_SET_ORDER ) ) {
			foreach ( $attr_matches as $match ) {
				$found_attrs[] = array(
					'attr' => $attr,
					'value' => $match[1],
					'shortcode' => substr( $match[0], 0, 150 ),
				);
			}
		}
	}
	if ( ! empty( $found_attrs ) ) {
		echo "<ul>";
		foreach ( $found_attrs as $found ) {
			echo "<li><strong>{$found['attr']}</strong>: " . esc_html( $found['value'] ) . "<br><small>" . esc_html( $found['shortcode'] ) . "</small></li>";
		}
		echo "</ul>";
	} else {
		echo "<p>❌ No image attributes found in WPBakery shortcodes</p>";
	}
}

// Check featured image
$featured_id = get_post_thumbnail_id( $post_id );
if ( $featured_id ) {
	$featured_url = wp_get_attachment_url( $featured_id );
	echo "<h3>Featured Image: ✅ Yes</h3>";
	echo "<p>ID: {$featured_id}</p>";
	echo "<p>URL: {$featured_url}</p>";
} else {
	echo "<h3>Featured Image: ❌ No</h3>";
}

// Try to use the actual extract_images_from_content method
if ( class_exists( 'FP\SEO\Editor\MetaboxRenderer' ) ) {
	echo "<h3>Using MetaboxRenderer::extract_images_from_content()</h3>";
	try {
		$renderer = new \FP\SEO\Editor\MetaboxRenderer();
		$images = $renderer->extract_images_from_content( $post );
		
		echo "<h4>Images Found: " . count( $images ) . "</h4>";
		if ( ! empty( $images ) ) {
			echo "<ul>";
			foreach ( $images as $index => $image ) {
				echo "<li>";
				echo "<strong>Image " . ( $index + 1 ) . ":</strong><br>";
				echo "SRC: " . esc_html( $image['src'] ?? 'N/A' ) . "<br>";
				echo "Attachment ID: " . ( $image['attachment_id'] ?? 'N/A' ) . "<br>";
				echo "Alt: " . esc_html( $image['alt'] ?? 'N/A' ) . "<br>";
				echo "Title: " . esc_html( $image['title'] ?? 'N/A' ) . "<br>";
				echo "</li>";
			}
			echo "</ul>";
		} else {
			echo "<p>❌ No images found</p>";
		}
	} catch ( \Throwable $e ) {
		echo "<p style='color: red;'>❌ Error: " . esc_html( $e->getMessage() ) . "</p>";
		echo "<pre>" . esc_html( $e->getTraceAsString() ) . "</pre>";
	}
} else {
	echo "<p>❌ MetaboxRenderer class not found</p>";
}

