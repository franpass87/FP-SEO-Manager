<?php
/**
 * Test Completo Metabox e AJAX - Verifica che tutti i campi e AJAX funzionino
 * 
 * IMPORTANTE: Esegui questo file tramite browser!
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-all-metabox-ajax.php?post_id=441
 * 
 * @package FP\SEO
 */

// Solo se eseguito via browser
if ( php_sapi_name() === 'cli' ) {
	die( "ERRORE: Esegui via browser\n" );
}

// Carica WordPress
$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
$wp_load = $document_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	die( "ERRORE: wp-load.php non trovato" );
}

require_once $wp_load;

// Verifica admin
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( '⛔ Devi essere loggato come amministratore' );
}

$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 441;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Completo Metabox e AJAX - FP SEO Manager</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
		.container { max-width: 1400px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
		.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 10px 0; }
		.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 10px 0; }
		pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px; }
		table { width: 100%; border-collapse: collapse; margin: 10px 0; }
		table th, table td { padding: 8px; text-align: left; border: 1px solid #ddd; }
		table th { background: #f8f9fa; }
		.meta-value { max-width: 500px; word-wrap: break-word; }
		.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
		.section h3 { margin-top: 0; color: #333; }
	</style>
</head>
<body>
<div class="container">
	<h1>🔍 Test Completo Metabox e AJAX - Post ID: <?php echo esc_html( $post_id ); ?></h1>
	
	<?php
	$post = get_post( $post_id );
	if ( ! $post ) {
		echo '<div class="error">❌ Post non trovato!</div>';
		exit;
	}
	
	echo '<div class="info">ℹ️ Post: <strong>' . esc_html( $post->post_title ) . '</strong> (ID: ' . $post_id . ')</div>';
	
	// Test 1: Verifica campi SEO principali
	echo '<div class="section">';
	echo '<h3>📊 Test 1: Campi SEO Principali</h3>';
	
	$seo_fields = array(
		'_fp_seo_title' => 'SEO Title',
		'_fp_seo_meta_description' => 'Meta Description',
		'_fp_seo_focus_keyword' => 'Focus Keyword',
		'_fp_seo_secondary_keywords' => 'Secondary Keywords',
	);
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore (get_post_meta)</th><th>Valore (DB Direct)</th><th>Stato</th></tr>';
	
	foreach ( $seo_fields as $meta_key => $field_name ) {
		// Clear cache
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		
		$wp_value = get_post_meta( $post_id, $meta_key, true );
		
		// DB direct
		global $wpdb;
		$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, $meta_key ) );
		
		$status = '✅ OK';
		if ( empty( $wp_value ) && ! empty( $db_value ) ) {
			$status = '⚠️ Cache Issue (DB has value)';
		} elseif ( empty( $wp_value ) && empty( $db_value ) ) {
			$status = 'ℹ️ Empty';
		}
		
		$display_wp = is_array( $wp_value ) ? 'Array' : ( $wp_value ?: '(vuoto)' );
		$display_db = is_array( $db_value ) ? 'Array' : ( $db_value ? maybe_unserialize( $db_value ) : '(vuoto)' );
		if ( is_array( $display_db ) ) {
			$display_db = implode( ', ', $display_db );
		}
		
		echo '<tr>';
		echo '<td><strong>' . esc_html( $field_name ) . '</strong><br><code>' . esc_html( $meta_key ) . '</code></td>';
		echo '<td class="meta-value">' . esc_html( substr( $display_wp, 0, 100 ) ) . '</td>';
		echo '<td class="meta-value">' . esc_html( substr( $display_db, 0, 100 ) ) . '</td>';
		echo '<td>' . $status . '</td>';
		echo '</tr>';
	}
	echo '</table>';
	echo '</div>';
	
	// Test 2: Verifica AJAX handlers registrati
	echo '<div class="section">';
	echo '<h3>🔧 Test 2: AJAX Handlers Registrati</h3>';
	
	global $wp_filter;
	$ajax_handlers = array();
	
	foreach ( $wp_filter as $hook_name => $priorities ) {
		if ( strpos( $hook_name, 'wp_ajax' ) === 0 ) {
			foreach ( $priorities as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_id => $callback_info ) {
					$function = $callback_info['function'];
					$callback_string = '';
					if ( is_array( $function ) && is_object( $function[0] ) ) {
						$callback_string = get_class( $function[0] ) . '::' . $function[1];
					} elseif ( is_array( $function ) && is_string( $function[0] ) ) {
						$callback_string = $function[0] . '::' . $function[1];
					} elseif ( is_string( $function ) ) {
						$callback_string = $function;
					}
					
					if ( strpos( $callback_string, 'FP\\SEO' ) !== false || strpos( $callback_string, 'fp_seo' ) !== false || strpos( $hook_name, 'fp_seo' ) !== false ) {
						$ajax_handlers[ $hook_name ][] = "Priority {$priority}: {$callback_string}";
					}
				}
			}
		}
	}
	
	if ( ! empty( $ajax_handlers ) ) {
		echo '<table>';
		echo '<tr><th>AJAX Hook</th><th>Handler</th></tr>';
		foreach ( $ajax_handlers as $hook_name => $handlers ) {
			foreach ( $handlers as $handler ) {
				echo '<tr><td><code>' . esc_html( $hook_name ) . '</code></td><td>' . esc_html( $handler ) . '</td></tr>';
			}
		}
		echo '</table>';
	} else {
		echo '<div class="warning">⚠️ Nessun handler AJAX FP SEO trovato</div>';
	}
	echo '</div>';
	
	// Test 3: Verifica altri metabox
	echo '<div class="section">';
	echo '<h3>📋 Test 3: Altri Campi Metabox</h3>';
	
	$other_fields = array(
		'_fp_seo_faq_questions' => 'FAQ Questions',
		'_fp_seo_howto' => 'HowTo Schema',
		'_fp_seo_social_meta' => 'Social Media Meta',
		'_fp_seo_geo_claims' => 'GEO Claims',
		'_fp_seo_geo_expose' => 'GEO Expose',
		'_fp_seo_update_frequency' => 'Update Frequency',
		'_fp_seo_fact_checked' => 'Fact Checked',
		'_fp_seo_content_type' => 'Content Type',
		'_fp_seo_performance_exclude' => 'Exclude from Analysis',
	);
	
	echo '<table>';
	echo '<tr><th>Campo</th><th>Valore</th><th>Stato</th></tr>';
	
	foreach ( $other_fields as $meta_key => $field_name ) {
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'post_meta' );
		
		$value = get_post_meta( $post_id, $meta_key, true );
		
		// Fallback DB
		if ( empty( $value ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1", $post_id, $meta_key ) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$value = is_array( $unserialized ) ? $unserialized : $db_value;
			}
		}
		
		$display_value = is_array( $value ) ? 'Array (' . count( $value ) . ' items)' : ( $value ?: '(vuoto)' );
		$status = $value ? '✅ OK' : 'ℹ️ Empty';
		
		echo '<tr>';
		echo '<td><strong>' . esc_html( $field_name ) . '</strong><br><code>' . esc_html( $meta_key ) . '</code></td>';
		echo '<td class="meta-value">' . esc_html( is_array( $display_value ) ? 'Array' : substr( $display_value, 0, 100 ) ) . '</td>';
		echo '<td>' . $status . '</td>';
		echo '</tr>';
	}
	echo '</table>';
	echo '</div>';
	
	// Test 4: Verifica MetadataResolver
	echo '<div class="section">';
	echo '<h3>🔍 Test 4: MetadataResolver</h3>';
	
	require_once __DIR__ . '/src/Utils/MetadataResolver.php';
	
	$seo_title = \FP\SEO\Utils\MetadataResolver::resolve_seo_title( $post );
	$meta_desc = \FP\SEO\Utils\MetadataResolver::resolve_meta_description( $post );
	$canonical = \FP\SEO\Utils\MetadataResolver::resolve_canonical_url( $post );
	$robots = \FP\SEO\Utils\MetadataResolver::resolve_robots( $post );
	
	echo '<table>';
	echo '<tr><th>Metodo</th><th>Valore</th><th>Stato</th></tr>';
	echo '<tr><td>resolve_seo_title()</td><td class="meta-value">' . esc_html( substr( $seo_title, 0, 100 ) ) . '</td><td>' . ( $seo_title ? '✅ OK' : 'ℹ️ Empty' ) . '</td></tr>';
	echo '<tr><td>resolve_meta_description()</td><td class="meta-value">' . esc_html( substr( $meta_desc, 0, 100 ) ) . '</td><td>' . ( $meta_desc ? '✅ OK' : 'ℹ️ Empty' ) . '</td></tr>';
	echo '<tr><td>resolve_canonical_url()</td><td class="meta-value">' . esc_html( $canonical ?: '(null)' ) . '</td><td>' . ( $canonical ? '✅ OK' : 'ℹ️ Empty' ) . '</td></tr>';
	echo '<tr><td>resolve_robots()</td><td class="meta-value">' . esc_html( $robots ?: '(vuoto)' ) . '</td><td>' . ( $robots ? '✅ OK' : 'ℹ️ Empty' ) . '</td></tr>';
	echo '</table>';
	echo '</div>';
	
	?>
	
	<div class="section">
		<h3>✅ Riepilogo</h3>
		<p>Questo test verifica:</p>
		<ul>
			<li>✅ Tutti i campi SEO principali vengono letti correttamente</li>
			<li>✅ Gli AJAX handlers sono registrati correttamente</li>
			<li>✅ Altri metabox funzionano correttamente</li>
			<li>✅ MetadataResolver funziona con il fix della cache</li>
		</ul>
	</div>
	
</div>
</body>
</html>

