#!/usr/bin/env node
/**
 * Read-only isolated staging readiness check. No WordPress writes or login.
 */
import { Buffer } from 'node:buffer';

const REQUIRED_HOST = 'uop-test.dreamloud.de';
const base = new URL(process.env.UOP_STAGING_URL || 'https://uop-test.dreamloud.de/');
if (
  base.protocol !== 'https:' ||
  base.hostname !== REQUIRED_HOST ||
  base.port !== '' ||
  base.pathname !== '/' ||
  base.search !== '' ||
  base.hash !== '' ||
  base.username !== '' ||
  base.password !== ''
) {
  throw new Error('Refusing any target other than the exact HTTPS UOP staging host.');
}
const username = process.env.UOP_STAGING_BASIC_USER || '';
const password = process.env.UOP_STAGING_BASIC_PASSWORD || '';
if (!username || !password) {
  throw new Error('Missing separate staging HTTP-access credentials.');
}
const headers = {
  Authorization: 'Basic ' + Buffer.from(username + ':' + password).toString('base64'),
  'Cache-Control': 'no-store',
};
const request = async (path) => {
  const response = await fetch(new URL(path, base), {
    headers,
    redirect: 'manual',
    signal: AbortSignal.timeout(15000),
  });
  if (response.status >= 300 && response.status < 400) {
    throw new Error('Unexpected redirect from staging (HTTP ' + response.status + ').');
  }
  if (!response.ok) {
    throw new Error('Staging readiness failed (HTTP ' + response.status + ').');
  }
  return response;
};
// A valid password does not prove access protection. Verify that outsiders
// really receive an authentication challenge before reading the staging site.
const anonymous = await fetch(base, {
  redirect: 'manual',
  signal: AbortSignal.timeout(15000),
});
if (anonymous.status !== 401 && anonymous.status !== 403) {
  throw new Error('Staging is not protected from anonymous visitors (expected HTTP 401/403).');
}
const front = await request('/');
if (front.headers.get('x-uop-staging') !== REQUIRED_HOST) {
  throw new Error('Staging identity marker missing or mismatched.');
}
if (!/noindex/i.test(front.headers.get('x-robots-tag') || '')) {
  throw new Error('Staging must be excluded from indexing.');
}
if (!/no-store/i.test(front.headers.get('cache-control') || '')) {
  throw new Error('Staging must not be shared-cacheable.');
}
const metadata = await (await request('/?rest_route=%2F')).json();
if (!metadata || typeof metadata.name !== 'string') {
  throw new Error('The target does not expose a WordPress REST root.');
}
if (process.env.UOP_STAGING_EXPECT_ACTIVE === '1') {
  if (!Array.isArray(metadata.namespaces) || !metadata.namespaces.includes('uop/v1')) {
    throw new Error('UOP Core REST namespace absent: activate plugin on staging.');
  }
}
console.log('Isolated UOP staging HTTPS / WordPress / noindex / no-store checks passed.');
