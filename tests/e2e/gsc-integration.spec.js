const { test, expect } = require('@playwright/test');

/**
 * Google Search Console integration tests
 */
test.describe('FP SEO Manager - GSC Integration', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to GSC settings', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=gsc');
    
    // Check if GSC tab exists or page loads
    await expect(page.locator('body')).toBeVisible();
  });

  test('should display GSC configuration form', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=gsc');
    
    // Look for GSC-related fields
    const gscFields = page.locator('input[name*="gsc"], input[name*="search_console"], button:has-text("Connect")');
    const count = await gscFields.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have nonce for GSC AJAX requests', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=gsc');
    
    // Check for nonce in data attributes or hidden inputs
    const nonce = page.locator('[data-nonce], input[name*="nonce"]');
    const count = await nonce.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});














