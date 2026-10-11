import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Isolated disposable HTTPS WordPress only');
test.use({ baseURL: 'https://127.0.0.1:8443', ignoreHTTPSErrors: true });
test.describe.configure({ mode: 'serial' });

function verifyIsolated() {
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ROOT ||
      !process.env.WP_ADMIN_PASSWORD || !process.env.UOP_M610_MEMBER_PAGE ||
      !process.env.UOP_M610_PORTAL_PAGE) {
    throw new Error('Refusing member browser test without complete disposable CI configuration.');
  }
}
function action(command) {
  verifyIsolated();
  execFileSync('wp', [
    'eval-file', 'tests/Fixtures/m610-members-helper.php',
    '--path=' + process.env.WP_ROOT,
  ], {
    env: { ...process.env, UOP_M610_MEMBER_ACTION: command },
    timeout: 30000, stdio: 'pipe', encoding: 'utf8',
  });
}
async function login(page, username) {
  verifyIsolated();
  await page.goto('/wp-login.php');
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASSWORD);
  await page.locator('#wp-submit').click();
  await expect(page.locator('body')).not.toContainText('ERROR: The password you entered', { timeout: 10000 });
  await expect(page).not.toHaveURL(/wp-login\.php\?/);
}
async function memberForm(page) {
  await page.goto('/?page_id=' + process.env.UOP_M610_MEMBER_PAGE);
  const form = page.locator('form[data-uop-registration-form][data-uop-guest="0"]');
  await expect(form).toBeVisible();
  return form;
}
async function submit(form, subjectName, email, personName) {
  const selection = form.locator('[data-uop-subject]');
  await expect(selection).toBeEnabled();
  await selection.selectOption({ label: subjectName });
  await form.locator('[data-uop-field="name"]').fill(personName);
  await form.locator('[data-uop-field="contact"]').fill(email);
  await form.getByRole('button', { name: 'Submit registration' }).click();
  await expect(form.getByRole('status')).toContainText('Your registration was received', { timeout: 15000 });
}
async function portal(page) {
  await page.goto('/?page_id=' + process.env.UOP_M610_PORTAL_PAGE);
  const root = page.locator('[data-uop-portal-root="portal"]');
  await expect(root.locator('select.uop-portal__select')).toBeVisible();
  return root;
}

test('adult: real WordPress login, own Gutenberg submission, private portal; manager access is denied', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_ci_adult');
  const form = await memberForm(page);
  const choices = form.locator('[data-uop-subject] option');
  await expect(choices).toHaveCount(2); // Placeholder plus this linked self, no strangers or dependents
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Caregiver Minor');
  await submit(form, 'Adult Member', 'adult-ci@example.invalid', 'CI adult browser');
  const root = await portal(page);
  await expect(root.locator('select.uop-portal__select option')).toHaveCount(1);
  await expect(root.locator('.uop-portal__entry')).toContainText('submitted');
  const adminDenied = await page.goto('/wp-admin/admin.php?page=uop-registrations');
  expect(adminDenied.status()).toBeGreaterThanOrEqual(400);
  await expect(page.getByRole('heading', { name: 'Registrations', exact: true })).toHaveCount(0);
});

test('caregiver: no implicit access, explicit delegated minor submission, immediate revocation and stale REST denial', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_ci_parent');
  let form = await memberForm(page);
  await expect(form.locator('[data-uop-subject] option')).toHaveCount(2);
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Caregiver Minor');
  await expect(form.locator('[data-uop-subject]')).not.toContainText('Unrelated Member');
  // No browser shortcut that grants access: only trusted server-side delegation.
  action('grant-child');
  form = await memberForm(page);
  const selection = form.locator('[data-uop-subject]');
  await expect(selection).toContainText('Caregiver Minor');
  await expect(selection).not.toContainText('Unrelated Member');
  await submit(form, 'Caregiver Minor', 'parent-ci@example.invalid', 'CI dependent minor');
  const childUuid = await selection.inputValue();
  const root = await portal(page);
  const selector = root.locator('select.uop-portal__select');
  await selector.selectOption({ label: 'Caregiver Minor' });
  await expect(root.locator('.uop-portal__entry')).toContainText('submitted');
  await expect(root.getByRole('button', { name: /Cancel registration/ })).toBeVisible();

  // Remove authorization while the caregiver still has an open browser session.
  action('revoke-child');
  await root.getByRole('button', { name: 'Refresh access' }).click();
  await expect(selector).not.toContainText('Caregiver Minor');
  await expect(selector).toContainText('Caregiver Member');
  await expect(root.locator('.uop-portal__entry')).toHaveCount(0);
  // A stale client cannot read the minor's registrations after live revocation.
  const status = await page.evaluate(async id => {
    const nonce = globalThis.uopM6Portal?.nonce || '';
    const result = await fetch('/?rest_route=/uop/v1/me/portal/registrations&person_id=' + encodeURIComponent(id), {
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': nonce, Accept: 'application/json' },
    });
    return result.status;
  }, childUuid);
  expect(status).toBe(404);
});

test('administrator: real control center, registration review, capacity acceptance, rejection and audit', async ({ page }) => {
  verifyIsolated();
  await login(page, 'uop_test_admin');
  await page.goto('/wp-admin/admin.php?page=uop-overview');
  await expect(page.getByRole('heading', { name: 'UOP Control Center' })).toBeVisible();
  await expect(page.locator('.uop-m6-console__nav a[href*="uop-registrations"]')).toBeVisible();

  await page.goto('/wp-admin/admin.php?page=uop-registrations');
  const list = page.getByRole('region', { name: 'Record list' });
  const detail = page.getByRole('region', { name: 'Record details' });
  await expect(list.getByRole('button', { name: /Adult Member/ })).toBeVisible();
  await list.getByRole('button', { name: /Adult Member/ }).click();
  await expect(detail.getByRole('button', { name: 'Mark for review' })).toBeVisible();
  page.on('dialog', dialog => dialog.accept());
  await detail.getByRole('button', { name: 'Mark for review' }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('review', { timeout: 15000 });
  await expect(detail.getByRole('button', { name: 'Decide allocation' })).toBeVisible();
  await detail.getByRole('button', { name: 'Decide allocation' }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('accepted', { timeout: 15000 });

  await list.getByRole('button', { name: /Caregiver Minor/ }).click();
  await expect(detail.getByRole('button', { name: 'Reject', exact: true })).toBeVisible();
  await detail.getByRole('button', { name: 'Reject', exact: true }).click();
  await expect(detail.locator('.uop-m6-status')).toHaveText('rejected', { timeout: 15000 });

  await page.goto('/wp-admin/admin.php?page=uop-audit');
  await expect(page.getByRole('heading', { name: 'UOP Audit' })).toBeVisible();
  await expect(page.locator('.uop-m6-console__table')).toBeVisible();
  action('verify-states');
});
