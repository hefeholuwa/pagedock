import { z } from 'zod';

/**
 * Register deployment tools on the PageDock MCP Server
 * @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server
 * @param {import('../client.js').PageDockClient} client
 */
export function registerDeployTools(server, client) {
  server.tool(
    'deploy_html',
    'Deploy a single-file HTML website or update an existing site on PageDock. Returns live preview URL and editor URL.',
    {
      html: z.string().describe('HTML content to deploy (must contain valid HTML markup)'),
      title: z.string().optional().describe('Human-friendly title for the site'),
      slug: z.string().optional().describe('Custom slug for the site (letters, numbers, hyphens)'),
      ttl: z.string().optional().describe('Expiry duration (e.g. "7d", "30d", "never")'),
    },
    async ({ html, title, slug, ttl }) => {
      try {
        const res = await client.deployHtml({ html, title, slug, ttl });
        const finalSlug = res.slug || slug;
        const liveUrl =
          res.liveUrl ||
          res.url ||
          (client.baseUrl ? `${client.baseUrl}/s/${finalSlug}/` : `/s/${finalSlug}/`);
        const editorUrl =
          res.editorUrl ||
          res.editor_url ||
          (client.baseUrl ? `${client.baseUrl}/editor/${finalSlug}` : `/editor/${finalSlug}`);
        const message =
          res.message ||
          `Site '${finalSlug}' deployed successfully and is live at ${liveUrl}`;

        return {
          content: [
            {
              type: 'text',
              text: JSON.stringify(
                {
                  success: true,
                  slug: finalSlug,
                  liveUrl,
                  editorUrl,
                  message,
                  ...(res.claim_token ? { claim_token: res.claim_token } : {}),
                  ...(res.version !== undefined ? { version: res.version } : {}),
                },
                null,
                2
              ),
            },
          ],
        };
      } catch (error) {
        return {
          isError: true,
          content: [
            {
              type: 'text',
              text: `Failed to deploy HTML: ${error.message || String(error)}`,
            },
          ],
        };
      }
    }
  );
}

export default registerDeployTools;
