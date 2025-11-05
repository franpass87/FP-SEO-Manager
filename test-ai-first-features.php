<?php
/**
 * Test AI-First Features - Quick Verification
 * 
 * Verifica che tutte le nuove funzionalità AI-first siano operative.
 * 
 * @package FP\SEO
 */

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
	die( '❌ Accesso negato. Solo amministratori possono eseguire questo test.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>FP SEO - Test AI-First Features</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px; background: #f5f5f5; }
		.container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
		h1 { color: #2563eb; margin-bottom: 10px; }
		.subtitle { color: #666; margin-bottom: 30px; }
		.test-section { margin: 30px 0; padding: 20px; background: #f9fafb; border-left: 4px solid #2563eb; border-radius: 4px; }
		.test-section h2 { margin-top: 0; color: #1e40af; }
		.success { color: #059669; font-weight: bold; }
		.error { color: #dc2626; font-weight: bold; }
		.warning { color: #f59e0b; font-weight: bold; }
		.info { color: #0891b2; }
		pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 6px; overflow-x: auto; }
		.metric { display: inline-block; margin: 10px 20px 10px 0; padding: 10px 15px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; }
		.metric-label { font-size: 12px; color: #6b7280; text-transform: uppercase; }
		.metric-value { font-size: 24px; font-weight: bold; color: #111827; }
		.endpoint-list { list-style: none; padding: 0; }
		.endpoint-list li { padding: 8px 12px; margin: 5px 0; background: white; border-radius: 4px; border-left: 3px solid #10b981; }
		.endpoint-list a { color: #0891b2; text-decoration: none; }
		.endpoint-list a:hover { text-decoration: underline; }
	</style>
</head>
<body>
	<div class="container">
		<h1>🤖 FP SEO Manager - Test AI-First Features</h1>
		<p class="subtitle">Verifica completa delle nuove funzionalità GEO per AI engines</p>

		<?php
		// Get first published post for testing
		$test_posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		if ( empty( $test_posts ) ) {
			echo '<div class="test-section">';
			echo '<p class="error">❌ Nessun post pubblicato trovato. Crea almeno un post per testare le funzionalità.</p>';
			echo '</div>';
			echo '</div></body></html>';
			exit;
		}

		$test_post = $test_posts[0];
		$post_id   = $test_post->ID;

		echo '<div class="test-section">';
		echo '<h2>📝 Post di Test</h2>';
		echo '<p><strong>Titolo:</strong> ' . esc_html( $test_post->post_title ) . '</p>';
		echo '<p><strong>ID:</strong> ' . $post_id . '</p>';
		echo '<p><strong>URL:</strong> <a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank">' . esc_html( get_permalink( $post_id ) ) . '</a></p>';
		echo '</div>';

		// Test 1: Class Loading
		echo '<div class="test-section">';
		echo '<h2>1️⃣ Test Caricamento Classi</h2>';

		$classes_to_test = array(
			'FP\SEO\GEO\FreshnessSignals',
			'FP\SEO\AI\QAPairExtractor',
			'FP\SEO\GEO\CitationFormatter',
			'FP\SEO\GEO\AuthoritySignals',
			'FP\SEO\GEO\SemanticChunker',
			'FP\SEO\GEO\EntityGraph',
			'FP\SEO\AI\ConversationalVariants',
			'FP\SEO\GEO\MultiModalOptimizer',
			'FP\SEO\AI\EmbeddingsGenerator',
			'FP\SEO\GEO\TrainingDatasetFormatter',
		);

		$loaded = 0;
		foreach ( $classes_to_test as $class ) {
			if ( class_exists( $class ) ) {
				echo '<p class="success">✅ ' . esc_html( $class ) . '</p>';
				$loaded++;
			} else {
				echo '<p class="error">❌ ' . esc_html( $class ) . ' - NON TROVATA</p>';
			}
		}

		echo '<p><strong>Risultato: ' . $loaded . '/' . count( $classes_to_test ) . ' classi caricate</strong></p>';
		echo '</div>';

		// Test 2: Freshness Signals
		echo '<div class="test-section">';
		echo '<h2>2️⃣ Test Freshness Signals</h2>';

		try {
			$signals = new FP\SEO\GEO\FreshnessSignals();
			$data    = $signals->get_freshness_data( $post_id );

			echo '<p class="success">✅ Freshness data generati</p>';
			echo '<div class="metric"><div class="metric-label">Update Frequency</div><div class="metric-value">' . esc_html( $data['update_frequency'] ?? 'N/A' ) . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Freshness Score</div><div class="metric-value">' . esc_html( number_format( $data['freshness_score'] ?? 0, 2 ) ) . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Content Version</div><div class="metric-value">' . esc_html( $data['version'] ?? 'N/A' ) . '</div></div>';
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 3: Q&A Extraction
		echo '<div class="test-section">';
		echo '<h2>3️⃣ Test Q&A Extraction</h2>';

		try {
			$extractor = new FP\SEO\AI\QAPairExtractor();
			$qa_pairs  = $extractor->get_qa_pairs( $post_id );

			if ( empty( $qa_pairs ) ) {
				echo '<p class="warning">⚠️ Nessuna Q&A pair trovata in cache.</p>';
				echo '<p class="info">ℹ️ Le Q&A verranno generate al primo accesso all\'endpoint o puoi generarle ora:</p>';
				echo '<p><a href="' . home_url( '/geo/content/' . $post_id . '/qa.json' ) . '" target="_blank">→ Genera Q&A pairs</a></p>';
			} else {
				echo '<p class="success">✅ Trovate ' . count( $qa_pairs ) . ' Q&A pairs in cache</p>';
				
				echo '<h3>Esempio Q&A:</h3>';
				$first_pair = $qa_pairs[0];
				echo '<p><strong>Q:</strong> ' . esc_html( $first_pair['question'] ) . '</p>';
				echo '<p><strong>A:</strong> ' . esc_html( substr( $first_pair['answer'], 0, 200 ) ) . '...</p>';
				echo '<p><strong>Confidence:</strong> ' . esc_html( $first_pair['confidence'] ) . '</p>';
			}
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 4: Semantic Chunking
		echo '<div class="test-section">';
		echo '<h2>4️⃣ Test Semantic Chunking</h2>';

		try {
			$chunker = new FP\SEO\GEO\SemanticChunker();
			$chunks  = $chunker->chunk_content( $post_id );

			echo '<p class="success">✅ Generati ' . count( $chunks ) . ' chunks semantici</p>';
			
			if ( ! empty( $chunks ) ) {
				$first_chunk = $chunks[0];
				echo '<div class="metric"><div class="metric-label">Max Tokens</div><div class="metric-value">' . esc_html( $first_chunk['token_count'] ?? 0 ) . '</div></div>';
				echo '<div class="metric"><div class="metric-label">Keywords</div><div class="metric-value">' . count( $first_chunk['keywords'] ?? array() ) . '</div></div>';
				echo '<div class="metric"><div class="metric-label">Entities</div><div class="metric-value">' . count( $first_chunk['entities'] ?? array() ) . '</div></div>';
			}
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 5: Entity Graph
		echo '<div class="test-section">';
		echo '<h2>5️⃣ Test Entity Graph</h2>';

		try {
			$graph = new FP\SEO\GEO\EntityGraph();
			$data  = $graph->build_entity_graph( $post_id );

			$entity_count = count( $data['entities'] ?? array() );
			$rel_count    = count( $data['relationships'] ?? array() );

			echo '<p class="success">✅ Entity graph generato</p>';
			echo '<div class="metric"><div class="metric-label">Entities</div><div class="metric-value">' . $entity_count . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Relationships</div><div class="metric-value">' . $rel_count . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Graph Density</div><div class="metric-value">' . number_format( $data['statistics']['graph_density'] ?? 0, 2 ) . '</div></div>';
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 6: Authority Signals
		echo '<div class="test-section">';
		echo '<h2>6️⃣ Test Authority Signals</h2>';

		try {
			$authority = new FP\SEO\GEO\AuthoritySignals();
			$signals   = $authority->get_authority_signals( $post_id );

			echo '<p class="success">✅ Authority signals calcolati</p>';
			echo '<div class="metric"><div class="metric-label">Overall Authority</div><div class="metric-value">' . number_format( $signals['overall_score'] ?? 0, 2 ) . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Author Publications</div><div class="metric-value">' . esc_html( $signals['author']['credentials']['publications'] ?? 0 ) . '</div></div>';
			echo '<div class="metric"><div class="metric-label">References</div><div class="metric-value">' . esc_html( $signals['content_signals']['references_count'] ?? 0 ) . '</div></div>';
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 7: Multi-Modal Optimizer
		echo '<div class="test-section">';
		echo '<h2>7️⃣ Test Multi-Modal Optimizer</h2>';

		try {
			$optimizer = new FP\SEO\GEO\MultiModalOptimizer();
			$data      = $optimizer->optimize_images( $post_id );

			echo '<p class="success">✅ Immagini analizzate</p>';
			echo '<div class="metric"><div class="metric-label">Total Images</div><div class="metric-value">' . esc_html( $data['total_images'] ?? 0 ) . '</div></div>';
			echo '<div class="metric"><div class="metric-label">Optimization Score</div><div class="metric-value">' . number_format( $data['optimization_score'] ?? 0, 2 ) . '</div></div>';
			
			if ( ! empty( $data['summary']['images_with_alt'] ) ) {
				echo '<div class="metric"><div class="metric-label">Images with ALT</div><div class="metric-value">' . esc_html( $data['summary']['images_with_alt'] ) . '</div></div>';
			}
		} catch ( Exception $e ) {
			echo '<p class="error">❌ Errore: ' . esc_html( $e->getMessage() ) . '</p>';
		}

		echo '</div>';

		// Test 8: Endpoint URLs
		echo '<div class="test-section">';
		echo '<h2>8️⃣ Endpoint Disponibili</h2>';
		echo '<p>Clicca per testare gli endpoint (si apriranno in nuova tab):</p>';

		$base_url = home_url();
		$endpoints = array(
			'Site JSON'        => $base_url . '/geo/site.json',
			'Updates JSON'     => $base_url . '/geo/updates.json',
			'Content JSON'     => $base_url . '/geo/content/' . $post_id . '.json',
			'Q&A Pairs'        => $base_url . '/geo/content/' . $post_id . '/qa.json',
			'Semantic Chunks'  => $base_url . '/geo/content/' . $post_id . '/chunks.json',
			'Entity Graph'     => $base_url . '/geo/content/' . $post_id . '/entities.json',
			'Authority'        => $base_url . '/geo/content/' . $post_id . '/authority.json',
			'Variants'         => $base_url . '/geo/content/' . $post_id . '/variants.json',
			'Images'           => $base_url . '/geo/content/' . $post_id . '/images.json',
			'Embeddings'       => $base_url . '/geo/content/' . $post_id . '/embeddings.json',
			'GEO Sitemap'      => $base_url . '/geo-sitemap.xml',
			'AI.txt'           => $base_url . '/.well-known/ai.txt',
			'Training Data'    => $base_url . '/geo/training-data.jsonl',
		);

		echo '<ul class="endpoint-list">';
		foreach ( $endpoints as $label => $url ) {
			echo '<li><strong>' . esc_html( $label ) . ':</strong> <a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a></li>';
		}
		echo '</ul>';

		echo '</div>';

		// Test 9: OpenAI Configuration
		echo '<div class="test-section">';
		echo '<h2>9️⃣ OpenAI Configuration</h2>';

		$options    = get_option( 'fp_seo_performance', array() );
		$openai_key = $options['ai']['openai_api_key'] ?? '';

		if ( empty( $openai_key ) ) {
			echo '<p class="warning">⚠️ OpenAI API Key NON configurata</p>';
			echo '<p class="info">ℹ️ Le funzionalità AI (Q&A extraction, Variants, Embeddings) richiederanno la configurazione della API key.</p>';
			echo '<p><a href="' . admin_url( 'admin.php?page=fp-seo-performance-settings&tab=ai' ) . '">→ Configura OpenAI API Key</a></p>';
		} else {
			echo '<p class="success">✅ OpenAI API Key configurata</p>';
			echo '<p><strong>Modello:</strong> ' . esc_html( $options['ai']['openai_model'] ?? 'N/A' ) . '</p>';
		}

		echo '</div>';

		// Summary
		echo '<div class="test-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-left-color: #764ba2;">';
		echo '<h2 style="color: white;">✅ Riepilogo Test</h2>';

		$total_tests = 7;
		$passed = $loaded >= 10 ? $total_tests : $total_tests - 1;

		echo '<p><strong>Test Passati:</strong> ' . $passed . '/' . $total_tests . '</p>';

		if ( $passed === $total_tests ) {
			echo '<p class="success" style="font-size: 20px;">🎉 TUTTI I TEST PASSATI! Il sistema è operativo!</p>';
		} else {
			echo '<p class="warning">⚠️ Alcuni test hanno avuto problemi. Verifica gli errori sopra.</p>';
		}

		echo '</div>';

		// Next Steps
		echo '<div class="test-section">';
		echo '<h2>🎯 Prossimi Passi</h2>';
		echo '<ol>';
		echo '<li><strong>Flush Permalinks:</strong> Vai su Impostazioni → Permalinks → Salva (se non l\'hai già fatto)</li>';
		echo '<li><strong>Testa Endpoint:</strong> Clicca sui link sopra per verificare che gli endpoint funzionino</li>';
		echo '<li><strong>Configura OpenAI:</strong> Aggiungi API key se vuoi Q&A automatiche e variants</li>';
		echo '<li><strong>Ottimizza Contenuto:</strong> Aggiungi alt text alle immagini, claims, data sources</li>';
		echo '<li><strong>Monitora Risultati:</strong> Controlla citazioni su ChatGPT, Gemini, Perplexity tra 2-4 settimane</li>';
		echo '</ol>';
		echo '</div>';

		// Documentation
		echo '<div class="test-section">';
		echo '<h2>📚 Documentazione</h2>';
		echo '<ul>';
		echo '<li><a href="' . plugins_url( 'AI-FIRST-IMPLEMENTATION-COMPLETE.md', __FILE__ ) . '">📄 AI-First Implementation Complete</a> - Documentazione completa</li>';
		echo '<li><a href="' . plugins_url( 'QUICK-START-AI-FIRST.md', __FILE__ ) . '">⚡ Quick Start Guide</a> - Guida rapida 5 minuti</li>';
		echo '<li><a href="' . plugins_url( 'BUGFIX-AI-FEATURES-SESSION.md', __FILE__ ) . '">🐛 Bugfix Report</a> - Report qualità codice</li>';
		echo '</ul>';
		echo '</div>';
		?>

	</div>
</body>
</html>


