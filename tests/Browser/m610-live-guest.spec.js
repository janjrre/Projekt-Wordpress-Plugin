import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Disposable HTTPS + WordPress + Mailpit only');
test.use({
  baseURL: 'https://127.0.0.1:8443',
  ignoreHTTPSErrors: true,
});

function runCiAction(action) {
  // Only local disposable CI WordPress, never production or an external host.
  if (process.env.UOP_M610_CI !== '1' || !process.env.WP_ROOT) {
    throw new Error('Refusing non-disposable WP-CLI action.');
  }
  execFileSync('wp', [
    'eval-file', 'tests/Fixtures/m610-live-helper.php', '--path=' + process.env.WP_ROOT,
  ], {
    env: { ...process.env, UOP_M610_ACTION: action },
    encoding: 'utf8', timeout: 30000, stdio: 'pipe',
  });
}

async function mailLink(request, key, address) {
  const list = await request.get('http://127.0.0.1:8025/api/v1/messages');
  expect(list.status()).toBe(200);
  const { messages } = await list.json();
  const candidate = (messages || []).filter(m =>
    JSON.stringify(m.To).includes(address) && !JSON.stringify(m.To).includes('dreamloud.de')
  );
  expect(candidate.length, 'Expected a real UOP email in isolated SMTP test inbox').toBeGreaterThan(0);
  for (const message of candidate) {
    const detail = await request.get('http://127.0.0.1:8025/api/v1/message/' + encodeURIComponent(message.ID));
    expect(detail.status()).toBe(200);
    const content = await detail.json();
    const payload = String(content.Text || '') + '\n' + String(content.HTML || '');
    const rawUrls = payload.match(/https:\/\/127\.0\.0\.1:8443\/[^\s"'<>]+/g) || [];
    const value = rawUrls.map(u => u.replaceAll('&amp;', '&')).find(u => u.includes(key));
    if (value) return value;
  }
  throw new Error('The received UOP mail did not contain the expected HTTPS bearer link.');
}

test('public form → REST → actual email → one-time verification → waitlist offer → confirmed seat', async ({ page, request }) => {
  test.setTimeout(120000);
  if (process.env.UOP_M610_CI !== '1' || !process.env.UOP_M610_PAGE_ID) {
    throw new Error('Missing isolated seeded WordPress form.');
  }

  // Submit via the actual server-rendered Gutenberg block, not an API shortcut.
  const pageResponse = await page.goto('/?page_id=' + encodeURIComponent(process.env.UOP_M610_PAGE_ID));
  const form = page.locator('form[data-uop-registration-form][data-uop-guest="1"]');
  if (await form.count() === 0) {
    // Safe pre-submission diagnostics: this public test page contains no token,
    // private record or real participant.
    const mainText = (await page.locator('body').innerText()).slice(0, 700);
    throw new Error('Published UOP guest form not rendered; gate=' + (pageResponse.headers()['x-uop-ci-flags'] || 'unknown') + '; URL=' + new URL(page.url()).pathname +
      '; page=' + mainText.replaceAll(/\\s+/g, ' '));
  }
  await expect(form).toBeVisible();
  await form.locator('[data-uop-field="name"]').fill('Browser Guest');
  await form.locator('[data-uop-field="contact"]').fill('guest-ci@example.invalid');
  const submit = form.getByRole('button', { name: 'Submit registration' });
  await expect(submit).toBeEnabled();
  await submit.click();
  await expect(form.getByRole('status')).toContainText('email confirmation link', { timeout: 20000 });
  await expect(submit).toBeDisabled();

  // Simulate the trusted background queue's next iteration, not the domain
  // decision itself. Actual UOP OutboxDispatcher and EmailDeliveryWorker run.
  runCiAction('drain-verification');
  const verificationUrl = await mailLink(request, 'uop-verify=1#registration_id=', 'guest-ci@example.invalid');
  await page.goto(verificationUrl);
  await expect(page.getByRole('heading', { name: 'Confirm email address' })).toBeVisible();
  await expect.poll(() => page.evaluate(() => globalThis.location.hash)).toBe('');
  await page.getByRole('button', { name: 'Confirm email' }).click();
  await expect(page.getByRole('status')).toContainText('request has been received');
  runCiAction('check-verified');

  // The real capacity services fill the only seat, waitlist this guest, release
  // the seat and send an expiring, private offer via the same M5 worker.
  runCiAction('offer');
  runCiAction('drain-offer');
  const offerUrl = await mailLink(request, 'uop-offer=1#offer_id=', 'guest-ci@example.invalid');
  await page.goto(offerUrl);
  await expect(page.getByRole('heading', { name: 'Accept your place' })).toBeVisible();
  await expect.poll(() => page.evaluate(() => globalThis.location.hash)).toBe('');
  await page.getByRole('button', { name: 'Accept place' }).click();
  await expect(page.getByRole('status')).toContainText('confirmation has been received');
  runCiAction('check-accepted');
});


test('public guest REST rejects malformed, mass-assigned and oversized bodies with stable errors', async ({ request }) => {
  if (process.env.UOP_M610_CI !== '1') {
    throw new Error('Refusing non-disposable security regression.');
  }
  const path = '/?rest_route=%2Fuop%2Fv1%2Fregistrations%2Fguest';
  const scenarios = [
    '{"event_id":',
    JSON.stringify({
      event_id: '00000000-0000-4000-8000-000000000001',
      command_id: '00000000-0000-4000-8000-000000000002',
      fields: { name: 'Attempt', contact: 'injected@example.invalid' },
      email_verified_at: '2000-01-01 00:00:00',
    }),
    ' '.repeat(32769),
  ];
  for (const data of scenarios) {
    const response = await request.post(path, {
      data,
      headers: { 'Content-Type': 'application/json' },
      failOnStatusCode: false,
    });
    expect(response.status()).toBe(400);
    const denied = await response.json();
    expect(denied.code).toBe('uop_invalid_schema');
    expect(JSON.stringify(denied)).not.toContain('injected@example.invalid');
    expect(JSON.stringify(denied)).not.toContain('email_verified_at');
  }
});
