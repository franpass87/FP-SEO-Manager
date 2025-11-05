<?php
/**
 * Complete Features Test
 * 
 * URL: http://fp-development.local/wp-content/plugins/FP-SEO-Manager/test-all-features.php
 * 
 * @package FP\SEO
 */

// Load WordPress
$wp_load = 'C:/Users/franc/Local Sites/fp-development/app/public/wp-load.php';
if (!file_exists($wp_load)) {
    die('ERROR: wp-load.php not found at: ' . $wp_load);
}
require_once $wp_load;

if (!current_user_can('manage_options')) {
    wp_die('Admin access required');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FP SEO - Complete Features Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2563eb; border-bottom: 3px solid #2563eb; padding-bottom: 10px; }
        h2 { color: #374151; margin-top: 30px; background: #f9fafb; padding: 10px 15px; border-left: 4px solid #2563eb; }
        .test { padding: 15px; margin: 15px 0; border-radius: 6px; border-left: 4px solid; }
        .pass { background: #d1fae5; border-left-color: #059669; color: #065f46; }
        .fail { background: #fee2e2; border-left-color: #dc2626; color: #991b1b; }
        .warn { background: #fef3c7; border-left-color: #f59e0b; color: #92400e; }
        .info { background: #dbeafe; border-left-color: #2563eb; color: #1e40af; }
        pre { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; margin: 2px; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 FP SEO Performance - Complete Features Test</h1>
        <p><strong>Version:</strong> <?php echo esc_html( FP_SEO_PERFORMANCE_VERSION ?? '0.4.0' ); ?> | <strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
        $tests_passed = 0;
        $tests_failed = 0;
        $tests_total = 0;

        // TEST 1: Composer Autoload
        echo '<h2>Test 1: Composer Autoload & Dependencies</h2>';
        $tests_total++;
        
        $vendor_autoload = __DIR__ . '/vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            echo '<div class="test pass">✅ Vendor autoload exists</div>';
            
            // Check Google Client
            if (class_exists('\Google\Client')) {
                echo '<div class="test pass">✅ Google\Client class available</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ Google\Client NOT available - Run: composer install</div>';
                $tests_failed++;
            }
        } else {
            echo '<div class="test fail">❌ Vendor autoload missing - Run: composer install</div>';
            $tests_failed++;
        }

        // TEST 2: GEO Classes
        echo '<h2>Test 2: GEO Classes</h2>';
        $geo_classes = array(
            'FP\SEO\GEO\Router',
            'FP\SEO\GEO\AiTxt',
            'FP\SEO\GEO\GeoSitemap',
            'FP\SEO\GEO\SiteJson',
            'FP\SEO\GEO\ContentJson',
            'FP\SEO\GEO\UpdatesJson',
            'FP\SEO\GEO\Extractor',
        );
        
        foreach ($geo_classes as $class) {
            $tests_total++;
            if (class_exists($class)) {
                echo '<div class="test pass">✅ ' . $class . '</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ ' . $class . ' NOT FOUND</div>';
                $tests_failed++;
            }
        }

        // TEST 3: GSC Classes
        echo '<h2>Test 3: Google Search Console Classes</h2>';
        $gsc_classes = array(
            'FP\SEO\Integrations\GscClient',
            'FP\SEO\Integrations\GscData',
            'FP\SEO\Admin\GscSettings',
            'FP\SEO\Admin\GscDashboard',
        );
        
        foreach ($gsc_classes as $class) {
            $tests_total++;
            if (class_exists($class)) {
                echo '<div class="test pass">✅ ' . $class . '</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ ' . $class . ' NOT FOUND</div>';
                $tests_failed++;
            }
        }

        // TEST 4: Advanced Features Classes
        echo '<h2>Test 4: Advanced Features Classes</h2>';
        $advanced_classes = array(
            'FP\SEO\Integrations\IndexingApi',
            'FP\SEO\Integrations\AutoIndexing',
            'FP\SEO\History\ScoreHistory',
            'FP\SEO\Linking\InternalLinkSuggester',
        );
        
        foreach ($advanced_classes as $class) {
            $tests_total++;
            if (class_exists($class)) {
                echo '<div class="test pass">✅ ' . $class . '</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ ' . $class . ' NOT FOUND</div>';
                $tests_failed++;
            }
        }

        // TEST 5: Assets Files
        echo '<h2>Test 5: Assets Files</h2>';
        $asset_files = array(
            'serp-preview.js' => 'assets/admin/js/serp-preview.js',
            'serp-preview.css' => 'assets/admin/css/components/serp-preview.css',
            'admin.css' => 'assets/admin/css/admin.css',
        );
        
        foreach ($asset_files as $name => $path) {
            $tests_total++;
            $full_path = __DIR__ . '/' . $path;
            if (file_exists($full_path)) {
                echo '<div class="test pass">✅ ' . $name . ' (' . number_format(filesize($full_path)) . ' bytes)</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ ' . $name . ' NOT FOUND</div>';
                $tests_failed++;
            }
        }

        // TEST 6: Database Table
        echo '<h2>Test 6: Database Table</h2>';
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_seo_score_history';
        $tests_total++;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            echo '<div class="test pass">✅ Table ' . $table_name . ' exists (' . $count . ' records)</div>';
            $tests_passed++;
            
            // Show sample records
            $samples = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 5");
            if ($samples) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Post ID</th><th>Score</th><th>Status</th><th>Date</th></tr>';
                foreach ($samples as $row) {
                    echo '<tr>';
                    echo '<td>' . $row->id . '</td>';
                    echo '<td>' . $row->post_id . '</td>';
                    echo '<td><strong>' . $row->score . '</strong></td>';
                    echo '<td>' . $row->status . '</td>';
                    echo '<td>' . $row->recorded_at . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } else {
            echo '<div class="test warn">⚠️ Table NOT exists - Riattiva plugin per crearla</div>';
            $tests_failed++;
        }

        // TEST 7: Endpoints GEO
        echo '<h2>Test 7: GEO Endpoints</h2>';
        $geo_endpoints = array(
            '/.well-known/ai.txt',
            '/geo-sitemap.xml',
            '/geo/site.json',
            '/geo/updates.json',
        );
        
        foreach ($geo_endpoints as $endpoint) {
            $tests_total++;
            $url = home_url($endpoint);
            $response = wp_remote_get($url, array('timeout' => 5, 'sslverify' => false));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                echo '<div class="test pass">✅ <a href="' . esc_url($url) . '" target="_blank">' . $endpoint . '</a> → 200 OK</div>';
                $tests_passed++;
            } else {
                $code = is_wp_error($response) ? 'ERROR' : wp_remote_retrieve_response_code($response);
                echo '<div class="test fail">❌ ' . $endpoint . ' → ' . $code . ' (Flush permalinks?)</div>';
                $tests_failed++;
            }
        }

        // TEST 8: GSC Configuration
        echo '<h2>Test 8: GSC Configuration</h2>';
        $options = get_option('fp_seo_performance', array());
        $gsc = $options['gsc'] ?? array();
        
        $tests_total++;
        if (!empty($gsc['service_account_json']) && !empty($gsc['site_url'])) {
            echo '<div class="test pass">✅ GSC configured (JSON key + Site URL present)</div>';
            echo '<div class="test info">📌 Site URL: ' . esc_html($gsc['site_url']) . '</div>';
            echo '<div class="test info">📌 GSC Enabled: ' . (!empty($gsc['enabled']) ? 'Yes' : 'No') . '</div>';
            echo '<div class="test info">📌 Auto Indexing: ' . (!empty($gsc['auto_indexing']) ? 'Yes' : 'No') . '</div>';
            $tests_passed++;
            
            // Test connection
            if (class_exists('\FP\SEO\Integrations\GscClient')) {
                $client = new \FP\SEO\Integrations\GscClient();
                $connected = $client->test_connection();
                
                if ($connected) {
                    echo '<div class="test pass">✅ GSC Connection Test: SUCCESS</div>';
                } else {
                    echo '<div class="test warn">⚠️ GSC Connection Test: FAILED (Check credentials)</div>';
                }
            }
        } else {
            echo '<div class="test warn">⚠️ GSC NOT configured - Configure in Settings → GSC</div>';
            $tests_failed++;
        }

        // TEST 9: Registered Hooks
        echo '<h2>Test 9: Registered Hooks</h2>';
        global $wp_filter;
        
        $hooks_to_check = array(
            'admin_head' => array('inject_modern_styles', 'Menu', 'Metabox'),
            'fpseo_settings_tabs' => array('add_gsc_tab', 'GscSettings'),
            'fpseo_after_score_calculation' => array('record_score', 'ScoreHistory'),
            'publish_post' => array('on_publish', 'AutoIndexing'),
        );
        
        foreach ($hooks_to_check as $hook => $search_terms) {
            $tests_total++;
            $found = false;
            
            if (isset($wp_filter[$hook])) {
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $callback_str = '';
                        if (is_array($callback['function'])) {
                            $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                            $method = $callback['function'][1];
                            $callback_str = $class . '::' . $method;
                        }
                        
                        foreach ($search_terms as $term) {
                            if (strpos($callback_str, $term) !== false) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ($found) {
                echo '<div class="test pass">✅ Hook: ' . $hook . ' (' . implode(', ', $search_terms) . ')</div>';
                $tests_passed++;
            } else {
                echo '<div class="test fail">❌ Hook: ' . $hook . ' NOT registered</div>';
                $tests_failed++;
            }
        }

        // TEST 10: Functional Tests
        echo '<h2>Test 10: Functional Tests</h2>';
        
        // Test GEO Extractor
        $tests_total++;
        if (class_exists('\FP\SEO\GEO\Extractor')) {
            $test_post = get_post(1); // Assume post ID 1 exists
            if ($test_post) {
                $extractor = new \FP\SEO\GEO\Extractor();
                $extracted = $extractor->extract($test_post);
                
                if (isset($extracted['keywords']) && is_array($extracted['keywords'])) {
                    echo '<div class="test pass">✅ GEO Extractor functional (' . count($extracted['keywords']) . ' keywords extracted)</div>';
                    $tests_passed++;
                } else {
                    echo '<div class="test fail">❌ GEO Extractor NOT working</div>';
                    $tests_failed++;
                }
            } else {
                echo '<div class="test warn">⚠️ No test post available (ID 1)</div>';
            }
        }

        // Test Internal Link Suggester
        $tests_total++;
        if (class_exists('\FP\SEO\Linking\InternalLinkSuggester')) {
            $suggester = new \FP\SEO\Linking\InternalLinkSuggester();
            $test_post = get_post(1);
            if ($test_post) {
                $suggestions = $suggester->get_suggestions(1);
                
                if (is_array($suggestions)) {
                    echo '<div class="test pass">✅ Internal Link Suggester functional (' . count($suggestions) . ' suggestions)</div>';
                    $tests_passed++;
                    
                    if (!empty($suggestions)) {
                        echo '<div class="test info">Top suggestion: "' . esc_html($suggestions[0]['title']) . '" (relevance: ' . $suggestions[0]['relevance'] . ')</div>';
                    }
                } else {
                    echo '<div class="test fail">❌ Internal Link Suggester NOT working</div>';
                    $tests_failed++;
                }
            }
        }

        // SUMMARY
        echo '<h2>📊 Test Summary</h2>';
        $success_rate = $tests_total > 0 ? round(($tests_passed / $tests_total) * 100, 1) : 0;
        
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>Total Tests</td><td><strong>' . $tests_total . '</strong></td></tr>';
        echo '<tr><td>Passed</td><td><span class="badge badge-success">' . $tests_passed . '</span></td></tr>';
        echo '<tr><td>Failed</td><td><span class="badge badge-fail">' . $tests_failed . '</span></td></tr>';
        echo '<tr><td>Success Rate</td><td><strong>' . $success_rate . '%</strong></td></tr>';
        echo '</table>';
        
        if ($success_rate >= 90) {
            echo '<div class="test pass">';
            echo '<h3>🎉 EXCELLENT! All critical features working!</h3>';
            echo '<p>Il plugin è pronto per la produzione. Tutti i componenti principali funzionano correttamente.</p>';
            echo '</div>';
        } elseif ($success_rate >= 70) {
            echo '<div class="test warn">';
            echo '<h3>⚠️ GOOD - Some features need attention</h3>';
            echo '<p>La maggior parte delle funzionalità funziona. Verifica i test falliti sopra.</p>';
            echo '</div>';
        } else {
            echo '<div class="test fail">';
            echo '<h3>❌ ISSUES DETECTED</h3>';
            echo '<p>Diversi componenti hanno problemi. Controlla i test falliti e segui le istruzioni di fix.</p>';
            echo '</div>';
        }

        // ACTION ITEMS
        echo '<h2>📋 Action Items</h2>';
        
        if (!$table_exists) {
            echo '<div class="test warn">⚠️ <strong>Riattiva plugin</strong> per creare tabella wp_fp_seo_score_history</div>';
        }
        
        if (!class_exists('\Google\Client')) {
            echo '<div class="test fail">❌ <strong>Run: composer install</strong> nella cartella LAB del plugin</div>';
        }
        
        if ($tests_failed > 0) {
            echo '<div class="test warn">⚠️ <strong>Flush Permalinks</strong>: Settings → Permalinks → Save</div>';
        }
        
        if (empty($gsc['service_account_json'])) {
            echo '<div class="test info">ℹ️ <strong>Configure GSC</strong>: Settings → FP SEO → Google Search Console</div>';
        }

        // QUICK LINKS
        echo '<h2>🔗 Quick Test Links</h2>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('admin.php?page=fp-seo-performance') . '" target="_blank">📊 Dashboard</a></li>';
        echo '<li><a href="' . admin_url('admin.php?page=fp-seo-performance-settings&tab=gsc') . '" target="_blank">⚙️ Settings → GSC</a></li>';
        echo '<li><a href="' . admin_url('admin.php?page=fp-seo-performance-settings&tab=geo') . '" target="_blank">🤖 Settings → GEO</a></li>';
        echo '<li><a href="' . admin_url('post.php?post=1&action=edit') . '" target="_blank">📝 Edit Post (test SERP Preview)</a></li>';
        echo '<li><a href="' . home_url('/.well-known/ai.txt') . '" target="_blank">🤖 ai.txt</a></li>';
        echo '<li><a href="' . home_url('/geo-sitemap.xml') . '" target="_blank">🗺️ geo-sitemap.xml</a></li>';
        echo '</ul>';

        ?>

        <div class="test info">
            <h3>🚀 Next Steps</h3>
            <ol>
                <li>Se test falliti: Segui "Action Items" sopra</li>
                <li>Riattiva plugin: Plugins → Disattiva → Riattiva</li>
                <li>Configura GSC: Settings → FP SEO → Google Search Console</li>
                <li>Test endpoint GEO: Click links sopra</li>
                <li>Test SERP Preview: Edit any post</li>
            </ol>
        </div>

    </div>
</body>
</html>

