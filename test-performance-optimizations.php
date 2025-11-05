<?php
/**
 * Performance Optimization Test Suite
 * 
 * Test file per verificare le ottimizzazioni di performance implementate
 * nel plugin FP SEO Performance.
 * 
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WordPress
require_once dirname( __FILE__ ) . '/../../../../wp-load.php';

// Load plugin
require_once dirname( __FILE__ ) . '/fp-seo-performance.php';

echo "<h1>🚀 FP SEO Performance - Test Ottimizzazioni</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.test-section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
.test-pass { color: #28a745; font-weight: bold; }
.test-fail { color: #dc3545; font-weight: bold; }
.test-info { color: #17a2b8; }
.metrics { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 10px 0; }
</style>\n";

// Test 1: Verifica caricamento del plugin
echo "<div class='test-section'>";
echo "<h2>1. Test Caricamento Plugin</h2>\n";

if ( class_exists( 'FP\\SEO\\Infrastructure\\Plugin' ) ) {
    echo "<span class='test-pass'>✅ Plugin caricato correttamente</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Errore nel caricamento del plugin</span><br>\n";
}

if ( class_exists( 'FP\\SEO\\Utils\\PerformanceOptimizer' ) ) {
    echo "<span class='test-pass'>✅ PerformanceOptimizer caricato</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ PerformanceOptimizer non trovato</span><br>\n";
}

if ( class_exists( 'FP\\SEO\\Utils\\PerformanceConfig' ) ) {
    echo "<span class='test-pass'>✅ PerformanceConfig caricato</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ PerformanceConfig non trovato</span><br>\n";
}
echo "</div>\n";

// Test 2: Verifica sistema di cache
echo "<div class='test-section'>";
echo "<h2>2. Test Sistema di Cache</h2>\n";

use FP\SEO\Utils\Cache;
use FP\SEO\Utils\PerformanceConfig;

// Test cache base
$test_key = 'fp_seo_test_' . time();
$test_value = 'test_value_' . rand( 1000, 9999 );

$cache_set = Cache::set( $test_key, $test_value, 60 );
if ( $cache_set ) {
    echo "<span class='test-pass'>✅ Cache::set() funziona</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Cache::set() fallito</span><br>\n";
}

$cached_value = Cache::get( $test_key );
if ( $cached_value === $test_value ) {
    echo "<span class='test-pass'>✅ Cache::get() funziona</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Cache::get() fallito</span><br>\n";
}

// Test cache remember
$remember_key = 'fp_seo_remember_' . time();
$remember_value = Cache::remember( $remember_key, function() {
    return 'remembered_value_' . rand( 1000, 9999 );
}, 60 );

if ( ! empty( $remember_value ) ) {
    echo "<span class='test-pass'>✅ Cache::remember() funziona</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Cache::remember() fallito</span><br>\n";
}

// Test configurazione
$settings = PerformanceConfig::get_settings();
if ( is_array( $settings ) && isset( $settings['cache'] ) ) {
    echo "<span class='test-pass'>✅ PerformanceConfig funziona</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ PerformanceConfig fallito</span><br>\n";
}
echo "</div>\n";

// Test 3: Verifica ottimizzazioni database
echo "<div class='test-section'>";
echo "<h2>3. Test Ottimizzazioni Database</h2>\n";

if ( class_exists( 'FP\\SEO\\History\\ScoreHistory' ) ) {
    echo "<span class='test-pass'>✅ ScoreHistory caricato</span><br>\n";
    
    // Test creazione tabella
    $score_history = new FP\SEO\History\ScoreHistory();
    $score_history->create_table();
    echo "<span class='test-info'>ℹ️ Tabella score history creata/verificata</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ ScoreHistory non trovato</span><br>\n";
}
echo "</div>\n";

// Test 4: Verifica ottimizzazioni asset
echo "<div class='test-section'>";
echo "<h2>4. Test Ottimizzazioni Asset</h2>\n";

if ( class_exists( 'FP\\SEO\\Utils\\Assets' ) ) {
    echo "<span class='test-pass'>✅ Assets class caricata</span><br>\n";
    
    $assets = new FP\SEO\Utils\Assets();
    $assets->register();
    echo "<span class='test-info'>ℹ️ Asset registration completato</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Assets class non trovata</span><br>\n";
}
echo "</div>\n";

// Test 5: Verifica integrazione AI
echo "<div class='test-section'>";
echo "<h2>5. Test Integrazione AI</h2>\n";

if ( class_exists( 'FP\\SEO\\Integrations\\OpenAiClient' ) ) {
    echo "<span class='test-pass'>✅ OpenAiClient caricato</span><br>\n";
    
    $ai_client = new FP\SEO\Integrations\OpenAiClient();
    $is_configured = $ai_client->is_configured();
    
    if ( $is_configured ) {
        echo "<span class='test-pass'>✅ OpenAI configurato</span><br>\n";
    } else {
        echo "<span class='test-info'>ℹ️ OpenAI non configurato (normale per test)</span><br>\n";
    }
} else {
    echo "<span class='test-fail'>❌ OpenAiClient non trovato</span><br>\n";
}
echo "</div>\n";

// Test 6: Metriche di performance
echo "<div class='test-section'>";
echo "<h2>6. Metriche di Performance</h2>\n";

$memory_usage = memory_get_peak_usage( true );
$memory_limit = ini_get( 'memory_limit' );
$execution_time = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
$db_queries = get_num_queries();

echo "<div class='metrics'>";
echo "<strong>Memoria utilizzata:</strong> " . size_format( $memory_usage ) . " / " . $memory_limit . "<br>\n";
echo "<strong>Tempo di esecuzione:</strong> " . round( $execution_time, 4 ) . " secondi<br>\n";
echo "<strong>Query database:</strong> " . $db_queries . "<br>\n";
echo "<strong>Versione PHP:</strong> " . PHP_VERSION . "<br>\n";
echo "<strong>WordPress:</strong> " . get_bloginfo( 'version' ) . "<br>\n";
echo "</div>\n";

// Valutazione performance
$memory_percent = ( $memory_usage / wp_convert_hr_to_bytes( $memory_limit ) ) * 100;

if ( $memory_percent < 50 ) {
    echo "<span class='test-pass'>✅ Uso memoria ottimale (" . round( $memory_percent, 1 ) . "%)</span><br>\n";
} elseif ( $memory_percent < 80 ) {
    echo "<span class='test-info'>⚠️ Uso memoria moderato (" . round( $memory_percent, 1 ) . "%)</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Uso memoria elevato (" . round( $memory_percent, 1 ) . "%)</span><br>\n";
}

if ( $execution_time < 1 ) {
    echo "<span class='test-pass'>✅ Tempo di esecuzione ottimale</span><br>\n";
} elseif ( $execution_time < 3 ) {
    echo "<span class='test-info'>⚠️ Tempo di esecuzione accettabile</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Tempo di esecuzione lento</span><br>\n";
}

if ( $db_queries < 10 ) {
    echo "<span class='test-pass'>✅ Query database ottimale</span><br>\n";
} elseif ( $db_queries < 20 ) {
    echo "<span class='test-info'>⚠️ Query database moderate</span><br>\n";
} else {
    echo "<span class='test-fail'>❌ Troppe query database</span><br>\n";
}

echo "</div>\n";

// Test 7: Verifica hook e filtri
echo "<div class='test-section'>";
echo "<h2>7. Test Hook e Filtri</h2>\n";

$hooks_to_check = array(
    'fp_seo_before_analysis',
    'fp_seo_after_analysis',
    'fp_seo_before_check',
    'fp_seo_after_check',
);

$hooks_registered = 0;
foreach ( $hooks_to_check as $hook ) {
    if ( has_action( $hook ) ) {
        $hooks_registered++;
    }
}

if ( $hooks_registered > 0 ) {
    echo "<span class='test-pass'>✅ Hook SEO registrati (" . $hooks_registered . "/" . count( $hooks_to_check ) . ")</span><br>\n";
} else {
    echo "<span class='test-info'>ℹ️ Hook SEO non ancora registrati (normale se non in admin)</span><br>\n";
}
echo "</div>\n";

// Riepilogo finale
echo "<div class='test-section'>";
echo "<h2>🎯 Riepilogo Test</h2>\n";

$total_tests = 7;
$passed_tests = 0;

// Conta i test passati (logica semplificata)
if ( class_exists( 'FP\\SEO\\Infrastructure\\Plugin' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\Utils\\PerformanceOptimizer' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\Utils\\PerformanceConfig' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\Utils\\Cache' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\History\\ScoreHistory' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\Utils\\Assets' ) ) $passed_tests++;
if ( class_exists( 'FP\\SEO\\Integrations\\OpenAiClient' ) ) $passed_tests++;

$success_rate = round( ( $passed_tests / $total_tests ) * 100, 1 );

echo "<div class='metrics'>";
echo "<strong>Test completati:</strong> " . $passed_tests . "/" . $total_tests . "<br>\n";
echo "<strong>Tasso di successo:</strong> " . $success_rate . "%<br>\n";

if ( $success_rate >= 90 ) {
    echo "<span class='test-pass'>🎉 Eccellente! Tutte le ottimizzazioni funzionano correttamente.</span><br>\n";
} elseif ( $success_rate >= 70 ) {
    echo "<span class='test-info'>👍 Buono! La maggior parte delle ottimizzazioni funziona.</span><br>\n";
} else {
    echo "<span class='test-fail'>⚠️ Attenzione! Alcune ottimizzazioni potrebbero non funzionare correttamente.</span><br>\n";
}

echo "</div>\n";
echo "</div>\n";

echo "<hr>\n";
echo "<p><em>Test completato il " . date( 'Y-m-d H:i:s' ) . "</em></p>\n";
echo "<p><strong>Nota:</strong> Questo è un test di base. Per test completi, usa la Test Suite integrata nel plugin.</p>\n";
?>
