<?php
/**
 * Debug script to test wp.media functionality
 * 
 * Access: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/debug-wp-media.php
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Check if user is logged in
if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
	wp_die( 'Access denied. Please log in as administrator.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>FP SEO - wp.media Debug</title>
	<?php wp_head(); ?>
	<style>
		body { font-family: Arial, sans-serif; padding: 20px; }
		.test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
		.success { color: green; }
		.error { color: red; }
		.warning { color: orange; }
		.info { color: blue; }
		pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
		button { padding: 10px 20px; margin: 5px; cursor: pointer; }
		#test-results { margin-top: 20px; }
	</style>
</head>
<body>
	<h1>FP SEO Manager - wp.media Debug Tool</h1>
	
	<div class="test-section">
		<h2>Test 1: Verifica wp.media disponibilità</h2>
		<button onclick="testWpMedia()">Test wp.media</button>
		<div id="test1-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 2: Test Featured Image Frame</h2>
		<button onclick="testFeaturedImageFrame()">Apri Featured Image Frame</button>
		<div id="test2-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 3: Test Media Library Frame</h2>
		<button onclick="testMediaLibraryFrame()">Apri Media Library Frame</button>
		<div id="test3-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 4: Verifica Scripts Caricati</h2>
		<button onclick="checkLoadedScripts()">Verifica Scripts</button>
		<div id="test4-results"></div>
	</div>
	
	<div class="test-section">
		<h2>Test 5: Verifica Interferenze</h2>
		<button onclick="checkInterferences()">Verifica Interferenze</button>
		<div id="test5-results"></div>
	</div>
	
	<div id="test-results"></div>
	
	<script>
		function log(elementId, message, type) {
			const el = document.getElementById(elementId);
			const className = type || 'info';
			el.innerHTML += '<div class="' + className + '">' + message + '</div>';
		}
		
		function testWpMedia() {
			const resultsId = 'test1-results';
			document.getElementById(resultsId).innerHTML = '';
			
			log(resultsId, 'Testing wp.media availability...', 'info');
			
			if (typeof wp === 'undefined') {
				log(resultsId, 'ERROR: wp is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp is defined', 'success');
			
			if (typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media is defined', 'success');
			
			if (typeof wp.media.featuredImage === 'undefined') {
				log(resultsId, 'ERROR: wp.media.featuredImage is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.featuredImage is defined', 'success');
			
			if (typeof wp.media.featuredImage.init !== 'function') {
				log(resultsId, 'ERROR: wp.media.featuredImage.init is not a function', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.featuredImage.init is a function', 'success');
			
			if (typeof wp.media.controller === 'undefined') {
				log(resultsId, 'ERROR: wp.media.controller is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.controller is defined', 'success');
			
			if (typeof wp.media.controller.FeaturedImage === 'undefined') {
				log(resultsId, 'ERROR: wp.media.controller.FeaturedImage is not defined', 'error');
				return;
			}
			log(resultsId, '✓ wp.media.controller.FeaturedImage is defined', 'success');
			
			log(resultsId, 'All wp.media components are available!', 'success');
		}
		
		function testFeaturedImageFrame() {
			const resultsId = 'test2-results';
			document.getElementById(resultsId).innerHTML = '';
			
			log(resultsId, 'Testing Featured Image Frame...', 'info');
			
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not available', 'error');
				return;
			}
			
			try {
				// Try to get the featured image frame
				const frame = wp.media.featuredImage.frame();
				
				if (!frame) {
					log(resultsId, 'ERROR: Featured image frame is null', 'error');
					return;
				}
				
				log(resultsId, '✓ Featured image frame created successfully', 'success');
				log(resultsId, 'Frame ID: ' + (frame.id || 'no ID'), 'info');
				log(resultsId, 'Frame states: ' + Object.keys(frame.states._models || {}).join(', '), 'info');
				
				// Try to open it
				frame.on('open', function() {
					log(resultsId, '✓ Frame opened successfully', 'success');
				});
				
				frame.on('close', function() {
					log(resultsId, 'Frame closed', 'info');
				});
				
				frame.open();
				
			} catch (error) {
				log(resultsId, 'ERROR: ' + error.message, 'error');
				log(resultsId, 'Stack: ' + error.stack, 'error');
			}
		}
		
		function testMediaLibraryFrame() {
			const resultsId = 'test3-results';
			document.getElementById(resultsId).innerHTML = '';
			
			log(resultsId, 'Testing Media Library Frame...', 'info');
			
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not available', 'error');
				return;
			}
			
			try {
				// Create a simple media frame
				const frame = wp.media({
					title: 'Test Media Library',
					button: {
						text: 'Use Image'
					},
					multiple: false,
					library: {
						type: 'image'
					}
				});
				
				if (!frame) {
					log(resultsId, 'ERROR: Media frame is null', 'error');
					return;
				}
				
				log(resultsId, '✓ Media frame created successfully', 'success');
				log(resultsId, 'Frame ID: ' + (frame.id || 'no ID'), 'info');
				
				frame.on('open', function() {
					log(resultsId, '✓ Frame opened successfully', 'success');
				});
				
				frame.on('close', function() {
					log(resultsId, 'Frame closed', 'info');
				});
				
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first();
					log(resultsId, '✓ Image selected: ' + attachment.get('url'), 'success');
				});
				
				frame.open();
				
			} catch (error) {
				log(resultsId, 'ERROR: ' + error.message, 'error');
				log(resultsId, 'Stack: ' + error.stack, 'error');
			}
		}
		
		function checkLoadedScripts() {
			const resultsId = 'test4-results';
			document.getElementById(resultsId).innerHTML = '';
			
			log(resultsId, 'Checking loaded scripts...', 'info');
			
			// Check for FP SEO scripts
			const scripts = document.querySelectorAll('script[src*="FP-SEO-Manager"]');
			log(resultsId, 'FP SEO scripts found: ' + scripts.length, scripts.length > 0 ? 'success' : 'warning');
			
			scripts.forEach(function(script, index) {
				log(resultsId, 'Script ' + (index + 1) + ': ' + script.src, 'info');
			});
			
			// Check for media scripts
			const mediaScripts = document.querySelectorAll('script[src*="media"]');
			log(resultsId, 'Media scripts found: ' + mediaScripts.length, mediaScripts.length > 0 ? 'success' : 'warning');
			
			// Check for inline scripts
			const inlineScripts = document.querySelectorAll('script:not([src])');
			let fpSeoInline = 0;
			inlineScripts.forEach(function(script) {
				if (script.textContent.indexOf('FP SEO') !== -1 || 
				    script.textContent.indexOf('fp-seo') !== -1 ||
				    script.textContent.indexOf('waitForWpMedia') !== -1) {
					fpSeoInline++;
				}
			});
			log(resultsId, 'FP SEO inline scripts found: ' + fpSeoInline, fpSeoInline > 0 ? 'info' : 'warning');
		}
		
		function checkInterferences() {
			const resultsId = 'test5-results';
			document.getElementById(resultsId).innerHTML = '';
			
			log(resultsId, 'Checking for interferences...', 'info');
			
			if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
				log(resultsId, 'ERROR: wp.media is not available', 'error');
				return;
			}
			
			// Check if wp.media.frame is set
			if (wp.media.frame) {
				log(resultsId, 'wp.media.frame is set: ' + (wp.media.frame.id || 'no ID'), 'warning');
			} else {
				log(resultsId, '✓ wp.media.frame is not set (good)', 'success');
			}
			
			// Check if wp.media.frames.customHeader is set
			if (wp.media.frames && wp.media.frames.customHeader) {
				log(resultsId, 'WARNING: wp.media.frames.customHeader is set (could cause conflicts)', 'warning');
			} else {
				log(resultsId, '✓ wp.media.frames.customHeader is not set (good)', 'success');
			}
			
			// Check if featuredImage.init has been called
			if (wp.media.featuredImage && wp.media.featuredImage._frame) {
				log(resultsId, 'wp.media.featuredImage._frame exists', 'info');
			} else {
				log(resultsId, 'wp.media.featuredImage._frame does not exist yet', 'info');
			}
			
			// Check for event listeners on featured image
			try {
				const postImageDiv = document.getElementById('postimagediv');
				if (postImageDiv) {
					log(resultsId, '✓ postimagediv found', 'success');
					
					const setThumbnail = document.getElementById('set-post-thumbnail');
					if (setThumbnail) {
						log(resultsId, '✓ set-post-thumbnail link found', 'success');
					} else {
						log(resultsId, 'WARNING: set-post-thumbnail link not found', 'warning');
					}
				} else {
					log(resultsId, 'WARNING: postimagediv not found (not on post editor page?)', 'warning');
				}
			} catch (error) {
				log(resultsId, 'ERROR checking postimagediv: ' + error.message, 'error');
			}
		}
		
		// Auto-run tests on load
		window.addEventListener('load', function() {
			setTimeout(function() {
				testWpMedia();
				checkLoadedScripts();
				checkInterferences();
			}, 1000);
		});
	</script>
	
	<?php wp_footer(); ?>
</body>
</html>


