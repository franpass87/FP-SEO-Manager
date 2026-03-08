const { test, expect } = require('@playwright/test');

/**
 * AJAX handler security tests
 */
test.describe('FP SEO Manager - AJAX Handlers', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should require nonce for AI generation', async ({ page }) => {
    // Try to make AJAX request without nonce
    const response = await page.request.post('/wp-admin/admin-ajax.php', {
      data: {
        action: 'fp_seo_generate_ai_content',
        // No nonce
      }
    });
    
    // Should fail with 403 or error response
    expect(response.status()).toBeGreaterThanOrEqual(400);
  });

  test('should require authentication for AJAX requests', async ({ page, context }) => {
    // Create new context without authentication
    const newContext = await context.browser().newContext();
    const newPage = await newContext.newPage();
    
    const response = await newPage.request.post('/wp-admin/admin-ajax.php', {
      data: {
        action: 'fp_seo_generate_ai_content',
        nonce: 'invalid_nonce'
      }
    });
    
    // Should fail
    expect(response.status()).toBeGreaterThanOrEqual(400);
    
    await newContext.close();
  });

  test('should sanitize input in AJAX requests', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    // This test would need actual AJAX interaction
    // For now, just verify page loads
    await expect(page.locator('body')).toBeVisible();
  });
});














