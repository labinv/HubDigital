import { test, expect } from '@playwright/test';

const mailpitBase = process.env.E2E_MAILPIT_URL || 'http://127.0.0.1:8025';
const email = process.env.E2E_DEPOSITANTE_EMAIL;
const password = process.env.E2E_DEPOSITANTE_PASSWORD;

test('PRE-CLOSE-001 restablece por el flujo real la cuenta sintética exclusiva', async ({ page, request }) => {
  expect(email).toMatch(/@labinvepn\.test$/);
  expect(password?.length).toBeGreaterThanOrEqual(8);

  const started = Date.now();
  await page.goto('/forgot-password', { waitUntil: 'networkidle' });
  await page.locator('input[name=email]').fill(email);
  await page.locator('[data-test="email-password-reset-link-button"]').click();
  await expect(page.locator('body')).toContainText(/enlace|restablecimiento|correo/i);

  const deadline = Date.now() + 45_000;
  let message;
  while (Date.now() < deadline && !message) {
    const response = await request.get(`${mailpitBase}/api/v1/messages?limit=100`);
    expect(response.ok()).toBeTruthy();
    const messages = (await response.json()).messages || [];
    message = messages.find((candidate) =>
      candidate.To?.some((to) => to.Address === email)
      && /reset your password|restablecer|contrase/i.test(candidate.Subject || '')
      && new Date(candidate.Created).getTime() >= started - 5_000,
    );
    if (!message) await page.waitForTimeout(750);
  }
  expect(message, 'Debe llegar el correo de recuperación a Mailpit').toBeTruthy();

  const detailResponse = await request.get(`${mailpitBase}/api/v1/message/${message.ID}`);
  expect(detailResponse.ok()).toBeTruthy();
  const detail = await detailResponse.json();
  const source = `${detail.HTML || ''}\n${detail.Text || ''}`.replaceAll('&amp;', '&');
  const resetUrl = (source.match(/https?:\/\/[^\s"'<>]+/g) || [])
    .find((candidate) => /\/reset-password\//.test(candidate));
  expect(resetUrl, 'El correo debe contener el enlace real').toBeTruthy();

  await page.goto(resetUrl, { waitUntil: 'networkidle' });
  await page.locator('input[name=password]').fill(password);
  await page.locator('input[name=password_confirmation]').fill(password);
  await page.locator('[data-test="reset-password-button"]').click();
  await expect(page).toHaveURL(/dashboard|login/);
});
