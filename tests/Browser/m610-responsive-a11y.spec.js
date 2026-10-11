import { test, expect } from '@playwright/test';

test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Requires isolated local HTTPS WordPress CI');
test.use({ baseURL: 'https://127.0.0.1:8443', ignoreHTTPSErrors: true });
const widths = [320, 390, 768, 1280];

/**
 * An overloaded disposable PHP/HTTPS CI proxy may reset one browser request.
 * Retry only transient transport errors, never server/application failures.
 */
async function visit(page, target, options = {}) {
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      return await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000, ...options });
    } catch (error) {
      const expected = new URL(target, page.url());
      // WordPress may perform a second same-target redirect while the CI
      // proxy is still processing the first response. Accept only after the
      // expected target has actually reached DOMContentLoaded; all caller
      // assertions still inspect the real HTML and current permissions.
      if (String(error).includes('is interrupted by another navigation to "' + expected.href + '"')) {
        try {
          await page.waitForLoadState('domcontentloaded', { timeout: 12000 });
          if (page.url() === expected.href) return null;
        } catch { /* Retry a still-incomplete navigation. */ }
      }
      if (!(/net::ERR_(?:TOO_MANY_RETRIES|CONNECTION_RESET|EMPTY_RESPONSE)/.test(String(error)) ||
        String(error).includes('chrome-error://chromewebdata/') ||
        String(error).includes('is interrupted by another navigation to "' + expected.href + '"')) || attempt === 2) {
        throw error;
      }
    }
  }
}

/**
 * DOMContentLoaded does not guarantee stylesheets completed on a slow runner.
 * Never measure accessible target sizes from an unstyled WordPress page.
 */
async function waitForUopStyles(page) {
  await expect.poll(() => page.evaluate(() => {
    const sheets = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
      .filter(link => link.href.includes('/wp-content/plugins/uop-core/assets/'));
    return sheets.length > 0 && sheets.every(link => Boolean(link.sheet));
  }), { timeout: 20000 }).toBeTruthy();
}


function requireCi() {
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ADMIN_PASSWORD ||
      !process.env.UOP_M610_PAGE_ID || !process.env.UOP_M610_MEMBER_PAGE ||
      !process.env.UOP_M610_PORTAL_PAGE) {
    throw new Error('Responsive browser audits may only run on seeded disposable CI.');
  }
}

async function login(page, username) {
  requireCi();
  await visit(page, '/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
  await page.locator('#wp-submit').click();
  // A bare negative check for 'wp-login.php?' passed *before* the login
  // navigation even started, racing subsequent admin page.goto() commands.
  // Wait for the completed real WordPress admin landing instead.
  await page.waitForURL(url => url.pathname.startsWith('/wp-admin/'), {
    waitUntil: 'domcontentloaded', timeout: 20000,
  });
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

// Verify the actual UOP content, not the surrounding third-party WordPress theme.
// Intentional horizontal scroll is allowed ONLY within the named audit-table region.
async function noOffscreenUopControls(page, root, label) {
  await waitForUopStyles(page);
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
    await visit(page, '/?page_id=' + process.env.UOP_M610_PAGE_ID, { waitUntil: 'domcontentloaded' });
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
    await visit(page, '/?page_id=' + process.env.UOP_M610_PORTAL_PAGE, { waitUntil: 'domcontentloaded' });
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
    await visit(page, '/wp-admin/admin.php?page=uop-registrations', { waitUntil: 'domcontentloaded' });
    const root = page.locator('.uop-m6-admin');
    const list = root.getByRole('region', { name: 'Record list' });
    const details = root.getByRole('region', { name: 'Record details' });
    const first = list.locator('.uop-m6-items button').first();
    await expect(first).toBeVisible();
    await noOffscreenUopControls(page, root, 'registration management at ' + width + 'px');
    const itemSize = await first.evaluate(node => ({
      height: node.getBoundingClientRect().height,
      minHeight: globalThis.getComputedStyle(node).minHeight,
      cssFile: [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
        .filter(link => link.href.includes('m6-admin.css')).map(link => link.href),
    }));
    expect(itemSize.height, JSON.stringify(itemSize)).toBeGreaterThanOrEqual(44);
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
    await visit(page, '/wp-admin/admin.php?page=uop-overview', { waitUntil: 'domcontentloaded' });
    const consoleRoot = page.locator('.uop-m6-console');
    await expect(consoleRoot.getByRole('navigation', { name: 'UOP Administration' })).toBeVisible();
    await noOffscreenUopControls(page, consoleRoot, 'admin overview at ' + width + 'px');
  }
  await page.setViewportSize({ width: 320, height: 850 });
  await visit(page, '/wp-admin/admin.php?page=uop-audit', { waitUntil: 'domcontentloaded' });
  const audit = page.locator('.uop-m6-console__table');
  await expect(audit).toBeVisible();
  await expect(audit.locator('th[scope="col"]')).toHaveCount(5);
  await expect(audit).toHaveAttribute('tabindex', '0');
  const scrollable = await audit.evaluate(node => node.scrollWidth > node.clientWidth);
  expect(scrollable, 'audit table must scroll within its own region, never force whole-page sideways scrolling').toBeTruthy();
  await audit.focus();
  await expect(audit).toBeFocused();
});

test('people editor and form builder reflow without clipped controls at 320–1280px', async ({ page }) => {
  requireCi();
  await login(page, 'uop_test_admin');
  for (const width of widths) {
    await page.setViewportSize({ width, height: 850 });
    await visit(page, '/wp-admin/admin.php?page=uop-people', { waitUntil: 'domcontentloaded' });
    const people = page.locator('.uop-m6-admin');
    const list = people.getByRole('region', { name: 'Record list' });
    const first = list.locator('.uop-m6-items button').first();
    await expect(first).toBeVisible();
    await noOffscreenUopControls(page, people, 'people editor at ' + width + 'px');
    await first.focus();
    await page.keyboard.press('Enter');
    await expect(people.getByRole('region', { name: 'Record details' }).locator('h2')).toBeFocused({ timeout: 12000 });

    await visit(page, '/wp-admin/admin.php?page=uop-forms', { waitUntil: 'domcontentloaded' });
    const builder = page.locator('.uop-m3');
    const key = page.getByLabel('Form key');
    await expect(key).toBeVisible();
    await noOffscreenUopControls(page, builder, 'form builder at ' + width + 'px');
    await key.focus();
    await expect(key).toBeFocused();
    const add = builder.getByRole('button', { name: 'Create draft' });
    // DOMContentLoaded only confirms that a stylesheet link exists, not
    // that its CSS has loaded. Measure touch targets after the actual UOP
    // stylesheet is parsed so slow CI runners cannot report a false 40px.
    await expect.poll(() => page.evaluate(() => {
      const styles = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
        .filter(link => link.href.includes('m3-form-builder.css'));
      return styles.length > 0 && styles.every(link => link.sheet !== null);
    }), { timeout: 10000 }).toBe(true);
    const createSize = await add.evaluate(node => ({
      height: node.getBoundingClientRect().height,
      minHeight: globalThis.getComputedStyle(node).minHeight,
      cssFile: [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
        .filter(link => link.href.includes('m3-form-builder.css')).map(link => link.href),
    }));
    expect(createSize.height, JSON.stringify(createSize)).toBeGreaterThanOrEqual(44);
  }
});

test('operations and consent editor keep wide tables in keyboard-scrollable regions on narrow screens', async ({ page }) => {
  requireCi();
  await login(page, 'uop_test_admin');
  for (const width of [320, 390, 768]) {
    await page.setViewportSize({ width, height: 850 });
    await visit(page, '/wp-admin/admin.php?page=uop-operations', { waitUntil: 'domcontentloaded' });
    const operations = page.locator('.uop-m6-console');
    await expect(operations.getByRole('navigation', { name: 'Operations sections' })).toBeVisible();
    const regions = operations.locator('.uop-m6-console__table');
    await expect(regions).toHaveCount(3);
    for (const region of await regions.all()) {
      await expect(region).toHaveAttribute('tabindex', '0');
      await expect(region.locator('th[scope="col"]').first()).toBeVisible();
      await expect(region).toHaveCSS('overflow-x', 'auto');
      const rectangle = await region.boundingBox();
      expect(rectangle.x).toBeGreaterThanOrEqual(-1);
      expect(rectangle.x + rectangle.width).toBeLessThanOrEqual(width + 1);
    }
    const privacy = operations.getByRole('textbox', { name: 'Verified request email' });
    await expect(privacy).toBeVisible();
    await noOffscreenUopControls(page, operations, 'operations at ' + width + 'px');

    await visit(page, '/wp-admin/admin.php?page=uop-consents', { waitUntil: 'domcontentloaded' });
    const consent = page.locator('.uop-m6-console');
    await expect(consent.getByRole('heading', { name: 'Consent documents' })).toBeVisible();
    await noOffscreenUopControls(page, consent, 'consent editor at ' + width + 'px');
    await expect(consent.getByRole('textbox', { name: /Title/i })).toBeVisible();
  }
});
