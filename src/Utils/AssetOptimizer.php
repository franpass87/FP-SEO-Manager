<?php
/**
 * Asset optimization utilities for CSS, JS, and images.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Asset optimization system for better performance.
 */
class AssetOptimizer {

	/**
	 * Plugin file path.
	 */
	private string $plugin_file;

	/**
	 * Assets directory path.
	 */
	private string $assets_dir;

	/**
	 * Minified assets directory.
	 */
	private string $minified_dir;

	/**
	 * Performance monitor instance.
	 */
	private PerformanceMonitor $monitor;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param PerformanceMonitor $monitor Performance monitor instance.
	 */
	public function __construct( string $plugin_file, PerformanceMonitor $monitor ) {
		$this->plugin_file = $plugin_file;
		
		// Verifica che le funzioni WordPress siano disponibili
		if ( ! function_exists( 'plugin_dir_path' ) ) {
			throw new \RuntimeException( 'WordPress functions not available. Plugin must be loaded within WordPress context.' );
		}
		
		$this->assets_dir = plugin_dir_path( $plugin_file ) . 'assets/';
		$this->minified_dir = $this->assets_dir . 'minified/';
		$this->monitor = $monitor;
	}

	/**
	 * Initialize asset optimization.
	 */
	public function init(): void {
		// Skip entirely on frontend to prevent any conflicts with images/videos/rendering
		if ( ! is_admin() ) {
			return;
		}
		
		// Verifica che le funzioni WordPress siano disponibili
		if ( ! function_exists( 'wp_mkdir_p' ) ) {
			throw new \RuntimeException( 'WordPress functions not available. Plugin must be loaded within WordPress context.' );
		}
		
		// Create minified directory if it doesn't exist
		if ( ! file_exists( $this->minified_dir ) ) {
			wp_mkdir_p( $this->minified_dir );
		}

		// Add hooks for asset optimization - ONLY in admin
		add_action( 'admin_enqueue_scripts', [ $this, 'optimize_admin_assets' ], 1 );
		add_action( 'wp_head', [ $this, 'add_preload_hints' ], 1 );
		add_action( 'wp_footer', [ $this, 'add_defer_scripts' ], 1 );
	}

	/**
	 * Optimize frontend assets.
	 */
	public function optimize_frontend_assets(): void {
		// Only optimize on frontend
		if ( is_admin() ) {
			return;
		}

		$this->optimize_css_assets();
		$this->optimize_js_assets();
		$this->optimize_image_assets();
	}

	/**
	 * Optimize admin assets.
	 */
	public function optimize_admin_assets(): void {
		// Only optimize in admin
		if ( ! is_admin() ) {
			return;
		}

		$this->optimize_css_assets();
		$this->optimize_js_assets();
	}

	/**
	 * Run full optimization on demand (e.g. via AJAX).
	 */
	public function optimize_all(): void {
		$this->optimize_css_assets();
		$this->optimize_js_assets();
		$this->optimize_image_assets();
	}

	/**
	 * Optimize CSS assets.
	 */
	private function optimize_css_assets(): void {
		$css_files = $this->get_css_files();
		
		foreach ( $css_files as $file ) {
			$minified_file = $this->get_minified_path( $file, 'css' );
			
			// Check if minified version exists and is newer
			if ( $this->should_minify( $file, $minified_file ) ) {
				$this->minify_css( $file, $minified_file );
			}
		}
	}

	/**
	 * Optimize JS assets.
	 */
	private function optimize_js_assets(): void {
		$js_files = $this->get_js_files();
		
		foreach ( $js_files as $file ) {
			$minified_file = $this->get_minified_path( $file, 'js' );
			
			// Check if minified version exists and is newer
			if ( $this->should_minify( $file, $minified_file ) ) {
				$this->minify_js( $file, $minified_file );
			}
		}
	}

	/**
	 * Optimize image assets.
	 */
	private function optimize_image_assets(): void {
		$image_files = $this->get_image_files();
		
		foreach ( $image_files as $file ) {
			$optimized_file = $this->get_optimized_image_path( $file );
			
			// Check if optimized version exists and is newer
			if ( $this->should_optimize_image( $file, $optimized_file ) ) {
				$this->optimize_image( $file, $optimized_file );
			}
		}
	}

	/**
	 * Get CSS files to optimize.
	 *
	 * @return array<string>
	 */
	private function get_css_files(): array {
		$css_files = [];
		$css_dir = $this->assets_dir . 'css/';
		
		if ( is_dir( $css_dir ) ) {
			$files = glob( $css_dir . '*.css' );
			foreach ( $files as $file ) {
				$css_files[] = $file;
			}
		}

		// Also check subdirectories
		$subdirs = glob( $css_dir . '*/', GLOB_ONLYDIR );
		foreach ( $subdirs as $subdir ) {
			$files = glob( $subdir . '*.css' );
			foreach ( $files as $file ) {
				$css_files[] = $file;
			}
		}

		return $css_files;
	}

	/**
	 * Get JS files to optimize.
	 *
	 * @return array<string>
	 */
	private function get_js_files(): array {
		$js_files = [];
		$js_dir = $this->assets_dir . 'js/';
		
		if ( is_dir( $js_dir ) ) {
			$files = glob( $js_dir . '*.js' );
			foreach ( $files as $file ) {
				$js_files[] = $file;
			}
		}

		// Also check subdirectories
		$subdirs = glob( $js_dir . '*/', GLOB_ONLYDIR );
		foreach ( $subdirs as $subdir ) {
			$files = glob( $subdir . '*.js' );
			foreach ( $files as $file ) {
				$js_files[] = $file;
			}
		}

		return $js_files;
	}

	/**
	 * Get image files to optimize.
	 *
	 * @return array<string>
	 */
	private function get_image_files(): array {
		$image_files = [];
		$image_dir = $this->assets_dir . 'images/';
		
		if ( is_dir( $image_dir ) ) {
			$extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
			foreach ( $extensions as $ext ) {
				$files = glob( $image_dir . '*.{' . $ext . ',' . strtoupper( $ext ) . '}', GLOB_BRACE );
				foreach ( $files as $file ) {
					$image_files[] = $file;
				}
			}
		}

		return $image_files;
	}

	/**
	 * Check if file should be minified.
	 *
	 * @param string $original_file Original file path.
	 * @param string $minified_file Minified file path.
	 * @return bool
	 */
	private function should_minify( string $original_file, string $minified_file ): bool {
		// Skip if minified file doesn't exist
		if ( ! file_exists( $minified_file ) ) {
			return true;
		}

		// Skip if original file is newer than minified file
		return filemtime( $original_file ) > filemtime( $minified_file );
	}

	/**
	 * Check if image should be optimized.
	 *
	 * @param string $original_file Original file path.
	 * @param string $optimized_file Optimized file path.
	 * @return bool
	 */
	private function should_optimize_image( string $original_file, string $optimized_file ): bool {
		// Skip if optimized file doesn't exist
		if ( ! file_exists( $optimized_file ) ) {
			return true;
		}

		// Skip if original file is newer than optimized file
		return filemtime( $original_file ) > filemtime( $optimized_file );
	}

	/**
	 * Minify CSS file.
	 *
	 * @param string $input_file Input file path.
	 * @param string $output_file Output file path.
	 * @return bool
	 */
	private function minify_css( string $input_file, string $output_file ): bool {
		$this->monitor->start_timer( 'css_minification' );

		$css_content = file_get_contents( $input_file );
		if ( $css_content === false ) {
			return false;
		}

		// Basic CSS minification
		$minified = $this->minify_css_content( $css_content );

		// Ensure output directory exists
		$output_dir = dirname( $output_file );
		if ( ! file_exists( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		$result = file_put_contents( $output_file, $minified ) !== false;

		$this->monitor->end_timer( 'css_minification' );

		return $result;
	}

	/**
	 * Minify JS file.
	 *
	 * @param string $input_file Input file path.
	 * @param string $output_file Output file path.
	 * @return bool
	 */
	private function minify_js( string $input_file, string $output_file ): bool {
		$this->monitor->start_timer( 'js_minification' );

		$js_content = file_get_contents( $input_file );
		if ( $js_content === false ) {
			return false;
		}

		// Basic JS minification
		$minified = $this->minify_js_content( $js_content );

		// Ensure output directory exists
		$output_dir = dirname( $output_file );
		if ( ! file_exists( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		$result = file_put_contents( $output_file, $minified ) !== false;

		$this->monitor->end_timer( 'js_minification' );

		return $result;
	}

	/**
	 * Optimize image file.
	 *
	 * @param string $input_file Input file path.
	 * @param string $output_file Output file path.
	 * @return bool
	 */
	private function optimize_image( string $input_file, string $output_file ): bool {
		$this->monitor->start_timer( 'image_optimization' );

		// Ensure output directory exists
		$output_dir = dirname( $output_file );
		if ( ! file_exists( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		// Use WordPress image editor for optimization
		$image_editor = wp_get_image_editor( $input_file );
		if ( is_wp_error( $image_editor ) ) {
			return false;
		}

		// Resize if too large
		$image_size = $image_editor->get_size();
		if ( $image_size['width'] > 1920 || $image_size['height'] > 1080 ) {
			$image_editor->resize( 1920, 1080, true );
		}

		// Set quality
		$image_editor->set_quality( 85 );

		// Save optimized image
		$result = $image_editor->save( $output_file );

		$this->monitor->end_timer( 'image_optimization' );

		return ! is_wp_error( $result );
	}

	/**
	 * Minify CSS content.
	 *
	 * @param string $css CSS content.
	 * @return string
	 */
	private function minify_css_content( string $css ): string {
		// Remove comments
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		
		// Remove unnecessary whitespace
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*{\s*/', '{', $css );
		$css = preg_replace( '/;\s*/', ';', $css );
		$css = preg_replace( '/\s*}\s*/', '}', $css );
		$css = preg_replace( '/\s*,\s*/', ',', $css );
		$css = preg_replace( '/\s*:\s*/', ':', $css );
		
		// Remove trailing semicolons
		$css = preg_replace( '/;}/', '}', $css );
		
		// Remove leading/trailing whitespace
		$css = trim( $css );

		return $css;
	}

	/**
	 * Minify JS content.
	 *
	 * @param string $js JS content.
	 * @return string
	 */
	private function minify_js_content( string $js ): string {
		// Remove single-line comments (but preserve URLs)
		$js = preg_replace( '/(?<!:)\/\/.*$/', '', $js );
		
		// Remove multi-line comments
		$js = preg_replace( '/\/\*.*?\*\//s', '', $js );
		
		// Remove unnecessary whitespace
		$js = preg_replace( '/\s+/', ' ', $js );
		$js = preg_replace( '/\s*{\s*/', '{', $js );
		$js = preg_replace( '/\s*}\s*/', '}', $js );
		$js = preg_replace( '/\s*;\s*/', ';', $js );
		$js = preg_replace( '/\s*,\s*/', ',', $js );
		$js = preg_replace( '/\s*:\s*/', ':', $js );
		$js = preg_replace( '/\s*\(\s*/', '(', $js );
		$js = preg_replace( '/\s*\)\s*/', ')', $js );
		
		// Remove leading/trailing whitespace
		$js = trim( $js );

		return $js;
	}

	/**
	 * Get minified file path.
	 *
	 * @param string $original_file Original file path.
	 * @param string $extension File extension.
	 * @return string
	 */
	private function get_minified_path( string $original_file, string $extension ): string {
		$relative_path = str_replace( $this->assets_dir, '', $original_file );
		$relative_path = str_replace( '.' . $extension, '.min.' . $extension, $relative_path );
		return $this->minified_dir . $relative_path;
	}

	/**
	 * Get optimized image path.
	 *
	 * @param string $original_file Original file path.
	 * @return string
	 */
	private function get_optimized_image_path( string $original_file ): string {
		$relative_path = str_replace( $this->assets_dir, '', $original_file );
		$path_info = pathinfo( $relative_path );
		$relative_path = $path_info['dirname'] . '/' . $path_info['filename'] . '.optimized.' . $path_info['extension'];
		return $this->minified_dir . $relative_path;
	}

	/**
	 * Add preload hints for critical assets.
	 */
	public function add_preload_hints(): void {
		// Skip preload hints on frontend (admin assets should not be preloaded on frontend)
		if ( ! is_admin() ) {
			return;
		}
		
		$critical_css = $this->get_critical_css_files();
		
		foreach ( $critical_css as $css_file ) {
			$url = $this->get_asset_url( $css_file );
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
		}

		$critical_js = $this->get_critical_js_files();
		
		foreach ( $critical_js as $js_file ) {
			$url = $this->get_asset_url( $js_file );
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="script">';
		}
	}

	/**
	 * Add defer attribute to non-critical scripts.
	 */
	public function add_defer_scripts(): void {
		// Only add admin scripts in admin context
		if ( ! is_admin() ) {
			return;
		}
		
		$non_critical_js = $this->get_non_critical_js_files();
		
		foreach ( $non_critical_js as $js_file ) {
			$url = $this->get_asset_url( $js_file );
			echo '<script src="' . esc_url( $url ) . '" defer></script>';
		}
	}

	/**
	 * Get critical CSS files.
	 *
	 * @return array<string>
	 */
	private function get_critical_css_files(): array {
		return [
			'admin/css/fp-seo-ui-system.css',
			'admin/css/fp-seo-notifications.css',
		];
	}

	/**
	 * Get critical JS files.
	 *
	 * @return array<string>
	 */
	private function get_critical_js_files(): array {
		return [
			'admin/js/fp-seo-ui-system.js',
		];
	}

	/**
	 * Get non-critical JS files.
	 *
	 * @return array<string>
	 */
	private function get_non_critical_js_files(): array {
		return [
			'admin/js/admin.js',
			'admin/js/bulk-auditor.js',
			'admin/js/ai-generator.js',
		];
	}

	/**
	 * Get asset URL.
	 *
	 * @param string $asset_path Asset path.
	 * @return string
	 */
	private function get_asset_url( string $asset_path ): string {
		return plugins_url( 'assets/' . $asset_path, $this->plugin_file );
	}

	/**
	 * Get optimization statistics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_optimization_stats(): array {
		$stats = [
			'css_files'        => count( $this->get_css_files() ),
			'js_files'         => count( $this->get_js_files() ),
			'image_files'      => count( $this->get_image_files() ),
			'minified_css'     => $this->count_files_matching(
				$this->minified_dir,
				static fn( \SplFileInfo $file ): bool => str_ends_with( $file->getFilename(), '.min.css' )
			),
			'minified_js'      => $this->count_files_matching(
				$this->minified_dir,
				static fn( \SplFileInfo $file ): bool => str_ends_with( $file->getFilename(), '.min.js' )
			),
			'optimized_images' => $this->count_files_matching(
				$this->minified_dir,
				static fn( \SplFileInfo $file ): bool => str_contains( $file->getFilename(), '.optimized.' )
			),
		];

		// Calculate space savings
		$original_size = $this->calculate_directory_size( $this->assets_dir );
		$minified_size = $this->calculate_directory_size( $this->minified_dir );

		$stats['original_size_mb']   = round( $original_size / 1024 / 1024, 2 );
		$stats['minified_size_mb']   = round( $minified_size / 1024 / 1024, 2 );
		$stats['space_saved_mb']     = round( ( $original_size - $minified_size ) / 1024 / 1024, 2 );
		$stats['compression_ratio']  = $original_size > 0 ? round( ( 1 - ( $minified_size / $original_size ) ) * 100, 2 ) : 0;

		return $stats;
	}

	/**
	 * Calculate directory size.
	 *
	 * @param string $directory Directory path.
	 * @return int
	 */
	private function calculate_directory_size( string $directory ): int {
		$iterator = $this->get_recursive_iterator( $directory );

		if ( null === $iterator ) {
			return 0;
		}

		$size = 0;

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && $file->isFile() ) {
				$size += (int) $file->getSize();
			}
		}

		return $size;
	}

	/**
	 * Count files that satisfy a filter.
	 *
	 * @param string   $directory Base directory.
	 * @param callable $filter    Callback receiving SplFileInfo.
	 */
	private function count_files_matching( string $directory, callable $filter ): int {
		$iterator = $this->get_recursive_iterator( $directory );

		if ( null === $iterator ) {
			return 0;
		}

		$count = 0;

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && $file->isFile() && $filter( $file ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Safely create a recursive iterator for the given directory.
	 */
	private function get_recursive_iterator( string $directory ): ?RecursiveIteratorIterator {
		if ( ! is_dir( $directory ) ) {
			return null;
		}

		try {
			return new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
			);
		} catch ( \UnexpectedValueException $e ) {
			return null;
		}
	}
}
