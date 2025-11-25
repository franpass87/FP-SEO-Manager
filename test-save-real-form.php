<?php
/**
 * Test Salvataggio Reale - Simula submit form
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-real-form.php?post_id=441
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-real-form.php?post_id=441\n" );
}

// Carica WordPress
$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : dirname( dirname( dirname( dirname( __FILE__ ) ) ) );

$wp_load_paths = array(
	$document_root . '/wp-load.php',
	dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
	__DIR__ . '/../../../../wp-load.php',
);

$current_file = __FILE__;
if ( strpos( $current_file, 'wp-content' ) !== false ) {
	$parts = explode( 'wp-content', $current_file );
	$wp_root = dirname( $parts[0] );
	$wp_load_paths[] = $wp_root . '/wp-load.php';
}

$wp_load = null;
foreach ( $wp_load_paths as $path ) {
	$path = str_replace( '\\', '/', $path );
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato.<br>DOCUMENT_ROOT: " . $document_root . "<br>__FILE__: " . __FILE__ );
}

require_once $wp_load;

// Verifica admin
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( '⛔ Devi essere loggato come amministratore. <a href="' . wp_login_url( $_SERVER['REQUEST_URI'] ) . '">Accedi qui</a>' );
}

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Salvataggio Reale Form - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #bee5eb; }
		.test-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
		.test-section h3 { margin-top: 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		table th { background: #f8f9fa; font-weight: bold; }
		.btn { display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
		.btn:hover { background: #005a87; }
		.btn-success { background: #28a745; }
	</style>
</head>
<body>
<div class="container">
	<h1>🎯 Test Salvataggio Reale - Simula Submit Form</h1>
	
	<?php
	$test_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;
	$test_post = get_post( $test_post_id );
	
	if ( ! $test_post ) {
		echo '<div class="error">❌ Post ID ' . $test_post_id . ' non trovato</div>';
		echo '</div></body></html>';
		exit;
	}
	
	// Pulisci cache
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	if ( function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( array( $test_post_id ) );
	}
	
	// Leggi valori attuali
	$current_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$current_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	$current_keyword = get_post_meta( $test_post_id, '_fp_seo_focus_keyword', true );
	
	echo '<div class="test-section">';
	echo '<h3>📋 Valori Attuali nel Database</h3>';
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore</th></tr>';
	echo '<tr><td>SEO Title</td><td>' . ( $current_title ? esc_html( $current_title ) : '<em>vuoto</em>' ) . '</td></tr>';
	echo '<tr><td>Meta Description</td><td>' . ( $current_desc ? esc_html( $current_desc ) : '<em>vuoto</em>' ) . '</td></tr>';
	echo '<tr><td>Focus Keyword</td><td>' . ( $current_keyword ? esc_html( $current_keyword ) : '<em>vuoto</em>' ) . '</td></tr>';
	echo '</table>';
	echo '</div>';
	
	// Test salvataggio simulando POST
	if ( isset( $_POST['test_save'] ) ) {
		echo '<div class="test-section">';
		echo '<h3>🔄 Test Salvataggio Simulato (come da form submit)</h3>';
		
		$test_title = sanitize_text_field( $_POST['test_title'] );
		$test_desc = sanitize_textarea_field( $_POST['test_desc'] );
		$test_keyword = sanitize_text_field( $_POST['test_keyword'] );
		
		// Simula $_POST esattamente come fa WordPress quando si fa "Aggiorna"
		$_POST['fp_seo_title'] = $test_title;
		$_POST['fp_seo_title_sent'] = '1';
		$_POST['fp_seo_meta_description'] = $test_desc;
		$_POST['fp_seo_meta_description_sent'] = '1';
		$_POST['fp_seo_focus_keyword'] = $test_keyword;
		$_POST['fp_seo_performance_metabox_present'] = '1';
		
		// Log POST data
		error_log( 'FP SEO TEST: Simulating form submit - POST keys: ' . implode( ', ', array_keys( $_POST ) ) );
		error_log( 'FP SEO TEST: fp_seo_title = ' . $test_title );
		error_log( 'FP SEO TEST: fp_seo_meta_description = ' . $test_desc );
		error_log( 'FP SEO TEST: fp_seo_focus_keyword = ' . $test_keyword );
		error_log( 'FP SEO TEST: fp_seo_performance_metabox_present = ' . ( isset( $_POST['fp_seo_performance_metabox_present'] ) ? $_POST['fp_seo_performance_metabox_present'] : 'NOT SET' ) );
		
		try {
			$saver = new \FP\SEO\Editor\MetaboxSaver();
			$result = $saver->save_all_fields( $test_post_id );
			
			if ( $result ) {
				echo '<div class="success">✅ Salvataggio completato con successo!</div>';
				
				// Pulisci cache e rileggi
				clean_post_cache( $test_post_id );
				wp_cache_delete( $test_post_id, 'post_meta' );
				if ( function_exists( 'update_post_meta_cache' ) ) {
					update_post_meta_cache( array( $test_post_id ) );
				}
				
				$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
				$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
				$saved_keyword = get_post_meta( $test_post_id, '_fp_seo_focus_keyword', true );
				
				$title_match = ( $saved_title === $test_title );
				$desc_match = ( $saved_desc === $test_desc );
				$keyword_match = ( $saved_keyword === $test_keyword );
				
				if ( $title_match && $desc_match && $keyword_match ) {
					echo '<div class="success">✅ Tutti i valori sono stati salvati correttamente nel database!</div>';
					echo '<table>';
					echo '<tr><th>Campo</th><th>Valore Salvato</th><th>Risultato</th></tr>';
					echo '<tr><td>SEO Title</td><td>' . esc_html( $saved_title ) . '</td><td style="color: green;">✅ OK</td></tr>';
					echo '<tr><td>Meta Description</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: green;">✅ OK</td></tr>';
					echo '<tr><td>Focus Keyword</td><td>' . esc_html( $saved_keyword ) . '</td><td style="color: green;">✅ OK</td></tr>';
					echo '</table>';
				} else {
					echo '<div class="error">❌ Alcuni valori non corrispondono!</div>';
					echo '<table>';
					echo '<tr><th>Campo</th><th>Valore Atteso</th><th>Valore Salvato</th><th>Risultato</th></tr>';
					echo '<tr><td>SEO Title</td><td>' . esc_html( $test_title ) . '</td><td>' . esc_html( $saved_title ) . '</td><td style="color: ' . ( $title_match ? 'green' : 'red' ) . ';">' . ( $title_match ? '✅' : '❌' ) . '</td></tr>';
					echo '<tr><td>Meta Description</td><td>' . esc_html( $test_desc ) . '</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: ' . ( $desc_match ? 'green' : 'red' ) . ';">' . ( $desc_match ? '✅' : '❌' ) . '</td></tr>';
					echo '<tr><td>Focus Keyword</td><td>' . esc_html( $test_keyword ) . '</td><td>' . esc_html( $saved_keyword ) . '</td><td style="color: ' . ( $keyword_match ? 'green' : 'red' ) . ';">' . ( $keyword_match ? '✅' : '❌' ) . '</td></tr>';
					echo '</table>';
				}
			} else {
				echo '<div class="error">❌ Salvataggio fallito (save_all_fields ritornato FALSE)</div>';
			}
		} catch ( \Exception $e ) {
			echo '<div class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</div>';
			echo '<pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
		}
		
		echo '</div>';
	}
	
	// Verifica log recenti
	echo '<div class="test-section">';
	echo '<h3>📝 Log Recenti</h3>';
	
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$log_lines = file( $log_file );
		$recent_logs = array_slice( $log_lines, -100 );
		$fp_seo_logs = array_filter( $recent_logs, function( $line ) {
			return strpos( $line, 'FP SEO' ) !== false;
		} );
		
		if ( ! empty( $fp_seo_logs ) ) {
			echo '<div class="info">ℹ️ Trovati ' . count( $fp_seo_logs ) . ' log FP SEO recenti</div>';
			echo '<pre>' . esc_html( implode( '', array_slice( $fp_seo_logs, -20 ) ) ) . '</pre>';
		} else {
			echo '<div class="info">ℹ️ Nessun log FP SEO trovato negli ultimi 100 log</div>';
		}
	} else {
		echo '<div class="info">ℹ️ File debug.log non trovato</div>';
	}
	
	echo '</div>';
	
	// Form per test
	echo '<div class="test-section">';
	echo '<h3>🧪 Test Salvataggio Manuale</h3>';
	echo '<form method="post">';
	echo '<p><label>SEO Title:<br><input type="text" name="test_title" value="Test Form Submit ' . time() . '" style="width: 100%; padding: 8px;"></label></p>';
	echo '<p><label>Meta Description:<br><textarea name="test_desc" style="width: 100%; padding: 8px; min-height: 100px;">Descrizione test form submit ' . time() . '</textarea></label></p>';
	echo '<p><label>Focus Keyword:<br><input type="text" name="test_keyword" value="keyword test ' . time() . '" style="width: 100%; padding: 8px;"></label></p>';
	echo '<p><button type="submit" name="test_save" class="btn btn-success">💾 Salva e Verifica</button></p>';
	echo '</form>';
	echo '</div>';
	
	echo '<p>';
	echo '<a href="' . admin_url( 'post.php?post=' . $test_post_id . '&action=edit' ) . '" class="btn btn-success">Modifica Post nell\'Editor</a> ';
	echo '<a href="?post_id=' . $test_post_id . '" class="btn">Ricarica</a>';
	echo '</p>';
	?>
	
</div>
</body>
</html>


