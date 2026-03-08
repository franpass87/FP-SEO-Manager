<?php
/**
 * Debug script to check what happens to _thumbnail_id during post save
 * 
 * Usage: Access via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/debug-featured-image-save.php?post_id=565
 */

// Load WordPress
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
	__DIR__ . '/../../wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded ) {
	die( 'WordPress not found' );
}

$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;

if ( ! $post_id ) {
	die( 'Please provide post_id parameter: ?post_id=565' );
}

$post = get_post( $post_id );
if ( ! $post ) {
	die( 'Post not found: ' . $post_id );
}

echo '<h1>Debug Featured Image Save - Post ID: ' . $post_id . '</h1>';

// Check current _thumbnail_id in database
$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
echo '<h2>Current _thumbnail_id in database:</h2>';
echo '<pre>';
var_dump( $thumbnail_id );
echo '</pre>';

// Check if thumbnail exists
if ( $thumbnail_id && $thumbnail_id !== '-1' ) {
	$attachment = get_post( $thumbnail_id );
	echo '<h2>Thumbnail attachment:</h2>';
	echo '<pre>';
	var_dump( $attachment ? array(
		'ID' => $attachment->ID,
		'post_title' => $attachment->post_title,
		'guid' => $attachment->guid,
	) : 'Attachment not found' );
	echo '</pre>';
} else {
	echo '<p><strong>No featured image set (thumbnail_id is empty or -1)</strong></p>';
}

// Check what WordPress would do during save
echo '<h2>WordPress Save Behavior:</h2>';
echo '<p>WordPress removes _thumbnail_id if it\'s not present in $_POST during a normal save (non-AJAX).</p>';
echo '<p>This is standard WordPress behavior. When you save a post normally, WordPress only preserves _thumbnail_id if it\'s explicitly included in $_POST.</p>';

// Check if there are any filters that might interfere
echo '<h2>Active Filters on update_post_metadata:</h2>';
global $wp_filter;
if ( isset( $wp_filter['update_post_metadata'] ) ) {
	echo '<pre>';
	foreach ( $wp_filter['update_post_metadata']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) ) {
				$function_name = is_object( $callback['function'][0] ) 
					? get_class( $callback['function'][0] ) . '::' . $callback['function'][1]
					: $callback['function'][0] . '::' . $callback['function'][1];
			} else {
				$function_name = $callback['function'];
			}
			echo "Priority: $priority, Function: $function_name\n";
		}
	}
	echo '</pre>';
} else {
	echo '<p>No filters on update_post_metadata</p>';
}

// Check if there are any filters on delete_post_metadata
echo '<h2>Active Filters on delete_post_metadata:</h2>';
if ( isset( $wp_filter['delete_post_metadata'] ) ) {
	echo '<pre>';
	foreach ( $wp_filter['delete_post_metadata']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) ) {
				$function_name = is_object( $callback['function'][0] ) 
					? get_class( $callback['function'][0] ) . '::' . $callback['function'][1]
					: $callback['function'][0] . '::' . $callback['function'][1];
			} else {
				$function_name = $callback['function'];
			}
			echo "Priority: $priority, Function: $function_name\n";
		}
	}
	echo '</pre>';
} else {
	echo '<p>No filters on delete_post_metadata</p>';
}

// Check save_post hooks
echo '<h2>Active save_post hooks (priority 20 and below):</h2>';
if ( isset( $wp_filter['save_post'] ) ) {
	echo '<pre>';
	foreach ( $wp_filter['save_post']->callbacks as $priority => $callbacks ) {
		if ( $priority <= 20 ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) ) {
					$function_name = is_object( $callback['function'][0] ) 
						? get_class( $callback['function'][0] ) . '::' . $callback['function'][1]
						: $callback['function'][0] . '::' . $callback['function'][1];
				} else {
					$function_name = $callback['function'];
				}
				echo "Priority: $priority, Function: $function_name\n";
			}
		}
	}
	echo '</pre>';
} else {
	echo '<p>No save_post hooks</p>';
}

echo '<h2>Conclusion:</h2>';
echo '<p>If _thumbnail_id is being removed during save, it\'s likely because WordPress removes it when it\'s not present in $_POST during a normal save.</p>';
echo '<p>This is standard WordPress behavior. The plugin should not interfere with this, but if it does, we need to ensure _thumbnail_id is preserved.</p>';


