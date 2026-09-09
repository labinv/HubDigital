import { test, expect } from '@playwright/test';
import { observeRuntime, expectHealthy, screenshot } from './helpers.mjs';

test.describe('Portal público', () => {
  test('PUB-001 inicio ofrece navegación y accesos reales', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    const response = await page.goto('/', { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(/HubDigital|Laboratorio de Invertebrados/i);
    await expect(page.getByRole('link', { name: /Catálogo/i }).first()).toHaveAttribute('href', /\/portal$/);
    await expect(page.getByRole('link', { name: /Depósitos/i }).first()).toHaveAttribute('href', /\/depositos$/);
    await expect(page.getByRole('link', { name: /iniciar sesi.n/i }).first()).toHaveAttribute('href', /login/);
    await expect(page.locator('img[alt]').first()).toBeVisible();
    await expectHealthy(page, errors, /Laboratorio de Invertebrados|Hub Digital/i);
    await screenshot(page, testInfo, 'PUB-001-inicio');
  });

  test('PUB-002 el catálogo real es /portal y sus enlaces no usan /catalogo', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    const response = await page.goto('/portal', { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: /Catálogo del laboratorio de invertebrados/i })).toBeVisible();
    await expect(page.getByText('Búsqueda', { exact: true })).toBeVisible();
    await expect(page.locator('a[href="/catalogo"]')).toHaveCount(0);
    await expectHealthy(page, errors, /Catálogo del laboratorio de invertebrados/i);
    await screenshot(page, testInfo, 'PUB-002-catalogo', true);
  });

  test('PUB-003 búsqueda imposible muestra estado vacío y se puede limpiar', async ({ page }) => {
    const errors = observeRuntime(page);
    await page.goto('/portal', { waitUntil: 'networkidle' });
    const input = page.getByPlaceholder('Ej. EPN-001, EPN-002');
    await input.fill('QA-INEXISTENTE-999999');
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/Sin taxones visibles|0 registros|Sin resultados|No se encontraron/i);
    await page.getByRole('button', { name: /Limpiar filtros/i }).last().click();
    await expect(input).toHaveValue('');
    await expectHealthy(page, errors, /Catálogo/i);
  });

  test('PUB-004 depósitos explica modalidades, privacidad y controles de acceso', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    const response = await page.goto('/depositos', { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: /Depósito de colecciones biológicas/i })).toBeVisible();
    await expect(page.locator('body')).toContainText(/depósito temporal o la donación/i);
    await expect(page.locator('body')).toContainText(/documentos y datos sensibles solo serán visibles/i);
    await expect(page.getByRole('link', { name: /Crear cuenta e iniciar/i }).first()).toHaveAttribute('href', /register/);
    await expect(page.getByRole('link', { name: /Ya tengo una cuenta/i })).toHaveAttribute('href', /login/);
    await expectHealthy(page, errors, /Tu solicitud, paso a paso/i);
    await screenshot(page, testInfo, 'PUB-004-depositos');
  });

  test('PUB-005 documentos privados y detalle autenticado no se exponen anónimamente', async ({ page }) => {
    for (const route of [
      '/prestamos/deposito/e740f825-5539-41f6-8060-82b1b89dafdb/documento/0',
      '/prestamos/deposito/e740f825-5539-41f6-8060-82b1b89dafdb/acta-recepcion.pdf',
      '/prestamos/deposito/e740f825-5539-41f6-8060-82b1b89dafdb',
    ]) {
      const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
      const protegido = [401, 403, 404].includes(response?.status())
        || (response?.status() === 200 && /\/login(?:\?|$)/.test(page.url()));
      expect(protegido).toBe(true);
      expect(page.url()).not.toMatch(/\.pdf(?:\?|$)/);
    }
  });

  test('PUB-006 navegación directa, recarga y atrás/adelante conservan identidad', async ({ page }) => {
    await page.goto('/depositos', { waitUntil: 'networkidle' });
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /Depósito de colecciones biológicas/i })).toBeVisible();
    await page.goto('/portal', { waitUntil: 'networkidle' });
    await page.goBack({ waitUntil: 'networkidle' });
    await expect(page).toHaveURL(/\/depositos$/);
    await page.goForward({ waitUntil: 'networkidle' });
    await expect(page).toHaveURL(/\/portal$/);
  });

  test('PUB-007 manifest, service worker, offline y reconexión', async ({ page, context }) => {
    const manifest = await page.request.get('/manifest.webmanifest');
    expect(manifest.status()).toBe(200);
    expect(manifest.headers()['content-type']).toMatch(/manifest|json/);
    await page.goto('/depositos', { waitUntil: 'networkidle' });
    expect(await page.evaluate(async () => Boolean(await navigator.serviceWorker.ready))).toBe(true);
    await context.setOffline(true);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(/Depósito de colecciones biológicas|Sin conexión/i);
    await context.setOffline(false);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /Depósito de colecciones biológicas/i })).toBeVisible();
  });

  test('PUB-008 teclado, foco y etiquetas principales', async ({ page }) => {
    await page.goto('/depositos', { waitUntil: 'networkidle' });
    await page.keyboard.press('Tab');
    const focused = page.locator(':focus');
    await expect(focused).toBeVisible();
    await expect(focused).toHaveAttribute('href', /.+/);
    expect(await page.locator('h1').count()).toBe(1);
    expect(await page.locator('img:not([alt])').count()).toBe(0);
  });
});
