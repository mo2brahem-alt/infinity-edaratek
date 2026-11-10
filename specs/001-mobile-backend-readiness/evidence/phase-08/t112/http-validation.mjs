// T112 external observation only. Never follows redirects or accesses production.
import https from 'node:https';
import { createHash, randomUUID } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const target = 'test.edaratek.com';
const runId = randomUUID();
const qaPath = `edaratek-t112-qa-${runId}-absent.txt`;
const probes = [
  ['HEAD', '/'],
  ['GET', '/up'],
  ['GET', '/api/v1/me'],
  ['HEAD', `/storage/${qaPath}`],
  ['HEAD', `/media-files/${qaPath}`],
];

function observe([method, pathname]) {
  return new Promise(resolve => {
    const started = new Date().toISOString();
    const finish = result => resolve({ method, pathname, timestamp_utc: started, ...result });
    let finished = false;
    const done = result => { if (!finished) { finished = true; finish(result); } };
    const request = https.request({
      hostname: target, servername: target, port: 443, path: pathname, method,
      rejectUnauthorized: true, timeout: 12000, agent: false,
      headers: { Accept: pathname.startsWith('/api/') ? 'application/json' : '*/*',
        'User-Agent': 'Edaratek-T112-ReadOnly-Validation/1.0' },
    }, response => {
      const pieces = [];
      let bytes = 0;
      const certificate = response.socket.getPeerCertificate();
      const safeHeaders = {};
      for (const key of ['content-type', 'cache-control', 'pragma', 'expires', 'x-content-type-options', 'age', 'via', 'x-cache', 'cf-cache-status']) {
        if (response.headers[key]) safeHeaders[key] = String(response.headers[key]).slice(0, 240).replace(/[\r\n]/g, '');
      }
      let redirect = null;
      if (response.headers.location) {
        try { const url = new URL(response.headers.location, `https://${target}`); redirect = {
          origin: url.origin, same_approved_host: url.hostname === target, followed: false,
          path_and_query_suppressed: true,
        }; } catch { redirect = { malformed: true, followed: false }; }
      }
      response.on('data', part => {
        bytes += part.length;
        if (bytes > 262144) { response.destroy(); done({ status: 'INCOMPLETE_BODY_LIMIT', http_status: response.statusCode, received_bytes: bytes }); }
        else pieces.push(part);
      });
      response.on('end', () => {
        const body = Buffer.concat(pieces), text = body.toString('utf8');
        let errorShape = null;
        if (pathname.startsWith('/api/')) {
          try { const data = JSON.parse(text); const error = data.error;
            errorShape = { top_level_keys: Object.keys(data).filter(x => ['data', 'error', 'meta', 'links', 'message', 'errors'].includes(x)),
              error_object: !!error && typeof error === 'object',
              code: typeof error?.code === 'string' && /^[A-Z_]{1,64}$/.test(error.code) ? error.code : null,
              request_id_present: typeof error?.request_id === 'string' || typeof data.request_id === 'string' };
          } catch { errorShape = { json: false }; }
        }
        done({ status: 'HTTP_RESPONSE', http_status: response.statusCode,
          tls_authorized: response.socket.authorized, tls_certificate_fingerprint256: certificate.fingerprint256 ?? null,
          safe_headers: safeHeaders, redirect, received_bytes: body.length,
          body_sha256: createHash('sha256').update(body).digest('hex'),
          debug_markers_observed: /Whoops|Ignition|Stack trace:|SQLSTATE\[|vendor\/laravel\/framework\/src\//i.test(text),
          api_shape: errorShape, raw_body_exported: false, cookies_exported: false });
      });
      response.on('error', error => done({ status: 'RESPONSE_ERROR', error_code: error.code ?? 'UNCLASSIFIED', raw_error_suppressed: true }));
    });
    request.on('timeout', () => request.destroy(Object.assign(new Error('TIMEOUT'), { code: 'TIMEOUT' })));
    request.on('error', error => done({ status: 'CONNECTION_ERROR', error_code: error.code ?? 'UNCLASSIFIED', raw_error_suppressed: true }));
    request.end();
  });
}

const observations = [];
for (const probe of probes) observations.push(await observe(probe));
const result = { target, run_id: runId, completed_utc: new Date().toISOString(),
  scope: 'Unauthenticated HTTPS observations only; no redirects, no uploads/cache writes, no real resource identifiers.',
  certificate_validation_bypassed: false, observations,
  limitations: ['Missing-file probes cannot prove denial of existing private bytes.',
    'HTTP headers cannot certify physical storage/aliases/CDN topology, effective debug/cache isolation or deployed revision.',
    'Absence of debug markers in these responses is not a forced-error redaction test.'],
  production_accessed: false, server_configuration_modified: false, real_files_migrated: 0 };
writeFileSync(fileURLToPath(new URL('./http-observations.json', import.meta.url)), JSON.stringify(result, null, 2) + '\n');
console.log(JSON.stringify(result));
