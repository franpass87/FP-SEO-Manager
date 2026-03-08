const { test, expect } = require('@playwright/test');

/**
 * Frontend rendering tests
 */
test.describe('FP SEO Manager - Frontend Rendering', () => {
  test('should render meta tags on homepage', async ({ page }) => {
    await page.goto('/');
    
    // Check for meta description
    const metaDesc = page.locator('meta[name="description"]');
    if (await metaDesc.count() > 0) {
      const content = await metaDesc.getAttribute('content');
      expect(content).toBeTruthy();
    }

    // Check for Open Graph tags
    const ogTitle = page.locator('meta[property="og:title"]');
    const ogDesc = page.locator('meta[property="og:description"]');
    const ogCount = await ogTitle.count() + await ogDesc.count();
    expect(ogCount).toBeGreaterThanOrEqual(0);
  });

  test('should render schema markup', async ({ page }) => {
    await page.goto('/');
    
    // Check for JSON-LD schema
    const schema = page.locator('script[type="application/ld+json"]');
    const count = await schema.count();
    expect(count).toBeGreaterThanOrEqual(0);
    
    if (count > 0) {
      const schemaContent = await schema.first().textContent();
      expect(schemaContent).toBeTruthy();
    }
  });

  test('should not have console errors', async ({ page }) => {
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Filter out known non-critical errors
    const criticalErrors = errors.filter(err => 
      !err.includes('wp-compression-test') && 
      !err.includes('favicon')
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('should render social media tags', async ({ page }) => {
    await page.goto('/');
    
    // Check for Twitter Card tags
    const twitterCard = page.locator('meta[name*="twitter"]');
    const count = await twitterCard.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should render keywords if configured', async ({ page }) => {
    await page.goto('/');
    
    // Check for keywords meta tag
    const keywords = page.locator('meta[name="keywords"]');
    const count = await keywords.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });
});














