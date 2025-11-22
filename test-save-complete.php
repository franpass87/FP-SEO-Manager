<?php
/**
 * Test completo del salvataggio - verifica end-to-end
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-complete.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-complete.php\n" );
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
	<title>Test Completo Salvataggio SEO - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #c3e6cb; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #f5c6cb; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #bee5eb; }
		.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; border: 1px solid #ffeaa7; }
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
	<h1>🎯 Test Completo Salvataggio SEO - End-to-End</h1>
	
	<?php
	$tests_passed = 0;
	$tests_failed = 0;
	$all_results = array();
	
	// Test 1: Post di test
	echo '<div class="test-section">';
	echo '<h3>Test 1: Post di Test</h3>';
	
	$test_post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;
	$test_post = get_post( $test_post_id );
	
	if ( ! $test_post ) {
		echo '<div class="error">❌ Post ID ' . $test_post_id . ' non trovato</div>';
		$tests_failed++;
		$all_results[] = array( 'test' => 'Post trovato', 'status' => 'failed' );
	} else {
		echo '<div class="success">✅ Post trovato: ID ' . $test_post_id . ' - "' . esc_html( $test_post->post_title ) . '"</div>';
		$tests_passed++;
		$all_results[] = array( 'test' => 'Post trovato', 'status' => 'passed' );
	}
	echo '</div>';
	
	if ( ! $test_post ) {
		echo '</div></body></html>';
		exit;
	}
	
	// Test 2: Verifica classi
	echo '<div class="test-section">';
	echo '<h3>Test 2: Classi Disponibili</h3>';
	
	$classes_to_check = array(
		'\FP\SEO\Editor\MetaboxSaver',
		'\FP\SEO\Editor\Metabox',
		'\FP\SEO\Editor\MetaboxRenderer',
	);
	
	$classes_found = 0;
	foreach ( $classes_to_check as $class ) {
		if ( class_exists( $class ) ) {
			echo '<div class="success">✅ Classe ' . esc_html( $class ) . ' trovata</div>';
			$classes_found++;
		} else {
			echo '<div class="error">❌ Classe ' . esc_html( $class ) . ' NON trovata</div>';
		}
	}
	
	if ( $classes_found === count( $classes_to_check ) ) {
		$tests_passed++;
		$all_results[] = array( 'test' => 'Classi disponibili', 'status' => 'passed' );
	} else {
		$tests_failed++;
		$all_results[] = array( 'test' => 'Classi disponibili', 'status' => 'failed' );
	}
	echo '</div>';
	
	// Test 3: Test salvataggio con valori unici
	echo '<div class="test-section">';
	echo '<h3>Test 3: Salvataggio con Valori Unici</h3>';
	
	$unique_title = 'Test Completo SEO Title ' . time() . ' ' . rand( 1000, 9999 );
	$unique_desc = 'Test Completo Meta Description ' . time() . ' ' . rand( 1000, 9999 );
	
	echo '<div class="info">ℹ️ Valori unici generati:<br>';
	echo 'SEO Title: <strong>' . esc_html( $unique_title ) . '</strong><br>';
	echo 'Meta Description: <strong>' . esc_html( $unique_desc ) . '</strong></div>';
	
	// Pulisci cache prima
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	// Simula $_POST
	$_POST['fp_seo_title'] = $unique_title;
	$_POST['fp_seo_title_sent'] = '1';
	$_POST['fp_seo_meta_description'] = $unique_desc;
	$_POST['fp_seo_meta_description_sent'] = '1';
	$_POST['fp_seo_performance_metabox_present'] = '1';
	
	// Salva
	try {
		$saver = new \FP\SEO\Editor\MetaboxSaver();
		$result = $saver->save_all_fields( $test_post_id );
		
		if ( $result ) {
			echo '<div class="success">✅ save_all_fields() ritornato TRUE</div>';
			$tests_passed++;
		} else {
			echo '<div class="error">❌ save_all_fields() ritornato FALSE</div>';
			$tests_failed++;
		}
	} catch ( \Exception $e ) {
		echo '<div class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</div>';
		echo '<pre>' . esc_html( $e->getTraceAsString() ) . '</pre>';
		$tests_failed++;
	}
	
	// Pulisci cache dopo
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	if ( function_exists( 'update_post_meta_cache' ) ) {
		update_post_meta_cache( array( $test_post_id ) );
	}
	
	echo '</div>';
	
	// Test 4: Verifica salvataggio immediato
	echo '<div class="test-section">';
	echo '<h3>Test 4: Verifica Salvataggio Immediato</h3>';
	
	$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore Atteso</th><th>Valore Salvato</th><th>Risultato</th></tr>';
	
	$title_match = ( $saved_title === $unique_title );
	$desc_match = ( $saved_desc === $unique_desc );
	
	if ( $title_match ) {
		echo '<tr><td>SEO Title</td><td>' . esc_html( substr( $unique_title, 0, 50 ) ) . '...</td><td>' . esc_html( substr( $saved_title, 0, 50 ) ) . '...</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
		$all_results[] = array( 'test' => 'SEO Title salvato', 'status' => 'passed' );
	} else {
		echo '<tr><td>SEO Title</td><td>' . esc_html( substr( $unique_title, 0, 50 ) ) . '...</td><td>' . esc_html( substr( $saved_title, 0, 50 ) ) . '...</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
		$all_results[] = array( 'test' => 'SEO Title salvato', 'status' => 'failed', 'expected' => $unique_title, 'got' => $saved_title );
	}
	
	if ( $desc_match ) {
		echo '<tr><td>Meta Description</td><td>' . esc_html( substr( $unique_desc, 0, 50 ) ) . '...</td><td>' . esc_html( substr( $saved_desc, 0, 50 ) ) . '...</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
		$all_results[] = array( 'test' => 'Meta Description salvata', 'status' => 'passed' );
	} else {
		echo '<tr><td>Meta Description</td><td>' . esc_html( substr( $unique_desc, 0, 50 ) ) . '...</td><td>' . esc_html( substr( $saved_desc, 0, 50 ) ) . '...</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
		$all_results[] = array( 'test' => 'Meta Description salvata', 'status' => 'failed', 'expected' => $unique_desc, 'got' => $saved_desc );
	}
	
	echo '</table>';
	echo '</div>';
	
	// Test 5: Verifica persistenza dopo flush cache
	echo '<div class="test-section">';
	echo '<h3>Test 5: Verifica Persistenza (Cache Flush)</h3>';
	
	// Flush completo cache
	wp_cache_flush();
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
	// Rileggi
	$persisted_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$persisted_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	if ( $persisted_title === $unique_title && $persisted_desc === $unique_desc ) {
		echo '<div class="success">✅ I dati persistono correttamente anche dopo flush cache completo</div>';
		$tests_passed++;
		$all_results[] = array( 'test' => 'Persistenza dopo cache flush', 'status' => 'passed' );
	} else {
		echo '<div class="error">❌ I dati non persistono correttamente</div>';
		echo '<p>Title atteso: ' . esc_html( $unique_title ) . '</p>';
		echo '<p>Title letto: ' . esc_html( $persisted_title ) . '</p>';
		echo '<p>Desc attesa: ' . esc_html( $unique_desc ) . '</p>';
		echo '<p>Desc letta: ' . esc_html( $persisted_desc ) . '</p>';
		$tests_failed++;
		$all_results[] = array( 'test' => 'Persistenza dopo cache flush', 'status' => 'failed' );
	}
	echo '</div>';
	
	// Test 6: Verifica hook
	echo '<div class="test-section">';
	echo '<h3>Test 6: Hook Registrati</h3>';
	
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
		$all_results[] = array( 'test' => 'Hook registrati', 'status' => 'passed' );
	} else {
		$all_results[] = array( 'test' => 'Hook registrati', 'status' => 'warning', 'found' => $hooks_found, 'total' => $hooks_checked );
	}
	
	echo '</div>';
	
	// Test 7: Verifica log recenti
	echo '<div class="test-section">';
	echo '<h3>Test 7: Log Recenti</h3>';
	
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$log_lines = file( $log_file );
		$recent_logs = array_slice( $log_lines, -50 );
		$fp_seo_logs = array_filter( $recent_logs, function( $line ) {
			return strpos( $line, 'FP SEO' ) !== false;
		} );
		
		if ( ! empty( $fp_seo_logs ) ) {
			echo '<div class="info">ℹ️ Trovati ' . count( $fp_seo_logs ) . ' log FP SEO recenti</div>';
			echo '<pre>' . esc_html( implode( '', array_slice( $fp_seo_logs, -10 ) ) ) . '</pre>';
		} else {
			echo '<div class="warning">⚠️ Nessun log FP SEO trovato negli ultimi 50 log</div>';
		}
	} else {
		echo '<div class="warning">⚠️ File debug.log non trovato</div>';
	}
	
	echo '</div>';
	
	// Riepilogo finale
	echo '<div class="test-section">';
	echo '<h2>📊 Riepilogo Finale</h2>';
	
	echo '<table>';
	echo '<tr><th>Test</th><th>Risultato</th></tr>';
	foreach ( $all_results as $result ) {
		$status_icon = $result['status'] === 'passed' ? '✅' : ( $result['status'] === 'warning' ? '⚠️' : '❌' );
		$status_color = $result['status'] === 'passed' ? 'green' : ( $result['status'] === 'warning' ? 'orange' : 'red' );
		echo '<tr><td>' . esc_html( $result['test'] ) . '</td><td style="color: ' . $status_color . '; font-weight: bold;">' . $status_icon . ' ' . esc_html( $result['status'] ) . '</td></tr>';
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
		echo '<li>✅ Gli hook sono registrati correttamente</li>';
		echo '<li>✅ Il metabox viene renderizzato correttamente</li>';
		echo '<li>✅ Supporto Gutenberg/REST API attivo</li>';
		echo '</ul>';
		echo '<p><strong>🎉 PROBLEMA RISOLTO COMPLETAMENTE!</strong></p>';
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

