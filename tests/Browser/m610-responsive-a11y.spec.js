import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

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
      const response = await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 30000, ...options });
      if (new URL(target, page.url()).pathname.startsWith('/wp-admin/')) await waitForAdminStyles(page);
      return response;
    } catch (error) {
      const expected = new URL(target, page.url());
      // WordPress may perform a second same-target redirect while the CI
      // proxy is still processing the first response. Accept only after the
      // expected target has actually reached DOMContentLoaded; all caller
      // assertions still inspect the real HTML and current permissions.
      if (String(error).includes('is interrupted by another navigation to "' + expected.href + '"')) {
        try {
          await page.waitForLoadState('domcontentloaded', { timeout: 12000 });
          if (page.url() === expected.href) {
            if (expected.pathname.startsWith('/wp-admin/')) await waitForAdminStyles(page);
            return null;
          }
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
 * A link.sheet object can exist before the CSS response is fully parsed.
 * Wait for same-origin WordPress/UOP admin styles to expose CSSOM rules before
 * measuring touch targets or clicking beneath the collapsing admin sidebar.
 */
async function waitForAdminStyles(page) {
  await expect.poll(() => page.evaluate(() => {
    const local = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
      .filter(link => {
        const url = new URL(link.href, globalThis.location.href);
        return url.origin === globalThis.location.origin &&
          (/\/wp-(?:admin|includes)\//.test(url.pathname) ||
            url.pathname.includes('/wp-content/plugins/uop-core/'));
      });
    return local.length > 0 && local.every(link => {
      try {
        const rules = link.sheet?.cssRules;
        return Boolean(rules) && (rules.length > 0 || !link.href.includes('m3-form-builder.css'));
      } catch { return false; }
    });
  }), { timeout: 20000 }).toBeTruthy();
}

async function waitForUopStyles(page) {
  await expect.poll(() => page.evaluate(() => {
    const sheets = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
      .filter(link => link.href.includes('/wp-content/plugins/uop-core/assets/'));
    return sheets.length > 0 && sheets.every(link => {
      try { return Boolean(link.sheet) && link.sheet.cssRules.length > 0; }
      catch { return false; }
    });
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
  // The loopback-only PHP/HTTPS proxy can occasionally leave an otherwise
  // valid login POST without a navigation. Retry only that transient transport
  // state, never a WordPress credential rejection or an unexpected redirect.
  for (let attempt = 0; attempt < 2; attempt++) {
    await visit(page, '/wp-login.php');
    await page.locator('#user_login').fill(username);
    await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
    try {
      await page.locator('#wp-submit').click();
      await page.waitForURL(url => url.pathname.startsWith('/wp-admin/'), {
        waitUntil: 'domcontentloaded', timeout: 35000,
      });
      // The actual authenticated WordPress UI must appear; a changed URL alone
      // is not proof of a successful login.
      await expect(page.locator('#wpadminbar')).toBeVisible();
      await waitForAdminStyles(page);
      return;
    } catch (error) {
      const rejection = await page.locator('#login_error').textContent().catch(() => '');
      if (rejection) {
        throw new Error('WordPress rejected test login for ' + username + ': ' + rejection.trim());
      }
      const stillOnLogin = new URL(page.url()).pathname === '/wp-login.php' &&
        await page.locator('#loginform').isVisible().catch(() => false);
      if (!stillOnLogin || attempt === 1) {
        throw new Error('Disposable WordPress login did not complete for ' + username +
          '; URL=' + page.url() + '; attempt=' + (attempt + 1) + '; reason=' + String(error));
      }
    }
  }
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
    const guardianForm = section.locator('[data-uop-guardian-claim]');
    await expect(guardianForm.getByRole('textbox', { name: 'Dependent invitation code' })).toBeVisible();
    const requestReview = guardianForm.getByRole('button', { name: 'Request review' });
    expect((await requestReview.boundingBox()).height).toBeGreaterThanOrEqual(44);
    const refresh = section.getByRole('button', { name: 'Refresh access' });
    expect((await refresh.boundingBox()).height).toBeGreaterThanOrEqual(44);
    await selector.focus();
    await page.keyboard.press('Tab');
    await expect(refresh).toBeFocused();
  }
});

test('guardian claim portal receipt and manager review queue require distinct authorities', async ({ page }) => {
  requireCi();
  await login(page, 'uop_ci_adult');
  await visit(page, '/?page_id=' + process.env.UOP_M610_PORTAL_PAGE, { waitUntil: 'domcontentloaded' });
  const form = page.locator('[data-uop-guardian-claim]');
  await expect(form).toBeVisible();
  await form.getByRole('textbox', { name: 'Dependent invitation code' })
    .fill('00000000-0000-4000-8000-000000000001');
  await form.getByRole('button', { name: 'Request review' }).click();
  await expect(form.getByRole('status')).toContainText('Request received.');
  await expect(form.getByRole('status')).toContainText('Access is not granted');
  // A regular member cannot reach the manager's private review queue.
  const denied = await visit(page, '/wp-admin/admin.php?page=uop-guardians', { waitUntil: 'domcontentloaded' });
  expect(denied.status()).toBeGreaterThanOrEqual(400);

  // Switch between independent synthetic WordPress identities explicitly.
  await page.context().clearCookies();
  await login(page, 'uop_test_admin');
  await visit(page, '/wp-admin/admin.php?page=uop-guardians', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Guardian requests' })).toBeVisible();
  await expect(page.getByText('No pending guardian requests.')).toBeVisible();
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
    // Some CI Chromium builds expose a stylesheet link before its cascade
    // reaches the first dynamically rendered record button. The threshold
    // remains strict; fail if real CSS never produces a 44px touch target.
    // An overloaded disposable TLS server may drop the admin stylesheet
    // subrequest with ERR_TOO_MANY_RETRIES. One *documented asset failure*
    // permits reloading the unchanged page; real CSS sizing defects still fail.
    for (let attempt = 0; attempt < 2; attempt++) {
      try {
        await expect.poll(() => first.evaluate(node => {
          const sheet = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
            .find(link => link.href.includes('/assets/m6-admin.css'));
          return sheet && sheet.sheet !== null ? node.getBoundingClientRect().height : 0;
        }), { timeout: 10000 }).toBeGreaterThanOrEqual(44);
        break;
      } catch (error) {
        const asset = await page.evaluate(() => {
          const link = [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
            .find(node => node.href.includes('/assets/m6-admin.css'));
          return { requested: Boolean(link), loaded: Boolean(link?.sheet) };
        });
        if (attempt > 0 || asset.loaded || !asset.requested) throw error;
        await visit(page, page.url(), { waitUntil: 'domcontentloaded' });
        await expect(first).toBeVisible();
      }
    }
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
      return styles.length > 0 && styles.every(link => {
        try { return Boolean(link.sheet) && link.sheet.cssRules.length > 0; }
        catch { return false; }
      });
    }), { timeout: 10000 }).toBe(true);
    const createSize = await add.evaluate(node => {
      const sheetRules = [...globalThis.document.styleSheets]
        .filter(sheet => sheet.href?.includes('m3-form-builder.css'))
        .flatMap(sheet => {
          try {
            return [...sheet.cssRules].filter(rule => rule.selectorText &&
              rule.selectorText.split(',').some(selector => {
                try { return node.matches(selector.trim()); } catch { return false; }
              })).map(rule => rule.cssText.slice(0, 240));
          } catch { return ['Stylesheet rules unavailable']; }
        });
      return {
        height: node.getBoundingClientRect().height,
        minHeight: globalThis.getComputedStyle(node).minHeight,
        bodyClass: globalThis.document.body.className,
        inBuilderMount: Boolean(node.closest('#uop-m3-form-builder')),
        cssFile: [...globalThis.document.querySelectorAll('link[rel="stylesheet"]')]
          .filter(link => link.href.includes('m3-form-builder.css')).map(link => link.href),
        matchedBuilderRules: sheetRules,
      };
    });
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


test('guardian claim → independent admin review → event-only access, without opening the dependent portal', async ({ page }) => {
  requireCi();
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ROOT) {
    throw new Error('Only the isolated WordPress test instance may expose the synthetic code.');
  }
  const dependent = execFileSync('wp', [
    'option', 'get', 'uop_m610_ci_review_child', '--path=' + process.env.WP_ROOT,
  ], { env: process.env, encoding: 'utf8', timeout: 30000 }).trim();
  expect(dependent).toMatch(/^[0-9a-f-]{36}$/);

  await login(page, 'uop_ci_review_parent');
  await visit(page, '/?page_id=' + process.env.UOP_M610_MEMBER_PAGE);
  let members = page.locator('form[data-uop-registration-form][data-uop-guest="0"]');
  await expect(members).toBeVisible();
  await expect(members.locator('[data-uop-subject]')).not.toContainText('Review Dependent');

  await visit(page, '/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  const claim = page.locator('[data-uop-guardian-claim]');
  await claim.getByRole('textbox', { name: 'Dependent invitation code' }).fill(dependent);
  await claim.getByRole('button', { name: 'Request review' }).click();
  // Wait for either an accepted receipt or an explicit storage error before
  // asking the synthetic-only DB probe to diagnose a missing claim.
  await expect.poll(() => claim.getByRole('status').textContent(), {
    timeout: 15000,
  }).toMatch(/Request received|server could not complete|request could not be completed/i);
  const probe = JSON.parse(execFileSync('wp', [
    'eval-file', 'tests/Fixtures/m610-guardian-ci-probe.php',
    '--path=' + process.env.WP_ROOT,
  ], { env: process.env, encoding: 'utf8', timeout: 30000 }));
  expect(probe.account_linked, JSON.stringify(probe)).toBe(true);
  expect(probe.self_active, JSON.stringify(probe)).toBe(true);
  expect(probe.child_found, JSON.stringify(probe)).toBe(true);
  expect(probe.child_active, JSON.stringify(probe)).toBe(true);
  expect(probe.pending_count, JSON.stringify(probe)).toBe(1);
  await expect(claim.getByRole('status')).toContainText('Request received.');
  await expect(claim.getByRole('status')).toContainText('Access is not granted');
  await expect(page.locator('[data-uop-portal-root="portal"] select.uop-portal__select')).not.toContainText('Review Dependent');

  await page.context().clearCookies();
  await login(page, 'uop_test_admin');
  await visit(page, '/wp-admin/admin.php?page=uop-guardians');
  const heading = page.getByRole('heading', { name: /Review Representative.*Review Dependent/ });
  await expect(heading).toBeVisible();
  const pending = page.locator('li').filter({ has: heading });
  await pending.getByRole('combobox', { name: 'Limit authorization to event' })
    .selectOption({ label: 'UOP CI members-only signup' });
  await pending.getByRole('checkbox', { name: /independently checked the representative authority/ }).check();
  await pending.getByRole('button', { name: 'Approve for 90 days' }).click();
  await expect(page).toHaveURL(/result=updated/);
  await expect(page.getByText('No pending guardian requests.')).toBeVisible();

  await page.context().clearCookies();
  await login(page, 'uop_ci_review_parent');
  await visit(page, '/?page_id=' + process.env.UOP_M610_MEMBER_PAGE);
  members = page.locator('form[data-uop-registration-form][data-uop-guest="0"]');
  await expect(members.locator('[data-uop-subject]')).toContainText('Review Dependent');
  await visit(page, '/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  await expect(page.locator('[data-uop-portal-root="portal"] select.uop-portal__select')).not.toContainText('Review Dependent');
});
