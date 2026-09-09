import { test, expect } from '@playwright/test';
import { accounts, login, observeRuntime, expectHealthy } from './helpers.mjs';

test.describe.serial('Depósitos: borradores y reglas documentales', () => {
  test.describe.configure({ retries: 0 });
  test('DOC-001 donación crea borrador, salta origen y se recupera entre sesiones', async ({ page }) => {
    const errors = observeRuntime(page);
    await login(page, accounts.depositanteC);
    await page.goto('/prestamos/deposito/nueva', { waitUntil: 'networkidle' });
    const recovered = page.getByText(/Borrador pendiente recuperado/i);
    if (!await recovered.count()) {
      const donacion = page.getByRole('radio', { name: /Donación/i });
      await donacion.click();
      await expect(donacion).toHaveAttribute('aria-checked', 'true');
      const continuar = page.getByRole('button', { name: 'Continuar', exact: true });
      await expect(continuar).toBeEnabled();
      await continuar.click();
      await expect(page.getByRole('heading', { name: /Documentos oficiales/i })).toBeVisible();
      await page.reload({ waitUntil: 'networkidle' });
    }
    await expect(page.getByText(/Borrador pendiente recuperado/i)).toBeVisible();
    await expectHealthy(page, errors, /Nueva solicitud de depósito/i);
    await page.getByRole('button', { name: 'Descartar', exact: true }).click();
    await page.getByRole('button', { name: 'Descartar', exact: true }).last().click();
    await expect(page.getByText(/Borrador pendiente recuperado/i)).toHaveCount(0);
    await expect(page.getByRole('heading', { name: /Tipo de trámite/i })).toBeVisible();
  });

  test('DOC-002 depósito nacional en Pichincha exige documentos reales y rechaza archivo no PDF', async ({ page }) => {
    const errors = observeRuntime(page);
    await login(page, accounts.depositanteC);
    await page.goto('/prestamos/deposito/nueva', { waitUntil: 'networkidle' });
    if (await page.getByText(/1\/2 cargados/i).count()) {
      await page.getByRole('button', { name: 'Descartar', exact: true }).click();
      await page.getByRole('button', { name: 'Descartar', exact: true }).last().click();
      await expect(page.getByRole('heading', { name: /Tipo de trámite/i })).toBeVisible();
    }
    if (!await page.getByRole('heading', { name: /Documentos oficiales/i }).count()) {
      const deposito = page.getByRole('radio', { name: /^Depósito/i });
      await deposito.click();
      await expect(deposito).toHaveAttribute('aria-checked', 'true');
      await expect(page.getByRole('button', { name: 'Continuar', exact: true })).toBeEnabled();
      await page.getByRole('button', { name: 'Continuar', exact: true }).click();
      await page.getByRole('radio', { name: /Nacional \(Ecuador\)/i }).click();
      await page.getByRole('radio', { name: /Posee permisos del MAE/i }).click();
      await page.getByRole('radio', { name: /Dentro de Pichincha/i }).click();
      await page.getByRole('button', { name: /Guardar y continuar/i }).click();
    }
    await expect(page.locator('body')).toContainText(/autorización de recolección/i);
    await expect(page.locator('body')).toContainText(/permiso de movilización/i);
    const input = page.locator('input[type=file]').first();
    await input.setInputFiles(process.env.E2E_INVALID_PDF);
    await expect(page.locator('body')).toContainText(/no corresponde a un documento PDF válido/i);
    await expect(page.locator('body')).toContainText(/0\/2 cargados/i);
    expect(errors).toEqual([]);
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Descartar', exact: true }).click();
    await page.getByRole('button', { name: 'Descartar', exact: true }).last().click();
  });
});
