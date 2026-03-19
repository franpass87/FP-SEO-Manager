<?php
/**
 * FP SEO Manager - Quick diagnostic check (run via browser).
 * Delete after verification.
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	$load = dirname( __DIR__, 3 ) . '/wp-load.php';
	if ( file_exists( $load ) ) {
		require_once $load;
	} else {
		die( 'wp-load.php not found at: ' . htmlspecialchars( $load ) );
	}
}

header( 'Content-Type: text/html; charset=utf-8' );

$checks = [];
$ok = true;

// 1. Plugin active
$checks['plugin_active'] = defined( 'FP_SEO_PERFORMANCE_VERSION' );
if ( ! $checks['plugin_active'] ) {
	$ok = false;
}

// 2. Version
$checks['version'] = defined( 'FP_SEO_PERFORMANCE_VERSION' ) ? FP_SEO_PERFORMANCE_VERSION : 'N/A';

// 3. Redirect table exists
global $wpdb;
$table = $wpdb->prefix . 'fp_seo_redirects';
$checks['redirect_table'] = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;

// 4. Classes loadable
$checks['redirect_repo'] = class_exists( 'FP\SEO\Redirects\RedirectRepository' );
$checks['html_sitemap'] = class_exists( 'FP\SEO\GEO\HtmlSitemap' );
$checks['sitemap_router'] = class_exists( 'FP\SEO\Redirects\SitemapRouter' );

// 5. Sitemap URL
$checks['sitemap_url'] = home_url( '/sitemap/' );

// 6. Rewrite rules (check if sitemap rule registered)
$rules = get_option( 'rewrite_rules', [] );
$checks['sitemap_rewrite'] = ! empty( $rules['^sitemap/?$'] ) || ! empty( $rules['sitemap/?$'] );

?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>FP SEO Manager Check</title></head>
<body style="font-family:sans-serif;max-width:600px;margin:2rem auto;padding:1rem;">
<h1>FP SEO Manager – Diagnostic</h1>
<table border="1" cellpadding="8" style="border-collapse:collapse;width:100%;">
<tr><th>Check</th><th>Status</th></tr>
<?php foreach ( $checks as $k => $v ) : ?>
<tr>
	<td><?php echo esc_html( $k ); ?></td>
	<td><?php echo is_bool( $v ) ? ( $v ? '&#9989; OK' : '&#10060; FAIL' ) : esc_html( (string) $v ); ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php if ( $ok ) : ?>
<p><strong>All core checks passed.</strong></p>
<p><a href="<?php echo esc_url( $checks['sitemap_url'] ); ?>">Test HTML Sitemap</a></p>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fp-seo-redirects' ) ); ?>">Redirect Manager (admin)</a></p>
<?php else : ?>
<p><strong>Some checks failed.</strong> Ensure plugin is active and run: Settings → Permalinks → Save.</p>
<?php endif; ?>
<p><small>Delete this file after verification.</small></p>
</body>
</html>
