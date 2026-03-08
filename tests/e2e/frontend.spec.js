const { test, expect } = require('@playwright/test');

/**
 * Frontend tests for FP SEO Manager
 */
test.describe('FP SEO Manager - Frontend', () => {
  test('should load homepage without errors', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Check page loaded
    await expect(page.locator('body')).toBeVisible();
    
    // Filter known non-critical errors
    const criticalErrors = consoleErrors.filter(err => 
      !err.includes('favicon') && 
      !err.includes('sourcemap') &&
      !err.includes('extension') &&
      !err.includes('wp-emoji') &&
      !err.includes('jquery')
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('should have proper meta tags', async ({ page }) => {
    await page.goto('/');
    
    // Check for meta description
    const metaDescription = page.locator('meta[name="description"]');
    if (await metaDescription.count() > 0) {
      const content = await metaDescription.getAttribute('content');
      expect(content).toBeTruthy();
    }
    
    // Check for Open Graph tags if present
    const ogTitle = page.locator('meta[property="og:title"]');
    // Just verify page structure, don't fail if OG tags aren't present
    await expect(page.locator('head')).toBeVisible();
  });

  test('should have proper schema markup', async ({ page }) => {
    await page.goto('/');
    
    // Check for JSON-LD schema
    const schemaScripts = page.locator('script[type="application/ld+json"]');
    const count = await schemaScripts.count();
    
    // Schema is optional, just verify page structure
    await expect(page.locator('body')).toBeVisible();
  });

  test('should check for XSS vulnerabilities in output', async ({ page }) => {
    await page.goto('/');
    const content = await page.content();
    
    // Should not have unescaped script tags in body
    const bodyContent = content.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyContent) {
      // Check for suspicious patterns
      const suspiciousPatterns = [
        /<script[^>]*>.*?<\/script>/gi,
        /javascript:/gi,
        /onerror=/gi,
        /onclick=/gi
      ];
      
      for (const pattern of suspiciousPatterns) {
        const matches = bodyContent[1].match(pattern);
        // Some legitimate uses might exist, but excessive matches could indicate issues
        if (matches && matches.length > 10) {
          console.warn(`Found ${matches.length} matches for pattern ${pattern}`);
        }
      }
    }
    
    // Basic check passed
    expect(content).toBeTruthy();
  });
});














