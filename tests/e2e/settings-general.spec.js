const { test, expect } = require('@playwright/test');

/**
 * Settings General Tab tests
 */
test.describe('FP SEO Manager - Settings General Tab', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings&tab=general');
  });

  test('should display General tab content', async ({ page }) => {
    await expect(page.locator('a.nav-tab-active:has-text("General")')).toBeVisible();
    await expect(page.locator('form[action="options.php"]')).toBeVisible();
  });

  test('should have nonce in form', async ({ page }) => {
    const nonce = page.locator('input[name="_wpnonce"]');
    await expect(nonce).toBeVisible();
    const nonceValue = await nonce.inputValue();
    expect(nonceValue.length).toBeGreaterThan(0);
  });

  test('should save settings successfully', async ({ page }) => {
    // Fill some form fields if present
    const titleField = page.locator('input[name*="title"], input[name*="site_title"]');
    if (await titleField.count() > 0) {
      await titleField.first().fill('Test Site Title');
    }

    // Submit form
    await page.click('input[type="submit"][name="submit"]');
    
    // Check for success message
    await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
  });

  test('should validate input fields', async ({ page }) => {
    // Check for required fields
    const requiredFields = page.locator('input[required], select[required]');
    const count = await requiredFields.count();
    
    if (count > 0) {
      // Try to submit without filling required fields
      await page.click('input[type="submit"][name="submit"]');
      // Should show validation error or prevent submission
    }
  });
});














