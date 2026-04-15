import { test, expect } from '@playwright/test';

const ACCOUNTS = {
  student: { username: 'student', password: 'Student123!' },
  admin: { username: 'admin', password: 'Admin123!' },
  teacher: { username: 'teacher', password: 'Teacher123!' },
};

test.describe('Auth flow', () => {
  test('student can log in and reach the catalog', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: /Sign in/i })).toBeVisible();

    await page.getByPlaceholder('Enter your username').fill(ACCOUNTS.student.username);
    await page.getByPlaceholder('Enter your password').fill(ACCOUNTS.student.password);
    await page.getByRole('button', { name: /Sign In/i }).click();

    await expect(page).toHaveURL(/\/catalog$/);
    await expect(page.getByRole('heading', { name: /Resource Catalog/i })).toBeVisible();
  });

  test('invalid credentials show the server error banner', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('Enter your username').fill(ACCOUNTS.student.username);
    await page.getByPlaceholder('Enter your password').fill('wrong-password');
    await page.getByRole('button', { name: /Sign In/i }).click();

    await expect(page.getByText(/Invalid credentials/i)).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
  });

  test('unauthenticated visitor is redirected to /login', async ({ page }) => {
    await page.goto('/catalog');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('admin is routed to admin-capable views after login', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('Enter your username').fill(ACCOUNTS.admin.username);
    await page.getByPlaceholder('Enter your password').fill(ACCOUNTS.admin.password);
    await page.getByRole('button', { name: /Sign In/i }).click();

    await expect(page).toHaveURL(/\/catalog$/);

    // /admin should load now (admin has access)
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByText(/Scope Management/i)).toBeVisible();
  });

  test('student cannot reach /admin — router guard redirects to /catalog', async ({ page }) => {
    await page.goto('/login');
    await page.getByPlaceholder('Enter your username').fill(ACCOUNTS.student.username);
    await page.getByPlaceholder('Enter your password').fill(ACCOUNTS.student.password);
    await page.getByRole('button', { name: /Sign In/i }).click();
    await expect(page).toHaveURL(/\/catalog$/);

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/catalog$/);
  });
});
