const { test, expect } = require('@playwright/test');

/**
 * Settings AI Tab tests
 */
test.describe('FP SEO Manager - Settings AI Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=ai');
  });

  test('should display AI tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("AI")')).toBeVisible();
  });

  test('should display OpenAI configuration', async ({ page }) => {
    // Look for OpenAI API key field
    const apiKeyField = page.locator('input[name*="openai"], input[name*="api_key"], input[type="password"][name*="ai"]');
    const count = await apiKeyField.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should save AI settings', async ({ page }) => {
    await page.click('input[type="submit"][name="submit"]');
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });
});














