# FP-SEO-Manager Regression Testing Checklist

**Version**: 0.9.0-pre.72  
**Date**: 2025-01-27

This checklist verifies all critical fixes from CHANGELOG.md, especially versions 0.9.0-pre.27 through 0.9.0-pre.72.

---

## Post Type Protection (0.9.0-pre.32+)

### Unsupported Post Types
- [ ] Create/edit an attachment (image)
  - [ ] Verify plugin does NOT interfere
  - [ ] Verify image saves correctly
  - [ ] Verify metadata saves correctly
  - [ ] Check logs for "skipped - unsupported post type"

- [ ] Create/edit a revision
  - [ ] Verify plugin does NOT interfere
  - [ ] Verify revision saves correctly

- [ ] Create/edit a nav_menu_item
  - [ ] Verify plugin does NOT interfere
  - [ ] Verify menu item saves correctly

- [ ] Create/edit custom post types without editor support
  - [ ] Test with Nectar Sliders (if available)
  - [ ] Verify plugin does NOT interfere
  - [ ] Verify slider saves correctly

### Supported Post Types
- [ ] Create/edit a post
  - [ ] Verify metabox appears
  - [ ] Verify SEO data saves correctly
  - [ ] Verify analysis runs

- [ ] Create/edit a page
  - [ ] Verify metabox appears
  - [ ] Verify SEO data saves correctly
  - [ ] Verify analysis runs

---

## Media Library Blocking (0.9.0-pre.27+)

### Media Library Pages
- [ ] Navigate to Media Library (upload.php)
  - [ ] Verify plugin does NOT load
  - [ ] Verify thumbnails display correctly
  - [ ] Verify no JavaScript errors
  - [ ] Verify no PHP errors

- [ ] Navigate to Add New Media (media-new.php)
  - [ ] Verify plugin does NOT load
  - [ ] Verify upload works correctly
  - [ ] Verify no errors

- [ ] Test AJAX query-attachments
  - [ ] Open media library in grid view
  - [ ] Verify plugin does NOT load
  - [ ] Verify attachments load correctly
  - [ ] Verify no errors

### Post Edit Pages (Should Load)
- [ ] Navigate to post edit page
  - [ ] Verify plugin DOES load
  - [ ] Verify metabox appears
  - [ ] Verify functionality works

---

## Homepage Protection (Disabled Hooks)

### Homepage Editor
- [ ] Open homepage in editor
  - [ ] Verify no "Auto Draft" issues
  - [ ] Verify homepage saves correctly
  - [ ] Verify no interference with homepage status
  - [ ] Check logs for disabled hooks

### Homepage Frontend
- [ ] View homepage on frontend
  - [ ] Verify meta tags output correctly
  - [ ] Verify no errors
  - [ ] Verify homepage displays correctly

---

## Performance Optimizer Filters (Disabled)

### Database Queries
- [ ] Check Query Monitor
  - [ ] Verify no interference with WordPress queries
  - [ ] Verify `posts_where` filter is disabled
  - [ ] Verify `posts_orderby` filter is disabled
  - [ ] Verify query count is normal

### Post Queries
- [ ] Test post list queries
  - [ ] Verify posts display correctly
  - [ ] Verify sorting works
  - [ ] Verify filtering works
  - [ ] Verify no query interference

---

## Cache Clearing Fixes (0.9.0-pre.31)

### Page Load
- [ ] Open post editor
  - [ ] Verify no cache clearing during page load
  - [ ] Verify post object is correct
  - [ ] Verify no "Auto Draft" issues
  - [ ] Check logs for cache clearing calls

### Save Operations
- [ ] Save a post
  - [ ] Verify cache clearing only during save
  - [ ] Verify post saves correctly
  - [ ] Verify no interference

---

## Meta Saving Protection (0.9.0-pre.34+)

### All Save Methods
- [ ] Test `save_meta()` method
  - [ ] Verify post type check FIRST
  - [ ] Verify unsupported types exit immediately
  - [ ] Check logs for early exit

- [ ] Test `save_meta_edit_post()` method
  - [ ] Verify post type check FIRST
  - [ ] Verify unsupported types exit immediately

- [ ] Test `save_meta_insert_post()` method
  - [ ] Verify post type check FIRST
  - [ ] Verify unsupported types exit immediately

- [ ] Test AJAX save methods
  - [ ] Test `handle_save_fields_ajax()`
  - [ ] Test `handle_save_images_ajax()`
  - [ ] Verify post type checks

### Social Media Manager
- [ ] Test `save_social_meta()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

### Schema Metaboxes
- [ ] Test `save_faq_schema()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

- [ ] Test `save_howto_schema()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

### Keywords Manager
- [ ] Test `save_keywords_meta()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

### GEO Metaboxes
- [ ] Test `GeoMetaBox::save_meta()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

- [ ] Test `FreshnessMetaBox::save_meta()` method
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

### Automation
- [ ] Test `AutoSeoOptimizer::maybe_auto_optimize()`
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

- [ ] Test `AutoGenerationHook::on_publish()`
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

- [ ] Test `AutoGenerationHook::on_update()`
  - [ ] Verify post type check
  - [ ] Verify unsupported types exit

---

## Static Tracking Protection (0.9.0-pre.33)

### Duplicate Prevention
- [ ] Save same post multiple times quickly
  - [ ] Verify static tracking prevents duplicates
  - [ ] Verify post type check happens BEFORE tracking
  - [ ] Check logs for tracking messages

---

## Logging Improvements (0.9.0-pre.33+)

### Debug Logging
- [ ] Enable WP_DEBUG
- [ ] Perform various operations
- [ ] Check debug.log for:
  - [ ] "skipped - unsupported post type" messages
  - [ ] Post type information in logs
  - [ ] Supported types in logs
  - [ ] No excessive logging

---

## Migration Testing

### Database Migrations
- [ ] Fresh installation
  - [ ] Activate plugin
  - [ ] Verify score history table created
  - [ ] Verify migration version tracked

- [ ] Upgrade from previous version
  - [ ] Install previous version
  - [ ] Create test data
  - [ ] Upgrade to current version
  - [ ] Verify data preserved
  - [ ] Verify migrations run

---

## Uninstall Testing

### Data Cleanup
- [ ] Create comprehensive test data:
  - [ ] Options
  - [ ] Post meta (all types)
  - [ ] User meta
  - [ ] Transients
  - [ ] Database table entries
- [ ] Delete plugin (not deactivate)
- [ ] Verify ALL data removed:
  - [ ] All options
  - [ ] All post meta
  - [ ] All user meta
  - [ ] All transients
  - [ ] Database table dropped
- [ ] Verify no orphaned data

---

## Compatibility Testing

### Themes
- [ ] Test with Twenty Twenty-Four
- [ ] Test with Salient
- [ ] Test with other active themes
- [ ] Verify no conflicts

### Plugins
- [ ] Test with WooCommerce
- [ ] Test with Yoast SEO
- [ ] Test with Rank Math
- [ ] Test with page builders
- [ ] Verify no conflicts

---

## Performance Regression

### Before/After Comparison
- [ ] Measure performance before changes
- [ ] Measure performance after changes
- [ ] Verify no degradation
- [ ] Verify improvements (if any)

### Memory Usage
- [ ] Check memory usage
- [ ] Verify no memory leaks
- [ ] Verify acceptable footprint

### Query Count
- [ ] Check database query count
- [ ] Verify no N+1 queries
- [ ] Verify optimization works

---

## Security Regression

### All Security Fixes
- [ ] Verify nonce validation still works
- [ ] Verify capability checks still work
- [ ] Verify XSS prevention still works
- [ ] Verify SQL injection prevention still works
- [ ] Verify output escaping still works

---

## Notes

- Test each fix individually
- Document any regressions found
- Verify fixes don't break other functionality
- Test edge cases
- Test with different user roles
- Test with different post types

---

**Status**: Ready for Regression Testing














