import { test, expect } from '@playwright/test';
import { login, observeRuntime, screenshot } from './helpers.mjs';

const fixtures = {
  correction: {
    id: process.env.E2E_CORRECTION_ID,
    number: process.env.E2E_CORRECTION_NUMBER,
  },
  reception: {
    id: process.env.E2E_RECEPTION_ID,
    number: process.env.E2E_RECEPTION_NUMBER,
    qr: process.env.E2E_RECEPTION_QR,
  },
};

const accounts = {
  depositor: { email: process.env.E2E_DEPOSITANTE_EMAIL, password: process.env.E2E_DEPOSITANTE_PASSWORD },
  curator: { email: process.env.E2E_CURADOR_EMAIL || 'test.curaduria@labinvepn.test', password: process.env.E2E_SHARED_PASSWORD },
  receiver: { email: process.env.E2E_RECEPTOR_EMAIL || 'test.recepcion@labinvepn.test', password: process.env.E2E_SHARED_PASSWORD },
};

const correctionComment = 'SOL cierre funcional: corregir localidad y volver a firmar la solicitud.';

async function logoutByCookies(page) {
  await page.context().clearCookies();
}

async function assertKeyboardFocus(page, locator) {
  await locator.scrollIntoViewIfNeeded();
  await locator.focus();
  await expect(locator).toBeFocused();
  const outline = await locator.evaluate((element) => {
    const style = getComputedStyle(element);
    return { outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth, boxShadow: style.boxShadow };
  });
  expect(outline.outlineStyle !== 'none' || outline.outlineWidth !== '0px' || outline.boxShadow !== 'none').toBeTruthy();
}

test.describe.serial('Cierre funcional: corrección y recepción recuperada', () => {
  test.describe.configure({ retries: 0 });

  test('DEP-CLOSE-001 corrige, invalida firma previa, vuelve a firmar y reenvía', async ({ page }, testInfo) => {
    expect(fixtures.correction.id).toBeTruthy();
    const errors = observeRuntime(page);

    await login(page, accounts.curator);
    await page.goto(`/prestamos/curador/deposito/${fixtures.correction.id}`, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(fixtures.correction.number);
    const reject = page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).first();
    if (await reject.count()) {
      await assertKeyboardFocus(page, reject);
      await reject.press('Enter');
      await page.getByLabel('Tipo de rechazo').selectOption('Subsanable');
      await page.getByLabel('Motivo del rechazo').fill(correctionComment);
      await page.getByLabel('Motivo del rechazo').press('Tab');
      await page.waitForTimeout(600);
      await page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).last().click();
      await expect(page.getByLabel('Motivo del rechazo')).toHaveCount(0);
      await expect(page).toHaveURL(/\/prestamos\/curador\/depositos/);
    } else {
      await expect(page.locator('body')).toContainText(/Requiere Correcci.n/i);
    }

    await logoutByCookies(page);
    await login(page, accounts.depositor);
    await page.goto(`/prestamos/deposito/${fixtures.correction.id}`, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(/Requiere Correcci.n/i);
    await expect(page.locator('body')).toContainText(correctionComment);
    const correctLink = page.getByRole('link', { name: /Corregir/i });
    await assertKeyboardFocus(page, correctLink);
    await correctLink.press('Enter');
    await page.waitForURL(new RegExp(`/prestamos/deposito/${fixtures.correction.id}/corregir$`));
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.getByText('Nueva solicitud de depósito', { exact: true }).last()).toBeVisible();
    await expect(page.locator('body')).toContainText(/Paso 5 de 6/i);
    await expect(page.locator('body')).toContainText(/Matriz|Darwin Core/i);
    await expect(page.locator('body')).toContainText(/MEPN-QA-001|QA-TERRA-20260908-1100/i);
    await page.getByRole('button', { name: /Revisar y enviar/i }).click();
    await expect(page.locator('body')).toContainText(/Revisar, firmar y enviar/i);
    await expect(page.locator('body')).not.toContainText(/Firmada y validada/i);
    await expect(page.getByRole('button', { name: /Firmar con Firmador HubDigital/i })).toBeVisible();
    await screenshot(page, testInfo, 'DEP-CLOSE-001-firma-invalidada', true);

    const declaration = page.getByRole('checkbox', { name: /Declaro bajo juramento/i });
    if (await declaration.getAttribute('aria-checked') !== 'true') await declaration.click();
    await page.locator('input[type=file]').setInputFiles(process.env.E2E_CERT_PATH);
    await page.locator('input[type=password]').last().fill(process.env.E2E_CERT_PASSWORD);
    const signResponse = page.waitForResponse((response) => response.url().includes(`/depositos/solicitud/${fixtures.correction.id}/firmar`));
    await page.getByRole('button', { name: /Firmar con Firmador HubDigital/i }).click();
    expect((await signResponse).status()).toBe(200);
    await page.waitForTimeout(1_200);
    await expect(page.locator('body')).toContainText(/Firmada y validada/i, { timeout: 25_000 });

    const send = page.getByRole('button', { name: /^Enviar solicitud$/i });
    const declarationAfterSigning = page.getByRole('checkbox', { name: /Declaro bajo juramento/i });
    if (await declarationAfterSigning.getAttribute('aria-checked') !== 'true') await declarationAfterSigning.click();
    await expect(send).toBeEnabled();
    await send.click();
    await expect(page.locator('body')).toContainText(/Pendiente de Revisi.n|enviada|curadur/i);
    await screenshot(page, testInfo, 'DEP-CLOSE-001-reenviada', true);

    await logoutByCookies(page);
    await login(page, accounts.curator);
    await page.goto('/prestamos/curador/depositos', { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(fixtures.correction.number);
    expect(errors).toEqual([]);
  });

  test('REC-CLOSE-001 suspende, reanuda y confirma una sola recepción bajo latencia', async ({ page }, testInfo) => {
    expect(fixtures.reception.id).toBeTruthy();
    const errors = observeRuntime(page);
    await login(page, accounts.receiver);

    const qrResponse = await page.goto(`/prestamos/lote/${fixtures.reception.qr}`, { waitUntil: 'networkidle' });
    if (qrResponse?.status() === 429) {
      const retryAfter = Number(qrResponse.headers()['retry-after'] || 1);
      await page.waitForTimeout(Math.min(Math.max(retryAfter, 1), 30) * 1_000);
      await page.goto(`/prestamos/receptor/deposito/${fixtures.reception.id}/recepcion`, { waitUntil: 'networkidle' });
    }
    await expect(page.locator('body')).toContainText(fixtures.reception.number);

    const start = page.getByRole('button', { name: /Iniciar constataci.n f.sica/i });
    if (await start.count()) await start.click();
    await expect(page.locator('body')).toContainText(/Lista de verificaci.n de recepci.n/i);
    await page.getByRole('button', { name: /Suspender y devolver/i }).click();
    await page.getByLabel(/Fallo de integridad/i).selectOption({ index: 1 });
    await page.getByRole('button', { name: /Suspender recepci.n/i }).click();
    await expect(page.locator('body')).toContainText(/Recepci.n suspendida/i);
    await expect(page.locator('body')).toContainText(/Correcci.n en sitio/i);
    await screenshot(page, testInfo, 'REC-CLOSE-001-suspendida', true);

    await page.setViewportSize({ width: 390, height: 844 });
    const retry = page.getByRole('button', { name: /Reintentar recepci.n/i });
    await assertKeyboardFocus(page, retry);
    await screenshot(page, testInfo, 'REC-CLOSE-001-suspendida-movil', true);
    await retry.press('Enter');
    await expect(page.locator('body')).toContainText(/Lista de verificaci.n de recepci.n/i);

    const switches = page.locator('[role=switch]:visible');
    expect(await switches.count()).toBe(4);
    for (let index = 0; index < 4; index += 1) {
      if (await switches.nth(index).getAttribute('aria-checked') !== 'true') await switches.nth(index).click();
    }

    let delayApproval = true;
    let approvalRequests = 0;
    await page.route('**/livewire-*/update', async (route) => {
      if (delayApproval) {
        approvalRequests += 1;
        await new Promise((resolve) => setTimeout(resolve, 1_800));
      }
      await route.continue();
    });
    const approve = page.getByRole('button', { name: /Aprobar recepci.n/i });
    const box = await approve.boundingBox();
    const approving = approve.click();
    await expect(approve).toBeDisabled();
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
    await approving;
    delayApproval = false;
    await expect(page.locator('body')).toContainText(/Lote recibido y constatado/i);
    expect(approvalRequests).toBe(1);
    await screenshot(page, testInfo, 'REC-CLOSE-001-recibida-movil', true);

    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(/Verificado F.sicamente|Lote recibido y constatado/i);
    await expect(page.getByRole('button', { name: /Aprobar recepci.n/i })).toHaveCount(0);
    expect(errors).toEqual([]);
  });
});
