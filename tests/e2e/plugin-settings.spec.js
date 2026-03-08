const { test, expect } = require('@playwright/test');

/**
 * Plugin settings page tests
 */
test.describe('FP SEO Manager - Settings Page', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should navigate to settings page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    // Check page title
    await expect(page.locator('h1')).toContainText(/FP SEO Performance/i);
    
    // Check tabs are visible
    await expect(page.locator('.nav-tab-wrapper')).toBeVisible();
    
    // Check for General tab
    await expect(page.locator('a.nav-tab:has-text("General")')).toBeVisible();
  });

  test('should display all settings tabs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    const expectedTabs = ['General', 'Analysis', 'Performance', 'Automation', 'Advanced'];
    
    for (const tab of expectedTabs) {
      await expect(page.locator(`a.nav-tab:has-text("${tab}")`)).toBeVisible();
    }
  });

  test('should switch between tabs', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    // Click Analysis tab
    await page.click('a.nav-tab:has-text("Analysis")');
    
    // Verify tab is active
    await expect(page.locator('a.nav-tab-active:has-text("Analysis")')).toBeVisible();
    
    // Click Performance tab
    await page.click('a.nav-tab:has-text("Performance")');
    
    // Verify tab is active
    await expect(page.locator('a.nav-tab-active:has-text("Performance")')).toBeVisible();
  });

  test('should save settings successfully', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    // Wait for form to load
    await page.waitForSelector('form[method="post"]');
    
    // Check for nonce field
    const nonceField = page.locator('input[name="_wpnonce"]');
    await expect(nonceField).toBeVisible();
    
    // Try to find a setting to modify (e.g., enable analyzer)
    const enableAnalyzer = page.locator('input[name*="enable_analyzer"]');
    if (await enableAnalyzer.count() > 0) {
      await enableAnalyzer.first().check();
      
      // Submit form
      await page.click('input[type="submit"][name="submit"]');
      
      // Wait for success message
      await expect(page.locator('.notice-success, .updated')).toBeVisible({ timeout: 5000 });
    }
  });

  test('should have proper nonce verification', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    // Check for nonce field in form
    const nonce = await page.locator('input[name="_wpnonce"]').getAttribute('value');
    expect(nonce).toBeTruthy();
    expect(nonce.length).toBeGreaterThan(10);
  });

  test('should escape output properly', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    
    // Check that HTML is escaped in text content
    const pageContent = await page.content();
    
    // Should not have unescaped <script> tags
    const scriptMatches = pageContent.match(/<script[^>]*>(?!.*<\/script>)/gi);
    expect(scriptMatches).toBeNull();
  });

  test('should check console for errors', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    await page.goto('/wp-admin/admin.php?page=fp-seo-performance-settings');
    await page.waitForLoadState('networkidle');
    
    // Filter out known non-critical errors
    const criticalErrors = consoleErrors.filter(err => 
      !err.includes('favicon') && 
      !err.includes('sourcemap') &&
      !err.includes('extension')
    );
    
    expect(criticalErrors.length).toBe(0);
  });
});














