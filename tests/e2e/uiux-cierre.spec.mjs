import { test, expect } from '@playwright/test';
import { login, observeRuntime, screenshot } from './helpers.mjs';

const accounts = {
  depositor: {
    email: process.env.E2E_DEPOSITANTE_EMAIL || 'test.depositante@labinvepn.test',
    password: process.env.E2E_DEPOSITANTE_PASSWORD || process.env.E2E_SHARED_PASSWORD,
  },
  curator: {
    email: process.env.E2E_CURADOR_EMAIL || 'test.curaduria@labinvepn.test',
    password: process.env.E2E_SHARED_PASSWORD,
  },
  receiver: {
    email: process.env.E2E_RECEPTOR_EMAIL || 'test.recepcion@labinvepn.test',
    password: process.env.E2E_SHARED_PASSWORD,
  },
};

const correctionId = process.env.E2E_CORRECTION_ID || 'cdd5d6c4-d71f-44ae-ae0e-56a0abf2b4bc';
const correctionNumber = process.env.E2E_CORRECTION_NUMBER || 'MEPN-INV-DEP-00018';
const receptionQr = process.env.E2E_RECEPTION_QR || 'LOTE-FYQCIF';

async function expectVisibleFocus(page, locator) {
  await locator.scrollIntoViewIfNeeded();
  await locator.focus();
  await expect(locator).toBeFocused();
  const state = await locator.evaluate((element) => {
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    return {
      visible: rect.width > 0 && rect.height > 0
        && rect.right > 0 && rect.bottom > 0
        && rect.left < innerWidth && rect.top < innerHeight,
      indicator: style.outlineStyle !== 'none'
        || style.outlineWidth !== '0px'
        || style.boxShadow !== 'none',
    };
  });
  expect(state).toEqual({ visible: true, indicator: true });
}

async function expectNoHorizontalOverflow(page) {
  const layout = await page.evaluate(() => ({
    client: document.documentElement.clientWidth,
    scroll: document.documentElement.scrollWidth,
  }));
  expect(layout.scroll).toBeLessThanOrEqual(layout.client + 2);
}

test.describe.serial('Cierre UI/UX focal de depósitos', () => {
  test.describe.configure({ retries: 0 });

  test('UIUX-CLOSE-001 excluye el sidebar móvil cerrado del teclado y conserva su apertura', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, accounts.depositor);
    await page.goto('/prestamos/mis-depositos', { waitUntil: 'networkidle' });

    const sidebar = page.locator('[data-flux-sidebar-on-mobile]');
    const toggle = page.getByRole('button', { name: 'Toggle sidebar' }).first();
    await expect(sidebar).toHaveAttribute('data-flux-sidebar-collapsed-mobile', '');
    await expect(sidebar).toHaveAttribute('inert', '');

    await page.locator('body').focus();
    await page.keyboard.press('Tab');
    await expect(toggle).toBeFocused();
    await expectVisibleFocus(page, toggle);
    await toggle.press('Enter');
    await expect(sidebar).not.toHaveAttribute('data-flux-sidebar-collapsed-mobile', '');
    await expect(sidebar).not.toHaveAttribute('inert', '');
    await expect(page.getByRole('link', { name: 'Mis depósitos' })).toBeInViewport();
    await screenshot(page, testInfo, 'UIUX-CLOSE-001-sidebar-movil-abierto', true);

    await page.keyboard.press('Escape');
    await expectNoHorizontalOverflow(page);
    expect(errors).toEqual([]);
  });

  test('UIUX-CLOSE-002 valida etiquetas, filtros, vacío, foco y reflujo del depositante', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await login(page, accounts.depositor);
    await page.goto('/prestamos/mis-depositos', { waitUntil: 'networkidle' });

    const type = page.getByLabel('Tipo', { exact: true });
    const status = page.getByLabel('Estado', { exact: true });
    const from = page.getByLabel('Desde', { exact: true });
    const until = page.getByLabel('Hasta', { exact: true });
    await expect(type).toBeVisible();
    await expect(status).toBeVisible();
    await expect(from).toBeVisible();
    await expect(until).toBeVisible();
    await expectVisibleFocus(page, status);

    await status.selectOption({ label: 'Pendiente de Revisión' });
    await expect(page.locator('body')).toContainText(correctionNumber);
    await from.fill('2099-01-01');
    await until.fill('2099-12-31');
    await expect(page.getByRole('heading', { name: 'Sin resultados' })).toBeVisible();
    const clear = page.getByRole('button', { name: /Limpiar filtros/i });
    await expectVisibleFocus(page, clear);
    await clear.press('Enter');
    await expect(page.getByRole('heading', { name: 'Sin resultados' })).toHaveCount(0);

    for (const viewport of [
      { width: 1440, height: 900, name: '1440' },
      { width: 768, height: 1024, name: '768' },
      { width: 390, height: 844, name: '390' },
      { width: 320, height: 740, name: '320' },
    ]) {
      await page.setViewportSize(viewport);
      await expectNoHorizontalOverflow(page);
      await screenshot(page, testInfo, `UIUX-CLOSE-002-bandeja-${viewport.name}`, true);
    }

    await page.evaluate(() => { document.body.style.zoom = '200%'; });
    await expectNoHorizontalOverflow(page);
    await screenshot(page, testInfo, 'UIUX-CLOSE-002-bandeja-zoom-css-200', true);
    expect(errors).toEqual([]);
  });

  test('UIUX-CLOSE-003 activa por teclado la revisión sin confirmar cambios', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    await page.setViewportSize({ width: 768, height: 1024 });
    await login(page, accounts.curator);
    await page.goto(`/prestamos/curador/deposito/${correctionId}`, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(correctionNumber);

    const reject = page.getByRole('button', { name: 'Rechazar solicitud', exact: true }).first();
    await expect(reject).toBeEnabled();
    await expectVisibleFocus(page, reject);
    await reject.press('Enter');
    await expect(page.getByLabel('Tipo de rechazo')).toBeVisible();
    await expect(page.getByLabel('Motivo del rechazo')).toBeVisible();
    await screenshot(page, testInfo, 'UIUX-CLOSE-003-modal-curatorial-teclado', true);
    await page.keyboard.press('Escape');
    await expect(page.getByLabel('Tipo de rechazo')).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
    expect(errors).toEqual([]);
  });

  test('UIUX-CLOSE-004 abre QR móvil una vez en sesión reutilizada sin 429', async ({ page }, testInfo) => {
    const errors = observeRuntime(page);
    const responses = [];
    page.on('response', response => {
      if (response.status() >= 400) {
        responses.push({ status: response.status(), url: response.url(), retryAfter: response.headers()['retry-after'] || null });
      }
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, accounts.receiver);
    const response = await page.goto(`/prestamos/lote/${receptionQr}`, { waitUntil: 'networkidle' });
    expect(response?.status()).toBe(200);
    await expect(page.locator('body')).toContainText(/MEPN-INV-DEP-00019|Verificado F.sicamente/i);
    await expectNoHorizontalOverflow(page);
    await screenshot(page, testInfo, 'UIUX-CLOSE-004-qr-movil', true);
    expect(responses).toEqual([]);
    expect(errors).toEqual([]);
  });
});
