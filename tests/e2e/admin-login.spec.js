const { test, expect } = require('@playwright/test');

/**
 * Admin login tests for FP SEO Manager
 */
test.describe('WordPress Admin Login', () => {
  test('should login successfully with valid credentials', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    // Wait for login form
    await expect(page.locator('#user_login')).toBeVisible();
    
    // Fill credentials
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    
    // Submit form
    await page.click('#wp-submit');
    
    // Wait for admin dashboard
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    
    // Verify we're logged in
    await expect(page).toHaveURL(/.*wp-admin.*/);
  });

  test('should show error with invalid credentials', async ({ page }) => {
    await page.goto('/wp-admin/');
    
    await page.fill('#user_login', 'invalid_user');
    await page.fill('#user_pass', 'invalid_pass');
    await page.click('#wp-submit');
    
    // Should show error message
    await expect(page.locator('.login-error, #login_error')).toBeVisible();
  });
});














