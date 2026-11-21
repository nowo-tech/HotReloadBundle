import { test, expect } from '@playwright/test';

/** REQ-DEMO-013: the demo pages render on the UiKit shell without console errors. */
test.describe('Hot Reload demo', () => {
  test('home, live page and login render without a console error', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error') {
        errors.push(message.text());
      }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    for (const [path, heading] of [['/', 'FrankenPHP Hot Reload demo'], ['/live', 'Live page'], ['/login', 'Demo login']]) {
      const response = await page.goto(path);
      expect(response?.status(), path).toBe(200);
      await expect(page.getByTestId('demo-platform')).toBeVisible();
      await expect(page.getByRole('heading', { level: 1 })).toContainText(heading);
    }
    await page.waitForLoadState('networkidle');
    expect(errors).toEqual([]);
  });

  test('sign in with the demo user shows the user menu', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Username').fill('debugger');
    await page.getByLabel('Password').first().fill('debug');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByTestId('demo-platform')).toContainText('debugger');
  });
});
