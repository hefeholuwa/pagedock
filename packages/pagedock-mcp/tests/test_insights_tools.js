import assert from 'node:assert/strict';
import { createMcpServer, server as defaultServer } from '../src/server.js';

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

console.log('=== Running PageDock MCP Insights Tools (Analytics & Forms) Test Suite ===\n');

// 1. Tool Registration & Schema Tests
await test('Server registers get_site_analytics and list_form_submissions tools', () => {
  assert(defaultServer, 'defaultServer must be exported');
  const registered = Object.keys(defaultServer._registeredTools);

  const requiredTools = ['get_site_analytics', 'list_form_submissions'];
  for (const toolName of requiredTools) {
    assert(registered.includes(toolName), `Tool '${toolName}' must be registered on server`);
    const tool = defaultServer._registeredTools[toolName];
    assert.equal(typeof tool.description, 'string', `Tool '${toolName}' must have a description`);
    assert(tool.description.length > 5, `Tool '${toolName}' description must be descriptive`);
  }
});

await test('get_site_analytics schema requires slug and accepts optional days', () => {
  const tool = defaultServer._registeredTools['get_site_analytics'];
  assert(tool.inputSchema, 'get_site_analytics must have an inputSchema');

  // Missing slug
  const missingSlug = tool.inputSchema.safeParse({});
  assert.equal(missingSlug.success, false, 'Should fail when slug is missing');

  // Valid minimal
  const validMin = tool.inputSchema.safeParse({ slug: 'my-landing' });
  assert.equal(validMin.success, true, 'Should succeed with slug only');
  assert.equal(validMin.data.slug, 'my-landing');

  // Valid with days
  const validWithDays = tool.inputSchema.safeParse({ slug: 'my-landing', days: 14 });
  assert.equal(validWithDays.success, true, 'Should accept optional days');
  assert.equal(validWithDays.data.days, 14);

  // Invalid days (non-positive or non-number)
  const invalidDays = tool.inputSchema.safeParse({ slug: 'my-landing', days: -5 });
  assert.equal(invalidDays.success, false, 'Should reject negative days');
});

await test('list_form_submissions schema requires slug and accepts optional isSpam boolean', () => {
  const tool = defaultServer._registeredTools['list_form_submissions'];
  assert(tool.inputSchema, 'list_form_submissions must have an inputSchema');

  // Missing slug
  const missingSlug = tool.inputSchema.safeParse({});
  assert.equal(missingSlug.success, false, 'Should fail when slug is missing');

  // Valid minimal - default isSpam should be false or optional
  const validMin = tool.inputSchema.safeParse({ slug: 'contact-site' });
  assert.equal(validMin.success, true, 'Should succeed with slug only');
  assert.equal(validMin.data.slug, 'contact-site');
  assert.equal(validMin.data.isSpam, false, 'isSpam should default to false');

  // Valid with explicit isSpam true
  const validSpam = tool.inputSchema.safeParse({ slug: 'contact-site', isSpam: true });
  assert.equal(validSpam.success, true, 'Should accept isSpam: true');
  assert.equal(validSpam.data.isSpam, true);

  // Invalid isSpam type
  const invalidSpam = tool.inputSchema.safeParse({ slug: 'contact-site', isSpam: 'yes' });
  assert.equal(invalidSpam.success, false, 'Should reject string isSpam');
});

// Mock helper to create mock PageDockClient
function createMockClient(overrides = {}) {
  return {
    baseUrl: 'http://localhost:8080',
    apiToken: null,
    claimToken: null,
    getAnalytics: async (slug, days) => ({
      success: true,
      site: slug,
      analytics: {
        days: days || 30,
        total_views: 450,
        unique_visitors: 180,
        today_views: 25,
        today_uniques: 12,
        top_referrers: [
          { host: 'google.com', views: 210, percentage: 46.7 },
          { host: 'twitter.com', views: 95, percentage: 21.1 },
          { host: 'direct', views: 80, percentage: 17.8 },
        ],
        top_pages: [
          { path: '/', views: 320, percentage: 71.1 },
          { path: '/pricing', views: 85, percentage: 18.9 },
        ],
        devices: [
          { device: 'Desktop', count: 270, percentage: 60.0 },
          { device: 'Mobile', count: 180, percentage: 40.0 },
        ],
      },
    }),
    getFormSubmissions: async (slug, isSpam) => ({
      success: true,
      site: slug,
      is_spam: isSpam ?? false,
      submissions: [
        {
          id: 101,
          created_at: '2026-11-08 09:30:00',
          ip_address: '192.168.1.10',
          is_spam: isSpam ? 1 : 0,
          form_name: 'contact_us',
          data: {
            name: 'Alice Johnson',
            email: 'alice@example.com',
            message: 'Interested in enterprise plan.',
          },
        },
        {
          id: 102,
          created_at: '2026-11-08 08:15:00',
          ip_address: '10.0.0.5',
          is_spam: isSpam ? 1 : 0,
          form_name: 'newsletter',
          data: {
            email: 'bob@example.com',
          },
        },
      ],
    }),
    ...overrides,
  };
}

// 2. Tool Execution Tests
await test('get_site_analytics calls client.getAnalytics(slug) and returns structured text with views, unique visitors, referrers, and summary metrics', async () => {
  let capturedSlug = null;
  let capturedDays = null;
  const mockClient = createMockClient({
    getAnalytics: async (slug, days) => {
      capturedSlug = slug;
      capturedDays = days;
      return {
        success: true,
        site: slug,
        analytics: {
          days: 30,
          total_views: 820,
          unique_visitors: 340,
          today_views: 45,
          today_uniques: 20,
          top_referrers: [
            { host: 'news.ycombinator.com', views: 500, percentage: 61.0 },
            { host: 'github.com', views: 220, percentage: 26.8 },
          ],
        },
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['get_site_analytics'];

  const toolResult = await tool.handler({ slug: 'viral-launch' });
  assert.equal(capturedSlug, 'viral-launch');
  assert.equal(capturedDays, undefined);

  assert(Array.isArray(toolResult.content), 'Must return content array');
  assert.equal(toolResult.content[0].type, 'text');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'viral-launch');
  assert.equal(parsed.views, 820);
  assert.equal(parsed.unique_visitors, 340);
  assert(Array.isArray(parsed.referrers));
  assert.equal(parsed.referrers.length, 2);
  assert.equal(parsed.referrers[0].host, 'news.ycombinator.com');
  assert(parsed.summary, 'Summary metrics object must exist');
  assert.equal(parsed.summary.total_views, 820);
  assert.equal(parsed.summary.unique_visitors, 340);
  assert.equal(parsed.summary.today_views, 45);
});

await test('get_site_analytics forwards optional days parameter when provided', async () => {
  let capturedDays = null;
  const mockClient = createMockClient({
    getAnalytics: async (slug, days) => {
      capturedDays = days;
      return {
        success: true,
        analytics: { total_views: 100, unique_visitors: 40, top_referrers: [] },
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['get_site_analytics'];

  await tool.handler({ slug: 'custom-days-site', days: 7 });
  assert.equal(capturedDays, 7);
});

await test('list_form_submissions calls client.getFormSubmissions(slug, isSpam) with default isSpam=false', async () => {
  let capturedSlug = null;
  let capturedIsSpam = null;

  const mockClient = createMockClient({
    getFormSubmissions: async (slug, isSpam) => {
      capturedSlug = slug;
      capturedIsSpam = isSpam;
      return {
        success: true,
        submissions: [
          {
            id: 1,
            created_at: '2026-11-08 10:00:00',
            ip_address: '1.2.3.4',
            is_spam: false,
            data: { email: 'user@test.com', message: 'Hello' },
          },
        ],
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['list_form_submissions'];

  const toolResult = await tool.handler({ slug: 'leads-landing' });
  assert.equal(capturedSlug, 'leads-landing');
  assert.equal(capturedIsSpam, false);

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'leads-landing');
  assert.equal(parsed.count, 1);
  assert(Array.isArray(parsed.submissions));
  assert.equal(parsed.submissions.length, 1);

  const sub = parsed.submissions[0];
  assert.equal(sub.id, 1);
  assert.deepEqual(sub.fields, { email: 'user@test.com', message: 'Hello' });
  assert.equal(sub.timestamp, '2026-11-08 10:00:00');
  assert.equal(sub.ip, '1.2.3.4');
  assert.equal(sub.is_spam, false);
});

await test('list_form_submissions filters for spam leads when isSpam is true', async () => {
  let capturedIsSpam = null;

  const mockClient = createMockClient({
    getFormSubmissions: async (slug, isSpam) => {
      capturedIsSpam = isSpam;
      return {
        success: true,
        submissions: [
          {
            id: 99,
            created_at: '2026-11-08 04:00:00',
            ip_address: '203.0.113.195',
            is_spam: true,
            data: { email: 'spammer@bot.net', message: 'Click this link' },
          },
        ],
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['list_form_submissions'];

  const toolResult = await tool.handler({ slug: 'leads-landing', isSpam: true });
  assert.equal(capturedIsSpam, true);

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.count, 1);
  assert.equal(parsed.submissions[0].is_spam, true);
});

// 3. Error Handling and Boundary Safety
await test('Insights tool handlers gracefully catch client errors and return isError: true', async () => {
  const mockClient = createMockClient({
    getAnalytics: async () => {
      const err = new Error('Site not found');
      err.status = 404;
      throw err;
    },
    getFormSubmissions: async () => {
      const err = new Error('Database connection failed');
      err.status = 500;
      throw err;
    },
  });

  const server = createMcpServer({ client: mockClient });

  const analyticsRes = await server._registeredTools['get_site_analytics'].handler({
    slug: 'nonexistent-site',
  });
  assert.equal(analyticsRes.isError, true);
  assert(Array.isArray(analyticsRes.content));
  assert(analyticsRes.content[0].text.includes('Site not found'));

  const formsRes = await server._registeredTools['list_form_submissions'].handler({
    slug: 'error-site',
  });
  assert.equal(formsRes.isError, true);
  assert(Array.isArray(formsRes.content));
  assert(formsRes.content[0].text.includes('Database connection failed'));
});

console.log(`\nInsights Tools Tests Completed: Passed: ${testsPassed}, Failed: ${testsFailed}`);
if (testsFailed > 0) {
  process.exit(1);
} else {
  process.exit(0);
}
