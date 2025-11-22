<?php
/**
 * Diagnostica e risoluzione del problema di salvataggio dei campi SEO
 * 
 * IMPORTANTE: Esegui questo file tramite browser, non via CLI!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/diagnose-save-issue.php
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser, non via CLI
if ( php_sapi_name() === 'cli' ) {
	echo "ERRORE: Questo script deve essere eseguito via browser, non via CLI.\n";
	echo "Apri: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/diagnose-save-issue.php\n";
	exit( 1 );
}

// Carica WordPress
// Prova diversi percorsi possibili
$wp_load_paths = array(
	__DIR__ . '/../../../../wp-load.php',
	dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
	getcwd() . '/wp-load.php',
);

$wp_load = null;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		$wp_load = $path;
		break;
	}
}

if ( ! $wp_load ) {
	// Se non trovato, prova a cercare nella directory corrente
	$current_dir = getcwd();
	if ( strpos( $current_dir, 'wp-content' ) !== false ) {
		$parts = explode( 'wp-content', $current_dir );
		$wp_root = $parts[0];
		$wp_load = $wp_root . 'wp-load.php';
	}
}

if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato. Percorsi provati:\n" . implode( "\n", $wp_load_paths ) . "\n" );
}

require_once $wp_load;

// Verifica se siamo in admin o CLI
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

// Abilita error reporting
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

echo "<h1>Diagnostica Salvataggio Campi SEO</h1>\n";
echo "<pre>\n";

// Test 1: Verifica che l'hook sia registrato
echo "\n=== TEST 1: Verifica Hook Registrati ===\n";
global $wp_filter;
$save_post_hooks = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post']->callbacks : array();
echo "Hook 'save_post' trovati: " . count( $save_post_hooks ) . "\n";

$fp_seo_found = false;
foreach ( $save_post_hooks as $priority => $callbacks ) {
	foreach ( $callbacks as $callback ) {
		$callback_str = '';
		if ( is_array( $callback['function'] ) ) {
			$obj = $callback['function'][0];
			$method = $callback['function'][1];
			if ( is_object( $obj ) ) {
				$class = get_class( $obj );
				$callback_str = $class . '::' . $method;
				if ( strpos( $class, 'Metabox' ) !== false || strpos( $method, 'save_meta' ) !== false ) {
					$fp_seo_found = true;
					echo "  ✓ TROVATO: {$callback_str} (priorità: {$priority})\n";
				}
			}
		} elseif ( is_string( $callback['function'] ) ) {
			$callback_str = $callback['function'];
			if ( strpos( $callback_str, 'save_meta' ) !== false ) {
				$fp_seo_found = true;
				echo "  ✓ TROVATO: {$callback_str} (priorità: {$priority})\n";
			}
		}
	}
}

if ( ! $fp_seo_found ) {
	echo "  ⚠ WARNING: Hook FP SEO non trovato esplicitamente nei callback!\n";
	echo "  Verifico se Metabox è registrato e forza registrazione...\n";
	
	// Prova a registrare manualmente se non trovato
	if ( class_exists( 'FP\SEO\Editor\Metabox' ) ) {
		$plugin = FP\SEO\Infrastructure\Plugin::instance();
		$container = $plugin->get_container();
		$metabox = $container->get( FP\SEO\Editor\Metabox::class );
		if ( $metabox && method_exists( $metabox, 'register' ) ) {
			echo "  ✓ Istanza Metabox trovata, richiamo register()...\n";
			$metabox->register();
			echo "  ✓ register() chiamato\n";
			
			// Verifica di nuovo
			$save_post_hooks_after = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post']->callbacks : array();
			foreach ( $save_post_hooks_after as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) ) {
						$obj = $callback['function'][0];
						$method = $callback['function'][1];
						if ( is_object( $obj ) ) {
							$class = get_class( $obj );
							if ( $obj === $metabox && $method === 'save_meta' ) {
								$fp_seo_found = true;
								echo "  ✓ TROVATO DOPO REGISTER: {$class}::{$method} (priorità: {$priority})\n";
							}
						}
					}
				}
			}
		}
	}
	
	if ( ! $fp_seo_found ) {
		echo "  ✗ ERRORE: Hook FP SEO ancora non trovato dopo tentativo di registrazione!\n";
	} else {
		echo "  ✓ Hook FP SEO registrato correttamente\n";
	}
} else {
	echo "  ✓ Hook FP SEO registrato correttamente\n";
}

// Test 2: Verifica che MetaboxSaver esista
echo "\n=== TEST 2: Verifica Classe MetaboxSaver ===\n";
if ( class_exists( 'FP\SEO\Editor\MetaboxSaver' ) ) {
	echo "  ✓ Classe MetaboxSaver trovata\n";
	
	// Test istanziazione
	try {
		$saver = new FP\SEO\Editor\MetaboxSaver();
		echo "  ✓ MetaboxSaver istanziato correttamente\n";
	} catch ( Exception $e ) {
		echo "  ✗ ERRORE: Impossibile istanziare MetaboxSaver: " . $e->getMessage() . "\n";
	}
} else {
	echo "  ✗ ERRORE: Classe MetaboxSaver non trovata!\n";
}

// Test 3: Verifica che i campi siano presenti nel database (per un post di test)
echo "\n=== TEST 3: Verifica Campi nel Database ===\n";
$test_post_id = 441; // ID del post di test
$test_post = get_post( $test_post_id );
if ( $test_post ) {
	echo "  ✓ Post di test trovato: {$test_post->post_title} (ID: {$test_post_id})\n";
	
	$title_meta = get_post_meta( $test_post_id, '_fp_seo_title', true );
	$desc_meta = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
	
	echo "  Titolo SEO nel DB: " . ( $title_meta ? "'{$title_meta}'" : "(vuoto)" ) . "\n";
	echo "  Descrizione SEO nel DB: " . ( $desc_meta ? "'" . substr( $desc_meta, 0, 50 ) . "...'" : "(vuoto)" ) . "\n";
	
	// Verifica direttamente nel database
	global $wpdb;
	$db_title = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$test_post_id,
		'_fp_seo_title'
	) );
	$db_desc = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
		$test_post_id,
		'_fp_seo_meta_description'
	) );
	
	echo "  Titolo nel DB (diretto): " . ( $db_title ? "'{$db_title}'" : "(vuoto)" ) . "\n";
	echo "  Descrizione nel DB (diretto): " . ( $db_desc ? "'" . substr( $db_desc, 0, 50 ) . "...'" : "(vuoto)" ) . "\n";
	
	if ( $title_meta !== $db_title ) {
		echo "  ⚠ WARNING: Mismatch tra get_post_meta e query diretta per il titolo!\n";
	}
	if ( $desc_meta !== $db_desc ) {
		echo "  ⚠ WARNING: Mismatch tra get_post_meta e query diretta per la descrizione!\n";
	}
} else {
	echo "  ✗ ERRORE: Post di test (ID: {$test_post_id}) non trovato!\n";
}

// Test 4: Simula salvataggio
echo "\n=== TEST 4: Simulazione Salvataggio ===\n";
if ( $test_post ) {
	// Simula $_POST
	$_POST['fp_seo_performance_metabox_present'] = '1';
	$_POST['fp_seo_title'] = 'Test Diagnostico - ' . time();
	$_POST['fp_seo_title_sent'] = '1';
	$_POST['fp_seo_meta_description'] = 'Descrizione Test Diagnostico - ' . time();
	$_POST['fp_seo_meta_description_sent'] = '1';
	$_POST['fp_seo_performance_nonce'] = wp_create_nonce( 'fp_seo_performance_save' );
	
	echo "  Simulando POST con:\n";
	echo "    - fp_seo_title: {$_POST['fp_seo_title']}\n";
	echo "    - fp_seo_meta_description: {$_POST['fp_seo_meta_description']}\n";
	echo "    - fp_seo_performance_metabox_present: {$_POST['fp_seo_performance_metabox_present']}\n";
	
	// Disabilita DOING_AUTOSAVE
	if ( ! defined( 'DOING_AUTOSAVE' ) ) {
		define( 'DOING_AUTOSAVE', false );
	}
	
	// Chiama direttamente save_meta
	try {
		if ( class_exists( 'FP\SEO\Editor\Metabox' ) ) {
			// Ottieni l'istanza di Metabox dal container
			$plugin = FP\SEO\Infrastructure\Plugin::instance();
			$container = $plugin->get_container();
			$metabox = $container->get( FP\SEO\Editor\Metabox::class );
			
			if ( $metabox ) {
				echo "  ✓ Istanza di Metabox ottenuta\n";
				
				// Salva
				$metabox->save_meta( $test_post_id, $test_post, true );
				
				// Verifica se salvato
				$saved_title = get_post_meta( $test_post_id, '_fp_seo_title', true );
				$saved_desc = get_post_meta( $test_post_id, '_fp_seo_meta_description', true );
				
				echo "  Dopo salvataggio:\n";
				echo "    - Titolo salvato: " . ( $saved_title ? "'{$saved_title}'" : "(vuoto)" ) . "\n";
				echo "    - Descrizione salvata: " . ( $saved_desc ? "'" . substr( $saved_desc, 0, 50 ) . "...'" : "(vuoto)" ) . "\n";
				
				if ( $saved_title === $_POST['fp_seo_title'] && $saved_desc === $_POST['fp_seo_meta_description'] ) {
					echo "  ✓ SUCCESSO: Dati salvati correttamente!\n";
				} else {
					echo "  ✗ ERRORE: Dati non salvati correttamente!\n";
					echo "    Atteso titolo: {$_POST['fp_seo_title']}\n";
					echo "    Ottenuto: " . ( $saved_title ?: "(vuoto)" ) . "\n";
				}
			} else {
				echo "  ✗ ERRORE: Impossibile ottenere istanza di Metabox dal container\n";
			}
		} else {
			echo "  ✗ ERRORE: Classe Metabox non trovata!\n";
		}
	} catch ( Exception $e ) {
		echo "  ✗ ERRORE durante il salvataggio: " . $e->getMessage() . "\n";
		echo "    Trace: " . $e->getTraceAsString() . "\n";
	}
}

// Test 5: Verifica log
echo "\n=== TEST 5: Verifica Log ===\n";
$log_file = WP_CONTENT_DIR . '/debug.log';
if ( file_exists( $log_file ) ) {
	$log_lines = file( $log_file, FILE_IGNORE_NEW_LINES );
	$log_lines = array_slice( $log_lines, -50 ); // Ultime 50 righe
	
	$fp_seo_logs = array_filter( $log_lines, function( $line ) {
		return strpos( $line, 'FP SEO' ) !== false;
	} );
	
	echo "  Ultime 10 righe con 'FP SEO':\n";
	foreach ( array_slice( $fp_seo_logs, -10 ) as $log_line ) {
		echo "    " . $log_line . "\n";
	}
	
	if ( empty( $fp_seo_logs ) ) {
		echo "  ⚠ WARNING: Nessun log FP SEO trovato!\n";
	}
} else {
	echo "  ⚠ WARNING: File debug.log non trovato in: {$log_file}\n";
}

// Test 6: Verifica campi nascosti nel form
echo "\n=== TEST 6: Verifica Campi Nascosti ===\n";
if ( class_exists( 'FP\SEO\Editor\MetaboxRenderer' ) ) {
	echo "  ✓ Classe MetaboxRenderer trovata\n";
	
	// Verifica se il campo nascosto viene aggiunto
	$renderer_file = __DIR__ . '/src/Editor/MetaboxRenderer.php';
	$renderer_content = file_get_contents( $renderer_file );
	
	if ( strpos( $renderer_content, 'fp_seo_performance_metabox_present' ) !== false ) {
		echo "  ✓ Campo nascosto 'fp_seo_performance_metabox_present' presente nel renderer\n";
	} else {
		echo "  ✗ ERRORE: Campo nascosto 'fp_seo_performance_metabox_present' NON trovato nel renderer!\n";
	}
} else {
	echo "  ✗ ERRORE: Classe MetaboxRenderer non trovata!\n";
}

// Test 7: Verifica JavaScript
echo "\n=== TEST 7: Verifica JavaScript ===\n";
$js_file = __DIR__ . '/assets/admin/js/metabox-ai-fields.js';
if ( file_exists( $js_file ) ) {
	$js_content = file_get_contents( $js_file );
	
	if ( strpos( $js_content, 'fp_seo_title_sent' ) !== false ) {
		echo "  ✓ Campo 'fp_seo_title_sent' presente nel JavaScript\n";
	} else {
		echo "  ✗ ERRORE: Campo 'fp_seo_title_sent' NON trovato nel JavaScript!\n";
	}
	
	if ( strpos( $js_content, 'fp_seo_meta_description_sent' ) !== false ) {
		echo "  ✓ Campo 'fp_seo_meta_description_sent' presente nel JavaScript\n";
	} else {
		echo "  ✗ ERRORE: Campo 'fp_seo_meta_description_sent' NON trovato nel JavaScript!\n";
	}
	
	if ( strpos( $js_content, 'ensureFieldsInForm' ) !== false ) {
		echo "  ✓ Funzione 'ensureFieldsInForm' presente nel JavaScript\n";
	} else {
		echo "  ✗ ERRORE: Funzione 'ensureFieldsInForm' NON trovata nel JavaScript!\n";
	}
} else {
	echo "  ✗ ERRORE: File JavaScript non trovato: {$js_file}\n";
}

// Test 8: Fix automatico
echo "\n=== TEST 8: Fix Automatico ===\n";
$fixes_applied = array();

// Fix 1: Verifica che save_post accetti 3 parametri
$metabox_file = __DIR__ . '/src/Editor/Metabox.php';
$metabox_content = file_get_contents( $metabox_file );

if ( preg_match( '/public function save_meta\s*\(\s*int\s+\$post_id\s*\)\s*:/', $metabox_content ) ) {
	echo "  ⚠ WARNING: save_meta accetta solo 1 parametro, ma save_post ne passa 3!\n";
	$fixes_applied[] = "Correggere firma di save_meta per accettare 3 parametri";
}

// Fix 2: Verifica che add_action usi il numero corretto di parametri
if ( preg_match( "/add_action\s*\(\s*'save_post'\s*,\s*array\s*\(\s*\$this\s*,\s*'save_meta'\s*\)\s*,\s*\d+\s*,\s*1\s*\)/", $metabox_content ) ) {
	echo "  ⚠ WARNING: add_action('save_post') specifica 1 parametro, ma save_post ne passa 3!\n";
	$fixes_applied[] = "Correggere add_action per specificare 3 parametri";
}

if ( empty( $fixes_applied ) ) {
	echo "  ✓ Nessun fix necessario (problemi di firma corretti)\n";
} else {
	echo "  Fix necessari:\n";
	foreach ( $fixes_applied as $fix ) {
		echo "    - {$fix}\n";
	}
}

// Test aggiuntivo: Verifica esplicita dell'hook FP SEO
echo "\n  Verifica esplicita hook FP SEO...\n";
if ( class_exists( 'FP\SEO\Editor\Metabox' ) ) {
	$plugin = FP\SEO\Infrastructure\Plugin::instance();
	$container = $plugin->get_container();
	$metabox = $container->get( FP\SEO\Editor\Metabox::class );
	
	if ( $metabox ) {
		$found_explicit = false;
		global $wp_filter;
		$save_post_hooks_check = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post']->callbacks : array();
		
		foreach ( $save_post_hooks_check as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) ) {
					$obj = $callback['function'][0];
					$method = $callback['function'][1];
					// Confronta l'oggetto, non la classe (perché potrebbero esserci più istanze)
					if ( is_object( $obj ) && $obj === $metabox && $method === 'save_meta' ) {
						$found_explicit = true;
						$class = get_class( $obj );
						echo "  ✓ TROVATO ESPLICITAMENTE: {$class}::{$method} (priorità: {$priority}, oggetto: " . spl_object_hash( $obj ) . ")\n";
						break 2;
					}
				}
			}
		}
		
		if ( ! $found_explicit ) {
			echo "  ✗ ERRORE: Hook FP SEO NON trovato esplicitamente nei callback!\n";
			echo "  Oggetto Metabox: " . spl_object_hash( $metabox ) . "\n";
			echo "  Questo significa che save_meta NON viene chiamato durante il salvataggio normale!\n";
		}
	}
}

// Test 9: Fix automatico dell'hook
echo "\n=== TEST 9: Fix Automatico Hook ===\n";
if ( ! $fp_seo_found && class_exists( 'FP\SEO\Editor\Metabox' ) ) {
	echo "  ⚠ Hook FP SEO non trovato, applico fix automatico...\n";
	
	$plugin = FP\SEO\Infrastructure\Plugin::instance();
	$container = $plugin->get_container();
	
	// Rimuovi hook esistenti se presenti
	remove_action( 'save_post', array( $container->get( FP\SEO\Editor\Metabox::class ), 'save_meta' ), 5 );
	remove_action( 'edit_post', array( $container->get( FP\SEO\Editor\Metabox::class ), 'save_meta_edit_post' ), 5 );
	
	// Re-registra l'hook
	$metabox = $container->get( FP\SEO\Editor\Metabox::class );
	if ( $metabox && method_exists( $metabox, 'register' ) ) {
		// Registra manualmente gli hook
		add_action( 'save_post', array( $metabox, 'save_meta' ), 5, 3 );
		add_action( 'edit_post', array( $metabox, 'save_meta_edit_post' ), 5, 2 );
		echo "  ✓ Hook ri-registrato manualmente\n";
		
		// Verifica di nuovo
		global $wp_filter;
		$save_post_hooks_after = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post']->callbacks : array();
		$found_after = false;
		foreach ( $save_post_hooks_after as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) ) {
					$obj = $callback['function'][0];
					$method = $callback['function'][1];
					if ( is_object( $obj ) && $obj === $metabox && $method === 'save_meta' ) {
						$found_after = true;
						$class = get_class( $obj );
						echo "  ✓ Hook trovato dopo fix: {$class}::{$method} (priorità: {$priority})\n";
					}
				}
			}
		}
		
		if ( $found_after ) {
			echo "  ✓ SUCCESSO: Hook FP SEO ora registrato correttamente!\n";
		} else {
			echo "  ✗ ERRORE: Hook ancora non trovato dopo fix!\n";
		}
	} else {
		echo "  ✗ ERRORE: Impossibile ottenere istanza di Metabox per fix\n";
	}
} else {
	echo "  ✓ Nessun fix necessario (hook già registrato)\n";
}

// Riassunto
echo "\n=== RIASSUNTO ===\n";
echo "Diagnostica completata. Verifica i risultati sopra per identificare il problema.\n";
echo "\nSe il problema persiste:\n";
echo "1. Verifica che i log siano attivi (WP_DEBUG = true)\n";
echo "2. Controlla wp-content/debug.log per vedere se save_meta viene chiamato\n";
echo "3. Verifica che i campi nascosti siano presenti nel form HTML\n";
echo "4. Controlla la console del browser per errori JavaScript\n";
echo "\nSe l'hook non è registrato:\n";
echo "1. Verifica che il plugin sia attivo\n";
echo "2. Verifica che il metodo register() di Metabox venga chiamato\n";
echo "3. Controlla che non ci siano errori fatali durante l'inizializzazione\n";
echo "\n</pre>\n";

