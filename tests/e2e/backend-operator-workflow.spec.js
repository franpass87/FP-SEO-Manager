const { test, expect } = require('@playwright/test');

/**
 * Backend Operator Workflow Tests
 * 
 * These tests simulate real-world usage by a backend operator
 * who uses the plugin daily to optimize content for SEO.
 */
test.describe('FP SEO Manager - Backend Operator Workflow', () => {
  let adminPage;
  let postId;

  test.beforeEach(async ({ page }) => {
    adminPage = page;
    // Login as admin
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('Complete SEO optimization workflow for a new post', async ({ page }) => {
    // Step 1: Create new post
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    // Fill title
    const titleField = page.locator('#title, .wp-block-post-title input, .wp-block-post-title textarea').first();
    await titleField.fill('Complete Guide to WordPress SEO in 2025');
    
    // Fill content
    const contentField = page.locator('.wp-block-post-content, #content, .editor-post-text-editor');
    if (await contentField.count() > 0) {
      await contentField.first().fill('This is a comprehensive guide to WordPress SEO. It covers all aspects of SEO optimization including meta tags, schema markup, and content optimization.');
    }
    
    // Step 2: Verify metabox is visible
    const metabox = page.locator('#fp-seo-performance-metabox, .fp-seo-metabox, [id*="fp-seo"]');
    await expect(metabox.first()).toBeVisible({ timeout: 5000 });
    
    // Step 3: Fill SEO fields manually
    const seoTitleField = page.locator('input[name*="seo_title"], input[id*="seo_title"]');
    if (await seoTitleField.count() > 0) {
      await seoTitleField.first().fill('WordPress SEO Guide 2025 - Complete Tutorial');
    }
    
    const metaDescField = page.locator('textarea[name*="meta_description"], textarea[id*="meta_description"]');
    if (await metaDescField.count() > 0) {
      await metaDescField.first().fill('Learn everything about WordPress SEO in 2025. Complete guide with best practices, tips, and strategies for optimizing your WordPress site.');
    }
    
    const focusKeywordField = page.locator('input[name*="focus_keyword"], input[id*="focus_keyword"]');
    if (await focusKeywordField.count() > 0) {
      await focusKeywordField.first().fill('wordpress seo');
    }
    
    // Step 4: Save post
    const publishButton = page.locator('button:has-text("Pubblica"), button:has-text("Publish"), #publish');
    await publishButton.first().click();
    
    // Wait for save confirmation
    await page.waitForSelector('.notice-success, .updated', { timeout: 10000 });
    
    // Step 5: Verify SEO data was saved
    const successMessage = page.locator('.notice-success, .updated');
    await expect(successMessage.first()).toBeVisible();
  });

  test('Bulk audit workflow', async ({ page }) => {
    // Step 1: Navigate to Bulk Audit
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify page loaded
    const pageTitle = page.locator('h1, .wrap h1');
    await expect(pageTitle.first()).toContainText(/audit|Audit/i);
    
    // Step 3: Select post type
    const postTypeSelect = page.locator('select[name*="post_type"], select[id*="post_type"]');
    if (await postTypeSelect.count() > 0) {
      await postTypeSelect.first().selectOption('post');
    }
    
    // Step 4: Verify posts list is visible
    const postsTable = page.locator('table.wp-list-table, .posts-table, table');
    if (await postsTable.count() > 0) {
      await expect(postsTable.first()).toBeVisible();
    }
    
    // Step 5: Select some posts (if checkboxes available)
    const checkboxes = page.locator('input[type="checkbox"][name*="post"]');
    if (await checkboxes.count() > 2) {
      await checkboxes.nth(1).check();
      await checkboxes.nth(2).check();
    }
  });

  test('Settings configuration workflow', async ({ page }) => {
    // Step 1: Navigate to Settings
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify settings page loaded
    const settingsTitle = page.locator('h1, .wrap h1');
    await expect(settingsTitle.first()).toContainText(/settings|Settings|Impostazioni/i);
    
    // Step 3: Navigate through tabs
    const tabs = page.locator('.nav-tab, .nav-tab-wrapper a, [role="tab"]');
    const tabCount = await tabs.count();
    
    if (tabCount > 0) {
      // Click on General tab
      await tabs.first().click();
      await page.waitForTimeout(500);
      
      // Click on Analysis tab (if available)
      if (tabCount > 1) {
        await tabs.nth(1).click();
        await page.waitForTimeout(500);
      }
      
      // Click on AI tab (if available)
      if (tabCount > 2) {
        await tabs.nth(2).click();
        await page.waitForTimeout(500);
      }
    }
    
    // Step 4: Verify save button exists
    const saveButton = page.locator('button[type="submit"]:has-text("Salva"), input[type="submit"]:has-text("Salva"), #submit');
    if (await saveButton.count() > 0) {
      await expect(saveButton.first()).toBeVisible();
    }
  });

  test('Schema markup workflow', async ({ page }) => {
    // Step 1: Create or edit a post
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    // Fill basic post data
    const titleField = page.locator('#title, .wp-block-post-title input, .wp-block-post-title textarea').first();
    await titleField.fill('Test Post for Schema Markup');
    
    // Step 2: Look for FAQ Schema metabox
    const faqMetabox = page.locator('[id*="faq"], [class*="faq"], [id*="FAQ"]');
    if (await faqMetabox.count() > 0) {
      await expect(faqMetabox.first()).toBeVisible();
    }
    
    // Step 3: Look for HowTo Schema metabox
    const howtoMetabox = page.locator('[id*="howto"], [class*="howto"], [id*="HowTo"]');
    if (await howtoMetabox.count() > 0) {
      await expect(howtoMetabox.first()).toBeVisible();
    }
  });

  test('Performance dashboard workflow', async ({ page }) => {
    // Step 1: Navigate to Performance Dashboard
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-dashboard');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify dashboard loaded
    const dashboardTitle = page.locator('h1, .wrap h1');
    await expect(dashboardTitle.first()).toBeVisible();
    
    // Step 3: Look for metrics
    const metrics = page.locator('.metric, .stat, [class*="score"], [class*="metric"]');
    if (await metrics.count() > 0) {
      // At least one metric should be visible
      await expect(metrics.first()).toBeVisible();
    }
    
    // Step 4: Look for charts/graphs
    const charts = page.locator('canvas, svg, [class*="chart"], [id*="chart"]');
    if (await charts.count() > 0) {
      // Charts might take time to render
      await page.waitForTimeout(2000);
    }
  });

  test('Social media meta configuration workflow', async ({ page }) => {
    // Step 1: Navigate to Social Media settings
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-social');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify page loaded
    const pageTitle = page.locator('h1, .wrap h1');
    await expect(pageTitle.first()).toBeVisible();
    
    // Step 3: Look for Open Graph settings
    const ogSettings = page.locator('[id*="og"], [class*="og"], [name*="og"]');
    if (await ogSettings.count() > 0) {
      await expect(ogSettings.first()).toBeVisible();
    }
    
    // Step 4: Look for Twitter Card settings
    const twitterSettings = page.locator('[id*="twitter"], [class*="twitter"], [name*="twitter"]');
    if (await twitterSettings.count() > 0) {
      await expect(twitterSettings.first()).toBeVisible();
    }
  });

  test('Internal links workflow', async ({ page }) => {
    // Step 1: Navigate to Internal Links
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-links');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify page loaded
    const pageTitle = page.locator('h1, .wrap h1');
    await expect(pageTitle.first()).toBeVisible();
    
    // Step 3: Look for link suggestions
    const suggestions = page.locator('.suggestion, [class*="suggestion"], [class*="link"]');
    if (await suggestions.count() > 0) {
      await expect(suggestions.first()).toBeVisible();
    }
  });

  test('Multiple keywords workflow', async ({ page }) => {
    // Step 1: Navigate to Multiple Keywords
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-keywords');
    await page.waitForLoadState('networkidle');
    
    // Step 2: Verify page loaded
    const pageTitle = page.locator('h1, .wrap h1');
    await expect(pageTitle.first()).toBeVisible();
    
    // Step 3: Look for keyword input
    const keywordInput = page.locator('input[name*="keyword"], input[id*="keyword"]');
    if (await keywordInput.count() > 0) {
      await expect(keywordInput.first()).toBeVisible();
    }
  });
});




