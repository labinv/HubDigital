import { test, expect } from '@playwright/test';
import { accounts, branchFixtures, login, observeRuntime, screenshot } from './helpers.mjs';

async function openCurator(page, fixture) {
  await login(page, accounts.curador);
  const response = await page.goto(`/prestamos/curador/deposito/${fixture.id}`, { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('body')).toContainText(fixture.numero);
}

test.describe.serial('Depósitos: ramas curatoriales y recepción', () => {
  test.describe.configure({ retries: 0 });
  test.beforeAll(() => expect(Object.keys(branchFixtures).length).toBeGreaterThanOrEqual(5));

  test('CUR-RAM-001 rechazo subsanable persiste y habilita corrección al dueño', async ({ page }, testInfo) => {
    const fixture = branchFixtures.correccion;
    await openCurator(page, fixture);
    await page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).first().click();
    await page.getByLabel('Tipo de rechazo').selectOption('Subsanable');
    await page.getByLabel('Motivo del rechazo').fill('Corrección sintética QA: completar respaldo documental.');
    await page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).last().click();
    await expect(page).toHaveURL(/\/prestamos\/curador\/depositos/);
    await page.context().clearCookies();
    await login(page, accounts.depositanteB);
    await page.goto(`/prestamos/deposito/${fixture.id}`, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(/Requiere Corrección|completar respaldo documental/i);
    await expect(page.getByRole('link', { name: /Corregir/i })).toBeVisible();
    await screenshot(page, testInfo, 'CUR-RAM-001-correccion');
  });

  test('CUR-RAM-002 rechazo definitivo cierra sin acción de corregir', async ({ page }, testInfo) => {
    const fixture = branchFixtures.rechazo;
    await openCurator(page, fixture);
    await page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).first().click();
    await page.getByLabel('Tipo de rechazo').selectOption('Definitivo');
    await page.getByLabel('Motivo del rechazo').fill('Rechazo definitivo sintético QA por documentación incompatible.');
    await page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).last().click();
    await expect(page).toHaveURL(/\/prestamos\/curador\/depositos/);
    await page.context().clearCookies();
    await login(page, accounts.depositanteB);
    await page.goto(`/prestamos/deposito/${fixture.id}`, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(/Rechazo Permanente|documentación incompatible/i);
    await expect(page.getByRole('link', { name: /Corregir/i })).toHaveCount(0);
    await screenshot(page, testInfo, 'CUR-RAM-002-rechazo');
  });

  test('CUR-RAM-003 donación aprobada genera QR y acta de transferencia', async ({ page }, testInfo) => {
    const fixture = branchFixtures.donacion;
    const errors = observeRuntime(page);
    await openCurator(page, fixture);
    await page.getByRole('button', { name: /Aprobar donación/i }).click();
    await page.getByRole('button', { name: /Sí, aprobar/i }).click();
    await expect(page.locator('body')).toContainText(/Aprobada Documentalmente/i);
    await expect(page.locator('body')).toContainText(/Acta de Transferencia|Código QR/i);
    expect(errors).toEqual([]);
    await screenshot(page, testInfo, 'CUR-RAM-003-donacion-aprobada', true);
  });

  test('REC-RAM-001 checklist parcial exige confirmación y acepta con observaciones', async ({ page }, testInfo) => {
    const fixture = branchFixtures.recepcion_observada;
    const errors = observeRuntime(page);
    await login(page, accounts.receptor);
    await page.goto(`/prestamos/receptor/deposito/${fixture.id}/recepcion`, { waitUntil: 'networkidle' });
    const iniciar = page.getByRole('button', { name: /Iniciar constatación física/i });
    if (await iniciar.count()) await iniciar.click();
    await expect(page.getByRole('heading', { name: /Lista de verificación de recepción/i })).toBeVisible();
    const switches = page.locator('[role=switch]:visible');
    expect(await switches.count()).toBeGreaterThanOrEqual(4);
    for (let index = 0; index < 2; index += 1) {
      if (await switches.nth(index).getAttribute('aria-checked') !== 'true') await switches.nth(index).click();
    }
    await page.getByRole('button', { name: /Aprobar recepción/i }).click();
    await expect(page.getByText('Aceptar con observaciones', { exact: true }).first()).toBeVisible();
    await page.getByLabel(/Comentario adicional/i).fill('Observación sintética QA: embalaje y rotulado pendientes.');
    await page.getByRole('button', { name: /Aceptar con observaciones/i }).last().click();
    await expect(page.locator('body')).toContainText(/Lote recibido y constatado/i);
    await expect(page.locator('body')).toContainText(/embalaje y rotulado pendientes/i);
    expect(errors).toEqual([]);
    await screenshot(page, testInfo, 'REC-RAM-001-observada');
  });

  test('REC-RAM-002 suspensión registra causa y permite reintento', async ({ page }, testInfo) => {
    const fixture = branchFixtures.recepcion_rechazada;
    const errors = observeRuntime(page);
    await login(page, accounts.receptor);
    await page.goto(`/prestamos/receptor/deposito/${fixture.id}/recepcion`, { waitUntil: 'networkidle' });
    const iniciar = page.getByRole('button', { name: /Iniciar constatación física/i });
    if (await iniciar.count()) await iniciar.click();
    await expect(page.getByRole('heading', { name: /Lista de verificación de recepción/i })).toBeVisible();
    await page.getByRole('button', { name: /Suspender y devolver/i }).click();
    await page.getByLabel(/Fallo de integridad/i).selectOption({ index: 1 });
    await page.getByRole('button', { name: /Suspender recepción/i }).click();
    await expect(page.locator('body')).toContainText(/Recepción suspendida/i);
    await expect(page.getByRole('button', { name: /Reintentar recepción/i })).toBeVisible();
    expect(errors).toEqual([]);
    await screenshot(page, testInfo, 'REC-RAM-002-suspendida');
  });
});
