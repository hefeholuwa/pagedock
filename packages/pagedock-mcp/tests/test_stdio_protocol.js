import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const indexPath = path.resolve(__dirname, '../src/index.js');

console.log('=== Running PageDock MCP Stdio Protocol Test Suite ===\n');

async function testStdioProtocol() {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [indexPath], {
      env: {
        ...process.env,
        PAGEDOCK_BASE_URL: 'http://localhost:8080',
      },
      stdio: ['pipe', 'pipe', 'pipe'],
    });

    let buffer = '';
    let initReceived = false;
    let toolsReceived = false;
    let stderrOutput = '';

    const timeout = setTimeout(() => {
      child.kill('SIGKILL');
      reject(new Error('Test timed out after 10 seconds waiting for stdio responses'));
    }, 10000);

    child.stderr.on('data', (chunk) => {
      stderrOutput += chunk.toString();
    });

    child.stdout.on('data', (chunk) => {
      buffer += chunk.toString();
      const lines = buffer.split('\n');
      // Keep last incomplete line in buffer
      buffer = lines.pop();

      for (const line of lines) {
        if (!line.trim()) continue;
        let msg;
        try {
          msg = JSON.parse(line);
        } catch (e) {
          console.error('Non-JSON line on stdout:', line);
          continue;
        }

        // Handle initialize response (id = 1)
        if (msg.id === 1) {
          try {
            assert.equal(msg.jsonrpc, '2.0', 'JSON-RPC version must be 2.0');
            assert(msg.result, 'initialize response must contain result');
            assert.equal(
              msg.result.serverInfo?.name,
              'pagedock-mcp',
              `Expected serverInfo.name to be "pagedock-mcp", got "${msg.result.serverInfo?.name}"`
            );
            initReceived = true;
            console.log('PASS: MCP initialize response verified with serverInfo name: "pagedock-mcp"');

            // Send notifications/initialized
            const initializedNotification = JSON.stringify({
              jsonrpc: '2.0',
              method: 'notifications/initialized',
            }) + '\n';
            child.stdin.write(initializedNotification);

            // Send tools/list request (id = 2)
            const listToolsRequest = JSON.stringify({
              jsonrpc: '2.0',
              id: 2,
              method: 'tools/list',
              params: {},
            }) + '\n';
            child.stdin.write(listToolsRequest);
          } catch (err) {
            clearTimeout(timeout);
            child.kill();
            return reject(err);
          }
        } else if (msg.id === 2) {
          try {
            assert.equal(msg.jsonrpc, '2.0', 'JSON-RPC version must be 2.0');
            assert(msg.result, 'tools/list response must contain result');
            assert(Array.isArray(msg.result.tools), 'result.tools must be an array');

            const toolNames = msg.result.tools.map((t) => t.name);
            const expectedTools = [
              'deploy_html',
              'list_sites',
              'get_site',
              'pull_site',
              'delete_site',
              'update_site_file',
              'list_versions',
              'rollback_site',
              'get_site_analytics',
              'list_form_submissions',
            ];

            for (const expected of expectedTools) {
              assert(
                toolNames.includes(expected),
                `Expected tool "${expected}" to be registered in tools/list response. Found: ${toolNames.join(', ')}`
              );
            }

            assert.equal(
              msg.result.tools.length,
              10,
              `Expected exactly 10 registered tools, got ${msg.result.tools.length}`
            );

            toolsReceived = true;
            console.log('PASS: All 10 tools present in tools/list response');

            // Close stdin to test clean exit
            child.stdin.end();
          } catch (err) {
            clearTimeout(timeout);
            child.kill();
            return reject(err);
          }
        }
      }
    });

    child.on('error', (err) => {
      clearTimeout(timeout);
      reject(err);
    });

    child.on('close', (code, signal) => {
      clearTimeout(timeout);
      try {
        assert.equal(initReceived, true, 'MCP initialize exchange must have completed');
        assert.equal(toolsReceived, true, 'MCP tools/list exchange must have completed');
        assert.equal(code, 0, `Expected clean exit with code 0, got code ${code} (signal: ${signal})`);
        assert(
          !stderrOutput.includes('UnhandledPromiseRejection') &&
          !stderrOutput.includes('unhandledRejection') &&
          !stderrOutput.includes('uncaughtException'),
          `Stderr contains unhandled errors: ${stderrOutput}`
        );
        console.log('PASS: Clean process exit on stdin close without errors');
        resolve();
      } catch (err) {
        if (stderrOutput) {
          console.error('Child process stderr:', stderrOutput);
        }
        reject(err);
      }
    });

    // Send standard MCP JSON-RPC 2.0 initialize request
    const initRequest = JSON.stringify({
      jsonrpc: '2.0',
      id: 1,
      method: 'initialize',
      params: {
        protocolVersion: '2024-11-05',
        capabilities: {},
        clientInfo: {
          name: 'test-client',
          version: '1.0.0',
        },
      },
    }) + '\n';

    child.stdin.write(initRequest);
  });
}

try {
  await testStdioProtocol();
  console.log('\nStdio Protocol Test Completed: 1 test passed, 0 failed');
  process.exit(0);
} catch (err) {
  console.error('\nFAIL: Stdio Protocol Test failed');
  console.error(err);
  process.exit(1);
}
