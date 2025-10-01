# Plugin Audit Report — FP SEO Performance — 2025-10-01

## Summary
- Files scanned: 76/76
- Issues found: 3 (Critical: 0 | High: 1 | Medium: 2 | Low: 0)
- Key risks:
  - Analyzer ignores administrator toggles for individual checks, so disabling a check has no effect on results.
  - Bulk audit listing uses an unoptimised `WP_Query`, which can hammer the database on large sites.
  - Site Health PSI test fires a live PageSpeed Insights request on every run, risking slow dashboards and quota exhaustion.
- Recommended priorities:
  1. Make analyzer respect the stored check enable/disable settings.
  2. Optimise the bulk audit query for large datasets.
  3. Cache or reuse PSI results inside the Site Health test to avoid repeated remote calls.

## Issues
### [High] Analyzer ignores per-check enable/disable toggles
- ID: ISSUE-001
- File: src/Analysis/Analyzer.php:54
- Snippet:
  ```php
  if ( empty( $checks ) ) {
          $checks = $this->default_checks();
  }

  $enabled_ids = array();

  foreach ( $checks as $check ) {
          $enabled_ids[ $check->id() ] = true;
  }
  ```

Diagnosis: The analyzer always runs the full hard-coded checklist and only exposes a filter for third-party code. The plugin never applies the saved "Analysis » Checks" settings (checkboxes rendered in `SettingsPage::render_analysis_tab`) to that filter, so disabling a check in the UI does nothing.

Impact: Functional/regression. Editors cannot turn off noisy checks, score weights don't match expectations, and canonical/robots toggles in settings have no effect, undermining trust in the tool.

Repro steps:
1. Uncheck “Open Graph tags” in Settings → Analysis.
2. Save settings and open any post metabox.
3. The Open Graph check still runs and reports warnings.

Proposed fix (concise):

```php
add_filter( 'fp_seo_perf_checks_enabled', static function( array $ids ): array {
    $options = Options::get();
    $enabled = array_filter( $options['analysis']['checks'] ?? array(), 'boolval' );
    return array_values( array_intersect( array_keys( $enabled ), $ids ) );
} );
```

Side effects / Regression risk: Low — filtering by stored settings only removes checks administrators already disabled.

Est. effort: M

Tags: #functional #settings #analyzer

### [Medium] Bulk audit table query misses performance flags
- ID: ISSUE-002
- File: src/Admin/BulkAuditPage.php:450
- Snippet:
  ```php
  $args = array(
          'post_type'      => 'all' === $post_type ? $types : $post_type,
          'post_status'    => 'any' === $status ? $this->get_allowed_statuses() : $status,
          'posts_per_page' => 200,
          'orderby'        => 'date',
          'order'          => 'DESC',
  );

  $query = new WP_Query( $args );
  ```

Diagnosis: The admin report pulls up to 200 full `WP_Post` objects without disabling pagination counts or cache priming. On large sites this forces an expensive `SQL_CALC_FOUND_ROWS` plus meta/term cache hydration, slowing every bulk audit screen load.

Impact: Performance. Slow admin page loads, higher DB load on shared hosts when editors open the bulk auditor.

Proposed fix (concise):

```php
$args['no_found_rows']          = true;
$args['update_post_meta_cache'] = false;
$args['update_post_term_cache'] = false;
```

Side effects / Regression risk: Low — the table already works with simple pagination and does not rely on totals or caches.

Est. effort: S

Tags: #performance #wpquery #admin

### [Medium] Site Health PSI test triggers fresh API calls every run
- ID: ISSUE-003
- File: src/SiteHealth/SeoHealth.php:202
- Snippet:
  ```php
  $endpoint = add_query_arg(
          array(
                  'url'      => $request_url,
                  'key'      => $api_key,
                  'strategy' => 'mobile',
          ),
          'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
  );

  $response = wp_remote_get(
          $endpoint,
          array(
                  'timeout' => 15,
          )
  );
  ```

Diagnosis: The Site Health check calls PageSpeed Insights synchronously on every dashboard visit and async cron run, with no caching or reuse of the plugin’s existing `Perf\Signals` PSI cache.

Impact: Performance/availability. Slow Site Health screens, API quota exhaustion, and timeouts on shared hosts when PSI is enabled.

Proposed fix (concise):

```php
$cache_key = 'fp_seo_perf_site_health_psi';
$cached    = get_site_transient( $cache_key );
if ( ! $refresh && is_array( $cached ) ) {
    return $cached;
}
$result = ( new Signals() )->collect( $home_url, array(), true );
set_site_transient( $cache_key, $result, HOUR_IN_SECONDS );
```

Side effects / Regression risk: Low — reuses existing PSI handling and respects cache expiry.

Est. effort: M

Tags: #performance #remote-request #site-health

## Conflicts & Duplicates
None observed in scanned files.

## Deprecated & Compatibility
- `Requires at least` in both `fp-seo-performance.php` and `readme.txt` still lists WordPress 6.2; update to 6.6 to match current target.
- No PHP 8.2/8.3 deprecations detected in the scanned batch.

## Performance Hotspots
- Bulk audit query in `src/Admin/BulkAuditPage.php:450` should disable pagination/meta caches (see ISSUE-002).
- Site Health PSI test in `src/SiteHealth/SeoHealth.php:202` should share cached PSI data (see ISSUE-003).

## i18n & A11y
- Text domain usage is consistent across scanned files.
- No accessibility regressions spotted in admin templates reviewed in this batch.

## Test Coverage (scanned portion)
- Unit tests exist for most analyzer components, but no tests cover the Settings → Analyzer toggle integration (ISSUE-001).
- Signals/Site Health specs focus on URL normalization paths and still miss caching behaviour addressed in ISSUE-003.

## Next Steps (per fase di FIX)
- Ordine consigliato: ISSUE-001 → ISSUE-003 → ISSUE-002.
- Safe-fix batch plan:
  - **Batch 1:** Apply filter respect for analyzer toggles (ISSUE-001) and add a regression test for disabled checks.
  - **Batch 2:** Add PSI caching within Site Health (ISSUE-003) and cover with integration test.
  - **Batch 3:** Harden bulk audit `WP_Query` arguments (ISSUE-002) and run performance smoke-test on large datasets.
