const { test, expect } = require('@playwright/test');

/**
 * Settings Advanced Tab tests
 */
test.describe('FP SEO Manager - Settings Advanced Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=advanced');
  });

  test('should display Advanced tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("Advanced")')).toBeVisible();
  });

  test('should have nonce in form', async ({ page }) => {
    const nonce = page.locator('input[name="_wpnonce"]');
    await expect(nonce).toBeVisible();
  });

  test('should save advanced settings', async ({ page }) => {
    await page.click('input[type="submit"][name="submit"]');
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });
});














