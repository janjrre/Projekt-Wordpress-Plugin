import { test, expect } from '@playwright/test';

test('M6 administration reflows at 320px and supports keyboard focus', async ({ page }) => {
  test.setTimeout(90000);
  const loginPage = await page.request.get('/wp-login.php');
  const login = await page.request.post('/wp-login.php', {
    form: {
      log: process.env.WP_ADMIN_USER || 'uop_test_admin',
      pwd: process.env.WP_ADMIN_PASSWORD,
      testcookie: '1',
      redirect_to: new URL('/wp-admin/', loginPage.url()).href,
    },
  });
  expect(login.ok()).toBeTruthy();
  expect(login.url()).toMatch(/\/wp-admin\//);

  await page.goto('/wp-admin/plugins.php');
  const plugin = page.locator('tr[data-slug="uop-core"]');
  const activate = plugin.getByRole('link', { name: 'Activate UOP Core', exact: true });
  if (await activate.isVisible()) await activate.click();

  await page.setViewportSize({ width: 320, height: 720 });
  for (const section of ['uop-people', 'uop-registrations']) {
    await page.goto('/wp-admin/admin.php?page=' + section);
    const shell = page.locator('#uop-m6-admin-root .uop-m6-shell');
    await expect(shell).toBeVisible({ timeout: 15000 });
    const refresh = page.getByRole('button', { name: 'Refresh list', exact: true });
    await expect(refresh).toBeVisible();
    await refresh.focus();
    await expect(refresh).toBeFocused();

    const layout = await shell.evaluate(element => {
      const style = getComputedStyle(element);
      const wrapper = element.closest('.uop-m6-admin');
      const panels = [...element.querySelectorAll('.uop-m6-panel')];
      return {
        columns: style.gridTemplateColumns.split(' ').length,
        overflow: wrapper.scrollWidth - wrapper.clientWidth,
        panels: panels.map(panel => panel.scrollWidth - panel.clientWidth),
        buttonHeight: parseFloat(getComputedStyle(element.querySelector('.uop-m6-toolbar .button')).minHeight),
      };
    });
    expect(layout.columns).toBe(1);
    expect(layout.overflow).toBeLessThanOrEqual(2);
    expect(layout.panels.every(size => size <= 2)).toBeTruthy();
    expect(layout.buttonHeight).toBeGreaterThanOrEqual(32);
    await expect(shell.locator('section[aria-label="Record list"]')).toHaveAttribute('aria-busy', /^(true|false)$/);
    await expect(shell.locator('section[aria-label="Record details"]')).toHaveAttribute('aria-busy', /^(true|false)$/);
    await expect(page.locator('body')).not.toContainText(/Fatal error:|Warning:|Failed checks:/);
  }
});
