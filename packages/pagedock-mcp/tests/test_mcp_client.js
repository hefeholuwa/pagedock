import assert from 'node:assert/strict';
import { PageDockClient } from '../src/client.js';

let testsPassed = 0;
let testsFailed = 0;

async function test(name, fn) {
  try {
    await fn();
    console.log(`PASS: ${name}`);
    testsPassed++;
  } catch (err) {
    console.error(`FAIL: ${name}`);
    console.error(err);
    testsFailed++;
  }
}

// Helper to mock globalThis.fetch
function mockFetch(handler) {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (url, options = {}) => {
    return handler(url.toString(), options);
  };
  return () => {
    globalThis.fetch = originalFetch;
  };
}

console.log('=== Running PageDock MCP API Client Test Suite ===\n');

// Test 1: Instantiation & Defaults
await test('Instantiates with default baseUrl (http://localhost:8080) and no token', () => {
  const client = new PageDockClient();
  assert.equal(client.baseUrl, 'http://localhost:8080');
  assert.equal(client.apiToken, null);
});

await test('Instantiates with custom baseUrl and trims trailing slash', () => {
  const client = new PageDockClient({ baseUrl: 'https://preview.pagedock.io/' });
  assert.equal(client.baseUrl, 'https://preview.pagedock.io');
});

await test('Instantiates with apiToken and sends Bearer Authorization header', async () => {
  let capturedHeaders = null;
  const restore = mockFetch((url, options) => {
    capturedHeaders = options.headers;
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ sites: [] }),
    };
  });

  try {
    const client = new PageDockClient({ apiToken: 'test-secret-token' });
    await client.listSites();
    assert(capturedHeaders, 'Headers should be captured');
    const authHeader = capturedHeaders.Authorization || capturedHeaders.authorization;
    assert.equal(authHeader, 'Bearer test-secret-token');
  } finally {
    restore();
  }
});

// Test 2: deployHtml({ html, title, slug, ttl })
await test('deployHtml with slug issues POST /api/editor/{slug}', async () => {
  let capturedUrl = null;
  let capturedMethod = null;
  let capturedBody = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method;
    capturedBody = JSON.parse(options.body);
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, slug: 'landing-v1', liveUrl: 'http://localhost:8080/s/landing-v1/' }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.deployHtml({
      html: '<!DOCTYPE html><html><body><h1>Hello World</h1></body></html>',
      title: 'Landing Page',
      slug: 'landing-v1',
      ttl: 3600,
    });

    assert.equal(capturedMethod, 'POST');
    assert.equal(capturedUrl, 'http://localhost:8080/api/editor/landing-v1');
    assert.equal(capturedBody.title, 'Landing Page');
    assert.equal(capturedBody.slug, 'landing-v1');
    assert.equal(capturedBody.ttl, 3600);
    assert(capturedBody.html.includes('Hello World'));
    assert.equal(res.success, true);
    assert.equal(res.slug, 'landing-v1');
  } finally {
    restore();
  }
});

await test('deployHtml without slug issues POST /api/upload', async () => {
  let capturedUrl = null;
  let capturedMethod = null;
  let capturedBody = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method;
    capturedBody = JSON.parse(options.body);
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, slug: 'auto-gen-123', liveUrl: 'http://localhost:8080/s/auto-gen-123/' }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.deployHtml({
      html: '<h1>Quick Deploy</h1>',
      title: 'Auto Site',
      ttl: 86400,
    });

    assert.equal(capturedMethod, 'POST');
    assert.equal(capturedUrl, 'http://localhost:8080/api/upload');
    assert.equal(capturedBody.title, 'Auto Site');
    assert.equal(capturedBody.ttl, 86400);
    assert(capturedBody.html.includes('Quick Deploy'));
    assert.equal(res.success, true);
  } finally {
    restore();
  }
});

// Test 3: listSites() -> GET /api/sites
await test('listSites issues GET /api/sites', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, sites: [{ slug: 'alpha', title: 'Alpha' }] }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.listSites();

    assert.equal(capturedMethod, 'GET');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites');
    assert.equal(res.success, true);
    assert.equal(res.sites.length, 1);
  } finally {
    restore();
  }
});

// Test 4: getSite(slug) -> GET /api/sites?slug={slug}
await test('getSite issues GET /api/sites?slug={slug}', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, site: { slug: 'portfolio', views: 42 } }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.getSite('portfolio');

    assert.equal(capturedMethod, 'GET');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites?slug=portfolio');
    assert.equal(res.success, true);
    assert.equal(res.site.slug, 'portfolio');
  } finally {
    restore();
  }
});

// Test 5: pullSite(slug) -> GET /s/{slug}/
await test('pullSite issues GET /s/{slug}/ and retrieves HTML content', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const sampleHtml = '<!DOCTYPE html><html><body><h1>Pulled Site</h1></body></html>';
  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'text/html; charset=utf-8' }),
      text: async () => sampleHtml,
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.pullSite('pulled-site');

    assert.equal(capturedMethod, 'GET');
    assert.equal(capturedUrl, 'http://localhost:8080/s/pulled-site/');
    assert.equal(res.success, true);
    assert.equal(res.html, sampleHtml);
    assert.equal(res.slug, 'pulled-site');
  } finally {
    restore();
  }
});

// Test 6: listVersions(slug) -> GET /api/sites/{slug}/versions
await test('listVersions issues GET /api/sites/{slug}/versions', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({
        success: true,
        slug: 'my-app',
        current_version: 3,
        versions: [{ version: 1 }, { version: 2 }, { version: 3 }],
      }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.listVersions('my-app');

    assert.equal(capturedMethod, 'GET');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/versions');
    assert.equal(res.success, true);
    assert.equal(res.current_version, 3);
  } finally {
    restore();
  }
});

// Test 7: rollbackSite(slug, version) -> POST /api/sites/{slug}/rollback
await test('rollbackSite issues POST /api/sites/{slug}/rollback with version', async () => {
  let capturedUrl = null;
  let capturedMethod = null;
  let capturedBody = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method;
    capturedBody = JSON.parse(options.body);
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, restored_version: 2 }),
    };
  });

  try {
    const client = new PageDockClient();
    const res = await client.rollbackSite('my-app', 2);

    assert.equal(capturedMethod, 'POST');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/rollback');
    assert.equal(capturedBody.version, 2);
    assert.equal(res.success, true);
  } finally {
    restore();
  }
});

// Test 8: getAnalytics(slug) -> GET /api/sites/{slug}/analytics
await test('getAnalytics issues GET /api/sites/{slug}/analytics and accepts optional days', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, analytics: { total_views: 120 } }),
    };
  });

  try {
    const client = new PageDockClient();
    const res1 = await client.getAnalytics('my-app');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/analytics');
    assert.equal(res1.success, true);

    const res2 = await client.getAnalytics('my-app', 14);
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/analytics?days=14');
    assert.equal(res2.success, true);
  } finally {
    restore();
  }
});

// Test 9: getFormSubmissions(slug, isSpam) -> GET /api/sites/{slug}/forms
await test('getFormSubmissions issues GET /api/sites/{slug}/forms with spam filtering', async () => {
  let capturedUrl = null;
  let capturedMethod = null;

  const restore = mockFetch((url, options) => {
    capturedUrl = url;
    capturedMethod = options.method || 'GET';
    return {
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: true, submissions: [] }),
    };
  });

  try {
    const client = new PageDockClient();
    await client.getFormSubmissions('my-app');
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/forms');

    await client.getFormSubmissions('my-app', false);
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/forms?is_spam=false');

    await client.getFormSubmissions('my-app', true);
    assert.equal(capturedUrl, 'http://localhost:8080/api/sites/my-app/forms?is_spam=true');
  } finally {
    restore();
  }
});

// Test 10: Graceful error handling
await test('Throws formatted error on API failure', async () => {
  const restore = mockFetch(() => {
    return {
      ok: false,
      status: 404,
      statusText: 'Not Found',
      headers: new Headers({ 'content-type': 'application/json' }),
      json: async () => ({ success: false, error: 'Site not found' }),
      text: async () => JSON.stringify({ success: false, error: 'Site not found' }),
    };
  });

  try {
    const client = new PageDockClient();
    await assert.rejects(
      async () => {
        await client.getSite('non-existent');
      },
      (err) => {
        assert(err.message.includes('Site not found') || err.message.includes('404'));
        return true;
      }
    );
  } finally {
    restore();
  }
});

console.log(`\nTests Completed: Passed: ${testsPassed}, Failed: ${testsFailed}`);
if (testsFailed > 0) {
  process.exit(1);
} else {
  process.exit(0);
}
