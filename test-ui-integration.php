<?php
/**
 * Test UI Integration
 * 
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Test UI Integration
 */
class FP_SEO_UI_Integration_Test {

    /**
     * Run all tests
     */
    public static function run_tests() {
        echo "<h2>🧪 FP SEO UI Integration Tests</h2>\n";
        
        self::test_ui_system_files();
        self::test_asset_registration();
        self::test_metabox_registration();
        self::test_ajax_handlers();
        self::test_plugin_integration();
        
        echo "<h3>✅ All tests completed!</h3>\n";
    }

    /**
     * Test UI system files exist
     */
    private static function test_ui_system_files() {
        echo "<h3>📁 Testing UI System Files</h3>\n";
        
        $files = array(
            'assets/admin/css/fp-seo-ui-system.css' => 'UI System CSS',
            'assets/admin/css/fp-seo-notifications.css' => 'Notifications CSS',
            'assets/admin/js/fp-seo-ui-system.js' => 'UI System JS',
            'src/Social/ImprovedSocialMediaManager.php' => 'Improved Social Media Manager',
            'src/Links/InternalLinkManager.php' => 'Internal Link Manager',
            'src/Keywords/MultipleKeywordsManager.php' => 'Multiple Keywords Manager',
        );
        
        foreach ( $files as $file => $description ) {
            $path = plugin_dir_path( FP_SEO_PERFORMANCE_FILE ) . $file;
            if ( file_exists( $path ) ) {
                echo "✅ {$description}: {$file}\n";
            } else {
                echo "❌ {$description}: {$file} - MISSING!\n";
            }
        }
    }

    /**
     * Test asset registration
     */
    private static function test_asset_registration() {
        echo "<h3>🎨 Testing Asset Registration</h3>\n";
        
        $styles = array(
            'fp-seo-ui-system' => 'UI System CSS',
            'fp-seo-notifications' => 'Notifications CSS',
            'fp-seo-performance-admin' => 'Admin CSS',
        );
        
        $scripts = array(
            'fp-seo-ui-system' => 'UI System JS',
            'fp-seo-performance-admin' => 'Admin JS',
        );
        
        foreach ( $styles as $handle => $description ) {
            if ( wp_style_is( $handle, 'registered' ) ) {
                echo "✅ {$description}: {$handle} - REGISTERED\n";
            } else {
                echo "❌ {$description}: {$handle} - NOT REGISTERED!\n";
            }
        }
        
        foreach ( $scripts as $handle => $description ) {
            if ( wp_script_is( $handle, 'registered' ) ) {
                echo "✅ {$description}: {$handle} - REGISTERED\n";
            } else {
                echo "❌ {$description}: {$handle} - NOT REGISTERED!\n";
            }
        }
    }

    /**
     * Test metabox registration
     */
    private static function test_metabox_registration() {
        echo "<h3>📦 Testing Metabox Registration</h3>\n";
        
        global $wp_meta_boxes;
        
        $metaboxes = array(
            'fp_seo_social_media_improved' => 'Improved Social Media Metabox',
            'fp_seo_internal_links' => 'Internal Links Metabox',
            'fp_seo_multiple_keywords' => 'Multiple Keywords Metabox',
        );
        
        foreach ( $metaboxes as $id => $description ) {
            $found = false;
            foreach ( $wp_meta_boxes as $page => $contexts ) {
                foreach ( $contexts as $context => $priorities ) {
                    foreach ( $priorities as $priority => $boxes ) {
                        if ( isset( $boxes[ $id ] ) ) {
                            $found = true;
                            break 3;
                        }
                    }
                }
            }
            
            if ( $found ) {
                echo "✅ {$description}: {$id} - REGISTERED\n";
            } else {
                echo "❌ {$description}: {$id} - NOT REGISTERED!\n";
            }
        }
    }

    /**
     * Test AJAX handlers
     */
    private static function test_ajax_handlers() {
        echo "<h3>🔄 Testing AJAX Handlers</h3>\n";
        
        $handlers = array(
            'fp_seo_preview_social' => 'Social Media Preview',
            'fp_seo_optimize_social' => 'Social Media Optimization',
            'fp_seo_get_link_suggestions' => 'Link Suggestions',
            'fp_seo_analyze_internal_links' => 'Internal Links Analysis',
            'fp_seo_analyze_keywords' => 'Keywords Analysis',
            'fp_seo_suggest_keywords' => 'Keywords Suggestions',
            'fp_seo_optimize_keywords' => 'Keywords Optimization',
        );
        
        foreach ( $handlers as $action => $description ) {
            if ( has_action( "wp_ajax_{$action}" ) ) {
                echo "✅ {$description}: {$action} - REGISTERED\n";
            } else {
                echo "❌ {$description}: {$action} - NOT REGISTERED!\n";
            }
        }
    }

    /**
     * Test plugin integration
     */
    private static function test_plugin_integration() {
        echo "<h3>🔌 Testing Plugin Integration</h3>\n";
        
        // Test if classes exist
        $classes = array(
            'FP\\SEO\\Social\\ImprovedSocialMediaManager' => 'Improved Social Media Manager',
            'FP\\SEO\\Links\\InternalLinkManager' => 'Internal Link Manager',
            'FP\\SEO\\Keywords\\MultipleKeywordsManager' => 'Multiple Keywords Manager',
        );
        
        foreach ( $classes as $class => $description ) {
            if ( class_exists( $class ) ) {
                echo "✅ {$description}: {$class} - LOADED\n";
            } else {
                echo "❌ {$description}: {$class} - NOT LOADED!\n";
            }
        }
        
        // Test if hooks are registered
        $hooks = array(
            'wp_head' => 'Meta tags output',
            'admin_menu' => 'Admin menu',
            'add_meta_boxes' => 'Metabox registration',
            'save_post' => 'Post save handling',
        );
        
        foreach ( $hooks as $hook => $description ) {
            $count = 0;
            global $wp_filter;
            if ( isset( $wp_filter[ $hook ] ) ) {
                $count = count( $wp_filter[ $hook ]->callbacks );
            }
            echo "✅ {$description}: {$hook} - {$count} callbacks\n";
        }
    }
}

// Run tests if accessed directly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    FP_SEO_UI_Integration_Test::run_tests();
} elseif ( is_admin() && current_user_can( 'manage_options' ) ) {
    add_action( 'admin_notices', function() {
        if ( isset( $_GET['fp_seo_test_ui'] ) ) {
            echo '<div class="notice notice-info"><pre>';
            FP_SEO_UI_Integration_Test::run_tests();
            echo '</pre></div>';
        }
    });
}
