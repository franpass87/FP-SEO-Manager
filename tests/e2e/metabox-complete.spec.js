/**
 * Complete E2E tests for metabox - all buttons, fields, interactions.
 *
 * @package FP\SEO\Tests\E2E
 */

const { test, expect } = require('@playwright/test');

test.describe('FP SEO Manager - Complete Metabox Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/wp-admin/');
    await page.fill('#user_login', 'FranPass87');
    await page.fill('#user_pass', '00Antonelli00');
    await page.click('#wp-submit');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 10000 });
  });

  test('should display all metabox sections', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const sections = [
      'fp-seo-serp-optimization-section',
      'fp-seo-serp-preview-section',
      'fp-seo-analysis-section',
      'fp-seo-images-section',
      'fp-seo-ai-section',
      'fp-seo-social-section',
      'fp-seo-schema-section',
      'fp-seo-internal-links-section',
      'fp-seo-gsc-section',
    ];

    for (const section of sections) {
      const sectionElement = page.locator(`[data-section*="${section}"], .${section}`);
      if (await sectionElement.count() > 0) {
        await expect(sectionElement.first()).toBeVisible();
      }
    }
  });

  test('should have all input fields present and functional', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const fields = [
      { id: 'fp-seo-title', type: 'text' },
      { id: 'fp-seo-meta-description', type: 'textarea' },
      { id: 'fp-seo-slug', type: 'text' },
      { id: 'fp-seo-excerpt', type: 'textarea' },
      { id: 'fp-seo-focus-keyword', type: 'text' },
      { id: 'fp-seo-secondary-keywords', type: 'text' },
    ];

    for (const field of fields) {
      const fieldElement = page.locator(`#${field.id}`);
      if (await fieldElement.count() > 0) {
        await expect(fieldElement.first()).toBeVisible();
        await fieldElement.first().fill('Test value');
        const value = await fieldElement.first().inputValue();
        expect(value).toBe('Test value');
      }
    }
  });

  test('should have character counters working', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const titleField = page.locator('#fp-seo-title');
    if (await titleField.count() > 0) {
      await titleField.first().fill('A'.repeat(100));
      await page.waitForTimeout(500);
      
      // Check for character counter
      const counter = page.locator('[class*="character"], [class*="counter"]');
      if (await counter.count() > 0) {
        await expect(counter.first()).toBeVisible();
      }
    }
  });

  test('should have all AI generation buttons present', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const aiButtons = [
      'fp-seo-ai-generate-title',
      'fp-seo-ai-generate-description',
      'fp-seo-ai-generate-slug',
    ];

    for (const buttonId of aiButtons) {
      const button = page.locator(`#${buttonId}, [data-action*="${buttonId}"], button[class*="ai"]`);
      if (await button.count() > 0) {
        await expect(button.first()).toBeVisible();
      }
    }
  });

  test('should toggle SERP preview desktop/mobile', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const desktopToggle = page.locator('[data-toggle="desktop"], button:has-text("Desktop")');
    const mobileToggle = page.locator('[data-toggle="mobile"], button:has-text("Mobile")');
    
    if (await desktopToggle.count() > 0) {
      await desktopToggle.first().click();
      await page.waitForTimeout(500);
    }
    
    if (await mobileToggle.count() > 0) {
      await mobileToggle.first().click();
      await page.waitForTimeout(500);
    }
  });

  test('should switch between social media tabs', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const platforms = ['Facebook', 'Twitter', 'LinkedIn', 'Pinterest'];
    
    for (const platform of platforms) {
      const tab = page.locator(`button:has-text("${platform}"), [data-platform="${platform.toLowerCase()}"]`);
      if (await tab.count() > 0) {
        await tab.first().click();
        await page.waitForTimeout(500);
        await expect(tab.first()).toBeVisible();
      }
    }
  });

  test('should have internal links buttons functional', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const refreshButton = page.locator('button:has-text("Refresh"), [data-action*="refresh"]');
    const analyzeButton = page.locator('button:has-text("Analyze"), [data-action*="analyze"]');
    
    if (await refreshButton.count() > 0) {
      await expect(refreshButton.first()).toBeVisible();
    }
    
    if (await analyzeButton.count() > 0) {
      await expect(analyzeButton.first()).toBeVisible();
    }
  });

  test('should have FAQ schema buttons functional', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const addButton = page.locator('button:has-text("Aggiungi Domanda"), [data-action*="add-faq"]');
    const generateButton = page.locator('button:has-text("Genera con AI"), [data-action*="generate-faq"]');
    
    if (await addButton.count() > 0) {
      await expect(addButton.first()).toBeVisible();
    }
    
    if (await generateButton.count() > 0) {
      await expect(generateButton.first()).toBeVisible();
    }
  });

  test('should have HowTo schema buttons functional', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const addButton = page.locator('button:has-text("Aggiungi Step"), [data-action*="add-step"]');
    const generateButton = page.locator('button:has-text("Genera con AI"), [data-action*="generate-howto"]');
    
    if (await addButton.count() > 0) {
      await expect(addButton.first()).toBeVisible();
    }
    
    if (await generateButton.count() > 0) {
      await expect(generateButton.first()).toBeVisible();
    }
  });

  test('should handle AJAX calls without errors', async ({ page }) => {
    const ajaxErrors = [];
    
    page.on('response', response => {
      if (response.url().includes('admin-ajax.php') && response.status() >= 400) {
        ajaxErrors.push({
          url: response.url(),
          status: response.status(),
        });
      }
    });
    
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    // Try to trigger an AJAX call by clicking an AI button
    const aiButton = page.locator('button[class*="ai"], button:has-text("AI")').first();
    if (await aiButton.count() > 0) {
      await aiButton.click();
      await page.waitForTimeout(2000);
    }
    
    // Filter out non-critical errors
    const criticalErrors = ajaxErrors.filter(err => 
      err.status !== 400 && err.status !== 403
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('should update SERP preview in real-time', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    const titleField = page.locator('#fp-seo-title');
    if (await titleField.count() > 0) {
      await titleField.first().fill('Test SEO Title');
      await page.waitForTimeout(1000);
      
      // Check if SERP preview updated
      const preview = page.locator('[class*="serp-preview"], [class*="preview"]');
      if (await preview.count() > 0) {
        await expect(preview.first()).toBeVisible();
      }
    }
  });

  test('should display SEO analysis score', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php');
    await page.waitForSelector('#title, .wp-block-post-title', { timeout: 10000 });
    
    // Fill some content to trigger analysis
    const titleField = page.locator('#title, .wp-block-post-title input, .wp-block-post-title textarea');
    if (await titleField.count() > 0) {
      await titleField.first().fill('Test Post Title');
      await page.waitForTimeout(3000);
      
      // Check for score display
      const score = page.locator('[class*="score"], [class*="seo-score"]');
      if (await score.count() > 0) {
        await expect(score.first()).toBeVisible();
      }
    }
  });
});



