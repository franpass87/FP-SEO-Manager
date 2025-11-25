<?php
/**
 * Test Debug Meta Load - Verifica perché i meta non vengono caricati
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-debug-meta-load.php?post_id=441
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser\n" );
}

// Carica WordPress
$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
$wp_load = $document_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato" );
}

require_once $wp_load;

// Verifica admin
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( '⛔ Devi essere loggato come amministratore' );
}

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Debug Meta Load - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		table th { background: #f8f9fa; }
		.meta-value { max-width: 500px; word-wrap: break-word; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Debug Meta Load - Post ID: <?php echo esc_html( $post_id ); ?></h1>
	
	<?php
	$post = get_post( $post_id );
	if ( ! $post ) {
		echo '<div class="error">❌ Post non trovato!</div>';
		exit;
	}
	
	echo '<div class="info">ℹ️ Post: <strong>' . esc_html( $post->post_title ) . '</strong> (ID: ' . $post_id . ')</div>';
	
	// Test 1: Lettura diretta senza cache clear
	echo '<h3>📊 Test 1: Lettura Diretta (senza cache clear)</h3>';
	$seo_title_1 = get_post_meta( $post_id, '_fp_seo_title', true );
	$meta_desc_1 = get_post_meta( $post_id, '_fp_seo_meta_description', true );
	$focus_keyword_1 = get_post_meta( $post_id, '_fp_seo_focus_keyword', true );
	$secondary_keywords_1 = get_post_meta( $post_id, '_fp_seo_secondary_keywords', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore</th><th>Tipo</th></tr>';
	echo '<tr><td>_fp_seo_title</td><td class="meta-value">' . esc_html( $seo_title_1 ?: '(vuoto)' ) . '</td><td>' . gettype( $seo_title_1 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_meta_description</td><td class="meta-value">' . esc_html( $meta_desc_1 ?: '(vuoto)' ) . '</td><td>' . gettype( $meta_desc_1 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_focus_keyword</td><td class="meta-value">' . esc_html( $focus_keyword_1 ?: '(vuoto)' ) . '</td><td>' . gettype( $focus_keyword_1 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_secondary_keywords</td><td class="meta-value">' . esc_html( is_array( $secondary_keywords_1 ) ? implode( ', ', $secondary_keywords_1 ) : ( $secondary_keywords_1 ?: '(vuoto)' ) ) . '</td><td>' . gettype( $secondary_keywords_1 ) . '</td></tr>';
	echo '</table>';
	
	// Test 2: Con cache clear
	echo '<h3>📊 Test 2: Con Cache Clear</h3>';
	clean_post_cache( $post_id );
	wp_cache_delete( $post_id, 'post_meta' );
	if ( function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( array( $post_id ) );
	}
	
	$seo_title_2 = get_post_meta( $post_id, '_fp_seo_title', true );
	$meta_desc_2 = get_post_meta( $post_id, '_fp_seo_meta_description', true );
	$focus_keyword_2 = get_post_meta( $post_id, '_fp_seo_focus_keyword', true );
	$secondary_keywords_2 = get_post_meta( $post_id, '_fp_seo_secondary_keywords', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore</th><th>Tipo</th></tr>';
	echo '<tr><td>_fp_seo_title</td><td class="meta-value">' . esc_html( $seo_title_2 ?: '(vuoto)' ) . '</td><td>' . gettype( $seo_title_2 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_meta_description</td><td class="meta-value">' . esc_html( $meta_desc_2 ?: '(vuoto)' ) . '</td><td>' . gettype( $meta_desc_2 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_focus_keyword</td><td class="meta-value">' . esc_html( $focus_keyword_2 ?: '(vuoto)' ) . '</td><td>' . gettype( $focus_keyword_2 ) . '</td></tr>';
	echo '<tr><td>_fp_seo_secondary_keywords</td><td class="meta-value">' . esc_html( is_array( $secondary_keywords_2 ) ? implode( ', ', $secondary_keywords_2 ) : ( $secondary_keywords_2 ?: '(vuoto)' ) ) . '</td><td>' . gettype( $secondary_keywords_2 ) . '</td></tr>';
	echo '</table>';
	
	// Test 3: Query diretta al database
	echo '<h3>📊 Test 3: Query Diretta Database</h3>';
	global $wpdb;
	$meta_results = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN (%s, %s, %s, %s)",
		$post_id,
		'_fp_seo_title',
		'_fp_seo_meta_description',
		'_fp_seo_focus_keyword',
		'_fp_seo_secondary_keywords'
	) );
	
	echo '<table>';
	echo '<tr><th>Meta Key</th><th>Valore (DB)</th></tr>';
	if ( ! empty( $meta_results ) ) {
		foreach ( $meta_results as $meta ) {
			$value = $meta->meta_value;
			if ( $meta->meta_key === '_fp_seo_secondary_keywords' ) {
				$unserialized = maybe_unserialize( $value );
				if ( is_array( $unserialized ) ) {
					$value = implode( ', ', $unserialized );
				}
			}
			echo '<tr><td>' . esc_html( $meta->meta_key ) . '</td><td class="meta-value">' . esc_html( $value ?: '(vuoto)' ) . '</td></tr>';
		}
	} else {
		echo '<tr><td colspan="2">Nessun meta trovato nel database</td></tr>';
	}
	echo '</table>';
	
	// Test 4: Verifica se i valori sono serializzati
	if ( ! empty( $meta_results ) ) {
		echo '<h3>📊 Test 4: Verifica Serializzazione</h3>';
		echo '<table>';
		echo '<tr><th>Meta Key</th><th>Valore Raw</th><th>Serializzato?</th></tr>';
		foreach ( $meta_results as $meta ) {
			$is_serialized = is_serialized( $meta->meta_value );
			$unserialized = $is_serialized ? maybe_unserialize( $meta->meta_value ) : $meta->meta_value;
			echo '<tr>';
			echo '<td>' . esc_html( $meta->meta_key ) . '</td>';
			echo '<td class="meta-value"><pre>' . esc_html( substr( $meta->meta_value, 0, 200 ) ) . '</pre></td>';
			echo '<td>' . ( $is_serialized ? '✅ Sì' : '❌ No' ) . '</td>';
			echo '</tr>';
			if ( $is_serialized ) {
				echo '<tr><td colspan="3"><strong>Valore unserializzato:</strong> ' . esc_html( is_array( $unserialized ) ? implode( ', ', $unserialized ) : $unserialized ) . '</td></tr>';
			}
		}
		echo '</table>';
	}
	?>
	
</div>
</body>
</html>


