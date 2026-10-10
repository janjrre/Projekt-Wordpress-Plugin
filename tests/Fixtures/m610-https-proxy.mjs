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
