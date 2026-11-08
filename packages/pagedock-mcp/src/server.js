import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { PageDockClient } from './client.js';
import { registerDeployTools } from './tools/deploy.js';
import { registerSiteTools } from './tools/sites.js';
import { registerPatchTools } from './tools/patch.js';
import { registerVersionTools } from './tools/versions.js';
import { registerAnalyticsTools } from './tools/analytics.js';
import { registerFormTools } from './tools/forms.js';

/**
 * Creates and initializes a PageDock McpServer instance
 * @param {Object} [options]
 * @param {PageDockClient} [options.client] - Custom or mocked PageDockClient
 * @param {string} [options.baseUrl] - PageDock REST API base URL
 * @param {string} [options.apiToken] - Bearer authentication token
 * @param {string} [options.claimToken] - Guest site claim token
 * @param {Object} [options.serverInfo] - Name and version overrides
 * @returns {McpServer}
 */
export function createMcpServer(options = {}) {
  const isClientInstance = Boolean(options && (typeof options.deployHtml === 'function' || options instanceof PageDockClient));
  const client = isClientInstance ? options : (options.client || new PageDockClient(options));
  const serverInfo = isClientInstance ? {} : (options.serverInfo || {});
  const server = new McpServer({
    name: 'pagedock-mcp',
    version: '0.1.0',
    ...serverInfo,
  });

  server.client = client;

  // Register deployment tools (deploy_html)
  registerDeployTools(server, client);

  // Register site management tools (list_sites, get_site, pull_site, delete_site)
  registerSiteTools(server, client);

  // Register patching tools (update_site_file)
  registerPatchTools(server, client);

  // Register version and rollback tools (list_versions, rollback_site)
  registerVersionTools(server, client);

  // Register analytics and insight tools (get_site_analytics)
  registerAnalyticsTools(server, client);

  // Register form lead capture tools (list_form_submissions)
  registerFormTools(server, client);

  return server;
}

export const server = createMcpServer();
export default server;
export {
  registerDeployTools,
  registerSiteTools,
  registerPatchTools,
  registerVersionTools,
  registerAnalyticsTools,
  registerFormTools,
};

