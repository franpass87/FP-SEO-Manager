<?php
/**
 * Script diagnostico completo per il metabox SEO Performance.
 * 
 * Eseguire da browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/DIAGNOSTICA-METABOX-COMPLETA.php
 * 
 * Oppure da admin: aggiungere ?diagnostica=metabox all'URL dell'editor post
 */

// Carica WordPress - metodo robusto che funziona anche con junction/symlink
if ( ! defined( 'ABSPATH' ) ) {
	// Calcola percorso assoluto del file corrente
	$current_file = __FILE__;
	
	// Normalizza il percorso (risolve .. e .)
	$current_dir = dirname( realpath( $current_file ) ?: $current_file );
	
	// Prova percorsi multipli - da wp-content/plugins/FP-SEO-Manager/ verso root
	$possible_paths = array();
	
	// Metodo 1: Percorso relativo standard (4 livelli su)
	$possible_paths[] = dirname( dirname( dirname( dirname( $current_dir ) ) ) ) . '/wp-load.php';
	
	// Metodo 2: Usando __DIR__ normalizzato
	$possible_paths[] = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';
	
	// Metodo 3: Basato su DOCUMENT_ROOT
	if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
		$doc_root = rtrim( $_SERVER['DOCUMENT_ROOT'], '/\\' );
		// Trova wp-content nel percorso corrente
		if ( strpos( $current_dir, 'wp-content' ) !== false ) {
			$wp_content_pos = strpos( $current_dir, 'wp-content' );
			$wp_root = substr( $current_dir, 0, $wp_content_pos );
			$possible_paths[] = $wp_root . 'wp-load.php';
		}
		$possible_paths[] = $doc_root . '/wp-load.php';
	}
	
	// Metodo 4: Cerca wp-load.php salendo dalla directory corrente
	$search_dir = $current_dir;
	$max_levels = 10;
	$level = 0;
	while ( $level < $max_levels && $search_dir !== dirname( $search_dir ) ) {
		$wp_load = $search_dir . '/wp-load.php';
		if ( file_exists( $wp_load ) ) {
			$possible_paths[] = $wp_load;
			break;
		}
		$search_dir = dirname( $search_dir );
		$level++;
	}
	
	// Rimuovi duplicati e normalizza
	$possible_paths = array_unique( array_map( 'realpath', array_filter( $possible_paths, 'file_exists' ) ) );
	
	// Carica il primo file valido trovato
	$wp_load_found = false;
	foreach ( $possible_paths as $path ) {
		if ( file_exists( $path ) && is_readable( $path ) ) {
			require_once $path;
			$wp_load_found = true;
			break;
		}
	}
	
	// Se ancora non trovato, mostra errore dettagliato
	if ( ! $wp_load_found || ! defined( 'ABSPATH' ) ) {
		$error_msg = '<h1 style="color: red;">Errore: wp-load.php non trovato</h1>';
		$error_msg .= '<p><strong>File corrente:</strong> ' . htmlspecialchars( $current_file ) . '</p>';
		$error_msg .= '<p><strong>Directory corrente:</strong> ' . htmlspecialchars( $current_dir ) . '</p>';
		$error_msg .= '<p><strong>DOCUMENT_ROOT:</strong> ' . htmlspecialchars( $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' ) . '</p>';
		$error_msg .= '<p><strong>Percorsi tentati:</strong></p><ul>';
		$all_paths = array(
			dirname( dirname( dirname( dirname( $current_dir ) ) ) ) . '/wp-load.php',
			dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
		);
		if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$all_paths[] = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
		}
		foreach ( $all_paths as $path ) {
			$exists = file_exists( $path ) ? '✓ ESISTE' : '✗ NON ESISTE';
			$error_msg .= '<li>' . htmlspecialchars( $path ) . ' <strong>' . $exists . '</strong></li>';
		}
		$error_msg .= '</ul>';
		die( $error_msg );
	}
}

// Abilita output buffer per evitare problemi
ob_start();

?>
<!DOCTYPE html>
<html>
<head>
	<title>Diagnostica Metabox SEO Performance</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
		h1 { color: #23282d; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
		h2 { color: #0073aa; margin-top: 30px; border-left: 4px solid #0073aa; padding-left: 10px; }
		.success { color: #46b450; font-weight: bold; }
		.error { color: #dc3232; font-weight: bold; }
		.warning { color: #ffb900; font-weight: bold; }
		.info { color: #0073aa; }
		.section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #0073aa; }
		.code { background: #23282d; color: #fff; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
		table th { background: #f1f1f1; font-weight: bold; }
		ul { margin: 10px 0; padding-left: 20px; }
		li { margin: 5px 0; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Diagnostica Completa Metabox SEO Performance</h1>
	
	<?php
	$errors = array();
	$warnings = array();
	$successes = array();

	// 1. VERIFICA AMBIENTE WORDPRESS
	echo '<h2>1. Ambiente WordPress</h2>';
	echo '<div class="section">';
	
	echo '<p><strong>is_admin():</strong> ' . ( is_admin() ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>' ) . '</p>';
	echo '<p><strong>WP_ADMIN:</strong> ' . ( defined( 'WP_ADMIN' ) ? ( WP_ADMIN ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>' ) : '<span class="warning">NON DEFINITA</span>' ) . '</p>';
	echo '<p><strong>REQUEST_URI:</strong> <code>' . ( $_SERVER['REQUEST_URI'] ?? 'N/A' ) . '</code></p>';
	echo '<p><strong>Current Hook:</strong> <code>' . current_action() . '</code></p>';
	echo '<p><strong>WP Version:</strong> ' . get_bloginfo( 'version' ) . '</p>';
	
	echo '</div>';

	// 2. VERIFICA PLUGIN CARICATO
	echo '<h2>2. Stato Plugin</h2>';
	echo '<div class="section">';
	
	if ( class_exists( 'FP\SEO\Infrastructure\Plugin' ) ) {
		$successes[] = 'Plugin classe principale caricata';
		echo '<p class="success">✓ Plugin classe principale caricata</p>';
		
		$plugin = FP\SEO\Infrastructure\Plugin::instance();
		$container = $plugin->get_container();
		$registry = $plugin->get_registry();
		
		echo '<p><strong>Container:</strong> <span class="success">Istanza creata</span></p>';
		echo '<p><strong>Registry:</strong> <span class="success">Istanza creata</span></p>';
		echo '<p><strong>Registry booted:</strong> ' . ( $registry->is_booted() ? '<span class="success">TRUE</span>' : '<span class="warning">FALSE</span>' ) . '</p>';
		
	} else {
		$errors[] = 'Plugin classe principale NON caricata';
		echo '<p class="error">✗ Plugin classe principale NON caricata</p>';
		echo '</div>';
		echo '</div></body></html>';
		ob_end_flush();
		die();
	}
	
	echo '</div>';

	// 3. VERIFICA SERVICE PROVIDERS
	echo '<h2>3. Service Providers Registrati</h2>';
	echo '<div class="section">';
	
	$providers = $registry->get_providers();
	echo '<p><strong>Totale providers registrati:</strong> ' . count( $providers ) . '</p>';
	
	$mainMetaboxProvider = null;
	foreach ( $providers as $provider ) {
		$providerClass = get_class( $provider );
		if ( $provider instanceof FP\SEO\Infrastructure\Providers\Metaboxes\MainMetaboxServiceProvider ) {
			$mainMetaboxProvider = $provider;
			$successes[] = 'MainMetaboxServiceProvider trovato';
			echo '<p class="success">✓ MainMetaboxServiceProvider trovato: <code>' . $providerClass . '</code></p>';
		}
	}
	
	if ( ! $mainMetaboxProvider ) {
		$errors[] = 'MainMetaboxServiceProvider NON trovato';
		echo '<p class="error">✗ MainMetaboxServiceProvider NON trovato</p>';
		echo '<p>Providers trovati:</p><ul>';
		foreach ( $providers as $provider ) {
			echo '<li><code>' . get_class( $provider ) . '</code></li>';
		}
		echo '</ul>';
	}
	
	echo '</div>';

	// 4. VERIFICA CONTAINER - METABOX
	echo '<h2>4. Container - Servizio Metabox</h2>';
	echo '<div class="section">';
	
	try {
		$metabox = $container->get( FP\SEO\Editor\Metabox::class );
		$successes[] = 'Metabox nel container';
		echo '<p class="success">✓ Metabox presente nel container</p>';
		echo '<p><strong>Classe istanza:</strong> <code>' . get_class( $metabox ) . '</code></p>';
		
		// Verifica proprietà
		$reflection = new ReflectionClass( $metabox );
		$properties = $reflection->getProperties();
		echo '<p><strong>Proprietà pubbliche:</strong> ' . count( $properties ) . '</p>';
		
	} catch ( Exception $e ) {
		$errors[] = 'Metabox NON nel container: ' . $e->getMessage();
		echo '<p class="error">✗ Metabox NON nel container</p>';
		echo '<p><strong>Errore:</strong> <code>' . esc_html( $e->getMessage() ) . '</code></p>';
		$metabox = null;
	}
	
	echo '</div>';

	// 5. VERIFICA HOOK REGISTRATI
	echo '<h2>5. Hook WordPress Registrati</h2>';
	echo '<div class="section">';
	
	global $wp_filter;
	
	// Verifica hook add_meta_boxes
	if ( isset( $wp_filter['add_meta_boxes'] ) ) {
		echo '<p class="success">✓ Hook <code>add_meta_boxes</code> esiste</p>';
		
		$callbacks = $wp_filter['add_meta_boxes']->callbacks;
		$metaboxHookFound = false;
		$allCallbacks = array();
		
		foreach ( $callbacks as $priority => $hooks ) {
			foreach ( $hooks as $hook ) {
				$callbackInfo = array(
					'priority' => $priority,
					'function' => 'unknown',
				);
				
				if ( is_array( $hook['function'] ) ) {
					if ( is_object( $hook['function'][0] ) ) {
						$callbackInfo['function'] = get_class( $hook['function'][0] ) . '::' . $hook['function'][1];
						if ( $hook['function'][0] instanceof FP\SEO\Editor\Metabox ) {
							$metaboxHookFound = true;
							$callbackInfo['found'] = true;
							$successes[] = 'Hook metabox trovato in add_meta_boxes';
						}
					} else {
						$callbackInfo['function'] = $hook['function'][0] . '::' . $hook['function'][1];
					}
				} else if ( is_string( $hook['function'] ) ) {
					$callbackInfo['function'] = $hook['function'];
				} else if ( is_object( $hook['function'] ) ) {
					$callbackInfo['function'] = 'Closure';
				}
				
				$allCallbacks[] = $callbackInfo;
			}
		}
		
		if ( $metaboxHookFound ) {
			echo '<p class="success">✓ Hook metabox trovato in <code>add_meta_boxes</code></p>';
		} else {
			$warnings[] = 'Hook metabox NON trovato in add_meta_boxes';
			echo '<p class="warning">⚠ Hook metabox NON trovato in <code>add_meta_boxes</code></p>';
			echo '<p>Callback registrati in add_meta_boxes:</p><ul>';
			foreach ( $allCallbacks as $cb ) {
				$marker = isset( $cb['found'] ) ? ' <span class="success">[TROVATO]</span>' : '';
				echo '<li>Priorità <strong>' . $cb['priority'] . '</strong>: <code>' . esc_html( $cb['function'] ) . '</code>' . $marker . '</li>';
			}
			echo '</ul>';
		}
		
	} else {
		$errors[] = 'Hook add_meta_boxes non esiste';
		echo '<p class="error">✗ Hook <code>add_meta_boxes</code> NON esiste</p>';
	}
	
	// Verifica altri hook importanti
	$importantHooks = array( 'admin_init', 'admin_menu', 'load-post.php', 'load-post-new.php' );
	echo '<p><strong>Altri hook importanti:</strong></p><ul>';
	foreach ( $importantHooks as $hookName ) {
		if ( isset( $wp_filter[ $hookName ] ) ) {
			$count = count( $wp_filter[ $hookName ]->callbacks );
			echo '<li><code>' . $hookName . '</code>: <span class="success">' . $count . ' callback registrati</span></li>';
		} else {
			echo '<li><code>' . $hookName . '</code>: <span class="warning">nessun callback</span></li>';
		}
	}
	echo '</ul>';
	
	echo '</div>';

	// 6. VERIFICA BOOT STATO
	echo '<h2>6. Stato Boot Service Providers</h2>';
	echo '<div class="section">';
	
	if ( $mainMetaboxProvider ) {
		// Verifica se è stato bootato usando reflection per accedere a proprietà private
		try {
			$reflection = new ReflectionClass( $mainMetaboxProvider );
			$bootedPropertyName = '_booted_' . get_class( $mainMetaboxProvider );
			
			// Prova a verificare se la proprietà esiste
			if ( $reflection->hasProperty( $bootedPropertyName ) ) {
				$property = $reflection->getProperty( $bootedPropertyName );
				$property->setAccessible( true );
				$booted = $property->getValue( $mainMetaboxProvider );
				echo '<p><strong>Boot flag (proprietà):</strong> ' . ( $booted ? '<span class="success">TRUE</span>' : '<span class="warning">FALSE</span>' ) . '</p>';
			} else {
				// Usa property_exists per proprietà dinamiche
				if ( property_exists( $mainMetaboxProvider, $bootedPropertyName ) ) {
					$booted = $mainMetaboxProvider->{$bootedPropertyName};
					echo '<p><strong>Boot flag (dinamica):</strong> ' . ( $booted ? '<span class="success">TRUE</span>' : '<span class="warning">FALSE</span>' ) . '</p>';
				} else {
					echo '<p class="warning">⚠ Impossibile verificare flag boot (proprietà non esiste)</p>';
				}
			}
		} catch ( Exception $e ) {
			echo '<p class="warning">⚠ Errore verificando boot flag: ' . esc_html( $e->getMessage() ) . '</p>';
		}
		
		// Verifica se boot_admin è stato chiamato testando se il metabox è registrato
		if ( $metabox ) {
			// Prova a chiamare register() per vedere se funziona
			try {
				$testRegister = method_exists( $metabox, 'register' );
				echo '<p><strong>Metodo register() esiste:</strong> ' . ( $testRegister ? '<span class="success">TRUE</span>' : '<span class="error">FALSE</span>' ) . '</p>';
				
				if ( $testRegister ) {
					// NON chiamiamo register() qui perché potrebbe interferire
					// Ma verifichiamo se gli hook sono già stati registrati
					echo '<p class="info">ℹ Per verificare se register() è stato chiamato, controlla se l\'hook add_meta_boxes contiene il callback (vedi sezione 5)</p>';
				}
			} catch ( Exception $e ) {
				echo '<p class="error">✗ Errore verificando metodo register: ' . esc_html( $e->getMessage() ) . '</p>';
			}
		}
	}
	
	echo '</div>';

	// 7. VERIFICA POST TYPES
	echo '<h2>7. Post Types Supportati</h2>';
	echo '<div class="section">';
	
	if ( class_exists( 'FP\SEO\Utils\PostTypes' ) ) {
		$postTypes = FP\SEO\Utils\PostTypes::analyzable();
		echo '<p><strong>Post types analizzabili:</strong></p><ul>';
		foreach ( $postTypes as $pt ) {
			echo '<li><code>' . esc_html( $pt ) . '</code></li>';
		}
		echo '</ul>';
		
		if ( empty( $postTypes ) ) {
			$errors[] = 'Nessun post type supportato';
			echo '<p class="error">✗ Nessun post type supportato!</p>';
		} else {
			$successes[] = count( $postTypes ) . ' post type supportati';
		}
	} else {
		$errors[] = 'Classe PostTypes non trovata';
		echo '<p class="error">✗ Classe PostTypes non trovata</p>';
	}
	
	echo '</div>';

	// 8. VERIFICA TIMING HOOKS
	echo '<h2>8. Timing Hooks WordPress</h2>';
	echo '<div class="section">';
	
	echo '<p><strong>Ordine hook WordPress (rilevanti):</strong></p>';
	echo '<ol>';
	echo '<li><code>plugins_loaded</code> (priorità default: 10)</li>';
	echo '<li><code>admin_init</code> (dopo plugins_loaded in admin)</li>';
	echo '<li><code>admin_menu</code> (prima di add_meta_boxes)</li>';
	echo '<li><code>load-post.php</code> (molto precoce per edit post)</li>';
	echo '<li><code>add_meta_boxes</code> (quando la pagina edit viene caricata)</li>';
	echo '</ol>';
	
	echo '<p class="info">ℹ Il plugin si boota su <code>plugins_loaded</code>. I service provider admin devono bootare prima di <code>add_meta_boxes</code>.</p>';
	
	echo '</div>';

	// 9. TEST MANUALE BOOT
	echo '<h2>9. Test Boot Manuale</h2>';
	echo '<div class="section">';
	
	if ( $mainMetaboxProvider && ! $registry->is_booted() ) {
		echo '<p class="warning">⚠ Registry non ancora bootato. Proviamo a bootarlo manualmente...</p>';
		try {
			$registry->boot();
			echo '<p class="success">✓ Boot manuale completato</p>';
			
			// Verifica se ora il metabox è registrato
			if ( isset( $wp_filter['add_meta_boxes'] ) ) {
				$callbacks = $wp_filter['add_meta_boxes']->callbacks;
				$foundAfterBoot = false;
				foreach ( $callbacks as $priority => $hooks ) {
					foreach ( $hooks as $hook ) {
						if ( is_array( $hook['function'] ) && 
						     is_object( $hook['function'][0] ) && 
						     $hook['function'][0] instanceof FP\SEO\Editor\Metabox ) {
							$foundAfterBoot = true;
							break 2;
						}
					}
				}
				
				if ( $foundAfterBoot ) {
					echo '<p class="success">✓ Dopo boot manuale, hook metabox trovato!</p>';
				} else {
					echo '<p class="error">✗ Dopo boot manuale, hook metabox ancora NON trovato</p>';
				}
			}
			
		} catch ( Exception $e ) {
			echo '<p class="error">✗ Errore durante boot manuale: ' . esc_html( $e->getMessage() ) . '</p>';
		}
	} else if ( $registry->is_booted() ) {
		echo '<p class="info">ℹ Registry già bootato</p>';
	} else {
		echo '<p class="error">✗ Impossibile fare test boot manuale: MainMetaboxServiceProvider non trovato</p>';
	}
	
	echo '</div>';

	// 10. RIEpilOGO
	echo '<h2>10. Riepilogo Diagnostica</h2>';
	echo '<div class="section">';
	
	echo '<p><strong>Successi:</strong> <span class="success">' . count( $successes ) . '</span></p>';
	if ( ! empty( $successes ) ) {
		echo '<ul>';
		foreach ( $successes as $success ) {
			echo '<li class="success">✓ ' . esc_html( $success ) . '</li>';
		}
		echo '</ul>';
	}
	
	echo '<p><strong>Warning:</strong> <span class="warning">' . count( $warnings ) . '</span></p>';
	if ( ! empty( $warnings ) ) {
		echo '<ul>';
		foreach ( $warnings as $warning ) {
			echo '<li class="warning">⚠ ' . esc_html( $warning ) . '</li>';
		}
		echo '</ul>';
	}
	
	echo '<p><strong>Errori:</strong> <span class="error">' . count( $errors ) . '</span></p>';
	if ( ! empty( $errors ) ) {
		echo '<ul>';
		foreach ( $errors as $error ) {
			echo '<li class="error">✗ ' . esc_html( $error ) . '</li>';
		}
		echo '</ul>';
	}
	
	// Conclusione
	if ( empty( $errors ) && $metaboxHookFound ) {
		echo '<p class="success" style="font-size: 18px; padding: 15px; background: #e7f7e7; border-radius: 4px;">✓ Tutto sembra a posto! Il metabox dovrebbe essere registrato correttamente.</p>';
	} else if ( empty( $errors ) ) {
		echo '<p class="warning" style="font-size: 18px; padding: 15px; background: #fff9e5; border-radius: 4px;">⚠ Nessun errore critico, ma l\'hook metabox non è stato trovato. Potrebbe essere un problema di timing o il metabox non è ancora stato bootato.</p>';
	} else {
		echo '<p class="error" style="font-size: 18px; padding: 15px; background: #fce8e8; border-radius: 4px;">✗ Sono stati trovati errori critici che impediscono il corretto funzionamento del metabox.</p>';
	}
	
	echo '</div>';

	// 11. DEBUG INFO AGGIUNTIVE
	echo '<h2>11. Informazioni Debug Aggiuntive</h2>';
	echo '<div class="section">';
	
	echo '<p><strong>Variabili globali WordPress:</strong></p>';
	echo '<ul>';
	echo '<li><code>$pagenow</code>: ' . ( isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : 'non definita' ) . '</li>';
	echo '<li><code>$typenow</code>: ' . ( isset( $GLOBALS['typenow'] ) ? $GLOBALS['typenow'] : 'non definita' ) . '</li>';
	echo '<li><code>$post</code>: ' . ( isset( $GLOBALS['post'] ) ? ( $GLOBALS['post'] ? get_class( $GLOBALS['post'] ) . ' (ID: ' . $GLOBALS['post']->ID . ')' : 'null' ) : 'non definita' ) . '</li>';
	echo '</ul>';
	
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		echo '<p class="success">✓ WP_DEBUG è abilitato</p>';
	} else {
		echo '<p class="warning">⚠ WP_DEBUG non è abilitato - i log di debug potrebbero non essere disponibili</p>';
	}
	
	echo '</div>';

	?>
	
</div>
</body>
</html>

<?php
ob_end_flush();

