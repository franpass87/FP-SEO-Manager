const { test, expect } = require('@playwright/test');

/**
 * Plugin menu navigation tests
 */
test.describe('FP SEO Manager - Menu Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should display SEO Performance menu item', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Check for menu item
    await expect(page.locator('#toplevel_page_fp-seo-performance')).toBeVisible();
  });

  test('should navigate to dashboard', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance');
    
    // Check dashboard content
    await expect(page.locator('h1, h2')).toContainText(/SEO Performance/i);
  });

  test('should navigate to bulk audit page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk-audit');
    
    // Check page loads
    await expect(page.locator('body')).toBeVisible();
    
    // Check for nonce in form if present
    const nonceField = page.locator('input[name*="nonce"]');
    if (await nonceField.count() > 0) {
      await expect(nonceField.first()).toBeVisible();
    }
  });

  test('should navigate to performance dashboard', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-dashboard');
    
    await expect(page.locator('body')).toBeVisible();
  });

  test('should navigate to GSC dashboard if available', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-gsc');
    
    await expect(page.locator('body')).toBeVisible();
  });

  test('should navigate to test suite if available', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-test-suite');
    
    await expect(page.locator('body')).toBeVisible();
  });
});














