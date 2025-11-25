<?php
/**
 * Test script per verificare l'inizializzazione del MetaboxRenderer nel costruttore di Metabox.
 * 
 * Questo script verifica che:
 * 1. Il renderer venga inizializzato correttamente nel costruttore
 * 2. Non ci siano errori fatali durante l'inizializzazione
 * 3. Il metabox venga registrato correttamente
 */

// Carica WordPress - usa percorso assoluto dalla workspace
$wp_load_path = 'C:\Users\franc\Local Sites\fp-development\app\public\wp-load.php';
if (!file_exists($wp_load_path)) {
    // Fallback: prova percorso relativo
    $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
}
if (!file_exists($wp_load_path)) {
    die('ERRORE: wp-load.php non trovato.<br>File corrente: ' . __FILE__ . '<br>Directory corrente: ' . dirname(__FILE__));
}
require_once $wp_load_path;

// Solo per admin
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Test Renderer Init</title></head><body>';
echo '<h1>Test Inizializzazione MetaboxRenderer nel Costruttore</h1>';

try {
    // Verifica che le classi esistano
    if (!class_exists('\FP\SEO\Editor\Metabox')) {
        die('<p style="color:red;">ERRORE: Classe Metabox non trovata</p>');
    }
    
    if (!class_exists('\FP\SEO\Editor\MetaboxRenderer')) {
        die('<p style="color:red;">ERRORE: Classe MetaboxRenderer non trovata</p>');
    }
    
    echo '<p style="color:green;">✓ Classi trovate correttamente</p>';
    
    // Crea un'istanza di Metabox e verifica che il renderer sia inizializzato
    echo '<h2>1. Creazione istanza Metabox...</h2>';
    
    $reflection = new ReflectionClass('\FP\SEO\Editor\Metabox');
    $rendererProperty = $reflection->getProperty('renderer');
    $rendererProperty->setAccessible(true);
    
    $metabox = new \FP\SEO\Editor\Metabox();
    $renderer = $rendererProperty->getValue($metabox);
    
    echo '<p>Metabox istanziato: ' . (isset($metabox) ? '✓' : '✗') . '</p>';
    echo '<p>Renderer inizializzato: ' . ($renderer !== null ? '✓' : '<span style="color:red;">✗ NULL</span>') . '</p>';
    
    if ($renderer !== null) {
        echo '<p style="color:green;">✓ Renderer inizializzato correttamente nel costruttore</p>';
        echo '<p>Classe renderer: ' . get_class($renderer) . '</p>';
        
        if (method_exists($renderer, 'render')) {
            echo '<p style="color:green;">✓ Metodo render() presente</p>';
        } else {
            echo '<p style="color:red;">✗ Metodo render() NON presente</p>';
        }
    } else {
        echo '<p style="color:red;">✗ ERRORE: Renderer è NULL dopo il costruttore</p>';
    }
    
    // Verifica che l'hook add_meta_boxes sia registrato
    echo '<h2>2. Verifica hook add_meta_boxes...</h2>';
    
    global $wp_filter;
    $hook_registered = false;
    
    if (isset($wp_filter['add_meta_boxes'])) {
        $callbacks = $wp_filter['add_meta_boxes']->callbacks;
        foreach ($callbacks as $priority => $hooks) {
            foreach ($hooks as $hook) {
                if (is_array($hook['function']) && 
                    $hook['function'][0] instanceof \FP\SEO\Editor\Metabox &&
                    $hook['function'][1] === 'add_meta_box') {
                    $hook_registered = true;
                    echo '<p style="color:green;">✓ Hook add_meta_boxes registrato con priorità ' . $priority . '</p>';
                    break 2;
                }
            }
        }
    }
    
    if (!$hook_registered) {
        echo '<p style="color:orange;">⚠ Hook add_meta_boxes non trovato (potrebbe essere registrato in un altro modo)</p>';
    }
    
    // Test chiamata diretta al metodo register()
    echo '<h2>3. Test chiamata register()...</h2>';
    
    try {
        $metabox->register();
        $renderer_after_register = $rendererProperty->getValue($metabox);
        
        if ($renderer_after_register !== null) {
            echo '<p style="color:green;">✓ Renderer ancora disponibile dopo register()</p>';
        } else {
            echo '<p style="color:red;">✗ Renderer è NULL dopo register()</p>';
        }
    } catch (\Throwable $e) {
        echo '<p style="color:red;">✗ Errore in register(): ' . esc_html($e->getMessage()) . '</p>';
        echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
    }
    
    echo '<h2>4. Test render() su un post...</h2>';
    
    $post = get_post(441);
    if ($post) {
        echo '<p>Post trovato: ID ' . $post->ID . ' - ' . $post->post_title . '</p>';
        
        // Simula il rendering
        ob_start();
        try {
            $metabox->render($post);
            $output = ob_get_clean();
            
            $has_fallback = strpos($output, 'Modalità Fallback') !== false;
            $has_renderer_output = strlen($output) > 1000;
            
            echo '<p>Output generato: ' . strlen($output) . ' caratteri</p>';
            
            if ($has_fallback) {
                echo '<p style="color:orange;">⚠ Modalità FALLBACK rilevata (renderer potrebbe non essere stato inizializzato)</p>';
            } else {
                echo '<p style="color:green;">✓ Output completo generato (non in modalità fallback)</p>';
            }
            
            if ($has_renderer_output) {
                echo '<p style="color:green;">✓ Output completo del renderer</p>';
            } else {
                echo '<p style="color:orange;">⚠ Output potrebbe essere incompleto</p>';
            }
            
            echo '<h3>Anteprima output (primi 500 caratteri):</h3>';
            echo '<pre style="background:#f0f0f0;padding:10px;max-height:200px;overflow:auto;">' . esc_html(substr($output, 0, 500)) . '</pre>';
            
        } catch (\Throwable $e) {
            ob_end_clean();
            echo '<p style="color:red;">✗ Errore in render(): ' . esc_html($e->getMessage()) . '</p>';
            echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
        }
    } else {
        echo '<p style="color:orange;">⚠ Post ID 441 non trovato</p>';
    }
    
    echo '<h2>Riepilogo</h2>';
    echo '<ul>';
    echo '<li>Renderer inizializzato nel costruttore: ' . ($renderer !== null ? '✓' : '✗') . '</li>';
    echo '<li>Hook add_meta_boxes registrato: ' . ($hook_registered ? '✓' : '?') . '</li>';
    echo '<li>Renderer disponibile dopo register(): ' . (isset($renderer_after_register) && $renderer_after_register !== null ? '✓' : '✗') . '</li>';
    echo '</ul>';
    
} catch (\Throwable $e) {
    echo '<p style="color:red;">ERRORE FATALE: ' . esc_html($e->getMessage()) . '</p>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
}

echo '</body></html>';
