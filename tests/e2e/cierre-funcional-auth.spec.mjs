import { test, expect } from '@playwright/test';

const mailpitBase = process.env.E2E_MAILPIT_URL || 'http://127.0.0.1:8025';
const email = process.env.E2E_AUTH_EMAIL;
const oldPassword = process.env.E2E_AUTH_OLD_PASSWORD;
const newPassword = process.env.E2E_AUTH_NEW_PASSWORD;

async function latestMail(request, recipient, subjectPattern, notBefore) {
  const deadline = Date.now() + 45_000;
  while (Date.now() < deadline) {
    const response = await request.get(`${mailpitBase}/api/v1/messages?limit=100`);
    expect(response.ok()).toBeTruthy();
    const messages = (await response.json()).messages || [];
    const match = messages.find((message) =>
      message.To?.some((to) => to.Address === recipient)
      && subjectPattern.test(message.Subject || '')
      && new Date(message.Created).getTime() >= notBefore - 5_000,
    );
    if (match) return match;
    await new Promise((resolve) => setTimeout(resolve, 750));
  }
  throw new Error(`No llegó a Mailpit el mensaje esperado para ${recipient}.`);
}

async function mailLink(request, messageId, pathPattern) {
  const response = await request.get(`${mailpitBase}/api/v1/message/${messageId}`);
  expect(response.ok()).toBeTruthy();
  const message = await response.json();
  const source = `${message.HTML || ''}\n${message.Text || ''}`.replaceAll('&amp;', '&');
  const urls = source.match(/https?:\/\/[^\s"'<>]+/g) || [];
  const link = urls.find((candidate) => pathPattern.test(candidate));
  if (!link) throw new Error('El correo no contiene el enlace funcional esperado.');
  return link;
}

async function login(page, password) {
  await page.goto('/login', { waitUntil: 'networkidle' });
  await page.locator('input[name=email]').fill(email);
  await page.locator('input[name=password]').fill(password);
  await page.getByRole('button', { name: /iniciar sesi.n|ingresar/i }).click();
}

test.describe.serial('Cierre funcional: autenticación completa', () => {
  test.describe.configure({ retries: 0 });

  test('AUTH-CLOSE-001 verificación de correo y recuperación de contraseña usan enlaces reales', async ({ page, request }, testInfo) => {
    expect(email).toMatch(/@labinvepn\.test$/);
    expect(oldPassword?.length).toBeGreaterThanOrEqual(8);
    expect(newPassword?.length).toBeGreaterThanOrEqual(8);

    const registrationStarted = Date.now();
    await page.goto('/register', { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: /Depositante/i }).click();
    await page.locator('input[name=first_name]').fill('Sol');
    await page.locator('input[name=last_name]').fill('Cierre Funcional');
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=cargo]').fill('Analista QA sintético');
    await page.locator('input[name=institucion]').fill('Institución sintética HubDigital');
    await page.locator('input[name=password]').fill(oldPassword);
    await page.locator('input[name=password_confirmation]').fill(oldPassword);
    await page.getByRole('button', { name: /Crear cuenta/i }).click();

    await expect(page).toHaveURL(/\/email\/verify/);
    await expect(page.locator('body')).toContainText(/pendiente de verificaci.n/i);
    await page.screenshot({ path: testInfo.outputPath('AUTH-CLOSE-001-pendiente.png'), fullPage: true });

    const verifyMail = await latestMail(request, email, /verifica.*correo/i, registrationStarted);
    const verifyUrl = await mailLink(request, verifyMail.ID, /\/email\/verify\//);

    const tampered = new URL(verifyUrl);
    tampered.searchParams.set('signature', `${tampered.searchParams.get('signature') || ''}x`);
    const invalidResponse = await page.goto(tampered.toString(), { waitUntil: 'domcontentloaded' });
    expect(invalidResponse?.status()).toBe(403);
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|Internal Server Error/i);

    const verifiedResponse = await page.goto(verifyUrl, { waitUntil: 'networkidle' });
    expect(verifiedResponse?.status()).toBeLessThan(400);
    await expect(page).toHaveURL(/\/dashboard|verified=1/);
    await expect(page.locator('body')).toContainText(/Portal del consultor|Mis dep.sitos|Dep.sitos/i);
    await page.screenshot({ path: testInfo.outputPath('AUTH-CLOSE-001-verificada.png'), fullPage: true });

    await page.locator('[data-test="sidebar-menu-button"]').click();
    await page.locator('[data-test="logout-button"]').click();
    await expect(page).toHaveURL(/\/$/);

    const resetStarted = Date.now();
    await page.goto('/forgot-password', { waitUntil: 'networkidle' });
    await page.locator('input[name=email]').fill(email);
    await page.locator('[data-test="email-password-reset-link-button"]').click();
    await expect(page.locator('body')).toContainText(/enlace|restablecimiento|correo/i);

    const resetMail = await latestMail(request, email, /reset your password|restablecer|contrase/i, resetStarted);
    const resetUrl = await mailLink(request, resetMail.ID, /\/reset-password\//);
    await page.goto(resetUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toContainText(/Restablecer contrase.a/i);
    await expect(page.locator('input[name=password]')).toBeVisible();
    await page.locator('input[name=password]').fill(newPassword);
    await page.locator('input[name=password_confirmation]').fill(newPassword);
    await page.locator('[data-test="reset-password-button"]').click();
    await expect(page).toHaveURL(/\/dashboard|\/login/);

    if (page.url().includes('/login')) await login(page, newPassword);
    await expect(page).toHaveURL(/\/dashboard/);
    await expect(page.locator('body')).toContainText(/Portal del consultor|Mis dep.sitos|Dep.sitos/i);
    await page.screenshot({ path: testInfo.outputPath('AUTH-CLOSE-001-clave-nueva.png'), fullPage: true });

    await page.context().clearCookies();
    await login(page, oldPassword);
    await expect(page).toHaveURL(/\/login/);
    await expect(page.locator('body')).toContainText(/credenciales|contrase.a|incorrect|no coinciden/i);

    await page.goto(resetUrl, { waitUntil: 'networkidle' });
    await page.locator('input[name=password]').fill(oldPassword);
    await page.locator('input[name=password_confirmation]').fill(oldPassword);
    await page.locator('[data-test="reset-password-button"]').click();
    await expect(page.locator('body')).toContainText(/token|inv.lido|expir|restablecimiento/i);
    await expect(page).toHaveURL(/reset-password/);
  });
});
