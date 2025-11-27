<?php
/**
 * Test rapido per verificare che nectar_slider sia escluso
 * Esegui: php quick-test-nectar.php
 */

// Trova wp-load.php
$wp_load = __DIR__ . '/../../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato in: $wp_load\n" );
}

require_once $wp_load;

echo "=== TEST NECTAR SLIDER EXCLUSION ===\n\n";

// Verifica classe
if ( ! class_exists( '\FP\SEO\Utils\PostTypes' ) ) {
	die( "ERRORE: Classe PostTypes non trovata\n" );
}

// Ottieni post types supportati
$supported = \FP\SEO\Utils\PostTypes::analyzable();

echo "Post types supportati (" . count( $supported ) . "):\n";
foreach ( $supported as $type ) {
	echo "  - $type\n";
}

echo "\n";

// Verifica esclusione
$nectar_included = in_array( 'nectar_slider', $supported, true );
$home_slider_included = in_array( 'home_slider', $supported, true );

if ( $nectar_included ) {
	echo "❌ ERRORE: nectar_slider è nei post types supportati!\n";
} else {
	echo "✅ OK: nectar_slider NON è nei post types supportati\n";
}

if ( $home_slider_included ) {
	echo "❌ ERRORE: home_slider è nei post types supportati!\n";
} else {
	echo "✅ OK: home_slider NON è nei post types supportati\n";
}

echo "\n";

// Verifica hook
global $wp_filter;
$save_post_hooks = isset( $wp_filter['save_post'] ) ? $wp_filter['save_post'] : null;
$save_post_nectar_hooks = isset( $wp_filter['save_post_nectar_slider'] ) ? $wp_filter['save_post_nectar_slider'] : null;

$fp_seo_generic_hooks = 0;
if ( $save_post_hooks ) {
	foreach ( $save_post_hooks->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
				$class = get_class( $callback['function'][0] );
				if ( strpos( $class, 'FP\\SEO' ) !== false ) {
					$fp_seo_generic_hooks++;
				}
			}
		}
	}
}

$fp_seo_nectar_hooks = 0;
if ( $save_post_nectar_hooks ) {
	foreach ( $save_post_nectar_hooks->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
				$class = get_class( $callback['function'][0] );
				if ( strpos( $class, 'FP\\SEO' ) !== false ) {
					$fp_seo_nectar_hooks++;
				}
			}
		}
	}
}

echo "Hook generici save_post del plugin FP SEO: $fp_seo_generic_hooks\n";
if ( $fp_seo_generic_hooks > 0 ) {
	echo "❌ ERRORE: Trovati hook generici!\n";
} else {
	echo "✅ OK: Nessun hook generico trovato\n";
}

echo "\nHook specifici save_post_nectar_slider del plugin FP SEO: $fp_seo_nectar_hooks\n";
if ( $fp_seo_nectar_hooks > 0 ) {
	echo "❌ ERRORE: Trovati hook specifici per nectar_slider!\n";
} else {
	echo "✅ OK: Nessun hook specifico trovato\n";
}

echo "\n=== RIEPILOGO ===\n";
if ( ! $nectar_included && ! $home_slider_included && $fp_seo_generic_hooks === 0 && $fp_seo_nectar_hooks === 0 ) {
	echo "✅ TUTTO OK! Nessun problema rilevato.\n";
} else {
	echo "❌ PROBLEMI RILEVATI:\n";
	if ( $nectar_included ) echo "  - nectar_slider è nei post types supportati\n";
	if ( $home_slider_included ) echo "  - home_slider è nei post types supportati\n";
	if ( $fp_seo_generic_hooks > 0 ) echo "  - Hook generici save_post trovati\n";
	if ( $fp_seo_nectar_hooks > 0 ) echo "  - Hook specifici save_post_nectar_slider trovati\n";
}

