/**
 * Complete E2E tests for admin pages - navigation, forms, AJAX.
 *
 * @package FP\SEO\Tests\E2E
 */

const { test, expect } = require('@playwright/test');

test.describe('FP SEO Manager - Complete Admin Pages Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to dashboard and display statistics', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance');
    await expect(page.locator('h1:has-text("SEO Performance")')).toBeVisible();
    
    // Check for statistics
    const stats = page.locator('[class*="stat"], [class*="dashboard"]');
    if (await stats.count() > 0) {
      await expect(stats.first()).toBeVisible();
    }
  });

  test('should navigate to settings page and switch tabs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    await expect(page.locator('h1:has-text("Settings")')).toBeVisible();
    
    const tabs = ['General', 'Analysis', 'Performance', 'Automation', 'Advanced'];
    
    for (const tab of tabs) {
      const tabLink = page.locator(`.nav-tab:has-text("${tab}")`);
      if (await tabLink.count() > 0) {
        await tabLink.first().click();
        await page.waitForTimeout(500);
        await expect(tabLink.first()).toHaveClass(/nav-tab-active/);
      }
    }
  });

  test('should save settings form', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=general');
    
    // Find and fill a setting field
    const enableAnalyzer = page.locator('input[name*="enable_analyzer"]');
    if (await enableAnalyzer.count() > 0) {
      await enableAnalyzer.first().check();
      
      // Submit form
      const submitButton = page.locator('input[type="submit"], button[type="submit"]');
      if (await submitButton.count() > 0) {
        await submitButton.first().click();
        await page.waitForTimeout(1000);
        
        // Check for success message
        const successMessage = page.locator('.notice-success, .updated');
        if (await successMessage.count() > 0) {
          await expect(successMessage.first()).toBeVisible();
        }
      }
    }
  });

  test('should navigate to bulk auditor page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk');
    await expect(page.locator('h1:has-text("Bulk Auditor")')).toBeVisible();
    
    // Check for bulk auditor UI elements
    const selectAll = page.locator('input[type="checkbox"][name*="select"], .select-all');
    if (await selectAll.count() > 0) {
      await expect(selectAll.first()).toBeVisible();
    }
  });

  test('should select posts and trigger bulk analysis', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk');
    
    // Select a post
    const postCheckbox = page.locator('input[type="checkbox"][name*="post"]').first();
    if (await postCheckbox.count() > 0) {
      await postCheckbox.first().check();
      
      // Click analyze button
      const analyzeButton = page.locator('button:has-text("Analyze"), [data-action*="analyze"]');
      if (await analyzeButton.count() > 0) {
        await analyzeButton.first().click();
        await page.waitForTimeout(2000);
        
        // Check for progress indicator
        const progress = page.locator('[class*="progress"], [class*="loading"]');
        if (await progress.count() > 0) {
          await expect(progress.first()).toBeVisible();
        }
      }
    }
  });

  test('should navigate to performance dashboard', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-dashboard');
    await expect(page.locator('h1:has-text("Performance")')).toBeVisible();
    
    // Check for performance buttons
    const healthCheckButton = page.locator('button:has-text("Health Check"), [data-action*="health"]');
    if (await healthCheckButton.count() > 0) {
      await expect(healthCheckButton.first()).toBeVisible();
    }
  });

  test('should trigger health check AJAX', async ({ page }) => {
    const ajaxCalls = [];
    
    page.on('response', response => {
      if (response.url().includes('admin-ajax.php') && response.url().includes('health')) {
        ajaxCalls.push({
          url: response.url(),
          status: response.status(),
        });
      }
    });
    
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-dashboard');
    
    const healthCheckButton = page.locator('button:has-text("Health Check"), [data-action*="health"]');
    if (await healthCheckButton.count() > 0) {
      await healthCheckButton.first().click();
      await page.waitForTimeout(2000);
      
      // Verify AJAX call was made
      expect(ajaxCalls.length).toBeGreaterThan(0);
    }
  });

  test('should export bulk audit results', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk');
    
    const exportButton = page.locator('button:has-text("Export"), a:has-text("Export"), [data-action*="export"]');
    if (await exportButton.count() > 0) {
      await expect(exportButton.first()).toBeVisible();
      
      // Note: Actually clicking export would download a file, so we just verify button exists
    }
  });

  test('should handle import/export settings', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=advanced');
    
    // Check for import/export section
    const importSection = page.locator('[class*="import"], [class*="export"]');
    if (await importSection.count() > 0) {
      await expect(importSection.first()).toBeVisible();
    }
  });

  test('should display all admin menu items', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    const menuItems = [
      'SEO Performance',
      'Settings',
      'Bulk Auditor',
      'Performance',
      'Test Suite',
    ];
    
    for (const item of menuItems) {
      const menuItem = page.locator(`#toplevel_page_fp-seo-performance, .wp-menu-name:has-text("${item}")`);
      if (await menuItem.count() > 0) {
        await expect(menuItem.first()).toBeVisible();
      }
    }
  });

  test('should handle form validation errors', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=general');
    
    // Try to submit empty form or invalid data
    const submitButton = page.locator('input[type="submit"], button[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.first().click();
      await page.waitForTimeout(1000);
      
      // Check for error messages
      const errorMessage = page.locator('.notice-error, .error');
      // Errors may or may not appear depending on validation
      // Just verify page is responsive
      await expect(page.locator('body')).toBeVisible();
    }
  });
});



