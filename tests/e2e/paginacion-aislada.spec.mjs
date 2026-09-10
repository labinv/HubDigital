import { test, expect } from '@playwright/test';
import { observeRuntime, screenshot } from './helpers.mjs';

test('PAG-CLOSE-001 pagina y filtra 26 registros en PostgreSQL aislado', async ({ page }, testInfo) => {
  const errors = observeRuntime(page);
  const loginResponse = await page.goto('/login', { waitUntil: 'domcontentloaded' });
  expect(loginResponse?.status()).toBe(200);
  await page.locator('input[type=email]').fill('qa.sol.paginacion@labinvepn.test');
  await page.locator('input[type=password]').fill(process.env.E2E_ISOLATED_PASSWORD);
  await page.getByRole('button', { name: /iniciar sesi.n|ingresar|acceder/i }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));

  await page.goto('/divulgacion', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /Cat.logo divulgado/i })).toBeVisible();
  const rows = page.locator('tbody tr');
  await expect(rows).toHaveCount(25);
  await expect(page.locator('body')).toContainText('SOL-PAG-001');
  await expect(page.locator('body')).not.toContainText('SOL-PAG-026');
  await screenshot(page, testInfo, 'PAG-CLOSE-001-pagina-1', true);

  const next = page.getByRole('button', { name: /Next|Siguiente/i }).first();
  await expect(next).toBeVisible();
  await next.focus();
  await expect(next).toBeFocused();
  await next.press('Enter');
  await expect(page).toHaveURL(/page=2/);
  await expect(rows).toHaveCount(1);
  await expect(page.locator('body')).toContainText('SOL-PAG-026');
  await expect(page.locator('body')).not.toContainText('SOL-PAG-001');
  await screenshot(page, testInfo, 'PAG-CLOSE-001-pagina-2', true);

  const catalogFilter = page.getByLabel(/N.. de cat.logo/i);
  await catalogFilter.fill('SOL-PAG-013');
  await expect(page).not.toHaveURL(/page=2/);
  await expect(rows).toHaveCount(1);
  await expect(rows.first()).toContainText('SOL-PAG-013');
  await expect(page.getByRole('button', { name: /Next|Siguiente/i })).toHaveCount(0);
  await screenshot(page, testInfo, 'PAG-CLOSE-001-filtro', true);

  expect(errors).toEqual([]);
});
