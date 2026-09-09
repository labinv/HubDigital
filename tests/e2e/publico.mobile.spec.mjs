import { test, expect } from '@playwright/test';
import { observeRuntime, expectHealthy, screenshot } from './helpers.mjs';

test.describe('Portal público móvil', () => {
  for (const [id, route, identity] of [
    ['PUB-M01', '/', /Laboratorio de Invertebrados|Hub Digital/i],
    ['PUB-M02', '/portal', /Catálogo del laboratorio de invertebrados/i],
    ['PUB-M03', '/depositos', /Tu solicitud, paso a paso/i],
  ]) {
    test(`${id} contenido sin desbordamiento horizontal`, async ({ page }, testInfo) => {
      const errors = observeRuntime(page);
      const response = await page.goto(route, { waitUntil: 'networkidle' });
      expect(response?.status()).toBe(200);
      await expectHealthy(page, errors, identity);
      const layout = await page.evaluate(() => ({ scrollWidth: document.documentElement.scrollWidth, clientWidth: document.documentElement.clientWidth }));
      expect(layout.scrollWidth).toBeLessThanOrEqual(layout.clientWidth + 2);
      await screenshot(page, testInfo, id);
    });
  }
});
