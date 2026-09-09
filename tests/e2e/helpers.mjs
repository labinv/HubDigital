import { expect } from '@playwright/test';
import fs from 'node:fs';

export const accounts = {
  depositante: { email: process.env.E2E_DEPOSITANTE_EMAIL || 'test.depositante@labinvepn.test', password: process.env.E2E_SHARED_PASSWORD },
  depositanteB: { email: process.env.E2E_DEPOSITANTE_B_EMAIL, password: process.env.E2E_DEPOSITANTE_B_PASSWORD || process.env.E2E_SHARED_PASSWORD },
  depositanteC: { email: process.env.E2E_DEPOSITANTE_C_EMAIL, password: process.env.E2E_DEPOSITANTE_C_PASSWORD || process.env.E2E_SHARED_PASSWORD },
  curador: { email: process.env.E2E_CURADOR_EMAIL || 'test.curaduria@labinvepn.test', password: process.env.E2E_SHARED_PASSWORD },
  receptor: { email: process.env.E2E_RECEPTOR_EMAIL || 'test.recepcion@labinvepn.test', password: process.env.E2E_SHARED_PASSWORD },
};

export const historical = {
  closedNumber: process.env.E2E_CLOSED_NUMBER || 'MEPN-INV-DEP-00002',
  closedId: process.env.E2E_CLOSED_ID || 'e740f825-5539-41f6-8060-82b1b89dafdb',
  receivedNumber: process.env.E2E_RECEIVED_NUMBER || 'MEPN-INV-DEP-00003',
  receivedId: process.env.E2E_RECEIVED_ID || 'fecec178-5f2f-4400-bc1c-a4e4c26fa944',
  receivedQr: process.env.E2E_RECEIVED_QR || 'LOTE-UCQZ8A',
};

export const branchFixtures = process.env.E2E_FIXTURES_FILE
  ? JSON.parse(fs.readFileSync(process.env.E2E_FIXTURES_FILE, 'utf8')).fixtures
  : {};

export function requireCredential(account, label) {
  if (!account.email || !account.password) throw new Error(`Falta la credencial E2E de ${label}.`);
}

export async function login(page, account) {
  requireCredential(account, account.email || 'cuenta');
  const response = await page.goto('/login', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  await expect(page.locator('input[type=email]')).toHaveCount(1);
  await expect(page.locator('input[type=password]')).toHaveCount(1);
  await page.locator('input[type=email]').fill(account.email);
  await page.locator('input[type=password]').fill(account.password);
  await page.getByRole('button', { name: /iniciar sesi.n|ingresar|acceder/i }).click();
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/\/login(?:\?|$)/);
}

export function observeRuntime(page) {
  const errors = [];
  page.on('pageerror', error => errors.push({ type: 'pageerror', message: error.message }));
  page.on('console', message => {
    if (message.type() === 'error') errors.push({ type: 'console', message: message.text() });
  });
  page.on('response', response => {
    if (response.status() >= 500) errors.push({ type: 'http', status: response.status(), url: response.url() });
  });
  return errors;
}

export async function expectHealthy(page, errors, identity) {
  await expect(page.locator('body')).toContainText(identity);
  await expect(page.locator('body')).not.toContainText(/SQLSTATE|Internal Server Error|Whoops|Error 1033/i);
  expect(errors, JSON.stringify(errors, null, 2)).toEqual([]);
}

export async function screenshot(page, testInfo, name, fullPage = false) {
  const file = testInfo.outputPath(`${name}.png`);
  await page.screenshot({ path: file, fullPage });
  await testInfo.attach(name, { path: file, contentType: 'image/png' });
}
