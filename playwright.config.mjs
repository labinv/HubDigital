import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

const outputRoot = path.resolve(process.env.E2E_OUTPUT_DIR || 'test-results/e2e');

export default defineConfig({
  testDir: './tests/e2e',
  grep: process.env.E2E_GREP ? new RegExp(process.env.E2E_GREP) : undefined,
  fullyParallel: false,
  forbidOnly: true,
  retries: 1,
  workers: 1,
  maxFailures: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  outputDir: path.join(outputRoot, 'artifacts'),
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(outputRoot, 'resultados.json') }],
    ['junit', { outputFile: path.join(outputRoot, 'resultados.xml') }],
    ['html', { outputFolder: path.join(outputRoot, 'html'), open: 'never' }],
  ],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'https://dev.labinvepn.org',
    headless: true,
    locale: 'es-EC',
    actionTimeout: 15_000,
    navigationTimeout: 45_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chrome-escritorio',
      testIgnore: /\.mobile\.spec\.mjs$/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'chrome-movil',
      testMatch: /\.mobile\.spec\.mjs$/,
      use: { ...devices['Pixel 7'] },
    },
  ],
});
