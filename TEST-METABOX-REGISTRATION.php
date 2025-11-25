<?php
/**
 * Test script per verificare la registrazione del metabox.
 * 
 * Eseguire da browser: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/TEST-METABOX-REGISTRATION.php
 */

// Carica WordPress
require_once __DIR__ . '/../../../../wp-load.php';

// Verifica se siamo in admin
echo "<h1>Test Registrazione Metabox SEO</h1>";
echo "<p><strong>is_admin():</strong> " . (is_admin() ? 'TRUE' : 'FALSE') . "</p>";
echo "<p><strong>WP_ADMIN definita:</strong> " . (defined('WP_ADMIN') ? (WP_ADMIN ? 'TRUE' : 'FALSE') : 'NON DEFINITA') . "</p>";
echo "<p><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";

// Verifica se il plugin è caricato
if (class_exists('FP\SEO\Infrastructure\Plugin')) {
    echo "<p style='color: green;'><strong>✓ Plugin caricato</strong></p>";
    
    $plugin = FP\SEO\Infrastructure\Plugin::instance();
    $container = $plugin->get_container();
    $registry = $plugin->get_registry();
    
    echo "<p><strong>Registry booted:</strong> " . ($registry->is_booted() ? 'TRUE' : 'FALSE') . "</p>";
    
    // Verifica se MainMetaboxServiceProvider è registrato
    $providers = $registry->get_providers();
    $mainMetaboxProvider = null;
    foreach ($providers as $provider) {
        if ($provider instanceof FP\SEO\Infrastructure\Providers\Metaboxes\MainMetaboxServiceProvider) {
            $mainMetaboxProvider = $provider;
            break;
        }
    }
    
    if ($mainMetaboxProvider) {
        echo "<p style='color: green;'><strong>✓ MainMetaboxServiceProvider registrato</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ MainMetaboxServiceProvider NON registrato</strong></p>";
    }
    
    // Verifica se Metabox è nel container
    try {
        $metabox = $container->get(FP\SEO\Editor\Metabox::class);
        echo "<p style='color: green;'><strong>✓ Metabox nel container</strong></p>";
        echo "<p><strong>Classe:</strong> " . get_class($metabox) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Metabox NON nel container</strong></p>";
        echo "<p><strong>Errore:</strong> " . $e->getMessage() . "</p>";
    }
    
    // Verifica hook registrati
    global $wp_filter;
    if (isset($wp_filter['add_meta_boxes'])) {
        echo "<p style='color: green;'><strong>✓ Hook 'add_meta_boxes' registrato</strong></p>";
        $callbacks = $wp_filter['add_meta_boxes']->callbacks;
        $metaboxHookFound = false;
        foreach ($callbacks as $priority => $hooks) {
            foreach ($hooks as $hook) {
                if (is_array($hook['function']) && 
                    is_object($hook['function'][0]) && 
                    $hook['function'][0] instanceof FP\SEO\Editor\Metabox) {
                    $metaboxHookFound = true;
                    echo "<p style='color: green;'><strong>✓ Hook metabox trovato (priorità: $priority)</strong></p>";
                    break 2;
                }
            }
        }
        if (!$metaboxHookFound) {
            echo "<p style='color: orange;'><strong>⚠ Hook metabox non trovato in add_meta_boxes</strong></p>";
        }
    } else {
        echo "<p style='color: orange;'><strong>⚠ Hook 'add_meta_boxes' non registrato ancora</strong></p>";
    }
    
} else {
    echo "<p style='color: red;'><strong>✗ Plugin NON caricato</strong></p>";
}

// Verifica post types supportati
if (function_exists('get_post_types')) {
    echo "<h2>Post Types Analizzabili</h2>";
    $postTypes = FP\SEO\Utils\PostTypes::analyzable();
    echo "<p><strong>Post types:</strong> " . implode(', ', $postTypes) . "</p>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> Questo è un test diagnostico. Verificare che tutto sia TRUE/verde per il corretto funzionamento.</p>";

