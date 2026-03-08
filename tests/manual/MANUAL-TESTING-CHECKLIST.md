# FP-SEO-Manager Manual Testing Checklist

**Version**: 0.9.0-pre.72  
**Date**: 2025-01-27

---

## Admin UI Testing

### Main Menu
- [ ] Navigate to WordPress admin
- [ ] Verify "FP SEO Performance" menu appears
- [ ] Verify all submenu items are visible:
  - [ ] Settings
  - [ ] Bulk Audit
  - [ ] Performance Dashboard
  - [ ] Schema
  - [ ] Social Media
  - [ ] Internal Links
  - [ ] Multiple Keywords
- [ ] Verify menu icons display correctly
- [ ] Verify menu items have correct capabilities

### Settings Page
- [ ] Open Settings page
- [ ] Verify all tabs are accessible:
  - [ ] General
  - [ ] Analysis
  - [ ] AI
  - [ ] GEO
  - [ ] Google Search Console
  - [ ] Performance
- [ ] Test saving settings on each tab
- [ ] Verify settings persist after page reload
- [ ] Test with invalid data (should be rejected)
- [ ] Verify default values are applied
- [ ] Test form validation errors display correctly

### Bulk Audit Page
- [ ] Open Bulk Audit page
- [ ] Verify post type selector works
- [ ] Select posts and run audit
- [ ] Verify progress indicators display
- [ ] Verify results display correctly
- [ ] Test with 0 posts (should handle gracefully)
- [ ] Test export functionality
- [ ] Verify error handling for failed audits

### Performance Dashboard
- [ ] Open Performance Dashboard
- [ ] Verify metrics display correctly
- [ ] Verify charts/graphs render
- [ ] Test date range filters
- [ ] Verify data refreshes correctly

---

## Editor Metabox Testing

### Main SEO Metabox
- [ ] Open post editor (Gutenberg)
- [ ] Verify "SEO Performance" metabox appears
- [ ] Verify all sections are visible:
  - [ ] SEO Title
  - [ ] Meta Description
  - [ ] Focus Keyword
  - [ ] Slug
  - [ ] Excerpt
  - [ ] AI Generation section
  - [ ] Real-time Analysis
- [ ] Test saving all fields
- [ ] Verify character counters work (title: 60, meta: 155)
- [ ] Verify color coding (green/yellow/red)
- [ ] Test AI generation button
- [ ] Verify "Apply suggestions" button works
- [ ] Test with Classic Editor (if available)

### Schema Metaboxes
- [ ] Verify FAQ Schema metabox appears
- [ ] Add FAQ items
- [ ] Verify FAQ items save correctly
- [ ] Verify HowTo Schema metabox appears
- [ ] Add HowTo steps
- [ ] Verify HowTo saves correctly

### QA Metabox
- [ ] Verify Q&A Pairs metabox appears
- [ ] Add Q&A pairs
- [ ] Verify Q&A pairs save correctly
- [ ] Verify schema output is valid

### Freshness Metabox
- [ ] Verify Freshness metabox appears
- [ ] Set update frequency
- [ ] Set review dates
- [ ] Verify dates save correctly

### Author Profile Fields
- [ ] Go to Users → Profile
- [ ] Verify author profile fields appear
- [ ] Fill in author data
- [ ] Verify data saves correctly

---

## Frontend Testing

### Meta Tags
- [ ] Create post with SEO data
- [ ] View post on frontend
- [ ] Inspect page source
- [ ] Verify meta tags in `<head>`:
  - [ ] `<title>` tag
  - [ ] `<meta name="description">`
  - [ ] Canonical URL
  - [ ] No duplicate tags
- [ ] Verify proper HTML escaping
- [ ] Test with post without SEO data (fallback)

### Schema JSON-LD
- [ ] View post with schema data
- [ ] Inspect page source
- [ ] Verify JSON-LD script tag exists
- [ ] Copy JSON-LD and validate at https://validator.schema.org
- [ ] Verify schema is valid JSON
- [ ] Verify required fields are present

### Social Media Tags
- [ ] View post with social meta
- [ ] Inspect page source
- [ ] Verify Open Graph tags:
  - [ ] `og:title`
  - [ ] `og:description`
  - [ ] `og:image`
  - [ ] `og:url`
- [ ] Verify Twitter Card tags:
  - [ ] `twitter:card`
  - [ ] `twitter:title`
  - [ ] `twitter:description`
  - [ ] `twitter:image`
- [ ] Test with Facebook Debugger
- [ ] Test with Twitter Card Validator

### Conditional Rendering
- [ ] Test on supported post types (post, page)
- [ ] Test on unsupported post types (attachment, revision)
- [ ] Verify meta tags only output on supported types
- [ ] Test on homepage
- [ ] Test on archive pages (should not output)
- [ ] Test with `_fp_seo_performance_exclude` meta set

---

## REST API Testing

### Endpoints
- [ ] Test GET `/wp-json/fp-seo/v1/meta/{id}`
  - [ ] With valid post ID
  - [ ] With invalid post ID (should return 404)
  - [ ] Without authentication (should return 401/403)
- [ ] Test POST `/wp-json/fp-seo/v1/meta/{id}`
  - [ ] With valid data
  - [ ] With invalid data (should return 400)
  - [ ] Without permissions (should return 403)
- [ ] Verify REST meta fields registered:
  - [ ] `fp_seo_title`
  - [ ] `fp_seo_meta_description`

### Authentication
- [ ] Test with cookie authentication
- [ ] Test with application password
- [ ] Test without authentication (should fail)
- [ ] Verify permission checks work

### Validation
- [ ] Test with missing required parameters
- [ ] Test with invalid data types
- [ ] Test with XSS attempts (should be sanitized)
- [ ] Verify error responses are correct

---

## WP-CLI Testing

### Analysis Command
- [ ] Run `wp fp-seo analysis --help`
- [ ] Run `wp fp-seo analysis` (analyze all posts)
- [ ] Run `wp fp-seo analysis --post-type=post`
- [ ] Run `wp fp-seo analysis --post-id=1`
- [ ] Verify output is formatted correctly
- [ ] Test with no posts (should handle gracefully)
- [ ] Test error handling

### Cache Command
- [ ] Run `wp fp-seo cache --help`
- [ ] Run `wp fp-seo cache clear`
- [ ] Run `wp fp-seo cache flush`
- [ ] Verify cache is cleared
- [ ] Test error handling

---

## Security Testing

### Nonce Validation
- [ ] Test all forms have nonces
- [ ] Test AJAX requests verify nonces
- [ ] Test with expired nonce (should fail)
- [ ] Test with invalid nonce (should fail)

### Capability Checks
- [ ] Test admin pages with user without `manage_options`
- [ ] Test metabox with user without `edit_posts`
- [ ] Verify unauthorized access is blocked
- [ ] Verify proper error messages

### XSS Prevention
- [ ] Test input with `<script>` tags
- [ ] Test input with `javascript:` URLs
- [ ] Verify output is escaped
- [ ] Test in all input fields

### SQL Injection Prevention
- [ ] Verify all queries use prepared statements
- [ ] Test with SQL injection attempts
- [ ] Verify no direct SQL concatenation

---

## Performance Testing

### Memory Usage
- [ ] Check memory usage on admin pages
- [ ] Check memory usage on frontend
- [ ] Verify no memory leaks
- [ ] Use tools like Query Monitor

### Database Queries
- [ ] Check query count on admin pages
- [ ] Check query count on frontend
- [ ] Verify no N+1 queries
- [ ] Use Query Monitor to verify

### Load Time
- [ ] Measure admin page load time
- [ ] Measure frontend page load time
- [ ] Verify acceptable performance
- [ ] Use browser DevTools

### Asset Loading
- [ ] Verify CSS/JS only load when needed
- [ ] Verify no conflicts with theme/plugins
- [ ] Check asset sizes
- [ ] Verify minification works

---

## Compatibility Testing

### Themes
- [ ] Test with Twenty Twenty-Four
- [ ] Test with Salient
- [ ] Test with other major themes
- [ ] Verify metaboxes display correctly
- [ ] Verify frontend output doesn't break layout

### Plugins
- [ ] Test with WooCommerce
- [ ] Test with Yoast SEO (no conflicts)
- [ ] Test with Rank Math (no conflicts)
- [ ] Test with page builders:
  - [ ] Elementor
  - [ ] WPBakery
  - [ ] Divi
  - [ ] Gutenberg

### Editors
- [ ] Test with Gutenberg editor
- [ ] Test with Classic Editor
- [ ] Verify metaboxes work in both

### Browsers
- [ ] Test in Chrome
- [ ] Test in Firefox
- [ ] Test in Safari
- [ ] Test in Edge
- [ ] Verify responsive design

---

## Multisite Testing

### Network Activation
- [ ] Activate plugin network-wide
- [ ] Verify tables created per site
- [ ] Verify settings isolated per site
- [ ] Test on multiple sites

### Per-Site Provisioning
- [ ] Verify each site gets own data
- [ ] Verify settings don't leak between sites
- [ ] Test cron events per site
- [ ] Test uninstall per site

---

## Multilanguage Testing

### Translation
- [ ] Verify all strings are translatable
- [ ] Test with different languages
- [ ] Verify translation files load
- [ ] Test RTL support (if applicable)

### FP-Multilanguage
- [ ] Test with FP-Multilanguage plugin
- [ ] Verify integration works
- [ ] Test language-specific meta
- [ ] Verify no conflicts

### WPML/Polylang
- [ ] Test with WPML (if available)
- [ ] Test with Polylang (if available)
- [ ] Verify compatibility

---

## Uninstall Testing

### Data Cleanup
- [ ] Create test data (options, meta, transients)
- [ ] Delete plugin (not deactivate)
- [ ] Verify all options removed
- [ ] Verify all post meta removed
- [ ] Verify all user meta removed
- [ ] Verify all transients removed
- [ ] Verify database table dropped
- [ ] Verify no orphaned data

### Deactivation vs Uninstall
- [ ] Deactivate plugin
- [ ] Verify data is preserved
- [ ] Delete plugin
- [ ] Verify data is removed

---

## Regression Testing

### Critical Fixes (from CHANGELOG)
- [ ] Test post type protection (0.9.0-pre.32+)
- [ ] Test media library blocking (0.9.0-pre.27+)
- [ ] Test homepage protection (disabled hooks)
- [ ] Test performance optimizer filters (disabled)
- [ ] Test all fixes from 0.9.0-pre.27 through 0.9.0-pre.72

### Known Issues
- [ ] Verify no interference with Nectar Sliders
- [ ] Verify no interference with attachments
- [ ] Verify no "Auto Draft" issues
- [ ] Verify media library thumbnails work

---

## Notes

- Test in staging environment first
- Document any issues found
- Take screenshots of problems
- Test with different user roles
- Test with different post types
- Test edge cases

---

**Status**: Ready for Manual Testing














