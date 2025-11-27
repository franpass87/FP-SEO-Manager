<?php
/**
 * Mostra il contenuto completo di un post per debug
 * 
 * Accesso: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/show-full-content.php?post_id=131
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

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
if ( $post_id <= 0 ) {
	wp_die( 'Specifica un post_id valido: ?post_id=131' );
}

$post = get_post( $post_id );
if ( ! $post ) {
	wp_die( 'Post non trovato.' );
}

global $wpdb;
$db_content = $wpdb->get_var( $wpdb->prepare(
	"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
	$post_id
) );

echo '<h1>Contenuto Completo - Post ID: ' . esc_html( $post_id ) . '</h1>';
echo '<h2>' . esc_html( $post->post_title ) . '</h2>';
echo '<hr>';

echo '<h3>Contenuto dal Database (lunghezza: ' . strlen( $db_content ) . ' caratteri)</h3>';
echo '<pre style="background: #f5f5f5; padding: 20px; border: 1px solid #ddd; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">';
echo esc_html( $db_content );
echo '</pre>';

echo '<hr>';
echo '<h3>Analisi Shortcode WPBakery</h3>';

// Estrai tutti gli shortcode WPBakery
preg_match_all( '/\[vc_\w+.*?\]/is', $db_content, $all_shortcodes, PREG_SET_ORDER );
echo '<p><strong>Shortcode WPBakery totali trovati:</strong> ' . count( $all_shortcodes ) . '</p>';

// Cerca attributi immagine in tutti gli shortcode
$image_attrs = array( 'image', 'bg_image', 'background_image', 'images', 'bg_image_url', 'image_url' );
$found_images = array();

foreach ( $all_shortcodes as $shortcode_match ) {
	$shortcode = $shortcode_match[0];
	$shortcode_name = '';
	if ( preg_match( '/\[(vc_\w+)/i', $shortcode, $name_match ) ) {
		$shortcode_name = $name_match[1];
	}
	
	foreach ( $image_attrs as $attr ) {
		$pattern = '/' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\']/i';
		if ( preg_match( $pattern, $shortcode, $attr_match ) ) {
			$found_images[] = array(
				'shortcode' => $shortcode_name,
				'attr' => $attr,
				'value' => $attr_match[1],
				'full_shortcode' => substr( $shortcode, 0, 500 ),
			);
		}
	}
}

echo '<p><strong>Attributi immagine trovati:</strong> ' . count( $found_images ) . '</p>';
if ( ! empty( $found_images ) ) {
	echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
	echo '<tr><th>Shortcode</th><th>Attributo</th><th>Valore</th><th>Anteprima Shortcode</th></tr>';
	foreach ( $found_images as $found ) {
		echo '<tr>';
		echo '<td>' . esc_html( $found['shortcode'] ) . '</td>';
		echo '<td>' . esc_html( $found['attr'] ) . '</td>';
		echo '<td><code>' . esc_html( $found['value'] ) . '</code></td>';
		echo '<td><small>' . esc_html( $found['full_shortcode'] ) . '</small></td>';
		echo '</tr>';
	}
	echo '</table>';
} else {
	echo '<p style="color: red;"><strong>Nessun attributo immagine trovato negli shortcode WPBakery!</strong></p>';
	echo '<p>Questo significa che le immagini potrebbero essere:</p>';
	echo '<ul>';
	echo '<li>In shortcode annidati (dentro vc_row/vc_column)</li>';
	echo '<li>In attributi con nomi diversi</li>';
	echo '<li>Renderizzate solo nel frontend (non nel contenuto raw)</li>';
	echo '</ul>';
}

echo '<hr>';
echo '<p><a href="' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . '">← Torna all\'editor</a></p>';
?>

