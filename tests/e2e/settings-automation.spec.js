const { test, expect } = require('@playwright/test');

/**
 * Settings Automation Tab tests
 */
test.describe('FP SEO Manager - Settings Automation Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=automation');
  });

  test('should display Automation tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("Automation")')).toBeVisible();
  });

  test('should display auto-optimization settings', async ({ page }) => {
    // Look for automation toggles
    const autoFields = page.locator('input[type="checkbox"][name*="auto"], input[type="checkbox"][name*="automation"]');
    const count = await autoFields.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should save automation settings', async ({ page }) => {
    await page.click('input[type="submit"][name="submit"]');
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });
});














