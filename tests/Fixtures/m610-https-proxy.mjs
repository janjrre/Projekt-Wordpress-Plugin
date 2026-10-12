#!/usr/bin/env node
/**
 * Loopback-only TLS termination for ephemeral GitHub Actions WordPress.
 * A short-lived self-signed certificate is generated for each CI job.
 * Production DNS and Dreamloud hosting are never involved.
 */
import https from 'node:https';
import http from 'node:http';
import { readFileSync } from 'node:fs';

const options = {
  key: readFileSync(process.env.UOP_HTTPS_KEY),
  cert: readFileSync(process.env.UOP_HTTPS_CERT),
};
const server = https.createServer(options, (request, response) => {
  // Match a real HTTPS web server: serve publicly enqueued UOP static assets
  // without queuing them behind slow PHP requests. Chromium previously saw
  // net::ERR_TOO_MANY_RETRIES for m3-form-builder.css during the 44px A11y
  // assertion, despite the actual source CSS defining min-height:44px.
  //
  // Only allow simple basenames in this repository's assets directory.
  // Never proxy untrusted file paths, WP config, media, or other projects.
  const pathname = new URL(request.url || '/', 'https://127.0.0.1:8443').pathname;
  const asset = /^\/wp-content\/plugins\/uop-core\/assets\/([a-z0-9][a-z0-9._-]*\.(css|js))$/i.exec(pathname);
  if (request.method === 'GET' && asset) {
    try {
      const bytes = readFileSync(new URL('../../assets/' + asset[1], import.meta.url));
      response.writeHead(200, {
        'content-type': asset[2].toLowerCase() === 'css' ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8',
        'cache-control': 'no-store',
        'x-content-type-options': 'nosniff',
      });
      response.end(bytes);
    } catch {
      response.writeHead(404);
      response.end();
    }
    return;
  }
  const upstream = http.request({
    hostname: '127.0.0.1',
    port: 8080,
    path: request.url,
    method: request.method,
    headers: {
      ...request.headers,
      'x-forwarded-proto': 'https',
      'x-forwarded-host': request.headers.host,
    },
  }, (upstreamResponse) => {
    response.writeHead(upstreamResponse.statusCode || 502, upstreamResponse.headers);
    upstreamResponse.pipe(response);
  });
  upstream.on('error', () => {
    if (!response.headersSent) response.writeHead(502);
    response.end();
  });
  request.pipe(upstream);
});
server.listen(8443, '127.0.0.1', () => {
  console.log('Temporary HTTPS proxy listening on 127.0.0.1:8443');
});
