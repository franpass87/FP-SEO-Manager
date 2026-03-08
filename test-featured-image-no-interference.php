<?php
/**
 * Test script to verify that FP-SEO-Manager does NOT interfere with featured images
 * 
 * Access: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-featured-image-no-interference.php
 */

// Load WordPress
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	$wp_load_path = dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php';
}
if ( ! file_exists( $wp_load_path ) ) {
	$wp_load_path = dirname( dirname( __FILE__ ) ) . '/wp-load.php';
}
require_once( $wp_load_path );

// Check if user is logged in
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( 'Access denied. Please log in as administrator.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Test Featured Image - No Interference</title>
	<?php wp_head(); ?>
	<style>
		body { font-family: Arial, sans-serif; padding: 20px; }
		.success { color: green; }
		.error { color: red; }
		.info { color: blue; }
		.test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
	</style>
</head>
<body>
	<h1>Test: FP-SEO-Manager Non Interferisce con Featured Images</h1>
	
	<?php
	// Test 1: Verifica che _thumbnail_id non sia in WordPressNativeProtection
	$test1_passed = true;
	$reflection = new ReflectionClass( 'FP\SEO\Editor\Helpers\WordPressNativeProtection' );
	$constants = $reflection->getConstants();
	
	if ( isset( $constants['NATIVE_META_KEYS'] ) ) {
		$native_keys = $constants['NATIVE_META_KEYS'];
		if ( in_array( '_thumbnail_id', $native_keys, true ) ) {
			$test1_passed = false;
		}
	}
	?>
	
	<div class="test-section">
		<h2>Test 1: _thumbnail_id NON è in WordPressNativeProtection</h2>
		<?php if ( $test1_passed ) : ?>
			<p class="success">✅ PASS: _thumbnail_id non è nella lista NATIVE_META_KEYS</p>
		<?php else : ?>
			<p class="error">❌ FAIL: _thumbnail_id è ancora nella lista NATIVE_META_KEYS</p>
		<?php endif; ?>
	</div>
	
	<?php
	// Test 2: Verifica che non ci siano metodi che preservano/fixano featured images
	$test2_passed = true;
	$metabox_class = new ReflectionClass( 'FP\SEO\Editor\Metabox' );
	$methods = $metabox_class->getMethods();
	$problematic_methods = array();
	
	foreach ( $methods as $method ) {
		$method_name = $method->getName();
		if ( strpos( $method_name, 'preserve_featured_image' ) !== false ||
			 strpos( $method_name, 'fix_featured_image' ) !== false ||
			 strpos( $method_name, 'ensure_featured_image' ) !== false ) {
			$problematic_methods[] = $method_name;
			$test2_passed = false;
		}
	}
	?>
	
	<div class="test-section">
		<h2>Test 2: Metodi problematici rimossi da Metabox</h2>
		<?php if ( $test2_passed ) : ?>
			<p class="success">✅ PASS: Nessun metodo che interferisce con featured images trovato</p>
		<?php else : ?>
			<p class="error">❌ FAIL: Metodi problematici trovati: <?php echo implode( ', ', $problematic_methods ); ?></p>
		<?php endif; ?>
	</div>
	
	<?php
	// Test 3: Verifica che non ci siano filtri admin_post_thumbnail_html registrati
	$test3_passed = true;
	global $wp_filter;
	$has_filter = false;
	
	if ( isset( $wp_filter['admin_post_thumbnail_html'] ) ) {
		$callbacks = $wp_filter['admin_post_thumbnail_html']->callbacks;
		foreach ( $callbacks as $priority => $callback_group ) {
			foreach ( $callback_group as $callback ) {
				if ( is_array( $callback['function'] ) && 
					 is_object( $callback['function'][0] ) &&
					 get_class( $callback['function'][0] ) === 'FP\SEO\Editor\Metabox' ) {
					$has_filter = true;
					$test3_passed = false;
					break 2;
				}
			}
		}
	}
	?>
	
	<div class="test-section">
		<h2>Test 3: Filtro admin_post_thumbnail_html NON registrato</h2>
		<?php if ( $test3_passed ) : ?>
			<p class="success">✅ PASS: Il plugin non registra filtri su admin_post_thumbnail_html</p>
		<?php else : ?>
			<p class="error">❌ FAIL: Il plugin registra ancora filtri su admin_post_thumbnail_html</p>
		<?php endif; ?>
	</div>
	
	<?php
	// Test 4: Verifica che wp.media sia disponibile (test JavaScript)
	?>
	
	<div class="test-section">
		<h2>Test 4: wp.media disponibile (JavaScript)</h2>
		<p class="info">Verifica in console del browser...</p>
		<div id="wp-media-test-result"></div>
		<script>
		jQuery(document).ready(function($) {
			var result = $('#wp-media-test-result');
			
			// Wait for wp.media to be available
			setTimeout(function() {
				if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
					result.html('<p class="success">✅ PASS: wp.media e wp.media.featuredImage sono disponibili</p>');
				} else {
					result.html('<p class="error">❌ FAIL: wp.media o wp.media.featuredImage non sono disponibili</p>');
				}
			}, 1000);
		});
		</script>
	</div>
	
	<?php
	// Test 5: Verifica che non ci siano hook save_post che preservano featured images
	$test5_passed = true;
	$problematic_hooks = array();
	
	if ( isset( $wp_filter['save_post'] ) ) {
		$callbacks = $wp_filter['save_post']->callbacks;
		foreach ( $callbacks as $priority => $callback_group ) {
			foreach ( $callback_group as $callback ) {
				if ( is_array( $callback['function'] ) && 
					 is_object( $callback['function'][0] ) &&
					 get_class( $callback['function'][0] ) === 'FP\SEO\Editor\Metabox' ) {
					$method_name = is_array( $callback['function'] ) ? $callback['function'][1] : '';
					if ( strpos( $method_name, 'preserve_featured_image' ) !== false ) {
						$problematic_hooks[] = $method_name . ' (priority: ' . $priority . ')';
						$test5_passed = false;
					}
				}
			}
		}
	}
	?>
	
	<div class="test-section">
		<h2>Test 5: Nessun hook save_post che preserva featured images</h2>
		<?php if ( $test5_passed ) : ?>
			<p class="success">✅ PASS: Nessun hook save_post che preserva featured images trovato</p>
		<?php else : ?>
			<p class="error">❌ FAIL: Hook problematici trovati: <?php echo implode( ', ', $problematic_hooks ); ?></p>
		<?php endif; ?>
	</div>
	
	<div class="test-section">
		<h2>Riepilogo</h2>
		<?php
		$all_tests_passed = $test1_passed && $test2_passed && $test3_passed && $test5_passed;
		if ( $all_tests_passed ) :
		?>
			<p class="success"><strong>✅ TUTTI I TEST SONO PASSATI</strong></p>
			<p>Il plugin FP-SEO-Manager non interferisce più con le funzioni standard di WordPress per le immagini in evidenza.</p>
		<?php else : ?>
			<p class="error"><strong>❌ ALCUNI TEST SONO FALLITI</strong></p>
			<p>Ci sono ancora interferenze con le funzioni standard di WordPress per le immagini in evidenza.</p>
		<?php endif; ?>
	</div>
	
	<?php wp_footer(); ?>
</body>
</html>


