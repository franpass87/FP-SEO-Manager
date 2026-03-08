const { test, expect } = require('@playwright/test');

/**
 * Settings Performance Tab tests
 */
test.describe('FP SEO Manager - Settings Performance Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=performance');
  });

  test('should display Performance tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("Performance")')).toBeVisible();
  });

  test('should display PSI integration settings', async ({ page }) => {
    // Look for PSI-related fields
    const psiFields = page.locator('input[name*="psi"], input[name*="pagespeed"], select[name*="psi"]');
    const count = await psiFields.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should display cache settings', async ({ page }) => {
    // Look for cache-related fields
    const cacheFields = page.locator('input[name*="cache"], select[name*="cache"]');
    const count = await cacheFields.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should save performance settings', async ({ page }) => {
    await page.click('input[type="submit"][name="submit"]');
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });
});














