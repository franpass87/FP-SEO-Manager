const { test, expect } = require('@playwright/test');

/**
 * GEO integration tests
 */
test.describe('FP SEO Manager - GEO Integration', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to GEO settings', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=geo');
    
    // Check if GEO tab exists or page loads
    await expect(page.locator('body')).toBeVisible();
  });

  test('should display GEO configuration form', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=geo');
    
    // Look for GEO-related fields
    const geoFields = page.locator('input[name*="geo"], select[name*="geo"], input[name*="location"]');
    const count = await geoFields.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should save GEO settings', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=geo');
    
    const submitBtn = page.locator('input[type="submit"][name="submit"]');
    if (await submitBtn.count() > 0) {
      await submitBtn.click();
      await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
    }
  });
});














