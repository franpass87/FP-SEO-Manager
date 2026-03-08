<?php
/**
 * Test script to verify featured image saving
 * 
 * Access: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-featured-image-save.php?post_id=XXX
 */

// Load WordPress
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	$wp_load_path = dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php';
}
if ( ! file_exists( $wp_load_path ) ) {
	$wp_load_path = dirname( dirname( __FILE__ ) ) . '/wp-load.php';
}
require_once( $wp_load_path );

// Check if user is logged in
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( 'Access denied. Please log in as administrator.' );
}

$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Featured Image Save</title>
	<?php wp_head(); ?>
	<style>
		body { font-family: Arial, sans-serif; padding: 20px; }
		.test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
		.success { color: green; font-weight: bold; }
		.error { color: red; font-weight: bold; }
		.info { color: blue; }
		pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
	</style>
</head>
<body>
	<h1>Test Featured Image Save</h1>
	
	<?php if ( $post_id > 0 ): ?>
		<div class="test-section">
			<h2>Post ID: <?php echo esc_html( $post_id ); ?></h2>
			
			<?php
			// Get current featured image
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			$thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : null;
			
			// Get from database directly
			global $wpdb;
			$db_thumbnail_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1",
				$post_id
			) );
			
			?>
			
			<h3>Current Featured Image Status</h3>
			<p><strong>Thumbnail ID (get_post_thumbnail_id):</strong> <?php echo $thumbnail_id ? esc_html( $thumbnail_id ) : 'NOT SET'; ?></p>
			<p><strong>Thumbnail ID (Database):</strong> <?php echo $db_thumbnail_id ? esc_html( $db_thumbnail_id ) : 'NOT SET'; ?></p>
			<?php if ( $thumbnail_url ): ?>
				<p><strong>Thumbnail URL:</strong> <img src="<?php echo esc_url( $thumbnail_url ); ?>" style="max-width: 150px;" /></p>
			<?php else: ?>
				<p><strong>Thumbnail URL:</strong> NOT SET</p>
			<?php endif; ?>
			
			<?php if ( $thumbnail_id != $db_thumbnail_id ): ?>
				<p class="error">⚠️ MISMATCH: Cache and database values differ!</p>
			<?php endif; ?>
			
			<h3>Test Set Featured Image</h3>
			<form method="post" action="">
				<input type="hidden" name="test_action" value="set_thumbnail" />
				<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>" />
				<label>Attachment ID: <input type="number" name="attachment_id" value="" required /></label>
				<button type="submit">Set Featured Image</button>
			</form>
			
			<?php
			if ( isset( $_POST['test_action'] ) && $_POST['test_action'] === 'set_thumbnail' ) {
				$attachment_id = intval( $_POST['attachment_id'] );
				$test_post_id = intval( $_POST['post_id'] );
				
				echo '<h3>Test Results</h3>';
				
				// Check if attachment exists
				$attachment = get_post( $attachment_id );
				if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
					echo '<p class="error">ERROR: Attachment ID ' . esc_html( $attachment_id ) . ' does not exist</p>';
				} else {
					echo '<p class="success">✓ Attachment exists: ' . esc_html( $attachment->post_title ) . '</p>';
					
					// Set featured image
					$result = set_post_thumbnail( $test_post_id, $attachment_id );
					
					if ( $result ) {
						echo '<p class="success">✓ Featured image set successfully</p>';
					} else {
						echo '<p class="error">ERROR: Failed to set featured image</p>';
					}
					
					// Verify
					$verify_id = get_post_thumbnail_id( $test_post_id );
					if ( $verify_id == $attachment_id ) {
						echo '<p class="success">✓ Verified: Featured image ID matches</p>';
					} else {
						echo '<p class="error">ERROR: Verification failed. Expected: ' . esc_html( $attachment_id ) . ', Got: ' . esc_html( $verify_id ) . '</p>';
					}
					
					// Check database
					$db_check = $wpdb->get_var( $wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1",
						$test_post_id
					) );
					
					if ( $db_check == $attachment_id ) {
						echo '<p class="success">✓ Database check: Featured image ID matches</p>';
					} else {
						echo '<p class="error">ERROR: Database check failed. Expected: ' . esc_html( $attachment_id ) . ', Got: ' . esc_html( $db_check ) . '</p>';
					}
				}
			}
			?>
			
			<h3>Available Attachments</h3>
			<?php
			$attachments = get_posts( array(
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'posts_per_page' => 10,
				'orderby' => 'date',
				'order' => 'DESC'
			) );
			
			if ( $attachments ) {
				echo '<ul>';
				foreach ( $attachments as $attachment ) {
					$thumb = wp_get_attachment_image_url( $attachment->ID, 'thumbnail' );
					echo '<li>';
					echo '<strong>ID: ' . esc_html( $attachment->ID ) . '</strong> - ' . esc_html( $attachment->post_title );
					if ( $thumb ) {
						echo ' <img src="' . esc_url( $thumb ) . '" style="max-width: 50px; vertical-align: middle;" />';
					}
					echo '</li>';
				}
				echo '</ul>';
			} else {
				echo '<p>No attachments found</p>';
			}
			?>
		</div>
	<?php else: ?>
		<div class="test-section">
			<h2>Select a Post</h2>
			<?php
			$posts = get_posts( array(
				'post_type' => 'post',
				'posts_per_page' => 20,
				'orderby' => 'date',
				'order' => 'DESC'
			) );
			
			if ( $posts ) {
				echo '<ul>';
				foreach ( $posts as $post ) {
					echo '<li><a href="?post_id=' . esc_attr( $post->ID ) . '">' . esc_html( $post->post_title ) . ' (ID: ' . esc_html( $post->ID ) . ')</a></li>';
				}
				echo '</ul>';
			} else {
				echo '<p>No posts found</p>';
			}
			?>
		</div>
	<?php endif; ?>
	
	<?php wp_footer(); ?>
</body>
</html>


