#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { PageDockClient } from './client.js';
import { createMcpServer } from './server.js';

// Environment configuration
const baseUrl = process.env.PAGEDOCK_BASE_URL || 'http://localhost:8080';
const apiToken = process.env.PAGEDOCK_API_TOKEN || null;
const claimToken = process.env.PAGEDOCK_CLAIM_TOKEN || null;

// Initialize client & MCP server
const client = new PageDockClient({
  baseUrl,
  apiToken,
  claimToken,
});

const server = createMcpServer(client);
const transport = new StdioServerTransport();

async function main() {
  await server.connect(transport);
}

// Clean signal handling
let isShuttingDown = false;
const shutdown = async () => {
  if (isShuttingDown) return;
  isShuttingDown = true;
  try {
    await server.close();
  } catch {
    // Ignore clean close errors during termination
  }
  process.exit(0);
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

// Unhandled error handlers
process.on('unhandledRejection', (reason) => {
  console.error('[pagedock-mcp] Unhandled Rejection:', reason);
  process.exit(1);
});

process.on('uncaughtException', (err) => {
  console.error('[pagedock-mcp] Uncaught Exception:', err);
  process.exit(1);
});

// Run server
main().catch((err) => {
  console.error('[pagedock-mcp] Fatal error starting server:', err);
  process.exit(1);
});
