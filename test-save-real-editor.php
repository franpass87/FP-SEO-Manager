<?php
/**
 * Test salvataggio reale nell'editor WordPress
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-real-editor.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-real-editor.php\n" );
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
	<title>Test Salvataggio Reale - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #bee5eb; }
		.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #ffeaa7; }
		.test-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
		.test-section h3 { margin-top: 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
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
	<h1>🧪 Test Salvataggio Reale nell'Editor</h1>
	
	<?php
	$tests_passed = 0;
	$tests_failed = 0;
	
	// Test 1: Verifica post di test
	echo '<div class="test-section">';
	echo '<h3>Test 1: Post di Test</h3>';
	
	$test_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;
	$test_post = get_post( $test_post_id );
	
	if ( ! $test_post ) {
		echo '<div class="error">❌ Post ID ' . $test_post_id . ' non trovato</div>';
		$tests_failed++;
	} else {
		echo '<div class="success">✅ Post trovato: ID ' . $test_post_id . ' - "' . esc_html( $test_post->post_title ) . '"</div>';
		$tests_passed++;
	}
	echo '</div>';
	
	if ( ! $test_post ) {
		echo '</div></body></html>';
		exit;
	}
	
	// Test 2: Leggi valori attuali
	echo '<div class="test-section">';
	echo '<h3>Test 2: Valori Attuali nel Database</h3>';
	
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	$current_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$current_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore Attuale</th></tr>';
	echo '<tr><td>SEO Title</td><td>' . ( $current_title ? esc_html( $current_title ) : '<em>(vuoto)</em>' ) . '</td></tr>';
	echo '<tr><td>Meta Description</td><td>' . ( $current_desc ? esc_html( $current_desc ) : '<em>(vuoto)</em>' ) . '</td></tr>';
	echo '</table>';
	echo '</div>';
	
	// Test 3: Simula salvataggio con valori nuovi
	echo '<div class="test-section">';
	echo '<h3>Test 3: Simulazione Salvataggio con Valori Nuovi</h3>';
	
	$new_seo_title = 'Test Reale SEO Title ' . time();
	$new_meta_desc = 'Test Reale Meta Description ' . time();
	
	echo '<div class="info">ℹ️ Nuovi valori:<br>';
	echo 'SEO Title: <strong>' . esc_html( $new_seo_title ) . '</strong><br>';
	echo 'Meta Description: <strong>' . esc_html( $new_meta_desc ) . '</strong></div>';
	
	// Simula $_POST
	$_POST['fp_seo_title'] = $new_seo_title;
	$_POST['fp_seo_title_sent'] = '1';
	$_POST['fp_seo_meta_description'] = $new_meta_desc;
	$_POST['fp_seo_meta_description_sent'] = '1';
	$_POST['fp_seo_performance_metabox_present'] = '1';
	
	// Salva
	try {
		$saver = new \FP\SEO\Editor\MetaboxSaver();
		$result = $saver->save_all_fields( $test_post_id );
		
		if ( $result ) {
			echo '<div class="success">✅ Salvataggio completato con successo</div>';
			$tests_passed++;
		} else {
			echo '<div class="error">❌ Salvataggio fallito</div>';
			$tests_failed++;
		}
	} catch ( \Exception $e ) {
		echo '<div class="error">❌ Errore durante salvataggio: ' . esc_html( $e->getMessage() ) . '</div>';
		echo '<pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
		$tests_failed++;
	}
	
	// Pulisci cache
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	if ( function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( array( $test_post_id ) );
	}
	
	echo '</div>';
	
	// Test 4: Verifica che i valori siano stati salvati
	echo '<div class="test-section">';
	echo '<h3>Test 4: Verifica Salvataggio</h3>';
	
	// Attendi un attimo e rileggi
	sleep( 1 );
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore Atteso</th><th>Valore Salvato</th><th>Risultato</th></tr>';
	
	// Verifica Title
	if ( $saved_title === $new_seo_title ) {
		echo '<tr><td>SEO Title</td><td>' . esc_html( $new_seo_title ) . '</td><td>' . esc_html( $saved_title ) . '</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
	} else {
		echo '<tr><td>SEO Title</td><td>' . esc_html( $new_seo_title ) . '</td><td>' . esc_html( $saved_title ) . '</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
	}
	
	// Verifica Description
	if ( $saved_desc === $new_meta_desc ) {
		echo '<tr><td>Meta Description</td><td>' . esc_html( $new_meta_desc ) . '</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
	} else {
		echo '<tr><td>Meta Description</td><td>' . esc_html( $new_meta_desc ) . '</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
	}
	
	echo '</table>';
	echo '</div>';
	
	// Test 5: Verifica persistenza (rilegge dopo pulizia cache completa)
	echo '<div class="test-section">';
	echo '<h3>Test 5: Verifica Persistenza</h3>';
	
	// Pulisci TUTTA la cache
	wp_cache_flush();
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	// Rileggi
	$persisted_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$persisted_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	if ( $persisted_title === $new_seo_title && $persisted_desc === $new_meta_desc ) {
		echo '<div class="success">✅ I dati persistono correttamente anche dopo pulizia cache completa</div>';
		$tests_passed++;
	} else {
		echo '<div class="error">❌ I dati non persistono correttamente</div>';
		echo '<p>Title atteso: ' . esc_html( $new_seo_title ) . '</p>';
		echo '<p>Title letto: ' . esc_html( $persisted_title ) . '</p>';
		echo '<p>Desc attesa: ' . esc_html( $new_meta_desc ) . '</p>';
		echo '<p>Desc letta: ' . esc_html( $persisted_desc ) . '</p>';
		$tests_failed++;
	}
	echo '</div>';
	
	// Riepilogo
	echo '<div class="test-section">';
	echo '<h2>📊 Riepilogo Test</h2>';
	echo '<table>';
	echo '<tr><th>Test</th><th>Risultato</th></tr>';
	echo '<tr><td>Test Passati</td><td style="color: green; font-weight: bold;">' . $tests_passed . '</td></tr>';
	echo '<tr><td>Test Falliti</td><td style="color: red; font-weight: bold;">' . $tests_failed . '</td></tr>';
	echo '<tr><td>Totale</td><td><strong>' . ( $tests_passed + $tests_failed ) . '</strong></td></tr>';
	echo '</table>';
	
	if ( $tests_failed === 0 ) {
		echo '<div class="success">';
		echo '<h3>✅ TUTTI I TEST SONO PASSATI!</h3>';
		echo '<p>Il salvataggio dei campi SEO funziona perfettamente e i dati persistono correttamente nel database.</p>';
		echo '</div>';
	} else {
		echo '<div class="error">';
		echo '<h3>❌ ALCUNI TEST SONO FALLITI</h3>';
		echo '<p>Controlla i dettagli sopra per identificare i problemi.</p>';
		echo '</div>';
	}
	
	echo '<p>';
	echo '<a href="' . admin_url( 'post.php?post=' . $test_post_id . '&action=edit' ) . '" class="btn btn-success">Modifica Post nell\'Editor</a> ';
	echo '<a href="?post_id=' . $test_post_id . '" class="btn">Rilancia Test</a>';
	echo '</p>';
	echo '</div>';
	?>
	
</div>
</body>
</html>

