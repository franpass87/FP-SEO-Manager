<?php
/**
 * Trova post/pagine che contengono immagini
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/find-posts-with-images.php
 */

// Carica WordPress
$wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	die( 'ERRORE: Impossibile trovare wp-load.php' );
}
require_once $wp_load_path;

// Assicurati che l'utente sia un amministratore
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Non hai i permessi sufficienti per accedere a questa pagina.' );
}

echo '<h1>FP SEO Manager - Trova Post con Immagini</h1>';
echo '<hr>';

global $wpdb;

// Trova post/pagine che contengono immagini nel contenuto
$posts_with_images = $wpdb->get_results( "
	SELECT ID, post_title, post_type, post_status, 
		   LENGTH(post_content) as content_length,
		   (LENGTH(post_content) - LENGTH(REPLACE(post_content, '<img', ''))) / 4 as img_count,
		   (LENGTH(post_content) - LENGTH(REPLACE(post_content, '[vc_', ''))) / 4 as vc_count
	FROM {$wpdb->posts}
	WHERE post_status = 'publish'
	  AND post_type IN ('post', 'page')
	  AND (
		post_content LIKE '%<img%' OR
		post_content LIKE '%[vc_%' OR
		post_content LIKE '%vc_single_image%' OR
		post_content LIKE '%vc_gallery%'
	  )
	ORDER BY img_count DESC, vc_count DESC
	LIMIT 20
" );

echo '<h2>Post/Pagine con Immagini (max 20)</h2>';
if ( empty( $posts_with_images ) ) {
	echo '<p>Nessun post/pagina trovato con immagini nel contenuto.</p>';
} else {
	echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
	echo '<tr><th>ID</th><th>Titolo</th><th>Tipo</th><th>Status</th><th>Lunghezza</th><th>&lt;img&gt; tags</th><th>WPBakery</th><th>Link</th></tr>';
	foreach ( $posts_with_images as $post ) {
		$edit_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
		$debug_url = admin_url( '../wp-content/plugins/FP-SEO-Manager/debug-images-extraction.php?post_id=' . $post->ID );
		echo '<tr>';
		echo '<td>' . esc_html( $post->ID ) . '</td>';
		echo '<td>' . esc_html( $post->post_title ) . '</td>';
		echo '<td>' . esc_html( $post->post_type ) . '</td>';
		echo '<td>' . esc_html( $post->post_status ) . '</td>';
		echo '<td>' . esc_html( $post->content_length ) . '</td>';
		echo '<td>' . esc_html( $post->img_count ) . '</td>';
		echo '<td>' . esc_html( $post->vc_count ) . '</td>';
		echo '<td><a href="' . esc_url( $edit_url ) . '">Modifica</a> | <a href="' . esc_url( $debug_url ) . '">Debug</a></td>';
		echo '</tr>';
	}
	echo '</table>';
}

// Trova post/pagine con immagine in evidenza
$posts_with_featured = $wpdb->get_results( "
	SELECT p.ID, p.post_title, p.post_type, p.post_status,
		   pm.meta_value as thumbnail_id
	FROM {$wpdb->posts} p
	INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
	WHERE p.post_status = 'publish'
	  AND p.post_type IN ('post', 'page')
	  AND pm.meta_key = '_thumbnail_id'
	LIMIT 20
" );

echo '<hr>';
echo '<h2>Post/Pagine con Immagine in Evidenza (max 20)</h2>';
if ( empty( $posts_with_featured ) ) {
	echo '<p>Nessun post/pagina trovato con immagine in evidenza.</p>';
} else {
	echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
	echo '<tr><th>ID</th><th>Titolo</th><th>Tipo</th><th>Status</th><th>Thumbnail ID</th><th>Link</th></tr>';
	foreach ( $posts_with_featured as $post ) {
		$edit_url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
		$debug_url = admin_url( '../wp-content/plugins/FP-SEO-Manager/debug-images-extraction.php?post_id=' . $post->ID );
		echo '<tr>';
		echo '<td>' . esc_html( $post->ID ) . '</td>';
		echo '<td>' . esc_html( $post->post_title ) . '</td>';
		echo '<td>' . esc_html( $post->post_type ) . '</td>';
		echo '<td>' . esc_html( $post->post_status ) . '</td>';
		echo '<td>' . esc_html( $post->thumbnail_id ) . '</td>';
		echo '<td><a href="' . esc_url( $edit_url ) . '">Modifica</a> | <a href="' . esc_url( $debug_url ) . '">Debug</a></td>';
		echo '</tr>';
	}
	echo '</table>';
}

echo '<hr>';
echo '<p><a href="' . admin_url() . '">← Torna alla Dashboard</a></p>';
?>

