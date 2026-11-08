import assert from 'node:assert/strict';
import { createMcpServer, server as defaultServer } from '../src/server.js';
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

console.log('=== Running PageDock MCP Deploy & Site Tools Test Suite ===\n');

// 1. Tool Registration & Schema Tests
await test('Default server registers core deploy and site management tools', () => {
  assert(defaultServer, 'defaultServer must be exported');
  const registered = Object.keys(defaultServer._registeredTools);

  const requiredTools = ['deploy_html', 'list_sites', 'get_site', 'pull_site', 'delete_site'];
  for (const toolName of requiredTools) {
    assert(registered.includes(toolName), `Tool '${toolName}' must be registered on server`);
    const tool = defaultServer._registeredTools[toolName];
    assert.equal(typeof tool.description, 'string', `Tool '${toolName}' must have a description`);
    assert(tool.description.length > 5, `Tool '${toolName}' description must be descriptive`);
  }
});

await test('deploy_html input schema validates required and optional parameters', () => {
  const tool = defaultServer._registeredTools['deploy_html'];
  assert(tool.inputSchema, 'deploy_html must have an inputSchema');

  // html is required
  const invalidRes = tool.inputSchema.safeParse({});
  assert.equal(invalidRes.success, false, 'Should fail when html is missing');

  // html provided
  const validMin = tool.inputSchema.safeParse({ html: '<!DOCTYPE html><html><body>Hi</body></html>' });
  assert.equal(validMin.success, true, 'Should succeed with html only');
  assert.equal(validMin.data.html, '<!DOCTYPE html><html><body>Hi</body></html>');

  // all optional parameters
  const validFull = tool.inputSchema.safeParse({
    html: '<h1>Full</h1>',
    title: 'My Project',
    slug: 'my-project',
    ttl: '7d',
  });
  assert.equal(validFull.success, true);
  assert.equal(validFull.data.title, 'My Project');
  assert.equal(validFull.data.slug, 'my-project');
  assert.equal(validFull.data.ttl, '7d');
});

await test('list_sites input schema allows empty parameters', () => {
  const tool = defaultServer._registeredTools['list_sites'];
  if (tool.inputSchema) {
    const res = tool.inputSchema.safeParse({});
    assert.equal(res.success, true, 'list_sites should succeed with empty parameters');
  }
});

await test('get_site, pull_site, and delete_site schemas require slug', () => {
  for (const toolName of ['get_site', 'pull_site', 'delete_site']) {
    const tool = defaultServer._registeredTools[toolName];
    assert(tool.inputSchema, `${toolName} must have an inputSchema`);

    const missingSlug = tool.inputSchema.safeParse({});
    assert.equal(missingSlug.success, false, `${toolName} should reject missing slug`);

    const withSlug = tool.inputSchema.safeParse({ slug: 'alpha-landing' });
    assert.equal(withSlug.success, true, `${toolName} should accept valid slug`);
    assert.equal(withSlug.data.slug, 'alpha-landing');
  }
});

// Mock helper to create mock PageDockClient
function createMockClient(overrides = {}) {
  return {
    baseUrl: 'http://localhost:8080',
    apiToken: null,
    claimToken: null,
    deployHtml: async () => ({
      success: true,
      slug: 'mock-site',
      url: 'http://localhost:8080/s/mock-site',
      liveUrl: 'http://localhost:8080/s/mock-site/',
      editorUrl: 'http://localhost:8080/editor/mock-site',
      title: 'Mock Site',
      message: 'Site deployed successfully.',
    }),
    listSites: async () => ({
      success: true,
      sites: [
        {
          slug: 'site-a',
          title: 'Site A',
          views: 10,
          url: 'http://localhost:8080/s/site-a',
        },
      ],
    }),
    getSite: async (slug) => ({
      success: true,
      site: {
        slug,
        title: 'Site Title',
        entry_file: 'index.html',
        file_count: 3,
        total_size_bytes: 4096,
        views: 25,
      },
    }),
    pullSite: async (slug) => ({
      success: true,
      slug,
      html: '<!DOCTYPE html><html><body><h1>Mock Pulled Site</h1></body></html>',
    }),
    deleteSite: async (slug) => ({
      success: true,
      slug,
      message: `Site '${slug}' deleted.`,
    }),
    ...overrides,
  };
}

// 2. Tool Execution Tests with Mock PageDockClient
await test('deploy_html tool execution calls client.deployHtml and returns live URL, editor URL, slug, and status message', async () => {
  let capturedArgs = null;
  const mockClient = createMockClient({
    deployHtml: async (args) => {
      capturedArgs = args;
      return {
        success: true,
        slug: args.slug || 'gen-site-123',
        url: `http://localhost:8080/s/${args.slug || 'gen-site-123'}`,
        liveUrl: `http://localhost:8080/s/${args.slug || 'gen-site-123'}/`,
        editorUrl: `http://localhost:8080/editor/${args.slug || 'gen-site-123'}`,
        title: args.title || 'Untitled',
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['deploy_html'];

  const toolResult = await tool.handler({
    html: '<!DOCTYPE html><html><body><h1>Hello MCP</h1></body></html>',
    title: 'Hello MCP Site',
    slug: 'hello-mcp',
    ttl: '30d',
  });

  assert.deepEqual(capturedArgs, {
    html: '<!DOCTYPE html><html><body><h1>Hello MCP</h1></body></html>',
    title: 'Hello MCP Site',
    slug: 'hello-mcp',
    ttl: '30d',
  });

  assert(Array.isArray(toolResult.content), 'Result must contain content array');
  assert.equal(toolResult.content[0].type, 'text');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'hello-mcp');
  assert(parsed.liveUrl.includes('/s/hello-mcp'));
  assert(parsed.editorUrl.includes('/editor/hello-mcp'));
  assert(typeof parsed.message === 'string' && parsed.message.length > 0);
});

await test('list_sites tool execution returns JSON list of sites with slug, title, views, and live URLs', async () => {
  let called = false;
  const mockClient = createMockClient({
    listSites: async () => {
      called = true;
      return {
        success: true,
        sites: [
          {
            slug: 'portfolio-v1',
            title: 'Design Portfolio',
            views: 120,
            url: 'http://localhost:8080/s/portfolio-v1',
          },
          {
            slug: 'docs-site',
            title: 'Documentation',
            views: 45,
            url: 'http://localhost:8080/s/docs-site',
          },
        ],
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['list_sites'];

  const toolResult = await tool.handler({});
  assert.equal(called, true);
  assert(Array.isArray(toolResult.content));

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.sites.length, 2);
  assert.equal(parsed.sites[0].slug, 'portfolio-v1');
  assert.equal(parsed.sites[0].title, 'Design Portfolio');
  assert.equal(parsed.sites[0].views, 120);
  assert(parsed.sites[0].liveUrl.includes('/s/portfolio-v1'));
});

await test('get_site tool execution returns site configuration, file count, total size bytes, views, and URLs', async () => {
  let requestedSlug = null;
  const mockClient = createMockClient({
    getSite: async (slug) => {
      requestedSlug = slug;
      return {
        success: true,
        site: {
          slug,
          title: 'Detailed Site',
          entry_file: 'index.html',
          file_count: 5,
          total_size_bytes: 81920,
          views: 99,
          created_at: '2026-11-08 10:00:00',
        },
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['get_site'];

  const toolResult = await tool.handler({ slug: 'detailed-site' });
  assert.equal(requestedSlug, 'detailed-site');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.site.slug, 'detailed-site');
  assert.equal(parsed.site.title, 'Detailed Site');
  assert.equal(parsed.site.file_count, 5);
  assert.equal(parsed.site.total_size_bytes, 81920);
  assert.equal(parsed.site.views, 99);
  assert(parsed.site.liveUrl.includes('/s/detailed-site'));
  assert(parsed.site.editorUrl.includes('/editor/detailed-site'));
});

await test('pull_site tool execution returns raw HTML code of the site for agent inspection or editing', async () => {
  const sampleHtml = '<!DOCTYPE html><html><head><title>Pulled Site</title></head><body><h1>Pulled</h1></body></html>';
  let requestedSlug = null;
  const mockClient = createMockClient({
    pullSite: async (slug) => {
      requestedSlug = slug;
      return {
        success: true,
        slug,
        html: sampleHtml,
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['pull_site'];

  const toolResult = await tool.handler({ slug: 'pulled-site' });
  assert.equal(requestedSlug, 'pulled-site');
  assert(Array.isArray(toolResult.content));
  assert.equal(toolResult.content[0].type, 'text');
  assert.equal(toolResult.content[0].text, sampleHtml);
});

await test('delete_site tool execution removes site and confirms deletion', async () => {
  let deletedSlug = null;
  const mockClient = createMockClient({
    deleteSite: async (slug) => {
      deletedSlug = slug;
      return { success: true };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['delete_site'];

  const toolResult = await tool.handler({ slug: 'old-site' });
  assert.equal(deletedSlug, 'old-site');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'old-site');
  assert(parsed.message.includes('deleted') || parsed.message.includes('success'));
});

// 3. Error Handling
await test('Tool handlers gracefully catch client errors and return isError response', async () => {
  const mockClient = createMockClient({
    getSite: async () => {
      const err = new Error('Site not found');
      err.status = 404;
      throw err;
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['get_site'];

  const toolResult = await tool.handler({ slug: 'missing-site' });
  assert.equal(toolResult.isError, true);
  assert(Array.isArray(toolResult.content));
  assert(toolResult.content[0].text.includes('Site not found'));
});

console.log(`\nDeploy & Site Tools Tests Completed: Passed: ${testsPassed}, Failed: ${testsFailed}`);
if (testsFailed > 0) {
  process.exit(1);
} else {
  process.exit(0);
}
