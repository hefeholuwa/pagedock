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

console.log('=== Running PageDock MCP Patch & Versioning Tools Test Suite ===\n');

// 1. Tool Registration & Schema Tests
await test('Server registers update_site_file, list_versions, and rollback_site tools', () => {
  assert(defaultServer, 'defaultServer must be exported');
  const registered = Object.keys(defaultServer._registeredTools);

  const requiredTools = ['update_site_file', 'list_versions', 'rollback_site'];
  for (const toolName of requiredTools) {
    assert(registered.includes(toolName), `Tool '${toolName}' must be registered on server`);
    const tool = defaultServer._registeredTools[toolName];
    assert.equal(typeof tool.description, 'string', `Tool '${toolName}' must have a description`);
    assert(tool.description.length > 5, `Tool '${toolName}' description must be descriptive`);
  }
});

await test('update_site_file schema validates slug and content as required, path and summary as optional', () => {
  const tool = defaultServer._registeredTools['update_site_file'];
  assert(tool.inputSchema, 'update_site_file must have an inputSchema');

  // Missing required parameters
  const emptyRes = tool.inputSchema.safeParse({});
  assert.equal(emptyRes.success, false, 'Should fail when empty');

  const missingContent = tool.inputSchema.safeParse({ slug: 'test-site' });
  assert.equal(missingContent.success, false, 'Should fail when content is missing');

  const missingSlug = tool.inputSchema.safeParse({ content: '<p>Updated</p>' });
  assert.equal(missingSlug.success, false, 'Should fail when slug is missing');

  // Valid minimal
  const validMin = tool.inputSchema.safeParse({
    slug: 'test-site',
    content: '<p>Updated</p>',
  });
  assert.equal(validMin.success, true, 'Should succeed with slug and content');
  assert.equal(validMin.data.slug, 'test-site');
  assert.equal(validMin.data.content, '<p>Updated</p>');

  // Valid full
  const validFull = tool.inputSchema.safeParse({
    slug: 'test-site',
    path: 'style.css',
    content: 'body { background: #000; }',
    summary: 'Dark mode styling update',
  });
  assert.equal(validFull.success, true, 'Should succeed with all parameters');
  assert.equal(validFull.data.path, 'style.css');
  assert.equal(validFull.data.summary, 'Dark mode styling update');
});

await test('list_versions schema requires slug', () => {
  const tool = defaultServer._registeredTools['list_versions'];
  assert(tool.inputSchema, 'list_versions must have an inputSchema');

  const missingSlug = tool.inputSchema.safeParse({});
  assert.equal(missingSlug.success, false, 'Should reject missing slug');

  const withSlug = tool.inputSchema.safeParse({ slug: 'my-landing' });
  assert.equal(withSlug.success, true, 'Should accept slug');
  assert.equal(withSlug.data.slug, 'my-landing');
});

await test('rollback_site schema requires slug and accepts optional version number', () => {
  const tool = defaultServer._registeredTools['rollback_site'];
  assert(tool.inputSchema, 'rollback_site must have an inputSchema');

  const missingSlug = tool.inputSchema.safeParse({});
  assert.equal(missingSlug.success, false, 'Should reject missing slug');

  const withSlugOnly = tool.inputSchema.safeParse({ slug: 'my-landing' });
  assert.equal(withSlugOnly.success, true, 'Should accept slug alone');

  const withVersion = tool.inputSchema.safeParse({ slug: 'my-landing', version: 2 });
  assert.equal(withVersion.success, true, 'Should accept numeric version');
  assert.equal(withVersion.data.version, 2);

  const invalidVersion = tool.inputSchema.safeParse({ slug: 'my-landing', version: 'two' });
  assert.equal(invalidVersion.success, false, 'Should reject non-numeric version');
});

// Mock helper to create mock PageDockClient
function createMockClient(overrides = {}) {
  return {
    baseUrl: 'http://localhost:8080',
    apiToken: null,
    claimToken: null,
    updateSiteFiles: async (slug, files, summary) => ({
      success: true,
      slug,
      files: Array.isArray(files) ? files.map(f => f.path || f.file) : Object.keys(files),
      version: 4,
    }),
    listVersions: async (slug) => ({
      success: true,
      slug,
      current_version: 3,
      versions: [
        {
          version_number: 3,
          created_at: '2026-11-08 10:30:00',
          file_count: 3,
          total_size_bytes: 4096,
          summary: 'Added contact form',
        },
        {
          version_number: 2,
          created_at: '2026-11-08 10:15:00',
          file_count: 2,
          total_size_bytes: 3500,
          summary: 'Updated stylesheet',
        },
        {
          version_number: 1,
          created_at: '2026-11-08 10:00:00',
          file_count: 1,
          total_size_bytes: 2048,
          summary: 'Initial deployment',
        },
      ],
    }),
    rollbackSite: async (slug, version) => ({
      success: true,
      slug,
      restored_version: version ?? 2,
      new_version: 5,
      message: `Successfully restored revision v${version ?? 2}.`,
    }),
    ...overrides,
  };
}

// 2. Tool Execution Tests with Mock PageDockClient
await test('update_site_file execution with default parameters calls updateSiteFiles with index.html and returns JSON', async () => {
  let captured = null;
  const mockClient = createMockClient({
    updateSiteFiles: async (slug, files, summary) => {
      captured = { slug, files, summary };
      return {
        success: true,
        slug,
        files: ['index.html'],
        version: 2,
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['update_site_file'];

  const toolResult = await tool.handler({
    slug: 'patch-test',
    content: '<!DOCTYPE html><h1>Patched via MCP</h1>',
  });

  assert.equal(captured.slug, 'patch-test');
  assert.deepEqual(captured.files, [{ path: 'index.html', content: '<!DOCTYPE html><h1>Patched via MCP</h1>' }]);
  assert.equal(captured.summary, 'Updated file via MCP');

  assert(Array.isArray(toolResult.content), 'Must return content array');
  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'patch-test');
  assert.equal(parsed.path, 'index.html');
  assert.equal(parsed.version, 2);
  assert(typeof parsed.message === 'string' && parsed.message.includes('updated'));
});

await test('update_site_file execution with custom path and summary passes them to client', async () => {
  let captured = null;
  const mockClient = createMockClient({
    updateSiteFiles: async (slug, files, summary) => {
      captured = { slug, files, summary };
      return {
        success: true,
        slug,
        files: ['css/style.css'],
        version: 5,
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['update_site_file'];

  const toolResult = await tool.handler({
    slug: 'patch-test',
    path: 'css/style.css',
    content: 'body { color: red; }',
    summary: 'Fix header styles',
  });

  assert.equal(captured.slug, 'patch-test');
  assert.deepEqual(captured.files, [{ path: 'css/style.css', content: 'body { color: red; }' }]);
  assert.equal(captured.summary, 'Fix header styles');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.path, 'css/style.css');
  assert.equal(parsed.version, 5);
});

await test('list_versions execution returns snapshots with version_number, created_at, file_count, summary', async () => {
  let requestedSlug = null;
  const mockClient = createMockClient({
    listVersions: async (slug) => {
      requestedSlug = slug;
      return {
        success: true,
        slug,
        current_version: 2,
        versions: [
          {
            version_number: 2,
            created_at: '2026-11-08 10:30:00',
            file_count: 2,
            summary: 'Added CSS file',
          },
          {
            version_number: 1,
            created_at: '2026-11-08 10:00:00',
            file_count: 1,
            summary: 'Initial deployment',
          },
        ],
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['list_versions'];

  const toolResult = await tool.handler({ slug: 'site-versions' });
  assert.equal(requestedSlug, 'site-versions');

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'site-versions');
  assert(Array.isArray(parsed.versions));
  assert.equal(parsed.versions.length, 2);

  const v2 = parsed.versions[0];
  assert.equal(v2.version_number, 2);
  assert.equal(v2.created_at, '2026-11-08 10:30:00');
  assert.equal(v2.file_count, 2);
  assert.equal(v2.summary, 'Added CSS file');
});

await test('rollback_site execution with specific version restores target version', async () => {
  let captured = null;
  const mockClient = createMockClient({
    rollbackSite: async (slug, version) => {
      captured = { slug, version };
      return {
        success: true,
        slug,
        restored_version: 1,
        new_version: 3,
        message: 'Successfully restored revision v1.',
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['rollback_site'];

  const toolResult = await tool.handler({ slug: 'site-rollback', version: 1 });
  assert.equal(captured.slug, 'site-rollback');
  assert.equal(captured.version, 1);

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'site-rollback');
  assert.equal(parsed.restoredVersion, 1);
  assert(parsed.message.includes('restored') || parsed.message.includes('rolled back'));
});

await test('rollback_site execution without version restores previous version (1-click rollback)', async () => {
  let captured = null;
  const mockClient = createMockClient({
    rollbackSite: async (slug, version) => {
      captured = { slug, version };
      return {
        success: true,
        slug,
        restored_version: 3,
        new_version: 5,
        message: 'Successfully restored revision v3.',
      };
    },
  });

  const server = createMcpServer({ client: mockClient });
  const tool = server._registeredTools['rollback_site'];

  const toolResult = await tool.handler({ slug: 'site-rollback' });
  assert.equal(captured.slug, 'site-rollback');
  assert.equal(captured.version, undefined);

  const parsed = JSON.parse(toolResult.content[0].text);
  assert.equal(parsed.success, true);
  assert.equal(parsed.slug, 'site-rollback');
  assert.equal(parsed.restoredVersion, 3);
});

// 3. Error Handling
await test('Tool handlers gracefully catch client errors and return isError response', async () => {
  const mockClient = createMockClient({
    updateSiteFiles: async () => {
      const err = new Error('Unauthorized to edit this site');
      err.status = 403;
      throw err;
    },
    listVersions: async () => {
      const err = new Error('Site not found');
      err.status = 404;
      throw err;
    },
    rollbackSite: async () => {
      const err = new Error('Snapshot v99 does not exist');
      err.status = 400;
      throw err;
    },
  });

  const server = createMcpServer({ client: mockClient });

  const patchRes = await server._registeredTools['update_site_file'].handler({
    slug: 'forbidden-site',
    content: '<h1>test</h1>',
  });
  assert.equal(patchRes.isError, true);
  assert(patchRes.content[0].text.includes('Unauthorized'));

  const verRes = await server._registeredTools['list_versions'].handler({
    slug: 'missing-site',
  });
  assert.equal(verRes.isError, true);
  assert(verRes.content[0].text.includes('Site not found'));

  const rbRes = await server._registeredTools['rollback_site'].handler({
    slug: 'invalid-version',
    version: 99,
  });
  assert.equal(rbRes.isError, true);
  assert(rbRes.content[0].text.includes('Snapshot v99 does not exist'));
});

console.log(`\nPatch & Versioning Tools Tests Completed: Passed: ${testsPassed}, Failed: ${testsFailed}`);
if (testsFailed > 0) {
  process.exit(1);
} else {
  process.exit(0);
}
