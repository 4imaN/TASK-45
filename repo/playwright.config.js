import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests exercise the running Vue SPA against the real Laravel backend.
 * Prerequisites: the stack is up at https://localhost (docker compose up) with the
 * static bootstrap accounts seeded. See run_e2e.sh.
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_BASE_URL || 'https://localhost',
    ignoreHTTPSErrors: true, // self-signed cert is documented behavior
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
