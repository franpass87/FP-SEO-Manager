<?php
/**
 * Test diretto del salvataggio dei campi SEO
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-direct.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-save-direct.php\n" );
}

// Carica WordPress - trova wp-load.php
// Usa DOCUMENT_ROOT per trovare la root di WordPress
$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : dirname( dirname( dirname( dirname( __FILE__ ) ) ) );

$wp_load_paths = array(
	$document_root . '/wp-load.php',
	dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php',
	__DIR__ . '/../../../../wp-load.php',
);

// Se il file corrente è in wp-content/plugins/..., risali alla root
$current_file = __FILE__;
if ( strpos( $current_file, 'wp-content' ) !== false ) {
	$parts = explode( 'wp-content', $current_file );
	$wp_root = dirname( $parts[0] );
	$wp_load_paths[] = $wp_root . '/wp-load.php';
}

$wp_load = null;
foreach ( $wp_load_paths as $path ) {
	$path = str_replace( '\\', '/', $path ); // Normalizza separatori
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato.<br>DOCUMENT_ROOT: " . $document_root . "<br>__FILE__: " . __FILE__ . "<br>Percorsi provati:<br>" . implode( "<br>", $wp_load_paths ) );
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
	<title>Test Salvataggio SEO - FP SEO Manager</title>
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
		.btn-danger { background: #dc3545; }
	</style>
</head>
<body>
<div class="container">
	<h1>🧪 Test Salvataggio Campi SEO</h1>
	
	<?php
	$tests_passed = 0;
	$tests_failed = 0;
	$results = array();
	
	// Test 1: Verifica che il plugin sia caricato
	echo '<div class="test-section">';
	echo '<h3>Test 1: Plugin Caricato</h3>';
	
	if ( class_exists( '\FP\SEO\Editor\MetaboxSaver' ) ) {
		echo '<div class="success">✅ Classe MetaboxSaver trovata</div>';
		$tests_passed++;
	} else {
		echo '<div class="error">❌ Classe MetaboxSaver NON trovata</div>';
		$tests_failed++;
	}
	
	if ( class_exists( '\FP\SEO\Editor\Metabox' ) ) {
		echo '<div class="success">✅ Classe Metabox trovata</div>';
		$tests_passed++;
	} else {
		echo '<div class="error">❌ Classe Metabox NON trovata</div>';
		$tests_failed++;
	}
	echo '</div>';
	
	// Test 2: Trova un post da testare
	echo '<div class="test-section">';
	echo '<h3>Test 2: Post per Test</h3>';
	
	$test_post = get_posts( array(
		'post_type' => 'post',
		'posts_per_page' => 1,
		'post_status' => 'any',
	) );
	
	if ( empty( $test_post ) ) {
		// Crea un post di test
		$test_post_id = wp_insert_post( array(
			'post_title' => 'Test SEO Salvataggio ' . date( 'Y-m-d H:i:s' ),
			'post_content' => 'Questo è un post di test per verificare il salvataggio dei campi SEO.',
			'post_status' => 'draft',
			'post_type' => 'post',
		) );
		
		if ( is_wp_error( $test_post_id ) ) {
			echo '<div class="error">❌ Errore creazione post: ' . $test_post_id->get_error_message() . '</div>';
			$tests_failed++;
		} else {
			echo '<div class="success">✅ Post di test creato: ID ' . $test_post_id . '</div>';
			$test_post = array( get_post( $test_post_id ) );
			$tests_passed++;
		}
	} else {
		$test_post = $test_post[0];
		$test_post_id = $test_post->ID;
		echo '<div class="info">ℹ️ Usando post esistente: ID ' . $test_post_id . ' - "' . esc_html( $test_post->post_title ) . '"</div>';
		$tests_passed++;
	}
	
	if ( ! isset( $test_post_id ) || ! $test_post_id ) {
		echo '<div class="error">❌ Impossibile ottenere un post per il test</div>';
		$tests_failed++;
		echo '</div>';
		echo '</div></body></html>';
		exit;
	}
	echo '</div>';
	
	// Test 3: Simula salvataggio
	echo '<div class="test-section">';
	echo '<h3>Test 3: Simulazione Salvataggio</h3>';
	
	// Valori di test
	$test_seo_title = 'Test SEO Title ' . time();
	$test_meta_desc = 'Test Meta Description ' . time();
	
	// Simula $_POST
	$_POST['fp_seo_title'] = $test_seo_title;
	$_POST['fp_seo_title_sent'] = '1';
	$_POST['fp_seo_meta_description'] = $test_meta_desc;
	$_POST['fp_seo_meta_description_sent'] = '1';
	$_POST['fp_seo_performance_metabox_present'] = '1';
	
	echo '<div class="info">ℹ️ Valori di test:<br>';
	echo 'SEO Title: <strong>' . esc_html( $test_seo_title ) . '</strong><br>';
	echo 'Meta Description: <strong>' . esc_html( $test_meta_desc ) . '</strong></div>';
	
	// Pulisci cache prima
	clean_post_cache( $test_post_id );
	wp_cache_delete( $test_post_id, 'post_meta' );
	
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
		echo '<div class="error">❌ Eccezione durante salvataggio: ' . esc_html( $e->getMessage() ) . '</div>';
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
	
	// Test 4: Verifica salvataggio
	echo '<div class="test-section">';
	echo '<h3>Test 4: Verifica Dati Salvati</h3>';
	
	$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore Atteso</th><th>Valore Salvato</th><th>Risultato</th></tr>';
	
	// Verifica Title
	if ( $saved_title === $test_seo_title ) {
		echo '<tr><td>SEO Title</td><td>' . esc_html( $test_seo_title ) . '</td><td>' . esc_html( $saved_title ) . '</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
	} else {
		echo '<tr><td>SEO Title</td><td>' . esc_html( $test_seo_title ) . '</td><td>' . esc_html( $saved_title ) . '</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
	}
	
	// Verifica Description
	if ( $saved_desc === $test_meta_desc ) {
		echo '<tr><td>Meta Description</td><td>' . esc_html( $test_meta_desc ) . '</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: green;">✅ OK</td></tr>';
		$tests_passed++;
	} else {
		echo '<tr><td>Meta Description</td><td>' . esc_html( $test_meta_desc ) . '</td><td>' . esc_html( $saved_desc ) . '</td><td style="color: red;">❌ ERRORE</td></tr>';
		$tests_failed++;
	}
	
	echo '</table>';
	echo '</div>';
	
	// Test 5: Verifica hook registrati
	echo '<div class="test-section">';
	echo '<h3>Test 5: Hook Registrati</h3>';
	
	global $wp_filter;
	
	$hooks_to_check = array(
		'save_post' => 'Metabox::save_meta',
		'edit_post' => 'Metabox::save_meta_edit_post',
		'rest_api_init' => 'Metabox::register_rest_meta_fields',
	);
	
	$hooks_found = 0;
	foreach ( $hooks_to_check as $hook => $method ) {
		if ( isset( $wp_filter[ $hook ] ) ) {
			echo '<div class="success">✅ Hook <code>' . esc_html( $hook ) . '</code> registrato</div>';
			$hooks_found++;
		} else {
			echo '<div class="warning">⚠️ Hook <code>' . esc_html( $hook ) . '</code> non trovato</div>';
		}
	}
	
	if ( $hooks_found === count( $hooks_to_check ) ) {
		$tests_passed++;
	} else {
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
		echo '<p>Il salvataggio dei campi SEO funziona correttamente.</p>';
		echo '</div>';
	} else {
		echo '<div class="error">';
		echo '<h3>❌ ALCUNI TEST SONO FALLITI</h3>';
		echo '<p>Controlla i dettagli sopra per identificare i problemi.</p>';
		echo '</div>';
	}
	
	echo '<p><a href="' . admin_url( 'post.php?post=' . $test_post_id . '&action=edit' ) . '" class="btn btn-success">Modifica Post di Test</a></p>';
	echo '</div>';
	?>
	
</div>
</body>
</html>

