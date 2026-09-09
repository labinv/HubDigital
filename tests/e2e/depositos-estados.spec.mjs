import { test, expect } from '@playwright/test';
import { accounts, historical, login, observeRuntime, expectHealthy, screenshot } from './helpers.mjs';

test.describe('Depósitos: estados y controles por rol', () => {
  test('DEP-EST-001 expediente cerrado mantiene correspondencia integral', async ({ page }) => {
    await login(page, accounts.depositante);
    await page.goto(`/prestamos/deposito/${historical.closedId}`, { waitUntil: 'networkidle' });
    const body = await page.locator('body').innerText();
    for (const marker of [historical.closedNumber, 'LOTE-MKXAPN']) expect(body).toContain(marker);
    expect(body).toMatch(/Verificado Físicamente/i);
    expect(body).toMatch(/acta.*firmad|firmad.*acta/i);
  });

  test('CUR-EST-001 bandeja curatorial identifica el acta pendiente adicional', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await login(page, accounts.curador);
    await page.goto('/prestamos/curador/depositos?vista=actas', { waitUntil: 'networkidle' });
    await expectHealthy(page, errors, /Depósitos|Actas|Curadur/i);
    await expect(page.locator('body')).toContainText(historical.receivedNumber);
    await expect(page.locator('body')).toContainText(/Acta pendiente|generar y firmar/i);
    await screenshot(page, testInfo, 'CUR-EST-001-acta-pendiente', true);
  });

  test('REC-EST-001 QR válido resuelve el expediente y evita duplicar recepción', async ({ page }) => {
    const errors = observeRuntime(page);
    await login(page, accounts.receptor);
    const response = await page.goto(`/prestamos/lote/${historical.receivedQr}`, { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expectHealthy(page, errors, new RegExp(historical.receivedNumber));
    await expect(page.locator('body')).toContainText(/Verificado Físicamente|recibido y constatado/i);
    await expect(page.getByRole('button', { name: /Aprobar recepci.n/i })).toHaveCount(0);
  });

  test('REC-EST-002 QR inexistente tiene rechazo controlado', async ({ page }) => {
    await login(page, accounts.receptor);
    const response = await page.goto('/prestamos/lote/LOTE-ZZZZZZ', { waitUntil: 'domcontentloaded' });
    expect([404, 422]).toContain(response?.status());
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|Internal Server Error|Whoops/i);
  });

  test('REC-EST-003 Depositante no puede abrir recepción por UUID', async ({ page }) => {
    await login(page, accounts.depositante);
    const response = await page.goto(`/prestamos/receptor/deposito/${historical.receivedId}/recepcion`, { waitUntil: 'domcontentloaded' });
    expect([403, 404]).toContain(response?.status());
  });

  test('CUR-EST-002 dashboard y CSV reflejan datos conocidos', async ({ page }) => {
    const errors = observeRuntime(page);
    await login(page, accounts.curador);
    await page.goto('/dashboard', { waitUntil: 'networkidle' });
    await expectHealthy(page, errors, /Recepción y depósitos|Cola de acción curatorial/i);
    await expect(page.locator('body')).toContainText(historical.receivedNumber);
    const period = page.locator('select').first();
    for (const value of ['6', '12', '24']) {
      await period.selectOption(value);
      await expect(period).toHaveValue(value);
      await expect(page.locator('body')).not.toContainText(/SQLSTATE|Internal Server Error/i);
    }
    const report = page.getByRole('button', { name: /Descargar reporte/i }).or(page.getByRole('link', { name: /Descargar reporte/i })).first();
    const downloadPromise = page.waitForEvent('download');
    await report.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/\.csv$/i);
    const stream = await download.createReadStream();
    let csv = '';
    for await (const chunk of stream) csv += chunk.toString('utf8');
    expect(csv).toMatch(/solicitud|depósito|estado|fecha/i);
  });
});
