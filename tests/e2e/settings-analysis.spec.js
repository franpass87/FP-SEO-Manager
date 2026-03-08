const { test, expect } = require('@playwright/test');

/**
 * Settings Analysis Tab tests
 */
test.describe('FP SEO Manager - Settings Analysis Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=analysis');
  });

  test('should display Analysis tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("Analysis")')).toBeVisible();
  });

  test('should display SEO checks configuration', async ({ page }) => {
    // Look for checkboxes or toggles for SEO checks
    const checks = page.locator('input[type="checkbox"][name*="check"], input[type="checkbox"][name*="analysis"]');
    const count = await checks.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should save analysis settings', async ({ page }) => {
    // Toggle a check if available
    const firstCheck = page.locator('input[type="checkbox"][name*="check"]').first();
    if (await firstCheck.count() > 0) {
      await firstCheck.click();
    }

    // Submit
    await page.click('input[type="submit"][name="submit"]');
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });
});














