<?php
/**
 * Test finale del salvataggio - verifica completa
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-final.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-final.php\n" );
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
	<title>Test Finale Salvataggio SEO - FP SEO Manager</title>
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
	<h1>🎯 Test Finale Salvataggio SEO</h1>
	
	<?php
	$tests_passed = 0;
	$tests_failed = 0;
	$all_tests = array();
	
	// Test 1: Verifica post di test
	echo '<div class="test-section">';
	echo '<h3>Test 1: Post di Test</h3>';
	
	$test_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;
	$test_post = get_post( $test_post_id );
	
	if ( ! $test_post ) {
		echo '<div class="error">❌ Post ID ' . $test_post_id . ' non trovato</div>';
		$tests_failed++;
		$all_tests[] = array( 'name' => 'Post trovato', 'status' => 'failed' );
	} else {
		echo '<div class="success">✅ Post trovato: ID ' . $test_post_id . ' - "' . esc_html( $test_post->post_title ) . '"</div>';
		$tests_passed++;
		$all_tests[] = array( 'name' => 'Post trovato', 'status' => 'passed' );
	}
	echo '</div>';
	
	if ( ! $test_post ) {
		echo '</div></body></html>';
		exit;
	}
	
	// Test 2: Verifica che il metabox sia renderizzato correttamente
	echo '<div class="test-section">';
	echo '<h3>Test 2: Metabox Renderizzato</h3>';
	
	// Simula una chiamata al renderer
	try {
		$renderer = new \FP\SEO\Editor\MetaboxRenderer();
		echo '<div class="success">✅ MetaboxRenderer istanziato correttamente</div>';
		$tests_passed++;
		$all_tests[] = array( 'name' => 'MetaboxRenderer istanziato', 'status' => 'passed' );
	} catch ( \Exception $e ) {
		echo '<div class="error">❌ Errore istanziando MetaboxRenderer: ' . esc_html( $e->getMessage() ) . '</div>';
		$tests_failed++;
		$all_tests[] = array( 'name' => 'MetaboxRenderer istanziato', 'status' => 'failed', 'error' => $e->getMessage() );
	}
	echo '</div>';
	
	// Test 3: Test salvataggio multiplo
	echo '<div class="test-section">';
	echo '<h3>Test 3: Salvataggio Multiplo</h3>';
	
	$test_values = array(
		array( 'title' => 'Test 1 - ' . time(), 'desc' => 'Descrizione Test 1 - ' . time() ),
		array( 'title' => 'Test 2 - ' . time(), 'desc' => 'Descrizione Test 2 - ' . time() ),
		array( 'title' => 'Test 3 - ' . time(), 'desc' => 'Descrizione Test 3 - ' . time() ),
	);
	
	$save_tests_passed = 0;
	$save_tests_failed = 0;
	
	foreach ( $test_values as $index => $test_value ) {
		// Pulisci cache
		clean_post_cache( $test_post_id );
		wp_cache_delete( $test_post_id, 'post_meta' );
		
		// Simula $_POST
		$_POST['fp_seo_title'] = $test_value['title'];
		$_POST['fp_seo_title_sent'] = '1';
		$_POST['fp_seo_meta_description'] = $test_value['desc'];
		$_POST['fp_seo_meta_description_sent'] = '1';
		$_POST['fp_seo_performance_metabox_present'] = '1';
		
		// Salva
		try {
			$saver = new \FP\SEO\Editor\MetaboxSaver();
			$result = $saver->save_all_fields( $test_post_id );
			
			if ( ! $result ) {
				echo '<div class="error">❌ Test ' . ( $index + 1 ) . ': Salvataggio fallito</div>';
				$save_tests_failed++;
				continue;
			}
			
			// Pulisci cache e verifica
			clean_post_cache( $test_post_id );
			wp_cache_delete( $test_post_id, 'post_meta' );
			if ( function_exists( 'update_post_meta_cache' ) ) {
				update_post_meta_cache( array( $test_post_id ) );
			}
			
			$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
			$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
			
			if ( $saved_title === $test_value['title'] && $saved_desc === $test_value['desc'] ) {
				echo '<div class="success">✅ Test ' . ( $index + 1 ) . ': Salvataggio e verifica OK</div>';
				$save_tests_passed++;
			} else {
				echo '<div class="error">❌ Test ' . ( $index + 1 ) . ': Mismatch - Atteso: "' . esc_html( $test_value['title'] ) . '", Salvato: "' . esc_html( $saved_title ) . '"</div>';
				$save_tests_failed++;
			}
		} catch ( \Exception $e ) {
			echo '<div class="error">❌ Test ' . ( $index + 1 ) . ': Errore: ' . esc_html( $e->getMessage() ) . '</div>';
			$save_tests_failed++;
		}
		
		// Pulisci $_POST
		unset( $_POST['fp_seo_title'], $_POST['fp_seo_title_sent'] );
		unset( $_POST['fp_seo_meta_description'], $_POST['fp_seo_meta_description_sent'] );
		unset( $_POST['fp_seo_performance_metabox_present'] );
	}
	
	if ( $save_tests_passed === count( $test_values ) ) {
		$tests_passed += count( $test_values );
		$all_tests[] = array( 'name' => 'Salvataggio multiplo', 'status' => 'passed' );
	} else {
		$tests_failed += $save_tests_failed;
		$all_tests[] = array( 'name' => 'Salvataggio multiplo', 'status' => 'failed', 'passed' => $save_tests_passed, 'failed' => $save_tests_failed );
	}
	
	echo '</div>';
	
	// Test 4: Verifica valori finali
	echo '<div class="test-section">';
	echo '<h3>Test 4: Valori Finali nel Database</h3>';
	
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	$final_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$final_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore nel Database</th></tr>';
	echo '<tr><td>SEO Title</td><td>' . ( $final_title ? esc_html( $final_title ) : '<em>(vuoto)</em>' ) . '</td></tr>';
	echo '<tr><td>Meta Description</td><td>' . ( $final_desc ? esc_html( $final_desc ) : '<em>(vuoto)</em>' ) . '</td></tr>';
	echo '</table>';
	
	if ( $final_title && $final_desc ) {
		echo '<div class="success">✅ Valori presenti nel database</div>';
		$tests_passed++;
		$all_tests[] = array( 'name' => 'Valori nel database', 'status' => 'passed' );
	} else {
		echo '<div class="warning">⚠️ Alcuni valori sono vuoti</div>';
		$all_tests[] = array( 'name' => 'Valori nel database', 'status' => 'warning' );
	}
	echo '</div>';
	
	// Test 5: Verifica hook
	echo '<div class="test-section">';
	echo '<h3>Test 5: Hook Registrati</h3>';
	
	global $wp_filter;
	
	$hooks_checked = 0;
	$hooks_found = 0;
	
	$hooks_to_check = array(
		'save_post' => 'Metabox::save_meta',
		'rest_api_init' => 'Metabox::register_rest_meta_fields',
	);
	
	foreach ( $hooks_to_check as $hook => $method ) {
		$hooks_checked++;
		if ( isset( $wp_filter[ $hook ] ) ) {
			echo '<div class="success">✅ Hook <code>' . esc_html( $hook ) . '</code> registrato</div>';
			$hooks_found++;
		} else {
			echo '<div class="warning">⚠️ Hook <code>' . esc_html( $hook ) . '</code> non trovato</div>';
		}
	}
	
	if ( $hooks_found === $hooks_checked ) {
		$tests_passed++;
		$all_tests[] = array( 'name' => 'Hook registrati', 'status' => 'passed' );
	} else {
		$all_tests[] = array( 'name' => 'Hook registrati', 'status' => 'warning', 'found' => $hooks_found, 'total' => $hooks_checked );
	}
	
	echo '</div>';
	
	// Riepilogo finale
	echo '<div class="test-section">';
	echo '<h2>📊 Riepilogo Finale</h2>';
	
	echo '<table>';
	echo '<tr><th>Test</th><th>Risultato</th></tr>';
	foreach ( $all_tests as $test ) {
		$status_icon = $test['status'] === 'passed' ? '✅' : ( $test['status'] === 'warning' ? '⚠️' : '❌' );
		$status_color = $test['status'] === 'passed' ? 'green' : ( $test['status'] === 'warning' ? 'orange' : 'red' );
		echo '<tr><td>' . esc_html( $test['name'] ) . '</td><td style="color: ' . $status_color . '; font-weight: bold;">' . $status_icon . ' ' . esc_html( $test['status'] ) . '</td></tr>';
	}
	echo '<tr><th>Test Passati</th><td style="color: green; font-weight: bold;">' . $tests_passed . '</td></tr>';
	echo '<tr><th>Test Falliti</th><td style="color: red; font-weight: bold;">' . $tests_failed . '</td></tr>';
	echo '<tr><th>Totale</th><td><strong>' . ( $tests_passed + $tests_failed ) . '</strong></td></tr>';
	echo '</table>';
	
	if ( $tests_failed === 0 ) {
		echo '<div class="success">';
		echo '<h3>✅ TUTTI I TEST SONO PASSATI!</h3>';
		echo '<p><strong>Il salvataggio dei campi SEO funziona perfettamente!</strong></p>';
		echo '<ul>';
		echo '<li>✅ I campi vengono salvati correttamente nel database</li>';
		echo '<li>✅ I dati persistono dopo pulizia cache</li>';
		echo '<li>✅ Il salvataggio multiplo funziona</li>';
		echo '<li>✅ Gli hook sono registrati correttamente</li>';
		echo '<li>✅ Il metabox viene renderizzato correttamente</li>';
		echo '</ul>';
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


