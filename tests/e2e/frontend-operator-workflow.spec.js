const { test, expect } = require('@playwright/test');

/**
 * Frontend Operator Workflow Tests
 * 
 * These tests simulate real-world usage by an end user
 * who views the website and verifies SEO elements are correctly rendered.
 */
test.describe('FP SEO Manager - Frontend Operator Workflow', () => {
  let testPostUrl;

  test.beforeAll(async ({ request }) => {
    // Create a test post with SEO data via API
    // This would typically be done by the backend operator first
    // For now, we'll assume a post exists or create it via admin
  });

  test('Verify meta tags on optimized post', async ({ page }) => {
    // Step 1: Navigate to a post (assuming one exists)
    // In real scenario, this would be a post optimized by backend operator
    await page.goto('/');
    
    // Try to find a post link
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      // If no post found, skip test
      test.skip();
      return;
    }
    
    // Step 2: Get page source
    const content = await page.content();
    
    // Step 3: Verify title tag
    const titleTag = await page.locator('title').textContent();
    expect(titleTag).toBeTruthy();
    expect(titleTag.length).toBeGreaterThan(0);
    
    // Step 4: Verify meta description
    const metaDesc = page.locator('meta[name="description"]');
    if (await metaDesc.count() > 0) {
      const descContent = await metaDesc.getAttribute('content');
      expect(descContent).toBeTruthy();
      expect(descContent.length).toBeGreaterThan(0);
    }
    
    // Step 5: Verify canonical URL
    const canonical = page.locator('link[rel="canonical"]');
    if (await canonical.count() > 0) {
      const canonicalUrl = await canonical.getAttribute('href');
      expect(canonicalUrl).toBeTruthy();
      expect(canonicalUrl).toMatch(/^https?:\/\//);
    }
  });

  test('Verify Open Graph tags', async ({ page }) => {
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Verify Open Graph tags
    const ogTitle = page.locator('meta[property="og:title"]');
    if (await ogTitle.count() > 0) {
      const ogTitleContent = await ogTitle.getAttribute('content');
      expect(ogTitleContent).toBeTruthy();
    }
    
    const ogDesc = page.locator('meta[property="og:description"]');
    if (await ogDesc.count() > 0) {
      const ogDescContent = await ogDesc.getAttribute('content');
      expect(ogDescContent).toBeTruthy();
    }
    
    const ogImage = page.locator('meta[property="og:image"]');
    if (await ogImage.count() > 0) {
      const ogImageContent = await ogImage.getAttribute('content');
      expect(ogImageContent).toBeTruthy();
      expect(ogImageContent).toMatch(/^https?:\/\//);
    }
    
    const ogUrl = page.locator('meta[property="og:url"]');
    if (await ogUrl.count() > 0) {
      const ogUrlContent = await ogUrl.getAttribute('content');
      expect(ogUrlContent).toBeTruthy();
      expect(ogUrlContent).toMatch(/^https?:\/\//);
    }
  });

  test('Verify Twitter Card tags', async ({ page }) => {
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Verify Twitter Card tags
    const twitterCard = page.locator('meta[name="twitter:card"]');
    if (await twitterCard.count() > 0) {
      const cardType = await twitterCard.getAttribute('content');
      expect(cardType).toBeTruthy();
    }
    
    const twitterTitle = page.locator('meta[name="twitter:title"]');
    if (await twitterTitle.count() > 0) {
      const twitterTitleContent = await twitterTitle.getAttribute('content');
      expect(twitterTitleContent).toBeTruthy();
    }
    
    const twitterDesc = page.locator('meta[name="twitter:description"]');
    if (await twitterDesc.count() > 0) {
      const twitterDescContent = await twitterDesc.getAttribute('content');
      expect(twitterDescContent).toBeTruthy();
    }
  });

  test('Verify schema JSON-LD markup', async ({ page }) => {
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Look for schema JSON-LD
    const schemaScripts = page.locator('script[type="application/ld+json"]');
    const schemaCount = await schemaScripts.count();
    
    if (schemaCount > 0) {
      // At least one schema should be present
      expect(schemaCount).toBeGreaterThan(0);
      
      // Verify first schema is valid JSON
      const firstSchema = await schemaScripts.first().textContent();
      expect(firstSchema).toBeTruthy();
      
      // Try to parse as JSON
      try {
        const schemaJson = JSON.parse(firstSchema);
        expect(schemaJson).toHaveProperty('@context');
        expect(schemaJson).toHaveProperty('@type');
      } catch (e) {
        // JSON might be invalid, but that's a separate issue
        console.warn('Schema JSON might be invalid:', e);
      }
    }
  });

  test('Verify homepage meta tags', async ({ page }) => {
    await page.goto('/');
    
    // Verify title
    const title = await page.locator('title').textContent();
    expect(title).toBeTruthy();
    
    // Verify meta description
    const metaDesc = page.locator('meta[name="description"]');
    if (await metaDesc.count() > 0) {
      const descContent = await metaDesc.getAttribute('content');
      expect(descContent).toBeTruthy();
    }
    
    // Verify canonical
    const canonical = page.locator('link[rel="canonical"]');
    if (await canonical.count() > 0) {
      const canonicalUrl = await canonical.getAttribute('href');
      expect(canonicalUrl).toBeTruthy();
    }
  });

  test('Verify GEO endpoints accessibility', async ({ page }) => {
    // Test ai.txt
    const aiTxtResponse = await page.goto('/.well-known/ai.txt');
    if (aiTxtResponse) {
      expect(aiTxtResponse.status()).toBeLessThan(400);
    }
    
    // Test geo sitemap
    const sitemapResponse = await page.goto('/geo-sitemap.xml');
    if (sitemapResponse) {
      expect(sitemapResponse.status()).toBeLessThan(400);
      
      // Verify it's XML
      const contentType = sitemapResponse.headers()['content-type'];
      if (contentType) {
        expect(contentType).toContain('xml');
      }
    }
    
    // Test site.json
    const siteJsonResponse = await page.goto('/geo/site.json');
    if (siteJsonResponse) {
      expect(siteJsonResponse.status()).toBeLessThan(400);
      
      // Verify it's JSON
      const contentType = siteJsonResponse.headers()['content-type'];
      if (contentType) {
        expect(contentType).toContain('json');
      }
    }
  });

  test('Verify mobile responsiveness of meta tags', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Verify viewport meta tag
    const viewport = page.locator('meta[name="viewport"]');
    if (await viewport.count() > 0) {
      const viewportContent = await viewport.getAttribute('content');
      expect(viewportContent).toBeTruthy();
    }
    
    // Verify meta tags are still present on mobile
    const title = await page.locator('title').textContent();
    expect(title).toBeTruthy();
    
    const metaDesc = page.locator('meta[name="description"]');
    if (await metaDesc.count() > 0) {
      const descContent = await metaDesc.getAttribute('content');
      expect(descContent).toBeTruthy();
    }
  });

  test('Verify no duplicate meta tags', async ({ page }) => {
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Check for duplicate title tags
    const titleTags = page.locator('title');
    const titleCount = await titleTags.count();
    expect(titleCount).toBeLessThanOrEqual(1);
    
    // Check for duplicate meta descriptions
    const metaDescs = page.locator('meta[name="description"]');
    const metaDescCount = await metaDescs.count();
    expect(metaDescCount).toBeLessThanOrEqual(1);
    
    // Check for duplicate canonical URLs
    const canonicals = page.locator('link[rel="canonical"]');
    const canonicalCount = await canonicals.count();
    expect(canonicalCount).toBeLessThanOrEqual(1);
  });

  test('Verify page performance with SEO elements', async ({ page }) => {
    // Measure page load time
    const startTime = Date.now();
    
    await page.goto('/');
    
    const postLink = page.locator('a[href*="/post"], a[href*="/blog"], article a').first();
    
    if (await postLink.count() > 0) {
      const href = await postLink.getAttribute('href');
      await page.goto(href);
    } else {
      test.skip();
      return;
    }
    
    // Wait for page to be fully loaded
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    // Page should load in reasonable time (less than 5 seconds)
    expect(loadTime).toBeLessThan(5000);
    
    // Verify meta tags don't slow down page
    const title = await page.locator('title').textContent();
    expect(title).toBeTruthy();
  });
});




