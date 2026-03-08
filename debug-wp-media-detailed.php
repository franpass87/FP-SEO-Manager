<?php
/**
 * Detailed debug script to test wp.media functionality and find interferences
 * 
 * Access: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/debug-wp-media-detailed.php
 */

// Load WordPress - adjust path based on plugin location
$wp_load_path = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
if ( ! file_exists( $wp_load_path ) ) {
	// Try alternative path
	$wp_load_path = dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php';
}
if ( ! file_exists( $wp_load_path ) ) {
	// Try another alternative
	$wp_load_path = dirname( dirname( __FILE__ ) ) . '/wp-load.php';
}
require_once( $wp_load_path );

// Check if user is logged in
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( 'Access denied. Please log in as administrator.' );
}

// Get a test post ID
$test_post_id = 0;
$posts = get_posts( array( 'numberposts' => 1, 'post_type' => 'post' ) );
if ( ! empty( $posts ) ) {
	$test_post_id = $posts[0]->ID;
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>FP SEO - wp.media Detailed Debug</title>
	<?php wp_head(); ?>
	<style>
		body { font-family: Arial, sans-serif; padding: 20px; background: #fff; }
		.test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; border-radius: 5px; }
		.success { color: green; font-weight: bold; }
		.error { color: red; font-weight: bold; }
		.warning { color: orange; font-weight: bold; }
		.info { color: blue; }
		pre { background: #f0f0f0; padding: 10px; overflow-x: auto; border: 1px solid #ddd; }
		button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #0073aa; color: white; border: none; border-radius: 3px; }
		button:hover { background: #005a87; }
		#console-output { background: #000; color: #0f0; padding: 15px; font-family: monospace; max-height: 400px; overflow-y: auto; }
		.console-line { margin: 2px 0; }
		.console-error { color: #f00; }
		.console-warn { color: #ff0; }
		.console-info { color: #0ff; }
	</style>
</head>
<body>
	<h1>FP SEO Manager - wp.media Detailed Debug Tool</h1>
	
	<div class="test-section">
		<h2>Console Output</h2>
		<div id="console-output"></div>
		<button onclick="clearConsole()">Clear Console</button>
	</div>
	
	<div class="test-section">
		<h2>Test 1: Verifica wp.media e Componenti</h2>
		<button onclick="testWpMediaComponents()">Test Completo wp.media</button>
		<div id="test1-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 2: Test Featured Image (come WordPress Core)</h2>
		<button onclick="testFeaturedImageCore()">Test Featured Image Core</button>
		<div id="test2-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 3: Verifica Event Listeners</h2>
		<button onclick="checkEventListeners()">Verifica Event Listeners</button>
		<div id="test3-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 4: Verifica Scripts FP SEO</h2>
		<button onclick="checkFpSeoScripts()">Verifica Scripts FP SEO</button>
		<div id="test4-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 5: Simula Click Featured Image</h2>
		<button onclick="simulateFeaturedImageClick()">Simula Click</button>
		<div id="test5-results"></div>
	</div>
	
	<script>
		const consoleOutput = document.getElementById('console-output');
		
		function consoleLog(message, type) {
			const line = document.createElement('div');
			line.className = 'console-line console-' + (type || 'info');
			line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
			consoleOutput.appendChild(line);
			consoleOutput.scrollTop = consoleOutput.scrollHeight;
		}
		
		function clearConsole() {
			consoleOutput.innerHTML = '';
		}
		
		function log(elementId, message, type) {
			const el = document.getElementById(elementId);
			const className = type || 'info';
			el.innerHTML += '<div class="' + className + '">' + message + '</div>';
			consoleLog(message, type);
		}
		
		function testWpMediaComponents() {
			const resultsId = 'test1-results';
			document.getElementById(resultsId).innerHTML = '';
			consoleLog('=== Test 1: Verifica wp.media ===', 'info');
			
			// Test wp
			if (typeof wp === 'undefined') {
				log(resultsId, 'ERROR: wp is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp is defined', 'success');
			
			// Test wp.media
			if (typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media is defined', 'success');
			
			// Test wp.media.featuredImage
			if (typeof wp.media.featuredImage === 'undefined') {
				log(resultsId, 'ERROR: wp.media.featuredImage is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.featuredImage is defined', 'success');
			
			// Test wp.media.featuredImage.init
			if (typeof wp.media.featuredImage.init !== 'function') {
				log(resultsId, 'ERROR: wp.media.featuredImage.init is not a function', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.featuredImage.init is a function', 'success');
			
			// Test wp.media.featuredImage.frame
			if (typeof wp.media.featuredImage.frame !== 'function') {
				log(resultsId, 'ERROR: wp.media.featuredImage.frame is not a function', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.featuredImage.frame is a function', 'success');
			
			// Test wp.media.controller
			if (typeof wp.media.controller === 'undefined') {
				log(resultsId, 'ERROR: wp.media.controller is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.controller is defined', 'success');
			
			// Test wp.media.controller.FeaturedImage
			if (typeof wp.media.controller.FeaturedImage === 'undefined') {
				log(resultsId, 'ERROR: wp.media.controller.FeaturedImage is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.controller.FeaturedImage is defined', 'success');
			
			// Check if init has been called
			try {
				const frame = wp.media.featuredImage.frame();
				if (frame) {
					log(resultsId, '✓ wp.media.featuredImage.frame() returns a frame', 'success');
					log(resultsId, 'Frame ID: ' + (frame.id || 'no ID'), 'info');
				} else {
					log(resultsId, 'WARNING: wp.media.featuredImage.frame() returns null', 'warning');
				}
			} catch (error) {
				log(resultsId, 'ERROR calling wp.media.featuredImage.frame(): ' + error.message, 'error');
			}
			
			log(resultsId, '=== Test 1 completato ===', 'info');
		}
		
		function testFeaturedImageCore() {
			const resultsId = 'test2-results';
			document.getElementById(resultsId).innerHTML = '';
			consoleLog('=== Test 2: Test Featured Image Core ===', 'info');
			
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not available', 'error');
				return;
			}
			
			try {
				// Check if featuredImage.init has been called
				const postImageDiv = document.getElementById('postimagediv');
				if (!postImageDiv) {
					log(resultsId, 'WARNING: postimagediv not found (not on post editor page?)', 'warning');
					log(resultsId, 'Creating test postimagediv...', 'info');
					
					// Create a test div
					const testDiv = document.createElement('div');
					testDiv.id = 'postimagediv';
					testDiv.innerHTML = '<p><a href="#" id="set-post-thumbnail">Imposta immagine in evidenza</a></p>';
					document.body.appendChild(testDiv);
					
					// Initialize featured image
					if (typeof wp.media.featuredImage.init === 'function') {
						wp.media.featuredImage.init();
						log(resultsId, '✓ wp.media.featuredImage.init() called', 'success');
					}
				} else {
					log(resultsId, '✓ postimagediv found', 'success');
				}
				
				// Try to get the frame
				const frame = wp.media.featuredImage.frame();
				
				if (!frame) {
					log(resultsId, 'ERROR: Featured image frame is null', 'error');
					return;
				}
				
				log(resultsId, '✓ Featured image frame created', 'success');
				log(resultsId, 'Frame ID: ' + (frame.id || 'no ID'), 'info');
				
				// Add event listeners
				frame.on('open', function() {
					log(resultsId, '✓ Frame opened successfully', 'success');
				});
				
				frame.on('close', function() {
					log(resultsId, 'Frame closed', 'info');
				});
				
				frame.on('select', function() {
					log(resultsId, 'Image selected!', 'success');
				});
				
				// Try to open
				log(resultsId, 'Attempting to open frame...', 'info');
				frame.open();
				
			} catch (error) {
				log(resultsId, 'ERROR: ' + error.message, 'error');
				log(resultsId, 'Stack: ' + error.stack, 'error');
			}
		}
		
		function checkEventListeners() {
			const resultsId = 'test3-results';
			document.getElementById(resultsId).innerHTML = '';
			consoleLog('=== Test 3: Verifica Event Listeners ===', 'info');
			
			const postImageDiv = document.getElementById('postimagediv');
			if (!postImageDiv) {
				log(resultsId, 'WARNING: postimagediv not found', 'warning');
				return;
			}
			
			const setThumbnail = document.getElementById('set-post-thumbnail');
			if (!setThumbnail) {
				log(resultsId, 'WARNING: set-post-thumbnail link not found', 'warning');
				return;
			}
			
			log(resultsId, '✓ set-post-thumbnail link found', 'success');
			log(resultsId, 'Link href: ' + setThumbnail.href, 'info');
			log(resultsId, 'Link onclick: ' + (setThumbnail.onclick ? 'has onclick' : 'no onclick'), 'info');
			
			// Check jQuery events
			if (typeof jQuery !== 'undefined') {
				const $link = jQuery(setThumbnail);
				const events = jQuery._data(setThumbnail, 'events');
				
				if (events) {
					log(resultsId, 'jQuery events found: ' + Object.keys(events).join(', '), 'info');
					
					if (events.click) {
						log(resultsId, 'Click events: ' + events.click.length, 'info');
						events.click.forEach(function(handler, index) {
							log(resultsId, '  Handler ' + (index + 1) + ': ' + (handler.handler ? handler.handler.toString().substring(0, 100) : 'no handler'), 'info');
						});
					}
				} else {
					log(resultsId, 'No jQuery events found on link', 'warning');
				}
			}
		}
		
		function checkFpSeoScripts() {
			const resultsId = 'test4-results';
			document.getElementById(resultsId).innerHTML = '';
			consoleLog('=== Test 4: Verifica Scripts FP SEO ===', 'info');
			
			// Check for FP SEO scripts
			const scripts = document.querySelectorAll('script[src*="FP-SEO-Manager"]');
			log(resultsId, 'FP SEO external scripts: ' + scripts.length, scripts.length > 0 ? 'info' : 'warning');
			
			scripts.forEach(function(script, index) {
				log(resultsId, 'Script ' + (index + 1) + ': ' + script.src, 'info');
			});
			
			// Check for inline scripts
			const inlineScripts = document.querySelectorAll('script:not([src])');
			let fpSeoInline = 0;
			let fpSeoScripts = [];
			
			inlineScripts.forEach(function(script) {
				const content = script.textContent || script.innerHTML;
				if (content.indexOf('FP SEO') !== -1 || 
				    content.indexOf('fp-seo') !== -1 ||
				    content.indexOf('waitForWpMedia') !== -1 ||
				    content.indexOf('fp-seo-social') !== -1) {
					fpSeoInline++;
					fpSeoScripts.push({
						hasWaitForWpMedia: content.indexOf('waitForWpMedia') !== -1,
						hasWpMedia: content.indexOf('wp.media') !== -1,
						hasFeaturedImage: content.indexOf('featuredImage') !== -1,
						length: content.length
					});
				}
			});
			
			log(resultsId, 'FP SEO inline scripts: ' + fpSeoInline, fpSeoInline > 0 ? 'info' : 'warning');
			
			fpSeoScripts.forEach(function(script, index) {
				log(resultsId, 'Inline script ' + (index + 1) + ':', 'info');
				log(resultsId, '  - Has waitForWpMedia: ' + script.hasWaitForWpMedia, script.hasWaitForWpMedia ? 'info' : 'warning');
				log(resultsId, '  - Has wp.media: ' + script.hasWpMedia, script.hasWpMedia ? 'info' : 'warning');
				log(resultsId, '  - Has featuredImage: ' + script.hasFeaturedImage, script.hasFeaturedImage ? 'info' : 'warning');
				log(resultsId, '  - Length: ' + script.length + ' chars', 'info');
			});
		}
		
		function simulateFeaturedImageClick() {
			const resultsId = 'test5-results';
			document.getElementById(resultsId).innerHTML = '';
			consoleLog('=== Test 5: Simula Click Featured Image ===', 'info');
			
			const setThumbnail = document.getElementById('set-post-thumbnail');
			if (!setThumbnail) {
				log(resultsId, 'ERROR: set-post-thumbnail link not found', 'error');
				return;
			}
			
			log(resultsId, 'Found set-post-thumbnail link', 'success');
			
			// Check if wp.media.featuredImage is available
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined' || typeof wp.media.featuredImage === 'undefined') {
				log(resultsId, 'ERROR: wp.media.featuredImage is not available', 'error');
				return;
			}
			
			// Check if init has been called
			try {
				const frame = wp.media.featuredImage.frame();
				if (!frame) {
					log(resultsId, 'WARNING: Featured image frame is null, trying to initialize...', 'warning');
					
					// Try to initialize
					if (typeof wp.media.featuredImage.init === 'function') {
						wp.media.featuredImage.init();
						log(resultsId, '✓ wp.media.featuredImage.init() called', 'success');
					}
				}
			} catch (error) {
				log(resultsId, 'ERROR getting frame: ' + error.message, 'error');
			}
			
			// Add listener before clicking
			if (typeof wp.media.featuredImage.frame === 'function') {
				try {
					const frame = wp.media.featuredImage.frame();
					
					frame.on('open', function() {
						log(resultsId, '✓ Frame opened!', 'success');
					});
					
					frame.on('error', function(error) {
						log(resultsId, 'ERROR in frame: ' + error, 'error');
					});
					
				} catch (error) {
					log(resultsId, 'ERROR setting up frame listeners: ' + error.message, 'error');
				}
			}
			
			// Simulate click
			log(resultsId, 'Simulating click on set-post-thumbnail...', 'info');
			
			try {
				// Use jQuery if available
				if (typeof jQuery !== 'undefined') {
					jQuery(setThumbnail).trigger('click');
					log(resultsId, '✓ Click triggered via jQuery', 'success');
				} else {
					// Use native click
					const event = new MouseEvent('click', {
						bubbles: true,
						cancelable: true,
						view: window
					});
					setThumbnail.dispatchEvent(event);
					log(resultsId, '✓ Click dispatched via native event', 'success');
				}
				
				// Wait and check if modal opened
				setTimeout(function() {
					const modal = document.querySelector('.media-modal, .media-frame');
					if (modal) {
						log(resultsId, '✓ Media modal opened!', 'success');
					} else {
						log(resultsId, 'WARNING: Media modal did not open', 'warning');
					}
				}, 1000);
				
			} catch (error) {
				log(resultsId, 'ERROR simulating click: ' + error.message, 'error');
				log(resultsId, 'Stack: ' + error.stack, 'error');
			}
		}
		
		// Auto-run tests on load
		window.addEventListener('load', function() {
			setTimeout(function() {
				consoleLog('Page loaded, starting auto-tests...', 'info');
				testWpMediaComponents();
				checkFpSeoScripts();
			}, 2000);
		});
		
		// Catch JavaScript errors
		window.addEventListener('error', function(event) {
			consoleLog('JavaScript Error: ' + event.message + ' at ' + event.filename + ':' + event.lineno, 'error');
		});
	</script>
	
	<?php wp_footer(); ?>
</body>
</html>

