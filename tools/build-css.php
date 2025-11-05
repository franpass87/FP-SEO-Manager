<?php
/**
 * Build CSS - Compila tutti i CSS in un unico file
 */

$base_dir = __DIR__ . '/assets/admin/css';
$output_file = $base_dir . '/admin.css';

// Leggi il file principale
$main_css = file_get_contents($output_file);

// Estrai tutti gli @import
preg_match_all("/@import url\('components\/([^']+)'\);/", $main_css, $matches);

$compiled_css = "/**\n * FP SEO Performance - Compiled Admin Styles\n * Auto-generated - DO NOT EDIT\n */\n\n";

// Rimuovi tutti gli @import dal file principale
$main_css_clean = preg_replace("/@import url\('components\/[^']+'\);\n?/", '', $main_css);

// Aggiungi tutti i componenti importati
foreach ($matches[1] as $component_file) {
	$component_path = $base_dir . '/components/' . $component_file;
	if (file_exists($component_path)) {
		$component_css = file_get_contents($component_path);
		$compiled_css .= "\n/* === Component: {$component_file} === */\n";
		$compiled_css .= $component_css . "\n";
		echo "✅ Aggiunto: {$component_file} (" . number_format(strlen($component_css)) . " bytes)\n";
	} else {
		echo "❌ Non trovato: {$component_file}\n";
	}
}

// Aggiungi il resto del CSS principale
$compiled_css .= "\n/* === Main CSS === */\n";
$compiled_css .= $main_css_clean;

// Salva il file compilato
file_put_contents($output_file, $compiled_css);

echo "\n✅ CSS compilato con successo!\n";
echo "📄 File: {$output_file}\n";
echo "📦 Dimensione: " . number_format(strlen($compiled_css)) . " bytes\n";

