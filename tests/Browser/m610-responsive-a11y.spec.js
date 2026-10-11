import { test, expect } from '@playwright/test';

test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Requires isolated local HTTPS WordPress CI');
test.use({ baseURL: 'https://127.0.0.1:8443', ignoreHTTPSErrors: true });
const widths = [320, 390, 768, 1280];

function requireCi() {
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ADMIN_PASSWORD ||
      !process.env.UOP_M610_PAGE_ID || !process.env.UOP_M610_MEMBER_PAGE ||
      !process.env.UOP_M610_PORTAL_PAGE) {
    throw new Error('Responsive browser audits may only run on seeded disposable CI.');
  }
}

async function login(page, username) {
  requireCi();
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php\?/);
}

// Verify the actual UOP content, not the surrounding third-party WordPress theme.
// Intentional horizontal scroll is allowed ONLY within the named audit-table region.
async function noOffscreenUopControls(page, root, label) {
  const data = await root.evaluate(element => {
    const edge = globalThis.document.documentElement.clientWidth;
    const box = element.getBoundingClientRect();
    const controls = [...element.querySelectorAll('input,select,textarea,button,a')]
      .filter(el => {
        const rect = el.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) return false;
        return rect.left < -1 || rect.right > edge + 1;
      }).map(el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''));
    return { edge, left: box.left, right: box.right, controls };
  });
  expect(data.left, label + ' starts outside the viewport').toBeGreaterThanOrEqual(-1);
  expect(data.right, label + ' extends outside the viewport').toBeLessThanOrEqual(data.edge + 1);
  expect(data.controls, label + ' has unreachable controls').toEqual([]);
}

async function formNames(form) {
  const invalid = await form.evaluate(node => [...node.querySelectorAll('input,textarea,select')]
    .filter(control => control.type !== 'hidden' && control.getBoundingClientRect().height > 0)
    .filter(control => !control.labels?.length &&
      !control.getAttribute('aria-label') && !control.getAttribute('aria-labelledby'))
    .map(control => control.outerHTML.slice(0, 160)));
  expect(invalid, 'Every visible registration field needs an accessible name').toEqual([]);
}

test('public HTTPS registration has labelled fields, keyboard path and 320–1280px reflow', async ({ page }) => {
  requireCi();
  for (const width of widths) {
    await page.setViewportSize({ width, height: 850 });
    await page.goto('/?page_id=' + process.env.UOP_M610_PAGE_ID, { waitUntil: 'domcontentloaded' });
    const section = page.locator('.uop-m6-blocks--registration-form');
    const form = section.locator('[data-uop-registration-form][data-uop-guest="1"]');
    await expect(form).toBeVisible();
    await noOffscreenUopControls(page, section, 'public registration at ' + width + 'px');
    await formNames(form);
    const name = form.locator('[data-uop-field="name"]');
    const email = form.locator('[data-uop-field="contact"]');
    await name.focus();
    await expect(name).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(email).toBeFocused();
    const submit = form.getByRole('button', { name: 'Submit registration' });
    expect((await submit.boundingBox()).height).toBeGreaterThanOrEqual(44);
  }
});

test('authenticated portal keeps person selector, focus and touch actions usable at mobile/tablet/desktop widths', async ({ page }) => {
  requireCi();
  await login(page, 'uop_ci_adult');
  for (const width of widths) {
    await page.setViewportSize({ width, height: 850 });
    await page.goto('/?page_id=' + process.env.UOP_M610_PORTAL_PAGE, { waitUntil: 'domcontentloaded' });
    const section = page.locator('.uop-m6-blocks--portal');
    const selector = section.getByRole('combobox', { name: 'Person to manage' });
    await expect(selector).toBeVisible();
    await noOffscreenUopControls(page, section, 'participant portal at ' + width + 'px');
    const refresh = section.getByRole('button', { name: 'Refresh access' });
    expect((await refresh.boundingBox()).height).toBeGreaterThanOrEqual(44);
    await selector.focus();
    await page.keyboard.press('Tab');
    await expect(refresh).toBeFocused();
  }
});

test('admin record list and details reflow and keyboard selection moves focus to loaded details', async ({ page }) => {
  requireCi();
  await login(page, 'uop_test_admin');
  for (const width of widths) {
    await page.setViewportSize({ width, height: 850 });
    await page.goto('/wp-admin/admin.php?page=uop-registrations', { waitUntil: 'domcontentloaded' });
    const root = page.locator('.uop-m6-admin');
    const list = root.getByRole('region', { name: 'Record list' });
    const details = root.getByRole('region', { name: 'Record details' });
    const first = list.locator('.uop-m6-items button').first();
    await expect(first).toBeVisible();
    await noOffscreenUopControls(page, root, 'registration management at ' + width + 'px');
    expect((await first.boundingBox()).height).toBeGreaterThanOrEqual(44);
    await first.focus();
    await page.keyboard.press('Enter');
    await expect(details.locator('h2')).toBeFocused({ timeout: 12000 });
    await expect(first).toHaveAttribute('aria-pressed', 'true');
    await expect(details.locator('.uop-m6-detail')).toBeVisible();
  }
});

test('admin overview and audit table provide responsive navigation and keyboard-scrollable data', async ({ page }) => {
  requireCi();
  await login(page, 'uop_test_admin');
  for (const width of widths) {
    await page.setViewportSize({ width, height: 850 });
    await page.goto('/wp-admin/admin.php?page=uop-overview', { waitUntil: 'domcontentloaded' });
    const consoleRoot = page.locator('.uop-m6-console');
    await expect(consoleRoot.getByRole('navigation', { name: 'UOP Administration' })).toBeVisible();
    await noOffscreenUopControls(page, consoleRoot, 'admin overview at ' + width + 'px');
  }
  await page.setViewportSize({ width: 320, height: 850 });
  await page.goto('/wp-admin/admin.php?page=uop-audit', { waitUntil: 'domcontentloaded' });
  const audit = page.locator('.uop-m6-console__table');
  await expect(audit).toBeVisible();
  await expect(audit.locator('th[scope="col"]')).toHaveCount(5);
  await expect(audit).toHaveAttribute('tabindex', '0');
  const scrollable = await audit.evaluate(node => node.scrollWidth > node.clientWidth);
  expect(scrollable, 'audit table must scroll within its own region, never force whole-page sideways scrolling').toBeTruthy();
  await audit.focus();
  await expect(audit).toBeFocused();
});
