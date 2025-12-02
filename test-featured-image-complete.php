<?php
/**
 * Complete test for featured image functionality
 */
$wp_load_path = '';
if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
	$doc_root = rtrim( $_SERVER['DOCUMENT_ROOT'], '/\\' );
	$wp_load_path = $doc_root . '/wp-load.php';
}
if ( ! $wp_load_path || ! file_exists( $wp_load_path ) ) {
	$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
}
require_once $wp_load_path;

if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Unauthorized' );
}

$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 399;

echo "<h1>Test Completo Immagine in Evidenza - Post ID: {$post_id}</h1>";

$post = get_post( $post_id );
if ( ! $post ) {
	die( "Post {$post_id} not found" );
}

echo "<h2>1. Verifica Immagine in Evidenza</h2>";
$thumbnail_id = get_post_thumbnail_id( $post_id );
echo "<pre>";
echo "Thumbnail ID: " . ( $thumbnail_id ? $thumbnail_id : 'NONE' ) . "\n";
if ( $thumbnail_id ) {
	$url = wp_get_attachment_url( $thumbnail_id );
	echo "URL: " . ( $url ? $url : 'NONE' ) . "\n";
	if ( $url ) {
		echo "<img src='" . esc_url( $url ) . "' style='max-width: 300px;' />\n";
	}
} else {
	echo "⚠️ Post non ha immagine in evidenza impostata\n";
	echo "Per testare, imposta un'immagine in evidenza per questo post.\n";
}
echo "</pre>";

echo "<h2>2. Test get_preview_data()</h2>";
try {
	$social_manager = new \FP\SEO\Social\ImprovedSocialMediaManager();
	$reflection = new ReflectionClass( $social_manager );
	$method = $reflection->getMethod( 'get_preview_data' );
	$method->setAccessible( true );
	$preview_data = $method->invoke( $social_manager, $post );
	
	echo "<pre>";
	echo "Preview Data Image: " . ( ! empty( $preview_data['image'] ) ? $preview_data['image'] : 'EMPTY' ) . "\n";
	if ( ! empty( $preview_data['image'] ) ) {
		echo "✅ get_preview_data restituisce l'immagine correttamente\n";
	} else {
		echo "⚠️ get_preview_data non restituisce l'immagine (normale se non c'è immagine in evidenza)\n";
	}
	echo "</pre>";
} catch ( Exception $e ) {
	echo "<pre>Error: " . $e->getMessage() . "</pre>";
}

echo "<h2>3. Test Recupero Immagine Diretto</h2>";
echo "<pre>";
$methods = array(
	'get_the_post_thumbnail_url' => get_the_post_thumbnail_url( $post_id, 'full' ),
	'get_post_thumbnail_id + wp_get_attachment_url' => function() use ( $post_id ) {
		$id = get_post_thumbnail_id( $post_id );
		return $id ? wp_get_attachment_url( $id ) : '';
	},
	'get_post_meta + wp_get_attachment_url' => function() use ( $post_id ) {
		$id = get_post_meta( $post_id, '_thumbnail_id', true );
		return $id ? wp_get_attachment_url( (int) $id ) : '';
	},
	'Database query' => function() use ( $post_id ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id' LIMIT 1",
			$post_id
		) );
		return $id ? wp_get_attachment_url( (int) $id ) : '';
	}
);

foreach ( $methods as $method_name => $method_result ) {
	if ( is_callable( $method_result ) ) {
		$result = $method_result();
	} else {
		$result = $method_result;
	}
	echo "{$method_name}: " . ( $result ? $result : 'EMPTY' ) . "\n";
}
echo "</pre>";

echo "<h2>4. Conclusione</h2>";
if ( $thumbnail_id ) {
	echo "<p style='color: green;'><strong>✅ Post ha un'immagine in evidenza. Il codice dovrebbe mostrarla nel metabox SEO.</strong></p>";
} else {
	echo "<p style='color: orange;'><strong>⚠️ Post non ha un'immagine in evidenza. Imposta un'immagine per vedere se viene mostrata nel metabox SEO.</strong></p>";
}

echo "<hr>";
echo "<p><a href='" . admin_url( "post.php?post={$post_id}&action=edit" ) . "'>Vai all'editor del post</a></p>";





