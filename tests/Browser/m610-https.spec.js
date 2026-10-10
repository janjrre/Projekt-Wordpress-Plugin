import { test, expect } from '@playwright/test';

// The normal HTTP/WordPress CI matrix still runs its existing security tests.
// These positive-HTTPS checks run only in the isolated HTTPS+Mailpit workflow.
test.skip(process.env.UOP_HTTPS_E2E !== '1', 'Requires disposable GitHub HTTPS WordPress and Mailpit');
test.use({
  baseURL: 'https://127.0.0.1:8443',
  ignoreHTTPSErrors: true, // CI-only self-signed certificate, never staging or production
});

test('HTTPS registration confirmation landing uses a private fragment and not HTTP', async ({ page }) => {
  const id = '12345678-1234-4234-8234-123456789abc';
  const token = 'a'.repeat(64);
  const res = await page.goto('/?uop-verify=1#registration_id=' + id + '&token=' + token);
  expect(res.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Confirm email address' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Confirm email' })).toBeEnabled();
  await expect.poll(() => page.evaluate(() => globalThis.location.hash)).toBe('');
  expect(new URL(page.url()).searchParams.has('token')).toBe(false);
  await expect(page.locator('#uop-guest-verification')).toHaveAttribute(
    'data-endpoint',
    /https:\/\/127\.0\.0\.1:8443\/(?:index\.php)?\?rest_route=|https:\/\/127\.0\.0\.1:8443\/wp-json\//,
  );
});

test('HTTPS waitlist offer confirmation landing is available and never leaks its token into requests', async ({ page }) => {
  const id = '12345678-1234-4234-8234-123456789abc';
  const token = 'b'.repeat(64);
  const url = '/?uop-offer=1#offer_id=' + id + '&token=' + token;
  const res = await page.goto(url);
  expect(res.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Accept your place' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Accept place' })).toBeEnabled();
  await expect.poll(() => page.evaluate(() => globalThis.location.hash)).toBe('');
  expect(new URL(page.url()).searchParams.has('token')).toBe(false);
});

test('WordPress actually sent a test email through isolated SMTP into Mailpit', async ({ request }) => {
  const response = await request.get('http://127.0.0.1:8025/api/v1/messages');
  expect(response.ok()).toBeTruthy();
  const inbox = await response.json();
  expect(inbox.messages).toEqual(
    expect.arrayContaining([
      expect.objectContaining({
        Subject: 'UOP HTTPS isolated SMTP smoke',
      }),
    ]),
  );
  const matching = inbox.messages.filter((message) => message.Subject === 'UOP HTTPS isolated SMTP smoke');
  expect(matching).toHaveLength(1);
  const recipient = JSON.stringify(matching[0].To);
  expect(recipient).toContain('uop-ci@example.invalid');
  expect(JSON.stringify(inbox)).not.toContain('dreamloud.de');
});
