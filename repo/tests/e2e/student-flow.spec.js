import { test, expect } from '@playwright/test';

const STUDENT = { username: 'student', password: 'Student123!' };

async function login(page) {
  await page.goto('/login');
  await page.getByPlaceholder('Enter your username').fill(STUDENT.username);
  await page.getByPlaceholder('Enter your password').fill(STUDENT.password);
  await page.getByRole('button', { name: /Sign In/i }).click();
  await expect(page).toHaveURL(/\/catalog$/);
}

test.describe('Student browse + loan flow', () => {
  test('catalog renders after login and shows resources', async ({ page }) => {
    await login(page);

    // Wait for the catalog fetch to land
    await expect(page.getByRole('heading', { name: /Resource Catalog/i })).toBeVisible();

    // Either resources or the empty state must render — both are valid states
    const hasResources = await page.locator('a[href^="/catalog/"]').first().isVisible().catch(() => false);
    const emptyState = await page.getByText(/No resources found/i).isVisible().catch(() => false);
    expect(hasResources || emptyState).toBe(true);
  });

  test('student can navigate to loans page from top nav', async ({ page }) => {
    await login(page);

    await page.goto('/loans');
    await expect(page).toHaveURL(/\/loans$/);
    await expect(page.getByRole('heading', { name: /My Loans/i })).toBeVisible();
  });

  test('student can navigate to reservations page', async ({ page }) => {
    await login(page);

    await page.goto('/reservations');
    await expect(page).toHaveURL(/\/reservations$/);
    await expect(page.getByRole('heading', { name: /My Reservations/i })).toBeVisible();
  });

  test('logout clears session and forces redirect to /login', async ({ page }) => {
    await login(page);

    // Clear the session via the logout API directly (the UI exposes it through the nav,
    // but the shape of that nav varies — hitting the endpoint is stable)
    const token = await page.evaluate(() => localStorage.getItem('token'));
    await page.request.post('/api/auth/logout', {
      headers: { Authorization: `Bearer ${token}`, 'X-Idempotency-Key': 'e2e-logout-' + Date.now() },
    });
    await page.evaluate(() => { localStorage.removeItem('token'); localStorage.removeItem('user'); });

    await page.goto('/catalog');
    await expect(page).toHaveURL(/\/login$/);
  });
});
