import { test, expect } from '@playwright/test';
import { accounts, historical, login, observeRuntime, expectHealthy, screenshot } from './helpers.mjs';

test.describe('Autenticación y portal del depositante', () => {
  test('CON-001 registro público limita roles y valida campos', async ({ page }) => {
    await page.goto('/register', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /Crear cuenta/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Solicitante/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Depositante/i })).toBeVisible();
    await expect(page.locator('input[name=rol]')).toHaveValue(/PRESTAMISTA|DEPOSITANTE/);
    await expect(page.locator('input[name=rol]')).not.toHaveValue(/CURADOR|RECEPTOR|ADMIN/i);
    await page.getByRole('button', { name: /Crear cuenta/i }).click();
    await expect(page.locator('body')).toContainText(/campo.*obligatorio|required|nombre/i);
    await expect(page).toHaveURL(/\/register/);
  });

  test('CON-002 login inválido rechaza sin crear sesión', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'networkidle' });
    await page.locator('input[type=email]').fill(accounts.depositante.email);
    await page.locator('input[type=password]').fill('Incorrecta-QA-2026!');
    await page.getByRole('button', { name: /iniciar sesi.n|ingresar/i }).click();
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/credenciales|contraseña|incorrect|no coinciden/i);
  });

  test('CON-003 recuperación de contraseña confirma solicitud sin filtrar cuenta', async ({ page }) => {
    await page.goto('/forgot-password', { waitUntil: 'networkidle' });
    await expect(page.locator('input[type=email]')).toHaveCount(1);
    await page.locator('input[type=email]').fill(accounts.depositante.email);
    await page.locator('[data-test="email-password-reset-link-button"]').click();
    await expect(page.locator('body')).toContainText(/enlace|restablecimiento|enviado|correo/i);
  });

  test('CON-004 login, dashboard, perfil trasladado y logout', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await login(page, accounts.depositante);
    await page.goto('/dashboard', { waitUntil: 'networkidle' });
    await expectHealthy(page, errors, /Portal del consultor|Mis depósitos|Depósitos/i);
    await page.goto('/settings/profile', { waitUntil: 'networkidle' });
    await expect(page.locator('input[name=first_name]')).toHaveValue(/Consultora/i);
    await expect(page.locator('input[name=last_name]')).toHaveValue(/MEPN Prueba/i);
    await expect(page.locator('input[name=cargo]')).not.toHaveValue('');
    await expect(page.locator('input[name=institucion]')).not.toHaveValue('');
    await screenshot(page, testInfo, 'CON-004-perfil');
    await page.locator('[data-test="sidebar-menu-button"]').click();
    await page.locator('[data-test="logout-button"]').click();
    await expect(page).toHaveURL(/\/$/);
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/login/);
  });

  test('CON-005 rutas privadas redirigen sin sesión', async ({ page }) => {
    for (const route of ['/dashboard', '/depositos/mis-solicitudes', `/prestamos/deposito/${historical.closedId}`]) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });
      await expect(page).toHaveURL(/\/login/);
    }
  });

  test('CON-006 el Curador no entra al formulario del depositante', async ({ page }) => {
    await login(page, accounts.curador);
    const response = await page.goto('/prestamos/deposito/nueva', { waitUntil: 'domcontentloaded' });
    expect([403, 404]).toContain(response?.status());
  });

  test('CON-007 listado, filtros, historial y detalle del expediente propio', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await login(page, accounts.depositante);
    await page.goto('/prestamos/mis-depositos', { waitUntil: 'networkidle' });
    await expect(page.getByRole('heading', { name: /Mis depósitos/i })).toBeVisible();
    await expect(page.locator('body')).toContainText(historical.closedNumber);
    const selects = page.locator('select');
    expect(await selects.count()).toBeGreaterThanOrEqual(2);
    await selects.nth(0).selectOption({ label: 'Donación' });
    await page.waitForTimeout(800);
    await expect(page.locator('body')).toContainText(/Sin resultados|Donación|Limpiar/i);
    await selects.nth(0).selectOption('');
    await page.waitForTimeout(800);
    await page.goto(`/prestamos/deposito/${historical.closedId}`, { waitUntil: 'networkidle' });
    await expectHealthy(page, errors, new RegExp(historical.closedNumber));
    await expect(page.locator('body')).toContainText(/Verificado Físicamente|Acta|LOTE-MKXAPN/i);
    await screenshot(page, testInfo, 'CON-007-detalle-cerrado', true);
  });

  test('CON-008 segundo depositante no puede leer expediente ajeno', async ({ page }) => {
    if (!accounts.depositanteB.email) throw new Error('Falta E2E_DEPOSITANTE_B_EMAIL; el fixture de aislamiento es obligatorio.');
    await login(page, accounts.depositanteB);
    const response = await page.goto(`/prestamos/deposito/${historical.closedId}`, { waitUntil: 'domcontentloaded' });
    expect([403, 404]).toContain(response?.status());
    await expect(page.locator('body')).not.toContainText(/LOTE-MKXAPN|acta firmada/i);
  });

  test('CON-009 documento firmado propio es recuperable y privado', async ({ page }) => {
    await login(page, accounts.depositante);
    await page.goto(`/prestamos/deposito/${historical.closedId}`, { waitUntil: 'networkidle' });
    const link = page.locator('a[href*="acta-recepcion.pdf"],a[href*="pdf-firmado"],a[href*="descargar-pdf"]').first();
    await expect(link).toBeVisible();
    const href = await link.getAttribute('href');
    const response = await page.request.get(href);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toMatch(/application\/pdf/);
    expect((await response.body()).subarray(0, 4).toString()).toBe('%PDF');
  });
});
