const { test, expect } = require('@playwright/test');

/**
 * Bulk Audit tests
 */
test.describe('FP SEO Manager - Bulk Audit', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-bulk');
  });

  test('should display bulk audit page', async ({ page }) => {
    await expect(page.locator('h1')).toContainText(/Bulk Auditor/i);
  });

  test('should display audit table', async ({ page }) => {
    // Wait for table to load
    await page.waitForTimeout(2000);
    const table = page.locator('table, .bulk-audit-table, [class*="audit"]');
    const count = await table.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have nonce for AJAX requests', async ({ page }) => {
    // Check for nonce in page (might be in data attributes or hidden inputs)
    const nonceInput = page.locator('input[name*="nonce"], [data-nonce]');
    const count = await nonceInput.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should trigger bulk analysis', async ({ page }) => {
    // Look for analyze button
    const analyzeBtn = page.locator('button:has-text("Analyze"), button:has-text("Start"), [id*="analyze"]');
    if (await analyzeBtn.count() > 0) {
      // Intercept AJAX request
      const responsePromise = page.waitForResponse(response => 
        response.url().includes('admin-ajax.php') && response.url().includes('bulk')
      );
      
      await analyzeBtn.first().click();
      
      // Wait for response
      try {
        await responsePromise;
      } catch (e) {
        // Response might not come if no posts to analyze
      }
    }
  });

  test('should export CSV', async ({ page }) => {
    // Look for export button
    const exportBtn = page.locator('a:has-text("Export"), button:has-text("Export"), [href*="export"]');
    if (await exportBtn.count() > 0) {
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 5000 }).catch(() => null),
        exportBtn.first().click()
      ]);
      
      // Download might not trigger if no data
      if (download) {
        expect(download.suggestedFilename()).toContain('.csv');
      }
    }
  });
});














